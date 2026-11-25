<?php
declare(strict_types=1);

namespace Groza\Admin;

/**
 * Пересохраняет фото средствами GD: EXIF (в том числе геометки) не переносится, дописанный в файл код пропадает,
 * поворот по EXIF Orientation применяется, на выходе WebP + JPEG нескольких ширин (без увеличения).
 */
final class ImageProcessor
{
    private const WEBP_QUALITY = 80;
    private const JPEG_QUALITY = 82;

    private const MEMORY_RESERVE = 33554432; // 32 МБ на сам PHP и копии под ширины
    private const BYTES_PER_PIXEL = 5;        // раскодированная картинка + копия при масштабировании
    private const BYTES_PER_PIXEL_ROTATE = 9; // imagerotate держит рядом ещё одну полноразмерную копию
    private const RAISED_MEMORY = 268435456;  // 256 МБ
    private const TOO_LITTLE_MEMORY = 'Серверу не хватает памяти для обработки фото. Сообщите разработчику.';
    private const REQUIRED = ['imagewebp', 'imagecreatefromjpeg', 'imagecreatefrompng', 'finfo_open'];

    /** @var \Closure(string):bool */
    private \Closure $hasFunction;
    /** @var \Closure(int):int получает текущий лимит, пробует поднять его и возвращает лимит после попытки */
    private \Closure $raiseMemory;

    /**
     * @param int|null $memoryLimit байты (-1 — без лимита); null — читать memory_limit в момент обработки (для тестов — подставить)
     * @param callable|null $hasFunction проверка наличия функций расширений (для тестов)
     * @param callable|null $raiseMemory попытка поднять memory_limit до 256 МБ; по умолчанию — ini_set (только когда лимит не подставлен)
     */
    public function __construct(private int $maxPixels = 25000000, private ?int $memoryLimit = null, ?callable $hasFunction = null, ?callable $raiseMemory = null)
    {
        $this->raiseMemory = $raiseMemory !== null ? \Closure::fromCallable($raiseMemory) : ($memoryLimit === null
            ? static function (int $current): int {
                @ini_set('memory_limit', '256M');

                return \Groza\App::iniBytes((string) ini_get('memory_limit'));
            }
            : static fn (int $current): int => $current);
        $this->hasFunction = $hasFunction !== null ? \Closure::fromCallable($hasFunction) : static fn (string $f): bool => function_exists($f);
    }

    /** Нужные расширения PHP есть? Иначе — понятный отказ вместо ошибки 500. */
    public function assertCapable(): void
    {
        foreach (self::REQUIRED as $fn) {
            if (!($this->hasFunction)($fn)) {
                throw new UploadException('Сервер не умеет обрабатывать фото. Сообщите разработчику.');
            }
        }
    }

    /**
     * Сколько пикселей можно раскодировать: не больше maxPixels и не больше, чем влезает в память.
     * ≈5 байт на пиксель, а при повороте по EXIF (imagerotate) — ≈9; плюс запас. Если лимит памяти ниже 256 МБ — сначала пробуем его поднять.
     */
    public function effectiveMaxPixels(bool $rotates = false): int
    {
        $limit = $this->memoryBudget();
        if ($limit <= 0) {
            return $this->maxPixels;
        }
        $bpp = $rotates ? self::BYTES_PER_PIXEL_ROTATE : self::BYTES_PER_PIXEL;

        return max(0, min($this->maxPixels, intdiv($limit - self::MEMORY_RESERVE, $bpp)));
    }

    /** Лимит памяти в байтах после попытки поднять его до 256 МБ; 0 — без лимита. */
    private function memoryBudget(): int
    {
        $limit = $this->memoryLimit ?? \Groza\App::iniBytes((string) ini_get('memory_limit'));
        if ($limit <= 0) { // -1 — без лимита
            return 0;
        }
        if ($limit < self::RAISED_MEMORY) {
            $limit = max($limit, ($this->raiseMemory)($limit));
        }

        return max(0, $limit);
    }

    /**
     * Оценка пика памяти по размерам (измерено на GD этой сборки): раскодирование JPEG ≈ 6 Б/пикс, PNG/WebP ≈ 10; поворот ≈ 9;
     * кодирование самой большой ширины — к снимку в памяти добавляется уменьшенная копия и буфер кодировщика.
     *
     * @param list<int> $widths
     */
    private function estimatePeak(int $w, int $h, bool $alpha, bool $rotates, array $widths): int
    {
        $n = $w * $h;
        $sizes = $this->sizes($w, $widths);
        $tw = max($sizes);
        $out = $tw * max(1, (int) round($h * $tw / $w));
        $same = $tw === $w;
        $encode = $alpha
            ? 6 * $n + ($same ? 10 * $n : (int) (9.5 * $out))
            : 6 * $n + ($same ? (int) (4.5 * $n) : (int) (8.5 * $out));

        return max($alpha ? 10 * $n : 6 * $n, $rotates ? 9 * $n : 0, $encode);
    }

    /**
     * Точная проверка по оценке пика (форма снимка важна: узкий и длинный выходит в полный размер).
     * Не влезает — понятный отказ, а не фатальная ошибка памяти.
     *
     * @param list<int> $widths
     */
    private function assertFitsMemory(int $w, int $h, int $type, int $orientation, array $widths): void
    {
        $limit = $this->memoryBudget();
        if ($limit <= 0) {
            return;
        }
        if ($orientation >= 5) {
            [$w, $h] = [$h, $w];
        }
        $alpha = $type !== IMAGETYPE_JPEG;
        $rotates = in_array($orientation, [3, 5, 6, 7, 8], true);
        $fits = fn (int $a, int $b): bool => $this->estimatePeak($a, $b, $alpha, $rotates, $widths) + self::MEMORY_RESERVE <= $limit;
        if ($fits($w, $h)) {
            return;
        }
        $lo = 0.0; // наибольшая доля сторон, которая ещё влезает (форма снимка сохраняется)
        $hi = 1.0;
        for ($i = 0; $i < 24; $i++) {
            $mid = ($lo + $hi) / 2;
            if ($fits(max(1, (int) floor($w * $mid)), max(1, (int) floor($h * $mid)))) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }
        $mp = intdiv(max(1, (int) floor($w * $lo)) * max(1, (int) floor($h * $lo)), 1000000);
        throw new UploadException($mp < 1 ? self::TOO_LITTLE_MEMORY : 'Фото слишком большое: уменьшите до ' . $mp . ' мегапикселей');
    }

    /**
     * @param list<int> $widths
     * @return array{src:string,widths:list<int>,formats:list<string>,w:int,h:int} src — это $base (без каталога и расширения)
     */
    public function process(string $path, string $destDir, string $base, array $widths = [480, 960, 1600]): array
    {
        $info = is_file($path) ? @getimagesize($path) : false;
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw new UploadException('Файл не похож на фото. Подходят JPEG, PNG и WebP');
        }
        [$srcW, $srcH, $type] = $info;
        $this->assertCapable();
        // размер проверяем по заголовку, до декодирования: огромная картинка — это «бомба» для памяти
        // поворот по EXIF (3, 5–8) требует второй полноразмерной копии — бюджет памяти считаем с учётом этого
        $orientation = $type === IMAGETYPE_JPEG ? $this->orientation($path) : 1;
        $maxPixels = $this->effectiveMaxPixels(in_array($orientation, [3, 5, 6, 7, 8], true));
        if ($maxPixels < 1) { // памяти не хватает даже на запас PHP: «уменьшите до 1 Мп» было бы неправдой
            throw new UploadException(self::TOO_LITTLE_MEMORY);
        }
        if ($srcW * $srcH > $maxPixels) {
            throw new UploadException('Фото слишком большое: уменьшите до ' . max(1, intdiv($maxPixels, 1000000)) . ' мегапикселей');
        }
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new UploadException('Файл не похож на фото. Подходят JPEG, PNG и WebP');
        }
        $this->assertFitsMemory($srcW, $srcH, $type, $orientation, $widths);

        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => @imagecreatefromwebp($path),
        };
        if ($img === false) {
            throw new UploadException('Не удалось прочитать фото. Откройте его и сохраните заново как JPEG');
        }
        // Варианты строятся во временной папке и переносятся на место только когда готовы все: если PHP оборвётся
        // по памяти (фатальная ошибка, finally не выполняется), в папке загрузок не останется «половины» фото под настоящими именами.
        $tmpDir = $destDir . '/.tmp-' . $base;
        $made = [];
        $moved = [];
        try {
            if ($type === IMAGETYPE_JPEG) {
                $this->orient($img, $orientation);
            }
            if (!imageistruecolor($img)) {
                imagepalettetotruecolor($img);
            }
            $w = imagesx($img);
            $h = imagesy($img);
            $hasAlpha = $type !== IMAGETYPE_JPEG; // у JPEG прозрачности не бывает
            $sizes = $this->sizes($w, $widths);
            if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                throw new UploadException('Не удалось сохранить фото. Попробуйте ещё раз');
            }
            $this->removeDir($tmpDir);
            if (!@mkdir($tmpDir, 0755) && !is_dir($tmpDir)) {
                throw new UploadException('Не удалось сохранить фото. Попробуйте ещё раз');
            }
            $out = ['w' => 0, 'h' => 0];
            foreach ($sizes as $tw) {
                $th = max(1, (int) round($h * $tw / $w));
                // Память: лишних полноразмерных копий не держим. Той же ширины, что у исходника, — копия не нужна вовсе;
                // у JPEG прозрачности нет, значит одна уменьшенная картинка годится и для WebP, и для JPEG;
                // с прозрачностью (PNG/WebP) промежуточная картинка для WebP освобождается до создания плоской копии для JPEG.
                $scaled = $tw === $w && $th === $h ? $img : $this->scale($img, $w, $h, $tw, $th);
                $made[] = $webp = "$tmpDir/$base-$tw.webp";
                $okWebp = @imagewebp($scaled, $webp, self::WEBP_QUALITY);
                if ($hasAlpha) {
                    unset($scaled);
                    $flat = $this->flatten($img, $w, $h, $tw, $th);
                } else {
                    $flat = $scaled;
                    unset($scaled);
                }
                imageinterlace($flat, true); // progressive
                $made[] = $jpg = "$tmpDir/$base-$tw.jpg";
                $okJpg = @imagejpeg($flat, $jpg, self::JPEG_QUALITY);
                unset($flat);
                if (!$okWebp || !$okJpg) {
                    throw new UploadException('Не удалось сохранить фото. Попробуйте ещё раз');
                }
                @chmod($webp, 0644);
                @chmod($jpg, 0644);
                $out = ['w' => $tw, 'h' => $th];
            }
            unset($img); // перед переносом память уже не нужна
            foreach ($made as $file) {
                $to = $destDir . '/' . basename($file);
                if (!@rename($file, $to)) {
                    throw new UploadException('Не удалось сохранить фото. Попробуйте ещё раз');
                }
                $moved[] = $to;
            }
        } catch (\Throwable $e) {
            foreach ([...$made, ...$moved] as $f) {
                @unlink($f);
            }
            throw $e;
        } finally {
            unset($img);
            $this->removeDir($tmpDir);
        }

        return ['src' => $base, 'widths' => $sizes, 'formats' => ['webp', 'jpg'], 'w' => $out['w'], 'h' => $out['h']];
    }

    /** Удаляет временную папку вместе с файлами первого уровня (вложенных в ней не бывает). */
    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $name) {
            if (is_file($dir . '/' . $name)) {
                @unlink($dir . '/' . $name);
            }
        }
        @rmdir($dir);
    }

    /** Ширины строго меньше исходной, плюс сама исходная (но не больше максимальной из списка): 400 px → [400]. @param list<int> $widths @return list<int> */
    private function sizes(int $srcW, array $widths): array
    {
        $widths = array_values(array_unique(array_filter(array_map('intval', $widths), static fn (int $w): bool => $w > 0)));
        sort($widths);
        $sizes = array_values(array_filter($widths, static fn (int $w): bool => $w < $srcW));
        $top = min($srcW, $widths === [] ? $srcW : max($widths));
        if (!in_array($top, $sizes, true)) {
            $sizes[] = $top;
        }
        sort($sizes);

        return $sizes;
    }

    private function orientation(string $path): int
    {
        if (!function_exists('exif_read_data')) {
            return 1;
        }
        $exif = @exif_read_data($path, 'IFD0');
        $o = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        return $o >= 1 && $o <= 8 ? $o : 1;
    }

    /** Поворачивает на месте: исходная картинка освобождается сразу после успешного поворота. */
    private function orient(\GdImage &$img, int $o): void
    {
        $ops = match ($o) {
            2 => [['flip', IMG_FLIP_HORIZONTAL]],
            3 => [['rot', 180]],
            4 => [['flip', IMG_FLIP_VERTICAL]],
            5 => [['rot', 90], ['flip', IMG_FLIP_VERTICAL]],
            6 => [['rot', -90]],
            7 => [['rot', -90], ['flip', IMG_FLIP_VERTICAL]],
            8 => [['rot', 90]],
            default => [],
        };
        foreach ($ops as [$op, $arg]) {
            if ($op === 'flip') {
                imageflip($img, $arg);
            } else {
                $rotated = @imagerotate($img, $arg, 0);
                if ($rotated === false) {
                    throw new UploadException('Не удалось обработать фото. Попробуйте сохранить его заново как JPEG');
                }
                $img = $rotated; // прежнее изображение освобождается здесь
                unset($rotated);
            }
        }
    }

    private function scale(\GdImage $img, int $w, int $h, int $tw, int $th): \GdImage
    {
        $dst = @imagecreatetruecolor($tw, $th);
        if ($dst === false) {
            throw new UploadException('Не удалось обработать фото. Попробуйте уменьшить его');
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, (int) imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $tw, $th, $w, $h);

        return $dst;
    }

    /** Прозрачность для JPEG невозможна: белый фон, и уменьшение сразу на него — одна копия вместо двух. */
    private function flatten(\GdImage $img, int $w, int $h, int $tw, int $th): \GdImage
    {
        $flat = @imagecreatetruecolor($tw, $th);
        if ($flat === false) {
            throw new UploadException('Не удалось обработать фото. Попробуйте уменьшить его');
        }
        imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
        imagealphablending($flat, true);
        imagecopyresampled($flat, $img, 0, 0, 0, 0, $tw, $th, $w, $h);

        return $flat;
    }
}

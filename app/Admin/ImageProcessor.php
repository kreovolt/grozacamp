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

    public function __construct(private int $maxPixels = 25000000)
    {
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
        // размер проверяем по заголовку, до декодирования: огромная картинка — это «бомба» для памяти
        if ($srcW * $srcH > $this->maxPixels) {
            throw new UploadException('Фото слишком большое: уменьшите до 25 мегапикселей');
        }
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new UploadException('Файл не похож на фото. Подходят JPEG, PNG и WebP');
        }
        $this->ensureMemory($srcW, $srcH);

        $img = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => @imagecreatefromwebp($path),
        };
        if ($img === false) {
            throw new UploadException('Не удалось прочитать фото. Откройте его и сохраните заново как JPEG');
        }
        $made = [];
        try {
            if ($type === IMAGETYPE_JPEG) {
                $img = $this->orient($img, $this->orientation($path));
            }
            if (!imageistruecolor($img)) {
                imagepalettetotruecolor($img);
            }
            $w = imagesx($img);
            $h = imagesy($img);
            $sizes = $this->sizes($w, $widths);
            if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                throw new UploadException('Не удалось сохранить фото. Попробуйте ещё раз');
            }
            $out = ['w' => 0, 'h' => 0];
            foreach ($sizes as $tw) {
                $th = max(1, (int) round($h * $tw / $w));
                $scaled = $this->scale($img, $w, $h, $tw, $th);
                try {
                    $made[] = $webp = "$destDir/$base-$tw.webp";
                    $okWebp = imagewebp($scaled, $webp, self::WEBP_QUALITY);
                    $flat = $this->flatten($scaled, $tw, $th);
                    try {
                        imageinterlace($flat, true); // progressive
                        $made[] = $jpg = "$destDir/$base-$tw.jpg";
                        $okJpg = imagejpeg($flat, $jpg, self::JPEG_QUALITY);
                    } finally {
                        unset($flat);
                    }
                } finally {
                    unset($scaled);
                }
                if (!$okWebp || !$okJpg) {
                    throw new UploadException('Не удалось сохранить фото. Попробуйте ещё раз');
                }
                @chmod($webp, 0644);
                @chmod($jpg, 0644);
                $out = ['w' => $tw, 'h' => $th];
            }
        } catch (\Throwable $e) {
            foreach ($made as $f) {
                @unlink($f);
            }
            throw $e;
        } finally {
            unset($img);
        }

        return ['src' => $base, 'widths' => $sizes, 'formats' => ['webp', 'jpg'], 'w' => $out['w'], 'h' => $out['h']];
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

    private function orient(\GdImage $img, int $o): \GdImage
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
                $rotated = imagerotate($img, $arg, 0);
                if ($rotated === false) {
                    throw new UploadException('Не удалось обработать фото. Попробуйте сохранить его заново как JPEG');
                }
                $img = $rotated;
            }
        }

        return $img;
    }

    private function scale(\GdImage $img, int $w, int $h, int $tw, int $th): \GdImage
    {
        $dst = imagecreatetruecolor($tw, $th);
        if ($dst === false) {
            throw new UploadException('Не удалось обработать фото. Попробуйте уменьшить его');
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, (int) imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $tw, $th, $w, $h);

        return $dst;
    }

    /** Прозрачность для JPEG невозможна: подкладываем белый фон. */
    private function flatten(\GdImage $img, int $w, int $h): \GdImage
    {
        $flat = imagecreatetruecolor($w, $h);
        if ($flat === false) {
            throw new UploadException('Не удалось обработать фото. Попробуйте уменьшить его');
        }
        imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
        imagealphablending($flat, true);
        imagecopy($flat, $img, 0, 0, 0, 0, $w, $h);

        return $flat;
    }

    /** Раскодированная картинка занимает ~4 байта на пиксель (и ещё копии); запас под это, а если не вышло — понятный отказ вместо падения. */
    private function ensureMemory(int $w, int $h): void
    {
        $limit = self::bytes((string) ini_get('memory_limit'));
        if ($limit <= 0) {
            return;
        }
        $need = (int) ($w * $h * 4 * 3) + 32 * 1048576;
        if ($need <= $limit - memory_get_usage()) {
            return;
        }
        if (@ini_set('memory_limit', (string) ($memory = memory_get_usage() + $need)) === false || self::bytes((string) ini_get('memory_limit')) < $memory) {
            throw new UploadException('Фото слишком большое для обработки: уменьшите его и попробуйте снова');
        }
    }

    private static function bytes(string $v): int
    {
        return \Groza\App::iniBytes($v);
    }
}

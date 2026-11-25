<?php
declare(strict_types=1);

namespace Groza\Admin;

/**
 * Приём загруженных файлов: фото (JPEG/PNG/WebP, пересохраняются) и PDF (как есть, после проверки содержимого).
 * Тип определяется по содержимому, а не по имени и не по MIME от браузера. Имена на диске — случайные.
 */
final class Uploads
{
    private const IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp'];
    private const IMAGE_MIME = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    private const NOT_PHOTO = 'Файл не похож на фото. Подходят JPEG, PNG и WebP';

    /** @var \Closure(string):bool */
    private \Closure $isUploaded;
    /** @var \Closure(string,string):bool */
    private \Closure $move;

    /**
     * $isUploaded / $move — подмена для тестов (файлы там не приходят по HTTP); в работе всегда is_uploaded_file / move_uploaded_file.
     */
    public function __construct(
        private string $baseDir,
        private string $baseUrl,
        private ImageProcessor $images,
        private int $maxImageBytes = 10485760,
        private int $maxPdfBytes = 20971520,
        ?callable $isUploaded = null,
        ?callable $move = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->isUploaded = $isUploaded !== null ? \Closure::fromCallable($isUploaded) : static fn (string $f): bool => is_uploaded_file($f);
        $this->move = $move !== null ? \Closure::fromCallable($move) : static fn (string $a, string $b): bool => move_uploaded_file($a, $b);
    }

    /** @param array<string,mixed> $file запись из $_FILES @return array{src:string,widths:list<int>,formats:list<string>,w:int,h:int} */
    public function storeImage(array $file): array
    {
        $this->images->assertCapable();
        $tmp = $this->checkUpload($file);
        $size = $this->actualSize($tmp);
        if ($size > $this->maxImageBytes) {
            throw new UploadException('Фото больше ' . $this->mb($this->maxImageBytes) . ' МБ');
        }
        $head = $this->head($tmp);
        if ($this->isHeic($head)) {
            throw new UploadException('Фото в формате HEIC (так снимает iPhone) пока не подходит. Сохраните его как JPEG: на iPhone — Настройки → Камера → Форматы → «Наиболее совместимый»; или отправьте себе фото через мессенджер и загрузите полученный файл');
        }
        $kind = $this->imageKind($head);
        $mime = $this->mime($tmp);
        if ($kind === null || $mime !== self::IMAGE_MIME[$kind]) {
            throw new UploadException(self::NOT_PHOTO);
        }
        if (!in_array($this->extension((string) ($file['name'] ?? '')), self::IMAGE_EXT, true)) {
            throw new UploadException(self::NOT_PHOTO);
        }

        $dir = $this->baseDir . '/img';
        $name = bin2hex(random_bytes(8));
        $d = $this->images->process($tmp, $dir, $name);
        $d['src'] = $this->baseUrl . '/img/' . $name;

        return $d;
    }

    /** @param array<string,mixed> $file @return array{url:string,bytes:int,originalName:string} */
    public function storePdf(array $file): array
    {
        if (!function_exists('finfo_open')) {
            throw new UploadException('Сервер не умеет проверять файлы. Сообщите разработчику.');
        }
        $tmp = $this->checkUpload($file);
        $size = $this->actualSize($tmp);
        if ($size > $this->maxPdfBytes) {
            throw new UploadException('Файл больше ' . $this->mb($this->maxPdfBytes) . ' МБ');
        }
        if (!str_starts_with($this->head($tmp), '%PDF-') || $this->mime($tmp) !== 'application/pdf'
            || $this->extension((string) ($file['name'] ?? '')) !== 'pdf') {
            throw new UploadException('Это не PDF. Загрузите файл с расширением .pdf');
        }
        $dir = $this->baseDir . '/docs';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new UploadException('Не удалось сохранить файл. Попробуйте ещё раз');
        }
        $name = bin2hex(random_bytes(8)) . '.pdf';
        $dest = $dir . '/' . $name;
        if (!($this->move)($tmp, $dest)) {
            throw new UploadException('Не удалось сохранить файл. Попробуйте ещё раз');
        }
        @chmod($dest, 0644);

        return ['url' => $this->baseUrl . '/docs/' . $name, 'bytes' => (int) filesize($dest), 'originalName' => $this->cleanName((string) ($file['name'] ?? ''))];
    }

    /**
     * Удаляет файл по адресу вида /uploads/docs/x.pdf; для фото — адрес-основу из дескриптора (/uploads/img/{id}): уходят все ширины и форматы.
     * Всё, что ведёт за пределы папки загрузок, отклоняется.
     */
    public function delete(string $url): void
    {
        $refuse = new UploadException('Нельзя удалить этот файл');
        $prefix = $this->baseUrl . '/';
        if (!str_starts_with($url, $prefix) || preg_match('/[\x00-\x1F\x7F\\\\]/', $url) === 1) {
            throw $refuse;
        }
        $rel = substr($url, strlen($prefix));
        $parts = explode('/', $rel);
        if (count($parts) < 2 || !in_array($parts[0], ['img', 'docs'], true)) {
            throw $refuse;
        }
        foreach ($parts as $p) {
            if ($p === '' || $p === '.' || $p === '..' || preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/', $p) !== 1) {
                throw $refuse;
            }
        }
        $root = realpath($this->baseDir);
        if ($root === false) {
            return; // загрузок ещё не было
        }
        $dirReal = realpath($root . '/' . implode('/', array_slice($parts, 0, -1)));
        if ($dirReal === false) {
            return;
        }
        $this->mustBeInside($root, $dirReal, $refuse);
        $leaf = end($parts);
        $targets = [];
        if (!str_contains($leaf, '.') && $parts[0] === 'img') {
            foreach (glob($dirReal . '/' . $leaf . '-*') ?: [] as $f) {
                if (preg_match('/-\d{1,5}\.(webp|jpg)$/', $f) === 1) {
                    $targets[] = $f;
                }
            }
        } elseif (file_exists($dirReal . '/' . $leaf)) {
            $targets[] = $dirReal . '/' . $leaf;
        }
        foreach ($targets as $t) {
            $real = realpath($t);
            if ($real === false || is_link($t)) {
                throw $refuse;
            }
            $this->mustBeInside($root, $real, $refuse);
            if (is_file($real)) {
                @unlink($real);
            }
        }
    }

    /**
     * Адреса загруженных файлов, упомянутые в текстах данных (текущих и хранимых версий): по ним решают, что ещё нужно.
     * Фото — адрес-основа /uploads/img/{id}, документ — его адрес.
     *
     * @param iterable<string> $raws
     * @return array<string,true>
     */
    public function referencedIn(iterable $raws): array
    {
        $re = '#' . preg_quote($this->baseUrl, '#') . '/(img|docs)/([A-Za-z0-9_-][A-Za-z0-9._-]*)#';
        $refs = [];
        foreach ($raws as $raw) {
            if (preg_match_all($re, str_replace('\\/', '/', $raw), $m, PREG_SET_ORDER) > 0) {
                foreach ($m as $hit) {
                    $refs[$this->key($hit[1], $hit[2])] = true;
                }
            }
        }

        return $refs;
    }

    /** Нужен ли ещё файл по этому адресу (в `$refs` из referencedIn). @param array<string,true> $refs */
    public function isReferenced(string $url, array $refs): bool
    {
        $re = '#^' . preg_quote($this->baseUrl, '#') . '/(img|docs)/(.+)$#';

        return preg_match($re, $url, $m) !== 1 || isset($refs[$this->key($m[1], $m[2])]);
    }

    /**
     * Файлы в папке загрузок, на которые нигде нет ссылок. Файлы моложе `$minAge` секунд не трогаем:
     * их мог только что загрузить чей-то запрос, который ещё не успел записать данные.
     *
     * @param array<string,true> $refs
     * @return list<string> адреса (для фото — адрес-основа)
     */
    public function orphans(array $refs, int $minAge = 0): array
    {
        $found = []; // адрес → время изменения самого свежего файла
        foreach (glob($this->baseDir . '/img/*') ?: [] as $file) {
            if (is_file($file) && !is_link($file) && preg_match('/^([A-Za-z0-9_][A-Za-z0-9_-]*)-\d{1,5}\.(webp|jpg)$/', basename($file), $m) === 1) {
                $url = $this->key('img', $m[1]);
                $found[$url] = max($found[$url] ?? 0, (int) filemtime($file));
            }
        }
        foreach (glob($this->baseDir . '/docs/*') ?: [] as $file) {
            if (is_file($file) && !is_link($file) && preg_match('/^[A-Za-z0-9_][A-Za-z0-9._-]*$/', basename($file)) === 1) {
                $found[$this->key('docs', basename($file))] = (int) filemtime($file);
            }
        }
        $now = time();
        $orphans = array_values(array_filter(
            array_keys($found),
            static fn (string $url): bool => !isset($refs[$url]) && $now - $found[$url] >= $minAge
        ));
        sort($orphans, SORT_STRING);

        return $orphans;
    }

    private function key(string $kind, string $name): string
    {
        $name = rtrim($name, '.');
        if ($kind === 'img') {
            $name = (string) preg_replace('/-\d{1,5}\.(webp|jpg)$/', '', $name);
        }

        return $this->baseUrl . '/' . $kind . '/' . $name;
    }

    private function mustBeInside(string $root, string $real, UploadException $e): void
    {
        $r = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $p = str_replace('\\', '/', $real) . '/';
        if (!str_starts_with($p, $r)) {
            throw $e;
        }
    }

    /** Код ошибки загрузки и то, что файл действительно пришёл по HTTP. @param array<string,mixed> $file */
    private function checkUpload(array $file): string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        switch ($error) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new UploadException('Файл слишком большой для сервера. Уменьшите фото (до 10 МБ) или PDF (до 20 МБ) и попробуйте снова');
            case UPLOAD_ERR_PARTIAL:
                throw new UploadException('Файл не загрузился полностью (возможно, пропал интернет). Попробуйте ещё раз');
            case UPLOAD_ERR_NO_FILE:
                throw new UploadException('Файл не выбран');
            default:
                throw new UploadException('Не удалось загрузить файл. Попробуйте ещё раз');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || str_contains($tmp, "\0") || !($this->isUploaded)($tmp) || !is_file($tmp)) {
            throw new UploadException('Не удалось загрузить файл. Попробуйте ещё раз');
        }

        return $tmp;
    }

    /** Размер — по самому файлу, а не по числу из запроса. */
    private function actualSize(string $path): int
    {
        clearstatcache(true, $path);
        $s = filesize($path);
        if ($s === false || $s === 0) {
            throw new UploadException('Файл пустой. Выберите другой файл');
        }

        return $s;
    }

    private function head(string $path): string
    {
        $h = @fopen($path, 'rb');
        if ($h === false) {
            throw new UploadException('Не удалось загрузить файл. Попробуйте ещё раз');
        }
        $b = (string) fread($h, 32);
        fclose($h);

        return $b;
    }

    private function mime(string $path): string
    {
        $f = new \finfo(FILEINFO_MIME_TYPE);

        return (string) $f->file($path);
    }

    private function isHeic(string $head): bool
    {
        return strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp'
            && in_array(substr($head, 8, 4), ['heic', 'heix', 'hevc', 'hevx', 'mif1', 'msf1'], true);
    }

    private function imageKind(string $head): ?string
    {
        return match (true) {
            str_starts_with($head, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($head, "\x89PNG") => 'png',
            strlen($head) >= 12 && str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'webp',
            default => null,
        };
    }

    private function extension(string $name): string
    {
        // из «сырого» имени, без перекодировки и подстановок: проверка байтовая, кодировка имени не важна
        $name = str_replace('\\', '/', $name);
        $name = trim(substr($name, (int) strrpos('/' . $name, '/')));
        $dot = strrpos($name, '.');

        return $dot === false ? '' : strtolower(substr($name, $dot + 1));
    }

    /** Имя для показа: без пути и управляющих символов, кириллица сохраняется, до 120 знаков. */
    private function cleanName(string $name): string
    {
        $name = mb_scrub($name); // не-UTF-8 байты заменяются, иначе preg_* с /u вернёт null
        $name = str_replace('\\', '/', $name);
        $name = (string) substr($name, (int) strrpos('/' . $name, '/'));
        $name = (string) preg_replace('/[\p{C}]+/u', '', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        if (mb_strlen($name) > 120) {
            $dot = mb_strrpos($name, '.');
            $ext = $dot !== false && mb_strlen($name) - $dot <= 10 ? mb_substr($name, $dot) : '';
            $name = mb_substr($name, 0, 120 - mb_strlen($ext)) . $ext;
        }

        return $name !== '' ? $name : 'document.pdf';
    }

    private function mb(int $bytes): int
    {
        return max(1, intdiv($bytes, 1048576));
    }
}

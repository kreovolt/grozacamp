<?php
declare(strict_types=1);
/**
 * Маленькая картинка для списка в форме: ключ из библиотеки сайта или описание загруженного фото. Нет картинки — ничего.
 * @var mixed $value
 * @var string $class
 */
use function Groza\View\e;
use function Groza\View\image;

try {
    $d = is_string($value) || is_array($value) ? image($value) : null;
} catch (\InvalidArgumentException) {
    $d = null;
}
if ($d !== null && !empty($d['src']) && !empty($d['widths']) && !empty($d['formats'])):
    $ext = in_array('jpg', $d['formats'], true) ? 'jpg' : $d['formats'][0];
?>
<img class="<?= e($class ?? 'current__img') ?>" src="<?= e($d['src'] . '-' . min($d['widths']) . '.' . $ext) ?>" alt="" width="96" height="96">
<?php endif ?>

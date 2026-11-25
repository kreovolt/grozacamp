<?php
declare(strict_types=1);
/**
 * Ссылка на запись другого списка: выпадающий список с названиями.
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value
 * @var string $aria
 * @var array<string,string> $refOptions
 */
use function Groza\View\e;

$current = is_scalar($value) ? (string) $value : '';
echo $this->partial('admin/fields/_label', ['f' => $f, 'id' => $id]);
?>
<select id="<?= e($id) ?>" name="<?= e((string) $f['name']) ?>" <?= $aria ?>>
  <option value="">— не выбрано —</option>
<?php foreach ($refOptions as $key => $label): ?>
  <option value="<?= e((string) $key) ?>"<?= $current === (string) $key ? ' selected' : '' ?>><?= e((string) $label) ?></option>
<?php endforeach ?>
</select>

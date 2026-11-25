<?php
/**
 * Галерея фотографий: текущие (подпись, «Убрать», ↑ ↓) и места для новых. Порядок — порядок строк на странице;
 * кнопки ↑ ↓ включает admin.js. Готовые описания фото из формы не принимаются: сервер делает их сам.
 * @var array<string,mixed> $f
 * @var string $id
 * @var mixed $value {rows: список строк, new: [номер => подпись]}
 * @var string|null $error
 * @var string|null $altError
 */
use function Groza\View\e;

$name = (string) $f['name'];
$rows = is_array($value) && is_array($value['rows'] ?? null) ? $value['rows'] : [];
$new = is_array($value) && is_array($value['new'] ?? null) && $value['new'] !== [] ? $value['new'] : [1 => ''];
?>
<fieldset class="gallery" data-gallery <?= $aria ?>>
  <legend><?= e((string) $f['label']) ?></legend>
<?php if ($altError !== null): ?>
  <p class="field-error" id="<?= e($id) ?>-alt-error"><?= e($altError) ?></p>
<?php endif ?>
<?php if ($rows === []): ?>
  <p class="hint">Фотографий пока нет.</p>
<?php endif ?>
  <ul class="gallery__list" data-gallery-list>
<?php foreach ($rows as $row): $t = (string) $row['token']; $own = is_array($row['value']); ?>
    <li class="gallery__item<?= !empty($row['del']) ? ' is-removed' : '' ?>" data-gallery-item>
      <input type="hidden" name="<?= e($name) ?>__old[]" value="<?= e($t) ?>">
      <?= $this->partial('admin/fields/_thumb', ['value' => $row['value'], 'class' => 'gallery__thumb']) ?>
      <div class="gallery__body">
<?php if ($own): ?>
        <label for="<?= e($id) ?>__alt_<?= e($t) ?>">Что на фото?</label>
        <input type="text" id="<?= e($id) ?>__alt_<?= e($t) ?>" name="<?= e($name) ?>__alt_<?= e($t) ?>" value="<?= e((string) $row['alt']) ?>" maxlength="200" autocomplete="off"<?= $altError !== null && trim((string) $row['alt']) === '' && empty($row['del']) ? ' aria-invalid="true" aria-describedby="' . e($id) . '-alt-error"' : '' ?>>
<?php else: ?>
        <p class="gallery__note">Фото из библиотеки сайта, подпись уже есть. Заменить файл нельзя — только убрать.</p>
<?php endif ?>
        <label class="choice choice--check"><input type="checkbox" name="<?= e($name) ?>__del_<?= e($t) ?>" value="1"<?= !empty($row['del']) ? ' checked' : '' ?>><span>Убрать это фото</span></label>
      </div>
      <div class="gallery__move">
        <button type="button" class="btn btn-icon" data-gallery-up aria-label="Выше" hidden>↑</button>
        <button type="button" class="btn btn-icon" data-gallery-down aria-label="Ниже" hidden>↓</button>
      </div>
    </li>
<?php endforeach ?>
  </ul>
  <div class="gallery__new" data-gallery-new>
<?php foreach ($new as $n => $newAlt): $n = (int) $n; ?>
    <div class="gallery__slot" data-gallery-slot>
      <label class="file-pick" for="<?= e($id) ?>__new_<?= $n ?>">Добавить фото</label>
      <input type="file" id="<?= e($id) ?>__new_<?= $n ?>" name="<?= e($name) ?>__new_<?= $n ?>" accept="image/jpeg,image/png,image/webp">
      <label for="<?= e($id) ?>__newalt_<?= $n ?>">Что на фото?</label>
      <input type="text" id="<?= e($id) ?>__newalt_<?= $n ?>" name="<?= e($name) ?>__newalt_<?= $n ?>" value="<?= e((string) $newAlt) ?>" maxlength="200" autocomplete="off">
    </div>
<?php endforeach ?>
    <button type="button" class="btn btn-ghost" data-gallery-add hidden>Ещё фото</button>
  </div>
  <p class="hint">JPEG, PNG или WebP до 10 МБ. К каждому новому фото — подпись одной фразой для незрячих посетителей. Новые фото встают в конец, потом их можно подвинуть ↑ ↓.</p>
</fieldset>

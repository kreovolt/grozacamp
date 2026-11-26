<?php
declare(strict_types=1);

/**
 * Общая оболочка формы: скрытые поля защиты, поле-ловушка, общая ошибка, блоки успеха и «позвоните нам».
 * @var string $formId
 * @var string $action
 * @var string $success      текст на месте формы после отправки с JS
 * @var string $submitLabel
 * @var string $body         разметка полей
 * @var array<string,string>|null $guard   null — защита форм не настроена
 * @var array<string,string> $errors
 * @var array<string,string>|null $labels  подписи полей (для сводки ошибок без JS)
 * @var array<string,mixed> $settings
 */
use function Groza\View\e;
?>
<?php if ($guard === null): /* защита не настроена — вместо формы телефон и WhatsApp */ ?>
<div class="rounded-card border-2 border-rust-deep bg-paper px-6 py-6">
  <?= $this->partial('partials/contact-fallback', ['intro' => 'Форма сейчас недоступна.']) ?>
</div>
<?php return; endif ?>
<div data-form-root>
  <form method="post" action="<?= e($action) ?>" novalidate data-form="<?= e($formId) ?>" class="space-y-5">
    <input type="hidden" name="_ts" value="<?= e($guard['_ts'] ?? '') ?>">
    <input type="hidden" name="_sig" value="<?= e($guard['_sig'] ?? '') ?>">
    <?php /* Ловушка для ботов: за экраном, без табуляции, не читается скринридером */ ?>
    <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
      <label>Не заполняйте это поле <input type="text" name="<?= e(\Groza\Forms\FormGuard::HONEYPOT) ?>" value="" tabindex="-1" autocomplete="new-password" data-lpignore="true" data-1p-ignore></label>
    </div>
    <p class="rounded-btn border-2 border-rust-deep bg-paper px-4 py-3 font-semibold text-rust-deep" role="alert" data-form-error<?= empty($errors['_form']) ? ' hidden' : '' ?>><?= e($errors['_form'] ?? '') ?></p>
<?php $fieldErrors = array_diff_key($errors, ['_form' => true]); ?>
<?php if ($fieldErrors !== []): /* без JS страница показана заново после отправки: сводка в начале формы, ссылки на поля */ ?>
    <div class="rounded-btn border-2 border-rust-deep bg-paper px-4 py-3 text-rust-deep" role="alert" data-form-summary>
      <p class="font-semibold"><?= $formId === 'vacancy' ? 'Анкета' : 'Заявка' ?> не отправлена. Проверьте:</p>
      <ul class="mt-2 list-disc pl-5">
<?php foreach ($fieldErrors as $name => $message): ?>
        <li><a class="underline" href="#<?= e($formId . '-' . $name) ?>"><?= e($labels[$name] ?? $name) ?></a> — <?= e($message) ?></li>
<?php endforeach ?>
      </ul>
    </div>
<?php endif ?>
    <?= $body ?>
    <div>
      <?= $this->partial('components/button', ['label' => $submitLabel, 'type' => 'submit', 'size' => 'lg', 'class' => 'w-full sm:w-auto']) ?>
    </div>
  </form>
  <p class="rounded-card bg-forest px-6 py-8 font-display text-2xl font-semibold uppercase leading-tight text-cream" role="status" tabindex="-1" data-form-success hidden><?= e($success) ?></p>
  <div class="rounded-card border-2 border-rust-deep bg-paper px-6 py-6" role="alert" tabindex="-1" data-form-fallback hidden>
    <?= $this->partial('partials/contact-fallback', ['intro' => 'Не получилось отправить форму.']) ?>
  </div>
</div>

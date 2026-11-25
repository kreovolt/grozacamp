<?php
declare(strict_types=1);

/**
 * ВРЕМЕННАЯ страница с формой: показывает ошибки проверки без JS, пока нет /booking/ и /vacancies/
 * (их заменят BookingPage — Task 9 и VacanciesPage — Task 10).
 * @var string $formId
 * @var string $title
 * @var array<string,string> $values
 * @var array<string,string> $errors
 * @var array<string,mixed> $options
 * @var array<string,string> $guard
 */
use function Groza\View\e;

$this->layout('layout', ['title' => $title . ' — детский лагерь «Гроза», Пермь', 'noindex' => true]);
?>
<div class="wrap py-12 desk:py-16">
  <h1 class="font-display text-[clamp(2rem,1.4rem+2.5vw,3.25rem)] font-bold uppercase leading-[1.05]"><?= e($title) ?></h1>
  <div class="mt-8 max-w-xl">
    <?= $this->partial('forms/' . $formId, ['values' => $values, 'errors' => $errors, 'options' => $options, 'guard' => $guard]) ?>
  </div>
</div>

<?php
declare(strict_types=1);
/**
 * Страница 503: сайт работает как боевой или тестовый (staging, prod), но не настроен (config/local.php).
 * Вход в админку закрыт: без секрета приложения и адреса сайта сессия и формы небезопасны.
 * Значения настроек не показываются — только какие ключи заполнить.
 * @var list<string> $problems
 */
use function Groza\View\e;

$this->layout('admin/layout', ['title' => 'Сайт не настроен', 'user' => null, 'csrf' => '']);
?>
<section class="card card-narrow" aria-labelledby="nc-title">
  <h1 id="nc-title" class="h1">Сайт не настроен</h1>
  <p class="alert alert-error" role="alert">Сайт не настроен: заполните config/local.php. Пока этого не сделано, вход в админку закрыт.</p>
  <p>Что заполнить (образец — config/local.example.php):</p>
  <ul>
<?php foreach ($problems as $p): ?>
    <li><?= e($p) ?></li>
<?php endforeach ?>
  </ul>
  <p>Сайт для посетителей работает, но формы заявок вместо отправки показывают телефон и WhatsApp.</p>
</section>

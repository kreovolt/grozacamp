// Яндекс.Метрика. Подключается только когда в настройках задан счётчик (data-id у этого <script>); inline-кода нет — CSP script-src 'self'.
(function () {
  const id = Number(document.currentScript && document.currentScript.dataset.id);
  if (!id) return;
  window.ym = window.ym || function () { (window.ym.a = window.ym.a || []).push(arguments); };
  window.ym.l = Date.now();
  const tag = document.createElement('script');
  tag.async = true;
  tag.src = 'https://mc.yandex.ru/metrika/tag.js';
  document.head.appendChild(tag);
  window.ym(id, 'init', { clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: false });
})();

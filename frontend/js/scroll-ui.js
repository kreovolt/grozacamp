// Что зависит от прокрутки:
// - шапка (partials/header): тень появляется, только когда под ней уже едет страница (data-scrolled);
//   вверху страницы шапка и первый экран одного цвета, и тень там выглядела бы тёмной полосой;
// - кнопка «Наверх» (partials/to-top): видна, когда страница прокручена дальше полутора экранов.
//   Саму прокрутку делает ссылка #top; плавность — scroll-behavior в app.css (выключена при prefers-reduced-motion).
export function initScrollUi() {
  const header = document.querySelector('[data-header]');
  const link = document.querySelector('[data-to-top]');
  if (!header && !link) return;
  let queued = false;
  const update = () => {
    queued = false;
    if (header) header.toggleAttribute('data-scrolled', window.scrollY > 4);
    if (link) link.classList.toggle('is-visible', window.scrollY > window.innerHeight * 1.5);
  };
  window.addEventListener('scroll', () => {
    if (queued) return;
    queued = true;
    requestAnimationFrame(update);
  }, { passive: true });
  // После перехода по #top убрать хеш из адреса, чтобы ссылкой на страницу делились без «#top»
  link?.addEventListener('click', () => {
    setTimeout(() => {
      if (window.location.hash === '#top') history.replaceState(history.state, '', window.location.pathname + window.location.search);
    }, 0);
  });
  update();
}

// Кнопка «Наверх» (templates/partials/to-top.php): показываем, когда страница прокручена дальше полутора экранов.
// Саму прокрутку делает ссылка #top; плавность — scroll-behavior в app.css (выключена при prefers-reduced-motion).
export function initToTop() {
  const link = document.querySelector('[data-to-top]');
  if (!link) return;
  let queued = false;
  const update = () => {
    queued = false;
    link.classList.toggle('is-visible', window.scrollY > window.innerHeight * 1.5);
  };
  window.addEventListener('scroll', () => {
    if (queued) return;
    queued = true;
    requestAnimationFrame(update);
  }, { passive: true });
  // После перехода по #top убрать хеш из адреса, чтобы ссылкой на страницу делились без «#top»
  link.addEventListener('click', () => {
    setTimeout(() => {
      if (window.location.hash === '#top') history.replaceState(history.state, '', window.location.pathname + window.location.search);
    }, 0);
  });
  update();
}

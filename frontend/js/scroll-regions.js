// Области с прокруткой вбок (таблицы Сведений, лента фото на телефоне): Tab доходит до них, только когда им действительно
// есть что прокручивать — тогда их можно листать стрелками; когда всё помещается, лишней остановки Tab нет.
export function initScrollRegions() {
  const regions = [...document.querySelectorAll('[data-scroll-region]')];
  if (!regions.length) return;
  const update = () => {
    regions.forEach((el) => {
      if (el.scrollWidth > el.clientWidth + 1) el.tabIndex = 0;
      else el.removeAttribute('tabindex');
    });
  };
  update();
  window.addEventListener('load', update);
  if ('ResizeObserver' in window) {
    const observer = new ResizeObserver(update);
    regions.forEach((el) => observer.observe(el));
  } else {
    window.addEventListener('resize', update, { passive: true });
  }
}

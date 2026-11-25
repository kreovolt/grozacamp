// Оглавление страницы (templates/components/toc.php): на телефоне длинный список (> 8 пунктов) стартует свёрнутым.
// open у <details> нельзя задать из CSS, поэтому делаем это здесь; без JS список остаётся открытым.
const MAX_OPEN_ITEMS = 8;

export function initToc() {
  const wide = window.matchMedia('(min-width: 1024px)');
  document.querySelectorAll('[data-toc] details').forEach((details) => {
    if (details.querySelectorAll('li').length <= MAX_OPEN_ITEMS) return;
    const sync = () => { details.open = wide.matches; };
    sync();
    if (wide.addEventListener) wide.addEventListener('change', sync);
  });
}

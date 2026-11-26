// Вопросы и ответы (components/faq-list): в группе <details name="…"> открыт только один ответ.
// Современные браузеры закрывают предыдущий сами по атрибуту name; здесь — то же для старых браузеров
// и удержание открытого вопроса на месте: когда над ним закрывается длинный ответ, страница не прыгает вверх.
export function initAccordions() {
  let anchor = null; // вопрос, по которому только что нажали, и где он был на экране

  document.addEventListener('click', (event) => {
    const summary = event.target instanceof Element ? event.target.closest('details[name] > summary') : null;
    anchor = summary ? { summary, top: summary.getBoundingClientRect().top } : null;
  });

  document.addEventListener('toggle', (event) => {
    const details = event.target;
    if (!(details instanceof HTMLDetailsElement) || !details.open || !details.name) return;
    for (const other of document.querySelectorAll('details[name][open]')) {
      if (other !== details && other.name === details.name) other.open = false;
    }
    if (anchor && anchor.summary.parentElement === details) {
      const shift = anchor.summary.getBoundingClientRect().top - anchor.top;
      if (Math.abs(shift) > 1) window.scrollBy({ top: shift, behavior: 'instant' });
    }
    anchor = null;
  }, true); // toggle не всплывает — ловим на погружении
}

// Меню шапки: выпадающий список «Сведения…» (десктоп) и полноэкранное меню с аккордеоном (телефон).
// Разметка — templates/partials/nav-desktop.php и nav-mobile.php.

const OPEN_DELAY = 80; // наведение: не открывать от случайного пролёта мышью
const CLOSE_DELAY = 260; // уход мыши: дать время дойти до панели

/** Выпадающие списки: кнопка [data-dropdown-toggle] + панель по aria-controls внутри [data-dropdown]. */
export function initDropdowns(root = document) {
  root.querySelectorAll('[data-dropdown]').forEach(setupDropdown);
}

function setupDropdown(dd) {
  const button = dd.querySelector('[data-dropdown-toggle]');
  const panel = button && document.getElementById(button.getAttribute('aria-controls'));
  if (!panel) return;

  let timer = 0;
  let openedByHover = false;
  const isOpen = () => button.getAttribute('aria-expanded') === 'true';
  const links = () => Array.from(panel.querySelectorAll('a[href]'));

  function open(byHover = false) {
    clearTimeout(timer);
    if (isOpen()) return;
    openedByHover = byHover;
    button.setAttribute('aria-expanded', 'true');
    panel.hidden = false;
    document.addEventListener('pointerdown', onOutside, true);
    document.addEventListener('keydown', onEscape);
  }

  function close(returnFocus = false) {
    clearTimeout(timer);
    if (!isOpen()) return;
    openedByHover = false;
    button.setAttribute('aria-expanded', 'false');
    panel.hidden = true;
    document.removeEventListener('pointerdown', onOutside, true);
    document.removeEventListener('keydown', onEscape);
    if (returnFocus) button.focus();
  }

  function onOutside(e) {
    if (!dd.contains(e.target)) close();
  }

  function onEscape(e) {
    if (e.key !== 'Escape') return;
    const focusInside = dd.contains(document.activeElement);
    close(focusInside);
    if (focusInside) e.preventDefault();
  }

  // Клик, Enter и пробел. Если список уже открыт наведением, клик его закрепляет, а не закрывает.
  button.addEventListener('click', () => {
    if (!isOpen()) open();
    else if (openedByHover) openedByHover = false;
    else close();
  });

  // Стрелки: вниз с кнопки открывает и ведёт к первому пункту; вверх/вниз/Home/End — по пунктам
  dd.addEventListener('keydown', (e) => {
    const items = links();
    const i = items.indexOf(document.activeElement);
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (!isOpen()) open();
      (items[i + 1] || items[0]).focus();
    } else if (e.key === 'ArrowUp' && isOpen()) {
      e.preventDefault();
      (i > 0 ? items[i - 1] : items[items.length - 1]).focus();
    } else if ((e.key === 'Home' || e.key === 'End') && i >= 0) {
      e.preventDefault();
      items[e.key === 'Home' ? 0 : items.length - 1].focus();
    }
  });

  // Фокус ушёл из меню (Tab дальше по странице) — закрыть
  dd.addEventListener('focusout', (e) => {
    if (isOpen() && e.relatedTarget && !dd.contains(e.relatedTarget)) close();
  });
  // Клавиатура внутри панели закрепляет список, открытый наведением
  dd.addEventListener('focusin', (e) => {
    if (panel.contains(e.target)) openedByHover = false;
  });

  // Наведение мышью (на сенсорных экранах — только нажатие)
  dd.addEventListener('pointerenter', (e) => {
    if (e.pointerType !== 'mouse') return;
    clearTimeout(timer);
    if (!isOpen()) timer = setTimeout(() => open(true), OPEN_DELAY);
  });
  dd.addEventListener('pointerleave', (e) => {
    if (e.pointerType !== 'mouse') return;
    clearTimeout(timer);
    if (isOpen() && openedByHover) timer = setTimeout(() => close(), CLOSE_DELAY);
  });
}

/** Полноэкранное меню телефона: [data-menu-open] открывает панель по aria-controls. */
export function initMobileMenu() {
  const opener = document.querySelector('[data-menu-open]');
  const panel = opener && document.getElementById(opener.getAttribute('aria-controls'));
  if (!panel) return;
  const closer = panel.querySelector('[data-menu-close]');
  const desktop = window.matchMedia('(min-width: 1200px)');

  function open() {
    panel.hidden = false;
    opener.setAttribute('aria-expanded', 'true');
    document.documentElement.classList.add('menu-open');
    document.addEventListener('keydown', onKeydown);
    (closer || panel).focus();
  }

  function close(returnFocus = true) {
    if (panel.hidden) return;
    panel.hidden = true;
    opener.setAttribute('aria-expanded', 'false');
    document.documentElement.classList.remove('menu-open');
    document.removeEventListener('keydown', onKeydown);
    if (returnFocus) opener.focus();
  }

  // Esc закрывает; Tab не выпускает фокус из открытого меню
  function onKeydown(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      close();
      return;
    }
    if (e.key !== 'Tab') return;
    const items = focusable(panel);
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  opener.addEventListener('click', open);
  if (closer) closer.addEventListener('click', () => close());
  // Переход по ссылке (в том числе на якорь этой же страницы) закрывает меню
  panel.addEventListener('click', (e) => {
    if (e.target.closest('a[href]')) close(false);
  });
  // Окно стало широким — меню в шапке видно целиком, панель не нужна
  const onWide = (e) => e.matches && close(false);
  if (desktop.addEventListener) desktop.addEventListener('change', onWide);
  else desktop.addListener(onWide);

  panel.querySelectorAll('[data-accordion]').forEach(setupAccordion);
}

function setupAccordion(button) {
  const target = document.getElementById(button.getAttribute('aria-controls'));
  if (!target) return;
  button.addEventListener('click', () => {
    const expand = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', String(expand));
    target.hidden = !expand;
  });
}

function focusable(container) {
  return Array.from(container.querySelectorAll('a[href], button:not([disabled])'))
    .filter((el) => el.getClientRects().length > 0);
}

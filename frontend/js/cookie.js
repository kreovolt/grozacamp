// Уведомление о cookie: «Понятно» запоминается в localStorage; без хранилища (приватный режим) показывается каждый раз.
const KEY = 'groza-cookie-ok';

function read() {
  try { return localStorage.getItem(KEY) === '1'; } catch { return false; }
}

// Пока уведомление открыто, его высота лежит в --cookie-h на <html>: кнопка «Наверх» (app.css) поднимается над ним.
function publishHeight(box) {
  const root = document.documentElement;
  const set = () => {
    if (box.hidden) root.style.removeProperty('--cookie-h');
    else root.style.setProperty('--cookie-h', `${Math.ceil(box.getBoundingClientRect().height)}px`);
  };
  set();
  if ('ResizeObserver' in window) new ResizeObserver(set).observe(box);
  return set;
}

export function initCookieNotice() {
  const box = document.querySelector('[data-cookie-notice]');
  if (!box || read()) return;
  box.hidden = false;
  const update = publishHeight(box);
  box.querySelector('[data-cookie-accept]')?.addEventListener('click', () => {
    try { localStorage.setItem(KEY, '1'); } catch { /* без хранилища — спросим в следующий раз */ }
    box.hidden = true;
    update();
  });
}

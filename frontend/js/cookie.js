// Уведомление о cookie: «Понятно» запоминается в localStorage; без хранилища (приватный режим) показывается каждый раз.
const KEY = 'groza-cookie-ok';

function read() {
  try { return localStorage.getItem(KEY) === '1'; } catch { return false; }
}

export function initCookieNotice() {
  const box = document.querySelector('[data-cookie-notice]');
  if (!box || read()) return;
  box.hidden = false;
  box.querySelector('[data-cookie-accept]')?.addEventListener('click', () => {
    try { localStorage.setItem(KEY, '1'); } catch { /* без хранилища — спросим в следующий раз */ }
    box.hidden = true;
  });
}

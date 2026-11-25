// Формы заявки и анкеты: улучшение поверх обычной отправки. Без JS форма работает как есть
// (POST → редирект на /spasibo/). Ожидание 3 секунд проверяет сервер — здесь его не имитируем.

const SENDING = 'Отправляем…';

function setError(form, name, message) {
  const holder = form.querySelector(`[data-field="${name}"]`);
  if (!holder) return null;
  const input = holder.querySelector('input, select, textarea');
  const out = holder.querySelector('[data-error]');
  if (out) {
    out.textContent = message || '';
    out.hidden = !message;
  }
  if (input) {
    if (message) input.setAttribute('aria-invalid', 'true');
    else input.removeAttribute('aria-invalid');
  }
  return input;
}

function clearErrors(form) {
  form.querySelectorAll('[data-field]').forEach((f) => setError(form, f.dataset.field, ''));
  const general = form.querySelector('[data-form-error]');
  if (general) {
    general.textContent = '';
    general.hidden = true;
  }
}

function showGeneral(form, message) {
  const general = form.querySelector('[data-form-error]');
  if (!general) return;
  general.textContent = message;
  general.hidden = false;
  general.setAttribute('tabindex', '-1');
  general.focus();
}

function refreshGuard(form, guard) {
  if (!guard) return;
  Object.keys(guard).forEach((name) => {
    const input = form.querySelector(`input[name="${name}"]`);
    if (input) input.value = guard[name];
  });
}

function reveal(root, form, selector) {
  form.hidden = true;
  const box = root.querySelector(selector);
  if (!box) return;
  box.hidden = false;
  box.focus();
}

async function submit(form, root) {
  const button = form.querySelector('button[type="submit"]');
  const label = button ? button.querySelector('span') : null;
  const original = label ? label.textContent : '';
  if (button) button.disabled = true;
  if (label) label.textContent = SENDING;
  clearErrors(form);

  try {
    const response = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    });
    let data = null;
    try {
      data = await response.json();
    } catch {
      data = null;
    }

    if (data && data.ok) {
      reveal(root, form, '[data-form-success]');
      return;
    }
    if (data && data.errors) {
      refreshGuard(form, data.guard);
      Object.keys(data.errors).forEach((name) => {
        if (name === '_form') showGeneral(form, data.errors[name]);
        else setError(form, name, data.errors[name]);
      });
      // фокус — на первом по порядку на странице, а не в порядке ответа сервера
      const first = form.querySelector('[aria-invalid="true"]');
      if (first) first.focus();
      return;
    }
    reveal(root, form, '[data-form-fallback]');
  } catch {
    reveal(root, form, '[data-form-fallback]');
  } finally {
    if (button) button.disabled = false;
    if (label) label.textContent = original;
  }
}

export function initForms() {
  document.querySelectorAll('form[data-form]').forEach((form) => {
    const root = form.closest('[data-form-root]') || form.parentElement;
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      if (form.dataset.sending) return;
      form.dataset.sending = '1';
      submit(form, root).finally(() => delete form.dataset.sending);
    });
  });
}

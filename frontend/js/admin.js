// Админка: черновики форм, счётчики знаков, фокус на первой ошибке, имя выбранного файла.
// Без этого скрипта всё работает (формы обычные), он только делает работу удобнее.

const DRAFT_PREFIX = 'draft:';
const SAVE_EVERY_MS = 5000;
const TYPING_PAUSE_MS = 1000;
const KEEP_DAYS = 30;
const SKIP = new Set(['_csrf', '_hash']);

// localStorage может быть недоступен (приватное окно, запрет): тогда черновиков просто нет
function store() {
  try {
    return window.localStorage;
  } catch (e) {
    return null;
  }
}

function read(key) {
  const ls = store();
  if (!ls) return null;
  try {
    const raw = ls.getItem(key);
    const data = raw ? JSON.parse(raw) : null;
    return data && typeof data === 'object' && data.values && typeof data.values === 'object' ? data : null;
  } catch (e) {
    return null;
  }
}

function write(key, value) {
  const ls = store();
  if (!ls) return;
  try {
    ls.setItem(key, JSON.stringify(value));
  } catch (e) {
    /* нет места — черновик не сохраняется, форма работает */
  }
}

function remove(key) {
  const ls = store();
  if (!ls) return;
  try {
    ls.removeItem(key);
  } catch (e) {
    /* ничего */
  }
}

/** Значения полей формы: имя → строка (радио — выбранное, флажок — true/false). Токены и файлы не берём. */
function snapshot(form) {
  const out = {};
  for (const el of Array.from(form.elements)) {
    if (!el.name || SKIP.has(el.name) || el.disabled) continue;
    const type = el.type;
    // hidden: служебные поля (токены, метки фото в галерее) — не ввод человека; черновик их не хранит и не возвращает
    if (type === 'file' || type === 'submit' || type === 'button' || type === 'reset' || type === 'hidden') continue;
    if (type === 'radio') {
      if (el.checked) out[el.name] = el.value;
      else if (!(el.name in out)) out[el.name] = null;
    } else if (type === 'checkbox') {
      out[el.name] = el.checked;
    } else {
      out[el.name] = el.value;
    }
  }
  return out;
}

function same(a, b) {
  const keys = Object.keys(a);
  if (keys.length !== Object.keys(b).length) return false;
  return keys.every((k) => a[k] === b[k]);
}

function apply(form, values) {
  for (const el of Array.from(form.elements)) {
    if (!el.name || SKIP.has(el.name) || !(el.name in values)) continue;
    const v = values[el.name];
    if (el.type === 'radio') {
      el.checked = el.value === v;
    } else if (el.type === 'checkbox') {
      el.checked = v === true;
    } else if (el.type !== 'file' && typeof v === 'string') {
      el.value = v;
    } else {
      continue;
    }
    el.dispatchEvent(new Event('input', { bubbles: true }));
  }
}

function pruneOld() {
  const ls = store();
  if (!ls) return;
  try {
    const limit = Date.now() - KEEP_DAYS * 86400000;
    for (let i = ls.length - 1; i >= 0; i--) {
      const key = ls.key(i);
      if (key && key.indexOf(DRAFT_PREFIX) === 0) {
        const d = read(key);
        if (!d || !(d.at > limit)) ls.removeItem(key);
      }
    }
  } catch (e) {
    /* ничего */
  }
}

function initDraft(form) {
  const key = DRAFT_PREFIX + location.pathname;
  const hashField = form.elements.namedItem('_hash');
  const server = snapshot(form);
  // Форма показана после отказа (409/422): значения в ней — несохранённый ввод человека, а не данные сервера.
  // Такой черновик не удаляем, пока ввод не сохранён (иначе при истёкшей сессии текст пропал бы).
  const unsaved = form.hasAttribute('data-draft-unsaved');
  const isNew = /\/new\/$/.test(location.pathname);
  // Состояние файла, на котором основан ввод: у обычной формы — её _hash, у страницы после отказа сервер даёт прежний (data-based-on).
  // В черновик попадает только это значение (отдельным ключом); сам _hash формы в черновик и обратно не ходит никогда.
  let base = form.getAttribute('data-based-on') || (hashField ? hashField.value : '');
  let conflict = form.hasAttribute('data-draft-conflict');
  const formHash = hashField ? hashField.value : '';

  // Успешное сохранение: сервер вернул ?saved=1 — черновик (и черновик «новой записи») больше не нужен
  // Один раз: адрес очищается от saved/created, чтобы обновление страницы потом не стирало новый ввод.
  const params = new URLSearchParams(location.search);
  if (params.get('saved') === '1') {
    remove(key);
    if (params.get('created') === '1') remove(DRAFT_PREFIX + location.pathname.replace(/\/[a-f0-9]{12}\/$/, '/new/'));
    params.delete('saved');
    params.delete('created');
    const rest = params.toString();
    try {
      window.history.replaceState(null, '', location.pathname + (rest ? '?' + rest : '') + location.hash);
    } catch (e) {
      /* ничего */
    }
  }

  const draft = read(key);
  const notice = document.querySelector('[data-draft-notice]');
  if (draft && !same(draft.values, server)) {
    apply(form, draft.values);
    // _hash остаётся тем, что отдал сервер сейчас: человек видит нынешнее состояние записи. Предупреждаем словами.
    if (typeof draft.base === 'string') base = draft.base;
    conflict = draft.conflict === true;
    if (notice) {
      notice.hidden = false;
      const text = notice.querySelector('[data-draft-text]');
      if (text && !isNew) text.textContent = text.getAttribute('data-existing') || text.textContent;
    }
    if (!isNew) {
      const changed = base !== '' && base !== formHash;
      const banner = document.querySelector('[data-draft-banner="' + (conflict ? 'conflict' : 'changed') + '"]');
      if (banner && (conflict || changed)) banner.hidden = false;
    }
  }

  const persist = () => {
    const now = snapshot(form);
    if (!unsaved && same(now, server)) {
      remove(key);
      return;
    }
    write(key, { at: Date.now(), base: base, conflict: conflict, values: now });
  };

  const cancel = document.querySelector('[data-draft-cancel]');
  if (cancel) {
    cancel.addEventListener('click', () => {
      remove(key);
      window.removeEventListener('beforeunload', persist);
      window.removeEventListener('pagehide', persist);
      window.clearInterval(timer);
      location.replace(location.pathname + location.search);
    });
  }

  const timer = window.setInterval(persist, SAVE_EVERY_MS);
  let pause = 0;
  form.addEventListener('input', () => {
    window.clearTimeout(pause);
    pause = window.setTimeout(persist, TYPING_PAUSE_MS);
  });
  window.addEventListener('beforeunload', persist);
  window.addEventListener('pagehide', persist);
}

function initCounters(root) {
  for (const out of Array.from(root.querySelectorAll('[data-count-for]'))) {
    const input = document.getElementById(out.getAttribute('data-count-for'));
    const max = Number(out.getAttribute('data-max')) || 0;
    if (!input) continue;
    const update = () => {
      const n = Array.from(input.value).length;
      out.textContent = n + ' из ' + max;
      out.classList.toggle('is-over', max > 0 && n >= max);
    };
    input.addEventListener('input', update);
    update();
  }
}

function initFiles(root) {
  for (const input of Array.from(root.querySelectorAll('input[type=file]'))) {
    const label = root.querySelector('label[for="' + input.id + '"]');
    if (!label) continue;
    const original = label.textContent;
    const sync = () => {
      label.textContent = input.files && input.files.length ? 'Выбрано: ' + input.files[0].name : original;
    };
    input.addEventListener('change', sync);
    sync(); // файл могли выбрать до запуска скрипта (медленная загрузка): подпись должна совпасть с полем
  }
}

// Галерея: кнопки ↑ ↓ двигают строки (порядок сохранения — порядок строк), «Ещё фото» добавляет место для нового фото.
// Без скрипта порядок не меняется, а новое фото добавляется по одному за сохранение.
function initGallery(root) {
  for (const gallery of Array.from(root.querySelectorAll('[data-gallery]'))) {
    const list = gallery.querySelector('[data-gallery-list]');
    const items = () => Array.from(list.querySelectorAll('[data-gallery-item]'));
    const sync = () => {
      const all = items();
      all.forEach((item, i) => {
        const up = item.querySelector('[data-gallery-up]');
        const down = item.querySelector('[data-gallery-down]');
        up.hidden = false;
        down.hidden = false;
        up.disabled = i === 0;
        down.disabled = i === all.length - 1;
      });
    };
    gallery.addEventListener('click', (ev) => {
      const btn = ev.target instanceof Element ? ev.target.closest('[data-gallery-up], [data-gallery-down]') : null;
      if (!btn) return;
      const item = btn.closest('[data-gallery-item]');
      const up = btn.hasAttribute('data-gallery-up');
      const other = up ? item.previousElementSibling : item.nextElementSibling;
      if (!other) return;
      if (up) list.insertBefore(item, other);
      else list.insertBefore(other, item);
      sync();
      btn.focus();
      // порядок — часть ввода: черновик должен его заметить
      gallery.closest('form').dispatchEvent(new Event('input', { bubbles: true }));
    });
    sync();

    const add = gallery.querySelector('[data-gallery-add]');
    const box = gallery.querySelector('[data-gallery-new]');
    if (add && box) {
      add.hidden = false;
      add.addEventListener('click', () => {
        const slots = box.querySelectorAll('[data-gallery-slot]');
        const last = slots[slots.length - 1];
        const copy = last.cloneNode(true);
        const number = Array.from(slots).reduce((max, slot) => {
          const m = /__new_(\d+)$/.exec(slot.querySelector('input[type=file]').name);
          return m ? Math.max(max, Number(m[1])) : max;
        }, 0) + 1;
        for (const el of Array.from(copy.querySelectorAll('[id], [name], [for]'))) {
          for (const attr of ['id', 'name', 'for']) {
            const v = el.getAttribute(attr);
            if (v) el.setAttribute(attr, v.replace(/(__new(?:alt)?_)\d+$/, '$1' + number));
          }
          if (el.tagName === 'INPUT') el.value = '';
          if (el.classList.contains('file-pick')) el.textContent = 'Добавить фото';
        }
        box.insertBefore(copy, add);
        initFiles(copy);
        copy.querySelector('input[type=file]').focus();
      });
    }
  }
}

function focusProblem() {
  const first = document.querySelector('.has-error [aria-invalid="true"]');
  if (first) {
    // у группы радиокнопок ошибка помечена на fieldset: фокус — на выбранную (или первую) кнопку внутри
    const target = first.tagName === 'FIELDSET' ? first.querySelector('input:checked') || first.querySelector('input') : first;
    (target || first).focus();
    return;
  }
  const banner = document.getElementById('form-banner') || document.getElementById('error-summary');
  if (banner) banner.focus();
}

document.addEventListener('DOMContentLoaded', () => {
  pruneOld();
  const form = document.querySelector('form[data-draft]');
  if (!form) return;
  const start = () => {
    initCounters(form);
    initFiles(form);
    initGallery(form);
    initDraft(form);
    focusProblem();
    form.setAttribute('data-draft-ready', '');
  };
  // На страницах с редактором текста (admin-editor.js) ждём, пока он запустится: он переписывает значения своих полей
  const editors = window.grozaEditorsReady;
  if (editors && typeof editors.then === 'function') editors.then(start, start);
  else start();
});

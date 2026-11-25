// Редактор текста с оформлением (Trix 2) для админки. Подключается только на страницах, где есть такие поля.
// Без этого скрипта поле — обычное многострочное (<textarea data-richtext>): текст можно править как HTML.
//
// Что отключено намеренно:
//  - вставка картинок и файлов (перетаскивание, вставка из буфера, кнопка скрепки): в тексте на сайте их быть не должно;
//  - зачёркнутый, «код» и вложенные списки: на сайте для них нет оформления.
// Всё, что придёт на сервер, ещё раз очищает Sanitizer (app/Admin/Sanitizer.php): редактор — не граница безопасности.
//
// CSP админки без 'unsafe-inline': Trix не добавляет <style> в страницу (убрано при сборке, см. scripts/build-assets.mjs),
// его стили лежат в /assets/css/admin-editor.css.
import Trix from 'trix/dist/trix.esm.js';

// Заголовок в тексте сайта — h3 (h1 на странице один, в шапке). Кнопка «Заголовок» ставит и читает именно h3,
// поэтому заголовки из готовых текстов (правила, политика) не теряются при открытии в редакторе.
Trix.config.blockAttributes.heading1.tagName = 'h3';

let seq = 0;

function toolbarHtml() {
  const btn = (cls, title, attrs) =>
    '<button type="button" class="trix-button trix-button--icon trix-button--icon-' + cls + '" ' + attrs
    + ' title="' + title + '" tabindex="-1">' + title + '</button>';
  return '<div class="trix-button-row">'
    + '<span class="trix-button-group trix-button-group--text-tools" data-trix-button-group="text-tools">'
    + btn('bold', 'Жирный', 'data-trix-attribute="bold" data-trix-key="b"')
    + btn('italic', 'Курсив', 'data-trix-attribute="italic" data-trix-key="i"')
    + btn('link', 'Ссылка', 'data-trix-attribute="href" data-trix-action="link" data-trix-key="k"')
    + '</span>'
    + '<span class="trix-button-group trix-button-group--block-tools" data-trix-button-group="block-tools">'
    + btn('heading-1', 'Заголовок', 'data-trix-attribute="heading1"')
    + btn('quote', 'Цитата', 'data-trix-attribute="quote"')
    + btn('bullet-list', 'Маркированный список', 'data-trix-attribute="bullet"')
    + btn('number-list', 'Нумерованный список', 'data-trix-attribute="number"')
    + '</span>'
    + '<span class="trix-button-group-spacer"></span>'
    + '<span class="trix-button-group trix-button-group--history-tools" data-trix-button-group="history-tools">'
    + btn('undo', 'Шаг назад', 'data-trix-action="undo" data-trix-key="z"')
    + btn('redo', 'Шаг вперёд', 'data-trix-action="redo" data-trix-key="shift+z"')
    + '</span>'
    + '</div>'
    + '<div class="trix-dialogs" data-trix-dialogs>'
    + '<div class="trix-dialog trix-dialog--link" data-trix-dialog="href" data-trix-dialog-attribute="href">'
    + '<div class="trix-dialog__link-fields">'
    + '<input type="url" name="href" class="trix-input trix-input--dialog" placeholder="Адрес ссылки, например https://vk.com/grozacamp" aria-label="Адрес ссылки" data-trix-validate-href required data-trix-input>'
    + '<div class="trix-button-group">'
    + '<input type="button" class="trix-button trix-button--dialog" value="Ссылка" data-trix-method="setAttribute">'
    + '<input type="button" class="trix-button trix-button--dialog" value="Убрать" data-trix-method="removeAttribute">'
    + '</div></div></div></div>';
}

function enhance(textarea) {
  const n = ++seq;
  const label = textarea.id ? document.querySelector('label[for="' + textarea.id + '"]') : null;
  if (label && !label.id) label.id = 'rt-label-' + n;

  const toolbar = document.createElement('trix-toolbar');
  toolbar.id = 'rt-toolbar-' + n;
  toolbar.innerHTML = toolbarHtml();

  const editor = document.createElement('trix-editor');
  editor.id = 'rt-editor-' + n;
  editor.className = 'trix-content richtext-editor';
  editor.setAttribute('toolbar', toolbar.id);
  editor.setAttribute('input', textarea.id);
  editor.setAttribute('aria-multiline', 'true');
  if (label) editor.setAttribute('aria-labelledby', label.id);
  // состояние ошибки и пояснение переходят на сам редактор: скрытое поле сфокусировать нельзя
  if (textarea.getAttribute('aria-invalid') === 'true') {
    editor.setAttribute('aria-invalid', 'true');
    textarea.removeAttribute('aria-invalid');
  }
  const described = textarea.getAttribute('aria-describedby');
  if (described) editor.setAttribute('aria-describedby', described);

  let fromTrix = textarea.value;
  editor.addEventListener('trix-change', () => {
    fromTrix = textarea.value;
    // черновик формы (admin.js) следит за событием input
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
  });
  // Значение поля изменили снаружи (восстановлен черновик): показываем его в редакторе
  textarea.addEventListener('input', () => {
    if (textarea.value !== fromTrix) {
      fromTrix = textarea.value;
      editor.value = textarea.value;
    }
  });

  // Trix при запуске переписывает значение поля в своём виде (<div> вместо <p>): черновик (admin.js) должен снимать «исходное» после этого
  const ready = new Promise((resolve) => {
    editor.addEventListener('trix-initialize', () => window.requestAnimationFrame(() => resolve()), { once: true });
  });

  textarea.hidden = true;
  textarea.after(toolbar, editor);
  if (label) label.addEventListener('click', () => editor.focus());
  return ready;
}

// Картинки и файлы в тексте не нужны: ни перетаскивание, ни вставка
document.addEventListener('trix-file-accept', (e) => e.preventDefault());
document.addEventListener('trix-attachment-add', (e) => {
  try {
    const target = e.target;
    const range = target.editor.getDocument().getRangeOfAttachment(e.attachment);
    target.editor.setSelectedRange(range);
    target.editor.deleteInDirection('forward');
  } catch (err) {
    /* картинку в любом случае уберёт сервер */
  }
});

const ready = Array.from(document.querySelectorAll('textarea[data-richtext]')).map(enhance);
// admin.js ждёт этого (но не дольше двух секунд), чтобы запомнить значения полей уже после запуска редакторов
window.grozaEditorsReady = Promise.race([
  Promise.all(ready),
  new Promise((resolve) => window.setTimeout(resolve, 2000)),
]);

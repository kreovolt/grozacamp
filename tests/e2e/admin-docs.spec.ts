import { expect, test, type Page } from '@playwright/test';
import { PORT_ADMIN, url } from './ports';
import { resolve } from 'node:path';

// Тот же сервер и те же данные, что у admin-shifts.spec.ts (порт 8097, storage/test-admin), свой пользователь.
// Заменённые и удалённые файлы уходят с сайта в карантин storage/test-admin/quarantine, пока их версия в истории
// (откат возвращает их на место); потом их убирает `php scripts/uploads-gc.php`.

const PASSWORD = 'e2e-password-123';
const ADMIN = url(PORT_ADMIN);
const PDF = resolve('tests/fixtures/uploads/real.pdf');
const JPG = resolve('tests/fixtures/uploads/small.jpg');

test.describe.configure({ mode: 'serial' });
test.use({ baseURL: ADMIN, viewport: { width: 375, height: 812 }, hasTouch: true });
test.beforeEach(() => {
  test.setTimeout(60_000);
});

async function login(page: Page) {
  await page.goto('/admin/login/');
  await page.getByLabel('Логин').fill('e2e-docs');
  await page.getByLabel('Пароль').fill(PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page).toHaveURL(/\/admin\/$/);
}

// Скрипт админки (подписи файлов, черновик) оживляет форму не сразу: пока нет метки data-draft-ready, выбранный файл не отражается в подписи
async function formReady(page: Page) {
  await page.locator('form[data-draft-ready]').waitFor();
}

async function removeCurrent(page: Page) {
  await page.getByRole('link', { name: 'Удалить' }).first().click();
  await page.getByRole('button', { name: 'Да, удалить' }).click();
  await expect(page).toHaveURL(/\?ok=delete$/);
}

test('PDF в «Документы» с разделом «Учредительные документы» виден в «Сведениях»; заменённый и удалённый файл не скачать, откат возвращает', async ({ page }) => {
  await login(page);
  await page.goto('/admin/documents/new/');
  await formReady(page);
  await page.getByLabel('Название').fill('Устав e2e');
  await page.getByLabel('Раздел').selectOption('uchreditelnye');
  await page.getByLabel('Дата документа').fill('2026-05-01');

  // без файла сохранить нельзя
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('#error-summary')).toBeVisible();

  await page.locator('#f-file').setInputFiles(PDF);
  await expect(page.locator('label[for="f-file"]')).toContainText('Выбрано: real.pdf');
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');

  // в редакторе: имя файла, размер, «Открыть», «Заменить»
  await expect(page.locator('.current__file')).toContainText('real.pdf');
  await expect(page.locator('.current__file')).toContainText('PDF,');
  const open = page.getByRole('link', { name: 'Открыть' });
  const url = await open.getAttribute('href');
  expect(url).toMatch(/^\/uploads\/docs\/[0-9a-f]{16}\.pdf$/);
  expect((await page.request.get(url!)).status()).toBe(200);
  await expect(page.locator('label[for="f-file"]')).toHaveText('Заменить файл');

  // в списке: раздел, дата, «PDF, размер»
  await page.goto('/admin/documents/');
  const row = page.locator('.row', { hasText: 'Устав e2e' });
  await expect(row).toContainText('Учредительные документы');
  await expect(row).toContainText('01.05.2026');
  await expect(row).toContainText('PDF,');

  // на сайте, в разделе «Документы» страницы «Сведения»
  await page.goto('/svedeniya/ob-organizacii/');
  const docs = page.locator('#dokumenty');
  await expect(docs).toContainText('Устав e2e');
  await expect(docs.locator('li', { hasText: 'Устав e2e' })).toContainText('PDF,');

  // замена: новый файл отдаётся; старый по прежнему адресу больше не скачать (не тот скан не должен остаться в интернете)
  await page.goto('/admin/documents/');
  await row.getByRole('link', { name: 'Устав e2e' }).click();
  await formReady(page);
  await page.locator('#f-file').setInputFiles(PDF);
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');
  const url2 = await page.getByRole('link', { name: 'Открыть' }).getAttribute('href');
  expect(url2).not.toBe(url);
  expect((await page.request.get(url!)).status()).toBe(404);
  expect((await page.request.get(url2!)).status()).toBe(200);

  // единственные учредительные документы: после удаления п. 4 а не будет выполнен — админка спрашивает, ничего не удаляя
  await page.getByRole('link', { name: 'Удалить' }).first().click();
  await page.getByRole('button', { name: 'Да, удалить' }).click();
  await expect(page.locator('#error-summary')).toContainText('После удаления не будет заполнен п. 4 а) Учредительные документы — сайт перестанет соответствовать приказу.');
  await page.getByLabel('Сохранить всё равно').check();
  await page.getByRole('button', { name: 'Удалить всё равно' }).click();
  await expect(page).toHaveURL(/\?ok=delete$/);
  expect((await page.request.get(url2!)).status()).toBe(404); // запись удалена: файла нет на сайте, но он в карантине для истории
  await page.goto('/svedeniya/ob-organizacii/');
  await expect(page.locator('#dokumenty')).not.toContainText('Устав e2e');

  // «Вернуть эту версию» (до удаления): документ и его файл снова на сайте
  await page.goto('/admin/history/');
  const block = page.locator('details.history', { hasText: 'Документы' });
  await block.locator('summary').click();
  const before = block.locator('.history__item').first();
  await before.getByLabel('Да, вернуть').check();
  await before.getByRole('button', { name: 'Вернуть эту версию' }).click();
  await expect(page.getByRole('status')).toContainText('Версия возвращена');
  expect((await page.request.get(url2!)).status()).toBe(200);
  expect((await page.request.get(url!)).status()).toBe(404); // эта замена по-прежнему только в истории
  await page.goto('/svedeniya/ob-organizacii/');
  await expect(page.locator('#dokumenty')).toContainText('Устав e2e');
});

test('фото сотрудника: без подписи «Что на фото?» сохранить нельзя; ответственный за ОВЗ попадает в «Сведения»', async ({ page }) => {
  await login(page);
  await page.goto('/admin/team/new/');
  await formReady(page);
  await page.getByLabel('Имя').fill('Проверкина Анна Петровна');
  await page.getByLabel('Должность').fill('Педагог-организатор');
  await page.getByLabel('Педагог', { exact: true }).check();
  await page.getByLabel('Образование').fill('Высшее');
  await page.locator('[data-field="ovzResponsible"] label.switch').click();
  await page.locator('#f-photo').setInputFiles(JPG);
  await page.getByRole('button', { name: 'Сохранить' }).click();

  await expect(page.locator('#f-photo__alt-error')).toHaveText('Опишите, что на фото, — это нужно незрячим посетителям');
  await expect(page.getByLabel('Что на фото?')).toBeFocused();
  await expect(page.getByLabel('Имя')).toHaveValue('Проверкина Анна Петровна');

  await page.getByLabel('Что на фото?').fill('Анна в зелёной футболке у входа в лагерь');
  await formReady(page);
  await page.locator('#f-photo').setInputFiles(JPG); // браузер не хранит выбранный файл после ошибки
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');
  await expect(page.getByLabel('Что на фото?')).toHaveValue('Анна в зелёной футболке у входа в лагерь');

  await page.goto('/svedeniya/ob-organizacii/');
  await expect(page.locator('#sostav')).toContainText('Проверкина Анна Петровна');
  await expect(page.locator('[data-item$="sostav.b"], [data-item*="sostav.b"]').first()).toContainText('Проверкина Анна Петровна');

  await page.goto('/admin/team/');
  await page.locator('.row', { hasText: 'Проверкина Анна Петровна' }).getByRole('link', { name: 'Изменить' }).click();
  await removeCurrent(page);
});

test('«Скрыть» в списке вакансий убирает вакансию с сайта, «Показать» возвращает', async ({ page }) => {
  await login(page);
  await page.goto('/vacancies/');
  const title = (await page.locator('h2, h3').filter({ hasText: /Вожатый/ }).first().innerText()).trim();

  await page.goto('/admin/vacancies/');
  const row = page.locator('.row', { hasText: 'Вожатый' }).first();
  const hide = row.getByRole('button', { name: /Скрыть/ });
  await expect.poll(async () => (await hide.boundingBox())!.height).toBeGreaterThanOrEqual(44); // пока стили не загрузились, кнопка обычного роста
  await hide.click();
  await expect(page.getByRole('status')).toContainText('Видимость на сайте изменена');
  await expect(page.locator('.row', { hasText: 'Вожатый' }).first()).toContainText('Скрыто');
  await page.goto('/vacancies/');
  await expect(page.getByText(title)).toHaveCount(0);

  await page.goto('/admin/vacancies/');
  await page.locator('.row', { hasText: 'Вожатый' }).first().getByRole('button', { name: /Показать/ }).click();
  await expect(page.locator('.row', { hasText: 'Вожатый' }).first()).not.toContainText('Скрыто');
  await page.goto('/vacancies/');
  await expect(page.getByText(title).first()).toBeVisible();
});

test('редактор базы: два раздела с короткими названиями пунктов приказа; галерея — ↑ ↓ и «убрать»', async ({ page }) => {
  await login(page);
  await page.goto('/admin/venues/');
  await page.locator('.row').first().getByRole('link', { name: 'Изменить' }).click();
  await formReady(page); // порядок фото и «убрать» оживляет скрипт админки: без него клики уходят в пустоту

  await expect(page.getByRole('heading', { name: 'Материально-техническое обеспечение (п. 9)' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Доступная среда (п. 11)' })).toBeVisible();
  await expect(page.getByLabel('9 б) Условия проживания')).toBeVisible();
  const details = page.locator('details.legal').first();
  await expect(details).not.toHaveAttribute('open', '');
  await details.locator('summary').click();
  await expect(details).toContainText('о дате ввода в эксплуатацию');

  // порядок: первое фото вниз
  const keys = () => page.locator('input[name="photos__old[]"]').evaluateAll((els) => els.map((e) => (e as HTMLInputElement).value));
  const before = await keys();
  expect(before.length).toBeGreaterThan(2);
  await page.locator('[data-gallery-item]').first().locator('[data-gallery-down]').click();
  await expect.poll(keys).toEqual([before[1], before[0], ...before.slice(2)]);
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');
  expect(await keys()).toEqual([before[1], before[0], ...before.slice(2)]);

  // вернуть порядок
  await page.locator('[data-gallery-item]').first().locator('[data-gallery-down]').click();
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');
  expect(await keys()).toEqual(before);

  // новое фото: без подписи отказ, с подписью — добавлено, убрано — файлы удалены
  await formReady(page);
  await page.locator('#f-photos__new_1').setInputFiles(JPG);
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('#error-summary')).toContainText('Опишите, что на фото');
  await formReady(page);
  await page.locator('#f-photos__new_1').setInputFiles(JPG);
  await page.locator('#f-photos__newalt_1').fill('Новый корпус у леса');
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');
  expect((await keys()).length).toBe(before.length + 1);
  const last = (await keys())[before.length];
  expect(last).toMatch(/^[0-9a-f]{16}$/);
  const thumb = await page.locator('[data-gallery-item]').last().locator('img').getAttribute('src');
  expect((await page.request.get(thumb!)).status()).toBe(200);

  await page.locator(`input[name="photos__del_${last}"]`).check({ force: true });
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');
  expect(await keys()).toEqual(before);
  expect((await page.request.get(thumb!)).status()).toBe(404); // фото убрано из галереи: с сайта ушло (в карантин — для истории)
});

test('«Ещё фото» дважды: три новых фото с подписями сохраняются все три', async ({ page }) => {
  await login(page);
  await page.goto('/admin/venues/');
  await page.locator('.row').first().getByRole('link', { name: 'Изменить' }).click();
  await formReady(page);
  const keys = () => page.locator('input[name="photos__old[]"]').evaluateAll((els) => els.map((e) => (e as HTMLInputElement).value));
  const before = await keys();

  const add = page.getByRole('button', { name: 'Ещё фото' });
  await add.click();
  await add.click();
  // у каждого места своё имя поля: иначе PHP оставит только последнее (пустое) и фото пропадут
  await expect(page.locator('input[type=file][name^="photos__new_"]')).toHaveCount(3);
  expect(await page.locator('input[type=file][name^="photos__new_"]').evaluateAll((els) => els.map((e) => (e as HTMLInputElement).name)))
    .toEqual(['photos__new_1', 'photos__new_2', 'photos__new_3']);
  const files = [JPG, resolve('tests/fixtures/uploads/photo.png'), resolve('tests/fixtures/uploads/photo.webp')];
  for (const [i, file] of files.entries()) {
    await page.locator(`#f-photos__new_${i + 1}`).setInputFiles(file);
    await page.locator(`#f-photos__newalt_${i + 1}`).fill(`Новое фото ${i + 1}`);
    await expect(page.locator(`label[for="f-photos__new_${i + 1}"]`)).toContainText('Выбрано:');
  }
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');

  const after = await keys();
  expect(after.length).toBe(before.length + 3);
  const added = after.slice(before.length);
  for (const [i, key] of added.entries()) {
    expect(key).toMatch(/^[0-9a-f]{16}$/);
    await expect(page.locator(`input[name="photos__alt_${key}"]`)).toHaveValue(`Новое фото ${i + 1}`);
  }

  // вернуть как было
  await formReady(page);
  // флажок под липкой панелью кнопок: щелчок по координатам попал бы в неё
  for (const key of added) await page.locator(`input[name="photos__del_${key}"]`).dispatchEvent('click');
  for (const key of added) await expect(page.locator(`input[name="photos__del_${key}"]`)).toBeChecked();
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');
  expect(await keys()).toEqual(before);
});

test('вопросы: счётчик «На главной: N из 5»; отзывы: скрыть и показать', async ({ page }) => {
  await login(page);
  await page.goto('/admin/faq/');
  await expect(page.locator('#list-counter')).toHaveText(/^На главной: \d из 5$/);

  await page.goto('/admin/reviews/');
  const row = page.locator('.row').first();
  await row.getByRole('button', { name: /Скрыть/ }).click();
  await expect(page.locator('.row').first()).toContainText('Скрыто');
  await page.locator('.row').first().getByRole('button', { name: /Показать/ }).click();
  await expect(page.locator('.row').first()).not.toContainText('Скрыто');
});

test('подпись файла совпадает с полем, даже если файл выбран раньше, чем запустился скрипт админки', async ({ page }) => {
  await login(page);
  let release!: () => void;
  const scriptGate = new Promise<void>((resolveGate) => {
    release = resolveGate;
  });
  // скрипт админки «грузится медленно»: событие change от выбора файла проходит раньше, чем он подписался
  await page.route(/\/assets\/js\/admin\.js/, async (route) => {
    await scriptGate;
    await route.continue();
  });
  await page.goto('/admin/documents/new/', { waitUntil: 'commit' });
  await page.locator('#f-file').waitFor({ state: 'attached' });
  await page.locator('#f-file').setInputFiles(PDF);
  await expect(page.locator('label[for="f-file"]')).not.toContainText('Выбрано'); // скрипта ещё нет
  release();
  await expect(page.locator('label[for="f-file"]')).toContainText('Выбрано: real.pdf');
});

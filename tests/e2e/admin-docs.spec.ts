import { expect, test, type Page } from '@playwright/test';
import { resolve } from 'node:path';

// Тот же сервер и те же данные, что у admin-shifts.spec.ts (порт 8097, storage/test-admin), свой пользователь.
// Всё, что тест создаёт, он удаляет (вместе с загруженными файлами): общий каталог public/uploads остаётся чистым.
const PASSWORD = 'e2e-password-123';
const ADMIN = 'http://127.0.0.1:8097';
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

test('PDF в «Документы» с разделом «Учредительные документы» виден в «Сведениях»; замена и удаление чистят файлы', async ({ page }) => {
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

  // замена: старый файл больше не отдаётся, новый — отдаётся
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

  await removeCurrent(page);
  expect((await page.request.get(url2!)).status()).toBe(404);
  await page.goto('/svedeniya/ob-organizacii/');
  await expect(page.locator('#dokumenty')).not.toContainText('Устав e2e');
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
  expect(await keys()).toEqual([before[1], before[0], ...before.slice(2)]);
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
  expect((await page.request.get(thumb!)).status()).toBe(404);
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

import { expect, test } from '@playwright/test';

// Форма живёт на временной странице /_forms/booking/ до Task 9 (тогда — /booking/).
// Сервер сам требует ≥ 3 с между показом формы и отправкой, поэтому тест честно ждёт.
const FORM = '/_forms/booking/';
const WAIT_MS = 3300;

async function fill(page: import('@playwright/test').Page) {
  await page.getByLabel('Смена').selectOption('spasatel-2027');
  await page.getByLabel('Ваше имя').fill('Анна Иванова');
  await page.getByLabel('Телефон').fill('8 950 441-12-99');
  await page.getByLabel('Возраст ребёнка').fill('11');
  await page.getByLabel('Согласен на обработку персональных данных').check();
}

test('с JS: заявка уходит без перезагрузки и показывает «Спасибо! Мы перезвоним»', async ({ page }) => {
  await page.goto(FORM);
  await fill(page);
  await page.waitForTimeout(WAIT_MS);
  await page.getByRole('button', { name: 'Отправить заявку' }).click();
  await expect(page.getByText('Спасибо! Мы перезвоним')).toBeVisible();
  await expect(page).toHaveURL(new RegExp(`${FORM}$`));
});

test('с JS: пустая отправка — ошибки у полей и фокус на первом из них', async ({ page }) => {
  await page.goto(FORM);
  await page.waitForTimeout(WAIT_MS);
  await page.getByRole('button', { name: 'Отправить заявку' }).click();
  await expect(page.locator('#booking-shift-error')).toHaveText('Выберите смену из списка');
  await expect(page.locator('#booking-consent-error')).toHaveText('Нужно согласие на обработку данных');
  await expect(page.getByLabel('Смена')).toBeFocused();
  await expect(page.getByLabel('Смена')).toHaveAttribute('aria-invalid', 'true');
  // после исправления повторная отправка проходит сразу: принятая метка времени сохраняется
  await fill(page);
  await page.getByRole('button', { name: 'Отправить заявку' }).click();
  await expect(page.getByText('Спасибо! Мы перезвоним')).toBeVisible();
});

test('с JS: анкета — фокус на первом по порядку на странице поле с ошибкой', async ({ page }) => {
  await page.goto('/_forms/vacancy/');
  await page.waitForTimeout(WAIT_MS);
  await page.getByRole('button', { name: 'Отправить анкету' }).click();
  await expect(page.locator('#vacancy-fullName-error')).toBeVisible();
  // «Должность» стоит на странице первой, хотя в ответе сервера ошибка ФИО идёт раньше
  await expect(page.getByLabel('Должность')).toBeFocused();
});

test.describe('без JavaScript', () => {
  test.use({ javaScriptEnabled: false });

  test('успешная отправка открывает /spasibo/', async ({ page }) => {
    await page.goto(FORM);
    await fill(page);
    await page.waitForTimeout(WAIT_MS);
    await page.getByRole('button', { name: 'Отправить заявку' }).click();
    await expect(page).toHaveURL(/\/spasibo\/\?f=booking$/);
    await expect(page.getByRole('heading', { name: 'Спасибо! Мы перезвоним' })).toBeVisible();
  });

  test('ошибки проверки возвращают форму с введёнными значениями', async ({ page }) => {
    await page.goto(FORM);
    await page.getByLabel('Ваше имя').fill('Анна Иванова');
    await page.waitForTimeout(WAIT_MS);
    await page.getByRole('button', { name: 'Отправить заявку' }).click();
    await expect(page.getByLabel('Ваше имя')).toHaveValue('Анна Иванова');
    await expect(page.locator('#booking-phone-error')).toBeVisible();
  });
});

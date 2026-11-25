import { expect, test } from '@playwright/test';

// Форма заявки живёт на /booking/ (анкета — на /vacancies/).
// Сервер сам требует ≥ 3 с между показом формы и отправкой, поэтому тест честно ждёт.
const FORM = '/booking/';
const WAIT_MS = 3300;

async function fill(page: import('@playwright/test').Page) {
  await page.locator('#booking-shift').selectOption('spasatel-2027');
  await page.getByLabel('Ваше имя').fill('Анна Иванова');
  await page.getByLabel('Телефон').fill('8 950 441-12-99');
  await page.getByLabel('Возраст ребёнка').fill('11');
  await page.getByLabel('Согласен на обработку персональных данных').check();
}

test('карточка смены: «Забронировать» ведёт к форме с выбранной сменой', async ({ page }) => {
  await page.goto('/booking/');
  await page.getByRole('link', { name: 'Забронировать смену «Спасатель»' }).click();
  await expect(page).toHaveURL(/\/booking\/\?smena=spasatel-2027#zayavka$/);
  await expect(page.locator('#booking-shift')).toHaveValue('spasatel-2027');
  await expect(page.locator('#zayavka')).toBeInViewport();
  await expect(page.locator('#booking-shift')).toBeInViewport();
});

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
  await expect(page.locator('#booking-shift')).toBeFocused();
  await expect(page.locator('#booking-shift')).toHaveAttribute('aria-invalid', 'true');
  // после исправления повторная отправка проходит сразу: принятая метка времени сохраняется
  await fill(page);
  await page.getByRole('button', { name: 'Отправить заявку' }).click();
  await expect(page.getByText('Спасибо! Мы перезвоним')).toBeVisible();
});

test('с JS: анкета — фокус на первом по порядку на странице поле с ошибкой', async ({ page }) => {
  await page.goto('/vacancies/');
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

// Регрессия: на медленном устройстве load наступал посреди плавной прокрутки к #zayavka,
// ScrollTrigger.refresh возвращал позицию scrollTo и обрывал её — форма оставалась за экраном.
test('карточка смены на медленном устройстве: прокрутка к форме не обрывается', async ({ page }) => {
  await page.goto('/booking/');
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 6 });
  await page.route('**/assets/img/**', async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 700));
    await route.continue();
  });
  await page.getByRole('link', { name: 'Забронировать смену «Спасатель»' }).click();
  await expect(page).toHaveURL(/#zayavka$/);
  await expect(page.locator('#zayavka')).toBeInViewport({ timeout: 10_000 });
  await expect(page.locator('#booking-shift')).toBeInViewport();
});

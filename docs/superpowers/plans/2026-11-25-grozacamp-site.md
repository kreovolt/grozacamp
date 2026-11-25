# Сайт лагеря «Гроза»: план реализации

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Сайт grozacamp.ru на PHP без базы данных: публичные страницы в стиле «Экспедиция», раздел сведений по приказу № 201, формы заявок и собственная админка для офиса.

**Architecture:** Одна точка входа `public/index.php` → маленький роутер → контроллеры страниц собирают данные из JSON-файлов (`storage/data/*.json`) и отдают PHP-шаблоны. Админка правит те же JSON-файлы через общий слой `JsonStore` (атомарная запись, версии, защита от одновременной правки). CSS/JS собираются на компьютере разработчика (Tailwind 3.4, esbuild, GSAP); на хостинг уходят готовые файлы и `vendor/`.

**Tech Stack:** PHP ≥ 8.1 (разработка на 8.2), Composer (phpmailer/phpmailer, symfony/html-sanitizer, phpunit/phpunit ^10.5), Node 24 (tailwindcss 3.4, esbuild, gsap 3, trix 2, sharp, @fontsource/oswald, @fontsource/manrope, @playwright/test, @axe-core/playwright, basic-ftp).

**Spec:** `docs/superpowers/specs/2026-11-25-grozacamp-redesign-design.md` (дальше — «спека»). Вспомогательно: `.agents/product-marketing.md`, `content/old-site-content.md`.

## Global Constraints

- Код совместим с PHP 8.1: без readonly-классов, типизированных констант классов, `json_validate()`, `#[\Override]`. `declare(strict_types=1);` в каждом файле. Пространство имён `Groza\` → `app/` (PSR-4).
- Базы данных нет. Данные — `storage/data/{name}.json`, имена файлов только `[a-z0-9-]+`.
- Часовой пояс приложения — `Asia/Yekaterinburg` (Пермь). Все «сегодня/прошла ли смена» считаются в нём.
- Никаких внешних CDN, Google Fonts, reCAPTCHA, встраиваемых чужих скриптов. Единственный разрешённый внешний хост — Яндекс.Метрика (`mc.yandex.ru`), и только если задан `metrikaId`.
- Шрифты локально: Oswald 500/600/700 (заголовки, меню, кнопки, цифры), Manrope 400/600/700 (текст), подмножества cyrillic + latin, woff2.
- Цвета (Tailwind theme): `sand #f1ebdd`, `forest #22392c`, `moss #5e7a4b`, `rust #c8562f`, `rust-dark #b54a26` (фон кнопок с белым текстом), `gold #d9a93a`, `ink #1f2a22`, `paper #ffffff`.
- Адреса страниц — со слешем на конце (`/booking/`); без слеша → 301 на адрес со слешем.
- Подписи и порядок меню — ровно как в спеке §5.2 (тест в Task 4). Меню не редактируется из админки.
- Контакты по умолчанию (seed): телефон `+7 (342) 247-43-47`, WhatsApp `+7 999 123-93-00` (`https://wa.me/79991239300`), почта `2474347@bk.ru`, адрес офиса `г. Пермь, ул. Милиционера Власова, 37`, часы `Пн–Пт 10:00–19:00`, `Сб–Вс 12:00–17:00`, ВК `https://vk.com/grozacamp`. Получатель заявок — настройка `mailTo`, по умолчанию `2474347@bk.ru`.
- Бюджеты: главная при первом показе ≤ 1,5 МБ (ширина 375 px), герой ≤ 200 КБ, LCP ≤ 2,5 с (мобильный Lighthouse на тестовом адресе).
- Загрузки: фото JPEG/PNG/WebP ≤ 10 МБ и ≤ 25 млн пикселей; документы PDF ≤ 20 МБ. История — 20 последних версий на файл данных.
- Публичные формы не хранят заявки на сервере; в логах нет полей форм.
- Слова, которых нет в заголовках, первом экране и описаниях смен: «оружие», «бой», «огнев», «курсант», «противник», «боев», «красная зона», «боец». Названия смен («Штурмовик» и др.) — исключение.
- Не использовать фото `kids-running-field`, `spas-stage-cheer`, `living-dining`. Героем главной — `shoot-group-splash`.
- Дисциплина по стеку разработчика: ub-mode и TDD (сначала падающий тест), мелкие коммиты с понятным сообщением на русском или английском, в конце сообщения строка `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Интерфейс сайта и админки — на русском, `lang="ru"`.

## Review Focus

1. **Двое сохраняют одно и то же.** Офис и руководитель одновременно правят смены. Ожидание: второе сохранение не затирает молча первое, человек видит «кто-то уже сохранил» и свои введённые значения. → тест в Task 2 (`JsonStore` hash) и Task 16 (409 с сохранением ввода).
2. **Фото с телефона.** С iPhone приходит HEIC или огромный снимок. Ожидание: понятное сообщение «сохраните как JPEG» / «уменьшите фото», а не белый экран из-за нехватки памяти. → тесты в Task 15.
3. **Последний день смены и часовой пояс.** Сервер в UTC, лагерь в Перми (UTC+5). Ожидание: смена, которая заканчивается сегодня, видна весь день по пермскому времени. → тест в Task 3.
4. **Телефон в любом виде.** Родители пишут «8 950 441-12-99», «+7(950)4411299», «9504411299». Ожидание: принимается и в письме приходит в одном виде `+79504411299`. → тесты в Task 3 и Task 8.
5. **Длинный текст и истёкшая сессия.** Сотрудник полчаса пишет текст раздела, сессия истекла. Ожидание: набранное не пропадает. → тест черновика в Task 16 (localStorage) и в Task 18 (повторный показ формы с введённым текстом при истёкшем CSRF).

---

## Структура файлов

```
composer.json  package.json  phpunit.xml  playwright.config.ts  tailwind.config.js  .gitignore
public/                     ← корень сайта на хостинге
  index.php  .htaccess  robots.txt (генерируется роутом, файла нет)
  assets/  (css/app.css, css/admin.css, js/*.js, fonts/, img/, icons.svg, favicon*)
  uploads/ (img/, docs/, .htaccess без выполнения скриптов)   ← данные, не в git (кроме .htaccess)
app/
  bootstrap.php  App.php
  Http/       Request.php Response.php Router.php SecurityHeaders.php
  Support/    Clock.php SystemClock.php FixedClock.php Dates.php Phone.php Slug.php
              Session.php Csrf.php RateLimiter.php
  Data/       JsonStore.php ConflictException.php AuditLog.php Seeder.php
  Domain/     Collections.php Validator.php ValidationException.php Shifts.php
  View/       View.php Picture.php helpers.php
  Content/    Nav.php
  Forms/      FormGuard.php BookingForm.php VacancyForm.php ValidationResult.php
  Mail/       Message.php MailTransport.php SmtpTransport.php ArrayTransport.php FileTransport.php Mailer.php MailException.php
  Pages/      HomePage.php BookingPage.php ShiftPage.php FormController.php InfoPage.php VacanciesPage.php
              TeamPage.php ContactsPage.php TextPage.php SvedeniyaPage.php NotFoundPage.php SitemapController.php RobotsController.php
  Readiness/  Readiness.php
  Seo/        Meta.php Sitemap.php JsonLd.php
  Admin/      Auth.php Users.php Uploads.php ImageProcessor.php UploadException.php Sanitizer.php FormRenderer.php
              Controllers/ AuthController.php DashboardController.php CollectionController.php
                           TextsController.php SettingsController.php HistoryController.php BackupController.php
config/       config.php local.example.php routes.php nav.php texts.php order201.php redirects.php seo.php photos.json
templates/    layout.php partials/ components/ forms/ pages/ pages/svedeniya/ admin/
frontend/     css/app.css css/admin.css js/main.js js/nav.js js/forms.js js/motion.js js/admin.js js/admin-editor.js
seed/         data/*.json (только подтверждённое)  demo/*.json (примерные данные для разработки и тестов, не выкладываются)  files/docs/*.pdf
storage/      data/ versions/ logs/ ratelimit/ mail/ test/      ← не в git
scripts/      dev.mjs build-assets.mjs optimize-photos.mjs seed.php user.php readiness.php deploy.mjs check-host.php
tests/php/    Unit/** Support/TestApp.php        tests/e2e/*.spec.ts        tests/fixtures/
_source/      (оригиналы фото и шеврон, не в git — 239 МБ)
```

---

## Часть A. Публичный сайт

### Task 1: Каркас проекта, окружение, роутер

**Files:**
- Create: `.gitignore`, `composer.json`, `package.json`, `phpunit.xml`, `public/index.php`, `public/.htaccess`, `app/bootstrap.php`, `app/App.php`, `app/Http/{Request,Response,Router}.php`, `app/Support/{Clock,SystemClock,FixedClock}.php`, `config/{config.php,local.example.php,routes.php}`, `scripts/dev.mjs`, `tests/php/Support/TestApp.php`
- Test: `tests/php/Unit/Http/RouterTest.php`

**Interfaces:**
- Produces:
  - `Request::fromGlobals(): Request`; `Request::create(string $method, string $path, array $query = [], array $post = [], array $headers = [], array $files = [], string $ip = '127.0.0.1'): Request`; методы `method(): string`, `path(): string`, `query(string $k, ?string $default = null): ?string`, `post(string $k, $default = null)`, `allPost(): array`, `header(string $name): ?string`, `ip(): string`, `file(string $k): ?array`, `wantsJson(): bool` (Accept содержит `application/json`).
  - `Response::html(string $body, int $status = 200): Response`, `::redirect(string $url, int $status = 302)`, `::json(array $data, int $status = 200)`, `withHeader(string $n, string $v): Response`, `status(): int`, `body(): string`, `header(string $n): ?string`, `send(): void`.
  - `Router::get(string $pattern, callable $handler)`, `::post(...)`, `::redirects(array $map)` (точные пути → цель, 301), `::dispatch(Request $r): Response`. Шаблон `'/program/{slug}/'`, параметр — `[a-z0-9-]+`, хендлер вызывается как `$handler(Request $r, array $params): Response`.
  - `Clock::now(): DateTimeImmutable` (в `Asia/Yekaterinburg`); `FixedClock::__construct(string $iso)`.
  - `App::boot(array $config): App`; `App::handle(Request $r): Response`; геттеры `config(): array`, `clock(): Clock` (остальные сервисы добавляют следующие задачи).
  - `TestApp::make(array $overrides = []): App` — временная папка `storage`, данные из `seed/demo`, `FixedClock('2027-01-15T12:00:00+05:00')`; `TestApp::get(App $app, string $path, array $query = []): Response`, `TestApp::post(App $app, string $path, array $post, array $headers = []): Response`.
  - Конфиг (`config/config.php` сливает умолчания, `config/local.php` и переменные окружения): `env` (`dev|test|staging|prod`), `baseUrl`, `rootDir`, `storageDir`, `timezone`, `appSecret`, `mail{host,port,user,pass,secure,from,fromName}`, `staging{user,pass}`, `metrikaId`, `fakeNow` (только `test`).

- [ ] **Step 1: Окружение.** С согласия пользователя установить PHP 8.2 (`winget install --id PHP.PHP.8.2`), включить в `php.ini` расширения `gd`, `fileinfo`, `mbstring`, `openssl`, `zip`, `exif`; скачать Composer с getcomposer.org в `tools/composer.phar`. `git init` в корне проекта. `.gitignore`: `vendor/ node_modules/ storage/ public/uploads/* !public/uploads/.htaccess _source/ tools/ config/local.php config/deploy.local.json .superpowers/ test-results/ playwright-report/`.
- [ ] **Step 2: Зависимости.** `php tools/composer.phar require phpmailer/phpmailer symfony/html-sanitizer` и `--dev phpunit/phpunit:^10.5`; в `composer.json` — `"config": {"platform": {"php": "8.1.0"}}`, autoload `Groza\\` → `app/`. `npm i -D tailwindcss@3.4 esbuild gsap trix sharp @fontsource/oswald @fontsource/manrope @playwright/test @axe-core/playwright basic-ftp`. Скрипты npm: `dev`, `build`, `test` (`php vendor/bin/phpunit`), `e2e` (`playwright test`), `photos`, `deploy`.
- [ ] **Step 3: Падающий тест роутера.**

```php
final class RouterTest extends TestCase {
  public function test_get_route(): void { /* get('/booking/') → dispatch GET /booking/ → 200, body 'ok' */ }
  public function test_missing_trailing_slash_redirects(): void { /* GET /booking → 301, Location '/booking/' */ }
  public function test_param_route(): void { /* '/program/{slug}/' + GET /program/spasatel/ → $params['slug'] === 'spasatel' */ }
  public function test_bad_param_is_404(): void { /* GET /program/Спасатель/ → 404 */ }
  public function test_unknown_path_404(): void {}
  public function test_redirect_map_single_hop(): void { /* redirects(['/faq/' => '/info/#faq']); GET /faq → 301 Location '/info/#faq' (без промежуточного /faq/) */ }
  public function test_method_not_allowed(): void { /* POST на GET-маршрут → 405 */ }
}
```

- [ ] **Step 4: Запустить** `php vendor/bin/phpunit --filter RouterTest` → FAIL (классов нет).
- [ ] **Step 5: Реализовать** `Request`, `Response`, `Router`, `Clock`-классы, `App::boot/handle`, `config/config.php`, `config/routes.php` (пока только `/` → «ok»). `public/index.php` ищет `../app/bootstrap.php`, если нет — `./app/bootstrap.php` (на хостинге без отдельного корня сайта всё лежит в одной папке); под `cli-server` отдаёт существующие файлы из `public/` как есть. `public/.htaccess`: всё, что не файл, → `index.php`; запрет доступа к `app|config|storage|templates|vendor|seed|scripts|tests|frontend` и к файлам с точкой в начале имени.
- [ ] **Step 6: Запустить тест** → PASS. Проверить `npm run dev` (`scripts/dev.mjs` параллельно запускает `php -S 127.0.0.1:8080 -t public public/index.php`, `tailwindcss --watch`, `esbuild --watch`): `http://127.0.0.1:8080/` отвечает «ok».
- [ ] **Step 7: Commit** `chore: project skeleton, router, dev environment`.

### Task 2: Хранилище данных и журнал

**Files:**
- Create: `app/Data/{JsonStore,ConflictException,AuditLog}.php`
- Test: `tests/php/Unit/Data/{JsonStoreTest,AuditLogTest}.php`

**Interfaces:**
- Produces:
  - `JsonStore::__construct(string $dataDir, string $versionsDir, int $keepVersions = 20)`
  - `read(string $name, array $default = []): array`
  - `hash(string $name): string` — sha1 содержимого файла, `''` если файла нет
  - `updatedAt(string $name): ?DateTimeImmutable`
  - `write(string $name, array $data, string $actor, ?string $expectedHash = null): void` — бросает `ConflictException`, если `$expectedHash !== null` и не совпал с текущим
  - `versions(string $name): array` — `list<array{id:string, at:string, actor:string}>`, новые первыми
  - `restore(string $name, string $versionId, string $actor): void`
  - `AuditLog::__construct(string $file)`, `record(string $actor, string $action, string $target): void`, `recent(int $limit = 50): array`

- [ ] **Step 1: Падающие тесты.**

```php
test_roundtrip_keeps_cyrillic()        // write('settings', ['name'=>'Гроза']) → read() === ['name'=>'Гроза']; файл содержит «Гроза» без \u-экранирования
test_read_missing_returns_default()     // read('nope', ['items'=>[]]) === ['items'=>[]]
test_rejects_bad_names()                // write('../x', …) и write('Shifts', …) → InvalidArgumentException
test_each_write_saves_previous_version()// 2 записи → versions() count 1, actor и at заполнены
test_keeps_only_20_versions()           // 25 записей → count(versions()) === 20
test_restore_returns_old_content()      // restore(id) → read() равен старому; restore сам создаёт версию
test_stale_hash_throws_conflict()       // $h = hash(); write(...); write(..., $h) → ConflictException, файл не изменился   (Review Focus 1)
test_no_temp_files_left()               // после write в папке нет *.tmp
test_audit_appends_jsonl()              // record('anna','shifts.status','abc') → recent()[0] == ['actor'=>'anna','action'=>'shifts.status','target'=>'abc','at'=>…]
```

- [ ] **Step 2: Запустить** `php vendor/bin/phpunit --filter "JsonStoreTest|AuditLogTest"` → FAIL.
- [ ] **Step 3: Реализовать.** Запись: `flock(LOCK_EX)` на `{name}.lock` → копия текущего файла в `versions/{name}/{YmdHis}-{rand}.json` (+ `.meta` с actor) → запись во временный файл → `rename`. JSON: `JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR`. `App` получает `store(): JsonStore` и `audit(): AuditLog` (пути из `storageDir`).
- [ ] **Step 4: Запустить** → PASS.
- [ ] **Step 5: Commit** `feat: atomic JSON store with versions and conflict detection`.

### Task 3: Схемы данных, проверка, смены, даты, телефоны

**Files:**
- Create: `app/Domain/{Collections,Validator,ValidationException,Shifts}.php`, `app/Support/{Dates,Phone,Slug}.php`
- Test: `tests/php/Unit/Domain/{ValidatorTest,ShiftsTest}.php`, `tests/php/Unit/Support/{DatesTest,PhoneTest,SlugTest}.php`

**Interfaces:**
- Produces:
  - `Collections::all(): array` — `array<string, array{label:string, publicUrl:?string, fields: list<Field>}>`, где `Field = array{name, type, label, required?:bool, max?:int, options?:array<string,string>, help?:string}`; `type ∈ text|textarea|richtext|int|date|enum|bool|image|file|list|ref`. Коллекции и поля — по спеке §8.1–8.4, плюс: `vacancies {title, summary, conditions(richtext), hidden}`, `faq {question, answer(richtext), onHome}`, `reviews {text, author, visible}`. Перечисления: `status = soon|open|few|closed`; `team.group = leadership|pedagogue|counselor|other`; `documents.section = uchreditelnye|pravila|programma-vospitaniya|kalendarnyj-plan|programma-razvitiya|inoe`. У `venues` 13 полей доступной среды (п. 11 приказа, а–н) с ключами `access_a, access_b, access_v, access_g, access_d, access_e, access_zh, access_z, access_i, access_k, access_l, access_m, access_n` и 5 полей МТО (п. 9, а–д) `mto_a, mto_b, mto_v, mto_g, mto_d`; все — `textarea`.
  - `Collections::get(string $name): array` (бросает `InvalidArgumentException` для неизвестной).
  - `Validator::validate(array $collectionDef, array $input): array` — возвращает нормализованную запись или бросает `ValidationException` с `errors(): array<string,string>` (сообщения на русском).
  - `Shifts::days(string $start, string $end): int` (включительно); `::visible(array $items, DateTimeImmutable $now): array`; `::nearestSeason(array $items, DateTimeImmutable $now): ?string`; `::costPerDay(?int $price, int $days): ?int`; `Shifts::STATUS_LABELS = ['soon'=>'Скоро','open'=>'Идёт набор','few'=>'Мало мест','closed'=>'Мест нет']`.
  - `Dates::ru(string $ymd): string`, `Dates::range(string $start, string $end): string`.
  - `Phone::normalize(string $raw): ?string`, `Phone::pretty(string $e164): string` (`+7 (950) 441-12-99`).
  - `Slug::fromRu(string $s): string`.

- [ ] **Step 1: Падающие тесты.**

```php
// ShiftsTest
assertSame(21, Shifts::days('2026-07-02', '2026-07-22'));
assertSame(14, Shifts::days('2026-08-17', '2026-08-30'));
assertSame(2857, Shifts::costPerDay(60000, 21));
assertNull(Shifts::costPerDay(null, 21));
test_visible_excludes_hidden_and_past_sorts_by_start();
test_shift_ending_today_visible_late_evening_perm();   // now = 2027-07-22T23:30:00+05:00, dateEnd 2027-07-22 → видна  (Review Focus 3)
test_nearest_season_null_when_none();
// ValidatorTest (коллекция shifts)
test_title_required();                // errors['title'] === 'Заполните поле'
test_end_before_start_rejected();     // errors['dateEnd']
test_status_enum();                   // 'maybe' → errors['status']
test_price_normalized();              // '60 000' → 60000; '' → null
test_slug_from_title_when_empty();    // 'Спасатель' → 'spasatel'
test_days_computed();                 // в результате 'days' === 21
// DatesTest
assertSame('2–22 июля 2027', Dates::range('2027-07-02', '2027-07-22'));
assertSame('25 июля – 14 августа 2027', Dates::range('2027-07-25', '2027-08-14'));
// PhoneTest  (Review Focus 4)
foreach (['8 950 441-12-99', '+7(950)4411299', '9504411299', '8-950-441-12-99'] as $raw) assertSame('+79504411299', Phone::normalize($raw));
assertNull(Phone::normalize('12345'));
assertSame('+7 (950) 441-12-99', Phone::pretty('+79504411299'));
// SlugTest
assertSame('pogranichnik', Slug::fromRu('Пограничник'));
assertSame('leto-2027', Slug::fromRu('Лето 2027'));
```

- [ ] **Step 2: Запустить** `php vendor/bin/phpunit --filter "ShiftsTest|ValidatorTest|DatesTest|PhoneTest|SlugTest"` → FAIL.
- [ ] **Step 3: Реализовать.** Транслит — таблица ГОСТ 7.79-2000 (вариант Б), без апострофов. Валидатор: `text|textarea` — trim + обрезка `max`, удаление управляющих символов; `int` — только цифры после удаления пробелов; `date` — `Y-m-d`; `richtext` — пропускает как есть (очистку делает Task 18); `image|file` — массив-дескриптор или `null`; `list` — массив непустых строк; `ref` — slug из указанной коллекции.
- [ ] **Step 4: Запустить** → PASS.
- [ ] **Step 5: Commit** `feat: data schemas, validation, shift and date helpers`.

### Task 4: Шаблоны, дизайн-система, шапка, меню, подвал

Перед вёрсткой загрузить скиллы `impeccable`, `tailwindcss`, `no-ai-design-slop`. Эталон вида — вариант B в `.superpowers/brainstorm/666-1795589771/content/visual-style.html` (CSS-класс `.B`).

**Files:**
- Create: `app/View/{View,helpers}.php`, `app/Content/Nav.php`, `config/nav.php`, `templates/layout.php`, `templates/partials/{topbar,header,nav-desktop,nav-mobile,footer,breadcrumbs}.php`, `templates/components/{button,section-head,topo,polaroid,stats}.php`, `frontend/css/app.css`, `tailwind.config.js`, `frontend/js/{main,nav}.js`, `scripts/build-assets.mjs`, `public/assets/fonts/*`, `public/assets/icons.svg`, `playwright.config.ts`
- Test: `tests/php/Unit/View/{ViewTest,NavTest}.php`, `tests/e2e/nav.spec.ts`

**Interfaces:**
- Consumes: `App` (Task 1).
- Produces:
  - `View::__construct(string $templatesDir, array $globals = [])`; `render(string $template, array $vars = []): string`. Внутри шаблона: `$this->layout(string $name, array $vars = []): void`, `$this->partial(string $name, array $vars = []): string`.
  - Хелперы (`app/View/helpers.php`, функции в пространстве `Groza\View`): `e(?string $s): string`, `pending(?string $value): string` (пусто → `<span class="pending">Информация обновляется</span>`, иначе экранированное значение), `asset(string $path): string` (добавляет `?v=` из `public/assets/manifest.json`), `icon(string $name, string $class = ''): string`, `tel(string $e164): string` (`tel:+7…`).
  - `Nav::main(): array` — `list<array{label:string, href:string, kind:'button'|'link'|'dropdown', children?:list<array{label,href,children?}>}>`.
  - `App::view(): View`; глобальные переменные шаблонов: `settings`, `nav`, `currentPath`, `env`.

- [ ] **Step 1: Падающие тесты.**

```php
// NavTest
assertSame(['Купить путёвку', 'Информация для родителей', 'Вакансии', 'Сведения об организации отдыха детей и их оздоровления'], array_column(Nav::main(), 'label'));
assertSame(['/booking/', '/info/', '/vacancies/', '/svedeniya/'], array_column(Nav::main(), 'href'));
assertSame('button', Nav::main()[0]['kind']);
$dd = Nav::main()[3]['children'];
assertSame(['Об организации отдыха детей и их оздоровления', 'Деятельность', 'Материально-техническое обеспечение и оснащённость', 'Услуги, в том числе платные', 'Доступная среда', 'Иная информация'], array_column($dd, 'label'));
assertSame(['Основные сведения', 'Документы', 'Руководство', 'Педагогический и вожатский состав', 'Контакты'], array_column($dd[0]['children'], 'label'));
assertSame(['/svedeniya/ob-organizacii/#osnovnye-svedeniya', '/svedeniya/ob-organizacii/#dokumenty', '/svedeniya/ob-organizacii/#rukovodstvo', '/svedeniya/ob-organizacii/#sostav', '/svedeniya/ob-organizacii/#kontakty'], array_column($dd[0]['children'], 'href'));
assertSame(['/svedeniya/ob-organizacii/', '/svedeniya/deyatelnost/', '/svedeniya/mto/', '/svedeniya/uslugi/', '/svedeniya/dostupnaya-sreda/', '/svedeniya/inaya-informaciya/'], array_column($dd, 'href'));
// ViewTest
assertSame('&lt;script&gt;', e('<script>'));
assertStringContainsString('Информация обновляется', pending(''));
assertSame('a&amp;b', pending('a&b'));
test_layout_wraps_content_and_sets_title();
```

```ts
// nav.spec.ts (1440×900): пункты шапки в порядке из NavTest; клик и Enter по «Сведения…» открывают список (aria-expanded="true"), Esc закрывает и возвращает фокус на кнопку;
// клик мимо закрывает. (375×812): бургер открывает меню, «Сведения…» раскрывается аккордеоном с 11 пунктами; ссылка «Позвонить» имеет href "tel:+73422474347".
```

- [ ] **Step 2: Запустить** `php vendor/bin/phpunit --filter "NavTest|ViewTest"` → FAIL.
- [ ] **Step 3: Реализовать** `View`, хелперы, `Nav` (данные в `config/nav.php`), шаблоны шапки и подвала по спеке §5.2–5.3 (верхняя полоса с телефоном, WhatsApp, ВК и часами; бургер ниже ширины, где 4 пункта не помещаются; подбирается по факту, ориентир 1200 px). Tailwind-тема с токенами из Global Constraints, шрифты из `@fontsource` копируются `scripts/build-assets.mjs` в `public/assets/fonts/`, там же сборка CSS/JS с хешами в `public/assets/manifest.json`. Компоненты: кнопка (`rust-dark`, Oswald, верхний регистр), заголовок секции с кикером (gold, разрядка .2em), топо-линии (SVG из макета B), полароид (белая рамка 8 px, поворот −2…+1,5°, тень), полоса цифр.
- [ ] **Step 4: Запустить** PHP-тесты → PASS; `npm run e2e -- nav.spec.ts` → PASS (webServer в `playwright.config.ts`: `php -S 127.0.0.1:8099 -t public public/index.php`, `APP_ENV=test`; заполнение тестовыми данными перед прогоном добавляет Task 6).
- [ ] **Step 5: Commit** `feat: layout, design tokens, header navigation and footer`.

### Task 5: Фото: подготовка и вывод

**Files:**
- Create: `config/photos.json`, `scripts/optimize-photos.mjs`, `app/View/Picture.php`, `public/assets/img/*` (результат, коммитится), `public/assets/img/manifest.json`, `public/assets/favicon.{ico,svg}`, `public/assets/apple-touch-icon.png`, `public/assets/og-default.jpg`, `public/assets/logo-header.{webp,png}`
- Test: `tests/php/Unit/View/PictureTest.php`

**Interfaces:**
- Produces:
  - Дескриптор фото: `array{src:string, widths:list<int>, formats:list<string>, w:int, h:int}`; файлы лежат как `{src}-{width}.{format}`.
  - `picture(array $img, string $alt, string $sizes = '100vw', bool $eager = false, string $class = ''): string` (функция в `Groza\View`, реализация в `Picture.php`).
  - `photo(string $key): array` — дескриптор из `public/assets/img/manifest.json`.

- [ ] **Step 1: Падающий тест.**

```php
$img = ['src'=>'/assets/img/hero-river','widths'=>[480,960,1440,1920],'formats'=>['avif','webp','jpg'],'w'=>1920,'h'=>1280];
$html = picture($img, 'Ребята у реки', '100vw');
// первым <source type="image/avif">, затем webp; srcset содержит "/assets/img/hero-river-480.avif 480w"
// <img src="/assets/img/hero-river-960.jpg" width="1920" height="1280" loading="lazy" decoding="async" alt="Ребята у реки">
assertStringContainsString('fetchpriority="high"', picture($img, 'x', '100vw', true));
assertStringContainsString('loading="eager"', picture($img, 'x', '100vw', true));
assertStringNotContainsString('image/avif', picture([...$img, 'formats'=>['webp','jpg']], 'x'));
assertStringContainsString('alt="&quot;x&quot;"', picture($img, '"x"'));
```

- [ ] **Step 2: Запустить** `php vendor/bin/phpunit --filter PictureTest` → FAIL.
- [ ] **Step 3: Реализовать** `Picture.php`. `config/photos.json` — ключ → `{file, widths?, position?}`; ключи и источники (`_source/photos/`): `hero-river`←`shoot-group-splash` (ширины 480/960/1440/1920), `kids-evening`←`kids-friends-hug-evening`, `kids-girls`←`kids-girls-friends`, `kids-boy`←`kids-smiling-boy-v`, `kids-bubbles`←`kids-girl-bubbles-v`, `kids-teens`←`kids-teens-group`, `kids-gazebo`←`kids-group-gazebo`, `kids-tent`←`kids-sunny-tent`, `counselors`←`shoot-counselors`, `counselors-kids`←`shoot-counselors-kids`, `squad-river`←`shoot-squad-river`, `toss`←`shoot-toss-hero`, `spas-mchs`←`spas-group-mchs`, `spas-demo`←`spas-rescuer-demo`, `spas-fire`←`spas-firefighter-kid`, `spas-laugh`←`spas-facepaint-laugh`, `sturm-smile`←`sturm-kids-group-smile`, `sturm-relay`←`sturm-relay-race`, `sturm-theatre`←`sturm-theatre-costumes`, `sturm-cheer`←`sturm-group-cheer`, `base-cabins`←`living-aframe-cabins`, `base-cabins-row`←`living-cabins-row`, `base-room`←`living-room-beds`, `base-bunk`←`living-room-bunk`, `base-wash`←`living-washbasin`, `base-building`←`living-building-wide`, `base-stage`←`living-stage-hall`, `base-gazebo`←`living-gazebo`, `base-rafts`←`living-rafts`, `base-sports`←`living-sports-bars`, `base-playground`←`living-playground-v`. Остальные ширины — 480/960/1440. Сжатие sharp: avif q50, webp q72, jpg q78 mozjpeg. Из шеврона: фавиконы 32/180 px, логотип шапки 104 px высотой (2×). OG 1200×630 — кроп героя.
- [ ] **Step 4: Запустить** `npm run photos`, затем тест → PASS. Проверить: `hero-river-1920.avif` ≤ 200 КБ (иначе снизить качество для героя).
- [ ] **Step 5: Commit** `feat: photo pipeline and responsive picture helper`.

### Task 6: Стартовые данные и тексты

Для текстов загрузить скиллы `copywriting` и `humanizer`; тон и слова — из `.agents/product-marketing.md`; факты — только из `content/old-site-content.md`, неподтверждённое оставлять пустым.

**Files:**
- Create: `config/texts.php`, `app/Data/Seeder.php`, `scripts/seed.php`, `seed/data/{settings,texts,shifts,venues,team,documents,vacancies,faq,reviews}.json`, `seed/demo/*.json`, `seed/files/docs/*.pdf`, `tests/e2e/global-setup.ts`
- Modify: `playwright.config.ts` (`globalSetup` запускает `php scripts/seed.php --demo --target=storage/test --force`)
- Test: `tests/php/Unit/Data/{SeedTest,CopyLintTest}.php`

**Interfaces:**
- Consumes: `Collections`, `Validator` (Task 3), `JsonStore` (Task 2).
- Produces:
  - `config/texts.php` — реестр текстовых блоков: `array<string, array{label:string, type:'plain'|'richtext'|'list', group:'home'|'info'|'svedeniya'|'vacancies'|'legal', required?:string}>`, где `required` — номер пункта приказа (напр. `'10.а'`), если блок обязателен. Ключи: `home.heroKicker`, `home.heroTitle`, `home.heroLead`, `home.stats` (list «значение|подпись»), `home.included` (list), `home.schedule` (list «время|дело»), `info.prepare`, `info.contraindications`, `info.medicines`, `info.contact`, `info.transfer`, `info.visits`, `lists.packing` (list, обязательный 10.д), `lists.documents` (list, 10.г), `rules` (richtext, 4.б), `policy`, `sved.campType` (3.г), `sved.legalForm` (3.в), `sved.fullName` (3.а), `sved.shortName`, `sved.director` (3.б), `sved.addresses` (list, 3.д), `sved.mode` (3.е), `sved.ages` (8.а), `sved.eduPrograms` (8.в), `sved.healthPrograms` (8.г), `sved.methods` (8.д), `sved.paidOrder` (10.а), `sved.compensation` (10.в), `sved.other`, `vacancies.intro`, `vacancies.benefits`, `vacancies.selection`.
  - `Seeder::seedMissing(JsonStore $store, string $seedDir): void` — кладёт файл из seed, только если в `storage/data` его нет; вызывается в `App::boot` (на хостинге без SSH данные появятся при первом запросе).
  - `php scripts/seed.php [--demo] [--target=DIR] [--force]` (`--force` запрещён при `env=prod`).
  - Структура коллекций в JSON: `{"items": [ {...запись, "id": "...", "updatedAt": "...", "updatedBy": "..."} ]}`; `settings.json` и `texts.json` — объекты.

- [ ] **Step 1: Падающие тесты.**

```php
// SeedTest
test_every_seed_collection_validates();          // и seed/data, и seed/demo: каждая запись проходит Validator::validate
test_texts_json_has_every_registry_key();         // array_keys(config/texts.php) ⊆ array_keys(texts.json)
test_demo_has_open_shifts_after_fake_now();       // в seed/demo ≥ 3 смены сезона «Лето 2027 (пример)», видимые при 2027-01-15
test_data_seed_has_no_demo_marks();               // в seed/data нет строки «(пример)»
test_seed_missing_does_not_overwrite();           // существующий storage/data/settings.json не меняется
// CopyLintTest — анти-цель «мама пугается»
test_headings_and_leads_avoid_scary_words();      // texts home.*, shifts.lead/activities (seed/data и seed/demo): нет подстрок из Global Constraints, регистр не важен; title смены не проверяется
```

- [ ] **Step 2: Запустить** `php vendor/bin/phpunit --filter "SeedTest|CopyLintTest"` → FAIL.
- [ ] **Step 3: Реализовать** `Seeder`, `scripts/seed.php`, `config/texts.php`.
- [ ] **Step 4: Наполнить `seed/data`** (новые тексты, не копия старого сайта и не suncamp59.ru): `settings` — контакты из Global Constraints, `vkReviewsUrl = https://vk.com/topic-185063757_43871120`, `youtube`; `texts` — герой («Лагерь настоящих приключений»), полоса цифр (`2016|работаем с этого года`, `40+|проведённых смен`, `до 24|детей в отряде, 2 вожатых`, `5 раз|питание в день`), «что входит в стоимость», распорядок дня из программы 2024, блоки `/info/`, правила (вычитанный текст старых правил), политика ПД (переработанная старая, оператор — пусто до ответа заказчика), `sved.director` = «Лучников Евгений Алексеевич»; `team` — 25 человек со старой `/team` (ФИО, должность, `group`; образование пусто); `venues` — `solnyshko` (ДЗОЛ «Солнышко», дер. Коляды, Усть-Качкинское сельское поселение, Пермский район; фото `base-*`) и `vostok` (ДЗОЛ «Восток», с. Усть-Качка); `faq` — 11 вопросов старого FAQ, переписанных, 5 с `onHome`; `shifts`, `reviews`, `vacancies`, `documents` — пустые `items`. С согласия пользователя скачать из веб-архива PDF программы воспитания 2026 и календарного плана 2026 (ссылки в `old-site-content.md` §0) в `seed/files/docs/`, но в `documents.json` не добавлять — только после подтверждения заказчика.
- [ ] **Step 5: Наполнить `seed/demo`** — копия `seed/data` + 3 смены «Лето 2027 (пример)» (даты 2027-07-02…22, 2027-07-25…08-14, 2027-08-17…30; цены 60000/60000/40000; статусы open/few/closed; база `solnyshko`), 1 короткая смена «Весенние каникулы 2027 (пример)» (база `vostok`, 7–14 лет), 3 отзыва, 2 вакансии, 2 документа, у части команды заполнено образование.
- [ ] **Step 6: Запустить тесты** → PASS.
- [ ] **Step 7: Commit** `feat: seed data, text registry, copy lint`.

### Task 7: Главная

Скиллы: `impeccable`, `cro`, `copywriting`.

**Files:**
- Create: `app/Pages/HomePage.php`, `templates/pages/home.php`, `templates/components/{shift-card,steps,included,faq-list,review,cta-final,team-strip}.php`
- Modify: `config/routes.php`
- Test: `tests/php/Unit/Pages/HomePageTest.php`, `tests/e2e/home.spec.ts`

**Interfaces:**
- Consumes: `JsonStore`, `Shifts`, `View`, `picture()`, `photo()`, `Nav`.
- Produces: `HomePage::__construct(App $app)`, `__invoke(Request $r, array $params): Response`. Компонент `templates/components/shift-card.php` принимает `['shift'=>array, 'venue'=>?array, 'bookingHref'=>string]` — переиспользуется в Task 9.

- [ ] **Step 1: Падающие тесты.**

```php
test_kicker_override_wins();             // texts home.heroKicker = 'Своё' → в HTML 'Своё'
test_kicker_auto_from_nearest_season();  // пустой override → 'Смены: Лето 2027 (пример) · Пермский край'
test_kicker_without_shifts();            // нет видимых смен → 'Детский лагерь · Пермский край'
test_shift_cards_match_visible();        // количество .shift-card == count(Shifts::visible(...))
test_team_strip_max_6_on_home();
test_faq_max_5_on_home();
test_reviews_fallback_to_vk_link();      // нет видимых отзывов → ссылка на settings.vkReviewsUrl
```

```ts
// home.spec.ts: h1 'Лагерь настоящих приключений'; кнопка 'Выбрать смену' → '/booking/'; картинка героя loading="eager";
// в полосе цифр 4 пункта и нет текста '24/7'; блоки по спеке §7.1 в порядке 1–12 (data-block="hero|stats|shifts|kids|care|steps|included|base|team|reviews|faq|cta").
```

- [ ] **Step 2: Запустить** `php vendor/bin/phpunit --filter HomePageTest` → FAIL.
- [ ] **Step 3: Реализовать** страницу по спеке §7.1 (12 блоков; тексты блоков 4–7 и 12, не перечисленные в реестре `config/texts.php`, — прямо в шаблоне). «Распорядок дня» — `<details>` без JS. «Где живём» — фото баз, у которых есть видимые смены.
- [ ] **Step 4: Запустить** PHP-тест и `npm run e2e -- home.spec.ts` → PASS. Посмотреть страницу в браузере на 1440 и 375 px, сравнить с макетом B.
- [ ] **Step 5: Commit** `feat: home page`.

### Task 8: Формы: защита, проверка, письма

**Files:**
- Create: `app/Forms/{FormGuard,BookingForm,VacancyForm,ValidationResult}.php`, `app/Support/RateLimiter.php`, `app/Mail/{Message,MailTransport,SmtpTransport,ArrayTransport,FileTransport,Mailer,MailException}.php`, `app/Pages/FormController.php`, `templates/forms/{booking,vacancy,_field}.php`, `templates/pages/{thanks,form-error}.php`, `frontend/js/forms.js`
- Modify: `config/routes.php` (`POST /form/booking/`, `POST /form/vacancy/`, `GET /spasibo/`)
- Test: `tests/php/Unit/Forms/{FormGuardTest,BookingFormTest,VacancyFormTest}.php`, `tests/php/Unit/Mail/MailerTest.php`, `tests/php/Unit/Pages/FormControllerTest.php`, `tests/e2e/forms.spec.ts`

**Interfaces:**
- Consumes: `Phone`, `Clock`, `JsonStore` (settings, shifts, vacancies).
- Produces:
  - `RateLimiter::__construct(string $dir, Clock $clock)`, `hit(string $bucket, string $key, int $max, int $windowSec): bool` (true — можно; ключ хранится как sha256 с `appSecret`).
  - `FormGuard::__construct(string $secret, RateLimiter $limiter, Clock $clock, string $baseUrl)`; `fields(string $formId): array{_ts:string, _sig:string}` (скрытые поля; поле-ловушка называется `website`); `check(Request $r, string $formId): ?string` → `null` или `'honeypot'|'too_fast'|'expired'|'bad_signature'|'bad_origin'|'rate_limited'`. Пороги: быстрее 3 с — `too_fast`, старше 2 ч — `expired`, больше 5 отправок за 10 мин с одного IP на форму — `rate_limited`; `Origin`/`Referer` с чужим хостом — `bad_origin`.
  - `ValidationResult{ public array $data; public array $errors; ok(): bool }`.
  - `BookingForm::validate(array $post, array $openShiftSlugs): ValidationResult` — поля `shift` (slug из списка или `any`), `parentName` (2–80), `phone` (`Phone::normalize`), `childAge` (5–18), `comment` (≤ 1000, необязательно), `consent === 'yes'`.
  - `VacancyForm::validate(array $post, array $positions): ValidationResult` — `fullName`, `phone`, `email` (необязательно, валидный), `age` (16–80), `position` (из списка или `Другое`), `experience` (≤ 2000), `shifts` (≤ 300), `vk` (необязательно, `https://vk.com/…`), `consent`.
  - `Message{to, subject, text}`; `MailTransport::send(Message $m): void` (бросает `MailException`); `ArrayTransport::$sent`; `FileTransport(string $dir)` пишет `.eml` (для `dev`); `SmtpTransport(array $mailConfig)` на PHPMailer.
  - `Mailer::__construct(MailTransport $t, string $to)`; `bookingRequest(array $data, ?array $shift): void`; `vacancyApplication(array $data): void`. Тема: `Заявка на путёвку: {смена или «смена не выбрана»} — {parentName}`, `Анкета на вакансию: {position} — {fullName}`; из полей, попадающих в тему, удаляются `\r` и `\n`.
  - `App::guard(): FormGuard`, `App::mailer(): Mailer` (`test` → `ArrayTransport`, `dev` → `FileTransport(storage/mail)`, иначе SMTP).
  - Шаблоны форм принимают `['values'=>array, 'errors'=>array, 'options'=>array, 'guard'=>array]`.

- [ ] **Step 1: Падающие тесты.**

```php
// FormGuardTest
test_honeypot();        // website='x' → 'honeypot'
test_too_fast();        // ts 1 с назад → 'too_fast'
test_expired();         // ts 3 ч назад → 'expired'
test_tampered_sig();    // изменённый _sig → 'bad_signature'
test_foreign_origin();  // Origin: https://evil.example → 'bad_origin'
test_rate_limit();      // 6-я корректная отправка за 10 мин с IP → 'rate_limited'
test_valid();           // → null
// BookingFormTest
test_phone_formats_normalized();   // '8 950 441-12-99' → data['phone'] === '+79504411299'   (Review Focus 4)
test_unknown_shift_rejected();     // shift 'nope' → errors['shift']
test_any_shift_allowed();          // shift 'any' → ok
test_consent_required();           // errors['consent'] === 'Нужно согласие на обработку данных'
test_crlf_removed_from_name();     // "Анна\r\nBcc: x@y" → "Анна Bcc: x@y"
// MailerTest
test_booking_mail();               // ArrayTransport: 1 письмо, to = settings.mailTo, тема 'Заявка на путёвку: Спасатель — Иванова Анна', в тексте '+7 (950) 441-12-99'
test_subject_has_no_newlines();
// FormControllerTest
test_success_without_js_redirects();   // 303, Location '/spasibo/?f=booking'
test_success_with_js_returns_json();   // Accept: application/json → {"ok":true}
test_honeypot_pretends_success();      // 303 /spasibo/, ArrayTransport пуст
test_validation_error_rerenders_with_values(); // 422, в HTML введённое имя и текст ошибки у поля
test_mail_failure_shows_phone();       // транспорт бросает → 503, в теле '+7 (342) 247-43-47' и 'wa.me/79991239300'; JSON → {"ok":false,"fallback":true}
test_rate_limited_page();              // 429, в теле телефон
test_log_has_no_personal_data();       // после ошибок storage/logs/*.log не содержит имени и телефона
```

```ts
// forms.spec.ts: с JS — отправка заявки показывает на месте 'Спасибо! Мы перезвоним' (ожидание ≥ 3 с перед отправкой);
// с javaScriptEnabled: false — после отправки открывается /spasibo/; пустая отправка — ошибки у полей, фокус на первом поле с ошибкой.
```

- [ ] **Step 2: Запустить** `php vendor/bin/phpunit --filter "FormGuardTest|BookingFormTest|VacancyFormTest|MailerTest|FormControllerTest"` → FAIL.
- [ ] **Step 3: Реализовать.** Ошибки проверки без JS: `POST /form/booking/` заново рисует страницу `/booking/` (через `BookingPage` из Task 9 — до него временно отдельный шаблон формы), `POST /form/vacancy/` — `/vacancies/`. В логах — только `formId` и код ошибки.
- [ ] **Step 4: Запустить** → PASS (e2e — после Task 9, когда форма стоит на странице).
- [ ] **Step 5: Commit** `feat: public forms with spam protection and email delivery`.

### Task 9: «Купить путёвку» и страница смены

Скиллы: `cro`, `copywriting`.

**Files:**
- Create: `app/Pages/{BookingPage,ShiftPage}.php`, `templates/pages/{booking,shift}.php`
- Modify: `config/routes.php`, `app/Pages/FormController.php` (ошибки → `BookingPage`)
- Test: `tests/php/Unit/Pages/{BookingPageTest,ShiftPageTest}.php`, дописать `tests/e2e/forms.spec.ts`

**Interfaces:**
- Consumes: `shift-card` (Task 7), формы (Task 8), `Shifts`, `Dates`.
- Produces: `BookingPage::render(Request $r, array $values = [], array $errors = [], int $status = 200): Response`; `ShiftPage::__invoke(Request $r, array $params): Response`; `config/redirects.php` → ключ `legacyShiftSlugs` (список старых slug из `old-site-content.md` §2, таблица «История карточек смен») — используется здесь и в Task 12.

- [ ] **Step 1: Падающие тесты.**

```php
test_lists_only_visible_shifts();
test_closed_shift_has_no_booking_button();       // карточка 'Мест нет', кнопки «Забронировать» нет
test_query_preselects_shift();                   // ?smena=spasatel-2027 → <option value="spasatel-2027" selected>
test_unknown_query_ignored();
test_no_shifts_shows_soon_text();                // 'Даты скоро. Оставьте заявку — позвоним первыми'; в select только 'Пока не знаю / любая'
test_shift_page_404_for_unknown();
test_legacy_slug_redirects_to_booking();         // /program/leto-v-groze-1-smena-2/ → 301 /booking/
test_past_shift_page();                          // 'Смена завершена' + ссылка /booking/
test_price_missing_text();                       // price null → 'Стоимость уточняйте в офисе' и телефон
test_shift_page_shows_venue_and_included();
```

```ts
// forms.spec.ts (+): клик «Забронировать» на карточке → /booking/?smena=…#zayavka, смена выбрана в форме, форма в зоне видимости.
```

- [ ] **Step 2: Запустить** → FAIL.
- [ ] **Step 3: Реализовать** по спеке §7.2–7.3. Ссылка «Забронировать»: `/booking/?smena={slug}#zayavka`.
- [ ] **Step 4: Запустить** PHP-тесты и `npm run e2e -- forms.spec.ts` → PASS.
- [ ] **Step 5: Commit** `feat: booking and shift pages`.

### Task 10: Родителям, вакансии, команда, контакты, правила, политика, 404

**Files:**
- Create: `app/Pages/{InfoPage,VacanciesPage,TeamPage,ContactsPage,TextPage,NotFoundPage}.php`, `templates/pages/{info,vacancies,team,contacts,text,404}.php`, `templates/components/toc.php`
- Modify: `config/routes.php`, `app/Pages/FormController.php` (ошибки анкеты → `VacanciesPage`)
- Test: `tests/php/Unit/Pages/ContentPagesTest.php`

**Interfaces:**
- Consumes: `texts`, `settings`, `team`, `vacancies`, `faq`.
- Produces: `VacanciesPage::render(Request $r, array $values = [], array $errors = [], int $status = 200): Response`; компонент `toc.php` (`['items'=>list<array{id,label}>]`) — переиспользуется в Task 11; `NotFoundPage` — обработчик 404 роутера.

- [ ] **Step 1: Падающие тесты.**

```php
test_info_sections_in_order();     // id: prepare, packing, documents, contraindications, medicines, contact, transfer, visits, rules, faq
test_info_lists_are_shared();      // пункты #packing == texts lists.packing (тот же источник, что /svedeniya/uslugi/)
test_vacancies_empty_text();       // нет видимых вакансий → 'Сейчас набор закрыт, оставьте анкету на следующий сезон'; в select только 'Другое'
test_team_hides_hidden_sorted();
test_contacts_page();              // телефон, WhatsApp, почта, адрес офиса, часы
test_rules_and_policy_pages();     // /pravila/ и /policy/ рисуют richtext из texts
test_404_has_useful_links();       // статус 404, ссылки '/booking/' и '/contacts/'
```

- [ ] **Step 2: Запустить** → FAIL. **Step 3: Реализовать** по спеке §7.4, §7.5, §7.7. **Step 4: Запустить** → PASS.
- [ ] **Step 5: Commit** `feat: parents info, vacancies, team, contacts, legal and 404 pages`.

### Task 11: Раздел сведений и проверка готовности

**Files:**
- Create: `config/order201.php`, `app/Readiness/Readiness.php`, `app/Pages/SvedeniyaPage.php`, `templates/pages/svedeniya/{hub,ob-organizacii,deyatelnost,mto,uslugi,dostupnaya-sreda,inaya-informaciya}.php`, `templates/components/{doc-list,staff-table}.php`
- Modify: `config/routes.php`
- Test: `tests/php/Unit/Readiness/ReadinessTest.php`, `tests/php/Unit/Pages/SvedeniyaPageTest.php`, `tests/fixtures/complete/*.json`

**Interfaces:**
- Consumes: все коллекции, `texts`, `settings`, `JsonStore::updatedAt`, `toc.php`.
- Produces:
  - `config/order201.php` — `list<array{id:string, paragraph:string, title:string, full:string, level:'required'|'optional', source:string}>`. `full` — дословная формулировка пункта, скопированная из https://pravo.ppt.ru/prikaz/minprosveshcheniya-rossii/n-201-311677 (пп. 3–11); `source` — откуда брать данные (`texts:sved.fullName`, `team:education`, `documents:uchreditelnye`, `venues:access_a` …). Обязательность: «(при наличии)» и «(при условии…)» → `optional`.
  - `Readiness::evaluate(array $data, DateTimeImmutable $now): array` → `list<array{id:string, section:string, label:string, ok:bool, level:'required'|'warning', fix:string}>`; `$data = ['settings'=>…, 'texts'=>…, 'shifts'=>…, 'venues'=>…, 'team'=>…, 'documents'=>…]`; `fix` — адрес в админке (`/admin/texts/svedeniya/#sved.fullName`, `/admin/team/{id}/` …).
  - `Readiness::isReady(array $items): bool` — все `required` в `ok`.
  - Предупреждения (`warning`): нет открытых смен в текущем или следующем году; документу больше 365 дней; нет ответственного за работу с детьми с ОВЗ.

- [ ] **Step 1: Падающие тесты.**

```php
// ReadinessTest
test_complete_fixture_is_ready();               // tests/fixtures/complete → isReady === true
test_demo_missing_education_flagged();          // seed/demo → пункт 'sostav.a' ok=false, fix '/admin/team/{id}/'
test_each_venue_in_season_needs_13_access_fields(); // пустое access_k у solnyshko → 'dostupnaya.k:solnyshko' ok=false
test_optional_items_never_block();              // пустые 'sved.eduPrograms', 'programma-razvitiya' не влияют на isReady
test_prices_required_for_uslugi();              // видимая смена без цены → 'uslugi.b' ok=false
test_old_document_warning();                    // документ от 2025-01-01 при now 2027-01-15 → warning
// SvedeniyaPageTest
test_titles_exact();   // H1: 'Об организации отдыха детей и их оздоровления', 'Деятельность',
                       // 'Материально-техническое обеспечение и оснащенность организации отдыха детей и их оздоровления',
                       // 'Услуги, в том числе платные, предоставляемые организацией отдыха детей и их оздоровления', 'Доступная среда', 'Иная информация'
test_hub_links_all_sections_and_anchors();      // 6 ссылок + 5 якорей
test_ob_organizacii_has_five_anchored_parts();  // id osnovnye-svedeniya, dokumenty, rukovodstvo, sostav, kontakty — в этом порядке
test_staff_table_columns();                     // 'ФИО', 'Должность', 'Образование', 'Направление подготовки / специальность', 'Квалификация'
test_empty_required_shows_pending();            // пустой sved.legalForm → 'Информация обновляется'
test_dostupnaya_lists_13_items_per_venue();     // буквы а…н по порядку
test_uslugi_shows_cost_per_day();               // '≈ 2 857 ₽ в день' для 60000/21
test_documents_show_type_and_size();            // 'PDF, 1,2 МБ'
test_updated_date_shown();                      // 'Обновлено: …' из JsonStore::updatedAt
```

- [ ] **Step 2: Запустить** → FAIL.
- [ ] **Step 3: Реализовать** по спеке §7.6. Таблица смен года на «Деятельности» и цены на «Услугах» — из `shifts`, не из текста. «Основные сведения» д) — адрес офиса + адреса баз сезона.
- [ ] **Step 4: Запустить** → PASS.
- [ ] **Step 5: Commit** `feat: official information section and readiness check`.

### Task 12: SEO, перенаправления, заголовки безопасности

Скиллы: `seo-audit`, `schema`.

**Files:**
- Create: `config/{redirects.php,seo.php}`, `app/Seo/{Meta,Sitemap,JsonLd}.php`, `app/Pages/{SitemapController,RobotsController}.php`, `app/Http/SecurityHeaders.php`
- Modify: `app/App.php` (заголовки на каждый ответ, `staging` — Basic-авторизация и `X-Robots-Tag: noindex`), `templates/layout.php` (meta, canonical, OG, JSON-LD, счётчик Метрики при `metrikaId`), `templates/pages/shift.php` (JSON-LD Event)
- Test: `tests/php/Unit/Seo/{RedirectsTest,SitemapTest,JsonLdTest,MetaTest}.php`, `tests/php/Unit/Http/SecurityHeadersTest.php`

**Interfaces:**
- Produces:
  - `config/redirects.php` → `['map' => array<string,string>, 'legacyShiftSlugs' => list<string>, 'gone' => list<string>]`. `map` — ровно пары из спеки §5.4 плюс `/booking_form_borba/`, `/forma-bronirovaniya-sambo/`, `/let/` → `/booking/`; `gone` — `/wp-admin/`, `/wp-login.php`, `/xmlrpc.php` → 404.
  - `Meta::for(string $pageId, array $ctx = []): array{title:string, description:string, canonical:string, image:string}`; заголовок `{Страница} — детский лагерь «Гроза», Пермь`, главная — `Детский лагерь «Гроза» — лагерь приключений под Пермью`.
  - `Sitemap::build(array $data, string $baseUrl, DateTimeImmutable $now): string`.
  - `JsonLd::camp(array $settings, string $baseUrl): array` (`@type: SummerCamp`), `JsonLd::event(array $shift, ?array $venue, string $baseUrl): array` (`@type: Event`, `offers` с `priceCurrency: RUB`, если есть цена).
  - `SecurityHeaders::apply(Response $r, array $config): Response`.

- [ ] **Step 1: Падающие тесты.**

```php
test_each_spec_redirect();          // data provider по config/redirects.php map: 301 и точный Location
test_wp_paths_404();
test_sitemap_contains_public_pages_and_visible_shifts(); // есть /svedeniya/mto/, /program/{slug}/; нет /admin/, /spasibo/, /form/
test_robots_prod_and_staging();     // prod: 'Disallow: /admin/' и 'Sitemap: {baseUrl}/sitemap.xml'; staging: 'Disallow: /'
test_camp_jsonld();                 // @type SummerCamp, telephone '+7 (342) 247-43-47', sameAs содержит vk
test_event_jsonld_offer();          // price 60000 → offers.price '60000', priceCurrency 'RUB'; без цены — нет offers
test_meta_titles_unique();          // у всех публичных страниц разные title
test_headers_on_every_response();   // CSP с default-src 'self' и frame-ancestors 'none', X-Content-Type-Options nosniff, Referrer-Policy strict-origin-when-cross-origin; HSTS только prod
test_csp_allows_metrika_only_when_configured();
test_staging_requires_basic_auth(); // без авторизации → 401
test_cookie_notice_only_with_metrika(); // metrikaId пуст → уведомления нет; задан → блок со ссылкой '/policy/'
```

- [ ] **Step 2: Запустить** → FAIL. **Step 3: Реализовать.** Перенаправления — в PHP (работают на любом сервере и тестируются); `.htaccess` отвечает за передачу запросов в `index.php`, запреты и кэш статики (`/assets/*` — `Cache-Control: public, max-age=31536000, immutable`; имена файлов с хешем из Task 4). Уведомление о cookie закрывается кнопкой «Понятно», выбор хранится в `localStorage`. **Step 4: Запустить** → PASS.
- [ ] **Step 5: Commit** `feat: seo meta, sitemap, structured data, redirects, security headers`.

### Task 13: Анимации, скорость, доступность

Скиллы: `gsap`, `gsap-scrolltrigger`, `review-animations`, `optimize-web-animations`.

**Files:**
- Create: `frontend/js/motion.js`, `tests/e2e/quality.spec.ts`
- Modify: шаблоны (атрибуты `data-reveal`), `frontend/js/main.js`

**Interfaces:**
- Produces: элементы с `data-reveal` появляются при прокрутке (GSAP ScrollTrigger, `opacity 0→1`, `y 16→0`, 0,5 с); заголовок героя — по словам. Без JS и при `prefers-reduced-motion: reduce` всё видно сразу (стили скрытия ставит только JS).

- [ ] **Step 1: Падающие тесты** (`quality.spec.ts`).

```ts
test('reduced motion shows everything')  // reducedMotion: 'reduce' → у всех [data-reveal] computed opacity === '1'
test('no js shows everything')           // javaScriptEnabled: false → то же
test('home weight budget')               // 375×812, сумма размеров ответов при загрузке ≤ 1_500_000 байт; ответ героя ≤ 200_000
test('only own hosts')                   // для /, /booking/, /svedeniya/ob-organizacii/ все запросы на 127.0.0.1 (152-ФЗ)
test('axe has no serious issues')        // @axe-core/playwright на /, /booking/, /info/, /svedeniya/ob-organizacii/, /admin/login/ — нет 'serious' и 'critical'
test('no console errors')
```

- [ ] **Step 2: Запустить** `npm run e2e -- quality.spec.ts` → FAIL (анимаций и части атрибутов нет; что-то из бюджета может уже проходить).
- [ ] **Step 3: Реализовать** `motion.js`; лёгкий «подъём» полароидов при наведении — CSS. Подгружать GSAP только на страницах с `data-reveal`.
- [ ] **Step 4: Запустить** → PASS.
- [ ] **Step 5: Commit** `feat: motion with reduced-motion support, quality budgets`.

## Часть B. Админка

### Task 14: Вход в админку

**Files:**
- Create: `app/Support/{Session,Csrf}.php`, `app/Admin/{Auth,Users}.php`, `app/Admin/Controllers/AuthController.php`, `scripts/user.php`, `templates/admin/{layout,login,password}.php`, `frontend/css/admin.css`
- Modify: `config/routes.php` (`/admin/login/`, `/admin/logout/`, `/admin/password/`), `app/App.php` (для всех `POST /admin/*` — проверка CSRF; для `/admin/*`, кроме входа, — `requireUser`)
- Test: `tests/php/Unit/Admin/{AuthTest,UsersTest,CsrfTest}.php`, `tests/e2e/admin-auth.spec.ts`

**Interfaces:**
- Produces:
  - `Session::start(array $config): void` (имя `groza_admin`, `HttpOnly`, `SameSite=Strict`, `Secure` вне `dev|test`, `use_strict_mode`), `get/set/forget/regenerate/destroy`.
  - `Csrf::token(): string`, `Csrf::verify(?string $token): bool` (привязан к сессии, `hash_equals`). Поле формы — `_csrf`.
  - `Users::__construct(JsonStore $store)`; `add(string $login, string $name, string $password): void`, `setPassword(string $login, string $password): void`, `remove(string $login): void`; пароль ≥ 10 символов, `password_hash(PASSWORD_DEFAULT)`; файл `users.json`: `{"items":[{"login","name","hash","createdAt"}]}`.
  - `Auth::__construct(JsonStore $store, RateLimiter $limiter, Clock $clock, int $idleSeconds = 1800, int $absoluteSeconds = 43200)`; `attempt(string $login, string $password, string $ip): string` → `'ok'|'invalid'|'locked'`; `user(): ?array{login,name}`; `logout(): void`; `requireUser(Request $r): ?Response` (редирект на `/admin/login/?next=…`, `next` — только относительный путь, начинающийся с `/admin/`).
  - `App::auth(): Auth`, `App::actor(): string` (логин текущего пользователя для `JsonStore::write`).
  - CLI: `php scripts/user.php add <login> "<Имя>"` (пароль спрашивает без эха), `passwd <login>`, `remove <login>`.

- [ ] **Step 1: Падающие тесты.**

```php
test_login_ok_sets_user_and_regenerates_id();
test_wrong_password_generic_message();     // 'invalid'; на странице 'Неверный логин или пароль' и для несуществующего логина то же
test_lockout_after_5_failures();           // 6-я попытка с верным паролем → 'locked' на 15 мин (по логину и по IP)
test_idle_timeout();                       // сдвиг часов на 31 мин → user() === null
test_admin_requires_login();               // GET /admin/ → 302 '/admin/login/?next=%2Fadmin%2F'
test_next_cannot_leave_admin();            // next=https://evil.example → после входа Location '/admin/'
test_post_without_csrf_rejected();         // POST /admin/shifts/x/status/ без _csrf → 419, данные не изменились
test_users_short_password_rejected();
test_login_events_audited_without_password();
```

```ts
// admin-auth.spec.ts: вход → панель; смена пароля; выход → /admin/login/.
```

- [ ] **Step 2: Запустить** → FAIL. **Step 3: Реализовать.** Для несуществующего логина всё равно вызывать `password_verify` с заранее посчитанным хешем. **Step 4: Запустить** → PASS.
- [ ] **Step 5: Commit** `feat: admin authentication with lockout, idle timeout and csrf`.

### Task 15: Загрузка файлов и обработка фото

**Files:**
- Create: `app/Admin/{Uploads,ImageProcessor,UploadException}.php`, `public/uploads/.htaccess`, `tests/fixtures/make-fixtures.mjs`, `tests/fixtures/*` (результат скрипта)
- Test: `tests/php/Unit/Admin/{UploadsTest,ImageProcessorTest}.php`

**Interfaces:**
- Produces:
  - `ImageProcessor::__construct(int $maxPixels = 25000000)`; `process(string $path, string $destDir, string $base, array $widths = [480, 960, 1600]): array` → дескриптор фото (формат из Task 5, `formats = ['webp','jpg']`, ширины не больше исходной).
  - `Uploads::__construct(string $baseDir, string $baseUrl, ImageProcessor $images, int $maxImageBytes = 10485760, int $maxPdfBytes = 20971520)`; `storeImage(array $file): array` (дескриптор); `storePdf(array $file): array{url:string, bytes:int, originalName:string}`; `delete(string $url): void`. Фото → `uploads/img/{16 hex}-{w}.{fmt}`, PDF → `uploads/docs/{16 hex}.pdf`.
  - `UploadException::getMessage()` — готовый текст для сотрудника.

- [ ] **Step 1: Подготовить фикстуры** `node tests/fixtures/make-fixtures.mjs` (sharp): `gps.jpg` (EXIF с GPS), `rotated.jpg` (Orientation=6, 300×200), `small.jpg` (400×300), `photo.png`, `fake.jpg` (текст `<?php echo 1;`), `real.pdf` (минимальный PDF), `fake.pdf` (не PDF), `photo.heic` (байты `....ftypheic`).
- [ ] **Step 2: Падающие тесты.**

```php
test_exif_and_gps_removed();          // выход gps.jpg: exif_read_data без GPS*, в webp нет блока 'Exif'
test_orientation_applied();           // rotated.jpg → дескриптор w=200, h=300
test_widths_not_upscaled();           // small.jpg → widths === [400]
test_php_disguised_as_jpg_rejected(); // 'Файл не похож на фото. Подходят JPEG, PNG и WebP'
test_heic_rejected_with_hint();       // сообщение содержит 'HEIC' и 'JPEG'   (Review Focus 2)
test_too_many_pixels_rejected();      // new ImageProcessor(maxPixels: 10000) + small.jpg → 'Фото слишком большое: уменьшите до 25 мегапикселей'   (Review Focus 2)
test_image_size_limit();              // > 10 МБ → 'Фото больше 10 МБ'
test_pdf_magic_checked();             // fake.pdf → 'Это не PDF'
test_pdf_size_limit();                // > 20 МБ → 'Файл больше 20 МБ'
test_cyrillic_name_kept_as_original();// 'Устав лагеря.pdf' → originalName 'Устав лагеря.pdf', url из латиницы
test_delete_outside_base_refused();   // delete('/../config/config.php') → UploadException
test_upload_error_codes();            // UPLOAD_ERR_INI_SIZE → 'Файл слишком большой для сервера'
```

- [ ] **Step 3: Запустить** `php vendor/bin/phpunit --filter "UploadsTest|ImageProcessorTest"` → FAIL.
- [ ] **Step 4: Реализовать.** Тип — по `finfo` и сигнатуре, не по расширению; GD пересохраняет (EXIF не переносится); поворот по `exif_read_data` (если расширения `exif` нет — без поворота). Число пикселей проверять через `getimagesize` до декодирования. `public/uploads/.htaccess`: `php_flag engine off`, `RemoveHandler .php .phtml .phar`, `Options -ExecCGI -Indexes`, запрет всего, кроме `jpg|webp|png|pdf`.
- [ ] **Step 5: Запустить** → PASS.
- [ ] **Step 6: Commit** `feat: safe uploads with image re-encoding`.

### Task 16: Каркас админки и редактор списков (смены)

**Files:**
- Create: `app/Admin/FormRenderer.php`, `app/Admin/Controllers/{DashboardController,CollectionController}.php`, `templates/admin/{dashboard,collection-list,collection-edit}.php`, `templates/admin/fields/{text,textarea,richtext,int,date,enum,bool,image,file,list,ref}.php`, `frontend/js/admin.js`
- Modify: `config/routes.php`
- Test: `tests/php/Unit/Admin/CollectionControllerTest.php`, `tests/e2e/admin-shifts.spec.ts`

**Interfaces:**
- Consumes: `Collections`, `Validator`, `JsonStore` (с `expectedHash`), `AuditLog`, `Auth`, `Csrf`, `Uploads` (поля image/file).
- Produces:
  - Маршруты: `GET /admin/` (панель; до Task 19 — список разделов), `GET /admin/{collection}/`, `GET /admin/{collection}/new/`, `GET /admin/{collection}/{id}/`, `POST /admin/{collection}/{id}/` (`id = new` — создание), `POST /admin/{collection}/{id}/delete/`, `POST /admin/{collection}/{id}/move/` (`dir=up|down`), `POST /admin/shifts/{id}/status/` (`status`).
  - Скрытое поле `_hash` на каждой форме правки = `JsonStore::hash(collection)` в момент открытия.
  - `FormRenderer::render(array $collectionDef, array $values, array $errors): string`.
  - Новая запись получает `id = bin2hex(random_bytes(6))`, `order` в конец, `updatedAt`, `updatedBy`.
  - Каждая страница правки показывает «Где на сайте» (`publicUrl` из `Collections`) и ссылку «Посмотреть на сайте».

- [ ] **Step 1: Падающие тесты.**

```php
test_create_shift();                    // POST валидной смены → в shifts.json, audit 'shifts.create'
test_invalid_rerenders_422_with_values();
test_conflict_409_keeps_input();        // _hash устарел → 409, введённые значения на странице, текст 'Пока вы редактировали, кто-то сохранил изменения', файл не изменился   (Review Focus 1)
test_status_toggle();                   // status=closed → смена closed, audit 'shifts.status'
test_move_up_swaps_order();
test_delete_requires_post_and_csrf();
test_unknown_collection_404();
```

```ts
// admin-shifts.spec.ts (375×812): вход → «Смены» → у смены кнопка «Мест нет» → на /booking/ у карточки 'Мест нет'.
// Черновик: в форме смены набрать текст в «Описание», перезагрузить страницу → текст на месте и плашка 'Восстановлен черновик'; после сохранения черновик удалён.   (Review Focus 5)
```

- [ ] **Step 2: Запустить** → FAIL.
- [ ] **Step 3: Реализовать.** `admin.js`: автосохранение формы правки в `localStorage` (ключ — путь страницы + `_hash`) раз в 5 с и при уходе со страницы, восстановление с плашкой, очистка после успешного сохранения. Список смен на телефоне — крупные кнопки статуса. Вид — спека §6, последний пункт.
- [ ] **Step 4: Запустить** → PASS.
- [ ] **Step 5: Commit** `feat: admin shell and collection editor with shift status toggle`.

### Task 17: Базы, команда, документы, вакансии, вопросы, отзывы

**Files:**
- Modify: `app/Domain/Collections.php` (подписи и подсказки полей для админки), `app/Admin/Controllers/CollectionController.php` (поля image/file: загрузка, замена, удаление старого файла после успешного сохранения), `templates/admin/fields/{image,file}.php`
- Test: `tests/php/Unit/Admin/CollectionFilesTest.php`, `tests/e2e/admin-docs.spec.ts`

**Interfaces:**
- Consumes: `Uploads` (Task 15), `CollectionController` (Task 16).
- Produces: у поля `image` обязательная подпись `alt`; у `file` — заголовок и дата документа; подсказки к 13 полям доступной среды — короткие названия пунктов а–н из `config/order201.php`.

- [ ] **Step 1: Падающие тесты.**

```php
test_upload_document_pdf();          // POST documents с PDF → файл в uploads/docs, bytes записаны; /svedeniya/ob-organizacii/ показывает 'PDF,'
test_replace_file_deletes_old();
test_failed_save_keeps_old_file();   // ошибка проверки → старый файл на месте, новый удалён
test_image_requires_alt();           // 'Опишите, что на фото, — это нужно незрячим посетителям'
test_ovz_responsible_listed();       // ovzResponsible=true → в блоке ОВЗ на /svedeniya/ob-organizacii/#sostav
test_hidden_vacancy_not_public();
```

```ts
// admin-docs.spec.ts: загрузить PDF в «Документы» с разделом «Учредительные документы» → документ виден в «Сведениях».
```

- [ ] **Step 2: Запустить** → FAIL. **Step 3: Реализовать.** **Step 4: Запустить** → PASS.
- [ ] **Step 5: Commit** `feat: admin editors for venues, team, documents, vacancies, faq, reviews`.

### Task 18: Тексты, «Сведения», главная, настройки

**Files:**
- Create: `app/Admin/Sanitizer.php`, `app/Admin/Controllers/{TextsController,SettingsController}.php`, `templates/admin/{texts,settings}.php`, `frontend/js/admin-editor.js`
- Modify: `config/routes.php` (`GET|POST /admin/texts/{group}/`, `GET|POST /admin/settings/`)
- Test: `tests/php/Unit/Admin/{SanitizerTest,TextsControllerTest,SettingsControllerTest}.php`

**Interfaces:**
- Consumes: `config/texts.php` (группы и обязательность), `config/order201.php`, `JsonStore`.
- Produces:
  - `Sanitizer::clean(string $html): string` — на symfony/html-sanitizer: разрешены `p, br, strong, em, a[href], ul, ol, li, h3, blockquote`; `b→strong`, `i→em`, `h1→h3` (Trix), `div→p`; ссылки только `http(s)`, `mailto`, `tel`, внешним — `rel="noopener"`.
  - Группы текстов в админке: «Главная» (`home.*`), «Родителям» (`info.*`, `lists.*`, `rules`), «Сведения» (`sved.*`), «Вакансии» (`vacancies.*`), «Документы о данных» (`policy`). Настройки — поля `settings`.
  - Редактор richtext — Trix 2 (собирается в `admin-editor.js`, без внешних CDN), вставка картинок и файлов в редакторе отключена.

- [ ] **Step 1: Падающие тесты.**

```php
// SanitizerTest
assertSame('<p>x</p>', Sanitizer::clean('<p onclick="a()">x</p><script>alert(1)</script>'));
assertStringNotContainsString('javascript:', Sanitizer::clean('<a href="javascript:alert(1)">x</a>'));
assertSame('<h3>Заголовок</h3>', Sanitizer::clean('<h1>Заголовок</h1>'));
assertSame('<ul><li><strong>a</strong></li></ul>', Sanitizer::clean('<ul><li><b>a</b></li></ul>'));
// TextsControllerTest
test_unknown_text_key_rejected();
test_clearing_required_needs_confirm();   // пустой sved.fullName без confirm → не сохранено, предупреждение с 'п. 3.а приказа № 201'; с confirm=1 → сохранено
test_expired_csrf_keeps_typed_text();     // POST с устаревшим _csrf → 419, страница входа/повтора содержит введённый текст в поле (не сохранён)   (Review Focus 5)
test_richtext_sanitized_on_save();
// SettingsControllerTest
test_phone_normalized_and_mailto_validated(); // mailTo 'not-an-email' → ошибка
```

- [ ] **Step 2: Запустить** → FAIL. **Step 3: Реализовать.** **Step 4: Запустить** → PASS.
- [ ] **Step 5: Commit** `feat: admin text and settings editors with sanitized rich text`.

### Task 19: Панель готовности, история, резервная копия

**Files:**
- Create: `app/Admin/Controllers/{HistoryController,BackupController}.php`, `templates/admin/history.php`, `scripts/readiness.php`
- Modify: `app/Admin/Controllers/DashboardController.php`, `templates/admin/dashboard.php`, `config/routes.php` (`GET /admin/history/`, `POST /admin/history/restore/`, `GET /admin/backup/`)
- Test: `tests/php/Unit/Admin/{DashboardTest,HistoryTest,BackupTest}.php`, `tests/php/Unit/Readiness/ReadinessCliTest.php`

**Interfaces:**
- Consumes: `Readiness` (Task 11), `JsonStore::versions/restore`, `AuditLog::recent`.
- Produces: панель `/admin/` = «Готовность к проверке» (красные пункты со ссылкой «Заполнить», предупреждения) + последние изменения; `GET /admin/backup/` — zip `groza-backup-YYYY-MM-DD.zip` с `data/*.json` и `uploads/**`; `php scripts/readiness.php` — печатает незаполненные пункты, код выхода 1, если `isReady` ложно.

- [ ] **Step 1: Падающие тесты.**

```php
test_dashboard_red_item_links_to_fix();   // у члена команды нет образования → ссылка '/admin/team/{id}/'
test_restore_from_history();              // restore → данные вернулись, audit 'restore'
test_restore_requires_post_and_csrf();
test_backup_zip_contents();               // в архиве data/shifts.json и файлы uploads
test_backup_requires_login();
test_cli_exit_codes();                    // seed/demo → 1; tests/fixtures/complete → 0
```

- [ ] **Step 2: Запустить** → FAIL. **Step 3: Реализовать** (если на хостинге нет `ZipArchive` — Task 21 решает по результату `check-host.php`). **Step 4: Запустить** → PASS.
- [ ] **Step 5: Commit** `feat: readiness dashboard, history rollback, backup download`.

## Часть C. Проверка и запуск

### Task 20: Ревью безопасности и кода

- [ ] **Step 1:** Запустить `/security-review` и `superpowers:requesting-code-review` по всей ветке; `php tools/composer.phar audit`, `npm audit --omit=dev`; скилл `reverse-skill` → `api-security` (формы, админка, загрузки).
- [ ] **Step 2:** Каждую найденную проблему — сначала тестом, потом исправлением (`superpowers:receiving-code-review`).
- [ ] **Step 3:** `npm test && npm run e2e` → всё зелёное.
- [ ] **Step 4: Commit** `fix: address security and code review findings`.

### Task 21: Тестовый адрес на хостинге

Нужны доступы от заказчика (спека §13, п. 9). Без них задача ждёт.

**Files:**
- Create: `scripts/deploy.mjs`, `scripts/check-host.php`, `config/deploy.example.json`
- Test: `tests/e2e/smoke.spec.ts` (только чтение; адрес из `PLAYWRIGHT_BASE_URL`)

**Interfaces:**
- Produces: `node scripts/deploy.mjs <staging|prod> [--dry-run] [--init-users]` — `composer install --no-dev -o`, `npm run build`, выгрузка по FTPS (`basic-ftp`) с исключениями `storage/`, `public/uploads/*` (кроме `.htaccess`), `seed/demo/`, `tests/`, `frontend/`, `node_modules/`, `.git/`, `tools/`, `_source/`, `docs/`; раскладка `split` (корень сайта = `public/`) или `single` (всё в одну папку) из `config/deploy.local.json`; `--init-users` выгружает локальный `storage/data/users.json`, только если на сервере его нет.

- [ ] **Step 1:** Выгрузить `check-host.php` на тестовый адрес, открыть, записать: версия PHP (≥ 8.1), GD с WebP, `fileinfo`, `exif`, `zip`, `mbstring`, HTTPS. Удалить файл с сервера. Если PHP < 8.1 или нет GD с WebP — остановиться и сообщить пользователю.
- [ ] **Step 2:** Создать учётные записи локально (`php scripts/user.php add …`), прописать на сервере `config/local.php` (env `staging`, `baseUrl`, `appSecret` — случайные 32 байта, SMTP, Basic-пароль тестового адреса).
- [ ] **Step 3:** `node scripts/deploy.mjs staging --dry-run` → проверить список файлов; затем без `--dry-run` и с `--init-users`.
- [ ] **Step 4:** `PLAYWRIGHT_BASE_URL=https://… npm run e2e -- smoke.spec.ts` → PASS (страницы отвечают 200, меню по спеке, перенаправления, 401 без Basic-пароля, `X-Robots-Tag: noindex`).
- [ ] **Step 5:** Отправить тестовую заявку; заказчик подтверждает, что письмо пришло на `2474347@bk.ru` и не в спам.
- [ ] **Step 6:** Lighthouse (мобильный) для `/` и `/booking/`: LCP ≤ 2,5 с.
- [ ] **Step 7: Commit** `chore: deployment script and staging smoke tests`.

### Task 22: Наполнение данными заказчика и приёмка

- [ ] **Step 1:** Внести ответы заказчика по спеке §13 через админку на тестовом адресе (смены 2027, юрлицо и основные сведения, документы, команда с образованием, базы с МТО и доступной средой, родителям, вакансии, отзывы, политика ПД с оператором). Новые тексты — через `copywriting` и `humanizer`.
- [ ] **Step 2:** `/admin/` — «Готовность к проверке» вся зелёная.
- [ ] **Step 3:** Прогон скиллами `seo-audit`, `schema`, `ai-seo`, `audit-ai-design-slop`; исправления — отдельными коммитами.
- [ ] **Step 4:** Заказчик смотрит тестовый адрес; правки по списку.
- [ ] **Step 5: Commit** финального стартового набора данных в `seed/data` (без персональных данных сверх публикуемых на сайте) `chore: final seed data from client`.

### Task 23: Запуск на grozacamp.ru

- [ ] **Step 1:** С согласия пользователя — полная резервная копия текущего хостинга (файлы + дамп базы WordPress через панель хостинга), сохранить у разработчика.
- [ ] **Step 2:** Перенести данные с тестового адреса (кнопка «Скачать резервную копию» → выгрузка `storage/data` и `public/uploads` на боевой), `config/local.php` с `env = prod`, `metrikaId`.
- [ ] **Step 3:** `node scripts/deploy.mjs prod`.
- [ ] **Step 4:** `PLAYWRIGHT_BASE_URL=https://grozacamp.ru npm run e2e -- smoke.spec.ts` (боевой режим: без Basic, без noindex, HSTS есть); на боевом сайте нет текста «Информация обновляется» (`smoke.spec.ts` обходит все страницы из `sitemap.xml`).
- [ ] **Step 5:** Заказчик (или разработчик с его доступом) добавляет `sitemap.xml` в Яндекс.Вебмастер; проверить, что старые адреса из `config/redirects.php` дают 301.
- [ ] **Step 6:** Старый WordPress удалить только после согласия заказчика.
- [ ] **Step 7:** Уборка: закрыть вкладку Chrome, остановить сервер визуальных макетов (`stop-server.sh`), убедиться, что `.superpowers/` в `.gitignore`.
- [ ] **Step 8: Commit** `chore: launch` и тег `v1.0.0`.

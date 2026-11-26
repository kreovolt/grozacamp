// Точка входа скриптов сайта (собирается в public/assets/js/main.js).
import { initCookieNotice } from './cookie.js';
import { initForms } from './forms.js';
import { initDropdowns, initMobileMenu } from './nav.js';
import { initToTop } from './to-top.js';

// С JS выпадающее меню управляется скриптом; без JS работает запасной вариант на CSS (:hover/:focus-within)
document.documentElement.classList.add('js');

initDropdowns();
initMobileMenu();
initForms();
initCookieNotice();
initToTop();

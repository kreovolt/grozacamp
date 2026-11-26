// Точка входа скриптов сайта (собирается в public/assets/js/main.js).
import { initAccordions } from './accordion.js';
import { initCookieNotice } from './cookie.js';
import { initForms } from './forms.js';
import { initDropdowns, initMobileMenu } from './nav.js';
import { initScrollRegions } from './scroll-regions.js';
import { initScrollUi } from './scroll-ui.js';

// С JS выпадающее меню управляется скриптом; без JS работает запасной вариант на CSS (:hover/:focus-within)
document.documentElement.classList.add('js');

initDropdowns();
initMobileMenu();
initForms();
initCookieNotice();
initScrollUi();
initScrollRegions();
initAccordions();

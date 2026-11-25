// Анимации сайта (собирается отдельно: public/assets/js/motion.js; подключается только на страницах с [data-reveal]).
// Принципы: двигаем только transform и opacity; всё видно без JS и при prefers-reduced-motion
// (скрытие ставит только этот скрипт, и только для блоков ниже первого экрана — вёрстка не прыгает).
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

/**
 * Аварийный выход: если анимация упала посреди работы, блоки не должны остаться прозрачными или сдвинутыми.
 * Чистим инлайновые свойства обычным DOM (движок как раз мог быть причиной сбоя).
 */
function showAll() {
  document.querySelectorAll('[data-reveal], [data-word]').forEach((el) => {
    el.style.opacity = '';
    el.style.transform = '';
    el.style.translate = '';
  });
  try {
    ScrollTrigger.getAll().forEach((t) => t.kill());
    gsap.killTweensOf('[data-reveal], [data-word]');
  } catch {
    // движок уже недоступен — всё видимое важнее
  }
}

/** Запуск этапа анимации: любая ошибка внутри — показать всё как есть. */
function guarded(step) {
  try {
    step();
  } catch {
    showAll();
  }
}

function splitWords(el) {
  const words = el.textContent.trim().split(/\s+/);
  el.textContent = '';
  return words.map((word, i) => {
    const span = document.createElement('span');
    span.setAttribute('data-word', '');
    span.style.display = 'inline-block';
    span.textContent = word;
    el.appendChild(span);
    if (i < words.length - 1) el.appendChild(document.createTextNode(' '));
    return span;
  });
}

/** Заголовок героя по словам: старт с видимого состояния (opacity .4), чтобы текст читался сразу и не задерживал LCP. */
function heroTitle() {
  const h1 = document.querySelector('[data-hero-title]');
  if (!h1) return;
  const words = splitWords(h1);
  gsap.from(words, {
    y: '0.3em',
    opacity: 0.4,
    duration: 0.5,
    ease: 'power2.out',
    stagger: Math.min(0.06, 0.25 / words.length),
    clearProps: 'transform,opacity',
  });
}

/** Блоки ниже первого экрана плавно появляются. Те, что уже на экране, не трогаем (без вспышки и без CLS). */
function reveal() {
  const limit = window.innerHeight * 0.9;
  const items = Array.from(document.querySelectorAll('[data-reveal]'))
    .filter((el) => el.getBoundingClientRect().top > limit);
  if (!items.length) return;

  // Элементы с собственным transform в CSS (наклон полароида) двигать по y нельзя — только прозрачность
  items.forEach((el) => {
    const hasCssTransform = getComputedStyle(el).transform !== 'none';
    el.dataset.revealMode = hasCssTransform ? 'fade' : 'rise';
  });
  gsap.set(items, { opacity: 0 });
  gsap.set(items.filter((el) => el.dataset.revealMode === 'rise'), { y: 16 });

  ScrollTrigger.batch(items, {
    start: 'top 90%',
    once: true,
    interval: 0.08,
    onEnter: (batch) => guarded(() => {
      gsap.to(batch, {
        opacity: 1,
        y: 0,
        duration: 0.5,
        ease: 'power2.out',
        stagger: 0.08,
        overwrite: true,
        clearProps: 'transform,opacity',
      });
    }),
  });
}

/**
 * Переход по ссылке с хешем (/booking/?smena=…#zayavka). Браузер докручивает к якорю плавно (scroll-behavior: smooth),
 * а ScrollTrigger при каждом пересчёте (load, шрифты, resize) запоминает текущую позицию и возвращает её через scrollTo —
 * это обрывает плавную прокрутку на полпути, и форма остаётся за экраном. Поэтому после пересчёта, пока посетитель
 * сам не начал прокручивать страницу, ставим якорь на место мгновенно.
 */
function keepHashTarget() {
  let id;
  try {
    id = decodeURIComponent(window.location.hash.slice(1));
  } catch {
    return; // битый хеш (#%E0%A4%A) — просто не следим за якорем
  }
  const target = id ? document.getElementById(id) : null;
  if (!target) return;
  let touched = false;
  const stop = () => { touched = true; };
  ['wheel', 'touchstart', 'keydown', 'pointerdown'].forEach((name) => window.addEventListener(name, stop, { once: true, passive: true }));
  const until = performance.now() + 4000;
  const onRefresh = () => {
    if (touched || performance.now() > until) {
      ScrollTrigger.removeEventListener('refresh', onRefresh);
      return;
    }
    target.scrollIntoView({ behavior: 'instant', block: 'start' });
  };
  ScrollTrigger.addEventListener('refresh', onRefresh);
}

if (!reduce.matches) {
  gsap.registerPlugin(ScrollTrigger);
  guarded(heroTitle);
  guarded(reveal);
  try {
    keepHashTarget();
  } catch {
    // якорь — улучшение; сбой не должен ломать остальную инициализацию
  }
  // Картинки и шрифты сдвигают положение блоков — пересчитать точки срабатывания
  window.addEventListener('load', () => guarded(() => ScrollTrigger.refresh()));
}

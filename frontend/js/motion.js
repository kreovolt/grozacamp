// Анимации сайта (собирается отдельно: public/assets/js/motion.js; подключается только на страницах с [data-reveal]).
// Принципы: двигаем только transform и opacity; всё видно без JS и при prefers-reduced-motion
// (скрытие ставит только этот скрипт, и только для блоков ниже первого экрана — вёрстка не прыгает).
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

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
    onEnter: (batch) => {
      gsap.to(batch, {
        opacity: 1,
        y: 0,
        duration: 0.5,
        ease: 'power2.out',
        stagger: 0.08,
        overwrite: true,
        clearProps: 'transform,opacity',
      });
    },
  });
}

if (!reduce.matches) {
  gsap.registerPlugin(ScrollTrigger);
  heroTitle();
  reveal();
  // Картинки и шрифты сдвигают положение блоков — пересчитать точки срабатывания
  window.addEventListener('load', () => ScrollTrigger.refresh());
}

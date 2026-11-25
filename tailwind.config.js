// Тема сайта «Гроза», вариант B «Экспедиция» (спека §6). Токены — из Global Constraints;
// дополнительные оттенки (*.deep, ink.soft, cream, sage) нужны для контраста AA и текста на тёмном.
/** @type {import('tailwindcss').Config} */
export default {
  content: ['./templates/**/*.php', './app/**/*.php', './frontend/js/**/*.js'],
  theme: {
    screens: {
      sm: '640px',
      md: '768px',
      lg: '1024px',
      desk: '1200px', // с этой ширины — полное меню в шапке и верхняя полоса; ниже — бургер
      xl: '1360px',
    },
    extend: {
      colors: {
        sand: { DEFAULT: '#f1ebdd', deep: '#e7dec9' },
        forest: { DEFAULT: '#22392c', deep: '#1a2c22' },
        moss: '#5e7a4b',
        rust: { DEFAULT: '#c8562f', dark: '#b54a26', deep: '#9e3f1f' },
        gold: '#d9a93a',
        ink: { DEFAULT: '#1f2a22', soft: '#4a5a4c' },
        paper: '#ffffff',
        cream: '#efe9da', // основной текст на forest
        sage: '#b9c4b0', // второстепенный текст на forest (6.9:1)
      },
      fontFamily: {
        display: ['Oswald', '"Arial Narrow"', '"Roboto Condensed"', 'sans-serif'],
        sans: ['Manrope', 'system-ui', '-apple-system', '"Segoe UI"', 'Roboto', 'sans-serif'],
      },
      borderRadius: {
        btn: '6px',
        card: '12px',
      },
      boxShadow: {
        // тёплые мягкие тени: смещение вниз и размытие, без цветного ореола
        polaroid: '0 1px 2px rgba(43, 32, 15, 0.12), 0 14px 28px -14px rgba(43, 32, 15, 0.5)',
        lift: '0 2px 4px rgba(43, 32, 15, 0.1), 0 24px 40px -18px rgba(43, 32, 15, 0.55)',
        card: '0 1px 2px rgba(43, 32, 15, 0.06), 0 12px 30px -18px rgba(43, 32, 15, 0.38)',
        pop: '0 2px 6px rgba(26, 44, 34, 0.1), 0 28px 56px -24px rgba(26, 44, 34, 0.55)',
        cta: '0 6px 14px -8px rgba(122, 46, 20, 0.6)',
      },
      letterSpacing: {
        nav: '0.06em',
        btn: '0.08em',
        brand: '0.08em',
        kicker: '0.2em',
      },
      maxWidth: {
        site: '82.5rem', // 1320px
      },
      keyframes: {
        'drop-in': {
          from: { opacity: '0', transform: 'translateY(-6px)' },
          to: { opacity: '1', transform: 'none' },
        },
      },
      animation: {
        'drop-in': 'drop-in 160ms cubic-bezier(0.16, 1, 0.3, 1)',
      },
    },
  },
  plugins: [],
};

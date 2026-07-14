/**
 * Design tokens do YVE Agency — fonte única da verdade (FE-01).
 *
 * Antes, cada layout repetia seu próprio `tailwind.config` inline e um bloco
 * <style> com valores hardcoded — `.card` e `.btn-primary` chegaram a divergir
 * entre o painel e o portal. Mudar a marca exigia editar 4 arquivos.
 *
 * Agora: mudar a cor de acento aqui repercute em todas as telas. É também a
 * base do white-label por agência (PROD-06).
 */
module.exports = {
  darkMode: 'class',

  // Purge: só as classes usadas nestes arquivos entram no CSS final.
  content: [
    './resources/views/**/*.php',
    './public/js/**/*.js',
    './app/**/*.php', // classes montadas no PHP (ex.: previewFrameClass)
  ],

  theme: {
    extend: {
      colors: {
        // Superfícies escuras da marca (fundo → cards → dropdowns).
        gray: {
          925: '#0f1117',
          950: '#09090f',
        },
        surface: {
          DEFAULT: '#0d0d14', // sidebar, topbar
          raised:  '#12121a', // dropdowns, option
          card:    '#16161f', // cards do portal
        },
        /**
         * Acento da marca — YVE Beauty (dourado champagne).
         *
         * A cor foi extraída dos arquivos oficiais da logo: `#c6a15b`, idêntica
         * nos três (monograma, ícone e horizontal). Os tons acima e abaixo são
         * derivados dela.
         *
         * Trocar aqui = trocar a identidade do produto inteiro.
         */
        brand: {
          50:  '#faf6ef',
          200: '#e6d4b0',
          300: '#dcc48f',
          400: '#d4b478',
          500: '#c6a15b',  // ← a cor da logo
          600: '#b8914c',
          700: '#9a7739',
          800: '#7c5f2d',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
      },
      boxShadow: {
        brand: '0 4px 14px rgba(198,161,91,0.25)',
        'brand-lg': '0 6px 20px rgba(198,161,91,0.35)',
        glow: '0 0 80px -20px rgba(198,161,91,0.30)',
      },
      keyframes: {
        fadeInUp: {
          from: { opacity: '0', transform: 'translateY(8px)' },
          to:   { opacity: '1', transform: 'translateY(0)' },
        },
      },
      animation: {
        'fade-in-up': 'fadeInUp 0.25s ease-out',
      },
    },
  },

  plugins: [],
};

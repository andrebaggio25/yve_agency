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
        // Acento da marca. Trocar aqui = trocar a identidade do produto inteiro.
        brand: {
          50:  '#f5f3ff',
          300: '#c4b5fd',
          400: '#a78bfa',
          500: '#8b5cf6',
          600: '#7c3aed',
          700: '#6d28d9',
          800: '#5b21b6',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
      },
      boxShadow: {
        brand: '0 4px 14px rgba(124,58,237,0.25)',
        'brand-lg': '0 6px 20px rgba(124,58,237,0.35)',
        glow: '0 0 80px -20px rgba(139,92,246,0.3)',
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

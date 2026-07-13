/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./app/Views/**/*.php",
    "./public/**/*.js",
  ],
  darkMode: ['selector', '[data-theme="dark"]'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Cairo', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
      },
      colors: {
        primary: {
          DEFAULT: '#4f46e5',
          hover:   '#4338ca',
          dark:    '#6366f1',
          'dark-hover': '#818cf8',
        },
        secondary: {
          DEFAULT: '#0ea5e9',
          dark:    '#38bdf8',
        },
        success: {
          DEFAULT: '#10b981',
          dark:    '#34d399',
        },
        warning: {
          DEFAULT: '#f59e0b',
          dark:    '#fbbf24',
        },
        error: {
          DEFAULT: '#ef4444',
          dark:    '#f87171',
        },
        // Light mode surfaces
        surface: {
          app:    '#f4f7fa',
          card:   '#ffffff',
          'card-hover': '#fcfdfe',
          input:  '#f1f3f9',
          border: '#e2e8f0',
          'border-hover': '#cbd5e1',
        },
        // Dark mode surfaces
        'surface-dark': {
          app:    '#0f172a',
          card:   '#1e293b',
          'card-hover': '#24334d',
          input:  '#0f172a',
          border: '#334155',
          'border-hover': '#475569',
        },
        text: {
          main:    '#1e293b',
          muted:   '#64748b',
          inverse: '#ffffff',
          'dark-main':  '#f8fafc',
          'dark-muted': '#94a3b8',
        },
      },
      borderRadius: {
        sm: '8px',
        md: '12px',
        lg: '20px',
        full: '9999px',
      },
      boxShadow: {
        sm: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
        md: '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
        lg: '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
        'dark-sm': '0 1px 2px 0 rgb(0 0 0 / 0.3)',
        'dark-md': '0 4px 6px -1px rgb(0 0 0 / 0.4)',
        'dark-lg': '0 10px 15px -3px rgb(0 0 0 / 0.5)',
      },
      transitionTimingFunction: {
        smooth: 'cubic-bezier(0.4, 0, 0.2, 1)',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
};

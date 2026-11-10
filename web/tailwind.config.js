/** Palette: surface #EAF1FF, brand #8B5CF6 */
export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        surface: { DEFAULT: '#EAF1FF', deep: '#D9E5FF', line: '#C7D7F7' },
        brand: { DEFAULT: '#8B5CF6', 50: '#F5F0FF', 100: '#EDE5FF', 200: '#DDD0FE', 400: '#A78BFA',
                 500: '#8B5CF6', 600: '#7C3AED', 700: '#6D28D9', 900: '#3B1A7A' },
        ink: { DEFAULT: '#1E1B3A', soft: '#4B4872' },
      },
      fontFamily: { sans: ['"Atkinson Hyperlegible"', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
      keyframes: { ping2: { '75%,100%': { transform: 'scale(2.2)', opacity: '0' } } },
      animation: { ping2: 'ping2 1.6s cubic-bezier(0,0,.2,1) infinite' },
    },
  },
};

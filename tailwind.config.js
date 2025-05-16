module.exports = {
  prefix: 'tw-',
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.js',
    './resources/sass/**/*.scss', 
  ],
  corePlugins: {
    preflight: true,
  },
  theme: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/typography'),
    require('tailwindcss-motion'),
    require('daisyui'),
  ],
  daisyui: {
    themes: ['light', 'dark', 'dracula'],
    darkTheme: 'dracula',
    base: true,
    styled: true,
    utils: true,
    logs: true,
  },
};
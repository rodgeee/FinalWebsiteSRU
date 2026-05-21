/** Tailwind build for admin dashboard and pages */
module.exports = {
  content: [
    "./templates/**/*.html.twig",
    "./assets/**/*.js",
  ],
  theme: {
    // Sora (headings) + system sans-serif only.
    fontFamily: {
      sans: ['sans-serif'],
      serif: ['Sora', 'sans-serif'],
      mono: ['sans-serif'],
      heading: ['Sora', 'sans-serif'],
    },
    extend: {
      colors: {
        brand: '#0077FF',
        text: '#1A1A1A',
        textMuted: '#666666',
        card: '#F8F9FB',
        border: '#E5E7EB',
      },
      boxShadow: { card: '0 8px 24px rgba(0,0,0,0.06)' },
    },
  },
  plugins: [],
};

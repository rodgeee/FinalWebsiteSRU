/* Copy self-hosted Inter + Sora woff2 from @fontsource packages into public/fonts */
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const outDir = path.join(root, 'public', 'fonts');
fs.mkdirSync(outDir, { recursive: true });

const copies = [
  ['node_modules/@fontsource/inter/files/inter-latin-400-normal.woff2', 'inter-latin-400-normal.woff2'],
  ['node_modules/@fontsource/inter/files/inter-latin-500-normal.woff2', 'inter-latin-500-normal.woff2'],
  ['node_modules/@fontsource/inter/files/inter-latin-600-normal.woff2', 'inter-latin-600-normal.woff2'],
  ['node_modules/@fontsource/inter/files/inter-latin-700-normal.woff2', 'inter-latin-700-normal.woff2'],
  ['node_modules/@fontsource/sora/files/sora-latin-700-normal.woff2', 'sora-latin-700-normal.woff2'],
  ['node_modules/@fontsource/sora/files/sora-latin-800-normal.woff2', 'sora-latin-800-normal.woff2'],
];

for (const [relSrc, name] of copies) {
  const src = path.join(root, relSrc);
  const dest = path.join(outDir, name);
  if (!fs.existsSync(src)) {
    console.warn('copy-fonts: missing', relSrc, '(run npm install)');
    continue;
  }
  fs.copyFileSync(src, dest);
}

/* Standalone pages (e.g. POS) load /styles/site-fonts.css — keep identical to assets/styles/fonts.css */
const fontsCssSrc = path.join(root, 'assets', 'styles', 'fonts.css');
const siteFontsDest = path.join(root, 'public', 'styles', 'site-fonts.css');
if (fs.existsSync(fontsCssSrc)) {
  fs.mkdirSync(path.dirname(siteFontsDest), { recursive: true });
  fs.copyFileSync(fontsCssSrc, siteFontsDest);
}

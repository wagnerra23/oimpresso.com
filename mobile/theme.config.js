/**
 * Theme tokens — alinhados com a paleta Oimpresso v2 (lib/oi-theme.ts).
 *
 * Os valores em `light` e `dark` espelham `lightPalette` e `darkPalette` para
 * que as classes Tailwind (`bg-background`, `text-foreground`, etc.) renderizem
 * com as mesmas cores que os primitivos `OiX`.
 *
 * v3 (handoff 3): SKIN do protótipo — accent ROXO (não mais magenta como
 * primária; magenta fica só na marca/logo); neutros com tinta roxa do CSS
 * canônico.
 *
 * @type {const}
 */
const themeColors = {
  primary:    { light: '#663e9e', dark: '#9a2bcb' },
  background: { light: '#f7f6f9', dark: '#15131b' },
  surface:    { light: '#fdfcfe', dark: '#1e1a27' },
  foreground: { light: '#2d2933', dark: '#f2f2f2' },
  muted:      { light: '#908d96', dark: '#7e7b8c' },
  border:     { light: '#e0dee4', dark: '#2a2633' },
  success:    { light: '#549864', dark: '#22c55e' },
  warning:    { light: '#c49548', dark: '#ffb300' },
  error:      { light: '#c45b56', dark: '#ff4d4f' },
};

module.exports = { themeColors };

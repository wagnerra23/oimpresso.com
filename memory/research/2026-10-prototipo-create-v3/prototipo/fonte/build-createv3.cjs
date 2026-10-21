// Monta um HTML único (artefato) do protótipo /sells/create-v3 a partir da âncora do repo.
// Uso: node build-createv3.cjs <repoRoot> <saida.html>
const fs = require('fs');
const path = require('path');

const repo = process.argv[2];
const out = process.argv[3];
const esbuild = require(path.join(repo, 'node_modules', 'esbuild'));

const ds = path.join(repo, 'prototipo-ui', 'design-system');
const host = path.join(repo, 'prototipo-ui', 'cowork', 'Wagner');
const ler = (p) => fs.readFileSync(p, 'utf8');
const mascaraDS = (s) => s.replace(/R\$ [1-9][\d.,]*/g, 'R$ —');

// 1. Tokens + tipografia do DS, com as fontes self-hosted embutidas como data URI
let tokens = ler(path.join(ds, 'colors_and_type.css')).replace(
  /url\('(assets\/fonts\/[^']+\.woff2)'\)/g,
  (_, rel) => "url(data:font/woff2;base64," + fs.readFileSync(path.join(ds, rel)).toString('base64') + ")"
);
const dsStyles = ler(path.join(ds, 'styles.css')).replace(/@import[^;]+;/g, '');
const cockpit = ler(path.join(ds, 'cockpit_domains.css'));
const shell = ler(path.join(host, 'styles.css'));
const ondas = ler(path.join(host, 'otimiza-ondas.css'));
const vendaCss = ler(path.join(host, 'venda-v3.css'));

// 2. JSX da âncora compilado (React.createElement clássico, igual ao Babel do host)
let jsx = ler(path.join(host, 'venda-v3.jsx')).replace(/\r\n/g, '\n');
// 2b. Ajustes pedidos nos comentários do artefato — aplicados por cima da âncora, sem editar o repo
for (const a of require('./ajustes-artefato.cjs')) {
  const n = jsx.split(a.de).length - 1;
  if (n !== 1) throw new Error('Ajuste "' + a.motivo + '": trecho encontrado ' + n + ' vez(es), esperado 1. A âncora mudou?');
  jsx = jsx.replace(a.de, () => a.para);
}
const vendaJs = esbuild.transformSync(jsx, {
  loader: 'jsx', jsx: 'transform', target: 'es2019', minify: false, legalComments: 'none',
}).code;
const vendaJsMascarado = vendaJs.replace(/R\$ 0,01 perdido/g, 'centavo perdido');
// O bundle do DS e a âncora trazem textos de demonstração com valores em reais (um exemplo de
// KPI do DS e a frase didática do centavo perdido nas parcelas). Mascarados aqui porque o HTML
// gerado mora em memory/, onde valor monetário é Tier 0 (scripts/governance/brl-scan-diff.mjs).
const dsBundle = mascaraDS(ler(path.join(ds, '_ds_bundle.js')));

const head = ler(path.join(__dirname, 'createv3-head.html'));
const app = ler(path.join(__dirname, 'createv3-app.js'));

const fecha = (s) => s.replace(/<\/script/gi, '<\\/script');
const fechaCss = (s) => s.replace(/<\/style/gi, '<\\/style');

const html = [
  head,
  '<style id="ds-tokens">', fechaCss(tokens), fechaCss(dsStyles), '</style>',
  '<style id="ds-cockpit">', fechaCss(cockpit), '</style>',
  '<style id="host-shell">', fechaCss(shell), fechaCss(ondas), '</style>',
  '<style id="venda-v3">', fechaCss(vendaCss), '</style>',
  '<div id="app"></div>',
  '<script src="https://unpkg.com/react@18.3.1/umd/react.production.min.js"></script>',
  '<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.production.min.js"></script>',
  '<script src="https://unpkg.com/lucide@0.460.0/dist/umd/lucide.js"></script>',
  '<script id="ds-bundle">', fecha(dsBundle), '</script>',
  '<script id="venda-v3-build">', fecha(vendaJsMascarado), '</script>',
  '<script id="artefato">', fecha(app), '</script>',
].join('\n');

fs.mkdirSync(path.dirname(out), { recursive: true });
fs.writeFileSync(out, html);
console.log('ok', out, (html.length / 1024).toFixed(0) + ' KB');

#!/usr/bin/env node
// @ts-check
/**
 * aplicar-payload.test.mjs — selftest do applier de payload do espelho Cowork.
 *
 * Roda o CLI DE FORA, num sandbox por cwd, com payload sintético. Não testa helper
 * exportado: o §5 2026-07-30 registra que assert sobre função-satélite fica verde
 * enquanto o pipeline regride — o bite tem que atravessar o chokepoint real.
 *
 * Cobre o que o applier PROMETE:
 *   1. escreve fiel e conta certo (NOVO / ATUALIZADO / inalterado)
 *   2. --dry não escreve
 *   3. recusa path que sai do espelho (../) — trava de escopo
 *   4. recusa .md dentro de cowork/ — R1 do cowork-ssot-guard
 *   5. recusa bytes divergente — corrupção de transporte
 *   6. AVISA em perda líquida de linhas — o caso qa-conformance.js (espelho À FRENTE),
 *      que sem aviso vira regressão silenciosa
 *   7. o alerta NÃO dispara em sync legítimo que cresce — controle negativo
 *   8. --require-complete-shell fecha HTML→CSS/JS→imports/assets transitivos
 *   9. `_ds/**` pousa no snapshot canônico (bundle/CSS/base64 byte-idêntico)
 *  10. dependência transitiva ausente cancela o lote inteiro antes do 1º write
 *
 * Uso: node scripts/design-sync/aplicar-payload.test.mjs
 */
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';

const APPLIER = resolve('scripts/design-sync/aplicar-payload.mjs');
let fails = 0;
const check = (nome, ok, detalhe = '') => {
  console.log(`[${ok ? 'OK' : 'FAIL'}] ${nome}${ok ? '' : '  → ' + detalhe}`);
  if (!ok) fails++;
};

/** sandbox: cwd próprio com `prototipo-ui/cowork/`, pra nunca tocar o repo real */
function sandbox(arquivosIniciais = {}) {
  const dir = mkdtempSync(join(tmpdir(), 'aplicar-payload-'));
  mkdirSync(join(dir, 'prototipo-ui', 'cowork'), { recursive: true });
  for (const [rel, txt] of Object.entries(arquivosIniciais)) {
    writeFileSync(join(dir, 'prototipo-ui', 'cowork', rel), txt, 'utf8');
  }
  return dir;
}
function payload(dir, files) {
  const p = join(dir, 'payload.json');
  writeFileSync(p, JSON.stringify({
    schema: 'test', generatedAt: '2026-08-17T00:00:00Z', source: 'selftest',
    totalBytes: files.reduce((a, f) => a + Buffer.byteLength(f.content, 'utf8'), 0),
    missing: [],
    files: files.map((f) => ({ bytes: Buffer.byteLength(f.content, 'utf8'), ...f })),
  }), 'utf8');
  return p;
}
function completePayload(dir, files, missing = []) {
  const p = join(dir, 'payload-completo.json');
  const normalized = files.map((f) => {
    const binary = f.isBase64 === true || f.encoding === 'base64';
    const bytes = binary ? Buffer.from(f.content, 'base64').length : Buffer.byteLength(f.content, 'utf8');
    return { bytes, ...f };
  });
  writeFileSync(p, JSON.stringify({
    schema: 'oimpresso-shell-v2', entry: 'oimpresso.com.html',
    generatedAt: '2026-08-18T00:00:00Z', source: 'selftest', missing,
    totalBytes: normalized.reduce((a, f) => a + f.bytes, 0), files: normalized,
  }), 'utf8');
  return p;
}
function rodar(dir, pay, args = []) {
  try {
    const payloads = Array.isArray(pay) ? pay : [pay];
    const out = execFileSync('node', [APPLIER, ...payloads, ...args], { cwd: dir, encoding: 'utf8' });
    return { code: 0, out };
  } catch (e) { return { code: e.status ?? 1, out: (e.stdout || '') + (e.stderr || '') }; }
}

// ── 1 + 2: escreve fiel, conta certo, e --dry não escreve ────────────────────
{
  const dir = sandbox({ 'a.jsx': 'velho\n' });
  const pay = payload(dir, [{ path: 'a.jsx', content: 'novo\n' }, { path: 'b.css', content: 'x\n' }]);
  const seco = rodar(dir, pay, ['--dry']);
  check('--dry NÃO escreve', readFileSync(join(dir, 'prototipo-ui/cowork/a.jsx'), 'utf8') === 'velho\n', seco.out);
  check('--dry conta 1 atualizado + 1 novo', /1 atualizado\(s\) · 1 novo\(s\)/.test(seco.out), seco.out);

  const r = rodar(dir, pay);
  check('aplica: conteúdo fica BYTE-idêntico ao payload',
    readFileSync(join(dir, 'prototipo-ui/cowork/a.jsx'), 'utf8') === 'novo\n' &&
    readFileSync(join(dir, 'prototipo-ui/cowork/b.css'), 'utf8') === 'x\n', r.out);
  check('aplica: rc=0 quando nada é recusado', r.code === 0, 'rc=' + r.code);

  const r2 = rodar(dir, pay);
  check('idempotente: 2ª aplicação = 2 inalterados', /0 atualizado\(s\) · 0 novo\(s\) · 2 inalterado/.test(r2.out), r2.out);
}

// ── 3: trava de escopo (path que sai do espelho) ─────────────────────────────
{
  const dir = sandbox();
  const pay = payload(dir, [{ path: '../../fora.txt', content: 'nao deveria existir\n' }]);
  const r = rodar(dir, pay);
  check('BITE escopo: recusa path que SAI do espelho', /RECUSADO/.test(r.out) && r.code !== 0, r.out);
  check('BITE escopo: nada foi escrito fora', !existsSync(join(dir, 'fora.txt')), 'escreveu fora do espelho!');
}

// ── 4: R1 do ssot-guard (.md dentro de cowork/) ──────────────────────────────
{
  const dir = sandbox();
  const pay = payload(dir, [{ path: 'LEIAME.md', content: '# nao\n' }]);
  const r = rodar(dir, pay);
  check('BITE R1: recusa .md dentro de cowork/', /R1 do ssot-guard/.test(r.out) && r.code !== 0, r.out);
  check('BITE R1: o .md não foi escrito', !existsSync(join(dir, 'prototipo-ui/cowork/LEIAME.md')));
}

// ── 5: bytes divergente = corrupção de transporte ────────────────────────────
{
  const dir = sandbox();
  const pay = join(dir, 'p.json');
  writeFileSync(pay, JSON.stringify({ files: [{ path: 'c.jsx', content: 'abc\n', bytes: 999 }] }), 'utf8');
  const r = rodar(dir, pay);
  check('BITE bytes: recusa quando declarado != real', /DIVERGENTE/.test(r.out) && r.code !== 0, r.out);
  check('BITE bytes: arquivo corrompido NÃO é escrito', !existsSync(join(dir, 'prototipo-ui/cowork/c.jsx')));
}

// ── 6 + 7: alerta de PERDA LÍQUIDA (o caso qa-conformance.js) ────────────────
{
  const grande = Array.from({ length: 200 }, (_, i) => 'linha ' + i).join('\n') + '\n';
  const pequeno = Array.from({ length: 30 }, (_, i) => 'linha ' + i).join('\n') + '\n';
  const dir = sandbox({ 'perde.jsx': grande, 'cresce.jsx': pequeno });
  const pay = payload(dir, [
    { path: 'perde.jsx', content: pequeno },   // vivo MENOR → espelho à frente
    { path: 'cresce.jsx', content: grande },   // vivo MAIOR → sync legítimo
  ]);
  const r = rodar(dir, pay, ['--dry']);
  const linhaPerde = r.out.split('\n').find((l) => l.includes('perde.jsx')) || '';
  const linhaCresce = r.out.split('\n').find((l) => l.includes('cresce.jsx')) || '';
  check('BITE regressão: AVISA quando o apply perde linhas (espelho à frente)',
    /PERDE \d+ LINHAS/.test(linhaPerde), linhaPerde || r.out);
  check('CONTROLE NEGATIVO: NÃO avisa em sync que cresce',
    !/PERDE/.test(linhaCresce), linhaCresce);
  check('o alerta é RELATO, não bloqueio (rc segue 0)', r.code === 0, 'rc=' + r.code);
}

// ── 8 + 9: fechamento transitivo + roteamento persistente de `_ds/**` ─────────
{
  const fontBytes = Buffer.from([0, 1, 2, 127, 128, 255]);
  const files = [
    { path: 'oimpresso.com.html', content: [
      '<link rel="stylesheet" href="styles.css?v=1">',
      '<link rel="stylesheet" href="_ds/ds-teste/colors_and_type.css">',
      '<script src="https://cdn.example/react.js"></script>',
      '<script src="app.js"></script>',
      '<script src="_ds/ds-teste/_ds_bundle.js"></script>',
    ].join('\n') },
    { path: 'styles.css', content: '@import "theme.css";\n' },
    { path: 'theme.css', content: '.hero{background:url("assets/bg.svg#x")}\n' },
    { path: 'assets/bg.svg', content: '<svg xmlns="http://www.w3.org/2000/svg"/>\n' },
    { path: 'app.js', content: 'import "./lib/util.js";\nimport "react/jsx-runtime";\n' },
    { path: 'lib/util.js', content: 'export const ok = true;\n' },
    { path: '_ds/ds-teste/colors_and_type.css', content: '@font-face{src:url("assets/fonts/mono.woff2")}\n' },
    { path: '_ds/ds-teste/_ds_bundle.js', content: 'globalThis.DS = { Drawer() {} };\n' },
    { path: '_ds/ds-teste/assets/fonts/mono.woff2', content: fontBytes.toString('base64'), isBase64: true },
  ];
  const dir = sandbox();
  const coworkPay = completePayload(dir, files.filter((f) => !f.path.startsWith('_ds/')));
  const dsPay = join(dir, 'payload-ds.json');
  const dsFiles = files.filter((f) => f.path.startsWith('_ds/')).map((f) => {
    const binary = f.isBase64 === true || f.encoding === 'base64';
    const bytes = binary ? Buffer.from(f.content, 'base64').length : Buffer.byteLength(f.content, 'utf8');
    return { bytes, ...f };
  });
  writeFileSync(dsPay, JSON.stringify({ source: 'design-system', missing: [], files: dsFiles }), 'utf8');
  const r = rodar(dir, [coworkPay, dsPay], ['--require-complete-shell']);
  check('SHELL COMPLETO: fecha HTML→CSS/JS→imports/assets e ignora CDN externa',
    r.code === 0 && /GRAFO COMPLETO/.test(r.out), r.out);
  check('SHELL COMPLETO: fonte/CSS/bundle `_ds` pousam no snapshot canônico',
    readFileSync(join(dir, 'scripts/design-sync/mirror-snapshot/_ds_bundle.js'), 'utf8').includes('Drawer') &&
    readFileSync(join(dir, 'scripts/design-sync/mirror-snapshot/colors_and_type.css'), 'utf8').includes('@font-face') &&
    readFileSync(join(dir, 'scripts/design-sync/mirror-snapshot/assets/fonts/mono.woff2')).equals(fontBytes), r.out);
  check('SHELL COMPLETO: `_ds` não vira segunda cópia dentro do espelho',
    !existsSync(join(dir, 'prototipo-ui/cowork/_ds/ds-teste/_ds_bundle.js')));
  check('SHELL COMPLETO: arquivos Cowork seguem no espelho',
    existsSync(join(dir, 'prototipo-ui/cowork/lib/util.js')) && existsSync(join(dir, 'prototipo-ui/cowork/assets/bg.svg')));
}

// ── 10: grafo incompleto é atômico — nenhum arquivo do lote é escrito ─────────
{
  const dir = sandbox();
  const files = [
    { path: 'oimpresso.com.html', content: '<link href="styles.css"><script src="app.js"></script>' },
    { path: 'styles.css', content: '.x{color:red}\n' },
    { path: 'app.js', content: 'import "./ausente.js";\n' },
  ];
  const pay = completePayload(dir, files);
  const r = rodar(dir, pay, ['--require-complete-shell']);
  check('BITE grafo: dependência JS transitiva ausente reprova nominalmente',
    r.code === 1 && /grafo local incompleto: ausente\.js/.test(r.out), r.out);
  check('BITE grafo: falha é atômica, nem o primeiro arquivo é escrito',
    !existsSync(join(dir, 'prototipo-ui/cowork/oimpresso.com.html')) &&
    !existsSync(join(dir, 'prototipo-ui/cowork/styles.css')), r.out);
}

// O gerador remoto também declara o que não conseguiu incluir; a máquina não aceita a palavra
// dele sem conferir, mas tampouco ignora uma ausência que ele próprio reconheceu.
{
  const dir = sandbox();
  const files = [{ path: 'oimpresso.com.html', content: '<div>ok</div>\n' }];
  const pay = completePayload(dir, files, ['arquivo-remoto.jsx']);
  const r = rodar(dir, pay, ['--require-complete-shell']);
  check('BITE missing declarado: payload autodeclarado incompleto reprova sem escrever',
    r.code === 1 && /payload declarou 1 ausente/.test(r.out) &&
    !existsSync(join(dir, 'prototipo-ui/cowork/oimpresso.com.html')), r.out);
}

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ applier: fiel/atômico · shell transitivo completo · _ds persistente · recusa escopo/R1/bytes · avisa regressão');

// ── 11: payload CORTADO no transporte (medido 2026-08-19) ───────────────────
// `sync/payload.json` tem ~3,5 MB e o DesignSync.get_file corta em 256 KiB. Antes da
// guarda isso morria num "Unterminated string" que nao dizia a causa nem o remedio.
// Bite dos dois lados: cortado reprova nomeando o teto; inteiro segue passando.
{
  const dir = sandbox();
  const pay = payload(dir, [{ path: 'a.jsx', content: 'conteudo integro' }]);
  const inteiro = readFileSync(pay, 'utf8');
  const cortado = join(dir, 'payload-cortado.json');
  writeFileSync(cortado, inteiro.slice(0, Math.floor(inteiro.length * 0.6)), 'utf8');

  const r = rodar(dir, cortado);
  check('BITE truncagem: payload cortado reprova nomeando o teto de transporte',
    r.code === 2 && r.out.includes('payload ilegível') && r.out.includes('256 KiB'), r.out);
  check('BITE truncagem: a mensagem ENSINA o remedio (aplicar em partes)',
    r.out.includes('em PARTES') && r.out.includes('p1.json p2.json'), r.out);
  check('BITE truncagem: nada e escrito quando o payload nao parseia',
    !existsSync(join(dir, 'prototipo-ui/cowork/a.jsx')), r.out);

  const ok = rodar(dir, pay);
  check('CONTROLE POSITIVO: payload inteiro continua aplicando',
    ok.code === 0 && existsSync(join(dir, 'prototipo-ui/cowork/a.jsx')), ok.out);
}

// ── 12: payload que PARSEIA mas veio incompleto ─────────────────────────────
// Pior que o cortado: e JSON valido, entao passaria batido e escreveria meio espelho.
{
  const dir = sandbox();
  const pay = payload(dir, [{ path: 'a.jsx', content: 'x' }]);
  const obj = JSON.parse(readFileSync(pay, 'utf8'));
  obj.fileCount = obj.files.length + 3;          // declara mais do que traz
  const mentiroso = join(dir, 'payload-incompleto.json');
  writeFileSync(mentiroso, JSON.stringify(obj), 'utf8');

  const r = rodar(dir, mentiroso);
  check('BITE incompleto: fileCount declarado > arquivos trazidos reprova',
    r.code === 2 && r.out.includes('payload incompleto') && r.out.includes('faltam 3'), r.out);
  check('BITE incompleto: nao escreve espelho pela metade',
    !existsSync(join(dir, 'prototipo-ui/cowork/a.jsx')), r.out);
}

process.exit(fails ? 1 : 0);

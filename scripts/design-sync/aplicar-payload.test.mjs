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
 *   4. .md NUNCA em cowork/ (R1) — roteia pra design-docs/ preservando a árvore
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
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, existsSync, readdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { execFileSync } from 'node:child_process';

const APPLIER = resolve('scripts/design-sync/aplicar-payload.mjs');
const GENERATOR = resolve('scripts/design-sync/gerar-payload-partes.mjs');
let fails = 0;
const check = (nome, ok, detalhe = '') => {
  console.log(`[${ok ? 'OK' : 'FAIL'}] ${nome}${ok ? '' : '  → ' + detalhe}`);
  if (!ok) fails++;
};

/**
 * FNV-1a 64 de referência — réplica do `fnv1a64()` do applier, pra fabricar o caso CORRETO
 * do bite de digest. Ancorado em vetor publicado (`"foobar"` → 85944171f73967e8), senão o
 * teste mediria a si mesmo: se eu copiasse um bug daqui, o "correto" e o applier errariam junto.
 */
function fnvRef(texto) {
  let h = 0xcbf29ce484222325n;
  const prime = 0x100000001b3n, mask = 0xffffffffffffffffn;
  for (const b of Buffer.from(texto, 'utf8')) { h = ((h ^ BigInt(b)) * prime) & mask; }
  return h.toString(16).padStart(16, '0');
}
if (fnvRef('foobar') !== '85944171f73967e8') {
  console.log('[FAIL] fnvRef não bate no vetor publicado FNV-1a 64 de "foobar" — o resto do bite de digest não vale');
  process.exit(1);
}

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

// ── 4: R1 do ssot-guard — .md NUNCA em cowork/, mas pousa em design-docs/ ────
//
// A INVARIANTE não mudou e é ela que este bite protege: nenhum `.md` no espelho, nunca.
// O que mudou (2026-08-21, decisão [W]) é o desfecho — antes o applier DESCARTAVA, e o
// preço foi 204 `.md` vivos no Cowork contra 0 no repo. Agora ele ROTEIA.
// A perna negativa é a que importa: se alguém reintroduzir o destino errado, o segundo
// assert reprova mesmo com o primeiro verde.
{
  const dir = sandbox();
  const pay = payload(dir, [{ path: 'cowork-inbox/LEIAME.md', content: '# doc\n' }]);
  const r = rodar(dir, pay);
  check('BITE R1: o .md NÃO pousa em cowork/ (invariante do ssot-guard)',
    !existsSync(join(dir, 'prototipo-ui/cowork/cowork-inbox/LEIAME.md')), r.out);
  check('.md pousa em design-docs/ preservando a árvore do vivo',
    existsSync(join(dir, 'prototipo-ui/design-docs/cowork-inbox/LEIAME.md')), r.out);
  check('.md roteado chega FIEL (byte a byte)',
    existsSync(join(dir, 'prototipo-ui/design-docs/cowork-inbox/LEIAME.md')) &&
    readFileSync(join(dir, 'prototipo-ui/design-docs/cowork-inbox/LEIAME.md'), 'utf8') === '# doc\n', r.out);
  check('lote com .md não falha o applier', r.code === 0, r.out);
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

// -- ENVELOPE do get_file: o 3o caso, medido 2026-08-20 no artefato real -----
// O #6003 cobriu (a) JSON cortado e (b) payload que parseia mas veio incompleto. Faltava o
// caso que o agente MAIS encontra: o ENVELOPE do get_file persistido em disco. Ele e JSON
// valido e nao tem `fileCount`, entao escapava das duas guardas e caia no generico
// "payload sem `files`" -- provado rodando a versao mergeada contra o envelope de 259,5 KB.
{
  const dir = sandbox();
  const env = join(dir, 'envelope.json');
  writeFileSync(env, JSON.stringify({
    method: 'get_file', path: 'sync/payload.json', truncated: true,
    content: '{"schema":"cowork-payload/1","files":[{"path":"styles.css","content":"cortad',
  }), 'utf8');
  const r = rodar(dir, env);
  check('BITE envelope: identifica como envelope do get_file, nao como payload ruim',
    r.code === 2 && /ENVELOPE do DesignSync\.get_file/.test(r.out), r.out);
  check('BITE envelope: nao repete o generico "payload sem files"',
    !/payload sem/.test(r.out), r.out);
  check('BITE envelope: ensina o remedio das PARTES',
    /em PARTES de ate 256 KiB/.test(r.out), r.out);
}

// CONTROLE NEGATIVO: payload legitimo tem `files`; a guarda nova nao pode captura-lo.
{
  const dir = sandbox();
  const pay = completePayload(dir, [{ path: 'oimpresso.com.html', content: '<div>ok</div>' }]);
  const r = rodar(dir, pay, ['--require-complete-shell']);
  check('CONTROLE: payload legitimo NAO e confundido com envelope',
    r.code === 0 && !/ENVELOPE/.test(r.out) &&
    existsSync(join(dir, 'prototipo-ui/cowork/oimpresso.com.html')), r.out);
}

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ applier: fiel/atômico · shell transitivo completo · _ds persistente · recusa escopo/bytes · .md roteado p/ design-docs (nunca cowork/) · avisa regressão');

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

// ── C1: digest declarado × calculado — contradição REPORTADA, nunca veredito ──
{
  const dir = sandbox();
  const pay = payload(dir, [
    { path: 'a.jsx', content: 'a\n', fnv64: 'deadbeefdeadbeef' },   // errado de propósito
    { path: 'b.jsx', content: 'b\n', fnv64: 'cafecafecafecafe' },   // errado de propósito
    { path: 'c.jsx', content: 'c\n', fnv64: fnvRef('c\n') },        // correto
  ]);
  const obj = JSON.parse(readFileSync(pay, 'utf8'));
  obj.hash = 'fnv1a-64 (hex 16) sobre o conteudo UTF-8';
  writeFileSync(pay, JSON.stringify(obj), 'utf8');

  const r = rodar(dir, pay);
  check('BITE digest: contradição sai no rodapé com 2/3',
    /digest N[ÃA]O bate em 2\/3/.test(r.out), r.out);
  check('BITE digest: NÃO bloqueia — rc=0 e os 3 arquivos escritos',
    r.code === 0 && ['a.jsx', 'b.jsx', 'c.jsx'].every((f) => existsSync(join(dir, 'prototipo-ui/cowork', f))), 'rc=' + r.code + r.out);
  check('BITE digest: diz que segue como REFERÊNCIA, não veredito',
    /REFER[ÊE]NCIA, n[ãa]o veredito/.test(r.out), r.out);
}
{ // controle negativo: sem `hash` no envelope, não inventa contradição
  const dir = sandbox();
  const pay = payload(dir, [{ path: 'a.jsx', content: 'a\n', fnv64: 'deadbeefdeadbeef' }]);
  const r = rodar(dir, pay);
  check('CONTROLE digest: envelope sem `hash` → nenhuma linha de digest',
    r.code === 0 && !/digest/i.test(r.out), r.out);
}

// ── C2: `bytes` ausente é NÃO MEDIDO, não "conferido" ────────────────────────
{
  const dir = sandbox();
  const pay = payload(dir, [{ path: 'a.jsx', content: 'a\n', bytes: null }, { path: 'b.jsx', content: 'b\n' }]);
  const r = rodar(dir, pay);
  check('BITE sem-bytes: lote parcial escreve e AVISA 1 sem prova',
    r.code === 0 && /1 arquivo\(s\) escrito\(s\) SEM prova de bytes/.test(r.out), r.out);
  check('BITE sem-bytes: o arquivo sem prova foi mesmo escrito',
    existsSync(join(dir, 'prototipo-ui/cowork/a.jsx')), r.out);
}
{
  const dir = sandbox();
  const pay = completePayload(dir, [
    { path: 'oimpresso.com.html', content: '<html><body>x</body></html>\n' },
    { path: 'solto.jsx', content: 'z\n', bytes: null },
  ]);
  const r = rodar(dir, pay, ['--require-complete-shell']);
  check('BITE sem-bytes: --require-complete-shell RECUSA o lote',
    r.code === 1 && /sem `bytes`/.test(r.out), 'rc=' + r.code + r.out);
  check('BITE sem-bytes: nada escrito quando recusa',
    !existsSync(join(dir, 'prototipo-ui/cowork/solto.jsx')), r.out);
}

// ── C3: `missing` declarado é lido nos DOIS modos ────────────────────────────
{
  const dir = sandbox();
  const pay = payload(dir, [{ path: 'a.jsx', content: 'a\n' }]);
  const obj = JSON.parse(readFileSync(pay, 'utf8'));
  obj.missing = ['x.jsx', 'y.css'];
  writeFileSync(pay, JSON.stringify(obj), 'utf8');

  const r = rodar(dir, pay);
  check('BITE missing: lote parcial RELATA os ausentes e aplica (rc=0)',
    r.code === 0 && /declarou 2 ausente\(s\): x\.jsx, y\.css/.test(r.out), 'rc=' + r.code + r.out);
  check('BITE missing: aplicou mesmo relatando', existsSync(join(dir, 'prototipo-ui/cowork/a.jsx')), r.out);
}
{
  const dir = sandbox();
  const pay = completePayload(dir, [{ path: 'oimpresso.com.html', content: '<html></html>\n' }], ['x.jsx']);
  const r = rodar(dir, pay, ['--require-complete-shell']);
  check('BITE missing: --require-complete-shell continua BLOQUEANDO',
    r.code === 1 && /declarou 1 ausente/.test(r.out), 'rc=' + r.code + r.out);
}
{ // controle negativo: missing vazio não gera relato nenhum
  const dir = sandbox();
  const pay = payload(dir, [{ path: 'a.jsx', content: 'a\n' }]);
  const r = rodar(dir, pay);
  check('CONTROLE missing: `missing: []` → nenhum relato de ausente',
    r.code === 0 && !/ausente\(s\)/.test(r.out), r.out);
}

// ── 13: integração real produtor v2 → consumidor transacional ────────────────
{
  const producer = mkdtempSync(join(tmpdir(), 'design-v2-producer-'));
  const out = join(producer, 'sync');
  writeFileSync(join(producer, 'oimpresso.com.html'), '<link rel="stylesheet" href="grande.css">\n');
  writeFileSync(join(producer, 'grande.css'), ':root{}\n' + 'x'.repeat(80000));
  execFileSync(process.execPath, [GENERATOR, '--root', producer, '--out', out, '--cap', '70000', '--chunk-bytes', '30000', '--piso', '0'], { encoding: 'utf8' });
  const parts = readdirSync(out).filter((name) => /^payload\.part\d+\.json$/.test(name)).sort().map((name) => join(out, name));
  check('v2 E2E: gerador produziu múltiplas partes', parts.length > 1, String(parts.length));

  const consumer = sandbox();
  const dry = rodar(consumer, parts, ['--dry', '--require-complete-shell']);
  check('v2 E2E: dry-run valida o lote sem escrever', dry.code === 0 && !existsSync(join(consumer, 'prototipo-ui/cowork/grande.css')), dry.out);
  const applied = rodar(consumer, parts, ['--require-complete-shell']);
  check('v2 E2E: applier promove bytes idênticos', applied.code === 0 && readFileSync(join(consumer, 'prototipo-ui/cowork/grande.css')).equals(readFileSync(join(producer, 'grande.css'))), applied.out);
  check('v2 E2E: estado ativo foi persistido fora de _ds', existsSync(join(consumer, 'scripts/design-sync/state/active-bundle.json')), applied.out);

  const incomplete = sandbox();
  const missingPart01 = rodar(incomplete, parts.slice(1), ['--require-complete-shell']);
  check('v2 E2E: ausência da part01 reprova antes de escrever', missingPart01.code !== 0 && !existsSync(join(incomplete, 'prototipo-ui/cowork/grande.css')), missingPart01.out);
}

process.exit(fails ? 1 : 0);

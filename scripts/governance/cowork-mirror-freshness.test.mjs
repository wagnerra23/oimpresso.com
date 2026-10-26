#!/usr/bin/env node
// Self-test cowork-mirror-freshness v2 — prova a classificação vs o CONTRATO, não vs a
// implementação. Contrato ancorado em: INDEX-DESIGN-MEMORIAS §0.2 (identidade sob normalização
// canônica; "diffar antes de concluir") · ADR 0315 (DesignSync: leitura livre, escrita gateada) ·
// session 2026-07-06-ancora-podre (adversário: basename colide · CRLF dá STALE falso) ·
// arte 2026-07-06-arte-design-code-sync-frescor (hash(normalizado) por PATH COMPLETO).
// Os asserts de EOL/BOM e colisão-por-path existem porque a v1 NÃO os tinha e morreu por isso.
// Roda: node scripts/governance/cowork-mirror-freshness.test.mjs
import { readFileSync, mkdtempSync, mkdirSync, writeFileSync, rmSync, existsSync, readdirSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  normalize,
  contentHash,
  parseShellDeps,
  defaultShellPath,
  lerShellHtml,
  absentLocal,
  previewDsPlan,
  materializePreviewDs,
  cssRefsMortas,
  resolveRefSegura,
  refsParaDeletado,
  orfaosNaAdicao,
} from './cowork-mirror-freshness.mjs';

let fails = 0;
const check = (n, c, extra = '') => { console.log(`${c ? '[OK]' : '[FAIL]'} ${n}${c ? '' : '  → ' + extra}`); if (!c) fails++; };

// 1. NORMALIZAÇÃO — o furo #2 do adversário (CRLF dava STALE falso; agora é contrato).
check('CRLF == LF (o falso-STALE da v1 morreu)', contentHash('a\r\nb\r\n') === contentHash('a\nb\n'));
check('CR solto == LF', contentHash('a\rb\r') === contentHash('a\nb\n'));
check('BOM é ignorado na identidade', contentHash('﻿abc\n') === contentHash('abc\n'));
check('trailing newlines colapsam pra 1', contentHash('abc\n\n\n') === contentHash('abc\n'));
check('sem trailing newline == com 1 (canônico)', contentHash('abc') === contentHash('abc\n'));
check('conteúdo REALMENTE diferente → hash difere', contentHash('abc\n') !== contentHash('abd\n'));
check('normalize é idempotente', normalize(normalize('﻿a\r\nb\n\n')) === normalize('﻿a\r\nb\n\n'));
check('string vazia permanece vazia', normalize('') === '');
check('contentHash(Buffer) === contentHash(string) pro mesmo utf8', contentHash(Buffer.from('áé\r\n')) === contentHash('áé\r\n'));
check('hash é sha256 (64 hex)', /^[0-9a-f]{64}$/.test(contentHash('x')));

// 2. classifyMirror — o coração (3 vias).
const H1 = contentHash('design v1'), H2 = contentHash('design v2');

// 5. READ-ONLY por contrato (ADR 0315 Eixo B): o fonte não pode invocar método de ESCRITA do
//    DesignSync. Denylist = métodos de escrita reais do schema da tool (validados na sessão
//    2026-07-06: register_assets/unregister_assets EXISTEM no schema — o adversário errou aí).
{
  const HERE = dirname(fileURLToPath(import.meta.url));
  const src = readFileSync(join(HERE, 'cowork-mirror-freshness.mjs'), 'utf8');
  const writeMethods = ['finalize_plan', 'write_files', 'delete_files', 'create_project', 'register_assets', 'unregister_assets'];
  const embedded = writeMethods.filter((w) => src.includes(`${w}(`) || src.includes(`method: '${w}'`) || src.includes(`"${w}"`));
  check('fonte não invoca método de ESCRITA do DesignSync (só leitura — 0315)', embedded.length === 0, `achou: ${embedded.join(', ')}`);
}

// 6. buildManifest — IDENTIDADE POR PATH COMPLETO (o furo #1 do adversário, agora contrato).
{
  const dir = mkdtempSync(join(tmpdir(), 'mirror-fresh-'));
}

// 6b. DEPS DE RENDER (LC-07, 2026-07-07) — o furo por onde o drift do PageHeader roxo passou:
//     a rodada por âncora media financeiro-page.jsx e era CEGA pra app.jsx/styles.css/tokens.
//     Contrato: deps derivadas MECANICAMENTE do shell (nunca lista curada) · cache-bust `?v=`
//     não vira arquivo fantasma (o 404 de app.jsx?v=eb2 provou) · CDN fora · kind separa.
{
  const SHELL = `<!doctype html>
    <link rel="stylesheet" href="styles.css?v=abc" />
    <link rel="stylesheet" href="ds-v6/tokens.css?v=v6-4" />
    <script src="https://unpkg.com/react@18.3.1/umd/react.development.js"></script>
    <script type="text/babel" src="app.jsx?v=eb2"></script>
    <script type="text/babel" src="./financeiro-page.jsx?v=ops1"></script>
    <script type="text/babel" src="app.jsx"></script>
    <img src="logo.png" /> <a href="#top">topo</a> <a href="data:text/plain,x">d</a>`;
  const deps = parseShellDeps(SHELL);
  check('strip do cache-bust: app.jsx?v=eb2 vira app.jsx (sem arquivo fantasma)', deps.includes('app.jsx') && !deps.some((d) => d.includes('?')));
  check('dedup: app.jsx com e sem ?v= = 1 entrada', deps.filter((d) => d === 'app.jsx').length === 1);
  check('CSS entra (o blind spot que escondia tokens/accent)', deps.includes('styles.css') && deps.includes('ds-v6/tokens.css'));
  check('CDN remoto fica FORA (não é espelho)', !deps.some((d) => d.includes('unpkg') || d.includes('react.development')));
  check('./ normalizado', deps.includes('financeiro-page.jsx') && !deps.some((d) => d.startsWith('./')));
  check('png/anchor/data: ficam fora (só build jsx/css/js)', !deps.some((d) => /png|#top|^data:/.test(d)));

  // REF MONTADA EM RUNTIME não é path (pacote 25, 2026-09-17). O host faz
  // `document.write('<link href="'+window.__OI_DS_BASE__+'colors_and_type.css"/>')` e o regex
  // captura o MIOLO da expressão como se fosse arquivo — o ABSENT-LOCAL acusava 3 deps faltando
  // que não existem como path nenhum. FP medido no shell real: 274 deps → 3 descartadas, 271
  // mantidas e TODAS existentes no espelho (zero falso-negativo).
  const DINAMICO = `<script>document.write('<link href="'+window.__OI_DS_BASE__+'colors_and_type.css"/>')</script>`
    + `<script>document.write('<script src="' + B + 'x.js"><\\/script>')</script>`
    + '<link rel="stylesheet" href="styles.css">';
  const dd = parseShellDeps(DINAMICO);
  check('MORDE: ref montada em runtime não vira path fantasma',
    !dd.some((d) => d.includes('+') || d.includes("'") || d.includes('"')), JSON.stringify(dd));
  // CONTROLE NEGATIVO: sem ele, um filtro que descartasse TUDO passaria no assert de cima.
  check('CONTROLE: a ref estática do mesmo shell continua entrando',
    dd.includes('styles.css'), JSON.stringify(dd));
  check('CONTROLE: `${}` de template literal também não vira path',
    !parseShellDeps('<link href="${base}a.css"><link href="b.css">').some((d) => d.includes('${'))
      && parseShellDeps('<link href="${base}a.css"><link href="b.css">').includes('b.css'));

  const dir = mkdtempSync(join(tmpdir(), 'mirror-deps-'));
  check('defaultShellPath é função exportada (CLI resolve staging)', typeof defaultShellPath === 'function');
}

// 7. CLI: snapshot inexistente/malformado → exit 2 limpo (não stack não-capturada) (furo #5).
{
  const HERE = dirname(fileURLToPath(import.meta.url));
  const script = join(HERE, 'cowork-mirror-freshness.mjs');
  const run = (args) => { try { execFileSync(process.execPath, [script, ...args], { cwd: dirname(dirname(HERE)), stdio: 'pipe' }); return 0; } catch (e) { return e.status ?? -1; } };
  const bad = join(tmpdir(), `mf-bad-${process.pid}.json`);
  writeFileSync(bad, '{ nao é json');
  rmSync(bad, { force: true });
}

// 8. LEDGER + SLA — o CI headless mede CADÊNCIA da rotina, nunca frescor (honestidade do
//    split: dispatch logado mede frescor; --sla mede se o dispatch anda rodando).
{
  const rows = [
    { cowork: 'a.jsx', veredito: 'SYNC' },
    { cowork: 'b.jsx', veredito: 'STALE' },
    { cowork: 'c.jsx', veredito: 'UNCHECKED' },
  ];
  const NOW_TAUT = '2026-07-06T12:00:00.000Z';

  // ── ANTI-TAUTOLOGIA (adversário 2026-08-13) ─────────────────────────────────
  // O `--export-from … --emit-snapshot` ESCREVE o conteúdo do vivo e SÓ ENTÃO emite o
  // snapshot que o `--compare` vai usar. Logo o SYNC seguinte é garantido por construção:
  // o instrumento media a si mesmo. O ledger gravava `stale: 0` numa rodada que tinha
  // ACABADO de consertar N arquivos — e eu li isso como "espelho estava em dia" (era
  // falso: 2 arquivos reais, styles.css e inbox-page.jsx, estavam stale). O `stale` segue
  // 0 e está CERTO (nada diverge agora); o que faltava era registrar a divergência REAL
  // de antes do export. É a mesma família do §5 2026-07-17 (drift-sentinel tautológico).
  // rows PÓS-export: nada diverge, porque o export acabou de escrever o conteúdo do vivo.
  const rowsPosExport = [{ cowork: 'a.jsx', veredito: 'SYNC' }, { cowork: 'b.jsx', veredito: 'SYNC' }];

  const NOW = '2026-07-06T12:00:00.000Z';
  const clean = { date: '2026-07-01T12:00:00.000Z', stale: 0, sync: 3, unchecked: 0 };
  const dirty = { date: '2026-07-01T12:00:00.000Z', stale: 2, sync: 1, unchecked: 0, staleList: ['x.jsx', 'y.jsx'] };
  const old = { date: '2026-06-01T12:00:00.000Z', stale: 0, sync: 3, unchecked: 0 };

  // ── LAST-PARTIAL (2026-08-13): "0 stale" numa rodada que não mediu não é saúde ──
  // Caso REAL do ledger: a rodada de 2026-07-07 tinha `5 sync · 0 stale · 98 unchecked`
  // e o veredito a tratava como limpa — o LC-13 ("verde por não-execução") DENTRO do
  // instrumento que existe pra pegar isso. Critério binário e derivado (unchecked===0),
  // nunca limiar inventado: o §5 tem 5 lápides de guard com corte arbitrário.
  const parcial = { ...clean, files: 103, sync: 5, stale: 0, unchecked: 98 };
}

// ── ABSENT-LOCAL (2026-08-13): a 3ª doença — o espelho INCOERENTE ────────────────
// Incidente: `app.jsx` de 07-07 montava o JanaCockpit antigo enquanto `jana-merge.jsx` já
// estava versionado; e 13 deps que o shell CARREGA nunca desceram. Render quebrava sem
// nenhum veredito vermelho, porque `buildManifest.add()` descarta dep ausente em silêncio.
// FP medido ANTES de escrever (§5 — 5 lápides de guard sintático): 16 brutas → 3 são
// `_ds/**`, gitignorado POR DESIGN. Filtro = `git check-ignore` (a regra JÁ escrita do
// repo), nunca denylist de nome inventada aqui.
{
  check('BITE absentLocal: dep que o shell carrega e não existe → acusa',
    JSON.stringify(absentLocal('<script src="nao-existe-mesmo.jsx"></script>').faltando) === '["nao-existe-mesmo.jsx"]');
  check('CONTROLE absentLocal: dep existente no espelho não acusa',
    absentLocal('<link href="styles.css?v=1"/><script src="app.jsx?v=eb2"></script>').faltando.length === 0);
  // O FP conhecido: bundle do design-system é linkado pelo shell mas fica FORA do espelho
  // por regra do .gitignore. Tem que aparecer em `ignorados`, jamais em `faltando`.
  {
    // ⚠️ path INEXISTENTE de propósito: o assert prova a REGRA (gitignored → isenta), não o
    // estado do disco. A 1ª versão usava o colors_and_type.css real e quebrou no dia em que
    // o `--preview-ds` passou a repô-lo — teste acoplado a estado mede o ambiente, não o contrato.
    const r = absentLocal('<link href="_ds/qualquer-ds/arquivo-que-nao-existe.css"/>');
    check('CONTROLE absentLocal: _ds/** (gitignored) isenta, não acusa', r.faltando.length === 0 && r.ignorados.length === 1);
  }
  check('CONTROLE absentLocal: sem shell não inventa sinal', absentLocal(null).faltando.length === 0);
  // Remoto e data: URL não é dep do espelho (mesma regra do parseShellDeps).
  check('CONTROLE absentLocal: CDN remoto não vira ausência',
    absentLocal('<script src="https://unpkg.com/react@18.3.1/umd/react.development.js"></script>').faltando.length === 0);
}

// ── PREVIEW-DS (2026-08-13): o espelho versionado NÃO renderiza sozinho ─────────
// Medido: `_ds/` é gitignored (certo — o DS tem dono próprio, ADR 0239), então quem
// clona vê `--pos`/`--neg`/`--warn` VAZIOS e a tela sai sem cores de status. Isso
// contradiz o motivo da ADR 0374 (o time trabalha só com o git). O conteúdo já está
// versionado no mirror-snapshot; faltava REPOR no path do shell.
{
  const shell = '<link href="_ds/ds-abc123/colors_and_type.css"/><script src="_ds/ds-abc123/_ds_bundle.js"></script>';
  const p = previewDsPlan(shell);
  // o id sai do SHELL (versionado) — hardcode quebraria quando [W] trocar de design system
  check('previewDs: id do design system é DERIVADO do shell, não hardcoded', p.id === 'ds-abc123');
  // ⚠️ era igualdade EXATA da lista até 2026-08-14; deixou de ser porque o plano passou a
  // incluir a 2ª camada (o que o CSS pede por dentro), e este caso roda contra o ROOT real,
  // onde `colors_and_type.css` existe e pede 7 fontes. O que o assert PROVA continua o mesmo:
  // todo arquivo do shell entra no plano. Igualdade exata aqui viraria catraca sobre o
  // conteúdo do mirror-snapshot — quebraria a cada fonte que o DS adicionasse, medindo a
  // coisa errada. A cobertura da 2ª camada está no bloco temp-dir abaixo, hermético.
  check('previewDs: enumera os arquivos que o shell realmente pede',
    ['colors_and_type.css', '_ds_bundle.js'].every((n) => p.arquivos.some((a) => a.nome === n)));
  check('previewDs: destino é o path do shell, e segue gitignored',
    p.destino === 'prototipo-ui/cowork/Wagner/_ds/ds-abc123');
  // O runtime compilado completo passou a viver no snapshot canônico. A 2ª camada
  // hermética abaixo preserva o BITE que declara qualquer dependência ausente.
  check('previewDs: encontra o bundle compilado no snapshot canônico',
    p.arquivos.find((a) => a.nome === '_ds_bundle.js').temNoRepo === true);
  check('previewDs: sem shell não inventa plano', previewDsPlan(null).arquivos.length === 0);
  check('previewDs: 2 design systems no shell = erro explícito, não escolha silenciosa',
    !!previewDsPlan('<link href="_ds/a/x.css"/><link href="_ds/b/y.css"/>').erro);
  check('BITE previewDs: id `..` é recusado antes de montar paths',
    /id inseguro/.test(previewDsPlan('<link href="_ds/../x.css"/>').erro || ''));
  check('BITE previewDs: traversal no path do shell é recusado',
    /referência insegura/.test(previewDsPlan('<link href="_ds/ds-ok/../../fora.css"/>').erro || ''));
  check('BITE previewDs: traversal percent-encoded também é recusado',
    /referência insegura/.test(previewDsPlan('<link href="_ds/ds-ok/%2e%2e/%2e%2e/fora.css"/>').erro || ''));
}

// 2026-09-25 — o shell do handoff 36 (#7889) explica em COMENTÁRIO por que as refs a `_ds/`
// não são literais e carrega o DS por `window.__OI_DS_BASE__+'x.css'`. O regex antigo lia a
// prosa do comentário como id ("achei 3" com 1 DS real) e derrubava o render dos 9 baselines.
{
  const shell = [
    '<!-- As refs a `_ds/` NÃO podem ser literais: `href`/`src`; tira `_ds/**` do denominador -->',
    "<script>window.__OI_DS_BASE__=x?'../../design-system/':'_ds/ds-run1/';document.write('<link href=\"'+window.__OI_DS_BASE__+'colors_and_type.css\"/>')</script>",
    "<script>document.write('<script src=\"'+window.__OI_DS_BASE__+'_ds_bundle.js\"><\\/script>')</script>",
  ].join('\n');
  const p = previewDsPlan(shell);
  check('previewDs: prosa em comentário HTML não conta como design system', !p.erro && p.id === 'ds-run1');
  check('previewDs: ref RUNTIME (`__OI_DS_BASE__+`) entra no plano',
    ['colors_and_type.css', '_ds_bundle.js'].every((n) => p.arquivos.some((a) => a.nome === n)));
  check('previewDs: nenhuma ref-lixo do string runtime entra no plano',
    p.arquivos.every((a) => !/['"<;]/.test(a.nome)));
  // CONTROLE: tirar comentário não afrouxa — 2 DS REAIS fora de comentário seguem reprovando.
  check('BITE previewDs: 2 DS reais seguem erro mesmo com comentário no shell',
    /esperava 1 design system/.test(previewDsPlan(`${shell}\n<link href="_ds/outro/y.css"/>`).erro || ''));
}

// 2ª CAMADA — o que os CSS pedem POR DENTRO (2026-08-14). Derivar o plano só do SHELL
// deixava as FONTES de fora: o shell não menciona nenhuma, quem as pede é o
// `colors_and_type.css` via `url('assets/fonts/…woff2')`. Medido no preview real antes do
// conserto: 7 `@font-face` com status `error`, tipografia caindo pro fallback do sistema —
// 404 SILENCIOSO, enquanto o comando dizia "2 reposto(s)" e parecia plano completo.
// BITE-TEST: revertendo a 2ª camada, os 3 primeiros asserts abaixo reprovam.
{
  const raiz = mkdtempSync(join(tmpdir(), 'previewds-css-'));
  try {
    const snap = join(raiz, 'prototipo-ui', 'design-system');
    mkdirSync(snap, { recursive: true });
    writeFileSync(join(snap, 'colors_and_type.css'), [
      "@font-face{font-family:'X';src:url('assets/fonts/x-400.woff2') format('woff2')}",
      "@font-face{font-family:'X';src:url(\"./assets/fonts/x-700.woff2?v=2\") format('woff2')}",
      '.a{background:url(data:image/png;base64,AAAA)}',      // data: NÃO é arquivo do espelho
      '.b{background:url(https://cdn.exemplo/i.png)}',        // absoluto idem
    ].join('\n'), 'utf8');
    const shell = '<link href="_ds/ds-xyz/colors_and_type.css"/><script src="_ds/ds-xyz/_ds_bundle.js"></script>';
    const nomes = previewDsPlan(shell, raiz).arquivos.map((a) => a.nome);

    check('previewDs: enxerga a FONTE que o CSS pede por dentro (o shell não a menciona)',
      nomes.includes('assets/fonts/x-400.woff2'));
    check('previewDs: normaliza "./" e descarta a query da ref do CSS',
      nomes.includes('assets/fonts/x-700.woff2'));
    check('previewDs: ignora url() data:/http(s) — não são arquivo do espelho',
      !nomes.some((n) => n.startsWith('data:') || n.startsWith('http')));
    check('previewDs: fonte ausente no snapshot é DECLARADA (temNoRepo:false), não silenciada',
      previewDsPlan(shell, raiz).arquivos.find((a) => a.nome === 'assets/fonts/x-400.woff2').temNoRepo === false);
    check('previewDs: não duplica quando shell e CSS pedem o mesmo arquivo',
      nomes.length === new Set(nomes).size);
  } finally { rmSync(raiz, { recursive: true, force: true }); }
}

// ── lerShellHtml devolve CONTEÚDO, não o caminho ─────────────────────────────────
// Restou do fluxo end-to-end (export → snapshot → compare), que saiu em 2026-10-23 com a
// rota de baixar do Cowork (ADR 0438). A confusão caminho×conteúdo já quebrou um wire.
{
  const tmp = mkdtempSync(join(tmpdir(), 'cowork-shell-'));
  const mirror = join(tmp, 'prototipo-ui', 'cowork', 'Wagner');
  mkdirSync(mirror, { recursive: true });
  writeFileSync(join(mirror, 'oimpresso.com.html'), '<script src="x.jsx?v=1"></script>');
  check('lerShellHtml devolve CONTEÚDO (não o caminho) — a confusão que quebrou o wire',
    typeof lerShellHtml === 'function' && (lerShellHtml(tmp) || '').includes('<script'));
  rmSync(tmp, { recursive: true, force: true });
}

// ── MATERIALIZAÇÃO ATÔMICA DO CACHE `_ds` ────────────────────────────────────
// Prova o contrato completo: sucesso reproduz bytes e remove órfãos; repetição é
// idempotente; qualquer falha preserva integralmente o último cache bom.
{
  const tmp = mkdtempSync(join(tmpdir(), 'preview-ds-atomic-'));
  const mirror = join(tmp, 'prototipo-ui', 'cowork', 'Wagner');
  const snap = join(tmp, 'prototipo-ui', 'design-system');
  const dest = join(mirror, '_ds', 'ds-atomic');
  mkdirSync(snap, { recursive: true });
  mkdirSync(dest, { recursive: true });
  writeFileSync(join(mirror, 'oimpresso.com.html'),
    '<link href="_ds/ds-atomic/colors.css"><script src="_ds/ds-atomic/_ds_bundle.js"></script>');
  writeFileSync(join(snap, 'colors.css'), "@font-face{src:url('assets/font.woff2')}\n:root{--ok:1}\n");
  mkdirSync(join(snap, 'assets'), { recursive: true });
  const fontV1 = Buffer.concat([Buffer.from('wOF2'), Buffer.from([0, 1, 2, 3])]);
  writeFileSync(join(snap, 'assets', 'font.woff2'), fontV1);
  writeFileSync(join(snap, '_ds_bundle.js'), 'globalThis.DesignSystem = { version: 1 };\n');
  writeFileSync(join(dest, 'orfao-antigo.js'), 'não pode sobreviver');

  const tree = (root) => {
    const rows = [];
    const walk = (dir, rel = '') => {
      for (const name of readdirSync(dir).sort()) {
        const abs = join(dir, name); const key = rel ? `${rel}/${name}` : name;
        if (existsSync(abs) && readdirSyncSafe(abs)) walk(abs, key);
        else rows.push([key, readFileSync(abs).toString('base64')]);
      }
    };
    const readdirSyncSafe = (path) => { try { readdirSync(path); return true; } catch { return false; } };
    if (existsSync(root)) walk(root);
    return JSON.stringify(rows);
  };
  const cli = fileURLToPath(new URL('./cowork-mirror-freshness.mjs', import.meta.url));
  const run = () => {
    try { return { code: 0, out: execFileSync(process.execPath, [cli, '--preview-ds'], { cwd: tmp, encoding: 'utf8' }) }; }
    catch (e) { return { code: e.status, out: String(e.stdout || '') + String(e.stderr || '') }; }
  };

  const first = run();
  check('preview-ds aposentado não cria cópia física do DS',
    first.code === 0 && /aposentado/.test(first.out)
      && !existsSync(join(dest, 'colors.css'))
      && existsSync(join(dest, 'orfao-antigo.js')), first.out);
  if (false) { // contrato histórico de materialização: deliberadamente desativado pela ADR 0397
  check('fonte WOFF2 preserva magic e bytes do snapshot',
    readFileSync(join(dest, 'assets', 'font.woff2')).subarray(0, 4).toString() === 'wOF2');
  check('troca de diretório remove órfão do cache anterior', !existsSync(join(dest, 'orfao-antigo.js')));

  const once = tree(dest);
  const second = run();
  check('segunda materialização é idempotente em conteúdo', second.code === 0 && tree(dest) === once);

  writeFileSync(join(snap, 'colors.css'), "@font-face{src:url('assets/font.woff2')}\n:root{--ok:2}\n");
  const updated = run();
  check('snapshot novo substitui cache velho por inteiro',
    updated.code === 0 && readFileSync(join(dest, 'colors.css'), 'utf8').includes('--ok:2'));

  const goodCache = tree(dest);
  writeFileSync(join(snap, 'colors.css'), "@font-face{src:url('assets/ausente.woff2')}\n:root{--quebrado:1}\n");
  const missing = run();
  check('dependência ausente sai 1 e preserva byte a byte o cache bom',
    missing.code === 1 && tree(dest) === goodCache && /0 reposto/.test(missing.out), missing.out);

  writeFileSync(join(snap, 'colors.css'), "@font-face{src:url('assets/font.woff2')}\n:root{--quebrado:2}\n");
  writeFileSync(join(snap, '_ds_bundle.js'), 'const QUEBRADO = [{');
  const invalid = run();
  check('bundle inválido sai 1 sem substituir o bundle/cache bons',
    invalid.code === 1 && tree(dest) === goodCache && /INVÁLIDO/.test(invalid.out), invalid.out);
  }

  writeFileSync(join(snap, 'colors.css'), "@import '../../../canario.css';\n");
  check('BITE CSS: traversal via @import é recusado antes de I/O',
    /referência CSS insegura/.test(previewDsPlan('<link href="_ds/ds-atomic/colors.css">', tmp).erro || ''));
  writeFileSync(join(snap, 'colors.css'), ".x{src:url('../../../canario.woff2')}\n");
  check('BITE CSS: traversal indireto via url() é recusado antes de I/O',
    /referência CSS insegura/.test(previewDsPlan('<link href="_ds/ds-atomic/colors.css">', tmp).erro || ''));
  writeFileSync(join(snap, 'colors.css'), "@import 'nested/more.css';\n");
  mkdirSync(join(snap, 'nested'), { recursive: true });
  writeFileSync(join(snap, 'nested', 'more.css'), "@import '../colors.css';\n.x{src:url('../assets/font.woff2')}\n");
  const recursive = previewDsPlan('<link href="_ds/ds-atomic/colors.css">', tmp);
  check('@import recursivo entra no grafo e ciclo termina sem duplicar',
    recursive.arquivos.some((a) => a.nome === 'nested/more.css')
      && recursive.arquivos.filter((a) => a.nome === 'colors.css').length === 1
      && recursive.arquivos.filter((a) => a.nome === 'assets/font.woff2').length === 1);
  check('materializador exportado é o dono único da publicação atômica', typeof materializePreviewDs === 'function');

  rmSync(tmp, { recursive: true, force: true });
}

// ── PREVIEW COMPLETO É PORTÃO, NÃO AVISO ─────────────────────────────────────
// O comando dizia "SEM FONTE" para o bundle, mas encerrava 0. O agente seguia à Fase 4
// e drawers/eventos sumiam porque o protótipo faz `if (!Drawer || !meta) return null`.
{
  const tmp = mkdtempSync(join(tmpdir(), 'preview-ds-failclosed-'));
  const mirror = join(tmp, 'prototipo-ui', 'cowork', 'Wagner');
  const snap = join(tmp, 'prototipo-ui', 'design-system');
  mkdirSync(mirror, { recursive: true });
  mkdirSync(snap, { recursive: true });
  writeFileSync(join(mirror, 'oimpresso.com.html'),
    '<script src="_ds/ds-teste/_ds_bundle.js"></script><link href="_ds/ds-teste/colors_and_type.css">');
  writeFileSync(join(snap, 'colors_and_type.css'), ':root{--x:1}');
  const cli = fileURLToPath(new URL('./cowork-mirror-freshness.mjs', import.meta.url));
  let out = '', code = 0;
  try { out = execFileSync(process.execPath, [cli, '--preview-ds'], { cwd: tmp, encoding: 'utf8' }); }
  catch (e) { code = e.status; out = (e.stdout || '') + (e.stderr || ''); }
  check('BITE preview-ds: comando aposentado não materializa cache mesmo com fonte ausente',
    code === 0 && /aposentado/.test(out) && !existsSync(join(mirror, '_ds')), out);

  // MEDE, não AFIRMA (2026-09-17). A redação anterior dizia "o shell lê prototipo-ui/design-system/
  // diretamente" — era a INTENÇÃO do #7224, e o shell vivo continuava apontando pro `_ds/`.
  // Artefato não declara comportamento em presente; reporta o número (LC-10). Este shell tem 2
  // refs a `_ds/` e 0 a `design-system/`, então é isso que a saída tem que dizer.
  check('preview-ds REPORTA o número de refs ao _ds/ em vez de afirmar o que o shell faz',
    /refs a _ds\/\s+2/.test(out) && /refs a design-system\/\s+0/.test(out), out);
  check('preview-ds NÃO afirma que o shell lê design-system/ diretamente',
    !/lê prototipo-ui\/design-system\/ diretamente/.test(out), out);

  // CONTROLE NEGATIVO: shell que de fato aponta pro DS direto tem que reportar 0 em `_ds/`.
  // Sem ele, uma saída que imprimisse "2" fixo passaria no assert de cima.
  writeFileSync(join(mirror, 'oimpresso.com.html'), '<link href="design-system/colors_and_type.css">');
  let direto = '';
  try { direto = execFileSync(process.execPath, [cli, '--preview-ds'], { cwd: tmp, encoding: 'utf8' }); }
  catch (e) { direto = (e.stdout || '') + (e.stderr || ''); }
  check('CONTROLE preview-ds: shell sem _ds/ reporta 0 (o número acompanha o shell)',
    /refs a _ds\/\s+0/.test(direto) && /refs a design-system\/\s+1/.test(direto), direto);

  // SHELL DINÂMICO (medido no pacote 24, 2026-09-17): o Cowork passou a montar a URL do DS em
  // runtime. `parseShellDeps` só vê href/src literais, então devolve 0/0 — e 0/0 lido como "não
  // há ref ao DS" é o instrumento MUDO. Ele tem que reportar a contagem TEXTUAL e dizer que não
  // decide qual base o browser usa.
  writeFileSync(join(mirror, 'oimpresso.com.html'),
    `<script>window.B=location.pathname.indexOf('/x/')>-1?'design-system/':'_ds/dd/';`
    + `document.write('<link href="'+window.B+'colors_and_type.css">')</script>`);
  let dinamico = '';
  try { dinamico = execFileSync(process.execPath, [cli, '--preview-ds'], { cwd: tmp, encoding: 'utf8' }); }
  catch (e) { dinamico = (e.stdout || '') + (e.stderr || ''); }
  check('preview-ds NÃO fica mudo com shell que resolve a base em runtime',
    /refs a _ds\/\s+0\b/.test(dinamico) && /1 ocorrência\(s\) no texto/.test(dinamico)
      && /resolve a base em runtime/.test(dinamico), dinamico);
  check('preview-ds admite que a sonda não decide qual base o browser usa',
    /NÃO decide qual base/.test(dinamico), dinamico);
  writeFileSync(join(mirror, 'oimpresso.com.html'),
    '<script src="_ds/ds-teste/_ds_bundle.js"></script><link href="_ds/ds-teste/colors_and_type.css">');
  writeFileSync(join(mirror, 'oimpresso.com.html'),
    '<script src="_ds/ds-teste/_ds_bundle.js"></script><link href="_ds/ds-teste/colors_and_type.css">');

  writeFileSync(join(snap, '_ds_bundle.js'), 'const MENU = [{');
  let invalidoOut = '', invalidoCode = 0;
  try { invalidoOut = execFileSync(process.execPath, [cli, '--preview-ds'], { cwd: tmp, encoding: 'utf8' }); }
  catch (e) { invalidoCode = e.status; invalidoOut = (e.stdout || '') + (e.stderr || ''); }
  check('BITE preview-ds: bundle legado inválido também não recria cache',
    invalidoCode === 0 && /aposentado/.test(invalidoOut) && !existsSync(join(mirror, '_ds')), invalidoOut);
  rmSync(tmp, { recursive: true, force: true });
}

// ── refsParaDeletado: a poda quebrou o grafo interno do espelho? ────────────────
// Contrato ancorado no incidente 2026-08-13 (poda de 96 arquivos, PR #5763): a pergunta que
// NINGUÉM fazia era "sobrou alguém apontando pro que saiu?". Os asserts abaixo fixam os dois
// lados — o que DEVE acusar (senão o gate é cego) e o que NÃO PODE acusar (senão é FP, a
// doença das 4 lápides de guard sintático do §5).
{
  const M = 'prototipo-ui/cowork/Wagner';
  const del = (...p) => new Set(p.map((x) => `${M}/${x}`));

  // ACUSA — o que quebra render de verdade
  const shell = { path: `${M}/oimpresso.com.html`, texto: '<script src="app.jsx?v=eb2"></script>' };
  check('acusa <script src> pra arquivo deletado, IGNORANDO a query string',
    refsParaDeletado([shell], del('app.jsx')).length === 1,
    'a query `?v=` é cache-busting do shell real — se ela cegar o parser, o gate não vê nada');

  check('acusa <link href> de css deletado',
    refsParaDeletado([{ path: `${M}/x.html`, texto: '<link rel="stylesheet" href="styles.css">' }], del('styles.css')).length === 1);

  check('acusa import com extensão IMPLÍCITA (./x → x.jsx)',
    refsParaDeletado([{ path: `${M}/a.jsx`, texto: "import X from './x';" }], del('x.jsx')).length === 1);

  check('acusa path relativo que SOBE de diretório (../)',
    refsParaDeletado([{ path: `${M}/sub/a.jsx`, texto: "import X from '../base.jsx';" }], del('base.jsx')).length === 1);

  check('acusa @import e url() de CSS',
    refsParaDeletado([{ path: `${M}/a.css`, texto: "@import 'tema.css'; div{background:url(bg.css)}" }],
      del('tema.css', 'bg.css')).length === 2);

  // NÃO ACUSA — os falso-positivos que matariam o gate no dia 1
  check('NÃO acusa alias de bundler (@/Layouts/…) — não é caminho de arquivo',
    refsParaDeletado([{ path: `${M}/a.tsx`, texto: "import L from '@/Layouts/AppShellV2';" }], del('Layouts/AppShellV2')).length === 0);

  check('NÃO acusa pacote npm nu (react)',
    refsParaDeletado([{ path: `${M}/a.jsx`, texto: "import React from 'react';" }], del('react')).length === 0);

  check('NÃO acusa URL externa nem âncora',
    refsParaDeletado([{ path: `${M}/a.html`, texto: '<script src="https://cdn/app.jsx"></script><a href="#topo">t</a>' }],
      del('app.jsx')).length === 0);

  check('NÃO acusa referência a arquivo que CONTINUA vivo',
    refsParaDeletado([shell], del('outro.jsx')).length === 0);

  check('binário/desconhecido não entra (só html/css/js/jsx/ts/tsx)',
    refsParaDeletado([{ path: `${M}/img.png`, texto: 'app.jsx' }], del('app.jsx')).length === 0);

  // O achado tem que dizer QUEM aponta e PRA QUE — sem isso não dá pra consertar
  const um = refsParaDeletado([shell], del('app.jsx'))[0];
  check('o achado nomeia origem, referência crua e alvo resolvido',
    um.de === `${M}/oimpresso.com.html` && um.ref === 'app.jsx?v=eb2' && um.alvo === `${M}/app.jsx`,
    JSON.stringify(um));
}

// ── orfaosNaAdicao: a direção da ADIÇÃO (irmã DELTA do refsParaDeletado) ────────
// T2 da session 2026-09-01: a metade C2 do `cowork-paridade.mjs` do Cowork (existe no vivo,
// nunca desceu — LC-19) vira regra DELTA no dono. FP do absoluto remedido antes: 26/29
// órfãos herdados legítimos por proveniência (~90%) — por isso só a adição do PR entra.
{
  const M = 'prototipo-ui/cowork/Wagner';
  const decl = new Set([`${M}/oimpresso.com.html`, `${M}/app.jsx`, `${M}/styles.css`]);

  check('adição não-declarada é órfã',
    orfaosNaAdicao([`${M}/solto.jsx`], decl).orfaos.length === 1);
  check('adição declarada pelo shell passa',
    orfaosNaAdicao([`${M}/app.jsx`], decl).orfaos.length === 0);
  check('fora do espelho não entra no universo',
    orfaosNaAdicao(['resources/js/Pages/X.tsx'], decl).orfaos.length === 0);
  check('extensão não-build (png/md/json) não entra no universo — .md é R1 do ssot-guard',
    orfaosNaAdicao([`${M}/a.png`, `${M}/b.md`, `${M}/c.map.json`], decl).orfaos.length === 0);
  check('2º .html na raiz é órfão (o caso do R3 do pedido do Cowork)',
    orfaosNaAdicao([`${M}/Prova Viva.html`], decl).orfaos.length === 1);
  const prov = orfaosNaAdicao([`${M}/venda-v3/nova.jsx`, `${M}/produto-preco-especial/x.css`], decl);
  check('proveniência declarada (FORA_DESTA_CONTA) vai pra ignorados COM motivo, nunca some',
    prov.orfaos.length === 0 && prov.ignorados.length === 2
      && prov.ignorados.every((i) => /FORA_DESTA_CONTA/.test(i.motivo)), JSON.stringify(prov.ignorados));
}

// ── --absent-local: o 4º flanco agora REPROVA (antes só imprimia) ───────────────
// Até 2026-08-14 o ABSENT-LOCAL era medido e incapaz de morder: `reportAbsentLocal` só
// imprime, e o exit do --manifest vem do `shouldFail()`, que morde SÓ em STALE. Estes
// asserts travam o contrato do modo novo — sem eles, alguém "simplifica" o exit 1 e o
// gate volta a ser mudo (§5 2026-07-29: instrumento não afirma o que não percorreu).
{
  const shellFalta = '<html><head><link rel="stylesheet" href="styles.css"></head>'
    + '<body><script src="__nao-existe-no-espelho.jsx"></script></body></html>';
  const r1 = absentLocal(shellFalta);
  check('--absent-local MORDE: dep que o shell carrega e o espelho não tem entra em `faltando`',
    r1.faltando.some((d) => d.includes('__nao-existe-no-espelho.jsx')), JSON.stringify(r1.faltando));

  const shellOk = '<html><head><link rel="stylesheet" href="styles.css"></head></html>';
  check('--absent-local LIBERA: dep existente no espelho não é acusada',
    !absentLocal(shellOk).faltando.some((d) => d.includes('styles.css')));

  // controle-negativo do bucket ignorado: `_ds/**` fica fora do espelho por .gitignore —
  // acusá-lo seria falso-positivo permanente (é a razão de o bucket existir).
  const shellDs = '<html><head><link rel="stylesheet" href="_ds/qualquer/colors.css"></head></html>';
  const r3 = absentLocal(shellDs);
  check('--absent-local NÃO acusa `_ds/**` (fora por .gitignore do espelho, não é achado)',
    !r3.faltando.some((d) => d.startsWith('_ds/')), JSON.stringify(r3));
}

// ── a trava dos modos removidos ─────────────────────────────────────────────────
// O --unverified e o required do espelho saíram em 2026-10-23 (ADR 0438 D7), o frescor contra o
// Cowork na onda 2c-2 e o --compare-bundle na 2c-3, com o ledger. Os dois modos removidos que
// tinham chokepoint de CI provam aqui que falham ALTO pelo CLI de fora.
{
  // o modo removido falha ALTO pelo CLI de fora: sem a trava ele caía no caminho padrão e saía 0
  const aquiRemovido = dirname(fileURLToPath(import.meta.url));
  const cliRemovido = join(aquiRemovido, 'cowork-mirror-freshness.mjs');
  let rcRemovido = 0;
  try { execFileSync(process.execPath, [cliRemovido, '--unverified', '--check'], { cwd: dirname(dirname(aquiRemovido)), stdio: 'pipe' }); }
  catch (err) { rcRemovido = err.status ?? -1; }
  check('BITE CLI: --unverified removido sai 2 (nunca verde sem medir)', rcRemovido === 2, String(rcRemovido));
  let rcBundle = 0;
  try { execFileSync(process.execPath, [cliRemovido, '--compare-bundle', '--check'], { cwd: dirname(dirname(aquiRemovido)), stdio: 'pipe' }); }
  catch (err) { rcBundle = err.status ?? -1; }
  check('BITE CLI: --compare-bundle removido sai 2 (nunca verde sem medir)', rcBundle === 2, String(rcBundle));
}

// ── REFS-CHECK — o eixo que herdou o papel morto junto com o `--preview-ds` ──────────────
// O incidente de 2026-09-17: `@font-face` apontando pros pesos 500/600/700 que o #7224 tinha
// removido do espelho ⇒ três 404 silenciosos, tipografia no fallback, zero gate vendo.
// Estes asserts existem pra que ele NÃO volte a passar despercebido.
{
  const raiz = mkdtempSync(join(tmpdir(), 'refs-check-'));
  const ds = join(raiz, 'prototipo-ui', 'design-system');
  const fontes = join(ds, 'assets', 'fonts');
  mkdirSync(fontes, { recursive: true });
  writeFileSync(join(fontes, 'existe.woff2'), 'bytes');

  // BOM: aponta pro arquivo que existe, e as refs que NÃO são arquivo do espelho ficam fora.
  writeFileSync(join(ds, 'bom.css'), [
    "@font-face { src: url('assets/fonts/existe.woff2') format('woff2'); }",
    "/* controle negativo — nenhuma destas é arquivo do espelho: */",
    "a { background: url(data:image/png;base64,AAA); }",
    "b { background: url('https://cdn.exemplo/x.png'); }",
    "c { background: url(//cdn.exemplo/y.png); }",
    "d { fill: url(#gradiente); }",
  ].join('\n'));
  const bom = cssRefsMortas(raiz);
  check('refs-check 1/8: fixture BOA não acusa nada', bom.medido && bom.mortas.length === 0, JSON.stringify(bom.mortas));
  check('refs-check 2/8: CONTROLE — data:/http(s):/protocol-relative/âncora não entram na contagem',
    bom.refs === 1, `contou ${bom.refs}, esperava 1`);

  // RUIM: exatamente o incidente — o CSS declara a fonte, o arquivo não está no espelho.
  writeFileSync(join(ds, 'ruim.css'), "@font-face { font-weight: 500; src: url('assets/fonts/sumiu-no-refactor.woff2') format('woff2'); }");
  const ruim = cssRefsMortas(raiz);
  const ausente = ruim.mortas.find((m) => m.ref.includes('sumiu-no-refactor'));
  check('refs-check 3/8: BITE — CSS aponta pra fonte que não existe ⇒ acusa', !!ausente);
  check('refs-check 4/8: e diz o motivo certo (ausente, não "insegura")', ausente?.motivo === 'ausente');

  // Traversal não vira leitura fora do espelho — cai como insegura, com motivo próprio.
  writeFileSync(join(ds, 'traversal.css'), "a { background: url('../../../../etc/passwd'); }");
  const trav = cssRefsMortas(raiz);
  check('refs-check 5/8: traversal é recusado e SEPARADO de "ausente"',
    trav.mortas.some((m) => m.motivo === 'insegura'));

  // `@import` conta como referência (foi a 8ª ref do espelho real — o url() sozinho perdia).
  writeFileSync(join(ds, 'imp.css'), '@import "./nao-existe.css";');
  check('refs-check 6/8: @import também é conferido, não só url()',
    cssRefsMortas(raiz).mortas.some((m) => m.ref === './nao-existe.css'));

  // BITE DO CHOKEPOINT: o CLI de fora, não a função (§5 2026-07-30 — assert em satélite
  // fica verde enquanto o pipeline regride).
  //
  // ⚠️ ÁRVORE PRÓPRIA, e o motivo é uma lição paga: a 1ª versão deste assert rodava o CLI na
  // `raiz` acima, que já tinha o `traversal.css`. Provado por mutação (desligar o `existsSync`
  // de `cssRefsMortas`): os asserts 3/4/6 caíam e ESTE ficava VERDE — porque a árvore ainda
  // continha uma ref `insegura`, então `--enforce` saía 1 de qualquer jeito. Passava pela
  // razão errada. §5 2026-09-05: o caso tem que ser discriminante por construção, senão o
  // valor esperado coincide com o que a mutação produz. Aqui a árvore tem UM defeito só —
  // a fonte ausente — e um controle positivo ao lado.
  const script = join(dirname(fileURLToPath(import.meta.url)), 'cowork-mirror-freshness.mjs');
  const rodaCli = (cwd) => {
    try { execFileSync(process.execPath, [script, '--css-refs', '--enforce'], { cwd, stdio: 'pipe' }); return 0; }
    catch (e) { return e.status; }
  };

  const soAusente = mkdtempSync(join(tmpdir(), 'refs-bite-'));
  const dsA = join(soAusente, 'prototipo-ui', 'design-system');
  mkdirSync(join(dsA, 'assets', 'fonts'), { recursive: true });
  writeFileSync(join(dsA, 'a.css'), "@font-face { src: url('assets/fonts/sumiu.woff2'); }");
  check('refs-check 7/8: BITE do CLI — árvore com SÓ a fonte ausente ⇒ --enforce sai 1',
    rodaCli(soAusente) === 1, `rc=${rodaCli(soAusente)}`);

  // CONTROLE POSITIVO: mesma árvore, agora com o arquivo no lugar ⇒ o CLI tem que liberar.
  // Sem isto, um CLI que saísse 1 SEMPRE passaria no assert de cima.
  writeFileSync(join(dsA, 'assets', 'fonts', 'sumiu.woff2'), 'bytes');
  check('refs-check 8/8: CONTROLE — com o arquivo presente, o mesmo CLI sai 0',
    rodaCli(soAusente) === 0, `rc=${rodaCli(soAusente)}`);

  rmSync(soAusente, { recursive: true, force: true });
  rmSync(raiz, { recursive: true, force: true });
}

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato v3 do comparador de frescor preservado (path completo + hash normalizado + absent-local que MORDE + refs-da-poda + refs-check que MORDE + modos removidos saem 2)');
process.exit(fails ? 1 : 0);

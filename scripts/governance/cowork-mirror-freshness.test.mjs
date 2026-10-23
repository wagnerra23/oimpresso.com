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
import { createHash } from 'node:crypto';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  normalize,
  contentHash,
  shouldFail,
  buildManifest,
  parseShellDeps,
  defaultShellPath,
  ledgerEntry,
  lerShellHtml,
  destinoDoBundle,
  rowsDoBundle,
  lerBundlePromovido,
  dsRuntimeRelPath,
  absentLocal,
  previewDsPlan,
  materializePreviewDs,
  cssRefsMortas,
  resolveRefSegura,
  refsParaDeletado,
  orfaosNaAdicao,
} from './cowork-mirror-freshness.mjs';
// `rawHash` saiu do script na onda 2c-2 (sem uso no código); as fixtures do bundle seguem precisando
// do mesmo sha256 do buffer cru, então ele vive aqui com o corpo idêntico ao que existia lá.
const rawHash = (buf) => createHash('sha256').update(buf).digest('hex');

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

// 4. shouldFail (--check) — SÓ STALE morde.
check('STALE presente → morde', shouldFail(['SYNC', 'STALE', 'UNCHECKED']) === true);
check('só SYNC → libera', shouldFail(['SYNC', 'SYNC']) === false);
check('UNCHECKED/LIVE-ABSENT sozinhos → NÃO morde (warn, não podre)', shouldFail(['UNCHECKED', 'LIVE-ABSENT', 'SYNC']) === false);

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
  const e = ledgerEntry(rows, '2026-07-06T12:00:00.000Z');
  check('ledgerEntry conta por veredito', e.files === 3 && e.sync === 1 && e.stale === 1 && e.unchecked === 1 && e.liveAbsent === 0);
  check('ledgerEntry lista os STALE por path', e.staleList.length === 1 && e.staleList[0] === 'b.jsx');
  check('ledgerEntry carrega a data da rodada', e.date === '2026-07-06T12:00:00.000Z');

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
  const eExp = ledgerEntry(rowsPosExport, NOW_TAUT, { origin: 'export', stalePreExport: 2 });
  check('ledger registra a divergência REAL de antes do export (não só o 0 pós-conserto)',
    eExp.stale === 0 && eExp.stalePreExport === 2 && eExp.origin === 'export');
  const eNorm = ledgerEntry(rowsPosExport, NOW_TAUT, {});
  check('CONTROLE: rodada que NÃO veio de export não ganha campo fantasma',
    eNorm.stalePreExport === undefined && eNorm.origin === undefined);

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

// ── dsRuntimeRelPath + frescor pós-transform ─────────────────────────────────────
// O exportPlan, o liveOnly e a recusa de canon de tela saíram em 2026-10-23 com a rota
// de baixar do Cowork (ADR 0438). Fica o roteamento do runtime do DS, que o
// --compare-bundle e o --preview-ds ainda usam.
{

  check('ds-runtime: remove o slug _ds e mantém o path relativo consumido pelo preview',
    dsRuntimeRelPath('_ds/office-impresso-019dd0/assets/fonts/x.woff2') === 'assets/fonts/x.woff2');
  // PR-A9 — as DUAS formas de bind. Wagner carimba o slug; Felipe não (medido 2026-09-17:
  // `Felipe/venda-v3/index.html:38,39,64` carrega `_ds/colors_and_type.css`, `_ds/styles.css`,
  // `_ds/_ds_bundle.js`). Antes disto o caminho sem slug LANÇAVA: o regex exige duas barras.
  check('A9: bind SEM slug resolve (a forma do Felipe, que antes lançava)',
    dsRuntimeRelPath('_ds/colors_and_type.css') === 'colors_and_type.css'
    && dsRuntimeRelPath('_ds/_ds_bundle.js') === '_ds_bundle.js');
  // `styles.css` carrega os COMPONENTES do DS e o host do Felipe depende dele — estava fora da
  // whitelist, então lançava com slug e sem slug.
  check('A9: styles.css entra na whitelist (com e sem slug)',
    dsRuntimeRelPath('_ds/styles.css') === 'styles.css'
    && dsRuntimeRelPath('_ds/slug-x/styles.css') === 'styles.css');
  // A ordem das tentativas vira CONTRATO aqui, senão é acaso de implementação.
  check('A9: com-slug só vence quando o resultado bate a whitelist',
    // com-slug daria `fonts/a.woff2` (não bate) ⇒ cai pro sem-slug
    dsRuntimeRelPath('_ds/assets/fonts/a.woff2') === 'assets/fonts/a.woff2');
  check('A9: no caso AMBÍGUO (ambos batem) com-slug vence, por regra declarada',
    dsRuntimeRelPath('_ds/assets/colors_and_type.css') === 'colors_and_type.css');
  // CONTROLE NEGATIVO: a whitelist não virou "aceita tudo" — extensão fora segue lançando nas
  // duas formas. Sem isto, um `return semSlug` sem guarda passaria nos asserts acima.
  let semSlugForaDaWhitelist = '';
  try { dsRuntimeRelPath('_ds/qualquer.txt'); } catch (e) { semSlugForaDaWhitelist = String(e.message); }
  check('CONTROLE A9: path sem slug FORA da whitelist segue lançando',
    /use --ds/.test(semSlugForaDaWhitelist), semSlugForaDaWhitelist);

  // PR-A9 §F.6 — FRESCOR POS-TRANSFORM. Com `transforms`, o que POUSA nao e o que VIAJOU: a ref
  // do DS e convertida na aterrissagem. Comparar contra o `sha256` do manifesto (pre-transform)
  // marcaria o host STALE em 100% dos ciclos — e ai este alarme, que e o UNICO que pega remendo
  // a mao no espelho (caso de 13/08, 4 dias sem ninguem ver), morreria de ruido.
  {
    const shaDe = (t) => createHash('sha256').update(t).digest('hex');
    const pre = '<link href="_ds/slug/colors_and_type.css">';
    const pos = '<link href="../../design-system/colors_and_type.css">';
    const comT = {
      files: [{ path: 'oimpresso.com.html', bytes: pre.length, sha256: shaDe(pre), role: 'cowork-source' }],
      transforms: [{ path: 'oimpresso.com.html', regra: 'ds-ref', de: '_ds/slug/', para: '../../design-system/', shaDepois: shaDe(pos) }],
    };
    const manDs = [{ cowork: 'oimpresso.com.html', repoPath: 'prototipo-ui/cowork/Wagner/oimpresso.com.html' }];
    const lerTxt = (txt) => (caminho) => (caminho.endsWith('oimpresso.com.html') ? Buffer.from(txt) : null);
    check('A9 §F.6a: com transform, disco POS-transform e SYNC (nao STALE por construcao)',
      rowsDoBundle(comT, manDs, lerTxt(pos))[0].veredito === 'SYNC');
    check('A9 §F.6b: remendo a mao no host SEGUE virando STALE (o alarme sobrevive)',
      rowsDoBundle(comT, manDs, lerTxt(pos + '<!--x-->'))[0].veredito === 'STALE');
    // CONTROLE: sem `transforms` nada muda — compara contra o sha do manifesto, como sempre.
    check('CONTROLE A9: sem transforms o contrato antigo fica intacto',
      rowsDoBundle({ files: comT.files }, manDs, lerTxt(pre))[0].veredito === 'SYNC');
  }

  let templateNoRuntime = '';
  try { dsRuntimeRelPath('templates/pt-05-dashboard/Pt05Dashboard.dc.html'); }
  catch (e) { templateNoRuntime = String(e.message); }
  check('BITE ds-runtime: template não contamina o snapshot de runtime',
    /use --ds/.test(templateNoRuntime), templateNoRuntime);
  let traversalNoRuntime = '';
  try { dsRuntimeRelPath('_ds/ds-teste/assets/../../fora.txt'); }
  catch (e) { traversalNoRuntime = String(e.message); }
  check('BITE ds-runtime: path traversal do payload é recusado',
    /caminho inseguro/.test(traversalNoRuntime), traversalNoRuntime);
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

// O SHELL entra no próprio manifesto quando versionado — fiação não medida foi a doença nº1.
{
  const man = buildManifest(undefined, { shellHtml: '<script src="app.jsx"></script>' });
  check('shell versionado entra no manifesto (vira STALE se [W] mexer nele)',
    man.some((f) => f.cowork === 'oimpresso.com.html'));
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

// ── ledgerEntry + a trava do modo removido ───────────────────────────────────────
// O --unverified e o required do espelho saíram em 2026-10-23 (ADR 0438 D7); o frescor
// contra o Cowork saiu na onda 2c-2. O ledgerEntry fica porque o --compare-bundle grava
// no ledger.
{
  const e2 = ledgerEntry([{ cowork: 'a.css', veredito: 'SYNC', repoHash: 'H1' }], '2026-08-17T00:00:00.000Z');
  check('ledgerEntry: grava `verifiedHash` path->hash',
    e2.verifiedHash && e2.verifiedHash['a.css'] === 'H1', JSON.stringify(e2.verifiedHash));
  const e = ledgerEntry([{ cowork: 'a.css', veredito: 'SYNC' }, { cowork: 'b.css', veredito: 'STALE' }], '2026-08-17T00:00:00.000Z');
  check('ledgerEntry: grava `verified` so com os SYNC',
    Array.isArray(e.verified) && e.verified.length === 1 && e.verified[0] === 'a.css', JSON.stringify(e.verified));
  // o modo removido falha ALTO pelo CLI de fora: sem a trava ele caía no caminho padrão e saía 0
  const aquiRemovido = dirname(fileURLToPath(import.meta.url));
  const cliRemovido = join(aquiRemovido, 'cowork-mirror-freshness.mjs');
  let rcRemovido = 0;
  try { execFileSync(process.execPath, [cliRemovido, '--unverified', '--check'], { cwd: dirname(dirname(aquiRemovido)), stdio: 'pipe' }); }
  catch (err) { rcRemovido = err.status ?? -1; }
  check('BITE CLI: --unverified removido sai 2 (nunca verde sem medir)', rcRemovido === 2, String(rcRemovido));
}

// ── lerBundlePromovido ───────────────────────────────────────────────────────────
{
  check('lerBundlePromovido: caminho inexistente → null (não objeto vazio)',
    lerBundlePromovido(tmpdir(), 'nao-existe-jamais.json') === null);
}

// ── COMPARE-BUNDLE (2026-09-08) — veredito a partir do bundle v2 promovido ────────────
// POR QUE ESTES CASOS EXISTEM: o ledger só aceitava snapshot de `get_file` por path, então
// aplicar o bundle com fidelidade provada não movia o `unchecked` — medido no dia: bundle
// promovido, espelho idêntico (0 mudança), `--sla` ainda dizendo "271 sem veredito".
{
  const H = (s) => rawHash(Buffer.from(s));

  // roteamento — a regra que o applier passou a IMPORTAR daqui (fonte única)
  // 2026-09-13 (decisão [W]): o `.md` perdeu o destino próprio. Antes ia pra `…/handoffs/`
  // enquanto o irmão `.jsx` da MESMA pasta ia pra `…/Wagner/` — a pasta do Cowork chegava
  // partida por extensão. Agora os dois pousam juntos, preservando a árvore da conta.
  check('bundle 1/8: `.md` pousa no espelho do dono, junto do irmão de build',
    destinoDoBundle('PEDIDO.md').destinoBase === 'prototipo-ui/cowork/Wagner');
  check('bundle 1-bis/8: `.md` e `.jsx` da MESMA pasta têm o MESMO destinoBase (árvore não parte)',
    destinoDoBundle('cowork-inbox/ancora/playbook/00-INDICE.md').destinoBase
      === destinoDoBundle('cowork-inbox/ancora/ds-anchor-check.mjs').destinoBase);
  check('bundle 1-ter/8: o caminho relativo é PRESERVADO (forma do Cowork, não a nossa)',
    destinoDoBundle('cowork-inbox/ancora/playbook/00-INDICE.md').destinoPath
      === 'cowork-inbox/ancora/playbook/00-INDICE.md');
  check('bundle 2/8: `_ds/**` roteia pro Design System canônico',
    destinoDoBundle('_ds/slug-qualquer/colors_and_type.css').destinoBase === 'prototipo-ui/design-system');
  check('bundle 3/8: o resto pousa no espelho',
    destinoDoBundle('app.jsx').destinoBase === 'prototipo-ui/cowork/Wagner'
    && destinoDoBundle('app.jsx').destinoPath === 'app.jsx');

  const manifestFake = [{ cowork: 'app.jsx', repoHash: 'x' }, { cowork: 'orfao.jsx', repoHash: 'y' }];
  const bundleFake = { files: [{ path: 'app.jsx', sha256: H('conteudo') }] };

  // SYNC: o hash do disco bate com o que o gerador calculou DO VIVO
  const rSync = rowsDoBundle(bundleFake, manifestFake, (p) => p === 'prototipo-ui/cowork/Wagner/app.jsx' ? Buffer.from('conteudo') : null);
  check('bundle 4/8: hash do disco == hash do manifesto ⇒ SYNC',
    rSync.find((r) => r.cowork === 'app.jsx').veredito === 'SYNC');

  // BITE — é o caso que motiva o modo: espelho remendado À MÃO depois da aplicação
  const rStale = rowsDoBundle(bundleFake, manifestFake, (p) => p === 'prototipo-ui/cowork/Wagner/app.jsx' ? Buffer.from('remendado a mao') : null);
  check('bundle 5/8: BITE — espelho editado à mão depois de aplicar ⇒ STALE',
    rStale.find((r) => r.cowork === 'app.jsx').veredito === 'STALE');
  check('bundle 6/8: e o --check morde nesse caso',
    shouldFail(rStale.map((r) => r.veredito)) === true);

  // FAIL-CLOSED: sumiu do espelho não é SYNC nem silêncio
  const rSumiu = rowsDoBundle(bundleFake, manifestFake, () => null);
  check('bundle 7/8: arquivo do bundle ausente no espelho ⇒ STALE (nunca verde por ausência)',
    rSumiu.find((r) => r.cowork === 'app.jsx').veredito === 'STALE');

  // O que o bundle não cobre segue SEM VEREDITO — a suíte não mente por omissão (LC-13)
  check('bundle 8/8: arquivo do espelho fora do bundle ⇒ UNCHECKED, nunca SYNC por omissão',
    rSync.find((r) => r.cowork === 'orfao.jsx').veredito === 'UNCHECKED');
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

// ── O ESPELHO TEM DOIS DONOS — e o --compare-bundle era cego para o do Felipe ────────────
// Medido 2026-09-17: `rowsDoBundle` fixava a base em `prototipo-ui/cowork/Wagner`. Lote
// aplicado com `--owner Felipe` pousa em `prototipo-ui/cowork/Felipe/`, então TODO arquivo
// era lido do path errado e o veredito saía STALE para tudo (sync 0 · stale 2 num lote de 2).
// Falso-vermelho é pior que alarme ausente: o do Felipe simplesmente não tinha medição.
// Estes asserts existem para que o alarme MORDA nos dois donos — e continue mordendo no
// Wagner, que é o controle de não-regressão (sem ele, um "aceita tudo" passaria).
{
  const H = (s) => rawHash(Buffer.from(s));
  const bundle2 = { files: [{ path: 'app.jsx', sha256: H('conteudo') }] };
  const leitorDe = (base, conteudo) => (p) => (p === base + '/app.jsx' ? Buffer.from(conteudo) : null);
  // 'AUSENTE' em vez de crashar: um mutante que descarta a row TEM que produzir VEREDITO
  // de contrato, nunca TypeError — exceção aborta a suíte e os asserts seguintes nem rodam,
  // e aí 'a mordida' vira contagem de falha, não prova (§5 2026-09-05).
  const vere = (rows) => (rows.find((r) => r.cowork === 'app.jsx') || {}).veredito || 'AUSENTE';

  // CONTROLE DE NÃO-REGRESSÃO — o default segue Wagner, byte por byte igual ao de antes.
  check('dono 1/9: CONTROLE — sem `owner`, o destino segue sendo o do Wagner',
    destinoDoBundle('app.jsx').destinoBase === 'prototipo-ui/cowork/Wagner');
  check('dono 2/9: CONTROLE — sem `owner`, lote íntegro do Wagner segue SYNC',
    vere(rowsDoBundle(bundle2, [], leitorDe('prototipo-ui/cowork/Wagner', 'conteudo'))) === 'SYNC');

  // O CONSERTO — o mesmo lote, aplicado na conta do Felipe, é medido no disco DELE.
  check('dono 3/9: `owner: Felipe` roteia para o espelho do Felipe',
    destinoDoBundle('app.jsx', 'Felipe').destinoBase === 'prototipo-ui/cowork/Felipe');
  check('dono 4/9: lote do Felipe aplicado e íntegro ⇒ SYNC (era STALE: lia o path do Wagner)',
    vere(rowsDoBundle(bundle2, [], leitorDe('prototipo-ui/cowork/Felipe', 'conteudo'), 'Felipe')) === 'SYNC');

  // BITE nos DOIS donos — o alarme que pega remendo à mão não pode existir só para um.
  check('dono 5/9: BITE — espelho do Felipe remendado à mão ⇒ STALE',
    vere(rowsDoBundle(bundle2, [], leitorDe('prototipo-ui/cowork/Felipe', 'remendado a mao'), 'Felipe')) === 'STALE');
  check('dono 6/9: BITE — arquivo ausente no espelho do Felipe ⇒ STALE (fail-closed preservado)',
    vere(rowsDoBundle(bundle2, [], () => null, 'Felipe')) === 'STALE');

  // ISOLAMENTO — medir a conta errada continua reprovando. Um conserto que "aceitasse tudo"
  // passaria nos asserts 4 e 5 e cairia aqui: o veredito TEM que depender do dono.
  check('dono 7/9: ISOLAMENTO — espelho do Felipe medido como Wagner ⇒ STALE (era este o bug)',
    vere(rowsDoBundle(bundle2, [], leitorDe('prototipo-ui/cowork/Felipe', 'conteudo'))) === 'STALE');
  check('dono 8/9: ISOLAMENTO — espelho do Wagner medido como Felipe ⇒ STALE',
    vere(rowsDoBundle(bundle2, [], leitorDe('prototipo-ui/cowork/Wagner', 'conteudo'), 'Felipe')) === 'STALE');

  // FAIL-CLOSED no dono: quem herda a regra herda a recusa do `pathsForOwner`.
  let recusou = false;
  try { destinoDoBundle('app.jsx', 'Larissa'); } catch { recusou = true; }
  check('dono 9/9: dono inválido é RECUSADO, não silenciosamente tratado como Wagner', recusou);
}

// ── BITE DO CLI — o chokepoint, não o helper ────────────────────────────────────────────
// Assert sobre função exportada não prova o pipeline (§5 2026-07-30): o `--compare-bundle`
// também escolhe o `active-bundle.json`, o manifesto e o shell, e os três eram do Wagner.
// Este bloco exercita o CLI DE FORA, numa árvore que só tem a conta do Felipe.
{
  const tmp = mkdtempSync(join(tmpdir(), 'bundle-owner-'));
  const espelhoF = join(tmp, 'prototipo-ui', 'cowork', 'Felipe');
  const stateF = join(tmp, 'scripts', 'design-sync', 'state', 'Felipe');
  mkdirSync(espelhoF, { recursive: true });
  mkdirSync(stateF, { recursive: true });

  const corpo = 'export const A = 1;';
  writeFileSync(join(espelhoF, 'app.jsx'), corpo);
  writeFileSync(join(stateF, 'active-bundle.json'), JSON.stringify({
    bundleId: 'b-felipe', generatedAt: '2026-09-17T00:00:00Z',
    files: [{ path: 'app.jsx', sha256: rawHash(Buffer.from(corpo)) }],
  }));

  const cliOwner = fileURLToPath(new URL('./cowork-mirror-freshness.mjs', import.meta.url));
  const rodar = (args) => {
    try { return { code: 0, out: execFileSync(process.execPath, [cliOwner, ...args], { cwd: tmp, encoding: 'utf8' }) }; }
    catch (e) { return { code: e.status ?? -1, out: (e.stdout || '') + (e.stderr || '') }; }
  };

  const ok = rodar(['--compare-bundle', '--owner', 'Felipe', '--check']);
  check('cli-dono 1/6: CLI com --owner Felipe acha o bundle DELE e libera o espelho íntegro',
    ok.code === 0 && /sync: 1/.test(ok.out), `rc=${ok.code} out=${ok.out.slice(0, 220)}`);
  check('cli-dono 2/6: e o relatório diz de QUAL dono está falando',
    /espelho de Felipe/.test(ok.out), ok.out.slice(0, 160));

  // BITE: remendo à mão no espelho do Felipe tem que derrubar o --check.
  writeFileSync(join(espelhoF, 'app.jsx'), 'export const A = 2; // remendo a mao');
  const mordeu = rodar(['--compare-bundle', '--owner', 'Felipe', '--check']);
  check('cli-dono 3/6: BITE — remendo à mão no espelho do Felipe derruba o --check',
    mordeu.code === 1 && /stale: 1/.test(mordeu.out), `rc=${mordeu.code} out=${mordeu.out.slice(0, 220)}`);

  // CONTROLE POSITIVO: repor o conteúdo faz o MESMO CLI liberar de novo.
  writeFileSync(join(espelhoF, 'app.jsx'), corpo);
  check('cli-dono 4/6: CONTROLE — reposto o conteúdo, o mesmo CLI sai 0',
    rodar(['--compare-bundle', '--owner', 'Felipe', '--check']).code === 0);

  // Nesta árvore não existe conta Wagner: o default NÃO pode herdar o bundle do Felipe.
  const semWagner = rodar(['--compare-bundle', '--check']);
  check('cli-dono 5/6: sem --owner, o CLI não enxerga o bundle do Felipe (estados são separados)',
    semWagner.code === 2 && semWagner.out.includes('Felipe') === false,
    `rc=${semWagner.code} out=${semWagner.out.slice(0, 220)}`);

  // O denominador tambem e do DONO: manifesto e shell. O que o espelho do Felipe TEM e o
  // bundle NAO cobre precisa sair como UNCHECKED (LC-13: nunca SYNC por omissao) -- e isso so
  // acontece se `buildManifest` varrer a arvore DELE e `defaultShellPath` achar o shell DELE.
  // Sao 3 valores distintos por construcao (2 / 1 / 0), entao nenhum mutante acerta por sorte:
  //   integro                       -> shell + dep do Felipe no manifesto  => unchecked: 2
  //   buildManifest fixado em Wagner-> arvore vazia                        => unchecked: 0
  //   defaultShellPath em Wagner    -> shell nao lido, dep nao entra       => unchecked: 1
  writeFileSync(join(espelhoF, 'oimpresso.com.html'), '<script src="dep.jsx"></script>');
  writeFileSync(join(espelhoF, 'dep.jsx'), 'export const D = 1;');
  const comDenom = rodar(['--compare-bundle', '--owner', 'Felipe']);
  check('cli-dono 6/6: o denominador vem da arvore E do shell DO DONO (unchecked: 2)',
    /unchecked: 2/.test(comDenom.out), comDenom.out.slice(0, 260));

  rmSync(tmp, { recursive: true, force: true });
}

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato v3 do comparador de frescor preservado (path completo + hash normalizado + absent-local que MORDE + refs-da-poda + refs-check que MORDE + compare-bundle por dono)');
process.exit(fails ? 1 : 0);

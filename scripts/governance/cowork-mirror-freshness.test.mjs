#!/usr/bin/env node
// Self-test cowork-mirror-freshness v2 — prova a classificação vs o CONTRATO, não vs a
// implementação. Contrato ancorado em: INDEX-DESIGN-MEMORIAS §0.2 (identidade sob normalização
// canônica; "diffar antes de concluir") · ADR 0315 (DesignSync: leitura livre, escrita gateada) ·
// session 2026-07-06-ancora-podre (adversário: basename colide · CRLF dá STALE falso) ·
// arte 2026-07-06-arte-design-code-sync-frescor (hash(normalizado) por PATH COMPLETO).
// Os asserts de EOL/BOM e colisão-por-path existem porque a v1 NÃO os tinha e morreu por isso.
// Roda: node scripts/governance/cowork-mirror-freshness.test.mjs
import { readFileSync, mkdtempSync, mkdirSync, writeFileSync, rmSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  normalize,
  contentHash,
  classifyMirror,
  verdictFor,
  shouldFail,
  veredictoFinal,
  buildManifest,
  parseShellDeps,
  defaultShellPath,
  ledgerEntry,
  lerShellHtml,
  slaVerdict,
  liveOnly,
  exportPlan,
  decodeDesignSyncPayload,
  artifactHash,
  dsRuntimeRelPath,
  absentLocal,
  previewDsPlan,
  nasceSemMedicao,
  refsParaDeletado,
  unverifiedSince,
  SLA_DAYS,
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
check('hash iguais → SYNC (espelho acompanha o vivo · §0.2)', classifyMirror({ repoHash: H1, liveHash: H1 }) === 'SYNC');
check('hash diferem → STALE (o vivo avançou, espelho ficou)', classifyMirror({ repoHash: H1, liveHash: H2 }) === 'STALE');
check('vivo null → LIVE-ABSENT, NÃO stale (rename/delete ≠ divergência)', classifyMirror({ repoHash: H1, liveHash: null }) === 'LIVE-ABSENT');
check('vivo "" → LIVE-ABSENT (ausência explícita)', classifyMirror({ repoHash: H1, liveHash: '' }) === 'LIVE-ABSENT');

// 3. verdictFor — "não buscado" ≠ "buscado e ausente"; e sem fantasma de prototype (furo #6).
check('chave fora do snapshot → UNCHECKED (nunca SYNC no silêncio)', verdictFor('x.jsx', H1, {}) === 'UNCHECKED');
check('presente e igual → SYNC', verdictFor('x.jsx', H1, { 'x.jsx': H1 }) === 'SYNC');
check('presente e diferente → STALE', verdictFor('x.jsx', H1, { 'x.jsx': H2 }) === 'STALE');
check('presente e null → LIVE-ABSENT', verdictFor('x.jsx', H1, { 'x.jsx': null }) === 'LIVE-ABSENT');
check('path homônimo de membro do prototype (toString) → UNCHECKED, não lixo', verdictFor('toString', H1, {}) === 'UNCHECKED');
check('constructor idem', verdictFor('constructor', H1, {}) === 'UNCHECKED');

// 4. shouldFail (--check) — SÓ STALE morde.
check('STALE presente → morde', shouldFail(['SYNC', 'STALE', 'UNCHECKED']) === true);
check('só SYNC → libera', shouldFail(['SYNC', 'SYNC']) === false);
check('UNCHECKED/LIVE-ABSENT sozinhos → NÃO morde (warn, não podre)', shouldFail(['UNCHECKED', 'LIVE-ABSENT', 'SYNC']) === false);

// 4b. veredictoFinal — a LINHA FINAL não pode mentir quando se roda sem `--check`.
//     Bug medido 2026-08-13: `stale: 1` no corpo e `✓ sem espelho STALE` no rodapé, porque o
//     log verde estava fora do `if (strict …)`. O texto é do FATO; `--check` é só exit code.
check('1 stale → veredito NEGATIVO', veredictoFinal(1).ok === false);
// Ancora = MARCADOR de veredito (✓/✗), nao a PALAVRA "STALE": em 2026-08-17 a redacao mudou
// (o texto parou de afirmar a DIRECAO que o hash nao mede) e 3 asserts quebraram por acoplamento
// a redacao, nao a comportamento. O contrato aqui e "nao afirma verde quando ha divergencia".
check('1 stale → texto não afirma verde', !/✓/.test(veredictoFinal(1).texto) && /✗/.test(veredictoFinal(1).texto));
check('0 stale → veredito positivo', veredictoFinal(0).ok === true && /✓/.test(veredictoFinal(0).texto));
// CONTROLE NEGATIVO do bite: o veredito NÃO pode depender de flag nenhuma — só do número.
check('mesmo número → mesmo veredito (independe de --check)',
  JSON.stringify(veredictoFinal(3)) === JSON.stringify(veredictoFinal(3)) && veredictoFinal(3).ok === false);

// 4c. COBERTURA — "não achei divergência" ≠ "não há divergência" (LC-13: verde por não-medição).
//     Reproduzido 2026-08-13: 2 medidos de 121, 0 stale, e a linha final afirmava
//     "✓ sem espelho STALE (divergência hash-provada)" sobre 119 arquivos que ninguém olhou.
{
  const parcial = veredictoFinal(0, { total: 121, medidos: 2, semVeredito: 119 });
  check('cobertura parcial + 0 stale → INCONCLUSIVO, não ✓', parcial.inconclusivo === true && !/^✓/.test(parcial.texto));
  check('o texto do inconclusivo carrega o DENOMINADOR (medidos e faltantes)',
    /\b2\b/.test(parcial.texto) && /\b119\b/.test(parcial.texto) && /\b121\b/.test(parcial.texto));
  check('inconclusivo NÃO afirma "hash-provada" (nada foi provado sobre os 119)',
    !/hash-provada/.test(parcial.texto));

  const completa = veredictoFinal(0, { total: 121, medidos: 121, semVeredito: 0 });
  // CONTROLE POSITIVO: cobertura total volta a ser ✓ — o conserto não vira alarme cego.
  check('CONTROLE: cobertura total + 0 stale → ✓ (não virou alarme permanente)',
    completa.inconclusivo === undefined && /✓/.test(completa.texto));

  // O sinal DURO vence a cobertura: stale é hash-provado, não depende de quantos faltam.
  check('1 stale + 119 sem veredito → ✗ STALE (o duro vence o inconclusivo)',
    veredictoFinal(1, { total: 121, medidos: 2, semVeredito: 119 }).ok === false);

  // ENFORCEMENT INALTERADO: inconclusivo não é falha dura — `--check` morde só em STALE.
  check('inconclusivo mantém ok=true (não promove cobertura a gate — ADR 0336 é decisão [W])',
    parcial.ok === true);

  // COMPAT: chamador sem cobertura segue funcionando (o argumento é opcional).
  check('CONTROLE: sem cobertura → comportamento antigo (✓ sem inconclusivo)',
    veredictoFinal(0).ok === true && veredictoFinal(0).inconclusivo === undefined);
}
{
  // BITE de integração: o fonte não pode mais imprimir a linha verde fora do veredito.
  // Conta só CÓDIGO — o docblock do `veredictoFinal` cita o `console.log` bugado de propósito,
  // e contar comentário como código é o falso-positivo que a medição de hoje catalogou
  // (`<FinAiAnomalia>` só existia numa linha `//`).
  const HERE_ = dirname(fileURLToPath(import.meta.url));
  const semComentario = readFileSync(join(HERE_, 'cowork-mirror-freshness.mjs'), 'utf8')
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/^\s*\/\/.*$/gm, '');
  const verdes = [...semComentario.matchAll(/console\.log\('✓ sem espelho STALE/g)].length;
  check('nenhum console.log direto da linha verde (só via veredictoFinal)', verdes === 0, `achei ${verdes}`);
  // CONTROLE POSITIVO do stripper: ele não pode estar apagando o arquivo inteiro.
  check('CONTROLE: o stripper preserva o código (veredictoFinal segue no fonte)',
    /export function veredictoFinal/.test(semComentario));
}

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
  try {
    const pages = join(dir, 'resources', 'js', 'Pages');
    const cowork = join(dir, 'prototipo-ui', 'cowork');
    mkdirSync(join(cowork, 'a'), { recursive: true });
    mkdirSync(join(cowork, 'b'), { recursive: true });
    // COLISÃO: mesmo basename, subdirs diferentes, CONTEÚDO diferente — v1 colapsava, v2 não pode.
    writeFileSync(join(cowork, 'a', 'x-page.jsx'), 'export const A = 1;\n');
    writeFileSync(join(cowork, 'b', 'x-page.jsx'), 'export const B = 2;\n');
    writeFileSync(join(cowork, 'raiz-page.jsx'), 'export const R = 0;\n');
    const mk = (mod, anchor) => {
      mkdirSync(join(pages, mod), { recursive: true });
      writeFileSync(join(pages, mod, 'Tela.charter.md'), `related_prototype: ${anchor}\n`);
    };
    mk('ModA', 'prototipo-ui/cowork/a/x-page.jsx');
    mk('ModB', 'prototipo-ui/cowork/b/x-page.jsx');
    mk('ModR', 'raiz-page.jsx'); // nome solto resolve na raiz (compat v1)
    mk('ModProsa', 'prototipo Cowork "payment-gateway-ui" F1+F1.5'); // prosa → pulada
    mk('ModMiss', 'sumiu.jsx'); // MISSING → fora do manifesto (território do anchor-content)

    const man = buildManifest(dir);
    check('homônimos em subdirs = 2 ENTRADAS separadas (não colapsa)', man.filter((m) => m.cowork.endsWith('x-page.jsx')).length === 2,
      `tem: ${man.map((m) => m.cowork).join(',')}`);
    const a = man.find((m) => m.cowork === 'a/x-page.jsx');
    const b = man.find((m) => m.cowork === 'b/x-page.jsx');
    check('chave é o PATH RELATIVO completo', !!a && !!b);
    check('homônimos têm hash DIFERENTE (conteúdo difere)', a && b && a.repoHash !== b.repoHash);
    check('cada tela ancora no path certo', a?.telas.includes('ModA/Tela') && b?.telas.includes('ModB/Tela'));
    check('nome solto resolve na raiz', man.some((m) => m.cowork === 'raiz-page.jsx' && m.telas.includes('ModR/Tela')));
    check('prosa e MISSING ficam fora (escopo == anchor-content)', !man.some((m) => /payment|sumiu/.test(m.cowork)));
    check('repoHash = contentHash(bytes do arquivo)', a?.repoHash === contentHash(readFileSync(join(cowork, 'a', 'x-page.jsx'))));
    // CRLF no disco ≠ LF no vivo → MESMO hash (identidade normalizada de ponta a ponta)
    writeFileSync(join(cowork, 'a', 'x-page.jsx'), 'export const A = 1;\r\n');
    const man2 = buildManifest(dir);
    check('arquivo CRLF no repo == vivo LF → SYNC (não falso-STALE)',
      verdictFor('a/x-page.jsx', man2.find((m) => m.cowork === 'a/x-page.jsx').repoHash, { 'a/x-page.jsx': contentHash('export const A = 1;\n') }) === 'SYNC');
    // --all inclui subdirs por path relativo (v1 sumia com homônimos no --all)
    const all = buildManifest(dir, { all: true });
    check('--all enumera por path relativo (2 homônimos presentes)', all.filter((m) => m.cowork.endsWith('x-page.jsx')).length === 2);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
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

  const dir = mkdtempSync(join(tmpdir(), 'mirror-deps-'));
  try {
    const pages = join(dir, 'resources', 'js', 'Pages');
    const cowork = join(dir, 'prototipo-ui', 'cowork');
    mkdirSync(join(cowork, 'ds-v6'), { recursive: true });
    writeFileSync(join(cowork, 'financeiro-page.jsx'), 'tela');
    writeFileSync(join(cowork, 'app.jsx'), 'const onColor = "var(--accent)";'); // o drift real de 2026-07-07
    writeFileSync(join(cowork, 'styles.css'), ':root{--accent:oklch(0.55 0.15 295)}');
    mkdirSync(join(pages, 'Fin'), { recursive: true });
    writeFileSync(join(pages, 'Fin', 'Tela.charter.md'), 'related_prototype: financeiro-page.jsx');

    const man = buildManifest(dir, { shellHtml: SHELL });
    const byPath = Object.fromEntries(man.map((m) => [m.cowork, m]));
    check('âncora continua entrando, kind=ancora', byPath['financeiro-page.jsx']?.kind === 'ancora');
    check('app.jsx ENTRA no manifest como dep (o drift de 2026-07-07 não passa mais cego)', byPath['app.jsx']?.kind === 'dep');
    check('styles.css entra como dep', byPath['styles.css']?.kind === 'dep');
    check('dep referenciada no shell mas AUSENTE do espelho fica fora (universo = espelho)', !byPath['ds-v6/tokens.css']);
    check('âncora que TAMBÉM é dep permanece ancora (telas ganham)', byPath['financeiro-page.jsx'].telas.includes('Fin/Tela'));
    // ── DENOMINADOR LIMPO: `_ds/` NÃO é do espelho (adversário 2026-08-13) ──────
    // `_ds/<id>/` é o Design System REPOSTO pelo `--preview-ds` a partir do snapshot já
    // versionado — é build local, gitignored, e NÃO tem contrapartida no projeto Cowork.
    // Entrando no manifesto ele inflava o total (124 em vez de 122) e nunca poderia sair
    // de UNCHECKED, porque o vivo não tem esses arquivos pra comparar: cobertura que jamais
    // fecha por construção. §5 2026-07-27 (denominador inventado).
    mkdirSync(join(cowork, '_ds', '019dd02f-d2d0-7ba6-a57f-24b3ddd073ac'), { recursive: true });
    writeFileSync(join(cowork, '_ds', '019dd02f-d2d0-7ba6-a57f-24b3ddd073ac', 'tokens.css'), ':root{}');
    const comDs = buildManifest(dir, { shellHtml: SHELL, all: true });
    check('_ds/ (build local do DS) fica FORA do manifesto — não infla o denominador',
      comDs.every((m) => !m.cowork.startsWith('_ds/')));
    // sem shell → só âncoras (comportamento antigo preservado; o WARN é do CLI)
    const semShell = buildManifest(dir, {});
    check('sem shellHtml → só âncoras (back-compat)', semShell.length === 1 && semShell[0].kind === 'ancora');
    // STALE de dep morde no --check igual âncora
    const st = verdictFor('app.jsx', byPath['app.jsx'].repoHash, { 'app.jsx': contentHash('const onColor = DIFERENTE;') });
    check('dep divergente → STALE (drift de app.jsx agora É detectado)', st === 'STALE' && shouldFail([st]) === true);
  } finally {
    rmSync(dir, { recursive: true, force: true });
  }
  check('defaultShellPath é função exportada (CLI resolve staging)', typeof defaultShellPath === 'function');
}

// 7. CLI: snapshot inexistente/malformado → exit 2 limpo (não stack não-capturada) (furo #5).
{
  const HERE = dirname(fileURLToPath(import.meta.url));
  const script = join(HERE, 'cowork-mirror-freshness.mjs');
  const run = (args) => { try { execFileSync(process.execPath, [script, ...args], { cwd: dirname(dirname(HERE)), stdio: 'pipe' }); return 0; } catch (e) { return e.status ?? -1; } };
  check('--compare sem snapshot → exit 2', run(['--compare', join(tmpdir(), 'nao-existe.json')]) === 2);
  const bad = join(tmpdir(), `mf-bad-${process.pid}.json`);
  writeFileSync(bad, '{ nao é json');
  check('--compare snapshot malformado → exit 2 (JSON.parse capturado)', run(['--compare', bad]) === 2);
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
  check('ledger vazio → NEVER-RAN (rotina nunca rodou ≠ tudo bem)', slaVerdict([], NOW).veredito === 'NEVER-RAN');
  check('rodada recente e limpa → FRESH', slaVerdict([clean], NOW).veredito === 'FRESH');
  check('rodada além do SLA → OVERDUE', slaVerdict([old], NOW).veredito === 'OVERDUE');
  check('última rodada com STALE → LAST-STALE (resultado sujo não some no tempo)', slaVerdict([dirty], NOW).veredito === 'LAST-STALE');
  check('rodada nova limpa APÓS suja → FRESH (só a última conta)', slaVerdict([dirty, clean], NOW).veredito === 'FRESH');
  check('ageDays calculado', slaVerdict([clean], NOW).ageDays === 5);
  check('fronteira: exatamente SLA_DAYS não é OVERDUE', slaVerdict([{ ...clean, date: '2026-06-22T12:00:00.000Z' }], NOW, SLA_DAYS).veredito === 'FRESH');

  // ── LAST-PARTIAL (2026-08-13): "0 stale" numa rodada que não mediu não é saúde ──
  // Caso REAL do ledger: a rodada de 2026-07-07 tinha `5 sync · 0 stale · 98 unchecked`
  // e o veredito a tratava como limpa — o LC-13 ("verde por não-execução") DENTRO do
  // instrumento que existe pra pegar isso. Critério binário e derivado (unchecked===0),
  // nunca limiar inventado: o §5 tem 5 lápides de guard com corte arbitrário.
  const parcial = { ...clean, files: 103, sync: 5, stale: 0, unchecked: 98 };
  check('BITE: rodada 95% UNCHECKED não é FRESH (0 stale sem cobertura não prova nada)',
    slaVerdict([parcial], NOW).veredito === 'LAST-PARTIAL');
  check('cobertura sai no veredito (medidos/total), não só a contagem de stale',
    JSON.stringify(slaVerdict([parcial], NOW).cobertura) === '{"medidos":5,"total":103}');
  check('CONTROLE: rodada COMPLETA e limpa continua FRESH (parcial não vira FP em todo mundo)',
    slaVerdict([{ ...clean, unchecked: 0 }], NOW).veredito === 'FRESH');
  check('PRECEDÊNCIA: stale vence parcial (resultado sujo é pior que cobertura baixa)',
    slaVerdict([{ ...parcial, stale: 2, staleList: ['a.jsx', 'b.jsx'] }], NOW).veredito === 'LAST-STALE');
  check('PRECEDÊNCIA: idade vence tudo (rodada velha é OVERDUE mesmo parcial)',
    slaVerdict([{ ...parcial, date: '2026-01-01T00:00:00.000Z' }], NOW).veredito === 'OVERDUE');
  check(`SLA_DAYS = 14`, SLA_DAYS === 14);
}

// ── liveOnly + exportPlan (v3 — 2026-08-11) ──────────────────────────────────────
// Contrato: o manifesto monta o universo do lado do ESPELHO, então arquivo que existe no
// VIVO e nunca foi exportado era invisível POR CONSTRUÇÃO (o LIVE-ABSENT cobre o inverso).
// Incidente: `jana-merge.jsx` vivia no Cowork, era citado por 21 sites do repo — charter,
// 2 .tsx de produção, workflow, testes — e NÃO estava versionado. Nenhuma ferramenta
// apontou, porque nenhuma olhava para esse lado. Medido no corpus real: 25 de 1310 paths.
{
  const man = [{ cowork: 'chat-jana.jsx' }, { cowork: 'app.jsx' }];
  check('BITE liveOnly: arquivo do vivo NUNCA exportado aparece',
    JSON.stringify(liveOnly(['chat-jana.jsx', 'jana-merge.jsx', 'app.jsx'], man)) === '["jana-merge.jsx"]');
  check('CONTROLE liveOnly: espelho completo não acusa',
    liveOnly(['chat-jana.jsx', 'app.jsx'], man).length === 0);
  check('CONTROLE liveOnly: _arquivo/ (morto upstream) não acusa',
    liveOnly(['_arquivo/velho.jsx'], man).length === 0);
  check('CONTROLE liveOnly: prototipo-ui/ (cópia do espelho dentro do vivo) não acusa',
    liveOnly(['prototipo-ui/cowork/chat-jana.jsx'], man).length === 0);
  // INVERTIDO em 2026-08-20. Este caso afirmava "CONTROLE: .md não é protótipo" e
  // congelava uma premissa MEDIDA COMO FALSA: o `list_files` do projeto vivo traz 174 `.md`
  // fora de `_arquivo/`, entre eles os F1 das ondas (`SUPERADMIN-F1-2026-08-18.md`), 30+
  // `PROMPT_PARA_CODE_*.md` e charters/casos escritos do lado do design. Eles dizem COMO
  // CONSTRUIR A TELA. Fica o BITE (detecta) + o controle do `.png`, que segue fora de
  // propósito — imagem não é fonte de construção e tem guard próprio (block-ancora-no-olho).
  check('BITE liveOnly: .md do vivo fora do espelho APARECE (F1/PROMPT_PARA_CODE)',
    JSON.stringify(liveOnly(['cowork-inbox/SUPERADMIN-F1-2026-08-18.md'], man)) === '["cowork-inbox/SUPERADMIN-F1-2026-08-18.md"]');
  check('CONTROLE liveOnly: .png segue fora (não é fonte de construção)',
    liveOnly(['screenshots/tela.png'], man).length === 0);
  check('CONTROLE liveOnly: .md em _arquivo/ (morto upstream) NAO acusa',
    liveOnly(['_arquivo/docs-legado/velho.md'], man).length === 0);

  // exportPlan: a transcrição manual causou STALE em 2026-08-11 (923 ln à mão vs 943 reais)
  // e ainda me levou a "corrigir" um charter que estava CERTO. A escrita sai do JSON.
  check('exportPlan: prefixa com o path do espelho',
    exportPlan([{ path: 'jana-merge.jsx', content: 'x\n' }])[0].relPath === 'prototipo-ui/cowork/jana-merge.jsx');
  check('exportPlan: conteúdo passa INTACTO (sem transcrição)',
    exportPlan([{ path: 'a.jsx', content: 'l1\nl2\n' }])[0].content === 'l1\nl2\n');

  // ── .md fora do espelho (2026-08-21, decisão [W]) ──────────────────────────
  // R1 do cowork-ssot-guard reprova `.md` em cowork/ (build-only). Antes o `.md` era
  // descartado e o preço foi 204 vivos no Cowork contra 0 no repo. Agora roteia — mas o
  // roteamento é por EXTENSÃO, não por flag, pra quem chama não conseguir errar.
  {
    const docs = 'prototipo-ui/design-docs/';
    const lote = [
      { path: 'cowork-inbox/SUPERADMIN-F1.md', content: '# f1\n' },
      { path: 'superadmin-page.jsx', content: 'const x=1;\n' },
    ];
    const plano = exportPlan(lote, { prefixoDocs: docs });
    const md = plano.find((p) => p.relPath.endsWith('.md'));
    const jsx = plano.find((p) => p.relPath.endsWith('.jsx'));
    check('BITE R1: .md NUNCA cai em prototipo-ui/cowork/',
      !md.relPath.startsWith('prototipo-ui/cowork/'), md.relPath);
    check('exportPlan: .md vai pra design-docs/ PRESERVANDO a árvore do vivo',
      md.relPath === docs + 'cowork-inbox/SUPERADMIN-F1.md', md.relPath);
    check('CONTROLE NEGATIVO: o não-.md do MESMO lote segue no espelho',
      jsx.relPath === 'prototipo-ui/cowork/superadmin-page.jsx', jsx.relPath);
    check('exportPlan: .md roteado passa INTACTO', md.content === '# f1\n');
    // Sem `prefixoDocs` o comportamento antigo é preservado — `--ds`/`--ds-runtime`
    // pousam FORA de cowork/, onde o R1 não alcança e um `.md` é legítimo.
    check('CONTROLE: sem prefixoDocs, .md segue o prefixo do destino (ds/dsRuntime)',
      exportPlan([{ path: 'x.md', content: 'a\n' }], { prefixo: 'prototipo-ui/design-system/' })[0]
        .relPath === 'prototipo-ui/design-system/x.md');
  }
  // ⚠️ confere a MENSAGEM, não só "lançou": medido por mutação que, com `catch → true`,
  // remover o guard AINDA passava (Buffer.byteLength(undefined) lança sozinho e mascarava).
  let msg = '';
  try { exportPlan([{ path: 'a.jsx' }]); } catch (e) { msg = String(e.message); }
  check('BITE exportPlan: guard próprio lança citando o path',
    /export: conteúdo ausente para "a\.jsx"/.test(msg));

  let truncado = '';
  try {
    decodeDesignSyncPayload({ path: '_ds/x/_ds_bundle.js', content: 'const MENU = [{', truncated: true }, 'bundle.json');
  } catch (e) { truncado = String(e.message); }
  check('BITE DesignSync: truncated:true é falha dura antes da escrita',
    /TRUNCADO.*nada foi escrito/i.test(truncado), truncado);

  const bytes = Buffer.from([0, 1, 2, 127, 128, 255]);
  const bin = decodeDesignSyncPayload({ path: 'assets/font.woff2', content: bytes.toString('base64'), isBase64: true });
  check('DesignSync base64: binário volta byte-idêntico, não como texto base64',
    bin.binary === true && Buffer.isBuffer(bin.content) && bin.content.equals(bytes));
  check('exportPlan: binário preserva bytes e tamanho real',
    exportPlan([bin])[0].bytes === bytes.length && exportPlan([bin])[0].content.equals(bytes));
  check('artifactHash: binário hasheia bytes crus',
    artifactHash(bin.content, true) === artifactHash(bytes, true));
  check('ds-runtime: remove o slug _ds e mantém o path relativo consumido pelo preview',
    dsRuntimeRelPath('_ds/office-impresso-019dd0/assets/fonts/x.woff2') === 'assets/fonts/x.woff2');
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

// ── CICLO EM 1 DOWNLOAD (2026-08-13) ────────────────────────────────────────────
// O --export-from passou a emitir o snapshot (--emit-snapshot). O contrato que este
// assert trava é a CHAVE: o exportPlan devolve `relPath` COM o prefixo do espelho,
// mas o --compare procura pelo path RELATIVO (o campo `cowork` do manifesto). Emitir
// com o prefixo errado daria "UNCHECKED" em tudo — verde-por-não-medir, o LC-13 na veia.
{
  const plano = exportPlan([{ path: 'app.jsx', content: 'x\n' }, { path: 'venda-v3/sells-ui.jsx', content: 'y\n' }]);
  const chaves = plano.map((p) => p.relPath.replace(/^prototipo-ui\/cowork\//, ''));
  check('snapshot emitido usa a MESMA chave do manifesto (relativa, não prefixada)',
    JSON.stringify(chaves) === '["app.jsx","venda-v3/sells-ui.jsx"]');
  // o hash emitido tem que ser o do conteúdo NORMALIZADO — senão CRLF daria STALE falso
  check('hash do snapshot emitido == contentHash do conteúdo (normalizado)',
    contentHash('a\r\nb\r\n') === contentHash(exportPlan([{ path: 'z.jsx', content: 'a\nb\n' }])[0].content));
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
    p.destino === 'prototipo-ui/cowork/_ds/ds-abc123');
  // O runtime compilado completo passou a viver no snapshot canônico. A 2ª camada
  // hermética abaixo preserva o BITE que declara qualquer dependência ausente.
  check('previewDs: encontra o bundle compilado no snapshot canônico',
    p.arquivos.find((a) => a.nome === '_ds_bundle.js').temNoRepo === true);
  check('previewDs: sem shell não inventa plano', previewDsPlan(null).arquivos.length === 0);
  check('previewDs: 2 design systems no shell = erro explícito, não escolha silenciosa',
    !!previewDsPlan('<link href="_ds/a/x.css"/><link href="_ds/b/y.css"/>').erro);
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
    const snap = join(raiz, 'scripts', 'design-sync', 'mirror-snapshot');
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

// ── NASCE-SEM-MEDIÇÃO (2026-08-13) ──────────────────────────────────────────────
// [W]: "garanta todos os novos sempre checados". Os 3 filtros são por DADO — cada um
// foi medido em 60d de histórico real (251 arquivos adicionados) antes de virar código:
//   todos ......... 51% fora (ruído: relatório .html)
//   só jsx/css/js . 38% fora (ruído: subdir com shell próprio)
//   + raiz ........ 15% fora
//   + no vivo ..... ~1% fora  ← o sinal
{
  const man = [{ cowork: 'app.jsx' }, { cowork: 'styles.css' }];
  const vivos = ['app.jsx', 'styles.css', 'forja-tarefas.jsx']; // norte-app.jsx NÃO está: sumiu do vivo

  const r = nasceSemMedicao(['prototipo-ui/cowork/forja-tarefas.jsx'], man, vivos);
  check('BITE: arquivo novo na raiz, no vivo e fora do manifesto → ACUSA',
    JSON.stringify(r.acusados) === '["forja-tarefas.jsx"]');

  check('CONTROLE: arquivo novo que JÁ está no manifesto não acusa',
    nasceSemMedicao(['prototipo-ui/cowork/app.jsx'], man, vivos).acusados.length === 0);
  // filtro 1 — subdir tem shell próprio (venda-v3/, prototipo-ui-patch/): 38%→15% do FP saiu daqui
  check('CONTROLE: subdir não acusa (tem shell próprio por desenho)',
    nasceSemMedicao(['prototipo-ui/cowork/venda-v3/sells-ui.jsx'], man, vivos).acusados.length === 0);
  // filtro 2 — .html no espelho é relatório/auditoria, não protótipo: 51%→38% saiu daqui
  check('CONTROLE: .html não acusa (é relatório, não protótipo de tela)',
    nasceSemMedicao(['prototipo-ui/cowork/Auditoria Financeiro.html'], man, vivos).acusados.length === 0);
  // filtro 3 — o que sumiu do vivo é resíduo a limpar, não "novo sem medição": 15%→1%
  {
    const res = nasceSemMedicao(['prototipo-ui/cowork/norte-app.jsx'], man, vivos);
    check('CONTROLE: sumiu do vivo → vai pra `residuo`, não pra `acusados`',
      res.acusados.length === 0 && JSON.stringify(res.residuo) === '["norte-app.jsx"]');
  }
  // honestidade: sem a lista do vivo o filtro forte não roda — e isso é DECLARADO, não escondido
  {
    const sv = nasceSemMedicao(['prototipo-ui/cowork/norte-app.jsx'], man, null);
    check('sem --vivos: marca semVivo e NÃO finge que filtrou',
      sv.semVivo === true && sv.acusados.length === 1);
  }
  check('CONTROLE: lista vazia não inventa acusação', nasceSemMedicao([], man, vivos).acusados.length === 0);
}

// ── FLUXO END-TO-END (2026-08-13) ────────────────────────────────────────────────
// Os asserts acima provam PEÇAS. Este prova a TRAVESSIA: get_file(JSON) →
// --export-from --emit-snapshot → --compare. É onde os contratos se encontram, e
// onde um erro de junção (chave prefixada, hash de conteúdo cru, snapshot vazio)
// aparece — nenhum assert de peça isolada pegaria.
// Roda em sandbox por cwd: não toca o espelho real.
{
  const tmp = mkdtempSync(join(tmpdir(), 'cowork-fluxo-'));
  const mirror = join(tmp, 'prototipo-ui', 'cowork');
  mkdirSync(mirror, { recursive: true });
  // espelho de partida: 1 arquivo DESATUALIZADO + o shell que o carrega
  writeFileSync(join(mirror, 'x.jsx'), 'versao ANTIGA\n');
  writeFileSync(join(mirror, 'oimpresso.com.html'), '<script src="x.jsx?v=1"></script>');
  const dirJson = join(tmp, 'baixados');
  mkdirSync(dirJson);
  // o que o DesignSync.get_file devolveria pro arquivo, já ATUALIZADO no vivo
  writeFileSync(join(dirJson, 'x.json'), JSON.stringify({ path: 'x.jsx', content: 'versao NOVA\n' }));

  const cli = fileURLToPath(new URL('./cowork-mirror-freshness.mjs', import.meta.url));
  const run = (args) => {
    try {
      return { out: execFileSync(process.execPath, [cli, ...args], { cwd: tmp, encoding: 'utf8' }), code: 0 };
    } catch (e) { return { out: (e.stdout || '') + (e.stderr || ''), code: e.status }; }
  };

  const snap = join(tmp, 'snap.json');
  const exp = run(['--export-from', dirJson, '--emit-snapshot', snap]);
  check('FLUXO 1/3: export escreve o conteúdo do vivo e marca ATUALIZADO',
    /ATUALIZADO/.test(exp.out) && readFileSync(join(mirror, 'x.jsx'), 'utf8') === 'versao NOVA\n');
  check('FLUXO 2/3: o snapshot sai do MESMO passo (sem 2º download)', existsSync(snap));

  // o compare tem que dizer SYNC — o espelho acabou de receber o conteúdo do vivo
  const cmpOk = run(['--compare', snap, '--check']);
  check('FLUXO 3/3: compare fecha SYNC e --check libera (exit 0)', cmpOk.code === 0);

  // ⚠️ CONTROLE POSITIVO: sem ele o fluxo acima passaria mesmo se o compare fosse cego
  writeFileSync(join(mirror, 'x.jsx'), 'alguem editou o espelho a mao\n');
  const cmpBad = run(['--compare', snap, '--check']);
  check('BITE do fluxo: espelho divergindo do snapshot → --check MORDE (exit 1)',
    cmpBad.code === 1 && /✗|DIVERGE/i.test(cmpBad.out));

  // ── NASCE-SEM-MEDIÇÃO ligado no export (o `--check-novos` era órfão) ─────────
  // Discriminação real: dois arquivos nascem, só um está no shell. Se acusar os dois
  // (ou nenhum), o aviso é decorativo. Foi assim que o wire nasceu quebrado: o call site
  // passava o CAMINHO do shell onde `parseShellDeps` quer o CONTEÚDO — sem erro, sem dep,
  // e o arquivo que ESTAVA no shell aparecia como não-medido.
  writeFileSync(join(dirJson, 'novo-visto.json'), JSON.stringify({ path: 'novo-visto.jsx', content: 'V' }));
  writeFileSync(join(dirJson, 'novo-cego.json'), JSON.stringify({ path: 'novo-cego.jsx', content: 'C' }));
  writeFileSync(join(mirror, 'oimpresso.com.html'), '<script src="x.jsx?v=1"></script><script src="novo-visto.jsx"></script>');
  const nasc = run(['--export-from', dirJson]);
  check('nasce-sem-medição roda NO export e acusa o que o shell não carrega',
    /NASCERAM sem entrar no manifesto/.test(nasc.out) && /novo-cego\.jsx/.test(nasc.out));
  check('CONTROLE: arquivo novo que o shell CARREGA não é acusado',
    !/ {4}novo-visto\.jsx/.test(nasc.out));
  check('CONTROLE: re-export sem nascimento não emite o aviso',
    !/NASCERAM sem entrar/.test(run(['--export-from', dirJson]).out));
  check('lerShellHtml devolve CONTEÚDO (não o caminho) — a confusão que quebrou o wire',
    typeof lerShellHtml === 'function' && (lerShellHtml(tmp) || '').includes('<script'));

  // ── MEDIR ≠ CONSERTAR (resíduo da tautologia, 2026-08-13) ───────────────────
  // Enquanto `--emit-snapshot` só existia dentro do `--export-from`, a pergunta do [W]
  // ("se eu alterar o protótipo, ele vê?") era irrespondível: o export escreve antes de
  // medir, então a resposta era SYNC por construção. O `--snapshot-from` mede sem tocar
  // no espelho. Os 3 asserts abaixo são o contrato: DETECTA · NÃO ESCREVE · MORDE.
  writeFileSync(join(mirror, 'x.jsx'), 'ESPELHO ANTIGO\n');
  const dirVivo = join(tmp, 'vivo-alterado');
  mkdirSync(dirVivo);
  writeFileSync(join(dirVivo, 'x.json'), JSON.stringify({ path: 'x.jsx', content: 'W ALTEROU NO COWORK\n' }));
  const snapMed = join(tmp, 'medicao.json');
  const med = run(['--snapshot-from', dirVivo, '--emit-snapshot', snapMed]);
  check('--snapshot-from DETECTA que o vivo divergiu', /DIVERGE/.test(med.out) && med.code === 0);
  check('--snapshot-from NÃO escreve no espelho (medir ≠ consertar)',
    readFileSync(join(mirror, 'x.jsx'), 'utf8') === 'ESPELHO ANTIGO\n');
  const vered = run(['--compare', snapMed, '--check']);
  check('veredito do snapshot de MEDIÇÃO é STALE e morde (exit 1)',
    vered.code === 1 && /✗|DIVERGE/i.test(vered.out));
  check('snapshot de medição se declara `_origin: medicao` (o ledger não o confunde com export)',
    JSON.parse(readFileSync(snapMed, 'utf8'))._origin === 'medicao');

  // Regressão 2026-08-18: o payload real do _ds_bundle.js veio com truncated:true,
  // mas o exportador ignorava o metadado e escrevia JS cortado como se fosse fiel.
  const dirTrunc = join(tmp, 'truncado');
  mkdirSync(dirTrunc);
  writeFileSync(join(dirTrunc, 'bundle.json'), JSON.stringify({
    path: '_ds/x/_ds_bundle.js', content: "const MENU = [{ label: 'Pa", truncated: true,
  }));
  const expTrunc = run(['--export-from', dirTrunc]);
  check('FLUXO BITE: export truncado sai 2, nomeia TRUNCADO e não grava arquivo',
    expTrunc.code === 2 && /TRUNCADO/.test(expTrunc.out)
      && !existsSync(join(mirror, '_ds', 'x', '_ds_bundle.js')), expTrunc.out);

  const dirBin = join(tmp, 'binario');
  mkdirSync(dirBin);
  const fontBytes = Buffer.from([0, 1, 2, 127, 128, 255]);
  writeFileSync(join(dirBin, 'font.json'), JSON.stringify({
    path: '_ds/ds-teste/assets/fonts/x.woff2', content: fontBytes.toString('base64'), isBase64: true,
  }));
  const expBin = run(['--export-from', dirBin, '--ds-runtime']);
  const fontOut = join(tmp, 'scripts', 'design-sync', 'mirror-snapshot', 'assets', 'fonts', 'x.woff2');
  check('FLUXO ds-runtime: base64 pousa no snapshot consumido pelo preview, byte-idêntico',
    expBin.code === 0 && existsSync(fontOut) && readFileSync(fontOut).equals(fontBytes), expBin.out);

  rmSync(tmp, { recursive: true, force: true });
}

// ── PREVIEW COMPLETO É PORTÃO, NÃO AVISO ─────────────────────────────────────
// O comando dizia "SEM FONTE" para o bundle, mas encerrava 0. O agente seguia à Fase 4
// e drawers/eventos sumiam porque o protótipo faz `if (!Drawer || !meta) return null`.
{
  const tmp = mkdtempSync(join(tmpdir(), 'preview-ds-failclosed-'));
  const mirror = join(tmp, 'prototipo-ui', 'cowork');
  const snap = join(tmp, 'scripts', 'design-sync', 'mirror-snapshot');
  mkdirSync(mirror, { recursive: true });
  mkdirSync(snap, { recursive: true });
  writeFileSync(join(mirror, 'oimpresso.com.html'),
    '<script src="_ds/ds-teste/_ds_bundle.js"></script><link href="_ds/ds-teste/colors_and_type.css">');
  writeFileSync(join(snap, 'colors_and_type.css'), ':root{--x:1}');
  const cli = fileURLToPath(new URL('./cowork-mirror-freshness.mjs', import.meta.url));
  let out = '', code = 0;
  try { out = execFileSync(process.execPath, [cli, '--preview-ds'], { cwd: tmp, encoding: 'utf8' }); }
  catch (e) { code = e.status; out = (e.stdout || '') + (e.stderr || ''); }
  check('BITE preview-ds: bundle sem fonte bloqueia com exit 1 antes de editar produto',
    code === 1 && /PREVIEW INCOMPLETO.*PARE/s.test(out), out);

  writeFileSync(join(snap, '_ds_bundle.js'), 'const MENU = [{');
  let invalidoOut = '', invalidoCode = 0;
  try { invalidoOut = execFileSync(process.execPath, [cli, '--preview-ds'], { cwd: tmp, encoding: 'utf8' }); }
  catch (e) { invalidoCode = e.status; invalidoOut = (e.stdout || '') + (e.stderr || ''); }
  check('BITE preview-ds: bundle truncado no disco falha no parser e mantém o portão fechado',
    invalidoCode === 1 && /INVÁLIDO.*_ds_bundle\.js.*PREVIEW INCOMPLETO/s.test(invalidoOut), invalidoOut);
  rmSync(tmp, { recursive: true, force: true });
}

// ── refsParaDeletado: a poda quebrou o grafo interno do espelho? ────────────────
// Contrato ancorado no incidente 2026-08-13 (poda de 96 arquivos, PR #5763): a pergunta que
// NINGUÉM fazia era "sobrou alguém apontando pro que saiu?". Os asserts abaixo fixam os dois
// lados — o que DEVE acusar (senão o gate é cego) e o que NÃO PODE acusar (senão é FP, a
// doença das 4 lápides de guard sintático do §5).
{
  const M = 'prototipo-ui/cowork';
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

// ── unverifiedSince — MEXEU-DEPOIS-DE-VERIFICAR (v4 · 2026-08-17) ────────────────
// A defesa contra remendo a mao no espelho. Predicado = DUAS DATAS, nunca nome/pasta/vocabulario
// (escapa da familia das 4 lapides de guard sintatico do §5). O caso real que motivou:
// 11 sites trocados a mao 33min DEPOIS de uma rodada de frescor, invisiveis por 4 dias.
{
  const L = [
    { date: '2026-08-13T17:00:00.000Z', verified: ['chat-jana.css', 'app.jsx'] },
    { date: '2026-08-13T21:00:00.000Z', verified: ['app.jsx'] },
  ];
  const r = unverifiedSince(L, [
    { cowork: 'chat-jana.css', lastCommitIso: '2026-08-13T18:01:18.000Z' }, // MEXEU depois das 17:00
    { cowork: 'app.jsx',       lastCommitIso: '2026-08-13T18:01:18.000Z' }, // verificado 21:00 = DEPOIS do commit
    { cowork: 'vendas.css',    lastCommitIso: '2026-08-10T10:00:00.000Z' }, // nunca verificado
    { cowork: 'novo.jsx',      lastCommitIso: null },                        // sem commit ainda
  ]);
  check('BITE unverifiedSince: MORDE o arquivo commitado DEPOIS da verificacao',
    r.mexidoDepois.length === 1 && r.mexidoDepois[0].cowork === 'chat-jana.css', JSON.stringify(r.mexidoDepois));
  check('unverifiedSince: LIBERA o arquivo cuja verificacao e POSTERIOR ao commit',
    !r.mexidoDepois.some((m) => m.cowork === 'app.jsx') && r.ok === 1, JSON.stringify(r));
  check('unverifiedSince: NUNCA-VERIFICADO nao vira mexido-depois (forward-only, nao pune legado)',
    r.nuncaVerificado.includes('vendas.css') && !r.mexidoDepois.some((m) => m.cowork === 'vendas.css'), JSON.stringify(r));
  check('unverifiedSince: arquivo SEM data de commit nao vira achado (nao afirma sem dado)',
    !r.mexidoDepois.some((m) => m.cowork === 'novo.jsx'), JSON.stringify(r.mexidoDepois));
  // MERGE/SQUASH nao e remendo a mao (falso-positivo medido 2026-08-17).
  // O squash do #5854 reescreveu a data de commit de 6 arquivos do espelho SEM mudar um byte;
  // um detector que compara so DATAS acusou os 6. Com o hash no ledger, "commitou depois" so
  // vira achado quando o CONTEUDO tambem mudou.
  {
    const L2 = [{ date: '2026-08-17T12:00:00.000Z', verified: ['a.jsx', 'b.jsx'],
      verifiedHash: { 'a.jsx': 'HASH_A', 'b.jsx': 'HASH_B' } }];
    const rSquash = unverifiedSince(L2, [
      { cowork: 'a.jsx', lastCommitIso: '2026-08-17T12:10:47.000Z', hashAtual: 'HASH_A' }, // squash: data nova, conteudo igual
      { cowork: 'b.jsx', lastCommitIso: '2026-08-17T12:10:47.000Z', hashAtual: 'OUTRO' },  // mexido de verdade
    ]);
    check('unverifiedSince: merge/squash (data nova, hash IGUAL) nao vira achado',
      !rSquash.mexidoDepois.some((m) => m.cowork === 'a.jsx') && rSquash.ok === 1, JSON.stringify(rSquash));
    check('BITE unverifiedSince: hash DIFERENTE depois da verificacao ainda MORDE',
      rSquash.mexidoDepois.length === 1 && rSquash.mexidoDepois[0].cowork === 'b.jsx', JSON.stringify(rSquash.mexidoDepois));
    // ledger SEM verifiedHash (rodada anterior ao campo) => fallback CONSERVADOR: acusa por data.
    const rVelhoHash = unverifiedSince([{ date: '2026-08-17T12:00:00.000Z', verified: ['a.jsx'] }],
      [{ cowork: 'a.jsx', lastCommitIso: '2026-08-17T12:10:47.000Z', hashAtual: 'HASH_A' }]);
    check('unverifiedSince: sem hash no ledger cai no conservador (acusa por data, nao inventa verde)',
      rVelhoHash.mexidoDepois.length === 1 && /sem hash/.test(rVelhoHash.mexidoDepois[0].motivo || ''), JSON.stringify(rVelhoHash.mexidoDepois));
    const e2 = ledgerEntry([{ cowork: 'a.css', veredito: 'SYNC', repoHash: 'H1' }], '2026-08-17T00:00:00.000Z');
    check('ledgerEntry: grava `verifiedHash` path->hash (insumo do desempate)',
      e2.verifiedHash && e2.verifiedHash['a.css'] === 'H1', JSON.stringify(e2.verifiedHash));
  }

  // ledger antigo (sem o campo `verified`) nao pode virar verde silencioso: e SEM DADO.
  const rVelho = unverifiedSince([{ date: '2026-07-06T00:00:00.000Z', files: 3, sync: 1 }],
    [{ cowork: 'chat-jana.css', lastCommitIso: '2026-08-13T18:01:18.000Z' }]);
  check('unverifiedSince: rodada SEM `verified` nao conta como prova (comLedger=0)',
    rVelho.comLedger === 0 && rVelho.mexidoDepois.length === 0 && rVelho.nuncaVerificado.length === 1, JSON.stringify(rVelho));
  // ledgerEntry passa a gravar QUAIS mediu — sem isso o detector nao tem insumo
  const e = ledgerEntry([{ cowork: 'a.css', veredito: 'SYNC' }, { cowork: 'b.css', veredito: 'STALE' }], '2026-08-17T00:00:00.000Z');
  check('ledgerEntry: grava `verified` so com os SYNC (insumo do --unverified)',
    Array.isArray(e.verified) && e.verified.length === 1 && e.verified[0] === 'a.css', JSON.stringify(e.verified));
}

console.log(fails ? `\n✗ ${fails} falha(s)` : '\n✓ contrato v3 do comparador de frescor preservado (path completo + hash normalizado + ledger/SLA + live-only + export fiel + absent-local que MORDE + refs-da-poda + fluxo e2e)');
process.exit(fails ? 1 : 0);

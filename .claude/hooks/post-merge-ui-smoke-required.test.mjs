#!/usr/bin/env node
// Teste do PORTE cross-plataforma post-merge-ui-smoke-required.mjs (ex-.ps1). Cada caso
// deriva do CONTRATO canônico (proibicoes.md §"Claim sem evidência" bullet pós-merge UI +
// PROTOCOLO-WAGNER R1 smoke real: merge UI → browser MCP + screenshot ANTES de declarar
// "pronto|deployed|funcionando|ao vivo|live em prod"), NÃO do output do .ps1 legado.
// Flag isolada via OIMPRESSO_UI_SMOKE_FLAG (tmpdir hermético). Roda em Linux/CI.
// Complementa scripts/governance/settings-evidence-smoke-registration.test.mjs (REGISTRO).
//
// 2026-08-28 — bloco novo: a PERNA DA ÂNCORA (comparação medida com o protótipo). Os bite-
// tests dela exercitam o CLI de fora (stdin→exit code), não só helpers puros: a lápide LC-15
// registra o caso em que 3 asserts "de contrato" pinavam um satélite enquanto o chokepoint
// seguia livre. Os 3 desfechos do enunciado estão provados: RUIM (tela com âncora, sem
// comparação) → bloqueia · BOM (comparação medida) → libera · n/a (tela sem âncora) → libera.
//
// Rodar: node .claude/hooks/post-merge-ui-smoke-required.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join, resolve } from 'node:path';
import { mkdtempSync, writeFileSync, mkdirSync, existsSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import {
  isAdminMerge, extractPrNumber, isUiFile, isPageTsx, isBrowserSmokeTool, isClaim,
  flagIsFresh, parseFlag, serializeFlag, blockMessage, blockMessageComparacao,
  ehComparacaoMedida, tocaMarcacao, linhasDoArquivo, charterDe, relatedPrototypeDe, telasComAncora,
} from './post-merge-ui-smoke-required.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'post-merge-ui-smoke-required.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── caso 1 (gatilho): merge admin + superfície UI ────────────────────────────────
check('merge admin detectado', isAdminMerge('gh pr merge 4028 --admin --squash'));
check('merge sem --admin não gatilha caso 1', !isAdminMerge('gh pr merge 4028 --squash'));
check('extrai PR number', extractPrNumber('gh pr merge 4028 --admin') === '4028');
check('UI: Page .tsx', isUiFile('resources/js/Pages/Sells/Index.tsx'));
check('UI: Component .tsx', isUiFile('resources/js/Components/Button.tsx'));
check('UI: css', isUiFile('resources/css/app.css'));
check('UI: blade core', isUiFile('resources/views/sale_pos/create.blade.php'));
check('UI: blade de módulo', isUiFile('Modules/Repair/Resources/views/kanban.blade.php'));
// 2026-08-28: 10 das 55 telas com âncora vivem aqui (ADR 0375) e eram INVISÍVEIS ao hook
check('UI: Page .tsx dentro do módulo (ADR 0375 — era cego)', isUiFile('Modules/Ponto/Resources/js/Pages/Ponto/Index.tsx'));
check('UI: css dentro do módulo', isUiFile('Modules/Ponto/Resources/css/ponto.css'));
check('não-UI: Controller PHP', !isUiFile('Modules/Jana/Http/Controllers/Foo.php'));
check('não-UI: teste .ts fora de resources', !isUiFile('scripts/governance/foo.ts'));
check('não-UI: PHP dentro de Resources do módulo', !isUiFile('Modules/Ponto/Resources/lang/pt.php'));

check('Page .tsx (núcleo) reconhecida', isPageTsx('resources/js/Pages/Sells/Index.tsx'));
check('Page .tsx (módulo) reconhecida', isPageTsx('Modules/Ponto/Resources/js/Pages/Ponto/Index.tsx'));
check('Componente NÃO é Page (não tem charter irmão)', !isPageTsx('resources/js/Components/Button.tsx'));

// ── caso 2: browser MCP prova que Claude está OLHANDO (F7: minúscula/hífen real) ─
check('smoke: mcp__claude-in-chrome__navigate (nome REAL pós-F7)', isBrowserSmokeTool('mcp__claude-in-chrome__navigate'));
check('smoke: mcp__Claude_in_Chrome__read_page (grafia antiga segue coberta)', isBrowserSmokeTool('mcp__Claude_in_Chrome__read_page'));
check('smoke: mcp__computer-use__screenshot', isBrowserSmokeTool('mcp__computer-use__screenshot'));
check('não-smoke: tabs_close não é olhar a tela', !isBrowserSmokeTool('mcp__claude-in-chrome__tabs_close_mcp'));
check('não-smoke: tool comum', !isBrowserSmokeTool('Bash'));

// ── caso 3: comparação MEDIDA (campo `command`, não prosa — §5 2026-07-26) ───────
check('compare: design-diff --compare', ehComparacaoMedida('node prototipo-ui/design-diff.mjs --compare prod.json design.json --check'));
check('compare: caminho absoluto', ehComparacaoMedida('node D:/repo/prototipo-ui/design-diff.mjs --compare a.json b.json'));
check('NÃO-compare: --probe sozinho só imprime a sonda', !ehComparacaoMedida('node prototipo-ui/design-diff.mjs --probe'));
check('NÃO-compare: falar de comparação no echo não conta', !ehComparacaoMedida('echo "comparei o design com a prod"'));
check('NÃO-compare: outro comparador', !ehComparacaoMedida('git diff --compare'));

// ── perna do diff: mede MARCAÇÃO (o que derruba o FP de 20,5% → 1,4%) ───────────
check('tocaMarcacao: className', tocaMarcacao(['+  <div className="flex gap-2">']));
check('tocaMarcacao: tag JSX', tocaMarcacao(['-      <Sparkline data={x} />']));
check('NÃO toca: só props/lógica (partial reload, o FP medido)', !tocaMarcacao(['+  only: ["filters"],', '-  const a = 1;']));
check('NÃO toca: diff vazio', !tocaMarcacao([]));

const DIFF = [
  'diff --git a/resources/js/Pages/A/Index.tsx b/resources/js/Pages/A/Index.tsx',
  '--- a/resources/js/Pages/A/Index.tsx',
  '+++ b/resources/js/Pages/A/Index.tsx',
  '+  <div className="flex">',
  'diff --git a/resources/js/Pages/B/Index.tsx b/resources/js/Pages/B/Index.tsx',
  '--- a/resources/js/Pages/B/Index.tsx',
  '+++ b/resources/js/Pages/B/Index.tsx',
  '+  only: ["x"],',
].join('\n');
check('linhasDoArquivo isola o arquivo certo (A)', linhasDoArquivo(DIFF, 'resources/js/Pages/A/Index.tsx').join() === '+  <div className="flex">');
check('linhasDoArquivo isola o arquivo certo (B)', linhasDoArquivo(DIFF, 'resources/js/Pages/B/Index.tsx').join() === '+  only: ["x"],');
check('linhasDoArquivo: arquivo ausente → vazio', linhasDoArquivo(DIFF, 'resources/js/Pages/C/Index.tsx').length === 0);
check('charterDe: irmão do .tsx', charterDe('resources/js/Pages/A/Index.tsx') === 'resources/js/Pages/A/Index.charter.md');

// ── caso 4: claims canônicos (proibicoes: pronto|deployed|funcionando|ao vivo|live) ─
for (const c of ['echo "pronto"', 'echo deployed com sucesso', 'echo "funcionando em prod"', 'echo "ao vivo"', 'echo "live em prod"', 'echo "smoke ok"']) {
  check(`claim: ${c}`, isClaim(c));
}
check('não-claim: git status', !isClaim('git status'));
check('não-claim: npm run build', !isClaim('npm run build'));

// ── flag: parse + TTL 5min + retro-compatibilidade do formato antigo ─────────────
const now = Date.now();
const iso = (msAgo) => new Date(now - msAgo).toISOString();
check('flag fresca (30s) vale', flagIsFresh(`${iso(30_000)}|4028`, now));
check('flag velha (6min) expira', !flagIsFresh(`${iso(360_000)}|4028`, now));
check('flag corrompida não vale (fail-open)', !flagIsFresh('lixo-sem-pipe', now) && parseFlag('lixo') === null);
// TTL por PERNA: 5min pro screenshot (inalterado) · 45min quando há âncora pendente — senão a
// perna nova se escaparia esperando o fluxo do design-diff terminar (gate mudo por evaporação).
const flagComTela = (msAgo) => serializeFlag({ ts: now - msAgo, pr: '1', telas: [{ tsx: 'a/B.tsx', ancora: 'x.jsx' }], viu: true });
check('TTL: flag SEM tela expira em 5min (caminho de sempre, intacto)', !flagIsFresh(`${iso(360_000)}|4028`, now));
check('TTL: flag COM tela ainda vale aos 6min (o screenshot já não valeria)', flagIsFresh(flagComTela(360_000), now));
check('TTL: flag COM tela expira aos 46min', !flagIsFresh(flagComTela(46 * 60_000), now));
check('TTL: ttlMin explícito sobrepõe (API preservada)', !flagIsFresh(flagComTela(360_000), now, 5));
check('formato ANTIGO "ISO|PR" segue válido (sem telas)', (() => {
  const f = parseFlag(`${iso(1000)}|4028`);
  return f && f.pr === '4028' && f.telas.length === 0 && f.viu === false && f.comp === false;
})());
check('serialize→parse ida e volta preserva telas/viu/comp', (() => {
  const orig = { ts: now, pr: '99', telas: [{ tsx: 'a/B.tsx', ancora: 'prototipo-ui/cowork/b-page.jsx' }], viu: true, comp: false };
  const f = parseFlag(serializeFlag(orig));
  return f.pr === '99' && f.telas.length === 1 && f.telas[0].ancora === 'prototipo-ui/cowork/b-page.jsx' && f.viu === true && f.comp === false;
})());
check('mensagem cita R1 + browser MCP + escape valves', (() => {
  const m = blockMessage('4028', 12, 'echo "pronto"');
  return /R1/.test(m) && /claude-in-chrome/.test(m) && /no-ui-smoke/.test(m) && /OIMPRESSO_UI_SMOKE_OVERRIDE/.test(m);
})());
check('mensagem da âncora cita design-diff --compare + ancora.mjs + os 2 escapes', (() => {
  const m = blockMessageComparacao('6385', 12, 'echo "pronto"', [{ tsx: 'a/B.tsx', ancora: 'prototipo-ui/cowork/b-page.jsx' }]);
  return /design-diff\.mjs --compare/.test(m) && /ancora\.mjs/.test(m) &&
    /no-ancora-compare/.test(m) && /OIMPRESSO_ANCORA_COMPARE_OVERRIDE/.test(m) &&
    /prototipo-ui\/cowork\/b-page\.jsx/.test(m);
})());

// ── telasComAncora: fixture no disco (as 3 pernas, com controle negativo por perna) ──
const fx = mkdtempSync(join(tmpdir(), 'ui-smoke-ancora-'));
const REPO_REAL = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
function fixCharter(rel, related) {
  const abs = join(fx, rel);
  mkdirSync(dirname(abs), { recursive: true });
  writeFileSync(abs, `---\npage: /x\ncomponent: ${rel.replace('.charter.md', '.tsx')}\nrelated_prototype: ${related}\n---\n# charter\n`);
}
mkdirSync(join(fx, 'prototipo-ui', 'cowork'), { recursive: true });
writeFileSync(join(fx, 'prototipo-ui', 'cowork', 'fix-page.jsx'), '// mock');
fixCharter('resources/js/Pages/Com/Index.charter.md', 'prototipo-ui/cowork/fix-page.jsx');      // âncora existe
fixCharter('resources/js/Pages/Na/Index.charter.md', 'n/a (herda PT-01 Lista)');                 // n/a puro
// `n/a` que MENCIONA um arquivo existente: só `ehDeclaracaoNa` a barra. Sem esta fixture o
// assert do n/a passava pelo motivo errado (a âncora não resolvia) — buraco pego na mutação.
fixCharter('resources/js/Pages/NaComPath/Index.charter.md', 'n/a (herda PT-01; referência visual em prototipo-ui/cowork/fix-page.jsx)');
fixCharter('resources/js/Pages/Morta/Index.charter.md', 'prototipo-ui/cowork/nao-existe.jsx');   // âncora não resolve
fixCharter('resources/js/Pages/SoLogica/Index.charter.md', 'prototipo-ui/cowork/fix-page.jsx');  // âncora ok, diff não-visual

check('relatedPrototypeDe lê o frontmatter', relatedPrototypeDe(join(fx, 'resources/js/Pages/Com/Index.charter.md')) === 'prototipo-ui/cowork/fix-page.jsx');
check('relatedPrototypeDe: charter ausente → null (fail-open)', relatedPrototypeDe(join(fx, 'nao/existe.charter.md')) === null);

const mkDiff = (p, linha) => ['diff --git a/' + p + ' b/' + p, '--- a/' + p, '+++ b/' + p, linha].join('\n');
const FILES = [
  'resources/js/Pages/Com/Index.tsx',
  'resources/js/Pages/Na/Index.tsx',
  'resources/js/Pages/NaComPath/Index.tsx',
  'resources/js/Pages/Morta/Index.tsx',
  'resources/js/Pages/SoLogica/Index.tsx',
  'resources/js/Components/Button.tsx',
  'Modules/Jana/Http/Controllers/Foo.php',
];
const DIFF_FX = [
  mkDiff('resources/js/Pages/Com/Index.tsx', '+  <div className="flex gap-2">'),
  mkDiff('resources/js/Pages/Na/Index.tsx', '+  <div className="flex">'),
  mkDiff('resources/js/Pages/NaComPath/Index.tsx', '+  <div className="flex">'),
  mkDiff('resources/js/Pages/Morta/Index.tsx', '+  <div className="flex">'),
  mkDiff('resources/js/Pages/SoLogica/Index.tsx', '+  only: ["filters"],'),
  mkDiff('resources/js/Components/Button.tsx', '+  <button className="x">'),
].join('\n');

const r = await telasComAncora(FILES, DIFF_FX, fx);
check('telasComAncora MEDIU (import do dono ancora.mjs funcionou)', r.mediu === true);
check('  cobra a tela com âncora resolvível + diff visual', r.telas.length === 1 && r.telas[0].tsx === 'resources/js/Pages/Com/Index.tsx');
check('  NÃO cobra tela n/a (119 das 217 — FP por construção)', !r.telas.some((t) => /\/Na\//.test(t.tsx)));
check('  NÃO cobra n/a que MENCIONA arquivo existente (isola a perna ehDeclaracaoNa)', !r.telas.some((t) => /NaComPath/.test(t.tsx)));
check('  NÃO cobra âncora declarada que não existe no disco', !r.telas.some((t) => /\/Morta\//.test(t.tsx)));
check('  NÃO cobra diff não-visual (o FP de 20,5% medido no corpus)', !r.telas.some((t) => /SoLogica/.test(t.tsx)));
check('  NÃO cobra componente (sem charter irmão)', !r.telas.some((t) => /Components/.test(t.tsx)));
check('telasComAncora usa a lib do repo, não do repoRoot da fixture', existsSync(join(REPO_REAL, 'prototipo-ui', 'ancora.mjs')));

// ── E2E: stdin JSON → exit code, flag hermética via OIMPRESSO_UI_SMOKE_FLAG ──────
const dir = mkdtempSync(join(tmpdir(), 'ui-smoke-fixture-'));
const FLAG = join(dir, 'pending.flag');
function runHook(payload, env = {}) {
  return spawnSync(process.execPath, [HOOK], {
    input: typeof payload === 'string' ? payload : JSON.stringify(payload),
    encoding: 'utf8',
    env: { ...process.env, OIMPRESSO_UI_SMOKE_FLAG: FLAG, ...env },
  });
}
const preBash = (cmd) => ({ hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: cmd } });
const postBash = (cmd) => ({ hook_event_name: 'PostToolUse', tool_name: 'Bash', tool_input: { command: cmd } });
const olhar = () => runHook({ hook_event_name: 'PreToolUse', tool_name: 'mcp__claude-in-chrome__navigate', tool_input: {} });
const TELA = [{ tsx: 'resources/js/Pages/Com/Index.tsx', ancora: 'prototipo-ui/cowork/fix-page.jsx' }];

// bite: flag fresca + claim → exit 2
writeFileSync(FLAG, `${new Date().toISOString()}|4028`);
check('E2E bite: claim com merge UI pendente → exit 2 (BLOQUEIA)', runHook(preBash('echo "pronto"')).status === 2);
check('E2E: comando neutro com flag fresca → exit 0 (só claim bloqueia)', runHook(preBash('git status')).status === 0);
// release SEM âncora (caminho de sempre): browser MCP limpa a flag e o claim passa
check('E2E release (tela n/a): browser MCP → exit 0 e flag LIMPA', (() => {
  const rr = olhar();
  return rr.status === 0 && !existsSync(FLAG);
})());
check('E2E: claim SEM flag (smoke já feito) → exit 0', runHook(preBash('echo "pronto"')).status === 0);

// ── BITE-TEST da perna da âncora (os 3 desfechos do enunciado) ───────────────────
// RUIM: PR tocou tela COM âncora · olhou · não comparou → BLOQUEIA
writeFileSync(FLAG, serializeFlag({ ts: Date.now(), pr: '6385', telas: TELA }));
check('E2E: olhar NÃO limpa a flag quando há tela com âncora', (() => {
  const rr = olhar();
  return rr.status === 0 && existsSync(FLAG) && parseFlag(readFileSync(FLAG, 'utf8')).viu === true;
})());
check('E2E BITE (ruim): screenshot feito, comparação NÃO → exit 2', (() => {
  const rr = runHook(preBash('echo "pronto"'));
  return rr.status === 2 && /design-diff\.mjs --compare/.test(rr.stderr || '');
})());
// BOM: rodou design-diff --compare → LIBERA
check('E2E: PostToolUse design-diff --compare carimba comp=1', (() => {
  const rr = runHook(postBash('node prototipo-ui/design-diff.mjs --compare prod.json design.json --check'));
  return rr.status === 0 && parseFlag(readFileSync(FLAG, 'utf8')).comp === true;
})());
check('E2E BITE (bom): screenshot + comparação medida → exit 0 e flag LIMPA', (() => {
  const rr = runHook(preBash('echo "pronto"'));
  return rr.status === 0 && !existsSync(FLAG);
})());
// ordem inversa (comparou antes de olhar) → ainda cobra o screenshot
writeFileSync(FLAG, serializeFlag({ ts: Date.now(), pr: '6385', telas: TELA, comp: true }));
check('E2E: comparou mas NÃO olhou → exit 2 (o screenshot segue obrigatório)', (() => {
  const rr = runHook(preBash('echo "pronto"'));
  return rr.status === 2 && /R1/.test(rr.stderr || '');
})());
// --probe sozinho NÃO libera (controle negativo do sinal)
writeFileSync(FLAG, serializeFlag({ ts: Date.now(), pr: '6385', telas: TELA, viu: true }));
runHook(postBash('node prototipo-ui/design-diff.mjs --probe'));
check('E2E: --probe sozinho não conta como comparação → exit 2', runHook(preBash('echo "pronto"')).status === 2);

// ── ESCAPE VALVES (anunciadas na mensagem ⇒ testadas — LC-15) ────────────────────
writeFileSync(FLAG, serializeFlag({ ts: Date.now(), pr: '6385', telas: TELA, viu: true }));
check('E2E escape: OIMPRESSO_ANCORA_COMPARE_OVERRIDE=1 dispensa SÓ a âncora → exit 0',
  runHook(preBash('echo "pronto"'), { OIMPRESSO_ANCORA_COMPARE_OVERRIDE: '1' }).status === 0);
writeFileSync(FLAG, serializeFlag({ ts: Date.now(), pr: '6385', telas: TELA }));
check('E2E escape: ANCORA_COMPARE_OVERRIDE NÃO dispensa o screenshot (não olhou → exit 2)',
  runHook(preBash('echo "pronto"'), { OIMPRESSO_ANCORA_COMPARE_OVERRIDE: '1' }).status === 2);
writeFileSync(FLAG, serializeFlag({ ts: Date.now(), pr: '6385', telas: TELA, viu: true }));
check('E2E escape: OIMPRESSO_UI_SMOKE_OVERRIDE=1 dispensa o hook inteiro → exit 0',
  runHook(preBash('echo "pronto"'), { OIMPRESSO_UI_SMOKE_OVERRIDE: '1' }).status === 0);

// TTL: flag velha expira e é removida — por PERNA
writeFileSync(FLAG, `${new Date(Date.now() - 10 * 60 * 1000).toISOString()}|4028`);
check('E2E: flag SEM âncora >5min → exit 0 e flag removida (TTL de sempre)', runHook(preBash('echo "pronto"')).status === 0 && !existsSync(FLAG));
writeFileSync(FLAG, serializeFlag({ ts: Date.now() - 10 * 60 * 1000, pr: '6385', telas: TELA, viu: true }));
check('E2E: flag COM âncora aos 10min AINDA bloqueia (não evapora antes do compare)', runHook(preBash('echo "pronto"')).status === 2);
writeFileSync(FLAG, serializeFlag({ ts: Date.now() - 50 * 60 * 1000, pr: '6385', telas: TELA, viu: true }));
check('E2E: flag COM âncora >45min → exit 0 e flag removida (TTL)', runHook(preBash('echo "pronto"')).status === 0 && !existsSync(FLAG));

// caso 1 sem gh de verdade: comando não-merge → não grava flag
check('E2E: PostToolUse não-merge → exit 0 e sem flag', (() => {
  const rr = runHook(postBash('git status'));
  return rr.status === 0 && !existsSync(FLAG);
})());
check('E2E: design-diff --compare SEM flag → exit 0 e não cria flag', (() => {
  const rr = runHook(postBash('node prototipo-ui/design-diff.mjs --compare a.json b.json'));
  return rr.status === 0 && !existsSync(FLAG);
})());
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('').status === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', runHook('{lixo').status === 0);

rmSync(dir, { recursive: true, force: true });
rmSync(fx, { recursive: true, force: true });

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — R1 (merge UI → browser MCP antes de claim) + perna da ÂNCORA (comparação medida com o protótipo) provados de fora do CLI: bite ruim/bom/n-a, ordem inversa, --probe não conta, 2 escape valves, TTL, fail-open.');
process.exit(fails ? 1 : 0);

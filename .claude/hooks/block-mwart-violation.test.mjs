#!/usr/bin/env node
// Teste do PORTE cross-plataforma block-mwart-violation.mjs (ex-.ps1). Cada caso deriva
// do CONTRATO canônico (ADR 0104 §F1/Enforcement + proibicoes.md §MWART: Page Inertia sem
// RUNBOOK-<tela-kebab>.md = caminho alternativo PROIBIDO), NÃO do output do .ps1 legado.
// Fixtures herméticas em tmpdir — roda em Linux/CI (o .ps1 nunca teve teste).
// Complementa scripts/governance/settings-automem-mwart-registration.test.mjs (REGISTRO).
//
// Rodar: node .claude/hooks/block-mwart-violation.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { decide, parsePagePath, toKebab, runbookStatus, charterRunbookExists } from './block-mwart-violation.mjs';

const HOOK = join(dirname(fileURLToPath(import.meta.url)), 'block-mwart-violation.mjs');
let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };

// ── fixture: repo-root fake com F1 feita pra Sells/Index e NfeBrasil/NfceStatus ──
const root = mkdtempSync(join(tmpdir(), 'mwart-fixture-'));
mkdirSync(join(root, 'memory', 'requisitos', 'Sells'), { recursive: true });
writeFileSync(join(root, 'memory', 'requisitos', 'Sells', 'RUNBOOK-index.md'), '# F1 ok');
mkdirSync(join(root, 'memory', 'requisitos', 'nfebrasil'), { recursive: true }); // pasta lowercase de propósito
writeFileSync(join(root, 'memory', 'requisitos', 'nfebrasil', 'RUNBOOK-NFCE-STATUS.md'), '# F1 ok'); // case diverso de propósito

// ── fixture ANINHADA #1: RUNBOOK por ROTA/SUBDIR, sem charter (mecanismo puro) ────
// governance/ModuleGrades/Index.tsx → RUNBOOK-module-grades.md (kebab do subdir, NÃO 'index').
// Pasta 'Governance' (capital) vs modulo parseado 'governance' (lower) → prova case-insensitive.
mkdirSync(join(root, 'memory', 'requisitos', 'Governance'), { recursive: true });
writeFileSync(join(root, 'memory', 'requisitos', 'Governance', 'RUNBOOK-module-grades.md'), '# F1 ok');

// ── fixture ANINHADA #2: RUNBOOK de nome NÃO-convencional, resgatado pelo charter ─
// Kb/Artigos/Index.tsx: nem kebab(tela)='index' nem kebab(subdir)='artigos' batem;
// só o charter (runbook: ...RUNBOOK-base-de-conhecimento.md) resgata — e o arquivo EXISTE.
mkdirSync(join(root, 'memory', 'requisitos', 'Kb'), { recursive: true });
writeFileSync(join(root, 'memory', 'requisitos', 'Kb', 'RUNBOOK-base-de-conhecimento.md'), '# F1 ok');
mkdirSync(join(root, 'resources', 'js', 'Pages', 'Kb', 'Artigos'), { recursive: true });
writeFileSync(join(root, 'resources', 'js', 'Pages', 'Kb', 'Artigos', 'Index.charter.md'),
  '---\npage: /kb/artigos\nrunbook: memory/requisitos/Kb/RUNBOOK-base-de-conhecimento.md\n---\n# Charter');

// ── fixture ANINHADA #3 (CASO-ATAQUE): charter aponta runbook FANTASMA (não existe) ─
// Kb/Fantasma/Index.tsx: charter declara runbook: ...RUNBOOK-nao-existe.md que NÃO existe.
// Nenhum RUNBOOK convencional bate → o charter fantasma NÃO pode furar o gate → BLOQUEIA.
mkdirSync(join(root, 'resources', 'js', 'Pages', 'Kb', 'Fantasma'), { recursive: true });
writeFileSync(join(root, 'resources', 'js', 'Pages', 'Kb', 'Fantasma', 'Index.charter.md'),
  '---\npage: /kb/fantasma\nrunbook: memory/requisitos/Kb/RUNBOOK-nao-existe.md\n---\n# Charter mentiroso');

// ── BLOCK: F1 ausente (ADR 0104 — não há 2º caminho) ────────────────────────────
check('BLOCK: tela sem RUNBOOK (pasta existe)', decide('Edit', 'resources/js/Pages/Sells/Create.tsx', root) !== null);
check('BLOCK: módulo sem pasta de requisitos (F1 nunca rolou)', decide('Write', 'resources/js/Pages/Financeiro/Painel.tsx', root) !== null);
check('BLOCK: subpasta 1 nível sem RUNBOOK', decide('Edit', 'resources/js/Pages/Sells/Detalhe/Resumo.tsx', root) !== null);
check('BLOCK: path Windows backslash', decide('Edit', 'resources\\js\\Pages\\Sells\\Create.tssx'.replace('tssx', 'tsx'), root) !== null);
check('BLOCK: MultiEdit também', decide('MultiEdit', 'resources/js/Pages/Sells/Create.tsx', root) !== null);
// CASO-ATAQUE: charter aponta runbook FANTASMA → NÃO fura o gate (existência é validada)
check('BLOCK: charter aponta runbook inexistente (aninhada) → não fura o gate',
  decide('Edit', 'resources/js/Pages/Kb/Fantasma/Index.tsx', root) !== null);

// ── ALLOW: F1 feita OU fora de escopo (exempções derivadas da regra) ─────────────
check('ALLOW: RUNBOOK existe (Sells/Index)', decide('Edit', 'resources/js/Pages/Sells/Index.tsx', root) === null);
check('ALLOW: lookup case-insensitive (pasta nfebrasil + RUNBOOK-NFCE-STATUS.md ← Linux não pode divergir do Windows)',
  decide('Edit', 'resources/js/Pages/NfeBrasil/NfceStatus.tsx', root) === null);
// ANINHADA: RUNBOOK por rota/subdir (mecanismo #1) — o falso-positivo do PR #4648
check('ALLOW: tela aninhada com RUNBOOK-<subdir>.md (governance/ModuleGrades/Index.tsx)',
  decide('Edit', 'resources/js/Pages/governance/ModuleGrades/Index.tsx', root) === null);
// ANINHADA: RUNBOOK de nome não-convencional, resgatado pelo charter (mecanismo #2)
check('ALLOW: tela aninhada resgatada pelo runbook: do charter (Kb/Artigos/Index.tsx)',
  decide('Edit', 'resources/js/Pages/Kb/Artigos/Index.tsx', root) === null);
check('ALLOW: _components privado do módulo não é tela', decide('Edit', 'resources/js/Pages/Sells/_components/Grid.tsx', root) === null);
check('ALLOW: módulo _Showcase (preview)', decide('Edit', 'resources/js/Pages/_Showcase/Foo.tsx', root) === null);
check('ALLOW: helper _*.tsx não é tela', decide('Edit', 'resources/js/Pages/Sells/_helpers.tsx', root) === null);
check('ALLOW: App/Layout são shell, não tela', decide('Edit', 'resources/js/Pages/Sells/App.tsx', root) === null);
check('ALLOW: fora de Pages/ (Component)', decide('Edit', 'resources/js/Components/Button.tsx', root) === null);
check('ALLOW: Read não bloqueia', decide('Read', 'resources/js/Pages/Sells/Create.tsx', root) === null);
check('ALLOW: path vazio (fail-open)', decide('Edit', '', root) === null);

// ── unidades: kebab + parse + status (redundância de defesa) ─────────────────────
check('toKebab: NfceStatus → nfce-status', toKebab('NfceStatus') === 'nfce-status');
check('toKebab: Index → index', toKebab('Index') === 'index');
check('toKebab: ModuleGrades → module-grades', toKebab('ModuleGrades') === 'module-grades');
check('parsePagePath extrai módulo/subdir/tela (flat → subdir null)',
  JSON.stringify(parsePagePath('resources/js/Pages/Sells/Create.tsx')) === '{"modulo":"Sells","subdir":null,"tela":"Create"}');
check('parsePagePath captura o subdir de tela aninhada',
  JSON.stringify(parsePagePath('resources/js/Pages/governance/ModuleGrades/Index.tsx')) === '{"modulo":"governance","subdir":"ModuleGrades","tela":"Index"}');
check('runbookStatus: sem-pasta vs sem-runbook vs ok (string, back-compat)',
  runbookStatus('Ghost', 'x', root) === 'sem-pasta' &&
  runbookStatus('Sells', 'create', root) === 'sem-runbook' &&
  runbookStatus('Sells', 'index', root) === 'ok');
check('runbookStatus: array de candidatos casa qualquer um (por-rota resolve)',
  runbookStatus('Governance', ['index', 'module-grades'], root) === 'ok' &&
  runbookStatus('Governance', ['index'], root) === 'sem-runbook');
check('runbookStatus: root inexistente → ok (fail-open)', runbookStatus('Sells', 'index', join(root, 'nao-existe')) === 'ok');
check('charterRunbookExists: charter com runbook: válido → true (resgata)',
  charterRunbookExists('resources/js/Pages/Kb/Artigos/Index.tsx', root) === true);
check('charterRunbookExists: charter com runbook: FANTASMA → false (não resgata)',
  charterRunbookExists('resources/js/Pages/Kb/Fantasma/Index.tsx', root) === false);
check('charterRunbookExists: sem charter ao lado → false',
  charterRunbookExists('resources/js/Pages/Sells/Create.tsx', root) === false);
check('mensagem cita ADR 0104 + /cockpit-runbook + override', (() => {
  const m = decide('Edit', 'resources/js/Pages/Sells/Create.tsx', root);
  return /ADR 0104/.test(m) && /cockpit-runbook/.test(m) && /mwart-override/.test(m);
})());

// ── E2E: stdin JSON → exit code, cwd = fixture (prova wrapper + fail-open) ───────
function runHook(stdin) {
  return spawnSync(process.execPath, [HOOK], { input: stdin, encoding: 'utf8', cwd: root }).status;
}
const j = (tool, path) => JSON.stringify({ tool_name: tool, tool_input: { file_path: path } });
check('E2E: tela sem RUNBOOK → exit 2 (BLOQUEIA)', runHook(j('Edit', 'resources/js/Pages/Sells/Create.tsx')) === 2);
check('E2E: RUNBOOK existe → exit 0', runHook(j('Edit', 'resources/js/Pages/Sells/Index.tsx')) === 0);
check('E2E: tela aninhada c/ RUNBOOK-<subdir> → exit 0', runHook(j('Edit', 'resources/js/Pages/governance/ModuleGrades/Index.tsx')) === 0);
check('E2E: charter fantasma (aninhada) → exit 2 (BLOQUEIA)', runHook(j('Edit', 'resources/js/Pages/Kb/Fantasma/Index.tsx')) === 2);
check('E2E: fora de escopo → exit 0', runHook(j('Edit', 'Modules/Jana/Services/Foo.php')) === 0);
check('E2E: stdin vazio → exit 0 (fail-open)', runHook('') === 0);
check('E2E: JSON inválido → exit 0 (fail-open, NUNCA trava sessão)', runHook('{lixo') === 0);

rmSync(root, { recursive: true, force: true });

console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — porte .mjs enforça F1 RUNBOOK (ADR 0104) em Win/Mac/Linux, lookup case-insensitive, RUNBOOK por rota/subdir + resgate por charter validado (fantasma NÃO fura), exempções derivadas da regra; fail-open provado (E2E).');
process.exit(fails ? 1 : 0);

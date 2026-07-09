#!/usr/bin/env node
// @ts-check
// SELF-TEST — prova que design-code-map-check.mjs DETECTA drift (--strict exit 1) e LIBERA
// quando o map.json bate com o disco (exit 0). Monta repo-fixture temporário COM git real
// (staleness precisa de `git log` de verdade — sem isso não dá pra provar a invalidação por
// sha, que é a razão de existir do prototipo_sha). Rodar: node scripts/governance/
// design-code-map-check.test.mjs — exit 0 = passa.
import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'design-code-map-check.mjs');

let fails = 0;
const check = (name, cond) => { console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}`); if (!cond) fails++; };

function git(root, args) { return spawnSync('git', args, { cwd: root, encoding: 'utf8' }); }
function sha(root) { return git(root, ['log', '-1', '--format=%h']).stdout.trim(); }

// ── fixture: repo git real com 1 tela mapeada ──────────────────────────────
const root = mkdtempSync(join(tmpdir(), 'dcmc-'));
git(root, ['init', '-q']);
git(root, ['config', 'user.email', 'test@test.local']);
git(root, ['config', 'user.name', 'test']);

const protoDir = join(root, 'prototipo-ui', 'cowork');
const vivoDir = join(root, 'resources', 'js', 'Pages', 'Fixture');
const reqDir = join(root, 'memory', 'requisitos', 'Fixture');
mkdirSync(protoDir, { recursive: true });
mkdirSync(vivoDir, { recursive: true });
mkdirSync(reqDir, { recursive: true });

writeFileSync(join(protoDir, 'fixture-page.jsx'), 'export default function Page() { return null }\n');
writeFileSync(join(vivoDir, 'Index.tsx'), 'export default function Index() { return null }\n');
git(root, ['add', '-A']);
git(root, ['commit', '-q', '-m', 'v1 protótipo']);
const shaV1 = sha(root);

const runCheck = (extra = []) => spawnSync('node', [SCRIPT, '--root', root, ...extra], { encoding: 'utf8' });

function writeMap(overrides = {}) {
  const base = {
    version: '1', tela: 'Fixture/Index', prototipo_sha: shaV1, gerado_em: '2026-01-01',
    partes: [
      { id: 'header', prototipo: { arquivo: 'prototipo-ui/cowork/fixture-page.jsx', linhas: '1-10' }, vivo: { arquivo: 'resources/js/Pages/Fixture/Index.tsx', linhas: '1-5' }, status: 'paridade', acao: 'no-op' },
    ],
  };
  writeFileSync(join(reqDir, 'index.map.json'), JSON.stringify({ ...base, ...overrides }, null, 2));
}

// 1. LIBERA: map íntegro, sha bate com o commit atual → advisory e strict passam
writeMap();
const okStrict = runCheck(['--check', '--strict']);
check('map íntegro (sha em dia, âncoras existem) → strict exit 0', okStrict.status === 0);
check('reporta 1 map.json encontrado', /1 map\.json encontrado/.test(okStrict.stdout));
check('cobertura sai no relatório', /cobertura:/.test(okStrict.stdout));

// 2. DRIFT — âncora vivo.arquivo quebrada (path que não existe)
writeMap({ partes: [{ id: 'header', prototipo: { arquivo: 'prototipo-ui/cowork/fixture-page.jsx', linhas: '1-10' }, vivo: { arquivo: 'resources/js/Pages/Fixture/FANTASMA.tsx', linhas: '1-5' }, status: 'paridade', acao: 'no-op' }] });
const badAnchor = runCheck(['--check', '--strict']);
check('vivo.arquivo inexistente → strict exit 1', badAnchor.status === 1);
check('motivo aponta FANTASMA', /FANTASMA/.test(badAnchor.stdout));
const badAnchorAdvisory = runCheck(['--check']);
check('mesmo drift, modo advisory (sem --strict) → exit 0', badAnchorAdvisory.status === 0);

// 3. STALE — protótipo re-exportou (novo commit no arquivo-fonte), sha salvo ficou pra trás
writeMap(); // volta ao mapa íntegro (sha=shaV1)
writeFileSync(join(protoDir, 'fixture-page.jsx'), 'export default function Page() { return "v2" }\n');
git(root, ['add', '-A']);
git(root, ['commit', '-q', '-m', 'v2 protótipo re-exportado']);
const stale = runCheck(['--check', '--strict']);
check('protótipo mudou sem regenerar o map → STALE, strict exit 1', stale.status === 1 && /STALE/.test(stale.stdout));

// 4. Regenerar o sha resolve o STALE
const shaV2 = sha(root);
writeMap({ prototipo_sha: shaV2 });
const fixed = runCheck(['--check', '--strict']);
check('sha atualizado (regenerado) → strict exit 0 de novo', fixed.status === 0);

// 5. TODO (âncora ainda não preenchida) NUNCA é drift, mesmo em --strict
writeMap({ partes: [{ id: 'header', prototipo: { arquivo: 'TODO', linhas: 'TODO' }, vivo: { arquivo: 'TODO', linhas: 'TODO' }, status: 'pendente-mapeamento', acao: 'x' }] });
const todoOnly = runCheck(['--check', '--strict']);
check('map só com TODO (recém-gerado) → strict exit 0 (pendência ≠ drift)', todoOnly.status === 0);
check('reporta pendente(s), não [DRIFT]', /pendente/.test(todoOnly.stdout) && !/\[DRIFT\]/.test(todoOnly.stdout));

// 6. schema quebrado (sem 'partes') → sempre drift
writeFileSync(join(reqDir, 'index.map.json'), JSON.stringify({ version: '1', tela: 'x' }, null, 2));
const badSchema = runCheck(['--check', '--strict']);
check('schema sem partes[] → strict exit 1', badSchema.status === 1);

rmSync(root, { recursive: true, force: true });
console.log(fails ? `\nSELFTEST FALHOU (${fails})` : '\nSELFTEST OK — design-code-map-check morde (âncora quebrada, sha stale, schema) e libera certo (íntegro, TODO pendente).');
process.exit(fails ? 1 : 0);

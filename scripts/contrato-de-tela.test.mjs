#!/usr/bin/env node
// TESTE DE REGRESSÃO — prova que o gate "Contrato de Tela" MORDE ("quem vigia os vigias").
// Controle-NEGATIVO: âncora ausente, copy ausente, ordem trocada, símbolo removido sem justificativa
// → exit 1. Controle-POSITIVO: tudo presente / na ordem / removido-mas-justificado → exit 0.
// Fixtures em tmp (hermético) — sem rede, sem DB. Omissão usa um git repo temporário.
// Rodar: node scripts/contrato-de-tela.test.mjs — exit 0 = todos passam, exit 1 = regressão.

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const SCRIPT = join(__dirname, 'contrato-de-tela.mjs');

let fails = 0;
const check = (name, cond, extra = '') => { console.log(`${cond ? '[OK]' : '[FAIL]'} ${name}${cond ? '' : '  ' + extra}`); if (!cond) fails++; };
const node = (root, args) => spawnSync('node', [SCRIPT, '--root', root, ...args], { encoding: 'utf8' });
const out = (r) => (r.stdout || '') + (r.stderr || '');
const git = (root, args) => spawnSync('git', ['-C', root, ...args], { encoding: 'utf8' });

function makeContractRoot({ tsx, contract }) {
  const root = mkdtempSync(join(tmpdir(), 'contrato-'));
  mkdirSync(join(root, 'tela'), { recursive: true });
  writeFileSync(join(root, 'tela', 'Index.tsx'), tsx);
  writeFileSync(join(root, 'contrato.json'), JSON.stringify(contract));
  return root;
}
const drop = (root) => rmSync(root, { recursive: true, force: true });

const GOOD_TSX = `export default function I(){return(<>
  <section data-contract="lista"><h2>Conversas</h2></section>
  <div data-contract="thread">Selecione uma conversa</div>
</>);}`;
const GOOD_CONTRACT = {
  tela: 'Fixture', alvo: ['tela'],
  secoes: [{ id: 'lista', copy: ['Conversas'] }, { id: 'thread', copy: ['Selecione uma conversa'] }],
  ordem: ['lista', 'thread'],
};

// 1. POSITIVO — tudo presente, na ordem → exit 0.
{
  const root = makeContractRoot({ tsx: GOOD_TSX, contract: GOOD_CONTRACT });
  const r = node(root, ['--contract', 'contrato.json']);
  check('contrato OK (âncora+copy+ordem) → exit 0', r.status === 0, out(r));
  drop(root);
}

// 2. NEGATIVO — âncora ausente (seção "contexto" sem data-contract) → exit 1.
{
  const root = makeContractRoot({
    tsx: GOOD_TSX,
    contract: { ...GOOD_CONTRACT, secoes: [...GOOD_CONTRACT.secoes, { id: 'contexto', copy: [] }], ordem: ['lista', 'thread', 'contexto'] },
  });
  const r = node(root, ['--contract', 'contrato.json']);
  check('âncora ausente → exit 1', r.status === 1 && /sem âncora/.test(out(r)), out(r));
  drop(root);
}

// 3. NEGATIVO — copy literal ausente → exit 1.
{
  const root = makeContractRoot({
    tsx: GOOD_TSX,
    contract: { ...GOOD_CONTRACT, secoes: [{ id: 'lista', copy: ['Conversas', 'Texto Que Nao Existe'] }, { id: 'thread', copy: ['Selecione uma conversa'] }] },
  });
  const r = node(root, ['--contract', 'contrato.json']);
  check('copy ausente → exit 1', r.status === 1 && /copy ausente/.test(out(r)), out(r));
  drop(root);
}

// 4. NEGATIVO — ordem trocada (thread antes de lista no fonte) → exit 1.
{
  const tsx = `export default function I(){return(<>
    <div data-contract="thread">Selecione uma conversa</div>
    <section data-contract="lista"><h2>Conversas</h2></section>
  </>);}`;
  const root = makeContractRoot({ tsx, contract: GOOD_CONTRACT });
  const r = node(root, ['--contract', 'contrato.json']);
  check('ordem divergente → exit 1', r.status === 1 && /ordem divergente/.test(out(r)), out(r));
  drop(root);
}

// ── Omissão (git repo temporário) ─────────────────────────────────────────────
function makeGitRepo() {
  const root = mkdtempSync(join(tmpdir(), 'contrato-omi-'));
  mkdirSync(join(root, 'tela'), { recursive: true });
  git(root, ['init', '-q']);
  git(root, ['config', 'user.email', 't@t.t']);
  git(root, ['config', 'user.name', 't']);
  writeFileSync(join(root, 'tela', 'x.ts'), `export function fooBar(){return 1;}\nexport const keep = 2;\n`);
  git(root, ['add', '-A']);
  git(root, ['commit', '-q', '-m', 'base']);
  // remove fooBar
  writeFileSync(join(root, 'tela', 'x.ts'), `export const keep = 2;\n`);
  git(root, ['add', '-A']);
  return root;
}

// 5. NEGATIVO — símbolo removido SEM justificativa → exit 1.
{
  const root = makeGitRepo();
  const gitAvail = git(root, ['rev-parse', 'HEAD']).status === 0;
  if (!gitAvail) { console.log('[SKIP] omissão (git indisponível)'); }
  else {
    git(root, ['commit', '-q', '-m', 'mexe na tela sem citar nada']);
    const r = node(root, ['--omission', 'HEAD~1', '--alvo', 'tela']);
    check('removido sem justificativa → exit 1', r.status === 1 && /removido "fooBar" SEM/.test(out(r)), out(r));
  }
  drop(root);
}

// 6. POSITIVO — símbolo removido COM justificativa no commit → exit 0.
{
  const root = makeGitRepo();
  const gitAvail = git(root, ['rev-parse', 'HEAD']).status === 0;
  if (!gitAvail) { console.log('[SKIP] omissão+just (git indisponível)'); }
  else {
    git(root, ['commit', '-q', '-m', 'remove fooBar — morto desde refactor X (justificado)']);
    const r = node(root, ['--omission', 'HEAD~1', '--alvo', 'tela']);
    check('removido COM justificativa → exit 0', r.status === 0, out(r));
  }
  drop(root);
}

// 7. --map --check POSITIVO — fonte existe + seção ancorada → exit 0.
{
  const root = mkdtempSync(join(tmpdir(), 'contrato-map-'));
  mkdirSync(join(root, 'tela'), { recursive: true });
  mkdirSync(join(root, 'proto'), { recursive: true });
  git(root, ['init', '-q']); git(root, ['config', 'user.email', 't@t.t']); git(root, ['config', 'user.name', 't']);
  if (git(root, ['rev-parse', '--git-dir']).status !== 0) { console.log('[SKIP] --map (git indisponível)'); drop(root); }
  else {
    writeFileSync(join(root, 'proto', 'src.jsx'), '// fonte canônica\n');
    writeFileSync(join(root, 'tela', 'Index.tsx'), 'export default () => (<div data-contract="hero">Olá</div>);');
    writeFileSync(join(root, 'x.contract.json'), JSON.stringify({ tela: 'X', fonte: 'proto/src.jsx', alvo: ['tela'], secoes: [{ id: 'hero', copy: ['Olá'] }] }));
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'base']);
    const r = node(root, ['--map', '--check']);
    check('--map --check fonte+âncora ok → exit 0', r.status === 0, out(r));
    drop(root);
  }
}

// 8. --map --check NEGATIVO — fonte aponta arquivo inexistente → exit 1.
{
  const root = mkdtempSync(join(tmpdir(), 'contrato-map-'));
  mkdirSync(join(root, 'tela'), { recursive: true });
  git(root, ['init', '-q']); git(root, ['config', 'user.email', 't@t.t']); git(root, ['config', 'user.name', 't']);
  if (git(root, ['rev-parse', '--git-dir']).status !== 0) { console.log('[SKIP] --map quebrada (git indisponível)'); drop(root); }
  else {
    writeFileSync(join(root, 'tela', 'Index.tsx'), 'export default () => (<div data-contract="hero">Olá</div>);');
    writeFileSync(join(root, 'x.contract.json'), JSON.stringify({ tela: 'X', fonte: 'proto/NAO-EXISTE.jsx', alvo: ['tela'], secoes: [{ id: 'hero', copy: ['Olá'] }] }));
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'base']);
    const r = node(root, ['--map', '--check']);
    check('--map --check fonte quebrada → exit 1', r.status === 1 && /fonte aponta arquivo inexistente/.test(out(r)), out(r));
    drop(root);
  }
}

console.log(fails ? `\n❌ ${fails} regressão(ões).` : `\n✅ todos os controles passam (gate morde e libera certo).`);
process.exit(fails ? 1 : 0);

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

// ── Acordo de estado backend↔frontend (catraca SEMÂNTICA · ADR 0286 §5) ───────
// Reproduz o bug 2026-06-18: o `connect` emite state:'paired', o `status` emite state:'connected';
// o ReconnectModal só tratava 'connected' → "Canal já pareado — sessão ativa" caía no ramo de ERRO.
// A catraca de PRESENÇA (testes 1-4) passava (âncora + copy ok); a SEMÂNTICA é que morde aqui.
function makeAgreementRoot({ frontendTs, valores = ['paired', 'connected'], backendPhp }) {
  const root = mkdtempSync(join(tmpdir(), 'contrato-acordo-'));
  mkdirSync(join(root, 'backend'), { recursive: true });
  mkdirSync(join(root, 'tela'), { recursive: true });
  // catraca 2a (presença) tem de continuar passando: 1 seção ancorada + copy literal.
  writeFileSync(join(root, 'tela', 'Index.tsx'), `export default () => (<div data-contract="ok">Canal reconectado!</div>);`);
  writeFileSync(join(root, 'tela', 'reconnectState.ts'), frontendTs);
  // backend espelha o ChannelsController: connect→'paired', status→'connected' (os DOIS vocabulários).
  writeFileSync(join(root, 'backend', 'ChannelsController.php'),
    backendPhp ?? `<?php\n// connect → whatsmeowPairedResponse\nreturn ['state' => 'paired', 'paired' => true];\n// statusWhatsmeow\nreturn ['state' => 'connected'];\n`);
  writeFileSync(join(root, 'contrato.json'), JSON.stringify({
    tela: 'AcordoFixture', alvo: ['tela'],
    secoes: [{ id: 'ok', copy: ['Canal reconectado!'] }],
    acordos_estado: [{ id: 'sessao-ativa', valores, backend: 'backend/ChannelsController.php', frontend: ['tela/reconnectState.ts'] }],
  }));
  return root;
}
const FE_GOOD = `export function isSessionActive(d){ return d.paired === true || d.state === 'paired' || d.state === 'connected'; }`;
const FE_BUG = `export function isSessionActive(d){ return d.state === 'connected'; }`; // só 'connected' — O BUG

// 4b.1 POSITIVO — frontend trata 'paired' E 'connected' (= fix #2984 isSessionActive) → exit 0.
{
  const root = makeAgreementRoot({ frontendTs: FE_GOOD });
  const r = node(root, ['--contract', 'contrato.json']);
  check("acordo de estado coerente (paired+connected nos 2 lados) → exit 0", r.status === 0, out(r));
  drop(root);
}

// 4b.2 NEGATIVO — O BUG: backend emite 'paired' mas o frontend só trata 'connected' → exit 1.
{
  const root = makeAgreementRoot({ frontendTs: FE_BUG });
  const r = node(root, ['--contract', 'contrato.json']);
  check(
    "paired≠connected: frontend ignora 'paired' → exit 1 (catraca semântica MORDE — faltou no #2974)",
    r.status === 1 && /paired/.test(out(r)) && /NÃO trata/.test(out(r)),
    out(r),
  );
  drop(root);
}

// 4b.3 NEGATIVO — drift de contrato: declara 'desconectado' que o backend não emite → exit 1.
{
  const root = makeAgreementRoot({ frontendTs: FE_GOOD, valores: ['paired', 'connected', 'desconectado'] });
  const r = node(root, ['--contract', 'contrato.json']);
  check(
    "estado declarado que o backend não emite → exit 1 (drift de contrato)",
    r.status === 1 && /desconectado/.test(out(r)) && /backend não emite/.test(out(r)),
    out(r),
  );
  drop(root);
}

// 4b.4 NEGATIVO — comment-blindness: o frontend tem o BUG (só 'connected') mas CITA 'paired' num
// comentário. Literal em prosa NÃO pode contar (senão é "backdoor de prosa", RUNBOOK §4) → exit 1.
// (sem o strip de comentário isto passaria VERDE — era o furo achado pelo adversário no arquivo real.)
{
  const feBugCommented = `// Aceita 'paired' (connect) e 'connected' (status) — mas o código abaixo só trata um.\nexport function isSessionActive(d){ return d.state === 'connected'; }`;
  const root = makeAgreementRoot({ frontendTs: feBugCommented });
  const r = node(root, ['--contract', 'contrato.json']);
  check(
    "comment-blindness: 'paired' só em comentário NÃO conta → exit 1 (anti backdoor-de-prosa)",
    r.status === 1 && /paired/.test(out(r)) && /NÃO trata/.test(out(r)),
    out(r),
  );
  drop(root);
}

// 4b.5 NEGATIVO — key-false-match: backend renomeou o state ('pareado') mas manteve a CHAVE booleana
// `'paired' => true`. A chave não é emissão de state → backend "não emite" 'paired' → exit 1 (drift).
{
  const bePareado = `<?php\nreturn ['state' => 'pareado', 'paired' => true];\nreturn ['state' => 'connected'];\n`;
  const root = makeAgreementRoot({ frontendTs: FE_GOOD, backendPhp: bePareado });
  const r = node(root, ['--contract', 'contrato.json']);
  check(
    "key-false-match: `'paired' => true` (chave) não conta como emissão → exit 1 (drift)",
    r.status === 1 && /paired/.test(out(r)) && /backend não emite/.test(out(r)),
    out(r),
  );
  drop(root);
}

// 4b.6 POSITIVO — escopo válido explícito (cliente:biz=4) + verdict aprovado → exit 0 (eixo D5).
{
  const root = mkdtempSync(join(tmpdir(), 'contrato-escopo-'));
  mkdirSync(join(root, 'backend'), { recursive: true });
  mkdirSync(join(root, 'tela'), { recursive: true });
  writeFileSync(join(root, 'tela', 'Index.tsx'), `export default () => (<div data-contract="ok">Canal reconectado!</div>);`);
  writeFileSync(join(root, 'tela', 'reconnectState.ts'), FE_GOOD);
  writeFileSync(join(root, 'backend', 'C.php'), `<?php\nreturn ['state' => 'paired'];\nreturn ['state' => 'connected'];\n`);
  writeFileSync(join(root, 'contrato.json'), JSON.stringify({
    tela: 'Esc', alvo: ['tela'], secoes: [{ id: 'ok', copy: ['Canal reconectado!'] }],
    acordos_estado: [{ id: 'sessao-ativa', verdict: 'aprovado', escopo: 'cliente:biz=4', valores: ['paired', 'connected'], backend: 'backend/C.php', frontend: ['tela/reconnectState.ts'] }],
  }));
  const r = node(root, ['--contract', 'contrato.json']);
  check("escopo válido cliente:biz=4 + verdict aprovado → exit 0", r.status === 0 && /escopo:cliente:biz=4/.test(out(r)), out(r));
  drop(root);
}

// 4b.7 NEGATIVO — escopo em formato inválido (typo) → exit 1 (não mis-escopa o veredito · Tier 0).
{
  const root = makeAgreementRoot({ frontendTs: FE_GOOD });
  writeFileSync(join(root, 'contrato.json'), JSON.stringify({
    tela: 'EscBad', alvo: ['tela'], secoes: [{ id: 'ok', copy: ['Canal reconectado!'] }],
    acordos_estado: [{ id: 'sessao-ativa', escopo: 'cliente_biz_4', valores: ['paired', 'connected'], backend: 'backend/ChannelsController.php', frontend: ['tela/reconnectState.ts'] }],
  }));
  const r = node(root, ['--contract', 'contrato.json']);
  check("escopo inválido 'cliente_biz_4' → exit 1 (formato)", r.status === 1 && /escopo inválido/.test(out(r)), out(r));
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

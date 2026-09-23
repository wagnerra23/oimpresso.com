#!/usr/bin/env node
// TESTE DE REGRESSÃO — prova que o gate "Contrato de Tela" MORDE ("quem vigia os vigias").
// Controle-NEGATIVO: âncora ausente, copy ausente, ordem trocada, símbolo removido sem justificativa
// → exit 1. Controle-POSITIVO: tudo presente / na ordem / removido-mas-justificado → exit 0.
// Fixtures em tmp (hermético) — sem rede, sem DB. Omissão usa um git repo temporário.
// Rodar: node scripts/contrato-de-tela.test.mjs — exit 0 = todos passam, exit 1 = regressão.

import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, readFileSync } from 'node:fs';
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

// ── Casamento de copy por FRONTEIRA DE IDENTIFICADOR (endurecimento 2026-08-25) ──
// O furo real: `blob.includes` dizia "presente" pra copy que só existe DENTRO de um
// identificador. Achado escrevendo o contrato de Arquivos/Index — pinar "Payload" passava
// verde por casar em `TrilhaPayload`. Estes 4 casos travam o comportamento nos dois sentidos.

// 4b. NEGATIVO — copy que só existe dentro de um identificador → exit 1 (era o furo).
{
  const tsx = `interface TrilhaPayload { x: number }
export default function I(){return(<>
  <section data-contract="lista"><h2>Conversas</h2></section>
</>);}`;
  const root = makeContractRoot({
    tsx,
    contract: { tela: 'F', alvo: ['tela'], secoes: [{ id: 'lista', copy: ['Conversas', 'Payload'] }] },
  });
  const r = node(root, ['--contract', 'contrato.json']);
  check('copy só dentro de identificador (TrilhaPayload) → exit 1',
    r.status === 1 && /copy ausente/.test(out(r)), out(r));
  drop(root);
}

// 4c. POSITIVO — a MESMA palavra, agora como copy de verdade em texto JSX → exit 0.
// Sem este par, o caso acima passaria por um gate que simplesmente rejeitasse "Payload".
{
  const tsx = `interface TrilhaPayload { x: number }
export default function I(){return(<>
  <section data-contract="lista"><th>Payload</th></section>
</>);}`;
  const root = makeContractRoot({
    tsx,
    contract: { tela: 'F', alvo: ['tela'], secoes: [{ id: 'lista', copy: ['Payload'] }] },
  });
  const r = node(root, ['--contract', 'contrato.json']);
  check('mesma palavra como copy REAL em texto JSX → exit 0', r.status === 0, out(r));
  drop(root);
}

// 4d. POSITIVO — copy legítima colada a pontuação/acento não pode ser derrubada pelo
// endurecimento (é o falso-positivo que mataria o gate na prática).
{
  const tsx = `export default function I(){return(<>
  <section data-contract="lista">
    <th>Ação</th><p>Nenhum evento registrado ainda.</p><span>Onde está preso</span>
  </section>
</>);}`;
  const root = makeContractRoot({
    tsx,
    contract: { tela: 'F', alvo: ['tela'], secoes: [{ id: 'lista', copy: ['Ação', 'Nenhum evento registrado ainda.', 'Onde está preso'] }] },
  });
  const r = node(root, ['--contract', 'contrato.json']);
  check('copy com acento / ponto final / espaços continua passando → exit 0', r.status === 0, out(r));
  drop(root);
}

// 4e. NEGATIVO — prefixo/sufixo de identificador maior também não conta como copy.
{
  const tsx = `const Todososdias = 1; const classificacaoDisco = 2;
export default function I(){return(<section data-contract="lista">x</section>);}`;
  const root = makeContractRoot({
    tsx,
    contract: { tela: 'F', alvo: ['tela'], secoes: [{ id: 'lista', copy: ['Todos'] }] },
  });
  const r = node(root, ['--contract', 'contrato.json']);
  check('copy que é só prefixo de identificador (Todososdias) → exit 1',
    r.status === 1 && /copy ausente/.test(out(r)), out(r));
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
    r.status === 1 && /paired/.test(out(r)) && /NÃO menciona/.test(out(r)),
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
    r.status === 1 && /paired/.test(out(r)) && /NÃO menciona/.test(out(r)),
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

// 4b.8 POSITIVO — strip string-aware: `//` dentro de URL na MESMA linha do state NÃO vira comentário.
// (regex naïve comia o resto da linha → falso-RED no `'state' => 'paired'`; o adversário pegou isso.)
{
  const beUrl = `<?php\nreturn ['url' => 'http://x//y/z', 'state' => 'paired'];\nreturn ['state' => 'connected'];\n`;
  const root = makeAgreementRoot({ frontendTs: FE_GOOD, backendPhp: beUrl });
  const r = node(root, ['--contract', 'contrato.json']);
  check("strip string-aware: '//' em URL não come o state na mesma linha → exit 0", r.status === 0, out(r));
  drop(root);
}

// 4b.9 NEGATIVO — escopo com path-traversal (`tela:../../etc/passwd`) → exit 1 (Tier 0).
{
  const root = makeAgreementRoot({ frontendTs: FE_GOOD });
  writeFileSync(join(root, 'contrato.json'), JSON.stringify({
    tela: 'EscTrav', alvo: ['tela'], secoes: [{ id: 'ok', copy: ['Canal reconectado!'] }],
    acordos_estado: [{ id: 'sessao-ativa', escopo: 'tela:../../etc/passwd', valores: ['paired', 'connected'], backend: 'backend/ChannelsController.php', frontend: ['tela/reconnectState.ts'] }],
  }));
  const r = node(root, ['--contract', 'contrato.json']);
  check("escopo path-traversal 'tela:../../etc/passwd' → exit 1", r.status === 1 && /escopo inválido/.test(out(r)), out(r));
  drop(root);
}

// ── Onda 2: resolução de escopo (não-vazamento Tier 0 · P0) ────────────────────
function makeResolveRoot(acordos) {
  const root = mkdtempSync(join(tmpdir(), 'contrato-resolve-'));
  mkdirSync(join(root, 'tela'), { recursive: true });
  writeFileSync(join(root, 'tela', 'Index.tsx'), `export default () => (<div data-contract="ok">x</div>);`);
  writeFileSync(join(root, 'contrato.json'), JSON.stringify({
    tela: 'R', alvo: ['tela'], secoes: [{ id: 'ok', copy: [] }], acordos_estado: acordos,
  }));
  return root;
}
// mesmo conceito `sessao-ativa` em 3 escopos (global / cliente:biz=4 / tela:R)
const ACORDOS_MULTI = [
  { id: 'sessao-ativa', escopo: 'global', valores: ['paired'], backend: 'tela/Index.tsx' },
  { id: 'sessao-ativa', escopo: 'cliente:biz=4', valores: ['paired'], backend: 'tela/Index.tsx' },
  { id: 'sessao-ativa', escopo: 'tela:R', valores: ['paired'], backend: 'tela/Index.tsx' },
];

// O2.1 — especificidade: ctx cliente:biz=4 (sem tela) → vence cliente:biz=4 (> global).
{
  const root = makeResolveRoot(ACORDOS_MULTI);
  const r = node(root, ['--resolve', 'contrato.json', '--ctx', 'cliente:biz=4']);
  check("resolução: ctx biz=4 → vence escopo cliente:biz=4 (> global)",
    /vence \[escopo:cliente:biz=4\]/.test(out(r)), out(r));
  drop(root);
}

// O2.2 — NÃO-VAZAMENTO TIER 0 (P0): ctx biz=7 → o veredito biz=4 NÃO aplica; cai pro global.
{
  const root = makeResolveRoot(ACORDOS_MULTI);
  const r = node(root, ['--resolve', 'contrato.json', '--ctx', 'cliente:biz=7']);
  check("NÃO-VAZAMENTO Tier 0: veredito cliente:biz=4 NÃO aplica a biz=7 (vence global)",
    /vence \[escopo:global\]/.test(out(r)) && !/vence \[escopo:cliente:biz=4\]/.test(out(r)), out(r));
  drop(root);
}

// O2.3 — tela > cliente: ctx tela:R + cliente:biz=4 → vence tela:R.
{
  const root = makeResolveRoot(ACORDOS_MULTI);
  const r = node(root, ['--resolve', 'contrato.json', '--ctx', 'tela:R,cliente:biz=4']);
  check("resolução: ctx tela:R+biz=4 → vence tela:R (tela > cliente)",
    /vence \[escopo:tela:R\]/.test(out(r)), out(r));
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

// ── C1/C2: as 2 correções de ruído medidas em 2026-09-22 no corpus real ───────
// Corpus: 231 merges de PR do main, escopo Pages+Modules. Antes: 22 merges acusados
// (16,3%), 152 acusações. Depois de C1+C2: 11 merges (8,1%), 48 acusações — 68% do
// ruído eliminado. Cada correção tem aqui o caso que ela conserta E o controle
// negativo que prova que ela não desligou a catraca.
function repoOmissao(nome) {
  const root = mkdtempSync(join(tmpdir(), nome));
  mkdirSync(join(root, 'tela'), { recursive: true });
  git(root, ['init', '-q']);
  git(root, ['config', 'user.email', 't@t.t']);
  git(root, ['config', 'user.name', 't']);
  return git(root, ['rev-parse', '--git-dir']).status === 0 ? root : (drop(root), null);
}

// 6b. C1 POSITIVO — símbolo MOVIDO de arquivo reaparece no `+` → NÃO é omissão → exit 0.
//     Era 52 das 152 acusações (34,2%), falso-positivo por construção.
{
  const root = repoOmissao('contrato-omis-mov-');
  if (!root) console.log('[SKIP] C1 movido (git indisponível)');
  else {
    writeFileSync(join(root, 'tela', 'x.ts'), `export function Sparkline(){return 1;}\n`);
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'base']);
    writeFileSync(join(root, 'tela', 'x.ts'), `// movido daqui\n`);
    writeFileSync(join(root, 'tela', 'y.ts'), `export function Sparkline(){return 1;}\n`);
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'reorganiza arquivos da tela']);
    const r = node(root, ['--omission', 'HEAD~1', '--alvo', 'tela']);
    check('C1 símbolo movido de arquivo → exit 0 (reaparece no diff, não é omissão)',
      r.status === 0 && /reaparece no diff/.test(out(r)), out(r));
    drop(root);
  }
}

// 6c. C1 CONTROLE NEGATIVO — sumiu de vez (não reaparece) → AINDA acusa → exit 1.
//     Sem este, C1 poderia ter desligado a catraca inteira e o verde pareceria saúde.
{
  const root = repoOmissao('contrato-omis-sumiu-');
  if (!root) console.log('[SKIP] C1 controle negativo (git indisponível)');
  else {
    writeFileSync(join(root, 'tela', 'x.ts'), `export function Sparkline(){return 1;}\n`);
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'base']);
    writeFileSync(join(root, 'tela', 'x.ts'), `// nada\n`);
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'mexe na tela sem citar nada']);
    const r = node(root, ['--omission', 'HEAD~1', '--alvo', 'tela']);
    check('C1 controle negativo: sumiu de vez → exit 1 (a catraca continua mordendo)',
      r.status === 1 && /SEM justificativa/.test(out(r)), out(r));
    drop(root);
  }
}

// 6d. C2 POSITIVO — descrição de it() renomeada NÃO acusa → exit 0.
//     Era 53 das 152 (34,9%), toda a amostra ruído: renomear a frase é o trabalho do PR.
{
  const root = repoOmissao('contrato-omis-testdesc-');
  if (!root) console.log('[SKIP] C2 descrição de teste (git indisponível)');
  else {
    writeFileSync(join(root, 'tela', 'x.spec.ts'), `it('gold-set tem >= 20 perguntas canon', () => {});\n`);
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'base']);
    writeFileSync(join(root, 'tela', 'x.spec.ts'), `it('gold-set tem >= 30 perguntas canon', () => {});\n`);
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'amplia o gold-set']);
    const r = node(root, ['--omission', 'HEAD~1', '--alvo', 'tela']);
    check('C2 descrição de teste renomeada → exit 0 (prosa não é símbolo)',
      r.status === 0 && /família não acusa/.test(out(r)), out(r));
    drop(root);
  }
}

// 6e. C2 CONTROLE NEGATIVO — C2 vale SÓ pra descrição de teste; símbolo no mesmo PR
//     continua acusando. Prova que C2 não virou anistia geral pra arquivo de teste.
{
  const root = repoOmissao('contrato-omis-c2neg-');
  if (!root) console.log('[SKIP] C2 controle negativo (git indisponível)');
  else {
    writeFileSync(join(root, 'tela', 'x.spec.ts'),
      `it('descrição antiga', () => {});\nexport function helperDoTeste(){return 1;}\n`);
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'base']);
    writeFileSync(join(root, 'tela', 'x.spec.ts'), `it('descrição nova', () => {});\n`);
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'renomeia o caso']);
    const r = node(root, ['--omission', 'HEAD~1', '--alvo', 'tela']);
    check('C2 controle negativo: símbolo some junto → exit 1 (C2 não anistia o arquivo)',
      r.status === 1 && /helperDoTeste/.test(out(r)), out(r));
    drop(root);
  }
}

// 6f. C3 POSITIVO — assinatura de função (sem `export`) alterada: a linha sai no `-` e
//     volta no `+` com o MESMO nome → não é omissão → exit 0. Até 2026-09-23 a regex da
//     família `function` era ancorada em `^[-]`, nunca casava o `+`, e C1 não valia pra ela:
//     acrescentar um parâmetro virava "removido SEM justificativa" (PR #7784).
{
  const root = repoOmissao('contrato-omis-assin-');
  if (!root) console.log('[SKIP] C3 assinatura alterada (git indisponível)');
  else {
    writeFileSync(join(root, 'tela', 'x.tsx'),
      `function FinanceiroConciliacao({ linhas, filters }: Props) {
  return null;
}
`);
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'base']);
    writeFileSync(join(root, 'tela', 'x.tsx'),
      `function FinanceiroConciliacao({ linhas, filters, resumo }: Props) {
  return null;
}
`);
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'acrescenta prop na tela']);
    const r = node(root, ['--omission', 'HEAD~1', '--alvo', 'tela']);
    check('C3 assinatura de função alterada → exit 0 (reaparece no +, não é omissão)',
      r.status === 0 && /reaparece no diff/.test(out(r)), out(r));
    drop(root);
  }
}

// 6g. C3 CONTROLE NEGATIVO — função (sem `export`) removida de vez → AINDA acusa → exit 1.
//     Prova que abrir a âncora pro `+` não desligou a família.
{
  const root = repoOmissao('contrato-omis-fnsumiu-');
  if (!root) console.log('[SKIP] C3 controle negativo (git indisponível)');
  else {
    writeFileSync(join(root, 'tela', 'x.tsx'),
      `function helperInterno(a) {
  return a;
}
export const keep = 1;
`);
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'base']);
    writeFileSync(join(root, 'tela', 'x.tsx'), `export const keep = 1;
`);
    git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'mexe na tela sem citar nada']);
    const r = node(root, ['--omission', 'HEAD~1', '--alvo', 'tela']);
    check('C3 controle negativo: função sumiu de vez → exit 1',
      r.status === 1 && /removido "helperInterno" SEM/.test(out(r)), out(r));
    drop(root);
  }
}

// 6h. C1 por FAMÍLIA — as rotas (`route()` e `Route::`) também têm de reaparecer no `+`.
//     O 6b só cobria `export`; foi por cobrir uma família só que a âncora da família
//     `function` passou (6f). Aqui cada família restante ganha o seu caso "movido → exit 0".
const NL = String.fromCharCode(10); // sem barra invertida: escrita por script colapsa o par (LC-26)
for (const [fam, antes, depois] of [
  ['route()', "const u = route('fin.conciliacao.index');" + NL, "const url = route('fin.conciliacao.index', { page: 2 });" + NL],
  ['Route::', "Route::get('/fin/conciliacao', [C::class, 'index']);" + NL, "Route::get('/fin/conciliacao', [C::class, 'index'])->name('x');" + NL],
]) {
  const root = repoOmissao('contrato-omis-fam-');
  if (!root) { console.log(`[SKIP] C1 família ${fam} (git indisponível)`); continue; }
  writeFileSync(join(root, 'tela', 'x.ts'), antes);
  git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'base']);
  writeFileSync(join(root, 'tela', 'x.ts'), depois);
  git(root, ['add', '-A']); git(root, ['commit', '-q', '-m', 'mexe na linha']);
  const r = node(root, ['--omission', 'HEAD~1', '--alvo', 'tela']);
  check(`C1 família ${fam}: linha alterada com o mesmo símbolo → exit 0`,
    r.status === 0 && /reaparece no diff/.test(out(r)), out(r));
  drop(root);
}

// 6i. C4 — invariante de SYMBOL_RES (toda família casa "-" e "+" com o mesmo símbolo).
//     Positivo no script real; negativos numa CÓPIA MUTADA do script (o CLI de fora, não um
//     helper — LC-15). Cada mutação reproduz uma forma do defeito do C3.
{
  const r = node(tmpdir(), ['--check-symbol-res']);
  check('C4 invariante SYMBOL_RES no script real → exit 0', r.status === 0 && !/assimétrico/.test(out(r)), out(r));
}
function scriptMutado(nome, de, para) {
  const dir = mkdtempSync(join(tmpdir(), nome));
  const src = readFileSync(SCRIPT, 'utf8');
  if (src.split(de).length !== 2) throw new Error(`mutação "${nome}": âncora não é única`);
  writeFileSync(join(dir, 'contrato-de-tela.mjs'), src.replace(de, para));
  writeFileSync(join(dir, 'auditar-intencao-fluxo.mjs'), readFileSync(join(__dirname, 'auditar-intencao-fluxo.mjs'), 'utf8'));
  return dir;
}
const runMut = (dir, args) => spawnSync('node', [join(dir, 'contrato-de-tela.mjs'), '--root', dir, ...args], { encoding: 'utf8' });
for (const [nome, de, para, sinal] of [
  // (a) o próprio defeito do C3: âncora só no "-".
  ['contrato-mut-ancora-', "re: /^[-+]\\s*(?:async", "re: /^[-]\\s*(?:async", /"function" assimétrico/],
  // (b) família sem amostra (a 6ª família futura nasce assim).
  ['contrato-mut-semamostra-', `amostra: ["const u = route('fin.conciliacao.index');", 'fin.conciliacao.index'], `, '', /"route\(\)" sem `amostra`/],
]) {
  const dir = scriptMutado(nome, de, para);
  const r = runMut(dir, ['--check-symbol-res']);
  check(`C4 controle negativo (${nome}) → exit 1`, r.status === 1 && sinal.test(out(r)), out(r));
  // e o --omission se recusa a rodar com família cega (não vira verde mudo).
  const o = runMut(dir, ['--omission', 'HEAD', '--alvo', 'x']);
  check(`C4 --omission recusa rodar com invariante quebrado (${nome}) → exit 1`,
    o.status === 1 && /invariante de SYMBOL_RES quebrado/.test(out(o)), out(o));
  drop(dir);
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

// 9. ESPELHO/DOC de design — contrato de OUTRO schema (sem `alvo`) e PULADO sob
//    `prototipo-ui/design-docs/` e sob `prototipo-ui/cowork/Wagner/` (espelho de leitura, ADR 0374),
//    mas REPROVA fora dessas pastas. O par good/bad e o que impede o skip de virar carimbo:
//    se alguem trocar o predicado por um `return 0` cego, o caso (c) fica vermelho.
{
  // schema do Cowork: tem `secoes`, NAO tem `alvo` — invalido como contrato do repo.
  const COWORK_SCHEMA = { id: 'x', titulo: 'X', build: ['x-page.jsx'], raiz: '.x-root', secoes: [{ id: 'header' }] };
  const casos = [
    ['prototipo-ui/design-docs/contrato-cowork/x.contract.json', 0, 'design-docs/'],
    ['prototipo-ui/cowork/Wagner/contrato/x.contract.json', 0, 'prototipo-ui/cowork/Wagner/'],
    ['governance/design/contracts/x.contract.json', 1, null],   // controle NEGATIVO: fora do espelho, morde
  ];
  for (const [rel, esperado, pasta] of casos) {
    const root = mkdtempSync(join(tmpdir(), 'contrato-doc-'));
    mkdirSync(join(root, dirname(rel)), { recursive: true });
    writeFileSync(join(root, rel), JSON.stringify(COWORK_SCHEMA));
    const r = node(root, ['--contract', rel]);
    const okStatus = r.status === esperado;
    const okMsg = esperado === 0
      ? new RegExp(`pulado.*${pasta.replace('/', '\/')}`).test(out(r))
      : /contrato sem `alvo`/.test(out(r));
    check(`--contract ${rel} → exit ${esperado}${esperado === 0 ? ' (pulado)' : ' (REPROVA)'}`,
      okStatus && okMsg, `status=${r.status} ${out(r)}`);
    drop(root);
  }
}

// 10. PREFLIGHT x --merge-ref — a perna de ancestralidade e PULADA quando o HEAD e o merge ref
//     de um PR (checkout@v4 em pull_request), mas as demais seguem. O caso (c) e o que impede
//     a flag de virar carimbo: com a flag ligada, worktree orfao AINDA reprova.
{
  // repo com uma branch genuinamente NAO-ancestral da base
  const mk = () => {
    const root = mkdtempSync(join(tmpdir(), 'preflight-'));
    const g = (...a) => git(root, a);
    g('init', '-q'); g('config', 'user.email', 't@t.t'); g('config', 'user.name', 't');
    writeFileSync(join(root, 'a.txt'), '1'); g('add', '-A'); g('commit', '-q', '-m', 'base');
    g('branch', 'base-ref');
    g('checkout', '-q', '-b', 'feature');
    writeFileSync(join(root, 'b.txt'), '2'); g('add', '-A'); g('commit', '-q', '-m', 'feature');
    // base-ref anda DEPOIS: feature deixa de ser descendente dela
    g('checkout', '-q', 'base-ref');
    writeFileSync(join(root, 'c.txt'), '3'); g('add', '-A'); g('commit', '-q', '-m', 'base anda');
    g('checkout', '-q', 'feature');
    return root;
  };
  if (git(mkdtempSync(join(tmpdir(), 'probe-')), ['--version']).status !== 0) {
    console.log('[SKIP] preflight (git indisponivel)');
  } else {
    // (a) SEM a flag, base nao-ancestral -> MORDE
    let root = mk();
    let r = node(root, ['--preflight', 'base-ref']);
    check('preflight sem --merge-ref, base nao-ancestral -> exit 1',
      r.status === 1 && /nao-ancestral|não-ancestral/.test(out(r)), `status=${r.status} ${out(r)}`);
    drop(root);

    // (b) COM a flag, MESMA base nao-ancestral -> PULA (e diz que pulou)
    root = mk();
    r = node(root, ['--preflight', 'base-ref', '--merge-ref']);
    check('preflight com --merge-ref, base nao-ancestral -> exit 0 + diz PULADA',
      r.status === 0 && /ancestralidade PULADA/.test(out(r)), `status=${r.status} ${out(r)}`);
    drop(root);

    // (c) CONTROLE NEGATIVO: com a flag ligada, worktree orfao AINDA reprova (nao virou carimbo)
    root = mkdtempSync(join(tmpdir(), 'preflight-orfao-'));
    git(root, ['init', '-q']);
    r = node(root, ['--preflight', 'HEAD', '--merge-ref']);
    check('preflight com --merge-ref, worktree orfao -> exit 1 (a flag NAO e carimbo)',
      r.status === 1 && /worktree/.test(out(r)), `status=${r.status} ${out(r)}`);
    drop(root);
  }
}

// ── Catraca 4 (--anti-tautologia): o contrato deriva da ÂNCORA, não da TELA ────
// Par good/bad + o terceiro controle que impede o modo de virar carimbo: copy só
// no alvo AVISA, não bloqueia (o FP medido é 15/31 — reprovar ali seria guard
// sintático). Origem: erro do [C] em 2026-09-09 (contrato de Patrimonio/Bens com a
// copy extraída do .tsx), nomeado pelo [W]: "tá pegando baseline e não pego a
// âncora certa".
{
  const mkAnti = ({ fonte, copy, tsx }) => {
    const root = mkdtempSync(join(tmpdir(), 'anti-taut-'));
    mkdirSync(join(root, 'governance', 'design', 'contracts'), { recursive: true });
    mkdirSync(join(root, 'prototipo-ui', 'cowork', 'Wagner'), { recursive: true });
    mkdirSync(join(root, 'resources', 'js', 'Pages', 'Foo'), { recursive: true });
    writeFileSync(join(root, 'resources', 'js', 'Pages', 'Foo', 'Index.tsx'), tsx);
    writeFileSync(join(root, 'prototipo-ui', 'cowork', 'Wagner', 'foo-page.jsx'), `const P = () => <div>Titulo Foo</div>;`);
    writeFileSync(join(root, 'governance', 'design', 'contracts', 'foo.contract.json'), JSON.stringify({
      tela: 'Foo/Index', fonte, alvo: ['resources/js/Pages/Foo/Index.tsx'],
      secoes: [{ id: 'cab', copy }],
    }));
    return root;
  };
  const TSX_OK = `export default function X(){return <div data-contract="cab">Titulo Foo</div>}`;

  // (a) RUIM: `fonte` é a própria tela → contrato tautológico → MORDE
  let root = mkAnti({ fonte: 'resources/js/Pages/Foo/Index.tsx', copy: ['Titulo Foo'], tsx: TSX_OK });
  let r = node(root, ['--anti-tautologia']);
  check('anti-tautologia: fonte = a propria tela -> exit 1',
    r.status === 1 && /PR[ÓO]PRIA TELA/i.test(out(r)), `status=${r.status} ${out(r)}`);
  drop(root);

  // (b) BOM: `fonte` é o protótipo → passa (controle negativo — não é carimbo ao contrário)
  root = mkAnti({ fonte: 'prototipo-ui/cowork/Wagner/foo-page.jsx', copy: ['Titulo Foo'], tsx: TSX_OK });
  r = node(root, ['--anti-tautologia']);
  check('anti-tautologia: fonte = prototipo -> exit 0',
    r.status === 0, `status=${r.status} ${out(r)}`);
  drop(root);

  // (c) CONTROLE que impede virar bloqueio: copy só no alvo AVISA e NÃO reprova
  root = mkAnti({
    fonte: 'prototipo-ui/cowork/Wagner/foo-page.jsx', copy: ['Titulo Foo', 'Botao Inventado'],
    tsx: `export default function X(){return <div data-contract="cab">Titulo Foo Botao Inventado</div>}`,
  });
  r = node(root, ['--anti-tautologia']);
  check('anti-tautologia: copy so no alvo -> AVISA, exit 0 (nao vira guard sintatico)',
    r.status === 0 && /existe no ALVO e n[ãa]o na FONTE/i.test(out(r)), `status=${r.status} ${out(r)}`);
  drop(root);
}

console.log(fails ? `\n❌ ${fails} regressão(ões).` : `\n✅ todos os controles passam (gate morde e libera certo).`);
process.exit(fails ? 1 : 0);

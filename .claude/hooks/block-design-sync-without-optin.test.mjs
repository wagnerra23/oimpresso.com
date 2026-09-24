#!/usr/bin/env node
// Teste da LÓGICA de block-design-sync-without-optin (ADR 0315 / fecha Gap 1 da 0299).
// Importa as funções puras e prova a classificação read-vs-write (incl. default-deny de
// método futuro/ausente), o opt-in por prompt, e o end-to-end real (spawn do hook → exit code).
// Complementa settings-design-sync-registration.test.mjs (que prova que o hook está REGISTRADO).
//
// Rodar: node .claude/hooks/block-design-sync-without-optin.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import {
  classifyDesignSync,
  isDesignSyncOptInPrompt,
  hasValidOptIn,
  isRetornoCoworkInbox,
  RETORNO_PROJECT_ID,
  READ_METHODS,
  OPT_IN_EXEMPLOS,
  denyMessage,
} from './block-design-sync-without-optin.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const HOOK = join(HERE, 'block-design-sync-without-optin.mjs');

let fails = 0;
function check(name, cond) {
  console.log((cond ? '[OK]   ' : '[FAIL] ') + name);
  if (!cond) fails++;
}

// ── 1. Leitura é sempre permitida (não-escrita, sem opt-in) ───────────────────
for (const m of ['list_projects', 'get_project', 'list_files', 'get_file', 'report_validate']) {
  check(`leitura "${m}" NÃO é escrita (livre)`, classifyDesignSync('DesignSync', { method: m }).isWrite === false);
}

// ── 2. Escrita conhecida é gateada ────────────────────────────────────────────
for (const m of ['finalize_plan', 'write_files', 'delete_files', 'register_assets', 'unregister_assets', 'create_project']) {
  check(`escrita "${m}" é gateada (isWrite)`, classifyDesignSync('DesignSync', { method: m }).isWrite === true);
}

// ── 3. DEFAULT-DENY: método futuro/desconhecido e method ausente → gateado ─────
check('método FUTURO desconhecido → gateado (default-deny)', classifyDesignSync('DesignSync', { method: 'publish_to_org_xpto' }).isWrite === true);
check('method AUSENTE → gateado (default-deny, fail-closed)', classifyDesignSync('DesignSync', {}).isWrite === true);
check('method vazio "" → gateado', classifyDesignSync('DesignSync', { method: '' }).isWrite === true);

// ── 4. Não-DesignSync passa reto (falso-positivo = veneno) ────────────────────
check('Write (tool nativa) NÃO é DesignSync', classifyDesignSync('Write', { method: 'write_files' }).isDesignSync === false);
check('tool MCP qualquer NÃO é DesignSync', classifyDesignSync('mcp__Oimpresso_MCP___Wagner__brief-fetch', {}).isDesignSync === false);
check('READ_METHODS cobre exatamente os 5 de leitura', READ_METHODS.size === 5);

// ── 5. Opt-in por prompt — exige INTENÇÃO de publicar, não menção ─────────────
// Positivos: comando /design-sync OU nome-da-feature + verbo de publicar.
check('"/design-sync e sobe" dá opt-in (comando)', isDesignSyncOptInPrompt('roda o /design-sync e sobe o botão'));
check('"manda pro claude.ai/design" dá opt-in (alvo+verbo)', isDesignSyncOptInPrompt('manda o componente pro claude.ai/design'));
check('"sobe os componentes pro design-sync" dá opt-in', isDesignSyncOptInPrompt('sobe os componentes pro design-sync'));
check('"publica no design sync" dá opt-in', isDesignSyncOptInPrompt('publica isso no design sync'));
// ── REGRESSÃO do red-team 2026-06-30 (furo #2 fail-open): DISCUTIR não arma ───
check('FURO#2: "como funciona o design sync?" NÃO arma escrita', !isDesignSyncOptInPrompt('como funciona o design sync?'));
check('FURO#2: "o que é design-sync?" NÃO arma escrita', !isDesignSyncOptInPrompt('o que é design-sync?'));
check('FURO#2: "explica o claude.ai/design" NÃO arma', !isDesignSyncOptInPrompt('explica o claude.ai/design pra mim'));
check('FURO#2: "nunca usa design sync" NÃO arma (deny nunca/jamais)', !isDesignSyncOptInPrompt('nunca usa design sync aqui'));
check('"não é design-sync, é cowork" NÃO dá opt-in (negação)', !isDesignSyncOptInPrompt('não é design-sync, é cowork'));
check('"melhora o design da tela" NÃO dá opt-in (design ≠ design-sync)', !isDesignSyncOptInPrompt('melhora o design da tela de venda'));
check('"design system em git" NÃO dá opt-in (sem verbo de publicar)', !isDesignSyncOptInPrompt('o design system em git é a fonte'));
check('prompt vazio NÃO dá opt-in', !isDesignSyncOptInPrompt(''));

// ── 6. Opt-in válido via env (escape valve) ───────────────────────────────────
const savedEnv = process.env.OIMPRESSO_DESIGN_SYNC_OK;
process.env.OIMPRESSO_DESIGN_SYNC_OK = '1';
check('OIMPRESSO_DESIGN_SYNC_OK=1 concede opt-in', hasValidOptIn());
if (savedEnv === undefined) delete process.env.OIMPRESSO_DESIGN_SYNC_OK; else process.env.OIMPRESSO_DESIGN_SYNC_OK = savedEnv;

// ── 7. END-TO-END: spawn do hook, prova exit code real ────────────────────────
function runHook(payload, env = {}) {
  const r = spawnSync(process.execPath, [HOOK], {
    input: JSON.stringify(payload),
    encoding: 'utf8',
    env: { ...process.env, OIMPRESSO_DESIGN_SYNC_OK: '', ...env },
  });
  return { code: r.status, stderr: r.stderr || '' };
}

// write_files SEM opt-in → exit 2 (BLOQUEIA) — este é o Gap que se fecha
const e2eBlock = runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'write_files' } });
check('E2E: write_files sem opt-in → exit 2 (BLOQUEIA)', e2eBlock.code === 2);
check('E2E: stderr cita ADR 0315', /0315/.test(e2eBlock.stderr));

// create_project SEM opt-in → exit 2
check('E2E: create_project sem opt-in → exit 2', runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'create_project' } }).code === 2);

// list_projects (leitura) SEM opt-in → exit 0 (inspeção livre)
check('E2E: list_projects sem opt-in → exit 0 (livre)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'list_projects' } }).code === 0);

// write_files COM opt-in (env) → exit 0
check('E2E: write_files COM opt-in (env) → exit 0', runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'write_files' } }, { OIMPRESSO_DESIGN_SYNC_OK: '1' }).code === 0);

// tool não-DesignSync → exit 0
check('E2E: tool Write → exit 0 (não é DesignSync)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'Write', tool_input: {} }).code === 0);

// método futuro desconhecido SEM opt-in → exit 2 (default-deny end-to-end)
check('E2E: método futuro sem opt-in → exit 2 (default-deny)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'sync_everything_v2' } }).code === 2);

// ── RETORNO do Code ao Cowork (sem opt-in, só em cowork-inbox/) ─────────────
const P = RETORNO_PROJECT_ID;
const DS_ID = '019dd02f-d2d0-7ba6-a57f-24b3ddd073ac';
const rec = 'cowork-inbox/placar/playbook/_saida-01.md';
check('RETORNO: finalize_plan em cowork-inbox/ libera', isRetornoCoworkInbox({ method: 'finalize_plan', projectId: P, writes: [rec], deletes: [] }));
check('RETORNO: write_files por localPath libera', isRetornoCoworkInbox({ method: 'write_files', projectId: P, files: [{ path: rec, localPath: rec }] }));
check('CONTROLE: tela fora de cowork-inbox/ NÃO libera', !isRetornoCoworkInbox({ method: 'finalize_plan', projectId: P, writes: [rec, 'clientes-page.jsx'], deletes: [] }));
check('CONTROLE: com deleção NÃO libera', !isRetornoCoworkInbox({ method: 'finalize_plan', projectId: P, writes: [rec], deletes: ['cowork-inbox/x.md'] }));
check('CONTROLE: curinga NÃO libera', !isRetornoCoworkInbox({ method: 'finalize_plan', projectId: P, writes: ['cowork-inbox/**'], deletes: [] }));
check('CONTROLE: `..` NÃO libera', !isRetornoCoworkInbox({ method: 'finalize_plan', projectId: P, writes: ['cowork-inbox/../styles.css'], deletes: [] }));
check('CONTROLE: projeto do Design System NÃO libera', !isRetornoCoworkInbox({ method: 'finalize_plan', projectId: DS_ID, writes: [rec], deletes: [] }));
check('CONTROLE: conteúdo inline (data) NÃO libera', !isRetornoCoworkInbox({ method: 'write_files', projectId: P, files: [{ path: rec, data: 'x' }] }));
check('CONTROLE: plano vazio NÃO libera', !isRetornoCoworkInbox({ method: 'finalize_plan', projectId: P, writes: [], deletes: [] }));
check('CONTROLE: delete_files NÃO libera', !isRetornoCoworkInbox({ method: 'delete_files', projectId: P, paths: [rec] }));
// ADR 0412: o FORMATO sozinho não isenta mais — sem localDir no espelho não há o que medir.
check('E2E: retorno em cowork-inbox/ SEM localDir medível → exit 2 (formato não basta)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'finalize_plan', projectId: P, writes: [rec], deletes: [] } }).code === 2);

// ── ADR 0412: isenção = canon JÁ MERGEADO (blob local ≡ origin/main), medido num repo real ──
// Sandbox git hermético: espelho prototipo-ui/cowork/Wagner, um arquivo mergeado (commit apontado
// por refs/remotes/origin/main) e variações que NÃO podem passar.
function g(cwd, ...args) {
  const r = spawnSync('git', args, { cwd, encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`git ${args.join(' ')}: ${r.stderr}`);
  return r.stdout.trim();
}
function runHookIn(cwd, tool_input) {
  const r = spawnSync(process.execPath, [HOOK], {
    cwd,
    input: JSON.stringify({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input }),
    encoding: 'utf8',
    env: { ...process.env, OIMPRESSO_DESIGN_SYNC_OK: '' },
  });
  return { code: r.status, stderr: r.stderr || '' };
}
const SB = mkdtempSync(join(tmpdir(), 'ds-retorno-'));
try {
  g(SB, 'init', '-q');
  g(SB, 'config', 'user.email', 't@t'); g(SB, 'config', 'user.name', 't'); g(SB, 'config', 'core.autocrlf', 'false');
  const ESP = join(SB, 'prototipo-ui', 'cowork', 'Wagner');
  const inbox = join(ESP, 'cowork-inbox', 'placar', 'playbook');
  mkdirSync(inbox, { recursive: true });
  writeFileSync(join(inbox, '_saida-01.md'), 'recibo mergeado\n');
  writeFileSync(join(inbox, '_saida-02.md'), 'recibo mergeado 2\n');
  g(SB, 'add', '-A'); g(SB, 'commit', '-q', '-m', 'canon');
  g(SB, 'update-ref', 'refs/remotes/origin/main', 'HEAD');
  const fin = (writes, extra = {}) => ({ method: 'finalize_plan', projectId: P, localDir: ESP, writes, deletes: [], ...extra });
  const wf = (files) => ({ method: 'write_files', projectId: P, planId: 'x', files });

  // BOA: arquivo idêntico a origin/main → plano e upload passam sem opt-in
  check('BITE boa: finalize_plan de canon mergeado → exit 0', runHookIn(SB, fin([rec])).code === 0);
  check('BITE boa: write_files do mesmo arquivo (localPath === path) → exit 0', runHookIn(SB, wf([{ path: rec, localPath: rec }])).code === 0);
  // Fora do plano medido
  check('BITE: write_files de path fora do plano medido → exit 2', runHookIn(SB, wf([{ path: 'cowork-inbox/placar/playbook/_saida-02.md', localPath: 'cowork-inbox/placar/playbook/_saida-02.md' }])).code === 2);
  // localPath ≠ path (upload de OUTRO arquivo com o nome do medido)
  check('BITE: localPath diferente do path → exit 2', runHookIn(SB, wf([{ path: rec, localPath: 'cowork-inbox/placar/playbook/_saida-02.md' }])).code === 2);
  // Disco mudou entre plano e upload
  writeFileSync(join(inbox, '_saida-01.md'), 'editado depois do plano\n');
  const mudou = runHookIn(SB, wf([{ path: rec, localPath: rec }]));
  check('BITE: arquivo editado entre finalize e write → exit 2', mudou.code === 2);
  // o disco editado deixa de ser canon: cai na medição de origin/main antes da comparação com o plano
check('BITE: stderr diz o motivo (arquivo deixou de ser canon)', /difere-de-origin-main|mudou-desde-o-plano/.test(mudou.stderr));
  // Conteúdo NÃO mergeado (difere de origin/main)
  const naoMerg = runHookIn(SB, fin([rec]));
  check('BITE: conteúdo local ≠ origin/main → finalize exit 2', naoMerg.code === 2);
  check('BITE: stderr diz difere-de-origin-main', /difere-de-origin-main/.test(naoMerg.stderr));
  g(SB, 'checkout', '-q', '--', '.');
  // Arquivo novo, nunca mergeado
  writeFileSync(join(inbox, '_saida-09.md'), 'recibo que ainda não foi pro main\n');
  check('BITE: arquivo ausente em origin/main → exit 2', runHookIn(SB, fin(['cowork-inbox/placar/playbook/_saida-09.md'])).code === 2);
  // Commitado localmente mas NÃO mergeado (HEAD à frente de origin/main)
  g(SB, 'add', '-A'); g(SB, 'commit', '-q', '-m', 'local');
  check('BITE: commitado no branch mas não em origin/main → exit 2', runHookIn(SB, fin(['cowork-inbox/placar/playbook/_saida-09.md'])).code === 2);
  // localDir fora do espelho (raiz do repo) com path relativo à raiz
  check('BITE: localDir = raiz do repo (fora do espelho) → exit 2', runHookIn(SB, fin([rec], { localDir: SB })).code === 2);
  // Projeto do Design System, mesmo com canon idêntico
  check('BITE: projeto DS com canon idêntico → exit 2', runHookIn(SB, { ...fin([rec]), projectId: DS_ID }).code === 2);
  // Tela (fora de cowork-inbox/) mergeada: NÃO isenta (fora_do_canal = decisão [W])
  writeFileSync(join(ESP, 'clientes-page.jsx'), 'x\n');
  g(SB, 'add', '-A'); g(SB, 'commit', '-q', '-m', 'tela'); g(SB, 'update-ref', 'refs/remotes/origin/main', 'HEAD');
  check('BITE: tela .jsx mergeada fora de cowork-inbox/ → exit 2', runHookIn(SB, fin(['clientes-page.jsx'])).code === 2);
  // Controle positivo final: depois do merge o _saida-09 passa (a regra é o main, não a lista)
  check('BITE boa: _saida-09 depois de mergeado → exit 0', runHookIn(SB, fin(['cowork-inbox/placar/playbook/_saida-09.md'])).code === 0);
  // Sem repo (localDir num dir solto) → não medi → exit 2 (LC-33)
  const solto = mkdtempSync(join(tmpdir(), 'ds-solto-'));
  mkdirSync(join(solto, 'prototipo-ui', 'cowork', 'Wagner', 'cowork-inbox'), { recursive: true });
  writeFileSync(join(solto, 'prototipo-ui', 'cowork', 'Wagner', 'cowork-inbox', 'a.md'), 'a\n');
  check('BITE: localDir fora de repo git → exit 2 (não medi ≠ isento)', runHookIn(solto, fin(['cowork-inbox/a.md'], { localDir: join(solto, 'prototipo-ui', 'cowork', 'Wagner') })).code === 2);
  rmSync(solto, { recursive: true, force: true });
} finally {
  rmSync(SB, { recursive: true, force: true });
}

// ── CONTRATO do texto anunciado (LC-15): toda frase que a mensagem anuncia arma o opt-in ──
const msg = denyMessage('finalize_plan', 'x');
const anunciadas = [...msg.matchAll(/«([^»]+)»/g)].map((m) => m[1]);
check('CONTRATO: a mensagem anuncia ≥1 frase de opt-in', anunciadas.length >= 1);
check('CONTRATO: a mensagem anuncia exatamente OPT_IN_EXEMPLOS', JSON.stringify(anunciadas) === JSON.stringify(OPT_IN_EXEMPLOS));
for (const a of anunciadas) check(`CONTRATO: frase anunciada «${a}» passa no predicado`, isDesignSyncOptInPrompt(a));
check('CONTRATO: a promessa antiga (\'diga "design-sync" explícito\') sumiu', !/diga "design-sync" explícito/.test(msg));
check('CONTRATO: o nome sozinho segue NÃO armando (a mensagem diz isso)', !isDesignSyncOptInPrompt('design-sync') && /SOZINHO não arma/.test(msg));
check('E2E: tela sem opt-in → exit 2 (segue bloqueada)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'DesignSync', tool_input: { method: 'finalize_plan', projectId: P, writes: ['clientes-page.jsx'], deletes: [] } }).code === 2);

console.log('');
if (fails === 0) {
  console.log('[PASS] block-design-sync: leitura livre, escrita gateada, default-deny, opt-in correto, E2E exit codes provados.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s).`);
process.exit(1);

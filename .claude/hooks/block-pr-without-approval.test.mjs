#!/usr/bin/env node
// TESTE DE REGRESSÃO DE GOVERNANÇA — R10 ("aprovação humana antes de publicar").
//
// Pergunta que este teste responde (Wagner 2026-05-28):
//   "A regra continua funcionando mesmo SEM a skill ativa?"
//
// Prova que R10 é enforçada pela MÁQUINA (hook block-pr-without-approval.mjs),
// NÃO pela orientação (skill wagner-protocol-enforce). Se alguém rebaixar/remover
// a skill, este teste AINDA passa → R10 sobrevive. Se o hook quebrar, este teste
// FALHA → você sabe que R10 ficou órfã ANTES de reformular qualquer skill.
//
// Rodar: node .claude/hooks/block-pr-without-approval.test.mjs
// Exit 0 = todos passam. Exit 1 = alguma regressão (caso explicitado).

import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { existsSync, writeFileSync, unlinkSync, mkdirSync, utimesSync } from 'node:fs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const HOOK = join(__dirname, 'block-pr-without-approval.mjs');
const FLAG = join(tmpdir(), 'oimpresso-pr-approval.flag');
// Mesmo path derivado pelo hook (import.meta.url → raiz da worktree). `.claude/run/` é gitignored.
const OVERRIDE_FILE = join(__dirname, '..', '..', '.claude', 'run', 'r10-override.txt');
function clearOverride() {
  if (existsSync(OVERRIDE_FILE)) unlinkSync(OVERRIDE_FILE);
}

function clearFlag() {
  if (existsSync(FLAG)) unlinkSync(FLAG);
}

function runHook(payload, env = {}) {
  const r = spawnSync('node', [HOOK], {
    input: JSON.stringify(payload),
    env: { ...process.env, ...env },
    encoding: 'utf8',
  });
  return { code: r.status, stderr: r.stderr || '' };
}

const PUSH = { hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: 'git push -u origin HEAD' } };
const PR = { hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: 'gh pr create --base main' } };
const STATUS = { hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: 'git status --short' } };
const prompt = (t) => ({ hook_event_name: 'UserPromptSubmit', prompt: t });
// UserPromptSubmit com transcript_path apontando pra um JSONL fake (gate de contexto).
const promptTC = (t, tp) => ({ hook_event_name: 'UserPromptSubmit', prompt: t, transcript_path: tp });

// Escreve um transcript JSONL temporario com o turno do assistente (e opcionalmente
// o turno do usuario depois). Espelha o formato real lido pelo hook.
let _tc = 0;
const _transcripts = [];
function makeTranscript(assistantText, userText) {
  const file = join(tmpdir(), `oimpresso-r10-tc-${process.pid}-${_tc++}.jsonl`);
  const lines = [];
  if (assistantText != null) {
    lines.push(JSON.stringify({ type: 'assistant', message: { role: 'assistant', content: [{ type: 'text', text: assistantText }] } }));
  }
  if (userText != null) {
    lines.push(JSON.stringify({ type: 'user', message: { role: 'user', content: [{ type: 'text', text: userText }] } }));
  }
  writeFileSync(file, lines.join('\n') + '\n', 'utf8');
  _transcripts.push(file);
  return file;
}

let fails = 0;
let total = 0;
function check(name, cond) {
  total++;
  if (cond) {
    console.log(`[OK] ${name}`);
  } else {
    console.log(`[FAIL] ${name}`);
    fails++;
  }
}

// 1. push SEM aprovação → BLOQUEIA (exit 2). (Este é o caso que falhou na sessão real.)
clearFlag();
check('push sem aprovação → BLOCK', runHook(PUSH).code === 2);

// 2. Wagner aprova ("pode fazer o PR") → flag criada (exit 0).
clearFlag();
const approve = runHook(prompt('pode fazer o PR'));
check('aprovação grava flag (exit 0)', approve.code === 0 && existsSync(FLAG));

// 3. Com flag → PR PASSA (exit 0) e CONSOME a flag.
check('PR com aprovação → ALLOW', runHook(PR).code === 0);
check('flag consumida após publicar', !existsSync(FLAG));

// 4. Segundo push (flag já consumida) → BLOQUEIA. (1 aprovação = 1 publicação.)
check('2º push sem nova aprovação → BLOCK', runHook(PUSH).code === 2);

// 5. Negação ("não pode pushar") → NÃO grava flag → push BLOQUEIA.
clearFlag();
runHook(prompt('não pode pushar ainda'));
check('negação não cria flag → BLOCK', !existsSync(FLAG) && runHook(PUSH).code === 2);

// 6. Comando inócuo (git status) → ALLOW (não é publicação).
clearFlag();
check('git status → ALLOW (não-publicação)', runHook(STATUS).code === 0);

// 7. Flag EXPIRADA (timestamp velho) → BLOQUEIA.
clearFlag();
writeFileSync(FLAG, new Date(Date.now() - 30 * 60000).toISOString(), 'utf8'); // 30min atrás
check('flag expirada → BLOCK', runHook(PUSH).code === 2);

// 8. Escape valve env → ALLOW.
clearFlag();
check('override env → ALLOW', runHook(PUSH, { OIMPRESSO_PR_APPROVAL_OVERRIDE: '1' }).code === 0);

// 9. "merge" do Wagner aprova → gh pr merge passa.
clearFlag();
runHook(prompt('pode mergear'));
check('"merge" aprova → gh pr merge ALLOW', runHook({ hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: 'gh pr merge 1908 --admin' } }).code === 0);

// 10. FALSO-POSITIVO corrigido: 'merge' incidental em conversa NAO aprova publicacao.
clearFlag();
runHook(prompt('qual a estrategia de merge antes?'));
check('"estrategia de merge" NAO aprova (falso-positivo) -> BLOCK', !existsSync(FLAG) && runHook(PUSH).code === 2);

// 11. Gap PowerShell fechado: push via tool PowerShell sem aprovacao -> BLOCK.
clearFlag();
check('PowerShell push sem aprovacao -> BLOCK', runHook({ hook_event_name: 'PreToolUse', tool_name: 'PowerShell', tool_input: { command: 'git push' } }).code === 2);

// 12. PowerShell push COM aprovacao -> ALLOW.
clearFlag();
runHook(prompt('pode pushar'));
check('PowerShell push com aprovacao -> ALLOW', runHook({ hook_event_name: 'PreToolUse', tool_name: 'PowerShell', tool_input: { command: 'git push -u origin HEAD' } }).code === 0);

// 13. publishPatterns ancorado: 'git push' embebido em comando de busca NAO bloqueia.
clearFlag();
check('busca com "git push" embebido (rg) -> ALLOW (nao e publicacao)', runHook({ hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command: "rg 'git push' .claude/hooks" } }).code === 0);

// --- Bypasses fechados no review adversarial 2026-06-20 (sem aprovacao -> BLOCK) ---
const bash = (command) => ({ hook_event_name: 'PreToolUse', tool_name: 'Bash', tool_input: { command } });

// 14. gh api -X POST .../pulls (cria PR via API) -> BLOCK.
clearFlag();
check('gh api -X POST /pulls -> BLOCK', runHook(bash('gh api -X POST repos/o/r/pulls -f title=x -f head=b -f base=main')).code === 2);

// 15. gh api --method PUT .../pulls/N/merge (merge via API) -> BLOCK.
clearFlag();
check('gh api PUT /pulls/N/merge -> BLOCK', runHook(bash('gh api --method PUT repos/o/r/pulls/5/merge')).code === 2);

// 16. gh api .../pulls -f (POST implicito por campo) -> BLOCK.
clearFlag();
check('gh api /pulls -f (POST implicito) -> BLOCK', runHook(bash('gh api repos/o/r/pulls -f title=x')).code === 2);

// 17. git -c k=v push (flag entre git e push) -> BLOCK.
clearFlag();
check('git -c k=v push -> BLOCK', runHook(bash('git -c http.sslVerify=false push origin HEAD')).code === 2);

// 18. ENV="a b" git push (prefixo de env com aspas) -> BLOCK.
clearFlag();
check('ENV="a b" git push -> BLOCK', runHook(bash('GIT_SSH_COMMAND="ssh -i k" git push')).code === 2);

// 19. FALSO-POSITIVO: gh api GET em /pulls (listar PRs, read) -> ALLOW.
clearFlag();
check('gh api /pulls (GET read) -> ALLOW', runHook(bash('gh api repos/o/r/pulls')).code === 0);

// 20. FALSO-POSITIVO: gh api comentario em PR (/pulls/N/comments) -> ALLOW.
clearFlag();
check('gh api /pulls/N/comments (comentario) -> ALLOW', runHook(bash('gh api repos/o/r/pulls/5/comments -f body=oi')).code === 0);

// 21. FALSO-POSITIVO: git -c k=v log (nao e push) -> ALLOW.
clearFlag();
check('git -c k=v log (nao-push) -> ALLOW', runHook(bash('git -c core.pager=cat log --oneline')).code === 0);

// --- Afirmativos CURTOS sob gate de contexto (opcao a, incidente PR #3358) ---

// 22. "ok" APOS o assistente perguntar "...abro o PR?" -> APROVA -> push ALLOW.
clearFlag();
{
  const tp = makeTranscript('Rodei os testes, tudo verde. Commito + abro o PR?', null);
  runHook(promptTC('ok', tp));
  check('"ok" apos "abro o PR?" -> APROVA -> push ALLOW', existsSync(FLAG) && runHook(PUSH).code === 0);
}

// 23. "aprovo" apos pergunta de merge -> APROVA (flag criada).
clearFlag();
{
  const tp = makeTranscript('Posso mergear o PR #10 agora?', null);
  runHook(promptTC('aprovo', tp));
  check('"aprovo" apos "posso mergear?" -> APROVA', existsSync(FLAG));
}

// 24. CONSERVADORIA: "ok" quando o assistente perguntou OUTRA coisa -> NAO aprova -> BLOCK.
clearFlag();
{
  const tp = makeTranscript('Qual cor voce prefere pro botao primario, azul ou verde?', null);
  runHook(promptTC('ok', tp));
  check('"ok" incidental (pergunta nao-publish) -> NAO aprova -> BLOCK', !existsSync(FLAG) && runHook(PUSH).code === 2);
}

// 25. CONSERVADORIA: "ok" sem transcript_path -> NAO aprova (falha-fecha) -> BLOCK.
clearFlag();
runHook(promptTC('ok', undefined));
check('"ok" sem transcript -> NAO aprova (fail-closed) -> BLOCK', !existsSync(FLAG) && runHook(PUSH).code === 2);

// 26. ROBUSTEZ DE TIMING: o "ok" do usuario ja gravado como ultima linha do transcript
//     -> hook ainda le o turno do assistente anterior -> APROVA.
clearFlag();
{
  const tp = makeTranscript('Tudo pronto. Quer que eu abra o PR?', 'ok');
  runHook(promptTC('ok', tp));
  check('transcript com user "ok" no fim -> le assistant anterior -> APROVA', existsSync(FLAG));
}

// 27. CONSERVADORIA: "isso" quando o assistente so MENCIONOU um PR (sem oferecer publicar)
//     -> NAO aprova (menção narrativa nao e oferta) -> BLOCK.
clearFlag();
{
  const tp = makeTranscript('Esse PR #99 ja foi mergeado semana passada. Seguimos no fix entao?', null);
  runHook(promptTC('isso', tp));
  check('"isso" apos PR mencionado sem oferta -> NAO aprova -> BLOCK', !existsSync(FLAG) && runHook(PUSH).code === 2);
}

// 28. CONSERVADORIA: negacao curta apos pergunta de publish -> NAO aprova (deny precede).
clearFlag();
{
  const tp = makeTranscript('Commito e abro o PR?', null);
  runHook(promptTC('nao, espera', tp));
  check('"nao, espera" apos pergunta de publish -> NAO aprova -> BLOCK', !existsSync(FLAG) && runHook(PUSH).code === 2);
}

// --- LACUNA 1 (2026-06-24): "merge"/"ok merge" como ORDEM curta sob gate de contexto ---

// 29. "ok merge" APOS o assistente perguntar "...abro o PR?" -> APROVA (caso real Wagner).
clearFlag();
{
  const tp = makeTranscript('Tudo verde. Posso abrir o PR?', null);
  runHook(promptTC('ok merge', tp));
  check('"ok merge" apos pergunta de publish -> APROVA', existsSync(FLAG));
}

// 30. CONSERVADORIA: "ok merge" incidental (pergunta NAO-publish) -> NAO aprova -> BLOCK.
clearFlag();
{
  const tp = makeTranscript('Qual branch voce quer usar de base?', null);
  runHook(promptTC('ok merge', tp));
  check('"ok merge" incidental (sem pergunta de publish) -> NAO aprova -> BLOCK', !existsSync(FLAG) && runHook(PUSH).code === 2);
}

// 31. "merge" curto apos pergunta de merge -> APROVA -> gh pr merge ALLOW.
clearFlag();
{
  const tp = makeTranscript('CI verde. Posso mergear o PR #12 agora?', null);
  runHook(promptTC('merge', tp));
  check('"merge" curto apos pergunta de merge -> APROVA -> gh pr merge ALLOW', existsSync(FLAG) && runHook(bash('gh pr merge 12 --admin')).code === 0);
}

// 32. FALSO-POSITIVO: 'merge' so casa afirmativo curto se for o texto INTEIRO; "merge"
//     embebido numa frase longa NAO aprova (isShortAffirmative exige match exato).
clearFlag();
{
  const tp = makeTranscript('Posso abrir o PR?', null);
  runHook(promptTC('da um merge conflict ali, resolve antes', tp));
  check('"...merge conflict..." (frase longa) -> NAO aprova -> BLOCK', !existsSync(FLAG) && runHook(PUSH).code === 2);
}

// --- LACUNA 2 (2026-06-24): override ACIONAVEL via arquivo-marcador (.claude/run/r10-override.txt) ---

// 33. override file com razao + recente -> ALLOW + consome (1 override = 1 publicacao).
clearFlag();
clearOverride();
mkdirSync(dirname(OVERRIDE_FILE), { recursive: true });
writeFileSync(OVERRIDE_FILE, 'Wagner aprovou "ok merge" no chat; deteccao por keyword falhou', 'utf8');
check('override file (razao+recente) -> ALLOW', runHook(PUSH).code === 0);
check('override file consumido apos usar', !existsSync(OVERRIDE_FILE));

// 34. override file VAZIO (sem razao) -> BLOCK (exige justificativa explicita).
clearFlag();
clearOverride();
writeFileSync(OVERRIDE_FILE, '   \n', 'utf8');
check('override file vazio (sem razao) -> BLOCK', runHook(PUSH).code === 2);
clearOverride();

// 35. override file EXPIRADO (mtime velho) -> BLOCK.
clearFlag();
clearOverride();
writeFileSync(OVERRIDE_FILE, 'razao qualquer', 'utf8');
utimesSync(OVERRIDE_FILE, new Date(), new Date(Date.now() - 30 * 60000));
check('override file expirado (mtime velho) -> BLOCK', runHook(PUSH).code === 2);
clearOverride();

clearFlag();
clearOverride();
for (const f of _transcripts) {
  try {
    if (existsSync(f)) unlinkSync(f);
  } catch {
    /* silent */
  }
}
console.log('');
if (fails === 0) {
  console.log(`[PASS] R10 enforçada pela MÁQUINA — sobrevive sem a skill. (${total}/${total})`);
  process.exit(0);
} else {
  console.log(`[FAIL] ${fails} caso(s) — R10 NÃO está garantida pela máquina. NÃO rebaixar a skill.`);
  process.exit(1);
}

#!/usr/bin/env node
// block-design-sync-without-optin.mjs — claude.ai/design NÃO é fonte de design canônica.
// Gateia a ESCRITA da tool nativa DesignSync (block determinístico por tool_name + method).
//
// REGISTRADO em .claude/settings.json — em UserPromptSubmit (grava flag de opt-in) +
// PreToolUse com matcher "DesignSync". Criar o arquivo NÃO ativa nada; o REGISTRO é o que
// ativa — scripts/governance/settings-design-sync-registration.test.mjs guarda contra
// des-registro (mesmo padrão de block-figma-without-optin / block-pr-without-approval).
//
// Documento-mãe: ADR 0315 (/design-sync vs Cowork+charter) — fecha o Gap 1 da ADR 0299
// (a classe NÃO-fonte não estava 100% fechada; só o Figma era gateado). Fonte de design
// canônica = protótipo Cowork (prototipo-ui/) + Design System em git (SSOT, ADR 0239) +
// charter da tela. claude.ai/design é "MCP/integração de design novo" → NÃO-fonte (ADR 0299 §1).
//
// PROBLEMA: a integração oficial /design-sync sincroniza um Design System hospedado no
// claude.ai/design. A direção write (write_files/delete_files/create_project) empurra
// componentes locais PRA FORA do perímetro git canônico, sem PR/CI/gate — publicação externa
// (publication-policy + R10). E cria um SEGUNDO armazém de DS divergindo do git (colide 0239).
//
// CONFORMIDADE COM ADR 0224 (block vs advisory): bloqueio legítimo = determinístico. Aqui é
// por `tool_name` exato (DesignSync) + `method` (string do tool_input) — NÃO por regex
// semântica de prompt. A única parte semântica é a detecção de opt-in no prompt do Wagner,
// que apenas CONCEDE (direção fail-safe). Logo NÃO rebaixa o critério do 0224.
//
// POLÍTICA (Eixo B do ADR 0315):
//   - Métodos de LEITURA (inspeção, sem publicar) → SEMPRE permitidos (sem opt-in).
//       list_projects · get_project · list_files · get_file · report_validate
//   - Qualquer OUTRO método (escrita/mutação) → exige opt-in explícito. DEFAULT-DENY:
//     método desconhecido/futuro também é gateado (fail-closed; não enumera só o de hoje).
//       finalize_plan · write_files · delete_files · register_assets · unregister_assets · create_project · <futuros>
//
// OPT-IN (concede; fail-safe): prompt do Wagner com o comando `/design-sync`, OU o nome da
//   feature ("design-sync" / "design sync" / "claude.ai/design") JUNTO de um verbo de publicar
//   (sobe/publica/sincroniza/envia/manda/push) — sem pergunta nem negação → grava flag TTL 15min.
//   O nome sozinho NÃO arma (furo #2). Ou env OIMPRESSO_DESIGN_SYNC_OK=1, ou .design-sync-allow.
//   A mensagem de bloqueio anuncia EXATAMENTE isto: OPT_IN_EXEMPLOS, testado contra o predicado.
//
// HONESTIDADE (limitações — endurecidas pelo red-team 2026-06-30, ver ADR 0315 §Furos):
//   - opt-in por prompt exige INTENÇÃO explícita (verbo de publicar + nome da feature, ou
//     /design-sync), NÃO mera menção: discutir/perguntar sobre a feature NÃO arma escrita
//     (furo #2 fechado — antes "como funciona design sync?" armava 15min de escrita).
//   - flag é POR-PROJETO (keyed no cwd), não machine-wide (furo #3 fechado).
//   - ATIVAÇÃO depende do harness rotear PreToolUse pra tool NATIVA DesignSync. Hook editado
//     no meio da sessão NÃO faz hot-reload → o gate só vale a partir da PRÓXIMA sessão. O E2E
//     prova a LÓGICA, não a entrega do payload pelo harness (furo #1 — exige baseline com
//     sessão fresca: rodar DesignSync.finalize_plan sem opt-in e ver o [BLOCKED]).
//   - leitura é livre — e é o vetor de injeção (get_file traz conteúdo de outros membros).
//     Tratar como DADO, nunca instrução (furo #5, inerente ao protocolo).
//   - NÃO fecha a classe inteira (Notion/file-MCP/screenshot seguem advisory).
//
// Exit: 0 = continua | 2 = bloqueia (stderr vira razão pro Claude)

import { stdin } from 'node:process';
import { tmpdir } from 'node:os';
import { join, resolve, relative, sep } from 'node:path';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { existsSync, writeFileSync, readFileSync } from 'node:fs';

const TTL_MIN = 15;
// Flag POR-PROJETO (keyed no cwd) — fecha o vazamento machine-wide (furo #3). UserPromptSubmit
// (grava) e PreToolUse (lê) rodam no MESMO cwd da sessão → a chave bate.
function flagPath(root = process.cwd()) {
  const h = createHash('sha1').update(String(root)).digest('hex').slice(0, 12);
  return join(tmpdir(), `oimpresso-design-sync-allow-${h}.flag`);
}

// ── Métodos de LEITURA (inspeção segura, sem publicar) ────────────────────────
// Tudo que NÃO está aqui é tratado como escrita/mutação → gateado (default-deny).
export const READ_METHODS = new Set([
  'list_projects',
  'get_project',
  'list_files',
  'get_file',
  'report_validate',
]);

// ── Classificação (lógica pura, importada pelo test) ──────────────────────────
// Retorna { isDesignSync, method, isWrite, reason }
export function classifyDesignSync(toolName, toolInput = {}) {
  if (toolName !== 'DesignSync') {
    return { isDesignSync: false, isWrite: false, reason: 'nao-design-sync' };
  }
  const method = typeof toolInput?.method === 'string' ? toolInput.method : '';
  // default-deny: método de leitura conhecido → não-escrita; QUALQUER outra coisa
  // (escrita conhecida, método novo/futuro, method ausente) → escrita/gateado.
  const isRead = READ_METHODS.has(method);
  const isWrite = !isRead;
  let reason;
  if (isRead) reason = 'leitura-permitida';
  else if (!method) reason = 'method-ausente-default-deny';
  else reason = `escrita:${method}`;
  return { isDesignSync: true, method, isWrite, reason };
}

// ── RETORNO do Code ao Cowork: a única escrita que dispensa opt-in ─────────────
// [W] 2026-09-24: "quando for gerar um retorno já use o upload designsync". O sentido Code ->
// Cowork do ciclo (recibo `_saida`, errata de playbook, restauração em `cowork-inbox/`) não
// existia como passo, e cada mudança do Code no espelho era desfeita ou derrubava o gate
// "espelho — mexeu depois de verificar" no retorno seguinte (#7843, #7866, e o import (35), que
// ia podar 12 arquivos do #7847). O retorno agora sobe pelo DesignSync, mas SÓ neste canal:
//   · projeto de TELAS (não o Design System — ADR 0315 segue valendo pra ele);
//   · todo path sob `cowork-inbox/` (o canal de PEDIDO/RECIBO; telas `*.jsx`/CSS não entram);
//   · path literal, sem curinga e sem `..`; nenhuma deleção;
//   · no write_files, conteúdo por `localPath` (sai do disco — ADR 0374, nunca transcrito).
// Fora disso, tudo continua exigindo o opt-in explícito abaixo. Quem diz O QUE subir é o
// `scripts/design-sync/pendentes-cowork.mjs --plano`.
//
// AUTORIZAÇÃO PERMANENTE = SÓ CANON JÁ MERGEADO (ADR 0412, emenda à 0315 — [W] 2026-09-24:
// "eu imagino que isso já deveria estar sempre gravado e autorizado"). O formato acima
// (isRetornoCoworkInbox) é necessário e NÃO basta: o PROTOCOL §10.6 só deixa subir o que "já
// está aceito no main" (subir ≠ decidir). Então, além do formato, o hook MEDE o conteúdo:
//   · finalize_plan: `localDir` tem de ser o espelho `prototipo-ui/cowork/<dono>` de um repo git,
//     e cada path do plano tem de existir lá com blob IDÊNTICO ao de `origin/main` (git
//     hash-object × git ls-tree — sem shell e SEM REDE: o hook lê o ref LOCAL `origin/main`).
//     ⚠️ Ref velho corta pros dois lados: arquivo mergeado depois do último fetch cai no opt-in
//     (seguro), MAS um arquivo local igual a uma versão ANTERIOR que ainda está no ref velho
//     passa — é canon que já foi mergeado, não conteúdo não-revisado, porém pode não ser o
//     último. Rode `git fetch origin main` antes do --plano. Passou → grava o plano medido
//     (localDir + blob por path) num registro por-projeto com TTL.
//   · write_files: cada arquivo tem de estar no plano medido, com `localPath === path` (o mesmo
//     arquivo que foi medido) e o blob ainda igual ao medido — editar o disco entre o plano e o
//     upload derruba a isenção.
// Qualquer falha de medição (não é repo, git ausente, ref ausente) = NÃO isenta → opt-in. Nunca
// o contrário (LC-33: não-medi não vira veredito).
export const RETORNO_PROJECT_ID = '019dcfd3-6ef2-7ee6-8512-b1b0e5544e58';
const ESPELHO_RE = /^prototipo-ui\/cowork\/[^/]+$/;

function planoPath(root = process.cwd()) {
  const h = createHash('sha1').update(String(root)).digest('hex').slice(0, 12);
  return join(tmpdir(), `oimpresso-design-sync-retorno-${h}.json`);
}

function git(args, cwd) {
  try {
    return execFileSync('git', args, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch {
    return null; // não medi → quem chama trata como NÃO isento
  }
}

/** Blob local (com os filtros do git, ex. eol) e blob em origin/main de UM arquivo do espelho. */
export function medirCanon(localDir, relPath) {
  const dir = resolve(String(localDir || ''));
  if (!existsSync(dir)) return { ok: false, motivo: 'localDir-inexistente' };
  const top = git(['rev-parse', '--show-toplevel'], dir);
  if (!top) return { ok: false, motivo: 'nao-e-repo-git' };
  const espelho = relative(top, dir).split(sep).join('/');
  if (!ESPELHO_RE.test(espelho)) return { ok: false, motivo: `localDir-fora-do-espelho:${espelho}` };
  const repoPath = `${espelho}/${relPath}`;
  const abs = join(dir, relPath);
  if (!existsSync(abs)) return { ok: false, motivo: `arquivo-local-ausente:${relPath}` };
  const local = git(['hash-object', '--path', repoPath, abs], top);
  const linha = git(['ls-tree', 'origin/main', '--', repoPath], top);
  if (!local) return { ok: false, motivo: `hash-local-falhou:${relPath}` };
  if (linha === null) return { ok: false, motivo: 'origin-main-ilegivel' };
  const main = (linha.match(/^\d+ blob ([0-9a-f]{40,64})\t/) || [])[1];
  if (!main) return { ok: false, motivo: `ausente-em-origin-main:${relPath}` };
  if (main !== local) return { ok: false, motivo: `difere-de-origin-main:${relPath}` };
  return { ok: true, blob: local };
}

/**
 * Decide se a escrita é o retorno de canon mergeado (isenta de opt-in).
 * finalize_plan grava o plano medido; write_files confere contra ele.
 * Retorna { isento, motivo }.
 */
export function avaliarRetornoCanon(toolInput = {}, { root = process.cwd(), now = Date.now() } = {}) {
  const i = toolInput || {};
  if (!isRetornoCoworkInbox(i)) return { isento: false, motivo: 'fora-do-formato-do-retorno' };
  if (i.method === 'finalize_plan') {
    const blobs = {};
    for (const p of i.writes) {
      const m = medirCanon(i.localDir, p);
      if (!m.ok) return { isento: false, motivo: m.motivo };
      blobs[p] = m.blob;
    }
    try {
      writeFileSync(planoPath(root), JSON.stringify({ ts: new Date(now).toISOString(), projectId: i.projectId, localDir: resolve(i.localDir), blobs }), 'utf8');
    } catch {
      return { isento: false, motivo: 'registro-do-plano-falhou' };
    }
    return { isento: true, motivo: 'canon-mergeado' };
  }
  // write_files
  let plano;
  try {
    plano = JSON.parse(readFileSync(planoPath(root), 'utf8'));
  } catch {
    return { isento: false, motivo: 'sem-plano-medido' };
  }
  const idadeMin = (now - new Date(plano.ts).getTime()) / 60000;
  if (!(idadeMin >= 0 && idadeMin < TTL_MIN)) return { isento: false, motivo: 'plano-medido-expirado' };
  if (plano.projectId !== i.projectId) return { isento: false, motivo: 'projeto-difere-do-plano' };
  for (const f of i.files) {
    if (f.localPath !== f.path) return { isento: false, motivo: `localPath-difere-do-path:${f.path}` };
    if (!plano.blobs?.[f.path]) return { isento: false, motivo: `fora-do-plano-medido:${f.path}` };
    const m = medirCanon(plano.localDir, f.path);
    if (!m.ok) return { isento: false, motivo: m.motivo };
    if (m.blob !== plano.blobs[f.path]) return { isento: false, motivo: `mudou-desde-o-plano:${f.path}` };
  }
  return { isento: true, motivo: 'canon-mergeado' };
}
const pathDoRetorno = (p) => typeof p === 'string' && p.startsWith('cowork-inbox/')
  && !p.includes('*') && !p.split('/').includes('..') && !p.includes('\\');

export function isRetornoCoworkInbox(toolInput = {}) {
  const i = toolInput || {};
  if (i.projectId !== RETORNO_PROJECT_ID) return false;
  if (i.method === 'finalize_plan') {
    const writes = Array.isArray(i.writes) ? i.writes : [];
    const deletes = Array.isArray(i.deletes) ? i.deletes : [];
    return writes.length > 0 && deletes.length === 0 && writes.every(pathDoRetorno);
  }
  if (i.method === 'write_files') {
    const files = Array.isArray(i.files) ? i.files : [];
    return files.length > 0 && files.every((f) => f && pathDoRetorno(f.path)
      && typeof f.localPath === 'string' && f.localPath.length > 0 && f.data === undefined);
  }
  return false;
}

// ── Opt-in (exige INTENÇÃO de publicar, não menção — furo #2 fechado) ─────────
// Discutir/perguntar sobre a feature NÃO arma escrita. Arma só: (a) o comando /design-sync,
// ou (b) nomear a feature (design-sync / claude.ai/design) JUNTO de um verbo de publicar.
const slashInvoke = /(?:^|\s)\/design-sync\b/i;                     // comando explícito do skill
const designTarget = /\bdesign[-\s]?sync\b|claude\.ai\/design\b/i;  // nome da feature
const syncVerb = /\b(sincroniz\w*|sobe|subir|publica\w*|push|envia\w*|manda\w*|salva\w*\s+na\s+nuvem)\b/i;
// pergunta/explicação NUNCA arma (mata "como funciona design sync?", "o que é design-sync?").
const interrogative = /\b(como|o que|que[ée]?|qual|quais|por\s?que|porque|explica\w*|entender|funciona\w*|d[uú]vida)\b/i;
// negação CANCELA — "não/nunca/jamais ... design-sync".
const optInDeny = /\b(n[aã]o|nunca|jamais)\s+(?:\S+\s+){0,4}(?:design[-\s]?sync|claude\.ai\/design)/i;

export function isDesignSyncOptInPrompt(text) {
  if (!text) return false;
  if (optInDeny.test(text)) return false;
  if (interrogative.test(text)) return false; // perguntar/explicar não destrava publicar
  if (slashInvoke.test(text)) return true; // rodar o skill = intenção
  if (designTarget.test(text) && syncVerb.test(text)) return true; // nomear feature + verbo de publicar
  return false;
}

// ── Opt-in válido? (flag TTL por-projeto | env | arquivo) ─────────────────────
export function hasValidOptIn(now = Date.now(), root = process.cwd()) {
  if (process.env.OIMPRESSO_DESIGN_SYNC_OK === '1') return true;
  if (existsSync(join(root, '.design-sync-allow'))) return true;
  const FLAG = flagPath(root);
  if (existsSync(FLAG)) {
    try {
      const ts = new Date(readFileSync(FLAG, 'utf8').trim());
      const ageMin = (now - ts.getTime()) / 60000;
      if (ageMin >= 0 && ageMin < TTL_MIN) return true;
    } catch {
      /* flag corrompida → trata como ausente */
    }
  }
  return false;
}

// Frases que a mensagem ANUNCIA como opt-in. O teste de contrato roda cada uma no predicado —
// a saída anunciada tem de ser a implementada (LC-15: antes a mensagem dizia 'diga "design-sync"'
// e "design-sync" sozinho NÃO passava no isDesignSyncOptInPrompt).
export const OPT_IN_EXEMPLOS = ['/design-sync', 'sobe pro design-sync', 'publica no claude.ai/design'];

export function optInInstrucao() {
  return [
    'Se você REALMENTE quer sincronizar de propósito, escreva no chat (sem pergunta nem negação) uma de:',
    ...OPT_IN_EXEMPLOS.map((e) => `  «${e}»`),
    '— o nome "design-sync" SOZINHO não arma (precisa do comando /design-sync ou de um verbo de publicar:',
    'sobe/publica/sincroniza/envia/manda/push). Ou OIMPRESSO_DESIGN_SYNC_OK=1, ou .design-sync-allow na raiz.',
  ];
}

export function denyMessage(method, motivo) {
  return [
    '[block-design-sync-without-optin] [BLOCKED: claude.ai/design não é fonte de design no oimpresso (ADR 0315 / fecha Gap 1 da 0299)]',
    '',
    `Tool: DesignSync (method: ${method || '—'})`,
    '',
    'Esse método ESCREVE/PUBLICA num projeto do claude.ai/design. A fonte de design canônica é o',
    'protótipo Cowork (prototipo-ui/) + o Design System em git (SSOT, ADR 0239) + charter da tela.',
    '',
    'LEITURA é livre (list_projects/get_file/...): inspecionar o que existe lá não precisa opt-in.',
    '',
    'ISENTO sem opt-in (ADR 0412): retorno de canon JÁ MERGEADO ao projeto de TELAS — todo path sob',
    'cowork-inbox/, sem deleção, localDir = prototipo-ui/cowork/<dono>, blob idêntico a origin/main,',
    'write_files por localPath === path. Plano pronto: node scripts/design-sync/pendentes-cowork.mjs --plano',
    `Por que esta escrita NÃO ficou isenta: ${motivo || '—'}`,
    '',
    ...optInInstrucao(),
  ].join('\n');
}

async function readStdin() {
  const chunks = [];
  for await (const c of stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  let raw;
  try {
    raw = await readStdin();
  } catch {
    process.exit(0);
  }
  if (!raw) process.exit(0);

  let p;
  try {
    p = JSON.parse(raw);
  } catch {
    process.exit(0);
  }

  const event = p.hook_event_name;

  // 1. UserPromptSubmit → grava flag de opt-in se o Wagner mencionou design-sync
  if (event === 'UserPromptSubmit') {
    if (isDesignSyncOptInPrompt(p.prompt || '')) {
      try {
        writeFileSync(flagPath(), new Date().toISOString(), 'utf8');
      } catch {
        /* silent */
      }
    }
    process.exit(0);
  }

  // 2. PreToolUse → bloqueia método de ESCRITA do DesignSync sem opt-in
  if (event === 'PreToolUse') {
    const c = classifyDesignSync(p.tool_name || '', p.tool_input || {});
    if (!c.isDesignSync) process.exit(0); // não é DesignSync → segue
    if (!c.isWrite) process.exit(0); // método de leitura → inspeção livre
    const r = avaliarRetornoCanon(p.tool_input || {});
    if (r.isento) process.exit(0); // retorno de canon já mergeado (ADR 0412, ver acima)
    if (hasValidOptIn()) process.exit(0); // Wagner autorizou (flag/env/arquivo)

    process.stderr.write(denyMessage(c.method, r.motivo) + '\n');
    process.exit(2);
  }

  process.exit(0);
}

// Só executa quando rodado como script (permite import puro no test).
const invokedDirectly =
  process.argv[1] && process.argv[1].replace(/\\/g, '/').endsWith('block-design-sync-without-optin.mjs');
if (invokedDirectly) main();

#!/usr/bin/env node
// Selftest HERMÉTICO do brief-fetch-curl.mjs (US-GOV-052 — o crítico: HTTP autenticado).
// fetch é INJETADO (nenhuma rede real). Prova os requisitos duros do adversário 2026-07-20:
//  · REDAÇÃO: o token NUNCA aparece na saída, nem quando o err.message CONTÉM o token.
//  · TIMEOUT: o fetch recebe um AbortSignal e um servidor que pendura é ABORTADO (não trava).
//  · FALL-OPEN: todo caminho de falha vira fallback + a saída sempre existe.
//
// Rodar: node .claude/hooks/brief-fetch-curl.test.mjs   (exit 0 = passa)

import { spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  resolveSettingsPath, readAuthHeader, buildBody, extractBrief,
  fetchBrief, runBrief, fallbackText, successText,
  restoreFromVault, findRepoRoot, vaultPath,
} from './brief-fetch-curl.mjs';

const __dirname = dirname(fileURLToPath(import.meta.url));
const HOOK = join(__dirname, 'brief-fetch-curl.mjs');

// Token FALSO — a asserção de vazamento procura esta substring exata na saída.
const TOKEN = 'Bearer mcp_FAKE_TOKEN_DO_NOT_LEAK_9f8e7d';
const SECRET = 'mcp_FAKE_TOKEN_DO_NOT_LEAK_9f8e7d';
const SETTINGS_OK = JSON.stringify({ mcpServers: { oimpresso: { headers: { Authorization: TOKEN } } } });
const SUCCESS = { jsonrpc: '2.0', id: 1, result: { content: [{ type: 'text', text: 'BRIEF: cycle X · HITL 2' }] } };

let fails = 0;
const check = (name, cond) => { console.log((cond ? '[OK]   ' : '[FAIL] ') + name); if (!cond) fails++; };
const noLeak = (label, s) => check(`SEM VAZAMENTO de token: ${label}`, !String(s).includes(SECRET));

// ── readAuthHeader ──
check('readAuthHeader lê token válido', readAuthHeader(SETTINGS_OK) === TOKEN);
check('readAuthHeader rejeita prefixo errado', readAuthHeader(JSON.stringify({ mcpServers: { oimpresso: { headers: { Authorization: 'Bearer abc' } } } })) === null);
check('readAuthHeader rejeita ausente', readAuthHeader(JSON.stringify({ mcpServers: {} })) === null);
// FIXTURE RUIM — o placeholder LITERAL do .claude/settings.local.json.example.
// Ele satisfaz `startsWith("Bearer mcp_")`, entao passava por "tem token" e o hook
// tentava HTTP autenticado com o placeholder (401 opaco em vez de "token ausente").
check('readAuthHeader rejeita o PLACEHOLDER do .example (forma valida, valor invalido)',
  readAuthHeader(JSON.stringify({ mcpServers: { oimpresso: { headers: { Authorization: 'Bearer mcp_COLE_SEU_TOKEN_AQUI' } } } })) === null);
// CONTROLE POSITIVO — token real nao pode ser confundido com placeholder: o mesmo
// prefixo, sem a marca. Sem este assert, "rejeitar tudo" passaria o teste acima.
// O valor e' hifenizado e auto-rotulado DE PROPOSITO: um literal opaco tipo
// `mcp_<alfanumerico longo>` acionaria o gitleaks (required) por parecer segredo.
const FIXTURE_OK = 'Bearer mcp_fixture-de-teste-nao-e-um-token';
check('CONTROLE POSITIVO: token com forma valida (mesmo prefixo, sem a marca) segue ACEITO',
  readAuthHeader(JSON.stringify({ mcpServers: { oimpresso: { headers: { Authorization: FIXTURE_OK } } } })) === FIXTURE_OK);
check('readAuthHeader rejeita JSON inválido (sem throw)', readAuthHeader('{lixo') === null);

// ── buildBody ──
const body = JSON.parse(buildBody());
check('buildBody: JSON-RPC tools/call brief-fetch', body.method === 'tools/call' && body.params.name === 'brief-fetch');

// ── extractBrief (razões fixas) ──
check('extractBrief sucesso', extractBrief(SUCCESS).ok === true && extractBrief(SUCCESS).text.includes('BRIEF'));
check('extractBrief error JSON-RPC → reason fixa', extractBrief({ error: { message: 'x' } }).reason === 'MCP retornou error JSON-RPC');
check('extractBrief sem content', extractBrief({ result: {} }).ok === false);
check('extractBrief content vazio', extractBrief({ result: { content: [{ type: 'text', text: '   ' }] } }).reason === 'conteúdo vazio');

// ── fetchBrief: captura init (prova signal + Authorization) ──
let captured = null;
const fetchCapture = async (url, init) => { captured = { url, init }; return { json: async () => SUCCESS }; };
const rCap = await fetchBrief({ fetchImpl: fetchCapture, authHeader: TOKEN, timeoutMs: 5000 });
check('fetchBrief sucesso com fetch injetado', rCap.ok === true);
check('fetch recebeu AbortSignal (timeout wired)', captured && captured.init && captured.init.signal instanceof AbortSignal);
check('fetch recebeu Authorization = token', captured && captured.init.headers.Authorization === TOKEN);
check('fetch foi no endpoint MCP correto', captured && captured.url === 'https://mcp.oimpresso.com/api/mcp');

// ── fetchBrief: err.message CONTÉM o token → reason NÃO vaza ──
const fetchLeakyThrow = async () => { const e = new Error('conn a ' + TOKEN + ' falhou'); e.name = 'TypeError'; throw e; };
const rThrow = await fetchBrief({ fetchImpl: fetchLeakyThrow, authHeader: TOKEN, timeoutMs: 5000 });
check('fetchBrief captura throw → ok:false', rThrow.ok === false);
noLeak('reason de erro de rede (err.message tinha token)', rThrow.reason);

// ── TIMEOUT REAL: fetch que pendura respeitando o signal → aborta rápido, não trava ──
// keepAlive ref'd simula o socket que um fetch real teria (o timer do AbortSignal.timeout é
// unref'd; sem I/O ref'd o loop sairia antes do abort disparar). Prova que o abort DE FATO morde.
const fetchHang = (url, init) => new Promise((_, rej) => {
  const keepAlive = setTimeout(() => {}, 5000);
  init.signal.addEventListener('abort', () => {
    clearTimeout(keepAlive);
    const e = new Error('aborted'); e.name = 'AbortError'; rej(e);
  });
});
const t0 = Date.now();
const rHang = await fetchBrief({ fetchImpl: fetchHang, authHeader: TOKEN, timeoutMs: 120 });
const dt = Date.now() - t0;
check('fetchBrief ABORTA servidor que pendura (não trava)', rHang.ok === false && /timeout/.test(rHang.reason));
check('abort respeitou o timeout (~120ms, não pendurou)', dt < 3000);

// ── runBrief (orquestrador) — REDAÇÃO em TODOS os caminhos ──
const cwdSemHandoff = mkdtempSync(join(tmpdir(), 'brief-red-'));
const base = { cwd: cwdSemHandoff, settingsTextOverride: SETTINGS_OK, timeoutMs: 200 };

const outSuccess = await runBrief({ ...base, fetchImpl: fetchCapture });
check('runBrief sucesso monta successText', outSuccess.includes('Daily Brief') && outSuccess.includes('BRIEF: cycle X'));
noLeak('runBrief caminho de SUCESSO', outSuccess);

const outErrRpc = await runBrief({ ...base, fetchImpl: async () => ({ json: async () => ({ error: { message: SECRET } }) }) });
check('runBrief error JSON-RPC → fallback', outErrRpc.includes('FALLBACK ATIVADO'));
noLeak('runBrief error JSON-RPC (message tinha token)', outErrRpc);

const outThrow = await runBrief({ ...base, fetchImpl: fetchLeakyThrow });
check('runBrief throw de rede → fallback', outThrow.includes('FALLBACK ATIVADO'));
noLeak('runBrief throw de rede', outThrow);

const outHang = await runBrief({ ...base, fetchImpl: fetchHang });
check('runBrief timeout → fallback', outHang.includes('FALLBACK') && /timeout/.test(outHang));
noLeak('runBrief timeout', outHang);

const outBadToken = await runBrief({ cwd: cwdSemHandoff, settingsTextOverride: '{json ruim', timeoutMs: 200, fetchImpl: fetchCapture });
check('runBrief settings ilegível → fallback token ausente', outBadToken.includes('FALLBACK') && outBadToken.includes('ausente/inválido'));

const outNoSettings = await runBrief({ cwd: cwdSemHandoff, settingsPath: null, timeoutMs: 200 });
// settingsPath null força resolveSettingsPath(cwd) — o tmp não tem .claude/settings.local.json na árvore (em CI /tmp)
check('runBrief sem settings na árvore → fallback', outNoSettings.includes('FALLBACK'));
rmSync(cwdSemHandoff, { recursive: true, force: true });

// ═══════════════════════════════════════════════════════════════════════════════════════
// AUTO-RECUPERAÇÃO DO COFRE (2026-08-07) — settings.local.json é gitignored, logo sem backup
// no git. Ele sumiu do repo principal e derrubou o MCP nos 33 worktrees de uma vez (herdam
// dali). Tudo INJETADO: nenhum fs real é tocado nestes casos.
// ═══════════════════════════════════════════════════════════════════════════════════════
const VAULT = '/cofre/mcp-settings.local.json';
const ROOT = '/repo';
const DEST = join(ROOT, '.claude', 'settings.local.json').replace(/\\/g, '/');
const mkVaultFs = ({ vaultExiste = true, destExiste = false, conteudo = SETTINGS_OK, escritaThrow = false } = {}) => {
  const escritas = [];
  return {
    escritas,
    existsFn: (p) => { const s = String(p).replace(/\\/g, '/'); if (s === VAULT) return vaultExiste; if (s === DEST) return destExiste; return false; },
    readFn: () => conteudo,
    writeFn: (p, d) => { if (escritaThrow) throw new Error('EACCES'); escritas.push({ p: String(p).replace(/\\/g, '/'), d }); },
    mkdirFn: () => {},
    vaultFile: VAULT, repoRoot: ROOT,
  };
};

// BITE-TEST: cofre válido + destino vazio → RESTAURA na raiz do repo
const fsOk = mkVaultFs();
const recOk = restoreFromVault(fsOk);
check('MORDE: árvore sem settings + cofre válido → restaura', recOk.ok === true);
check('MORDE: escreveu na RAIZ do repo (1 arquivo, não 33 cópias)', fsOk.escritas.length === 1 && fsOk.escritas[0].p === DEST);
check('MORDE: conteúdo restaurado é o do cofre', fsOk.escritas[0].d === SETTINGS_OK);

// CONTROLE NEGATIVO 1: destino JÁ existe → nunca sobrescreve
const fsDest = mkVaultFs({ destExiste: true });
check('NEG: destino já existe → NÃO sobrescreve', restoreFromVault(fsDest).reason === 'destino-ja-existe' && fsDest.escritas.length === 0);

// CONTROLE NEGATIVO 2: sem cofre → não escreve nada
const fsSem = mkVaultFs({ vaultExiste: false });
check('NEG: cofre ausente → não escreve', restoreFromVault(fsSem).reason === 'cofre-ausente' && fsSem.escritas.length === 0);

// CONTROLE NEGATIVO 3: cofre com placeholder (sem token válido) → não escreve
const fsPh = mkVaultFs({ conteudo: JSON.stringify({ mcpServers: { oimpresso: { headers: { Authorization: 'Bearer mcp_COLE_SEU_TOKEN_AQUI' } } } }) });
const recPh = restoreFromVault(fsPh);
check('NEG: cofre com PLACEHOLDER do .example → não escreve', recPh.reason === 'cofre-sem-token' && fsPh.escritas.length === 0);

// CONTROLE NEGATIVO 4: fora de repo git → nunca escreve em diretório aleatório
const fsFora = { ...mkVaultFs(), repoRoot: null };
check('NEG: fora de repo git → não escreve', restoreFromVault(fsFora).reason === 'fora-de-repo');

// CONTROLE NEGATIVO 5: escrita falha → fail-open com motivo fixo (não crasha)
check('NEG: escrita falha → motivo fixo, sem throw', restoreFromVault(mkVaultFs({ escritaThrow: true })).reason === 'escrita-falhou');

// REDAÇÃO: motivo do restore nunca carrega token
const fsLeak = mkVaultFs({ vaultExiste: false });
noLeak('reason do restore', JSON.stringify(restoreFromVault(fsLeak)));

// findRepoRoot: runner injetado (de dentro de worktree, --git-common-dir → .git do PRINCIPAL).
// CROSS-PLATAFORMA: nada de 'D:/...' literal — é absoluto no Windows e RELATIVO no POSIX
// (o CI roda Linux; o time MCP usa Mac/Linux — é o motivo de o hook ser .mjs, US-GOV-052).
const rootAbs = findRepoRoot('/qualquer', () => ({ status: 0, stdout: join('/repo', '.git') + '\n' }));
check('findRepoRoot: --git-common-dir absoluto → raiz do repo (tira o /.git)',
  typeof rootAbs === 'string' && /[\\/]repo$/.test(rootAbs) && !/\.git$/.test(rootAbs));
// caso REAL mais comum: na raiz do repo o git devolve ".git" RELATIVO → resolve contra o cwd
const rootRel = findRepoRoot(process.cwd(), () => ({ status: 0, stdout: '.git\n' }));
check('findRepoRoot: --git-common-dir relativo (".git") → resolve contra o cwd',
  rootRel === process.cwd());
check('findRepoRoot: git falha → null (guarda anti-escrita)', findRepoRoot('/qualquer', () => ({ status: 128, stdout: '' })) === null);
check('findRepoRoot: git ausente (throw) → null', findRepoRoot('/qualquer', () => { throw new Error('ENOENT'); }) === null);
check('vaultPath fica FORA da árvore do repo (~/.claude/oimpresso-local)', vaultPath('/home/x').replace(/\\/g, '/') === '/home/x/.claude/oimpresso-local/mcp-settings.local.json');

// E2E do orquestrador: sem settings na árvore + cofre válido → restaura e SEGUE pro brief
const tmpRec = mkdtempSync(join(tmpdir(), 'brief-rec-'));
const outRec = await runBrief({ cwd: tmpRec, settingsPath: null, timeoutMs: 200, fetchImpl: fetchCapture, restoreFn: () => ({ ok: true, dest: DEST, texto: SETTINGS_OK }) });
check('runBrief: restaurou → NÃO cai em fallback, entrega o brief', !outRec.includes('FALLBACK') && outRec.includes('BRIEF: cycle X'));
check('runBrief: avisa que restaurou (mensagem honesta)', outRec.includes('restaurado do cofre local'));
noLeak('runBrief caminho de RESTAURAÇÃO', outRec);

// E sem cofre → fallback COM a dica acionável (a mensagem promete só o que existe)
const outSemCofre = await runBrief({ cwd: tmpRec, settingsPath: null, timeoutMs: 200, restoreFn: () => ({ ok: false, reason: 'cofre-ausente' }) });
check('runBrief: sem cofre → fallback com CONSERTO acionável', outSemCofre.includes('FALLBACK') && outSemCofre.includes('CONSERTO') && outSemCofre.includes('settings.local.json.example'));
rmSync(tmpRec, { recursive: true, force: true });

// ── fallbackText/successText nunca vazam por construção ──
noLeak('fallbackText', fallbackText('motivo qualquer', 'tail do handoff'));
noLeak('successText', successText('conteúdo do brief'));

// ── resolveSettingsPath sobe a árvore sem crashar ──
check('resolveSettingsPath devolve string ou null (sobe a árvore sem crashar)',
  typeof resolveSettingsPath(process.cwd()) === 'string' || resolveSettingsPath(process.cwd()) === null);

// ── E2E: subprocess num cwd sem settings → fallback + exit 0 (não trava, não vaza) ──
const tmpE2E = mkdtempSync(join(tmpdir(), 'brief-e2e-'));
const rE2E = spawnSync(process.execPath, [HOOK], { cwd: tmpE2E, encoding: 'utf8', timeout: 15000 });
check('E2E: exit 0 (fail-open)', rE2E.status === 0);
check('E2E: imprime fallback (sem token/settings na árvore do tmp)', /FALLBACK ATIVADO/.test(rE2E.stdout));
noLeak('E2E stdout', rE2E.stdout);
noLeak('E2E stderr', rE2E.stderr || '');
rmSync(tmpE2E, { recursive: true, force: true });

console.log('');
if (fails === 0) {
  console.log('[PASS] brief-fetch-curl: fetch nativo + timeout aborta + ZERO vazamento de token + fail-open total.');
  process.exit(0);
}
console.log(`[FAIL] ${fails} caso(s) — o porte do brief-fetch regrediu (RISCO: token/hang/silent-fail).`);
process.exit(1);

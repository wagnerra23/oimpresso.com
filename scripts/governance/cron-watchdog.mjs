#!/usr/bin/env node
// @ts-check
/**
 * cron-watchdog.mjs — G6: heartbeat dos crons de governança (generaliza o auto-canário
 * single-cron do memory-health, ADR 0317 §2 — "quem vigia o vigia").
 *
 * O GitHub DESABILITA workflow agendado após 60d sem atividade no repo — EM SILÊNCIO.
 * Sem vigia, o schedule morre, as sentinelas (memory-health, drift, ragas…) param de
 * rodar e o brief fica verde = regressão disfarçada de saúde. Este roda em PR (sempre
 * ativo) e checa a idade da última run AGENDADA de CADA workflow com `schedule:`,
 * descobertos DINAMICAMENTE (não hardcode → cobre cron novo/removido automaticamente,
 * sem lista que drifta). Real-time DE PROPÓSITO (liveness ≠ conteúdo reproduzível, ao
 * contrário dos Checks de memory-health).
 *
 * 🔴 cron morto (última run agendada > limite por cadência) · 🟡 bootstrap (perguntei e
 * não houve run) · ⛔ NÃO MEDIDO (não consegui perguntar — `gh` ausente/sem auth/sem
 * `actions:read`/API fora). Os dois últimos eram a MESMA coisa até 2026-07-29, e o
 * colapso fazia o relatório afirmar "✓ todos os N crons com heartbeat < limite" tendo
 * medido ZERO: sem `gh`, os 24 saíam 🟡 e o exit era 0 — enquanto `system-map.yml`, um
 * deles, tinha 16 runs agendadas reais. Ausência de medição não é estado do cron.
 * Limite: semanal 10d · mensal 35d · diário/frequente 3d.
 *
 * ── EIXO 2 (2026-07-26): ENTREGA — "roda" ≠ "entrega" ───────────────────────
 * O eixo 1 acima só enxerga `.github/workflows` — 24 workflows agendados. Mas o app
 * tem 76 schedules Laravel (`php artisan schedule:list`), e pra ESSES não existe API
 * de liveness: o scheduler roda no servidor, não no GitHub. Copiar o mecanismo é
 * impossível; e seria medir a coisa errada de qualquer jeito.
 *
 * O incidente que originou este eixo mostra por quê: `governance:scorecard-snapshot
 * --alert` roda TODO DIA às 07:00 e os 5 scorecards do Governance v4 estavam com
 * `last_grade_at: 2026-05-16` — 71 dias parados. Liveness teria dado verde.
 *
 * ⚠️ ERRATA (2026-07-26, auditoria do próprio eixo): a 1ª redação dizia que o cron
 * "deveria atualizar" esses 5 e não entregava. FALSO, e a correção importa porque o
 * texto errado manda a próxima sessão caçar um culpado que não existe. Medido:
 * varredura contada de escritores (php/mjs/js em Modules/·scripts/·app/·.claude/)
 * achou ZERO que escrevam `memory/governance/scorecards/*.yaml` — os dois crons são
 * LEITORES (o de 07:00 grava `mcp_scorecard_runs`; o de 06:05, `mcp_module_grades_history`).
 * Os YAMLs são INPUT curado à mão; `last_grade_at` é carimbo de curadoria humana.
 * Ver o cabeçalho de `memory/governance/scorecards/_template.yaml` (dono da regra).
 *
 * Ou seja: este eixo mede IDADE de artefato, e idade não revela autoria. Ele acerta o
 * FATO (parou) e não pode concluir a CAUSA — quem investiga é que decide entre cron
 * quebrado, curadoria envelhecida ou mecanismo morto. Desfecho do caso dos 5 (decisão
 * [W], #4822): eram CURADORIA ENVELHECIDA — os YAMLs foram revisados e o alarme apagou
 * por conserto. O cron seguiu vivo e entregando (grava no DB); quem nunca aconteceu foi
 * a revisão humana.
 *
 * Então este eixo mede a CONSEQUÊNCIA, não a declaração: varre os artefatos de estado
 * versionados que carregam data interna própria e acusa os que envelheceram além do
 * limite. Não precisa de `gh`, não precisa de rede, não precisa saber QUEM deveria
 * ter escrito — mas TAMBÉM NÃO CONCLUI que existe um escritor (ver errata acima): o
 * que ele entrega é "este artefato de estado parou há Nd", e a causa é da investigação.
 *
 * Cobertura honesta: dos 290 arquivos de estado em `governance/` + `memory/governance/`,
 * só 13 declaram data interna — os outros 277 são baselines sem carimbo e ficam FORA
 * deste eixo (não dá pra medir idade do que não se data). Medição de 2026-07-26:
 * 5 parados (os scorecards, 71d) · próximo mais velho 44d → limite 60d com FP=0.
 *
 * Advisory ao nascer (ADR 0275). Sai != 0 pra ficar VERMELHO e visível — job advisory
 * pode e deve falhar; advisory = não bloqueia merge, nunca = não pode ficar vermelho.
 *
 * Uso: node scripts/governance/cron-watchdog.mjs            (os 2 eixos; eixo 1 precisa de `gh`)
 *      node scripts/governance/cron-watchdog.mjs --entrega  (só eixo 2 — sem rede)
 *      node scripts/governance/cron-watchdog.mjs --json     (só eixo 2, JSON pro Daily Brief)
 *      node scripts/governance/cron-watchdog.mjs --selftest (núcleo puro morde e libera)
 * Refs: ADR 0317 §2 (auto-canário) · 0256 (sentinela). Molde: memory-health.yml job cron-liveness.
 */
import { readdirSync, readFileSync } from 'node:fs';
import { execSync } from 'node:child_process';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';

const ROOT = process.cwd();
const WF_DIR = '.github/workflows';

const ARGS = new Set(process.argv.slice(2));

/**
 * Só executa quando chamado como script. Sem esta guarda, um `.test.mjs` (ou
 * qualquer import de `dataInterna`/`paradosEntre`) dispararia o watchdog inteiro,
 * incluindo as chamadas `gh` do eixo 1 — importar não pode ter efeito colateral.
 */
const EH_MAIN = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;

// Descobre workflows com trigger `schedule:` real (bloco com `- cron:`, não a palavra
// solta num comentário) e extrai o primeiro cron. Robusto a cron novo/removido.
function scheduledWorkflows() {
  const out = [];
  for (const f of readdirSync(join(ROOT, WF_DIR)).filter((x) => /\.ya?ml$/.test(x)).sort()) {
    let txt;
    try { txt = readFileSync(join(ROOT, WF_DIR, f), 'utf8'); } catch { continue; }
    const sched = txt.match(/^\s*schedule:\s*\n((?:\s*#.*\n|\s*-\s*cron:.*\n)+)/mi);
    if (!sched) continue;
    const crons = [...sched[1].matchAll(/cron:\s*['"]([^'"]+)['"]/g)].map((m) => m[1]);
    if (crons.length) out.push({ file: f, cron: crons[0] });
  }
  return out;
}

// Limite (dias) por cadência: DOW setado → semanal (10) · DOM setado → mensal (35) ·
// senão diário/frequente (3). Generoso o bastante p/ não dar falso-positivo, apertado
// o bastante p/ pegar morte de vários dias (o GitHub só desabilita aos 60d).
function thresholdDays(cron) {
  const parts = cron.trim().split(/\s+/);
  const dom = parts[2], dow = parts[4];
  if (dow && dow !== '*' && dow !== '?') return 10;
  if (dom && dom !== '*' && dom !== '?') return 35;
  return 3;
}

// `ok` separa "perguntei" de "não consegui perguntar". Sem essa distinção, `gh` ausente,
// sem auth, sem escopo `actions:read` ou API fora devolvia `''` — indistinguível de "zero
// runs" (ver classificarLiveness).
function gh(args) {
  try {
    return { ok: true, out: execSync(`gh ${args}`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim() };
  } catch {
    return { ok: false, out: '' };
  }
}

/**
 * Interpreta a resposta do `gh` preservando as TRÊS respostas possíveis:
 *   { ok:true,  at:'2026-07-28…' } → perguntei e HOUVE run agendada
 *   { ok:true,  at:'' }            → perguntei e NÃO houve run (bootstrap de verdade)
 *   { ok:false }                   → NÃO consegui perguntar (cego)
 * Resposta ilegível conta como CEGO, nunca como ausência: JSON quebrado é falha de
 * consulta, e tratá-lo como "sem run" inventaria um estado do cron a partir de um
 * defeito do consultante.
 */
export function interpretarResposta(r) {
  if (!r || r.ok !== true) return { ok: false };
  if (!r.out) return { ok: true, at: '' };
  try {
    const row = JSON.parse(r.out)[0] || {};
    return { ok: true, at: row.createdAt || '', conclusion: row.conclusion || '' };
  } catch { return { ok: false }; }
}

// Última run AGENDADA (event=schedule, qualquer conclusão — liveness ≠ sucesso).
// Filtra o JSON em JS (sem `--jq`): evita o quoting de aspas simples que o cmd.exe do
// Windows quebra (execSync usa cmd no Win, sh no CI) — cross-platform + testável local.
// `conclusion` vem na MESMA chamada (custo zero de API) e alimenta o eixo 3.
function lastScheduledRun(file) {
  return interpretarResposta(gh(`run list --workflow ${file} --event schedule --status completed --limit 1 --json createdAt,conclusion`));
}

/**
 * Classifica UMA porta de liveness. O estado `cego` existe porque ele NÃO é um estado
 * do cron — é a ausência de medição. Colapsá-lo em `bootstrap` (🟡 benigno) fazia o
 * watchdog imprimir "✓ todos os N crons com heartbeat < limite" tendo medido ZERO.
 * Medido 2026-07-29 sem `gh` no host: 8 workflows saíram como "sem run agendada ainda",
 * e `system-map.yml` — um deles — tem 16 runs agendadas de verdade.
 */
export function classificarLiveness(cron, consulta, nowMs) {
  const limite = thresholdDays(cron);
  if (!consulta || consulta.ok !== true) return { estado: 'cego', limite };
  if (!consulta.at) return { estado: 'bootstrap', limite };
  const dias = Math.floor((nowMs - new Date(consulta.at).getTime()) / 86400000);
  return { estado: dias > limite ? 'morto' : 'vivo', dias, limite, at: consulta.at };
}

/**
 * Agrega os estados e decide o que o relatório PODE afirmar.
 *  · `afirmaVerde` só quando houve medição de todos e nenhum morreu — a frase "todos com
 *    heartbeat < limite" é sobre os crons, e não pode ser dita sobre os não medidos.
 *  · `exit=1` quando há morto (alarme original) OU quando a cegueira é TOTAL: um watchdog
 *    que não mediu NADA não pode sair verde. Cegueira PARCIAL fica visível sem derrubar —
 *    falha transitória de API num único workflow não vale um vermelho (e o job é advisory).
 */
export function resumoLiveness(estados) {
  const cont = { vivo: 0, bootstrap: 0, cego: 0, morto: 0 };
  for (const e of estados) if (e in cont) cont[e]++;
  const total = estados.length;
  const cegoTotal = total > 0 && cont.cego === total;
  return { ...cont, total, medidos: total - cont.cego, cegoTotal,
    afirmaVerde: cont.morto === 0 && cont.cego === 0, exit: (cont.morto > 0 || cegoTotal) ? 1 : 0 };
}

// ─────────────────────────────────────────────────────────────────────────────
// EIXO 3 — SUCESSO ("rodou" ≠ "deu certo")
// ─────────────────────────────────────────────────────────────────────────────
/**
 * O eixo 1 pergunta `--status completed`, e `completed` INCLUI `failure`. Um cron que
 * dispara todo dia e falha todo dia era indistinguível de um saudável: heartbeat fresco,
 * verde. Foi o vão que escondeu, ao mesmo tempo:
 *   · `system-map.yml`      — 4 runs agendadas seguidas falhando (30/07→02/08 de 2026),
 *                             o PAINEL-SISTEMA congelado, ninguém avisado;
 *   · `governance-drift.yml`— 52 runs agendadas falhando em sequência; último sucesso
 *                             em 2026-06-11, streak desde 12/06.
 *
 * ERRATA (2026-08-03, revisão adversarial). A redação original afirmava "40 de 40, ZERO
 * sucesso desde 24/06" e "FP medido = 0, nenhum vermelho por desenho". As DUAS estavam
 * erradas, e o modo de errar é o que vale registrar:
 *   · "40 de 40 desde 24/06" saiu de um `gh run list --limit 40`. 40 runs diárias
 *     contadas pra trás de 02/08 caem exatamente em ~24/06 — a BORDA DA JANELA foi lida
 *     como início do fenômeno. Com a janela inteira (65 retidas): 52 failure + 13
 *     success, último sucesso 11/06. A dívida é 13 dias mais velha que o registrado.
 *   · "FP = 0" mediu a taxa de `failure` e concluiu sobre o SIGNIFICADO dela sem ler o
 *     código de cada um. Lido: `governance-drift` sai 1 POR DESENHO (`SecretsAuditCommand`
 *     → `return self::FAILURE; // drift detectado (CI-friendly)`, drift permanente à
 *     espera de rotação [W]) e `exposicao-tier0-sentinel` também (piso Tier-0 violado →
 *     comenta a issue #4567 → exit 1). Só `system-map` era defeito de mecanismo.
 *
 * E a janela ≤14 runs NÃO é comparável entre crons: cobre 0,9 dia no `mcp-drift-sentinel`
 * (roda de 30 em 30 minutos) e 77 dias no `jana-ragas-gate` (semanal) — 86x de diferença.
 * Somar os dois num "21 dos 24 com 0% de falha" é média de coisas incomensuráveis, e o
 * número já virou 20 quando o `mcp-drift-sentinel` acumulou 1 falha na janela de ~21h.
 *
 * O QUE SOBREVIVEU: o vão é real, e quem prova é o próprio relatório de CI, que imprime
 * as duas linhas juntas — `✓ governance-drift.yml 1d/3d` (eixo 1: vivo) ao lado de
 * `🔴 governance-drift.yml — última run agendada CONCLUIU 'failure'` (eixo 3).
 *
 * Eixo SEPARADO do liveness de propósito: vivo-e-falhando é um estado real (é o caso
 * dos dois acima), e colapsar os dois num só faria o relatório perder justo esse.
 */
// `completed` NÃO é só success/failure: o GitHub fecha run com 9 conclusões distintas.
// Tratar "tudo != success" como FALHA é falso-positivo medido, não hipotético —
// 20 dos 24 workflows agendados usam `cancel-in-progress: true`, e já existe run
// agendada cancelada de verdade (`mcp-drift-sentinel.yml`, run 29219824034, 13/07),
// que o filtro `--status completed` DEVOLVE. O pior caso é o próprio
// `mcp-drift-sentinel`: roda `*/30 * * * *` com `group:` sem `${{ github.ref }}`, então
// qualquer dispatch cancela a agendada em curso — e um cancelamento benigno viraria
// 🔴 + exit 1. Achado da revisão adversarial de 2026-08-03: a medição que precedeu o
// eixo 3 olhou a TAXA de `failure` e nunca o ESPAÇO de `conclusion`.
const CONCLUSOES_DE_FALHA = new Set(['failure', 'timed_out', 'startup_failure']);
// Não afirmam sucesso NEM defeito: a run não chegou ao fim por conta própria. Estado
// próprio de propósito — some no relatório sem derrubar, e proíbe afirmar verde.
const CONCLUSOES_INCONCLUSIVAS = new Set(['cancelled', 'skipped', 'neutral', 'stale', 'action_required']);

export function classificarSucesso(consulta) {
  if (!consulta || consulta.ok !== true) return { estado: 'cego' };
  if (!consulta.at) return { estado: 'bootstrap' };
  // conclusion ausente numa run já `completed` = resposta incompleta, não é "deu certo":
  // afirmar sucesso a partir de campo que não veio é o fail-open que a ADR 0317 §2 pune.
  if (!consulta.conclusion) return { estado: 'cego' };
  const c = consulta.conclusion;
  if (c === 'success') return { estado: 'ok', conclusion: c };
  if (CONCLUSOES_DE_FALHA.has(c)) return { estado: 'falhou', conclusion: c, at: consulta.at };
  if (CONCLUSOES_INCONCLUSIVAS.has(c)) return { estado: 'inconclusivo', conclusion: c, at: consulta.at };
  // Conclusão DESCONHECIDA (o GitHub pode acrescentar valores): inconclusivo, nunca
  // sucesso e nunca falha — inventar veredito a partir de string não prevista é o
  // mesmo fail-open, só que com cara de exaustividade.
  return { estado: 'inconclusivo', conclusion: c, at: consulta.at };
}

/**
 * FIAÇÃO dos eixos 1 e 3 — pura de propósito, recebendo `consultar` por parâmetro.
 *
 * Antes, este laço vivia solto no corpo do script e NENHUM assert o alcançava: a
 * revisão adversarial de 2026-08-03 provou com mutantes que desligar o eixo 3 inteiro
 * — trocar `if (s.estado === 'falhou')` por `if (false)`, ou tirar `rs.exit` do
 * `process.exit` — deixava o selftest VERDE. Os asserts provavam as funções puras e
 * nada da invocação: a classe "correção-do-mecanismo ≠ invocação" (§5 2026-07-09),
 * justamente o defeito que o eixo 2 deste arquivo se gaba de ter consertado.
 *
 * Residual honesto que permanece: a linha `process.exit(decidirExit(...))` no corpo do
 * script segue fora de teste — para cobri-la seria preciso E2E com `gh` stubado. O que
 * este refactor faz é reduzir a superfície não-testada de um laço inteiro para uma
 * linha, não zerá-la. Declarar isso é parte do conserto.
 */
export function avaliarCrons(wfs, consultar, nowMs) {
  const dead = [], boot = [], alive = [], cego = [], estados = [];
  const falhando = [], inconclusivos = [], estadosSucesso = [];
  for (const { file, cron } of wfs) {
    const consulta = consultar(file); // UMA consulta alimenta os eixos 1 e 3
    const c = classificarLiveness(cron, consulta, nowMs);
    const s = classificarSucesso(consulta);
    estadosSucesso.push(s.estado);
    if (s.estado === 'falhou') falhando.push(`${file} — última run agendada CONCLUIU '${s.conclusion}' (${s.at})`);
    else if (s.estado === 'inconclusivo') inconclusivos.push(`${file} — última run agendada CONCLUIU '${s.conclusion}' (${s.at}) — não afirma sucesso nem defeito`);
    estados.push(c.estado);
    if (c.estado === 'cego') cego.push(`${file} (cron '${cron}') — NÃO CONSULTÁVEL (gh ausente/sem auth/sem 'actions:read'/API fora): este cron NÃO foi medido`);
    else if (c.estado === 'bootstrap') boot.push(`${file} (cron '${cron}') — sem run agendada ainda (bootstrap; arma na 1ª execução)`);
    else if (c.estado === 'morto') dead.push(`${file} (cron '${cron}') MORTO há ${c.dias}d (limite ${c.limite}d) — última agendada: ${c.at}`);
    else alive.push(`${file} ${c.dias}d/${c.limite}d`);
  }
  return { dead, boot, alive, cego, estados, falhando, inconclusivos, estadosSucesso };
}

/** Decisão de saída dos 3 eixos, num só lugar testável. */
export function decidirExit(rLiveness, rSucesso, falhouEntrega) {
  return (rLiveness.exit || rSucesso.exit || falhouEntrega) ? 1 : 0;
}

/**
 * Agrega o eixo 3. `afirmaVerde` exige medição de todos E que ninguém esteja
 * inconclusivo — mesma regra do eixo 1: não se afirma sobre o que não se mediu.
 * `exit` só conta falha REAL: cancelamento não derruba o job.
 */
export function resumoSucesso(estados) {
  const cont = { ok: 0, bootstrap: 0, cego: 0, falhou: 0, inconclusivo: 0 };
  for (const e of estados) if (e in cont) cont[e]++;
  const total = estados.length;
  return { ...cont, total, medidos: total - cont.cego,
    afirmaVerde: cont.falhou === 0 && cont.cego === 0 && cont.inconclusivo === 0,
    exit: cont.falhou > 0 ? 1 : 0 };
}

// ─────────────────────────────────────────────────────────────────────────────
// EIXO 2 — ENTREGA (artefato de estado que envelheceu)
// ─────────────────────────────────────────────────────────────────────────────

/** Dias sem atualizar antes de acusar. 60d: os 5 verdadeiros tinham 71d, o mais velho legítimo 44d. */
const ENTREGA_LIMITE_DIAS = Number(process.env.OBRA_PARADA_DIAS || 60);

/**
 * Chaves de data interna que os artefatos de estado do projeto usam de fato
 * (levantadas varrendo os 290 arquivos, não supostas). Inclui as PT-BR: o projeto
 * escreve em PT-BR e `governance/jana-ragas-real-baseline.json` usa `gerado_em` —
 * sem elas o eixo teria ponto cego justo nos artefatos mais "da casa".
 */
const CHAVES_DATA = ['generated_at', 'updated_at', 'last_grade_at', 'last_updated',
  'snapshot_at', 'computed_at', 'reconciled_at', 'distilled_at',
  'gerado_em', 'atualizado_em', 'gerado_por_em', 'ultima_data'];

/** Artefatos de estado versionados (JSON/YAML sob governance/ e memory/governance/). */
function arquivosDeEstado() {
  try {
    return execSync('git ls-files', { encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 })
      .split('\n')
      .filter((p) => /^(memory\/)?governance\/.*\.(json|ya?ml)$/.test(p));
  } catch { return []; }
}

/**
 * Extrai a data interna MAIS RECENTE do texto (o artefato pode ter várias).
 * Retorna 'YYYY-MM-DD' ou null quando o arquivo não se data — e não-datado NUNCA
 * é acusado: medir idade do que não declara data seria inventar o número.
 */
export function dataInterna(txt) {
  const re = new RegExp(`(${CHAVES_DATA.join('|')})["']?\\s*[:=]\\s*["']?(\\d{4}-\\d{2}-\\d{2})`, 'g');
  const achadas = [...txt.matchAll(re)].map((m) => m[2]).sort();
  return achadas.length ? achadas[achadas.length - 1] : null;
}

/**
 * NÚCLEO PURO (testável, sem I/O): recebe [{arquivo, data}] e devolve os parados.
 * `nowMs` injetado — determinístico no selftest, real na execução.
 */
export function paradosEntre(entradas, nowMs, limiteDias) {
  const out = [];
  for (const { arquivo, data } of entradas) {
    if (!data) continue;
    const dias = Math.floor((nowMs - Date.parse(`${data}T00:00:00Z`)) / 86400000);
    if (dias > limiteDias) out.push({ arquivo, data, dias });
  }
  return out.sort((a, b) => b.dias - a.dias);
}

function checarEntrega(nowMs) {
  const entradas = [];
  for (const arquivo of arquivosDeEstado()) {
    let txt;
    try { txt = readFileSync(join(ROOT, arquivo), 'utf8'); } catch { continue; }
    entradas.push({ arquivo, data: dataInterna(txt) });
  }
  const datados = entradas.filter((e) => e.data);
  return {
    gate: 'obra-parada',
    limite_dias: ENTREGA_LIMITE_DIAS,
    total_estado: entradas.length,
    total_datados: datados.length,
    parados: paradosEntre(datados, nowMs, ENTREGA_LIMITE_DIAS),
  };
}

function reportarEntrega(r) {
  console.log(`\n📦 entrega — ${r.total_datados} artefato(s) de estado com data interna (de ${r.total_estado}) · limite ${r.limite_dias}d · ${r.parados.length} 🔴 parado(s)`);
  for (const p of r.parados) console.error(`🔴 ${p.arquivo} — parado há ${p.dias}d (última data interna: ${p.data})`);
  if (!r.parados.length) { console.log(`✓ nenhum artefato de estado além do limite.`); return 0; }
  console.error(`\n✗ ${r.parados.length} artefato(s) de estado parado(s) além do limite. Este eixo mede IDADE, não autoria — ele não sabe se o artefato tem escritor automático. Ao investigar, o achado cai num dos 3 casos: (a) cron que rodava e parou de ENTREGAR → conserta a entrega; (b) artefato CURADO À MÃO cuja revisão envelheceu → re-cura, ou aposenta quem o consome; (c) mecanismo inteiro parado → aposenta com lápide. Como distinguir: varra os escritores do path (quem faz write nele) — sem escritor, não é (a). Precedente 2026-07-26 (os 5 scorecards do Governance v4): varredura contada achou ZERO escritores — os crons de 06:05/07:00 apenas LEEM —, então NÃO era (a). [W] decidiu (b): revisar os 5 (#4822). Conferir "quem escreve neste path" é o passo que separa os 3 casos.`);
  return 1;
}

// ── selftest: o núcleo morde e libera (fixture ruim + fixture boa) ───────────
if (EH_MAIN && ARGS.has('--selftest')) {
  const NOW = Date.parse('2026-07-26T00:00:00Z');
  let falhas = 0;
  const ok = (cond, msg) => { console.log((cond ? '  PASS ' : '  FAIL ') + msg); if (!cond) falhas++; };

  const ruim = paradosEntre([{ arquivo: 'x.yaml', data: '2026-05-16' }], NOW, 60);
  ok(ruim.length === 1 && ruim[0].dias === 71, 'MORDE: artefato de 71d acusado (limite 60)');

  const boa = paradosEntre([{ arquivo: 'y.json', data: '2026-07-24' }], NOW, 60);
  ok(boa.length === 0, 'LIBERA: artefato de 2d passa');

  const borda = paradosEntre([{ arquivo: 'z.json', data: '2026-06-12' }], NOW, 60);
  ok(borda.length === 0, 'LIBERA: 44d (o mais velho legítimo medido) passa — sem FP');

  ok(paradosEntre([{ arquivo: 'n.json', data: null }], NOW, 60).length === 0,
    'LIBERA: artefato sem data interna nunca é acusado');

  ok(dataInterna('"generated_at": "2026-01-01"\n"updated_at": "2026-07-01"') === '2026-07-01',
    'dataInterna: pega a MAIS RECENTE quando há várias');
  ok(dataInterna('{"foo": 1}') === null, 'dataInterna: sem chave de data → null');
  ok(dataInterna('"gerado_em": "2026-07-01"') === '2026-07-01',
    'dataInterna: reconhece chave PT-BR (gerado_em) — o projeto escreve em PT-BR');
  // Regressão: `governance/route-hits.json` usa `ultima_data` e alimenta DOIS gates
  // required (charter-live-signal + anchor-lint). Sem esta chave ele era varrido e
  // classificado como não-datado → invisível pra sempre. Achado da auditoria 07-26.
  ok(dataInterna('"ultima_data": "2026-07-11"') === '2026-07-11',
    'dataInterna: reconhece ultima_data (route-hits.json → 2 gates required)');

  const ordem = paradosEntre([{ arquivo: 'novo', data: '2026-05-20' }, { arquivo: 'velho', data: '2026-01-01' }], NOW, 60);
  ok(ordem[0].arquivo === 'velho', 'ordena do mais parado pro menos');

  // ── EIXO 1: "não consegui perguntar" ≠ "nunca rodou" (defeito medido 2026-07-29) ──
  // Sem `gh` no host, os 8 workflows saíam como 🟡 bootstrap e o relatório terminava em
  // "✓ todos os 24 crons com heartbeat < limite" — tendo medido ZERO. `system-map.yml`,
  // um dos 8, tem 16 runs agendadas reais. Falso por construção, não por dado velho.
  ok(interpretarResposta({ ok: false }).ok === false,
    'consulta que FALHOU → ok:false (não vira ausência de run)');
  ok(interpretarResposta({ ok: true, out: '[]' }).at === '',
    'consulta OK com zero runs → bootstrap de verdade (at vazio)');
  ok(interpretarResposta({ ok: true, out: '[{"createdAt":"2026-07-28T12:19:54Z"}]' }).at === '2026-07-28T12:19:54Z',
    'consulta OK com run → devolve o timestamp');
  ok(interpretarResposta({ ok: true, out: 'nao é json' }).ok === false,
    'resposta ILEGÍVEL → cego, nunca ausência (defeito do consultante ≠ estado do cron)');

  ok(classificarLiveness('30 10 * * *', { ok: false }, NOW).estado === 'cego',
    'BITE: consulta falhou → estado CEGO (antes virava bootstrap benigno)');
  ok(classificarLiveness('30 10 * * *', { ok: true, at: '' }, NOW).estado === 'bootstrap',
    'LIBERA: bootstrap legítimo continua bootstrap (não virou cego ao contrário)');
  ok(classificarLiveness('30 10 * * *', { ok: true, at: '2026-07-25T10:00:00Z' }, NOW).estado === 'vivo',
    'LIBERA: run de 1d no cron diário segue viva');
  ok(classificarLiveness('30 10 * * *', { ok: true, at: '2026-07-01T10:00:00Z' }, NOW).estado === 'morto',
    'MORDE: run de 25d no cron diário (limite 3d) segue MORTA — alarme original intacto');

  const cegoTotal = resumoLiveness(['cego', 'cego', 'cego']);
  ok(cegoTotal.exit === 1 && cegoTotal.afirmaVerde === false,
    'BITE: cegueira TOTAL → exit 1 e proibido afirmar verde (antes: exit 0 + "✓ todos")');
  const cegoParcial = resumoLiveness(['vivo', 'vivo', 'cego']);
  ok(cegoParcial.exit === 0 && cegoParcial.afirmaVerde === false && cegoParcial.medidos === 2,
    'cegueira PARCIAL → não derruba (transitório), mas também não afirma verde');
  ok(resumoLiveness(['vivo', 'vivo', 'bootstrap']).afirmaVerde === true,
    'LIBERA: tudo medido e ninguém morto → pode afirmar verde (não virou carimbo ao contrário)');
  ok(resumoLiveness(['vivo', 'morto']).exit === 1,
    'MORDE: um morto entre medidos → exit 1 (o alarme que já existia)');

  // ── EIXO 3: "rodou" ≠ "deu certo" — o caso REAL, com os dados reais ──────────
  // system-map: 4 falhas seguidas (30/07→02/08) com heartbeat fresco; governance-drift:
  // 40/40 falhas desde 24/06. Os dois VIVOS o tempo todo — logo o eixo 1 os dava verdes.
  const runFalha = { ok: true, out: '[{"createdAt":"2026-08-02T11:40:32Z","conclusion":"failure"}]' };
  const runOk = { ok: true, out: '[{"createdAt":"2026-08-02T11:40:32Z","conclusion":"success"}]' };

  ok(interpretarResposta(runFalha).conclusion === 'failure',
    'a conclusão vem na MESMA consulta (sem chamada extra de API)');
  ok(classificarSucesso(interpretarResposta(runFalha)).estado === 'falhou',
    'MORDE: última agendada = failure → FALHOU (o caso system-map/governance-drift)');
  ok(classificarLiveness('30 10 * * *', interpretarResposta(runFalha), Date.parse('2026-08-03T12:00:00Z')).estado === 'vivo',
    'e ela segue VIVA no eixo 1 — é exatamente o vão: vivo E falhando ao mesmo tempo');
  ok(classificarSucesso(interpretarResposta(runOk)).estado === 'ok',
    'LIBERA: última agendada = success → ok (21 dos 24 medidos caem aqui)');
  ok(classificarSucesso({ ok: false }).estado === 'cego',
    'consulta falhou → CEGO, nunca "deu certo" (fail-open é o que a ADR 0317 §2 pune)');
  ok(classificarSucesso({ ok: true, at: '' }).estado === 'bootstrap',
    'LIBERA: sem run agendada ainda → bootstrap, não falha');
  ok(classificarSucesso({ ok: true, at: '2026-08-02T11:40:32Z', conclusion: '' }).estado === 'cego',
    'BITE: run completed SEM conclusion → cego, não sucesso (campo ausente ≠ verde)');
  ok(resumoSucesso(['ok', 'ok', 'falhou']).exit === 1 && resumoSucesso(['ok', 'ok', 'falhou']).afirmaVerde === false,
    'MORDE: um falhando entre medidos → exit 1 e proibido afirmar verde');
  ok(resumoSucesso(['ok', 'ok', 'bootstrap']).exit === 0 && resumoSucesso(['ok', 'ok', 'bootstrap']).afirmaVerde === true,
    'LIBERA: todos medidos com sucesso → verde (não virou alarme permanente)');
  ok(resumoSucesso(['ok', 'cego']).afirmaVerde === false && resumoSucesso(['ok', 'cego']).exit === 0,
    'cegueira parcial no eixo 3 → não derruba, mas também não afirma verde');

  // ── FP MEDIDO: `completed` tem 9 conclusões, não 2 (revisão adversarial 2026-08-03) ──
  // 20 dos 24 workflows agendados usam `cancel-in-progress: true`, e a run 29219824034
  // (`mcp-drift-sentinel`, 13/07) é um cancelamento REAL que o `--status completed` devolve.
  const runCancel = { ok: true, at: '2026-07-13T02:35:14Z', conclusion: 'cancelled' };
  ok(classificarSucesso(runCancel).estado === 'inconclusivo',
    'BITE: cancelled → INCONCLUSIVO, não "falhou" (o FP real do caso 29219824034)');
  ok(resumoSucesso(['ok', 'inconclusivo']).exit === 0,
    'LIBERA: cancelamento benigno NÃO derruba o job');
  ok(resumoSucesso(['ok', 'inconclusivo']).afirmaVerde === false,
    'mas também não deixa afirmar verde — não se afirma sobre o que não se mediu');
  for (const c of ['skipped', 'neutral', 'stale', 'action_required']) {
    ok(classificarSucesso({ ok: true, at: 'x', conclusion: c }).estado === 'inconclusivo',
      `LIBERA: '${c}' → inconclusivo (não é defeito do cron)`);
  }
  for (const c of ['failure', 'timed_out', 'startup_failure']) {
    ok(classificarSucesso({ ok: true, at: 'x', conclusion: c }).estado === 'falhou',
      `MORDE: '${c}' → falhou (falha real continua mordendo)`);
  }
  ok(classificarSucesso({ ok: true, at: 'x', conclusion: 'conclusao_futura_do_github' }).estado === 'inconclusivo',
    'conclusão DESCONHECIDA → inconclusivo, nunca sucesso (fail-open com cara de exaustividade)');

  // ── FIAÇÃO: os mutantes que passavam verde antes (mut1/mut2 da revisão adversarial) ──
  const wfsFake = [{ file: 'a.yml', cron: '30 10 * * *' }, { file: 'b.yml', cron: '30 10 * * *' }];
  const NOW3 = Date.parse('2026-08-03T12:00:00Z');
  const consultaFake = (f) => (f === 'a.yml'
    ? { ok: true, at: '2026-08-03T10:30:00Z', conclusion: 'failure' }
    : { ok: true, at: '2026-08-03T10:30:00Z', conclusion: 'success' });
  const av = avaliarCrons(wfsFake, consultaFake, NOW3);
  ok(av.falhando.length === 1 && av.falhando[0].includes('a.yml'),
    'FIAÇÃO: o laço REPORTA o cron falhando (mata o mutante `if (false) falhando.push`)');
  ok(av.estados.every((e) => e === 'vivo'),
    'FIAÇÃO: e os dois seguem VIVOS no eixo 1 — o vão, medido pela fiação inteira');
  ok(decidirExit(resumoLiveness(av.estados), resumoSucesso(av.estadosSucesso), 0) === 1,
    'FIAÇÃO: eixo 3 sozinho derruba o exit (mata o mutante que tira `rs.exit`)');
  ok(decidirExit({ exit: 0 }, { exit: 0 }, 0) === 0 && decidirExit({ exit: 0 }, { exit: 0 }, 1) === 1,
    'FIAÇÃO: sem falha → 0; entrega parada sozinha → 1 (os outros eixos seguem ligados)');
  const avCancel = avaliarCrons([{ file: 'c.yml', cron: '30 10 * * *' }],
    () => ({ ok: true, at: '2026-08-03T10:30:00Z', conclusion: 'cancelled' }), NOW3);
  ok(avCancel.falhando.length === 0 && avCancel.inconclusivos.length === 1
     && decidirExit(resumoLiveness(avCancel.estados), resumoSucesso(avCancel.estadosSucesso), 0) === 0,
    'FIAÇÃO: cron só-cancelado atravessa o caminho inteiro sem derrubar nada');

  console.log(falhas ? `\n✗ selftest: ${falhas} falha(s)` : '\n✓ selftest: núcleo morde e libera certo');
  process.exit(falhas ? 1 : 0);
}

// ── JSON pro Daily Brief (só eixo 2 — sem rede, roda em qualquer host) ──────
if (EH_MAIN && ARGS.has('--json')) {
  console.log(JSON.stringify(checarEntrega(Date.now())));
  process.exit(0);
}

// ── só o eixo 2 (sem `gh`) ──────────────────────────────────────────────────
if (EH_MAIN && ARGS.has('--entrega')) {
  process.exit(reportarEntrega(checarEntrega(Date.now())));
}

// Importado (por um .test.mjs, por exemplo): exporta o núcleo e não roda nada.
if (!EH_MAIN) { /* no-op — ver EH_MAIN */ }
else {

const wfs = scheduledWorkflows();
if (!wfs.length) {
  console.log('cron-watchdog: nenhum workflow agendado encontrado (nada a vigiar).');
  process.exit(reportarEntrega(checarEntrega(Date.now())));
}

const nowMs = Date.now(); // liveness real (não determinístico de propósito)
const { dead, boot, alive, cego, estados, falhando, inconclusivos, estadosSucesso } =
  avaliarCrons(wfs, lastScheduledRun, nowMs);
const r = resumoLiveness(estados);

console.log(`🩺 cron-watchdog — ${wfs.length} crons agendados · ${alive.length} vivos · ${boot.length} bootstrap · ${cego.length} ⛔ não medidos · ${dead.length} 🔴 mortos`);
for (const c of cego) console.error(`⛔ ${c}`);
for (const b of boot) console.log(`🟡 ${b}`);
for (const a of alive) console.log(`   ✓ ${a}`);
for (const d of dead) console.error(`🔴 ${d}`);
if (dead.length) {
  console.error(`\n✗ ${dead.length} cron(s) de governança MORTO(s) — o GitHub desabilitou o schedule (60d sem atividade) ou o workflow quebra na origem. Um push no repo re-ativa; confirme run agendada nova. (ADR 0317 §2 — o heartbeat que vigia os heartbeats).`);
} else if (r.cegoTotal) {
  // Um watchdog que não mediu NADA não pode sair verde — seria o "gate mudo" que parece
  // cobertura. Fora do CI (host sem `gh`), o caminho honesto é `--entrega`, que não usa rede.
  console.error(`\n⛔ cron-watchdog CEGO: nenhum dos ${wfs.length} crons pôde ser consultado — nada foi medido, então NADA pode ser afirmado sobre heartbeat. Em CI: confira 'gh' + GH_TOKEN + permissions.actions=read. Fora do CI: use --entrega (eixo 2, sem rede).`);
} else if (cego.length) {
  console.log(`✓ os ${r.medidos} cron(s) MEDIDOS estão com heartbeat < limite — ⚠️ ${cego.length} não foi(ram) medido(s) (acima): sobre esse(s) o watchdog não afirma nada.`);
} else {
  console.log(`✓ todos os ${wfs.length} crons de governança com heartbeat < limite.`);
}

// ── EIXO 3 — SUCESSO. Sempre roda, pela mesma razão do eixo 2: vivo-e-falhando é
// estado independente de morto, e o caso real (system-map, governance-drift) estava
// justamente vivo. Esconder atrás do eixo 1 faria o relatório mentir por omissão.
const rs = resumoSucesso(estadosSucesso);
console.log(`\n🎯 sucesso — ${rs.medidos} medido(s) de ${rs.total} · ${rs.ok} ok · ${rs.bootstrap} bootstrap · ${rs.cego} ⛔ não medidos · ${rs.inconclusivo} 🟠 inconclusivos · ${falhando.length} 🔴 falhando`);
for (const f of falhando) console.error(`🔴 ${f}`);
for (const i of inconclusivos) console.log(`🟠 ${i}`);
if (falhando.length) {
  console.error(`\n✗ ${falhando.length} cron(s) DISPARAM mas FALHAM — heartbeat fresco não é entrega. Abra o log com 'gh run list --workflow <file> --event schedule' e conserte a origem, ou registre por que o vermelho é esperado.`);
} else if (rs.cego || rs.inconclusivo) {
  console.log(`✓ nenhum cron MEDIDO falhou — ⚠️ ${rs.cego} não medido(s) e ${rs.inconclusivo} inconclusivo(s) (cancelled/skipped/...): sobre esse(s) nada se afirma.`);
} else {
  console.log(`✓ a última run agendada de todos os ${rs.total} concluiu com sucesso.`);
}

// Eixo 2 sempre roda — inclusive quando o eixo 1 já achou cron morto: são falhas
// independentes (um cron pode estar vivo e não entregar, e vice-versa) e esconder
// a segunda atrás da primeira faria o relatório mentir por omissão.
const falhouEntrega = reportarEntrega(checarEntrega(nowMs));
process.exit(decidirExit(r, rs, falhouEntrega));

}

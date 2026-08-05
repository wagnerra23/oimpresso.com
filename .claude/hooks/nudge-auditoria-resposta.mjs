#!/usr/bin/env node
// nudge-auditoria-resposta.mjs — Stop (advisory). Checklist de auditoria ANTES de responder.
//
// ── CONTRATO (a âncora — não a implementação) ────────────────────────────────
// Pedido de [M] em 2026-07-29: "crie um teste para checar essas opções sempre antes
// de me responder". As perguntas de auditoria nasceram de 3 erros MEDIDOS na mesma
// sessão — cada regra abaixo tem o caso real que a originou no .test.mjs:
//   1. citei biz=1 como tenant de teste lendo o proibicoes.md do checkout STALE
//      (canon vivo: tests/Support/WithSeededTenant.php SEEDED_TENANT_ID = 98)
//   2. afirmei "a tela ja esta ligada, e so abrir a URL" sem NUNCA ter aberto
//      (a rota dava 500 desde 2026-05-09 — Category::products() nunca existiu)
//   3. afirmei "US-PROD-023 esta todo" sem consultar o MCP (estava done)
//
// ── POR QUE NAO E HOOK NOVO DUPLICADO ───────────────────────────────────────
// Ja existem nudge-recommend-not-menu.mjs (R13 menu) e nudge-diagnosis-without-
// evidence.mjs (R1 causa). Este NAO reimplementa nenhum dos dois: cobre as lacunas
// medidas dos classificadores deles.
//   · o de menu exige /qual (voce|prefere|escolh|deles)/ e passou batido em
//     "qual DOS dois caminhos" — aqui nao ha regra de menu, e' problema dele.
//   · o de diagnostico so pega afirmacao de CAUSA ("a causa e..."). A regra ESTADO
//     abaixo cobre afirmacao de FUNCIONAMENTO ("funciona", "esta ligada"), que e'
//     categoria distinta e ficava sem dono.
// Ver .claude/hooks/_HOOKS-INDEX.md (gerado) pro mapa completo.
//
// ADVISORY: exit 0 SEMPRE (nunca bloqueia). Fail-open em qualquer erro.
// Selftest: node .claude/hooks/nudge-auditoria-resposta.mjs --selftest

import { fileURLToPath, pathToFileURL } from 'node:url';
import { readFileSync, existsSync } from 'node:fs';

/**
 * REGRAS — cada uma e' um par (gatilho, antidoto). So acusa quando o gatilho
 * dispara E o antidoto esta ausente. Ordem = ordem de exibicao.
 */
export const REGRAS = [
  {
    id: 'PONTEIRO',
    // Gatilho: afirmou algo sobre arquivo de codigo do repo.
    gatilho: /\b[\w./-]+\.(php|tsx|ts|jsx|mjs|blade\.php|yml|yaml)\b/i,
    // Antidoto: arquivo:linha OU leitura explicita do origin/main.
    antidoto: /(\.(php|tsx|ts|jsx|mjs|yml|yaml):\d+|git show origin\/main:|git log .*origin\/main|linha \d+)/i,
    aviso: 'PONTEIRO: voce citou arquivo de codigo sem arquivo:linha nem "git show origin/main:". Sem ponteiro, quem le nao consegue conferir — e o seu checkout pode estar milhares de commits atras.',
  },
  {
    id: 'ESTADO',
    // Gatilho: afirmou que algo FUNCIONA / esta no ar / abre.
    gatilho: /(est[aá] (ligad|no ar|funcionando|pront)|j[aá] est[aá] ligad|a tela (abre|carrega|funciona)|voc[eê] abre .{0,60}(a tela|e ela|e carrega)|funciona(ndo)? (hoje|agora|normal)|deploy(ado)? ok|smoke ok|ao vivo em prod|est[aá] pronto)/i,
    // Antidoto: verificacao DIRETA (nao basta a palavra "curl" solta).
    antidoto: /(screenshot|HTTP\/\d(\.\d)? \d{3}|^< HTTP|\d{3} (OK|Found|Moved)|laravel\.log|\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}|abri a tela|print da tela)/im,
    aviso: 'ESTADO: voce afirmou que algo FUNCIONA sem prova de verificacao direta (screenshot / status HTTP literal / linha de log). Foi assim que "a tela ja esta ligada" virou 500. Se nao verificou, diga "hipotese a confirmar".',
  },
  {
    id: 'TENANT',
    // Gatilho: propos validar/testar apontando biz=1 ou biz=4.
    gatilho: /(test(e|ar|es)|valid(ar|ou|a[cç][aã]o|ado)|smoke|seed).{0,60}(biz\s*=\s*[14]\b|business_id\s*=\s*[14]\b)|(biz\s*=\s*[14]\b|business_id\s*=\s*[14]\b).{0,60}(test(e|ar|es)|valid(ar|ou|a[cç][aã]o|ado)|smoke)/i,
    // Antidoto: reconheceu o tenant canonico vigente (98) ou leu do codigo.
    antidoto: /(biz\s*=\s*98|SEEDED_TENANT_ID|WithSeededTenant)/i,
    aviso: 'TENANT: voce apontou biz=1 ou biz=4 como alvo de teste. biz=1 e a WR2 (empresa real) e biz=4 e a Larissa (cliente). O tenant canonico vive no CODIGO: tests/Support/WithSeededTenant.php (SEEDED_TENANT_ID). Leia de la, nao da memoria.',
  },
  {
    id: 'BACKLOG',
    // Gatilho: propos executar trabalho novo.
    gatilho: /(posso (fazer|implementar|consertar|seguir)|vou (fazer|implementar|criar|abrir)|abro (o )?PR|pr[oó]ximo passo|sigo\?|confirma que sigo)/i,
    // Antidoto: citou backlog existente antes de propor.
    antidoto: /(US-[A-Z]{2,6}-\d{3}|tasks-list|tasks-detail|tasks-create|nenhuma task|nao existe US|sem US)/i,
    aviso: 'BACKLOG: voce propos trabalho sem citar se ja existe US/task. Consulte tasks-list antes — e diga explicitamente quando NAO existir. Backlog duplicado custa mais que a consulta.',
  },
  {
    id: 'BLAST',
    // Gatilho: garantiu que nao afeta nada.
    gatilho: /(n[aã]o (afeta|toca|encosta|muda) (nada|em nada)|n[aã]o [eé] tocad|zero risco|sem impacto|nada que a larissa|blast radius zero)/i,
    // Antidoto: diff medido OU lista nominal do que entra/nao entra.
    antidoto: /(git diff --stat|arquivos? tocad|1 arquivo|nao toco:|toco:)/i,
    aviso: 'BLAST: voce garantiu que "nao afeta nada" sem diff nem lista nominal de arquivos. Mostre `git diff --stat` ou enumere o que entra e o que NAO entra.',
  },
  {
    id: 'CI',
    // Gatilho: afirmou teste verde.
    gatilho: /(test(e|es) (verde|passou|passaram|ok)|suite verde|ci verde|0 failed|tudo passando)/i,
    // Antidoto: nomeou a lane e/ou contou assertions.
    antidoto: /(assertion|lane |lane:|\bpassed \(|gh pr checks)/i,
    aviso: 'CI: voce afirmou teste verde sem nomear a LANE nem contar ASSERTIONS. "0 failed" nao prova execucao — teste que pula sai exit 0 (LC-13). Diga qual lane e quantas assertions.',
  },
];

export const CABECALHO = '[nudge-auditoria-resposta] [AUDITORIA-RESPOSTA] Checklist pedido por [M] 2026-07-29 — pendencias nesta resposta:';

/** classificador PURO: devolve os ids das regras violadas (array vazio = limpo). */
export function auditar(text) {
  if (!text || typeof text !== 'string') return [];
  return REGRAS.filter((r) => r.gatilho.test(text) && !r.antidoto.test(text)).map((r) => r.id);
}

/** monta a mensagem advisory a partir dos ids violados. '' quando nao ha nada. */
export function montarAviso(ids) {
  if (!Array.isArray(ids) || ids.length === 0) return '';
  const linhas = REGRAS.filter((r) => ids.includes(r.id)).map((r) => `  - ${r.aviso}`);
  return [CABECALHO, ...linhas].join('\n');
}

/** último texto de mensagem assistant no transcript JSONL (últimas 50 linhas). */
export function lastAssistantText(transcriptPath) {
  if (!transcriptPath || !existsSync(transcriptPath)) return '';
  let lines;
  try { lines = readFileSync(transcriptPath, 'utf8').split('\n').filter(Boolean); } catch { return ''; }
  const tail = lines.slice(-50);
  for (let i = tail.length - 1; i >= 0; i--) {
    let o;
    try { o = JSON.parse(tail[i]); } catch { continue; }
    if (o && o.type === 'assistant' && o.message && Array.isArray(o.message.content)) {
      const t = o.message.content.filter((c) => c && c.type === 'text').map((c) => c.text).join('\n');
      if (t) return t;
    }
  }
  return '';
}

// ── stdin wrapper (fail-open em TUDO; SEMPRE exit 0 — advisory) ──────────────────

async function readStdin() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  return Buffer.concat(chunks).toString('utf8');
}

async function main() {
  try {
    let raw;
    try { raw = await readStdin(); } catch { process.exit(0); }
    if (!raw) process.exit(0);
    let input;
    try { input = JSON.parse(raw); } catch { process.exit(0); }
    const texto = lastAssistantText(input && input.transcript_path);
    const aviso = montarAviso(auditar(texto));
    if (aviso) console.log(aviso);
  } catch {
    // fail-open: nunca atrapalha a sessao
  }
  process.exit(0);
}

const isMain = process.argv[1] && pathToFileURL(process.argv[1]).href === import.meta.url;
if (isMain && !process.argv.includes('--selftest')) main();
if (isMain && process.argv.includes('--selftest')) {
  const casos = [
    ['ja esta ligada, e so abrir a URL', ['ESTADO']],
    ['rodei o smoke em biz=1 e passou', ['TENANT']],
    ['tudo limpo, sem afirmacao nenhuma', []],
  ];
  let ok = true;
  for (const [txt, esperado] of casos) {
    const got = auditar(txt);
    const bate = esperado.every((e) => got.includes(e));
    console.log(`${bate ? '[OK]  ' : '[FAIL]'} ${JSON.stringify(txt)} -> ${JSON.stringify(got)}`);
    if (!bate) ok = false;
  }
  process.exit(ok ? 0 : 1);
}

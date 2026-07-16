#!/usr/bin/env node
// @ts-check
/**
 * critica.mjs — passe crítico do pr-critic (a parte AGENTE; o roteamento é coleta.mjs).
 *
 * Contrato-âncora: arte 2026-06-22 §3 gap #5 (critic read-only GAP-SPEC × diff) +
 * grade-das-réguas 2026-07-09 ataque ①. Régua: Cognition — review agent com
 * CONTEXTO ZERO acha mais fundo. Por construção este processo nasce sem contexto:
 * roda em CI, recebe SÓ o diff do PR + os artefatos de contrato do manifesto.
 *
 * Pipeline: FINDER (1 chamada/grupo propõe candidatos) → trava de citação
 * (determinística) → VERIFICAÇÃO POR LENTES DIVERSAS (N lentes cegas votam;
 * sobrevive quem a maioria confirma). O finder acha; as lentes filtram o
 * falso-positivo que a citação sozinha não pega (citação REAL, contradição
 * inferida ERRADA). Padrão "perspective-diverse verify" (Workflow tool).
 *
 * Ressalvas de projeto (obrigatórias, ver README.md):
 *   - crítica ancora em CONTRATO CITADO (charter/casos/gap/map) — nunca opinião estética;
 *   - achado cuja citação não existe LITERALMENTE no contrato é DESCARTADO em código
 *     (trava anti-alucinação determinística) e contado no rodapé — nada some calado;
 *   - lentes e finder nascem CONTEXTO ZERO (só diff + contrato citado; nunca a árvore
 *     nem a sessão que gerou o PR); voto ausente NÃO conta como confirma (fail-safe);
 *   - NÃO valida cobertura (isso é o casos-gate/ADR 0264) — valida COERÊNCIA do diff;
 *   - advisory por lei (ADR 0314: required só Tier-0). Achados nunca falham o job.
 *
 * Custo controlado: só arquivos do diff (nunca a árvore), contratos truncados a
 * CORTE_CONTRATO, diff por arquivo a CORTE_DIFF, ≤ MAX_GRUPOS chamadas (coleta.mjs).
 *
 * Uso (CI):
 *   ANTHROPIC_API_KEY=... (ou OPENAI_API_KEY=...) node scripts/pr-critic/critica.mjs \
 *     --manifesto /tmp/manifesto.json --diff /tmp/pr.diff --out-dir storage/pr-critic
 *
 * Provider: auto — Anthropic se ANTHROPIC_API_KEY, senão OpenAI se OPENAI_API_KEY
 * (o repo já tem OPENAI_API_KEY nos secrets — decisão Wagner 2026-07-09).
 * Override: PR_CRITIC_PROVIDER=anthropic|openai · PR_CRITIC_MODEL=<id>.
 * Defaults: claude-opus-4-8 (anthropic) · gpt-4o (openai — gpt-4o-mini é o canon
 * de volume do repo, mas crítica de coerência pede o tier de raciocínio).
 */

import { createHash } from 'node:crypto';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { basename, join } from 'node:path';

const MODELO_DEFAULT = { anthropic: 'claude-opus-4-8', openai: 'gpt-4o' };

/** Resolve provider+modelo pelas envs disponíveis (exportado pra teste). */
export function resolverProvider(env = process.env) {
  let provider = env.PR_CRITIC_PROVIDER || null;
  if (!provider) {
    if (env.ANTHROPIC_API_KEY) provider = 'anthropic';
    else if (env.OPENAI_API_KEY) provider = 'openai';
  }
  if (!provider || !MODELO_DEFAULT[provider]) return null;
  if (provider === 'anthropic' && !env.ANTHROPIC_API_KEY) return null;
  if (provider === 'openai' && !env.OPENAI_API_KEY) return null;
  return { provider, modelo: env.PR_CRITIC_MODEL || MODELO_DEFAULT[provider] };
}
const CORTE_CONTRATO = 15_000; // chars por artefato de contrato
const CORTE_DIFF = 20_000;     // chars por arquivo do diff
const MARCADOR = '<!-- pr-critic-contrato -->';

const SYSTEM = `Você é um crítico adversarial de PR do ERP oimpresso (Laravel + Inertia/React), rodando em CI com CONTEXTO ZERO — você não participou da sessão que produziu este diff e não deve presumir intenções não escritas.

Sua ÚNICA pergunta: o DIFF contradiz, quebra ou ignora algo que o CONTRATO da tela/módulo declara? Contrato = charter (.charter.md), casos de uso (.casos.md), GAP-SPEC (-gap.md) e map (.map.json) fornecidos.

Regras duras:
1. Todo achado DEVE citar um trecho LITERAL do contrato (campo citacao_contrato, copiado verbatim de um dos artefatos — mínimo uma frase). Achado sem âncora literal será descartado mecanicamente.
2. PROIBIDO: opinião estética, preferência de estilo de código, sugestão de melhoria não ancorada em contrato, e re-validar cobertura de casos/testes (outro gate cuida disso).
3. Procure especificamente: comportamento removido/alterado que o contrato declara como invariante; ação do GAP-SPEC marcada "no-op"/"vivo à frente" que o diff regride; parte do map cujo arquivo vivo o diff altera de forma incompatível com o declarado; caso de uso (UC-*) cujo fluxo o diff quebra.
4. Se o diff é coerente com o contrato, devolva achados: [] — silêncio é um resultado válido e comum.
5. Severidade: alta = contradiz invariante explícito do contrato; media = provável drift (contrato diz X, diff sugere ~X); baixa = incoerência menor/documental.
6. Responda em PT-BR.`;

const SCHEMA = {
  type: 'json_schema',
  schema: {
    type: 'object',
    additionalProperties: false,
    required: ['achados'],
    properties: {
      achados: {
        type: 'array',
        items: {
          type: 'object',
          additionalProperties: false,
          required: ['arquivo', 'contrato', 'citacao_contrato', 'achado', 'severidade', 'confianca'],
          properties: {
            arquivo: { type: 'string', description: 'arquivo do diff onde está o problema' },
            contrato: { type: 'string', description: 'path do artefato de contrato citado' },
            citacao_contrato: { type: 'string', description: 'trecho LITERAL copiado do contrato' },
            achado: { type: 'string', description: 'o que o diff contradiz/quebra, em 1-3 frases' },
            severidade: { type: 'string', enum: ['alta', 'media', 'baixa'] },
            confianca: { type: 'string', enum: ['alta', 'media', 'baixa'] },
          },
        },
      },
      observacao: { type: 'string' },
    },
  },
};

// ── VERIFICAÇÃO POR LENTES DIVERSAS (perspective-diverse verify) ──────────────
// O finder PROPÕE (1 chamada/grupo). Cada achado candidato — já sobrevivente da
// trava de citação — é então VERIFICADO por N lentes CEGAS entre si e contexto-
// zero (só a citação + o hunk + a alegação, nunca a sessão nem os outros votos).
// Sobrevive só o achado que a MAIORIA confirma. Ataca o falso-positivo que a
// trava de citação NÃO pega: citação REAL, mas contradição INFERIDA errada (o
// verificador silencioso que erra é o que mata a confiança — lição #4038).
// Padrão "perspective-diverse verify" do próprio Workflow tool. Advisory (0314).

const PREAMBULO_LENTE = `Você é um VERIFICADOR CEGO de UM achado de crítica de PR do ERP oimpresso (Laravel + Inertia/React), rodando em CI com CONTEXTO ZERO: não viu a sessão que gerou o diff, não viu os outros verificadores, não pode presumir intenção não escrita. Você recebe só três coisas: (a) uma CITAÇÃO literal do contrato da tela/módulo, (b) o TRECHO do diff, (c) a ALEGAÇÃO do finder de que o diff contradiz a citação. Julgue SÓ pela sua lente. Responda em PT-BR.`;

/** As lentes — cada uma cega às outras, ângulo distinto de refutação. */
export const LENTES = [
  {
    id: 'contradicao-literal',
    rotulo: 'contradição literal',
    system: `${PREAMBULO_LENTE}

LENTE — contradição literal: o TRECHO DO DIFF realmente MUDA ou REMOVE o comportamento que a CITAÇÃO descreve? Se o hunk não toca no que a citação fala (a alegação é inferência sem base no diff mostrado), responda "refuta". Só "confirma" se o hunk literalmente altera/remove o que a citação declara.`,
  },
  {
    id: 'regressao-vs-adicao',
    rotulo: 'regressão vs adição',
    system: `${PREAMBULO_LENTE}

LENTE — regressão vs adição: a CITAÇÃO declara um INVARIANTE/fluxo que o diff QUEBRA (regressão real), ou o diff apenas ADICIONA/reorganiza algo que a citação NÃO proíbe? Só "confirma" se for regressão de um invariante ou caso de uso declarado. Adição neutra, refactor equivalente ou mudança que a citação não veda = "refuta".`,
  },
  {
    id: 'advogado-do-diff',
    rotulo: 'advogado do diff',
    system: `${PREAMBULO_LENTE}

LENTE — advogado do diff (cético contra falso-positivo): seu trabalho é DEFENDER o diff. Existe uma leitura plausível em que o diff é COERENTE com a citação? Na dúvida, responda "refuta". Só "confirma" se, mesmo tentando defender o diff, ele CLARAMENTE contradiz a citação.`,
  },
];

/** Nº mínimo de "confirma" (de LENTES.length) pra um achado sobreviver. Maioria. */
export const MIN_CONFIRMA = 2;
/** Teto de achados verificados por grupo (custo). Excedente vai como não-verificado. */
export const MAX_VERIFICAR = 8;

const VOTO_SCHEMA = {
  type: 'json_schema',
  schema: {
    type: 'object',
    additionalProperties: false,
    required: ['veredito', 'razao'],
    properties: {
      veredito: { type: 'string', enum: ['confirma', 'refuta'] },
      razao: { type: 'string', description: 'por que confirma/refuta, 1-2 frases ancoradas no diff+citação' },
    },
  },
};

/**
 * Agregação de votos (PURA — testada bite/release). Recebe a lista de votos
 * ({veredito}) e devolve o veredito final. Falta de voto (lente que morreu na
 * rede) NÃO conta como confirma — fail-safe contra falso-positivo. Sobrevive só
 * com confirma >= minConfirma.
 */
export function agregarVotos(votos, { minConfirma = MIN_CONFIRMA } = {}) {
  const validos = (votos || []).filter((v) => v && (v.veredito === 'confirma' || v.veredito === 'refuta'));
  const confirma = validos.filter((v) => v.veredito === 'confirma').length;
  const refuta = validos.filter((v) => v.veredito === 'refuta').length;
  return { confirma, refuta, votos_validos: validos.length, min_confirma: minConfirma, sobrevive: confirma >= minConfirma };
}

/** Prompt de UMA lente pra UM achado — só citação + hunk + alegação (contexto zero). */
export function montarPromptLente(achado, hunk) {
  const t = trunca(hunk || '[sem hunk no diff para este arquivo]', CORTE_DIFF, achado.arquivo);
  return [
    `## CITAÇÃO DO CONTRATO (${achado.contrato})`,
    `“${achado.citacao_contrato}”`,
    '',
    `## TRECHO DO DIFF (${achado.arquivo})`,
    '```diff',
    t.texto,
    '```',
    '',
    `## ALEGAÇÃO DO FINDER`,
    achado.achado,
    '',
    'Confirma ou refuta a alegação, só pela sua lente?',
  ].join('\n');
}

/** Roda as N lentes (cegas entre si) sobre um achado; devolve votos + agregação. */
async function verificarAchado(cfg, achado, hunk) {
  const userContent = montarPromptLente(achado, hunk);
  const votos = [];
  for (const lente of LENTES) {
    const r = await chamarAgente(cfg, {
      system: lente.system,
      schema: VOTO_SCHEMA,
      userContent,
      // recusa do provider = voto ausente (fail-safe: não vira "confirma")
      vazioSeRecusa: (m) => ({ veredito: 'refuta', razao: `recusa do provider tratada como refuta (${m})` }),
    });
    votos.push({ lente: lente.id, rotulo: lente.rotulo, veredito: r.veredito, razao: r.razao });
  }
  return { votos, agg: agregarVotos(votos) };
}

function argVal(flag, def = null) {
  const i = process.argv.indexOf(flag);
  return i !== -1 ? process.argv[i + 1] : def;
}

function trunca(texto, limite, rotulo) {
  if (texto.length <= limite) return { texto, truncado: false };
  return { texto: `${texto.slice(0, limite)}\n\n[TRUNCADO em ${limite} chars — ${rotulo}]`, truncado: true };
}

/** Divide um unified diff em blocos por arquivo (chave = path do lado b/). */
export function separarDiffPorArquivo(diffCompleto) {
  const blocos = new Map();
  const partes = diffCompleto.split(/^diff --git /m).filter(Boolean);
  for (const p of partes) {
    const m = p.match(/ b\/(\S+)/);
    if (m) blocos.set(m[1], 'diff --git ' + p);
  }
  return blocos;
}

/** Normaliza whitespace pra comparação de citação (anti-alucinação). */
export function normaliza(s) {
  return s.replace(/\s+/g, ' ').trim().toLowerCase();
}

/**
 * Trava determinística: mantém só achados cuja citação existe literalmente
 * (modulo whitespace) no artefato de contrato apontado — e com tamanho mínimo
 * pra não validar âncora vazia.
 */
export function filtrarPorCitacao(achados, conteudoPorContrato) {
  const validos = [];
  const descartados = [];
  for (const a of achados) {
    const corpo = conteudoPorContrato.get(a.contrato);
    const cit = normaliza(a.citacao_contrato || '');
    if (corpo && cit.length >= 15 && normaliza(corpo).includes(cit)) validos.push(a);
    else descartados.push(a);
  }
  return { validos, descartados };
}

async function comRetry(nome, fazer) {
  for (let tentativa = 0; tentativa < 3; tentativa++) {
    const res = await fazer();
    if (res.status === 429 || res.status >= 500) {
      const espera = Number(res.headers.get('retry-after')) * 1000 || 2000 * (tentativa + 1);
      console.log(`[critica] ${nome} HTTP ${res.status} — retry em ${espera}ms`);
      await new Promise((r) => setTimeout(r, espera));
      continue;
    }
    if (!res.ok) throw new Error(`${nome} ${res.status}: ${(await res.text()).slice(0, 500)}`);
    return res.json();
  }
  throw new Error(`${nome}: 3 tentativas esgotadas (429/5xx)`);
}

/** OpenAI json_schema strict exige TODAS as props em `required` — deriva a variante. */
function comTodasRequired(schemaObj) {
  const clone = structuredClone(schemaObj);
  clone.required = Object.keys(clone.properties || {});
  return clone;
}

async function chamarAnthropic(modelo, { system, schema, userContent, vazioSeRecusa }) {
  const msg = await comRetry('Anthropic API', () => fetch('https://api.anthropic.com/v1/messages', {
    method: 'POST',
    headers: {
      'x-api-key': process.env.ANTHROPIC_API_KEY,
      'anthropic-version': '2023-06-01',
      'content-type': 'application/json',
    },
    body: JSON.stringify({
      model: modelo,
      max_tokens: 16000,
      thinking: { type: 'adaptive' },
      system,
      output_config: { format: schema },
      messages: [{ role: 'user', content: userContent }],
    }),
  }));
  if (msg.stop_reason === 'refusal') return vazioSeRecusa('stop_reason=refusal');
  if (msg.stop_reason === 'max_tokens') console.log('[critica] AVISO: stop_reason=max_tokens — resposta pode estar truncada');
  const texto = (msg.content || []).filter((b) => b.type === 'text').map((b) => b.text).join('');
  return JSON.parse(texto);
}

async function chamarOpenAI(modelo, { system, schema, userContent, vazioSeRecusa }) {
  const schemaStrict = comTodasRequired(schema.schema);
  const msg = await comRetry('OpenAI API', () => fetch('https://api.openai.com/v1/chat/completions', {
    method: 'POST',
    headers: {
      authorization: `Bearer ${process.env.OPENAI_API_KEY}`,
      'content-type': 'application/json',
    },
    body: JSON.stringify({
      model: modelo,
      max_tokens: 8000,
      messages: [
        { role: 'system', content: system },
        { role: 'user', content: userContent },
      ],
      response_format: { type: 'json_schema', json_schema: { name: 'pr_critic_saida', strict: true, schema: schemaStrict } },
    }),
  }));
  const escolha = msg.choices && msg.choices[0];
  if (!escolha) throw new Error('OpenAI API: resposta sem choices');
  if (escolha.finish_reason === 'length') console.log('[critica] AVISO: finish_reason=length — resposta pode estar truncada');
  if (escolha.message.refusal) return vazioSeRecusa(`refusal: ${escolha.message.refusal}`);
  return JSON.parse(escolha.message.content);
}

/** Chamada genérica de agente (finder ou lente) — o `system`+`schema` vêm de fora. */
function chamarAgente(cfg, opts) {
  return cfg.provider === 'anthropic' ? chamarAnthropic(cfg.modelo, opts) : chamarOpenAI(cfg.modelo, opts);
}

/** Finder: 1 chamada por grupo (SYSTEM+SCHEMA do critic). Recusa → sem achados. */
function chamarFinder(cfg, userContent) {
  return chamarAgente(cfg, {
    system: SYSTEM,
    schema: SCHEMA,
    userContent,
    vazioSeRecusa: (m) => ({ achados: [], observacao: `chamada recusada (${m})` }),
  });
}

function montarPromptGrupo(grupo, conteudoPorContrato, diffPorArquivo) {
  const partes = [`# Grupo sob crítica: ${grupo.id} (módulo ${grupo.modulo})`];
  let truncados = 0;
  partes.push('\n## CONTRATOS (única base válida pra achados)\n');
  for (const [tipo, lista] of Object.entries(grupo.contratos)) {
    for (const p of lista) {
      const corpo = conteudoPorContrato.get(p);
      if (!corpo) continue;
      const t = trunca(corpo, CORTE_CONTRATO, p);
      if (t.truncado) truncados++;
      partes.push(`### [${tipo}] ${p}\n\n${t.texto}\n`);
    }
  }
  partes.push('\n## DIFF DO PR (só os arquivos deste grupo)\n');
  for (const arq of grupo.arquivos) {
    const bloco = diffPorArquivo.get(arq);
    if (!bloco) { partes.push(`### ${arq}\n\n[sem hunk no diff — possivelmente rename/binário]\n`); continue; }
    const t = trunca(bloco, CORTE_DIFF, arq);
    if (t.truncado) truncados++;
    partes.push(`### ${arq}\n\n\`\`\`diff\n${t.texto}\n\`\`\`\n`);
  }
  return { prompt: partes.join('\n'), truncados };
}

const EMOJI = { alta: '🔴', media: '🟠', baixa: '🟡' };
export const MARCADOR_DADOS = '<!-- pr-critic-data:';

/** ID estável de um achado (pra casar depois no medidor de precisão). */
export function idAchado(a) {
  return createHash('sha1').update(`${a.arquivo}|${a.contrato}|${normaliza(a.citacao_contrato || '')}`).digest('hex').slice(0, 12);
}

/** Resumo do voto pra exibição: "✓ 2/3 lentes (refuta: advogado do diff)". */
function resumoVoto(a) {
  if (!a.votos || !a.votos.length) return null;
  const confirma = a.votos.filter((v) => v.veredito === 'confirma');
  const refuta = a.votos.filter((v) => v.veredito === 'refuta');
  const cauda = refuta.length ? ` (refuta: ${refuta.map((v) => v.rotulo).join(', ')})` : '';
  return `✓ ${confirma.length}/${a.votos.length} lentes${cauda}`;
}

/**
 * Bloco machine-readable EMBUTIDO no comentário — o registro durável por-PR que
 * o medidor de precisão (precisao.mjs) lê depois (o comentário persiste no gh pra
 * sempre; não depende de artifact que expira). Só os achados SOBREVIVENTES entram.
 */
export function montarBlocoDados({ resultados, modelo }) {
  const achados = [];
  for (const r of resultados) {
    for (const a of r.achados) {
      achados.push({ id: idAchado(a), arquivo: a.arquivo, severidade: a.severidade, verificado: !!(a.votos && a.votos.length) });
    }
  }
  return `${MARCADOR_DADOS} ${JSON.stringify({ v: 1, modelo, achados })} -->`;
}

export function montarComentario({ resultados, semContrato, descartadosCap, totalDescartadosCitacao, totalDescartadosVoto = 0, totalNaoVerificados = 0, totalTruncados, modelo }) {
  const linhas = [MARCADOR, '## 🧿 pr-critic — coerência diff × contrato (advisory)', ''];
  const totalAchados = resultados.reduce((n, r) => n + r.achados.length, 0);
  if (totalAchados === 0) {
    linhas.push('Nenhuma incoerência entre o diff e os contratos (charter/casos/gap/map) dos grupos analisados.');
  }
  for (const r of resultados) {
    if (!r.achados.length) continue;
    linhas.push(`### ${r.grupo}`, '');
    for (const a of r.achados) {
      const voto = resumoVoto(a);
      const selo = voto ? ` · ${voto}` : (a.verificado === false ? ' · ⚠️ não verificado (teto de custo)' : '');
      linhas.push(`- ${EMOJI[a.severidade] || '⚪'} **${a.severidade}** · \`${a.arquivo}\` · confiança ${a.confianca}${selo}`);
      linhas.push(`  ${a.achado}`);
      linhas.push(`  > contrato [\`${a.contrato}\`]: “${a.citacao_contrato.slice(0, 300)}”`);
      linhas.push('');
    }
  }
  linhas.push('---', '<details><summary>Como ler / limites deste critic</summary>', '');
  linhas.push(`- **Advisory** (ADR 0314 — required só Tier-0): achados NÃO bloqueiam o merge; são insumo pro review humano.`);
  linhas.push(`- Critic roda com **contexto zero** (só diff + contratos) e só pode apontar o que o **contrato cita** — não valida cobertura (casos-gate) nem estética.`);
  linhas.push(`- Cada achado passa por **${LENTES.length} lentes cegas** (${LENTES.map((l) => l.rotulo).join(' · ')}); sobrevive quem a maioria (≥${MIN_CONFIRMA}) confirma — reduz falso-positivo sem perder recall.`);
  linhas.push(`- Grupos analisados: ${resultados.length} · sem contrato (não analisados): ${semContrato.length ? semContrato.map((g) => `\`${g.id}\``).join(', ') : 'nenhum'}.`);
  if (descartadosCap.length) linhas.push(`- ⚠️ Cortados pelo teto de grupos (NÃO analisados): ${descartadosCap.map((g) => `\`${g.id}\``).join(', ')}.`);
  if (totalDescartadosCitacao) linhas.push(`- ${totalDescartadosCitacao} achado(s) descartado(s) pela trava de citação (âncora não encontrada literalmente no contrato).`);
  if (totalDescartadosVoto) linhas.push(`- ${totalDescartadosVoto} achado(s) descartado(s) pelas lentes (maioria refutou — provável falso-positivo).`);
  if (totalNaoVerificados) linhas.push(`- ${totalNaoVerificados} achado(s) exibido(s) SEM verificação por lentes (teto de ${MAX_VERIFICAR}/grupo) — trate com cautela.`);
  if (totalTruncados) linhas.push(`- ${totalTruncados} artefato(s)/diff(s) truncado(s) por limite de custo — cobertura parcial nesses pontos.`);
  linhas.push(`- Modelo: \`${modelo}\` · mecanismo: \`scripts/pr-critic/\` (roteamento determinístico + finder + lentes).`);
  linhas.push('', '</details>', '', montarBlocoDados({ resultados, modelo }));
  return linhas.join('\n');
}

async function main() {
  const cfg = resolverProvider();
  if (!cfg) {
    console.log('[critica] nenhuma chave (ANTHROPIC_API_KEY/OPENAI_API_KEY) — passe crítico pulado (advisory).');
    process.exit(0);
  }
  const manifesto = JSON.parse(readFileSync(argVal('--manifesto'), 'utf8'));
  const diffPorArquivo = separarDiffPorArquivo(readFileSync(argVal('--diff'), 'utf8'));
  const outDir = argVal('--out-dir', 'storage/pr-critic');
  mkdirSync(outDir, { recursive: true });

  if (!manifesto.grupos.length) {
    console.log('[critica] nenhum grupo com contrato no manifesto — nada a criticar.');
    process.exit(0);
  }

  // carrega contratos 1x (trava de citação usa o MESMO conteúdo enviado ao agente)
  const conteudoPorContrato = new Map();
  for (const g of manifesto.grupos) {
    for (const lista of Object.values(g.contratos)) {
      for (const p of lista) {
        if (conteudoPorContrato.has(p)) continue;
        try { conteudoPorContrato.set(p, readFileSync(p, 'utf8')); }
        catch { console.log(`[critica] AVISO: contrato ilegível: ${p}`); }
      }
    }
  }

  const resultados = [];
  let totalDescartadosCitacao = 0;
  let totalDescartadosVoto = 0;
  let totalNaoVerificados = 0;
  let totalTruncados = 0;
  for (const grupo of manifesto.grupos) {
    const { prompt, truncados } = montarPromptGrupo(grupo, conteudoPorContrato, diffPorArquivo);
    totalTruncados += truncados;
    console.log(`[critica] ${grupo.id} — finder ${cfg.provider}:${cfg.modelo} (${Math.round(prompt.length / 1024)}KB de prompt)`);
    const resposta = await chamarFinder(cfg, prompt);
    const { validos, descartados } = filtrarPorCitacao(resposta.achados || [], conteudoPorContrato);
    if (descartados.length) console.log(`[critica]   ${descartados.length} achado(s) descartado(s) pela trava de citação`);
    totalDescartadosCitacao += descartados.length;

    // ── verificação por lentes diversas (só nos candidatos citação-válidos) ──
    const sobreviventes = [];
    for (let i = 0; i < validos.length; i++) {
      const a = validos[i];
      if (i >= MAX_VERIFICAR) {
        // teto de custo: exibe sem verificar, nunca some calado nem promove calado
        totalNaoVerificados++;
        sobreviventes.push({ ...a, verificado: false });
        continue;
      }
      const hunk = diffPorArquivo.get(a.arquivo);
      const { votos, agg } = await verificarAchado(cfg, a, hunk);
      console.log(`[critica]   lentes «${(a.achado || '').slice(0, 60)}…» → ${agg.confirma}/${votos.length} confirma ⇒ ${agg.sobrevive ? 'MANTÉM' : 'descarta'}`);
      if (agg.sobrevive) sobreviventes.push({ ...a, verificado: true, votos });
      else totalDescartadosVoto++;
    }
    resultados.push({ grupo: grupo.id, achados: sobreviventes, observacao: resposta.observacao || null });
  }

  const achadosPath = join(outDir, 'achados.json');
  writeFileSync(achadosPath, JSON.stringify({
    modelo: `${cfg.provider}:${cfg.modelo}`,
    lentes: LENTES.map((l) => l.id),
    min_confirma: MIN_CONFIRMA,
    resultados,
    descartados_citacao: totalDescartadosCitacao,
    descartados_voto: totalDescartadosVoto,
    nao_verificados: totalNaoVerificados,
  }, null, 2) + '\n');

  const comentario = montarComentario({
    resultados,
    semContrato: manifesto.sem_contrato || [],
    descartadosCap: manifesto.descartados || [],
    totalDescartadosCitacao,
    totalDescartadosVoto,
    totalNaoVerificados,
    totalTruncados,
    modelo: `${cfg.provider}:${cfg.modelo}`,
  });
  writeFileSync(join(outDir, 'comentario.md'), comentario + '\n');
  const total = resultados.reduce((n, r) => n + r.achados.length, 0);
  console.log(`[critica] ${total} achado(s) sobrevivente(s) (após citação + ${LENTES.length} lentes) · ${totalDescartadosVoto} refutado(s) por voto · saída em ${outDir}/`);
}

if (process.argv[1] && import.meta.url.endsWith(basename(process.argv[1]))) {
  main().catch((e) => { console.error(`[critica] ERRO: ${e.message}`); process.exit(1); });
}

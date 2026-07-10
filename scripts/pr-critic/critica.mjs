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
 * Ressalvas de projeto (obrigatórias, ver README.md):
 *   - crítica ancora em CONTRATO CITADO (charter/casos/gap/map) — nunca opinião estética;
 *   - achado cuja citação não existe LITERALMENTE no contrato é DESCARTADO em código
 *     (trava anti-alucinação determinística) e contado no rodapé — nada some calado;
 *   - NÃO valida cobertura (isso é o casos-gate/ADR 0264) — valida COERÊNCIA do diff;
 *   - advisory por lei (ADR 0314: required só Tier-0). Achados nunca falham o job.
 *
 * Custo controlado: só arquivos do diff (nunca a árvore), contratos truncados a
 * CORTE_CONTRATO, diff por arquivo a CORTE_DIFF, ≤ MAX_GRUPOS chamadas (coleta.mjs).
 *
 * Uso (CI):
 *   ANTHROPIC_API_KEY=... node scripts/pr-critic/critica.mjs \
 *     --manifesto /tmp/manifesto.json --diff /tmp/pr.diff --out-dir storage/pr-critic
 * Modelo: env PR_CRITIC_MODEL (default claude-opus-4-8).
 */

import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { basename, join } from 'node:path';

const MODELO = process.env.PR_CRITIC_MODEL || 'claude-opus-4-8';
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

async function chamarAnthropic(userContent) {
  const body = {
    model: MODELO,
    max_tokens: 16000,
    thinking: { type: 'adaptive' },
    system: SYSTEM,
    output_config: { format: SCHEMA },
    messages: [{ role: 'user', content: userContent }],
  };
  for (let tentativa = 0; tentativa < 3; tentativa++) {
    const res = await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: {
        'x-api-key': process.env.ANTHROPIC_API_KEY,
        'anthropic-version': '2023-06-01',
        'content-type': 'application/json',
      },
      body: JSON.stringify(body),
    });
    if (res.status === 429 || res.status >= 500) {
      const espera = Number(res.headers.get('retry-after')) * 1000 || 2000 * (tentativa + 1);
      console.log(`[critica] HTTP ${res.status} — retry em ${espera}ms`);
      await new Promise((r) => setTimeout(r, espera));
      continue;
    }
    if (!res.ok) throw new Error(`Anthropic API ${res.status}: ${(await res.text()).slice(0, 500)}`);
    const msg = await res.json();
    if (msg.stop_reason === 'refusal') return { achados: [], observacao: 'chamada recusada pelos classifiers (stop_reason=refusal)' };
    const texto = (msg.content || []).filter((b) => b.type === 'text').map((b) => b.text).join('');
    if (msg.stop_reason === 'max_tokens') console.log('[critica] AVISO: stop_reason=max_tokens — resposta pode estar truncada');
    return JSON.parse(texto);
  }
  throw new Error('Anthropic API: 3 tentativas esgotadas (429/5xx)');
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

export function montarComentario({ resultados, semContrato, descartadosCap, totalDescartadosCitacao, totalTruncados, modelo }) {
  const linhas = [MARCADOR, '## 🧿 pr-critic — coerência diff × contrato (advisory)', ''];
  const totalAchados = resultados.reduce((n, r) => n + r.achados.length, 0);
  if (totalAchados === 0) {
    linhas.push('Nenhuma incoerência entre o diff e os contratos (charter/casos/gap/map) dos grupos analisados.');
  }
  for (const r of resultados) {
    if (!r.achados.length) continue;
    linhas.push(`### ${r.grupo}`, '');
    for (const a of r.achados) {
      linhas.push(`- ${EMOJI[a.severidade] || '⚪'} **${a.severidade}** · \`${a.arquivo}\` · confiança ${a.confianca}`);
      linhas.push(`  ${a.achado}`);
      linhas.push(`  > contrato [\`${a.contrato}\`]: “${a.citacao_contrato.slice(0, 300)}”`);
      linhas.push('');
    }
  }
  linhas.push('---', '<details><summary>Como ler / limites deste critic</summary>', '');
  linhas.push(`- **Advisory** (ADR 0314 — required só Tier-0): achados NÃO bloqueiam o merge; são insumo pro review humano.`);
  linhas.push(`- Critic roda com **contexto zero** (só diff + contratos) e só pode apontar o que o **contrato cita** — não valida cobertura (casos-gate) nem estética.`);
  linhas.push(`- Grupos analisados: ${resultados.length} · sem contrato (não analisados): ${semContrato.length ? semContrato.map((g) => `\`${g.id}\``).join(', ') : 'nenhum'}.`);
  if (descartadosCap.length) linhas.push(`- ⚠️ Cortados pelo teto de grupos (NÃO analisados): ${descartadosCap.map((g) => `\`${g.id}\``).join(', ')}.`);
  if (totalDescartadosCitacao) linhas.push(`- ${totalDescartadosCitacao} achado(s) descartado(s) pela trava de citação (âncora não encontrada literalmente no contrato).`);
  if (totalTruncados) linhas.push(`- ${totalTruncados} artefato(s)/diff(s) truncado(s) por limite de custo — cobertura parcial nesses pontos.`);
  linhas.push(`- Modelo: \`${modelo}\` · mecanismo: \`scripts/pr-critic/\` (roteamento determinístico + agente).`);
  linhas.push('', '</details>');
  return linhas.join('\n');
}

async function main() {
  if (!process.env.ANTHROPIC_API_KEY) {
    console.log('[critica] ANTHROPIC_API_KEY ausente — passe crítico pulado (advisory).');
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
  let totalTruncados = 0;
  for (const grupo of manifesto.grupos) {
    const { prompt, truncados } = montarPromptGrupo(grupo, conteudoPorContrato, diffPorArquivo);
    totalTruncados += truncados;
    console.log(`[critica] ${grupo.id} — chamando ${MODELO} (${Math.round(prompt.length / 1024)}KB de prompt)`);
    const resposta = await chamarAnthropic(prompt);
    const { validos, descartados } = filtrarPorCitacao(resposta.achados || [], conteudoPorContrato);
    if (descartados.length) console.log(`[critica]   ${descartados.length} achado(s) descartado(s) pela trava de citação`);
    totalDescartadosCitacao += descartados.length;
    resultados.push({ grupo: grupo.id, achados: validos, observacao: resposta.observacao || null });
  }

  const achadosPath = join(outDir, 'achados.json');
  writeFileSync(achadosPath, JSON.stringify({ modelo: MODELO, resultados, descartados_citacao: totalDescartadosCitacao }, null, 2) + '\n');

  const comentario = montarComentario({
    resultados,
    semContrato: manifesto.sem_contrato || [],
    descartadosCap: manifesto.descartados || [],
    totalDescartadosCitacao,
    totalTruncados,
    modelo: MODELO,
  });
  writeFileSync(join(outDir, 'comentario.md'), comentario + '\n');
  const total = resultados.reduce((n, r) => n + r.achados.length, 0);
  console.log(`[critica] ${total} achado(s) confirmado(s) por citação · saída em ${outDir}/`);
}

if (process.argv[1] && import.meta.url.endsWith(basename(process.argv[1]))) {
  main().catch((e) => { console.error(`[critica] ERRO: ${e.message}`); process.exit(1); });
}

#!/usr/bin/env node
// @ts-check
/**
 * system-map-ia.test.mjs — self-test adversarial do núcleo puro da seção "Camada de IA"
 * do system-map.mjs (a matriz gerada do PAINEL-SISTEMA).
 *
 * POR QUE EXISTE: a seção nasceu pra matar números escritos à mão num diagrama de
 * arquitetura (§5 2026-07-17 — "doc canônico não repete número que outro sistema sabe
 * melhor"). Um extrator que conta ERRADO é PIOR que o número à mão, porque vem com
 * selo de "derivado". Então ele tem que provar a mordida em fixture, sem git e sem FS.
 *
 * Prova que o núcleo:
 *   · MORDE   — o caso REAL do erro humano: `caching.embeddings` NÃO é provider
 *               (a contagem à mão dizia 16; são 15).
 *   · MORDE   — agente FORA de `Ai/Agents/` é contado pelo contrato (varredura por
 *               pasta o perderia — é a razão de o contrato ser a fonte preferida).
 *   · LIBERA  — dublê de teste e classe abstrata NÃO contam como peça viva.
 *   · não confunde o default GLOBAL com `models.text.default` (que vem depois).
 *   · não conta nome parecido (`AgentAlgo`, `RerankerFactory`) por casar prefixo.
 *
 * Determinístico: strings inline, zero I/O.
 * Uso: node scripts/governance/system-map-ia.test.mjs
 */
import { classificarIa, parseProvidersAi, linhaAgentes } from './system-map.mjs';

let fails = 0;
const ok = (cond, msg) => { if (cond) console.log(`  ✓ ${msg}`); else { console.error(`  ✗ ${msg}`); fails++; } };

console.log('\n  system-map · camada de IA — self-test do núcleo puro\n');

// ── A) MORDE: o erro humano real — `caching.embeddings` não é provider ────────
{
  const cfg = `<?php return [
    'default' => 'openai',
    'default_for_images' => 'gemini',
    'caching' => [
        'embeddings' => [ 'cache' => false ],
    ],
    'providers' => [
        'anthropic' => [ 'driver' => 'anthropic', 'key' => env('K') ],
        'openai' => [
            'driver' => 'openai',
            'models' => [ 'text' => [ 'default' => 'gpt-4o-mini' ] ],
        ],
    ],
  ];`;
  const r = parseProvidersAi(cfg);
  ok(r.provs.length === 2 && r.provs.join(',') === 'anthropic,openai',
    `MORDE: bloco caching.embeddings NÃO vira provider — 2 providers (obtido: ${r.provs.length} → ${r.provs.join(',')})`);
  ok(r.defaultProv === 'openai',
    `default GLOBAL = openai, não o models.text.default que vem depois (obtido: ${r.defaultProv})`);
}

// ── A2) MORDE: `driver` fora do bloco providers NÃO infla a conta ─────────────
{
  const cfg = `<?php return [
    'default' => 'openai',
    'log' => [ 'driver' => 'daily' ],
    'queue' => [ 'driver' => 'database' ],
    'providers' => [
        'openai' => [ 'driver' => 'openai' ],
    ],
  ];`;
  const r = parseProvidersAi(cfg);
  ok(r.provs.length === 1 && r.provs[0] === 'openai',
    `MORDE: 'driver' de log/queue fora de providers não conta (obtido: ${r.provs.length} → ${r.provs.join(',')})`);
}

// ── B) controle negativo: arquivo sem providers não inventa nada ──────────────
{
  const r = parseProvidersAi(`<?php return [ 'algo' => true ];`);
  ok(r.provs.length === 0 && r.defaultProv === null,
    `LIBERA: config sem providers → 0 e default null (obtido: ${r.provs.length} / ${r.defaultProv})`);
}

// ── C) MORDE: agente fora da pasta canônica é achado pelo CONTRATO ────────────
{
  const r = classificarIa([
    { rel: 'Modules/Jana/Ai/Agents/ChatCopilotoAgent.php', txt: 'class ChatCopilotoAgent implements Agent {}' },
    { rel: 'Modules/Crm/Services/EscondidoAgent.php', txt: 'class EscondidoAgent implements Agent {}' },
  ]);
  ok(r.agente.length === 2,
    `MORDE: agente fora de Ai/Agents/ é contado pelo contrato (obtido: ${r.agente.length})`);
}

// ── D) LIBERA: dublê de teste e classe abstrata não são peça viva ─────────────
{
  const r = classificarIa([
    { rel: 'Modules/Jana/Tests/Feature/FakeAgent.php', txt: 'class FakeAgent implements Agent {}' },
    { rel: 'Modules/Jana/Ai/Agents/BaseAgent.php', txt: 'abstract class BaseAgent implements Agent {}' },
    { rel: 'Modules/Jana/Ai/Agents/RealAgent.php', txt: 'class RealAgent implements Agent {}' },
  ]);
  ok(r.agente.length === 1 && r.agente[0].endsWith('RealAgent.php'),
    `LIBERA: dublê em Tests/ e classe abstrata fora; só o real conta (obtido: ${r.agente.length})`);
}

// ── E) não casa nome PARECIDO por prefixo (o \\b existe por isso) ──────────────
{
  const r = classificarIa([
    { rel: 'Modules/X/AgentAlgo.php', txt: 'class Y implements AgentAlgoRunner {}' },
    { rel: 'Modules/X/Fac.php', txt: 'class F implements RerankerFactory {}' },
  ]);
  ok(r.agente.length === 0 && r.reranker.length === 0,
    `LIBERA: AgentAlgoRunner/RerankerFactory não contam como Agent/Reranker (obtido: ${r.agente.length}/${r.reranker.length})`);
}

// ── F) os três contratos são classificados separadamente ─────────────────────
{
  const r = classificarIa([
    { rel: 'Modules/Jana/Services/Memoria/MeilisearchDriver.php', txt: 'class M implements MemoriaContrato {}' },
    { rel: 'Modules/Jana/Services/Retrieval/RrfReranker.php', txt: 'class R implements Reranker {}' },
    { rel: 'Modules/Jana/Ai/Agents/A.php', txt: 'class A implements Agent {}' },
  ]);
  ok(r.memoria.length === 1 && r.reranker.length === 1 && r.agente.length === 1,
    `classifica os 3 contratos separadamente (obtido: mem=${r.memoria.length} rrk=${r.reranker.length} ag=${r.agente.length})`);
}

// ── G) o estado "não medido" nunca vira "0" nem acusa drift falso ────────────
{
  const semGit = linhaAgentes({ semInstrumento: true, agentes: 0, agentesPorPasta: 22 });
  ok(semGit.includes('não medido') && !semGit.includes('**0**') && !semGit.includes('⚠️'),
    'LIBERA: sem git grep → "não medido", nunca "0" e nunca alarme de drift');

  const integro = linhaAgentes({ semInstrumento: false, agentes: 22, agentesPorPasta: 22 });
  ok(integro.includes('**22**') && integro.includes('convenção íntegra') && !integro.includes('⚠️'),
    'LIBERA: contrato e pasta batem → sem alarme');

  const drift = linhaAgentes({ semInstrumento: false, agentes: 23, agentesPorPasta: 22 });
  ok(drift.includes('⚠️') && drift.includes('**23**') && drift.includes('**22**'),
    'MORDE: agente fora da pasta canônica → alarme de drift com os dois números');
}

console.log(fails === 0 ? '\n  OK — núcleo da camada de IA morde e não falsa-positiva.\n' : `\n  ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);

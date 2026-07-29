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
import {
  assertOrderedMarkers,
  classificarIa,
  parseProvidersAi,
  linhaAgentes,
  parseToolsRegistry,
  linhaTools,
} from './system-map.mjs';

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
  const semGit = linhaAgentes({ semInstrumento: true, agentes: 0, foraDaConvencao: [] });
  ok(semGit.includes('não medido') && !semGit.includes('**0**') && !semGit.includes('⚠️'),
    'LIBERA: git grep FALHOU → "não medido", nunca "0" e nunca alarme de drift');

  const integro = linhaAgentes({ semInstrumento: false, agentes: 22, foraDaConvencao: [] });
  ok(integro.includes('**22**') && integro.includes('convenção íntegra') && !integro.includes('⚠️'),
    'LIBERA: todo agente em Ai/Agents/ → sem alarme');

  const drift = linhaAgentes({ semInstrumento: false, agentes: 23, foraDaConvencao: ['EscondidoAgent'] });
  ok(drift.includes('⚠️') && drift.includes('EscondidoAgent'),
    'MORDE: agente fora da convenção → alarme NOMEANDO o arquivo (não só um número)');
}

// ── H) o BUG REAL refutado pelo adversarial: tools por pasta de UM módulo ─────
// A 1ª versão contava `Modules/Jana/Mcp/Tools/*Tool.php` = 39 pra descrever o que o
// servidor registra em TRÊS módulos = 44. Este bloco é o controle-negativo disso.
{
  const server = `<?php
class OimpressoMcpServer {
    protected array $tools = [
        // comentário citando FalsoTool::class não pode contar
        \\Modules\\Brief\\Mcp\\Tools\\BriefFetchTool::class,
        Tools\\MyWorkTool::class,
        Tools\\TriageTool::class,
        \\Modules\\TeamMcp\\Mcp\\Tools\\HandoffAckTool::class,
    ];
    protected array $resources = [ Resources\\CurrentResource::class ];
    protected array $prompts = [ Prompts\\BriefingPrompt::class ];
}`;
  const r = parseToolsRegistry(server);
  ok(r.ok && r.total === 4,
    `MORDE: conta o registro dos 3 módulos, não a pasta de um (obtido: ${r.total} — esperado 4)`);
  ok(r.porModulo.Jana === 2 && r.porModulo.Brief === 1 && r.porModulo.TeamMcp === 1,
    `resolve FQN por módulo e a forma relativa pro dono (obtido: ${JSON.stringify(r.porModulo)})`);
  ok(!('Resources' in r.porModulo) && r.total === 4,
    'LIBERA: $resources e $prompts ficam fora do array $tools');

  const semArray = parseToolsRegistry('<?php class X {}');
  ok(semArray.ok === false && semArray.total === 0,
    'LIBERA: sem o array $tools → ok:false, e o render dirá "não medido" em vez de 0');
}

// ── I) a linha de tools: rótulo honesto e drift registro×arquivo ─────────────
{
  const bate = linhaTools({ registro: { ok: true, total: 44, porModulo: { Jana: 39 } }, arquivosTool: 44 });
  ok(bate.includes('registradas') && !bate.includes('expostas'),
    'MORDE no rótulo: diz "registradas"; "expostas" seria claim de runtime (MCP_TOOLS_EXPOSED)');
  ok(bate.includes('MCP_TOOLS_EXPOSED') && !bate.includes('⚠️'),
    'LIBERA: registro bate com arquivo → sem alarme, mas aponta o dono do runtime');

  const orfa = linhaTools({ registro: { ok: true, total: 44, porModulo: { Jana: 39 } }, arquivosTool: 45 });
  ok(orfa.includes('⚠️') && orfa.includes('não registrada'),
    'MORDE: arquivo *Tool.php sem registro → alarme (tool escrita que não sobe)');

  const nada = linhaTools({ registro: { ok: false, total: 0, porModulo: {} }, arquivosTool: 0 });
  ok(nada.includes('não medido') && !nada.includes('**0**'),
    'LIBERA: sem o array → "não medido", nunca "0 tools"');
}

// ── J) diagramas explicativos mordem drift de ordem, não só presença ─────────
{
  const source = 'persistir usuário\nredigir PII\nchamar modelo\npersistir resposta';
  let mordeu = false;
  assertOrderedMarkers(source, ['persistir usuário', 'redigir PII', 'chamar modelo'], 'fixture íntegra');
  try {
    assertOrderedMarkers(source, ['persistir usuário', 'persistir resposta', 'chamar modelo'], 'fixture fora de ordem');
  } catch {
    mordeu = true;
  }
  ok(mordeu, 'MORDE: âncora existente mas fora de ordem invalida o fluxo');
}

// ── J-bis) alinhamento não é estrutura (incidente 2026-07-29) ───────────────
// O gerador quebrou porque o marcador exigia `'role'        => 'assistant'`
// (8 espaços) e o ChatController:509 escreve com 1. O fluxo estava íntegro.
// Regressão aqui = PAINEL/ARCHITECTURE/ONBOARDING param de regenerar em silêncio.
{
  const alinhado = "\$m = Mensagem::create([\n    'role'        => 'assistant',\n]);";
  const compacto = "\$m = Mensagem::create([\n    'role' => 'assistant',\n]);";

  let liberou = true;
  try {
    // marcador alinhado × fonte compacta — foi ESTE par que quebrou em prod
    assertOrderedMarkers(compacto, ["Mensagem::create([", "'role'        => 'assistant'"], 'compacta');
    // e o inverso, pra não trocar um literal-de-formatação por outro
    assertOrderedMarkers(alinhado, ["Mensagem::create([", "'role' => 'assistant'"], 'alinhada');
  } catch {
    liberou = false;
  }
  ok(liberou, 'LIBERA: espaçamento de alinhamento não invalida a âncora (nos dois sentidos)');

  // controle negativo — normalizar NÃO pode afrouxar presença nem ordem
  let mordeuAusente = false;
  try {
    assertOrderedMarkers(compacto, ["'role' => 'system'"], 'ausente');
  } catch { mordeuAusente = true; }
  ok(mordeuAusente, 'MORDE: normalizar espaço não faz âncora AUSENTE passar');

  let mordeuOrdem = false;
  try {
    assertOrderedMarkers(compacto, ["'role' => 'assistant'", "Mensagem::create(["], 'ordem');
  } catch { mordeuOrdem = true; }
  ok(mordeuOrdem, 'MORDE: normalizar espaço não faz âncora FORA DE ORDEM passar');

  // newline segue sendo estrutura: colapsar \n juntaria linhas distintas
  let mordeuNewline = false;
  try {
    assertOrderedMarkers(compacto, ["Mensagem::create([ 'role' => 'assistant'"], 'newline');
  } catch { mordeuNewline = true; }
  ok(mordeuNewline, 'MORDE: só espaço/tab colapsa — newline continua separando linhas');
}

console.log(fails === 0 ? '\n  OK — núcleo da camada de IA morde e não falsa-positiva.\n' : `\n  ${fails} FALHA(S)\n`);
process.exit(fails === 0 ? 0 : 1);

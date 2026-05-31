# ADR ARQ-0006 (Financeiro) · RAG "perguntar ao histórico financeiro" — Jana copiloto que cita fonte

- **Status**: proposed
- **Data**: 2026-05-31
- **Decisores**: Wagner (pendente)
- **Categoria**: arq
- **Relacionado**: [KB-9.75 I2](../../METODO-9.75-FINANCEIRO.md) · [AUDIT-METODO-9.75-TELAS-2026-05-31](../../AUDIT-METODO-9.75-TELAS-2026-05-31.md) · [ADR 0035 stack IA](../../../../decisions/0035-stack-ia.md) · [ADR 0093 multi-tenant Tier 0](../../../../decisions/0093-multi-tenant-isolation-tier-0.md) · ADR tech/0001 (idempotência)

## Contexto

O método **KB-9.75** define a feature-tipo **I2 — "perguntar ao corpus"** (RAG): o operador pergunta em linguagem natural sobre os dados e recebe resposta **citando a fonte**. É a feature de maior ganho de score (+0,5 estimado) e a mais ausente.

A [auditoria de 2026-05-31](../../AUDIT-METODO-9.75-TELAS-2026-05-31.md) confirmou no código real: **nenhuma das "IAs" do Financeiro é LLM**. `FinMonthDigest`, `FinMonthResume`, `AiResumoMes`, `FinTroubleshooter` são heurística/compute pura (os arquivos declaram "sem LLM"). O único uso real de modelo é `BoletoOcrService` (OCR Vision). Logo **I2 não existe em lugar nenhum** do módulo.

A persona Eliana [E] quer perguntas como: *"esse cliente costuma atrasar?"*, *"quanto recebi desse canal nos últimos 90 dias?"*, *"qual fornecedor mais cresceu em despesa este trimestre?"*. Hoje ela exporta CSV e calcula na planilha — exatamente o que a proposta de valor promete eliminar.

**Restrições duras:**
- Dados financeiros são **estruturados** (`fin_titulos`, `fin_titulo_baixas`, `fin_extrato_lancamentos`) e já isolados por `business_id` (Tier 0, ADR 0093 IRREVOGÁVEL).
- Resposta **precisa citar fonte exata** (IDs de título/baixa) — número financeiro sem rastreabilidade é inaceitável (auditoria fiscal).
- Princípio P4 do método: **IA copiloto** — propõe, humano confere, **nunca muta** estado.
- PII: contraparte/documento não pode vazar pra prompt/log sem cuidado (LGPD).

## Decisão (proposta)

Implementar I2 como **tool-calling estruturado** (LLM traduz a pergunta → chama *tools* read-only já escopadas por `business_id` → responde citando os registros retornados), **não** como RAG de texto livre sobre um índice vetorial.

**Por quê tool-calling > RAG vetorial aqui:**
- Os dados são tabulares e exatos — "quanto recebi" é um `SUM`, não uma busca semântica. Tool-calling dá resposta **determinística e citável**; RAG vetorial alucinaria números.
- `business_id` entra **no servidor** (na assinatura da tool), nunca via prompt — o LLM não consegue burlar o Tier 0.
- Reusa a stack IA canônica (ADR 0035) + `ai_usage_log` (já existe, migration) pra custo.

**Forma:**
1. Conjunto pequeno de tools read-only (ex.: `titulos_por_contraparte`, `recebido_por_periodo`, `aging_resumo`, `serie_temporal`) — cada uma recebe `int $businessId` no 1º arg (igual `UnificadoService`/`TituloRepository`) e devolve dados + IDs de origem.
2. Jana (ADR 0035) faz orquestração: pergunta PT-BR → escolhe tool(s) → compõe resposta PT-BR **com bloco "Fontes: #título-N, baixa-M"** clicável (reusa `FinCrossLinkify`).
3. UI: painel irmão do `AiResumoMes`/overflow ✦ no Unificado (e depois DRE/Relatórios), com a pergunta + resposta + fontes. **Read-only**, sem botão que mute.

## Consequências

**Positivas**
- Fecha o maior gap de 9,75 (Inteligência) reusando Tier 0 já pronto.
- Resposta citável → confiável pra contexto fiscal (diferencial vs Conta Azul/Bling, que não têm).
- Mais barato que indexar corpus vetorial; custo por pergunta logado (`ai_usage_log`).

**Negativas / custo**
- Custo de LLM por pergunta (mitigar: cache de perguntas frequentes + modelo Haiku pra roteamento).
- Precisa **eval anti-alucinação** (Pest com perguntas-fixture + asserção de que a resposta bate com SQL direto).
- Trabalho de scaffold antes da UI: as tools read-only + o roteador.

**Bloqueios / sequência (anti regressão F3)**
1. Wagner aceita esta ADR (`status: accepted`).
2. PR separado: tools read-only + Pest (sem UI ainda).
3. SÓ ENTÃO: PR de UI (painel de pergunta) consumindo as tools.

## Alternativas consideradas

- **(B) RAG vetorial sobre dump textual dos títulos** — rejeitada: alucina números, fonte imprecisa, caro de indexar, e dados já são estruturados.
- **(C) Text-to-SQL livre** — rejeitada: risco de query arbitrária cross-tenant (o LLM montaria o `where`), difícil de blindar Tier 0. Tool-calling com `business_id` server-side é mais seguro.
- **(D) Não fazer (manter heurística)** — é o estado atual; deixa o módulo travado em ~Inteligência 5,5 e não atinge 9,75.

## Riscos

- **Tier 0:** `business_id` SEMPRE no servidor, nunca no prompt/payload do LLM. Pest cross-tenant obrigatório (biz=1 vs biz=99) como em `TituloRepositoryWave18Test`.
- **Alucinação numérica:** toda resposta com número cita a fonte; eval compara com SQL direto.
- **PII (LGPD):** documento/nome de contraparte não vai pro log; redigir antes de `ai_usage_log`.
- **Custo:** teto por business + cache; monitorar via `ai_usage_log`.

---

**Proposta por:** Claude Code (Opus 4.8), 2026-05-31, ancorada na [auditoria das telas](../../AUDIT-METODO-9.75-TELAS-2026-05-31.md). Aguarda decisão Wagner antes de qualquer código.

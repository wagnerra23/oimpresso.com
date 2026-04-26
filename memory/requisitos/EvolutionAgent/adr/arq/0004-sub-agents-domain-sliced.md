# ADR ARQ-0004 (EvolutionAgent) · Sub-agents fatiados por domínio

- **Status**: accepted
- **Data**: 2026-04-26
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

`memory/requisitos/` tem ~18 módulos catalogados. Pergunta sobre Financeiro não precisa de contexto de Cms ou PontoWr2.

Carregar tudo no contexto = ~50k tokens. Carregar só fatia relevante = ~5-7k.

## Decisão

**1 agente router** (`EvolutionAgent`) + **4 sub-agents domain-sliced** na Fase 2:

| Sub-agent | Memória que carrega | Justificativa de prioridade |
|---|---|---|
| `FinanceiroAgent` | `memory/requisitos/Financeiro/` + ADRs arq/* relevantes | Wave 1 + 2 mergeados; Onda crítica pra meta R$5mi |
| `PontoAgent` | `memory/requisitos/PontoWr2/` | Tier A roadmap; produto âncora 2026 |
| `CmsAgent` | `memory/requisitos/Cms/` + `memory/comparativos/` | Em redesign Inertia; conversão = entrada novo cliente |
| `CopilotoAgent` | `memory/requisitos/Copiloto/` | Spec novo; chat IA do cliente final |

Os outros ~14 módulos: triageados pelo agente router, sem sub-agent dedicado. Se virarem prioritários, promover.

Router tem **só metas + ADR 0026** no prompt (~1k tokens) e delega via tool `Delegate(escopo, query)`.

## Consequências

**Positivas:**
- ~7× menos tokens/query (5-7k vs 50k).
- Cada sub-agent fica especialista; system prompt focado.
- Adicionar novo domínio = criar nova classe `XAgent extends BaseLlmAgent`, 1 arquivo.
- Eval por sub-agent: regressão fica visível no domínio que quebrou.

**Negativas:**
- Pergunta atravessada (ex: "como Financeiro impacta Ponto?") precisa router orquestrar.
- Sub-agents podem divergir nas convenções; mitigação = trait `EvolutionBaseAgent` com helpers comuns.
- 4 agentes = 4 system prompts pra manter.

**ROI estimado**: ~7× (cada query carrega 1/7 do contexto que carregaria mono).

## Critério de promoção (futuro)

Promover módulo a sub-agent dedicado quando:
- ≥3 perguntas sobre ele em 1 mês (via tracing Vizra), OU
- Está em ADR de prioridade Tier A do roadmap, OU
- Wagner explicit pedir.

## Alternativas consideradas

| Alt | Motivo de rejeição |
|---|---|
| 1 agente monolítico | ~7× mais caro em tokens; sem benefício. |
| 1 sub-agent por módulo (18) | Overhead de manutenção; 14 deles são baixa prioridade. |
| Sub-agents dinâmicos (gerados on-the-fly) | Complexidade prematura; só faz sentido com ≥10 domínios ativos. |

## Links

- [SPEC §5 Arquitetura](../../SPEC.md#5-arquitetura)
- [ADR 0026 posicionamento](../../../../decisions/) — origem dos prioritários

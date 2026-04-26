# ADR ARQ-0001 (EvolutionAgent) · Vizra ADK como base do agente

- **Status**: accepted
- **Data**: 2026-04-26
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Wagner pediu agente que "cuide da evolução do sistema" com memória otimizada, evals de desempenho e máxima alavancagem do Laravel. Wagner explicitou: "use o vizra adk eu tenho conhecimento".

Avaliados:
- **Roll-your-own** com Anthropic SDK direto (PHP `anthropic-ai/sdk`)
- **Vizra ADK** ([github.com/vizra-ai/vizra-adk](https://github.com/vizra-ai/vizra-adk))
- **LaravelAI módulo próprio** (já specado em `memory/requisitos/LaravelAI/`)

## Decisão

Usar **Vizra ADK** como base.

Justificativa por requisito do Wagner:
- "Memória fundamental, otimizada" → Vizra tem **vector memory + RAG** built-in.
- "Testes claros de desempenho" → Vizra tem **Evaluation Framework com LLM-as-Judge**.
- "Tire o máximo do Laravel" → Vizra usa Service Providers, Eloquent, Artisan, Livewire v4.
- "Comece pequeno" → `vizra:make:agent` + `vizra:chat` em 1 comando.

## Consequências

**Positivas:**
- ~3 semanas de código evitadas (memory + eval + tracing + dashboard prontos).
- Multi-LLM grátis via Prism PHP integrado.
- Sub-agents, workflows, MCP-client, queue-jobs nativos.
- MIT, ativo, com Issue tracker no GitHub.

**Negativas:**
- Pacote relativamente jovem; possíveis breaking changes em majors.
- Requer **PHP 8.2+ e Laravel 11+** — só viável após merge L13 em 6.7-bootstrap.
- Acopla projeto a uma decisão tecnológica externa; substituir custa caro depois.

**ROI estimado**: ~15× (3 semanas evitadas / 1 dia setup).

## Alternativas consideradas

| Alt | Motivo de rejeição |
|---|---|
| Roll-your-own (Anthropic SDK PHP) | Reinventar memory/RAG/eval = 3 semanas. ROI negativo vs Vizra. |
| LaravelAI módulo próprio | É adapter genérico, não framework de agentes. Pode ser **incorporado depois** se Vizra não atender ponto específico. |
| Symfony AI Bundle | Não é Laravel-native; mais fricção. |
| LangChain via Python externo | Sai do stack PHP. Wagner explicit pediu Laravel. |

## Pré-requisitos antes da Fase 1

- [ ] Merge `chore/upgrade-laravel-13` em `6.7-bootstrap` concluído
- [ ] PHP 8.2+ confirmado no Herd local + Hostinger
- [ ] Livewire ^4.0 disponível (pra dashboard Vizra — opcional, mas vem grátis)

## Links

- [Vizra ADK GitHub](https://github.com/vizra-ai/vizra-adk)
- [Vizra docs](https://docs.vizra.ai)
- [SPEC §4 ROI table](../../SPEC.md#4-roi-por-componente)

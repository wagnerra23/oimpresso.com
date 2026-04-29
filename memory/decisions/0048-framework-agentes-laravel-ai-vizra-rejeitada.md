# ADR 0048 — Framework de agentes IA: `laravel/ai` (Vizra ADK rejeitada oficialmente)

**Status:** Aceito · Supersede parcialmente [ADR 0032](0032-vizra-adk-prism-php-orquestracao.md) · Confirma e fortalece [ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md)
**Data:** 2026-04-29
**Origem:** Pesquisa exaustiva Wagner (29-abr-2026) — anexo `files.zip/ADR-001-framework-agentes-ia.md`

---

## Contexto

A primeira escolha foi **Vizra ADK** (jun/2025), que era o framework mais maduro para agentes em Laravel. O agente NCM do WR2 foi implementado com Vizra e funcionou.

Em **fev/2026** a Laravel lançou o `laravel/ai` SDK oficial. Em **mar/2026** saiu o Laravel 13. O **Vizra ADK quebrou** na atualização para Laravel 13 e o agente NCM ficou desativado.

Essa quebra confirma o trade-off já antecipado em [ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md): adotar Vizra significava aceitar dependência de mantenedor único. O sinal foi estratégico, não técnico.

---

## Decisão

**Consolidar 100% no Laravel AI SDK oficial (`laravel/ai`).** Vizra ADK fica **rejeitada como dependência atual** do oimpresso.

- A "Camada B" do ADR 0035 (Vizra ADK) é **absorvida pela Camada A** (`laravel/ai` + agents nativos do oimpresso).
- Não migramos `LaravelAiSdkDriver` + `ChatCopilotoAgent` / `BriefingAgent` / `SugestoesMetasAgent` para Vizra.
- A task **COP-015 (Vizra ADK install + migrar conversas)** é **cancelada** no TASKS.md.

---

## Justificativa

- **Estabilidade**: mantido pela equipe core da Laravel — suporte garantido enquanto o framework existir.
- **Failover automático** entre providers (OpenAI, Anthropic, Gemini, Groq, xAI) sem código de fallback.
- **MCP nativo via `laravel/mcp`** — abre porta para integração com Claude Desktop, Cursor, futuros (ver ADR 0053).
- **Escopo amplo num pacote só**: texto, embeddings, imagens, áudio, vector stores, web search.
- **Risco de manutenção é o critério dominante para dev solo.** Vizra quebrar no Laravel 13 foi sinal estratégico.
- **Custo de migração baixo** no oimpresso: nosso `LaravelAiSdkDriver` já roda direto sobre `laravel/ai`, não usa Vizra.

---

## Consequências

**Positivas:**
- Estabilidade garantida em futuras versões do Laravel.
- Sintaxe idiomática Laravel — onboarding rápido.
- Acesso a features oficiais futuras (provider tools, file search, reranking).
- Nenhum bloqueio de upgrade de framework.

**Negativas / Trade-offs:**
- **Sem dashboard nativo** (compensável com Filament em ~1 dia se precisar — não está no roadmap atual).
- **Sem evaluations framework pronto** (precisa montar harness customizado — ver ADR 0051 sobre estratégia de teste).
- **SDK ainda em beta (v0.6.x em abr/2026)** — esperar v1.0 para considerar maduro. Mitigação: monitorar releases, congelar versão minor.

---

## Alternativas descartadas (todas)

- **Vizra ADK**: quebrou no Laravel 13, dependência de mantenedor único, ecossistema mais frágil. **Rejeitado oficialmente.**
- **LarAgent / Neuron AI / ai-agents-laravel**: comunidades menores, sem garantia oficial.
- **Stack Python paralela (LangChain, CrewAI)**: força microsserviço Python que dev solo não tem capacidade de manter.

---

## Triggers para reavaliar

Reabrir a decisão se:
1. `laravel/ai` v1.0 sair com regressões de API que quebrem o `LaravelAiSdkDriver`.
2. Vizra ADK voltar com suporte oficial Laravel 13 + roadmap garantido por 12+ meses.
3. Surgir framework PHP de agente com adoção em produção comprovada (>10 cases públicos).

---

## Referências

- Pesquisa exaustiva Wagner: `files.zip/ADR-001-framework-agentes-ia.md` (29-abr-2026)
- ADR 0035 — Stack canônica IA (este ADR confirma e fortalece)
- ADR 0032 — Vizra ADK + Prism PHP orquestração (este ADR supersede parcialmente)
- ADR 0046 — Gap ChatCopilotoAgent (resolvido com Caminho A em ADR 0047)

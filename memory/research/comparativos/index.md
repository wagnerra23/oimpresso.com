---
id: research-comparativos-index
---

# Cofre de Comparativos — Índice

> **O que é:** comparativos competitivos estilo Capterra/G2 do oimpresso.
>
> **Quando usar:** antes de tomar decisão de roadmap >2 sprints, ao lançar/repensar pricing, ao entrar em vertical novo, ou quando concorrente lança feature ameaçadora.
>
> **Como criar:** copia [_TEMPLATE_capterra_oimpresso.md](./template-capterra-oimpresso.md) → preenche → adiciona linha aqui no índice.

## Convenções

- **Naming:** `<assunto>_<tipo>_capterra_YYYY_MM_DD.md`
  - Produto inteiro: `oimpresso_vs_concorrentes_capterra_*.md`
  - Módulo específico: `<modulo>_vs_concorrentes_capterra_*.md`
  - Marketing/copy: `site_marketing_concorrentes_<nicho>_*.md`
  - Análise interna: `<assunto>_oimpresso_capterra_*.md`
- **Template versão:** v1.0 (2026-04-25). Ver checklist em [_TEMPLATE_capterra_oimpresso.md](./template-capterra-oimpresso.md).
- **Trigger "guarde no cofre"**: Wagner pode pedir explicitamente — significa salvar artefato aqui (se for comparativo) OU em `memory/decisions/` (decisão), `memory/requisitos/{Mod}/SPEC.md` (US), ou auto-memória (preferência/quirk).

## Comparativos publicados

| Data | Arquivo | Assunto | Decisão informada |
|---|---|---|---|
| 2026-04-25 | [site_marketing_concorrentes_comunicacao_visual_2026_04_25.md](./site-marketing-concorrentes-comunicacao-visual-2026-04-25.md) | Copy/visual concorrentes vertical CV | Reescrita do site (Hero PT-BR "orça/imprime/monta/entrega") |
| 2026-04-25 | [oimpresso_vs_concorrentes_capterra_2026_04_25.md](./oimpresso-vs-concorrentes-capterra-2026-04-25.md) | oimpresso vs Mubisys/Zênite/Calcgraf/Calcme/Visua/Bling/Omie | ADR 0026 (posicionamento "ERP gráfico com IA"); 3 features 6m: PricingFpv + Jana v1 + CT-e/MDF-e |
| 2026-04-26 | [sistemas_memoria_oimpresso_capterra_2026_04_26.md](./sistemas-memoria-oimpresso-capterra-2026-04-26.md) | **Camada A** — 9 sistemas de memória de DEV (CLAUDE.md vs decisions/ vs auto-mem vs MemCofre vs Git/PRs etc) | ADRs 0027/0028/0030; 3 ações 30d (CLAUDE.md aponta cofre, rename 0024 dup, AGENTS.md vira tombstone) |
| 2026-04-26 | [copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md](./copiloto-runtime-memory-vs-mem0-langgraph-letta-zep-capterra-2026-04-26.md) | **Camada C apenas** — Jana runtime memory vs Mem0/LangGraph/Letta/Zep/OMEGA (foco em memória especializada) | Caminho B: REST adapter pra Mem0 (5 sprints, Tier 1→6-7); 14 US-COPI-MEM-NNN |
| 2026-04-26 | [stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md](./stack-agente-php-vizra-prism-mem0-capterra-2026-04-26.md) | **Stack completa A+B+C** — 7 players: Prism PHP, Vizra ADK, Mem0, LangGraph, Letta, Zep, OMEGA | ADR 0031 (MemoriaContrato + Mem0 default) + ADR 0032 (Vizra ADK + Prism PHP); roadmap 7 sprints |
| 2026-04-27 | [revisao_caminho_2026_04_27_capterra.md](./revisao-caminho-2026-04-27-capterra.md) | **Revisão de caminho** — auditoria pós-sprint 6 com 5 caminhos avaliados (atual ADR 0037 / pivot comercial / Typesense / Mem0 cedo / validar Larissa) | Recomenda validar com Larissa ANTES de sprint 7; gatilho de pivot mensurável 30/90d |
| 2026-04-27 | [claude_desktop_vs_laravel_mcp_oimpresso_2026_04_27.md](./claude-desktop-vs-laravel-mcp-oimpresso-2026-04-27.md) | **Claude Desktop ecosystem vs nossa stack Laravel MCP** — 7 MCP servers populares (GitHub/Brave/Slack/Postgres/Filesystem/Linear/Notion) vs potencial do oimpresso | Vácuo absoluto no vertical brasileiro de gráfica; recomenda MCP server como sprint 7 alternativo se foco for receita; setup técnico completo passo-a-passo |
| 2026-04-28 | [qa_eval_ia_estado_arte_capterra_2026_04_28.md](./qa-eval-ia-estado-arte-capterra-2026-04-28.md) | **Estado-da-arte de QA/eval de IA + ciclo de vida completo** — 8 plataformas (Vizra ADK / Braintrust / LangSmith / Langfuse / Phoenix / DeepEval / Promptfoo / Claude Skills) em 7 categorias (offline / online / HITL / RAG / agente / safety / stack BR), 42 features | **Caminho B (self-host pragmático): Vizra eval + Langfuse Hostinger + DeepEval CLI** formalizado em [ADR 0041](../decisions/0041-stack-qa-ia-vizra-langfuse-deepeval.md). Sprint 7 = golden set + DeepEval CI; Sprint 8 = PII redactor + Langfuse; Sprint 9 = online judge + HITL admin. Métrica de fé 90d (28-jul-2026): faithfulness ≥85% + 0 PII leak |
| 2026-05-05 | [prompt_skill_management_2026_05_05.md](./prompt-skill-management-2026-05-05.md) | **Estado-da-arte UI gestão de prompts/skills** — 10 ferramentas (Langfuse / LangSmith / Humanloop / Vellum / PromptLayer / Portkey / Agenta / Helicone / Anthropic Console / Anthropic Skills) em 6 categorias (versionamento / sync DB↔git / diff & history / rationale / testes / governance), 31 features | **Caminho B: construir UI Inertia/React própria** copiando padrões Langfuse (versions+labels+webhook) + LangSmith (diff two-pane) + Anthropic Skills (folder-per-skill+git PR). 5 tabelas + 5 telas. ADR 0075 detalha (supersede 0073). 7 dias úteis V1 |

## Próximos sugeridos (não criar sem motivo)

- `pontowr2_vs_concorrentes_capterra_*.md` — antes de virar Tier A do Ponto (Dashboard vivo)
- `copiloto_vs_concorrentes_capterra_*.md` — quando Jana sair do dry_run e tiver pricing
- `financeiro_vs_concorrentes_capterra_*.md` — antes de cobrar take rate de boleto

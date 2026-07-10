---
date: "2026-07-10"
topic: "Estado-da-arte 'spec por feature' 2026 (GitHub Spec Kit / AWS Kiro / OpenSpec / EARS) — o que COMPLEMENTA o trio requirements/plan/tasks do PR #4044, rankeado por impacto×esforço, respeitando as 5 fronteiras do SDD."
authors: [C]
related_adrs:
  - 0306-strangler-spec-anchored-reconstrucao-sdd
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0302-fonte-unica-doneness-anchor-aposenta-status-spec
  - 0070-jira-style-task-management-current-md-removed
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0094-constituicao-v2-7-camadas-8-principios
---

# Estado-da-arte "spec por feature" — o que complementa o trio (2026-07-10)

> **Gatilho:** Wagner, sobre o degrau spec-por-feature (trio, PR #4044): *"esse eu sei que se pesquisar eu tenho coisa melhor para complementar, investigue"*. Estava certo — assumi "Spec Kit = specify->plan->tasks" de memória; o Spec Kit 2026 evoluiu pra uma cadeia com 3 fases que deixei de fora, e Kiro/OpenSpec têm peças que encaixam.
> **Raia:** só o AUTOR do trio. NÃO cobre governança/gates (auditoria irmã 2026-06-21) nem anchor (ADR 0273).
> **Desfecho:** os 3 complementos verdes foram APLICADOS ao template + piloto no PR #4044 (Wagner aprovou 2026-07-10).

## TL;DR — 6 complementos, 3 aplicados

O trio **é** o item P3 #9 do [audit SDD 2026-06-12](2026-06-12-audit-sdd-pesquisa-reclassificacao.md) ("spec->tasks compilation estilo Kiro/Spec Kit"). Certo no esqueleto. O que faltou:

| # | Complemento | Fonte | Impacto | Esforço | Veredito |
|---|---|---|:---:|:---:|---|
| 1 | **Fase Clarify** — desambiguação estruturada ANTES do plan, persistida no requirements | Spec Kit `/clarify` | ALTO | BAIXO | ✅ aplicado (usa `wagner-understand`) |
| 2 | **`design.md` mais rico** — data model + contratos + como o novo pluga no EXISTENTE | Kiro `design.md` | MÉDIO-ALTO | BAIXO | ✅ aplicado (seções opcionais no plan.md) |
| 3 | **EARS 6 formas (não 4)** | EARS | BAIXO | TRIVIAL | ✅ aplicado |
| 4 | **Analyze — consistência semântica trio×Constituição** | Spec Kit `/analyze` | ALTO | MÉDIO | 🟡 só authoring-time, NUNCA gate CI (fronteira 4) |
| 5 | **Delta framing** — requirements como MUDANÇA vs hoje | OpenSpec (pendente) | MÉDIO | baixo/alto | 🟡 só o espírito; NÃO archive (fronteira 1) |
| 6 | **`/constitution`** | Spec Kit | — | — | ❌ redundante — já temos Constituição v2, enforçada |

## 1. Mercado 2026 (re-verificado)

### GitHub Spec Kit — a cadeia cresceu
`/constitution -> /specify -> /clarify -> /plan -> /checklist -> /tasks -> /analyze -> /implement -> /converge`
- **`/clarify`**: varredura de ambiguidade em **10 categorias** (escopo, domínio, UX, não-funcional, integração, edge cases, restrições, terminologia, sinais de conclusão, placeholders); **<=5 perguntas dirigidas, 1 de cada vez**, resposta gravada numa seção `Clarifications` do spec.
- **`/analyze`**: read-only, cruza `spec x plan x tasks` (duplicação, ambiguidade, subespecificação, **violação de constitution**, buracos de cobertura); severidade CRITICAL->LOW; violação de constitution vira CRITICAL.
- **`/constitution`**: princípios inegociáveis referenciados em toda fase.

Fonte: [docs](https://github.github.com/spec-kit/quickstart.html) · [repo](https://github.com/github/spec-kit) · [DeepWiki](https://deepwiki.com/github/spec-kit/5-spec-driven-development-workflow) · [funDesk 2026](https://www.fundesk.io/spec-driven-development-github-spec-kit-guide).

### AWS Kiro — `design.md` mais rico que meu `plan.md`
`requirements.md (EARS) + design.md + tasks.md`. O `design.md` traz architecture decisions, component boundaries, data models, API contracts, sequence diagrams e **como o novo interage com o EXISTENTE** — e é gate de aprovação. Fonte: [Kiro Specs](https://kiro.dev/docs/specs/) · [Design-First](https://kiro.dev/docs/specs/feature-specs/tech-design-first/). O "pluga no existente" é o de maior valor no brownfield (ADR 0306), onde meu plan.md era raso.

### OpenSpec — delta (pendente de re-verificação)
Descreve a MUDANÇA (ADDED/MODIFIED/REMOVED) vs o estado atual e arquiva no spec ao mergear; brownfield-nativo. ADR 0306 já decidiu importar delta-spec. *Layout de arquivos não re-verificado nesta sessão (classificador de busca instável) — confirmar antes de formalizar.*

### EARS — 6 formas, meu template tinha 4
Faltavam **opcional** (`ONDE <feature> presente...`) e **complexa** (combinação). Corrigido.

## 2. Como cada um encaixa sem violar as 5 fronteiras
Fronteiras: (1) não duplica SPEC · (2) não compete com tasks MCP · (3) brownfield · (4) gate advisory, required=só Tier-0 · (5) barato.

- **#1 Clarify (APLICADO):** já temos `wagner-understand` + `wagner-request-refiner`; faltava PERSISTIR — seção `## Clarifications` no requirements (Q->A datado). Não redundante: o session doc do agente é efêmero; a seção vira contrato, sessão futura não re-pergunta.
- **#2 design.md no plan (APLICADO):** seções OPCIONAIS por não-trivialidade — Dados tocados / Contratos / Interação novo<->existente. NÃO renomear plan->design; NÃO exigir Mermaid.
- **#3 EARS 6 formas (APLICADO):** fix de template.
- **#4 Analyze (pendente):** feature-lint já cobre o mecânico; o semântico + cross-check Constituição precisa de LLM -> fica como assistente de AUTORIA, nunca gate CI (ADR 0271).
- **#5 Delta (pendente):** trazer só o enquadramento "o que muda vs hoje"; NÃO a máquina de archive (competiria com âncora/SPEC).
- **#6 /constitution (descartado):** Constituição v2 + proibicoes + ADRs já são isso, e enforçadas.

## Não trazer (over-engineering p/ 1 dev + IA-pair)
9-comandos-CLI cerimônia; archive OpenSpec; sequence diagram obrigatório; `/analyze` como gate CI; renomear plan->design.

## Rastreabilidade — já existe
Cadeia: `covers:` (task->AC) -> `us:` (feature->US) -> âncora (US->código) -> casos-gate (US->teste). Matriz separada duplicaria; futuro barato = feature-lint EMITIR a cadeia como relatório.

## Pendente (investigação futura)
- #4 `analyze` de authoring (skill/agente lê trio + Constituição).
- #5 confirmar layout OpenSpec + decidir seção `## Mudança`.

## Fontes
[Spec Kit docs](https://github.github.com/spec-kit/quickstart.html) · [repo](https://github.com/github/spec-kit) · [DeepWiki](https://deepwiki.com/github/spec-kit/5-spec-driven-development-workflow) · [funDesk](https://www.fundesk.io/spec-driven-development-github-spec-kit-guide) · [MS Dev](https://developer.microsoft.com/blog/spec-driven-development-spec-kit) · [Kiro Specs](https://kiro.dev/docs/specs/) · [Kiro Design-First](https://kiro.dev/docs/specs/feature-specs/tech-design-first/) · [ADR 0306](../decisions/0306-strangler-spec-anchored-reconstrucao-sdd.md) · [audit 2026-06-12](2026-06-12-audit-sdd-pesquisa-reclassificacao.md) · [arte gov SDD 2026-06-21](2026-06-21-arte-governanca-sdd.md)

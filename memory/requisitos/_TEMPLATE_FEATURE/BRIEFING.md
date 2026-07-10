<!--
  USE COMO BASE — NÃO EDITAR (canônico). Este BRIEFING é a porta do diretório (não se copia).
  Copie os 3 templates (requirements.md + plan.md + tasks.md) pra
  `memory/requisitos/<Mod>/features/<slug>/` e cure os placeholders {{...}}.
  Piloto de referência: memory/requisitos/RecurringBilling/features/gateway-ativacao/
-->

# Template FEATURE — o trio `requirements.md` + `plan.md` + `tasks.md`

> **O degrau que faltava no SDD:** a US do SPEC diz **o quê** (1 bloco, âncora de done-ness
> ADR 0273/0302); o registro MCP diz **quem/quando** (workflow, ADR 0070). Entre os dois não
> existia o **como executável**: requirements estruturados + plano técnico + tarefas atômicas
> com dependências (`blocked_by:`) que uma sessão consiga executar em ordem, sem re-decidir.
> Régua de mercado: GitHub Spec Kit (specify→plan→tasks) e Kiro (EARS + deps). A importação
> de **delta-spec (OpenSpec) e EARS (Kiro)** já foi decidida na [ADR 0306](../../decisions/0306-strangler-spec-anchored-reconstrucao-sdd.md)
> ("baratos e sem lock-in") — este template é a operacionalização dela.

## Quando criar uma feature-pasta (e quando NÃO)

| Situação | Caminho |
|---|---|
| US de bullets vai virar execução multi-sessão (≥3 tasks, ≥1 dependência real) | ✅ `features/<slug>/` com o trio |
| Fix tático de 1 arquivo, task única | ❌ direto (task MCP + PR); trio seria cerimônia |
| Mudança de decisão arquitetural | ❌ ADR (append-only) — o `plan.md` REFERENCIA ADRs, nunca os substitui |
| Tela nova/alterada | trio + **casos-gate** (UC no `<Tela>.casos.md`, ADR 0264) — o trio não substitui o contrato de tela |

## O contrato do trio (validado por `scripts/governance/feature-lint.mjs`)

1. **`requirements.md`** — user story + acceptance criteria **EARS** verificáveis (`AC-N`) + fora-de-escopo.
   Frontmatter `us:` aponta pra(s) US do SPEC do módulo — a US **continua no SPEC**; a pasta
   **detalha, aponta, nunca duplica** a decisão.
2. **`plan.md`** — decisões técnicas (o COMO), plug-points no código existente (comparar e não
   duplicar), riscos Tier-0 (business_id · REGRA MESTRE valor/estoque · PII · casos-gate).
3. **`tasks.md`** — tarefas atômicas `T-NN` com `blocked_by:` explícito (grafo acíclico),
   `covers:` (quais ACs prova) e **DoD por task** (prova verificável, não narração).

## O que o trio NÃO é (fronteiras duras)

- **Não substitui o SPEC** — a US, sua âncora `**Implementado em:**` e a prioridade vivem no
  `SPEC.md`. O SPEC ganha 1 linha `**Detalhamento:**` apontando pra pasta; nada mais migra.
- **Não substitui as tasks MCP** — estado de workflow (todo/doing/done) é do registro MCP
  (`tasks-create` com `parent_plan:<slug>`, ADR 0070). O `tasks.md` é o **plano versionado**
  (ordem + dependências + DoD); por isso **não carrega `status:`** (ADR 0302 — done-ness se
  lê da âncora da US, nunca se digita).
- **Não entra no schema-gate (decisão consciente):** os globs de `scripts/memory-schemas/`
  não cobrem `features/**` — o tipo novo COMEÇA FORA do gate, validado só pelo
  `feature-lint.mjs` advisory (gates nascem advisory, ADR 0271). Promoção a schema-gate é
  decisão futura com backfill, nunca default.

## Ciclo de vida

1. Copiar template → curar placeholders → `node scripts/governance/feature-lint.mjs <Mod>/<slug>`.
1b. **Fase Clarify (Spec Kit 2026):** se o pedido tem ambiguidade, rodar o agente `wagner-understand`
   ANTES do plan e gravar as respostas na seção `## Clarifications` do requirements — desambiguação
   vira parte do contrato (não some no session log). Feature clara: seção fica `_nenhuma_`.
2. Wagner aprova o plano → `tasks-create ... parent_plan:"<slug>"` (1 task MCP por T-NN ou 1 guarda-chuva).
3. Sessões executam na ordem topológica do `blocked_by:`; cada T-NN fechado = DoD provado.
4. Última task SEMPRE fecha o loop: atualizar a âncora `**Implementado em:**` da US no SPEC
   (`verificado@sha7`, ADR 0273) — done-ness da feature = âncora viva, não checkbox aqui.
5. Feature entregue → a pasta permanece como registro (append-only cultural); correções de
   rumo = editar o trio no PR da mudança, nunca criar `<slug>-v2/`.

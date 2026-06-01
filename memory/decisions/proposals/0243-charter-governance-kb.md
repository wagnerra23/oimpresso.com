---
slug: 0243-charter-governance-kb
number: 243
title: "Charter Governance no KB — page/module charters como nós governados (imutável + sugestão supervisionada + autorização), motor de maturidade Champion"
type: adr
status: accepted
authority: canonical
lifecycle: ativo
quarter: 2026-Q2
proposed_at: 2026-06-01
proposed_by: [wagner, claude-opus]
accepted_at: 2026-06-01
accepted_by: wagner
decided_by: [W]
module: KB
tier: CANON
supersedes: []
relates_to: [0061, 0093, 0094, 0101, 0149, 0150, 0236, 0241, 0242]
related_docs:
  - memory/requisitos/KB/CONCEITO-CHARTER-GOVERNANCE-V1.md
  - memory/requisitos/KB/SPEC-CHARTER-GOVERNANCE.md
  - memory/requisitos/KB/SCHEMA-CHARTER-GOVERNANCE-DELTA.md
  - memory/requisitos/KB/INTERFACE-CHARTER-KB.md
authors: [wagner, opus]
---

# ADR 0243 — Charter Governance no KB

> **Status:** ✅ **ACEITA por [W] em 2026-06-01** ("pode fazer" — gate F0). Número 0243 confirmado. _(Arquivo migra de `proposals/` → `decisions/` no PR de F1, padrão do projeto.)_ Origem: sessão Wagner 2026-06-01 ("eu quero ele como KB… não quero editar livre a page charter; no KB dá pra colocar comentários supervisionados e tem a parte de autorizar — é um sistema de governança completo. Faça o pacote completo, isso vai ajudar a tornar o sistema autônomo").
>
> Operacionaliza o [CONCEITO-CHARTER-GOVERNANCE-V1.md](../../requisitos/KB/CONCEITO-CHARTER-GOVERNANCE-V1.md) e fecha a lacuna do [ADR 0101](../0101-sistema-charter-capterra-governanca-escopo.md) (nível módulo) usando o KB grafo ([ADR 0149/0150](0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md)).

---

## Contexto

1. **Charter hoje é arquivo solto.** O page charter (`*.charter.md`) vive ao lado do `.tsx`, é bom contrato de tela (ADR 0101), mas não tem **camada de governança de evolução**: quem propõe mudança? quem autoriza? como não vira ficção?
2. **Falta o nível módulo.** ADR 0101 previu Capterra (mercado) + page charter (tela), mas **nada responde "o que este *módulo* é, até onde vai (limite), o que falta (backlog) e o que já entregou (changelog)"** num lugar só. Hoje essa informação está fragmentada em `SCOPE.md` + `SPEC.md` + `CHANGELOG.md` + `BRIEFING.md`.
3. **SRS virou zumbi tentando ser isso.** `Modules/SRS` (ex-MemCofre) tinha `RequirementsFileReader` + `ModuloController` que **já consolidavam** meta/backlog/changelog por módulo — mas o módulo está em deprecação ([SRS/DEPRECATION-PLAN.md](../../requisitos/SRS/DEPRECATION-PLAN.md)). A função de valor morreria junto.
4. **O KB já é um sistema de governança quase completo.** `Modules/KB` (ADR 0149/0150) tem: `type=charter` como nó read-only (invariante Tier 0 testada em `GovernanceInvariantsTest`), aresta `charter-of` auto-derivada, versionamento via git, categoria `governance`, trilhas, comentários, grafo, RAG. **~70% do que precisamos já existe.**
5. **Champion é inalcançável sem motor.** O SCREEN-GRADE ([SCREEN-GRADE-BOARD](../../governance/scorecards/SCREEN-GRADE-BOARD-2026-05-30.md)) mostra **0 de 222 telas em Champion (95-100)**. O board é uma *foto* — constata, não faz subir. Falta o **motor** que define alvo + mede + trava regressão (ratchet, ADR 0236) + força conformidade no CI.
6. **Wagner quer autonomia.** O loop Cowork↔Code autônomo ([ADR 0241](0241-loop-design-cowork-code-autonomo-zero-humano.md)) + charters de papel/champion ([ADR 0242](0242-charters-papel-governanca-loop-cowork-code.md)) precisam de um **objeto governado** sobre o qual os agentes operam com HITL mínimo.

Estado-da-arte mundial 2026 (pesquisa em [CONCEITO §7](../../requisitos/KB/CONCEITO-CHARTER-GOVERNANCE-V1.md)) converge: **catálogo no git + governança na plataforma** (Backstage), **status de confiança com cadência de re-verificação** (Guru), **lifecycle decidido≠verdadeiro** (Oxide RFD), **append-only imutável** (MADR), **linter de contrato no CI** (Spectral) e **charter mínimo IA-aware** (Gloaguen 2026: charter inchado degrada o agente, +20% custo).

---

## Decisão

**Adotamos o "Charter Governance no KB": page charters e module charters viram nós governados do grafo de conhecimento (`kb_nodes`), com núcleo imutável (git) + evolução por contribuição supervisionada + workflow de autorização — servindo como motor de maturidade rumo a Champion.**

### D.1 — Dois níveis de charter (nós tipados)

- **Page Charter** (`type=charter`) — contrato de tela. **Já existe.** Mission/Goals/Non-Goals/UX/Anti-hooks.
- **Module Charter** (`type=module-charter`, NOVO) — contrato de módulo: **Meta · Limite · Backlog · Changelog · Saúde**. Read-only consolidado de fontes que já existem (`SCOPE.md`, `SPEC.md`+DoD%, `CHANGELOG.md`, `BRIEFING.md`, `module:grade`), via o `RequirementsFileReader` **salvo do SRS**.

### D.2 — Núcleo imutável vs camada de evolução

| Zona | Conteúdo | Fonte da verdade | Como muda | Autoriza |
|---|---|---|---|---|
| 🔒 Núcleo | Mission/Goals/Non-Goals/Limite/Meta | **git** (`*.charter.md` → `mcp_memory_documents` → bridge) | só por **PR no git** | merge (owner/Wagner) |
| 💬 Camada KB | sugestões, comentários, anexos aprovados, métricas, verificação | **`kb_*`** | workflow de sugestão no KB | aprovação no KB (owner) |

O núcleo **nunca é editado livre** (preserva ADR 0061 git-canon + a invariante `is_editable=false` testada). A inteligência ao redor cresce de forma supervisionada.

### D.3 — Ciclo de autorização

`propor → revisar → aprovar → publicar`. Publicar bifurca: **mudança de núcleo** → gera PR no `.charter.md` (merge = publicação; bridge re-sincroniza); **anexo KB** → publica bloco aprovado sem tocar o núcleo. Toda ação auditada (`activity_log` + `mcp_audit_log`).

### D.4 — Motor de Champion (loop fechado por métrica — princípio #4 da Constituição)

Charter define o alvo → vira **checks do scorecard** → **CI gate** ("Spectral para charters" + Pest GUARD dos Non-Goals) falha o PR fora de conformidade → **ratchet** (ADR 0236, nota só sobe) + **cadência de re-verificação** (Guru-style) impede regressão. O charter deixa de ser foto e vira motor.

### D.5 — Charter mínimo (regra dura, Gloaguen 2026)

O charter carrega **só o julgamento institucional que linter nenhum captura** (Mission, Non-Goals, Anti-hooks). O que `module:grade`/Pest/stylelint já garantem deterministicamente, o charter **NÃO repete** — senão degrada o agente e queima budget de contexto.

### D.6 — Onde mora + processos (escolha delegada por Wagner)

- **Conhecimento/decisão:** `memory/requisitos/KB/` (módulo dono) + esta ADR em `memory/decisions/proposals/`.
- **Código:** `Modules/KB/` (entities/observers/migrations/controllers) + `resources/js/Pages/kb/` (interface).
- **Charters:** continuam `*.charter.md` no git, bridged pro KB (ADR 0061 preservado).
- **Processos:** `preflight-modulo` (BLOQUEADOR antes de tocar `Modules/KB`) · `multi-tenant-patterns` (Tier 0) · `mwart-process`/`mwart-comparative` (telas) · `charter-write` (gerar drafts) · `commit-discipline` (PR ≤300 linhas, faseado F1→F4) · Pest GUARD. Detalhe em [README-CHARTER-GOVERNANCE.md](../../requisitos/KB/README-CHARTER-GOVERNANCE.md).

### D.7 — Interface: reusa o tri-pane do KB

A interface vive no `Modules/KB` reusando o padrão de `kb/Index.tsx` (AppShellV2 + PageHeader + KpiGrid + lista master + preview markdown + atalhos `j/k/Enter/Esc`) que Wagner aprovou, acrescido do **painel de governança** (sugestões/aprovação/status/maturidade). Spec em [INTERFACE-CHARTER-KB.md](../../requisitos/KB/INTERFACE-CHARTER-KB.md).

---

## Como isto torna o sistema mais autônomo (resposta direta ao Wagner)

| Mecanismo | Contribuição p/ autonomia |
|---|---|
| **Charter = alvo legível por máquina** | o agente sabe **o que construir e o que NÃO** (Non-Goals) sem perguntar → menos HITL |
| **Scorecard + ratchet + CI gate** | o sistema **se auto-mede e se auto-trava** — regressão quebra o PR sozinha (loop fechado, princípio #4) |
| **Sugestão supervisionada + aprovação** | o loop Cowork↔Code (ADR 0241) tem **onde registrar proposta**; humano só **autoriza** (HITL mínimo, não autoria) |
| **Module Charter (meta/limite)** | agente conhece o **teto de ambição** do módulo antes de codar → não estende escopo sozinho (anti goal-drift) |
| **Charter mínimo (Gloaguen)** | contexto barato e preciso → agente opera melhor e mais barato sem humano |
| **Charters de papel (ADR 0242)** | definem **quem** (agente champion) decide/desenha/aplica; o charter de tela/módulo é **o objeto** que esses papéis operam |

**Síntese:** charter de papel (*quem*) + charter de tela/módulo (*o quê*) + scorecard (*como medir*) + ratchet/CI (*como travar*) = sistema que evolui com intervenção humana mínima = **mais autônomo**. O charter governado é a peça que faltava pra fechar o loop.

---

## Consequências

### Positivas
- Fecha a lacuna do nível módulo (ADR 0101) sem criar módulo novo (reusa KB).
- Transforma o SCREEN-GRADE de foto em motor (caminho real pra Champion + anti-regressão).
- **Salva a função de valor do SRS** (`RequirementsFileReader`) — destrava a deprecação com propósito.
- Governança world-class (converge com Backstage/Guru/Oxide/MADR) mas **mínima** (Gloaguen).
- Avança autonomia (ADR 0241/0242) com objeto governado + HITL mínimo.

### Custos / Trade-offs
- Deltas no `Modules/KB` (aditivos, Tier 0 preservado) — ver [SCHEMA-DELTA](../../requisitos/KB/SCHEMA-CHARTER-GOVERNANCE-DELTA.md).
- Execução faseada F1→F4 (commit-discipline ≤300 linhas/PR) — não é big-bang.
- Workflow de aprovação adiciona atrito — mitigado por governança *tiered* (gate proporcional ao risco) e charter mínimo.
- Risco de virar zumbi (lição SRS) → mitigado por **começar read-only** + cadência de verificação + métrica de adoção.

### Neutras / monitorar
- Cadência de re-verificação gera tarefas (boa carga, mas carga) — calibrar intervalo por tier.
- Module Charter read-only V1 (sem dado editável próprio) — decisão deliberada anti-SRS.

---

## Onde NÃO inventar (Tier 0 dentro deste sistema)
- ❌ Tornar charter `is_editable=true` (quebra invariante ADR 0061 + GovernanceInvariantsTest).
- ❌ Editar núcleo direto no KB sem PR no git.
- ❌ Charter que repete o que linter/CI já garante (Gloaguen — degrada agente).
- ❌ Tabela `srs_entries`-like ambiciosa (erro que matou o SRS).
- ❌ Qualquer `kb_*` sem `business_id` + FK (ADR 0093 IRREVOGÁVEL).

---

## Plano (faseado — detalhe na SPEC)

| Fase | Entrega |
|---|---|
| **F0** | Esta ADR aceita por Wagner + número confirmado |
| **F1** | Page charters ganham status workflow + modo sugestão + aprovação (deltas D3/D4/D5) |
| **F2** | Module Charter read-only: bridge `memory/requisitos/<X>/` + tela (D1/D2/D7) — salva `RequirementsFileReader` |
| **F3** | "Publicar = PR" + "Spectral para charters" no CI + Pest GUARD Non-Goals (D6/D9) |
| **F4** | Cadência de re-verificação + trilhas de onboarding por módulo + scorecard bronze→champion (D8) |

---

## Referências

- [CONCEITO-CHARTER-GOVERNANCE-V1.md](../../requisitos/KB/CONCEITO-CHARTER-GOVERNANCE-V1.md) — visão + estado-da-arte mundial
- [ADR 0101](../0101-sistema-charter-capterra-governanca-escopo.md) — Charter-Capterra (pai do conceito)
- [ADR 0149/0150](0150-kb-unificado-grafo-conhecimento-modulo-ia-central.md) — KB grafo (fundação)
- [ADR 0061](../0061-conhecimento-canonico-git-mcp-zero-automem.md) — git canon (núcleo imutável)
- [ADR 0093](../0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0
- [ADR 0236](../0236-*.md) — ratchet (nota só sobe)
- [ADR 0241](0241-loop-design-cowork-code-autonomo-zero-humano.md) · [ADR 0242](0242-charters-papel-governanca-loop-cowork-code.md) — autonomia + charters de papel
- [SRS/DEPRECATION-PLAN.md](../../requisitos/SRS/DEPRECATION-PLAN.md) — origem do `RequirementsFileReader`
- SCREEN-GRADE-BOARD — a meta Champion (0/222 hoje)

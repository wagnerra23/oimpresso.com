---
slug: feedback-mcp-tasks-antes-de-projetar
title: "Consultar o MCP (brief-fetch + tasks-list + decisions-search) ANTES de projetar/auditar/propor feature"
type: feedback
authority: canonical
lifecycle: ativo
session_date: '2026-05-31'
quarter: 2026-Q2
related:
  - '0053'
  - '0070'
  - '0091'
pii: false
---

# Consultar o MCP ANTES de projetar/auditar/propor qualquer feature

> **Regra (Tier 0 de processo):** No início de TODA sessão **e** antes de QUALQUER ação de *projetar / auditar / fazer roadmap / propor backlog / "achar gaps"*, chamar **`brief-fetch`** → **`tasks-list module:<X>`** → **`decisions-search`** (e `memoria-search` se preciso). O MCP é a **fonte viva da verdade** que o time usa. Os docs git (BRIEFING.md / SPEC.md / DOC_*.md) **atrasam** em relação ao código E ao backlog do MCP.

## Por quê (origem: sessão 2026-05-31, Wagner explícito)

Wagner: *"nunca mais repita esse erro de não conhecer o sistema… é ridículo não consultar o MCP antes de projetar funcionalidades."* Ele está certo. Nesta sessão eu projetei um "Método 9.75 Financeiro" inteiro **sem chamar o MCP uma vez** — violei as skills Tier A always-on [`brief-first`](../../.claude/skills/brief-first/SKILL.md) e [`mcp-first`](../../.claude/skills/mcp-first/SKILL.md). Consequências concretas:

1. **Dupliquei backlog que já existia.** O `tasks-list module:Financeiro` mostra que o que eu "descobri e projetei" já estava rastreado: **US-FIN-030** (aging buckets), **US-FIN-026** (anexos UI), **US-FIN-033** (notificações vencimento), **US-FIN-035** (combobox contraparte), US-FIN-031 (bulk), US-FIN-009 (conciliação OFX)… Meu "roadmap" foi re-inventar tasks que o time já tinha.
2. **Trabalhei off-cycle sem saber.** O `brief-fetch` mostra o ciclo ativo = **CYCLE-08 "Receita — monetizar a carteira legacy"** (pricing público, migrar clientes legacy pagando, ComVis, Agrosys). Financeiro-9.75 **não é o foco**. Se eu tivesse chamado brief-fetch no minuto zero, teria avisado o Wagner "isso é off-cycle, confirma?" antes de gastar a sessão.
3. **Projetei em cima de doc stale.** O roadmap v1 saiu da `BRIEFING.md` de 20/mai (atrás do código de 25/mai). Tive que descobrir o drift do jeito caro (pré-flight no código) — quando o MCP já tinha a verdade.

## Como aplicar (protocolo obrigatório)

**Sempre, nesta ordem, ANTES de escrever roadmap/auditoria/SPEC/proposta de feature:**

1. `brief-fetch` → ciclo ativo + foco-missão + EM VOO + HITL pending. **Se o pedido for off-cycle, AVISAR o Wagner antes de executar.**
2. `tasks-list module:<X>` (+ `status`/`priority`) → **o backlog já existe?** Não re-criar US-* que já estão lá. Mapear o trabalho a tasks existentes (ex: "isto fecha US-FIN-030").
3. `decisions-search "<tema>"` → ADR já decidiu? Não recontradizer/re-propor.
4. (se precisar de fato persistente) `memoria-search`.
5. SÓ ENTÃO ler código / projetar. Código > doc git, mas **MCP > tudo** pra estado vivo (ciclo, backlog, decisões).

Regra de ouro: **doc git (BRIEFING/SPEC) é cache que atrasa; MCP (brief + task-ledger) é o estado vivo.** Projetar a partir de doc git sozinho = duplicar task + perder foco de ciclo (ADR 0070 task-management Jira-style, ADR 0091 brief, ADR 0053 MCP server).

## Como prevenir DE VERDADE (não só este doc)

[Lição F3 M-AP-1](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md): *"documentação interna do agente não é gate — só registros externos (hook/CI/skill) impedem regressão."* As skills `brief-first`/`mcp-first` (Tier A) **existem e eu ignorei** — adesão falhou. Proposta de gate mais forte (follow-up): o hook/skill deve **bloquear** ação de design/auditoria/roadmap até que `brief-fetch` E `tasks-list` do módulo-alvo tenham sido chamados na sessão — não só avisar no SessionStart. Enquanto o gate forte não existe, este doc + a vergonha de 2026-05-31 são o lembrete.

## Refs

- Skills [`brief-first`](../../.claude/skills/brief-first/SKILL.md) + [`mcp-first`](../../.claude/skills/mcp-first/SKILL.md) (Tier A always-on — honrar, não ignorar)
- [`feedback-brave-mcp-primeiro-sempre.md`](feedback-brave-mcp-primeiro-sempre.md) (princípio MCP-first irmão)
- [ADR 0091](../decisions/0091-daily-brief.md) (brief-fetch) · [ADR 0070](../decisions/0070-jira-style-task-management-current-md-removed.md) (task ledger) · [ADR 0053](../decisions/0053-mcp-server-governanca-como-produto.md) (MCP server)
- [LICOES_F3_FINANCEIRO_REJEITADO.md](../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) M-AP-1 (gates externos)

---
**Gravado:** 2026-05-31 — a pedido do Wagner, após eu projetar o Método 9.75 Financeiro sem consultar o MCP (duplicando US-FIN-026/030/033/035 + ignorando que CYCLE-08 é Receita, não Financeiro).

---
slug: 0242-charters-papel-governanca-loop-cowork-code
number: 242
title: "Charters de papel — [W] soberano + agentes champion ([CC]/[CL]/[CD]/[CA]): memória-por-papel formaliza quem decide, desenha, aplica e trava no loop Cowork↔Code"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
proposed_at: "2026-05-31"
decided_at: "2026-05-31"
module: governance
quarter: 2026-Q2
tier: CANON
trust_level: tier-0-irrevogavel
tags: [governance, charter, memoria-por-papel, cowork-loop, champion-test, papeis, tier-0]
related:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0238-soberania-constituicao-wagner
  - 0241-loop-design-cowork-code-autonomo-zero-humano
related_adrs: [0079, 0094, 0114, 0238, 0241]
supersedes: []
authors: [wagner, claude-code]
dossier: prototipo-ui/CHARTER_GOVERNANCA_W.md
---

# ADR 0242 — Charters de papel: memória-por-papel do loop Cowork↔Code

> **Status:** ✅ **AUTORIZADA por [W] em 2026-05-31** ("vai vai"). Propostos por [CC] (Cowork) como
> `CHARTER_GOVERNANCA_W.md` + `CHARTER_CHAMPION_AGENTES.md`; **portados pro git pelo [CL] Claude Code**
> (número 0242 livre verificado em `origin/main`, topo era 0241 — [CC] não cunha número, ADR 0238).
> **Pendente:** merge em `main` pelo [W] — ato de versionamento final (Tier 0 · publication-policy / R10).
> Os papéis pertencem ao loop de [W]; [CC] propõe, [CL] porta, **[W] decide**.

---

## Contexto

A lei do loop Cowork↔Code já existe e é canon:

- **ADR 0079** — 7 camadas de governança.
- **ADR 0094** — Constituição Oimpresso V2 (7 camadas · 8 princípios duros).
- **ADR 0238** — a constituição é **soberania exclusiva de [W]** (autoria, modificação só por [W], mudança = reindexação, append-only).
- **ADR 0241** — o loop roda **autônomo (0-humano)**: gates de CI substituem os hops manuais de [W]; merge autônomo pro não-Tier-0; **Tier 0 fica humano**.
- **ADR 0114** + `prototipo-ui/PROTOCOL.md` — o loop em 6 papéis × 7 fases.

O que **faltava** não era *como* o loop corre (0241) nem *quem é dono da constituição* (0238) — era a **memória-por-papel**: o que faz cada papel ser *champion do seu pedaço* (Mission/Goals/Non-Goals/Champion-Test/anti-patterns), e — principalmente — o que cada papel **NÃO faz**, pra parar de virar muleta de [W] a cada sessão.

Sem esse registro, cada sessão **re-derivava os papéis de cabeça** — origem do anti-pattern nº1 catalogado: *[W] vira carteiro de status / responde o que o git já responde*. É o mesmo problema que o charter-first (L-14) resolveu pra **tela**; aqui aplica-se a **papel**.

Os dois charters foram redigidos no Cowork (working-docs, não-canon) e ficaram na fila `COWORK_NOTES.md → 📥 Pendentes` (item: "1 ADR de papéis + os 2 charters colados · Tier 0 · abre PR e espera [W]").

## Decisão

Adotam-se como **canônicos** dois charters de papel (memória-por-papel, charter-first aplicado a papel), versionados em `prototipo-ui/` ao lado de `PROTOCOL.md` (a lei dos 6 papéis × 7 fases que eles detalham):

1. **[`prototipo-ui/CHARTER_GOVERNANCA_W.md`](../../prototipo-ui/CHARTER_GOVERNANCA_W.md)** — **[W] como champion soberano de Tier 0.** FAZ só: aprovar ADR/constituição, multi-tenant, segredo, tooling/lint, produto, e o subjetivo que o git não responde (estético/estratégico/prioridade/dinheiro); briefar (início) + autorizar merge Tier 0 (fim); transformar erro em gate. NÃO: virar carteiro de status, responder o checável, microgerenciar F1/F3, editar a constituição no automático, aprovar no impulso. **Champion Test:** o loop roda 1 semana sem [W] tocar em nada exceto aprovar 2–3 Tier 0.

2. **[`prototipo-ui/CHARTER_CHAMPION_AGENTES.md`](../../prototipo-ui/CHARTER_CHAMPION_AGENTES.md)** — **os agentes como champion de cada fase:** **[CC]** (design F1 — protótipo que passa o gate de 1ª, guardião da identidade, propõe nunca impõe, não cunha número, read-only no git), **[CL]** (code F3 — Passo 0 base fresca, valida contra `main` sozinho §10.4, 1 unidade = 1 PR, retorno automático §10.2, **não mergeia Tier 0 sem [W]**), **[CD]/[CA]** (crítica F1.5 + a11y F3.5 — trava objetiva ≥80 / WCAG AA como auto-check de quem produz). **Fio comum:** passa o gate de 1ª · fecha o loop no verificável · propõe não impõe · erro vira gate, não culpa.

Natureza desta decisão:

- **É evolução/aplicação, não reescrita.** Não duplica 0238 (soberania) nem 0241 (mecânica do loop) nem 0079/0094 (camadas/constituição) — **referencia-os e os opera** em forma de papel. Onde houver conflito, vence a lei-mãe (0238/0241/0094), não o charter.
- **[CC] não cunha número de ADR** (0238): o número 0242 é livre no git, atribuído pelo [CL].
- **Append-only (ADR 0003):** mudança futura de papel = atualizar o charter + registrar; nunca apagar.

## Consequências

**Positivas**
- Papéis param de ser re-derivados a cada sessão → menos contexto-de-cabeça, menos drift de papel.
- O **Champion Test** vira régua objetiva de governança saudável (mede o quanto o loop precisa de [W] — idealmente pouco).
- Reforça o conserto canônico "erro vira gate, não culpa": quando [W] vira muleta, o fix é ratchet/canal/regra-acima — agora escrito por papel.

**Custo / limites**
- Memória-por-papel é append-only: evoluir um papel custa um registro (não um overwrite).
- Os charters vivem em `prototipo-ui/` (espelho do loop Cowork↔Code) — **não** em `resources/js/Pages/` (não são page-charters) nem em `_DesignSystem/` (evita falso-positivo de "doc-canon órfão" no `design-index-gate`, que cobra só `memory/requisitos/_DesignSystem/**`).
- Decisão Tier 0: só entra em `main` pelo merge de [W].

## Referências

- [ADR 0079](0079-constituicao-oimpresso-7-camadas-governanca.md) · [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) · [ADR 0238](0238-soberania-constituicao-wagner.md) · [ADR 0241](0241-loop-design-cowork-code-autonomo-zero-humano.md) · [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md)
- `prototipo-ui/PROTOCOL.md` §2 (overlay autônomo) · §10.2 (retorno) · §10.4 (gate de validação + Passo 0)
- Charters: [`CHARTER_GOVERNANCA_W.md`](../../prototipo-ui/CHARTER_GOVERNANCA_W.md) · [`CHARTER_CHAMPION_AGENTES.md`](../../prototipo-ui/CHARTER_CHAMPION_AGENTES.md)
- Origem da fila: handoff Cowork "Oimpresso ERP Comunicação Visual" → `COWORK_NOTES.md → 📥 Pendentes` (item #2)

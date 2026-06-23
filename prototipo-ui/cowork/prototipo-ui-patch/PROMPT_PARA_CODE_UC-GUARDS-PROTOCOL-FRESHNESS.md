# PROMPT_PARA_CODE — GUARDs a partir dos Casos de Uso + check `protocol_freshness`

> **Origem:** [CC] 2026-06-03 (view `rep-loop-casos` no `metricas.html` + os 2 docs de Casos de Uso que [W] anexou: Vendas e Oficina). [W]: "põe o botão e que nunca mais saia aquela merda de lá."
> **Natureza:** §10.4 PROPOSTA. Estende o padrão que JÁ existe (charter `PRECISA TER` + GUARDs Pest tipo `R-JANA-COCKPIT-001..007` · `review-freshness`). **NÃO** numera ADR, **NÃO** mergeia Tier 0.

## Passo 0 — verificar vs origin/main
1. O padrão charter→GUARD já existe? (sim — ex. `Cockpit.charter.md` + 7 Pest `R-JANA-COCKPIT-*`). **Reusar o padrão**, não inventar.
2. Convenção de seletor pra asserção = `data-testid` (já usada: `so-card-*`, `board-column-*`). Confirmar.
3. `review-freshness.mjs` (#2078) é o molde do check de frescor. Estender, não recriar.

## Fonte (os UCs reais — [W] anexou; URLs no paste)
- Casos de Uso — **Vendas** (UC-V01..07 comuns · UC-R01..03 revenda · UC-C01..05 comunicação visual). Cada UC tem uma linha **"A tela precisa:"** = a coluna `PRECISA TER`.
- Casos de Uso — **Oficina** ("Padrão Anti-Regressão").

## Tarefa A — UC → charter PRECISA TER + GUARD (começar pelo canon)
Para cada UC das telas canon (`Sells/Create`, `Sells/Show`, `OficinaAuto/ServiceOrders/*`):
1. Extrair a linha **"A tela precisa:"** → adicionar como item **PRECISA TER** na `<Tela>.charter.md`, com o **UC-id como rationale** (o porquê: ex. "UC-V04 — aprovação rastreável").
2. Escrever um **GUARD Pest** tagueado `uc-<id>` (ex. `->group('guard','uc-v04')`) que afirma a presença do elemento (via `data-testid`/componente). Some o elemento → vermelho.
3. Ex. concreto (UC-V04): botão `btn-enviar-orcamento` presente + estado `Aguardando aprovação` no enum. (snippet ilustrativo na view `rep-loop-casos`.)
4. Idempotente: se a charter já tem o item / o GUARD já existe, não duplicar.

## Tarefa B — check `protocol_freshness` (fecha o gap que [W] apontou)
No `jana:health-check` (advisory), acende quando:
- **(a)** existe UC (nos docs de casos) **sem** GUARD linkado (`uc-<id>` ausente nos testes);
- **(b)** existe tela canon **sem** charter;
- **(c)** charter cita um UC que **não existe mais**.
Reusa o motor do `review-freshness`. Emite no digest do ciclo diário (ponte irmã `CICLO-DIARIO-GOVERNANCA`). É a "detecção mecânica" — a lei (PROTOCOL) continua [W]; o check só **acende** e o [CL] propõe a reconciliação (§10.4).

## Guards / Tier 0
- **Advisory.** Não derruba CI/cron. Não auto-mergeia Tier 0. Não numera ADR.
- Cor/seletor: tokens DS + `data-testid` (sem cor crua). Começar pelas telas canon — não varrer 239 telas de uma vez.

## §10.4 / autorização
Aditivo (charters + Pest + 1 check). Retorno em `CODE_NOTES.md` com: quantos UCs viraram GUARD, quantos acenderam no `protocol_freshness`, e a lista do que ficou sem cobertura.

## new_design_memories
- **golden**: cada UC ("A tela precisa:") vira PRECISA TER (o porquê) + GUARD Pest `uc-<id>` (a trava) — some o elemento = build vermelho. O doc de casos para de defasar porque está amarrado ao teste (`protocol_freshness` acende o que falta).
- **regra**: tela canon sem charter, ou UC sem GUARD, ou charter citando UC morto = amarelo no digest diário.

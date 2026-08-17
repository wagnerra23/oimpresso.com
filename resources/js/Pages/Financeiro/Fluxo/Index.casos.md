---
id: resources-js-pages-financeiro-fluxo-index-casos
casos: Fluxo de caixa · /financeiro/fluxo
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/fluxo

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Personas: **Eliana [E]** (posição de caixa em <30s) + **Wagner [W]**. Tela **read-only**: nenhuma mutação.

## UC-FLX-01 — "Quanto tenho hoje e como fico em 30 dias?"
Status: 🧪 (`FluxoControllerTest` — shape `saldo_hoje/saldo_30d/pior_dia/margem_minima/conta/dias`)
Dado contas bancárias ativas e títulos abertos · Quando Eliana abre a aba **Projetado** · Então os 4 KPIs mostram Saldo hoje (com o nome da conta principal + "outras N"), Projeção 30 dias com delta vs hoje, Pior dia previsto e Margem mínima.
**Pronto quando:** `saldo_30d` = saldo de hoje ± o líquido dos títulos da janela e o caption nomeia a conta.

## UC-FLX-02 — Dia abaixo da margem mínima grita antes de acontecer
Status: 🧪 (`FluxoControllerTest` — `margem_minima` default do Q3)
Quando qualquer dia projetado cai abaixo da margem · Então a barra do dia muda de tom e a linha tracejada da margem fica visível no gráfico.
**Pronto quando:** dia < margem renderiza no tom de alerta e o "Pior dia previsto" aponta o mesmo dia.

## UC-FLX-03 — Janela de projeção é clampada
Status: 🧪 (`FluxoControllerTest` — clamp `?dias=N` 7..60, default 35)
Quando `?dias` vem fora de 7..60 (ou ausente) · Então o servidor clampa e a tela segue coerente com o rótulo do header.

## UC-FLX-04 — Realizado mostra 12 meses confirmados
Status: 🧪 (`FluxoRealizadoControllerTest` — payload canon + 12 meses + shape por mês)
Quando Eliana troca pra aba **Realizado** · Então vê Saldo 12M, Entradas, Saídas e Baixas registradas, barras gêmeas entrada×saída por mês (mês atual destacado) e a tabela "Detalhe por mês" com linha total.
**Pronto quando:** `totais.saldo == totais.entradas - totais.saidas` (invariante contábil) e a série tem exatamente 12 meses.

## [BACKLOG] Estorno não entra no Realizado
Status: ⬜ sem prova — o único whereNull('estorno_de_id') do arquivo está dentro do teste de Tier 0, computando o valor esperado — não é teste DE estorno. Vira `UC-FLX-05` quando existir teste citando o id (G-2).
Dado baixa com `estorno_de_id` preenchido · Quando a aba Realizado agrega · Então a baixa estornada é ignorada (visão "líquido confirmado").

## UC-FLX-06 — Aba inválida cai no default e o Projetado não paga a query do Realizado
Status: 🧪 (`FluxoRealizadoControllerTest` — tab inválida → projetado; lazy load)
Quando `?tab` é inválida · Então renderiza Projetado; e com `tab=projetado` o payload `realizado` chega nulo (perf).

## UC-FLX-07 — Tier 0 e read-only
Status: 🧪 (`FluxoControllerTest` + `FluxoRealizadoControllerTest` — business_id scope · GET não muta)
Nenhuma query enxerga outro `business_id` ([ADR 0093]) e um GET em `/financeiro/fluxo` (qualquer aba) não escreve nada.

## Backlog de casos (sem id — entram quando tiverem teste)
- **[BACKLOG] Empty state dos próximos eventos** — sem eventos em 7 dias, a tabela mostra "Nenhum evento programado nos próximos 7 dias." (hoje sem asserção).
- **[BACKLOG] Tooltip de barra** — hover exibe `data · saldo acumulado` (visual, sem prova).

## Como rodar
Lane `financeiro-pest` (MySQL real, CT100): `FluxoControllerTest`, `FluxoRealizadoControllerTest`, `FluxoCaixaServiceTest`.

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork (leva 1) — a tela tinha charter v2 e testes, mas **nenhum `casos.md`**: reprovava o gate do trio. Os `it()` existentes precisam citar os ids acima (G-2).

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md

---
id: resources-js-pages-financeiro-relatorios-index-casos
casos: Relatórios gerenciais · /financeiro/relatorios
irmaos: charter ao lado (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-08-17"
---

# Casos de uso — /financeiro/relatorios

> **Status:** ✅ passa (prova no manifesto) · 🧪 prova existe mas o `it()` ainda **não cita o id** (G-2 — corrigir no mesmo PR) · ⬜ sem prova · ❌ quebrou.
>
> Redigido pelo [CC] em 2026-08-17 a partir do charter da tela + do protótipo Cowork. Regra G-2 ([ADR 0264]) respeitada: comportamento **sem teste** fica em **[BACKLOG] sem id** — `UC-*` órfão quebra o `casos-gate`.

Charter em **draft** (aguarda [W] aprovar Non-Goals/Anti-hooks). Tela 100% leitura.

## UC-REL-01 — Fluxo projetado × realizado por semana
Status: 🧪 (`tests/Feature/Modules/Financeiro/RelatoriosTest.php`)
Quando o usuário abre a aba Fluxo com um período · Então vê KPIs (projetado/realizado a receber e a pagar, saldos) e a tabela semanal com as barras proj/real.

## UC-REL-02 — Resumo do período com atenção a vencidos
Status: 🧪 (`RelatoriosTest`)
Aba Resumo: a receber/a pagar em aberto, recebido/pago no período, saldos e o card de vencidos com link pro detalhe.

## UC-REL-03 — Filtro de período recarrega só o relatório
Status: 🧪 (`RelatoriosTest` — partial reload `only: ['filters','fluxo','resumo']`)
Mudar de/até (ou usar "Últimos 4 meses") não re-carrega a página inteira nem muta nada.

## [BACKLOG] Export CSV abre Excel BR sem quebrar acento
Status: ⬜ sem prova — test_export_csv_retorna_csv prova content-type e disposition, NÃO o BOM UTF-8 nem o separador ;. Vira `UC-REL-04` quando existir teste citando o id (G-2).
O CSV do período/aba sai com BOM UTF-8 e separador `;`.

## UC-REL-05 — DRE não vive mais aqui
Status: 🧪 (`RelatoriosTest` — banner/redirect pra `/financeiro/dre`)
A aba DRE saiu em 2026-05-20; a tela avisa e leva pra tela dedicada.

## UC-REL-06 — Tier 0 e zero mutação
Status: 🧪 (`RelatoriosTest` — BusinessScope; GET não escreve)
Relatório é sempre tenant-isolado ([ADR 0093]); não lança, não baixa, não recalcula.

## Backlog de casos (sem id)
- **[BACKLOG] Agendamento/e-mail do relatório** — Anti-hook: NÃO existe; export é sob demanda. Vira UC se alguém implementar.

## Trilha do tempo
- 2026-08-17 · [CC] criado no espelho Cowork. Pendência do charter permanece: [W] precisa aprovar Non-Goals + Anti-hooks pra sair de `draft`.

[ADR 0264]: ../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md
[ADR 0093]: ../../memory/decisions/0093-multi-tenant-isolation-tier-0.md

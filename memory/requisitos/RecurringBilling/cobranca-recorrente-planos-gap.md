---
id: requisitos-recurring-billing-cobranca-recorrente-planos-gap
tela: RecurringBilling/Planos/Index (/recurring-billing/planos)
prototipo: prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx
tela_viva: resources/js/Pages/RecurringBilling/Planos/Index.tsx
gerado_em: 2026-11-19
---

# Gap — Cobrança Recorrente · Planos (protótipo × tela viva)

> **Refeito em 2026-11-19** (lote L6 da auditoria RecurringBilling, item e5), base `origin/main` `bedeeb0096`.
> A versão de 2026-09-06 comparava com um placeholder (`:332-342`, `:370`). Desde o #8913 (07/10) a vista
> Planos do protótipo é real (`Planos()` `:742-839`, formulário `PlanoForm` `:696-740`) e aquelas linhas hoje
> são o drawer de assinatura. A versão antiga está no git.
>
> **Papel do protótipo — decisão [W] 2026-11-19 (b4):** fonte de design. Forma: o protótipo vale (UI-0029);
> comportamento, dado e permissão: a produção e o charter. Tela-mãe: `cobranca-recorrente-gap.md`.
> Leitura de código; a medição de render é o PR 2 do L6.

## Tabela de partes

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | Protótipo `:1090` + hero `:1096-1107`: "Planos · cobrança recorrente", linha `N PLANOS · N ATIVOS · MRR potencial`, "Novo plano" com `kbd N`. Vivo `Planos/Index.tsx:361-404`: mesmo título, linha e CTA, mais um botão "Voltar" para `/recurring-billing`. | Nada — paridade; o "Voltar" sai junto com a decisão de navegação da tela-mãe |
| KPIs | Protótipo `:759-768`: Ticket médio · MRR potencial (hero), Total planos, Total ativos, Plano top vendido. Vivo `:407-445`: os mesmos 4 com sparkline no hero. | Nada — paridade |
| Distribuição por ciclo | Protótipo `:770-779`: 5 caixas (mensal a customizado). Vivo `CicloDistribuicao` `:172-203`, renderizado em `:447-452`. | Nada — paridade |
| Busca | Protótipo `:782-789`: campo `/` por nome ou slug + botão Buscar. Vivo `:456-481`: o mesmo, no servidor. | Nada — paridade |
| Tabela | Protótipo `:797-818`: Plano (nome, slug, descrição curta), Ciclo, Valor, Assinaturas, Fiscal, Status, ações Editar/Excluir. Vivo `:488-560`: as mesmas colunas e ações. | Nada — paridade |
| Excluir plano | Protótipo `Confirm` `:830-837`: diálogo próprio; com assinaturas ativas o botão Excluir fica desabilitado e o texto explica o bloqueio. Vivo `handleDelete` `:338-353`: `window.confirm` nativo com aviso; o bloqueio vem do servidor (422). | **Decidir.** Diálogo próprio com bloqueio visível antes de enviar |
| Criar e editar | Protótipo: `PlanoForm` em painel sobreposto sobre a lista (`:696-740`, aberto por `formPlano`; `ROTAS` `:1059` mapeia novo/editar para as Pages Create/Edit). Vivo: Pages dedicadas `Planos/Create.tsx` e `Planos/Edit.tsx`; o charter do Index registra drawer no Index como Non-Goal. | **Decidir.** Painel sobreposto (protótipo) × página dedicada (charter) — conflito de forma, o protótipo vale até o [W] dizer outra coisa |
| Vazio | Protótipo `:791-796`: "Nenhum plano cadastrado" também para busca sem resultado (f2 da auditoria — defeito do protótipo). Vivo `EmptyState` `:612-631`. | Nada — o defeito vai ao Cowork no L7 |
| Paginação | Protótipo `:821-825`: "N planos no total · Página 1 de 1". Vivo `:565-575`: paginação real. | Nada — paridade |
| Atalhos e ajuda | Protótipo: `/` e `N`. Vivo: tour, `?`, ⌘K (`CmdPalette`), `j/k` (`:579-597`). | Nada — vivo à frente |

---
id: requisitos-recurring-billing-cobranca-recorrente-planos-gap
tela: RecurringBilling/Planos/Index (/recurring-billing/planos)
prototipo: prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx
tela_viva: resources/js/Pages/RecurringBilling/Planos/Index.tsx
gerado_em: 2026-11-19
---

# Gap — Cobrança Recorrente · Planos (protótipo × tela viva)

> **Refeito em 2026-11-19** (lote L6 da auditoria RecurringBilling, item e5), sobre `origin/main` `99f169a688`,
> com o protótipo do #11241 (L7). A versão de 2026-09-06 comparava com um placeholder que deixou de existir no
> #8913 (07/10); hoje a vista Planos do protótipo é `Planos()` `:824-927` com o formulário `PlanoForm` `:778-822`.
> A versão antiga está no git.
>
> **Papel do protótipo — decisão [W] 2026-11-19 (b4):** fonte de design. Forma: o protótipo vale (UI-0029);
> comportamento, dado e permissão: a produção e o charter. Tela-mãe: `cobranca-recorrente-gap.md`.
> Leitura de código; a medição de render é o PR 2 do L6.

## Tabela de partes

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | Protótipo `:1255` + hero `:1261-1272`: "Planos · cobrança recorrente", linha `N PLANOS · N ATIVOS · MRR dos planos`, "Novo plano" com `kbd N`. Vivo `Planos/Index.tsx:361-404`: mesmo título e CTA; a linha diz "MRR potencial"; tem ainda um botão "Voltar" para `/recurring-billing`. | **Decidir.** Texto "MRR dos planos"; o "Voltar" sai junto com a decisão de navegação da tela-mãe |
| KPIs | Protótipo `:842-851`: Ticket médio · MRR (hero), Total planos, Total ativos, Plano top vendido. Vivo `:407-445`: os mesmos 4 com sparkline no hero. | Nada — paridade |
| Distribuição por ciclo | Protótipo `:852-862`: 5 caixas (mensal a customizado). Vivo `CicloDistribuicao` `:172-203`, renderizado em `:447-452`. | Nada — paridade |
| Busca | Protótipo `:864-871`: campo `/` por nome ou slug + botão Buscar. Vivo `:456-481`: o mesmo, no servidor. | Nada — paridade |
| Tabela | Protótipo `:884-908`: Plano (nome, slug, descrição curta), Ciclo, Valor, Assinaturas, Fiscal, Status, ações Editar/Excluir. Vivo `:488-560`: as mesmas colunas e ações. | Nada — paridade |
| Excluir plano | Protótipo `Confirm` `:918-926`: diálogo próprio; com assinaturas ativas o botão Excluir fica desabilitado e o texto explica o bloqueio. Vivo `handleDelete` `:338-353`: `window.confirm` nativo com aviso; o bloqueio vem do servidor (422). | **Decidir.** Diálogo próprio com bloqueio visível antes de enviar |
| Criar e editar | Protótipo: `PlanoForm` em painel sobreposto sobre a lista (`:778-822`; `ROTAS` `:1201-1208` mapeia novo/editar para as Pages Create/Edit). Vivo: Pages dedicadas `Planos/Create.tsx` e `Planos/Edit.tsx`; o charter do Index registra drawer no Index como Non-Goal. | **Decidir.** Painel sobreposto (protótipo) × página dedicada (charter) — conflito de forma, o protótipo vale até o [W] dizer outra coisa |
| Vazio | Protótipo `:872-883`: dois textos — "Nenhum plano cadastrado" (sem dado) e "Nenhum plano encontrado para …" (busca). Vivo `EmptyState` `:612-631`: só "Nenhum plano cadastrado", também na busca sem resultado. | **Decidir.** Separar o vazio de primeira vez do vazio de busca |
| Paginação | Protótipo `:910-914`: "N planos no total · Página 1 de 1". Vivo `:565-575`: paginação real. | Nada — paridade |
| Atalhos e ajuda | Protótipo: `/` (`:829-833`) e `N`. Vivo: tour, `?`, ⌘K (`CmdPalette`), `j/k` (`:579-597`). | Nada — vivo à frente |

---
tela: RecurringBilling/Index (/recurring-billing)
prototipo: prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx
tela_viva: resources/js/Pages/RecurringBilling/Index.tsx
id: requisitos-recurring-billing-cobranca-recorrente-gap
gerado_em: 2026-11-19
---

# Gap — Cobrança Recorrente · Assinaturas (protótipo × tela viva)

> **Refeito em 2026-11-19** (lote L6 da auditoria RecurringBilling, item e5), base `origin/main` `bedeeb0096`.
> A versão anterior (2026-06-30, refeita em tabela em 2026-09-06) dizia que o protótipo era um mockup atrás
> da produção, com Planos/Faturas/Configurações como placeholders e "nada a adotar". Isso deixou de valer:
> o #8913 (07/10) deu uma vista real a cada aba e o protótipo passou a desenhar drawer, criação, edição,
> Pix Automático e cartão recorrente. A versão antiga está no git (`git log -- <este arquivo>`).
>
> **Papel do protótipo — decisão [W] 2026-11-19 (b4 da auditoria):** `cobranca-recorrente-page.jsx` é
> **fonte de design**. O cabeçalho do arquivo ainda se descreve como porte do git; a decisão vale sobre ele.
> Onde protótipo e produção divergem em FORMA, o protótipo é a referência (ADR UI-0029); comportamento,
> dado e permissão seguem a produção. Âncora: `node scripts/design/ancora.mjs RecurringBilling/Index`
> → `related_prototype (charter)`.
>
> Esta tabela é leitura de código, não medição de render. A medição (design-diff nos dois lados) é o
> PR 2 do L6 e fica registrada no `Index-visual-comparison.md`.

## Tabela de partes

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | Protótipo `:1088-1107` (`HEAD.assinaturas` `:1089`): h1 + linha mono `N ATIVAS · MRR · CHURN` + "Nova assinatura" com `kbd N`. Vivo `Index.tsx:514-573`: mesmo h1, mesma linha (dos KPIs do servidor) e mesmo CTA. | Nada — paridade |
| Navegação entre as 4 vistas | Protótipo: a sub-nav é a do shell (`window.PageHeaderNav` `:1108`, abaixo do cabeçalho), uma rota por vista (`ROTAS` `:1056-1063`). Vivo: abas próprias dentro do cabeçalho (`:529-560`, `router.visit` para as 3 outras Pages); Planos tem só "Voltar" e Faturas/Configurações não têm navegação. | **Decidir.** Trocar as abas próprias pela sub-nav canônica (`PageHeaderTabs`/`ModuleTabs`, abaixo do cabeçalho) nas 4 Pages — mudança de forma, entra com medição |
| KPIs | Protótipo `KpiStrip` `:165-187`: MRR hero, Churn, Próxima cobrança, Retentado falhos com dica `requer ação · <valor> a recuperar`. Vivo `:576-613`: os mesmos 4; a dica de Retentado falhos é só "requer ação". | **Decidir.** Valor a recuperar na dica — toca valor (regra mestre): soma no presenter PHP, nunca no front |
| Filtros e busca | Protótipo: `FinPeriodBar` (campo de data + período) `:620-624` + coluna de filtros `:625-665` (favoritos, status, próxima cobrança com intervalo, por plano, MRR filtrado) + busca `/` `:667-674`. Vivo `:622-748`: coluna com os mesmos blocos, tudo no servidor; sem barra de período. | **Decidir.** Adotar a barra de período compartilhada com o Financeiro |
| Lista | Protótipo `SubRow` `:189-208` e lista `:666-689`: estrela, avatar, cliente, `plano · ciclo · desde · próx.`, status + `método · valor`, vazio com CTA. Vivo `:751-839`: mesma linha, estrela persistida, `j/k`, skeleton. | Nada — paridade |
| Detalhe da assinatura | Protótipo `Drawer` `:311-458`: painel sobreposto com abas Detalhes \| ✦ IA, próxima cobrança, dados, nota pinada, fiscal, Pix Automático (`:213-246`), cartão recorrente (`:253-286`), histórico de 12 meses (`:288-309`), notas e eventos, rodapé de ações. Vivo: 3ª coluna fixa `:841-849` com `DetailDrawer` `:928-1146` — tudo isso menos o cartão recorrente, IA inline (`JanaPanel`), PDF no topo. | **Decidir.** Painel sobreposto com aba IA (forma). O bloco de cartão recorrente é a b5 da auditoria — não entra aqui |
| Nova assinatura | Protótipo `NovaAssinatura` `:482-534`: cliente, plano opcional, valor, ciclo, próxima cobrança, gateway, forma, descrição. Vivo `NewSubscriptionDrawer` `:1446-1772`: os mesmos campos, busca de cliente no servidor. | Nada — paridade. O campo de valor do protótipo usa ponto decimal sem máscara (f1 da auditoria) — o vivo não copia isso |
| Editar cobrança | Protótipo `EditarCobranca` `:460-480`: valor, ciclo, forma. Vivo `EditSubscriptionDrawer` `:1777-1906`: os mesmos três; cliente, plano e data ficam fixos. | Nada — paridade |
| Dados de exemplo | Protótipo `SUBS` `:44-83`: carteira fictícia de gráfica. Vivo: dados reais por empresa. | Nada — dado de exemplo não entra no app |

## O que não é desta tabela
- Defeitos do próprio protótipo (f1–f6 da auditoria: máscara de valor, estados de erro, acessibilidade do drawer, números de churn/MRR) vão ao Cowork no L7 — o espelho `prototipo-ui/cowork/Wagner/` não se edita aqui.
- Cartão recorrente no drawer: decisão b5.

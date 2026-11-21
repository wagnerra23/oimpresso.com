---
tela: RecurringBilling/Index (/recurring-billing)
prototipo: prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx
tela_viva: resources/js/Pages/RecurringBilling/Index.tsx
id: requisitos-recurring-billing-cobranca-recorrente-gap
gerado_em: 2026-11-19
---

# Gap — Cobrança Recorrente · Assinaturas (protótipo × tela viva)

> **Refeito em 2026-11-19** (lote L6 da auditoria RecurringBilling, item e5), sobre `origin/main` `99f169a688`,
> já com o protótipo do #11241 (L7, f1–f6). A versão anterior (2026-06-30, retabelada em 2026-09-06) dizia que o
> protótipo era um mockup atrás da produção, com Planos/Faturas/Configurações como placeholders e "nada a adotar".
> Isso deixou de valer no #8913 (07/10), que deu uma vista real a cada aba. A versão antiga está no git.
>
> **Papel do protótipo — decisão [W] 2026-11-19 (b4 da auditoria):** `cobranca-recorrente-page.jsx` é
> **fonte de design**. Onde protótipo e produção divergem em FORMA, o protótipo é a referência (ADR UI-0029);
> comportamento, dado e permissão seguem a produção. Âncora: `node scripts/design/ancora.mjs RecurringBilling/Index`
> → `related_prototype (charter)`.
>
> Esta tabela é leitura de código, não medição de render. A medição (design-diff nos dois lados) é o
> PR 2 do L6 e fica registrada no `Index-visual-comparison.md`.

## Tabela de partes

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | Protótipo `:1253-1272`: h1, linha mono `N COBRANDO · MRR · CHURN DO MÊS %` (`:1254`), "Nova assinatura" com `kbd N`. Vivo `Index.tsx:514-573`: mesmo h1 e CTA; a linha diz `N ATIVAS · MRR · CHURN %`. | **Decidir.** Texto da linha ("cobrando", "churn do mês") — a contagem vem do servidor |
| Navegação entre as 4 vistas | Protótipo: a sub-nav é a do shell (`window.PageHeaderNav` `:1273`, abaixo do cabeçalho), uma rota por vista (`ROTAS` `:1201-1208`). Vivo: abas próprias dentro do cabeçalho (`:529-560`, `router.visit` para as 3 outras Pages); Planos tem só "Voltar" e Faturas/Configurações não têm navegação. | **Decidir.** Trocar as abas próprias pela sub-nav canônica (`PageHeaderTabs`/`ModuleTabs`, abaixo do cabeçalho) nas 4 Pages |
| KPIs | Protótipo `KpiStrip` `:215-240`: MRR hero, Churn este mês (dica com o total), Próxima cobrança, "Retentando ou falhou" com dica `requer ação · <valor> a recuperar`. Vivo `:576-613`: os mesmos 4; o último se chama "Retentado falhos" e a dica é só "requer ação". | **Decidir.** Rótulo do 4º KPI e valor a recuperar na dica — o valor toca a regra mestre: soma no presenter PHP, nunca no front |
| Filtros e busca | Protótipo: `FinPeriodBar` (campo de data + período) `:702-706` + coluna de filtros `:707-747` (favoritos, status, próxima cobrança com intervalo, por plano, MRR filtrado) + busca `/` no topo da lista. Vivo `:622-748`: coluna com os mesmos blocos, tudo no servidor; sem barra de período. | **Decidir.** Adotar a barra de período compartilhada com o Financeiro |
| Lista | Protótipo `SubRow` `:242-264` e lista `:748-769`: estrela, avatar, cliente, `plano · ciclo · desde · próx.`, status + `método · valor`, vazio com CTA. Vivo `:751-839`: mesma linha, estrela persistida, `j/k`, skeleton. | Nada — paridade |
| Detalhe da assinatura | Protótipo `Drawer` `:379-540`: painel sobreposto com `role="dialog"` e foco preso (`useDialogo` `:149-167`), abas Detalhes \| ✦ IA, Pix Automático (`:269-302`), cartão recorrente (`:309-342`), histórico (`:347-377`), notas e eventos, rodapé de ações; "Cancelar assinatura" abre confirmação com motivo obrigatório (`:532-538`). Vivo: 3ª coluna fixa `:841-849` com `DetailDrawer` `:928-1146` — tudo isso menos o cartão recorrente, IA inline (`JanaPanel`), PDF no topo. | **Decidir.** Painel sobreposto com aba IA e confirmação de cancelamento com motivo (forma). O bloco de cartão recorrente é a b5 da auditoria — não entra aqui |
| Nova assinatura | Protótipo `NovaAssinatura` `:564-616`: cliente, plano opcional, valor com máscara de moeda (`mascaraValor` `:35`), ciclo, próxima cobrança, gateway, forma, descrição. Vivo `NewSubscriptionDrawer` `:1446-1772`: os mesmos campos, busca de cliente no servidor. | Nada — paridade de campos; a máscara de valor do vivo segue a regra mestre de valor, não a do protótipo |
| Editar cobrança | Protótipo `EditarCobranca` `:542-562`: valor, ciclo, forma. Vivo `EditSubscriptionDrawer` `:1777-1906`: os mesmos três; cliente, plano e data ficam fixos. | Nada — paridade |
| Carregando, erro e sem permissão | Protótipo `:1210-1226` + `:1274-1281`: estados de página "carregando", "não foi possível carregar" com tentar de novo e "você não tem acesso a esta tela". Vivo: só os skeletons dos `<Deferred>`; nenhum texto de erro ou de acesso negado na Page. | **Decidir.** Estados de erro e de acesso negado na Page |
| Dados de exemplo | Protótipo `SUBS` `:64-75` e afins (`:64-108`): carteira fictícia, contatos mascarados. Vivo: dados reais por empresa. | Nada — dado de exemplo não entra no app |

## O que não é desta tabela
- Cartão recorrente no drawer: decisão b5.
- Defeitos que restarem no protótipo vão ao Cowork (lote L7); o espelho `prototipo-ui/cowork/Wagner/` não se edita aqui.

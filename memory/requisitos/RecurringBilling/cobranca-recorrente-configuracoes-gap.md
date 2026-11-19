---
id: requisitos-recurring-billing-cobranca-recorrente-configuracoes-gap
tela: RecurringBilling/Configuracoes/Index (/recurring-billing/configuracoes)
prototipo: prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx
tela_viva: resources/js/Pages/RecurringBilling/Configuracoes/Index.tsx
gerado_em: 2026-11-19
---

# Gap — Cobrança Recorrente · Configurações (protótipo × tela viva)

> **Refeito em 2026-11-19** (lote L6 da auditoria RecurringBilling, item e5), base `origin/main` `bedeeb0096`.
> A versão de 2026-09-06 comparava com um placeholder (`:332-342`, `:372`). Desde o #8913 (07/10) a vista
> Configurações do protótipo é real (`Configuracoes()` `:999-1053`) e aquelas linhas hoje são o drawer de
> assinatura. A versão antiga está no git.
>
> **Papel do protótipo — decisão [W] 2026-11-19 (b4):** fonte de design. Forma: o protótipo vale (UI-0029);
> comportamento, dado e permissão: a produção. Tela-mãe: `cobranca-recorrente-gap.md`.
> Leitura de código; a medição de render é o PR 2 do L6.
>
> ⚠️ O lote L4 (sessão rb-l4-tela) está mexendo nesta Page (URL e header do webhook Asaas, d14). As linhas do
> vivo abaixo são de `bedeeb0096`; o map declara as âncoras `data-contract`, que sobrevivem ao deslocamento.

## Tabela de partes

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | Protótipo `:1092` + hero `:1096-1107`: "Configurações · cobrança recorrente", linha em texto corrido (sem mono), sem CTA. Vivo `Configuracoes/Index.tsx:177-204`: mesmo título e linha, mais a dica `?`. | Nada — paridade |
| Gateways de boleto/pix | Protótipo `:1002-1016` (dados `:951-953`): cartões com avatar, selo sandbox e status; botão "Adicionar gateway" sem ação. Vivo `:208-216` + `GatewaysContent` `:476-553`: os mesmos cartões; "Adicionar gateway" com selo "em breve aqui". | Nada — paridade; o vivo declara o que ainda não faz em vez de fingir |
| Régua de dunning | Protótipo `:1018-1033` (dados `:955-958`): três retentativas +3d/+7d/+15d; o texto diz que cada uma "avisa o cliente por e-mail e WhatsApp". Vivo `:219-257`: os mesmos três passos, texto vindo do controller. | Nada — paridade de forma. O aviso por e-mail/WhatsApp não existe no módulo: é a decisão b7 da auditoria, não desta tabela |
| NF-e de boleto pago | Protótipo `:1035-1046`: chave desligada + selo "Em breve" + explicação. Vivo `:260-297`: o mesmo selo "Em breve". | Nada — paridade |
| Webhooks | Protótipo `:1048-1050` + `WebhookCard` `:975-997` (dados `:960-963`): URL, método, copiar, autenticação. Vivo `:300-310` + `WebhookCard` `:555-622`. Os dois mostram a URL do Asaas sem `/api` e o header `X-Webhook-Token` (`ConfiguracoesController.php:102-103`), que o código não aceita (d14). | Nada aqui. O L4 corrige o vivo; o protótipo continua com o texto errado e o pedido de correção vai ao Cowork no L7 — comportamento é da produção, não do desenho |
| Grade das seções | Protótipo `:1001`: grade de 2 colunas. Vivo `:205-311`: 1 coluna no celular, 2 a partir de `lg`. | Nada — paridade |
| Atalhos e ajuda | Protótipo: nenhum. Vivo: tour de 4 passos e `?` (`:314-316`, `:346-445`). | Nada — vivo à frente |

---
id: requisitos-recurring-billing-cobranca-recorrente-configuracoes-gap
tela: RecurringBilling/Configuracoes/Index (/recurring-billing/configuracoes)
prototipo: prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx
tela_viva: resources/js/Pages/RecurringBilling/Configuracoes/Index.tsx
gerado_em: 2026-11-19
---

# Gap — Cobrança Recorrente · Configurações (protótipo × tela viva)

> **Refeito em 2026-11-19** (lote L6 da auditoria RecurringBilling, item e5), sobre `origin/main` `99f169a688`,
> com o protótipo do #11241 (L7). A versão de 2026-09-06 comparava com um placeholder que deixou de existir no
> #8913 (07/10); hoje a vista Configurações do protótipo é `Configuracoes()` `:1125-1198`. A versão antiga está no git.
>
> **Papel do protótipo — decisão [W] 2026-11-19 (b4):** fonte de design. Forma: o protótipo vale (UI-0029);
> comportamento, dado e permissão: a produção. Tela-mãe: `cobranca-recorrente-gap.md`.
> Leitura de código; a medição de render é o PR 2 do L6.
>
> ⚠️ O PR do lote L4 (#11203, URL e header do webhook Asaas, d14) mexe nesta Page e ainda não mergeou. As linhas
> do vivo abaixo são de `99f169a688`; o map declara as âncoras `data-contract`, que sobrevivem ao deslocamento.

## Tabela de partes

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | Protótipo `:1257` + hero `:1261-1272`: "Configurações · cobrança recorrente", linha em texto corrido, sem CTA. Vivo `Configuracoes/Index.tsx:177-204`: mesmo título e linha, mais a dica `?`. | Nada — paridade |
| Gateways de boleto/pix | Protótipo `:1128-1157` (dados `:1062-1070`): cartões com avatar, selo sandbox e **estado da conexão** (credencial inválida, certificado vencido) com ação "Corrigir credencial"/"Renovar certificado"; vazio "Nenhum gateway configurado"; C6 na lista. Vivo `:208-216` + `GatewaysContent` `:476-553`: cartões com sandbox e ativo; "Adicionar gateway" com selo "em breve aqui". | **Decidir.** Estado da conexão por gateway — precisa de o backend saber se a credencial vale |
| Régua de dunning | Protótipo `:1158-1179` (dados `:1073-1083`): uma régua por forma — boleto (+3d/+7d/+15d) e cartão recorrente (+1d/+3d/+7d). Vivo `:219-257`: uma régua só, a do boleto, com texto do controller. | **Decidir.** Régua do cartão depende da b5 (cartão recorrente). O aviso por e-mail/WhatsApp no texto do boleto é a b7 |
| Nota fiscal na fatura paga | Protótipo `:1180-1192`: título "Nota fiscal automática na fatura paga", chave desligada com `aria-disabled`, selo "Em breve". Vivo `:260-297`: título "NFe-de-boleto-pago automática", selo "Em breve". | **Decidir.** Título novo do protótipo (vale também para pagamento que não é boleto) |
| Webhooks | Protótipo `:1193-1196` + `WebhookCard` `:1100-1123` (dados `:1085-1088`): URL, método, copiar, autenticação, **último evento recebido e falhas nas últimas 24h** com alerta. Vivo `:300-310` + `WebhookCard` `:555-622`: URL, método, copiar, autenticação. Os dois mostram a URL do Asaas sem `/api` e o header `X-Webhook-Token`, que o código não aceita (d14). | **Decidir.** Último evento e falhas em 24h — precisa de registro de recebimento por empresa. A URL e o header errados: o L4 corrige o vivo; o protótipo continua errado e o pedido vai ao Cowork |
| Grade das seções | Protótipo `:1127`: grade de 2 colunas. Vivo `:205-311`: 1 coluna no celular, 2 a partir de `lg`. | Nada — paridade |
| Atalhos e ajuda | Protótipo: nenhum. Vivo: tour de 4 passos e `?` (`:314-316`, `:346-445`). | Nada — vivo à frente |

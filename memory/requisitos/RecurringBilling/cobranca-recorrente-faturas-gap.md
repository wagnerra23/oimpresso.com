---
id: requisitos-recurring-billing-cobranca-recorrente-faturas-gap
tela: RecurringBilling/Faturas/Index (/recurring-billing/faturas)
prototipo: prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx
tela_viva: resources/js/Pages/RecurringBilling/Faturas/Index.tsx
gerado_em: 2026-11-19
---

# Gap — Cobrança Recorrente · Faturas (protótipo × tela viva)

> **Refeito em 2026-11-19** (lote L6 da auditoria RecurringBilling, item e5), sobre `origin/main` `99f169a688`,
> com o protótipo do #11241 (L7). A versão de 2026-09-06 comparava com um placeholder de 3 elementos que deixou de
> existir no #8913 (07/10); hoje a vista Faturas do protótipo é `Faturas()` `:940-1056`. A versão antiga está no git.
>
> **Papel do protótipo — decisão [W] 2026-11-19 (b4):** fonte de design. Forma: o protótipo vale (UI-0029);
> comportamento, dado e permissão: a produção e o charter. Tela-mãe: `cobranca-recorrente-gap.md`.
> Leitura de código; a medição de render é o PR 2 do L6.

## Tabela de partes

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | Protótipo `:1256` + hero `:1261-1272`: "Faturas · cobrança recorrente", linha `N FATURAS · ATRASADAS N`, **sem CTA**. Vivo `Faturas/Index.tsx:445-476`: mesmo título e linha, mais o botão "Nova fatura" desabilitado com selo "em breve" (`:462-473`). | **Decidir.** Tirar o botão desabilitado — o protótipo não o desenha e criar fatura avulsa é Non-Goal do charter |
| KPIs | Protótipo `:974-983`: Pago este mês (hero, sem gráfico), Pendente, Atrasado com contagem, Total de faturas. Vivo `:479-519`: os mesmos 4; o hero leva um sparkline com série sintética derivada de `total_pago_mes` (`:489-494`). | **Decidir.** Tirar o sparkline sintético do hero (o protótipo não tem e a série não é dado real) |
| Filtros e busca | Protótipo `:984-993`: chips de 9 status **com contagem** e `aria-pressed`, gateway, período; busca `/` `:994-1003`. Vivo `:522-607`: pills de 5 status sem contagem, gateway, período, busca, contador, tudo no servidor. | **Decidir.** Contagem por status nos chips (vem do servidor) e os status novos |
| Tabela | Protótipo `:1013-1035`: Número, Cliente + CNPJ, Plano ou "avulsa", Valor, Vencimento, Status (8: paga, pendente, atrasada, paga parcial, estornada, reembolsada, chargeback, cancelada — `:934-936`), Gateway, Ações. Vivo `:624-694`: as mesmas colunas e 5 status. | **Decidir.** Paga parcial, estornada e chargeback exigem dado que o backend não grava hoje (pagamento parcial é o d6 da auditoria) |
| Cancelar e estornar | Protótipo: cancelar com motivo opcional (`:1038-1044`) e **estornar pagamento** com valor e motivo obrigatórios (`:1045-1055`). Vivo: só cancelar — `CancelDialog` `:283-356` + `handleCancel` `:419-435`. | **Decidir.** Estorno pela tela: o charter tem "Refund manual" como Non-Goal e o estorno toca valor (regra mestre) — decisão [W] |
| Vazio | Protótipo `:1004-1012`: dois textos — "Nenhuma fatura gerada ainda" (sem dado) e "Nenhuma fatura encontrada" (filtro). Vivo `:612-621`: um texto só. | **Decidir.** Separar o vazio de primeira vez do vazio de filtro |
| Paginação e rodapé | Protótipo: sem paginação. Vivo `:699-744`: página X de Y + nota de rodapé. | Nada — vivo à frente |
| Atalhos e ajuda | Protótipo: só `/` (`:951-955`). Vivo: tour, `?`, Esc, estilos de impressão (`:379-405`, `:758-759`). | Nada — vivo à frente |

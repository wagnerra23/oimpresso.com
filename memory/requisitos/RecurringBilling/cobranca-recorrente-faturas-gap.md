---
id: requisitos-recurring-billing-cobranca-recorrente-faturas-gap
tela: RecurringBilling/Faturas/Index (/recurring-billing/faturas)
prototipo: prototipo-ui/cowork/Wagner/cobranca-recorrente-page.jsx
tela_viva: resources/js/Pages/RecurringBilling/Faturas/Index.tsx
gerado_em: 2026-11-19
---

# Gap — Cobrança Recorrente · Faturas (protótipo × tela viva)

> **Refeito em 2026-11-19** (lote L6 da auditoria RecurringBilling, item e5), base `origin/main` `bedeeb0096`.
> A versão de 2026-09-06 comparava com um placeholder de 3 elementos (`:332-342`, `:371`). Desde o #8913
> (07/10) a vista Faturas do protótipo é real (`Faturas()` `:849-946`) e aquelas linhas hoje são o drawer de
> assinatura. A versão antiga está no git.
>
> **Papel do protótipo — decisão [W] 2026-11-19 (b4):** fonte de design. Forma: o protótipo vale (UI-0029);
> comportamento, dado e permissão: a produção e o charter. Tela-mãe: `cobranca-recorrente-gap.md`.
> Leitura de código; a medição de render é o PR 2 do L6.

## Tabela de partes

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | Protótipo `:1091` + hero `:1096-1107`: "Faturas · cobrança recorrente", linha `N FATURAS · ATRASADAS N`, **sem CTA**. Vivo `Faturas/Index.tsx:445-476`: mesmo título e linha, mais o botão "Nova fatura" desabilitado com selo "em breve" (`:462-473`). | **Decidir.** Tirar o botão desabilitado — o protótipo não o desenha e criar fatura avulsa é Non-Goal do charter |
| KPIs | Protótipo `:880-889`: Pago este mês (hero, sem gráfico), Pendente, Atrasado com contagem, Total de faturas. Vivo `:479-519`: os mesmos 4; o hero leva um sparkline com série sintética derivada de `total_pago_mes` (`:489-494`). | **Decidir.** Tirar o sparkline sintético do hero (o protótipo não tem e a série não é dado real) |
| Filtros e busca | Protótipo `:890-899`: chips de status **com contagem**, gateway, período; busca `/` `:901-907`. Vivo `:522-607`: pills de status sem contagem, gateway, período, busca, contador `N / total`, tudo no servidor. | **Decidir.** Contagem por status nos chips — vem do servidor junto dos KPIs |
| Tabela | Protótipo `:913-936`: Número, Cliente + CNPJ, Plano ou "avulsa", Valor, Vencimento com relativo, Status (4), Gateway, ação. Vivo `:624-694`: as mesmas colunas e 5 status (inclui reembolsada). | Nada — paridade; o vivo tem um status a mais |
| Cancelar fatura | Protótipo `Confirm` `:938-946`: resumo da fatura, aviso de cancelamento no gateway, motivo opcional. Vivo `CancelDialog` `:283-356` + `handleCancel` `:419-435`. | Nada — paridade |
| Vazio | Protótipo `:909-912`: "Nenhuma fatura encontrada. Ajuste os filtros ou a busca." Vivo `:612-621`. | Nada — paridade |
| Paginação e rodapé | Protótipo: sem paginação. Vivo `:699-744`: página X de Y + nota de rodapé. | Nada — vivo à frente |
| Atalhos e ajuda | Protótipo: só `/`. Vivo: tour, `?`, Esc, estilos de impressão (`:379-405`, `:758-759`). | Nada — vivo à frente |

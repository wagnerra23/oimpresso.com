---
id: requisitos-estoque-stock-transfer-index-gap
tela: StockTransfer/Index (/stock-transfers)
prototipo: prototipo-ui/cowork/estoque-page.jsx + estoque-forms.jsx
tela_viva: resources/js/Pages/StockTransfer/Index.tsx
gerado_em: 2026-09-06
---

# GAP-SPEC — StockTransfer/Index

> **Âncora resolvida por `bundle_source`** (`ancora.mjs StockTransfer/Index` → `âncora ✓ [-page.jsx (bundle · bundle_source)] estoque-page.jsx`); o charter declara `related_prototype: n/a (herda PT-01 Lista)` — coexistência prevista.
>
> **Porte reverso** do vivo lido em 2026-08-22 (`estoque-page.jsx:1-3`) → expectativa-base é paridade. Frescor: **STALE** — `TabBar` no espelho x `window.CliTabs` no vivo (nota completa e errata no [gap do StockAdjustment/Index](stock-adjustment-index-gap.md)).
>
> **Região do protótipo:** aba **Transferências** — `AbaTransferencias` (`estoque-page.jsx:318-416`) + `COLS_TR` (`:306-316`) + `DrawerTransferencia` e `FolhaTransferencia` (`estoque-forms.jsx:567-...` e `:403-484`).

| Parte | Estado no vivo | Ação |
|---|---|---|
| Header / PageHeader | `PageHeader` com título "Transferências de estoque", contador e CTA "Nova transferência" gated por `permissions.create` (`Index.tsx:149-157`) | Nada — paridade. |
| Filtros | Select de filial, **select de status alimentado pelo servidor** (`statuses`), datas De/Até e busca client-side (`Index.tsx:167-199`) | **Decidir.** O protótipo tem `PeriodBar` com presets Dia/Semana/Mês (`estoque-page.jsx:144-147`) e chips do filtro ativo removíveis (`:176-180`); o vivo usa datas cruas e não exibe chip. O filtro de status em si já existe no vivo e é **melhor** — vem do servidor, não de enum hardcoded. Construir ou rejeitar por escrito apenas o `PeriodBar` + chips. |
| Abas por status | Ausente como aba; existe como select (`Index.tsx:173-177`) | **Decidir.** O protótipo usa o `TabBar` do DS com uma aba por status e contador em cada (`estoque-page.jsx:371-375`). É a mesma capacidade do select do vivo, com outra forma e o ganho do contador. Construir ou rejeitar por escrito. |
| Coluna "Mexeu no saldo?" | **Presente** (`Index.tsx:215`, `:236`), com o predicado `mexeuNoSaldo` derivado de leitura medida nos 3 sites do `StockTransferController` — não do nome do status (`:118-134`) | Nada — vivo à frente. O protótipo tem a mesma coluna (`estoque-page.jsx:310`, célula em `:348`), mas decide por `D.STATUS_TRF[t.status].move` sobre mock. O vivo cita R-XFER-003 e UC-INV-02 e registra a medição no código. |
| Tabela / colunas | 8 colunas: Data, Ref. Nº, Origem → Destino, Status, Mexeu no saldo?, Frete, Total, Ações (`Index.tsx:211-218`); valores atrás de `view_purchase_price` (`:241`, `:244`) | **Decidir.** `COLS_TR` do protótipo (`estoque-page.jsx:306-316`) tem 9 colunas — falta no vivo **Itens** (contagem) e **Observação** (esta já nasce desligada por padrão, `off: true` em `:315`). Ambas leitura; `Itens` exige o backend expor a contagem. Construir ou rejeitar por escrito. |
| Sub-linha com os SKUs da rota | Ausente | **Decidir.** O protótipo põe os SKUs da carga como sub-linha da coluna rota (`estoque-page.jsx:346`), o que deixa ver o conteúdo sem abrir nada. Exige o backend enviar os itens na listagem. Construir ou rejeitar por escrito. |
| Ordenação, densidade e seletor de colunas | Ausentes | **Decidir.** O protótipo tem sort por coluna (`estoque-page.jsx:392-394`), botão de densidade (`:157`) e seletor de colunas em `localStorage` (`:161-170`). Preferência de exibição, não toca valor. Construir ou rejeitar por escrito. |
| Exportação | Ausente | **Decidir.** CSV, Excel e Imprimir no protótipo (`estoque-page.jsx:158-160`; `exportar` em `:359-367`). ⚠️ **Toca VALOR** — exporta `shipping_charges` e `final_total`; adotar exige provar por dois caminhos que os totais exportados batem com a tela + antes→depois de amostra (REGRA MESTRE). Construir ou rejeitar por escrito. |
| Rodapé de somatórios | Ausente | **Decidir.** O protótipo soma o conjunto filtrado em três números — Transferências, Total transferido e **Ainda sem mover saldo** (`estoque-page.jsx:397-404`, cálculo em `:356-357`). ⚠️ **Toca VALOR**: agregação monetária nova em tela; exige dupla confirmação + antes→depois antes de exibir, e o mesmo gate `view_purchase_price` que o vivo já aplica por linha. Construir ou rejeitar por escrito. |
| Paginação | Ausente — renderiza `rows` inteiro | **Decidir.** O protótipo pagina de 10 em 10 (`estoque-page.jsx:404`). Construir ou rejeitar por escrito. |
| Seleção múltipla / ações em massa | Ausente | **Decidir.** O `BulkBar` do protótipo (`estoque-page.jsx:406-413`) traz **"Concluir selecionadas"**, "Imprimir folhas", "Exportar seleção" e "Excluir e reverter". ⚠️ **Toca ESTOQUE e é o item mais pesado desta tela**: concluir em lote dispara, para cada transferência, a baixa na origem e a entrada no destino (`updateStatus` do controller, citado em `Index.tsx:122-126`). Exige dupla confirmação + antes→depois do saldo de cada registro afetado, e decisão [W] explícita sobre permitir o ato em lote. Construir ou rejeitar por escrito. |
| Ações por linha | Ver, **Imprimir** (abre `/stock-transfers/{id}/print` em nova aba) e Excluir gated por `permissions.delete` (`Index.tsx:246-259`) | Nada — vivo à frente. O protótipo oferece as mesmas ações via drawer; o vivo já tem a impressão direto na linha. |
| Diálogo de exclusão | `AlertDialog` do DS cujo texto **muda conforme o saldo já foi movido ou não** (`Index.tsx:293-300`) | Nada — vivo à frente. O `ModalExcluir` do protótipo (`estoque-forms.jsx:358-370`) tem texto único. |
| Drawer de detalhe e folha de transferência | Ausentes — "Ver" navega para outra rota; a impressão é server-side | **Decidir.** O protótipo tem `DrawerTransferencia` (`estoque-forms.jsx:567+`) com endereço, cidade e telefone dos dois locais, e `FolhaTransferencia` (`:403-484`) — romaneio com código de barras, campos de assinatura de conferente e recebedor. O vivo já imprime pelo servidor; a decisão é se a folha do protótipo substitui esse layout. Construir ou rejeitar por escrito. |
| Estado vazio | Dois estados: com filtro ativo e sem nenhum registro, este com CTA "+ Registrar primeira transferência" (`Index.tsx:262-285`) | Nada — vivo à frente. |

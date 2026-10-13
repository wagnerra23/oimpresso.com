---
sessao: "_saida-07"
thread: "07 · Requisições de compra em React"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "#9213 (F1/F2) + PR desta thread (F3, claude/compras-07-f3-react)"
base_lida: wagnerra23/oimpresso.com@main e6f1e8a36b
---
# _saida-07

## 1 · Feito
- **F1/F2 (#9213):** RUNBOOK `memory/requisitos/Compras/_telas/RUNBOOK-purchase-requisition-index.md`, mapa
  `purchase-requisition-parity.md` e `RequisicoesCompraBaselineTest` (7 casos sobre a DataTable da Blade, 98 × 99).
- **F3 (este PR):** `GET /purchase-requisition` responde Inertia `PurchaseRequisition/Index` quando a flag
  `useV2ComprasRequisicoes` está ligada para o negócio; desligada (default) ou com `?legacy=1`, segue a Blade.
  - O SQL da lista saiu do `index()` para `PurchaseRequisitionController::consultaRequisicoes()`, usado pela DataTable e
    pela tela. Bloco movido idêntico ao original, trocado `request()` por `$request` (54 linhas, verificado por script).
  - O ramo DataTable passou a exigir `! request()->inertia()` (o Inertia v3 manda `X-Requested-With`).
  - Já nasce com o que o PHPStan cobrou na thread 06: retorno do `index()` como `JsonResponse|View|Inertia\Response` e
    `$purchaseRequisitionStatuses` declarada.
  - Trio: `Index.tsx` + `Index.charter.md` + `Index.casos.md` (UC-REQC-01..05) + `RequisicoesCompraContratoTest`.
  - Forma: a do alvo da A2 (`compras--requisicoes--index`): cabeçalho, KPIs, nota, busca + filtros, tabela, rodapé.

## 2 · Não feito, e por quê
- **Ver e excluir:** seguem no modal da Blade (`?legacy=1`). O `show()` devolve fragmento de modal. Escrita em PR próprio.
- **KPI "Itens requisitados" e coluna "Pedido gerado"** do protótipo: a lista da Blade não tem. Decisão [W].
- **Filtros de período e de "necessário até"** ficam no SQL (a DataTable os usa); a tela React expõe local e status.
- **F4 (smoke biz=1 com a flag) e F5 (cutover):** decisão [W].

## 3 · Pedido literal
> "continua com a 07 quando o #9203 entrar" — [W] 2026-10-13.

## 4 · Descobertas
- `@format_datetime` carrega o shift de fuso preservado de propósito (ADR 0066): o teste não fixa a hora à mão, deriva da
  mesma expressão e fixa o dia.
- O JOIN com `users` da lista é INNER: requisição sem autor válido não aparece. Mantido igual nos dois caminhos.
- Lição da thread 06 aplicada no contrato: `withHeaders` persiste entre requisições do mesmo teste, então o segundo
  caminho chama `flushHeaders()` antes de ler a DataTable.

## 5 · Prefixo tocado
`app/Http/Controllers/PurchaseRequisitionController.php` · `resources/js/Pages/PurchaseRequisition/{Index.tsx,Index.charter.md,Index.casos.md}` ·
`tests/Feature/Purchase/RequisicoesCompraContratoTest.php` · `.github/workflows/purchase-pest.yml` · este recibo (arquivo novo).

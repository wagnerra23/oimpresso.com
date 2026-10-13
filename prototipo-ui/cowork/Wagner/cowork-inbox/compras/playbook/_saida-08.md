---
sessao: "_saida-08"
thread: "08 · Devoluções de compra em React"
dono: "[CL]"
data: 2026-10-13
tipo: recibo
entregue_em: "PR F1/F2 + PR F3 desta thread (claude/compras-08-f1-baseline · claude/compras-08-f3-react)"
base_lida: wagnerra23/oimpresso.com@main 14c14e04e2
---
# _saida-08

## 1 · Feito
- **F1/F2:** RUNBOOK `memory/requisitos/Compras/_telas/RUNBOOK-purchase-return-index.md`, mapa
  `purchase-return-parity.md` e `DevolucoesCompraBaselineTest` (7 casos sobre a DataTable da Blade, 98 × 99). Os valores
  (total, devido com e sem pagamento, e as duas somas do rodapé) provados por dois caminhos: à mão e o `data-orig-value`
  que a Blade entrega.
- **F3:** `GET /purchase-return` responde Inertia `PurchaseReturn/Index` quando a flag `useV2ComprasDevolucoes` está
  ligada para o negócio; desligada (default) ou com `?legacy=1`, segue a Blade.
  - O SQL da lista saiu do `index()` para `PurchaseReturnController::consultaDevolucoes()`, usado pela DataTable e pela
    tela. Bloco movido idêntico ao original, trocado `request()` por `$request` (56 linhas, verificado por script).
  - O devido é a mesma conta da coluna `payment_due` (`final_total − pago`, pago nulo vale zero), e total e devido chegam
    formatados pelo `num_f`. A tela não calcula, não formata e não soma valor.
  - Trio: `Index.tsx` + `Index.charter.md` + `Index.casos.md` (UC-DEVC-01..05) + `DevolucoesCompraContratoTest`.
  - Forma: a do alvo da A2 (`compras--devolucoes--index`): cabeçalho, aviso, contagem por status de pagamento, busca +
    filtro de local, tabela.

## 2 · Não feito, e por quê
- **Total devolvido e total devido** (rodapé da Blade e KPIs do protótipo): é soma de valor. Pela regra mestre, só com
  dupla prova e antes→depois apresentado ao [W]. A tela mostra a **contagem** por status de pagamento, que é contagem.
- **Editar, registrar pagamento, ver pagamentos e excluir:** seguem na Blade (`?legacy=1`). PR próprio, regra mestre.
- **Filtro de período:** fica no SQL (a DataTable o usa); a tela expõe o local, que é o filtro visível da Blade. A Blade
  não tem campo de fornecedor na interface, embora o SQL aceite `supplier_id`.
- **F4 (smoke biz=1 com a flag) e F5 (cutover):** decisão [W].

## 3 · Pedido literal
> "continua com a 08 quando o #9213 entrar" — [W] 2026-10-13.

## 4 · Descobertas
- O aviso do protótipo diz que a devolução "nasce sempre de uma compra existente, nunca avulsa". **Não é verdade no
  sistema:** a devolução combinada (`CombinedPurchaseReturnController`) não tem compra de origem (`return_parent_id`
  vazio) e a própria lista trata esse caso no editar. A tela nova não repete a frase; corrigir no protótipo, **no Cowork**.
- O rodapé da Blade soma total e devido em JavaScript, só sobre as linhas da página visível.

## 5 · Prefixo tocado
`app/Http/Controllers/PurchaseReturnController.php` · `resources/js/Pages/PurchaseReturn/{Index.tsx,Index.charter.md,Index.casos.md}` ·
`tests/Feature/Purchase/Devolucoes{Baseline,Contrato}Test.php` · `memory/requisitos/Compras/_telas/{RUNBOOK-purchase-return-index,purchase-return-parity}.md` ·
`.github/workflows/purchase-pest.yml` · este recibo (arquivo novo).

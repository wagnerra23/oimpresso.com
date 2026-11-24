---
slug: sells-runbook-sell-return-add
title: "Sells — Runbook do registro de devolução /sell-return/add/{venda} (migração MWART, PR 2 de 2)"
type: runbook
module: Sells
tela: SellReturn/Add
status: ativo
owner: W
last_validated: "2026-10-05"
date: 2026-10-05
preconditions:
  - "Permissão access_sell_return OU access_own_sell_return"
  - "Escopo business_id da sessão na venda de origem (ADR 0093)"
  - "REGRA MESTRE de valor/estoque (memory/proibicoes.md): store() e cálculo intactos"
steps:
  - "Ramo X-Inertia em SellReturnController@add com 404 para venda de outro business"
  - "Page resources/js/Pages/SellReturn/Add.tsx postando o payload do form Blade no store() existente"
  - "Contrato Pest em tests/Feature/Sells/SellReturnAddContratoTest.php na lane sells-pest"
---

# RUNBOOK — Registro de devolução (`/sell-return/add/{venda}`)

> **Tipo:** runbook MWART (Blade → Inertia/React), **registro** da devolução.
> **Thread:** `prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/playbook/01-telas-legadas.md` §03 — PR 2 de 2
> (o PR 1 é a lista, `RUNBOOK-sell-return-index.md`).
> **Refs:** ADR 0104 (MWART) · ADR 0093 (multi-tenant) · `memory/requisitos/Sells/CASOS-USO-DEVOLUCAO.md`.
> **Protótipo (desde 2026-11-23):** `prototipo-ui/cowork/Wagner/devolucao-v3.html` — matriz e ondas no §6.

## 1. Objetivo

Registrar a devolução em React quando a visita é Inertia, **sem mudar como ela é gravada**. A
devolução move **valor** (total devolvido, saldo a pagar ao cliente) e **estoque** (volta ao
local da venda), então o caminho de escrita é o mesmo do form Blade: `POST /sell-return` →
`SellReturnController@store` → `TransactionUtil::addSellReturn`. Nenhuma linha desses dois muda.

## 2. Pré-condições

- [x] Permissão `access_sell_return` ou `access_own_sell_return` (mesma checagem do `add()`).
- [x] Venda de origem buscada com `business_id` da sessão; de outra empresa → 404 antes de
      qualquer prop.
- [x] Carga completa de página continua na Blade (`sell_return.add`), byte a byte.

## 3. Passo a passo

### 3.1 Controller — ramo Inertia no `add()`

Depois da checagem de permissão e de assinatura. Carga inicial só confere que a venda existe
no business (`exists()` barato) e devolve 404 se não; a venda com linhas vem deferida
(`Inertia::defer`, grupo `venda`).

A prop `venda` traz os campos do form **como texto no formato da empresa**, com as mesmas
expressões das diretivas da Blade (`@num_format`, `@format_quantity`, `@format_datetime`,
`num_f(..., true)`). A Page devolve esses textos ao `store()` sem reformatar — o servidor recebe
a mesma coisa pelos dois caminhos.

### 3.2 Front — `resources/js/Pages/SellReturn/Add.tsx`

PT-02 (formulário de detalhe): cabeçalho da venda de origem, tabela de linhas com quantidade
a devolver, desconto, totais e "Salvar devolução". O total mostrado antes de salvar sai de
`_components/devolucaoCalculo.ts`, espelho de `Util::num_uf` + `ProductUtil::calculateInvoiceTotal`
— o mesmo número que o servidor vai gravar. O envio é `application/x-www-form-urlencoded` com as
chaves do form Blade (`products[i][quantity]`, `products[i][unit_price_inc_tax]`,
`products[i][sell_line_id]`, `discount_type`, `discount_amount`, `tax_id`, `tax_amount`,
`transaction_id`, `invoice_no`, `transaction_date`).

### 3.3 Teste — `tests/Feature/Sells/SellReturnAddContratoTest.php`

Lane `sells-pest.yml` (allowlist no workflow). Tenant 98 × 99. A prova de valor/estoque posta o
payload da Blade numa venda e o da Page numa venda gêmea, e compara total, desconto, imposto,
`quantity_returned`, saldo de estoque e situação do pagamento — mais a conta à mão no cabeçalho.

## 4. Fora deste PR

- **Motivo da devolução** (charter R3): o `store()` não grava motivo; exigiria mudar o store → fora.
- **Aviso de item sob medida** (charter R4): não há dado no produto que diga "sob medida".
- Impressão do comprovante após salvar (a Blade chama `pos_print`); excluir devolução e
  adicionar pagamento seguem na Blade.

## 5. Rollback

Remover o `if (request()->header('X-Inertia'))` do `add()`. A Blade nunca deixou de responder
à carga completa, e o `store()` não mudou.

## 6. Protótipo Devolução V3 — o que já existe e o que falta (medido em 2026-11-23)

O protótipo `prototipo-ui/cowork/Wagner/devolucao-v3.html` veio de um Artefato (ADR 0438) e é
a âncora desta tela desde 2026-11-23 (`related_prototype` do charter). Ele desenha muito mais
que o registro de hoje: a devolução de venda e a de compra, a NF-e de devolução por regime, o
financeiro, o retorno da consignação e as resoluções em que a peça não volta. A matriz abaixo
diz, para cada parte, o que o repo já faz e onde. Medida em `origin/main` de 2026-11-23.

### 6.1 Matriz

| # | O protótipo mostra | Hoje no repo | Onde |
|---|---|---|---|
| 1 | Escolher a venda finalizada e a quantidade por item, com saldo e "devolver tudo" | ✅ existe | `SellReturn/Add.tsx` (R1 teto por linha · P-038 #10186) |
| 2 | Prévia do quanto abate do título e do quanto vira crédito | ✅ existe | `PreviaFinanceiraDevolucao` (#10254) · `Financeiro/DevolucaoVendaTituloService` |
| 3 | Caixa do período fechado: o valor vira parcela de devolução nova, com forma (dinheiro, PIX, compensar no acerto, transferência) | ❌ não existe | — |
| 4 | Motivo por item, com o depósito de destino (avarias, perdas, depósito principal) | ❌ não existe | o `store()` não grava motivo (§4) |
| 5 | Resolução "trocar com crédito" com validade de 6 meses | 🟡 só no Vestuario | `Modules/Vestuario/Services/DevolucaoService` (`vestuario_devolucoes`); o núcleo guarda o crédito no `sell_return` em aberto, sem validade |
| 6 | Resoluções em que a peça fica com o cliente: refação (OS de retrabalho) e abatimento (Nota de Crédito) | ❌ não existe | o núcleo só tem o título `cn_heading` do layout de fatura para imprimir a própria devolução (migration `2018_05_21_131607`); não há documento de abatimento sem devolver a peça |
| 7 | NF-e de devolução da venda: `finNFe=4`, `refNFe`, CFOP espelho, proporcional, base e alíquotas da origem | 🟡 backend pronto, fora desta tela | `NfeBrasil/Services/DevolucaoService::emitirDevolucao`, chamado só por `Fiscal/AcoesController::devolucao` (thread Fiscal 20) |
| 8 | Rateio de desconto, frete e outras despesas (UC-07) | 🟡 parcial | `DevolucaoService` rateia `vDesc`, `vFrete`, `vOutro`; `vSeg` não aparece |
| 9 | ICMS-ST destacado ou retido (UC-09, UC-10, UC-18) · FCP (UC-11) · IPI devolvido e `impostoDevol` (UC-03, UC-05) · DIFAL (UC-12, UC-16) · desonerado/ZFM (UC-19) · exterior (UC-14) | ❌ não existe | `grep` dessas tags no `DevolucaoService`: 0 ocorrências |
| 10 | Devolução de compra: NF-e de saída a partir da nota de entrada, regime do fornecedor, expedição e acerto da conta a pagar | ❌ sem NF-e | `PurchaseReturnController` / `CombinedPurchaseReturnController` em Blade; `CFOP_ESPELHO` só cobre venda |
| 11 | Venda sem nota (Rota Livre): retorno ao estoque e financeiro, sem tributos | ✅ o caminho do núcleo | `sell_return` sem NF-e |
| 12 | Acerto e retorno de consignação (vendidas × voltaram) | ❌ não existe | só o bullet `[BACKLOG] Consignação para revendedor` em `Sells/Create.casos.md`; no código, `consigna` aparece apenas em `Marketplaces/.../CfopDoPedido.php` |
| 13 | Cancelar a nota em vez de devolver quando a venda é do dia e não saiu | 🟡 backend existe | `NfeService::cancelar`; nada liga isso ao registro |
| 14 | Lista com áreas Vendas e Compras, estados (rascunho → concluída) e KPIs | 🟡 telas separadas, sem estados | `SellReturn/Index` (âncora `vendas-extras.jsx`) · `PurchaseReturn/Index` (âncora `compras-extras.jsx`) |
| 15 | Importar o XML da nota de origem e mostrar a prévia do XML de devolução | ❌ não existe | — |

Os casos UC-01 a UC-19 vêm de um documento que o protótipo liga por link para outro Artefato.
Em 2026-11-23 ele não abriu nesta conta e **não está no git**. Antes da onda 4 ele precisa entrar
no repo, ou os testes fiscais não têm contrato de onde derivar.

### 6.2 Ondas

Cada onda é um PR, atrás de flag que nasce `false`. As que tocam valor ou estoque seguem a REGRA
MESTRE de `memory/proibicoes.md` (dois caminhos de prova e tabela antes→depois aprovada pelo [W]).
Os exemplos do protótipo **não viram seed** sem revisão do contador.

| Onda | Entrega | Linhas da matriz | Valor/estoque |
|---|---|---|---|
| 1 | O registro emite a NF-e de devolução quando a venda tem nota autorizada, usando o `DevolucaoService` que já existe | 7, 13 | sim (imposto) |
| 2 | Motivo por item e depósito de destino gravados pelo `store()` | 4 | sim (estoque) |
| 3 | Caixa fechado vira parcela de devolução nova, com a forma de reembolso | 3 | sim (valor) |
| 4 | Tags fiscais que faltam no `DevolucaoService`, uma por PR, com o XML esperado no teste: `vSeg`, ST, FCP, IPI devolvido, DIFAL, desonerado, exterior | 8, 9, 15 | sim (imposto) |
| 5 | Devolução de compra com NF-e de saída (CFOP espelho de compra, regime do fornecedor) e a tela de compra migrada da Blade | 10 | sim |
| 6 | Resoluções sem devolver a peça (refação e abatimento com Nota de Crédito) e crédito com validade | 5, 6 | sim |
| 7 | Estados da devolução e a lista unificada | 14 | não |

**Onda 1 — estado em 2026-11-23.** Rota em [#11374](https://github.com/wagnerra23/oimpresso.com/pull/11374)
e botão em [#11378](https://github.com/wagnerra23/oimpresso.com/pull/11378), atrás de
`NFEBRASIL_DEVOLUCAO_PELA_VENDA` + `NFEBRASIL_DEVOLUCAO_PELA_VENDA_BIZ` (por empresa). Smoke no staging
do CT 100 **sem SEFAZ**, tenant 98, em transação desfeita: 13 de 13 verificações, do XML de origem ao XML
da devolução ([comentário no #11374](https://github.com/wagnerra23/oimpresso.com/pull/11374#issuecomment-7114929421)).
**Não testado, por decisão [W] de 2026-11-23** ("basta o smoke"): a transmissão à SEFAZ de homologação,
porque o staging não tem certificado A1. Em 2026-11-23 a biz=1 emitia na SEFAZ de **produção** e não tinha
NF-e 55 autorizada; o primeiro uso real com a flag ligada é, portanto, também a primeira transmissão.

Ficam para decisão do [W] antes de começar a onda correspondente:
- **Onda 6:** se a Nota de Crédito vira documento novo do ERP e se a validade de 6 meses do
  crédito sai do Vestuario para o núcleo.
- **Onda 7:** re-ancorar `SellReturn/Index` e `PurchaseReturn/Index` neste protótipo é decisão
  par-a-par (§5 2026-08-28); hoje elas seguem `vendas-extras.jsx` e `compras-extras.jsx`.
- **Linha 12:** a consignação está só como backlog em `Sells/Create.casos.md`, com os CFOP
  pendentes do contador. A remessa precisa existir antes do acerto e do retorno que o protótipo
  desenha.

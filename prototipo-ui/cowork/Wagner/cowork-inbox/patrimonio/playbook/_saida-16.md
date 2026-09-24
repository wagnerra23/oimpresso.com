---
sessao: "16"
titulo: Saída da thread 16 — a fusão para no dado, antes do código
dono: "[CL]"
medido_em: 2026-09-24
base_medida: 723d2b1e6991 (origin/main fresco)
arquivos_de_producao_tocados: 0
invalida: "a premissa de §4-ter DADO ('o mesmo asset_transactions/alocação que a tela já consome') — a lista de revogações tem outro grão de linha"
---

# 16 · Saída — a thread manda parar, e parou

## Veredito
**Não executada, pela regra da própria thread.** O §4-ter diz: *"Se o Blade de revogação usar campo que a Page não recebe, declare no `_saida` e pare no que falta — não invente fonte."* O Blade usa **cinco** campos que a Page não recebe e **uma** ação de escrita que a Page não tem. A razão não é de layout: as duas telas listam **linhas de tipos diferentes**.

O pré-requisito de quebra (`se o controller já faz Inertia::render, não execute`) **não** disparou: `RevokeAllocatedAssetController.php:108` ainda devolve `view('assetmanagement::asset_revocation.index')`, e esse é o único chamador (`git grep asset_revocation.index` em código = 1). A lacuna 2 de 09/09 continua **real e aberta**.

## O defeito de premissa — grão de linha
| tela | fonte | filtro | 1 linha = |
|---|---|---|---|
| `Patrimonio/Alocacoes` (`AssetAllocationController::baseAllocationsQuery`) | `asset_transactions` | `transaction_type = 'allocate'`, `groupBy(id)` | **uma alocação**, com `SUM` das devoluções filhas |
| `asset_revocation.index` (`RevokeAllocatedAssetController::index`, ramo ajax) | `asset_transactions` | `transaction_type = 'revoke'`, `join PT` no pai | **uma devolução** |

A relação é **1 alocação : N devoluções**. O modelo permite N, medido no código (não em dado de produção): `store()` grava `revoke` com qualquer `quantity` contra o mesmo `parent_id`, e tanto `revoked_quantity` (`SUM(COALESCE(PT.quantity,0))`) quanto `_getRevokedQtyOfAllocatedAsset` somam os filhos. **O protótipo modela 1:1** — `revoke` é um objeto só por alocação (`patrimonio-page.jsx:95`, `:100`, `:435` `codigo: { primary: a.id, sub: a.revoke.id }`). A premissa dele não vale no nosso modelo: com devolução parcial, uma alocação tem vários `REV-`.

## Mapa de campos — o que o Blade mostra × o que a Page recebe
| coluna do Blade (`index.blade.php`) | campo | na Page (`buildAlocacoesPayload`)? |
|---|---|---|
| Código da revogação | `asset_transactions.ref_no` (revoke) | ❌ — `ref_no` da Page é o da **alocação** |
| Revogado para | `receiver` do pai | 🟡 equivale a `recebido_por` (mesmo usuário, via pai) |
| Código da alocação | `PT.ref_no` | 🟡 é o `ref_no` da linha da Page |
| Bem · Série/modelo · Categoria | `assets.*`, `CAT.name` | ✅ `bem`, `modelo`, `categoria` |
| Quantidade | quantidade **da devolução** | ❌ — a Page tem a da alocação e o **agregado** `devolvido` |
| Revogado em | `transaction_datetime` (revoke) | ❌ |
| Revogado por | `created_by` da revoke | ❌ — `alocado_por` é quem **entregou** |
| Motivo | `reason` da revoke | ❌ — `motivo` da Page é o da **alocação** |
| Ação **Excluir** | `DELETE /asset/revocation/{id}` | ❌ — e é **escrita que devolve saldo** |

Faltam 5 campos no grão da devolução (código, quantidade, data, autor, motivo) e a ação de excluir. Renderizar `/asset/revocation` como o recorte `Devolvidas` da Page **não é fusão, é perda**: some o histórico por evento, e some o único caminho de UI que desfaz uma devolução errada.

## Por que não "só montar o payload das devoluções" no controller
O dado existe — a query do ramo ajax já está escopada por `business_id` (Tier 0 ok). O que não cabe no mandato da thread:
1. **Page com dois tipos de linha.** A visão `revogacao` exigiria um segundo conjunto de colunas e um segundo contrato em `Alocacoes.tsx` — é uma tela nova dentro da existente, que o §4-ter proíbe (*"não uma Page nova, não um componente duplicado"*) e que o §D.2 não cobre (a guarda fala de *prop opcional*, não de segunda tabela).
2. **A ação Excluir.** Levá-la para React é escrita de quantidade — REGRA MESTRE (prova por dois caminhos + antes→depois ao [W]) e território da decisão **D-FORMS**. Deixá-la para trás é regressão de capacidade viva.
3. **Colisão de prefixo.** O [#7904](https://github.com/wagnerra23/oimpresso.com/pull/7904), aberto, edita 2 dos 3 arquivos do prefixo (`RevokeAllocatedAssetController.php` e `SmokeRoutesTest.php`, este com casos apendados no fim do arquivo). Esta sessão tinha instrução de não tocar arquivos de PR aberto.

## Achado lateral (não corrigido — fora do prefixo)
O fluxo de **criar** devolução não tem entrada de UI hoje. `RevokeAllocatedAssetController::create` só responde sob `ajax()` e era aberto pelo botão "Revogar" da tabela Blade de alocações (`asset_allocation/index.blade.php:115`). Esse arquivo segue no disco, mas nenhum código o renderiza (`git grep asset_allocation.index` = 0): o `index()` virou `Alocacoes.tsx`, que declara "nenhuma ação por linha" (charter §Features). O Blade de devoluções lista e exclui, mas não tem botão de criar. Quem a Page manda para `/asset/revocation` ("Devoluções", header) chega a uma lista sem caminho de escrita. É dívida de D-FORMS, registrada aqui para não virar descoberta futura.

## O que destrava a 16 (decisão, não conserto)
Uma de duas, e ela é de produto:
- **(a)** a aba Alocações passa a ter **visão por evento** (linhas de devolução, 1:N), com as 5 colunas acima — reescrita do §4-ter, com colunas e contrato próprios;
- **(b)** a aba fica no grão da alocação e o histórico `REV-` vai para o **drawer do bem** (é onde o protótipo mostra o `REV-` junto de cada alocação, `:723-729`), e `/asset/revocation` redireciona — mas isso **funde a rota**, que o §B reservou ao [W].

Nas duas, a ação Excluir precisa da resposta da D-FORMS antes. Nenhuma das duas cabe em "prop opcional + 3 arquivos".

## Checklist (§D da thread)
1. `index()` devolve `Inertia::render('Patrimonio/Alocacoes', …)` — ❌ **não executado**: parada pelo §4-ter DADO
2. Prop opcional sem diff na visão default — n/a (nada editado; `Alocacoes.tsx` intacto)
3. `asset_revocation.index` com 0 chamadores — ❌ segue com **1** (`:108`)
4. Permissão — medida por leitura: `index()` só tem o gate de assinatura (`superadmin` ou `assetmanagement_module`), o mesmo do `AssetAllocationController::index`; não foi exercitada em teste porque nada mudou
5. `SmokeRoutesTest` — não tocado (colisão com #7904)
6. A11y `th scope` — n/a (nenhuma tabela nova)
7. Placar no corpo do PR ✅

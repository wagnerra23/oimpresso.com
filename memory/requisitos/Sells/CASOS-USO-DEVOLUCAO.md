---
id: requisitos-sells-casos-uso-devolucao
doc: casos-uso
page: /sell-return
charter: _pendente_ (tela 100% Blade — sem twin React)
module: Sells
status: ativo
updated: "2026-08-18"
---

# Casos de uso — Devolução de venda (`sell_return`)

> **Contrato da devolução**, hoje 100% Blade: zero twin React, zero charter. **Mas não é zero teste** —
> a *reintegração de estoque* já é coberta pelo módulo Estoque (ver quadro abaixo). O que falta é o
> contrato do **resto** da operação. É o maior buraco de risco do lote Vendas/PDV
> ([inventário](INVENTARIO-BLADE-VENDAS-PDV.md) §3-C) — e **mexe em estoque**.
>
> ⚠️ **Correção registrada (2026-08-18, mesma sessão).** A 1ª versão deste doc dizia *"zero teste"* e
> declarava 8 CU. Errado: meu detector procurava o nome da **view** (`sell_return.index`) dentro dos
> testes, e por isso não via os que exercitam `TransactionUtil::addSellReturn` **direto**. Varredura
> contada depois (`git grep -l -E "addSellReturn|sell_return|SellReturn" -- tests/ e2e/` → 10 arquivos)
> achou dois testes vivos. **Três CU meus eram duplicata** e foram rebaixados a ponteiro. Mesma família
> do falso-negativo dos recibos no inventário: *busca por nome não é inventário*.

## O que JÁ tem dono (não reescrever aqui)

| Comportamento | UC dono | Teste | Dono do doc |
|---|---|---|---|
| Devolução reintegra `qty_available` | `UC-EST-03` | `tests/Feature/Estoque/EstoqueDevolucaoVendaTest.php` | [DOC-RAIZ-ESTOQUE](../Estoque/DOC-RAIZ-ESTOQUE.md) |
| Devolução **parcial** reintegra só o devolvido | `UC-EST-03` | idem (2º `it`) | idem |
| Devolução é *reason-agnostic* (dinheiro / crédito) | `UC-EST-04` | `EstoqueDevolucaoVestuarioTest.php` | idem |
| Tier 0 — devolver `sell_line` de outro business é rejeitado | `UC-EST-04` | idem (3º `it`) | idem |

> Se algum destes precisar mudar, **muda lá** — este doc só aponta.

**Vocabulário (fonte única, com gate):** [`memory/dominio/vendas.md`](../../dominio/vendas.md) —
devolução = `sell_return`; o termo canônico é **"devolução"**, **nunca "estorno"** (estorno é de
*pagamento*, domínio financeiro). O `dominio:check` (G-4) falha o CI se um enum divergir.

**Legenda de status:** ✅ passa (com prova no manifesto) · 🧪 em prova parcial · ⬜ não verificado ·
❌ quebrou. Nenhum dos **5 CU deste doc** tem teste hoje — os ⬜ são exatamente o trabalho.

## Onde o teste vai morar (já tem dono — não criar lane)

`tests/Feature/Estoque/DevolucaoContratoTest.php` → entra na lane **`estoque-pest`**, que é
**REQUIRED** desde a [ADR 0369](../../decisions/0369-tres-lanes-pest-valor-estoque-lei-required-emenda-0314.md)
e cobre no próprio nome *"movimentação de saldo (venda/compra/devolução) no MySQL real"*.

A seleção é **árvore menos quarentena** (`.github/estoque-pest-quarantine.list`, 19 linhas, nenhuma de
devolução) → **arquivo novo entra rodando**, sem allowlist a editar. Roda no **CT 100**, nunca local
([proibicoes §Ambiente](../../proibicoes.md)); tenant de teste é o fictício **98**
([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — **biz=4 jamais**.

> Higiene do gatilho: o `paths:` do bloco `push` da lane não lista `SellReturnController.php` nem
> `TransactionUtil.php`. Não é buraco no fluxo normal (o bloco `pull_request` roda **sem** filtro de
> paths), mas somar os dois ao `push` é correto quando este contrato entrar.

---

## Rastreabilidade

Só o **delta** — 5 CU. O que já tem dono está no quadro acima e **não** ganha id novo aqui.

| CU | Caso de uso | Prio | Eixo Tier 0 | Status |
|---|---|---|---|---|
| CU-DEV-03 | Segunda devolução **substitui** a primeira (upsert) | must | estoque | 🧪 **provado no CT 100** |
| CU-DEV-04 | Valor da devolução não infla (desconto %) | must | valor | 🧪 **provado no CT 100** |
| CU-DEV-05 | Excluir devolução devolve o estoque ao estado anterior | must | estoque | 🧪 **provado no CT 100** |
| CU-DEV-07 | `access_own_sell_return` limita a devolução própria | must | permissão | ⬜ **sem veredito** |
| CU-DEV-08 | Devolver mais do que foi vendido é recusado | must | estoque | 🧪 **corrigido — trava landada** |

**Veredito de 2026-08-18** — `docker exec ... php artisan test`, container `oimpresso-staging`, MySQL
real, `business_id=98`:

- `DevolucaoContratoTest` → **3 passed · 16 assertions**, os três nomes visíveis no output (rodou, não
  pulou). Sobe a 🧪, não a ✅: falta o veredito entrar no manifesto G-7.
- `DevolucaoGuardaContratoTest` → **CU-DEV-08 ❌ CONFIRMADO · CU-DEV-07 sem veredito**.

### CU-DEV-08 — achado confirmado **e corrigido** (eixo ESTOQUE Tier 0)

**O achado.** Com o controle positivo VERDE (devolução válida de `2` creditou o estoque — logo o POST
executa mesmo), o contrato reprovou: `POST /sell-return` com quantidade `100` numa venda de `10` levou
`qty_available` de `10.0` para `110.0`. Cem unidades que nunca saíram entraram no estoque.

**A decisão.** [W] em 2026-08-18: *"precisa ter lastro"*.

**Onde a trava ficou, e por quê.** Em `TransactionUtil::addSellReturn`, **antes de qualquer escrita** —
não no controller. Varredura contada dos chamadores: **dois** (`SellReturnController@store` e a **API
pública** `Modules\Connector` .../`Api\SellController@addSellReturn`). Travar só no web deixaria a API,
a superfície mais exposta, aberta.

**Prova em dois caminhos (REGRA MESTRE §1):**

1. *Teste* — `CU-DEV-08` foi de ❌ a ✅, com o controle positivo continuando verde (a devolução válida
   ainda credita, então o verde não é por não-execução). Suíte de devolução: **9 passed / 29 assertions**,
   incluindo os preexistentes `UC-EST-03` e `UC-EST-04` — sem regressão.
2. *Fronteira medida* — venda de `10`:

   | Devolveu | Estoque | Resultado |
   |---:|---|---|
   | 1 | 10 → 11 | aceito |
   | 9,9999 | 10 → 20 | aceito |
   | **10 (total)** | 10 → 20 | **aceito** |
   | 10,5 | 10 → **10** | recusado |
   | 11 | 10 → **10** | recusado |
   | 100 | 10 → **10** | recusado |

   A devolução **total** — o caso legítimo mais comum — continua passando. Era o risco real do fix.

**Dois defeitos do próprio fix, pegos antes de fechar:** (a) a 1ª versão comparava com `num_f`, que
devolve **string formatada** — comparação lexicográfica em código de estoque; (b) a mensagem usava
`num_f`, que lê `session('currency')` sem fallback e **estoura fora de request** — a API do Connector
e qualquer job cairiam num `ErrorException` genérico em vez desta regra. Ambos corrigidos.

### Dado histórico em produção: **não há nada a corrigir** (e a 1ª leitura estava errada)

Medido em prod, 2026-08-18:

```
excesso REAL de devolução (quantity >= 0):  0
```

**Zero registros** de devolução acima do vendido. A trava é puramente preventiva.

⚠️ **Correção de uma afirmação minha.** A primeira medição foi um `COUNT` agregado do predicado
`quantity_returned > quantity` e devolveu 11 linhas (biz=164 com 10, biz=1 com 1). Eu li isso como
*"11 devoluções acima do vendido"* e cheguei a levar a decisão de conserto ao [W] nesses termos.
**Falso.** O dry-run por registro — exigido pela REGRA MESTRE **antes** de qualquer escrita — mostrou
`quantity_returned = 0,0000` nas **onze**: o predicado disparava porque `quantity` é **negativo**
(−1, −2, −4), e `0 > −1`. São linhas de quantidade negativa com devolução zero, e 5 delas nem são
venda (`type=purchase status=received` dentro de `transaction_sell_lines`).

Duas lições, ambas já catalogadas no §5 e reincididas aqui:

1. **Agregado não é evidência do fenômeno** — `COUNT` de um predicado conta o predicado, não a
   história que você atribui a ele. Abrir as linhas custava um `SELECT`.
2. **O dry-run por registro não é burocracia** — foi ele que impediu uma escrita em produção
   (tenant do piloto LIVE) baseada em diagnóstico falso.

**Fica em aberto, como observação e não como ação:** existem 11 linhas com `quantity < 0` em
`transaction_sell_lines`. Não são objeto desta trava e não foram investigadas — o padrão (biz=164,
datas de 2017 e 2024, migração do Office Comercial) sugere legado de importação. Levantar isso é
trabalho próprio, com dono próprio.

### O terceiro caminho, ainda sem lastro

`Modules\Vestuario\Services\DevolucaoService::registrarDevolucao` **não passa** por `addSellReturn` —
chama `ProductUtil::updateProductQuantity` direto (l.336). Seu `validarPayload` (l.350) exige
`quantidade_devolvida > 0`, mas **não** compara com o vendido. É o módulo do cliente piloto: fix
próprio, com teste próprio, em PR separado. **Não coberto por esta trava.**

### CU-DEV-07 — segue sem veredito (o controle positivo não passa)

`GET /sell-return` devolve **403** para o usuário criado com só `access_own_sell_return`, embora
`$alheio->fresh()->can('access_own_sell_return')` seja `true` (assertado no teste). O `index()` não
deveria abortar nesse caso, então o 403 vem de outra camada — provavelmente middleware da rota, ou
usuário de teste sem os campos que o stack UltimatePOS espera. **Não medido qual.**
Enquanto o controle positivo não passar, **nada se afirma** sobre a hipótese do `$sells`.

### O falso-verde que o controle positivo pegou

Na 1ª rodada o CU-DEV-08 passou **verde — e era mentira**. Ele só afirmava "o estoque não mudou", o
que também é verdade quando o POST nem roda (naquele momento faltava `session('user.id')` e o insert
violava `created_by NOT NULL`, caindo no `catch`). O controle positivo entrou depois e derrubou o
verde. Sem ele, este documento teria registrado "guarda existe" — o oposto da verdade.

> A numeração **pula 01/02/06 de propósito** — eles existiram na 1ª versão e viraram ponteiro para
> `UC-EST-03`/`UC-EST-04`. Reciclar o número faria um id significar duas coisas no histórico.

---



## CU-DEV-03 · Segunda devolução **substitui** a primeira — comportamento a caracterizar
- **Por que este CU existe:** `addSellReturn` procura um `sell_return` com o mesmo `return_parent_id`
  e, se acha, **atualiza** em vez de criar. E a quantidade é **atribuída**, não somada:
  `$sell_line->quantity_returned = $quantity` (6181). Logo **uma venda tem no máximo UMA devolução**.
- **Aceite (caracterização do comportamento ATUAL):** Dado devolução de `3` já lançada · Quando lança
  outra de `2` na mesma venda · Então **não** existem dois `sell_return` (o registro é o mesmo,
  `updated`), `quantity_returned = 2` (**não** `5`) e o estoque líquido reflete `2`.
- ⚠️ **Decisão [W] pendente:** isto é intencional (a devolução é sempre *o total devolvido daquela venda*,
  e a tela reabre o valor anterior) ou é perda silenciosa quando o cliente devolve em dois momentos?
  O teste **trava o comportamento atual**; mudá-lo é mexer em estoque → **REGRA MESTRE**
  (dois caminhos + tabela antes→depois + OK [W]), nunca de carona.

## CU-DEV-04 · Valor da devolução não infla (desconto percentual)
- **Por que:** `addSellReturn` chama `ProductUtil::calculateInvoiceTotal(...)` e passa `discount_amount`
  por `num_uf` — **o mesmo vetor** do incidente de 2026-06-05 que inflou 16 vendas da ROTA LIVRE ~×100k.
- **Aceite:** Dado devolução de `227,90` com desconto de `10,05%` · Quando `addSellReturn` grava ·
  Então `final_total = 204.99605` (jamais `~20.499.605`) e vale sempre `final_total ≤ total_before_tax`.
- **Vizinho a caracterizar:** `addSellReturn($input, $biz, $user, $uf_number = true)` — com `$uf_number
  = false` o parsing pt-BR **não** roda. Travar as duas chamadas como estão; unificar é US separada.

## CU-DEV-05 · Excluir devolução devolve o estoque ao estado anterior
- **Aceite:** Dado devolução de `3` lançada (estoque `X+3`) · Quando exclui · Então `quantity_returned`
  volta a `0`, `qty_available` volta a `X`, a `transactions` do `sell_return` é removida e cada
  `transaction_payments` dela emite `TransactionPaymentDeleted`.
- **Nota:** `destroy` roda dentro de `DB::beginTransaction()`; a asserção é do estado **após** o commit.


## CU-DEV-07 · `access_own_sell_return` limita à devolução própria — **failing-first**
- **Aceite:** Dado usuário **sem** `access_sell_return` e **com** `access_own_sell_return` · Quando pede
  `show`/`destroy` de devolução criada por outro · Então recebe negativa clara, sem erro genérico.
- 🔴 **Hipótese medida, ainda não provada:** o filtro "só as minhas" usa `$sells` em `show()` (l.345) e
  `destroy()` (l.412), mas `$sells` só é **definido na l.64, dentro de `index()`**. Varredura contada:
  11 ocorrências de `$sells` no arquivo — 9 dentro de `index()` (legítimas), **2 fora de escopo**.
  Se a leitura estiver certa, esses dois caminhos lançam antes de filtrar e o erro cai no `catch`
  genérico como *"algo deu errado"*. **Não é conclusão** — vira achado quando o teste ficar vermelho
  (§5 2026-07-15: achado exige varredura contada **+** âncora de contrato **+** vermelho rodado).
  O `index()`, que usa a variável certa, é o **controle positivo** do teste.

## CU-DEV-08 · Devolver mais do que foi vendido é recusado — **failing-first**
- **Aceite:** Dado venda com qty `10` · Quando lança devolução de `100` · Então é recusada e o estoque
  **não** é creditado.
- 🔴 **Hipótese medida:** não localizei trava nos dois lados. No front, o input de quantidade de
  `sell_return/partials/product_row.blade.php` (l.26) **não** tem `data-rule-max-value` — o do PDV
  (`sale_pos/product_row.blade.php`) tem. No back, `store()` faz `$request->except('_token')` e entrega
  ao `addSellReturn` **sem `validate()`**. Se confirmado, é crédito de estoque sem lastro.
- ⚠️ Vermelho aqui é **o achado**, não bug de teste. O conserto é decisão [W] sob REGRA MESTRE.

---

## Backlog (sem id até ter teste que os defenda — regra G-2)

- **[BACKLOG] Recibo da devolução** (`sell_return/receipt.blade.php`, 397 ln) — contrato de saída.
- **[BACKLOG] Devolução na listagem de vendas** — o indicador já é coberto por `UC-SIDX-01`
  ([Sells/Index.casos.md](../../../resources/js/Pages/Sells/Index.casos.md)); aqui seria o inverso.
- **[BACKLOG] Reward points recalculados na devolução** — `addSellReturn` mexe em `rp_earned` quando
  `enable_rp=1`; ROTA LIVRE não usa, então fica sem teste até haver tenant que use.
- **[BACKLOG] Efeito no Financeiro** — se a devolução gera/abate título. Não medido; o dono do tema é
  `Modules/Financeiro`, então o UC nasce lá, não aqui.

## Fontes usadas (ordem canônica de how-trabalhar.md)

1. **Canon:** `memory/dominio/vendas.md` (vocabulário, com gate) · ADR 0369 (lane) · ADR 0093
   (multi-tenant) · `proibicoes.md` REGRA MESTRE (valor/estoque).
2. **Código, só para confirmar:** `SellReturnController` (594 ln) · `TransactionUtil::addSellReturn`
   (6081-6192).
3. **Legado Delphi:** **não localizado** — não há `ANTI-REGRESSAO-*` de devolução. Se existir contrato
   de paridade do WR Comercial, ele **precede** o que está escrito aqui e estes CU se corrigem.
4. **Mercado:** não consultado (1-3 bastaram).
5. **Perguntar ao [W]:** CU-DEV-03 (upsert é intencional?) está aberto.

## Trilha do tempo

- 2026-08-18 · [CC] criado a partir do [inventário](INVENTARIO-BLADE-VENDAS-PDV.md). 8 CU derivados de
  canon + código; nenhum com teste ainda. Duas hipóteses medidas e **não** afirmadas como achado
  (CU-DEV-07 escopo de `$sells`, CU-DEV-08 ausência de trava de quantidade).

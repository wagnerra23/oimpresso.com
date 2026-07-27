---
id: memory-requisitos-produto-telas-ajuste-estoque-relatorio-casos
casos: Botão "Fix" do relatório de estoque · GET /reports/adjust-product-stock → ProductUtil::fixVariationStockMisMatch
irmaos: SPEC.md US-PROD-028 (âncora) · SDD-tela-cadastro-produto-v1.0.md §6.1 CU-PROD-10
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é o único ponto do sistema onde um clique SOBRESCREVE o saldo direto, sem movimentação e sem rastro no kardex — e os três parâmetros da escrita viajam na querystring de um GET.
owner: wagner
related_us: [US-PROD-028]
last_run: "2026-07-27"
last_run_ci: "1 UC já coberto por teste em lane (UC-PFIX-01); UC-PFIX-02/03 nascem neste PR — veredito pendente da lane Estoque · MySQL"
---

# Casos de Uso & Aceite — Ajuste de saldo pelo relatório de estoque (fluxo sem tela React)

> **Âncora:** `US-PROD-028` (*Blindar `fixVariationStockMisMatch` com parsing locale-safe*,
> `status: done`, [PR #4636](https://github.com/wagnerra23/oimpresso.com/pull/4636)) do
> [SPEC](../SPEC.md), cruzada com `CU-PROD-10` `[T0]` do
> [SDD §6.1](../SDD-tela-cadastro-produto-v1.0.md), com a **REGRA MESTRE valor/estoque**
> ([proibicoes.md](../../../proibicoes.md) Tier 0), com o
> [DOC-RAIZ-ESTOQUE](../../Estoque/DOC-RAIZ-ESTOQUE.md) §7 (INV-6 — saldo endereçado pelo par
> variação × local) e §10 (*"usar SEMPRE `ProductUtil` pra mexer `qty_available`"*), e com o
> **contrato de paridade Delphi** (`AR-PROD-051`/`AR-PROD-052` `[V0]` — o botão **Verificar**
> confere a disponibilidade **daquele local**).
>
> **Como este arquivo nasceu:** agent `sdd-from-source` ([ADR 0351](../../../decisions/0351-sdd-from-source.md)),
> fechando a lacuna `US-PROD-028 entregue sem contrato` do painel
> [`_STATUS-GENERATED.md`](../_STATUS-GENERATED.md). A US está `done` e **tem** teste
> (`EstoqueFixMismatchNumUfTest`, em lane) — o que faltava era o **UC** que amarra o que foi
> entregue à cadeia `US → CU → UC → teste`.
>
> **Status:** ✅ passa (prova na lane) · 🧪 teste cita o UC (veredito pendente) ·
> ⬜ não verificado · ❌ quebrou · 🔶 decisão [W].

---

## ⚠️ O fluxo, medido (2026-07-27, sha `16606e35c4`)

```
report/product_stock_details.blade.php   ← ÚNICO emissor: <a href> "Fix"
   │  ?location_id={{$location->id}}&variation_id={{$row->variation_id}}&stock={{$row->total_stock_calculated}}
   ▼
GET /reports/adjust-product-stock            (routes/web.php)
   ▼
ReportController@adjustProductStock          ← gate: report.stock_details
   ▼
ProductUtil::fixVariationStockMisMatch($biz, $variation_id, $location_id, $stock)
   │  UPDATE variation_location_details.qty_available = num_uf($stock)
   └─ DELETE das linhas "duplicadas" do mesmo par
```

Varredura contada: `adjustProductStock|adjust-product-stock` aparece em **1 rota + 1 Blade**, e em
**0** arquivos de `resources/js/`. Re-medir:

```
git grep -n "adjustProductStock\|adjust-product-stock" -- routes/ resources/
```

**Três propriedades incomuns deste caminho** (todas medidas, nenhuma inferida):

| # | Propriedade | Por que importa |
|---|---|---|
| 1 | **Escreve saldo dentro de um `GET`** | não passa por CSRF, é pré-carregável por link/prefetch, e não deixa movimentação no kardex (`AR-PROD-064` exige origem + usuário em cada movimento) |
| 2 | **Os 3 parâmetros vêm da querystring** | quem sabe montar a URL escolhe variação, local e valor — a própria `US-PROD-028` registrou isso no "escopo honesto" |
| 3 | **Sobrescreve, não movimenta** | é o único ponto do ecossistema que faz `qty_available = X` em vez de `+= delta` |

⚖️ **Força do veredito destes UC — `advisory`.** Lane `PHP / Pest (Estoque · MySQL)`, fora do
[`required-checks-baseline.json`](../../../../governance/required-checks-baseline.json):
**reprovação é visível e não bloqueia merge.**

---

## Rastreabilidade

| UC | Caso de uso | US | Prio | Contrato | Teste | Status |
|----|-------------|----|------|----------|-------|--------|
| UC-PFIX-01 | Saldo em pt-BR (`"1.500"`) grava 1500, não 1,5 | US-PROD-028 | must `[V0]` | REGRA MESTRE + DOC-RAIZ §10 | `EstoqueFixMismatchNumUfTest` | 🧪 (verde — já em lane desde #4636) |
| UC-PFIX-02 | O Fix não altera saldo de variação de outro business | US-PROD-028 | must `[T0]` | `CU-PROD-10` + [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md) | `AjusteEstoqueRelatorioContratoTest` | 🧪 (verde esperado) |
| UC-PFIX-03 | Reconciliar um local não mexe no saldo dos outros | US-PROD-028 | must `[V0]` | DOC-RAIZ §7 INV-6 + `AR-PROD-051/052` | `AjusteEstoqueRelatorioContratoTest` | 🧪 (verde esperado) |
| **Σ US-PROD-028** | — | — | — | 3 UC, 0 órfão | 2 arquivos, ambos em lane | 🧪 pendente da lane |

> 🧪 **e não ✅**: eu não rodo teste (CT 100 · [ADR 0062](../../../decisions/0062-separacao-runtime-hostinger-ct100.md)).
> O `UC-PFIX-01` tem histórico de execução (era RED antes do fix, recibo no docblock do teste:
> CT 100 `oimpresso-staging` HEAD `34fe49730`, *"1.5 is not identical to 1500.0"*), mas o veredito
> **desta** corrida é da lane, não da minha leitura (G-7).

---

## UC-PFIX-01 · Saldo em pt-BR (`"1.500"`) grava 1500, não 1,5 · `must` `[V0]`

- **Persona:** Larissa clica em "Fix" numa linha do relatório em que o calculado e o registrado
  divergem. O valor que vai na URL é o **calculado**, renderizado pela Blade.
- **Aceite:** *Dado* uma variação com saldo divergente · *Quando* o Fix roda com
  `stock = "1.500"` · *Então* `qty_available` fica `1500`.
- **Teste:** [`EstoqueFixMismatchNumUfTest`](../../../../tests/Feature/Estoque/EstoqueFixMismatchNumUfTest.php)
  — `REGRESSÃO US-PROD-028: fixVariationStockMisMatch aplica num_uf — "1.500" grava 1500 (não 1,5)`,
  com contraprova no irmão `updateProductQuantity`.
- **Contrato:** REGRA MESTRE (`proibicoes.md` Tier 0 — *toda escrita de valor/estoque é
  locale-safe*; origem: incidente 2026-06-05) + [DOC-RAIZ-ESTOQUE §10](../../Estoque/DOC-RAIZ-ESTOQUE.md)
  (*"usar SEMPRE `ProductUtil` pra mexer `qty_available`"*, e o caminho numérico canônico é
  `num_uf`-based: 4 irmãos o aplicam).
- **Regressão que defende:** `fixVariationStockMisMatch` era o **único** dos 5 mutadores de saldo
  que não aplicava `num_uf` — gravava o `$stock` cru do request. O fix (#4636) fechou o eixo
  numérico; este UC o **trava**.
- **Status: 🧪** — já em lane desde #4636 (veredito da corrida vem da lane).

---

## UC-PFIX-02 · O Fix não altera saldo de variação de outro business · `must` `[T0]`

- **Persona:** qualquer usuário com `report.stock_details` — que é permissão de **relatório**, não
  de estoque. Os três parâmetros da escrita estão na URL.
- **Aceite:** *Dado* que o Fix grava no **meu** par (pré-condição) · *Quando* chamo o Fix com a
  variação e o local de **outro** business · *Então* o saldo daquele tenant permanece exatamente
  o que era.
- **Teste:** [`AjusteEstoqueRelatorioContratoTest`](../../../../tests/Feature/Produto/AjusteEstoqueRelatorioContratoTest.php)
  — `UC-PFIX-02 · o Fix não altera saldo de variação de outro business (Tier 0)`.
- **Contrato:** `CU-PROD-10` `[T0]` + [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md)
  + o **"escopo honesto" da própria US-PROD-028**, que declarou o vetor e o deixou fora do fix:
  *"(a) endpoint **GET** com `stock` arbitrário na query → tampering grava qualquer valor sem
  `num_uf`/validação/CSRF (qualquer user com `report.stock_details`)"*.
- **Regressão que defende:** o isolamento hoje mora num `join` (`bl.business_id = $business_id`) —
  se a query for reescrita pra filtrar direto por `variation_location_details` (que **não tem**
  `business_id`; é o INV-6, contrato transitivo do DOC-RAIZ §6/§7), o escopo desaparece e nada
  acusa. Este UC é o guarda desse transitivo neste caminho específico.
  > ⚠️ **Nota honesta de comportamento (medida, deliberadamente FORA do assert):** quando o par não
  > é seu, a query de deduplicação logo abaixo do `if` dereferencia `$vld->id` com `$vld` nulo. O
  > teste tolera o `Throwable` e afirma **só** o efeito no banco. O desfecho HTTP disso está no
  > §Backlog — escolher entre 404, 422 ou silêncio é decisão [W], e encodá-lo no assert seria
  > escolher o remédio antes do diagnóstico (`proibicoes.md` §5, 2026-07-15).
- **Status: 🧪** — verde esperado (trava de invariante Tier 0).

---

## UC-PFIX-03 · Reconciliar um local não mexe no saldo dos outros · `must` `[V0]`

- **Persona:** Larissa tem o mesmo produto em dois locais. Ela reconcilia o do depósito; o da loja
  **não pode** mudar.
- **Aceite:** *Dado* o mesmo produto com 5 no local A e 30 no local B · *Quando* o Fix reconcilia
  o local A para 17 · *Então* A fica 17 (pré-condição) e B continua 30.
- **Teste:** [`AjusteEstoqueRelatorioContratoTest`](../../../../tests/Feature/Produto/AjusteEstoqueRelatorioContratoTest.php)
  — `UC-PFIX-03 · o Fix reconcilia só o local da linha — o saldo dos outros locais não muda`.
- **Contrato:** [DOC-RAIZ-ESTOQUE §7](../../Estoque/DOC-RAIZ-ESTOQUE.md) INV-6 (o saldo é
  endereçado pelo **par** variação × local) + `AR-PROD-051` `[V0]` (**Disponível** é saldo por
  local) + `AR-PROD-052` `[V0]` (o botão **Verificar** do legado confere a disponibilidade
  **daquele** local) + Blade `product_stock_details` (cada linha da tabela é um par, e o botão
  está **na linha**) + REGRA MESTRE.
- **Regressão que defende:** além do `UPDATE`, o método roda um **`DELETE`** das linhas
  "duplicadas" do mesmo par — hoje filtrado por `location_id` **e** `variation_id`. Se o filtro de
  local sair dessa segunda query (ou se o índice mudar e alguém "otimizar" o `where`), o botão
  passa a **apagar** o saldo dos outros locais: em silêncio, dentro de um `GET`, sem confirmação e
  sem rastro no kardex. Nenhum teste cobria a especificidade por local neste caminho.
- **Status: 🧪** — verde esperado (trava `[V0]` de alcance da escrita).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> ⚠️ Este diretório **não** é varrido pelo `casos-coverage-guard` (que vê só `Pages/**`), então o
> G-2 não pune um órfão aqui — o critério de parada é disciplina, não gate.

- **[BACKLOG] `GET` que escreve saldo — sem CSRF, sem POST, sem confirmação.** A própria
  `US-PROD-028` declara: *"As opções GET→POST/CSRF e recomputação server-side continuam **fora do
  escopo** desta US; só viram nova US com sinal próprio"* — decisão [W] já registrada. **Não vira
  UC**, justamente porque o remédio foi explicitamente adiado; transformá-lo em contrato agora
  seria reabrir por dentro uma fronteira que o dono já desenhou. Re-abrir só com sinal
  ([ADR 0105](../../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)) — ex.: auditoria
  mostrando exploração, que a US define como o gatilho pra escalar a p0.
- **[BACKLOG] Par inexistente/alheio dereferencia `$vld->id` nulo.** Medido: a query de
  deduplicação está **fora** do `if (! empty($vld))`, e `adjustProductStock` não tem `try/catch` —
  o desfecho é erro 500 (não 404). Divergência aberta: o `CU-PROD-10` item 2 promete *"cross-tenant
  por ID → **404**"*, e o SDD já registra que isso é *"verdadeiro só no GET"* em outra tela, com
  decisão [W] pendente lá (§Backlog de [`SellingPrices.casos.md`](../../../../resources/js/Pages/Produto/SellingPrices.casos.md)).
  **Mesma decisão, terceira tela** — quando [W] resolver o padrão, os três fecham juntos.
- **[BACKLOG] O ajuste não deixa rastro no kardex.** `AR-PROD-064` `[V0]` exige que **cada
  movimento** rastreie origem (Cód. Venda / NF) **e** usuário, append-only. Este caminho escreve
  `qty_available` direto, sem `Transaction`/`purchase_line`/`stock_adjustment` — então a diferença
  some do histórico e o próximo relatório mostra "calculado = registrado" sem explicar o que houve.
  Vira UC (ou US) quando [W] decidir se reconciliação é **movimento** (e aí precisa de tipo
  próprio, como o `stock_adjustment` que já existe) ou **correção fora do razão**.
- **[BACKLOG] Nenhum teste cobre o `ReportController@adjustProductStock` em si** — os 3 UC acima
  exercitam o `ProductUtil` (o serviço), não a rota. O gate de permissão (`report.stock_details`),
  o `redirect()->back()` e a mensagem de sucesso não têm contrato. Vira UC quando houver fixture
  de usuário **sem** a permissão (hoje o seed de biz=1 é admin, então o caso negativo não é
  exercitável sem montar user novo — mesma pendência do trio do `BulkEdit`).

---

## Refs

- SPEC (âncora da US): [`SPEC.md`](../SPEC.md) §`US-PROD-028` (`status: done`,
  **Implementado em:** `ProductUtil::fixVariationStockMisMatch` + `EstoqueFixMismatchNumUfTest`)
- SDD: [`SDD-tela-cadastro-produto-v1.0.md`](../SDD-tela-cadastro-produto-v1.0.md)
  §5.3 **F12** (fluxo do ajuste) + §6.1 `CU-PROD-10`
- Doutrina de estoque: [`DOC-RAIZ-ESTOQUE.md`](../../Estoque/DOC-RAIZ-ESTOQUE.md) §7 (INV-6) + §10
- Paridade Delphi: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../ANTI-REGRESSAO-cadastro-produto-legacy.md)
  (`AR-PROD-051`/`AR-PROD-052`/`AR-PROD-064`)
- Blade emissora: `resources/views/report/product_stock_details.blade.php` (linha do botão "Fix")
- Controller: `app/Http/Controllers/ReportController.php` — `adjustProductStock()`.
  Re-localize com `grep -n "function adjustProductStock" app/Http/Controllers/ReportController.php`
- Scorecard de origem: [`app-utils-productutil.yaml`](../../../governance/scorecards/funcoes/app-utils-productutil.yaml)
  (`fixVariationStockMisMatch` C2 — o parecer que gerou a US)
- Painel da cadeia: [`_STATUS-GENERATED.md`](../_STATUS-GENERATED.md)
- Lane: `PHP / Pest (Estoque · MySQL)` (**advisory** — fora do `required-checks-baseline.json`)

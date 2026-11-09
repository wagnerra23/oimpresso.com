---
id: requisitos-compras-briefing
slug: compras-briefing
title: "BRIEFING — Modules/Compras"
type: briefing
module: Compras
status: em-construcao
updated_at: "2026-11-09"
version: 0.4
owner: W
---

# BRIEFING — Modules/Compras

## TL;DR (6 linhas)

- **O que é:** cockpit de compras (`/compras`) — lista paginada + 4 KPIs + drawer denso 5 tabs — e as regras de compra que o CRUD `/purchases/*` Inertia consome (convergência C1). Domínio: nota de entrada / pedido de compra sobre `transactions` polimórfica.
- **Estado (verificado 2026-08-16):** cockpit **live no código**. **NÃO está em produção nem canary pra nenhum business** (D5=0). Larissa @ ROTA LIVRE biz=4 sinalizou dor mas nunca usou. _(A nota do módulo não é restateada aqui — ver §Score.)_
- **Caminho:** B híbrido — greenfield Controllers/Pages, REUSA `transactions` + `TransactionUtil` + Observer Financeiro.
- **Desde 26/10:** entraram recebimento parcial pelo pedido, MRP, cotação (só serviço), alçada de valor, inspeção no recebimento (só serviço), custo médio móvel e curva ABC. Alçada, inspeção, custo médio e curva ABC nascem **desligados** (§Flags).
- **Maior gap:** a **ponte DF-e→compra** (`nfe_dfe_recebidos → Transaction type=purchase`, US-COM-003) — o import fiscal já existe no NfeBrasil, falta plugar.
- **Capacidade vs mercado:** **34/100** _(medido 2026-07-03, antes das entregas de outubro; não remedido)_ ([CAPTERRA-FICHA.md](CAPTERRA-FICHA.md)).

## Capacidades atuais (2026-11-09)

| Capacidade | Status | Onde |
|---|---|---|
| Cockpit `/compras`: lista + 4 KPIs + filtros query-string | ✅ live no código | `ComprasController::index` + `ComprasService` (Inertia::defer 4 props) · `Index.tsx` |
| Drawer detalhe 5 tabs (Resumo/Itens/Documentos/Pagamentos/Histórico) + timeline | ✅ | `Drawer.tsx` (608 LOC) |
| Multi-tenant Tier 0 REAL (Pest cross-tenant biz=1/99 + guard SQL + auth-based `business_id`) | ✅ | `MultiTenantTest.php` + `MultiTenantSqlGuardTest.php` (hotfix R1 `contacts.business_id`) |
| Throttle 60/1 + FormRequest `ListarComprasRequest` + OTel spans | ✅ (throttle sem Pest do 429) | `Routes/web.php` + `Http/Requests/` + `ComprasService` |
| Rotas de `/compras` exigem o pacote `compras_module` e, no cockpit, `compras.view` | ✅ desde 2026-11-06 | `ComprasLiberado` (#10572) |
| Telas React do núcleo: compras (lista, nova, editar, detalhe), pedidos, requisições, devoluções | ✅ Blade `purchase.show` apagada (#10037) | `resources/js/Pages/Purchase*/` |
| Estoque na entrada (movimenta `variation_location_details` por variação/local) | ✅ **preservado do Blade** + guard `assertPurchaseVariationsOwnership` | `PurchaseController::store` → `ProductUtil` (fluxo `/purchases`) |
| Grade tam×cor matricial (vestuário) | ✅ **construída ponta-a-ponta** (upgrade sobre Blade linha-a-linha) | `GET /purchases/grade-matrix` → `GradeMatrixInput`/`GradeProductCombobox` em `Purchase/Create.tsx` (US-COM-005) |
| Contas a pagar automático ao comprar | ✅ herdado (Observer Financeiro cria `fin_titulos` type=pagar) | `TransactionObserver` via `/purchases/store` |
| FSM 6 estágios PERSISTIDA | ❌ só `const STAGES` visual no `Drawer.tsx` (não é máquina de estado) | — |
| Importar XML DF-e como compra | 🟡 **substrato pronto no NfeBrasil** (pull SEFAZ NSU + manifestação testados); falta só a **ponte→compra** | `Modules/NfeBrasil/Services/Manifestacao/*` ✅ · bridge `ImportarDfeComoCompraService` ❌ (US-COM-003) |
| Matching automático XML→produto (EAN/xProd) | ❌ | depende da ponte |
| Recebimento parcial pelo pedido de compra | ✅ a tela de compra traz o pedido com o que falta; o pedido fica parcial até completar | `PedidosAbertosDoFornecedor` + `ReceberDoPedido` (US-COM-013) |
| MRP: necessidade por insumo e local, com data para pedir | ✅ acima da lista de requisições | `NecessidadeDeCompra` (US-COM-030) |
| Cotação entre fornecedores, fechada em pedido | 🟡 serviço pronto e testado, **sem tela** | `CotacaoDeCompra` (US-COM-031) |
| Alçada de valor no pedido (comprador, gerente, diretoria) | ✅ desligada por padrão | `AlcadaDoPedido` (US-COM-032) |
| Inspeção no recebimento; reprovado vira devolução | 🟡 serviço pronto e testado, **sem tela**, desligada | `InspecaoDoRecebimento` (US-COM-034) |
| Custo do recebimento e preço proposto no detalhe da compra | 🟡 aprovar/recusar pronto; falta a fila na Fabricação | `AplicarCustoDoRecebimento` + `PropostasDePreco` (US-COM-026/028) |
| Custo médio móvel por variação | 🟡 atrás de flag, depende da semente aprovada pelo [W] | `CustoMedioMovel` + `compras:custo-medio-semear` (US-PROD-024) |
| Curva ABC e sugestão de reposição pelo giro | 🟡 só leitura, atrás de flag | `CurvaAbcReposicao` (US-ESTOQUE-003) |
| Estoque mínimo vira requisição | ✅ desligado por padrão | `RequisicaoEstoqueMinimoService` (US-COM-027) |
| Teste de cálculo custo/total/estoque (Tier 0) | ✅ E2E na lane de PR, no tenant 98 | `PurchaseCalculoValorEstoqueE2ETest` (US-COM-011) |
| Isolamento entre empresas no caminho de compra e pedido | ✅ auditoria 2026-11-05, lote 0 | #10425, #10451, #10464, #10476, #10481 |

## Flags e chaves de ativação

Valor em produção não é copiado aqui: confira no `.env` e em `business.common_settings`. Em código, todas nascem desligadas.

| Chave | Onde | Liga o quê |
|---|---|---|
| `compras_module` | pacote da empresa (Superadmin) | menu e rotas operacionais de `/compras` |
| `COMPRAS_CUSTO_MEDIO_MOVEL` | env · `config('compras.custo_medio_movel')` | custo médio móvel; só depois da semente aprovada pelo [W] |
| `COMPRAS_CURVA_ABC_REPOSICAO` | env · `config('compras.curva_abc_reposicao')` | curva ABC e sugestão de reposição |
| `compras_alcada` | `business.common_settings` | alçada de valor do pedido, com os limites |
| `compras_inspecao_recebimento` | `business.common_settings` | inspeção no recebimento |
| `compras_requisicao_estoque_minimo` | `business.common_settings` (aba Fluxos da empresa) | requisição automática por estoque mínimo |

## Capabilities mapeadas pra vertical

- **horizontal:** todo business que compra de fornecedor — base de qualquer ERP
- **vestuário (Larissa):** grade tam×cor por modelo pai (US-COM-005) — construída, aguarda canary
- **automotivo (OficinaAuto):** futuro — compra de peças com lote/garantia
- **gráfica (Officeimpresso):** futuro — matéria-prima (PVC, papel) com unidade fracionada

## Score (atual — 3 réguas distintas)

| Régua | Nota | O que mede | Fonte |
|---|:-:|---|---|
| **module-grade v3** | **viva — não copiada** | governança/higiene (Tier 0, Pest, doc, sec, LGPD, obs) | era `governance/module-grades-baseline.json` (APOSENTADO 2026-09-15 — [ADR 0399](../../decisions/0399-aposentar-rubrica-module-grade-gate-e-baseline.md)) · rubrica e gate APOSENTADOS em 2026-09-15 (ADR 0399); a linha fica como registro do que se media |
| **CAPTERRA design** | **67** _(medido 2026-07-03)_ | UX/UI do protótipo Cowork F1 | [CAPTERRA-DESIGN-FICHA.md](CAPTERRA-DESIGN-FICHA.md) |
| **CAPTERRA capacidade** | **34** _(medido 2026-07-03)_ | features/automação/fiscal/recebimento vs líderes | [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) |

> **Por que a 1ª linha não tem número:** a nota do módulo tem **dono vivo** (baseline + gate que reporta em todo PR). Em **2026-08-16** este doc afirmava **59** enquanto o dono media **73** — 14 pontos de drift silencioso, porque a nota subiu e o texto não. _(Os dois números acima são o retrato daquele dia, não o valor de hoje: para o valor de hoje, leia o dono.)_ Doc canônico não restateia número que outro sistema sabe melhor (proibições §5, 2026-07-17). As duas CAPTERRA ficam porque são **fato datado** de auditoria — snapshot honesto, não ponteiro podre.
>
> Leitura: as três divergem de propósito. Higiene e design escondem que o **motor de compra** (34) não fecha o ciclo — importa/manifesta (via NfeBrasil) mas não vira compra, não casa PO, não recebe parcial, não concilia. Ver §8 da FICHA.

## Próximos passos (backlog priorizado)

Fonte viva: `tasks-list module:Compras` no MCP e o [SPEC.md](SPEC.md).

1. **[P0]** Ponte DF-e recebida → Compra (US-COM-003) e matching XML→produto (US-COM-012)
2. **[P0]** Lane de Compras por árvore-menos-quarentena (US-COM-022)
3. **[P1]** Testes de invariante de estoque (US-COM-015) e de contas a pagar pelo fluxo (US-COM-016) · FSM persistida (US-COM-014)
4. **Telas pendentes:** cotação e inspeção · fila de preços na Fabricação
5. **Canary** do cockpit (US-COM-023), ato do [W]

## Pegadinhas conhecidas (Tier 0)

- Multi-tenant ADR 0093 — `auth()->user()->business_id` (não session); Job recebe `$businessId` no constructor
- **Valor/estoque (regra-mestre):** entrada de compra MEXE EM ESTOQUE (1 célula de grade = 1 SKU × custo × qty) — dupla confirmação + antes→depois + aprovação antes de mergear cálculo
- **Tenant de teste = 98** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md), que **supersede** a `0101-tests` e a esqueceu fisicamente — o slug antigo só resolve por lápide em `governance/adr-tombstones.json`). **biz=4 é proibido sem exceção** em teste, fixture, smoke ou exemplo; canary 7d em biz=4 é ato de [W], não de teste.
  - ⚠️ **Drift aberto (não consertado aqui):** o `MultiTenantTest.php` deste módulo ainda usa **biz=1 vs biz=99**, doutrina anterior à 0358. Não toquei — é infra de teste Tier 0 e o conserto pertence à leva que migrou os outros módulos (#4974), não a um PR de BRIEFING. Decisão de [W].
- Convergência C1 — NÃO criar `Pages/Compras/Create.tsx`; "+ Nova compra" delega `/purchases/create` via `router.visit`
- Cowork bundle aplicar INTEIRO 1ª vez (proibicoes.md Tier 0)

## Owner & ADRs

- Owner: Wagner
- ADRs base: [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) · 0094 · [0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md) _(supersede a `0101-tests`)_ · 0104 · [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) · 0106 · 0114 · 0143
- ADR proposta: [compras-purchase-convergencia-c1](../../decisions/proposals/compras-purchase-convergencia-c1.md) (C1 vigente)

## Refs

- [SPEC.md](SPEC.md) (US-COM-001..034) · [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (capacidade 34) · [CAPTERRA-DESIGN-FICHA.md](CAPTERRA-DESIGN-FICHA.md) (design 67) · [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) (backlog)
- SDD do fluxo de ponta a ponta: seção do domínio em [SDD-tela-cockpit-compras-v1.0.md](SDD-tela-cockpit-compras-v1.0.md) · [AUDIT-SENIOR-2026-05-25.md](AUDIT-SENIOR-2026-05-25.md) · [DISCOVERY-LARISSA-COMPRAS.md](DISCOVERY-LARISSA-COMPRAS.md) · [RUNBOOK-compras-index.md](RUNBOOK-compras-index.md)
- Session logs: [2026-05-21 como-integrar](../../sessions/2026-05-21-como-integrar-compras.md) · [2026-07-03 capterra](../../sessions/2026-07-03-capterra-compras.md)

## Contrato de tela (SDD)

O módulo passou a ter **SDD** em [`SDD-tela-cockpit-compras-v1.0.md`](SDD-tela-cockpit-compras-v1.0.md) — §5 fluxos + §6 casos de uso — e `casos.md` por tela,
gerados pelo chip `sdd-from-source` ([ADR 0351](../../decisions/0351-sdd-from-source.md), PR #4864).

> **Contagem viva — não copiada aqui** (CU · UC · telas cobertas · onde a cadeia quebra):
> `node scripts/governance/requisitos-status.mjs Compras`
>
> O painel derivado fica em [`_STATUS-GENERATED.md`](_STATUS-GENERATED.md). Número escrito à mão apodrece —
> este doc aponta para o dono, não restateia (proibições §5, 2026-07-17).

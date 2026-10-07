---
sessao: "05"
titulo: Esquemas de fatura · Impostos · Tipos de serviço → Inertia — saída
playbook: sistema
thread: "05"
dono: "[CL]"
data: "2026-10-07"
base: wagnerra23/oimpresso.com@main 925f3e0428 (lido 2026-10-07; #9011, #9012 e #9013 ainda abertos)
---

# _saida-05 · Esquemas de fatura · Impostos · Tipos de serviço → Inertia

Mesmo caminho da thread 04, uma tela por vez pelo MWART (ADR 0104): F1 (RUNBOOK + paridade) → F2 (Pest baseline da
Blade) → F3 (Page atrás de flag: lista, depois drawer). **F4 (smoke biz=1 com a flag ligada) e F5 (cutover) não foram
feitas**: F4 depende de ligar a flag em produção para biz=1, que espera o OK do [W]; F5 é decisão [W]. Com as flags
desligadas (default), produção segue nas Blades.

## 1 · Feito

| Tela | Rota | Flag (default OFF) | F1+F2 | F3-1 (lista) | F3-2 (drawer) |
|---|---|---|---|---|---|
| Impostos | `/tax-rates` | `useV2ConfiguracoesImpostos` | #8981 | #9006 | #9011 |
| Tipos de serviço | `/types-of-service` | `useV2ConfiguracoesTiposServico` | #8983 | #9007 | #9012 |
| Esquemas de fatura | `/invoice-schemes` | `useV2ConfiguracoesEsquemasFatura` | #8984 | #9009 | #9013 |

- Pages em `resources/js/Pages/Configuracoes/<Aba>/Index.tsx`, com charter + casos + teste de contrato, e as abas do
  `ConfiguracoesSubNav` (D1) da thread 04.
- **Tier 0 corrigido (#8979):** `InvoiceSchemeController::update/destroy/setDefault` alcançavam, pelo id, o esquema de
  fatura de outro negócio (prefixo e número inicial da numeração das notas). Mesmo desenho do código de barras (#8924).
  Vermelho antes, verde depois, no CT 100.
- **Valor (regra mestre):** alíquota e taxa de embalagem viajam como texto pt-BR e o `num_uf` segue único parser.
  Provado por dois caminhos (endpoint × `num_uf`) no baseline e no drawer. Editar só o nome não move o número.
- Os 3 controllers entraram no gatilho da lane `acessos-pest`.

## 2 · Não feito e por quê
- **F4 e F5** (acima).
- **Grupos de imposto:** listados, não editados. O CRUD é do `GroupTaxController` (fora do prefixo), com achado aberto (§3.1).
- **Editor de layout de fatura:** a aba só lista e leva ao editor da Blade (`InvoiceLayoutController`, fora do prefixo).
- O contador de notas emitidas (`invoice_count`) não é editável: é a venda que incrementa.

## 3 · Achados para o índice (não editei o índice — é do Cowork)
1. **`GroupTaxController` — Tier 0 e valor, decisão [W].** `store()`/`update()` aceitam alíquotas de **outro negócio**
   como sub-impostos (`TaxRate::whereIn('id', $ids)` sem `business_id`), somam o valor delas na alíquota do grupo e gravam
   o pivô apontando para elas. O `store()` não confere permissão nenhuma. Mexe no imposto da venda; sugere thread própria
   com dupla prova de valor.
2. **Numeração aleatória não limpa o número inicial.** `InvoiceSchemeController::store/update` comparam `number_type ==
   'aleatory'`, mas a chave é `random`. Travado como está no `EsquemasFaturaBaselineTest`; mudar mexe na numeração de nota.
3. O `json_encode` do `TypesOfServiceController::update` é redundante nesta versão do Laravel (mutante equivalente).
4. No conserto do #8924 (código de barras) o `refresh()` do `setDefault` ficou redundante depois da troca para
   `is_default = true` — mesmo mecanismo medido aqui no esquema de fatura. Inofensivo; o comentário de lá descreve o
   cenário com `1`.
5. `TaxRateController`/`TypesOfServiceController::update/destroy` de id de outro negócio não alteram nada, mas os de
   Tipos de serviço respondem `success: true`.

## 4 · Provas
- `Inertia::render(` nos 3 controllers (provas do índice): no `main` desde #9006 / #9007 / #9009.
- Pest no CT 100 (cópia isolada, sem tocar o checkout compartilhado): mordida provada por mutante em cada defesa de
  isolamento, no `num_uf` de alíquota e taxa, no recálculo do grupo e no ida e volta dos drawers.
- Lane `acessos-pest` conferida por nome de teste pelo gerente da fila em cada PR.

## 5 · Placar
entregue 3 de 3 telas até F3 · ausentes F4/F5 por decisão [W] (flag em produção / cutover) · grupos de imposto e
numeração aleatória por decisão [W].

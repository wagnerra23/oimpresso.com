---
sessao: "13"
titulo: "Despesas em React — saída da thread"
autor: "[CL]"
data: 2026-10-14
base: wagnerra23/oimpresso.com@main a1b04cbacc
thread: 13-despesas.md
veredito: "entregue atrás de flag OFF — lista, drawer/ações, nova despesa e categorias em React pelos mesmos endpoints da Blade; US-MWART-019 (QA + aprovação visual) e 5 decisões [W] pendentes antes de ligar a flag."
---
# _saida-13 · Despesas em React

## O que a thread pedia e o que mudou no caminho
A ficha pedia 2 PRs (lista + drawer; nova despesa + categorias), processo MWART (ADR 0104) e trio + teste de contrato
98×99, com a Blade como fallback em `?classico=1`. Três coisas mudaram a forma, cada uma por um motivo medido:

- **Saíram 7 PRs, não 2.** O MWART exige o F1 (RUNBOOK + paridade) mergeado antes de criar a Page, e o hook
  `block-mwart-violation` barra o `.tsx` sem o RUNBOOK. A US-015 (ação dupla) não pôde sair sozinha: o
  `OrphanRenderGateTest` (required) reprova `Inertia::render` de Page inexistente, então ela foi junto com a 016.
- **Pasta das Pages:** `Pages/Financeiro/Despesas/` e `Pages/Financeiro/CategoriasDespesa/`, e não `Pages/Despesas/`
  como o `prefixo` do índice. Os caminhos casam com os slugs dos alvos da A3 (`financeiro--despesas--index`,
  `financeiro--categorias-despesa--index`). `Pages/Financeiro/Categorias` já existia e é a `fin_categorias`, outra tabela.
- **Despesa vira título?** Já vira, e a tela não mexe nisso: o `TituloAutoService` cria `fin_titulos` com
  `origem='despesa'` na criação e o `BridgeExpenseToTitulosCommand` cobre o histórico. As telas novas gravam
  `transactions` pelo mesmo `store()`.

A migração já tinha dono: **US-MWART-009** (Onda 6). As subtasks entraram como US-MWART-014..019 no `Mwart/SPEC.md`.

## Entregue
| PR | US | o quê | estado |
|---|---|---|---|
| [#9260](https://github.com/wagnerra23/oimpresso.com/pull/9260) | F1 | `RUNBOOK-despesas.md`, `despesas-parity.md` (22 itens), US-MWART-014..019 | mergeado |
| [#9267](https://github.com/wagnerra23/oimpresso.com/pull/9267) | 014 | `ExpensesBaselineTest`: o que a Blade/controller fazem hoje, antes de mexer | mergeado |
| [#9275](https://github.com/wagnerra23/oimpresso.com/pull/9275) | 015/016 | ação dupla no `ExpenseController@index` + `Financeiro/Despesas/Index` | mergeado |
| [#9276](https://github.com/wagnerra23/oimpresso.com/pull/9276) | 017 | drawer 760 + Editar, Documento, Excluir (mesmo `destroy`) | mergeado |
| [#9280](https://github.com/wagnerra23/oimpresso.com/pull/9280) | 018 (1) | Nova despesa pelo mesmo `store` | mergeado |
| [#9344](https://github.com/wagnerra23/oimpresso.com/pull/9344) | 018 (2) | `Financeiro/CategoriasDespesa/Index` pelos mesmos `store`/`update`/`destroy` | aberto, verde, armado no gerente |

Tudo atrás da flag `useV2FinanceiroDespesas`, default **OFF** (sem a chave no GrowthBook). Com ela desligada, ou com
`?classico=1`, `/expenses` e `/expense-categories` servem a Blade. Nada mudou para quem usa o sistema.

## Provas (runs da lane `PHP / Pest (Financeiro · MySQL)`, testes pelo nome)
| teste | casos | run | resultado |
|---|---|---|---|
| `tests/Feature/Expense/ExpensesBaselineTest.php` | 8 casos `baseline:` | 38466270398 | 8 ✓ · lane 497 passed, 0 failed |
| `tests/Feature/Expense/DespesasIndexContratoTest.php` | UC-DESPES-01..04 (#9275) | 38473422467 | ✓ · lane 493 passed, 0 failed |
| idem | UC-DESPES-01..05 (#9276) | 38467901444 | ✓ · lane 494 passed, 0 failed |
| idem | UC-DESPES-01..07, o 07 em 4 formatos de data (#9280) | 38467905171 | ✓ · lane 499 passed, 0 failed |
| `tests/Feature/Expense/CategoriasDespesaContratoTest.php` | UC-CATD-01..05 (#9344) | 38550714299 | ✓ · lane 512 passed, 0 failed |
| `tests/js/despesas-total-envio.test.ts` | UC-DESPES-06 (o que a tela envia) | vitest local + passo do `ci.yml` | 6/6 |

Tier 0: UC-DESPES-01 e 05 e UC-CATD-01, 03 e 04 são casos de isolamento entre negócios, todos com controle positivo
no mesmo caso. Nos de lista, a despesa alheia usa o MEU local, para que só o `business_id` a separe.

## VALOR (regra mestre de `proibicoes.md`)
- **Nada gravado muda de valor.** Total e devido da tela nova usam a mesma conta e o mesmo sinal do DataTable
  (estorno negativo; devido = total − pagamentos), provado por dois caminhos no UC-DESPES-02: 150/100 e −150/−100.
- **Nova despesa:** a tela manda o total com 2 casas e vírgula (`1.234,56` → `"1234,56"`), nunca float cru (incidente
  2026-06-05). Prova por três caminhos (UC-DESPES-06): o que a tela envia, o que o banco grava (1234.56) e o que as
  duas listas leem (1234.56).
- **Data:** a 1ª versão trocava `uf_date` por `uf_datetime_input` no `TransactionUtil::createExpense`. Foi
  **revertida**, porque mudava um resultado da Blade (`date_format = Y-m-d` passaria a gravar `:00` nos segundos).
  `TransactionUtil` ficou idêntico ao main. A tela nova manda ISO com `T`, que a Blade nunca envia, e só esse formato é
  convertido no `store`.
- **Rodapé:** soma só a página visível, igual à Blade. Trocar por total do filtro é a decisão D-DESP-TOTAL.

## Prova do índice
As provas da thread 13 no `00-INDICE.md` são `Inertia::render(` nos dois controllers. No `main` @a1b04cbacc o
`ExpenseController.php` já contém (1 ocorrência); o `ExpenseCategoryController.php` só passa a conter quando o #9344
entrar. Até lá o placar mostra a thread 13 como não entregue, e isso é o estado real.

## Pendente — US-MWART-019 e decisões [W] antes de ligar a flag
1. **Menu.** Desde 2026-05-20 (decisão [W]) o dropdown core "Despesas" fica escondido com o Financeiro habilitado, e
   a sub-navegação do Financeiro não tem aba de Despesas. Hoje não há caminho pelo menu até as telas novas.
2. **Pagamento na tela nova.** O drawer manda para a tela clássica: as rotas de pagamento só devolvem fragmento de
   modal da Blade, e escrever valor na tela nova é decisão [W].
3. **D-DESP-TOTAL.** Rodapé por página (como hoje) ou total do filtro (muda o número exibido).
4. **Excluir categoria com despesa lançada.** O protótipo recusa; o servidor de hoje exclui (lógico).
5. **Colunas "Lançamentos" e "Gasto no mês" nas categorias.** Não existem na Blade; o gasto é valor exibido novo.

E a US-019 em si: smoke em biz=1 com a flag ligada, aprovação visual [W] (F1.5) e os `.snap` do `visual-regression`.
Pela ADR 0411, criar snapshot é decisão [W]; o check acusa "tela sem contrato visual" e é advisory.

## Fora (não inventado)
- **Hipótese não testada:** o `parent_id` do `ExpenseCategoryController@store`/`update` não é conferido contra o
  negócio da sessão. Está no backlog do `CategoriasDespesa/Index.casos.md` e precisa de teste antes de virar achado.
- **Hipótese não testada:** `TransactionUtil::updateExpense` sem `final_total` no request cai no `catch` (`success=0`).
  A edição continua na Blade, que sempre manda o total.
- Documento anexo, recorrência e linha de pagamento na Nova despesa seguem na tela clássica.
- A tabela "Alvos exportados" do `governance/design/targets/README.md` não foi tocada (fora do prefixo).

## O que os testes ensinaram (consertos só de teste, nenhum de produção)
- Gravar `user.*` na sessão faz o `SetSessionData` pular a reconstrução: `currency` fica nula e o `num_f` do DataTable
  quebra com 500. Sessão de teste nasce de `session()->flush()` + `actingAs`.
- `permitted_locations()` lê só as permissões **diretas** do usuário: `location.N` não vem pelo papel.
- `withHeaders()` grava no TestCase e vaza para a próxima requisição do mesmo teste (o `X-Inertia` transformava o JSON
  do DataTable em resposta Inertia). Cabeçalho vai por chamada.
- `business.time_format` é `enum('12','24')`: gravar o inteiro 12 vira índice de enum inválido (vazio).

## Placar
entregue 5 de 6 PRs mergeados (o 6º, #9344, verde e armado) · US-MWART-014..018 feitas · US-MWART-019 pendente [W].

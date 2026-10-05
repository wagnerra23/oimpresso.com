---
sessao: "_saida-03"
thread: "03 · Cliente/Grupos — CustomerGroupController → Inertia"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main (rebase sobre origin/main de 2026-10-05)
---
# _saida-03

## Placar
**Entregue 1 de 1** — as 4 provas do json no branch: contrato `cliente-grupos.contract.json`, charter e casos de
`Cliente/Grupos/Index`, e `Inertia::render('Cliente/Grupos/Index'` no `CustomerGroupController`. O veredito dos
testes é da lane `cliente-pest.yml` no PR.

## Entregue
- `CustomerGroupController::index` responde Inertia `Cliente/Grupos/Index` (D2 = tela própria). Sem flag, por D1
  (aposentar o dual-render no Cliente). O ramo `request()->ajax()` da DataTable saiu: o Inertia manda
  `X-Requested-With` e esse ramo engoliria a visita.
- Props: `grupos` **deferida** (conta cadastros por grupo), `tabelas`, `pode` (create/update/delete).
- Tela `resources/js/Pages/Cliente/Grupos/Index.tsx`: lista com busca, diálogo de criar/editar, confirmação de
  excluir com o número de cadastros. Grava pelos endpoints de sempre (`store`, `update`, `destroy`), com o mesmo
  corpo que a Blade mandava.
- Tier 0: `store/update` passam a recusar `selling_price_group_id` de outro negócio (antes gravavam qualquer um).
- RUNBOOK `memory/requisitos/Cliente/RUNBOOK-grupos.md`, charter, casos UC-CGRP-01..05, contrato.

## Valor (regra mestre)
O percentual do grupo muda o preço de venda, então:
- **O caminho de gravação não mudou:** `store/update` seguem lendo `amount` com `num_uf`. A tela manda o texto
  pt-BR (`"10,50"`, `"-5,25"`), o mesmo formato do `input_number` da Blade, e pré-preenche a edição com 2 casas
  (a coluna é `double(5,2)`).
- **Dupla prova (UC-CGRP-03):** para `"10,50"`, `"-5,25"`, `"0,00"`, `"12"` e `"-10"`, o valor gravado pelo
  endpoint e o `num_uf` aplicado ao mesmo texto dão o mesmo número.
- **Antes → depois nos dados:** nenhum registro muda. Não há migration nem backfill; a mudança é só de tela.

## Divergência com o protótipo (decidida por [W] em 2026-10-05)
O protótipo chama o percentual de **"Desconto"**, mostra `−X%` e só aceita dígitos. O sistema aplica o valor
como **ajuste com sinal**: positivo aumenta e negativo diminui o preço (dica `lang_v1.tooltip_calculation_percentage`).
A tela usa "Ajuste (%)" e aceita sinal e decimal. Copiar o protótipo inverteria o sentido do preço. [W]
aprovou "Ajuste" em 2026-10-05.

Também ficaram de fora, no backlog do casos: "Ver cadastros do grupo" (a lista de clientes não lê o filtro da URL)
e o grupo padrão não-excluível (não existe no backend).

## Pendente / [W]
- Screenshot [W2] da tela em produção → charter `live` e remoção das Blades `resources/views/customer_group/*`.
- Sem baseline de visual-regression regravada (ADR 0409).

## NÃO MEDI
Pest local (regra: só CI/CT 100). A tela em produção só depois do deploy.

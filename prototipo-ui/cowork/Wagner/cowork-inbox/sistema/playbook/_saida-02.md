---
sessao: "02"
titulo: Funções e permissões (Role) → Inertia — saída
playbook: sistema
thread: "02"
dono: "[CL]"
data: "2026-10-08"
base: wagnerra23/oimpresso.com@main e2d884ed0c
---

# _saida-02 · Funções e permissões (Role) → Inertia

Feita pelo processo MWART (ADR 0104) em 3 PRs: F1+F2, F3 backend, F3 Page. **F4 (smoke biz=1 com a flag ligada)
e F5 (cutover) não foram feitas**, por serem decisão [W]. Com a flag `useV2SistemaFuncoes` desligada (o padrão),
produção segue na Blade `role/*`.

## 1 · Feito
- **F1 + F2** (#9058): `memory/requisitos/User/RUNBOOK-funcoes.md` + `funcoes-parity.md` + baseline Pest do
  controller de hoje (`tests/Feature/Roles/FuncoesBaselineTest.php`). Trava lista, cadastro, edição, exclusão e 403,
  sempre no par tenant 98 × 99 com a contraprova positiva no próprio negócio. CT 100: 8 passed (44 assertions).
  Mutante (tirar o filtro de `business_id` do `update()`) derruba o caso 98 × 99. Lane: run 37817080736.
- **F3-1a backend** (#9061): ramo `Inertia::render('Funcoes/Index')` atrás da flag no `index()`, com o ramo
  DataTable em `ajax() && ! inertia()`. Props `funcoes` (deferida: papéis só do negócio, nome sem `#negócio`, regra
  "padrão sem editar/excluir" igual à DataTable, nº de usuários pela pivot restrita aos ids do negócio) e `pode`.
  `FuncoesContratoTest` UC-FUNC-01..04. Mutante (tirar o filtro de negócio da lista) derruba UC-FUNC-03. Lane: run 37818110700.
- **F3-1b Page** (#9063): trio `resources/js/Pages/Funcoes/Index.{tsx,charter.md,casos.md}` no PT-01. Busca, tabela
  com marca de padrão e nº de usuários, exclusão confirmada. A recusa 422 "função em uso" agora aparece.
- `store`/`update`/`destroy` intocados. A lane `acessos-pest` já cobria `RoleController` e `tests/Feature/Roles/`.

## 2 · Não feito e por quê
- **Drawer de cadastrar/editar (F3-2) — pedido pela thread, NÃO entregue.** "Editar permissões" e "Nova função"
  abrem a Blade. O formulário tem 153 controles do núcleo (`PermissionCatalog::CORE`) mais os declarados por cada
  módulo em `user_permissions()`, com rótulos espalhados em chaves `__()` das views e radios de escopo. Para virar
  drawer precisa: (a) endpoint que sirva o catálogo agrupado com rótulo, (b) o editor, (c) prova de que o POST
  React chega idêntico ao da Blade (o `update()` faz `syncPermissions` destrutivo e só preserva o que o
  formulário não oferece). São vários PRs.
- **F4 / F5:** decisão [W].
- **Descrição e cor por função** do protótipo: sem fonte no banco.

## 3 · Achados para o índice (não editei o índice — é do Cowork)
1. **`RoleController::edit()` com id de outro negócio quebra em vez de 404.** `find()` devolve `null` e o `foreach`
   estoura. Não vaza o papel alheio (o baseline prova), mas responde erro. Mesmo desenho da thread 12; trocar por
   `findOrFail` pede PR próprio.
2. **Grupo de preço de outro negócio aceito como permissão — decisão [W].** `spg_permissions[]` só confere o padrão
   `selling_price_group.<id>`, não de quem é o grupo. O nome fica gravado no papel.
3. A recusa 422 "função em uso" do `destroy()` não aparecia na Blade (o jQuery não trata o `error`). A Page mostra.

## 4 · Provas
- `Inertia::render(` no `RoleController` (prova do índice): no `main` desde #9061.
- Pest no CT 100 numa cópia isolada (`/tmp/s02` no container, checkout compartilhado intocado), com mutante por defesa
  Tier 0. Lane `acessos-pest` conferida pelo nome do teste em cada PR pelo gerente da fila.

## 5 · Placar
entregue F1 + F2 + F3-1 (lista) · ausentes F3-2 (drawer de edição) por tamanho, F4/F5 por decisão [W] · achado 2 por decisão [W].

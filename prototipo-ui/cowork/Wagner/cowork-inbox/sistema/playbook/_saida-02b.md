---
sessao: "02b"
titulo: Funções e permissões — adendo da F3-2 (drawer de cadastrar/editar)
playbook: sistema
thread: "02"
dono: "[CL]"
data: "2026-10-13"
base: wagnerra23/oimpresso.com@main (pós #9170)
---

# _saida-02b · F3-2 entregue

O `_saida-02` (2026-10-08) registrou a F3-2 como **não entregue**. Ela entrou no
[#9170](https://github.com/wagnerra23/oimpresso.com/pull/9170), mergeado em 2026-10-13.

## Feito
- Na tela React `/roles` (flag `useV2SistemaFuncoes`, **desligada**), "Nova função" e "Editar permissões" abrem um
  drawer 760 (PT-02) em vez da Blade.
- O drawer pede a prop `editor` por partial reload. `App\Utils\FormularioDeFuncao` lê os controles da Blade
  `role/create|edit` do próprio negócio, então não existe um segundo catálogo de permissões escrito à mão.
- Salvar manda o mesmo corpo do formulário da Blade para o `store`/`update` de sempre. Os dois não mudaram.

## Provas
- `FuncoesContratoTest` UC-FUNC-01..08 PASS na lane `acessos-pest`, run 38391438227, no head do merge.
- UC-FUNC-06 (Tier 0): uma função de outro negócio devolve 404. O mutante sem o filtro `business_id` derruba o caso.
- UC-FUNC-07 (paridade): salvar sem mexer em nada não muda permissões.

## Segue pendente
- F4 (smoke em biz=1 com a flag ligada) e F5 (cutover): decisão [W].
- Achado 1 do `_saida-02`: o `edit()` da Blade devolve 500 para função de outro negócio. Pede PR próprio.
- Achado 2: grupo de preço de outro negócio em `spg_permissions[]` é decisão [W] (Tier 0 + valor).

## Placar
entregue F1 + F2 + F3-1 + F3-2 · ausentes F4/F5 por decisão [W].

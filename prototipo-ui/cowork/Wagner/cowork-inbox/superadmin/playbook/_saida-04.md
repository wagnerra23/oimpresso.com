---
sessao: "_saida-04"
thread: "04 · Assinaturas: add/edit/edit_date → drawer (Assinaturas/Index)"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main 5672d9bcb9
---
# _saida-04

## O que a medida mostrou antes de mexer
- **Mudar status** e **editar vigência** já são gavetas da lista React (`Assinaturas/Index.tsx`,
  `GavetaDeAcao`), gravando pelo `update()` e pelo `updateSubscription()` da SA-O4b. Os modais
  Blade `edit` e `edit_date_modal` não tinham mais quem os chamasse: o único chamador era a
  `index.blade.php`, que o controller não renderiza desde a SA-O4a.
- **Adicionar assinatura** (`add_subscription`) também não tinha entrada: abria por AJAX das listas
  Blade de Negócios e de Assinaturas, e as duas viraram Inertia. `git grep` por
  `superadmin-subscription/create` em `Modules/` e `resources/`: zero chamadores.
- No protótipo (`superadmin-page.jsx`), "Adicionar assinatura" mora no drawer do **negócio**
  (rodapé do `NegocioDrawer` e kebab da linha), não na lista de Assinaturas. O `AssinaturaForm`
  do protótipo só tem status e vigência.

## Entregue
- `SuperadminSubscriptionsController`: `create()`, `edit()` e `editSubscription()` deixaram de
  devolver Blade e levam à lista (`/superadmin/superadmin-subscription`). Sem o `ajax()` antigo:
  o cliente Inertia manda `X-Requested-With` em toda visita. `create()` ganhou a mesma guarda de
  superadmin dos outros dois (antes não tinha nenhuma).
- `store()` **não mudou**: é quem grava a assinatura nova e fica para a entrada em Negócios. O
  cálculo de valor (`_add_subscription`, preço do pacote) não foi tocado.
- Apagadas as 3 views: `add_subscription`, `edit` e `edit_date_modal`
  (`Resources/views/superadmin_subscription/`). Zero referência restante.
- UC-SAASS-18 em `Index.casos.md` e teste em `SuperadminAssinaturasContratoTest.php` (lane
  `verticais-pest.yml`): as três rotas levam à lista, pelo browser e numa visita Inertia, sem
  `modal-dialog` no corpo; o admin de negócio não chega à lista por elas.
- `memory/requisitos/Superadmin/SUPERFICIE.md` regerado.

## Por que não há drawer novo nesta thread
Não criei gaveta de "Adicionar assinatura" em Assinaturas: o protótipo põe a ação em Negócios, e
a tela de Assinaturas declara que gaveta é estado local, não rota (comentário em `Index.tsx`),
então um link `?nova=` vindo de outra tela iria contra isso. A tela de Negócios está no PR aberto
da thread 02 (#8714); mexer nela daqui colidiria.

## Prova do json, medida no branch
`SuperadminSubscriptionsController.php` não contém `view('superadmin::superadmin_subscription.edit')` — ✅

## Corte
As três rotas serviam Blade. Este PR fica **sem auto-merge** para o [W] mergear.

## Pendente
- **"Adicionar assinatura" no drawer do negócio** (`Negocios/Index.tsx`), com pacote, pago via e
  transação, gravando no `store()`. Fica para depois do #8714. Sugestão de validação a levar
  junto: `store()` hoje não valida nada (`package_id` nulo cai no `catch` genérico).
- `Resources/views/superadmin_subscription/index.blade.php` segue no disco sem ser renderizada.

## NÃO MEDI
Pest e PHPStan (CI/CT 100). O resultado da lane fica no PR.

## Placar
`04` — entregue 1 de 1 pela prova do json; a entrada de "Adicionar assinatura" vai para Negócios.
O `00-INDICE.md` não foi editado.

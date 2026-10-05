---
sessao: "_saida-11"
thread: "11 · Fim do topnav Blade + limpeza O8"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main cb1fe1d6f4
prefixo_tocado: "7 blades órfãs do Essentials (dashboard · leave · leave_type · sales_targets · sidebar_hrm) · SUPERFICIE.md do Essentials (derivado) · 1 comentário em HrmLicencaTest e 1 em HrmMetasTest"
entregue_em: "#8659 · #8662 · #8665 · #8675"
---
# _saida-11

## Pedido literal
`/onda HRM --thread 11`: remover o topnav Blade e as blades das telas que viraram Page. As provas do
índice são 3 arquivos ausentes: `nav_hrm`, `sidebar_hrm` e `hrm_dashboard`.

## Placar
**Entregue 2 de 3 provas.** `sidebar_hrm` e `hrm_dashboard` saíram. `nav_hrm` fica, pelo PARAR SE
da própria ficha (abaixo).

Das 14 views do prefixo, **7 saíram e 7 ficam**. As que ficam têm renderizador vivo.

## Feito, em 3 PRs (≤ ~300 linhas cada, só deleção)
| PR | Removido | Linhas | Renderizador hoje |
|---|---|---|---|
| [#8659](https://github.com/wagnerra23/oimpresso.com/pull/8659) | `dashboard/hrm_dashboard` | −301 | `hrmDashboard` → `Inertia::render('Essentials/Painel')` |
| [#8662](https://github.com/wagnerra23/oimpresso.com/pull/8662) | `leave/index` · `leave/change_status_modal` | −307 | `index` → `Essentials/Licencas/Index`; o modal só era incluído pela index |
| este | `leave_type/index` · `leave_type/create` · `sales_targets/index` · `layouts/partials/sidebar_hrm` | −267 | `Essentials/Tipos` · `Essentials/Metas`; `leave_type/create` só era incluída pela index; `sidebar_hrm` não tinha nenhuma referência |

Varredura no repo inteiro (`git grep`, fora `memory/` e `prototipo-ui/`) por `view('essentials::…')`,
`@include` e `@extends` de cada uma das 7: **0 renderizadores vivos em 7 de 7**. Os hits que sobram
são comentários históricos.

Em cada PR, `module-surface --all --check` passa (com `SUPERFICIE.md` regenerado) e
`blade-migration-census --ratchet` também.

## Ficam, pelo PARAR SE ("blade ainda referenciada por rota ativa")
| View | Quem renderiza |
|---|---|
| `layouts/nav_hrm` | `@include` em `attendance/index` · `payroll/{create,edit,index,pay_payroll_group,view_payroll_group,partials/user_payrolls}` · `holiday/index` · `settings/add`, e `DataController::addTaxonomies` (`'navbar'` de Departamentos/Cargos) |
| `leave/create` | `EssentialsLeaveController::create` (resource `hrm/leave`) |
| `leave/activity_modal` | `activity()` em `/hrm/leave/activity/{id}`, linkada por `Licencas/Index.tsx:818` |
| `leave/user_leave_summary` | `getUserLeaveSummary()` em `/hrm/user-leave-summary` |
| `leave_type/edit` | `EssentialsLeaveTypeController::edit`, linkada por `Tipos.tsx:204` |
| `sales_targets/sales_target_modal` | `SalesTargetController::setSalesTarget` em `/hrm/set-sales-target/{id}` |

`nav_hrm` só sai quando `attendance/*` e `payroll/*` (threads 09 e 10, `nao_toca` aqui) deixarem de
incluí-lo **e** Departamentos/Cargos tiverem `navbar` próprio. As outras 5 saem quando as Pages
absorverem o modal/rota correspondente e a rota morrer, o que é `Routes/web.php` (`nao_toca`).

## Não feito, e por quê
- **Chaves de lang (item 3 da ficha):** não removidas neste lote. Medi as 30 chaves `lang.*` usadas
  pelas 7 blades. **9** não aparecem em mais nenhum arquivo fora dos `lang/`:
  `add_leave_type`, `all_leave_types`, `all_leaves`, `allowance_and_deduction`, `my_leaves`,
  `my_sales_targets`, `target_achieved_last_month`, `target_achieved_this_month`, `todays_attendance`.
  A medida é por grep literal, então não vê chave montada dinamicamente. Saíram em
  [#8675](https://github.com/wagnerra23/oimpresso.com/pull/8675): 8 chaves, 82 linhas nos 16 idiomas,
  depois de conferir as chaves dinâmicas do módulo (`->type`, `->share_with`). `allowance_and_deduction`
  ficou fora porque não tem definição em lang nenhum.
- **Item 4 (`show()`/`edit()` → 500):** é `Routes/web.php`, `nao_toca`.
- **Testes `EssentialsBladeT1InertiaSmoke`:** não precisaram de ajuste. O arquivo não cita nenhuma
  das 7 views.
- **Nada rodado localmente.** A prova é a lane `essentials-pest.yml`, que roda `HrmLicencaTest` e
  `HrmMetasTest`. Nesses dois só mudou comentário.
- **Screenshot [W2]:** nenhuma tela renderizada muda, porque as rotas já serviam as Pages. Não
  regravei baseline visual (ADR 0409). Aprovação visual e contrato visual ficam com [W].

## Descobertas
1. `holiday/index.blade.php` e `settings/add.blade.php` ainda incluem `nav_hrm`, mas `/hrm/holiday`
   já é Inertia (`EssentialsBladeT1InertiaSmokeTest`, Wave D). `holiday/index` deve estar órfã. Está
   fora do prefixo desta thread, então só registro.
2. Os ramos DataTables (`X-Requested-With`) de `/hrm/leave` e `/hrm/sales-target` perderam a Blade
   que os consumia. Os testes `UC-HRM-34` e `UC-METAS-07` continuam travando o JSON, e os comentários
   deles passaram para o passado. Aposentar o ramo é decisão do controller, não desta limpeza.

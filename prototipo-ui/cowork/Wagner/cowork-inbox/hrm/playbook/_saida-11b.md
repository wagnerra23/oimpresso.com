---
sessao: "_saida-11b"
thread: "11 · Fim do topnav Blade + limpeza O8"
dono: "[CL]"
data: 2026-10-13
base_lida: wagnerra23/oimpresso.com@main 94c005aec5
prefixo_tocado: "nenhum — só este recibo"
entregue_em: "nenhum PR de código; parada pelo PARAR SE"
---
# _saida-11b

Complementa o [`_saida-11`](_saida-11.md) de 2026-10-05. Aquele recibo não foi editado.

## Pedido literal
Remover `Modules/Essentials/Resources/views/layouts/nav_hrm.blade.php`, a prova que faltava no
índice (`{ "tipo": "ausente", "path": ".../nav_hrm.blade.php" }`).

## Placar
**Parado. Nada removido.** O `nav_hrm` ainda tem consumidores com rota viva, e a ficha manda parar
nesse caso ("Qualquer blade ainda referenciada por rota ativa → não remover"). Continua 2 de 3 provas.

## Consumidores: 10 arquivos de código de 10
`git grep -l nav_hrm` no repo inteiro dá 43 arquivos. 31 são doc, protótipo e notas (`memory/`,
`prototipo-ui/`, `*.md`). Um é comentário em `e2e/essentials-tipos.spec.ts:14` e outro é nota em
`governance/design/contracts/essentials-licencas.contract.json:5`. Os 10 restantes chamam a view:

### Vivos (7 arquivos)
| Consumidor | Linha | Por que está vivo |
|---|---|---|
| `Http/Controllers/DataController.php` | `:447` (`hrm_department`) · `:457` (`hrm_designation`) | `'navbar' => 'essentials::layouts.nav_hrm'`. Quem lê é `resources/views/taxonomy/index.blade.php:10` (`@include($navbar)`), servida por `Route::resource('taxonomies')` em `routes/web.php:726`. Departamentos e Cargos renderizam o topnav. |
| `views/payroll/create.blade.php` | `:20` | `PayrollController.php:345` e `:935` |
| `views/payroll/edit.blade.php` | `:6` | `PayrollController.php:587` |
| `views/payroll/index.blade.php` | `:5` | `PayrollController.php:223` |
| `views/payroll/view_payroll_group.blade.php` | `:4` | `PayrollController.php:862` |
| `views/payroll/pay_payroll_group.blade.php` | `:4` | `PayrollController.php:1058` |
| `views/payroll/partials/user_payrolls.blade.php` | `:4` | `PayrollController.php:1216` |

As rotas da Folha estão ativas em `Modules/Essentials/Routes/web.php:85-93`: `Route::resource('/payroll')`,
`/payroll-group-datatable`, `/view/{id}/payroll-group`, `/edit/{id}/payroll-group`,
`/payroll-group/{id}/add-payment` e `/my-payrolls`.

### Parecem órfãos (3 arquivos), NÃO medidos em runtime
| Consumidor | Linha | Indício pela leitura das rotas |
|---|---|---|
| `views/attendance/index.blade.php` | `:5` | `/hrm/attendance` é `permanentRedirect` para `/ponto/espelho` (`web.php:75-76`), mas `AttendanceController.php:176` ainda tem o `view(...)`. Está no `nao_toca` desta thread. |
| `views/holiday/index.blade.php` | `:5` | `EssentialsHolidayController.php:65` devolve `Inertia::render('Essentials/Holidays/Index')`. Nenhum `view('essentials::holiday.…')` no código. |
| `views/settings/add.blade.php` | `:5` | `EssentialsSettingsController.php:47` devolve `Inertia::render('Essentials/Settings/Index')`. Nenhum `view('essentials::settings.add')` no código. |

Remover esses 3 não tira o `nav_hrm`, porque os 7 vivos continuam. Ficam para quem for dono do prefixo deles.

## Bloqueios
1. **Folha sem Page.** [W] decidiu em [`_DECISOES-W-2026-10-13`](_DECISOES-W-2026-10-13.md) que a
   thread 10 segue sem Page até existirem o modelo de verba e o motor. Enquanto isso, as 6 blades de
   payroll são a Folha em produção e incluem o topnav.
2. **Taxonomias sem navbar próprio.** Departamentos e Cargos usam o `nav_hrm` como navbar. Trocar
   isso sozinho não tira o arquivo (o bloqueio 1 continua), então nenhum PR foi aberto.

## Pedido ao Cowork
No `00-INDICE.md`, a prova `{ "tipo": "ausente", "path": "Modules/Essentials/Resources/views/layouts/nav_hrm.blade.php" }`
da thread 11 deve passar a constar como **"bloqueada até a Folha ter Page"** (dependência da thread 10),
em vez de pendente. As outras 2 provas da thread (`sidebar_hrm` e `hrm_dashboard`) já estão cumpridas.

## Não feito
- Nenhum código, teste ou lang tocado. Nada rodado localmente.
- Medição feita por leitura de `git grep` e das rotas em `origin/main` 94c005aec5, sem a aplicação rodando.

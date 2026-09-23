---
sessao: "02"
titulo: casos.md com UC · Essentials Todo + Reminders
executor: "[C]"
base: 317e1b4ec33
---
# _saida 02

## Checklist
1. ✅ Lido `scripts/lib/uc-regex.mjs` — heading `## UC-ETODO-NN` / `## UC-EREM-NN` casa `UC-(?:[A-Z][A-Z0-9]{0,5})?-?\d{1,3}` (prefixos de 5 e 4 letras). Ids varridos no repo antes: sem colisão.
2. ✅ Molde de casos: `Cliente/Index.casos.md` + `Essentials/Tipos.casos.md`. Molde de teste: `Modules/Essentials/Tests/Feature/HrmTiposIndexTest.php` (tenant 98 × 99, headers Inertia, `DatabaseTransactions`).
3. ✅ UC derivados do charter + Controller real (`ToDoController@index/@update`, `ReminderController@index/@store`), nenhum do protótipo. Charter × `.tsx` conferidos: sem contradição (Todo: filtros com `only:['todos','filtros']` e `only_status`; Reminders: `role="grid"` como a emenda 2026-09-09 do charter diz).
4. ✅ Cada UC-id no título de um `it()` (casos-gate G-2) — 0 órfãos meus no `--report`.
5. ✅ `php -l` nos 2 testes (php 8.3 do Laragon): sem erro de sintaxe. Pest **não** rodado (CT 100 only).
6. ⚠️ **PARAR SE acionado: >300 linhas** (467). Divisão natural, 1 PR por tela — ver abaixo.
7. ⚠️ Nenhuma lane de PR executa os testes novos — ver abaixo.

## Arquivos

| tela | arquivo | linhas |
|---|---|---|
| Todo | `resources/js/Pages/Essentials/Todo/Index.casos.md` | 79 |
| Todo | `tests/Feature/Essentials/TodoIndexContratoTest.php` | 165 |
| Reminders | `resources/js/Pages/Essentials/Reminders/Index.casos.md` | 72 |
| Reminders | `tests/Feature/Essentials/RemindersIndexContratoTest.php` | 151 |

Divisão proposta: **PR-A Todo (244 linhas)** · **PR-B Reminders (223 linhas)** + este `_saida`. Os pares não se referenciam.

UC-ids: `UC-ETODO-01` (lista abre com a tarefa) · `02` [T0] outro business não aparece · `03` filtro por status · `04` troca de status grava · `05` [T0] troca em tarefa alheia = 404 e nada muda.
`UC-EREM-01` (tela abre com o lembrete + 4 opções de repetição) · `02` [T0] outro business não aparece · `03` lembrete de colega do mesmo business não aparece · `04` criar grava no meu business/usuário.

## Lane de CI

`node scripts/governance/test-lane-coverage.mjs`: nenhuma das 33 lanes lê `tests/Feature/Essentials/` (grep em `.github/workflows/*.yml` e `.github/*.list`: 0 ocorrências). A lane dona do tema, `Essentials · Pest (MySQL)` (`.github/workflows/essentials-pest.yml`), passa **arquivos explícitos** e hoje não inclui estes dois. O prefixo da thread é `tests/`, e `.github/` está fora dele — **não mexi**. Para os testes rodarem no PR, alguém com escopo em `.github` acrescenta as 2 linhas na allowlist da lane (e `tests/Feature/Essentials/**` no `paths`/`paths-filter`). Até lá rodam só na nightly do CT 100. Por isso todo UC nasce `⬜ sem veredito`.

## Saída dos comandos

`node scripts/casos-coverage-guard.mjs`:
```
casos:check · 72 violações (telas: 220, casos.md: 160)
✅ Sem violações novas DESTE PR (débito caiu −11 vs baseline).
```
(`--report`: UCs órfãos 11, nenhum `ETODO`/`EREM`.)

`node scripts/qa/prototipo-readiness.mjs` (trecho):
```
  ✅ PRONTAS pra aplicar HOJE (trio + casos+UC + scorecard trava o comportamento): 61
       [core] Essentials/Reminders/Index
       [core] Essentials/Todo/Index
  🟡 PRECISAM DE 1 CICLO de blindagem antes (o metabolismo MV faz): 33
  Total de telas com protótipo real: 94
```
As duas telas saíram de 1-ciclo (35 → 33 no mesmo total 94).

## O que ficou de fora
- [BACKLOG] Todo: filtro não-admin "só próprias/atribuídas" e remover com gating `can.*` — sem fixture de usuário não-admin reutilizável.
- [BACKLOG] Reminders: editar/excluir e validação dos campos obrigatórios.
- Grade do mês, teclado e recorrência expandida (Reminders) são front — pedem teste de componente/e2e.
- Premissa não medida: o gate `essentials_module` do Controller passa no CI porque o módulo Superadmin não está "instalado" lá (mesma premissa do `HrmLicencaTest` que já está verde na lane). Se falhar com 403, é ambiente, não o contrato.

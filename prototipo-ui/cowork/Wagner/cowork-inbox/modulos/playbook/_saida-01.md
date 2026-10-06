---
sessao: "_saida-01"
thread: "01 · P1+P2: install falho volta a inativo e \"Com erro\" acende (com prova)"
dono: "[CL]"
data: 2026-10-06
tipo: recibo + desvio de prova
base_lida: wagnerra23/oimpresso.com@main bab78f4764
---
# _saida-01

## O que esta sessão fez
Colocou o teste que já prova o P1 e o P2 numa lane de PR. Não reescreveu o Service e não criou o arquivo de fixture pedido pela thread, pelos motivos abaixo.

- `.github/ci-sqlite-pest.list` + `tests/Feature/Modules/ModuleManagerServiceTest.php`. A lista é lida pelo job `PHP / Pest (Unit)` do `ci.yml`, que roda em todo PR.
- Antes desta linha, o `test-lane-coverage.mjs --json` listava esse arquivo como **fora do PR**. Ele rodava só no nightly do CT 100.

## Já estava no main antes deste índice (medido em bab78f4764)
| item | onde | desde |
|---|---|---|
| P1: install que falha não deixa o módulo ativo | `ModuleManagerService::install()`, `catch` → `setActive($name, $estadoAnterior)` | antes de 06/10 |
| P2: `module.json` malformado, sem `providers[]` ou ausente preenche `error` | `ModuleManagerService::list()` | antes de 06/10 |
| teste de P1 | `ModuleManagerServiceTest.php`, os dois `UC-MOD-13` (instalar **e** reinstalar) | antes de 06/10 |
| teste de P2 | o mesmo arquivo, os três `UC-MOD-20` + o controle "com providers fica `null`" | antes de 06/10 |

O P1 que entrou é **melhor** que o patch do `../PATCHES.md`. O patch volta o módulo para `false`; o código volta ao estado **anterior**. Com `false`, um "Reinstalar" que falhasse derrubaria um módulo que estava funcionando. O teste `UC-MOD-13 - reinstalar que falha preserva o modulo ATIVO` trava isso.

## As duas provas do índice não medem o que dizem
1. **`contem setActive($name, false)`**: o padrão casa em `uninstall()`, não no `catch` de `install()`. O `catch` chama `setActive($name, $estadoAnterior)`. `git log -S` aponta `d71000e9a7`, anterior ao índice. Pela regra da própria thread, não escrevo recibo por esta prova.
2. **`arquivo tests/Unit/Services/ModuleErroFixtureTest.php`**: criar o arquivo duplicaria os três `UC-MOD-20`. Além disso, ele quebraria nesse caminho. O docblock do `ModuleManagerServiceTest.php` explica: `tests/Pest.php` só liga `Tests\TestCase` em `Feature/`, `Browser/` e KB, e o teste usa `storage_path()` e a facade `File`. O rascunho em `../repo/` também escreve em `Modules/` real, coisa que o Service atual dispensa: o construtor aceita os paths do sandbox.

O placar segue mostrando 01 como `proximo` enquanto a prova (2) apontar para esse arquivo. Trocar a prova por "`ModuleManagerServiceTest.php` em lane de PR" é edição do `00-INDICE.md`, que é do [CC].

## Prova desta sessão
`node scripts/governance/test-lane-coverage.mjs --pr <N>` no PR desta thread. O número e o resultado do run ficam no PR.

## Fora do escopo, registrado
- `tests/Feature/Modules/ModuleManagementTest.php` (o Controller da tela) também está fora do PR. Não entrou aqui: ele precisa de usuário e permissão no banco, e não medi se é seguro no sqlite.
- Os dois `UC-MOD-17` que leem a tabela `system` dão `markTestSkipped` se ela não existir na base sqlite. São skip, não verde.

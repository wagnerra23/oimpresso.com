---
sessao: "01"
titulo: P1+P2: install falho volta a inativo e "Com erro" acende
dono: "[CL]"
base: 2fe69ddc0280
---
# 01 · P1+P2: install falho volta a inativo e "Com erro" acende

Lido no turno @2fe69ddc0280: `app/Services/ModuleManagerService.php` (16.215 B) existe; `tests/Unit/Services/ModuleErroFixtureTest.php` **não existe**.

1. Reler o `catch` de `install()`. Se ainda não chama `setActive($name, false)`, aplicar o P1 de `../PATCHES.md`. Se já chama, registrar no recibo e seguir.
2. Aplicar o P2 (`module.json` malformado / sem `providers[]` / ausente preenche `error`).
3. Criar `ModuleErroFixtureTest.php` com fixture `Modules/__ErrFixture__` (padrão `DetectDriftCommandTest`). Ponto de partida: `../repo/tests/Unit/Services/ModuleErroFixtureTest.php` — conferir contra o Service atual antes de copiar.

P4 (versão) **não entra** aqui: depende de D1.

> ⚠️ A prova `contem setActive($name, false)` só vale se o padrão **não** existia antes deste índice (06/10). Se `git log -S` mostrar commit anterior, não escreva recibo pela prova — reporte no `_saida` (regra do `/onda`).

## Prova
No JSON do `00-INDICE.md` — o placar confere. Recibo: `_saida-01.md`, de quem executar.

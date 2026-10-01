---
sessao: "04"
titulo: Simular retenção
dono: "[CL]"
base: 2fc50fa6dcb8
---
# 04 · PR-8 dry-run

`POST arquivos/retencao/simular` → `RetentionRunRequest`; **`dry_run` forçado `true` no controller**, `purge` recusado no controller. `run()` + `report()` em job. Permissão `arquivos.governanca`. Não escreve em `arquivos` nem em `arquivos_audit_log`.

Portão: canário — trocar `dry_run` pra `false` no controller reprova o teste.

## Prova
No JSON do índice.

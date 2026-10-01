---
sessao: "03"
titulo: Excluir e restaurar
dono: "[CL]"
base: 2fc50fa6dcb8
---
# 03 · PR-7

`DeleteArquivoRequest` → `softDelete()`; `RestoreArquivoRequest` → `restore()` (:211). Restaurar só aparece **dentro do grace** (30d) — fora dele o botão não existe (não é só desabilitado). Teste: trilha preservada.

## Prova
No JSON do índice.

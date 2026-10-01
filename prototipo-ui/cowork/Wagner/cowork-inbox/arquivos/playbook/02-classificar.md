---
sessao: "02"
titulo: Classificar
dono: "[CL]"
base: 2fc50fa6dcb8
---
# 02 · PR-6 classificar

`POST arquivos/{arquivo}/classificar` → `ReclassifyArquivoRequest` → `ArquivosService::classify()` (:159). Grava `classified_by/at` + audit `classify` com `motivo` (min 5). Drawer PT-02.

## Prova
No JSON do índice.

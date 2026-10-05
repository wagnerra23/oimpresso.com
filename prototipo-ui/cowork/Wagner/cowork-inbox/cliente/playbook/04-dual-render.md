---
sessao: "04"
titulo: Aposentar dual-render
dono: "[CL]"
base: ca44a3d54cd2
---
# 04 · /contacts → /cliente

`/cliente` (web.php:652, canary) e `Route::resource('contacts')` (:639) coexistem; a flag `mwart.cliente_index.enabled` escolhe. Só abre com D1.

Redirecionar `/contacts` para `/cliente` mantendo `?type=supplier` até D3.

## Prova
Recibo `_saida-04.md` com o caminho real da config.

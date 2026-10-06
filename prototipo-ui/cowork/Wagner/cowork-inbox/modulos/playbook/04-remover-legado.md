---
sessao: "04"
titulo: PR-8: remover o legado /manage-modules
dono: "[CL]"
base: 2fe69ddc0280
---
# 04 · PR-8: remover o legado /manage-modules

Detalhe e portão em `../PR-8-REMOVER-LEGADO.md`. **Não abre antes de:** 01 `feito` no placar (dependência declarada), lane verde nos testes de módulo e smoke 1280/1440 aprovado por [W2]. O legado é a rota de fuga enquanto a tela nova não tem prova.

Lido no turno: `BaseModuleInstallController.php:14,118` ainda redireciona para `/manage-modules` — entra no escopo (senão o install de módulo cai em 404 depois do PR). Os `routes/web.php` / `AdminSidebarMenu.php` de `../repo/` são de 2026-08-19 (`routes/web.php` lá tem 64.049 B, o do `main` 85.779 B): **refazer o patch no arquivo atual**, nunca copiar.

Lido no turno: `routes/web.php:1124-1125` (destroy + resource `manage-modules`) e `:1123` `upload-module` — este **fica**. View a apagar: `resources/views/install/modules/index.blade.php` (10.041 B). A 02 (fila) **não** é dependência: se D4 = não, ela nunca fecha e travaria esta.

## Prova
No JSON do `00-INDICE.md` — o placar confere. Recibo: `_saida-04.md`, de quem executar.

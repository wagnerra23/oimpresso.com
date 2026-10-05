---
sessao: "03"
titulo: repair.create/edit
dono: "[CL]"
base: ca44a3d54cd2
---
# 03 · Sair do Blade no cadastro de reparo

`RepairController:728` (`repair::repair.create`) e `:1125` (`repair::repair.edit`) não têm `render`. D1 decide: Page própria ou redirecionar pra `JobSheet/Create`/`Edit` (já em Inertia).

## Prova
No JSON do índice.

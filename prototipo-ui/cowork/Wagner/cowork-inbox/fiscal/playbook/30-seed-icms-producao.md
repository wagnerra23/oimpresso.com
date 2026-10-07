---
sessao: "30"
titulo: Seed ICMS interestadual em produção
dono: "[CL]"
base: 50e23057f1c2
---
# 30 · Rodar o seed

[W] 07/10: rodar agora. `php artisan db:seed --class="Modules\NfeBrasil\Database\Seeders\NfeIcmsUfSeeder"` (idempotente, só a Res. Senado 22/1989). Antes, conferir que a migração `2026_10_07_000003` já rodou no deploy. Recibo: contagem depois — 146 a 7%, 556 a 12%, 27 internos vazios.

# Feedback — Lote 5 (Grow / BI / Dashboard / Essentials)

Data: 2026-04-26
Branch: `claude/tests-batch-5-grow-bi-dash` (criado a partir de `main`,
porque `6.7-bootstrap` não existe neste repositório).

## Discrepâncias entre o briefing e a realidade do repo
| Esperado pelo briefing                      | Encontrado                                |
| ------------------------------------------- | ----------------------------------------- |
| Stack moderna (Pest, Inertia, Laravel 11+)  | Laravel 5.8 + Blade + PHPUnit 9.5         |
| Branch `6.7-bootstrap`                       | Inexistente — usado `main`                 |
| `Modules/Grow`                               | Inexistente — placeholder + SPEC TODO     |
| `Modules/Essentials/Tests/Feature/EssentialsTestCase.php` (modelo) | Apenas `.gitkeep`             |
| `memory/`, `decisions/`, CLAUDE.md          | Inexistentes — criados neste lote          |
| Controllers retornam Inertia                 | Retornam `view('xxx::index')` (Blade)     |

## Bloqueios encontrados
1. **Composer install falhou**: `composer.lock` está dessincronizado de
   `composer.json` (faltando `doctrine/dbal`, `lcobucci/jwt 3.3.3`
   conflita com 3.4.2 do lock).
   - Tentativas: `composer install --optimize-autoloader`,
     `--ignore-platform-reqs --no-scripts` — ambas abortaram com
     "lock file not up to date".
   - **Não foi feita** correção (composer update mexeria em dependências
     fora do escopo do lote). Documentado em SPEC + TODO global.
2. **Sem vendor/** disponível → `vendor/bin/phpunit` não pode ser
   executado nesta sessão. Os testes foram escritos defensivamente
   (com `skipIfAppNotBooted()`) para que skipem cleanly se a app não
   subir.

## O que foi entregue
- Base TestCase por módulo (BI, Dashboard, Essentials).
- Feature tests cobrindo auth/redirect-to-login dos controllers públicos
  principais (ver SPEC.md de cada módulo).
- SPEC.md em `memory/requisitos/<Modulo>/` para BI, Dashboard,
  Essentials e Grow.

## Próximos passos sugeridos (TODO global)
1. Resolver o lock file: `composer update doctrine/dbal lcobucci/jwt`
   ou rebaselinar.
2. Rodar `vendor/bin/phpunit --testsuite Feature --filter Essentials` e
   confirmar que todos os testes passam (ou skipam) na CI.
3. Reproduzir o lote para os módulos
   `Crm`/`Manufacturing`/`Repair`/`Project` que ainda não têm cobertura
   formal.
4. Decidir o ACL do `/dashboard` (ver Dashboard/SPEC.md) e endurecer o
   teste atual `index_responde_com_status_http_valido`.

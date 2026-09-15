# PR-8 (era PR-7) — remover o caminho `/manage-modules` — **ÚLTIMO PR**

> [CC] 2026-08-19, reordenado por [W] no mesmo dia. Faz parte do pacote `cowork-inbox/modulos/`.
> Remoção é irreversível: enquanto o Blade existe, ele é a **rota de fuga** se a tela nova falhar em
> produção. Apagar antes da prova troca um caminho feio por nenhum caminho.

## Portão de entrada (nada disso é opcional)

- [ ] PR-2 a PR-6 mergeados
- [ ] lane Pest **verde** em `ModuleManagementTest` + `ModuleManagerServiceTest` + `ModuleErroFixtureTest`
- [ ] `npm run screen:files -- Modules/Index` → trio ✅ e `contrato:check` verde
- [ ] smoke visual 1280/1440 aprovado por [W2]
- [ ] `/modulos` exercitada em produção com as 3 ações (toggle · install · uninstall) ao menos 1× sem incidente

## O que está no `repo/` já patchado

| Arquivo | Mudança |
|---|---|
| `routes/web.php` | tira as 3 rotas de `manage-modules` (`resource index/update`, `destroy/{module_name}`) e **mantém** `upload-module` + `regenerate` |
| `app/Http/Middleware/AdminSidebarMenu.php` | item de menu passa a apontar `route('modules.index')` e o `active` casa o segmento `modulos` (antes o item nunca acendia quando o operador chegava por `/modulos`) |

## O que apagar (não dá para representar como arquivo)

- `resources/views/install/modules/index.blade.php` — a view AdminLTE substituída
- `app/Http/Controllers/Install/ModulesController.php` — **apagar só os métodos órfãos** após as rotas saírem: `index`, `update`, `destroy`, `create`, `store`, `show`, `edit`, `__available_modules`.
  **FICAM:** `uploadModule` (instalar módulo por `.zip` — `/modulos` não cobre upload de código, é Non-Goal do charter) e `regenerate`.

## O que NÃO mexer

- `app/Services/LegacyMenuAdapter.php:287` (`'/manage-modules' => '/modulos'`) — é o que impede link antigo de quebrar. **Manter.**
- `Modules/*/Http/Controllers/InstallController.php` — nada a ver com a tela; é a convenção de install por módulo que `/modulos` dispara.
- Permissão `manage_modules` (`AuthServiceProvider.php:36`) — segue sendo a chave; o P5 justamente unifica `/modulos` nela.

## Documentação que ainda manda o operador na tela morta

- `.claude/skills/cockpit-runbook/TEMPLATE.md:42`
- `.claude/skills/criar-modulo/SKILL.md:26,46,74,151,252`
- `.claude/skills/migrar-modulo/SKILL.md:144,226`
- `.claude/skills/sidebar-menu-arch/SKILL.md:222`

Trocar `/manage-modules` por `/modulos`, preservando a menção histórica onde o texto explica a
substituição. Este item é o PR-1 (barato, sem risco) — pode ir antes de tudo.

## Smoke pós-merge

- [ ] `GET /manage-modules` ⇒ 404 (rota removida) e o link do menu antigo cai em `/modulos` pelo adapter
- [ ] item "Módulos" da sidebar **acende** quando se está em `/modulos`
- [ ] upload de módulo por `.zip` continua funcionando (`POST /upload-module`)
- [ ] `php artisan route:list | grep -i modul` sem rota apontando para `Install\ModulesController@index`
- [ ] nenhuma skill/runbook citando `/manage-modules` como caminho vivo

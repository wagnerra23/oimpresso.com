# Modules/Grow — Especificação

> **Origem:** CodeCanyon item 32094844 (Perfect Support / Ticketing / Document Management System), instalado como módulo nWidart no UltimatePOS 6.7.
> **Decisão Wagner (2026-04-22):** usar para a parte de produção do Office Impresso. Avaliar viabilidade vs reescrita em React. Ver `memory/claude/preference_modulos_prioridade.md`.

---

## 1. Estado atual

- **Tamanho:** ~800 rotas em `Modules/Grow/Routes/web.php` — a maioria comentada (legacy), apenas o subset `/grow/test`, `/grow`, `/grow/install*` está ativo.
- **Stack:** controllers legados PHP/Blade (NextLoop), com mistura de helpers próprios. Assets em `Resources/views/`.
- **Integração com UltimatePOS:** mediada por `App\Utils\ModuleUtil` (mesmo padrão dos demais módulos do POS). `business_id` provido por session.
- **InstallController:** já segue o padrão `BaseModuleInstallController` (ADR 0024) — instalação 1-clique, sem License Code.

## 2. Rotas públicas estáveis

Todas sob middleware `web, SetSessionData, auth, language, timezone, AdminSidebarMenu` com prefixo `/grow`.

| Método | URL                       | Controller                                        | Notas |
|--------|---------------------------|---------------------------------------------------|-------|
| GET    | `/grow/install`           | `InstallController@index`                         | Install 1-click (superadmin) |
| POST   | `/grow/install`           | `InstallController@install`                       | Compat — index() já cobre |
| GET    | `/grow/install/uninstall` | `InstallController@uninstall`                     | Uninstall via Base |
| GET    | `/grow/install/update`    | `InstallController@update`                        | Update via Base |
| GET    | `/grow/test`              | `Test@index`                                      | DEV-only smoke |
| POST   | `/grow/test`              | `Test@index`                                      | DEV-only smoke |
| GET    | `/grow`                   | `ControllersController@index` (placeholder)       | Aguarda implementação real |

> O resto das ~800 rotas (clientes, projetos, faturas, etc.) está **comentado** e fora do escopo até a decisão de migrar/reescrever.

## 3. Cobertura de testes

`Modules/Grow/Tests/Feature/`:

- `GrowTestCase.php` — base com `actAsAdmin()` + `assertRedirectsToLogin()`.
- `InstallControllerTest.php` — autenticação obrigatória + reflexão garantindo que estende `BaseModuleInstallController` (ADR 0024).
- `GrowRoutesTest.php` — sanidade do prefixo, autenticação obrigatória, sem vazamento de dados sem login.

## 4. TODO

- [ ] Decidir manter vs reescrever (Wagner pediu avaliação ROI; views legadas Blade não combinam com Inertia React do UltimatePOS 6.7).
- [ ] Reativar (ou deletar) o bloco legado de rotas comentadas em `Routes/web.php`.
- [ ] Implementar `ControllersController` real ou redirecionar `/grow` para tela canônica do POS.
- [ ] Quando migrado para Inertia, expandir os testes para cobrir props + scope de `business_id`.

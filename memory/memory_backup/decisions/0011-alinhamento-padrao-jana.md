# ADR 0011 — Alinhamento com o padrão Jana (UltimatePOS)

**Status:** ✅ Aceita
**Data:** 2026-04-18 (sessão 02)
**Relaciona-se com:** substitui parcialmente ADR 0002; reforça ADR 0001

## Contexto

Na sessão 01 criei o scaffolding do `Modules/PontoWr2/` assumindo a estrutura **moderna** do nWidart/laravel-modules (versões 10+): pasta `Routes/` com `web.php` + `api.php`, `RouteServiceProvider` dedicado, helper `module_path($mod, 'subpath')` com 2 argumentos, middlewares genéricos Laravel.

Quando a Eliana subiu o módulo para produção (`oimpresso.com`, hospedagem Hostinger compartilhada), o Laravel entrou em loop de erro com:

```
require(/home/.../public_html/Modules/PontoWr2): failed to open stream: No such file or directory
  em Modules/PontoWr2/Providers/RouteServiceProvider.php linha 49
```

Investigação mostrou que o UltimatePOS usa uma **versão mais antiga** do nWidart/laravel-modules, onde `module_path()` não aceita segundo argumento. O `module_path('PontoWr2', '/Routes/api.php')` retornava só o path base e o `Route::group(...)` tentava dar `require` no **diretório** — falha imediata.

Abrindo o módulo vizinho **Jana** (`Modules/Jana/`), em produção e funcionando, vi que o padrão real do UltimatePOS é significativamente diferente:

| Item | Jana (padrão UltimatePOS, funciona) | Meu scaffold original (quebrado) |
|---|---|---|
| Carregamento de rotas | `start.php` na raiz + `Http/routes.php` | `RouteServiceProvider` + `Routes/web.php` + `Routes/api.php` |
| Nº de ServiceProviders | 1 | 2 |
| `module.json` campos | `files: ["start.php"]`, sem `priority`/`version` | sem `files`, com `priority`/`order`/`version` |
| Estilo de rota | `Route::group([...'namespace' => 'Modules\Jana\Http\Controllers'...])` com strings de controller | `Route::get(..., [Controller::class, 'method'])` |
| API auth | `auth:api` (Passport) | `auth:sanctum` |
| Middleware stack web | `['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']` | `['web', 'auth', 'language']` |
| Lang folder | `Resources/lang/pt/` (código curto) | `Resources/lang/pt-BR/` |

## Decisão

**Adotar o padrão Jana como referência canônica** para o `Modules/PontoWr2/`. Especificamente:

1. Remover `Providers/RouteServiceProvider.php` (arquivo neutralizado, aguardando remoção física)
2. Remover pasta `Routes/` (removida)
3. Criar `start.php` na raiz do módulo
4. Criar `Http/routes.php` com três `Route::group`: web, API, install
5. Ajustar `PontoWr2ServiceProvider` para espelhar `JanaServiceProvider` (mesma assinatura `boot(Router $router)`, mesmo array `$middleware`, mesmos métodos `registerTranslations/Config/Views/Factories/Middleware`)
6. Ajustar `module.json` para conter `"files": ["start.php"]` e remover `priority`/`order extra`/`version`
7. Migrar traduções de `Resources/lang/pt-BR/` para `Resources/lang/pt/` (pasta `pt-BR/` mantida como legado que delega via `require`)
8. Usar middleware stack do UltimatePOS nas rotas web
9. Usar `auth:api` (Passport) nas rotas API — Sanctum não está instalado no UltimatePOS
10. Assinatura de rota estilo string (`'Controller@method'`) para casar com `namespace` do `Route::group`

## Consequências

### Positivas

- Módulo para de quebrar o boot do Laravel
- Desenvolvedores que já conhecem o Jana/Essentials/Connector/Superadmin entendem a nossa estrutura instantaneamente
- Menos código (1 ServiceProvider em vez de 2, sem abstrações `module_path` complexas)
- Middleware stack igual ao resto do sistema significa que o UltimatePOS aplica os mesmos tratamentos (sessão, sidebar, timezone, check de login)

### Negativas

- Perdemos algumas conveniências do Laravel "moderno" (ex.: `[Controller::class, 'method']` em vez de string)
- Rotas em um arquivo só podem crescer demais no futuro. Mitigação: particionar por `Route::group` aninhado se necessário
- `auth:api` (Passport) é mais pesado que Sanctum. Não temos escolha — é o padrão

### Lição aprendida (ESSENCIAL — documentar no CLAUDE.md e preferences)

**Quando estender UltimatePOS, SEMPRE olhe para `Modules/Jana/` (ou outro módulo em produção) primeiro e imite exatamente o padrão.** O UltimatePOS congelou convenções em uma versão específica do nWidart/laravel-modules. Trazer convenções Laravel modernas (Route::closure arrays, module_path com 2 args, Sanctum, pt-BR) vai quebrar em produção.

## Referências

- Log do crash: ver `memory/sessions/2026-04-18-session-02.md`
- Código-fonte de referência: `Modules/Jana/` (repositório do cliente WR2)
- Documentação original do nWidart v1.x (que ainda está embutida no UltimatePOS)

## Atualização — 2026-04-18 (pós-sessão 02)

Eliana adicionou mais dois módulos UltimatePOS ao repositório para triangulação do padrão: `Modules/Repair/` e `Modules/Project/`. Comparação confirmou que o padrão Jana está correto:

- `module.json`, `start.php`, estilo de `Route::group` — idênticos aos 3 módulos
- `Http/routes.php` com `/install`, `/install/uninstall`, `/install/update` apontando para `InstallController` — **padrão em todos os 3**, confirma que criar o InstallController do PontoWr2 foi correto
- **Variação observada** no stack de middleware web — cada módulo escolhe conforme caso de uso:
  - Jana: `[web, SetSessionData, auth, language, timezone, AdminSidebarMenu, CheckUserLogin]`
  - Repair: `[web, authh, auth, SetSessionData, language, timezone, AdminSidebarMenu]`
  - Project: `[web, authh, SetSessionData, auth, language, timezone, AdminSidebarMenu]`
  - PontoWr2: idem Jana + `ponto.access` (mantido — fluxo só de gestor, sem cliente final)
- **Variação observada** em ServiceProvider — Repair e Project têm provider enxuto (sem array `$middleware` nem `registerMiddleware`) porque não registram middleware próprio. Jana e PontoWr2 têm o padrão completo com aliasing, porque têm middleware customizado. **Ambas as variantes são corretas — use conforme necessidade real do módulo.**
- **Pastas extras permitidas:** Repair e Project têm `Notifications/` e `Utils/`; Jana tem `Transformers/`. Cada módulo pode ter diretórios próprios além do core.

**Conclusão:** padrão Jana confirmado. Com 3 módulos UltimatePOS como referência viva, futuras sessões podem usar qualquer um como gabarito (preferir o mais próximo em complexidade ao que estiver sendo construído).

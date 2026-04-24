<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Gera arquivo de **requisitos funcionais** para cada módulo em formato
 * denso + estruturado (machine-readable + human-readable).
 *
 * Estrutura do arquivo gerado:
 *   1. Frontmatter YAML (module, alias, status, areas, etc)
 *   2. Objetivo em prosa curta
 *   3. Áreas funcionais (com user stories)
 *   4. User stories com DoD rastreável → implementado_em: <path>
 *   5. Regras de negócio em formato Gherkin (testável)
 *   6. Integrações (hooks, cross-deps, APIs externas)
 *   7. Dados e entidades
 *   8. Decisões em aberto
 *   9. Histórico / changelog
 *
 * Regra de ouro: seções [TODO] NÃO são sobrescritas em regeneração se
 * tiverem sido preenchidas manualmente. O comando usa "preserve" mode
 * para manter edições humanas.
 */
class ModuleRequirementsGenerator
{
    public function __construct(protected ModuleSpecGenerator $specGen)
    {
    }

    public function render(array $spec): string
    {
        $existsInCurrent = (bool) ($spec['exists_in_current'] ?? false);

        if (! $existsInCurrent) {
            return $this->renderLegacyPlaceholder($spec);
        }

        return $this->renderActive($spec);
    }

    // ========================================================================
    // Active module (no branch atual)
    // ========================================================================

    protected function renderActive(array $spec): string
    {
        $name = $spec['name'];
        $alias = $spec['module_json']['alias'] ?? strtolower($name);
        $description = $this->extractDescription($spec);
        $active = (bool) ($spec['signals']['active'] ?? false);
        $priority = $spec['signals']['migration_priority'] ?? 'média';
        $risk = $spec['signals']['risk'] ?? 'médio';

        $routeCount = count($spec['routes']['all'] ?? []);
        $controllerCount = count($spec['controllers'] ?? []);
        $viewCount = (int) ($spec['views']['count'] ?? 0);
        $entities = $spec['entities'] ?? [];
        $permRegistered = $spec['permissions']['registered'] ?? [];
        $crossDeps = $spec['cross_deps'] ?? [];
        $hooks = $spec['upos_hooks'] ?? [];

        $areas = $this->groupControllersByArea($spec['controllers'] ?? [], $name);
        $storyCounter = 0;
        $ruleCounter = 0;

        // ---------------- Frontmatter ----------------
        $md = "---\n";
        $md .= "module: {$name}\n";
        $md .= "alias: {$alias}\n";
        $md .= "status: " . ($active ? 'ativo' : 'inativo') . "\n";
        $md .= "migration_target: react\n";
        $md .= "migration_priority: {$priority}\n";
        $md .= "risk: {$risk}\n";
        $md .= "areas: [" . implode(', ', array_keys($areas)) . "]\n";
        $md .= "last_generated: " . now()->format('Y-m-d') . "\n";
        $md .= "scale:\n";
        $md .= "  routes: {$routeCount}\n";
        $md .= "  controllers: {$controllerCount}\n";
        $md .= "  views: {$viewCount}\n";
        $md .= "  entities: " . count($entities) . "\n";
        $md .= "  permissions: " . count($permRegistered) . "\n";
        $md .= "---\n\n";

        // ---------------- Cabeçalho ----------------
        $md .= "# Requisitos funcionais — {$name}\n\n";
        $md .= "> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,\n";
        $md .= "> separada da spec técnica em `memory/modulos/{$name}.md`.\n";
        $md .= ">\n";
        $md .= "> Arquivos deste formato são consumidos pelo módulo **MemCofre**\n";
        $md .= "> (`/docs/modulos/{$name}`) que linka user stories com telas React,\n";
        $md .= "> regras Gherkin com testes, e mantém rastreabilidade evidência → requisito.\n\n";

        $md .= "## Sumário\n\n";
        $md .= "1. [Objetivo](#1-objetivo)\n";
        $md .= "2. [Áreas funcionais](#2-áreas-funcionais)\n";
        $md .= "3. [User stories](#3-user-stories)\n";
        $md .= "4. [Regras de negócio (Gherkin)](#4-regras-de-negócio-gherkin)\n";
        $md .= "5. [Integrações](#5-integrações)\n";
        $md .= "6. [Dados e entidades](#6-dados-e-entidades)\n";
        $md .= "7. [Decisões em aberto](#7-decisões-em-aberto)\n";
        $md .= "8. [Histórico e notas](#8-histórico-e-notas)\n\n";
        $md .= "---\n\n";

        // ---------------- 1. Objetivo ----------------
        $md .= "## 1. Objetivo\n\n";
        $md .= $description . "\n\n";

        // ---------------- 2. Áreas funcionais ----------------
        $md .= "## 2. Áreas funcionais\n\n";
        if (empty($areas)) {
            $md .= "_[TODO — descrever áreas funcionais. Esperado formato: lista com 1 linha por área explicando o que faz pro usuário final.]_\n\n";
        } else {
            foreach ($areas as $area => $data) {
                $md .= "### 2." . ($data['index'] + 1) . ". {$area}\n\n";
                $md .= "**Controller(s):** " . implode(', ', array_map(fn ($c) => "`{$c}`", $data['controllers'])) . "  \n";
                $md .= "**Ações (" . count($data['actions']) . "):** `" . implode('`, `', array_slice($data['actions'], 0, 12)) . '`';
                if (count($data['actions']) > 12) {
                    $md .= " _+ " . (count($data['actions']) - 12) . "_";
                }
                $md .= "\n\n";
                $md .= "_Descrição funcional:_ [TODO]\n\n";
            }
        }

        // ---------------- 3. User Stories ----------------
        $md .= "## 3. User stories\n\n";
        $md .= "> Convenção do ID: `US-" . strtoupper(substr($alias, 0, 4)) . "-NNN`\n";
        $md .= "> Campo `implementado_em` linka com a Page React que atende a story.\n\n";

        $stories = $this->extractUserStories($spec, $areas);
        if (empty($stories)) {
            $md .= "_[TODO — escrever user stories no formato abaixo.]_\n\n";
            $md .= $this->userStoryTemplate($alias, 1);
        } else {
            foreach ($stories as $i => $story) {
                $md .= $this->renderUserStory($story, $alias, $i + 1);
            }
        }

        // ---------------- 4. Regras Gherkin ----------------
        $md .= "## 4. Regras de negócio (Gherkin)\n\n";
        $md .= "> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser\n";
        $md .= "> **testável** — idealmente tem 1 teste Feature que a valida.\n\n";

        $rules = $this->extractBusinessRules($spec);
        if (empty($rules)) {
            $md .= "_[TODO — escrever regras no formato Gherkin.]_\n\n";
            $md .= $this->ruleTemplate($alias, 1);
        } else {
            foreach ($rules as $i => $rule) {
                $md .= $this->renderRule($rule, $alias, $i + 1);
            }
        }

        // ---------------- 5. Integrações ----------------
        $md .= "## 5. Integrações\n\n";
        if (! empty($hooks)) {
            $md .= "### 5.1. Hooks UltimatePOS registrados\n\n";
            foreach ($hooks as $hook) {
                $hookName = is_array($hook) ? ($hook['method'] ?? '?') : (string) $hook;
                $md .= "- **`{$hookName}()`** — " . $this->describeHook($hookName) . "\n";
            }
            $md .= "\n";
        }
        if (! empty($crossDeps)) {
            $md .= "### 5.2. Dependências entre módulos\n\n";
            foreach (array_slice($crossDeps, 0, 12) as $dep) {
                $direction = ($dep['direction'] ?? '?') === 'in' ? '🔽 consome de' : '🔼 é consumido por';
                $other = $dep['module'] ?? '?';
                $count = $dep['count'] ?? '?';
                $md .= "- {$direction} **{$other}** ({$count}x)\n";
            }
            $md .= "\n";
        }
        $md .= "### 5.3. Integrações externas\n\n";
        $md .= "_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_\n\n";

        // ---------------- 6. Dados ----------------
        $md .= "## 6. Dados e entidades\n\n";
        if (empty($entities)) {
            $md .= "_Módulo não declara entities próprias._\n\n";
        } else {
            $md .= "| Modelo | Tabela | Finalidade |\n";
            $md .= "|---|---|---|\n";
            foreach ($entities as $entity) {
                $cls = $entity['class'] ?? '?';
                $table = $entity['table'] ?? '—';
                $md .= "| `{$cls}` | `{$table}` | [TODO] |\n";
            }
            $md .= "\n";
        }

        // ---------------- 7. Decisões em aberto ----------------
        $md .= "## 7. Decisões em aberto\n\n";
        $md .= "> Questões que exigem decisão de produto/negócio antes de avançar.\n\n";
        $md .= "- [ ] [TODO]\n";
        $md .= "- [ ] [TODO]\n\n";

        // ---------------- 8. Histórico ----------------
        $md .= "## 8. Histórico e notas\n\n";
        $md .= "> Decisões tomadas, incidentes relevantes, contexto.\n\n";
        $md .= "- **" . now()->format('Y-m-d') . "** — arquivo gerado automaticamente por `module:requirements`\n\n";

        // ---------------- Rodapé ----------------
        $md .= "---\n";
        $md .= "_Última regeneração: " . now()->format('Y-m-d H:i') . "_  \n";
        $md .= "_Regerar: `php artisan module:requirements {$name}`_  \n";
        $md .= "_Ver no MemCofre: `/docs/modulos/{$name}`_\n";

        return $md;
    }

    // ========================================================================
    // Legacy placeholder (módulo perdido em branches antigas)
    // ========================================================================

    protected function renderLegacyPlaceholder(array $spec): string
    {
        $name = $spec['name'];
        $branches = $spec['branch_presence'] ?? [];
        $presentIn = array_filter($branches, fn ($v) => $v);

        $md = "---\n";
        $md .= "module: {$name}\n";
        $md .= "status: ausente_branch_atual\n";
        $md .= "action_required: decidir_ressuscitar_ou_deprecar\n";
        $md .= "present_in_branches: [" . implode(', ', array_keys($presentIn)) . "]\n";
        $md .= "last_generated: " . now()->format('Y-m-d') . "\n";
        $md .= "---\n\n";

        $md .= "# Requisitos funcionais — {$name} _(legado)_\n\n";
        $md .= "> ⚠️ **Módulo não existe no branch atual (`6.7-react`).**\n";
        $md .= "> Decidir entre **ressuscitar** ou **deprecar** antes de escrever\n";
        $md .= "> requisitos completos.\n\n";

        $md .= "## O que fazer com este módulo?\n\n";
        $md .= "- [ ] **Ressuscitar** — trazer do branch X, atualizar stack, migrar React\n";
        $md .= "- [ ] **Deprecar** — decisão de não trazer; apagar spec obsoleta\n";
        $md .= "- [ ] **Adiar** — congelado até próxima revisão\n\n";

        if (! empty($presentIn)) {
            $md .= "## Histórico\n\n";
            $md .= "Presente em: " . implode(', ', array_keys($presentIn)) . "\n\n";
        }

        $md .= "## Se ressuscitar, preencher:\n\n";
        $md .= "1. **Objetivo** — que valor entregava?\n";
        $md .= "2. **Áreas funcionais** — telas/fluxos\n";
        $md .= "3. **Motivação da volta**\n";
        $md .= "4. **Integrações** — módulos atuais que tocaria\n";
        $md .= "5. **Critérios de aceite** — quando considerar pronto\n\n";

        $md .= "## Referências\n\n";
        $md .= "- Spec técnica (se existir): `memory/modulos/{$name}.md`\n";
        $md .= "- Recomendações: `memory/modulos/RECOMENDACOES.md`\n\n";

        $md .= "---\n";
        $md .= "_Gerado por `module:requirements` em " . now()->format('Y-m-d H:i') . "_\n";

        return $md;
    }

    // ========================================================================
    // Helpers
    // ========================================================================

    protected function extractDescription(array $spec): string
    {
        $cfg = $spec['config'] ?? [];
        $modJson = $spec['module_json'] ?? [];

        if (! empty($cfg['description'])) return trim($cfg['description']);
        if (! empty($modJson['description'])) return trim($modJson['description']);

        // Fallback: monta descrição a partir de hooks e áreas
        $hooks = $spec['upos_hooks'] ?? [];
        $registraMenu = in_array('modifyAdminMenu', $hooks, true);
        $registraPerms = in_array('user_permissions', $hooks, true);

        $parts = [];
        if ($registraMenu) $parts[] = 'injeta itens na sidebar do UltimatePOS';
        if ($registraPerms) $parts[] = 'registra permissões Spatie próprias';

        $name = $spec['name'] ?? '?';
        $base = "Módulo **{$name}** do UltimatePOS";
        if (! empty($parts)) {
            $base .= ' que ' . implode(' e ', $parts);
        }
        return $base . ". _[TODO — expandir com 2-3 linhas sobre o valor de negócio.]_";
    }

    protected function groupControllersByArea(array $controllers, string $moduleName): array
    {
        $areas = [];
        $idx = 0;
        foreach ($controllers as $ctrl) {
            $cls = $ctrl['class'] ?? $ctrl;
            $actions = $ctrl['actions'] ?? [];

            // Skip helpers internos
            if (in_array($cls, ['DataController', 'InstallController'], true)) continue;

            // Nome semântico da área: remove Controller + prefixo do módulo
            $area = preg_replace('/Controller$/', '', $cls);
            $area = preg_replace('/^' . preg_quote($moduleName, '/') . '/', '', $area);
            $area = preg_replace('/^(Ponto|Essentials|Crm|Accounting|Repair|Project|Manufacturing|Writebot|Superadmin|Woocommerce|AssetManagement|Knowledgebase|IProduction|Officeimpresso|Grow|ProductCatalogue|Connector|Ai|Cms|Spreadsheet|Help|Jana|Fiscal|Boleto|Chat|Dashboard|BI)/', '', $area);
            $area = trim($area);
            if ($area === '') $area = 'Core';

            $areaHuman = trim(preg_replace('/([a-z])([A-Z])/', '$1 $2', $area));

            if (! isset($areas[$areaHuman])) {
                $areas[$areaHuman] = [
                    'index' => $idx++,
                    'controllers' => [],
                    'actions' => [],
                ];
            }
            $areas[$areaHuman]['controllers'][] = $cls;
            $areas[$areaHuman]['actions'] = array_values(array_unique(array_merge(
                $areas[$areaHuman]['actions'],
                $actions
            )));
        }

        ksort($areas);
        return $areas;
    }

    /**
     * Extrai user stories automaticamente de rotas tipo "create" + "store".
     * Cada área com rota create/index/show gera 1-2 stories candidatas.
     */
    protected function extractUserStories(array $spec, array $areas): array
    {
        $stories = [];
        $routes = $spec['routes']['all'] ?? [];

        // Agrupa rotas por controller
        $byController = [];
        foreach ($routes as $route) {
            $action = $route['action'] ?? '';
            // Extract controller from action: "Path\To\XController@method"
            if (preg_match('/([A-Z]\w+Controller)(?:@(\w+))?/', $action, $m)) {
                $ctrl = $m[1];
                $method = $m[2] ?? '';
                $byController[$ctrl][$method][] = $route;
            }
        }

        foreach ($areas as $areaName => $data) {
            foreach ($data['controllers'] as $ctrl) {
                $methods = $byController[$ctrl] ?? [];

                // Story padrão 1: listar
                if (isset($methods['index'])) {
                    $route = $methods['index'][0];
                    $stories[] = [
                        'area' => $areaName,
                        'title' => "Listar {$areaName}",
                        'persona' => 'usuário do módulo',
                        'goal' => "ver o conjunto de {$areaName}",
                        'value' => 'ter visão geral e filtrar o que importa',
                        'route' => "{$route['method']} {$route['uri']}",
                        'controller_action' => "{$ctrl}@index",
                    ];
                }

                // Story padrão 2: criar
                if (isset($methods['store']) || isset($methods['create'])) {
                    $route = $methods['store'][0] ?? $methods['create'][0];
                    $stories[] = [
                        'area' => $areaName,
                        'title' => "Criar {$areaName}",
                        'persona' => 'usuário autorizado',
                        'goal' => "criar um novo item em {$areaName}",
                        'value' => 'alimentar o sistema com os dados operacionais',
                        'route' => "{$route['method']} {$route['uri']}",
                        'controller_action' => "{$ctrl}@" . (isset($methods['store']) ? 'store' : 'create'),
                    ];
                }

                // Story padrão 3: ver detalhe
                if (isset($methods['show'])) {
                    $route = $methods['show'][0];
                    $stories[] = [
                        'area' => $areaName,
                        'title' => "Ver detalhe de {$areaName}",
                        'persona' => 'usuário com acesso ao item',
                        'goal' => 'consultar informação completa de um item específico',
                        'value' => 'tomar decisão com base em contexto completo',
                        'route' => "{$route['method']} {$route['uri']}",
                        'controller_action' => "{$ctrl}@show",
                    ];
                }
            }
            // Limita pra não inflar demais
            if (count($stories) >= 20) break;
        }

        return $stories;
    }

    /**
     * Extrai regras de negócio implícitas. Heurística:
     *  - Se tem permissão registrada → regra de autorização
     *  - Se tem scope business_id → regra multi-tenant
     *  - Cada área gera 1 regra de validação
     */
    protected function extractBusinessRules(array $spec): array
    {
        $rules = [];
        $name = $spec['name'] ?? '?';

        // Regra multi-tenant (sempre presente)
        $rules[] = [
            'title' => 'Isolamento multi-tenant por business_id',
            'given' => 'um usuário pertence ao business A',
            'when'  => "ele acessa qualquer recurso do módulo {$name}",
            'then'  => 'só vê registros com `business_id = A`',
            'impl'  => 'Controllers fazem `where(\'business_id\', session(\'business.id\'))`',
        ];

        // Regras de autorização (uma por permissão registrada)
        $perms = $spec['permissions']['registered'] ?? [];
        foreach (array_slice($perms, 0, 6) as $perm) {
            $rules[] = [
                'title' => "Autorização Spatie `{$perm}`",
                'given' => "um usuário **não** tem a permissão `{$perm}`",
                'when'  => 'ele tenta acessar a funcionalidade correspondente',
                'then'  => 'recebe `403 Unauthorized`',
                'impl'  => "Controllers checam `\$user->can('{$perm}')`",
            ];
        }

        return $rules;
    }

    protected function renderUserStory(array $story, string $alias, int $num): string
    {
        $id = 'US-' . strtoupper(substr($alias, 0, 4)) . '-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);

        $md = "### {$id} · {$story['title']}\n\n";
        $md .= "> **Área:** {$story['area']}  \n";
        $md .= "> **Rota:** `{$story['route']}`  \n";
        $md .= "> **Controller/ação:** `{$story['controller_action']}`\n\n";
        $md .= "**Como** {$story['persona']}  \n";
        $md .= "**Quero** {$story['goal']}  \n";
        $md .= "**Para** {$story['value']}\n\n";
        $md .= "**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_\n\n";
        $md .= "**Definition of Done:**\n";
        $md .= "- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)\n";
        $md .= "- [ ] Scope por `business_id` nas queries\n";
        $md .= "- [ ] Validação dos campos de input com FormRequest\n";
        $md .= "- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`\n";
        $md .= "- [ ] Teste Feature cobrindo auth, permissão, validação\n";
        $md .= "- [ ] Dark mode funciona\n";
        $md .= "- [ ] Responsivo mobile (grid cols-1 md:cols-N)\n";
        $md .= "- [ ] Toast `sonner` em mutations (success + error)\n\n";

        return $md;
    }

    protected function renderRule(array $rule, string $alias, int $num): string
    {
        $id = 'R-' . strtoupper(substr($alias, 0, 4)) . '-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);

        $md = "### {$id} · {$rule['title']}\n\n";
        $md .= "```gherkin\n";
        $md .= "Dado que {$rule['given']}\n";
        $md .= "Quando {$rule['when']}\n";
        $md .= "Então {$rule['then']}\n";
        $md .= "```\n\n";
        $md .= "**Implementação:** {$rule['impl']}  \n";
        $md .= "**Testado em:** _[TODO — apontar caminho do teste]_\n\n";

        return $md;
    }

    protected function userStoryTemplate(string $alias, int $num): string
    {
        $id = 'US-' . strtoupper(substr($alias, 0, 4)) . '-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
        return "### {$id} · [TODO — título]\n\n"
            . "**Como** [papel]  \n"
            . "**Quero** [ação]  \n"
            . "**Para** [objetivo de negócio]\n\n"
            . "**Implementado em:** _[path]_\n\n"
            . "**Definition of Done:**\n"
            . "- [ ] [critério]\n\n";
    }

    protected function ruleTemplate(string $alias, int $num): string
    {
        $id = 'R-' . strtoupper(substr($alias, 0, 4)) . '-' . str_pad((string) $num, 3, '0', STR_PAD_LEFT);
        return "### {$id} · [TODO — título]\n\n"
            . "```gherkin\n"
            . "Dado que [pré-condição]\n"
            . "Quando [ação]\n"
            . "Então [resultado esperado]\n"
            . "```\n\n"
            . "**Testado em:** _[path]_\n\n";
    }

    protected function describeHook(string $hook): string
    {
        return match ($hook) {
            'modifyAdminMenu'      => 'injeta itens na sidebar admin do UltimatePOS',
            'superadmin_package'   => 'registra pacote de licenciamento no Superadmin',
            'user_permissions'     => 'registra permissões Spatie no cadastro de Roles',
            'addTaxonomies'        => 'registra categorias/taxonomias customizadas',
            'moduleViewPartials'   => 'injeta conteúdo em views do core',
            'parse_notification'   => 'customiza rendering de notificações',
            'afterModelSaved'      => 'hook pós-save em models do core',
            'profitLossReportData' => 'contribui para relatório de lucros e perdas',
            default                => 'hook do UltimatePOS',
        };
    }
}

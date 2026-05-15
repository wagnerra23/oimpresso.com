<?php

namespace App\Services;

use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Menu;
use App\Services\Menu\MenuItem as NwidartItem;
use Throwable;

/**
 * Adapta a árvore de menu do UltimatePOS (nwidart/laravel-menus) para o shape
 * esperado pelo React AppShell.
 *
 * COMO FUNCIONA o sistema legado do UltimatePOS:
 *  1. O middleware `AdminSidebarMenu` (app/Http/Middleware/AdminSidebarMenu.php)
 *     cria a instância 'admin-sidebar-menu' via `Menu::create(...)` e popula o
 *     menu CORE (Home, POS, Vendas, Produtos, Contatos, Relatórios, Config).
 *  2. Depois chama `ModuleUtil::getModuleData('modifyAdminMenu')` que itera
 *     TODOS os módulos instalados em `Modules/` e invoca
 *     `Modules\<Nome>\Http\Controllers\DataController::modifyAdminMenu()`.
 *  3. Cada módulo instalado chama `Menu::modify('admin-sidebar-menu', ...)` e
 *     adiciona seus itens (URL, ícone SVG inline, order).
 *  4. No blade, `Menu::render('admin-sidebar-menu')` transforma em HTML.
 *
 * ESTE ADAPTER faz o passo "5": lê a instância já construída e converte para
 * JSON consumível pelo React. Chamar SEMPRE após middleware AdminSidebarMenu
 * ter rodado — ou seja, dentro de share() do HandleInertiaRequests (lazy),
 * pois lá o middleware stack já executou.
 *
 * Conversão de ícones: o legado usa SVG inline em string. Como React/Lucide
 * trabalha com nomes, mapeamos por heurística (palavra-chave no título/URL)
 * para o Lucide icon mais próximo. Fallback: 'Circle' (neutro).
 */
class LegacyMenuAdapter
{
    /**
     * Mapa de palavras-chave (título ou URL) → ícone Lucide.
     * Ordem importa: primeira correspondência ganha.
     */
    protected array $iconMap = [
        'home'           => 'Home',
        'dashboard'      => 'LayoutDashboard',
        'pos'            => 'ShoppingCart',
        'venda'          => 'ReceiptText',
        'sell'           => 'ReceiptText',
        'purchase'       => 'ShoppingBag',
        'compra'         => 'ShoppingBag',
        'product'        => 'Package',
        'produto'        => 'Package',
        'estoque'        => 'Warehouse',
        'stock'          => 'Warehouse',
        'categoria'      => 'FolderTree',
        'categor'        => 'FolderTree',
        'cliente'        => 'Users',
        'fornecedor'     => 'Factory',
        'contact'        => 'Users',
        'report'         => 'BarChart3',
        'relatór'        => 'BarChart3',
        'relatório'      => 'BarChart3',
        'financ'         => 'TrendingUp',
        'pagamento'      => 'CreditCard',
        'payment'        => 'CreditCard',
        'caixa'          => 'Coins',
        'cash'           => 'Coins',
        'despesa'        => 'TrendingDown',
        'expense'        => 'TrendingDown',
        'transferência'  => 'ArrowLeftRight',
        'transfer'       => 'ArrowLeftRight',
        'usuário'        => 'UserCog',
        'user'           => 'UserCog',
        'permiss'        => 'ShieldCheck',
        'role'           => 'ShieldCheck',
        'papel'          => 'ShieldCheck',
        'config'         => 'Settings',
        'empresa'        => 'Building2',
        'business'       => 'Building2',
        'ponto'          => 'Clock',
        'projeto'        => 'FolderKanban',
        'project'        => 'FolderKanban',
        'repair'         => 'Wrench',
        'repar'          => 'Wrench',
        'fabric'         => 'Factory',
        'manufact'       => 'Factory',
        'woocommerce'    => 'ShoppingBag',
        'crm'            => 'HeartHandshake',
        'account'        => 'Calculator',
        'contábil'       => 'Calculator',
        'cms'            => 'LayoutList',
        'connector'      => 'Plug',
        'api'            => 'Plug',
        'ia'             => 'Sparkles',
        'ai'             => 'Sparkles',
        'chat'           => 'MessageSquare',
        'help'           => 'HelpCircle',
        'ajuda'          => 'HelpCircle',
        'superadmin'     => 'ShieldAlert',
        'asset'          => 'Boxes',
        'ativo'          => 'Boxes',
        'catalogue'      => 'BookOpen',
        'catalog'        => 'BookOpen',
        'qr'             => 'QrCode',
        'impressora'     => 'Printer',
        'nfe'            => 'FileSpreadsheet',
        'boleto'         => 'Receipt',
        'transportadora' => 'Truck',
        'tax'            => 'Percent',
        'tribut'         => 'Percent',
    ];

    /**
     * Varre Modules/<Nome>/Resources/menus/topnav.php e devolve map
     * ['<Nome>' => ['label' => ..., 'icon' => ..., 'items' => [...]]] filtrado
     * por permissões Spatie do user atual.
     *
     * Fonte INDEPENDENTE da sidebar (ADR arq/0011). A sidebar continua vindo
     * de DataController::modifyAdminMenu(). TopNav é opcional — módulo que
     * não tiver o arquivo simplesmente não expõe topnav.
     *
     * Shape do arquivo:
     *
     *   return [
     *       'label' => 'Ponto WR2',
     *       'icon'  => 'Clock',
     *       'items' => [
     *           ['label' => 'Dashboard', 'href' => '/ponto', 'icon' => 'LayoutDashboard', 'can' => 'ponto.access'],
     *           ...
     *       ],
     *   ];
     */
    public function buildTopNavs(): array
    {
        $statusesFile = base_path('modules_statuses.json');
        if (!file_exists($statusesFile)) return [];

        $statuses = json_decode(file_get_contents($statusesFile), true) ?: [];
        $active = array_keys(array_filter($statuses, fn ($v) => $v === true));

        $out = [];
        foreach ($active as $moduleName) {
            $path = base_path("Modules/{$moduleName}/Resources/menus/topnav.php");
            if (!file_exists($path)) continue;

            try {
                $config = require $path;
                if (!is_array($config) || empty($config['items'])) continue;

                $filteredItems = [];
                foreach ($config['items'] as $item) {
                    if (!empty($item['can']) && !auth()->user()?->can($item['can'])) {
                        continue;
                    }
                    $filteredItems[] = [
                        'label'   => $this->resolveLabel($item['label'] ?? ''),
                        'icon'    => $item['icon'] ?? 'Circle',
                        'href'    => $item['href'] ?? '#',
                        'inertia' => $this->isInertiaRoute($item['href'] ?? null),
                        'badge'   => $item['badge'] ?? null,
                    ];
                }

                if (empty($filteredItems)) continue;

                $out[$moduleName] = [
                    'label' => $this->resolveLabel($config['label'] ?? $moduleName),
                    'icon'  => $config['icon'] ?? 'Circle',
                    'items' => $filteredItems,
                ];
            } catch (Throwable $e) {
                report($e);
            }
        }

        // Core topnavs (módulos UltimatePOS legado em app/Http/Controllers/, não em Modules/).
        // Lidos de config/core_topnavs.php — ADR 0107 §gap topnav.
        foreach (config('core_topnavs', []) as $moduleName => $config) {
            try {
                if (!is_array($config) || empty($config['items'])) continue;

                $filteredItems = [];
                foreach ($config['items'] as $item) {
                    if (!empty($item['can']) && !auth()->user()?->can($item['can'])) {
                        continue;
                    }
                    $filteredItems[] = [
                        'label'   => $this->resolveLabel($item['label'] ?? ''),
                        'icon'    => $item['icon'] ?? 'Circle',
                        'href'    => $item['href'] ?? '#',
                        'inertia' => $this->isInertiaRoute($item['href'] ?? null),
                        'badge'   => $item['badge'] ?? null,
                    ];
                }

                if (empty($filteredItems)) continue;

                $out[$moduleName] = [
                    'label' => $this->resolveLabel($config['label'] ?? $moduleName),
                    'icon'  => $config['icon'] ?? 'Circle',
                    'items' => $filteredItems,
                ];
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $out;
    }

    /**
     * Resolve string de label. Aceita literal ("Dashboard") ou chave i18n
     * ("ponto::lang.dashboard") — trans() resolve o que for.
     */
    protected function resolveLabel(string $raw): string
    {
        if (empty($raw)) return '';
        if (str_contains($raw, '::')) {
            $translated = trans($raw);
            return $translated !== $raw ? $translated : $raw;
        }
        return $raw;
    }

    public function build(): array
    {
        $instance = Menu::instance('admin-sidebar-menu');
        if (!$instance) {
            return [];
        }

        $items = [];
        foreach ($instance->getItems() as $nwItem) {
            /** @var NwidartItem $nwItem */
            try {
                $converted = $this->convertItem($nwItem);
                if ($converted !== null) {
                    $items[] = $converted;
                }
            } catch (Throwable $e) {
                report($e); // silencia item quebrado, shell continua
            }
        }

        // US-UI-SIDEBAR-001 — filtra items escondidos por business
        // (config em business.sidebar_hidden_groups). Multi-tenant Tier 0
        // (ADR 0093): scope pelo business_id do user autenticado.
        $items = $this->applyHiddenGroupsFilter($items);

        // nwidart já aplica `order()` mas vamos reforçar
        usort($items, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        // Remove `order` do payload final (já foi usado pra sort)
        return array_map(function ($item) {
            unset($item['order']);
            return $item;
        }, $items);
    }

    /**
     * Lookup table espelhada do frontend SIDEBAR_GROUPS
     * (resources/js/Components/cockpit/Sidebar.tsx ~linhas 97-159).
     *
     * Mantém sincronia manual: quando adicionar/remover grupo no front,
     * espelhe aqui. Sem isso, filter por chave de grupo não funciona.
     *
     * Match item→grupo é case-insensitive em literal-string.
     *
     * @return array<string, array<int, string>>  key:grupo => [items]
     */
    protected function sidebarGroupsMirror(): array
    {
        return [
            'office'       => ['Consulta de OS', 'Ordens de Serviço', 'Contatos', 'Clientes', 'Produtos', 'Vender', 'vender', 'Vendas', 'Orçamentos', 'Reparar', 'CRM', 'Crm', 'Office Impresso', 'Officeimpresso'],
            'oficina'      => ['Oficina Auto'],
            'fin'          => ['Despesas', 'Contas de pagamento', 'Accounting', 'Contabilidade', 'Financeiro'],
            'estoque'      => ['Compras', 'Transferências de ações', 'Ajuste de estoque', 'Gestão de ativos'],
            'fiscal'       => ['NFSe', 'NF-e Brasil'],
            'rh'           => ['HRM', 'Essenciais', 'Ponto'],
            'conhecimento' => ['Cofre de Memórias', 'SRS', 'Sistema de Regras', 'Base de Conhecimento', 'KB', 'Planilha', 'Notas'],
            'rel'          => ['Iniciar', 'Início', 'Home', 'Dashboard', 'Relatórios', 'Reservas', 'Pedidos', 'Cocina'],
            'ia'           => ['Copiloto', 'Jana', 'Projeto', 'Project Mgmt', 'Project'],
            'governanca'   => ['Governança', 'Governance', 'ADS', 'Adaptive Decision', 'Team MCP', 'TeamMcp'],
            'plataforma'   => ['CMS', 'Conector', 'Connector', 'Backup', 'Módulos', 'Modulos', 'Manage Modules', 'Personalizar'],
        ];
    }

    /**
     * Lê `business.sidebar_hidden_groups` do business autenticado e devolve
     * lista de strings (lowercase) representando grupos OU items escondidos.
     *
     * Default safe: retorna [] em qualquer falha (coluna ausente, JSON
     * inválido, business sem user logado, throw qualquer). Falha aberta —
     * sidebar continua mostrando tudo.
     *
     * Cache: per-request via static property (LegacyMenuAdapter é singleton
     * resolvido por ShellMenuBuilder no share Inertia — mesma instância).
     *
     * @return array<int, string>  lista normalizada (lowercase trimmed)
     */
    protected function hiddenList(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        try {
            $user = auth()->user();
            if (!$user || empty($user->business_id)) {
                return $cache = [];
            }

            // Defesa em profundidade: coluna pode não existir (migration pendente)
            if (!Schema::hasColumn('business', 'sidebar_hidden_groups')) {
                return $cache = [];
            }

            // Multi-tenant Tier 0 (ADR 0093): scope explícito pelo business_id
            // do user atual. Zero risco cross-tenant.
            $raw = DB::table('business')
                ->where('id', $user->business_id)
                ->value('sidebar_hidden_groups');

            if (empty($raw)) {
                return $cache = [];
            }

            $decoded = is_array($raw) ? $raw : json_decode($raw, true);
            if (!is_array($decoded)) {
                return $cache = [];
            }

            // Normaliza pra match case-insensitive (mb_strtolower + trim)
            $normalized = array_map(
                fn ($s) => mb_strtolower(trim((string) $s), 'UTF-8'),
                $decoded
            );

            return $cache = array_values(array_filter($normalized, fn ($s) => $s !== ''));
        } catch (Throwable $e) {
            report($e);
            return $cache = [];
        }
    }

    /**
     * Aplica o filtro de items escondidos. Cada entry em hiddenList pode
     * casar (case-insensitive) com:
     *   (a) chave de grupo SIDEBAR_GROUPS — esconde TODOS os items do grupo
     *   (b) label exato de item top-level — esconde só esse item
     *
     * Default safe: hiddenList vazia → retorna $items intacto.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    protected function applyHiddenGroupsFilter(array $items): array
    {
        $hidden = $this->hiddenList();
        if (empty($hidden)) {
            return $items;
        }

        $groupsMirror = $this->sidebarGroupsMirror();

        // Expande chaves de grupo em set de labels pra match O(1)
        $hiddenLabels = [];
        foreach ($hidden as $entry) {
            if (isset($groupsMirror[$entry])) {
                // chave de grupo → adiciona todos os items do grupo
                foreach ($groupsMirror[$entry] as $label) {
                    $hiddenLabels[mb_strtolower($label, 'UTF-8')] = true;
                }
            } else {
                // label de item top-level
                $hiddenLabels[$entry] = true;
            }
        }

        return array_values(array_filter($items, function ($item) use ($hiddenLabels) {
            $label = mb_strtolower(trim((string) ($item['label'] ?? '')), 'UTF-8');
            return $label === '' || !isset($hiddenLabels[$label]);
        }));
    }

    protected function convertItem(NwidartItem $item): ?array
    {
        // Divider/header do nwidart não viram item — pulamos
        if ($item->isDivider() || $item->isHeader()) {
            return null;
        }

        $props = $item->getProperties();
        $title = $props['title'] ?? '';

        if (empty($title)) {
            return null;
        }

        $url = method_exists($item, 'getUrl') ? $item->getUrl() : ($props['url'] ?? null);
        // Normaliza URL absoluta (app.url/path) → path relativo (/path).
        // Inertia usa url relativo (/ponto/relatorios) e o matching do client
        // compara com startsWith — precisamos do mesmo formato.
        $url = $this->toRelative($url);

        // Rewrites: rotas antigas quebradas → versões React novas
        $rewrites = [
            '/manage-modules' => '/modulos',
        ];
        if ($url !== null && isset($rewrites[$url])) {
            $url = $rewrites[$url];
        }
        $order = $props['order'] ?? 0;

        $children = [];
        if ($item->hasSubMenu() || $item->hasChilds()) {
            foreach ($item->getChilds() as $child) {
                try {
                    $childConverted = $this->convertItem($child);
                    if ($childConverted !== null) {
                        $children[] = $childConverted;
                    }
                } catch (Throwable $e) {
                    report($e);
                }
            }
            usort($children, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
            $children = array_map(function ($c) {
                unset($c['order']);
                return $c;
            }, $children);
        }

        $result = [
            'label'   => strip_tags($title),
            'icon'    => $this->guessIcon($title, $url),
            'order'   => $order,
            'inertia' => $this->isInertiaRoute($url),
        ];

        if (!empty($children)) {
            $result['children'] = $children;
            // Pais dropdown geralmente não têm URL própria no nwidart — deixamos undef
            if (!empty($url) && $url !== '#') {
                $result['href'] = $url;
            }
        } else {
            $result['href'] = $url ?? '#';
        }

        return $result;
    }

    /**
     * Heurística: palavra-chave no título ou URL → Lucide icon.
     * Case-insensitive. Primeira correspondência ganha.
     */
    protected function guessIcon(string $title, ?string $url): string
    {
        $haystack = mb_strtolower($title . ' ' . ($url ?? ''), 'UTF-8');

        foreach ($this->iconMap as $keyword => $icon) {
            if (str_contains($haystack, $keyword)) {
                return $icon;
            }
        }

        return 'Circle';
    }

    /**
     * Rotas Inertia já migradas (retornam Inertia\Response). Mantenha curta:
     * cada vez que uma tela migrar, adicione o PREFIXO aqui.
     */
    protected function isInertiaRoute(?string $url): bool
    {
        if (empty($url)) return false;
        $path = $this->toRelative($url) ?? '';

        $inertiaPrefixes = [
            '/ponto/react',
            '/ponto/relatorios',
            '/ponto/intercorrencias',
            '/ponto/aprovacoes',
            '/ponto/espelho',
            '/ponto/banco-horas',
            '/ponto/escalas',
            '/ponto/importacoes',
            '/ponto/colaboradores',
            '/ponto/configuracoes',
            '/modulos',
            '/essentials/todo',
            '/essentials/messages',
            '/essentials/knowledge-base',
            '/essentials/document',
            '/essentials/reminder',
            '/hrm/holiday',
            '/hrm/settings',
            '/docs',
            // Sem /financeiro, clicar no item da sidebar dispara full reload e
            // remonta o AppShell — perde o estado (filtros, accordion, sheet aberta).
            // Ver memory/claude/preference_persistent_layouts.md.
            '/financeiro',
            // Adicione aqui conforme migrar telas.
        ];
        // Dashboard do Ponto é `/ponto` exato (não prefixo) — o startsWith pegaria
        // /ponto/espelho também e marcaria como Inertia por engano. Tratamento à parte.
        if ($path === '/ponto') return true;

        foreach ($inertiaPrefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove scheme+host de URL absoluta, preservando path+query.
     * `https://oimpresso.test/ponto/espelho?x=1` → `/ponto/espelho?x=1`
     * Valores já relativos passam intactos.
     */
    protected function toRelative(?string $url): ?string
    {
        if ($url === null || $url === '' || $url === '#') {
            return $url;
        }
        $parts = parse_url($url);
        if ($parts === false) return $url;

        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $frag = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        // Se não tinha host, já era relativo — mantém original
        if (!isset($parts['host'])) {
            return $url;
        }

        return ($path !== '' ? $path : '/') . $query . $frag;
    }
}

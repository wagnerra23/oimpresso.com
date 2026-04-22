<?php

namespace App\Services;

use App\Utils\ModuleUtil;
use Menu;
use Nwidart\Menus\MenuItem as NwidartItem;
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

        // nwidart já aplica `order()` mas vamos reforçar
        usort($items, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        // Remove `order` do payload final (já foi usado pra sort)
        return array_map(function ($item) {
            unset($item['order']);
            return $item;
        }, $items);
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

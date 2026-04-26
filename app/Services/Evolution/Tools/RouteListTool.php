<?php

declare(strict_types=1);

namespace App\Services\Evolution\Tools;

use Illuminate\Support\Facades\Artisan;

class RouteListTool implements Tool
{
    public function name(): string
    {
        return 'RouteList';
    }

    public function description(): string
    {
        return 'Retorna a lista de rotas (uri, method, name) — opcionalmente filtradas por substring.';
    }

    public function __invoke(array $args = [])
    {
        $needle = isset($args['filter']) ? mb_strtolower((string) $args['filter']) : null;

        $exit = Artisan::call('route:list', ['--json' => true]);
        if ($exit !== 0) {
            return ['error' => 'route:list falhou', 'exit_code' => $exit];
        }

        $output = Artisan::output();
        $routes = json_decode($output, true) ?: [];

        if ($needle !== null && $needle !== '') {
            $routes = array_values(array_filter(
                $routes,
                fn ($r) => str_contains(mb_strtolower((string) ($r['uri'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($r['name'] ?? '')), $needle)
            ));
        }

        return array_map(fn ($r) => [
            'uri' => $r['uri'] ?? null,
            'method' => $r['method'] ?? null,
            'name' => $r['name'] ?? null,
            'action' => $r['action'] ?? null,
        ], $routes);
    }
}

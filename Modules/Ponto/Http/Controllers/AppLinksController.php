<?php

declare(strict_types=1);

namespace Modules\Ponto\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Arquivos de associação do app de ponto (Capacitor) com o domínio:
 * Android App Links (assetlinks.json) e iOS Universal Links (apple-app-site-association).
 *
 * Lê `pontowr2.app_links` (app de ponto pelo env de produção + `outros_android`, ex. o Fisio).
 * Só entra app com pacote e fingerprint válidos; sem nenhum, responde 404 — publicar um
 * arquivo vazio ou com fingerprint errado faz a loja recusar a verificação em silêncio.
 * Ver memory/requisitos/Ponto/RUNBOOK-publico.md §App Links.
 */
class AppLinksController extends Controller
{
    private const FINGERPRINT = '/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/';

    public function assetlinks(): JsonResponse
    {
        $apps = [[
            'package' => config('pontowr2.app_links.android_package'),
            'sha256'  => config('pontowr2.app_links.android_sha256'),
        ], ...(array) config('pontowr2.app_links.outros_android', [])];

        $declaracoes = [];
        foreach ($apps as $app) {
            $package = trim((string) ($app['package'] ?? ''));
            $fingerprints = array_values(array_filter(
                array_map(fn ($f) => strtoupper(trim($f)), explode(',', (string) ($app['sha256'] ?? ''))),
                fn ($f) => preg_match(self::FINGERPRINT, $f) === 1
            ));
            if ($package === '' || $fingerprints === []) {
                continue;
            }
            $declaracoes[] = [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target'   => [
                    'namespace'                => 'android_app',
                    'package_name'             => $package,
                    'sha256_cert_fingerprints' => $fingerprints,
                ],
            ];
        }

        abort_if($declaracoes === [], 404);

        return response()->json($declaracoes, 200, [], JSON_UNESCAPED_SLASHES);
    }

    public function appleAppSiteAssociation(): JsonResponse
    {
        $appId = trim((string) config('pontowr2.app_links.ios_app_id'));
        abort_if($appId === '', 404);

        $components = array_map(fn ($p) => ['/' => $p], (array) config('pontowr2.app_links.paths', []));

        return response()->json([
            'applinks' => ['details' => [['appIDs' => [$appId], 'components' => $components]]],
        ], 200, [], JSON_UNESCAPED_SLASHES);
    }
}

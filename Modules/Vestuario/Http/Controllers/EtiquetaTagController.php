<?php

declare(strict_types=1);

namespace Modules\Vestuario\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Vestuario\Services\EtiquetaTagService;

/**
 * EtiquetaTagController — geração lote de etiquetas TAG vestuário (US-VEST-020).
 *
 * 3 endpoints:
 * - GET  /vestuario/etiquetas              → Page Inertia (seletor produto+variação+copies)
 * - POST /vestuario/etiquetas/lote/zpl     → ZPL string pronta pra enviar TCP/USB pra impressora
 * - POST /vestuario/etiquetas/lote/pdf     → PDF A4 com grid 4×8 etiquetas (download)
 *
 * Multi-tenant: business_id derivado da session web ([ADR 0093](memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
 * Permissions: vestuario.etiqueta.view / vestuario.etiqueta.create.
 *
 * @see Modules/Vestuario/Services/EtiquetaTagService.php
 * @see memory/requisitos/Vestuario/RUNBOOK-etiqueta-tag.md
 */
class EtiquetaTagController extends Controller
{
    public function __construct(
        private EtiquetaTagService $svc,
    ) {
    }

    /**
     * Render seletor de etiquetas (Page Inertia).
     */
    public function index(Request $request): InertiaResponse
    {
        $this->authorizeAccess($request, 'view');

        return Inertia::render('Vestuario/Etiquetas/Index', [
            'config' => $this->safePublicConfig(),
        ]);
    }

    /**
     * Gera ZPL pra lote selecionado. Retorna string ZPL pra cliente baixar/enviar.
     *
     * Body:
     *   items: [{product_id, variation_id?, nome?, tamanho?, cor?, colecao?, preco?, sku?, ean13?}]
     *   copies?: int (default 1 — multiplica todas as etiquetas)
     */
    public function storeZpl(Request $request): Response
    {
        $this->authorizeAccess($request, 'create');

        $validated = $request->validate([
            'items'                  => 'required|array|min:1|max:500',
            'items.*.product_id'     => 'required|integer|min:1',
            'items.*.variation_id'   => 'nullable|integer|min:1',
            'items.*.nome'           => 'nullable|string|max:120',
            'items.*.tamanho'        => 'nullable|string|max:10',
            'items.*.cor'            => 'nullable|string|max:30',
            'items.*.colecao'        => 'nullable|string|max:40',
            'items.*.preco'          => 'nullable|numeric|min:0|max:99999',
            'items.*.sku'            => 'nullable|string|max:40',
            'items.*.ean13'          => 'nullable|string|size:13',
            'copies'                 => 'nullable|integer|min:1|max:100',
        ]);

        $items  = $this->expandItems($validated['items'], (int) ($validated['copies'] ?? 1));
        $zpl    = $this->svc->gerarLote($items);

        $businessId = $this->businessIdFromSession();

        Log::info('vestuario.etiqueta.lote.zpl', [
            'business_id' => $businessId,
            'items_count' => count($items),
            'bytes'       => strlen($zpl),
        ]);

        return response($zpl, 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => sprintf('attachment; filename="etiquetas-%s.zpl"', date('Ymd-His')),
        ]);
    }

    /**
     * Gera PDF A4 com grid de etiquetas. Cada etiqueta vira <div> Blade com EAN-13 + QR PNG inline base64.
     *
     * Mesmo body que storeZpl. Resposta: PDF binário download.
     */
    public function storePdf(Request $request): Response
    {
        $this->authorizeAccess($request, 'create');

        $validated = $request->validate([
            'items'                  => 'required|array|min:1|max:500',
            'items.*.product_id'     => 'required|integer|min:1',
            'items.*.variation_id'   => 'nullable|integer|min:1',
            'items.*.nome'           => 'nullable|string|max:120',
            'items.*.tamanho'        => 'nullable|string|max:10',
            'items.*.cor'            => 'nullable|string|max:30',
            'items.*.colecao'        => 'nullable|string|max:40',
            'items.*.preco'          => 'nullable|numeric|min:0|max:99999',
            'items.*.sku'            => 'nullable|string|max:40',
            'items.*.ean13'          => 'nullable|string|size:13',
            'copies'                 => 'nullable|integer|min:1|max:100',
        ]);

        $rawItems = $this->expandItems($validated['items'], (int) ($validated['copies'] ?? 1));

        // Renderiza cada etiqueta pegando meta do service (preserva EAN-13 calc + truncate)
        $rendered = [];
        foreach ($rawItems as $item) {
            $r = $this->svc->gerarEtiqueta(
                $item['product_id'],
                $item['variation_id'] ?? 0,
                $item['opts'] ?? [],
            );
            $rendered[] = $r['meta'] + [
                'ean13' => $r['ean13'],
                'sku'   => $r['sku'],
            ];
        }

        $businessId = $this->businessIdFromSession();

        $pdf = Pdf::loadView('vestuario::etiquetas.pdf', [
            'etiquetas' => $rendered,
            'business_id' => $businessId,
        ])->setPaper('a4', 'portrait');

        Log::info('vestuario.etiqueta.lote.pdf', [
            'business_id' => $businessId,
            'items_count' => count($rendered),
        ]);

        return $pdf->download(sprintf('etiquetas-%s.pdf', date('Ymd-His')));
    }

    /**
     * Expande items×copies em lista plana pro service.
     *
     * @param  array<int, array> $items
     * @return array<int, array> shape: {product_id, variation_id, opts}
     */
    private function expandItems(array $items, int $copies): array
    {
        $expanded = [];
        foreach ($items as $item) {
            for ($i = 0; $i < max(1, $copies); $i++) {
                $expanded[] = [
                    'product_id'   => (int) $item['product_id'],
                    'variation_id' => (int) ($item['variation_id'] ?? 0),
                    'opts'         => array_filter([
                        'nome'    => $item['nome']    ?? null,
                        'tamanho' => $item['tamanho'] ?? null,
                        'cor'     => $item['cor']     ?? null,
                        'colecao' => $item['colecao'] ?? null,
                        'preco'   => $item['preco']   ?? null,
                        'sku'     => $item['sku']     ?? null,
                        'ean13'   => $item['ean13']   ?? null,
                    ], fn ($v) => $v !== null),
                ];
            }
        }
        return $expanded;
    }

    /**
     * Config etiqueta exposta pro frontend (sem expor SQL/internals).
     *
     * @return array{width_dots:int, height_dots:int, dpi:int, margin_dots:int, qr_enabled:bool}
     */
    private function safePublicConfig(): array
    {
        return $this->svc->getPublicConfig($this->businessIdFromSession());
    }

    private function businessIdFromSession(): ?int
    {
        return session('user.business_id') ?? session('business.id');
    }

    /**
     * Authz: usa Spatie Permission se carregado, senão exige usuário autenticado.
     * Wagner adiciona role/permission via UI /copiloto/admin/team quando ligar enforce.
     */
    private function authorizeAccess(Request $request, string $action): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(401, 'Não autenticado');
        }

        $perm = "vestuario.etiqueta.{$action}";
        if (method_exists($user, 'can') && ! $user->can($perm)) {
            // Permission pode não estar seedada ainda (Sprint 1) — log warning mas não bloqueia.
            // Sprint 3 vira hard-block quando RepairSettingsSeeder/PermissionsSeeder rodar.
            Log::warning('vestuario.etiqueta.permission_check_missing', [
                'user_id'    => $user->getAuthIdentifier(),
                'permission' => $perm,
                'action'     => $action,
            ]);
        }
    }
}

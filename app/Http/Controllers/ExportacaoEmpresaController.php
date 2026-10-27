<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Business;
use App\Jobs\ExportarDadosDaEmpresaJob;
use App\Models\ExportacaoEmpresa;
use App\Utils\Util;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * US-BKP-001 — o dono da empresa pede a exportação dos dados e baixa o zip.
 *
 * - Só o DONO (`business.owner_id`) pede e baixa: o pacote carrega PII (decisão do pedido 2026-10-27).
 * - Flag `backup.exportacao_empresa.enabled` desligada → 404 (a rota não existe para quem usa).
 * - O pedido e o download vão para o registro de atividades da empresa.
 * - Download só por link assinado que expira junto com o arquivo; pedido de outra empresa → 404.
 */
class ExportacaoEmpresaController extends Controller
{
    public function __construct(private readonly Util $util) {}

    public function store(): JsonResponse
    {
        $business = $this->autorizarDono();

        $pedido = ExportacaoEmpresa::daEmpresa($business->id)->where('status', 'pendente')->latest('id')->first()
            ?? ExportacaoEmpresa::create(['business_id' => $business->id, 'solicitado_por' => auth()->id(), 'status' => 'pendente']);

        if ($pedido->wasRecentlyCreated) {
            ExportarDadosDaEmpresaJob::dispatch($business->id, (int) $pedido->id);
            $this->util->activityLog($pedido, 'exportacao_solicitada', null, [], false, $business->id);
        }

        return response()->json($this->resumo($pedido), $pedido->wasRecentlyCreated ? 201 : 200);
    }

    public function show(int $id): JsonResponse
    {
        $business = $this->autorizarDono();

        return response()->json($this->resumo(ExportacaoEmpresa::daEmpresa($business->id)->findOrFail($id)));
    }

    public function baixar(int $id): StreamedResponse
    {
        $business = $this->autorizarDono();
        $pedido = ExportacaoEmpresa::daEmpresa($business->id)->where('status', 'pronto')->findOrFail($id);
        abort_if($pedido->expira_em === null || $pedido->expira_em->isPast(), 410, 'Link expirado. Peça uma nova exportação.');

        $pedido->update(['baixado_em' => now(), 'downloads' => $pedido->downloads + 1]);
        $this->util->activityLog($pedido, 'exportacao_baixada', null, ['user_id' => auth()->id()], false, $business->id);

        return Storage::disk(config('backup.exportacao_empresa.disco'))
            ->download($pedido->arquivo, "dados-empresa-{$business->id}-{$pedido->created_at->format('Y-m-d')}.zip");
    }

    private function autorizarDono(): Business
    {
        abort_unless(config('backup.exportacao_empresa.enabled'), 404);
        $business = Business::findOrFail((int) auth()->user()->business_id);
        abort_unless((int) $business->owner_id === (int) auth()->id(), 403, 'Só o dono da empresa exporta os dados.');

        return $business;
    }

    private function resumo(ExportacaoEmpresa $pedido): array
    {
        $pronto = $pedido->status === 'pronto' && $pedido->expira_em && $pedido->expira_em->isFuture();

        return [
            'id' => $pedido->id,
            'status' => $pedido->status,
            'bytes' => $pedido->bytes,
            'expira_em' => $pedido->expira_em?->toIso8601String(),
            'link' => $pronto ? URL::temporarySignedRoute('backup.empresa.baixar', $pedido->expira_em, ['id' => $pedido->id]) : null,
        ];
    }
}

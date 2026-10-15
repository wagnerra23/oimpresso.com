<?php

namespace Modules\Producao\Http\Controllers;

use App\Services\AppLojas\EtiquetaProducao;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Milon\Barcode\DNS2D;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF A4 da OS com o QR da etiqueta (PR 07 do playbook propostas-app · modelo `a4_os` da E05,
 * thread 05 do PCP). O QR leva `EtiquetaProducao::codigo()` — o mesmo formato que o app e a tela
 * do operador leem — e abre "escolha sua etapa" com as etapas da OS listadas abaixo dele.
 *
 * Fora daqui (thread 05): etiqueta térmica 100×50 por etapa, ajuste `etiqueta_modelo` e o QR na
 * etiqueta da Assistência. Escopo por empresa (ADR 0093): OS de outra empresa → 404.
 */
class EtiquetaOsController extends Controller
{
    public function show(Request $request, int $id): Response
    {
        abort_unless($request->user()->can('producao.access'), 403);
        $biz = (int) $request->session()->get('user.business_id');

        $os = DB::table('comvis_os')->where('business_id', $biz)->where('id', $id)->whereNull('deleted_at')
            ->first(['id', 'numero', 'orcamento_id', 'data_prazo']);
        abort_if($os === null, 404);

        $codigo = EtiquetaProducao::codigo($biz, (int) $os->id, EtiquetaProducao::OS_TIPO_PADRAO);
        $html = view()->file(__DIR__ . '/../../Resources/views/etiqueta-os.blade.php', [
            'os' => $os,
            'cliente' => $this->cliente($biz, $os->orcamento_id),
            'etapas' => $this->etapas($biz, (int) $os->id),
            'codigo' => $codigo,
            'qrPng' => app(DNS2D::class)->getBarcodePNG($codigo, 'QRCODE', 6, 6),
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4', 'portrait')
            ->stream(sprintf('etiqueta-%s.pdf', preg_replace('/[^A-Za-z0-9-]/', '', (string) $os->numero) ?: $os->id));
    }

    private function cliente(int $biz, ?int $orcamentoId): ?string
    {
        if ($orcamentoId === null) {
            return null;
        }

        return DB::table('comvis_orcamentos as o')
            ->join('contacts as c', fn ($j) => $j->on('c.id', '=', 'o.contato_id')->on('c.business_id', '=', 'o.business_id'))
            ->where('o.business_id', $biz)->where('o.id', $orcamentoId)->value('c.name');
    }

    /** @return list<array{posto: string, responsavel: ?string}> */
    private function etapas(int $biz, int $osId): array
    {
        return DB::table('pcp_programacoes as p')
            ->leftJoin('pcp_postos as po', fn ($j) => $j->on('po.id', '=', 'p.posto_id')->on('po.business_id', '=', 'p.business_id'))
            ->leftJoin('users as u', fn ($j) => $j->on('u.id', '=', 'p.user_id')->on('u.business_id', '=', 'p.business_id'))
            ->where('p.business_id', $biz)->where('p.os_tipo', EtiquetaProducao::OS_TIPO_PADRAO)->where('p.os_id', $osId)
            ->orderBy('p.inicio_previsto')->orderBy('p.id')
            ->get(['po.nome as posto', 'u.first_name as responsavel'])
            ->map(fn ($e) => ['posto' => (string) ($e->posto ?? 'Etapa'), 'responsavel' => $e->responsavel])
            ->all();
    }
}

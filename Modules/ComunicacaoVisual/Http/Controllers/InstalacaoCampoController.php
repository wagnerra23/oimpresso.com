<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\ComunicacaoVisual\Services\ComprovanteInstalacao;

/**
 * InstalacaoCampoController — TELA do instalador no celular (US-COMVIS-007 · UC-PCV-25).
 *
 *   GET /comunicacao-visual/instalacoes/{id} → ComunicacaoVisual/Instalacao
 *
 * A tela só LÊ por aqui. Foto, assinatura e concluir vão pela API do comprovante
 * (InstalacaoComprovanteController), que é quem decide o que pode — a tela mostra a recusa.
 *
 * Props (todas eager: é uma instalação só, e a tela no celular precisa de tudo no primeiro paint):
 *   - instalacao    OS, cliente, endereço do cliente, início/fim, equipe, veículo e estado
 *   - comprovante   ComprovanteInstalacao::estado (o que já foi colhido e se pode concluir)
 *   - podeRegistrar comvis.os.update_status (ou superadmin) — a API confere de novo
 *
 * Tier 0 (ADR 0093): business_id vem da sessão; instalação de outra empresa = 404.
 */
class InstalacaoCampoController extends Controller
{
    public function __construct(private readonly ComprovanteInstalacao $comprovante) {}

    public function show(int $id): Response
    {
        $user = auth()->user();
        $superadmin = $user !== null && $user->can('superadmin');
        if (! $superadmin && ! ($user?->can('comvis.os.view') || $user?->can('comvis.os.update_status'))) {
            abort(403, 'Sem permissão na instalação (comvis.os.view ou comvis.os.update_status).');
        }

        $businessId = (int) (session('user.business_id') ?? session('business.id'));
        $linha = DB::table('cv_instalacoes as i')
            ->leftJoin('comvis_os as o', fn ($j) => $j->on('o.id', '=', 'i.os_id')->where('o.business_id', $businessId))
            ->leftJoin('comvis_orcamentos as orc', fn ($j) => $j->on('orc.id', '=', 'o.orcamento_id')->where('orc.business_id', $businessId))
            ->leftJoin('contacts as c', fn ($j) => $j->on('c.id', '=', 'orc.contato_id')->where('c.business_id', $businessId))
            ->where('i.business_id', $businessId)
            ->whereNull('i.deleted_at')
            ->where('i.id', $id)
            ->first(['i.id', 'o.numero as os_numero', 'c.name as cliente', 'c.address_line_1', 'c.address_line_2',
                'c.city', 'c.state', 'i.data_agendada', 'i.data_fim', 'i.equipe_user_ids_json', 'i.veiculo', 'i.status']);
        abort_if($linha === null, 404, 'Instalação não encontrada.');

        $equipe = array_map('intval', json_decode((string) $linha->equipe_user_ids_json, true) ?: []);
        $nomes = DB::table('users')->where('business_id', $businessId)->whereIn('id', $equipe)
            ->get(['first_name', 'last_name'])->map(fn ($u) => trim($u->first_name.' '.$u->last_name))->values();
        $endereco = implode(' — ', array_filter([
            implode(', ', array_filter([$linha->address_line_1, $linha->address_line_2])),
            implode('/', array_filter([$linha->city, $linha->state])),
        ]));

        try {
            $comprovante = $this->comprovante->estado($businessId, $id);
        } catch (ModelNotFoundException) {
            abort(404, 'Instalação não encontrada.');
        }

        return Inertia::render('ComunicacaoVisual/Instalacao', [
            'instalacao' => [
                'id' => (int) $linha->id,
                'os_numero' => $linha->os_numero,
                'cliente' => $linha->cliente,
                'endereco' => $endereco !== '' ? $endereco : null,
                'inicio' => $linha->data_agendada !== null ? Carbon::parse($linha->data_agendada)->toIso8601String() : null,
                'fim' => $linha->data_fim !== null ? Carbon::parse($linha->data_fim)->toIso8601String() : null,
                'equipe' => $nomes,
                'veiculo' => $linha->veiculo,
                'status' => $linha->status,
            ],
            'comprovante' => $comprovante,
            'podeRegistrar' => $superadmin || (bool) $user?->can('comvis.os.update_status'),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Producao\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Producao\Services\LinhaDaProducao;
use Modules\Producao\Services\PainelDaProducao;

/**
 * Linha do tempo por máquina (thread 07 do playbook · UC-PCV-08, 09, 30). Ver: `producao.access` +
 * módulo no pacote. Reprogramar e aplicar a sugestão de gargalo: `producao.etapa.mover`, conferida de
 * novo no `PUT /producao/etapas/{id}/reprogramar` (a flag aqui só esconde o arrastar).
 *
 * `?dia=proximo` mostra o próximo dia útil do calendário de produção (UC-FLX-06). O filtro do Quadro
 * vem na mesma URL (UC-PCV-06). A empresa vem da sessão (ADR 0093).
 */
class LinhaController extends Controller
{
    public function __construct(private readonly LinhaDaProducao $linha, private readonly PainelDaProducao $painel, private readonly ModuleUtil $moduleUtil) {}

    public function index(Request $request): Response
    {
        $biz = (int) $request->session()->get('user.business_id');
        $user = $request->user();
        $pacote = $user->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($biz, 'producao_module');
        abort_unless($biz > 0 && $pacote && $user->can('producao.access'), 403);

        $dias = $this->linha->dias($biz, now());
        $qual = $request->query('dia') === 'proximo' ? 'proximo' : 'hoje';
        $dia = Carbon::parse($dias[$qual]);
        $so = in_array($request->query('so'), ['atrasadas', 'arte'], true) ? $request->query('so') : 'todas';
        $texto = fn (string $k, int $max) => is_string($v = $request->query($k)) ? mb_substr(trim($v), 0, $max) : '';

        return Inertia::render('Producao/Linha/Index', [
            'hoje' => $dias['hoje'],
            'dias' => ['hoje' => $dias['hoje'], 'proximo' => $dias['proximo']],
            'dia' => $qual,
            'turno' => $dias['turno'],
            'filtro' => ['so' => $so, 'q' => $texto('q', 80), 'resp' => $texto('resp', 120), 'setor' => $texto('setor', 60)],
            'pode_mover' => $user->can('producao.etapa.mover'),
            'linha' => Inertia::defer(fn () => $this->linha->montar($biz, $dia, $dias['turno']['fim'])),
            'eventos' => Inertia::defer(fn () => $qual === 'hoje' ? $this->painel->eventos($biz, 12, $dia) : []),
        ]);
    }
}

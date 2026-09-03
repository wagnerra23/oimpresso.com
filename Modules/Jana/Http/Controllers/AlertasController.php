<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Jana\Http\Requests\UpdateAlertasConfigRequest;
use Modules\Jana\Notifications\MetaDesvioNotification;
use Modules\Jana\Services\AlertaService;

/**
 * Alertas de desvio de meta — `/ia/alertas` (aba Alertas da área Jana).
 *
 * "STUB spec-ready" saiu em 2026-09-02: o `index()` virou a lista consolidada que
 * o Blade antigo dizia não existir. A CONTA é do `AlertaService::calcular()` — a
 * mesma que dispara a `MetaDesvioNotification` —, e a tela só filtra e formata.
 * Âncora: `prototipo-ui/cowork/jana-telas-novas.jsx` §`JmAlertas`.
 */
class AlertasController extends Controller
{
    public function index(Request $request, AlertaService $alertas)
    {
        $businessId   = (int) $request->session()->get('user.business_id');
        $businessName = (string) ($request->session()->get('business.name') ?? '');
        $user         = auth()->user();

        // novo|lido vem do sino do usuário LOGADO: é o único "lido" que existe.
        // O listener notifica `criada_por_user_id`; quem não criou a meta vê `novo`.
        $lidas = [];
        if ($user) {
            foreach ($user->notifications()->where('type', MetaDesvioNotification::class)->get() as $n) {
                $metaId = (int) ($n->data['meta_id'] ?? 0);
                if ($metaId && ! isset($lidas[$metaId])) {
                    $lidas[$metaId] = $n->read_at?->toIso8601String();
                }
            }
        }

        return Inertia::render('Jana/Alertas', [
            'alertas' => $alertas->listar($businessId, $lidas),
            'corte'   => (float) config('copiloto.alertas.desvio_threshold_default', 10),
            'janaContext' => [
                'businessId'   => $businessId,
                'businessName' => $businessName,
                'userName'     => optional($user)->name,
            ],
        ]);
    }

    public function config()
    {
        return view('copiloto::alertas.config');
    }

    /**
     * A gravação NÃO está implementada — e a mensagem de retorno não pode dizer que
     * está. Persistir (em `business.essentials_settings` ou tabela dedicada) e ligar
     * no `AlertaService` é a US-COPI-061; até lá o formulário fica desabilitado na
     * view e esta rota devolve o que de fato aconteceu: nada.
     *
     * O FormRequest continua validando a whitelist (D8.c Wave 17) de propósito —
     * o contrato de entrada não deve regredir enquanto a persistência não chega.
     */
    public function updateConfig(UpdateAlertasConfigRequest $request)
    {
        return redirect()->route('jana.alertas.config')
            ->with('status', 'Ainda não é possível salvar a configuração de alertas — nada foi alterado.');
    }
}

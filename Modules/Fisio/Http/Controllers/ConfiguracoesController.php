<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fisio\Services\ConfiguracaoClinica;
use Modules\Fisio\Services\Whatsapp\CanalDaClinica;
use Modules\Fisio\Services\Whatsapp\EnvioWhatsappFisio;

/**
 * Configurações › WhatsApp do Fisio (protótipo `ConfigWhatsApp.dc.html`).
 *
 * A clínica escolhe por onde saem link, programa e lembrete; o número, o driver e a conexão por QR
 * são do módulo `Whatsapp` (link para lá na tela). Escolher QR exige o aceite do aviso de risco,
 * gravado com quem, quando e a versão do termo. Ler exige `fisio.access`; salvar, `fisio.editar`.
 */
class ConfiguracoesController extends Controller
{
    public function whatsapp(Request $request, CanalDaClinica $canal, EnvioWhatsappFisio $envio): Response
    {
        $biz = (int) $request->session()->get('user.business_id');
        $numero = $envio->numeroDeSaida($biz);
        $ritmo = $canal->ritmo($biz);

        return Inertia::render('Fisio/Configuracoes/Whatsapp', [
            'canal' => $canal->escolha($biz),
            'aceite_qr' => $canal->aceiteQr($biz) !== null,
            'teto_por_hora' => $ritmo['teto'],
            'silencio' => ['inicio' => $ritmo['silencio_inicio'], 'fim' => $ritmo['silencio_fim']],
            'lembrete_horario' => (string) config('fisio.whatsapp.lembrete_horario', '08:30'),
            // Só o final do número: é o número da clínica, mas não precisa aparecer inteiro.
            'numero' => $numero === null ? null : [
                'final' => substr(preg_replace('/\D/', '', (string) $numero->display_phone) ?: '', -4),
                'oficial' => (string) $numero->driver === 'meta_cloud',
                'saude' => (string) $numero->driver_health,
            ],
            'ativo_agora' => $envio->canalAtivo($biz) !== null,
            'url_canais' => Route::has('atendimento.channels.index') && $request->user()?->can('whatsapp.settings.manage')
                ? route('atendimento.channels.index') : null,
            'pode_editar' => (bool) $request->user()?->can('fisio.editar'),
            'limites' => ['teto_min' => CanalDaClinica::TETO_MIN, 'teto_max' => CanalDaClinica::TETO_MAX],
        ]);
    }

    public function salvarWhatsapp(Request $request, ConfiguracaoClinica $config, CanalDaClinica $canal): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('fisio.editar'), 403);
        $biz = (int) $request->session()->get('user.business_id');
        $hora = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';

        $dados = $request->validate([
            'canal' => ['required', Rule::in(CanalDaClinica::CANAIS)],
            'aceite_qr' => ['nullable', 'boolean'],
            'teto_por_hora' => ['required', 'integer', 'min:'.CanalDaClinica::TETO_MIN, 'max:'.CanalDaClinica::TETO_MAX],
            'silencio.inicio' => ['required', $hora],
            'silencio.fim' => ['required', $hora],
        ]);

        if ($dados['canal'] === CanalDaClinica::QR && $canal->aceiteQr($biz) === null) {
            if (! ($dados['aceite_qr'] ?? false)) {
                return back()->withErrors(['aceite_qr' => 'Para usar o número conectado por QR, marque que entende o risco de bloqueio.']);
            }
            $canal->aceitarRiscoQr($biz, (int) $request->user()->id);
        }

        $autor = (int) $request->user()->id;
        $config->gravar($biz, 'whatsapp.canal', $dados['canal'], $autor);
        $config->gravar($biz, 'whatsapp.teto_por_hora', (int) $dados['teto_por_hora'], $autor);
        $config->gravar($biz, 'whatsapp.silencio', $dados['silencio'], $autor);

        return back()->with('success', 'Configuração do WhatsApp salva.');
    }
}

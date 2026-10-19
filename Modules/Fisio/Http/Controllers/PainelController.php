<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fisio\Services\AtencaoDetector;
use Modules\Fisio\Services\PainelFisio;
use Modules\Fisio\Services\Whatsapp\EnviosFalhosDoDia;

/**
 * Painel do profissional (US-FISIO-010, ADR 0437 §4 e §5): KPIs, fila "precisa de atenção"
 * e tabela de pacientes. As contas são dos calculadores puros, montadas por PainelFisio.
 *
 * Tudo que consulta banco vai por `Inertia::defer`: a página abre com o esqueleto e as props
 * chegam num partial reload. As três props saem do MESMO cálculo (memoizado por request), para
 * KPI, fila e tabela nunca discordarem.
 *
 * `envios_falhos` (T-09e): os WhatsApp de hoje que não chegaram, com link `wa.me` para a clínica
 * mandar à mão — lido do módulo Whatsapp, sem tabela própria (EnviosFalhosDoDia).
 *
 * Sem check-in nenhum (o portal US-FISIO-007 ainda não existe), a tela mostra estado vazio
 * honesto: nada é inventado aqui.
 */
class PainelController extends Controller
{
    public function index(Request $request, PainelFisio $painel): Response
    {
        $dados = null;
        $montar = function () use (&$dados, $painel): array {
            return $dados ??= $painel->montar();
        };

        return Inertia::render('Fisio/Painel', [
            'regras' => [
                'subida_minima_dor' => AtencaoDetector::SUBIDA_MINIMA_DOR,
                'limiar_dor_padrao' => (int) config('fisio.pain_alert_threshold_padrao', AtencaoDetector::LIMIAR_DOR_PADRAO),
                'dias_sem_registro' => AtencaoDetector::DIAS_SEM_REGISTRO,
            ],
            'kpis' => Inertia::defer(fn () => $montar()['kpis']),
            'atencao' => Inertia::defer(fn () => $montar()['atencao']),
            'pacientes' => Inertia::defer(fn () => $montar()['pacientes']),
            'envios_falhos' => Inertia::defer(fn () => app(EnviosFalhosDoDia::class)->listar(
                (int) $request->session()->get('user.business_id'),
                (bool) $request->user()?->can('fisio.editar'),
            )),
        ]);
    }
}

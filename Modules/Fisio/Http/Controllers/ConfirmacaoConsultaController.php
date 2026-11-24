<?php

declare(strict_types=1);

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Fisio\Entities\Appointment;
use Modules\Fisio\Entities\BookingSettings;
use Modules\Fisio\Jobs\EnviarConfirmacaoConsultaJob;
use Modules\Fisio\Services\Agenda\ConfirmacaoDaConsulta;
use Modules\Fisio\Services\FisioAcesso;

/**
 * Página de um toque da mensagem de confirmação (etapa 04 A6): `/fisio/consulta/{consulta}`.
 *
 * Pública e sem PIN: a rota exige assinatura (`signed`), o link vale só para aquele atendimento e
 * vence no horário da sessão. Abrir (GET) só mostra; responder é POST. Não mostra nada clínico:
 * clínica, profissional, dia e hora.
 */
class ConfirmacaoConsultaController extends Controller
{
    public function __construct(private readonly ConfirmacaoDaConsulta $confirmacao, private readonly FisioAcesso $acesso) {}

    public function show(Request $request, int $consulta): Response
    {
        $a = $this->atendimento($consulta);

        return Inertia::render('Fisio/Public/Confirmacao', $this->props($a, (string) $request->session()->get('fisio_confirmacao_resultado', '')));
    }

    public function responder(int $consulta, string $resposta): RedirectResponse
    {
        $a = $this->atendimento($consulta);
        $resultado = $this->confirmacao->responder($a, $resposta);

        return redirect()->to($this->confirmacao->link($a->fresh() ?? $a))->with('fisio_confirmacao_resultado', $resultado);
    }

    private function atendimento(int $id): Appointment
    {
        $a = $this->confirmacao->doLink($id);
        abort_unless($this->acesso->pacoteAtivo((int) $a->business_id), 404);

        return $a;
    }

    private function nomeDoProfissional(int $id): string
    {
        $u = DB::table('users')->where('id', $id)->first(['first_name', 'last_name']);

        return $u === null ? '' : trim(((string) $u->first_name).' '.((string) $u->last_name));
    }

    /** @return array<string, mixed> */
    private function props(Appointment $a, string $resultado): array
    {
        $aberta = $this->confirmacao->aberta($a);

        return [
            'clinica' => (string) DB::table('business')->where('id', (int) $a->business_id)->value('name'),
            'profissional' => $this->nomeDoProfissional((int) $a->professional_id),
            'quando' => EnviarConfirmacaoConsultaJob::quando($a->starts_at),
            'status' => (string) $a->status,
            'aberta' => $aberta,
            'prazo_horas' => (int) BookingSettings::daEmpresa((int) $a->business_id)->cancel_min_hours,
            'resultado' => $resultado !== '' ? $resultado : null,
            'acoes' => $aberta ? [
                'confirmar' => $this->confirmacao->linkDaResposta($a, 'confirmar'),
                'nao_vou' => $this->confirmacao->linkDaResposta($a, 'nao_vou'),
            ] : null,
        ];
    }
}

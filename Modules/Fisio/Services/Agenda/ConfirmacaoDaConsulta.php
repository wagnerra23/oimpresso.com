<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Agenda;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\URL;
use Modules\Fisio\Entities\Appointment;

/**
 * Confirmação da consulta pela mensagem do WhatsApp (etapa 04 A6).
 *
 * A mensagem leva um link ASSINADO para o atendimento, que abre uma página de um toque:
 * Confirmo · Não vou poder ir · Preciso remarcar. O link funciona em qualquer canal da clínica
 * (oficial, QR ou envio manual), não pede PIN e só serve para aquele atendimento. Ele vence no
 * horário da sessão.
 *
 * O que a página faz:
 *  - Confirmo: status `confirmado`, canal `whatsapp` (mesma regra do portal: confirmar de novo
 *    não muda nada).
 *  - Não vou poder ir: cancela pelo paciente só se ainda estiver dentro do prazo da política da
 *    clínica; fora do prazo NÃO cancela e pede para avisar a clínica (falta e cancelamento tardio
 *    são sempre decididos pela profissional, A8).
 *  - Preciso remarcar: não muda nada aqui; manda remarcar em "Minhas consultas" no link do
 *    portal, que pede o PIN. Escolher o novo horário direto nesta página é o próximo passo.
 *
 * A abertura (GET) nunca muda nada: o WhatsApp abre o link sozinho para gerar a prévia.
 *
 * Roda sem sessão: o atendimento é lido pelo id que viaja assinado, e o business é o dele.
 */
final class ConfirmacaoDaConsulta
{
    public function __construct(private readonly AgendaDoPaciente $agenda, private readonly AgendaService $servico) {}

    /**
     * Atendimentos da empresa que pedem a mensagem agora: ainda `agendado`, entre `minimo_horas`
     * e `horas_antes` à frente.
     *
     * @return list<Appointment>
     */
    public function pendentes(int $businessId, CarbonInterface $agora): array
    {
        $antes = (int) config('fisio.confirmacao.horas_antes', 24);
        $minimo = (int) config('fisio.confirmacao.minimo_horas', 2);

        // SUPERADMIN: job sem sessão — filtro explícito por business_id (ADR 0093).
        return Appointment::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('status', 'agendado')
            ->where('starts_at', '>', $agora->copy()->addHours($minimo))
            ->where('starts_at', '<=', $agora->copy()->addHours($antes))
            ->orderBy('starts_at')
            ->get()->all();
    }

    /** Link de um toque, assinado e válido até o início da sessão. */
    public function link(Appointment $a): string
    {
        return URL::temporarySignedRoute('fisio.confirmacao.show', $a->starts_at, ['consulta' => (int) $a->id]);
    }

    /**
     * Link assinado para a resposta (`confirmar` ou `nao_vou`), com a mesma validade.
     */
    public function linkDaResposta(Appointment $a, string $resposta): string
    {
        return URL::temporarySignedRoute('fisio.confirmacao.responder', $a->starts_at, ['consulta' => (int) $a->id, 'resposta' => $resposta]);
    }

    /** O atendimento do link. A assinatura já garantiu o id; o business vem do próprio registro. */
    public function doLink(int $id): Appointment
    {
        // SUPERADMIN: rota pública sem sessão; o id viaja assinado e só ele é lido.
        return Appointment::withoutGlobalScopes()->findOrFail($id);
    }

    /** Ainda dá para responder pela mensagem? */
    public function aberta(Appointment $a): bool
    {
        return in_array($a->status, ['agendado', 'confirmado'], true) && $a->starts_at->isFuture();
    }

    /**
     * Aplica a resposta. Devolve o resultado mostrado ao paciente:
     * `confirmado`, `cancelado`, `fora_do_prazo` ou `encerrada` (atendimento já não aceita resposta).
     */
    public function responder(Appointment $a, string $resposta): string
    {
        if (! $this->aberta($a)) {
            return 'encerrada';
        }

        if ($resposta === 'confirmar') {
            $this->agenda->confirmar($a, 'whatsapp');

            return 'confirmado';
        }

        if (! $this->agenda->dentroDoPrazo($a)) {
            return 'fora_do_prazo';
        }
        $this->servico->cancelar($a, 'paciente', 'pela mensagem de confirmação');

        return 'cancelado';
    }
}

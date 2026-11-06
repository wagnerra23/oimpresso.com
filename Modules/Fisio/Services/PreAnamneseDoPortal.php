<?php

declare(strict_types=1);

namespace Modules\Fisio\Services;

use App\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\PreAnamnese;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Notifications\SinalDeAlertaNotification;

/**
 * Pré-anamnese do portal (Lote 5, protótipo `PacientePreAnamnese.dc.html`): onde dói, há quanto
 * tempo, dor agora, o que piora/melhora e sinais de alerta. Quem chama (PortalController) já
 * passou pelos portões do portal (link, PIN e consentimento de teleatendimento).
 *
 *  - Uma por paciente: a segunda resposta não grava nada (UNIQUE no banco). Enviada, não muda
 *    nem some: o model `PreAnamnese` recusa UPDATE e DELETE.
 *  - Sinais de alerta: só as chaves da lista fechada `config('fisio.red_flags')` que o protótipo
 *    pergunta, com o MESMO texto que o paciente já vê no check-in. Sinal marcado avisa o
 *    profissional do programa e o responsável pelo paciente, como no check-in; sem nenhum dos
 *    dois, quem cadastrou o contato. Só avisa: não
 *    sugere conduta (ADR 0437 §5).
 *  - Regiões e faixas de tempo são RASCUNHO do protótipo, pendentes da revisão da responsável
 *    técnica (como o texto dos questionários, US-FISIO-011).
 *
 * Portal público, sem sessão: toda leitura e escrita filtra o business do paciente (ADR 0093).
 */
class PreAnamneseDoPortal
{
    /** RASCUNHO do protótipo — revisão da responsável técnica pendente. */
    public const REGIOES = ['pescoco', 'ombro_direito', 'ombro_esquerdo', 'costas_meio', 'lombar', 'quadril', 'joelho_direito', 'joelho_esquerdo', 'tornozelo_pe', 'corpo_todo'];

    /** RASCUNHO do protótipo — revisão da responsável técnica pendente. */
    public const DESDE = ['menos_1_mes', '1_a_3_meses', '3_a_12_meses', 'mais_1_ano'];

    /** Sinais que o protótipo pergunta, todos da lista fechada do check-in. */
    public const SINAIS = ['febre', 'dormencia_regiao_intima', 'perda_controle_esfincter', 'perda_forca_pernas', 'queda'];

    public function respondida(Patient $paciente): bool
    {
        return PreAnamnese::withoutGlobalScopes() // SUPERADMIN: portal público; filtra pelo business do paciente.
            ->where('business_id', (int) $paciente->business_id)
            ->where('patient_id', (int) $paciente->id)
            ->exists();
    }

    /** @return list<string> sinais perguntados que estão na lista fechada vigente */
    public function sinais(): array
    {
        return array_values(array_intersect(self::SINAIS, (array) config('fisio.red_flags', [])));
    }

    /**
     * @param  array{regioes: list<string>, desde: string, dor: int, piora_melhora?: ?string, sinais: list<string>}  $dados
     * @return bool true se gravou agora; false se já havia resposta
     */
    public function registrar(Patient $paciente, array $dados): bool
    {
        $sinais = array_values(array_unique($dados['sinais']));
        try {
            PreAnamnese::query()->create([
                'business_id' => (int) $paciente->business_id,
                'patient_id' => (int) $paciente->id,
                'body' => [
                    'regioes' => array_values(array_unique($dados['regioes'])),
                    'desde' => $dados['desde'],
                    'dor' => (int) $dados['dor'],
                    'piora_melhora' => $dados['piora_melhora'] ?? null,
                ],
                'red_flags' => $sinais,
                'channel' => 'portal',
            ]);
        } catch (QueryException $e) {
            if ($this->respondida($paciente)) {
                return false; // corrida com outro envio: o primeiro vale
            }
            throw $e;
        }

        if ($sinais !== []) {
            $this->avisar($paciente, $sinais);
        }

        return true;
    }

    /** @param  list<string>  $sinais */
    private function avisar(Patient $paciente, array $sinais): void
    {
        $biz = (int) $paciente->business_id;
        $programa = Program::withoutGlobalScopes() // SUPERADMIN: portal público; filtra pelo business do paciente.
            ->where('business_id', $biz)
            ->where('patient_id', (int) $paciente->id)
            ->where('status', Program::STATUS_ATIVO)
            ->latest('id')
            ->first();
        $ids = array_values(array_unique(array_filter([
            $programa?->created_by !== null ? (int) $programa->created_by : null,
            $paciente->responsible_user_id !== null ? (int) $paciente->responsible_user_id : null,
        ])));
        if ($ids === []) {
            // Antes da 1ª consulta é comum não haver programa nem responsável: avisa quem cadastrou o contato.
            $cadastrou = DB::table('contacts')->where('business_id', $biz)->where('id', (int) $paciente->contact_id)->value('created_by');
            $ids = $cadastrou !== null ? [(int) $cadastrou] : [];
        }
        if ($ids === []) {
            return;
        }
        $destinatarios = User::query()->whereIn('id', $ids)->where('business_id', $biz)->get();
        if ($destinatarios->isNotEmpty()) {
            Notification::send($destinatarios, new SinalDeAlertaNotification($biz, (int) $paciente->id, 0, $sinais, SinalDeAlertaNotification::ORIGEM_PRE_ANAMNESE));
        }
    }
}

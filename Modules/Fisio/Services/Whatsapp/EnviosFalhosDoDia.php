<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Whatsapp;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Jobs\EnviarLembreteDiarioJob;
use Spatie\Activitylog\Models\Activity;

/**
 * Fallback humano do WhatsApp do Fisio (etapa 02 T-09e; UC-FPN-09): os envios de hoje que não
 * chegaram ao paciente, cada um com o link `wa.me` pré-preenchido para a clínica mandar à mão.
 *
 * Sem tabela de envio do Fisio ([W] 2026-10-19, "o WhatsApp já está pronto no sistema"). Lê o que
 * já existe:
 *  - a reserva do dia que `EnvioWhatsappFisio::reservar` grava no `activity_log` (paciente, tipo, dia,
 *    horário de saída);
 *  - as mensagens que o `SendWhatsappMessageJob` do módulo Whatsapp grava em `whatsapp_messages`
 *    (cada tentativa da fila é uma linha; `sent`/`delivered`/`read` em qualquer uma = chegou).
 *
 * Três casos entram na lista:
 *  - `falhou`: a mensagem do Fisio só tem tentativas `failed` (ou ficou `queued` além da tolerância);
 *  - `nao_saiu`: há reserva, o horário de saída passou da tolerância e nenhuma mensagem foi gravada;
 *  - `sem_canal`: o lembrete do dia não teve reserva porque o número da clínica está desconectado
 *    ou banido agora (o job não grava nada nesse caso; a lista é a mesma do job, recalculada).
 *
 * Fora do escopo, por já ter saída: programa enviado com o canal caído recebe os links `wa.me` no
 * próprio clique (EnvioWhatsappController). Lembrete que não saiu porque o canal caiu às 08:30 e
 * voltou antes da consulta ao painel NÃO aparece — não há registro disso sem gravar uma linha nova.
 *
 * O link só é montado com consentimento de WhatsApp vigente e opt-in do contato (LGPD), e só para
 * quem pode enviar (`fisio.editar`) — o corpo do programa carrega o link do portal e o código.
 */
final class EnviosFalhosDoDia
{
    /** Minutos depois do horário previsto em que "ainda na fila" passa a contar como falha. */
    public const TOLERANCIA_MIN = 30;

    private const CHEGOU = ['sent', 'delivered', 'read'];

    public function __construct(private readonly EnvioWhatsappFisio $envio) {}

    /** @return list<array<string, mixed>> */
    public function listar(int $businessId, bool $podeEnviar): array
    {
        $hoje = $this->envio->hoje($businessId);
        $limite = now()->subMinutes(self::TOLERANCIA_MIN);
        $clinica = $this->envio->nomeDaClinica($businessId);
        $itens = [];
        $comReserva = [];

        foreach ($this->reservas($businessId, $hoje) as [$reserva, $paciente, $tipo]) {
            $comReserva[$tipo][(int) $paciente->id] = true;
            $bodies = $this->mensagens($businessId, $paciente, $tipo, Carbon::parse($reserva->created_at));
            $saida = (string) ($reserva->properties?->get('saida') ?? '');
            $saida = $saida !== '' ? Carbon::parse($saida) : Carbon::parse($reserva->created_at);

            $falhas = [];
            $detalhe = null;
            foreach ($bodies as $body => $tentativas) {
                if (array_intersect(array_column($tentativas, 'status'), self::CHEGOU) !== []) {
                    continue;
                }
                $ultima = end($tentativas);
                if ($ultima['status'] === 'failed' || Carbon::parse($ultima['created_at'])->lessThan($limite)) {
                    $falhas[] = $body;
                    $detalhe ??= $ultima['failed_reason'];
                }
            }

            if ($bodies === [] && $saida->lessThan($limite)) {
                $motivo = 'nao_saiu';
                // Lembrete se remonta; o programa não (o código do portal não é recuperável).
                $falhas = $tipo === EnvioWhatsappFisio::LEMBRETE
                    ? [MensagensFisio::lembrete($this->envio->primeiroNome($paciente), $clinica)]
                    : [];
            } elseif ($falhas !== []) {
                $motivo = 'falhou';
            } else {
                continue;
            }

            $itens[] = $this->item($paciente, $tipo, $motivo, $detalhe, $falhas, $podeEnviar);
        }

        if ($this->envio->canalAtivo($businessId) === null && now()->format('H:i') >= (string) config('fisio.whatsapp.lembrete_horario', '08:30')) {
            foreach (EnviarLembreteDiarioJob::elegiveisDoDia($businessId, $hoje) as $paciente) {
                // Sem consentimento o lembrete não sairia nem com canal: não é falha, não entra.
                if (isset($comReserva[EnvioWhatsappFisio::LEMBRETE][(int) $paciente->id])
                    || ! $this->envio->consentimentoVigente($paciente)
                    || $this->envio->telefoneDoPaciente($paciente) === null) {
                    continue;
                }
                $texto = MensagensFisio::lembrete($this->envio->primeiroNome($paciente), $clinica);
                $itens[] = $this->item($paciente, EnvioWhatsappFisio::LEMBRETE, 'sem_canal', null, [$texto], $podeEnviar);
            }
        }

        return $itens;
    }

    /** @return list<array{0: Activity, 1: Patient, 2: string}> */
    private function reservas(int $businessId, string $hoje): array
    {
        $tipos = [
            EnvioWhatsappFisio::descricao(EnvioWhatsappFisio::LEMBRETE, $hoje) => EnvioWhatsappFisio::LEMBRETE,
            EnvioWhatsappFisio::descricao(EnvioWhatsappFisio::PROGRAMA, $hoje) => EnvioWhatsappFisio::PROGRAMA,
        ];
        $pacientes = Patient::query()->where('business_id', $businessId)->get()->keyBy('id');
        if ($pacientes->isEmpty()) {
            return [];
        }

        // activity_log não tem escopo de empresa: o filtro é o subject — só pacientes desta empresa.
        return Activity::query()
            ->where('log_name', EnvioWhatsappFisio::LOG)
            ->where('subject_type', (new Patient())->getMorphClass())
            ->whereIn('subject_id', $pacientes->keys()->all())
            ->whereIn('description', array_keys($tipos))
            ->orderBy('id')
            ->get()
            ->map(fn (Activity $a) => [$a, $pacientes[(int) $a->subject_id], $tipos[$a->description]])
            ->all();
    }

    /**
     * Mensagens do Fisio gravadas pelo módulo Whatsapp desde a reserva, agrupadas por corpo (cada
     * tentativa da fila é uma linha), na ordem em que foram criadas.
     *
     * @return array<string, list<array{status: string, failed_reason: ?string, created_at: string}>>
     */
    private function mensagens(int $businessId, Patient $paciente, string $tipo, Carbon $desde): array
    {
        $fone = (string) DB::table('contacts')
            ->where('business_id', $businessId)
            ->where('id', (int) $paciente->contact_id)
            ->value('mobile');
        if (trim($fone) === '') {
            return [];
        }

        $linhas = DB::table('whatsapp_messages as m')
            ->join('whatsapp_conversations as c', function ($j) use ($businessId) {
                $j->on('c.id', '=', 'm.conversation_id')->where('c.business_id', $businessId);
            })
            ->where('m.business_id', $businessId)
            ->where('c.customer_phone', self::normalizar($fone))
            ->where('m.direction', 'outbound')
            ->where('m.created_at', '>=', $desde)
            ->orderBy('m.id')
            ->get(['m.body', 'm.status', 'm.failed_reason', 'm.created_at']);

        $porCorpo = [];
        foreach ($linhas as $l) {
            $body = (string) $l->body;
            if (MensagensFisio::pertenceAo($tipo, $body)) {
                $porCorpo[$body][] = ['status' => (string) $l->status, 'failed_reason' => $l->failed_reason, 'created_at' => (string) $l->created_at];
            }
        }

        return $porCorpo;
    }

    /** @param  list<string>  $textos */
    private function item(Patient $paciente, string $tipo, string $motivo, ?string $detalhe, array $textos, bool $podeEnviar): array
    {
        $para = $this->envio->telefoneDoPaciente($paciente);
        $semLink = match (true) {
            ! $this->envio->consentimentoVigente($paciente) || $para === null => 'sem_consentimento',
            ! $podeEnviar => 'sem_permissao',
            $textos === [] => 'reenviar_pela_ficha',
            default => null,
        };

        return [
            'patient_id' => (int) $paciente->id,
            'nome' => DB::table('contacts')->where('business_id', (int) $paciente->business_id)->where('id', (int) $paciente->contact_id)->value('name'),
            'tipo' => $tipo,
            'motivo' => $motivo,
            'detalhe' => $detalhe,
            'wa_me' => $semLink === null ? array_map(fn (string $t) => MensagensFisio::waMe((string) $para, $t), $textos) : [],
            'sem_link' => $semLink,
        ];
    }

    /** Mesmo formato de `whatsapp_conversations.customer_phone` (SendWhatsappMessageJob). */
    private static function normalizar(string $fone): string
    {
        $digitos = preg_replace('/\D/', '', $fone) ?? '';
        if (strlen($digitos) <= 11) {
            $digitos = '55'.$digitos;
        }

        return '+'.$digitos;
    }
}

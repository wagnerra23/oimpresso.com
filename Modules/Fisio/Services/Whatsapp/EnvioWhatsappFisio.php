<?php

declare(strict_types=1);

namespace Modules\Fisio\Services\Whatsapp;

use App\Contact;
use App\Scopes\ScopeByBusiness;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Consent;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Services\DiaLocal;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Spatie\Activitylog\Models\Activity;

/**
 * Regras do WhatsApp do Fisio (US-FISIO-009; ADR 0437 §13.1) em cima do módulo `Whatsapp` que já
 * existe. O Fisio NÃO tem canal, driver nem tabela de envio próprios:
 *
 *  - Canal: o número de saída padrão da empresa no módulo Whatsapp (`handles_outbound_default`,
 *    mesmo critério do `EnviarLinkAprovacaoWhatsappJob` da OficinaAuto). Driver, fallback, health
 *    check e alerta de canal caído são do módulo. A escolha `meta_cloud` × `whatsmeow` e o aceite de
 *    risco ficam nas configurações do módulo. Sem número ativo → envio manual pelo link `wa.me`.
 *  - Consentimento: `fisio_consents` purpose `whatsapp`, versão vigente de config, não revogado —
 *    e o opt-out geral do contato (`Contact::canReceiveWhatsappNotification`, LGPD).
 *  - Idempotência PERSISTIDA: cada envio grava uma linha em `activity_log` (subject = paciente,
 *    descrição com tipo e dia). Antes de enviar, procura essa linha sob um lock — rodar o job duas
 *    vezes, ou retry de fila, não manda duas vezes no mesmo dia.
 *  - Ritmo: teto por hora e intervalo aleatório (o módulo Whatsapp não limita envio de saída por
 *    número) e horário de silêncio, via `AgendaDeEnvio`.
 *
 * Roda em job (sem sessão): toda leitura filtra `business_id` explicitamente (ADR 0093).
 */
class EnvioWhatsappFisio
{
    public const LOG = 'fisio';

    public const PROGRAMA = 'programa';

    public const LEMBRETE = 'lembrete';

    /** Saúde em que o módulo Whatsapp não consegue entregar nem pelo fallback. */
    private const CAIDO = ['disconnected', 'banned'];

    /** Número de saída do módulo Whatsapp que atende o Fisio, ou null (sem canal ou canal caído). */
    public function canalAtivo(int $businessId): ?WhatsappBusinessPhone
    {
        $telefone = $this->numeroDeSaida($businessId);
        if ($telefone === null || in_array((string) $telefone->driver_health, self::CAIDO, true)) {
            return null;
        }

        return $telefone;
    }

    public function numeroDeSaida(int $businessId): ?WhatsappBusinessPhone
    {
        // SUPERADMIN: job sem sessão; filtro explícito por business_id — número de outra empresa nunca serve (ADR 0093).
        return WhatsappBusinessPhone::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $businessId)
            ->where('handles_outbound_default', true)
            ->orderBy('id')
            ->first();
    }

    public function consentimentoVigente(Patient $paciente): bool
    {
        // SUPERADMIN: job sem sessão; filtro explícito por business_id e paciente (ADR 0093).
        return Consent::query()->withoutGlobalScopes()
            ->where('business_id', (int) $paciente->business_id)
            ->where('patient_id', (int) $paciente->id)
            ->where('purpose', 'whatsapp')
            ->where('version', (string) config('fisio.consent_versions.whatsapp'))
            ->whereNull('revoked_at')
            ->exists();
    }

    /** Celular do paciente (o contact), ou null sem telefone, contato apagado ou opt-out de WhatsApp. */
    public function telefoneDoPaciente(Patient $paciente): ?string
    {
        $contato = Contact::query()
            ->where('business_id', (int) $paciente->business_id)
            ->where('id', (int) $paciente->contact_id)
            ->first();
        if ($contato === null || ! $contato->canReceiveWhatsappNotification()) {
            return null;
        }
        $fone = trim((string) ($contato->mobile ?? ''));

        return $fone !== '' ? $fone : null;
    }

    public function primeiroNome(Patient $paciente): string
    {
        $nome = (string) DB::table('contacts')
            ->where('business_id', (int) $paciente->business_id)
            ->where('id', (int) $paciente->contact_id)
            ->value('name');

        return trim(explode(' ', trim($nome))[0]);
    }

    public function nomeDaClinica(int $businessId): string
    {
        return (string) DB::table('business')->where('id', $businessId)->value('name');
    }

    /** Dia civil da clínica (fuso do business; padrão America/Sao_Paulo). */
    public function hoje(int $businessId): string
    {
        return DiaLocal::de(now(), $this->fuso($businessId));
    }

    public function fuso(int $businessId): string
    {
        $tz = (string) DB::table('business')->where('id', $businessId)->value('time_zone');

        return $tz !== '' ? $tz : DiaLocal::FUSO_PADRAO;
    }

    /**
     * Reserva o envio de `$tipo` do dia para o paciente e agenda o horário de saída. `$preparar`
     * roda dentro da reserva (ex.: emitir o link do portal) e pode desistir devolvendo null — aí
     * nada é gravado e a próxima tentativa envia. Devolve null se já houve envio hoje.
     *
     * @template T
     *
     * @param  callable(): (T|null)  $preparar
     * @param  array<string, mixed>  $extra
     * @return array{saida: Carbon, dados: T}|null
     */
    public function reservar(Patient $paciente, string $tipo, callable $preparar, array $extra = []): ?array
    {
        $biz = (int) $paciente->business_id;
        $dia = $this->hoje($biz);
        $descricao = self::descricao($tipo, $dia);

        return Cache::lock("fisio:wa:{$biz}:{$paciente->id}:{$tipo}:{$dia}", 30)->block(10, function () use ($paciente, $biz, $tipo, $dia, $descricao, $preparar, $extra) {
            if ($this->jaEnviado($paciente, $descricao)) {
                return null;
            }

            $dados = $preparar();
            if ($dados === null) {
                return null;
            }

            $saida = $this->proximaSaida($biz);
            activity(self::LOG)
                ->performedOn($paciente)
                ->event('whatsapp_'.$tipo)
                ->withProperties(array_merge($extra, [
                    'business_id' => $biz,
                    'tipo' => $tipo,
                    'dia' => $dia,
                    'saida' => $saida->toIso8601String(),
                ]))
                ->log($descricao);

            return ['saida' => $saida, 'dados' => $dados];
        });
    }

    public static function descricao(string $tipo, string $dia): string
    {
        return "fisio.whatsapp.{$tipo} {$dia}";
    }

    private function jaEnviado(Patient $paciente, string $descricao): bool
    {
        return Activity::query()
            ->where('log_name', self::LOG)
            ->where('subject_type', $paciente->getMorphClass())
            ->where('subject_id', (int) $paciente->id)
            ->where('description', $descricao)
            ->exists();
    }

    /** Próximo horário livre no número da clínica (teto por hora, intervalo aleatório, silêncio). */
    private function proximaSaida(int $businessId): Carbon
    {
        $agora = now();
        $agendados = Activity::query()
            ->where('log_name', self::LOG)
            ->where('business_id', $businessId)
            ->whereIn('event', ['whatsapp_'.self::PROGRAMA, 'whatsapp_'.self::LEMBRETE])
            ->where('created_at', '>=', $agora->copy()->subDay())
            ->pluck('properties')
            ->map(function ($p) {
                $saida = (is_array($p) ? $p : (array) json_decode((string) json_encode($p), true))['saida'] ?? null;

                return is_string($saida) ? new DateTimeImmutable($saida) : null;
            })
            ->filter()
            ->values()
            ->all();

        $cfg = (array) config('fisio.whatsapp', []);
        $slot = AgendaDeEnvio::proximo(
            $agora->toDateTimeImmutable(),
            $agendados,
            [
                'teto' => (int) ($cfg['teto_por_hora'] ?? 30),
                'intervalo_min' => (int) ($cfg['intervalo_min_segundos'] ?? 20),
                'intervalo_max' => (int) ($cfg['intervalo_max_segundos'] ?? 90),
                'silencio_inicio' => (string) ($cfg['silencio']['inicio'] ?? '21:00'),
                'silencio_fim' => (string) ($cfg['silencio']['fim'] ?? '07:00'),
                'com_teto' => true,
            ],
            $this->fuso($businessId),
            fn (int $min, int $max): int => random_int($min, $max),
        );

        return Carbon::instance($slot);
    }
}
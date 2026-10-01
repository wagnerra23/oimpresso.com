<?php

declare(strict_types=1);

namespace Modules\Arquivos\Services;

use App\Contact;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Arquivos\Entities\Arquivo;

/**
 * AvisoTitularService — aviso ao titular antes do vencimento (LGPD Art. 18 VI).
 *
 * Decisão: ADR 0421 ([W] 2026-10-01 — D5 aprovada). Thread 05 / PR-9 do playbook Arquivos.
 *
 * Faz DUAS coisas, e só elas:
 *   - `elegiveis()` — LEITURA. Lista quem está na janela de aviso.
 *   - `registrarAviso()` — grava `titular_avisado_at` + linha `notice` na trilha.
 *
 * O que ele NÃO faz, por desenho:
 *   - **Não envia nada.** O canal (e-mail, WhatsApp, Notification) é decisão [W] pendente
 *     (ADR 0421 §Pendências). Quem enviar chama `registrarAviso()` DEPOIS de enviar, passando
 *     o canal usado. Gravar "avisado" sem ter avisado seria registro falso — por isso nenhum
 *     job/comando/rota chama este service hoje.
 *   - **Não apaga, não expira, não purga.** Não toca `deleted_at`, não chama o
 *     `ArquivosRetentionService` nem o `RetentionCleanupCommand`.
 *
 * Regras (ficha 05):
 *   - só `bucket = sensitive`;
 *   - só com titular identificado — ver TIPOS_COM_TITULAR;
 *   - janela = os JANELA_DIAS anteriores ao vencimento (vencido fica fora: o aviso é prévio);
 *   - vencimento = `created_at` + prazo, com o prazo resolvido igual à tela do acervo
 *     (`ArquivosAdminController::linha`): `retention_days` da linha → policy do
 *     `sub_destination` → `retention_days_default`.
 *
 * Multi-tenant Tier 0 (ADR 0093): `business_id` é argumento explícito em toda query, além do
 * global scope do model. Nada aqui cruza tenant.
 */
class AvisoTitularService
{
    /** `notice_period_days` de `Config/retention.php` (o shim não é registrado em config). */
    public const JANELA_DIAS = 30;

    /**
     * Donos (arquivable_type) cujo registro É a pessoa titular dos dados.
     *
     * Critério conservador, declarado na ADR 0421: só o cadastro de pessoa do ERP. Ticket,
     * OS e venda têm uma pessoa por trás, mas o vínculo não é direto — ampliar é decisão [W].
     */
    public const TIPOS_COM_TITULAR = [Contact::class];

    /**
     * Arquivos na janela de aviso, ainda não avisados. Só leitura.
     *
     * @return Collection<int, array{id:int, vence_em:string, dias_restantes:int<1, 30>}>
     */
    public function elegiveis(int $businessId, ?Carbon $agora = null): Collection
    {
        $hoje = ($agora ?? Carbon::now())->copy()->startOfDay();

        return Arquivo::query()
            ->where('business_id', $businessId)
            ->where('bucket', 'sensitive')
            ->whereNull('deleted_at')
            ->whereNull('titular_avisado_at')
            ->whereIn('arquivable_type', self::TIPOS_COM_TITULAR)
            ->whereNotNull('arquivable_id')
            ->orderBy('id')
            ->limit(1000)
            ->get(['id', 'business_id', 'sub_destination', 'retention_days', 'created_at'])
            ->map(fn (Arquivo $a) => $this->janela($a, $hoje))
            ->filter(fn (array $j) => $j['dias_restantes'] > 0 && $j['dias_restantes'] <= self::JANELA_DIAS)
            ->values();
    }

    /**
     * Grava que o titular foi avisado. Chame SÓ depois de o aviso ter saído pelo canal.
     *
     * Idempotente: já avisado → false, sem nova linha. Fora das regras → false.
     * Coluna e trilha na mesma transação: ou as duas, ou nenhuma.
     */
    public function registrarAviso(int $businessId, int $arquivoId, string $canal, ?int $userId = null): bool
    {
        $canal = trim($canal);
        if ($canal === '') {
            throw new \InvalidArgumentException('Canal do aviso é obrigatório.');
        }

        $arquivo = Arquivo::query()
            ->where('business_id', $businessId)
            ->whereKey($arquivoId)
            ->whereNull('deleted_at')
            ->first(['id', 'business_id', 'bucket', 'arquivable_type', 'arquivable_id', 'sub_destination', 'retention_days', 'created_at', 'titular_avisado_at']);

        if ($arquivo === null
            || $arquivo->bucket !== 'sensitive'
            || ! in_array($arquivo->arquivable_type, self::TIPOS_COM_TITULAR, true)
            || $arquivo->arquivable_id === null) {
            return false;
        }

        $janela = $this->janela($arquivo, Carbon::now()->startOfDay());

        return DB::transaction(function () use ($arquivo, $businessId, $canal, $userId, $janela) {
            $agora = Carbon::now();

            // UPDATE condicional: só a primeira chamada vence (idempotência sob concorrência).
            $mudou = DB::table('arquivos')
                ->where('id', $arquivo->id)
                ->where('business_id', $businessId)
                ->whereNull('titular_avisado_at')
                ->update(['titular_avisado_at' => $agora]);

            if ($mudou !== 1) {
                return false;
            }

            // Sem PII: nem nome de arquivo, nem caminho, nem dado do titular.
            DB::table('arquivos_audit_log')->insert([
                'arquivo_id'  => $arquivo->id,
                'business_id' => $businessId,
                'user_id'     => $userId,
                'action'      => 'notice',
                'payload'     => json_encode([
                    'canal'          => $canal,
                    'vence_em'       => $janela['vence_em'],
                    'dias_restantes' => $janela['dias_restantes'],
                ]),
                'created_at'  => $agora,
            ]);

            return true;
        });
    }

    /**
     * @return array{id:int, vence_em:string, dias_restantes:int}
     */
    private function janela(Arquivo $a, Carbon $hoje): array
    {
        $dias = $a->retention_days ?: (int) (config('arquivos.retention_days_policy.' . $a->sub_destination)
            ?: config('arquivos.retention_days_default', 90));

        $vence = Carbon::parse($a->created_at)->addDays((int) $dias)->startOfDay();

        return [
            'id'             => (int) $a->id,
            'vence_em'       => $vence->toDateString(),
            'dias_restantes' => (int) $hoje->diffInDays($vence, false),
        ];
    }
}

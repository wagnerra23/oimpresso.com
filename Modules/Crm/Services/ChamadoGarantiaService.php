<?php

declare(strict_types=1);

namespace Modules\Crm\Services;

use App\Scopes\ScopeByBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Entities\Schedule;
use Modules\Crm\Entities\SlaPolitica;

/**
 * Chamado de garantia com SLA — US-CRM-094 (lacuna P3).
 *
 * O chamado é um acompanhamento do CRM marcado como garantia: herda responsáveis
 * (`crm_schedule_users`), a venda (`crm_followup_invoices`), o tipo (categoria de
 * acompanhamento), a lista e o histórico que já existem. O que este serviço acrescenta é o
 * prazo: vem da política do tipo em `crm_sla_politicas`, ou da política padrão da empresa
 * (categoria nula), ou de `crm.garantia_sla.prazo_padrao_horas`.
 *
 * Fila e responsável seguem a mesma ordem: o que veio no pedido, senão a política, senão
 * quem abriu. Tudo atrás de `crm.garantia_sla.enabled`, desligada por padrão.
 */
final class ChamadoGarantiaService
{
    /**
     * @param  array{contact_id:int, relato:string, transaction_id?:int|null, repair_job_sheet_id?:int|null,
     *               followup_category_id?:int|null, fila?:string|null, responsavel_user_id?:int|null}  $dados
     */
    public function abrir(int $businessId, int $userId, array $dados, ?CarbonImmutable $agora = null): Schedule
    {
        if (! config('crm.garantia_sla.enabled')) {
            throw new \RuntimeException('Chamado de garantia desligado (CRM_GARANTIA_SLA_ENABLED).');
        }

        $agora ??= CarbonImmutable::now();
        $contactId = (int) $dados['contact_id'];
        $vendaId = ! empty($dados['transaction_id']) ? (int) $dados['transaction_id'] : null;
        $osId = ! empty($dados['repair_job_sheet_id']) ? (int) $dados['repair_job_sheet_id'] : null;
        $categoriaId = ! empty($dados['followup_category_id']) ? (int) $dados['followup_category_id'] : null;
        $relato = trim((string) $dados['relato']);

        $this->validar($businessId, $contactId, $vendaId, $osId, $categoriaId, $relato);

        $politica = $this->politica($businessId, $categoriaId);
        $prazoHoras = $politica?->prazo_horas ?? (int) config('crm.garantia_sla.prazo_padrao_horas', 48);
        $fila = ($dados['fila'] ?? null) ?: $politica?->fila;
        $responsavel = $this->responsavel($businessId, $dados['responsavel_user_id'] ?? null, $politica, $userId);
        $venceEm = $agora->addHours($prazoHoras);

        return DB::transaction(function () use ($businessId, $userId, $contactId, $vendaId, $osId, $categoriaId, $relato, $fila, $responsavel, $agora, $venceEm) {
            $chamado = Schedule::create([
                'business_id' => $businessId,
                'contact_id' => $contactId,
                'title' => 'Garantia: '.mb_strimwidth($relato, 0, 120, '…'),
                'description' => $relato,
                'status' => 'open',
                'schedule_type' => 'call',
                'followup_category_id' => $categoriaId,
                'start_datetime' => $agora,
                'end_datetime' => $venceEm,
                'allow_notification' => 0,
                'created_by' => $userId,
                'garantia' => true,
                'fila' => $fila,
                'sla_vence_em' => $venceEm,
                'repair_job_sheet_id' => $osId,
            ]);

            DB::table('crm_schedule_users')->insert([
                'schedule_id' => $chamado->id, 'user_id' => $responsavel,
                'created_at' => $agora, 'updated_at' => $agora,
            ]);
            if ($vendaId !== null) {
                DB::table('crm_followup_invoices')->insert(['follow_up_id' => $chamado->id, 'transaction_id' => $vendaId]);
            }

            return $chamado;
        });
    }

    private function validar(int $biz, int $contactId, ?int $vendaId, ?int $osId, ?int $categoriaId, string $relato): void
    {
        $erros = [];
        if (mb_strlen($relato) < 5) {
            $erros['relato'] = 'Descreva o defeito (mínimo 5 caracteres).';
        }
        if (! DB::table('contacts')->where('business_id', $biz)->where('id', $contactId)->exists()) {
            $erros['contact_id'] = 'Cliente não encontrado nesta empresa.';
        }
        if ($vendaId === null && $osId === null) {
            $erros['transaction_id'] = 'Ligue o chamado a uma venda ou a uma OS.';
        }
        if ($vendaId !== null && ! DB::table('transactions')->where('business_id', $biz)->where('id', $vendaId)
            ->where('type', 'sell')->where('contact_id', $contactId)->exists()) {
            $erros['transaction_id'] = 'Venda não encontrada para este cliente nesta empresa.';
        }
        if ($osId !== null && ! DB::table('repair_job_sheets')->where('business_id', $biz)->where('id', $osId)
            ->where('contact_id', $contactId)->exists()) {
            $erros['repair_job_sheet_id'] = 'OS não encontrada para este cliente nesta empresa.';
        }
        if ($categoriaId !== null && ! DB::table('categories')->where('business_id', $biz)->where('id', $categoriaId)
            ->where('category_type', 'followup_category')->exists()) {
            $erros['followup_category_id'] = 'Tipo de chamado não encontrado nesta empresa.';
        }

        if ($erros !== []) {
            throw ValidationException::withMessages($erros);
        }
    }

    private function politica(int $biz, ?int $categoriaId): ?SlaPolitica
    {
        $base = SlaPolitica::withoutGlobalScope(ScopeByBusiness::class)->where('business_id', $biz)->where('ativo', true);
        // SUPERADMIN: o serviço também roda fora de request (job/comando); o filtro explícito acima é o escopo.

        if ($categoriaId !== null) {
            $doTipo = (clone $base)->where('followup_category_id', $categoriaId)->first();
            if ($doTipo !== null) {
                return $doTipo;
            }
        }

        return $base->whereNull('followup_category_id')->first();
    }

    private function responsavel(int $biz, mixed $pedido, ?SlaPolitica $politica, int $quemAbriu): int
    {
        $daEmpresa = fn ($id) => DB::table('users')->where('business_id', $biz)->where('id', (int) $id)->exists();

        if (! empty($pedido)) {
            if (! $daEmpresa($pedido)) {
                throw ValidationException::withMessages(['responsavel_user_id' => 'Responsável não encontrado nesta empresa.']);
            }

            return (int) $pedido;
        }
        // Responsável da política que saiu da empresa cai para quem abriu, em vez de travar a abertura.
        if (! empty($politica?->responsavel_user_id) && $daEmpresa($politica->responsavel_user_id)) {
            return (int) $politica->responsavel_user_id;
        }

        return $quemAbriu;
    }
}

<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Repositories;

use App\Util\OtelHelper;
use Illuminate\Support\Carbon;
use Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface;
use Modules\Repair\Entities\JobSheet;
use Spatie\Activitylog\Models\Activity;

/**
 * RepairConsultaOsRepository — portal público lendo as folhas de OS reais do Repair.
 *
 * US-CONSULTA-001 (2026-10-02, decisão [W] "Ligar o ConsultaOs ao Repair"). Substitui o
 * mock de 4 OS fixas. Espelha a consulta de `CustomerRepairStatusController::postRepairStatus`
 * (já endurecida no #8527) e NÃO expõe mais do que ela expõe:
 *   nº da OS, marca, aparelho, modelo, nº de série, status com cor, data prevista e atividades.
 *
 * Tier 0 (ADR 0093) — leia antes de mexer:
 *  - A rota é pública. O ScopeByBusiness do JobSheet sai sem fazer nada quando não há
 *    usuário logado, então o ÚNICO filtro é o critério de busca. Sem tipo válido e número
 *    não vazio, devolve [] SEM consultar (revalida aqui, além do FormRequest).
 *  - O payload é montado campo a campo. Nada de `repair_job_sheets.*`: custo estimado,
 *    senha/padrão do aparelho, defeitos, checklist, notas internas, cliente (nome, CPF,
 *    endereço, e-mail) e business_id nunca saem daqui.
 *  - PENDENTE [W]: o portal não sabe de qual empresa é o cliente. Um nº de OS válido é
 *    procurado em TODAS as empresas (mesmo comportamento do /repair-status hoje). Como a
 *    numeração é sequencial por empresa, o mesmo nº pode casar OS de empresas diferentes.
 *    Fechar isso muda a URL do portal (identificar a empresa) — decisão do dono.
 *
 * Diferenças deliberadas em relação ao /repair-status (todas mais estritas):
 *  - nº da venda via `whereExists` (sem join que duplica linha);
 *  - busca por celular só quando `repair.enable_repair_check_using_mobile_num` está ligada
 *    (o POST do Repair aceita mesmo desligada);
 *  - no máximo LIMITE OS por resposta.
 */
class RepairConsultaOsRepository implements ConsultaOsRepositoryInterface
{
    public function buscar(string $tipo, string $numero, ?string $serie = null): array
    {
        $numero = trim($numero);
        $serie = $serie !== null ? trim($serie) : null;

        if (! in_array($tipo, self::tiposHabilitados(), true) || $numero === '') {
            return [];
        }

        return OtelHelper::span('consultaos.repository.lookup', [
            'repository_kind' => 'repair',
            'tipo' => $tipo,
            // Sem business_id: rota pública, a empresa não é conhecida (ver PENDENTE acima).
        ], function () use ($tipo, $numero, $serie) {
            $query = JobSheet::query()
                ->leftJoin('repair_statuses AS rs', 'repair_job_sheets.status_id', '=', 'rs.id')
                ->leftJoin('brands AS b', 'repair_job_sheets.brand_id', '=', 'b.id')
                ->leftJoin('repair_device_models AS rdm', 'rdm.id', '=', 'repair_job_sheets.device_model_id')
                ->leftJoin('categories AS device', 'device.id', '=', 'repair_job_sheets.device_id');

            if ($tipo === 'job_sheet_no') {
                $query->where('repair_job_sheets.job_sheet_no', $numero);
            } elseif ($tipo === 'invoice_no') {
                $query->whereExists(function ($sub) use ($numero) {
                    $sub->selectRaw('1')
                        ->from('transactions')
                        ->whereColumn('transactions.repair_job_sheet_id', 'repair_job_sheets.id')
                        ->where('transactions.invoice_no', $numero);
                });
            } else { // mobile_num
                $query->whereExists(function ($sub) use ($numero) {
                    $sub->selectRaw('1')
                        ->from('contacts')
                        ->whereColumn('contacts.id', 'repair_job_sheets.contact_id')
                        ->where('contacts.mobile', $numero);
                });
            }

            if ($serie !== null && $serie !== '') {
                $query->where('repair_job_sheets.serial_no', $serie);
            }

            $ordens = $query->select(
                'repair_job_sheets.id',
                'repair_job_sheets.job_sheet_no',
                'repair_job_sheets.serial_no',
                'repair_job_sheets.delivery_date',
                'rs.name AS status_nome',
                'rs.color AS status_cor',
                'b.name AS marca',
                'rdm.name AS modelo',
                'device.name AS aparelho',
            )
                ->orderByDesc('repair_job_sheets.id')
                ->limit(self::LIMITE)
                ->get();

            return $ordens->map(fn (JobSheet $os) => $this->publico($os))->values()->all();
        });
    }

    /** Tipos aceitos agora — celular depende da config do Repair. */
    public static function tiposHabilitados(): array
    {
        return array_values(array_filter(
            self::TIPOS,
            fn (string $t) => $t !== 'mobile_num' || (bool) config('repair.enable_repair_check_using_mobile_num'),
        ));
    }

    /**
     * Payload público de UMA OS — whitelist campo a campo.
     *
     * @return array<string, mixed>
     */
    private function publico(JobSheet $os): array
    {
        return [
            'numero' => (string) $os->job_sheet_no,
            'marca' => $os->getAttribute('marca'),
            'aparelho' => $os->getAttribute('aparelho'),
            'modelo' => $os->getAttribute('modelo'),
            'serie' => $os->serial_no,
            'status' => [
                'nome' => $os->getAttribute('status_nome'),
                'cor' => $os->getAttribute('status_cor'),
            ],
            'previsao_entrega' => $this->iso($os->getRawOriginal('delivery_date')),
            'atividades' => $this->atividades($os),
        ];
    }

    /**
     * Histórico público — as mesmas colunas do repair_activities.blade.php
     * (data, ação, quem, nota e mudança de conclusão). Sem `properties` cruas.
     *
     * @return list<array<string, mixed>>
     */
    private function atividades(JobSheet $os): array
    {
        return Activity::query()
            ->where('subject_type', $os->getMorphClass())
            ->where('subject_id', $os->getKey())
            ->where('description', '!=', 'is_sent_notification')
            ->with('causer')
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (Activity $a) {
                $acao = $a->description === 'status_changed'
                    ? __('repair::lang.status_changed_to', ['status' => $a->getExtraProperty('updated_status')])
                    : __('lang_v1.'.$a->description);

                return [
                    'data' => $this->iso($a->created_at),
                    'acao' => (string) $acao,
                    'por' => $a->causer?->user_full_name,
                    'nota' => $a->getExtraProperty('update_note') ?: null,
                    'conclusao_de' => $this->iso($a->getExtraProperty('completed_on_from')),
                    'conclusao_para' => $this->iso($a->getExtraProperty('completed_on_to')),
                ];
            })
            ->values()
            ->all();
    }

    private function iso(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->format('Y-m-d\TH:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}

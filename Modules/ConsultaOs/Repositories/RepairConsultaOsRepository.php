<?php

declare(strict_types=1);

namespace Modules\ConsultaOs\Repositories;

use App\Tenancy\ContextoEmpresa;
use App\Util\OtelHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\ConsultaOs\Contracts\ConsultaOsRepositoryInterface;
use Modules\Repair\Entities\JobSheet;
use Spatie\Activitylog\Models\Activity;

/**
 * RepairConsultaOsRepository — portal público lendo as folhas de OS reais do Repair.
 *
 * US-CONSULTA-001 (2026-10-02, decisão [W] "Ligar o ConsultaOs ao Repair"). Espelhava a consulta
 * do antigo `CustomerRepairStatusController::postRepairStatus` (removida no cutover de
 * 2026-10-27) e NÃO expõe mais do que ela expunha: nº da OS, marca, aparelho, modelo, nº de
 * série, status com cor, data prevista e atividades.
 *
 * Tier 0 (ADR 0093) — leia antes de mexer:
 *  - A rota é pública. A empresa vem do código na URL (/consulta-os/{codigo}, decisão [W]
 *    2026-11-17 "empresa na URL, celular só com série"), resolvido em EmpresaPublica antes de
 *    chegar aqui. Toda tabela de negócio lida filtra por ela explicitamente — repair_job_sheets,
 *    transactions e contacts — e a consulta roda dentro de ContextoEmpresa::comEmpresa
 *    (ADR 0453), para o global scope também filtrar quando o modo não for o aberto.
 *  - Sem empresa, tipo válido e número não vazio devolve [] SEM consultar (revalida aqui, além
 *    do FormRequest). Celular sem nº de série também devolve [].
 *  - O payload é montado campo a campo. Nada de `repair_job_sheets.*`: custo estimado,
 *    senha/padrão do aparelho, defeitos, checklist, notas internas, cliente (nome, CPF,
 *    endereço, e-mail) e business_id nunca saem daqui.
 *
 * Diferenças deliberadas em relação ao /repair-status antigo (todas mais estritas):
 *  - só a empresa do link, nunca todas (até 2026-11-17 o portal procurava em todas);
 *  - nº da venda via `whereExists` (sem join que duplica linha);
 *  - celular só com a config `repair.enable_repair_check_using_mobile_num` ligada E com série;
 *  - no máximo LIMITE OS por resposta;
 *  - quem mexeu na OS sai como primeiro nome + inicial do sobrenome ("Maria S."), nunca o nome
 *    completo (decisão [W] 2026-11-17). O nome completo segue no histórico interno da OS.
 */
class RepairConsultaOsRepository implements ConsultaOsRepositoryInterface
{
    public function buscar(int $businessId, string $tipo, string $numero, ?string $serie = null): array
    {
        $numero = trim($numero);
        $serie = $serie !== null ? trim($serie) : null;

        if ($businessId <= 0 || ! in_array($tipo, self::tiposHabilitados(), true) || $numero === '') {
            return [];
        }

        if ($tipo === 'mobile_num' && ($serie === null || $serie === '')) {
            return [];
        }

        return OtelHelper::span('consultaos.repository.lookup', [
            'repository_kind' => 'repair',
            'tipo' => $tipo,
            'business_id' => $businessId,
        ], fn () => app(ContextoEmpresa::class)->comEmpresa(
            $businessId,
            fn () => $this->consultar($businessId, $tipo, $numero, $serie),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function consultar(int $businessId, string $tipo, string $numero, ?string $serie): array
    {
        $query = JobSheet::query()
            ->leftJoin('repair_statuses AS rs', 'repair_job_sheets.status_id', '=', 'rs.id')
            ->leftJoin('brands AS b', 'repair_job_sheets.brand_id', '=', 'b.id')
            ->leftJoin('repair_device_models AS rdm', 'rdm.id', '=', 'repair_job_sheets.device_model_id')
            ->leftJoin('categories AS device', 'device.id', '=', 'repair_job_sheets.device_id')
            ->where('repair_job_sheets.business_id', $businessId);

        if ($tipo === 'job_sheet_no') {
            $query->where('repair_job_sheets.job_sheet_no', $numero);
        } elseif ($tipo === 'invoice_no') {
            $query->whereExists(function ($sub) use ($numero, $businessId) {
                $sub->selectRaw('1')
                    ->from('transactions')
                    ->whereColumn('transactions.repair_job_sheet_id', 'repair_job_sheets.id')
                    ->where('transactions.business_id', $businessId)
                    ->where('transactions.invoice_no', $numero);
            });
        } else { // mobile_num — só chega aqui com série (ver buscar())
            $query->whereExists(function ($sub) use ($numero, $businessId) {
                $sub->selectRaw('1')
                    ->from('contacts')
                    ->whereColumn('contacts.id', 'repair_job_sheets.contact_id')
                    ->where('contacts.business_id', $businessId)
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
    }

    /**
     * Tipos aceitos agora — celular depende da config do Repair.
     *
     * @return list<string>
     */
    public static function tiposHabilitados(): array
    {
        $tipos = [];
        foreach (self::TIPOS as $t) {
            if ($t !== 'mobile_num' || (bool) config('repair.enable_repair_check_using_mobile_num')) {
                $tipos[] = $t;
            }
        }

        return $tipos;
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
            ->map(function (Activity $a): array {
                $acao = $a->description === 'status_changed'
                    ? __('repair::lang.status_changed_to', ['status' => $a->getExtraProperty('updated_status')])
                    : __('lang_v1.'.$a->description);

                return [
                    'data' => $this->iso($a->created_at),
                    'acao' => (string) $acao,
                    'por' => self::nomePublico($a->causer),
                    'nota' => $a->getExtraProperty('update_note') ?: null,
                    'conclusao_de' => $this->iso($a->getExtraProperty('completed_on_from')),
                    'conclusao_para' => $this->iso($a->getExtraProperty('completed_on_to')),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Nome de quem mexeu na OS, como o público vê: primeiro nome + inicial do sobrenome
     * ("Maria S."). Sem o tratamento (`surname`, ex. "Sr.") e sem o sobrenome inteiro
     * (decisão [W] 2026-11-17). Sem primeiro nome devolve null e a tela omite o "· quem".
     */
    public static function nomePublico(?Model $pessoa): ?string
    {
        if ($pessoa === null) {
            return null;
        }

        $primeiro = preg_split('/\s+/u', trim((string) $pessoa->getAttribute('first_name')))[0] ?? '';
        if ($primeiro === '') {
            return null;
        }

        $sobrenome = trim((string) $pessoa->getAttribute('last_name'));

        return $sobrenome === '' ? $primeiro : $primeiro.' '.mb_strtoupper(mb_substr($sobrenome, 0, 1)).'.';
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

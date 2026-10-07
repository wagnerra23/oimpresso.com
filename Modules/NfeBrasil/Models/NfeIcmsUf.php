<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\NfeBrasil\Exceptions\AliquotaInternaNaoCadastradaException;

/**
 * Tabela ICMS/FCP por UF, curada (playbook Fiscal thread 09 · D-UF · R-NFE-021).
 *
 * Quem consulta a interna recebe o número cadastrado ou `AliquotaInternaNaoCadastradaException` —
 * nunca 0, nunca palpite (lei 4 do módulo). O motor ainda não lê esta tabela: ligar o fallback dele
 * é outra thread.
 *
 * @property int $id
 * @property int $business_id
 * @property string $uf_origem
 * @property string $uf_destino
 * @property float|null $aliquota_interestadual
 * @property float|null $aliquota_interna
 * @property float|null $fcp
 * @property \Illuminate\Support\Carbon|null $valida_de
 * @property \Illuminate\Support\Carbon|null $valida_ate
 * @property string|null $fonte
 */
class NfeIcmsUf extends Model
{
    use HasBusinessScope;

    protected $table = 'nfe_icms_uf';

    protected $fillable = [
        'business_id', 'uf_origem', 'uf_destino',
        'aliquota_interestadual', 'aliquota_interna', 'fcp',
        'valida_de', 'valida_ate', 'fonte',
    ];

    protected $casts = [
        'aliquota_interestadual' => 'float',
        'aliquota_interna'       => 'float',
        'fcp'                    => 'float',
        'valida_de'              => 'date:Y-m-d',
        'valida_ate'             => 'date:Y-m-d',
    ];

    /** Versão vigente na data (Y-m-d): NULL nas pontas = sem limite. */
    public function scopeVigenteEm(Builder $q, string $data): Builder
    {
        return $q->where(fn (Builder $w) => $w->whereNull('valida_de')->orWhere('valida_de', '<=', $data))
            ->where(fn (Builder $w) => $w->whereNull('valida_ate')->orWhere('valida_ate', '>=', $data));
    }

    /**
     * Alíquota interna (decimal) da UF de destino, para a empresa, na data.
     *
     * @throws AliquotaInternaNaoCadastradaException quando não há linha vigente ou a interna é NULL
     */
    public static function aliquotaInterna(int $businessId, string $ufOrigem, string $ufDestino, ?string $data = null): float
    {
        $linha = self::linhaVigente($businessId, $ufOrigem, $ufDestino, $data ?? now()->toDateString());

        if ($linha === null || $linha->aliquota_interna === null) {
            throw AliquotaInternaNaoCadastradaException::para(strtoupper($ufDestino));
        }

        return (float) $linha->aliquota_interna;
    }

    /** Alíquota interestadual semeada (decimal), ou null se origem = destino ou não houver linha. */
    public static function aliquotaInterestadual(int $businessId, string $ufOrigem, string $ufDestino, ?string $data = null): ?float
    {
        $linha = self::linhaVigente($businessId, $ufOrigem, $ufDestino, $data ?? now()->toDateString());

        return $linha?->aliquota_interestadual;
    }

    private static function linhaVigente(int $businessId, string $ufOrigem, string $ufDestino, string $data): ?self
    {
        return static::withoutGlobalScopes() // SUPERADMIN: lido por serviço/fila; o escopo é o where explícito abaixo
            ->where('business_id', $businessId)
            ->where('uf_origem', strtoupper($ufOrigem))
            ->where('uf_destino', strtoupper($ufDestino))
            ->vigenteEm($data)
            ->orderByDesc('id')
            ->first();
    }
}

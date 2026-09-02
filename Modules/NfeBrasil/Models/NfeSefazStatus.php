<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Saúde do autorizador SEFAZ por UF — US-NFE-006 / ADR TECH-0002.
 *
 * ⚠️ SEM HasBusinessScope, DE PROPÓSITO — e isto é desenho, não dívida.
 * "SEFAZ-SC está fora" é o MESMO fato para todo tenant: é estado de um serviço do
 * governo, não dado de negócio. Escopar por business duplicaria a mesma linha N vezes
 * e deixaria a falha de rede de um tenant contaminar a leitura do outro.
 * Registrado em governance/multi-tenant-scope-baseline.json > `allowlist` (exceção
 * permanente com razão), NUNCA em `grandfathered` (que é dívida datada e só desce).
 *
 * O isolamento por tenant vive na DECISÃO, não na observação:
 * `nfe_business_configs.em_contingencia` é por business e só o tenant liga.
 *
 * ⚠️ QUEM OBSERVA É LOCAL, O FATO É GLOBAL. O ping (`NfeService::consultarStatusSefaz`)
 * exige CERTIFICADO POR BUSINESS — não existe ping anônimo por UF. Então quem grava a
 * linha da UF é algum business daquela UF, com o cert dele. Dois businesses na mesma UF
 * sobrescrevem a mesma linha, o que é correto: o fato observado é o mesmo.
 */
class NfeSefazStatus extends Model
{
    protected $table = 'nfe_sefaz_status';

    /** Chave natural: 1 linha por autorizador. */
    protected $primaryKey = 'uf';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'uf', 'status', 'last_check_at', 'last_response_ms', 'consecutive_failures',
    ];

    protected $casts = [
        'last_check_at' => 'datetime',
        'last_response_ms' => 'integer',
        'consecutive_failures' => 'integer',
    ];

    public const VERDE = 'verde';

    public const AMARELO = 'amarelo';

    public const VERMELHO = 'vermelho';

    /** A coluna é string (não enum) — sinal de infra não é vocabulário de domínio. */
    public const STATUS_VALIDOS = [self::VERDE, self::AMARELO, self::VERMELHO];

    /**
     * ADR TECH-0002: 3 falhas seguidas => SUGERIR contingência.
     * Sugerir, nunca ativar — a ADR rejeitou auto-ativação porque a rede do servidor
     * pode cair sem a SEFAZ ter caído.
     */
    public const FALHAS_PARA_SUGERIR = 3;

    public function sugereContingencia(): bool
    {
        return $this->consecutive_failures >= self::FALHAS_PARA_SUGERIR;
    }
}

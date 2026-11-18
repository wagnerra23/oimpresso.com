<?php

declare(strict_types=1);

namespace Modules\ComunicacaoVisual\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\ComunicacaoVisual\Entities\Concerns\EscopoPorNegocioDaSessao;

/**
 * Comprovante da instalação de campo (US-COMVIS-007 · UC-PCV-25): foto antes, foto depois ou assinatura do
 * cliente. Uma linha por envio; o arquivo é anexo DESTA linha no módulo Arquivos (cofre privado), gravado
 * pelo contrato do núcleo `App\Contracts\Arquivos\AnexosDoApp`.
 *
 * Multi-tenant Tier 0 (ADR 0093): trait do módulo; o serviço também filtra o business_id explicitamente.
 *
 * @property int $id
 * @property int $business_id
 * @property int $instalacao_id
 * @property string $tipo foto_antes|foto_depois|assinatura
 * @property string|null $assinado_por
 * @property \Illuminate\Support\Carbon|null $consentimento_em
 * @property int|null $user_id
 */
class InstalacaoComprovante extends Model
{
    use EscopoPorNegocioDaSessao; // ADR 0093 — escopo automático por business (Tier 0)

    public const TIPOS = ['foto_antes', 'foto_depois', 'assinatura'];

    protected $table = 'cv_instalacao_comprovantes';

    protected $fillable = ['business_id', 'instalacao_id', 'tipo', 'assinado_por', 'consentimento_em', 'user_id'];

    protected $casts = [
        'business_id' => 'integer',
        'instalacao_id' => 'integer',
        'user_id' => 'integer',
        'consentimento_em' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (InstalacaoComprovante $row) {
            if ($row->business_id === null) {
                $row->business_id = session('user.business_id') ?? session('business.id')
                    ?? throw new \LogicException('Comunicação Visual: sem business_id para criar '.$row::class.' (sem sessão). Passe o business_id explícito.');
            }
        });
    }
}

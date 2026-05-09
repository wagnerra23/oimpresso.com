<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * NF-e recebida pelo destinatário (terceiro emitiu contra meu CNPJ).
 *
 * Caso primário: Gold Comunicação Visual recebe NF-e de fornecedores de placas
 * (US-NFE-049, ADR 0116). Sucessor canônico de App\Manifesto (legacy órfão removido).
 *
 * Multi-tenant: business_id global scope via HasBusinessScope (ADR 0093).
 */
class NfeDfeRecebido extends Model
{
    use HasBusinessScope;

    protected $table = 'nfe_dfe_recebidos';

    protected $fillable = [
        'business_id',
        'chave_44',
        'nsu',
        'cnpj_emitente',
        'nome_emitente',
        'valor_total',
        'num_protocolo',
        'data_emissao',
        'xml_path',
        'status_manifestacao',
        'manifestado_em',
        'prazo_confirmacao_em',
    ];

    protected $casts = [
        'valor_total'         => 'decimal:2',
        'data_emissao'        => 'datetime',
        'manifestado_em'      => 'datetime',
        'prazo_confirmacao_em' => 'date',
        'nsu'                 => 'integer',
    ];

    public const STATUS_PENDENTE      = 'pendente';
    public const STATUS_CIENCIA       = 'ciencia';
    public const STATUS_CONFIRMADA    = 'confirmada';
    public const STATUS_DESCONHECIDA  = 'desconhecida';
    public const STATUS_NAO_REALIZADA = 'nao_realizada';

    public function itens(): HasMany
    {
        return $this->hasMany(NfeDfeItem::class, 'dfe_recebido_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(NfeDfeEvento::class, 'dfe_recebido_id');
    }

    /**
     * Dias restantes até prazo de Confirmação (180d NT 2014.002).
     * Negativo = já vencido. Null se sem prazo definido.
     */
    public function diasAtePrazoConfirmacao(): ?int
    {
        if (! $this->prazo_confirmacao_em) {
            return null;
        }
        return (int) now()->startOfDay()->diffInDays($this->prazo_confirmacao_em, false);
    }

    public function podeManifestar(): bool
    {
        return $this->status_manifestacao === self::STATUS_PENDENTE
            || $this->status_manifestacao === self::STATUS_CIENCIA;
    }
}

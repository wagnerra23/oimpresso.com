<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Models;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Arquivos\Concerns\HasArquivos;
use Modules\Arquivos\Entities\Arquivo;

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
    use HasArquivos; // ADR 0123 — adopcao trait Sprint 3 US-ARQ-019

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

    /**
     * Accessor — retorna Arquivo XML preferindo arquivos table (ADR 0123),
     * fallback null se ainda usando coluna legacy xml_path.
     *
     * Sprint 3 US-ARQ-019. Migration backfill US-ARQ-020 popula arquivos rows
     * pra xml_path existentes.
     */
    public function getXmlArquivoAttribute(): ?Arquivo
    {
        if (! method_exists($this, 'arquivos')) return null;
        return $this->arquivos()
            ->where('sub_destination', 'nfe-xml')
            ->where('bucket', 'active')
            ->latest('created_at')
            ->first();
    }
}

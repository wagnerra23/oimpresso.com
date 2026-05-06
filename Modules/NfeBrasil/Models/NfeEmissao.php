<?php

namespace Modules\NfeBrasil\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Emissão fiscal — NFe (55), NFC-e (65), CT-e (67).
 *
 * Idempotência por (business_id, transaction_id): re-emitir mesma venda
 * UPos = no-op. Sequência única por (business_id, modelo, serie, numero) —
 * regra fiscal (não pode haver lacuna sem inutilização registrada).
 *
 * Status canônicos:
 *   pendente     — aguardando envio SEFAZ
 *   autorizada   — SEFAZ retornou cstat=100 (NF-e) ou 104 (NFC-e)
 *   rejeitada    — cstat 200..600 (erro de validação) — pode retentar
 *   denegada     — cstat 110, 301, 302, 205 — emitente irregular, NÃO emite
 *   cancelada    — evento 110111 com cstat=135
 *   inutilizada  — número pulado via processo de inutilização
 */
class NfeEmissao extends Model
{
    use SoftDeletes;

    protected $table = 'nfe_emissoes';

    protected $fillable = [
        'business_id', 'transaction_id',
        'modelo', 'serie', 'numero', 'chave_44',
        'status', 'cstat', 'motivo',
        'xml_path', 'danfe_path',
        'valor_total', 'emitido_em', 'metadata',
    ];

    protected $casts = [
        'numero'      => 'integer',
        'valor_total' => 'decimal:2',
        'emitido_em'  => 'datetime',
        'metadata'    => 'array',
    ];

    public function eventos(): HasMany
    {
        return $this->hasMany(NfeEvento::class, 'emissao_id');
    }

    public function scopeAutorizadas(Builder $q): Builder
    {
        return $q->where('status', 'autorizada');
    }

    public function scopeDoBusinessAtual(Builder $q): Builder
    {
        return $q->where('business_id', session('business.id'));
    }

    public function isAutorizada(): bool
    {
        return $this->status === 'autorizada';
    }

    public function isCancelavel(): bool
    {
        if ($this->status !== 'autorizada') return false;

        // NFC-e: 24h. NFe: 168h (7 dias). Após prazo, só carta de correção.
        $prazoHoras = $this->modelo === '65' ? 24 : 168;
        return $this->emitido_em
            && $this->emitido_em->diffInHours(now()) <= $prazoHoras;
    }
}

<?php

namespace Modules\NFSe\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\NFSe\Models\Concerns\NfseBusinessScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Colunas criadas por migrations do NfeBrasil (D-NFSE-CONSOLIDA), fora do alcance da
 * inferência de schema do Larastan — declaradas aqui. Cast decimal:2 ⇒ string.
 *
 * @property string|null $valor_iss_retido  ISS retido pelo tomador (US-FIN-072); NULL em nota anterior à coluna
 * @property string|null $valor_ret_pis
 * @property string|null $valor_ret_cofins
 * @property string|null $valor_ret_csll
 */
class NfseEmissao extends Model
{
    use SoftDeletes, NfseBusinessScope, LogsActivity;

    protected $table = 'nfse_emissoes';

    protected $fillable = [
        'business_id', 'numero', 'serie', 'rps_numero', 'competencia',
        'tomador_cnpj', 'tomador_cpf', 'tomador_nome', 'tomador_email', 'tomador_municipio_ibge',
        'lc116_codigo', 'ctrib_nac', 'cnae', 'descricao',
        'valor_servicos', 'valor_deducoes', 'valor_base_calculo',
        'aliquota_iss', 'valor_iss', 'iss_retido', 'valor_iss_retido',
        'retencao_federal_sujeito', 'valor_ret_pis', 'valor_ret_cofins', 'valor_ret_csll',
        'status', 'provider_protocolo', 'provider_codigo_verificacao',
        'pdf_url', 'xml_envio', 'xml_retorno', 'erro_mensagem',
        'idempotency_key', 'recurring_invoice_id', 'transaction_id',
    ];

    protected $casts = [
        'competencia'    => 'date',
        'valor_servicos' => 'decimal:2',
        'valor_deducoes' => 'decimal:2',
        'valor_base_calculo' => 'decimal:2',
        'aliquota_iss'   => 'decimal:4',
        'valor_iss'      => 'decimal:2',
        'iss_retido'     => 'boolean',
        'valor_iss_retido' => 'decimal:2',
        'retencao_federal_sujeito' => 'boolean',
        'valor_ret_pis'    => 'decimal:2',
        'valor_ret_cofins' => 'decimal:2',
        'valor_ret_csll'   => 'decimal:2',
    ];

    public function isEmitida(): bool  { return $this->status === 'emitida'; }
    public function isCancelada(): bool { return $this->status === 'cancelada'; }
    public function isErro(): bool      { return $this->status === 'erro'; }
    public function isPendente(): bool  { return in_array($this->status, ['rascunho', 'processando']); }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'rascunho'     => 'Rascunho',
            'processando'  => 'Processando...',
            'emitida'      => 'Emitida',
            'cancelada'    => 'Cancelada',
            'erro'         => 'Erro',
            default        => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'emitida'      => 'success',
            'cancelada'    => 'warning',
            'erro'         => 'danger',
            'processando'  => 'info',
            default        => 'secondary',
        };
    }

    public function transaction()
    {
        return $this->belongsTo(\App\Transaction::class);
    }

    /**
     * Auditoria LGPD — registra mudanças em emissões NFSe (PII tomador + valor + status).
     * D7.b Wave 14: audit trail append-only via activity_log (LGPD Art. 37).
     *
     * Exclui campos volumosos (xml_envio/xml_retorno/pdf_url) — auditoria foca em
     * mudança de estado, não em conteúdo SOAP/PDF (storage cost + ruído).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['xml_envio', 'xml_retorno', 'pdf_url'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('nfse.emissao');
    }
}

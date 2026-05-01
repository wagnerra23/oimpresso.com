<?php

namespace Modules\NFSe\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\NFSe\Models\Concerns\NfseBusinessScope;

class NfseEmissao extends Model
{
    use SoftDeletes, NfseBusinessScope;

    protected $table = 'nfse_emissoes';

    protected $fillable = [
        'business_id', 'numero', 'serie', 'rps_numero', 'competencia',
        'tomador_cnpj', 'tomador_cpf', 'tomador_nome', 'tomador_email', 'tomador_municipio_ibge',
        'lc116_codigo', 'cnae', 'descricao',
        'valor_servicos', 'valor_deducoes', 'valor_base_calculo',
        'aliquota_iss', 'valor_iss', 'iss_retido',
        'status', 'provider_protocolo', 'provider_codigo_verificacao',
        'pdf_url', 'xml_envio', 'xml_retorno', 'erro_mensagem',
        'idempotency_key', 'recurring_invoice_id',
    ];

    protected $casts = [
        'competencia'    => 'date',
        'valor_servicos' => 'decimal:2',
        'valor_deducoes' => 'decimal:2',
        'valor_base_calculo' => 'decimal:2',
        'aliquota_iss'   => 'decimal:4',
        'valor_iss'      => 'decimal:2',
        'iss_retido'     => 'boolean',
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
}

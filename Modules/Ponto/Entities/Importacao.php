<?php

namespace Modules\Ponto\Entities;

use App\Concerns\HasBusinessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Importacao AFD/AFDT — historico de processamento por business.
 *
 * Wave 18 D1 — Multi-tenant Tier 0 IRREVOGAVEL ([ADR 0093]):
 * trait HasBusinessScope aplica global scope automatico por business_id.
 * Cross-tenant leak permitiria ver arquivos/erros de outras empresas.
 */
class Importacao extends Model
{
    use HasBusinessScope;

    protected $table = 'ponto_importacoes';

    protected $fillable = [
        'business_id', 'tipo', 'nome_arquivo', 'arquivo_path',
        'hash_arquivo', 'tamanho_bytes', 'estado',
        'linhas_total', 'linhas_processadas', 'linhas_sucesso', 'linhas_erro',
        'erros_amostra', 'log', 'usuario_id',
        'iniciado_em', 'concluido_em',
    ];

    protected $casts = [
        'erros_amostra' => 'array',
        'iniciado_em'   => 'datetime',
        'concluido_em'  => 'datetime',
    ];

    public const ESTADO_PENDENTE             = 'PENDENTE';
    public const ESTADO_PROCESSANDO          = 'PROCESSANDO';
    public const ESTADO_CONCLUIDA            = 'CONCLUIDA';
    public const ESTADO_CONCLUIDA_COM_ERROS  = 'CONCLUIDA_COM_ERROS';
    public const ESTADO_FALHOU               = 'FALHOU';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(config('pontowr2.ultimatepos.user_model'), 'usuario_id');
    }

    public function percentualProgresso(): int
    {
        if ($this->linhas_total == 0) return 0;
        return (int) round(($this->linhas_processadas / $this->linhas_total) * 100);
    }
}

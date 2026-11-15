<?php

namespace Modules\Captacao\Entities;

use App\Concerns\HasBusinessScope;
use App\Contact;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Projeto de captação (captacao_projetos). O proponente é texto no próprio projeto — nome e
 * CNPJ, vazio quando o grupo ainda não tem CNPJ — e não FK para outro módulo (ADR 0452 D3).
 * O líder é um contato do núcleo, que não precisa ser usuário do sistema. Situação em lista
 * fechada (D5); tipo e área em lista com "outro".
 */
class Projeto extends Model
{
    use HasBusinessScope;

    public const SITUACOES = ['ideia', 'elaboracao', 'inscrito', 'aprovado', 'reprovado', 'captando', 'execucao', 'prestacao_contas', 'encerrado'];

    public const TIPOS = ['edital_municipal', 'edital_estadual', 'lei_incentivo', 'emenda_parlamentar', 'convenio_sus', 'captacao_privada', 'voluntariado', 'formalizacao_juridica', 'outro'];

    public const AREAS = ['saude', 'assistencia_social', 'direitos', 'cultura', 'educacao', 'esporte', 'outro'];

    protected $table = 'captacao_projetos';

    protected $fillable = [
        'business_id', 'titulo', 'objetivo', 'proponente_nome', 'proponente_cnpj', 'lider_contact_id', 'tipo', 'area', 'situacao', 'situacao_desde',
        'valor_solicitado', 'valor_aprovado', 'execucao_inicio', 'execucao_fim', 'prestacao_contas_ate', 'created_by',
    ];

    protected $casts = [
        'situacao_desde' => 'date',
        'execucao_inicio' => 'date',
        'execucao_fim' => 'date',
        'prestacao_contas_ate' => 'date',
        'valor_solicitado' => 'decimal:4',
        'valor_aprovado' => 'decimal:4',
    ];

    /** Líder do projeto. O contact vem do mesmo business: a onda 2 valida isso ao gravar. */
    public function lider(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'lider_contact_id');
    }

    public function aportes(): HasMany
    {
        return $this->hasMany(Aporte::class, 'projeto_id');
    }

    public function comprovantes(): HasMany
    {
        return $this->hasMany(Comprovante::class, 'projeto_id');
    }
}

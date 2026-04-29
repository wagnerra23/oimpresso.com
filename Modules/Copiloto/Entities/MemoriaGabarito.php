<?php

namespace Modules\Copiloto\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * MEM-EVAL-1 (ADR 0049/0050) — Pergunta do gabarito de eval.
 */
class MemoriaGabarito extends Model
{
    protected $table = 'copiloto_memoria_gabarito';

    protected $fillable = [
        'business_id', 'categoria', 'subcategoria',
        'pergunta', 'memoria_esperada_keys', 'resposta_esperada_pattern',
        'contexto_setup', 'dificuldade', 'ativo', 'notas',
    ];

    protected $casts = [
        'memoria_esperada_keys' => 'array',
        'contexto_setup'        => 'array',
        'dificuldade'           => 'integer',
        'ativo'                 => 'boolean',
    ];

    public const CATEGORIAS = [
        'info-extraction'  => 'Info-extraction (single-session)',
        'multi-session'    => 'Multi-session reasoning',
        'temporal'         => 'Temporal reasoning',
        'knowledge-update' => 'Knowledge update',
        'abstention'       => 'Abstention (deve dizer "não sei")',
    ];

    public function scopeAtivo($q)
    {
        return $q->where('ativo', true);
    }

    public function scopeDoBusiness($q, ?int $businessId)
    {
        if ($businessId === null) {
            return $q;
        }
        return $q->where(function ($qq) use ($businessId) {
            $qq->where('business_id', $businessId)
               ->orWhereNull('business_id');
        });
    }

    /**
     * Match um snippet (texto) contra os keys esperados (case-insensitive contains).
     */
    public function matchSnippet(string $snippet): bool
    {
        $snippet = mb_strtolower($snippet);
        foreach ($this->memoria_esperada_keys ?? [] as $key) {
            if (mb_stripos($snippet, mb_strtolower($key)) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Quantos dos memoria_esperada_keys foram cobertos por uma lista de snippets recall.
     */
    public function coverageScore(array $snippetsRecall): float
    {
        $keys = $this->memoria_esperada_keys ?? [];
        if (empty($keys)) {
            return 0.0;
        }

        $cobertos = 0;
        foreach ($keys as $key) {
            $keyLower = mb_strtolower($key);
            foreach ($snippetsRecall as $snippet) {
                if (mb_stripos(mb_strtolower($snippet), $keyLower) !== false) {
                    $cobertos++;
                    break;
                }
            }
        }
        return $cobertos / count($keys);
    }
}

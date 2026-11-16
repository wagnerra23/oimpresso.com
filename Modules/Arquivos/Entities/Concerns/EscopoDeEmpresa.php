<?php

namespace Modules\Arquivos\Entities\Concerns;

use App\Tenancy\ContextoEmpresa;
use Illuminate\Database\Eloquent\Builder;

/**
 * Escopo de empresa do Arquivo — ADR 0093 (Tier 0) + ADR 0453 (contexto explícito).
 *
 * O nome do scope continua `business_id`: consumidores de outros módulos saem dele com
 * `Arquivo::withoutGlobalScope('business_id') // SUPERADMIN: ...`.
 *
 * Modo `aberto` (padrão): a mesma regra de sempre — sessão (`user.business_id`, depois
 * `business.id`); sem sessão, não filtra. Modos `log`/`fechado`: a empresa vem do
 * ContextoEmpresa (job/comando/API também); sem empresa, registra ou lança. Dentro de
 * `comoPlataforma()` não filtra.
 */
trait EscopoDeEmpresa
{
    protected static function bootEscopoDeEmpresa(): void
    {
        static::addGlobalScope('business_id', function (Builder $query) {
            $businessId = self::empresaDoEscopoDeArquivos();
            if ($businessId !== null) {
                $query->where('arquivos.business_id', $businessId);
            }
        });

        // business_id vazio no create: contexto (explícito → sessão → usuário). Sem empresa,
        // o modo decide — `fechado` lança em vez de gravar a linha com business_id = 0.
        static::creating(function ($arquivo) {
            if ($arquivo->business_id !== null) {
                return;
            }
            $contexto = app(ContextoEmpresa::class);
            $businessId = $contexto->atual() ?? ((int) session('business.id') ?: null);
            if ($businessId === null) {
                $contexto->semEmpresa(static::class);
                $businessId = 0; // legado (aberto/log): mesma gravação de antes, agora registrada no modo log
            }
            $arquivo->business_id = $businessId;
        });
    }

    /** Empresa que o scope aplica, ou null para não filtrar. */
    private static function empresaDoEscopoDeArquivos(): ?int
    {
        $contexto = app(ContextoEmpresa::class);
        $businessId = $contexto->empresaDoEscopo(static::class);

        if ($businessId === null && ContextoEmpresa::modo() === ContextoEmpresa::ABERTO) {
            // Legado do Arquivo: além de `user.business_id`, aceitava `business.id`.
            $daSessao = (int) session('business.id');

            return $daSessao > 0 ? $daSessao : null;
        }

        return $businessId;
    }
}

<?php

namespace Modules\Arquivos\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\ArquivosService;

/**
 * Trait HasArquivos — outros models incluem pra ganhar relação polimórfica
 * com Modules/Arquivos backbone (ADR 0123 §4).
 *
 * Uso:
 *   class NfeXml extends Model {
 *       use HasArquivos;
 *   }
 *
 *   $nfeXml->attachArquivo($request->file('xml'), ['context' => 'nfe-xml']);
 *   $nfeXml->arquivos()->bucket('active')->get();
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */
trait HasArquivos
{
    public function arquivos(): MorphMany
    {
        return $this->morphMany(Arquivo::class, 'arquivable');
    }

    public function attachArquivo(UploadedFile $file, array $opts = []): Arquivo
    {
        return app(ArquivosService::class)->attach($this, $file, $opts);
    }

    /**
     * Helper: arquivos classificados num bucket específico.
     *
     * `bucket()` é um local scope definido em Arquivo::scopeBucket — Larastan
     * não propaga scopes do model target via MorphMany sem `@method` annotation.
     * Refator clean (US-ARQ-TYPE follow-up): adicionar
     * `@method static Builder<static> bucket(string $bucket)` em Arquivo +
     * generic `@return MorphMany<Arquivo, $this>` em arquivos() acima.
     */
    public function arquivosClassificados(string $bucket): \Illuminate\Database\Eloquent\Collection
    {
        /** @phpstan-ignore-next-line method.notFound */
        return $this->arquivos()->bucket($bucket)->get();
    }
}

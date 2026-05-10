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
     */
    public function arquivosClassificados(string $bucket): \Illuminate\Database\Eloquent\Collection
    {
        return $this->arquivos()->bucket($bucket)->get();
    }
}

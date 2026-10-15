<?php

declare(strict_types=1);

namespace App\Contracts\Arquivos\Nulo;

use App\Contracts\Arquivos\AnexosDoApp;
use Illuminate\Http\UploadedFile;

/** Padrão quando o módulo Arquivos não está carregado: nada é gravado nem servido. */
final class SemAnexosDoApp implements AnexosDoApp
{
    public function anexar(int $businessId, string $tipoDono, int $donoId, UploadedFile $arquivo): ?int
    {
        return null;
    }

    public function conteudo(int $businessId, int $arquivoId): ?array
    {
        return null;
    }
}

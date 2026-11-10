<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Onde moram XML e DANFE de nota fiscal.
 *
 * O disco `local` tem raiz `public/uploads`, que o Apache serve direto, e o caminho das notas é
 * previsível (`nfe-brasil/{biz}/notas/{serie}-{numero}.xml`). Por isso grava-se em `nfe_fiscal`,
 * fora da pasta pública; download só por rota autenticada na empresa ou link assinado.
 *
 * A leitura ainda aceita o disco antigo até `nfe:mover-arquivos-fiscais --executar` rodar.
 */
final class ArquivoFiscal
{
    public const DISCO = 'nfe_fiscal';

    public const DISCO_ANTIGO = 'local';

    /** Falha de disco não derruba a emissão: a nota já está autorizada na SEFAZ. */
    public static function gravar(string $caminho, string $conteudo): bool
    {
        $gravou = Storage::disk(self::DISCO)->put($caminho, $conteudo);
        if ($gravou === false) {
            Log::error('nfe.arquivo_fiscal.gravacao_falhou', ['tipo' => pathinfo($caminho, PATHINFO_EXTENSION)]);

            return false;
        }

        return true;
    }

    public static function existe(?string $caminho): bool
    {
        return self::discoQueTem($caminho) !== null;
    }

    public static function ler(?string $caminho): ?string
    {
        $disco = self::discoQueTem($caminho);
        if ($disco === null) {
            return null;
        }

        $conteudo = Storage::disk($disco)->get($caminho);

        return is_string($conteudo) ? $conteudo : null;
    }

    private static function discoQueTem(?string $caminho): ?string
    {
        if ($caminho === null || $caminho === '') {
            return null;
        }

        foreach ([self::DISCO, self::DISCO_ANTIGO] as $disco) {
            if (Storage::disk($disco)->exists($caminho)) {
                return $disco;
            }
        }

        return null;
    }
}

<?php

namespace Modules\Arquivos\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Tipo e tamanho do upload compartilhado (auditoria Arquivos L2, 2026-11-16).
 *
 * `ArquivosService::attach()`/`attachNoNegocio()` gravavam qualquer arquivo: a validação
 * dependia de cada consumidor lembrar de fazer. Esta é a rede de baixo — os consumidores
 * seguem com a regra deles (mais estreita), e quem chamar o serviço sem validar não grava
 * executável nem arquivo acima do teto.
 *
 * Regra:
 *  - tamanho ≤ `arquivos.upload_max_mb`;
 *  - extensão fora de `arquivos.upload_extensoes_bloqueadas`;
 *  - MIME (detectado no conteúdo) dentro de `arquivos.upload_mimes`.
 *
 * Lança ValidationException (campo `arquivo`): na web vira 422, não 500.
 */
final class ValidacaoUpload
{
    /** @throws ValidationException */
    public function validar(UploadedFile $arquivo): void
    {
        $maxBytes = max(1, (int) config('arquivos.upload_max_mb', 50)) * 1024 * 1024;
        $tamanho = $arquivo->getSize();
        if ($tamanho === false || $tamanho > $maxBytes) {
            throw ValidationException::withMessages([
                'arquivo' => 'Arquivo acima do limite de ' . intdiv($maxBytes, 1024 * 1024) . ' MB.',
            ]);
        }

        $extensao = strtolower((string) $arquivo->getClientOriginalExtension());
        $bloqueadas = array_map('strtolower', (array) config('arquivos.upload_extensoes_bloqueadas', []));
        if ($extensao !== '' && in_array($extensao, $bloqueadas, true)) {
            throw ValidationException::withMessages(['arquivo' => 'Tipo de arquivo não permitido.']);
        }

        $mime = strtolower((string) ($arquivo->getMimeType() ?? ''));
        $aceitos = array_map('strtolower', (array) config('arquivos.upload_mimes', []));
        if (! in_array($mime, $aceitos, true)) {
            throw ValidationException::withMessages(['arquivo' => 'Tipo de arquivo não permitido.']);
        }
    }
}

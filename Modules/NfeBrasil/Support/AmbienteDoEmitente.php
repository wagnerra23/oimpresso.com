<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Support;

use Illuminate\Support\Facades\DB;

/**
 * Ambiente SEFAZ da empresa (tpAmb): 1 = produção, 2 = homologação.
 *
 * Fonte única = coluna `business.ambiente`, a mesma que a emissão de NF-e usa
 * (`NfeService::buildXml` e `NfeService` linha do `tpAmb`), que o certificado troca
 * (`CertificadoController`) e que a consulta de cadastro lê (`SefazConsultaCadastroService`).
 * Sem a linha ou sem valor, 2 (homologação).
 *
 * Origem (auditoria Fiscal 2026-11-05, lote 0.2): manifestação e DF-e liam
 * `config('nfebrasil.ambiente', 2)`, chave que não existe no config do módulo, e a
 * inutilização lia `env('NFE_AMBIENTE', 2)`, que com config em cache devolve o padrão.
 * As três chamadas iam sempre para homologação, mesmo com a empresa emitindo em produção.
 */
final class AmbienteDoEmitente
{
    public static function resolver(int $businessId): int
    {
        $ambiente = (int) (DB::table('business')->where('id', $businessId)->value('ambiente') ?? 2);

        return $ambiente === 1 ? 1 : 2;
    }
}

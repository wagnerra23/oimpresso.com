<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0186 §evolução Técnica C — adiciona em `contacts`:
 *   - `ind_ie_dest`              tinyint(1) — NFe `indIEDest`: 1=contribuinte, 2=isento, 9=não contribuinte
 *                                            OBRIGATÓRIO no XML NFe `<dest>`. Derivado da chain SEFAZ
 *                                            pelo `SefazConsultaCadastroService` quando IE+cSit disponíveis.
 *   - `sefaz_cad_sit`            varchar(20) — situacao cadastral SEFAZ (`cSit` mapeado pra label PT-BR)
 *                                              valores: 'habilitado', 'nao_habilitado', 'suspenso', 'cancelado', 'paralisado'
 *   - `sefaz_cad_ind_cred_nfe`   tinyint(1) — `indCredNFe`: 0=não credenciado, 1=produção, 2=homologação, 3=ambos, 4=desabilitado
 *                                            avisa antes de emitir pra cliente que não recebe NFe (rejeição evitável)
 *   - `sefaz_cad_consultado_em`  timestamp nullable — última consulta SEFAZ bem-sucedida (cache vence 30d → re-consulta)
 *
 * Justificativa Técnica C (merge paralelo) vs apenas IE manual:
 *   - 6 das 10 rejeições NFe mais comuns dependem de `cSit` + IE (catalogadas na auditoria 2026-05-23)
 *   - Warning antecipado UI quando `cSit ≠ habilitado` evita perder venda + emitir NFe rejeitada
 *   - Persistir evita re-consultar SEFAZ a cada nova venda (cache Redis 30d serve API; DB serve UX no-fetch)
 *
 * Multi-tenant Tier 0 (ADR 0093): `contacts.business_id` já existe + indexado.
 * LGPD: nenhuma das 4 colunas entra em $logOnly (App\Contact). Dado fiscal público, não PII.
 *
 * IDEMPOTENTE — Schema::hasColumn check. Reversível: down() dropa apenas se existirem.
 *
 * @see memory/decisions/0186-chain-certificado-sefaz-consulta-cadastro.md §Evolução
 * @see Modules/NfeBrasil/Services/SefazConsultaCadastroService::consultar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'ind_ie_dest')) {
                // 1 = contribuinte ICMS, 2 = isento, 9 = não contribuinte. NULL = não classificado ainda.
                $table->tinyInteger('ind_ie_dest')->nullable()->after('ie');
            }

            if (! Schema::hasColumn('contacts', 'sefaz_cad_sit')) {
                // Enum textual PT-BR derivado do `cSit` SEFAZ:
                //   cSit 0 -> 'habilitado' · 1 -> 'nao_habilitado' · 2 -> 'suspenso'
                //   cSit 3 -> 'cancelado'  · 4 -> 'paralisado'    · 5 -> 'baixado'
                $table->string('sefaz_cad_sit', 20)->nullable();
            }

            if (! Schema::hasColumn('contacts', 'sefaz_cad_ind_cred_nfe')) {
                // indCredNFe: 0=não, 1=produção, 2=homologação, 3=ambos, 4=desabilitado emissão própria
                // (mas pode RECEBER — `indCredNFe` é só sobre EMITIR; destinatário sempre pode receber).
                // Mantemos pra UI exibir status de credenciamento como info.
                $table->tinyInteger('sefaz_cad_ind_cred_nfe')->nullable();
            }

            if (! Schema::hasColumn('contacts', 'sefaz_cad_consultado_em')) {
                $table->timestamp('sefaz_cad_consultado_em')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $cols = ['ind_ie_dest', 'sefaz_cad_sit', 'sefaz_cad_ind_cred_nfe', 'sefaz_cad_consultado_em'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('contacts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

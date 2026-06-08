<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Migra nfe_certificados do schema NFSe (2026-05-01) para o schema NfeBrasil (2026-05-06).
 *
 * Schema ANTES (NFSe / Eliana):
 *   cert_pfx_encrypted  TEXT    — Crypt::encryptString(base64_encode(pfxBin))
 *   senha_encrypted     VARCHAR — Crypt::encryptString(senha)
 *   titular_cnpj        VARCHAR
 *   titular_nome        VARCHAR
 *
 * Schema DEPOIS (NfeBrasil):
 *   uuid                UUID    — path do arquivo: storage/app/nfe-brasil/{biz}/cert/{uuid}.pfx.enc
 *   cnpj_titular        VARCHAR — renomeia titular_cnpj
 *   encrypted_password  TEXT    — cópia direta de senha_encrypted (mesmo Crypt::encryptString)
 *
 * Idempotente:
 *   - Se uuid já existe → novo schema, pula
 *   - Se cert_pfx_encrypted não existe → nada a migrar, pula
 *
 * Rollback: NÃO restaura arquivos (.pfx.enc gerados). Registre em log e recrie
 * os certs via `php artisan nfse:importar-cert` se necessário.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Já migrado (novo schema)
        if (Schema::hasColumn('nfe_certificados', 'uuid')) {
            return;
        }

        // Nada pra migrar (schema diferente ou tabela sem cert_pfx_encrypted)
        if (! Schema::hasColumn('nfe_certificados', 'cert_pfx_encrypted')) {
            return;
        }

        // ── 1. Adiciona novas colunas (nullable enquanto migramos dados) ──────
        Schema::table('nfe_certificados', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('business_id');
            $table->string('cnpj_titular', 14)->nullable()->index()->after('uuid');
            $table->text('encrypted_password')->nullable()->after('valido_ate');
        });

        // ── 2. Migra dados linha a linha ──────────────────────────────────────
        $rows = DB::table('nfe_certificados')
            ->whereNull('deleted_at')
            ->get();

        foreach ($rows as $row) {
            try {
                $uuid = (string) Str::uuid();

                // cert_pfx_encrypted = Crypt::encryptString(base64_encode(pfxBin))
                $pfxBase64 = Crypt::decryptString($row->cert_pfx_encrypted);
                $pfxBinary = base64_decode($pfxBase64, true);

                if ($pfxBinary === false || strlen($pfxBinary) === 0) {
                    Log::error("migrate nfe_certificados: base64_decode falhou id={$row->id}");
                    continue;
                }

                // Grava em disco no novo formato (Crypt::encrypt do binário puro)
                $diskPath = sprintf('nfe-brasil/%d/cert/%s.pfx.enc', $row->business_id, $uuid);
                Storage::put($diskPath, Crypt::encrypt($pfxBinary));

                DB::table('nfe_certificados')->where('id', $row->id)->update([
                    'uuid'               => $uuid,
                    'cnpj_titular'       => $row->titular_cnpj ?? null,
                    // senha_encrypted e encrypted_password são ambos Crypt::encryptString — cópia direta
                    'encrypted_password' => $row->senha_encrypted,
                ]);

                Log::info("migrate nfe_certificados: id={$row->id} biz={$row->business_id} → {$diskPath}");
            } catch (\Throwable $e) {
                Log::error("migrate nfe_certificados: erro id={$row->id} — " . $e->getMessage());
                // Não interrompe: demais linhas podem migrar normalmente
            }
        }

        // ── 3. Torna uuid obrigatório onde a migração de dados foi bem-sucedida ──
        // Linhas sem uuid (falha no passo 2) ficam com uuid=null — ok pro rollback manual
        Schema::table('nfe_certificados', function (Blueprint $table) {
            $table->string('cnpj_titular', 14)->nullable(false)->change();
        });

        // ── 4. Remove colunas antigas ─────────────────────────────────────────
        Schema::table('nfe_certificados', function (Blueprint $table) {
            // Tenta dropar FK antes das colunas (MySQL exige)
            try {
                $table->dropForeign(['business_id']);
            } catch (\Throwable) {
                // FK pode não existir ou ter nome diferente — ignora
            }

            $table->dropColumn(['cert_pfx_encrypted', 'senha_encrypted', 'titular_cnpj', 'titular_nome']);
        });

        // ── 5. Recria FK (foi removida junto) ────────────────────────────────
        Schema::table('nfe_certificados', function (Blueprint $table) {
            $table->foreign('business_id')
                ->references('id')
                ->on('business')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Rollback estrutural apenas — dados em arquivo NÃO são restaurados
        if (! Schema::hasColumn('nfe_certificados', 'uuid')) {
            return;
        }

        Schema::table('nfe_certificados', function (Blueprint $table) {
            $table->text('cert_pfx_encrypted')->nullable()->after('business_id');
            $table->string('senha_encrypted', 512)->nullable()->after('cert_pfx_encrypted');
            $table->string('titular_cnpj', 18)->nullable()->after('valido_ate');
            $table->string('titular_nome', 150)->nullable()->after('titular_cnpj');
        });

        Schema::table('nfe_certificados', function (Blueprint $table) {
            try {
                $table->dropForeign(['business_id']);
            } catch (\Throwable) {}

            $table->dropColumn(['uuid', 'cnpj_titular', 'encrypted_password']);
        });

        Schema::table('nfe_certificados', function (Blueprint $table) {
            $table->foreign('business_id')
                ->references('id')
                ->on('business')
                ->onDelete('cascade');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drift recovery — campos fiscais PT-BR em `business`.
 *
 * Contexto: Eliana [E] adicionou campos PT diretamente em `business` no schema
 * UltimatePOS 3.7 (custom BR fork). Após upgrade 3.7→6.7 (ADR 0019) os campos
 * sobreviveram em produção (Hostinger MySQL `oimpresso`) mas NÃO existem em
 * migration nenhuma do repo. Esta migration traz o schema pra git de forma
 * idempotente (só adiciona se a coluna não existir).
 *
 * Validar cada coluna contra o DB de produção:
 *   ssh hostinger 'cd domains/oimpresso.com/public_html && \
 *     php artisan tinker --execute="echo json_encode(DB::select(\"SHOW COLUMNS FROM business\"));"'
 *
 * Confirmados pela migration anterior (2026_04_24_100000):
 *   - business.cnpj
 *   - business.razao_social
 *
 * Inferidos pelo padrão UltimatePOS BR + ADR ARQ-0003 (NfeBrasil cert A1):
 *   - inscricao_estadual, inscricao_municipal
 *   - regime_tributario (1=Simples, 2=Simples Excesso, 3=Normal)
 *   - optante_simples_nacional
 *   - cnae, codigo_municipio_ibge
 *   - certificado_a1_path, certificado_a1_senha (encrypted), certificado_a1_validade
 *
 * Relacionado: ADR ARQ-0003 NfeBrasil (cert storage criptografado),
 * ADR ARQ-0002 RecurringBilling (NFSe sub-módulo standalone),
 * ADR 0019 (upgrade 3.7→6.7).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('business', function (Blueprint $table) {
            if (!Schema::hasColumn('business', 'cnpj')) {
                $table->string('cnpj', 20)->nullable()->after('name');
                $table->index('cnpj');
            }

            if (!Schema::hasColumn('business', 'razao_social')) {
                $table->string('razao_social', 150)->nullable()->after('cnpj');
            }

            if (!Schema::hasColumn('business', 'inscricao_estadual')) {
                $table->string('inscricao_estadual', 30)->nullable()->after('razao_social');
            }

            if (!Schema::hasColumn('business', 'inscricao_municipal')) {
                $table->string('inscricao_municipal', 30)->nullable()->after('inscricao_estadual');
            }

            if (!Schema::hasColumn('business', 'regime_tributario')) {
                $table->enum('regime_tributario', ['1', '2', '3'])
                    ->nullable()
                    ->comment('1=Simples Nacional, 2=Simples Excesso, 3=Regime Normal')
                    ->after('inscricao_municipal');
            }

            if (!Schema::hasColumn('business', 'optante_simples_nacional')) {
                $table->boolean('optante_simples_nacional')->default(false)->after('regime_tributario');
            }

            if (!Schema::hasColumn('business', 'cnae')) {
                $table->string('cnae', 20)->nullable()->after('optante_simples_nacional');
            }

            if (!Schema::hasColumn('business', 'codigo_municipio_ibge')) {
                $table->string('codigo_municipio_ibge', 10)->nullable()->after('cnae');
            }

            // Cert A1 inline em business — padrão 3.7 simples (sem encryption per-business).
            // ADR ARQ-0003 NfeBrasil propõe schema mais robusto em `nfe_certificados`
            // com chave por business (ARQ-0003). Os campos abaixo coexistem como
            // legacy/compat — quando NfeBrasil for materializado, migrar daqui pra lá.
            if (!Schema::hasColumn('business', 'certificado_a1_path')) {
                $table->string('certificado_a1_path', 255)->nullable()->after('codigo_municipio_ibge');
            }

            if (!Schema::hasColumn('business', 'certificado_a1_senha')) {
                $table->text('certificado_a1_senha')->nullable()
                    ->comment('Encrypted via Laravel encrypt()')
                    ->after('certificado_a1_path');
            }

            if (!Schema::hasColumn('business', 'certificado_a1_validade')) {
                $table->date('certificado_a1_validade')->nullable()->after('certificado_a1_senha');
            }

            if (!Schema::hasColumn('business', 'certificado_a1_serial')) {
                $table->string('certificado_a1_serial', 100)->nullable()->after('certificado_a1_validade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business', function (Blueprint $table) {
            $colunas = [
                'cnpj',
                'razao_social',
                'inscricao_estadual',
                'inscricao_municipal',
                'regime_tributario',
                'optante_simples_nacional',
                'cnae',
                'codigo_municipio_ibge',
                'certificado_a1_path',
                'certificado_a1_senha',
                'certificado_a1_validade',
                'certificado_a1_serial',
            ];

            foreach ($colunas as $col) {
                if (Schema::hasColumn('business', $col)) {
                    if ($col === 'cnpj') {
                        try {
                            $table->dropIndex(['cnpj']);
                        } catch (\Throwable $e) {
                        }
                    }
                    $table->dropColumn($col);
                }
            }
        });
    }
};

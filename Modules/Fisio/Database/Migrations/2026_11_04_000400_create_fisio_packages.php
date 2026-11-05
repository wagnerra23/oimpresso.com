<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pacote de sessões do paciente (US-FISIO-020, etapa F3 §3.4.2).
 *
 *  - `fisio_packages`: quantas sessões, ritmo e validade. **Sem valor em dinheiro**: o preço
 *    vive na venda do núcleo (`transaction_id`, nulo até a US-FISIO-022), o título no
 *    Financeiro e a nota no NFSe. É a regra do lote 4: nenhum financeiro paralelo.
 *  - `fisio_package_ledger`: APPEND-ONLY. Crédito na abertura, débito por sessão, estorno por
 *    lançamento inverso e extensão de validade. O saldo é a soma, nunca uma coluna.
 */
return new class extends Migration
{
    /** Fonte única do enum e do trigger; igual ao bloco de memory/dominio/fisio.md. */
    private const KINDS = ['credito', 'debito', 'estorno', 'extensao'];

    public function up(): void
    {
        if (! Schema::hasTable('fisio_packages')) {
            Schema::create('fisio_packages', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                $t->string('title', 120);
                $t->unsignedSmallInteger('sessions_total');
                $t->unsignedTinyInteger('sessions_per_week')->nullable();
                $t->date('valid_until')->nullable()->comment('validade inicial; extensão vai no ledger');
                $t->unsignedInteger('transaction_id')->nullable()->index()->comment('venda do núcleo (US-FISIO-022)');
                $t->string('status', 20)->default('ativo')->comment('ativo | encerrado');
                $t->unsignedInteger('created_by');
                $t->timestamps();
                $t->index(['business_id', 'patient_id', 'status'], 'fisio_pkg_biz_pac_status_idx');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (! Schema::hasTable('fisio_package_ledger')) {
            Schema::create('fisio_package_ledger', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('package_id')->constrained('fisio_packages');
                $t->enum('kind', ['credito', 'debito', 'estorno', 'extensao']); // manter igual a KINDS (o dominio:check lê o literal)
                $t->smallInteger('sessions')->comment('+N crédito, -1 débito, +1 estorno, 0 extensão');
                $t->date('valid_until')->nullable()->comment('só na extensão');
                $t->string('ref', 40)->nullable()->comment('agendamento:<id> | estorno:<ledger_id> | manual');
                $t->string('note', 200)->nullable();
                $t->unsignedInteger('created_by');
                $t->timestamp('created_at')->useCurrent();
                $t->index(['business_id', 'package_id'], 'fisio_pkg_ledger_biz_pkg_idx');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            foreach (['UPDATE', 'DELETE'] as $op) {
                $nome = 'trg_fisio_package_ledger_no_'.strtolower($op);
                DB::unprepared("DROP TRIGGER IF EXISTS {$nome}");
                DB::unprepared("CREATE TRIGGER {$nome} BEFORE {$op} ON fisio_package_ledger FOR EACH ROW
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fisio_package_ledger é append-only. Correção é lançamento inverso.'");
            }
            $lista = "'".implode("','", self::KINDS)."'";
            DB::unprepared('DROP TRIGGER IF EXISTS trg_fisio_package_ledger_kind');
            DB::unprepared("CREATE TRIGGER trg_fisio_package_ledger_kind BEFORE INSERT ON fisio_package_ledger FOR EACH ROW
                BEGIN IF NEW.kind IS NULL OR NEW.kind NOT IN ({$lista}) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fisio_package_ledger: tipo fora do enum fechado.';
                END IF; END");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            foreach (['no_update', 'no_delete', 'kind'] as $sufixo) {
                DB::unprepared("DROP TRIGGER IF EXISTS trg_fisio_package_ledger_{$sufixo}");
            }
        }
        Schema::dropIfExists('fisio_package_ledger');
        Schema::dropIfExists('fisio_packages');
    }
};

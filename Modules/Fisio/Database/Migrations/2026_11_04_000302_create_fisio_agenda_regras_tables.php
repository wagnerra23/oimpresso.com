<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda do Fisio, parte 2: lista de espera, regras da clínica e bloqueio do app por faltas
 * (US-FISIO-013, etapa 04 §3.2 A7, A8, A9, A21). Só aditiva. `business_id` indexado + FK
 * (ADR 0093). Valor e sinal ficam para o lote do Financeiro: `deposit_percent` é só o gancho
 * (0 = desligado).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fisio_waitlist')) {
            Schema::create('fisio_waitlist', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                $t->unsignedInteger('professional_id')->nullable();
                $t->foreignId('type_id')->nullable()->constrained('fisio_appointment_types');
                $t->json('preferences')->nullable()->comment('{dias:[1..6], turnos:[manha,tarde,noite], ate:Y-m-d}');
                $t->enum('status', ['ativo', 'oferecido', 'atendido', 'saiu', 'expirado'])->default('ativo');
                $t->dateTime('offered_starts_at')->nullable();
                $t->dateTime('offer_expires_at')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'status'], 'fisio_wait_biz_status_idx');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (! Schema::hasTable('fisio_booking_settings')) {
            Schema::create('fisio_booking_settings', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->unique();
                $t->boolean('online_enabled')->default(false);
                $t->enum('who_can_book', ['pacote', 'todos', 'ninguem'])->default('todos');
                $t->json('open_slots')->nullable()->comment('{"1":["08:00",...],...} dia ISO 1=seg');
                $t->unsignedSmallInteger('min_notice_hours')->default(2);
                $t->unsignedSmallInteger('cancel_min_hours')->default(24);
                $t->unsignedSmallInteger('max_days_ahead')->default(30);
                $t->unsignedTinyInteger('no_show_block_after')->default(2);
                $t->boolean('waitlist_enabled')->default(true);
                $t->unsignedSmallInteger('waitlist_offer_minutes')->default(15);
                $t->boolean('checkin_enabled')->default(true);
                $t->unsignedTinyInteger('deposit_percent')->default(0)->comment('gancho do Financeiro; 0 = sem sinal');
                $t->unsignedSmallInteger('policy_version')->default(1);
                $t->text('policy_text')->nullable();
                $t->timestamps();
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (Schema::hasTable('fisio_patients') && ! Schema::hasColumn('fisio_patients', 'booking_blocked_at')) {
            Schema::table('fisio_patients', function (Blueprint $t) {
                $t->dateTime('booking_blocked_at')->nullable()->after('blocked_at')
                    ->comment('app sem autoagendamento depois de N faltas sem aviso (A21)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fisio_patients', 'booking_blocked_at')) {
            Schema::table('fisio_patients', fn (Blueprint $t) => $t->dropColumn('booking_blocked_at'));
        }
        Schema::dropIfExists('fisio_booking_settings');
        Schema::dropIfExists('fisio_waitlist');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agenda do Fisio, parte 1: tipos, atendimentos e bloqueios (US-FISIO-013, etapa 04 §3.2). Só aditiva.
 *
 * Tabelas próprias: o `bookings` do núcleo é reserva de mesa de restaurante e a agenda da
 * Oficina é por veículo (decisão registrada no SPEC, F3-T01). `business_id` indexado + FK em
 * todas (ADR 0093). Atendimento não tem ON DELETE CASCADE vindo do paciente: sai por status.
 */
return new class extends Migration
{
    /** Status do atendimento (A10). Fonte única: Appointment::STATUS. */
    private const STATUS = ['agendado', 'confirmado', 'chegou', 'em_atendimento', 'realizado', 'faltou', 'cancelado'];

    public function up(): void
    {
        if (! Schema::hasTable('fisio_appointment_types')) {
            Schema::create('fisio_appointment_types', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('name', 80);
                $t->unsignedSmallInteger('duration_min')->default(50);
                $t->enum('modality', ['presencial', 'teleconsulta'])->default('presencial');
                $t->boolean('online_bookable')->default(false);
                $t->boolean('active')->default(true);
                $t->timestamps();
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (! Schema::hasTable('fisio_appointments')) {
            Schema::create('fisio_appointments', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('patient_id')->constrained('fisio_patients');
                $t->unsignedInteger('professional_id')->comment('users.id');
                $t->foreignId('type_id')->constrained('fisio_appointment_types');
                $t->dateTime('starts_at');
                $t->dateTime('ends_at');
                $t->enum('status', self::STATUS)->default('agendado');
                $t->boolean('is_overbook')->default(false)->comment('encaixe confirmado (A4)');
                $t->enum('source', ['clinica', 'online', 'lista_espera'])->default('clinica');
                $t->dateTime('confirmed_at')->nullable();
                $t->string('confirmation_channel', 20)->nullable();
                $t->dateTime('arrived_at')->nullable();
                $t->dateTime('cancelled_at')->nullable();
                $t->enum('cancelled_by', ['clinica', 'paciente'])->nullable();
                $t->string('cancel_reason', 280)->nullable();
                $t->boolean('late_cancel')->default(false)->comment('dentro do prazo da política');
                $t->foreignId('rescheduled_from_id')->nullable()->constrained('fisio_appointments');
                $t->string('patient_note', 280)->nullable()->comment('recado ao marcar (A20)');
                $t->unsignedTinyInteger('patient_rating')->nullable()->comment('1..5, privada (A20)');
                $t->foreignId('record_id')->nullable()->constrained('fisio_records')->comment('evolução que fechou');
                $t->unsignedSmallInteger('policy_version')->nullable();
                $t->unsignedInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'professional_id', 'starts_at'], 'fisio_appt_biz_prof_inicio_idx');
                $t->index(['business_id', 'patient_id', 'starts_at'], 'fisio_appt_biz_pac_inicio_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('professional_id')->references('id')->on('users');
            });
        }

        if (! Schema::hasTable('fisio_schedule_blocks')) {
            Schema::create('fisio_schedule_blocks', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedInteger('professional_id');
                $t->dateTime('starts_at');
                $t->dateTime('ends_at');
                $t->string('reason', 120)->nullable();
                $t->unsignedInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'professional_id', 'starts_at'], 'fisio_block_biz_prof_inicio_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('professional_id')->references('id')->on('users');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fisio_schedule_blocks');
        Schema::dropIfExists('fisio_appointments');
        Schema::dropIfExists('fisio_appointment_types');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RecurringBilling v9,75 — schema aditivo Onda 1.
 *
 * Adiciona:
 *  - 3 tabelas novas: rb_subscription_notes, rb_subscription_favorites, rb_subscription_events
 *  - 8 colunas em rb_subscriptions (payment_method, last_jobsheet_id, *_cached, paused_until, churn_reason, contact_phone_cached)
 *  - 4 colunas em rb_plans (descricao_curta, fiscal_type, fiscal_cfop, fiscal_servico)
 *
 * Todas operações idempotentes (Schema::hasColumn / hasTable guards — ADR tech/0008).
 * Backfill cached cols via comando `php artisan rb:backfill-cached-fields` (Onda 2).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL: toda tabela nova tem business_id indexed.
 * Refs: ADR 0093 multi-tenant, Method KB-9,75, memory/requisitos/RecurringBilling/Index-visual-comparison.md
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->extendSubscriptions();
        $this->extendPlans();
        $this->createNotesTable();
        $this->createFavoritesTable();
        $this->createEventsTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('rb_subscription_events');
        Schema::dropIfExists('rb_subscription_favorites');
        Schema::dropIfExists('rb_subscription_notes');

        Schema::table('rb_plans', function (Blueprint $table) {
            foreach (['fiscal_servico', 'fiscal_cfop', 'fiscal_type', 'descricao_curta'] as $col) {
                if (Schema::hasColumn('rb_plans', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('rb_subscriptions', function (Blueprint $table) {
            foreach ([
                'contact_phone_cached', 'churn_reason', 'paused_until',
                'total_revenue_cached', 'failed_count_cached', 'total_paid_cached',
                'last_jobsheet_id', 'payment_method',
            ] as $col) {
                if (Schema::hasColumn('rb_subscriptions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function extendSubscriptions(): void
    {
        Schema::table('rb_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('rb_subscriptions', 'payment_method')) {
                $table->enum('payment_method', ['pix', 'boleto', 'card'])->nullable()
                    ->after('conta_bancaria_id');
            }
            if (! Schema::hasColumn('rb_subscriptions', 'last_jobsheet_id')) {
                $table->unsignedBigInteger('last_jobsheet_id')->nullable()
                    ->after('payment_method')
                    ->comment('Soft link Modules/Repair JobSheet — sem FK pra preservar SoC Tier 0 (ADR 0094 §5)');
            }
            if (! Schema::hasColumn('rb_subscriptions', 'total_paid_cached')) {
                $table->unsignedSmallInteger('total_paid_cached')->default(0)
                    ->after('last_jobsheet_id');
            }
            if (! Schema::hasColumn('rb_subscriptions', 'failed_count_cached')) {
                $table->unsignedSmallInteger('failed_count_cached')->default(0)
                    ->after('total_paid_cached');
            }
            if (! Schema::hasColumn('rb_subscriptions', 'total_revenue_cached')) {
                $table->decimal('total_revenue_cached', 14, 2)->default(0)
                    ->after('failed_count_cached');
            }
            if (! Schema::hasColumn('rb_subscriptions', 'paused_until')) {
                $table->date('paused_until')->nullable()
                    ->after('total_revenue_cached');
            }
            if (! Schema::hasColumn('rb_subscriptions', 'churn_reason')) {
                $table->string('churn_reason', 64)->nullable()
                    ->after('paused_until');
            }
            if (! Schema::hasColumn('rb_subscriptions', 'contact_phone_cached')) {
                $table->string('contact_phone_cached', 32)->nullable()
                    ->after('churn_reason')
                    ->comment('Denormalizado pra lista rápida — atualizado via Observer Contact');
            }
        });
    }

    private function extendPlans(): void
    {
        Schema::table('rb_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('rb_plans', 'descricao_curta')) {
                $table->string('descricao_curta', 200)->nullable()
                    ->after('description');
            }
            if (! Schema::hasColumn('rb_plans', 'fiscal_type')) {
                $table->enum('fiscal_type', ['nfe', 'nfse', 'none'])->default('none')
                    ->after('ativo');
            }
            if (! Schema::hasColumn('rb_plans', 'fiscal_cfop')) {
                $table->string('fiscal_cfop', 8)->nullable()
                    ->after('fiscal_type')
                    ->comment('CFOP NFe 55 quando fiscal_type=nfe');
            }
            if (! Schema::hasColumn('rb_plans', 'fiscal_servico')) {
                $table->string('fiscal_servico', 8)->nullable()
                    ->after('fiscal_cfop')
                    ->comment('Código serviço NFS-e quando fiscal_type=nfse');
            }
        });
    }

    private function createNotesTable(): void
    {
        if (Schema::hasTable('rb_subscription_notes')) {
            return;
        }

        Schema::create('rb_subscription_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('subscription_id')->constrained('rb_subscriptions')->cascadeOnDelete();
            $table->unsignedInteger('user_id')
                ->comment('Autor — FK users.id (UPos legado int unsigned)');
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['subscription_id', 'is_pinned'], 'rb_notes_sub_pin_idx');
            $table->index(['business_id', 'created_at'], 'rb_notes_biz_created_idx');

            $table->foreign('user_id', 'rb_notes_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    private function createFavoritesTable(): void
    {
        if (Schema::hasTable('rb_subscription_favorites')) {
            return;
        }

        Schema::create('rb_subscription_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('subscription_id')->constrained('rb_subscriptions')->cascadeOnDelete();
            $table->unsignedInteger('user_id')
                ->comment('Favorito pessoal — FK users.id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'subscription_id'], 'rb_fav_user_sub_unique');

            $table->foreign('user_id', 'rb_fav_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
        });
    }

    private function createEventsTable(): void
    {
        if (Schema::hasTable('rb_subscription_events')) {
            return;
        }

        Schema::create('rb_subscription_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->foreignId('subscription_id')->constrained('rb_subscriptions')->cascadeOnDelete();
            $table->enum('kind', [
                'event-create', 'event-status', 'event-plan',
                'event-charge', 'event-retry', 'event-nf', 'note',
            ]);
            $table->string('by_actor', 64)
                ->comment('Quem disparou: sistema, SEFAZ, Eliana, Wagner, contact.name, etc');
            $table->text('body');
            $table->dateTime('occurred_at')->index();
            $table->timestamps();

            $table->index(['subscription_id', 'occurred_at'], 'rb_events_sub_at_idx');
            $table->index(['business_id', 'occurred_at'], 'rb_events_biz_at_idx');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Data migration 1→N — copia cada `whatsapp_business_configs` (legacy 1:1)
 * pra `whatsapp_business_phones` (1:N) e vincula conversations/messages
 * existentes ao novo phone_id correspondente.
 *
 * ADR 0117 §Q6 — conversas antigas em prod migram pro 1º número
 * cadastrado com `label='Comercial'` (default seguro). Admin reclassifica
 * manualmente depois (US-WA-041 backlog).
 *
 * Idempotente — se já existe phone com label='Comercial' pra um business
 * (rodada anterior), pula seed dele.
 *
 * Tokens cifrados (`meta_*`, `zapi_*`) copiam ciphertext raw via DB::table
 * — mesmo APP_KEY decifra de qualquer tabela. Não precisa re-criptografar.
 *
 * Roda em transaction. Se algo falhar, rollback completo (nenhum phone
 * criado, nenhum conversation/message atualizado).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Skip se tabela legacy não existe (deploys novos do zero — fase pós-PR 5)
        if (! Schema::hasTable('whatsapp_business_configs')) {
            return;
        }

        DB::transaction(function () {
            $configs = DB::table('whatsapp_business_configs')->get();

            foreach ($configs as $config) {
                // Idempotência: se já tem phone label='Comercial' deste business, pula
                $existing = DB::table('whatsapp_business_phones')
                    ->where('business_id', $config->business_id)
                    ->where('label', 'Comercial')
                    ->first();

                if ($existing) {
                    $phoneId = $existing->id;
                } else {
                    $phoneId = DB::table('whatsapp_business_phones')->insertGetId([
                        'business_id' => $config->business_id,
                        'phone_uuid' => (string) Str::uuid(),
                        'label' => 'Comercial', // default Q6 — admin reclassifica depois

                        'driver' => $config->driver ?? 'zapi',
                        'fallback_driver' => $config->fallback_driver ?? 'meta_cloud',
                        'display_phone' => $config->display_phone ?? null,

                        // Meta Cloud (ciphertext raw — mesma APP_KEY decifra)
                        'meta_phone_number_id' => $config->meta_phone_number_id ?? null,
                        'meta_access_token' => $config->meta_access_token ?? null,
                        'meta_app_secret' => $config->meta_app_secret ?? null,
                        'meta_webhook_verify_token' => $config->meta_webhook_verify_token ?? null,

                        // Z-API (ciphertext raw)
                        'zapi_instance_id' => $config->zapi_instance_id ?? null,
                        'zapi_instance_token' => $config->zapi_instance_token ?? null,
                        'zapi_client_token' => $config->zapi_client_token ?? null,

                        // Baileys
                        'baileys_instance_id' => $config->baileys_instance_id ?? null,
                        'baileys_phone_e164' => $config->baileys_phone_e164 ?? null,
                        'baileys_verified_name' => $config->baileys_verified_name ?? null,
                        'baileys_profile_pic_url' => $config->baileys_profile_pic_url ?? null,

                        // LGPD
                        'lgpd_acknowledged_at' => $config->lgpd_acknowledged_at ?? null,
                        'lgpd_acknowledged_by_user_id' => $config->lgpd_acknowledged_by_user_id ?? null,

                        // Roteamento — legacy 1-número fazia tudo, então marca todas flags true.
                        // Admin desmarca conscientemente quando cadastrar 2º número.
                        'handles_repair_status' => true,
                        'handles_billing' => true,
                        'handles_jana_bot' => true,
                        'handles_outbound_default' => true,

                        // Bot e templates
                        'bot_enabled' => $config->bot_enabled ?? false,
                        'template_repair_ready_name' => $config->template_repair_ready_name ?? null,
                        'template_repair_waiting_parts_name' => $config->template_repair_waiting_parts_name ?? null,
                        'template_billing_due_name' => $config->template_billing_due_name ?? null,
                        'template_billing_paid_name' => $config->template_billing_paid_name ?? null,

                        // Driver health
                        'driver_health' => $config->driver_health ?? 'never_checked',
                        'driver_health_consecutive_failures' => $config->driver_health_consecutive_failures ?? 0,
                        'last_health_check_at' => $config->last_health_check_at ?? null,
                        'last_health_message' => $config->last_health_message ?? null,

                        'created_at' => $config->created_at ?? now(),
                        'updated_at' => $config->updated_at ?? now(),
                    ]);
                }

                // Conversations existentes apontam pro novo phone (todas, do business)
                // Idempotente — só atualiza onde phone_id ainda é NULL
                DB::table('whatsapp_conversations')
                    ->where('business_id', $config->business_id)
                    ->whereNull('whatsapp_business_phone_id')
                    ->update(['whatsapp_business_phone_id' => $phoneId]);

                // Messages idem
                DB::table('whatsapp_messages')
                    ->where('business_id', $config->business_id)
                    ->whereNull('whatsapp_business_phone_id')
                    ->update(['whatsapp_business_phone_id' => $phoneId]);
            }
        });
    }

    public function down(): void
    {
        // Rollback: as 3 migrations anteriores droppam tabelas/colunas;
        // esta data migration é no-op no down (dados já vão embora com schema).
    }
};

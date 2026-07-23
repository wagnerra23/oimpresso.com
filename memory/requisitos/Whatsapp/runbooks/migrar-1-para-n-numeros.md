---
id: requisitos-whatsapp-runbooks-migrar-1-para-n-numeros
---

# Runbook — Migração Whatsapp 1→N números por business

> **Decisão mãe:** [ADR 0117](../../../decisions/0117-multiplos-numeros-whatsapp-por-business.md)
> **US mãe:** US-WA-040 em [SPEC.md](../SPEC.md)
> **Charter mãe:** [`Settings.charter.md`](../../../../resources/js/Pages/Whatsapp/Settings.charter.md) — vai pra v2
> **Cliente piloto:** WR2 Sistemas (`business_id=1`) — Comercial + Financeiro
> **Estado:** rascunho 2026-05-09, aguardando ADR 0117 aprovada antes de qualquer Edit em código

## Objetivo

Migrar schema Whatsapp de 1 config/business pra N números/business **sem downtime** e **sem perder histórico** de conversas/mensagens em prod (WR2 + qualquer outro business já configurado).

## Pré-requisitos

- [ ] ADR 0117 com `status: aceito` + `accepted_at` preenchido
- [ ] Charter `Settings.charter.md` v2 aprovado por Wagner (Non-Goal removido)
- [ ] Backup MySQL Hostinger fresco (≤24h) — `mysqldump oimpresso > pre-migracao-115.sql`
- [ ] Daemon CT 100 rodando estável (sem regressão recente em `whatsapp-baileys`)
- [ ] Ninguém ativo no `/whatsapp/conversations` (preferencialmente fim de noite)

## Ordem de PRs (4 sequenciais, ≤300 LOC cada)

### PR 1 — Schema + Migration de dados

**Touchpoints:**
- `Modules/Whatsapp/Database/Migrations/2026_05_NN_000001_create_whatsapp_business_phones_table.php` (NOVO)
- `Modules/Whatsapp/Database/Migrations/2026_05_NN_000002_create_whatsapp_phone_user_access_table.php` (NOVO)
- `Modules/Whatsapp/Database/Migrations/2026_05_NN_000003_add_phone_id_to_whatsapp_conversations_and_messages.php` (NOVO)
- `Modules/Whatsapp/Database/Migrations/2026_05_NN_000004_seed_whatsapp_business_phones_from_configs.php` (NOVO — data migration)
- `Modules/Whatsapp/Entities/WhatsappBusinessPhone.php` (NOVO)
- `Modules/Whatsapp/Entities/WhatsappPhoneUserAccess.php` (NOVO)
- `Modules/Whatsapp/Tests/Feature/MultiTenantIsolationTest.php` (UPDATE)
- `Modules/Whatsapp/Tests/Feature/PhonesMigrationDataTest.php` (NOVO)

**Migration de dados (passo crítico):**

```php
// 2026_05_NN_000004_seed_whatsapp_business_phones_from_configs.php
public function up(): void
{
    DB::transaction(function () {
        $configs = DB::table('whatsapp_business_configs')->get();

        foreach ($configs as $config) {
            $phoneId = DB::table('whatsapp_business_phones')->insertGetId([
                'business_id' => $config->business_id,
                'phone_uuid' => Str::uuid(),
                'label' => 'Comercial', // default — admin reclassifica depois
                'driver' => $config->driver,
                'fallback_driver' => $config->fallback_driver,
                'display_phone' => $config->display_phone,
                'meta_phone_number_id' => $config->meta_phone_number_id,
                'meta_access_token' => $config->meta_access_token,
                'meta_app_secret' => $config->meta_app_secret,
                'meta_webhook_verify_token' => $config->meta_webhook_verify_token,
                'zapi_instance_id' => $config->zapi_instance_id,
                'zapi_instance_token' => $config->zapi_instance_token,
                'zapi_client_token' => $config->zapi_client_token,
                'baileys_instance_id' => $config->baileys_instance_id,
                'baileys_phone_e164' => $config->baileys_phone_e164,
                'baileys_verified_name' => $config->baileys_verified_name,
                'baileys_profile_pic_url' => $config->baileys_profile_pic_url,
                'lgpd_acknowledged_at' => $config->lgpd_acknowledged_at,
                'lgpd_acknowledged_by_user_id' => $config->lgpd_acknowledged_by_user_id,
                'handles_repair_status' => true,    // legacy comportamento (1 número fazia tudo)
                'handles_billing' => true,
                'handles_jana_bot' => true,
                'handles_outbound_default' => true,
                'template_repair_ready_name' => $config->template_repair_ready_name,
                'template_repair_waiting_parts_name' => $config->template_repair_waiting_parts_name,
                'template_billing_due_name' => $config->template_billing_due_name,
                'template_billing_paid_name' => $config->template_billing_paid_name,
                'driver_health' => $config->driver_health,
                'driver_health_consecutive_failures' => $config->driver_health_consecutive_failures,
                'last_health_check_at' => $config->last_health_check_at,
                'last_health_message' => $config->last_health_message,
                'created_at' => $config->created_at,
                'updated_at' => $config->updated_at,
            ]);

            // Conversations existentes apontam pro novo phone (todas, do business)
            DB::table('whatsapp_conversations')
                ->where('business_id', $config->business_id)
                ->update(['whatsapp_business_phone_id' => $phoneId]);

            // Messages idem
            DB::table('whatsapp_messages')
                ->where('business_id', $config->business_id)
                ->update(['whatsapp_business_phone_id' => $phoneId]);
        }
    });
}

public function down(): void
{
    // Rollback: as 3 migrations anteriores droppam tabelas/colunas;
    // esta migration de dados é no-op no down (dados já dropados).
}
```

**Validação pós-migration (manual):**

```sql
-- 1. Toda config legacy virou 1 phone
SELECT
    (SELECT COUNT(*) FROM whatsapp_business_configs) AS configs_legacy,
    (SELECT COUNT(*) FROM whatsapp_business_phones) AS phones_novos;
-- Esperado: igual

-- 2. Toda conversation tem phone_id válido apontando pro mesmo business
SELECT COUNT(*) FROM whatsapp_conversations c
LEFT JOIN whatsapp_business_phones p ON p.id = c.whatsapp_business_phone_id
WHERE p.id IS NULL OR p.business_id != c.business_id;
-- Esperado: 0

-- 3. Idem messages
SELECT COUNT(*) FROM whatsapp_messages m
LEFT JOIN whatsapp_business_phones p ON p.id = m.whatsapp_business_phone_id
WHERE p.id IS NULL OR p.business_id != m.business_id;
-- Esperado: 0

-- 4. Multi-tenant: nenhum phone_id de business=A vinculado a conversation de business=B
SELECT c.business_id, p.business_id, COUNT(*)
FROM whatsapp_conversations c
JOIN whatsapp_business_phones p ON p.id = c.whatsapp_business_phone_id
WHERE c.business_id != p.business_id
GROUP BY c.business_id, p.business_id;
-- Esperado: 0 rows
```

### PR 2 — Driver factory + send job + listeners

**Touchpoints:**
- `Modules/Whatsapp/Services/Drivers/DriverFactory.php` — assinatura muda
- `Modules/Whatsapp/Jobs/SendWhatsappMessageJob.php` — constructor ganha `$phoneId`
- `Modules/Whatsapp/Listeners/NotifyRepairCustomer.php` — resolve phone via `handles_*`
- `Modules/RecurringBilling/Listeners/NotifyInvoicePaid.php` (e similares) — idem
- `Modules/Whatsapp/Listeners/DispatchToJanaBot.php` — idem
- `Modules/Whatsapp/Tests/Feature/EventRoutingTest.php` (NOVO)
- Todos `*JobTest.php` que dispatch `SendWhatsappMessageJob` precisam atualizar args

**Validação:**
```bash
php artisan test --filter=Whatsapp
# todos verdes
```

### PR 3 — Settings UI v2 + Charter v2 + permissão nova

**Touchpoints:**
- `resources/js/Pages/Whatsapp/Settings.charter.md` — `charter_version: 2` (Non-Goal removido) — Wagner aprova
- `resources/js/Pages/Whatsapp/Settings.tsx` — DEPRECATED, redireciona pra `Settings/Index.tsx`
- `resources/js/Pages/Whatsapp/Settings/Index.tsx` (NOVO) — lista
- `resources/js/Pages/Whatsapp/Settings/Edit.tsx` (NOVO) — form per-phone
- `resources/js/Pages/Whatsapp/Settings/_components/EventRoutingSection.tsx` (NOVO)
- `resources/js/Pages/Whatsapp/Settings/_components/AttendantsAcl.tsx` (NOVO)
- `Modules/Whatsapp/Http/Controllers/Admin/SettingsController.php` — quebra em `index()` + `edit()` + `update()` + `destroy()` + `acl()`
- `Modules/Whatsapp/Http/Requests/PhoneSettingsRequest.php` (NOVO — extraído de `BusinessSettingsRequest`)
- DataController do módulo: nova permission Spatie `whatsapp.phones.manage`
- `memory/requisitos/Whatsapp/Settings-Index-visual-comparison.md` (NOVO — skill mwart-comparative obrigatória)
- `memory/requisitos/Whatsapp/Settings-Edit-visual-comparison.md` (NOVO — idem)

**Pré-deploy:** seed permission nova em prod via `php artisan db:seed --class=WhatsappPermissionsSeeder` (PR 3 inclui seeder).

### PR 4 — Inbox ACL + filtro

**Touchpoints:**
- `Modules/Whatsapp/Http/Controllers/Admin/ConversationsController.php` — `index()` filtra por phones do user
- `resources/js/Pages/Whatsapp/Conversations/Index.tsx` — dropdown filtro número
- `resources/js/Pages/Whatsapp/_components/ConversationList.tsx` — badge label do número
- Centrifugo subscribe filtrado por `phone_uuid` no `useEffect`
- `Modules/Whatsapp/Tests/Feature/InboxAclTest.php` (NOVO)
- Browser MCP smoke (`mwart-process` F4)

## Cutover (PR 4 mergeada → produção)

Padrão MWART F5 ([ADR 0104](../../../decisions/0104-processo-mwart-canonico-unico-caminho.md)):

1. **Aviso prévio cliente** — Wagner avisa Larissa (ROTA LIVRE biz=4) e si próprio (WR2 biz=1) com 48h de antecedência: "Vamos atualizar tela Settings Whatsapp pra suportar múltiplos números. Suas conversas atuais ficam intactas — vão aparecer ligadas ao número 'Comercial'. Você pode renomear depois ou cadastrar um número novo de Financeiro."
2. **Deploy off-hours** — Hostinger pull + migrations + assets build (skill `commit-discipline` + `runtime-rules-hostinger-ct100`)
3. **Smoke biz=1 manual** — Wagner cadastra 2º número (Financeiro) em WR2, marca `handles_billing=true` desmarcando do Comercial, dispara um boleto teste, verifica que vai pelo número correto
4. **Canary 7d** — monitorar `storage/logs/laravel.log` ALERT entries + `php artisan jana:health-check` cobre `multi_tenant_isolation` (a guard `whatsapp_phone_business_match` adicionada PR 1)
5. **Monitor 30d** — métrica de validação: 0 conversations cross-phone (query SQL acima)

## Rollback

**Fase 1 — antes do PR 5 dropar `whatsapp_business_configs`:**

Tabela legacy continua existindo com dados originais (PR 1 NÃO drop ela; só adiciona deprecated docblock). Rollback = reverter código pros commits pré-115, mantendo tabelas novas como dead weight (limpa em PR 5 cancelado).

**Fase 2 — depois do PR 5 dropar legacy:**

Restore do `pre-migracao-115.sql` no Hostinger MySQL → revert merge → deploy. Aceitar perda de eventuais cadastros novos feitos durante PR 5 (aviso prévio Wagner).

## Riscos conhecidos

| Risco | Probabilidade | Mitigação |
|---|---|---|
| Migration de dados deixa conversation órfã (phone_id NULL) | Baixa (transaction wraps tudo) | Validação SQL pós-migration obrigatória + abort PR 1 se ≠0 |
| Listener Repair dispara em 0 phones porque admin esqueceu de marcar `handles_repair_status` em algum | Média | Default migration marca todas flags `true` no phone seed (legacy 1-número fazia tudo). Admin desmarca conscientemente |
| Listener dispara em 2 phones por config inconsistente | Média | UI Settings warns inline (`<EventRoutingSection>`); listener escolhe id ASC + warning estruturado |
| Atendente perde acesso porque admin esqueceu cadastrar `whatsapp_phone_user_access` | Alta nas primeiras semanas | Empty state UI Inbox explícito + admin notification "X atendentes não tem acesso a nenhum número" |
| Migration roda em DB grande (10k+ conversations) demora demais | Baixa hoje (volume real é pequeno) | Migration usa UPDATE em batch via `business_id` (não scan full table) |

## Abertos pra Sprint posterior

- Audit log `whatsapp_audit_log` (Charter Sprint 5) — quem cadastrou/editou/desativou número, quando, IP
- US-WA-041 "Mover conversa pra outro número" — admin reclassifica conversa antiga pós-migration
- Dashboard métricas per-phone (custo, deflection, NPS por número) — extensão US-WA-021

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-09 | Wagner + Opus 4.7 | Rascunho inicial. Aguardando ADR 0117 aprovada antes de qualquer Edit em código. |

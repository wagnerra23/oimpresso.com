---
number: 127
title: Modules/Auditoria — UI rica + undo sobre activity_log existente
status: aceito
date: "2026-05-10"
deciders: [Wagner]
supersedes: []
references:
  - 0061-conhecimento-canonico-git-mcp-zero-automem.md
  - 0093-multi-tenant-isolation-tier-0.md
  - 0094-constituicao-v2-7-camadas-8-principios.md
  - 0101-tests-business-id-1-nunca-cliente.md
  - 0104-processo-mwart-canonico-unico-caminho.md
  - 0123-modules-arquivos-backbone.md
lifecycle: active
---

## Contexto

Wagner pediu (2026-05-10): **"como posso ter um active_log de cada alteração e poder desfazer alguma alteração indevida do usuário ou da IA"**.

Estado real do repo no momento da proposta:

- ✅ `spatie/laravel-activitylog ^4.8` instalado em `composer.json:47`
- ✅ Tabela `activity_log` com schema completo: `subject_id/type`, `causer_id/type`, `properties` JSON (old/new), `event` (created/updated/deleted), `batch_uuid`, **`business_id` Tier 0** (migrations 2019/2021/2023)
- ✅ `config/activitylog.php` configurado com `delete_records_older_than_days: 365`
- ✅ Rota `/reports/activity-log` (UltimatePOS jQuery datatables) em [routes/web.php:515](../../routes/web.php#L515) → [ReportController::activityLog:3629](../../app/Http/Controllers/ReportController.php#L3629), já scoped por `business_id`
- ✅ **7 Models modernos** com trait `LogsActivity` configurada (referência canônica = [Modules/Financeiro/Models/Titulo.php:26-35](../../Modules/Financeiro/Models/Titulo.php#L26)) — capturam `properties.old`/`properties.new` automaticamente, **dão undo possível**
- 🟡 **~30 controllers legacy** (Sell, Purchase, Stock, Repair, Contact, Accounting/JournalEntry) usando `activity()->log('descrição texto')` manual — grava só `description`, **sem old/new = sem undo**
- ❌ Sem distinção `causer_kind` — `causer_type` cai sempre em `\App\User` mesmo quando ação foi disparada pela Jana via tool MCP. Quando Jana começar a criar/alterar registros (US-NFE / US-COPI futuras), Wagner não consegue ver "isso foi a IA"
- ❌ Sem UI de undo, sem diff old↔new lado-a-lado, sem filtro "ações da IA últimas 24h", sem janela permissão por papel
- ❌ Sem whitelist explícita de irreversibilidade — nada hoje impede tentativa de revert em NFe autorizada / Marcacao append-only / Boleto pago

ADR 0123 cria `arquivos_audit_log` separado pra DMS (Modules/Arquivos) — escopo distinto desta ADR. Coexistem: `arquivos_audit_log` é append-only restrito a operações de arquivo (upload/download/classify); `activity_log` cobre alterações em entidades de negócio (Sell, Purchase, Titulo, etc).

## Decisão

Criar **`Modules/Auditoria/`** como módulo de governança que:

1. **Reusa** `activity_log` existente (não duplica tabela)
2. **Padroniza** Models críticos pra usar `LogsActivity` trait (substitui log manual `description`-only por log estruturado old/new)
3. **Adiciona** `causer_kind` + `agent_run_id` em `activity_log` pra distinguir User vs IA
4. **Provê** UI Inertia rica com filtros, diff side-by-side e botão Reverter com whitelist de irreversibilidade
5. **Substitui** `/reports/activity-log` legacy (redirect 301 → `/auditoria`)

### 7 princípios duros

1. **Não duplicar tabela.** `activity_log` é a fonte. Migrations só ADICIONAM colunas, não criam tabela paralela.

2. **Multi-tenant Tier 0 mantido** ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)). Toda query em `Modules/Auditoria` filtra por `business_id` da sessão. `withoutGlobalScopes` proibido sem comentário explícito superadmin.

3. **Causer dual.** Coluna nova `causer_kind` ENUM(`user`, `agent`, `system`, `api`) NOT NULL DEFAULT `user`. Coluna nova `agent_run_id` BIGINT UNSIGNED NULL — preenchida quando ação veio de tool MCP / Jana, aponta pro turno de chat que originou a ação.

4. **Whitelist de irreversibilidade.** Service `RevertService` carrega registry estático de Models bloqueados:
   - `Modules\PontoWr2\Models\Marcacao` — Portaria MTP 671/2021 append-only ([proibições Tier 0](../../memory/proibicoes.md))
   - `Modules\NfeBrasil\Models\Transaction` quando `cstat IN (100, 101, 135)` — autorizada/cancelada/inutilizada SEFAZ
   - `Modules\Financeiro\Models\TituloBaixa` quando `origem='asaas-paid'` — boleto pago externamente
   - `Modules\Repair\Models\OS` quando `nfse_emitida=true`
   - `App\Transaction` quando tem `transaction_payment` posterior referenciando ela
   - Outros: append-only por design (logs MCP, audit do próprio Auditoria, etc)

   Tentativa de revert em modelo bloqueado retorna 422 + mensagem específica explicando o porquê (cita lei/regra).

5. **3 níveis de permissão.** `Permission` Spatie:
   - `auditoria.view` — qualquer user que tem permissão sobre o subject_type (ex: vendedor vê auditoria de Sell que ele criou; gestor vê todas)
   - `auditoria.revert.own` — usuário reverte ação dele próprio em ≤ **24h** desde `created_at`, sem side-effect posterior
   - `auditoria.revert.any` — admin reverte qualquer ação ≤ **30d**, dentro da whitelist
   - `auditoria.revert.unlimited` — superadmin reverte sem janela temporal (mas whitelist irreversibilidade ainda vale)

6. **Reverter ação da IA NÃO exige aprovação extra** (Wagner 2026-05-10). Mas a linha original em `activity_log` recebe atualização via coluna nova `reverted_at` + `reverted_by_user_id` + `revert_reason` — gera métrica observável "% ações IA revertidas por humano nos últimos 30d" em `jana:health-check`.

7. **Cada revert gera novo activity_log entry** (event=`reverted`, batch_uuid linkando original). Audit do audit. Append-only conceitualmente.

### Schema (migrations adicionais)

```sql
-- 2026_05_NN_add_causer_kind_and_revert_to_activity_log.php
ALTER TABLE activity_log
    ADD COLUMN causer_kind ENUM('user','agent','system','api') NOT NULL DEFAULT 'user' AFTER causer_type,
    ADD COLUMN agent_run_id BIGINT UNSIGNED NULL AFTER causer_kind,
    ADD COLUMN reverted_at TIMESTAMP NULL AFTER properties,
    ADD COLUMN reverted_by_user_id BIGINT UNSIGNED NULL AFTER reverted_at,
    ADD COLUMN revert_reason VARCHAR(500) NULL AFTER reverted_by_user_id,
    ADD INDEX idx_business_kind_created (business_id, causer_kind, created_at),
    ADD INDEX idx_subject_reverted (subject_type, subject_id, reverted_at);
```

Sem alteração de schema do que já existe. Sem rename. Sem drop. Append-only no nível DDL.

### API (Service + Trait helper)

```php
// Modules/Auditoria/Services/RevertService.php
class RevertService
{
    public const UNREVERTIBLE = [
        \Modules\PontoWr2\Models\Marcacao::class => 'Portaria MTP 671/2021 — registro de ponto é append-only',
        // ... ver registry completo no SPEC
    ];

    public function canRevert(Activity $log, User $by): RevertCheck;  // {allowed: bool, reason: string}
    public function revert(Activity $log, User $by, string $reason): Activity; // retorna activity_log entry do revert
}

// Modules/Auditoria/Services/CauserResolver.php
// Hook em Modules/Copiloto/Ai/Agents/* — quando ação veio de tool MCP, seta:
//   causer_kind = 'agent'
//   agent_run_id = <id da run Jana atual>
class CauserResolver
{
    public function fromContext(): array; // ['kind' => 'agent', 'agent_run_id' => 123]
}
```

### UI

- **`/auditoria`** (Inertia) — Index com filtros: data range, causer_kind (User/IA/Sistema/API), subject_type, business (só superadmin), evento (created/updated/deleted/reverted), apenas-irrevertíveis
- **`/auditoria/{id}`** — Detail com diff old↔new renderizado side-by-side (lib `react-diff-viewer-continued` ou similar — escolher na implementação), botão Reverter com modal confirmação obrigando `revert_reason ≥ 10 chars`
- Cards no topo: "Ações 24h | Por IA | Revertidas | Bloqueadas (whitelist)"

### Plano de adoção

**Sprint 1 — Padronização Vestuario + Financeiro (~6h IA-pair):**

Adicionar trait `LogsActivity` configurada nos Models críticos do piloto, seguindo padrão [Modules/Financeiro/Models/Titulo.php:28-35](../../Modules/Financeiro/Models/Titulo.php#L28):

- US-AUDIT-001 — `App\Transaction` (sell + purchase + sell_return + etc) com `logOnly` campos críticos
- US-AUDIT-002 — `App\TransactionSellLine` + `App\TransactionPayment`
- US-AUDIT-003 — `App\Product` + `App\VariationLocationDetails` (estoque)
- US-AUDIT-004 — `App\Contact` (cliente/fornecedor) — campo `tax_number_1` redacted (PII LGPD)

Substituir chamadas `activity()->log('texto')` manuais nos 30 controllers existentes onde o Model agora tem trait — log automático passa a cobrir.

**Sprint 2 — Causer dual + agent_run_id (~3h IA-pair):**

- US-AUDIT-005 — Migration `add_causer_kind_and_revert_to_activity_log` (5 colunas + 2 índices)
- US-AUDIT-006 — `CauserResolver` + hook em `Modules/Copiloto/Ai/Agents/*` setando `causer_kind=agent` quando ação vem de tool MCP

**Sprint 3 — Modules/Auditoria scaffold + UI Inertia (~12h IA-pair):**

- US-AUDIT-007 — Scaffold `Modules/Auditoria/` (skill `criar-modulo`, ADR 0011 padrão Jana/Repair/Project)
- US-AUDIT-008 — `RevertService` com registry UNREVERTIBLE + Pest tests cobrindo as 5 categorias bloqueadas
- US-AUDIT-009 — Pages Inertia `Index.tsx` + `Detail.tsx` (com `*.charter.md` ao lado, [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §3 charter > spec)
- US-AUDIT-010 — Redirect 301 `/reports/activity-log` → `/auditoria` + permissões Spatie + Pest multi-tenant isolation

**Total ~21h IA-pair = 1 sprint pequeno** (3 sub-sprints sequenciais).

## Não-goals

- ❌ NÃO substitui `arquivos_audit_log` ([ADR 0123](0123-modules-arquivos-backbone.md)) — esse é específico DMS de operações de arquivo (upload/download/classify), tabela própria append-only. Coexistem.
- ❌ NÃO loga leitura de PII (audit de SELECT) — escopo desta ADR é escrita (CRUD). Pilar 3 LGPD (audit de leitura sensível) fica pra ADR separada quando Eliana finalizar estudo LGPD ([memory/regras-time.md](../../memory/regras-time.md))
- ❌ NÃO faz `redo` (refazer revert) — revert da revert exige criar entrada manual, não há botão dedicado. Caso de uso esperado é raro
- ❌ NÃO replica em segundo banco / cold storage MVP — `delete_records_older_than_days=365` continua. Arquivamento parquet em CT 100 fica pra fase futura se volume justificar
- ❌ NÃO faz diff em campos `text`/`longtext` muito grandes (truncar em 10KB e mostrar "diff truncado, baixar full")
- ❌ NÃO substitui `Modules/Copiloto` memory — Auditoria é "o que mudou em registros de negócio", Copiloto é "o que Jana lembra de conversas/decisões". Distintos
- ❌ Modules/Auditoria NÃO é módulo vendável separado — é governança interna gratuita do núcleo, todos businesses ganham automaticamente

## Alternativas consideradas

### A. Não fazer nada — manter `/reports/activity-log` como está
**Rejeitada.** Cobertura parcial (7 de ~50+ Models críticos), sem old/new na maioria, sem undo, sem distinção IA. Wagner pediu segurança real, não relatório.

### B. Pacote `OwenIt/laravel-auditing`
**Rejeitada.** Concorrente de Spatie, mas projeto já tem Spatie em uso (~30 lugares + 7 Models trait). Trocar = re-trabalho enorme + duas tabelas concorrentes durante migração. Spatie cobre o caso de uso.

### C. Tabela `audit_log` separada controlada por Modules/Auditoria
**Rejeitada.** Duplicaria storage, fragmentaria leitura (precisa join entre `activity_log` legacy + `audit_log` novo), confundiria devs. Migrations ADITIVAS na tabela existente é mais simples.

### D. Snapshots completos do row em vez de diff
**Rejeitada.** Inflação de storage 10-100x. `properties.old`/`properties.new` do Spatie já é o diff. Se um campo não mudou, não entra no log (`logOnlyDirty()`).

### E. Undo via reverse migration / event sourcing
**Rejeitada.** Event sourcing exigiria refatorar todos os Models de negócio — escopo monstruoso. Undo via `properties.old → fill() + save()` em transação cobre 90% dos casos com whitelist de irreversíveis cobrindo o resto.

### F. Permissão de undo via aprovação dupla quando IA é causer
**Rejeitada (default Wagner 2026-05-10).** Latência alta + Wagner é dono operador único na maioria do tempo. Métrica `% ações IA revertidas` é visibilidade suficiente. Se métrica subir, reabrir decisão.

## Consequências

✅ **Boas:**
- Reuso de `activity_log` existente — sprint pequeno (~21h IA-pair vs sprint inteiro novo)
- Multi-tenant Tier 0 preservado (`business_id` já scoped)
- 7 Models já modernos viram piloto imediato (Financeiro/Titulo, etc)
- Distinção User vs IA permite métrica observável de qualidade da Jana
- Whitelist explícita previne erro destrutivo (operador não consegue reverter NFe autorizada)
- 3 níveis de permissão dão segurança progressiva (user limitado, admin amplo, superadmin absoluto)
- Substituição `/reports/activity-log` → `/auditoria` consolida UX (uma tela só, sem confusão)

⚠️ **Tradeoffs:**
- Migração dos 30 controllers legacy não é instantânea — Sprint 1 cobre 8-10 Models críticos do piloto, restante migra opt-in conforme cada módulo for tocado (não-disruptivo)
- Storage `activity_log` cresce. `delete_records_older_than_days=365` mitigam. Se volume explodir, ADR amendada com archive parquet
- `properties` é TEXT no schema atual (não JSON nativo MySQL) — queries com `JSON_EXTRACT` funcionam em MariaDB/MySQL 5.7+ mas não são index-friendly; mitigação = filtros UI usam `subject_type` + `event` + `business_id` (todos indexados)
- Trait `LogsActivity` em Model com muitos campos exige `logOnly([...])` cuidadoso — campo de senha/token NUNCA pode entrar (já há `protected $hidden` mas trait não respeita por default; precisa verificar). Pest test `pii_redactor_in_activity_log` obrigatório
- Reverter pode quebrar cascading se FK ON DELETE CASCADE foi ativada entre revert original e tentativa — `RevertService` precisa detectar inconsistência e abortar com mensagem clara

## Validação Sprint 1

- ✅ Pest test: `Activity::query()->count()` em context biz=1 NÃO retorna activity de biz=4 (multi-tenant Tier 0)
- ✅ Pest test: criar/atualizar/deletar `App\Transaction` gera linha em `activity_log` com `properties.old`/`properties.new` corretas
- ✅ Pest test: campo de senha/token (se existir no Model) NÃO aparece em `properties` (usar `$hidden` + Pest assert)
- ✅ Smoke biz=1 ([ADR 0101](0101-tests-business-id-1-nunca-cliente.md)): criar venda → conferir entrada em `/auditoria` com diff legível

## Validação Sprint 2

- ✅ Pest test: ação disparada por tool MCP `tasks-create` (causer = Jana) grava `causer_kind=agent` + `agent_run_id` preenchido
- ✅ Pest test: ação direta de Controller (causer = User logado) grava `causer_kind=user` + `agent_run_id=NULL`
- ✅ Health-check `jana:health-check` ganha métrica `pct_ia_actions_reverted_30d`

## Validação Sprint 3

- ✅ Pest test: `RevertService::canRevert()` retorna `{allowed: false}` pra todos 5 Models da whitelist UNREVERTIBLE com razão correta
- ✅ Pest test: revert de `App\Transaction` simples (sem dependência) restaura state old + cria linha `event=reverted` linkada via `batch_uuid`
- ✅ Pest test: revert por usuário sem permissão `auditoria.revert.any` em ação de outro user → 403
- ✅ Pest test: tentativa de revert em ação > 24h por user com só `auditoria.revert.own` → 403 "janela expirada"
- ✅ Smoke: redirect 301 `/reports/activity-log` → `/auditoria` mantém querystring (filtros)
- ✅ Charter F1.5 ([ADR 0107](0107-emendation-0104-visual-comparison-gate-f3.md)): Wagner aprova screenshot Index + Detail antes de PR final

## Notas de governança

- ADR 0127 aceita por Wagner em 2026-05-10. Sprint 1 liberado.
- Tasks US-AUDIT-001..010 são criadas via `tasks-create` MCP após PR docs deste ADR mergear na `main` (publication-policy [ADR 0040](0040-policy-publicacao-claude-supervisiona.md)).
- Whitelist UNREVERTIBLE é append-only — adicionar Model novo via PR + comentário no registry; remover Model = ADR amendada (não pode ficar fácil enfraquecer segurança).

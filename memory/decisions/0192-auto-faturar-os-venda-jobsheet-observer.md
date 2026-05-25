---
slug: 0192-auto-faturar-os-venda-jobsheet-observer
number: 192
title: "Auto-faturar OS → Venda via JobSheetObserver (Integração Vendas × Oficina A1 KB-9.75)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-25"
accepted_at: "2026-05-25"
accepted_via: "Wagner aprovou em sessão `frosty-greider-83ab2f` 2026-05-25 ~14:15 via AskUserQuestion 4-decisões pós-PR #1497 plano F3 — confirmações exatas: Onda 6 wave separada · Observer trigger `entregue_completo` · commission_split shape `{mecanico_id, mecanico_pct, balcao_id, balcao_pct}` total=100 · merge review manual"
module: repair
quarter: 2026-Q2
tags: [repair, sells, observer, integration, cross-module, fsm, multi-tenant, tier-0, kb-9.75, a1, append-only]
supersedes: []
supersedes_partially: []
amends: []
superseded_by: []
related:
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0104-processo-mwart-canonico-unico-caminho"
  - "0105-cliente-como-sinal-guiar-sem-mandar"
  - "0114-prototipo-ui-cowork-loop-formalizado"
  - "0121-oimpresso-modular-especializado-por-vertical"
  - "0143-fsm-pipeline-live-prod-marco-2026-05-12"
  - "0178-sells-unified-tabs-visao-supersede-0136"
charter_impact:
  - "Sells/Index.charter.md v4 → v5 (payload `/sells-list-json` ganha `source` + `os_ref` + label; saved tree `Por origem` + KPI hero breakdown · Goals atualizado sem mudar Non-Goals)"
  - "Repair/ProducaoOficina/Index.charter.md (drawer ganha card 'Esta OS gerou venda' quando stage=entregue_completo · Goals atualizado)"
pii: false
review_triggers:
  - "Volume de splits com >2 pessoas detectado (>5% das vendas oficina) → migrar `commission_split` shape de tupla pra array flex `[{user_id, role, pct}]`"
  - "Cliente pede 'estornar' venda derivada (cancelar OS pós-entrega) → adicionar reverse hook no Observer (Transaction soft-delete)"
  - "Outro vertical (ComunicacaoVisual, Vestuario) reportar 'minha OS não vira venda automaticamente' → revisar trigger FSM `entregue_completo` vs stage canonical per-business via `business.repair_settings.auto_invoice_on_stage`"
  - "Performance Observer lento (>50ms p95 no transition) → mover pra Job assíncrono (queue `repair-derived-sales`)"
---

# ADR 0192 · Auto-faturar OS → Venda via JobSheetObserver

## Contexto

Wagner aprovou em 2026-05-25 (F2-screenshot) a entrega F1 do protótipo Cowork **Integração Vendas × Oficina** (PR [#1493](https://github.com/wagnerra23/oimpresso.com/pull/1493) mergeado em `b2fcabbf2`). É o pré-requisito **A1** do método KB-9.75 que sobe nota de Vendas de 9,0 → 9,3.

**Conceito:** OS do módulo `Repair` entregue (`JobSheet.current_stage_id` transiciona pra status `is_completed_status=true` canonical `entregue_completo` per [ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) vira **automaticamente** uma venda do módulo `Sells` (`Transaction` com `type=sell`) com `source: 'oficina'` + `os_ref: 'OS-NNNN'`. Módulos seguem separados (operacional respeita persona: mecânico vs vendedor balcão), costurados em 5 pontos UI cross-source.

Hoje (pré-decisão), OS entregue exige que vendedor balcão **manualmente** abra Sells/Create, busque cliente, lance peças/serviço, faça checkout. Wagner reportou em sessão `frosty-greider-83ab2f`:

> "OS entregue do módulo Oficina vira automaticamente venda do módulo Vendas com source:'oficina' + osRef. Módulos seguem separados, costurados em 5 pontos. Auto-faturar sem click manual."

**Pré-requisitos validados:**

- `Modules/Repair` FSM Pipeline LIVE em prod biz=1 desde 2026-05-12 ([ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md))
- `Modules/Financeiro/Observers/TransactionObserver.php` precedente arquitetural (Observer-based cross-module sync)
- `app/Domain/Fsm/Observers/TransactionFsmObserver.php` precedente FSM-aware Observer
- Multi-tenant Tier 0 `business_id` global scope ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))
- Vocabulário shared multi-vertical Repair ([ADR 0121](0121-oimpresso-modular-especializado-por-vertical.md))

**3 caminhos arquiteturais avaliados:**

| Caminho | Pattern | Acoplamento Sells↔Repair | Idempotência | Esforço |
|---|---|---|---|---|
| **A · Service direto** | `JobSheetService::deliver()` chama `TransactionService::createFromOs()` | Forte (Repair depende de Sells API) | Manual (lock por `os_ref`) | Baixo (~3h) |
| **B · Observer (escolhido)** | `JobSheetObserver@updated` cria `Transaction` quando FSM transiciona | Fraco (event-driven · Observer só ouve mudanças) | Natural (skip se `os_ref` existe + scope) | Médio (~5h) |
| **C · Event Bus assíncrono** | `OsDeliveredEvent` dispara `CreateDerivedSaleListener` via queue | Frouxo (queue desacopla) | Por job_id | Alto (~8h + infra queue worker) |

## Decisão

**Caminho B — JobSheetObserver síncrono com idempotência por (business_id, os_ref).**

### Trigger

Observer hook `updated` em `Modules\Repair\Entities\JobSheet`. Dispara quando:

```php
$jobSheet->wasChanged('current_stage_id')
    && $jobSheet->currentStage()->is_completed_status === true
    && $jobSheet->currentStage()->slug === 'entregue_completo'  // FSM canonical · ADR 0143
```

Wagner escolheu `entregue_completo` (terminal · cliente buscou OS · dinheiro entrou) sobre `pronto_para_retirar` (cliente vai buscar · pode desistir). Conservador.

### Schema (Onda 1)

Migration aditiva `database/migrations/YYYY_MM_DD_add_source_and_os_ref_to_transactions.php`:

```php
Schema::table('transactions', function (Blueprint $table) {
    $table->enum('source', ['balcao', 'oficina', 'online'])
        ->default('balcao')
        ->nullable()
        ->after('type')
        ->comment('Origem da venda · A1 KB-9.75 · ADR 0192');

    $table->string('os_ref', 20)
        ->nullable()
        ->after('source')
        ->comment('Referência cross-módulo OS-NNNN quando source=oficina · ADR 0192');

    $table->json('commission_split')
        ->nullable()
        ->after('os_ref')
        ->comment('Split { mecanico_id, mecanico_pct, balcao_id, balcao_pct } total=100 · ADR 0192');

    $table->index(['business_id', 'source', 'transaction_date'], 'idx_transactions_source');
});
```

- Default `'balcao'` retroativo (vendas legacy = balcão · zero breaking change)
- Índice composto `(business_id, source, transaction_date)` pra KPI breakdown query (`/sells-list-json` agrupa por source)
- `commission_split` JSON nullable (NULL = sem split · venda direta)

### Idempotência

Observer skip se Transaction já existe pra essa OS:

```php
$exists = Transaction::where('business_id', $jobSheet->business_id)
    ->where('os_ref', "OS-{$jobSheet->id}")
    ->exists();

if ($exists) {
    Log::info('JobSheetObserver: skip · Transaction já existe', [
        'os_ref' => "OS-{$jobSheet->id}",
        'business_id' => $jobSheet->business_id,
    ]);
    return;
}
```

Cenários:
- OS re-aberta e re-entregue → não duplica (idempotente por `os_ref`)
- Multi-tenant: filtro `business_id` impede que biz=1 reuse `os_ref` de biz=2 (mas garante isolation)
- Race condition: lock em `Transaction::lockForUpdate()` se necessário (avaliar pós-canary)

### `commission_split` shape

```json
{
  "mecanico_id": 42,
  "mecanico_pct": 70.0,
  "balcao_id": 17,
  "balcao_pct": 30.0
}
```

- `mecanico_id` obrigatório (referência `users.id`)
- `mecanico_pct + balcao_pct === 100` validado server-side
- `balcao_id` NULL quando 100% mecânico (`mecanico_pct=100`)
- Mais flexibilidade (>2 pessoas, role customizado) NÃO implementada agora — revisitar se sinal real aparecer per [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)

### OS sem nota fiscal (fiscal vazio)

Wagner decidiu: **OS sem NF-e/NFS-e vira venda mesmo assim** (`Transaction` criada com `fiscal = '{}'` vazio · sem badge SEFAZ na UI). Fluxo informal não bloqueia auto-faturar. Vendedor pode emitir nota depois manualmente via Sells/Index ações hover-reveal.

### Multi-tenant Tier 0 ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))

- Observer herda `business_id` da OS pra criar Transaction (nunca cross-tenant)
- Idempotência key `(business_id, os_ref)` impede biz=2 enxergar OS de biz=1
- Endpoint `/sells-list-json` mantém global scope existente · novo field `source` não fura tenancy
- Pest test obrigatório `MultiTenantIntegrationVendasOficinaTest` em Onda 2 — OS biz=1 cria Transaction biz=1 (não biz=2)

### Permissions

- Frontend Sells/Index coluna Origem renderiza sempre — gate de visibilidade é em `direct_sell.view` existente
- Repair drawer card "Esta OS gerou venda" só aparece pra usuário com permissão `direct_sell.view` (lookup já scopado)
- Backend Observer roda independente de permission (system-level event)
- Felipe (mecânico) filtro pré-aplicado: UI-only via `localStorage['oimpresso.sells.visao_origem']='oficina'` se `user.profile_default === 'mecanico'` — sem ACL hard

### Roadmap implementação (6 ondas)

Detalhes em [`memory/sessions/2026-05-25-plano-f3-integracao-vendas-oficina.md`](../sessions/2026-05-25-plano-f3-integracao-vendas-oficina.md) (PR #1497).

- **Onda 0** (este ADR · este PR)
- **Onda 1** Migration schema (`add_source_and_os_ref_to_transactions`)
- **Onda 2** `JobSheetObserver@updated` + payload endpoints (`SellController::getSellsListJson`, `ProducaoOficinaController::index`)
- **Onda 3** Frontend Sells/Index coluna Origem (`VdSource`)
- **Onda 4** Frontend Sells/Index saved tree + KPI breakdown + listener `oimpresso:open-venda`
- **Onda 5** Frontend Repair/ProducaoOficina drawer card "Esta OS gerou venda"
- ~~Onda 6 Sells/Caixa.tsx — wave separada (fora deste plano · backlog)~~

Ondas 3-5 podem rodar paralelo (Worker A Sells/Index sequencial · Worker B Repair isolado).

## Status

**aceito** — Wagner aprovou via 4 respostas em AskUserQuestion 2026-05-25 ~14:15 sessão `frosty-greider-83ab2f`. Implementação Onda 0 (este ADR) → Onda 1 (migration) → Onda 2 (Observer+payload) → Fase 2 paralela.

## Consequências

### Positivas

- **Auto-faturar reduz fricção operacional:** mecânico não depende de vendedor balcão criar venda manual quando OS entregue. Wagner observou em CYCLE-06 Martinho Caçambas que essa fricção atrasa cobrança 1-2 dias em média.
- **Cross-link bidirecional:** Sells/Index coluna Origem com `↗ #OS-NNNN` clicável + Repair drawer card "Esta OS gerou venda #V-NNNN" — analista enxerga origem do faturamento sem trocar de tela.
- **Backend evento-driven preserva SoC:** `Modules/Repair` não importa nada de `Modules/Sells` (Observer é fronteira). Pattern reutilizável pra outros cross-module flows (ex: Compras → recebimento estoque vira Inventory entry).
- **Idempotência natural por `os_ref`:** re-execução do Observer (re-deploy, replay de jobs) não duplica.
- **`commission_split` JSON dá flexibilidade sem schema rigid:** UI editor pode ser adicionado em PR futura sem migration.
- **Default `source='balcao'` retroativo zero breaking change:** vendas legacy continuam aparecendo na coluna como "Balcão" sem migração de dados.

### Negativas

- **Acoplamento implícito Repair → Sells via `Transaction` model:** se Sells refatorar `Transaction` schema (improvável · UPOS legacy), Observer quebra. Mitigação: Pest GUARD `MultiTenantIntegrationVendasOficinaTest` cobre regressão.
- **Observer síncrono pode adicionar latência em FSM transition:** estimate ~10-20ms por Transaction create. Se >50ms p95 → mover pra Job assíncrono (review trigger).
- **`commission_split` só cobre 2 pessoas (mecânico + balcão):** caso real de 3+ pessoas (ex: dois mecânicos co-trabalhando) força workaround (split em 2 e ajustar manual). Aceitável até sinal real.
- **OS reaberta pós-entrega não desfaz Transaction:** Wagner não pediu reverse hook. Se cliente reportar "OS entreguei errado, preciso cancelar", precisa Transaction `void` manual (review trigger).
- **NF-e/NFS-e dispatch async não acopla:** auto-faturar cria Transaction mas NÃO emite nota fiscal. Wagner aprovou — vendedor decide depois. Pode causar drift se cliente esperava NF automática.

### Pendências (não bloqueiam aceite)

- UI editor pra `commission_split` (atualmente só backend) — backlog
- Reverse hook Observer `JobSheet@deleting` ou `JobSheet::reopen()` — backlog
- Dashboard "Vendas por origem" `/relatorios/vendas-origem` — backlog (mock no protótipo Caixa)
- Per-business `auto_invoice_on_stage` config — backlog (review trigger ativa)

## Refs

- [PR #1493 — protótipo Cowork F1 mergeado · `b2fcabbf2`](https://github.com/wagnerra23/oimpresso.com/pull/1493)
- [PR #1495 — docs SYNC_LOG + TELAS_REVIEW_QUEUE · `11484114d`](https://github.com/wagnerra23/oimpresso.com/pull/1495)
- [PR #1497 — plano F3 (este ADR é Onda 0)](https://github.com/wagnerra23/oimpresso.com/pull/1497)
- [`prototipo-ui/INTEGRACAO_VENDAS_OFICINA.md`](../../prototipo-ui/INTEGRACAO_VENDAS_OFICINA.md) — README Cowork F1
- [`memory/sessions/2026-05-25-plano-f3-integracao-vendas-oficina.md`](../sessions/2026-05-25-plano-f3-integracao-vendas-oficina.md) — plano F3 6 ondas
- [ADR 0143 FSM Pipeline LIVE](0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — `entregue_completo` stage canon
- [ADR 0121 Vertical especializado](0121-oimpresso-modular-especializado-por-vertical.md) — vocabulário shared Repair
- `Modules/Financeiro/Observers/TransactionObserver.php` — precedente arquitetural
- `app/Domain/Fsm/Observers/TransactionFsmObserver.php` — precedente FSM-aware Observer

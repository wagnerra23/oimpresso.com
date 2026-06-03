# Auditoria — Timezone `now()` em contextos não-HTTP (London leak multi-tenant)

**Data:** 2026-05-31
**Origem:** auditoria Financeiro Método 9.75 → derivou do bug Carbon-3 em `Titulo::agingBucket` → Wagner reportou **clientes reclamando de datas/horas com ~3h de diferença**.
**Gravidade:** ALTA — dado de negócio multi-tenant gravado/exibido no fuso errado.
**Tasks:** [US-WA-308](P0) · [US-FIN-047](P1) · [US-FIN-048](P1 prevenção).

---

## 1. Sintoma → raiz

`~3h de diferença` = assinatura exata de **`Europe/London` (UTC+0) vs `America/Sao_Paulo` (UTC−3)**.

`now()` no oimpresso é **context-dependent**:

| Contexto | `now()` fica em | Por quê |
|---|---|---|
| Request HTTP autenticado | **fuso do business** ✅ | middleware `App\Http\Middleware\Timezone` ([Kernel.php:83](../../app/Http/Kernel.php)) faz `config(['app.timezone'=>$business->time_zone])` + `date_default_timezone_set()` |
| Fila / CLI / Job / Listener / Command | **`Europe/London`** ❌ | middleware NÃO roda → cai no default de [config/app.php:69](../../config/app.php) |

UltimatePOS grava datas **no fuso local do business** (colunas `dateTime` sem UTC). Logo gravar/comparar com `now()` em London corrompe o dado em **+3h** — e "pula de dia" perto da meia-noite (msg das 22h SP vira 01h do dia seguinte).

> ⚠️ Isto é **ortogonal** ao bug do Carbon-3 (sinal de `diffInDays`). O sinal mata buckets; o timezone desloca o instante. São dois problemas distintos.

---

## 2. Mapa (triados ~125 contextos não-HTTP que usam tempo)

Só **2 de 125** setam o fuso na mão (`RecurringExpense`/`RecurringInvoice`). O resto roda em London.

### 🔴 Tier S — grava/compara dado multi-tenant (cliente percebe)

| Arquivo:linha | Dano | Task |
|---|---|---|
| `ProcessIncomingWebhookJob` (insert `created_at`) | hora da msg +3h no inbox · **sintoma reportado** | US-WA-308 |
| `ProcessRemindersJob:71` | lembrete dispara ~3h cedo | US-WA-308 |
| `ProcessAsaasPixWebhookListener:164` | data da baixa PIX (fallback `?? now()`) | US-FIN-047 |
| `ProcessAsaasWebhookJob:49,74` · `ProcessInterWebhookJob:45,69` | data pagamento + `created_at` manual | US-FIN-047 |
| `CnabRetornoProcessor:252,318` | data crédito CNAB + dias de atraso | US-FIN-047 |
| `SyncBankStatementsJob:57-58` | janela `now()->subDays()..now()` deslocada | US-FIN-047 |
| `BridgeExpenseToTitulosCommand:226,263` | emissão/`created_at` de título | US-FIN-048 (P2) |

### ⚪ Tier Z — ignorar (observabilidade interna, fuso irrelevante)
~100 `*HealthCommand`, Governance, Jana, KB, Brief, drift, audit.

---

## 3. Fix do sintoma (US-WA-308) — snippet pronto

O `ProcessIncomingWebhookJob` recebe `$this->businessId`, e o `Info` do whatsmeow carrega o `Timestamp` do evento (hoje **descartado** — `extractFromWhatsmeow` não o extrai). Usar a hora real do provider em vez de `now()` corrige **dois** problemas: o fuso E o delay de fila.

**(a) Cada `extractFrom*` passa a expor a hora do evento:**
```php
// extractFromWhatsmeow(): adicionar ao array retornado
'sent_at' => $info['Timestamp'] ?? null,  // whatsmeow serializa time.Time (RFC3339 c/ offset, ou epoch)
// extractFromMeta():    'sent_at' => $msg['timestamp'] ?? null,   // epoch (segundos, UTC)
// extractFromZapi():    'sent_at' => $payload['momment'] ?? null, // epoch ms — CONFIRMAR campo
// extractFromBaileys(): 'sent_at' => $payload['messageTimestamp'] ?? null, // epoch
```

**(b) Helper que resolve a hora no fuso do business, com fallback seguro:**
```php
use Illuminate\Support\Carbon;

private function resolveSentAt(mixed $raw): Carbon
{
    if (empty($raw)) {
        return now(); // fallback = comportamento ATUAL, não piora nada
    }
    $tz = optional(\App\Business::find($this->businessId))->time_zone ?: config('app.timezone');
    try {
        $c = is_numeric($raw)
            ? Carbon::createFromTimestamp((int) $raw)  // epoch é UTC
            : Carbon::parse((string) $raw);            // ISO/RFC3339 respeita offset embutido
        return $c->setTimezone($tz);                   // normaliza pro fuso local do business (storage UPos)
    } catch (\Throwable) {
        return now(); // parse falhou → fallback seguro
    }
}
```

**(c) No insert, trocar `$now` da MENSAGEM pela hora real:**
```php
$sentAt = $this->resolveSentAt($msg['sent_at'] ?? null);
// messages.created_at  => $sentAt
// conversations.last_message_at / last_inbound_at => $sentAt
// (conversations.created_at pode seguir now() — é a criação do registro, não a hora da msg)
```

> **Verificação pré-merge:** teste com payload de msg às **22h SP** → `created_at` NÃO pula de dia; e confirmar o formato real de `Info.Timestamp` com 1 payload do daemon (o helper já é robusto a epoch OU ISO).
> **Por que não foi feito como PR já:** mexe na ingestão de TODAS as mensagens de TODOS os clientes, com 4 providers de formato distinto → merece teste real, não PR às cegas.

---

## 4. Prevenção (US-FIN-048)
1. **Job middleware/trait `SetsBusinessTimezone`** — todo job/listener multi-tenant aplica no `handle()`, replicando o efeito do middleware HTTP.
2. **PHPStan rule `no-naive-now-in-queue`** — falha o CI se Job/Command/Listener usa `now()`/`today()`/`Carbon::now()` sem setar o fuso. Encaixa na cultura (`no-missing-tenant-scope`, `no-silent-fallback`).

---

## 5. Notas / decisões

- **"Mergear branches do fleet" NÃO resolve:** os branches que tocaram `ProcessIncomingWebhookJob` (PR #716, #1811) **já estão MERGED** e não tratam timezone. O fix é mudança nova e independente — não há colisão (correção de uma análise inicial equivocada que leu um `git diff` de branches já mergeados como "refator ativo").
- **Fallback seguro** em todo fix: sem timestamp do provider → mantém `now()` (não regride).
- **Raiz profunda (futuro, refator grande):** migrar storage de datas pra **UTC** (padrão Laravel) e converter só na exibição — hoje UPos grava no fuso local do business.

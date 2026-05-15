# Tutorial — Customer Memory: como funciona, ponta a ponta

> **Pra quem é:** Wagner (e time MCP em breve — Felipe/Maiara/Eliana/Luiz).
> **Objetivo:** entender o sistema que você acabou de aprovar (PR #919 + extensões US-WA-VOZ-002), sem precisar ler 14 arquivos PHP.
> **Pré-leitura:** [análise voz cliente biz=1](2026-05-15-analise-voz-cliente-suporte-biz1.md) (decide o "porquê") + [estado da arte](2026-05-15-arte-atendimento-omnichannel-memoria-cliente.md) (referência externa).
>
> Documento ENSINA. Se sumir 6 meses de uso e voltar, este doc deve te re-orientar.

---

## §1 — Mapa mental em 30 segundos

```
Mensagem WhatsApp chega
        │
        ▼
[1] webhook Baileys/Meta/Z-API → insere em messages + conversations
        │
        ▼
[2] MessageObserver dispatcha OmnichannelMessageReceived
        │
        ▼
[3] TouchCustomerMemoryOnMessage (listener síncrono)
        ├─ cheap: UPSERT last_interaction_at (1 query)
        └─ se memória > 6h velha → dispatcha Job
                │
                ▼
[4] RebuildCustomerMemoryJob (queue=customer-memory)
        │
        ▼
[5] CustomerMemoryRebuilder.rebuild()
        ├─ Step 1: Identity resolution (phone → Contact CRM) via ConversationContactLinker
        ├─ Step 2: Stats agregados (n_conversations, n_msgs_in/out, first/last_interaction)
        ├─ Step 3: Denormalize display_name + consent_status (LGPD)
        ├─ Step 4: Funcionário responsável (assigned_user_id + most_active_user_id)
        └─ Step 5: Reclamações heurística keywords (top 5, 30d, sem IA)
        │
        ▼
[6] customer_memory.last_rebuilt_at = NOW()
        │
        ▼  (futuro/opcional)
[7] customer-memory:enrich-firebird → external_sources JSON
        │  (Wagner roda Python local → JSON → comando importa)
        │
        ▼
[8] Sidebar UI Customer 360 (PR futuro) consome
    GET /atendimento/customer/{external_id}/profile
```

**Tudo é multi-tenant Tier 0** (`business_id` em todo lugar) e **LGPD-aware** (`consent_status` + `erasure_requested_at`).

---

## §2 — Onde fica cada coisa (mapa físico de arquivos)

| Camada | Arquivo | Responsabilidade |
|---|---|---|
| **Schema** | [`Modules/Whatsapp/Database/Migrations/2026_05_15_230000_create_customer_memory_table.php`](../../Modules/Whatsapp/Database/Migrations/2026_05_15_230000_create_customer_memory_table.php) | Tabela `customer_memory` — 24 cols + 5 índices |
| **Schema ext** | [`...2026_05_15_240000_add_employee_complaints_external_to_customer_memory.php`](../../Modules/Whatsapp/Database/Migrations/2026_05_15_240000_add_employee_complaints_external_to_customer_memory.php) | +6 cols US-WA-VOZ-002 |
| **Entity** | [`Modules/Whatsapp/Entities/CustomerMemory.php`](../../Modules/Whatsapp/Entities/CustomerMemory.php) | Eloquent Model + HasBusinessScope + 12 constants enum + helpers |
| **Service core** | [`Modules/Whatsapp/Services/CustomerMemory/CustomerMemoryRebuilder.php`](../../Modules/Whatsapp/Services/CustomerMemory/CustomerMemoryRebuilder.php) | Brain — recompila tudo idempotente |
| **Service Firebird** | [`Modules/Whatsapp/Services/CustomerMemory/OfficeimpressoEnrichService.php`](../../Modules/Whatsapp/Services/CustomerMemory/OfficeimpressoEnrichService.php) | Cross-DB enrichment |
| **Source plug** | [`Modules/Whatsapp/Services/CustomerMemory/Sources/JsonFileFirebirdSource.php`](../../Modules/Whatsapp/Services/CustomerMemory/Sources/JsonFileFirebirdSource.php) | Driver Firebird JSON-file (substituível) |
| **Job** | [`Modules/Whatsapp/Jobs/RebuildCustomerMemoryJob.php`](../../Modules/Whatsapp/Jobs/RebuildCustomerMemoryJob.php) | Async wrapper do Rebuilder, queue=customer-memory |
| **Listener** | [`Modules/Whatsapp/Listeners/TouchCustomerMemoryOnMessage.php`](../../Modules/Whatsapp/Listeners/TouchCustomerMemoryOnMessage.php) | Real-time touch + dispatch heavy se stale |
| **Comando backfill** | [`Modules/Whatsapp/Console/Commands/CustomerMemoryBackfillCommand.php`](../../Modules/Whatsapp/Console/Commands/CustomerMemoryBackfillCommand.php) | One-shot per business |
| **Comando cron** | [`Modules/Whatsapp/Console/Commands/CustomerMemoryRefreshDailyCommand.php`](../../Modules/Whatsapp/Console/Commands/CustomerMemoryRefreshDailyCommand.php) | Daily 02h BRT — refresh stale |
| **Comando Firebird** | [`Modules/Whatsapp/Console/Commands/CustomerMemoryEnrichFirebirdCommand.php`](../../Modules/Whatsapp/Console/Commands/CustomerMemoryEnrichFirebirdCommand.php) | Importa JSON Firebird |
| **Endpoint API** | [`Modules/Whatsapp/Http/Controllers/Api/CustomerProfileController.php`](../../Modules/Whatsapp/Http/Controllers/Api/CustomerProfileController.php) | `GET /atendimento/customer/{ext}/profile` |
| **Python export** | [`scripts/firebird/export-customers.py`](../../scripts/firebird/export-customers.py) | Roda LOCAL Wagner — gera JSON |

---

## §3 — Fluxo "msg chega → memória atualiza" (real-time)

Pra cada mensagem inbound/outbound que entra no DB:

1. **`MessageObserver::created()`** dispara `OmnichannelMessageReceived` ou `OmnichannelMessageSent`.
2. **`TouchCustomerMemoryOnMessage`** escuta o evento (registrado em `WhatsappServiceProvider::boot()`).
3. Listener faz **2 coisas**:
   - **Cheap path** (sempre): `Rebuilder::touch()` executa 1 query UPSERT atômico:
     ```sql
     INSERT INTO customer_memory (business_id, customer_external_id, last_interaction_at, ...)
     VALUES (1, '5548999872822', NOW(), ...)
     ON DUPLICATE KEY UPDATE last_interaction_at = NOW();
     ```
     Custo: 1 query, race-safe, latência <5ms.
   - **Heavy path** (condicional): se `last_rebuilt_at > 6h` OU NULL, dispatcha `RebuildCustomerMemoryJob`. Job vai pra queue `customer-memory` (separada da `whatsapp-history`).
4. **Worker `queue:work` consome** o job em background, chama `Rebuilder::rebuild()` que faz **5 steps**:
   - Identity (1 cache lookup + até 2 queries Contact)
   - Stats (2 queries SQL: conversations + messages JOIN)
   - Denormalize (1 query Contact ou 1 query conversations)
   - Assigned user (2 queries SQL)
   - Reclamações (1 query messages + regex PHP)
   - **Total: ~6-8 queries SQL + UPDATE.**

**Custo per-msg em pico (1000 msgs/h):**
- 1000× cheap (1 query) = 1000 queries (~5s no Hostinger)
- ~100× heavy (estimativa 10% dispatch rate) = 100 jobs (~10min background)

Sustentável pra biz=1 com 249 clientes ativos.

---

## §4 — Identity Resolution (telefone → Contact CRM)

Pergunta canônica: **"recebi msg do +5548999872822 — quem é essa pessoa?"**

### Algoritmo (REUSA `ConversationContactLinker` — US-WA-078)

1. **Normalize** phone E.164 → só dígitos: `+5548 (9) 9987-2822` → `5548999872822`.
2. **Mínimo 8 dígitos** (curto demais = false positive garantido — Twilio Identity Resolution).
3. **Suffix 8 dígitos**: extrai últimos 8 (`99872822`) — colisão ~10⁻⁸, OK pra BR.
4. **Query SQL**:
   ```sql
   SELECT id, name, mobile, landline, alternate_number
   FROM contacts
   WHERE business_id = 1
     AND deleted_at IS NULL
     AND (mobile LIKE '%99872822%'
       OR landline LIKE '%99872822%'
       OR alternate_number LIKE '%99872822%')
   ```
5. **PHP filter de pós-processamento** — strip não-dígitos de cada campo e compara contra `5548999872822` OU sufixo `99872822`. Cobre formato legacy `(48) 99872-2822`.
6. **Resolver outcome**:
   - 0 matches → `MATCH_UNKNOWN`, confidence 0.0
   - 1 match exato (campo todo == E.164 sem +) → `MATCH_EXACT`, confidence 1.0
   - 1 match sufixo → `MATCH_SUFFIX_8`, confidence 1.0
   - 2+ matches → `MATCH_AMBIGUOUS`, confidence = 1/count (ex: 0.5 pra 2)

### Cache anti-stampede

`ConversationContactLinker::attemptLink()` cacheia o resultado por **1 hora** em Redis/DB com sentinel `0` pra cache miss (anti-thundering-herd se 100 msgs do mesmo phone chegam em burst).

`ContactObserver` **invalida o cache** quando phone fields do Contact mudam (UI edit) — fix cross-contact recorrente catalogado handoff 10:10.

---

## §5 — Funcionário responsável (US-WA-VOZ-002)

Wagner perguntou: "coloque junto com o perfil do cliente, **funcionário**, reclamação".

2 derivações automáticas no `Rebuilder::refreshAssignedUser()`:

### `assigned_user_id` — último a responder
```sql
SELECT m.sender_user_id
FROM messages m JOIN conversations c ON c.id = m.conversation_id
WHERE c.business_id = 1
  AND c.customer_external_id IN ('5548999872822', '+5548999872822')
  AND m.direction = 'outbound'
  AND m.sender_user_id IS NOT NULL
ORDER BY m.created_at DESC
LIMIT 1;
```

### `most_active_user_id` — quem mais atendeu histórico
```sql
SELECT m.sender_user_id, COUNT(*) as n
FROM messages m JOIN conversations c ON c.id = m.conversation_id
WHERE c.business_id = 1
  AND c.customer_external_id IN (...)
  AND m.direction = 'outbound'
  AND m.sender_user_id IS NOT NULL
GROUP BY m.sender_user_id
ORDER BY n DESC
LIMIT 1;
```

`sender_user_id` é NULL quando:
- Msg outbound veio do chip direto (Wagner mandando do celular — fora do oimpresso)
- Bot Jana respondeu (`sender_kind='bot'`)

Esses casos não geram "atendente atribuído" — corretamente.

### Aplicação no Sidebar (futuro PR UI)

```
┌─ SOBRE O CLIENTE ─────────────┐
│ +5527998915927 · Cliente A    │
│ Contact CRM #1234 · Florianópolis│
│ Maiara atendeu por último     │ ← assigned_user_id
│ (89 msgs históricas)          │ ← most_active_user_count
│                               │
│ ⚠ 12 reclamações 30d         │ ← total_reclamacoes
│   2 críticas, 5 altas         │ ← severities em reclamacoes_recentes
└───────────────────────────────┘
```

---

## §6 — Reclamações (heurística sem IA)

`Rebuilder::refreshReclamacoes()` roda regex PHP sobre **inbound msgs últimos 30 dias** (cap 500 msgs defensivo).

### Tabela de keywords (PT-BR)

| Severity | Keywords (regex) |
|---|---|
| **critica** | processo · advogado · absurdo · nunca mais · cancelar · reembolso · procon |
| **alta** | reclamar · péssimo · horrível · insuportável · inadmissível · esperando até agora |
| **media** | problema · erro · bug · atras · não consigo · não funciona · deu ruim · travou |
| **baixa** | dúvida · ajuda · preciso |

Primeira match vence (severities em ordem decrescente).

### Output JSON em `reclamacoes_recentes`

```json
[
  {
    "date": "2026-05-15 18:08:16",
    "msg_id": 29202,
    "severity": "alta",
    "preview": "Mensagem aqui, max 140 chars..."
  },
  ...
]
```

**Por que heurística e não IA?** Custo zero, instantâneo, determinístico. Quando PR #916 (análise IA per-msg) mergear, este código será trocado por:
```php
$total = DB::table('messages')
    ->where('analise_categoria', 'reclamacao')
    ->where('business_id', $bizId)
    ->where('created_at', '>=', now()->subDays(30))
    ->count();
```

Sem perda de função — só upgrade de precisão.

### Anti-padrão evitado

Só **inbound** count como reclamação. Se atendente outbound usa "qual o problema?" → NÃO conta. Testado em [`OfficeimpressoEnrichServiceTest`](../../Modules/Whatsapp/Tests/Feature/CustomerMemoryRebuilderTest.php) (case "reclamações ignora msgs OUTBOUND").

---

## §7 — Cross-DB Firebird OfficeImpresso (US-WA-VOZ-002)

Wagner: "nem todo cliente está cadastrado [no Hostinger], pesquise no firebird."

### Por que não conectar Firebird direto do Hostinger?

| Opção | Veredito |
|---|---|
| PHP `ibase`/`firebird` PECL no Hostinger | ❌ shared hosting não permite PECL |
| Tunnel HTTP Hostinger → CT 100 → Firebird remoto | ❌ complexo, lento, LGPD risk |
| Container Docker Firebird CT 100 | ❌ Firebird mora no servidor do cliente WR, não nosso |
| **Export JSON local + import comando** | ✅ idempotente, versionado, offline-friendly |

### Pipeline real (3 etapas)

```
[Wagner Windows local]
    ↓ python scripts/firebird/export-customers.py
[Arquivo customers-2026-05-15.json]
    ↓ scp / upload
[Hostinger storage/app/firebird/]
    ↓ php artisan customer-memory:enrich-firebird --business=1 --json=...
[customer_memory.external_sources atualizado]
```

### O que `external_sources` guarda

```json
[
  {
    "source": "firebird_office_json:2026-05-15",
    "cliente_id": 1234,
    "nome": "ACME LTDA",
    "fone1": "554899872822",
    "fone2": null,
    "email": "contato@acme.com.br",
    "bloqueado": false,
    "cpf_cnpj": "12345678000100",
    "cidade": "Florianópolis",
    "data_cadastro": "2024-03-15T00:00:00"
  }
]
```

Sidebar mostra: **"Cliente também cadastrado no sistema antigo WR como código 1234 — oportunidade de migração CRM"**.

### Interface plug-and-play

```php
interface FirebirdLookupSourceContract {
    public function lookupByPhone(string $phoneE164): array;
    public function isHealthy(): bool;
    public function sourceLabel(): string;
}
```

Hoje impl: `JsonFileFirebirdSource`. Futuro: `PdoFirebirdSource` (CT 100 com `ibase` ativo) ou `HttpFirebirdProxySource` (microservice em Python no CT 100). Mesma interface — `OfficeimpressoEnrichService` consome qualquer driver.

### Merge defensivo

`enrich()` **preserva** entries de outras fontes (ex: ASAAS, Inter). Re-enrich não duplica — apenas substitui entries com mesmo prefixo de source.

---

## §8 — LGPD compliance

| Direito (Lei 13.709/18) | Como atende |
|---|---|
| **Art. 7º — consentimento** | `customer_memory.consent_status` denormaliza `contacts.whatsapp_consent` (`given`/`withdrawn`/`unknown`) |
| **Art. 18 — direito de apagamento** | Endpoint admin seta `customer_memory.erasure_requested_at` → endpoint API retorna profile minimalista (`state: 'erasure_requested'`) |
| **Art. 20 — auditabilidade** | `rebuilt_via` + `last_rebuilt_at` rastreia origem de cada update |
| **Logs sem PII** | Telefones nos logs sempre redacted: `substr(0,4)+'***'+substr(-2)` |

Próximo passo (não neste PR): UI admin pra cliente exercer erasure (botão "Apagar memória deste cliente" no Sidebar Customer 360).

---

## §9 — Comandos canon (cheat sheet pra Wagner)

```bash
# Migração (idempotente, re-rodável)
php artisan migrate

# 1️⃣ Backfill inicial biz=1 (one-shot)
php artisan customer-memory:backfill --business=1 --dry-run     # preview zero-cost
php artisan customer-memory:backfill --business=1 --queue        # dispatcha jobs
php artisan queue:work database --queue=customer-memory --stop-when-empty

# 2️⃣ Cron daily refresh (alinhado Kernel.php 02h BRT)
php artisan customer-memory:refresh-daily                       # todos businesses
php artisan customer-memory:refresh-daily --business=1 --detail # 1 business, log linha-a-linha

# 3️⃣ Enrichment Firebird (Wagner roda local Python + scp + comando)
python scripts/firebird/export-customers.py \
    --dsn "localhost/3050:C:/dados/EMPRESA.FDB" \
    --user SYSDBA --password masterkey \
    --output storage/app/firebird/customers-$(date +%F).json

scp storage/app/firebird/customers-*.json \
    u906587222@148.135.133.115:domains/oimpresso.com/public_html/storage/app/firebird/

php artisan customer-memory:enrich-firebird \
    --business=1 \
    --json=storage/app/firebird/customers-2026-05-15.json \
    --detail

# 4️⃣ Smoke endpoint (Sidebar UI consome este)
curl -s -b cookies.txt \
    https://oimpresso.com/atendimento/customer/5548999872822/profile | jq .

# 5️⃣ Inspecionar resultados SQL direto
SELECT
  display_name,
  identity_match_method,
  n_msgs_total,
  total_reclamacoes,
  JSON_LENGTH(external_sources) as external_count,
  last_rebuilt_at
FROM customer_memory
WHERE business_id = 1
ORDER BY n_msgs_total DESC
LIMIT 20;

# Top atendentes (most_active_user_id)
SELECT u.username, COUNT(*) as customers_atendidos
FROM customer_memory cm
JOIN users u ON u.id = cm.most_active_user_id
WHERE cm.business_id = 1
GROUP BY u.username
ORDER BY customers_atendidos DESC;

# Clientes com mais reclamações
SELECT display_name, total_reclamacoes, JSON_EXTRACT(reclamacoes_recentes, '$[0].severity') as worst
FROM customer_memory
WHERE business_id = 1 AND total_reclamacoes > 0
ORDER BY total_reclamacoes DESC
LIMIT 10;
```

---

## §10 — Troubleshooting (problemas comuns + fix)

| Sintoma | Causa provável | Fix |
|---|---|---|
| `customer_memory` vazia mesmo após msgs chegando | Listener não registrado | Conferir `WhatsappServiceProvider::boot()` tem `Event::listen(OmnichannelMessageReceived::class, TouchCustomerMemoryOnMessage::class)` |
| Stats desatualizados >24h | Cron daily não rodou | `php artisan schedule:list` deve mostrar `customer-memory:refresh-daily` daily 02:00 |
| `contact_id=NULL` em todos clientes | `ConversationContactLinker` cache stale OR Contact phones mal-formatados | `php artisan cache:clear` + verificar `contacts.mobile` tem dígitos |
| Endpoint 401 `no_business_context` | Session middleware quebrada | Stack canon: `['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']` |
| `enrich-firebird` retorna match=0 | JSON sem clientes OR sufixos 8 dígitos não batem | Inspecionar JSON manualmente: `cat customers-X.json \| jq '.customers[0]'` |
| `analise_categoria` reclamação não povoa | Aqui é heurística (`total_reclamacoes`), não `analise_categoria` (PR #916 separado) | Conferir `total_reclamacoes` em vez de `analise_categoria` |

---

## §10.5 — Employee Performance (US-WA-VOZ-003)

Espelha customer_memory mas pro **outro lado da relação**: cada atendente do business tem 1 row em `employee_performance` com volume + velocidade + qualidade + cobertura + **nota 0-100 transparente**.

### Schema (tabela `employee_performance`)

```
business_id, user_id [PRIMÁRIO], heuristic_name [FALLBACK], display_name,
n_msgs_total, n_conversations_atendidas, n_clientes_diferentes,
tempo_resposta_mediana_s, tempo_resposta_p90_s, sla_breach_count,
reclamacoes_recebidas, csat_avg,
horas_ativas_distintas, hora_pico, dias_ativos_30d, primeira_atividade_at, ultima_atividade_at,
temas_dominantes, nota_geral, nota_breakdown, nota_calculada_em,
flags, last_rebuilt_at, rebuilt_via
```

### Identidade flexível (resolve bloqueio dos 75% sem sender_user_id)

- **PRIMÁRIO**: `user_id` (`messages.sender_user_id`) — atendente respondeu via UI Inbox
- **FALLBACK**: `heuristic_name` (regex `body LIKE '%Nome:%'`) — pega quem assina prefix
- Atendente pode ter 2 rows se às vezes usa UI, às vezes não (caso transição) — admin manual merge depois

### Scoring transparente (publicado pro time saber)

| Dimensão | Pts | Como pontua |
|---|---|---|
| Volume produtivo | 25 | n_msgs (1500=25, escala linear cap) |
| Diversidade clientes | 20 | n_clientes (150=20) |
| Velocidade resposta | 25 | mediana <60s=25, <300s=18, <900s=12, <1800s=6, else=2 |
| Profundidade conv | 15 | msgs/conv 5-15=15 (sweet spot), 3-20=10, else=5 |
| Cobertura horária | 10 | horas_distintas (10h=10) |
| Engajamento | 5 | placeholder (CSAT futuro) |

### Faixas
- `excelente` ≥ 90
- `bom` ≥ 70
- `regular` ≥ 50
- `abaixo` < 50

### Comandos canon

```bash
# Backfill detecta automaticamente atendentes (sender_user_id + heurísticos)
php artisan employee-performance:backfill --business=1 --dry-run
php artisan employee-performance:backfill --business=1 --queue

# Refresh daily (cron 02:30h BRT — Kernel.php)
php artisan employee-performance:refresh-daily

# Endpoints
GET /atendimento/employee/scorecards                       # ranking time
GET /atendimento/employee/10/scorecard                     # user real
GET /atendimento/employee/heur:Maiara/scorecard            # heurístico
```

### Casos de uso administrativos

1. **Wagner 1:1 mensal** — abre scorecard atendente, discute pontos fracos
2. **Sidebar Inbox** — mostra "Conversation atendida por: Maiara · 94/100"
3. **Bonus/promoção** — Wagner usa ranking como input objetivo
4. **Coaching dirigido** — atendente com `velocidade=6` ganha treinamento timing
5. **Carga balanceamento** — atendente sobrecarregado (volume=25) ganha pausa

### Dados reais biz=1 medidos pré-implementação (2026-05-15)

| Atendente | Nota |
|---|---|
| Luiz | 99/100 |
| Maiara | 94/100 |
| Felipe | 50/100 |

(amostra heurística — Felipe baixo por volume, não qualidade)

---

## §11 — O que VEM em seguida (próximos PRs decoupled)

| Onda | PR | Esforço estimado | Depende de |
|---|---|---|---|
| 2 | UI `<CustomerSidebar>` React em Inbox | 1-2 dias | Este PR (#919) mergeado |
| 3 | Análise IA per-msg (PR #916 draft) | 0.5 dia | Wagner aprovar custo IA |
| 4 | `conversation_insights` (camada 2 do roadmap) | 1 dia | PR #916 ativo OU heurística estendida |
| 5 | `customer_voice_snapshots` (brief diário Wagner) | 1 dia | Camada 3 |
| 6 | `product_feedback_signals` (Capterra reverso → roadmap) | 1 dia | Camadas 3+4 |
| 7 | Sync contatos Baileys (importar do daemon) | 0.5 dia | independente |

Cada onda é PR menor, decoupled, mergeável sozinho.

---

## §12 — TL;DR pra você lembrar daqui 6 meses

**A peça central é `customer_memory`**. 1 row por cliente final WhatsApp.

**Atualiza sozinha** via:
- Listener real-time (cheap, sempre)
- Job background (heavy, condicional >6h)
- Cron daily 02h (catch-all)

**Conecta cliente WhatsApp → Contact CRM** via `ConversationContactLinker` (já existia, reusei).

**Conecta cliente WhatsApp → Firebird legacy** via JSON export Python local.

**Sabe quem atendeu** (`assigned_user_id` + `most_active_user_id`).

**Detecta reclamações** sem IA (regex heurística PT-BR).

**Respeita LGPD** (`consent_status` + `erasure_requested_at`).

**É Tier 0 multi-tenant** (`business_id` em tudo).

**É testado** (17+ Pest cases + lint PHP em todos arquivos).

**Está pronto pra UI consumir** via `GET /atendimento/customer/{ext_id}/profile`.

Próximo trabalho real: **Sidebar React** que consome esse endpoint e mostra pro atendente saber em 2 segundos "quem é essa pessoa do outro lado".

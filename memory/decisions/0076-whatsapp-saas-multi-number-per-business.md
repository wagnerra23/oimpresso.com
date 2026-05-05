---
slug: 0076-whatsapp-saas-multi-number-per-business
number: 0076
title: "WhatsApp como SaaS multi-tenant — N números por business + pricing cliente paga"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-05
module: Copiloto
quarter: 2026-Q3
tags: [whatsapp, saas, multi-tenant, channel-adapter, pricing, copiloto, billing]
supersedes: []
supersedes_partially: [0074]
superseded_by: []
related: [0035, 0058, 0059, 0060, 0073, 0074, 0075]
pii: true
review_triggers:
  - "Volume passar 50 instâncias Evolution no mesmo daemon (avaliar shard)"
  - "ANPD publicar guidance específica sobre BYO-number em SaaS BR"
  - "Cliente pedir compartilhamento de número entre N businesses (cenário grupo econômico)"
---

# ADR 0076 — WhatsApp como SaaS multi-tenant: N números por business + cliente paga

## Contexto

ADR 0074 (manhã) definiu o adapter pattern; ADR 0075 (tarde-início) trocou provider pra estratégia 3-fase Evolution → Z-API → Meta Cloud. Ambas assumiam **modelo single-tenant evolutivo** — Wagner+Larissa começam, outros vêm depois — com **pendência aberta**: "Wagner decide modelo de pricing (oimpresso paga vs cliente paga vs SaaS pricing) **antes** de habilitar canário em prod" (ADR 0074).

Wagner respondeu (mesmo dia, tarde-final): **"vai ser SaaS, o número por `business_id` e em cada business deve poder ter vários números."**

Isso muda 2 coisas estruturais que precisam ser tratadas **antes** de qualquer linha de código de produção:

### 1. Modelo de tenancy (estrutural — afeta schema)

ADR 0074 desenhou tabela única `copiloto_channel_identity` mapeando `(channel, wire_id) → (business_id, user_id)`, onde `wire_id` era o **telefone do contato externo** (cliente da Larissa). Isso assumia implicitamente que **havia 1 número WhatsApp por business** (o número do business escutando todos os contatos).

Modelo correto SaaS:
- Cada `business` POSSUI 1 ou mais **números receptores** (ex.: ROTA LIVRE pode ter "+55-vendas", "+55-suporte").
- Cada **número receptor** atende N **contatos externos** (clientes da Larissa).
- Webhook chega NÃO com a info do business — chega com o `instance` do provider (ou `phone_id`/`wa_id` recebedor); precisa **rotear primeiro pelo número-receptor**, depois pelo contato emissor.

Sem essa separação, 2 tenants com clientes externos no mesmo número de telefone (cenário comum: revendedor da Larissa também é cliente de outro tenant via gráfica concorrente) vazariam mensagens entre si.

### 2. Modelo financeiro (estrutural — afeta billing/pricing)

Pendência da ADR 0074 fechada: **cliente paga** via SaaS. Implica:
- Custo de mensagem (Meta R$/conversa, Z-API mensalidade fixa, Evolution self-host R$0+infra) é **input do pricing**, não despesa Wagner.
- Tracking per-business obrigatório no `copiloto_audit_log` / `mcp_audit_log` (já existe pra IA — extender pra channels).
- Dashboard `/copiloto/admin/custos` (US-COPI-070) ganha breakdown por canal.
- Pricing modelo (assinatura mensal vs pay-as-you-go vs híbrido) **fica pra ADR comercial separada** — esta ADR só fecha "cliente paga, oimpresso não absorve custo".

## Decisão

### Tenancy: 2 tabelas (substitui `copiloto_channel_identity` da ADR 0074)

```
copiloto_channel_number              -- números QUE O BUSINESS POSSUI
  id                BIGINT PK
  business_id       UNSIGNED INT (FK lógico business.id; global scope)
  channel           VARCHAR(30)  -- 'evolution' | 'zapi' | 'meta' | 'web'
  wire_id           VARCHAR(60)  -- '+5511...' (número do business)
  provider_instance VARCHAR(80)  -- nome instância Evolution / phone_id Meta
  provider_token    TEXT         -- ENCRYPTED (Crypt::encrypt) — token específico instância
  label             VARCHAR(60)  -- 'vendas' | 'suporte' | etc — UI/log only
  active            BOOL DEFAULT 1
  created_by        UNSIGNED BIGINT -- user.id que cadastrou (audit)
  timestamps
  UNIQUE(channel, wire_id)
  UNIQUE(channel, provider_instance)
  INDEX(business_id, active)

copiloto_channel_contact             -- contatos externos que FALAM com aquele número
  id                BIGINT PK
  channel_number_id BIGINT FK copiloto_channel_number.id ON DELETE CASCADE
  business_id       UNSIGNED INT  -- denormalizado (scope rápido + LGPD audit)
  channel           VARCHAR(30)   -- denormalizado
  wire_id           VARCHAR(60)   -- '+5511...' (telefone do contato externo)
  user_id           UNSIGNED BIGINT NULL -- mapeia user UltimatePOS se conhecido
  contact_name      VARCHAR(120) NULL    -- pushName WhatsApp
  opted_in_at       TIMESTAMP NULL
  revoked_at        TIMESTAMP NULL       -- LGPD opt-out (SAIR)
  first_seen_at     TIMESTAMP DEFAULT now()
  last_seen_at      TIMESTAMP DEFAULT now()
  timestamps
  UNIQUE(channel_number_id, wire_id)
  INDEX(business_id, channel)
  INDEX(user_id)
```

**Por que `business_id` denormalizado em `copiloto_channel_contact`** mesmo já estando em `copiloto_channel_number`:
- Scope global queries (`->where('business_id', $X)`) sem JOIN.
- `multi-tenant-patterns` skill exige `business_id` direto na tabela pra não depender de FK válida em queries críticas.
- Custo storage trivial (INT).

### Resolvers: 2 etapas

```
ChannelNumberResolver::resolveByInstance(string $channel, string $providerInstance): ?ChannelNumber
  → returna o número receptor (business_id resolvido aqui)
  → null se instância desconhecida (404 webhook — possível ataque ou config drift)

ChannelContactResolver::resolveOrTouch(ChannelNumber $number, string $wireId): array
  → existe + opted_in + !revoked → libera chat livre
  → existe + !opted_in → fluxo opt-in (responder consentimento)
  → existe + revoked → silêncio (LGPD)
  → não existe → cria registro com opted_in_at=NULL + dispara opt-in flow
```

### Webhook flow atualizado

```
1. POST /api/copiloto/whatsapp/evolution/webhook
2. Verify signature → 401 se inválido
3. Driver parseWebhook → IncomingMessage (com providerInstance)
4. ChannelNumberResolver::resolveByInstance($channel, $instance)
   → null → 404 "instância desconhecida" + log alerta
5. tenant_allowlist? (Fase 0 = só biz=4) → 403 se fora
6. ChannelContactResolver::resolveOrTouch($number, $incoming->wireId)
7. Branch:
   - revoked → 200 silencioso
   - !opted_in & body normalizado != "ACEITO|OK|SIM" → enfileira reply consentimento
   - !opted_in & body == "ACEITO" → markOptIn + saudação
   - opted_in & body == "SAIR" → revoke + reply confirmação
   - opted_in & outros → enfileira ProcessIncomingChannelMessageJob → ChatService
```

### Config atualizada

Daemon-level (compartilhado entre instâncias):

```php
'channels.whatsapp.evolution' => [
    'base_url'       => env('EVOLUTION_BASE_URL', 'http://evolution.ct100.local:8080'),
    'global_api_key' => env('EVOLUTION_GLOBAL_API_KEY', ''),  // master server key
    'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET', ''),  // shared (mais simples Fase 0)
    'timeout_seconds' => (int) env('EVOLUTION_TIMEOUT', 10),
],
```

Per-instância (cadastrado em `copiloto_channel_number`):
- `provider_instance` = nome único da instância no Evolution (ex.: `biz4-vendas`)
- `provider_token` = token da instância (gerado pela Evolution ao criar instância) — encrypted

NÃO existe mais `EVOLUTION_INSTANCE` no `.env`. Cada business tem `provider_instance` no DB.

### Pricing/billing

- Custo por mensagem (Meta) ou mensalidade (Z-API) tracked em `mcp_audit_log` com label `gen_ai.channel.cost`.
- Dashboard `/copiloto/admin/custos` (US-COPI-070) ganha quebra por canal+business.
- ADR comercial separada definirá tier/preço — fora do escopo aqui.
- Fase 0 (dogfooding ROTA LIVRE) **não cobra** — pricing começa em Fase 1.

## Justificativa

- **Por 2 tabelas e não 1 com `is_owner` flag**: separação semântica é mais limpa, índices ficam naturais (UNIQUE separadas), FK cascade do contato pelo número evita órfãos. Custo: 1 JOIN extra no resolve — desprezível com índices.
- **Por `provider_token` encrypted no DB e não `.env`**: cada cliente terá seu token Evolution/Z-API/Meta. Colocar tudo no `.env` não escala (50 clientes × 3 tokens = poluição) e amarra credencial à infra. DB encrypted é multi-tenant-friendly (token por linha) + já segue padrão Laravel `Crypt`.
- **Por roteamento por `provider_instance` e não por `wire_id` recebedor**: Evolution API webhook payload tem `instance`; Meta Cloud tem `phone_number_id`; Z-API tem `instance_id`. Todos são opacos pro provider, mas estáveis. `wire_id` recebedor pode mudar (mesma instância, número rotacionado por ban). Instância é a chave correta.
- **Por `tenant_allowlist` ainda relevante na Fase 0**: durante dogfooding (só ROTA LIVRE), allowlist `[4]` evita que webhook acidental de instância de outro tenant entre — defesa em profundidade até processo de cadastro estabilizar.
- **Por SaaS-first desde o dia 1 (não single-tenant evolutivo)**: retrofit de schema multi-tenant em produção com cliente real é caríssimo (data migration, opt-in re-coletado, downtime). Pagamos a complexidade adicional agora pra economizar muito depois.
- **Por `created_by` em `channel_number`**: trilha de auditoria — quem cadastrou cada número, quando. Necessário pra LGPD + ANPD (Art. 37 — registro de operações de tratamento).

**Quando reabrir:**
- Volume > 50 instâncias Evolution no mesmo daemon → avaliar shard (múltiplos daemons CT 100).
- ANPD publicar guidance específica BYO-number em SaaS BR.
- Cliente pedir compartilhamento entre businesses (grupo econômico) → modelar `copiloto_channel_number_share`.

## Consequências

**Positivas:**
- Modelo SaaS-correto desde dia 1 — sem retrofit caro depois.
- Escala N clientes × M números cada sem mudança de schema.
- Provider tokens isolados por instância — vazamento de 1 não compromete os outros.
- Routing webhook explícito (instância → number → contact) torna multi-tenant leak praticamente impossível em código novo (precisa quebrar 2 resolvers + allowlist).
- Fechado pricing pendente da ADR 0074 — cliente paga, oimpresso não absorve custo.

**Negativas / Trade-offs:**
- Schema é mais complexo (2 tabelas + 2 resolvers + 1 join no caminho crítico do webhook). Custo: ~5ms extra por requisição.
- Cadastro de novo número exige UI admin (US a criar, ver SPEC) ou seed manual via console — não é "scan QR e tá funcionando".
- Token encrypted em DB exige gestão de `APP_KEY` consistente entre Hostinger e CT 100 (já é hoje, mas regredir vira incidente).
- Migrar dados Fase 0 da estrutura antiga (`copiloto_channel_identity` que **ainda não foi pra produção** — só commitada no scaffold US-COPI-089) requer drop e recreate. **Sem prejuízo** pq a tabela ainda não tem dados.

**Riscos mitigados:**
- Multi-tenant leak entre clientes externos do mesmo número físico → impossível: contato é escopado a `channel_number_id`, não a `wire_id` global.
- Provider token vazando em log → encrypted no DB + nunca logado em texto plano.
- Cadastro acidental de número alheio → `created_by` audit + UI exige role admin.
- Cliente revoga consentimento e mensagem ainda chega → revoked_at gate em resolver retorna 200 silencioso.

## Implementação — diff sobre scaffold US-COPI-089 (commit `d60ddc18`)

**Schema (migration única, edita a já commitada):**
- `2026_05_05_200000_create_copiloto_channels_tables.php` (renomear) cria `copiloto_channel_number` + `copiloto_channel_contact`. Drop da tabela antiga `copiloto_channel_identity` se existir (não foi pra prod).

**Refactor código (mantém adapter pattern):**
- `IncomingMessage` ganha `?string $providerInstance` (nullable pra `web` channel).
- `EvolutionApiChannel::parseWebhook` extrai `instance` do payload.
- `Services/Channels/ChannelIdentityResolver.php` → split em 2:
  - `ChannelNumberResolver.php` (novo)
  - `ChannelContactResolver.php` (renomeado, lógica adaptada)
- `EvolutionWebhookController` adapta fluxo 2-step.
- `Config/config.php` — remove `evolution.instance`; adiciona `evolution.global_api_key`.
- Tests refatorados — cross-tenant blindagem refeita pra novo schema.

**SPEC US-COPI-089 (atualizar):**
- Acrescenta steps de seed manual de `copiloto_channel_number` pra biz=4.
- Cria US-COPI-092 (UI admin pra cadastrar números — pré-requisito Fase 1 quando entrar 2º cliente).

## Pricing fechado (resposta à pendência ADR 0074)

| Modelo | Decisão |
|---|---|
| Quem paga custo de mensagem | **Cliente** (SaaS) |
| Wagner / oimpresso absorve | **Não** (exceto Fase 0 dogfooding ROTA LIVRE — gratuito por ser canário) |
| Tier/preço | **ADR comercial separada** (futura) — escopo desta ADR é só "cliente paga" |
| Tracking custo | `mcp_audit_log.cost_brl` (já existe pra IA; estender pra channels) + dashboard US-COPI-070 |

## Referências

- ADR 0074 — Channel adapter WhatsApp (pattern segue válido; pendência pricing fechada aqui)
- ADR 0075 — Estratégia 3-fase Evolution → Z-API → Meta Cloud (provider strategy ortogonal)
- ADR 0073 — Auto-capture turn-level (continua channel-agnostic)
- ADR 0058 — Centrifugo + FrankenPHP CT 100
- ADR 0059 — Governança self-host
- ADR 0060 — Tudo rede interna Proxmox
- US-COPI-070 — Dashboard custos IA (extender pra channels)
- US-COPI-089 — implementa esta ADR (atualizada pra fluxo 2-step)
- US-COPI-092 (futura) — UI admin pra cadastrar números
- Evolution API multi-instance — https://doc.evolution-api.com/v2/en/configuration/instances

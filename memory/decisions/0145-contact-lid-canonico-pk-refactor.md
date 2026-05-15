# ADR 0145 — Refactor `contact_lid` como chave canônica de identidade WhatsApp (feature-wish)

- **Status:** feature-wish ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — aguarda sinal qualificado pra implementação)
- **Data:** 2026-05-15
- **Proponente:** Wagner [W+C]
- **Decisores:** Wagner
- **Supersedes:** —
- **Amends:** [ADR 0093](0093-multi-tenant-isolation-tier-0.md) (Tier 0 multi-tenant) · [ADR 0117](0117-multi-numero-business-phone.md) (multi-número business phone) · [ADR 0135](0135-omnichannel-inbox-arquitetura.md) (Inbox arquitetura)

## Contexto

Sessão 14-15/mai descobriu que o esquema atual usa `customer_external_id` (string única ora LID `<random>@lid` ora phone E.164) como chave de threading de Conversation. Isto não distingue os **3 identifiers** que WhatsApp expõe em 2026:

| Identifier | Formato | Quando aparece | Pode resolver phone? |
|---|---|---|---|
| **PN** | `+5548999872822` | conversas legacy, phonebook resolvido | sim |
| **LID** | `14628809617558@lid` | Multi-Device privacy 2023+, Click-to-Chat, Status reply | **não diretamente** (per-USER, opaco) |
| **BSUID** | `user_id` Meta-oficial | Cloud API webhooks desde 31-mar-2026 | parcial (Meta resolve internamente) |

Estudo protocol-level [memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md](../sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md) (797 linhas, 37 WebSearch+9 WebFetch) confirmou que **TODOS os drivers (Baileys 6.7.9, Baileys 7.x, Evolution API, Z-API, whatsapp-web.js)** sofrem o mesmo blackbox LID. **Cloud API Meta** resolve internamente expondo `wa_id`+`user_id`.

PR #854 aplicou 3 defesas anti-cross-contact (linker suffix-8, resolver bloqueia manual sem webhook, persister consulta resolver no history-sync). PR #855 (`schema-3-identifiers`) ADICIONA as 3 colunas (`lid`, `phone_e164`, `bsuid`) em `conversations` mas **mantém `customer_external_id` como chave de threading** (string única) — semântica frágil persiste.

## Problema raiz que esta ADR endereça

Mesmo com PR #855 mergeada, o threading continua sendo por `customer_external_id` (string), o que produz 2 patologias persistentes:

1. **Split de conversação** quando o resolver descobre LID→phone tardiamente — uma conv `customer_external_id='+LID_cru'` vira órfã, próximas msgs criam outra `customer_external_id='+phone_real'`. PR #856 (observer backfill) mitiga mas não elimina.
2. **Dedup ambíguo** entre 2 Contacts CRM com mesmo `tail4` phone (incident Wagner-Eliana). Linker hoje usa suffix-8 (PR #854) — melhor que tail4 mas ainda fuzzy.

## Decisão proposta (feature-wish, NÃO implementar ainda)

**Promover `contact_lid` (LID Multi-Device) a chave canônica de identidade**, mantendo `phone_e164` + `bsuid` como atributos enriquecidos.

### Modelo conceitual

```
ANTES (hoje):
  Conversation.customer_external_id (STRING) = chave única
  ├─ "+5548999872822" (phone)
  ├─ "+14628809617558" (LID cru — quando resolver falha)
  └─ ambíguo

DEPOIS (esta ADR):
  Conversation.contact_lid (STRING NOT NULL) = chave canônica
  + Conversation.phone_e164 (STRING nullable) = atributo enriquecido
  + Conversation.bsuid (STRING nullable) = atributo enriquecido
  + Conversation.customer_external_id mantido pra backward-compat
    mas DEPRECATED (computed property apontando pra contact_lid)
```

### Por que LID como chave (e não phone)

Recomendação oficial Z-API confirmada via [doc lid](https://developer.z-api.io/en/tips/lid):
> *"`chatLid` is described as the unique identifier most stable"*

- **LID é per-USER imutável** ([Whapi help](https://support.whapi.cloud/help-desk/faq/whatsapp-lid-lid)) — persiste por toda vida do account
- **Phone pode trocar** (cliente migra número) ou vir UNRELIABLE em scenarios privacy
- **BSUID Meta-oficial** convive como atributo enriquecido (Cloud API) — não substitui

### Backward-compat com biz=1 prod (Wagner: "nunca perca mensagem")

Migration em fases (zero perda):

**Fase 0 — Backfill (1 dev-day):**
- Para cada Conversation existente, calcular `contact_lid` a partir do payload mais recente de `messages`
- Quando payload tem `key.remoteJid` com `@lid` → extrair LID, gravar em `contact_lid`
- Quando payload tem só `@s.whatsapp.net` (phone real direto) → criar LID sintético `synthetic-pn-<phone>` (marca distinta) ou consultar `LidPhoneMap` reverso

**Fase 1 — Threading dual (1 dev-day):**
- MessagePersister escolhe chave por prioridade: `contact_lid` (se conhecido) > `phone_e164` (se conhecido) > `customer_external_id` (legacy fallback)
- ConversationContactLinker aceita ambas chaves no lookup

**Fase 2 — DEPRECATION `customer_external_id` (1 dev-day):**
- Marcar campo como deprecated em Eloquent doc + log warning quando lido
- 60d soft-deprecation antes de drop final

**Fase 3 — DROP `customer_external_id` (futuro, requer Wagner aprovar):**
- Remover de queries, índices, e finalmente coluna
- Reversível via migration `down()`

## Pré-requisitos (sinais qualificados pra ativar — [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md))

Esta ADR fica em **feature-wish** até pelo menos 1 dos sinais qualificados:

1. **Segundo incident cross-contact** em biz=1 prod (probabilidade baixa após PR #854-858 mergearem)
2. **Wagner ativa Cloud API canary** biz=99 (PR #858) — Cloud API entrega `wa_id`+`user_id` nativos, refactor `contact_lid` vira pré-req pra integração real
3. **Volume biz=1 cresce 5x+** (~250-1000 msgs/dia) ou novos biz verticais ativados (ComVis, OficinaAuto) — multi-tenant ganha N conversas com mesmo phone em business_ids diferentes, fuzzy match piora

> **Nota:** migração Baileys 7.x NÃO é sinal qualificado — é decisão já tomada (Wagner 13-15/mai, ver [feedback-baileys-7x-decisao-irreversivel.md](../reference/feedback-baileys-7x-decisao-irreversivel.md)). Quando essa migração rodar, traz `getPNForLID()` nativo que facilita Fase 0 backfill desta ADR — sinergia de execução, não pré-condição.

## Estimate (calibrado [ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md) fator 10x IA-pair)

- **Fase 0+1:** 2 dev-days IA-pair (~8h Claude) — backfill + threading dual
- **Fase 2:** 1 dev-day — deprecation + soft warnings
- **Fase 3:** 1 dev-day após 60d observation — drop final
- **Total:** ~4 dev-days IA-pair distribuídos em ~75 dias calendário (deprecation cycle)
- **Pest tests obrigatórios:** mínimo 8 testes (1 por fase × 2 happy/sad path)
- **Margem 2x ([ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md)):** considerar 8 dev-days IA-pair total

## Alternativas consideradas (e rejeitadas hoje)

### A. Manter status quo (`customer_external_id` único)

Hoje. **Rejeitada porque:** PR #854 fechou os 3 gaps imediatos mas a semântica frágil persiste. Próximo cross-contact (probabilidade não-zero) repete o ciclo.

### B. Adicionar `customer_external_id_v2` paralelo sem deprecar v1

Coexistência dual permanente. **Rejeitada porque:** complexidade cognitiva alta, dual maintenance em todas queries, atrito pra novos devs.

### C. Implementar AGORA sem aguardar sinal

Refactor proativo. **Rejeitada porque:** [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) exige sinal qualificado; 4-8 dev-days é investimento alto sem dor recorrente medida.

## Riscos e mitigações

| Risco | Probabilidade | Mitigação |
|---|---|---|
| **LID Multi-Device opaco impede dedup** quando cliente troca dispositivo | Baixa (LID é per-USER imutável por design WhatsApp) | Fase 0 backfill grava `phone_e164` quando conhecido — dedup secundário continua via phone |
| **Re-ingestão de history sync** depois do refactor cria conversations duplicadas | Média (já vivemos isso em 14/mai) | Fase 1 implementa `Conversation::scopeWithIdentity($lid, $phone, $bsuid)` que aceita qualquer um dos 3 — UNIQUE constraint em `(business_id, contact_lid)` previne duplicação |
| **Cliente Larissa ROTA LIVRE percebe split de conversação durante deprecation** | Baixa (deprecation cycle 60d permite ajuste fino) | Telemetria OTel `conversation_split_observed` durante Fase 1+2 alerta se ocorrer |
| **Cloud API Meta entrega `user_id` BSUID diferente do `chatLid` Baileys pro MESMO cliente** | Alta (driver diferente = identifier diferente) | `LidPhoneMap` vira `IdentityMap` com 3 colunas (`lid`, `phone_e164`, `bsuid`) — qualquer dos 3 resolve pro Contact CRM |

## Métricas de sucesso

Após implementação completa (Fase 3 done):

- **Zero conversations órfãs** com `contact_id=null` por causa de LID não-resolvido (hoje: 1 conv #37 + provavelmente outras em backfill)
- **Zero cross-contact incidents** em 90d após Fase 3 (hoje: 1 incident em 30d catalogado)
- **Threading consistente Baileys + Cloud API** (mesmo Contact CRM reconhece cliente independente do driver)
- **% conversations com `contact_lid` populado**: 100% em biz=1 (hoje: ~0%, recém-criado pela PR #855)

## Decisão (hoje)

- **Status `feature-wish` aceito.** ADR registrada formalmente em git pro time (Felipe/Maiara/Eliana[E]/Luiz) via MCP server.
- **NÃO implementar agora** — aguardar sinal qualificado (lista acima).
- **PRs em andamento permanecem foco:** #854 (anti-cross-contact P0), #855 (schema 3-identifiers — adiciona colunas mas mantém customer_external_id como chave), #856 (observer backfill), #857 (backup auth_state), #858 (Cloud API canary stub).
- **Revisitar esta ADR** quando: PR #858 promover de stub a canary real biz=99, OU 2º cross-contact incident, OU volume biz=1 cruzar 250 msgs/dia consistentes.

## Referências

- [memory/sessions/2026-05-14-whatsapp-incident-inbox-lid-cross-contact.md](../sessions/2026-05-14-whatsapp-incident-inbox-lid-cross-contact.md) — incident 14/mai catalogado
- [memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md](../sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md) — estudo protocol-level 797 linhas
- [Z-API lid docs](https://developer.z-api.io/en/tips/lid) — chave primária `chatLid`
- [Baileys 7.x migration guide](https://baileys.wiki/docs/migration/to-v7.0.0/) — `getPNForLID()` nativo
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Tier 0 multi-tenant IRREVOGÁVEL
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — sinal qualificado guia priorização
- [ADR 0117](0117-multi-numero-business-phone.md) — multi-número business phone
- [ADR 0135](0135-omnichannel-inbox-arquitetura.md) — Inbox arquitetura

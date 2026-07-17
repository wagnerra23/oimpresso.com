---
slug: 0142-notas-internas-sinal-treino-jana
number: 142
title: "Notas internas como sinal de treino pra Jana — slash commands + 3 tabelas + parser"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-12"
module: whatsapp
tags: [whatsapp, jana, memoria, slash-commands, atendimento, training-signal, ux-chatwoot]
supersedes: []
supersedes_partially: []
amends: [0135]
superseded_by: []
related: [0035-stack-ai-canonica-wagner-2026-04-26, 0048-framework-agentes-laravel-ai-vizra-rejeitada, 0052-contextonegocio-expor-multiplos-angulos, 0093-multi-tenant-isolation-tier-0, 0135-omnichannel-inbox-arquitetura]
pii: false
review_triggers:
  - "Volume de correções `/corrigir` > 50/mês/business → ativar export JSONL automático pra fine-tune"
  - "Cliente reportar `/lembrar` virou ruído (fatos contraditórios) → revisar promotion ↔ valid_until semantics"
  - "Atendente confundir Reply x Note vazando pro WhatsApp → emergência, ADR amend P0 com gate adicional"
  - "Slash command novo ganha demanda recorrente (Wagner 3+ pedidos sessão) → estender parser"
  - "Schema `copiloto_memoria_facts.metadata` JSON virar bottleneck performance (>1M rows) → considerar colunas concretas"
---

# ADR 0142 — Notas internas como sinal de treino pra Jana (slash commands + 3 tabelas + parser)

## Contexto

Wagner pediu 2026-05-12 padrão "Reply / Private note" do Chatwoot na inbox `/atendimento/inbox`. Caso de uso: atendente coordena com outros atendentes via notas privadas (não vai pro WhatsApp do cliente).

Insight adicional do Wagner: usar essas notas como **sinal estruturado pra Jana**:

> "Preciso de observação de mensagens internas igual ao Chatwoot — isso eu quero usar para ter memória, isso é outro para correção de mensagens erradas do bot, e configurações ou lembretes do cliente."

Hoje a Jana tem memória persistente via `copiloto_memoria_facts` ([ADR 0052](0052-contextonegocio-expor-multiplos-angulos.md)) mas:

- Fatos são populados via `MemoriaService::lembrar()` server-side ou via tool MCP — **sem caminho humano-no-loop pelo painel de atendimento**.
- Não há registro de "este bot respondeu errado, expected era X" — perdemos sinal de RLHF/fine-tune.
- Não há lembrete agendado pra atendente acompanhar conversa ("avisar quando boleto vencer").
- Não há override per-contato do `bot_enabled` global (cliente reclama bot é chato → atendente quer desligar só pra ele).

Concorrentes que servem de referência:
- **Chatwoot** — Reply/Note toggle + `@mention`, sem semântica IA
- **Octadesk** — Notas + tags + lembretes, sem integração com IA própria
- **Take Blip** — Custom commands em template, fechado (não-extensível)

Diferenciação oimpresso: **notas viram dados estruturados que treinam Jana**. Não vi competidor BR fazer isso.

## Decisão

**1. MVP "Notas internas" puro** — entrega imediata, sem semântica Jana:

- Coluna nova `is_internal_note` (boolean) em `whatsapp_messages` (legacy) E `messages` (omnichannel novo schema ADR 0135) — defense-in-depth multi-schema durante migração
- UI toggle Reply/Note estilo Chatwoot (fundo amarelo + ícone cadeado pra Note)
- `@mention` outros atendentes via dropdown → Centrifugo notification
- **Tier 0 IRREVOGÁVEL** — dispatch driver gateado por `is_internal_note=false` em **2 lugares**:
  - `InboxController::send()` antes de enfileirar Job
  - `SendMessageJob::handle()` antes de chamar driver
  - Defense-in-depth contra bug futuro vazar nota pro WhatsApp

Esta camada vai na **US-WA-071**.

**2. Parser slash commands em notas internas** — invocado SÓ quando `is_internal_note=true`:

```
Service: Modules\Whatsapp\Services\Notes\SlashCommandParser
Entrypoint: InboxController::storeMessage() após dispatch gate
Behavior: detecta /comando, valida sintaxe, dispatcha SlashCommandHandler{Lembrar,Corrigir,Lembrete,Config}
Tratamento de erro: comando inválido vira nota normal + flash warning UI ("comando /xxx não reconhecido")
```

Sintaxe formal:

```
/lembrar <texto>                              → grava fato sobre contato (US-WA-074)
/corrigir <expected_response>                 → marca msg referenciada como errada (US-WA-075)
                                                replied_to_message_id set automaticamente pela UI
/lembrete <data_humana_ou_iso> <body>         → cria lembrete agendado (US-WA-076)
                                                data: "amanhã", "daqui 3 dias", "próxima segunda", "2026-05-20"
                                                pode anexar a outro user: /lembrete @maria 2026-05-20 ...
/config <key>=<value>                         → toggle config per-contato (US-WA-077)
                                                v1: só bot={on|off|true|false}
```

Regex base (PHP):

```php
private const COMMAND_PATTERN = '/^\/(lembrar|corrigir|lembrete|config)\s+(.+?)$/sm';
```

Mais comandos viram **extensão** do parser (não exigem ADR nova). Princípio: parser é o ponto de extensão; cada handler é classe isolada.

**3. Três tabelas novas + reuso de `copiloto_memoria_facts`:**

### 3a. `whatsapp_jana_correcoes` — training signal

```sql
CREATE TABLE whatsapp_jana_correcoes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id INT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    message_id_errada BIGINT UNSIGNED NOT NULL,           -- FK pra messages (msg do bot)
    correcao_texto TEXT NOT NULL,                          -- "Deveria ter dito X"
    contact_id INT UNSIGNED NULL,                          -- FK opcional contacts
    atendente_user_id INT UNSIGNED NOT NULL,               -- quem corrigiu
    training_status VARCHAR(20) NOT NULL DEFAULT 'pending_review',
        -- pending_review | exported_for_fine_tune | rejected | applied
    metadata JSON NULL,                                    -- tokens, modelo usado, etc
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,

    INDEX wjc_biz_status_idx (business_id, training_status),
    INDEX wjc_msg_idx (message_id_errada),
    FOREIGN KEY (message_id_errada) REFERENCES messages(id) ON DELETE CASCADE
);
```

Global scope `business_id` obrigatório via trait `HasBusinessScope`.

### 3b. `whatsapp_reminders` — lembretes agendados

```sql
CREATE TABLE whatsapp_reminders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id INT UNSIGNED NOT NULL,
    conversation_id BIGINT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NULL,
    atendente_user_id INT UNSIGNED NOT NULL,               -- destinatário (quem será notificado)
    created_by_user_id INT UNSIGNED NOT NULL,              -- quem criou (pode = atendente_user_id)
    due_at TIMESTAMP NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
        -- pending | done | cancelled | snoozed
    notified_at TIMESTAMP NULL,                            -- preenchido quando cron disparar
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,

    INDEX wr_due_pending_idx (status, due_at),             -- cron query principal
    INDEX wr_user_status_idx (atendente_user_id, status)   -- listagem por user
);
```

### 3c. `whatsapp_contact_bot_overrides` — toggle bot per-contato

```sql
CREATE TABLE whatsapp_contact_bot_overrides (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id INT UNSIGNED NOT NULL,
    contact_id INT UNSIGNED NOT NULL,                       -- FK contacts UltimatePOS
    bot_enabled BOOLEAN NOT NULL,                            -- override do business config
    set_by_user_id INT UNSIGNED NOT NULL,
    reason TEXT NULL,                                        -- "cliente reclamou que bot é chato"
    set_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,

    UNIQUE KEY wcbo_biz_contact_unq (business_id, contact_id),
    INDEX wcbo_set_by_idx (set_by_user_id, set_at)
);
```

Engine de bot consulta override **antes** do `bot_enabled` global:

```php
$override = WhatsappContactBotOverride::where('contact_id', $contactId)->first();
$enabled = $override?->bot_enabled ?? $businessConfig->bot_enabled;
```

### 3d. Integração `copiloto_memoria_facts` — sem migration, via `metadata` JSON

`/lembrar` grava em `copiloto_memoria_facts` existente:

```php
CopilotoMemoriaFact::create([
    'business_id' => $businessId,
    'user_id' => $contactUserId ?? null,           // se contact for User UltimatePOS
    'fato' => $textoLembrado,
    'metadata' => [
        'source' => 'human_note',
        'source_user_id' => $atendenteUserId,
        'source_conversation_id' => $conversationId,
        'source_message_id' => $noteMessageId,
        'contact_id' => $contactId,
        'confidence' => 1.0,
        'category' => 'preference',                // preference | history | constraint
    ],
    'valid_from' => now(),
    'valid_until' => null,                          // ativo até esquecer()
]);
```

Embedding gerado via pipeline existente (`Modules\Jana\Services\Memoria\EmbeddingPipeline`) — Ollama no CT 100. **Sem mudança no recall** — `MeilisearchDriver` já busca por `business_id` + match texto, ignora metadata.

**4. Training signal pipeline (`/corrigir`) — fase 2 separada:**

MVP US-WA-075: registra correção, dashboard `/copiloto/admin/correcoes-jana` mostra lista + exporta JSONL manualmente.

Fase 2 (não escopo desta ADR): cron semanal exporta `training_status=pending_review` last 7d, OpenAI fine-tuning API ou injection few-shot no system prompt. Atende `review_trigger` "Volume `/corrigir` > 50/mês".

**5. Reminder cron — Job hourly:**

```php
// app/Console/Kernel.php
$schedule->job(new ProcessRemindersJob)
    ->hourly()
    ->withoutOverlapping(30);

// ProcessRemindersJob::handle()
WhatsappReminder::query()
    ->where('status', 'pending')
    ->where('due_at', '<=', now())
    ->whereNull('notified_at')
    ->lazy(50)
    ->each(function (WhatsappReminder $r) {
        // 1) Centrifugo publish no channel user:{atendente_user_id}
        // 2) Optional email (config whatsapp.reminders.email_enabled)
        // 3) Atualiza notified_at
    });
```

UI: notificação popup com 3 ações: `Concluir`, `Adiar 1h`, `Adiar 1 dia`.

## Schemas — fluxo end-to-end

```
1. Atendente digita "/lembrar prefere boleto" + check "Nota interna"
2. POST /atendimento/inbox/{id}/send {body, is_internal_note: true}
3. InboxController::send():
   3a. Gate: is_internal_note=true → NÃO dispatcha driver
   3b. Persiste message (is_internal_note=true)
   3c. Invoca SlashCommandParser
   3d. Parser detecta /lembrar → SlashCommandHandlerLembrar
   3e. Handler cria row em copiloto_memoria_facts com metadata source=human_note
   3f. Embedding pipeline async (Job)
4. Centrifugo publish "message_created" no canal conversation:{id} pros atendentes do canal
5. UI mostra nota amarela + badge "✓ memorizado" link pra /copiloto/admin/memoria?fact_id={id}
```

## Permissões

- `whatsapp.send` continua exigido pra enviar mensagem (Reply OU Note) — mesma gate
- **NÃO** adicionar permission nova `whatsapp.notes.private` — qualquer atendente com `whatsapp.send` pode criar nota
- Slash commands NÃO exigem permission extra — assume confiança no atendente que pode falar com cliente
- Audit log obrigatório em toda criação de nota + execução de slash

## Multi-tenant Tier 0

- Todas as 3 tabelas novas: `business_id` com global scope obrigatório ([ADR 0093](0093-multi-tenant-isolation-tier-0.md))
- `copiloto_memoria_facts` já tem global scope — `/lembrar` não muda nada lá
- Pest cross-tenant biz=99 obrigatório em cada US (071/074-077)

## Consequências

### Positivas

- Atendentes ganham canal interno de coordenação sem mudar de ferramenta — diminui troca de contexto, aumenta resolução por conversa
- Jana fica **auto-melhorável**: cada `/corrigir` é dado de treino; cada `/lembrar` enriquece recall do contato
- Lembretes substituem post-its físicos / Trello externo — fluxo fica dentro do oimpresso
- Override per-contato (`/config bot=off`) resolve o caso recorrente "este cliente reclamou do bot"
- Diferenciação real vs concorrentes BR (Octadesk/Movidesk/Blip não têm IA própria treinável pelo atendente)

### Negativas / Riscos

- Mais código: 3 tabelas + parser + 4 handlers + UI tab + cron Job + dashboard correções
- Risco UX: atendente confunde Reply x Note e vaza informação interna pro cliente → mitigado por gate duplo + cor amarela contrastante + ícone cadeado + atalho de teclado intencional
- Risco IA: `/lembrar` cria fatos contraditórios ao longo do tempo (cliente mudou de ideia) → mitigado por `valid_from`/`valid_until` semantics já existentes em `copiloto_memoria_facts`
- Custo Whisper/OpenAI quando combinar com US-WA-072 (mídia áudio) — alerta no Daily Brief
- Manutenção parser slash — cada comando novo é tentação de "mais um"; gate é review do dono módulo Whatsapp (W)

### Dívida técnica criada

- `copiloto_memoria_facts.metadata` JSON pode virar bottleneck pra queries por `source='human_note'` em alta escala. Trigger: >1M rows OR query `WHERE metadata->>'$.source' = 'human_note'` >100ms p95. Solução: gera coluna materializada `source_kind` via trigger MySQL.
- Schema legacy `whatsapp_messages` + novo `messages` (omnichannel) — `is_internal_note` precisa ser adicionado nos 2 durante a fase de coexistência (ADR 0135 §"Coexistência drift"). Refactor pra schema único ainda não tem dono.

## Alternativas consideradas

### Alternativa A — Sem slash commands (só Note interna)

Implementar só `is_internal_note` na message, sem semântica adicional. Atendentes coordenam-se via texto livre.

**Rejeitada:** perde 100% do valor "memória treinável pela Jana" — diferenciador chave vs concorrentes.

### Alternativa B — Botões UI em vez de slash commands

Cada comando vira botão dedicado (Botão "Lembrar fato", "Corrigir bot", "Criar lembrete").

**Rejeitada parcialmente:** botões adicionam click-cost e poluem UI. Mas vai ser implementado como **complemento** ao slash — botão "Corrigir" na msg do bot pré-preenche `/corrigir ` no input (best of both worlds). Slash continua sendo o canal de extensão.

### Alternativa C — Notas em tabela separada `whatsapp_notes` (não em messages)

Argumento: notas não são mensagens, não deveriam compartilhar tabela.

**Rejeitada:** Chatwoot tem 8 anos provando que mesma timeline (messages com flag) é UX superior. Tabela separada quebra ordenação cronológica natural + complica query inbox.

### Alternativa D — Fatos `/lembrar` em tabela própria `whatsapp_contact_facts`

Em vez de reusar `copiloto_memoria_facts`.

**Rejeitada:** duplica pipeline embedding + recall + LGPD esquecimento que já existe na Jana. Custo de manter dois sistemas paralelos > benefício de schema dedicado. `metadata` JSON resolve sem mudança schema.

### Alternativa E — `/config` aceita N keys (bot, prioridade, idioma, etc)

Generalizar `/config` pra qualquer chave de configuração per-contato.

**Rejeitada por enquanto:** v1 só `bot={on|off}`. Generalizar prematuramente cria superfície de bug + UX confusa (atendentes não sabem o que existe). Extensão vem com sinal de cliente pedindo (ADR 0105).

## Métricas de saúde (alimentam `jana:health-check`)

- `internal_note_dispatch_to_driver_violation_24h` — count de mensagens enviadas pro driver onde `is_internal_note=true`. **DEVE ser 0**. Alerta P0 se >0.
- `slash_command_parse_error_rate_24h` — % de notas com `/` que parser não reconheceu. Alerta se >20% (sinal que faltou documentação ou comando virou comum).
- `reminders_cron_lag_minutes` — diff entre `due_at` e `notified_at` médio. Alerta se >120min (cron travado).
- `jana_correcoes_pending_review_30d` — count de `whatsapp_jana_correcoes.status=pending_review` last 30d. Trigger pra abrir fase 2 quando >50.

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-12 | Wagner + Opus 4.7 | ADR criada (US-WA-073). Status: **proposto**. Bloqueia US-WA-074..077. |
| 2026-05-12 | Wagner | **Status: aceito**. Wagner aprovou em chat ("notas internas aceito"). US-WA-071 unblocked + US-WA-074..077 unblocked. |

## Referências

- [ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md) Stack IA — laravel/ai + LaravelAiSdkDriver + Agents próprios
- [ADR 0048](0048-framework-agentes-laravel-ai-vizra-rejeitada.md) Vizra rejeitada, Agents próprios
- [ADR 0052](0052-contextonegocio-expor-multiplos-angulos.md) Memória 3 ângulos faturamento
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0135](0135-omnichannel-inbox-arquitetura.md) Omnichannel Inbox schema polimórfico (mãe deste ADR)
- US-WA-071..077 em `memory/requisitos/Whatsapp/SPEC.md`

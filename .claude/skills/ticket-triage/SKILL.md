---
name: ticket-triage
mission: "Enriquecer cada ticket/conversa de atendimento com contexto financeiro+chat+giro do cliente, calcular score ponderado e devolver prioridade P1-P4 + sugestão de ação. Substitui triagem humana repetitiva por output JSON estruturado pronto pra fila."
description: ATIVAR quando user pedir "analise esse ticket", "triage", "vale a pena atender X?", "qual a prioridade", "esse cliente é importante?", "score do ticket", "/triage", "/ticket-triage", "enriquecer ticket", OU quando uma conversa do `/atendimento/inbox` for aberta pela 1ª vez (futuro hook). Roda 3 fontes em paralelo (financeiro UltimatePOS + WhatsApp histórico + giro operacional) + 5 regras determinísticas + LLM pra sentimento/categoria. Devolve JSON contrato fixo com score 0-100, prioridade P1-P4, risco_churn, vale_a_pena, sugestao_acao, sla_sugerido_horas, escalar_para. Trust L1 — APENAS análise, NÃO escreve em DB (operador decide aplicar).
type: process-skill
status: active
version: 0.1.0
trust_level: L1
owner: wagner
created_at: 2026-05-11
updated_at: 2026-05-11
parent_mission: "Toda skill substitui trabalho humano repetitivo com ROI provado, rumo ao ERP autônomo de R$ [redacted Tier 0]M em 24 meses."
charter_adr: 0094

triggers_on:
  - "analise esse ticket"
  - "analise essa conversa"
  - "analise conversa {id}"
  - "triage {id}"
  - "/triage"
  - "/ticket-triage"
  - "qual a prioridade do {id}"
  - "vale a pena atender {cliente}"
  - "esse cliente é importante"
  - "score do ticket {id}"
  - "enriquecer ticket {id}"
  - "classificar ticket {id}"
  - "{id} é P1?"
  - "{id} pode esperar?"

does_not_trigger_on:
  - "criar ticket" (criação ≠ análise)
  - "marcar resolvido" (action ≠ análise)
  - "enviar mensagem" (composer ≠ triagem)
  - perguntas conversacionais sem ID/cliente alvo
tier: B
---

# ticket-triage — Skill de triagem de atendimento

## Quando usar

Sempre que o operador (Wagner, Maiara, Felipe, Luiz, Eliana) pedir análise de
um ticket/conversa/OS ANTES de decidir prioridade de atendimento. Casos típicos:

- Fila do `/atendimento/inbox` cheia → "qual atender primeiro?"
- Conversa nova entra → "vale a pena ou cliente inadimplente?"
- Cliente reclama → "esse cliente é importante o suficiente pra reagir?"
- Triagem em lote (cron) → "analise as 30 conversas abertas e me dê fila ordenada"

## Quando NÃO usar

- Para CRIAR ticket/conversa (responsabilidade do composer ou webhook)
- Para EXECUTAR ação (atribuir, resolver, bloquear) — outro fluxo decide
- Conversa sem ID alvo claro — peça clarificação ao user

## Inputs aceitos

Skill aceita 3 formatos de identificação:

| Input do user | Resolução |
|---|---|
| `conversa:42` ou `thread:42` | `Conversation::find(42)` (schema novo) |
| `repair:1234` ou `os:1234` | `RepairOrder::find(1234)` |
| `cliente:5511...` | `Contact::where('mobile', 'LIKE', '%...')` + agrega todos open |

Skill sempre tem acesso a `business_id` da sessão atual (Tier 0 ADR 0093).

## Saída obrigatória (JSON contrato fixo)

```json
{
  "ticket_id": 42,
  "ticket_type": "conversation|repair|complaint",
  "score": 78,
  "prioridade": "P1|P2|P3|P4",
  "categoria": "fiscal|financeiro|tecnico|duvida|melhoria|reclamacao|comercial",
  "sentimento": "neutro|frustrado|raivoso|satisfeito|negociando",
  "risco_churn": "baixo|medio|alto|critico",
  "vale_a_pena": "sim|condicional|nao",
  "justificativa_valor": "string ≤ 200 chars",
  "justificativa_urgencia": "string ≤ 200 chars",
  "sugestao_acao": "string ≤ 400 chars com próximos passos concretos",
  "sla_sugerido_horas": 0.25,
  "escalar_para": "suporte_n1|n2|tecnico|comercial|diretoria|cobranca",
  "palavras_chave": ["sefaz", "rejeitada", "urgente"],
  "snapshot": {
    "ltv_centavos": 2400000,
    "mrr_centavos": 0,
    "status_financeiro": "em_dia|atrasado|inadimplente",
    "dias_atraso_max": 0,
    "plano": "free|basico|pro|enterprise|null",
    "giro_score": 72,
    "ultima_atividade_dias": 3,
    "tickets_abertos": 1,
    "tickets_30d": 4,
    "nfe_30d": 142
  },
  "fontes_consultadas": ["financeiro", "whatsapp", "giro", "memoria"],
  "version": "0.1.0",
  "enriched_at": "2026-05-11T18:43:00-03:00"
}
```

**Sempre retornar JSON parseable, mesmo em casos de dados ausentes
(`null` em campos faltantes, NUNCA omitir chave).**

## Algoritmo

### Passo 1: fetch 3 fontes em paralelo

```
sources = parallel([
  financeiro_cliente(client_id),     // LTV, MRR, status, plano, dias atraso
  whatsapp_historico(client_id, 30), // sentimento, palavras-chave, freq
  projeto_giro(client_id),           // NF-e 30d, usuarios ativos, tendência
])
memoria = memoria_cliente(client_id) // namespaces Vizra: negocio + interacoes
```

### Passo 2: regras determinísticas (BEFORE LLM)

Aplicar SEMPRE — economiza chamada LLM em casos óbvios:

1. **Inadimplente > 90 dias** → `vale_a_pena="nao"`, `P4`, `escalar_para="cobranca"`, `sugestao_acao="Regularizar pagamento antes do atendimento técnico"`
2. **Plano enterprise + texto contém ["sefaz", "nf-e rejeitada", "loja parada", "sistema fora"]** → urgência +30, `escalar_para="n2"` ou `tecnico`
3. **LTV no top 10% do business + sentimento ∈ ["raivoso","frustrado"] + palavra ∈ ["cancelar","procon","advogado","concorrente"]** → `risco_churn="critico"`, `escalar_para="diretoria"`, `P1`
4. **Mesma palavra-chave em 3+ tickets nas últimas 24h** (mesmo business) → flag incidente — todos viram `escalar_para="tecnico"` simultâneo
5. **Cliente ativo (NF-e > 100/mês) + ticket fiscal** → urgência mínima +15 (operação parada custa caro)

Se alguma regra disparou `P1` ou `vale_a_pena="nao"`, pode pular Passo 3 (LLM opcional).

### Passo 3: LLM pra sentimento/categoria/palavras

Quando dados são ambíguos OU regras não dispararam:

```
prompt = """
Cliente {nome} ({tipo}, {plano}, LTV={ltv}).
Texto do ticket: "{texto}"
Histórico WhatsApp 30d: {sumario_chat}
Última msg do cliente: "{ultima_msg_cliente}" ({sentimento_anterior})

Classifique em JSON:
- categoria (fiscal|financeiro|tecnico|duvida|melhoria|reclamacao|comercial)
- sentimento (neutro|frustrado|raivoso|satisfeito|negociando)
- palavras_chave (array, max 5, lowercase)
- resumo (máx 30 palavras)
- sugestao_acao (próximos passos concretos pra atendente, max 80 palavras)

Não invente. Se faltar dado, retorne null no campo.
"""
```

LLM via stack canônica ADR 0035: `laravel/ai` ^0.6.3 Camada A → Vizra ADK
Camada B (`LaravelAiSdkDriver`). Memória Camada C consulta namespaces
`negocio` + `interacoes` do `business_id`.

### Passo 4: score ponderado

```
valor_cliente = (
  0.35 × normalizar(ltv_centavos / max_ltv_business) +
  0.25 × normalizar(mrr_centavos / max_mrr_business) +
  0.20 × giro_score +
  0.10 × tempo_de_casa_score +
  0.10 × (plano == enterprise ? 1.0 : plano == pro ? 0.6 : 0.3)
)

urgencia = (
  0.40 × severidade_categoria +  // fiscal=1.0, tecnico=0.7, duvida=0.3
  0.25 × impacto_operacional +   // loja parada=1.0, módulo=0.5, cosmético=0.1
  0.20 × sla_remaining_score +    // quanto menos tempo, mais alto
  0.15 × sentimento_score         // raivoso=1.0, neutro=0.3
)

risco_churn = (
  0.40 × (palavra_critica_detectada ? 1.0 : 0.0) +
  0.30 × (dias_inativo / 30 capped at 1.0) +
  0.20 × (reclamacoes_30d / 5 capped at 1.0) +
  0.10 × (queda_uso_pct / 100 capped at 1.0)
)

score = 100 × (0.45 × valor_cliente + 0.40 × urgencia + 0.15 × risco_churn)
```

**Pesos configuráveis** via `ai_config.skill_weights.ticket_triage` (futuro DB) —
default acima foi calibrado pelo Wagner em 2026-05-11 com base nas dimensões
do brief inicial.

### Passo 5: derivação prioridade

```
P1 (Crítico — atender em ≤15min):   score ≥ 80 OU risco_churn=="critico"
P2 (Alto   — atender no dia):       score ∈ [60, 80) E vale_a_pena != "nao"
P3 (Médio  — SLA padrão 24h):       score ∈ [40, 60)
P4 (Baixo  — fila):                 score < 40 OU vale_a_pena=="nao"
```

### Passo 6: vale_a_pena (matriz 2x2)

```
                  ALTO VALOR              BAIXO VALOR
ALTA URGÊNCIA     "sim" (P1/P2)           "sim, mas educar" (P2)
BAIXA URGÊNCIA    "sim" (P2)              "condicional" (P3) ou "nao" (P4)
```

`condicional` = atender SE folga de fila + sugerir SLA reduzido + flag pro
gestor reavaliar relacionamento (potencial revisar contrato).

## Sources/tools que a skill consulta

| Tool | Origem | Returns |
|---|---|---|
| `financeiro_cliente(id)` | Modules/Financeiro + UltimatePOS `transactions` | `{ltv, mrr, status, plano, dias_atraso}` |
| `whatsapp_historico(id, 30)` | Modules/Whatsapp `conversations` + `messages` (schema novo ADR 0135) | `{convs_30d, sentimento, palavras_chave, ultima_msg}` |
| `projeto_giro(id)` | Modules/NfeBrasil + UltimatePOS `business_locations` | `{nfe_30d, usuarios_ativos, tendencia}` |
| `memoria_cliente(id)` | Modules/Jana namespaces `negocio` + `interacoes` (ADR 0035 §C) | `{perfil, atendimentos_anteriores, padroes}` |

Cada tool é função PHP no `Modules/Atendimento/Services/Triage/Sources/`
(módulo novo — ver §Roadmap operacional).

## Anti-patterns (NÃO faça)

- ❌ **Tomar ação** (atribuir / resolver / bloquear / enviar msg) — skill é
  só **análise**. Operador decide aplicar.
- ❌ **Persistir snapshot em DB** sem comando explícito — operador pode
  rejeitar a análise. Persistência fica pro Vizra agent operacional.
- ❌ **Vazar PII LGPD** (CPF/CNPJ) em resposta natural-language. JSON `snapshot`
  só tem dados financeiros agregados, não documentos.
- ❌ **Inventar dados** quando fonte tá vazia. Retornar `null` no campo
  específico, NUNCA fabricar. Justificativas devem citar dado real.
- ❌ **Score sem fontes consultadas** — sempre preencher `fontes_consultadas`
  com array do que efetivamente respondeu (`["financeiro","whatsapp"]` se
  giro deu timeout).
- ❌ **Aplicar regras determinísticas a partir de texto do user** sem
  validar contra DB. Ex: cliente alega "sou cliente premium" — tem que
  buscar plano real, não confiar na alegação.

## Como invocar (manual)

User digita um dos triggers no chat:

```
/triage conversa:42
analise o ticket 1234
vale a pena atender o cliente Larissa?
qual a prioridade da conversa 5?
```

Skill ativa automático, consulta sources, devolve JSON. Operador lê e decide
ação (atribuir / responder / escalar).

## Roadmap operacional (transformar skill em Vizra Agent)

Esta skill é spec executável. Pra evoluir pra **feature operacional rodando
em produção** (dashboard auto-priorizado, fila ordenada por P, escalation
automática), seguir:

### Fase 1 — Backend (~6h IA-pair)

- **Módulo novo `Modules/Atendimento/`** (atualmente Inbox vive em
  `Modules/Whatsapp/` — refactor pode acontecer ou criar `Modules/Atendimento`
  novo). ADR pendente.
- **Migration `ticket_enrichments`** (schema spec na §Schema da tabela abaixo)
- **3 enrichers PHP** em `Services/Triage/Sources/`:
  - `FinanceiroEnricher.php` — query `transactions` + `business.subscription`
  - `WhatsappEnricher.php` — query `conversations` + `messages` schema novo
  - `GiroEnricher.php` — query `nfe_emissoes` + `users.last_login_at`
- **`TicketTriageService.php`** orquestrador — chama os 3 enrichers em
  paralelo via `Illuminate\Support\Concurrency::run([...])` (Laravel 13.x).
- **`TicketTriageAgent.php`** (Vizra ADK) — namespace `agents.triage`,
  prompt fixo conforme §Passo 3.
- **`EnrichTicketJob.php`** queue `triage` (Horizon CT 100) — assync.

### Fase 2 — Frontend (~4h IA-pair)

- **Inbox `/atendimento/inbox`** ganha coluna "P" (badge cor por
  prioridade) na lista esquerda + dropdown ordenação "Prioridade ↓" como
  default
- **Sidebar direita** ganha card "Triagem IA" com snapshot + justificativa
  + botão "Re-triagar" + botão "Aplicar tag P1" (cria tag via US-WA-063)
- **Atalho `T`** no thread → roda triage on-demand

### Fase 3 — Métricas (~2h IA-pair)

- **`/copiloto/admin/triagem`** dashboard:
  - Tempo médio resolução por prioridade
  - % de P1 resolvidos no SLA
  - Falsos P1 (over-prioritization)
  - Correlação prioridade dada vs satisfação pós-atendimento (NPS)

### Schema da tabela (Fase 1)

```sql
CREATE TABLE ticket_enrichments (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  ticket_type ENUM('conversation', 'repair', 'complaint') NOT NULL,
  ticket_id BIGINT UNSIGNED NOT NULL,
  score TINYINT UNSIGNED NOT NULL,
  prioridade ENUM('P1','P2','P3','P4') NOT NULL,
  categoria VARCHAR(40) NOT NULL,
  sentimento VARCHAR(20) NOT NULL,
  risco_churn ENUM('baixo','medio','alto','critico') NOT NULL,
  vale_a_pena ENUM('sim','condicional','nao') NOT NULL,
  ltv_snapshot_centavos BIGINT UNSIGNED NULL,
  mrr_snapshot_centavos BIGINT UNSIGNED NULL,
  giro_score TINYINT UNSIGNED NULL,
  status_financeiro VARCHAR(30) NULL,
  payload_json JSON NOT NULL COMMENT 'Output completo da skill',
  agent_version VARCHAR(20) NOT NULL DEFAULT '0.1.0',
  enriched_at TIMESTAMP NOT NULL,
  enriched_by VARCHAR(40) NOT NULL DEFAULT 'skill:ticket-triage',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL,
  INDEX idx_biz_priority_at (business_id, prioridade, enriched_at),
  INDEX idx_ticket (ticket_type, ticket_id),
  -- Multi-tenant Tier 0 (ADR 0093)
  FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE
);
```

**Snapshot é histórico imutável** — você quer saber qual era o valor do
cliente NO MOMENTO do ticket, não o de hoje. Override = nova row, não UPDATE.

### Pest tests obrigatórios (Fase 1)

| ID | Cobre |
|---|---|
| R-TRIAGE-001 | Inadimplente > 90d sempre vira P4 + `vale_a_pena=nao` (regra det 1) |
| R-TRIAGE-002 | Plano enterprise + "sefaz" no texto vira P1 (regra det 2) |
| R-TRIAGE-003 | Top 10% LTV + "cancelar" + raivoso vira `risco_churn=critico` (regra det 3) |
| R-TRIAGE-004 | 3+ tickets mesma palavra 24h vira `escalar_para=tecnico` (regra det 4) |
| R-TRIAGE-005 | Tier 0: triage de biz=1 NÃO consulta dados de biz=99 |
| R-TRIAGE-006 | Source `WhatsappEnricher` retorna `null` (não fabrica) se 0 conversas |
| R-TRIAGE-007 | Score pondera 0.45/0.40/0.15 nos 3 eixos (snapshot pesos) |
| R-TRIAGE-008 | `enriched_at` é IMUTÁVEL (re-triage gera row nova) |

## Memória / aprendizado

A skill grava (via `memoria_cliente.write_async`) padrões em namespace
`triage_outcomes` por `business_id`:

- "Cliente X teve P1 em 2026-05-10, foi resolvido em 12min, NPS pós: 10"
- "Cliente Y teve P3 em 2026-05-08, virou churn em 2026-05-25 — recalibrar
  pesos: dar mais peso a `risco_churn`"

Re-calibração mensal (Fase 3 dashboard) ajusta pesos `0.45/0.40/0.15` baseado
em correlação prioridade ↔ outcome real.

## Versionamento

Skill semver. Bump versão quando:

- **patch** (0.1.x): tweaks de prompt LLM, regex de palavras-chave
- **minor** (0.x.0): nova regra determinística, novo campo no JSON output
- **major** (x.0.0): mudança breaking no contrato JSON OU mudança de pesos
  do score (operador pode ter ML treinado em cima)

## Referências

- **ADR 0035** Stack AI canônica (laravel/ai + Vizra ADK + namespaces memória)
- **ADR 0093** Multi-tenant Tier 0 (business_id global scope em todos os enrichers)
- **ADR 0094** Constituição v2 (princípio 4: loop fechado por métrica)
- **ADR 0135** Omnichannel Inbox (schema `conversations` + `messages` polimórfico)
- **US-WA-063** Tags classificadoras (já existe — pode receber `P1`/`P2`/`P3`/`P4`)
- **`Modules/Repair/`** se ticket_type=repair (já tem RepairOrder + status FSM)
- **Texto original Wagner 2026-05-11** (Claude.ai conversation que inspirou esta skill)

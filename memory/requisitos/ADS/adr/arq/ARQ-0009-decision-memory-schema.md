---
id: requisitos-ads-adr-arq-arq-0009-decision-memory-schema
slug: ARQ-0009-decision-memory-schema
title: "Decision Memory — Schema, retenção LGPD e uso pelo Learning Loop"
status: accepted
date: 2026-05-03
deciders: [Wagner]
category: arq
module: ADS
relates_to: [ARQ-0007, ARQ-0005]
---

# ARQ-0009 — Decision Memory: schema, retenção e uso

## Contexto

Sem registro persistente de decisões, o Learning Loop não tem dados para calibrar.
Sem auditoria de decisões, é impossível responder "por que o agente fez isso?".

A Decision Memory é o log append-only que alimenta tanto a auditoria quanto o aprendizado.
Ela é separada do `mcp_audit_log` (que registra acessos ao MCP) e do
`copiloto_memoria_metricas` (que registra performance do Copiloto chat).

## Decisão

### Schema canônico

```sql
CREATE TABLE mcp_dual_brain_decisions (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id           BIGINT UNSIGNED NOT NULL,          -- multi-tenant obrigatório

  -- Evento de entrada
  event_type            VARCHAR(80) NOT NULL,              -- 'service_layer_refactor'
  event_source          ENUM('brain_a','evolution_agent','wagner','scheduler') NOT NULL,
  domain                VARCHAR(50) NOT NULL,              -- 'Financeiro', 'NFSe', etc.
  files_affected        JSON,                              -- lista de paths
  event_metadata        JSON,                              -- contexto livre

  -- Roteamento
  risk_score            DECIMAL(4,3) NOT NULL,
  confidence_score      DECIMAL(4,3) NOT NULL,
  policy_applied        VARCHAR(50),                       -- 'ALLOW_BRAIN_A', 'BLOCK_ALWAYS', etc.
  destination           ENUM('brain_a','brain_b','pending_wagner','blocked','queued') NOT NULL,
  hitl_level            TINYINT NOT NULL DEFAULT 2,        -- 0,1,2,3

  -- Execução
  brain_used            ENUM('brain_a','brain_b','human','none') NOT NULL,
  model_used            VARCHAR(50),                       -- 'qwen2.5-coder:14b', 'claude-sonnet-4-6'
  instruction_generated TEXT,                              -- instrução para Claude Code
  tokens_used           INT,
  cost_usd              DECIMAL(8,6),
  execution_ms          INT,

  -- Outcome
  outcome               ENUM('success','fail','wagner_modified','wagner_rejected','cancelled','expired') NOT NULL DEFAULT 'cancelled',
  wagner_modified_to    TEXT,                              -- o que Wagner mudou (se modificou)
  diff_size_pct         TINYINT,                          -- % de mudança no output (0-100)
  pr_url                VARCHAR(255),
  commit_sha            VARCHAR(40),

  -- Auditoria
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at           TIMESTAMP NULL,
  resolved_by           VARCHAR(50),                       -- 'brain_a', 'brain_b', 'wagner'

  INDEX idx_domain_type (domain, event_type),
  INDEX idx_outcome (outcome),
  INDEX idx_business_created (business_id, created_at)
) ENGINE=InnoDB;
```

### Tabela de padrões aprendidos

```sql
CREATE TABLE mcp_decision_patterns (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id           BIGINT UNSIGNED NOT NULL,
  domain                VARCHAR(50) NOT NULL,
  event_type            VARCHAR(80) NOT NULL,

  pattern_hash          VARCHAR(64) NOT NULL UNIQUE,       -- SHA256 do padrão
  description           TEXT NOT NULL,                    -- descrição legível
  example_decision_ids  JSON,                             -- IDs em mcp_dual_brain_decisions

  success_count         INT NOT NULL DEFAULT 0,
  total_count           INT NOT NULL DEFAULT 0,
  success_rate          DECIMAL(4,3) GENERATED ALWAYS AS (
    CASE WHEN total_count > 0 THEN success_count / total_count ELSE 0 END
  ) STORED,

  is_hardcoded          BOOLEAN NOT NULL DEFAULT FALSE,   -- promovido para PolicyEngine.php
  approved_by_wagner    BOOLEAN NOT NULL DEFAULT FALSE,
  approved_at           TIMESTAMP NULL,

  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

### Tabela de thresholds do Decision Router

```sql
CREATE TABLE mcp_decision_thresholds (
  id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  domain                VARCHAR(50) NOT NULL DEFAULT '*',  -- '*' = global
  event_type            VARCHAR(80) NOT NULL DEFAULT '*',

  brain_a_risk_max      DECIMAL(4,3) NOT NULL DEFAULT 0.300,
  brain_a_conf_min      DECIMAL(4,3) NOT NULL DEFAULT 0.700,
  brain_b_risk_max      DECIMAL(4,3) NOT NULL DEFAULT 0.700,

  approved_by           VARCHAR(50) NOT NULL DEFAULT 'system',
  approved_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reason                TEXT,

  UNIQUE KEY uk_domain_type (domain, event_type)
) ENGINE=InnoDB;
```

### Retenção e LGPD

| Campo | Contém PII? | Retenção | Justificativa |
|---|---|---|---|
| `instruction_generated` | Pode (code com email, CPF em variável) | 365 dias | LGPD Art. 15 — auditoria de sistemas automatizados |
| `wagner_modified_to` | Pode | 365 dias | Idem |
| `event_metadata` | Pode (contexto livre) | 365 dias | Idem |
| `files_affected` | Não | Indefinido | Não tem PII, dado técnico |
| Scores e outcomes | Não | Indefinido | Dado técnico de calibração |

**Após 365 dias:** `instruction_generated` e `wagner_modified_to` são substituídos por
`[REDACTED-LGPD-365d]`. Os demais campos permanecem para análise de longo prazo do Learning Loop.

Implementação: command `ads:purge-pii-decisions` rodando mensalmente via scheduler.

### Acesso pelo Learning Loop

```php
// L2 (semanal) — Decision Learning
DecisionMemory::lastWeek()
  ->groupBy(['domain', 'event_type'])
  ->withOutcomeStats()   // taxa_sucesso, taxa_modificacao, custo_total
  ->get();

// L3 (mensal) — Meta Learning
DecisionMemory::lastMonth()
  ->whereOutcome('wagner_modified')
  ->withDiff()          // o que Wagner mudou
  ->orderByDiffSize()   // maiores diffs primeiro
  ->get();
```

## Consequências

**Positivas:**
- Toda decisão rastreável: dado um commit ou PR, é possível encontrar a decisão que o originou
- LGPD cumprido: purge automático de PII após 365 dias com campo `[REDACTED]` como evidência
- Schema rico permite análises que o Learning Loop ainda não faz hoje mas poderá fazer no futuro

**Negativas:**
- Tabela pode crescer rapidamente em projetos ativos (estimativa: ~100 registros/semana).
  Mitigação: índices otimizados + partition por `created_at` se necessário após 6 meses

---
id: requisitos-ads-adr-arq-arq-0004-risk-engine-formula-e-priors
slug: ARQ-0004-risk-engine-formula-e-priors
title: "Risk Engine — Fórmula, fatores, prior hardcoded e calibração"
status: accepted
date: 2026-05-03
deciders: [Wagner]
category: arq
module: ADS
relates_to: [ARQ-0003, ARQ-0005]
---

# ARQ-0004 — Risk Engine: fórmula, fatores e priors

## Contexto

Sem quantificação de risco, o Decision Router não tem base para decidir qual agente age.
Um threshold binário ("é crítico ou não") é insuficiente — a maioria dos eventos é cinza.

A fórmula precisa ser:
- Determinística (mesmo input → mesmo output, testável)
- Calibrável por histórico sem perder o prior de segurança
- Transparente: Wagner precisa entender por que um score foi 0.65 e não 0.45

## Decisão

### Fórmula canônica

```
risco = impacto × incerteza × (1 − reversibilidade) × criticidade_sistema
```

| Fator | O que mede | Range | Exemplo alto | Exemplo baixo |
|---|---|---|---|---|
| `impacto` | Amplitude dos usuários/processos afetados | 0–1 | Tabela `sells` (todos clientes) = 0.9 | Lang file PT-BR = 0.1 |
| `incerteza` | Brain A já fez isso antes com sucesso? | 0–1 | Nunca fez = 1.0 | Fez 20× com sucesso = 0.1 |
| `reversibilidade` | Dá pra desfazer sem perda? | 0–1 | Git revert instantâneo = 0.9 | Delete sem backup = 0.0 |
| `criticidade_sistema` | Peso do módulo/componente | 0–1 | Auth middleware = 1.0 | README = 0.05 |

**Nota:** `(1 − reversibilidade)` porque maior reversibilidade = menor risco.

### Prior hardcoded (tabela inicial)

Antes de ter histórico, os valores de `incerteza` e `impacto` vêm desta tabela.
Ela é atualizada apenas via PR + aprovação Wagner.

```php
// RiskPriors.php — NUNCA modificado por LLM
const PRIORS = [
    // [impacto, incerteza_inicial, reversibilidade, criticidade]
    'db_schema_change'        => [0.9, 0.8, 0.1, 0.9],  // risco = ~0.58
    'env_production'          => [1.0, 1.0, 0.0, 1.0],  // risco = 1.0 → BLOCK
    'auth_middleware'         => [0.9, 0.7, 0.2, 1.0],  // risco = ~0.50
    'billing_logic'           => [0.8, 0.6, 0.3, 0.9],  // risco = ~0.30
    'lang_file_pt_br'         => [0.2, 0.2, 0.9, 0.1],  // risco = ~0.00
    'adr_frontmatter_fix'     => [0.1, 0.3, 1.0, 0.1],  // risco = 0.0
    'test_only_change'        => [0.2, 0.3, 0.9, 0.1],  // risco = ~0.01
    'blade_view_ui_only'      => [0.3, 0.4, 0.8, 0.2],  // risco = ~0.02
    'composer_json_change'    => [0.7, 0.7, 0.3, 0.8],  // risco = ~0.27
    'service_layer_refactor'  => [0.5, 0.5, 0.6, 0.5],  // risco = ~0.08
    'migration_new_column'    => [0.6, 0.6, 0.4, 0.7],  // risco = ~0.10
    'pii_exposure'            => [1.0, 0.9, 0.0, 1.0],  // risco = 0.90 → BLOCK
    'append_only_table'       => [1.0, 1.0, 0.0, 1.0],  // risco = 1.0 → BLOCK
];
```

### Calibração por histórico

Após 10 execuções de um tipo específico, a `incerteza` começa a ser ajustada:

```
incerteza_calibrada = prior × (1 - taxa_sucesso_historico × 0.5)
```

Ou seja: 10 execuções com 100% de sucesso reduz incerteza em 50% em relação ao prior.
O fator 0.5 garante que incerteza nunca cai a zero — sempre há margem de segurança.

Calibração aprovada automaticamente se redução < 20pp do prior. Acima disso, task pendente Wagner.

### Score final e zona cinza

```
risco < 0.2   → zona verde  (Brain A pode executar)
0.2–0.4       → zona amarela (Brain A executa com HiTL-1 notificação)
0.4–0.7       → zona laranja (Brain B decide + HiTL-2 revisão assistida)
risco > 0.7   → zona vermelha (HiTL-3 ou BLOCK por Policy)
```

## Consequências

**Positivas:**
- Fórmula auditável: dado um evento, Wagner pode recalcular o score manualmente
- Prior conservador protege nos primeiros 30 dias sem histórico
- Calibração gradual reduz falsos positivos sem risco de expansão súbita

**Negativas:**
- Fatores como `impacto` e `criticidade_sistema` são subjetivos no prior. Mitigação: prior
  revisto por Wagner após primeiro mês com dados reais
- Eventos novos (sem prior) recebem `incerteza = 0.8` por padrão (conservador)

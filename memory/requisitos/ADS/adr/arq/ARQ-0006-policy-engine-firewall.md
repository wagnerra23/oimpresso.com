---
id: requisitos-ads-adr-arq-arq-0006-policy-engine-firewall
slug: ARQ-0006-policy-engine-firewall
title: "Policy Engine — Firewall imutável, categorias de regra e processo de mudança"
status: accepted
date: 2026-05-03
deciders: [Wagner]
category: arq
module: ADS
relates_to: [ARQ-0003, ARQ-0007]
---

# ARQ-0006 — Policy Engine: firewall de decisões

## Contexto

O Learning Loop calibra thresholds. O Confidence Engine cresce com o tempo. Ambos podem,
teoricamente, levar o sistema a agir com mais autonomia em domínios que não deveriam ser
delegados a agentes — seja por lei (LGPD, CLT, Portaria 671/2021), por contrato (Delphi WR2
imutável, ADR `feedback_delphi_contrato_imutavel`) ou por risco de negócio irreversível.

O Policy Engine existe para que essas regras nunca sejam ultrapassadas, independente de qualquer
calibração de confiança ou score de risco calculado.

## Decisão

### Princípio fundamental

> Uma regra de Policy Engine é código hardcoded no git. Nenhuma LLM pode ler, sugerir ou
> modificar regras de policy. Regras só mudam via PR aprovado por Wagner, com justificativa
> formal (ADR ou comentário no PR).

### Categorias de regra

**BLOCK_ALWAYS — nunca executa automaticamente, nem com confiança 1.0**

```php
const BLOCK_ALWAYS = [
    'env_production',          // .env Hostinger ou CT 100
    'append_only_table',       // ponto_marcacoes, movimentos financeiros imutáveis
    'auth_middleware',         // qualquer arquivo em middleware de autenticação
    'pii_direct_exposure',     // output que contém CPF/CNPJ/dados pessoais raw
    'delphi_contract',         // contrato request/response Delphi WR2 — congelado
    'composer_production',     // composer install/update sem --lock em prod
    'db_trigger_removal',      // remoção de trigger de imutabilidade
    'billing_financial_flow',  // fluxo de cobrança RecurringBilling
];
```

**REQUIRE_BRAIN_B — Brain A não toca, obrigatório Brain B + instrução detalhada**

```php
const REQUIRE_BRAIN_B = [
    'lgpd_data_handling',      // qualquer lógica que toca dados pessoais
    'db_schema_change',        // migration nova ou alteração de coluna
    'composer_json_change',    // mudança em dependências
    'nfse_fiscal_logic',       // lógica fiscal NFSe (erro = multa)
    'security_rule_change',    // ACL, permissões Spatie, middleware
    'multi_tenant_scope',      // remoção de business_id scope em query
];
```

**REQUIRE_HUMAN_REVIEW — sempre cria task pendente Wagner, mesmo com Brain B aprovando**

```php
const REQUIRE_HUMAN_REVIEW = [
    'new_module_creation',     // criar Modules/<Novo>/
    'new_adr_proposal',        // ADR gerada por agente (Wagner valida antes de commitar)
    'threshold_change',        // Learning Loop propondo novo threshold
    'pattern_hardcode',        // padrão aprendido virando regra hardcoded
    'production_deploy',       // qualquer ação que resulte em deploy em Hostinger
];
```

**ALLOW_BRAIN_A — pode executar sem revisão se confiança > 0.7**

```php
const ALLOW_BRAIN_A = [
    'lang_file_pt_br',
    'adr_frontmatter_fix',
    'md_link_fix',
    'comment_typo',
    'test_description_fix',
    'mcp_sync_memory',         // reindexar documentos no MCP
    'session_log_creation',    // criar memory/sessions/*.md
];
```

**ESCALATE_IF_CONCURRENT — bloqueia se outro agente já está tocando os mesmos arquivos**

```php
const ESCALATE_IF_CONCURRENT = [
    '*',  // regra global: qualquer arquivo com lock ativo → fila, não paralelo
];
```

### Processo de mudança de uma regra

1. Identificar a regra e categoria atual
2. Abrir PR com mudança no arquivo `PolicyEngine.php`
3. Incluir justificativa: por que a regra pode ser relaxada/endurecida?
4. Wagner aprova o PR
5. Após merge, Learning Loop registra a mudança como evento especial em `mcp_decision_patterns`

**Nunca:**
- Mudar policy via config `.env` ou banco de dados (seria contornável por agente)
- Aceitar sugestão de LLM para relaxar uma regra BLOCK_ALWAYS
- Criar "exceção temporária" — se precisa de exceção, a regra está errada e deve ser reformulada

### Conflito Policy vs Confidence

Se Confidence Engine diz confiança = 0.95 para um par `(domínio, tipo)` que está em
`BLOCK_ALWAYS`, Policy vence sempre. Não há override.

Se Confidence Engine diz confiança = 0.95 para um par que está em `REQUIRE_BRAIN_B`,
Brain B ainda é obrigatório, mas o HiTL pode ser rebaixado de HiTL-2 para HiTL-1
(notificação em vez de revisão ativa).

## Consequências

**Positivas:**
- Compliance com CLT, LGPD, Portaria 671/2021 e contrato Delphi garantidos por código
- Wagner não precisa revisar eventos que Policy já bloqueou — eles nunca chegam à fila
- Auditoria simples: `grep BLOCK_ALWAYS PolicyEngine.php` mostra tudo que nunca é delegado

**Negativas:**
- Lista BLOCK_ALWAYS conservadora pode gerar fricção em tarefas que deveriam ser rotineiras.
  Mitigação: revisão trimestral da lista com Wagner, promovendo para REQUIRE_BRAIN_B se o
  histórico de 90 dias mostrar zero incidentes naquele tipo

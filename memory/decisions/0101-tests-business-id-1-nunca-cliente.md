---
slug: 0101-tests-business-id-1-nunca-cliente
number: 101
title: "Tests SEMPRE business_id=1 (Wagner) — nunca cliente real, com guard CI"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-07"
module: governance
quarter: 2026-Q2
tags: [multi-tenant, tests, governance, security, lgpd, biz-isolation]
supersedes: []
supersedes_partially: []
superseded_by: []
related: ["0070-jira-style-task-management-current-md-removed", "0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios"]
pii: false
review_triggers: ["Se aparecer 3+ falsos-positivos do BusinessIdGuardTest em PRs legítimos, revisar regex patterns", "Se chegar dev novo sem onboarding, validar que skill `multi-tenant-patterns` cobre regra"]
---

# ADR 0101 — Tests SEMPRE business_id=1, NUNCA cliente real

## Contexto

Em 2026-05-07 noite-3, Wagner sinalizou erro grave durante a sessão de fechamento US-NFE-002:

> "emitir na minha empresa 1 sempre, isso é um erro padrão grave prioridade não pode no cliente. arrume todas divergências."

Reforçado depois:

> "biz 1 não esquecer / não testar empresa 4"

**Causa raiz:** ao escrever testes Pest pra US-NFE-002 (PRs #200, #201, #203 desta sessão), Claude adotou `business_id=4` como default em fixtures porque viu o pattern em código antigo. Esse antigo veio do início do projeto quando RotaLivre (`business_id=4`, Larissa) era o único cliente em produção e parecia sinônimo de "business real" pra teste.

**Estado encontrado durante audit:**

- 47 arquivos de teste com hardcode `business_id=4`
- Alguns helpers de fixture com `int $businessId = 4` como default
- Cenários cross-tenant usando `business_id=4` vs `business_id=5/7` (cliente vs cliente)
- 2 comentários em código de produção referenciando "Larissa (ROTA LIVRE)" como cliente típico

**Risco real desse padrão:**

1. **Smoke real acidental contra cliente:** runbook `runbook_smoke_sefaz_biz1.md` foi escrito orientando smoke contra biz=1 (Wagner). Mas se um dev/IA tentasse "smoke rápido" sem ler e copiasse pattern do test (biz=4), poderia disparar emissão fiscal real (mesmo em homologação) usando o cert da Larissa
2. **Vazamento PII em logs/CI:** tests rodando contra DB compartilhado de dev poderiam tocar dados reais da RotaLivre
3. **Confusão lógica em revisão de PRs:** ao ler test que diz `business_id=4 vs business_id=5`, leitor sem contexto não sabe se isso é cobaia técnica ou validação real
4. **Mistura cobaia ↔ produção comercial:** viola separação Wagner (operador) vs Cliente (RotaLivre). Cliente é entidade comercial cujos dados não devem ser cobaia técnica.

[ADR 0093](0093-multi-tenant-isolation-tier-0.md) já estabelece multi-tenant Tier 0 IRREVOGÁVEL com `business_id` global scope obrigatório. Este ADR adiciona a regra de **qual** business_id usar em testes.

## Decisão

**Em qualquer test, fixture, smoke, exemplo de código ou snippet do projeto oimpresso, `business_id` default deve ser `1` (empresa Wagner — WR2 Sistemas, Tubarão/SC).**

Cláusulas:

1. **Default fixture/helper:** `int $businessId = 1`
2. **Default em arrays mass-insert:** `'business_id' => 1`
3. **Cross-tenant adversário:** `business_id = 99` (número alto improvável de existir como tenant real)
4. **Smoke real homologação SEFAZ:** sempre na biz=1 (cert dela, ambiente=2 dela)
5. **Comentários em código:** **proibido** referenciar nome de cliente real ("Larissa", "RotaLivre") como cobaia. Usar genérico ("cliente piloto", "varejo gráfico típico")
6. **Auto-mem do agente:** entrada 🚨 no topo do MEMORY.md (`feedback_test_business_id_1_nunca_4.md`)

Exceção legítima — cenário cross-tenant onde se valida isolamento explícito:

```php
// OK: contraste 1 (Wagner) vs 99 (adversário improvável)
NfeFiscalRule::create(['business_id' => 1, ...]);
NfeFiscalRule::create(['business_id' => 99, ...]);

// PROIBIDO: contraste 1 vs 4 (Wagner vs RotaLivre cliente real)
// PROIBIDO: contraste 4 vs 5 (cliente vs cliente)
```

## Enforcement (CI)

**`tests/Unit/BusinessIdGuardTest.php`** (PR #216) — Pest unit que varre `tests/` + `Modules/*/Tests/` em busca de hardcode `business_id=4`. **Falha CI** se qualquer regressão.

7 patterns regex cobrindo todas as formas de hardcode:

- `'business_id' => 4` (array PHP, qualquer espaçamento)
- `->business_id = 4` (atribuição em objeto)
- `businessId: 4` (named arg PHP 8+)
- `$businessId = 4` (variável)
- `->business(4)` (fluent builder TransactionBuilder etc)
- `'user.business_id' => 4` (session put)
- `'business.id' => 4` (session put alt)

Pula comentários (linhas começando com `//` ou `*`) + auto-referência do próprio guard test. Mensagem de erro com `file:line:trecho` pra fix rápido. Roda em ~1s pra 148 arquivos.

## Consequências

### Positivo

- Cliente real fica isolado de cobaia técnica — nenhum test toca dados que parecem ser dele
- Smoke real fiscal em homologação SEFAZ é seguro (sempre biz=1)
- Onboarding de dev/IA mais limpo: regra clara, enforced em CI
- LGPD / privacidade: PII de cliente não vaza em PR/log/commit message via fixture
- Disciplina de cross-tenant testing: contraste com 99 é claramente "adversário fictício", não cliente real

### Negativo

- Fixtures legacy precisam de atenção quando migrados (3 PRs de cleanup nesta sessão: #208, #215, #216 — total 47 arquivos)
- Guard CI gasta ~1s por suite — custo trivial mas não-zero
- Cenário onde biz=4 é DADO REAL (ex: query contra DB compartilhado) precisa comentário explicando

### Migração

- ✅ NfeBrasil: 14 arquivos (PR #208) + 8 escapados (PR #215)
- ✅ Whatsapp: 8 arquivos (PR #216)
- ✅ RecurringBilling: 4 arquivos (PR #216)
- ✅ Copiloto: 12 arquivos (PR #216)
- ✅ Builders: 1 arquivo (PR #216)
- ✅ Guard CI ativo desde PR #216

**Audit final pré-ADR:** 148 arquivos / 0 violações / 50 arquivos com 237 ocorrências de `business_id=1` (Wagner).

## Refs

- Auto-mem: `feedback_test_business_id_1_nunca_4.md`
- [ADR 0093](0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- PRs de migração: [#208](https://github.com/wagnerra23/oimpresso.com/pull/208), [#215](https://github.com/wagnerra23/oimpresso.com/pull/215), [#216](https://github.com/wagnerra23/oimpresso.com/pull/216)
- Skill: `multi-tenant-patterns` (Tier A always-on)
- `tests/Unit/BusinessIdGuardTest.php` (guard CI)

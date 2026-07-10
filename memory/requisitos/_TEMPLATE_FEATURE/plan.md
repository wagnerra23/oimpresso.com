<!--
  TEMPLATE — copie pra memory/requisitos/<Mod>/features/<slug>/plan.md e cure os {{...}}.
  O plan é o COMO técnico. Decisão arquitetural nova = ADR (append-only), aqui só referência.
-->
---
feature: {{slug-kebab}}
module: {{PascalCase}}
---

# Plan — {{título curto da feature}}

## Decisões técnicas

| # | Decisão | Por quê (1 linha) | Âncora |
|---|---|---|---|
| D1 | {{ex: comando artisan idempotente, não SQL direto}} | {{ex: proibições "mexeu, registra" — caminho canônico}} | {{ADR/proibições/pattern}} |
| D2 | {{...}} | {{...}} | {{...}} |

## Plug-points (comparar e NÃO duplicar — inventário ANTES de criar símbolo)

> Regra `reuse-check`: antes de criar classe/comando/componente, listar o equivalente existente.

| Onde | O que já existe | Como esta feature encaixa |
|---|---|---|
| `{{Modules/<Mod>/...}}` | {{símbolo/pattern existente}} | {{estende/reusa/imita}} |
| `{{...}}` | {{...}} | {{...}} |

## Riscos Tier-0 (checklist obrigatório — marcar N/A conscientemente, nunca omitir)

- [ ] **Multi-tenant (ADR 0093):** {{toda query com `business_id`? job passa `$businessId` no constructor?}}
- [ ] **REGRA MESTRE valor/estoque:** {{mexe em preço/total/cobrança/estoque? → dry-run + tabela antes→depois + dupla confirmação + aprovação Wagner ANTES de escrita}}
- [ ] **PII/LGPD:** {{CPF/CNPJ em log/PR? → `[REDACTED]`/`PiiRedactor`}}
- [ ] **Tela (ADR 0264):** {{toca `resources/js/Pages/**`? → UC no `<Tela>.casos.md` + charter + gate visual}}
- [ ] **Runtime (ADR 0062):** {{daemon/pacote só-CT100? teste Pest → CT 100, nunca local/Hostinger}}

## Alternativas descartadas (anti-regressão — variante parecida também está descartada)

- {{alternativa}} — {{por que caiu, 1 linha}}

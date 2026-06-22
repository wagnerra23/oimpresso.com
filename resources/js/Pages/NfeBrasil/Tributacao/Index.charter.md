---
page: /nfe-brasil/tributacao
component: resources/js/Pages/NfeBrasil/Tributacao/Index.tsx
owner: wagner
status: live
last_validated: "2026-05-10"
parent_module: NfeBrasil
related_adrs: [29, 93, 94]
related_us: [US-NFE-010, US-NFE-061]
tier: A
charter_version: 1
---

# Page Charter — /nfe-brasil/tributacao

> **Status:** live em 2026-05-10. Charter criado por skill `charter-write` disparado pela auditoria de completude (US-NFE-061 P0). Non-Goals + Anti-hooks aprovados por Wagner em 2026-05-10.

---

## Mission

Configurar **tributação default + regras NCM específicas** do business — única tela onde o usuário aplica template setor (1-clique MEI / Simples / Lucro Presumido / Lucro Real), edita config default (regime, CFOP, CSOSN, CST, alíquotas), gerencia regras NCM específicas, e habilita/desabilita o gate per-business de **emissão automática NFC-e**.

---

## Goals — Features (faz)

- AppShellV2 + Head `Tributação · NF-e Brasil`
- Templates L1 (US-NFE-TPL-001): cards setoriais com `{titulo, descricao, regime, UF, modelo NFe}` e botão "Aplicar template"
- Confirmação destrutiva ao aplicar template se já existe config (`window.confirm` com aviso "vai SUBSTITUIR a configuração atual")
- Listagem regras NCM ordenadas por NCM → uf_origem → uf_destino (NULL last via `IS NULL DESC`)
- Cada regra mostra: NCM formatado (XXXX.XX.XX), UF origem→destino (ou "todas"), CFOP, CSOSN/CST, alíquotas (ICMS/PIS/COFINS/IPI) em `pct()` PT-BR
- Switch "Emissão automática NFC-e" — gate per-business (ADR 0093 Tier 0); requer config existir antes de toggle
- Botões linkados: Editar regra, Remover regra (com confirm), Nova regra, Importar CSV
- Toast feedback em todas mutações (sonner)
- Multi-tenant Tier 0: query usa `business_id` em todas operações + HasBusinessScope no `NfeFiscalRule` e `NfeBusinessConfig`
- AuditLog em mutações sensíveis: `activity('nfe.tributacao')->log('auto_emission.toggled')` no toggleAutoEmission (já implementado, linha 105-112 do Controller)
- Cascade defaults NCM→produto (ADR satélite arq/0006)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test.

- ❌ Calculadora interativa de tributo (essa tela é configuração; cálculo real é em runtime de venda)
- ❌ Importar regras de outro business (multi-tenant Tier 0)
- ❌ Templates customizados pelo usuário final (curadoria centralizada via `TributacaoTemplateService`)
- ❌ Histórico de mudanças inline na UI (audit log via `activity_log`, consulta separada)
- ❌ Validar regra contra SEFAZ no save (validação local; teste real é só na emissão)
- ❌ Bulk-edit alíquotas (uma regra por vez via Edit; bulk só via Import CSV)
- ❌ Sincronização automática com tabela TBT (Tributação por NCM oficial) — config humana

---

## UX Targets

- p95 first-paint < 1500ms (regras + config + templates carregados em 1 query Eloquent + 1 service)
- Toggle auto-emission < 500ms (preserveScroll Inertia)
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal
- Tipografia canon ADR 0110: header 24px, card title 14px, badge 10px (`text-[10px]`)
- Cores semânticas: emerald (success flash), red (error/destrutivo), primary (template aplicar)
- Layout responsive: templates em `grid md:grid-cols-3`; regras em tabela densa
- Confirm dialogs nativos do browser pra ações destrutivas (NCM remove + template substituir)
- Toast só pra ações concluídas; error pega 4xx/5xx

---

## UX Anti-patterns

- ❌ Modal pra editar regra (canon = navegar pra `/regras/{id}/edit` ou `_components/RegraForm`)
- ❌ Apply template sem confirm quando já há config (canon = `window.confirm` explícito)
- ❌ Mostrar alíquota com mais de 2 casas decimais (canon = `(decimal * 100).toFixed(2)` PT-BR com vírgula)
- ❌ Cor crua `bg-(green|red)-N` (canon = `bg-emerald-50` / `bg-destructive/10` ADR 0110)
- ❌ Auto-aplicar template sem clique (canon = botão explícito "Aplicar template")
- ❌ Toggle auto-emission antes de existir config (canon = mostra erro flash + redireciona)
- ❌ Reload full após mutação (canon = `preserveScroll: true`)
- ❌ Mostrar regras de outro tenant (multi-tenant Tier 0 — `business_id` global scope)

---

## Automation Hooks

- `GET /nfe-brasil/tributacao` → `TributacaoController::index` (Inertia render com regras + config + templates)
- `POST /nfe-brasil/tributacao/auto-emission/toggle` → toggleAutoEmission (gate per-business; valida config existe; loga via Spatie activity)
- `POST /nfe-brasil/tributacao/templates/{slug}/aplicar` → aplicarTemplate (cria/substitui config default; regras NCM permanecem)
- `GET /nfe-brasil/tributacao/regras/create` → form criar regra
- `POST /nfe-brasil/tributacao/regras` → store regra (FormRequest `UpsertRegraTributariaRequest`)
- `PUT /nfe-brasil/tributacao/regras/{id}` → update regra
- `DELETE /nfe-brasil/tributacao/regras/{id}` → destroy regra
- `POST /nfe-brasil/tributacao/import/preview` → preview CSV
- `POST /nfe-brasil/tributacao/import/aplicar` → aplicar CSV em batch
- Cascade NCM→produto: ao salvar config default, listeners propagam pra cálculos posteriores (ADR satélite arq/0006)
- Multi-tenant: HasBusinessScope no `NfeFiscalRule` + `NfeBusinessConfig`
- Audit: `activity('nfe.tributacao')->log()` no toggleAutoEmission ✅ implementado; expansão pra store/update/destroy regras + aplicarTemplate via US-NFE-062 P1

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emissão de NFe (essa tela é configuração)
- ❌ Não chama SEFAZ no render (só no smoke da tela /certificado)
- ❌ Não escreve no banco no render inicial (só nos POSTs)
- ❌ Não acessa regras de outro `business_id` (multi-tenant Tier 0)
- ❌ Não dispara Job de emissão quando toggleAutoEmission=true (Job é disparado por listener de venda finalizada)
- ❌ Não muda alíquotas históricas de NFes já emitidas (config nova só afeta emissões futuras)
- ❌ Não permite NCM inválido sem validação local (FormRequest valida formato 8 dígitos)

---

## Métricas vivas (Pest GUARD — a escrever em F1.5)

```php
// Modules/NfeBrasil/Tests/Charters/TributacaoCharterTest.php

it('renders under 1500ms p95 with regras + config + templates')
it('does not emit emails on render or any POST')
it('does not call SEFAZ on render or toggle auto-emission')
it('does not write to DB on render (only on POSTs)')
it('isolates regras and config by business_id (cross-tenant 404)')
it('blocks toggle auto-emission when config does not exist (flash error)')
it('logs activity on toggle auto-emission with business_id')
it('renders at 1280px without horizontal scroll')
it('formats alíquotas with 2 decimals PT-BR (vírgula not period)')
it('formats NCM as XXXX.XX.XX')
it('confirms destructively when applying template over existing config')
```

---

## Refs

- [US-NFE-010](../../../../../memory/requisitos/NfeBrasil/SPEC.md) — fase 2 UI tributação
- [US-NFE-TPL-001](../../../../../memory/requisitos/NfeBrasil/SPEC.md) — Templates L1
- [ADR satélite NfeBrasil/arq/0006](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md) — cascade NCM→produto
- [ADR 0029](../../../../memory/decisions/0029-inertia-upos.md) — Inertia + UltimatePOS
- [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 (gate per-business auto-emission)
- [ADR 0094](../../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-10 | [CC] charter-write skill + [W] | Draft criado por US-NFE-061 (auditoria de completude module-completeness-audit). Wagner aprovou Non-Goals + Anti-hooks no mesmo dia → status:live. |

---
page: /nfe-brasil/tributacao/config-default
component: resources/js/Pages/NfeBrasil/Tributacao/ConfigDefault.tsx
owner: wagner
status: draft
last_validated: 2026-05-16
parent_module: NfeBrasil
related_adrs: [0029, 0093, 0094]
related_us: [US-NFE-010]
tier: A
charter_version: 1
---

# Page Charter — /nfe-brasil/tributacao/config-default

> **Status:** draft em 2026-05-16. Charter criado pelo Wave M boost (auditoria NfeBrasil 71→82, gap D3.c charters 30%). Non-Goals + Anti-hooks aguardam aprovação Wagner antes de promover pra `status:live`.

---

## Mission

Configurar os **defaults tributários por business** (regime fiscal, CSOSN/CST, ICMS, PIS, COFINS, IPI) que ficam no Nível 4 da cascata de defaults do motor tributário (business → NCM → UF → produto). Única tela onde Gestor/Admin define os fallbacks aplicados quando nenhuma regra NCM específica casa.

---

## Goals — Features (faz)

- AppShellV2 + Head `Defaults Tributários · NF-e Brasil`
- Form com regime (mei / simples / lucro_presumido / lucro_real) — Select
- Wizard "Aplicar pelo regime" (botão `Wand2`) — pré-popula CSOSN/CST + alíquotas conservadoras do `REGIME_DEFAULTS` constante
- Toggle CSOSN vs CST automático pelo regime (Simples/MEI=CSOSN, Lucro Pres/Real=CST)
- Inputs ICMS, PIS, COFINS, IPI (decimal 0.0000 — 4 casas pra precisão fiscal)
- Save via `useForm` Inertia POST `/nfe-brasil/tributacao/config-default`
- Validação backend `ConfigDefaultRequest::authorize` (`nfe.tributacao.manage`)
- Toast feedback (sonner) — success em save, error em 4xx/5xx
- Link "Voltar" → `/nfe-brasil/tributacao` (Index)
- Multi-tenant Tier 0: `NfeTributacaoConfig::where('business_id', $businessId)` global scope (ADR 0093)
- Defaults conservadores SP (ICMS 18%) — outros estados ajustam via regras NCM Nível 2/3
- Hint visual no Select regime mostra defaults aplicados (educacional)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test.

- ❌ Editar regras NCM individuais (essa tela é só Nível 4 — regras NCM em `/nfe-brasil/tributacao` Index + RegraForm)
- ❌ Aplicar defaults cross-business (cada business tem `tributacao_default` próprio — ADR 0093)
- ❌ Wizard automático por UF (defaults são SP; outros estados via Nível 3 regra UF)
- ❌ Importar CSV NCM nessa tela (existe `ImportCsv.tsx` próprio)
- ❌ Calcular tributação de venda exemplo (motor calcula via `MotorTributarioService`, não preview UI)
- ❌ Histórico de mudanças de defaults (audit via `activity_log`, não UI aqui)
- ❌ Toggle ICMS-ST/MVA nessa tela (escopo cascata Nível 4 é defaults básicos; ST é regra NCM)
- ❌ Save sem confirmação de mudança crítica (regime alterado afeta TODAS emissões posteriores — confirm UI)

---

## UX Targets

- p95 first-paint < 1200ms (form simples + 1 query DB)
- Save Inertia POST < 1500ms p95
- 0 erros JS console
- Cabe em monitor 1280px sem scroll horizontal
- Tipografia canon ADR 0110: header 24px, label 13px, input 14px
- Cores semânticas: emerald (save success), red (validation error), sky (wizard hint)
- Decimal inputs aceitam vírgula PT-BR (0,1800) E ponto (0.1800)
- Wizard "Aplicar pelo regime" mostra valores antes de aplicar (preview opcional)
- File `preserveScroll: true` em save (sem reload full)
- Required fields marcados com `*` PT-BR

---

## UX Anti-patterns

- ❌ Auto-save em onChange (canon = save explícito via botão; mudança de regime é decisão grave)
- ❌ Wizard sobrescrever sem confirm (canon = mostrar valores antes, usuário aceita)
- ❌ Cor crua `bg-(green|red)-N` (canon = emerald/red semântico ADR 0110)
- ❌ Reload full após save (canon = `preserveScroll: true` Inertia)
- ❌ Validar apenas no backend (canon = validação client-side básica + backend canônico)
- ❌ Esconder hint de regime após primeiro uso (canon = sempre visível — operação rara, contexto importa)
- ❌ Modal pra confirmar save (canon = toast pós-save + flash message; modal só pra destrutivo)
- ❌ Aceitar regime ∉ {mei, simples, lucro_presumido, lucro_real} (canon = enum estrito)

---

## Automation Hooks

- `GET /nfe-brasil/tributacao/config-default` → `ConfigDefaultController::edit` (Inertia render com config atual)
- `POST /nfe-brasil/tributacao/config-default` → `ConfigDefaultController::update` (FormRequest valida regime+permissão; service persiste em `nfe_tributacao_config.tributacao_default` JSON)
- Multi-tenant: `NfeTributacaoConfig` usa `HasBusinessScope` (ADR 0093) — 1 row por business
- Service: `TributacaoConfigService::aplicarDefaults(regime)` retorna constante REGIME_DEFAULTS server-side espelhando client (consistência cascata)
- Motor tributário downstream: `MotorTributarioService::calcular(item)` usa `tributacao_default` como fallback Nível 4 quando NCM regra não casa
- Audit: `activity('nfe.tributacao.config')->log()` em save (mudança afeta emissões posteriores) — US-NFE-062 P1
- Validation: ICMS/PIS/COFINS/IPI ∈ [0, 1] (alíquotas decimais — 0.18 = 18%)

---

## Automation Anti-hooks

> O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails ao abrir ou salvar (ação interna config)
- ❌ Não dispara emails em mudança de regime (decisão administrativa, sem notificação automática)
- ❌ Não escreve no banco no render inicial (só no POST)
- ❌ Não acessa config de outro `business_id` (multi-tenant Tier 0)
- ❌ Não chama SEFAZ no save (config é interna; SEFAZ só vê na próxima emissão)
- ❌ Não dispara re-cálculo retroativo de NFes já autorizadas (config muda só futuro — append-only fiscal)
- ❌ Não modifica `nfe_emissoes` existentes (config é só template; emissões guardam snapshot da tributação aplicada)
- ❌ Não loga PII (config tributária é dado público fiscal — sem CPF/CNPJ aqui de qualquer forma)
- ❌ Não dispara Job background no save (operação síncrona simples — UPDATE config)

---

## Métricas vivas (Pest GUARD — a escrever em F1.5)

```php
// Modules/NfeBrasil/Tests/Charters/ConfigDefaultCharterTest.php

it('renders under 1200ms p95 with form + current config')
it('does not emit emails on render or save')
it('does not call SEFAZ on save (config is internal)')
it('does not write to DB on render (only on POST update)')
it('isolates config by business_id (cross-tenant 404)')
it('rejects regime outside enum {mei, simples, lucro_presumido, lucro_real}')
it('rejects aliquota outside [0, 1] range')
it('does not modify existing nfe_emissoes (append-only fiscal)')
it('renders at 1280px without horizontal scroll')
it('logs activity on save with business_id and regime change')
it('preserveScroll true on save (no reload)')
it('wizard preview shows REGIME_DEFAULTS before apply')
```

---

## Refs

- [US-NFE-010](../../../../memory/requisitos/NfeBrasil/SPEC.md) — Tributação + cascata defaults
- [ADR 0029](../../../../memory/decisions/0029-inertia-upos.md) — Inertia + UltimatePOS
- [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- ADR satélite `Modules/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto` — cascata 4 níveis (business → NCM → UF → produto)
- [Tributacao Index.charter.md](../Tributacao/Index.charter.md) — charter irmã (regras NCM Nível 2/3)
- [BRIEFING.md](../../../../memory/requisitos/NfeBrasil/BRIEFING.md) — estado consolidado módulo

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-16 | [CC] Wave M boost | Draft criado pelo Wave M auditoria (NfeBrasil 71→82, gap D3.c charters 30%). Non-Goals + Anti-hooks aguardam aprovação Wagner. |

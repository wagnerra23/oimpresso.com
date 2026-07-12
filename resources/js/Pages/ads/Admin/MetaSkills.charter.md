---
page: /ads/admin/meta-skills
component: resources/js/Pages/ads/Admin/MetaSkills.tsx
related_prototype: n/a (herda PT-04 Dashboard; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: ADS
related_adrs: [114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /ads/admin/meta-skills (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/ADS/Http/Controllers/Admin/MetaSkillsController@index` + `toggle`/`store`/`validateRule` (rotas `ads.admin.metaskills.*`, middleware `auth` — V1 superadmin). Lê via `GovernanceRulesService::listAll()` (`mcp_governance_rules`), agrupado por categoria. `store` grava `created_by='wagner'`, `enabled=false` (draft). Não há filtro business_id explícito — regras de governança são efetivamente globais/cross-tenant.
>
> PT-04 declarado pela faixa `KpiGrid`/`KpiCard` que lidera a tela; o editor inline (`useForm`/`<form>`) é secundário (toggle showEditor), por isso não classifiquei como PT-02.

---

## Mission
Gerir as regras SOFT de governança do ADS (ARQ-0007) — diferentes do Policy Engine (firewall HARD imutável): meta-skills são configuráveis aqui, com DSL JSON (condition + action), versão e contagem de triggers. Regem como as skills evoluem (promoção, escalação, retry, budget, review), avaliadas em runtime pelo `GovernanceRulesService`.

---

## Goals — Features (faz)
- 4 KPIs: meta-skills totais, ativas, triggers totais, categorias.
- Regras agrupadas por categoria (cards): nome, rule_key, versão, contagem de triggers, descrição, condição humana e ação (type + params).
- Switch por regra pra ativar/desativar (POST toggle, preserveScroll) sem deletar.
- Editor inline (toggle "Nova Meta-skill"): rule_key, categoria, nome, descrição, condition builder (AND/OR + campos/operadores/valores) e action JSON.
- Botão "Validar contra dados reais": POST fetch `/meta-skills/validate` (dry-run contra até 50 `mcp_decision_patterns`, retorna quantos matchariam) — read-only.
- Salvar cria draft com `enabled=false`.

---

## Non-Goals — Features (NÃO faz)
- ❌ NÃO deleta meta-skills (só desativa via switch — auditável, cria nova versão em mudança).
- ❌ NÃO edita as regras HARD do Policy Engine (essas são imutáveis, só PR git — ver `/ads/admin/policy`).
- ❌ NÃO ativa a regra recém-criada automaticamente (nasce draft desativada; Wagner ativa depois).
- ❌ NÃO escopa por business_id — regras são governança global; inferência pendente: confirmar com Wagner se meta-skills deveriam ser per-tenant.

---

## UX targets
- p95 < 1500ms (admin) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2 ; editor inline sem sair da página.

---

## Automation hooks (faz)
- "Validar contra dados reais" roda a condição contra patterns reais on-demand (dry-run, sem escrever).
- Meta-skills ativas são avaliadas em runtime pelo `GovernanceRulesService` contra cada decision/pattern (efeito fora desta tela).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Sem polling / auto-refresh.
- ❌ Criar regra nunca a deixa ativa sozinha (enabled=false forçado no store).
- ❌ Validar não persiste nada — é só simulação read-only.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot) — cobrir editor aberto + validação ok/erro
- [ ] Confirmar escopo (global vs per-business) das regras de governança

---
status: accepted
date: 2026-05-21
deciders: [wagner, claude]
adr_number: 0178
related: [0093, 0104, 0127]
supersedes: []
superseded_by: []
---

# ADR 0178 — Restauração dos campos fiscais BR em `contacts` (regressão UPOS 6.7)

## Contexto

Em 2025-06-09 o fork oimpresso (commit `7ab688162` "Versão 3.7 Original com as modificações brasil") adicionou 8 campos fiscais BR na `create_contacts_table` UPOS upstream: `cpf_cnpj`, `ie_rg`, `consumidor_final`, `contribuinte`, `rua`, `numero`, `bairro`, `cep`.

Em commits posteriores (`62e66cad7` / `ad58b9907` / `f9930aa37`) o upgrade UPOS 6.4 → 6.7 **sobrescreveu** a migration `create_contacts_table` com a versão upstream — sem os campos BR. Em deploys novos (dev limpo, CI), o schema perdeu os 4 campos canônicos (`cpf_cnpj`, `consumidor_final`, `contribuinte`, mais a migration órfã 2022_12_23_150311 `is_sincronizado`). Em prod existente os campos sobreviveram (ALTER TABLE em update PHP-UPOS não dropa columns).

Resultado: o frontend Inertia (`Cliente/Create.tsx`) só tinha 1 campo `tax_number` genérico, sem máscara CPF/CNPJ, sem validação mod-11, sem IE/IM/nome_fantasia/regime.

Wagner detectou 2026-05-21: "*acho que antes era `cpf_cnpj` que eu já usava*". Investigação confirmou.

## Decisão

Restaurar o canon BR em `contacts` via 4 slices:

1. **Migration 2026_05_21_140000_restore_br_fields_to_contacts.php** (PR #1313 mergeado) — IDEMPOTENTE via `Schema::hasColumn` check. 10 colunas:
   - `cpf_cnpj` (string 20, indexed) — identidade fiscal
   - `rg` (string 20)
   - `inscricao_estadual` / `inscricao_municipal` (string 30)
   - `indicador_ie` (tinyInteger — NFe 1/2/9)
   - `nome_fantasia` (string 150)
   - `consumidor_final` (boolean default false) — modernizado de int → bool
   - `contribuinte` (boolean default true)
   - `regime` (string 30 — simples/presumido/real/mei)
   - `suframa` (string 20)
2. **Rule `App\Rules\BR\CpfCnpj`** (PR #1313) — delega pra `Util::validarCnpjCpf` (mod-11 SEFAZ) que já estava vendored em `lib-custom/laravel-boleto/src/Util.php:1211` mas zero-usada
3. **UI Inertia React** (PR #1316) — `DadosFiscaisBRSection.tsx` reusada em Create/Edit + bloco "Dados fiscais BR" no Show com `cpf_cnpj_masked` (canon Anti-hook LGPD)
4. **Comando `cliente:backfill-cpf-cnpj`** (PR #1319) — IDEMPOTENTE + LGPD-friendly + Tier 0 safe pra migrar cadastros legacy `tax_number → cpf_cnpj` quando mod-11 válido

### Princípios

- **`tax_number` (genérico UPOS) preservado** pra back-compat — apenas adicionamos `cpf_cnpj` (canon BR semântico)
- **Migrations append-only** — não alteramos a `create_contacts_table` upstream (ainda fica drift potencial em upgrade futuro UPOS 7+ mas `Schema::hasColumn` no novo arquivo neutraliza)
- **LGPD**: `cpf_cnpj` mascarado via `maskTaxNumber()` ANTES de enviar pro frontend (Anti-hook Show.charter). Activity log Contact exclui PII via `logOnly`. Backfill log JSON nunca grava valor plain.
- **Multi-tenant Tier 0**: todos os endpoints filtrados por `business_id` global scope

## Consequências

### Positivas
- Larissa @ ROTA LIVRE (biz=4) consegue cadastrar cliente PJ completo: IE, IM, regime, nome fantasia, indicador NFe
- NFe SEFAZ pode usar `cpf_cnpj` semântico em vez de heurística "se tax_number tem 11 ou 14 dígitos"
- Pacote `nfephp-org/sped-*` (já no composer) pode consumir campos canônicos
- Validação mod-11 reaproveita código já vendored — zero deps extras

### Riscos / mitigações
- **Upgrade UPOS 7+ futuro pode sobrescrever de novo** → mitigação: Pest test GUARD `tests/Feature/Contact/ContactBrFieldsRestoredTest` falha em CI se alguém perder campos. Documentado neste ADR.
- **Drift entre `tax_number` e `cpf_cnpj`** → mitigação: backfill comando idempotente + recomendação interna usar `cpf_cnpj` daqui em frente (futuro: depreciar `tax_number` em US 2027+)
- **Migration 2021_01_26_155423_add_regime_table_contacts.php quebrada em deploy limpo** (`after('contribuinte')` falha sem coluna) → mitigação: nossa migration nova adiciona `regime` com `Schema::hasColumn` guard, neutralizando o efeito da quebrada sem alterá-la (append-only)

## Pendências / próximos passos

- **Slice 5a** — BrasilAPI lookup CNPJ (botão "Buscar CNPJ" auto-preenche razão/IE/fantasia) — PR paralelo nesta sessão
- **Slice 6** — RUNBOOK + visual-comparison Cliente (governança MWART) — PR paralelo
- **Slice 7** — FormRequest dedicado wirando Rule\BR\CpfCnpj no backend (hoje só máscara visual frontend) — PR paralelo
- **Slice 5b futuro** — Importer Delphi RotaLivre com mapping BR

## Refs

- Investigação: `memory/sessions/2026-05-21-investigar-campos-br-cliente.md`
- Commits canônicos:
  - `7ab688162` — baseline v3.7 BR (2025-06-09)
  - `62e66cad7` / `ad58b9907` / `f9930aa37` — regressão UPOS 6.7
  - `5c8e50432` — PR #1313 Slice 1 (migration + Rule)
  - `90d07ae41` — PR #1316 Slices 2+3 (UI)
  - `6e62750b6` — PR #1319 Slice 4 (backfill)
- ADRs: 0093 (Tier 0), 0104 (MWART), 0127 (FSM/LGPD)

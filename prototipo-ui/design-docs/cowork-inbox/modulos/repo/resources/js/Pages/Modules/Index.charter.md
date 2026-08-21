---
id: resources-js-pages-modules-index-charter
page: /modulos
component: resources/js/Pages/Modules/Index.tsx
related_prototype: prototipo-ui/cowork/modulos/modulos-page.jsx (herda PT-01 Lista + PT-02 Drawer)
owner: wagner
status: draft
last_validated: "2026-08-19"
parent_module: Superadmin
related_adrs: [93, 101, 114, 286]
tier: B
charter_version: 2
---

# Page Charter — /modulos (v2)

> **v2 escrita por [CC] 2026-08-19** sobre a v1 (draft de 2026-07-11). Sai de `draft` quando [W]
> responder as 4 decisões do §Pendências e ratificar Non-Goals + Anti-hooks.
>
> Backend: `app/Http/Controllers/ModuleManagementController` + `app/Services/ModuleManagerService`.
> Rotas `web.php:911-929` (`modules.index|toggle|install|uninstall`), grupo
> `web · setData · auth · SetSessionData · language · timezone · AdminSidebarMenu`.
> Substitui `/manage-modules` do UltimatePOS (AdminLTE quebrado); `LegacyMenuAdapter.php:287` redireciona.
>
> ⚠️ **Rota cross-tenant intencional** — estado app-wide, sem `business_id` scope (ADR 0093 §exceções
> superadmin). É a exceção documentada, não drift (travada por UC-MOD-15).

---

## Mission

Visão única dos módulos nWidart do app com seu estado real (ativo · inativo · com erro · não
registrado), e as três ações de ciclo de vida — ativar/desativar, instalar (migrations + setup),
desativar preservando dados — sem CLI e sem AdminLTE. Escopo global, para superadmin.

---

## Goals (faz)

- Inventário de `Modules/` com estado lido de `modules_statuses.json`, ordenado ativos → área → nome
- Contagens no header (total · ativos · inativos · com erro) + 4 KPI cards
- Filtro por área e por status (chips removíveis) + busca por nome/alias/descrição/área (debounce 300 ms, `/` foca)
- Toggle inline por linha; kebab com Instalar/Reinstalar e Desativar; confirmação que diz a consequência
- Detalhe em drawer lateral (PT-02) com descrição completa e metadados da pasta
- Vazio contextual (motivo + ação), skeleton no carregamento, 403 explicado

## Non-Goals (NÃO faz)

- ❌ NÃO habilita/desabilita módulo **por negócio** — isso é compra de pacote (`/superadmin/packages/{id}/edit`)
- ❌ NÃO aplica `business_id` scope (cross-tenant intencional)
- ❌ NÃO é acessível a usuário comum de negócio
- ❌ NÃO instala/remove **código** de módulo — reflete estado e roda migrations; código chega por deploy
- ❌ NÃO derruba tabela em nenhum caminho (uninstall = desativar)
- ❌ NÃO edita `module.json`

## UX targets

- p95 < 1500 ms na listagem
- Cabe em **1280px com a sidebar aberta**, sem scroll horizontal (a descrição trunca; a coluna encolhe)
- Estado de cada módulo legível por badge tokenizado, nunca por texto solto
- Detalhe é drawer lateral — nunca modal full-screen (PT-02)

## Acréscimos v2 (A1–A4)

- **A1** — zero enum cru na UI: `active|inactive|errored|unregistered` só aparecem como Ativo · Inativo · Com erro · Não registrado
- **A2** — ação destrutiva diz a consequência **antes** ("as tabelas do banco são PRESERVADAS")
- **A3** — 1280px sem overflow (medido, não estimado)
- **A4** — detalhe em drawer PT-02

## Automation hooks (faz)

- Reflete `modules_statuses.json` + varredura de `Modules/`
- `install` roda `module:migrate --force` e, se existir, `<alias>:install` (`--business` da sessão ou `--all`)
- `toggle`/`install` limpam `cache:clear` + `config:clear`

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO grava nada em GET
- ❌ NÃO dispara deploy
- ❌ NÃO ativa módulo para negócio sem o fluxo de pacote
- ❌ NÃO remove chave órfã do JSON por conta própria

---

## Regras de domínio (R1–R11)

R1 fonte do estado é `modules_statuses.json` · R2 só pasta em `Modules/` vira linha · R3 `registered`
distingue "não registrado" de "inativo" · R4 ordem ativos→área→nome · R5 área é heurística por
palavra-chave (fallback Outros) · R6 versão vem de `module.json` (**nenhum dos 32 declara** ⇒ v0.0 hoje;
ver D1) · R7 escopo app-wide · R8 install = ativar + migrate + `<alias>:install` · R9 desativar preserva
tabelas · R10 toggle/install limpam cache · R11 toast via `->with('status')` → prop `flash`, reload
parcial `['modules','flash']`.

---

## Pendências antes de `status: live`

- [ ] **D1** — versão sempre `v0.0`: ler `System::getProperty('<alias>_version')` (recomendado), declarar `version` nos 32 `module.json`, ou esconder
- [ ] **D2** — RBAC: hoje `is_admin`/`Admin#<biz>`; existe a permissão `manage_modules` (usada pelo legado e pelo menu) — unificar
- [ ] **D3** — drawer PT-02 entra na produção?
- [ ] **D4** — `install` roda migration dentro do request web: fila com estado "instalando" ou lock + limite declarado
- [ ] [W] ratifica Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)

## Irmãos

`Index.casos.md` (16 UC) · `prototipo-ui/contrato/modulos.contract.json` (ADR 0286) ·
`tests/Feature/Modules/ModuleManagementTest.php` · `tests/Unit/Services/ModuleManagerServiceTest.php`

---
id: resources-js-pages-modules-index-charter
page: /modulos
component: resources/js/Pages/Modules/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-08-19"
parent_module: Superadmin
related_adrs: [93, 101, 114, 286]
tier: B
charter_version: 2
---

# Page Charter — /modulos (v2)

> **v2 escrita por [CC] 2026-08-19** sobre a v1 (draft de 2026-07-11), com correções medidas por [CL]
> no mesmo dia. Sai de `draft` quando [W] responder as 4 decisões do §Pendências e ratificar
> Non-Goals + Anti-hooks — inferir Non-Goal é proibido, só [W] preenche.
>
> Backend: `app/Http/Controllers/ModuleManagementController` + `app/Services/ModuleManagerService`.
> Rotas `web.php:911-929` (`modules.index|toggle|install|uninstall`), grupo
> `web · setData · auth · SetSessionData · language · timezone · AdminSidebarMenu`.
> Substitui `/manage-modules` do UltimatePOS (AdminLTE quebrado).
> [`LegacyMenuAdapter.php:287`](../../../../app/Services/LegacyMenuAdapter.php) **reescreve o item de
> menu** (`$rewrites` dentro de `convertItem`) — **não** é redirect HTTP: URL antiga digitada ou salva
> nos favoritos continua batendo na rota Blade. Medido 2026-08-19.
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
- Filtro por área e por status (chips removíveis) + busca por nome/alias/descrição/área (debounce 300 ms)
- Toggle inline por linha; kebab com Instalar/Reinstalar e Desativar; confirmação que diz a consequência
- Vazio contextual (motivo + ação), 403 explicado

## Non-Goals (NÃO faz) — [W] ratifica

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

## Acréscimos v2 (A1–A4)

- **A1** — zero enum cru na UI: `active|inactive|errored|unregistered` só aparecem como Ativo · Inativo · Com erro · Não registrado
- **A2** — ação destrutiva diz a consequência **antes** ("as tabelas do banco são PRESERVADAS")
- **A3** — 1280px sem overflow (medido, não estimado)
- **A4** — detalhe em drawer PT-02 — **condicionado à decisão D3**; se D3 = não, vira tooltip na descrição truncada

## Automation hooks (faz)

- Reflete `modules_statuses.json` + varredura de `Modules/`
- `install` roda `module:migrate --force` e, se existir, `<alias>:install` (`--business` da sessão ou `--all`)
- `toggle`/`install` limpam `cache:clear` + `config:clear`

## Anti-hooks (NÃO faz automaticamente) — [W] ratifica

- ❌ NÃO grava nada em GET
- ❌ NÃO dispara deploy
- ❌ NÃO ativa módulo para negócio sem o fluxo de pacote
- ❌ NÃO remove chave órfã do JSON por conta própria

---

## Regras de domínio (R1–R11)

R1 fonte do estado é `modules_statuses.json` · R2 só pasta em `Modules/` vira linha · R3 `registered`
distingue "não registrado" de "inativo" · R4 ordem ativos→área→nome · R5 área é heurística por
`str_contains` no nome minúsculo (fallback Outros) · R6 versão vem de `module.json` — **medido
2026-08-19: 0 dos 32 declaram `version`**, logo as 32 linhas mostram `v0.0` (ver D1) · R7 escopo
app-wide · R8 install = ativar + migrate + `<alias>:install` · R9 desativar preserva tabelas ·
R10 toggle/install limpam cache · R11 toast via `->with('status')` → prop `flash`, reload parcial
`['modules','flash']`.

---

## Pendências antes de `status: live`

- [ ] **D1 — versão sempre `v0.0`.** Opções: ler a versão **instalada** de `System::getProperty('<chave>_version')`,
      declarar `version` nos 32 `module.json`, ou esconder a coluna até haver dado.
      ⚠️ O patch P4 propõe `\App\Models\System` — **essa classe não existe**; é
      [`App\System`](../../../../app/System.php) (`getProperty` na linha 36). E a chave consultada por
      `ModuleUtil::isModuleInstalled()` é `strtolower($name).'_version'`, **não** o alias — para
      módulo com alias kebab (`oficina-auto`) as duas divergem. Travar a convenção antes de exibir.
- [ ] **D2 — RBAC: existem duas leis para a mesma capacidade.** `/modulos` autoriza por
      `session('is_admin')` OU papel `Admin#<biz>`; o item de menu
      ([AdminSidebarMenu.php:809](../../../../app/Http/Middleware/AdminSidebarMenu.php)) e o legado
      ([Install/ModulesController](../../../../app/Http/Controllers/Install/ModulesController.php), 4 usos)
      autorizam pela permissão `manage_modules`, registrada em `AuthServiceProvider:36` — **verificado
      2026-08-19**. Dá para ver o item no menu e tomar 403 na tela. Patch P5 unifica em `manage_modules`.
- [ ] **D3 — drawer PT-02 entra na produção?** Decide o A4 e o `[BACKLOG]` reservado como UC-MOD-16.
- [ ] **D4 — `install` roda migration dentro do request web.** Fila com estado "instalando", ou lock +
      limite declarado. ⚠️ `config/queue.php` tem `'default' => env('QUEUE_CONNECTION', 'sync')` — se o
      `.env` do Hostinger não sobrescrever, `dispatch()` roda **inline** e a fila não resolve nada.
      Medir no servidor antes de escolher.
- [ ] [W] ratifica Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Exportar `modulos-page.{jsx,css}` do projeto Cowork para o espelho
      (`cowork-mirror-freshness --export-from`) e só então trocar o `related_prototype` para
      `prototipo-ui/cowork/modulos/modulos-page.jsx`. Hoje o arquivo existe **no Cowork** e **não** no
      espelho local (130 arquivos, flat, nenhum `modulos-*`) — declarar o path antes do export apontaria
      a âncora para arquivo inexistente, e transcrever build à mão é proibido (ADR 0374).

## Irmãos

`Index.casos.md` (15 UC + 4 `[BACKLOG]`) · `prototipo-ui/contrato/modulos.contract.json` (ADR 0286, MOD-O5) ·
`tests/Feature/Modules/ModuleManagementTest.php` · `tests/Feature/Modules/ModuleManagerServiceTest.php`

> O rascunho [CC] destinava o teste de serviço a `tests/Unit/Services/`. Fica em `tests/Feature/`
> porque `tests/Pest.php` só liga `Tests\TestCase` em Feature/Browser/KB: teste **estilo Pest** em
> `tests/Unit/` roda sem container Laravel (os 22 PHP de lá que precisam do app usam classe clássica
> `extends Tests\TestCase`; nenhum usa `uses(TestCase::class)`). Como o arquivo usa `storage_path()` e
> a facade `File`, no destino original ele quebraria.

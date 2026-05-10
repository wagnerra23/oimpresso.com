# Audit técnico — Modules/Repair shared OU Modules/OficinaAuto? — 2026-05-10

> **Status:** input técnico pra Wagner decidir ADR amendment 0121.
> Auditoria de read-only (não modifica código).
> Pesos: ADR 0105 (sinal qualificado), ADR 0094 §5 (SoC brutal), ADR 0093 (multi-tenant Tier 0).

## Contexto

[ADR 0121 §P8](../0121-oimpresso-modular-especializado-por-vertical.md) deixa explícita uma **decisão pendente**:
default temporário é "shared infrastructure", mas vocabulário automotivo
(`placa`, `vehicle`, `brand`, `km`, `box`, `mecanico`, `orcamento`,
`aprovacao_pendente`) **vazou pra produção** via PR #363 (kanban
`ProducaoOficina`). Cobertura atual de "ERP oficina auto" estimada em
55-60% ([gap-repair-vs-oficina-auto.md](gap-repair-vs-oficina-auto.md)).

ROTA LIVRE (Modules/Vestuario, biz=4, 99% volume) **não usa** Modules/Repair
hoje — charter Vestuario explicita "Modules/Repair: consome opcional".

Esta auditoria responde: **vocabulário automotivo é débito superficial
(arrumável em horas) ou estrutural (rebobinar 6+ meses de design)?**

## Inventário Modules/Repair

### Backend (PHP)

- **111 arquivos `.php`** (Modules/Repair/**), **52 Blade**, **6 Inertia tsx**
- **3 Entities**: `JobSheet`, `RepairStatus`, `DeviceModel`
- **9 Controllers**: `RepairController` (legacy core), `JobSheetController`,
  `RepairStatusController`, `DeviceModelController`, `DashboardController`,
  `ProducaoOficinaController` (kanban), `CustomerRepairStatusController`,
  `RepairSettingsController`, `DataController`, `InstallController`
- **17 migrations**: 3 tabelas próprias (`repair_statuses`,
  `repair_device_models`, `repair_job_sheets`) + 12 colunas anexadas a
  `transactions` (todas com prefixo `repair_*`) + 2 colunas em `business`
  (`repair_settings`, `repair_jobsheet_settings`) + 1 coluna em `brands`
  (`use_for_repair`) + 1 coluna em `products` (`repair_model_id`)
- **15 idiomas em `Resources/lang/`** — todos em vocabulário genérico-tech:
  `repair`, `serial_no`, `device_model`, `brand`, `repair_warranty`,
  `pre_repair_checklist`, `defect` (verificado em `pt/lang.php` linhas 1-60)
- **53 arquivos fora do módulo** referenciam `repair_job_sheet_id` ou
  prefixos `repair_*` em `transactions` — incluindo `app/Utils/TransactionUtil.php`,
  `app/Utils/ProductUtil.php`, `Modules/Connector/Http/Controllers/Api/SellController.php`,
  `app/Http/Controllers/SellPosController.php`, 6 receipts em `resources/views/sale_pos/`

### Frontend (Inertia/React)

6 Pages (todos sob `resources/js/Pages/Repair/`):
- `Dashboard/Index.tsx` (1 ocorrência "brand")
- `JobSheet/Index.tsx` (2 ocorrências "brand")
- `Status/Index.tsx` (genérico)
- `DeviceModels/Index.tsx` (3 ocorrências "brand")
- `Index.tsx` (genérico)
- **`ProducaoOficina/Index.tsx` — 38 ocorrências automotivas** (placa/vehicle/brand/km/box/mecanico/orcamento)
- `ProducaoOficina/Index.charter.md` (2 ocorrências; charter MWART)

Plus `ProducaoOficinaController.php` (35 ocorrências automotivas — mock data
cheia de Civic/Onix/HB20/Corolla + heurísticas BOXES B1-B4 + ELEVADORES E1-E2).

### Schema BD (vocabulário real)

| Tabela | Colunas-chave | Vocabulário |
|--------|---------------|-------------|
| `repair_job_sheets` | `serial_no`, `defects`, `device_id`, `device_model_id`, `brand_id`, `service_type` enum(`carry_in`/`pick_up`/`on_site`), `service_staff`, `estimated_cost`, `delivery_date`, `checklist`, `parts`, `security_pwd`, `security_pattern`, `comment_by_ss`, `product_condition`, `product_configuration` | **genérico-tech** (assistência técnica) |
| `repair_device_models` | `name`, `repair_checklist`, `brand_id`, `device_id` | **genérico-tech** |
| `repair_statuses` | `name`, `color`, `sort_order`, `is_completed_status`, `email_template`, `sms_template` | **agnóstico** |
| `transactions` (12 cols) | `repair_serial_no`, `repair_defects`, `repair_security_pwd`, `repair_security_pattern`, `repair_warranty_id`, `repair_brand_id`, `repair_status_id`, `repair_model_id`, `repair_due_date`, `repair_completed_on`, `repair_device_id`, `repair_updates_notif` | **genérico-tech** |
| `business` (2 cols JSON) | `repair_settings`, `repair_jobsheet_settings` | **agnóstico** |

**Conclusão schema:** ZERO colunas com vocabulário automotivo no BD.
Vocabulário auto vive **apenas no `ProducaoOficinaController` (mapping
heurístico) + `ProducaoOficina/Index.tsx` (UI labels)**. O resto do módulo
fala "device/serial_no/brand" — herança UltimatePOS-assistência-técnica.

### Clientes em prod usando hoje

`git log` desde 2026-04-01 mostra **17 commits** em Modules/Repair:
todos são desenvolvimento interno (S2.5 MWART + S6 Charter + PR #363
Kanban). PR #363 (drag-and-drop) entregue 2026-05-09 mas **sem cliente
piloto pago confirmado** — Modules/Repair cobre 0 clientes pagantes hoje
(ROTA LIVRE não usa, oficinas auto piloto pendente Martinho —
[ADR 0121 §P7](../0121-oimpresso-modular-especializado-por-vertical.md)).

**Implicação:** decisão A vs B vs C pode ser tomada **sem migration de
dados de cliente vivo** — janela de baixo risco que se fechará no momento
em que primeiro cliente entrar.

## Caminho A — Refactor pra shared infrastructure

### Mapeamento de renames

Todos no **frontend** + Controller (BD não muda):

| Domínio auto | Termo genérico shared | Onde está |
|--------------|------------------------|-----------|
| `plate` (UI) | `code` ou `reference` | `ProducaoOficina/Index.tsx` (1× type, 7× render), Controller `jobSheetToCard` |
| `vehicle` (UI) | `item` ou `subject` | `ProducaoOficina/Index.tsx` (1× type, 4× render), Controller `jobSheetToCard` |
| `brand` (UI) | `category` ou manter `brand` (já é genérico no BD) | idem |
| `km` (UI) | omitir / `meta` JSON | idem (hoje hardcoded `km: 0` no Controller — não tem mesmo) |
| `box` (UI) | `unit` ou `slot` | idem + filtros `BOXES = [B1..B4]` |
| `elevador` (UI) | `bay` ou `area` | idem + filtros `ELEVADORES = [E1..E2]` |
| `mecanico` (UI) | `executor` ou `assignee` | idem (já mapeia pra `User` genérico no Controller) |
| `mecanico_initials` | `executor_initials` | idem |
| `orcamento_total/pecas/status` | `quote_total/items/status` | idem |
| `aprovacao_pendente/aprovado` | `pending_approval/approved` | idem |

### Arquivos afetados

- **3 arquivos PHP**: `ProducaoOficinaController.php` (rename mock + jobSheetToCard + heurísticas BOXES/ELEVADORES) + 2 Pest tests sob `Modules/Repair/Tests/Feature/ProducaoOficinaTest.php`
- **2 arquivos TSX**: `ProducaoOficina/Index.tsx` + `ProducaoOficina/Index.charter.md`
- **0 migrations** — schema BD já é neutro
- **0 lang files** — todos os 15 idiomas já usam genérico-tech

### Esforço estimado (fator 10x IA-pair, ADR 0106)

- Rename frontend (Index.tsx + sub-components + types): **2h IA-pair**
- Rename Controller mock + heurística (BOXES → SLOTS, mecanico → executor): **1h**
- Update charter MWART + Pest snapshot fixtures: **1h**
- Refactor extras pra abrir abstração genérica reutilizável (props pra customizar labels por vertical: ex `Modules/ComunicacaoVisual` passa `vehicleLabel="Arte"`, `mecanicoLabel="Designer"`): **3h**
- **Total: ~7h IA-pair × 2x margem = 14h ≈ ~2 dias úteis Felipe**

### Riscos (rankeados)

- **A1 (médio):** Heurística `BOXES = [B1..B4]` + `ELEVADORES = [E1..E2]` é hard-coded UI. Genericizar requer abrir prop `slotConfig: SlotGroup[]` consumida do `business.repair_settings` (JSON já existe!). Esforço já incluído acima, mas ainda é refactor de UI não-trivial — risco de bug visual.
- **A2 (baixo):** PR #363 entregue há 1 dia (2026-05-09) — refazer cedo evita acumular código novo automotivo. Adiar = mais débito.
- **A3 (baixo):** Tests Pest GUARD existentes (PR #338) referenciam `serial_no` no fixture (ok, é genérico no BD). Não quebram.
- **A4 (médio):** Quando `Modules/OficinaAuto` for criado de fato (pendente sinal Martinho), ele vai querer `placa`/`mecanico` reais — solução: módulo vertical passa `labelOverrides` pro componente shared. Funciona, mas adiciona indireção.

### Pest tests necessários

- 1 snapshot test garantindo que props genéricas + override automotivo renderiza igual ao atual (preserva semantic visual)
- 2 unit tests (`Modules/ComunicacaoVisual` cenário + `Modules/OficinaAuto` cenário) usando `Modules/Repair` como shared infra com prop `labelOverrides`

### Backwards compat

- **Banco**: 0 mudança. Todas as 17 migrations continuam canon.
- **API/Connector**: `repair_job_sheet_id` em transactions já é genérico — sem mudança.
- **Cliente pagante**: 0 hoje (verificado no git log) — sem migration de UI legada.

## Caminho B — Rename pra Modules/OficinaAuto

### O que mudaria

- `git mv Modules/Repair Modules/OficinaAuto` (preservando histórico via skill `migrar-modulo`)
- Renomear namespace `Modules\Repair\*` → `Modules\OficinaAuto\*` em ~111 arquivos PHP
- Atualizar 53 arquivos fora do módulo que referenciam `Modules\Repair\` ou `repair_*` columns (parcialmente — colunas BD ficam, classes mudam)
- Atualizar `composer.json` (`autoload`/`extra.merge-plugin`), `bootstrap/cache/*`, `config/modules.php`
- Renomear rota base `/repair/*` → `/oficina-auto/*` (ou manter `/repair` por compat e ter alias)
- Adicionar entidade nova `Vehicle` (ADR 0121 §gap #1) com `placa unique` por biz
- Manter vocabulário UI automotivo (já está)

### Arquivos afetados

- **~111 arquivos PHP** (renames namespace) + **52 Blade** (paths internos `Modules/Repair/Resources/views/` → `Modules/OficinaAuto/Resources/views/`) + **6 Inertia tsx** + **53 arquivos fora do módulo** com referências
- **Total: ~222 arquivos** com edição mecânica

### Esforço estimado (fator 10x IA-pair)

- Rename namespace + paths via skill `migrar-modulo` (já documentada — ADR 0088): **3h IA-pair**
- Atualizar 53 referências externas (Connector + utils): **2h** (sed automatizável + revisão)
- Adicionar entidade `Vehicle` + migration + FK em JobSheet + form: **3h** (já estimado em gap-analysis)
- Atualizar 14+ docs (CLAUDE.md, what-oimpresso, charters, SPEC, ADR 0121 amendment): **2h**
- Pest tests cobrindo o rename + smoke novo módulo: **2h**
- Re-criar Modules/Repair shared (porque ComunicacaoVisual / Vestuario vão precisar de OS depois) **OU** decidir formalmente "cada vertical constrói seu Repair from scratch": **24h-160h** dependendo da escolha
- **Total mínimo (sem re-criar shared): ~12h × 2 = 24h ≈ 3 dias Felipe**
- **Total realista (re-criar shared pra ComunicacaoVisual usar): ~48h × 2 = 96h ≈ 12 dias Felipe**

### Riscos (rankeados)

- **B1 (alto):** **Re-explosão arquitetural quando ComunicacaoVisual virar piloto Q3/26** ([ADR 0121 §P7](../0121-oimpresso-modular-especializado-por-vertical.md)). ComunicacaoVisual vai querer kanban de OS de impressão — sem shared infra, ou (a) duplica 111 arquivos no novo módulo, ou (b) volta atrás e cria shared depois (caminho A com mais débito).
- **B2 (médio):** **53 arquivos fora do módulo** referenciam `Modules\Repair\` ou `repair_*`. Algumas referências em `app/Utils/TransactionUtil.php` e `Modules/Connector/Api/SellController.php` são **API contratos públicos** — quebra integração externa se rota `/repair/*` morrer.
- **B3 (médio):** 12 colunas `repair_*` em `transactions` ficam com nome esquisito (ex: `repair_security_pwd` num módulo OficinaAuto não faz sentido). Renomear colunas BD é **destrutivo, alto risco** ([ADR 0093 §multi-tenant Tier 0](../0093-multi-tenant-isolation-tier-0.md)) — provavelmente deixaria como dead-code legacy → mais débito.
- **B4 (baixo):** Idiomas `Resources/lang/` continuariam genéricos ("repair", "serial_no") num módulo chamado "OficinaAuto" — incoerência interna.
- **B5 (médio):** SoC brutal ([ADR 0094 §5](../0094-constituicao-v2-7-camadas-8-principios.md)) ferida — vertical OficinaAuto carrega lógica de status/checklist/job-sheet que tem zero a ver com auto.

### Como ComunicacaoVisual / Vestuario terão OS depois?

3 sub-caminhos:
- **B-i:** duplicar Modules/OficinaAuto pra Modules/ComunicacaoVisual (mais 111 arquivos × N módulos verticais — explode dívida)
- **B-ii:** criar Modules/OS shared do zero, deixar OficinaAuto consumir (= caminho A com 1 mês de atraso e dívida no meio)
- **B-iii:** ComunicacaoVisual / Vestuario nunca tem kanban OS (perda funcional vs concorrentes Mubisys/Calcgraf que têm)

## Caminho C — Manter ambos (status quo + débito documentado)

### O que muda

- 0 código
- Append em `gap-repair-vs-oficina-auto.md` documentando "vocabulário automotivo na UI ProducaoOficina é provisório, decisão final em ADR amendment quando vertical OficinaAuto for ativado"
- Adicionar comentário `// TECHDEBT-0121` em `ProducaoOficina/Index.tsx` linhas com `placa/vehicle/mecanico`

### Esforço

- **~30 minutos** (1 commit doc + 1 commit code-comments)

### Riscos

- **C1 (alto):** Cada PR novo no Modules/Repair pode acumular mais vocabulário automotivo (PR #363 já fez 38 ocorrências). Em 6 meses, custo de A será ~3-5x maior.
- **C2 (médio):** Confunde dev novo / Felipe / Maiara / Luiz — "isso é módulo auto ou genérico?". Skill `criar-modulo` precisa explicar exceção.
- **C3 (alto):** Quando ComunicacaoVisual virar piloto Q3/26, dev vai precisar fazer caminho A no meio do dev de outra coisa = piora timing.

## Recomendação técnica

### Caminho A se: planejamos múltiplos módulos verticais usando OS

✅ ADR 0121 §P8 já diz isso explicitamente. ✅ Schema BD já é neutro
(zero colunas auto). ✅ Custo é **2 dias Felipe** porque vocabulário
está concentrado em **2 arquivos** (Controller + Index.tsx) — não está
disseminado pelo módulo. ✅ Janela ótima: 0 clientes pagantes hoje =
sem migration de cliente.

### Caminho B se: aceitamos cada vertical ter seu Repair próprio

❌ Custo **6x maior** (12 dias se re-criar shared, ou 3 dias agora +
explosão depois). ❌ Quebra SoC brutal. ❌ B1 risco alto inevitável
em Q3/26. Justificável **só se Wagner abandonar Modules/ComunicacaoVisual**
e focar 100% OficinaAuto — contraditório com ADR 0121 §P7 (ComunicacaoVisual
é candidato Q3/26 com 6 saudáveis OfficeImpresso).

### Caminho C se: prioridade é não atrasar nenhum dev

⚠️ Aceita débito que dobra/triplica em 6 meses. Faz sentido APENAS
como ponte de 1-2 sprints até A ser executável (ex: se Felipe está
crítico em outra coisa). Não é solução final.

## Decisão sugerida

**🎯 Caminho A — refactor pra shared infrastructure.**

Justificativa quantitativa:
- **Custo agora**: ~14h ≈ 2 dias Felipe (fator 10x IA-pair confirmado em PRs MWART recentes)
- **Custo daqui a 6 meses se adiar**: ~40h+ (PRs novos acumulam vocabulário; quando ComunicacaoVisual entrar Q3/26, refactor terá que conviver com dev paralelo)
- **Custo do caminho B**: ~96h ≈ 12 dias se re-criar shared (necessário em Q3/26)
- **Janela única**: 0 clientes pagantes em Modules/Repair hoje. Migration zero.
- **Schema BD já está pronto** (zero colunas automotivas) — só UI vaza.
- **ADR 0121 §P8 default já é "shared"** — caminho A formaliza intenção.

**Esforço total: 2 dias Felipe IA-pair (≤300 linhas, 1 PR conventional, ADR 0094 commit-discipline). Top 1 risco: A1 (heurística BOXES/ELEVADORES hard-coded — abrir prop `slotConfig` consumida de `business.repair_settings.slots[]`).**

## Métricas pós-decisão

### Se caminho A escolhido (recomendado)

- **15 dias (sprint pós-decisão)**: PR refactor mergeado; `ProducaoOficina/Index.tsx` com props genéricas + `labelOverrides` opcional
- **30 dias**: Pest snapshot test garante 0 ocorrências de `placa|vehicle|mecanico|box|elevador` em `Modules/Repair/**` (CI gate); apenas em `Modules/OficinaAuto/Pages/*.tsx` (quando criado)
- **60 dias**: charter MWART `ProducaoOficina/Index.charter.md` reescrito sem vocabulário automotivo; entrada em CLAUDE.md "Modules/Repair = shared OS infra"
- **90 dias**: ADR amendment 0121 explicitando "P8 confirmado: Repair é shared, OficinaAuto consome via labelOverrides"

### Se caminho B escolhido

- **30 dias**: Modules/OficinaAuto SPEC.md formal + Vehicle entity migration
- **60 dias**: Modules/OS (shared) SPEC.md decidido (vai existir? não vai?)
- **90 dias**: ADR amendment 0121 explicitando vertical-by-vertical OS

### Se caminho C escolhido (não recomendado)

- **30 dias**: append em `gap-repair-vs-oficina-auto.md` com débito documentado + `TECHDEBT-0121` comments
- **180 dias**: revisitar — provável escalada pro caminho A com custo 3-4x maior

## ADR amendment proposto

Após Wagner aprovar (qualquer dos 3 caminhos), criar:

`memory/decisions/0122-repair-shared-vs-oficina-auto.md` (ou amendment 0121
direto via supersedes/amends) documentando a escolha.

---

**Conclusão executiva:** vocabulário automotivo em `Modules/Repair` é
**débito superficial, não estrutural**. Concentra-se em 2 arquivos
(Controller `ProducaoOficinaController` + Page `ProducaoOficina/Index.tsx`),
38+35 ocorrências, schema BD 0% contaminado. Refactor pra shared infra
custa ~2 dias Felipe e abre Modules/Repair pra ser consumido por
ComunicacaoVisual + Vestuario + OficinaAuto futuros (alinhado ADR 0121 §P8).
Janela ótima: 0 clientes pagantes hoje — depois fecha.

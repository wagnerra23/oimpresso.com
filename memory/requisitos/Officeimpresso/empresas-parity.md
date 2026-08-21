---
id: requisitos-officeimpresso-empresas-parity
titulo: "Paridade Blade↔React — Officeimpresso Empresas/Index (Empresas Licenciadas)"
tipo: parity
status: active
owner: W
criado: '2026-08-20'
related:
  - RUNBOOK-empresas.md
  - ../_DesignSystem/PARITY-TEMPLATE.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0264-governanca-executavel-trio-dominio-e2e'
---

# Paridade — Officeimpresso `Empresas/Index` (Onda 2, tela 5)

> Entregável da **F1/F2** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> Os itens de severidade **alta** viram teste de comportamento em **F4** — *presença deste
> arquivo não conta pra nada* ([PARITY-TEMPLATE](../_DesignSystem/PARITY-TEMPLATE.md) §Enforcement).

## Metadados

- **Blade legado:** `Modules/Officeimpresso/Resources/views/licenca_computador/businessall.blade.php` (140 ln)
- **Controller:** `LicencaComputadorController::businessall()`
- **Service:** `LicencaService::listarEmpresasComDesktop()` → `Business::where('is_officeimpresso', true)->get()`
- **Tela React alvo:** `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Empresas/Index.tsx`
- **Rota:** `GET /officeimpresso/businessall` — **intacta**; a React entra atrás da flag
  `useV2OfficeimpressoEmpresas` no mesmo path
- **Auditado em:** 2026-08-20 · **por:** Claude (leitura do Blade + controller + Service + model + `mysql-schema.sql`)

> **Coluna "Está no React?"** nasce `⏳` em tudo — a F3 é que constrói a tela e preenche item por
> item. Este mapa é o **contrato de entrada** da F3, não relatório de algo pronto.

## Cabeçalho e KPIs

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 1 | Título "Empresas Licenciadas" (`lang.businessall`) | ⏳ | `businessall.blade.php:10` | média | UC-EMPS-01 |
| 2 | Subtítulo "Todas as empresas com licença Office Impresso ativa" | ⏳ | `:11` | baixa | UC-EMPS-01 |
| 3 | KPI "Empresas" (total) + delta "com módulo Office Impresso" | ⏳ | `:16,22-29` | média | UC-EMPS-02 |
| 4 | KPI "Ativas" (`total - bloqueadas`) + delta "em operação" | ⏳ | `:18,32-39` | média | UC-EMPS-02 |
| 5 | KPI "Bloqueadas" (`where('officeimpresso_bloqueado', true)`) + delta "requer ação" | ⏳ | `:17,42-49` | média | UC-EMPS-02 |

## Tabela — 11 colunas

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 6 | Col **#** — `id`, mono | ⏳ | `:79` | baixa | UC-EMPS-03 |
| 7 | Col **Nome** — `name`, negrito | ⏳ | `:80` | **alta** | UC-EMPS-03 |
| 8 | Col **Razão Social** — `razao_social` | ⏳ | `:81` | média | UC-EMPS-03 |
| 9 | Col **CNPJ** — `cnpj`, mono | ⏳ | `:82` | **alta** | UC-EMPS-03 |
| 10 | Col **Versão Disp.** — `versao_disponivel ?: '—'` | ⏳ | `:83` | média | UC-EMPS-04 |
| 11 | Col **Versão Mín.** — `versao_minima` · **campo inexistente, renderiza sempre `—`** (ver D1) | ⏳ | `:84` | média | UC-EMPS-04 |
| 12 | Col **Máquinas** — `quantidade_maquinas` · **campo inexistente, sempre `—`** (ver D1) | ⏳ | `:85` | média | UC-EMPS-04 |
| 13 | Col **Banco** — `Str::limit(caminho_banco, 20)` · **campo inexistente em `business`, sempre vazio** (ver D1) | ⏳ | `:86` | baixa | UC-EMPS-04 |
| 14 | Col **Último Acesso** — `ultimo_acesso` · **campo inexistente, sempre `—`** (ver D1) | ⏳ | `:87` | média | UC-EMPS-04 |
| 15 | Col **Status** — pill "Bloqueada"/"Ativa" a partir de `officeimpresso_bloqueado` | ⏳ | `:88-94` | **alta** | UC-EMPS-05 |
| 16 | Col **Ações** — "Ver computadores" + "Ver log", ambos **ícone sem rótulo** | ⏳ | `:95-106` | **alta** | UC-EMPS-06 |
| 17 | "Ver computadores" → `viewLicencas({id})` (a tela #4) | ⏳ | `:96-100` | **alta** | UC-EMPS-06 |
| 18 | "Ver log desta empresa" → `/officeimpresso/licenca_log?business_id={id}` | ⏳ | `:101-105` | **alta** | UC-EMPS-07 |
| 19 | Ordenação default por `id` **desc** | ⏳ | `:128` (`order: [[0,'desc']]`) | média | UC-EMPS-08 |
| 20 | Paginação 25/página | ⏳ | `:127` | média | UC-EMPS-08 |
| 21 | Busca livre client-side sobre a tabela | ⏳ | `:130` (DataTables `search`) | média | UC-EMPS-08 |
| 22 | Vazio: `lang.no_records_found` = "Nenhum registro encontrado" | ⏳ | `:108-114` | média | UC-EMPS-09 |
| 23 | Vazio **com busca ativa**: "Nenhuma empresa encontrada" (`zeroRecords`) | ⏳ | `:134` | baixa | UC-EMPS-09 |

## Guardas de acesso e escopo

| # | Feature | React? | Evidência | Severidade | Defendido por |
|---|---|---|---|---|---|
| 24 | 403 pra autenticado sem `superadmin` nem `officeimpresso.access` | ⏳ | `authorizeAccess()` | **alta** | `LicencasAcessoPermissionTest:115` ✅ |
| 25 | 200 pra quem tem `officeimpresso.access` | ⏳ | idem | **alta** | `LicencasAcessoPermissionTest:130` ✅ |
| 26 | A lista traz **só** empresas com `is_officeimpresso = true` | ⏳ | `listarEmpresasComDesktop()` | **alta** | UC-EMPS-10 (F4) |
| 27 | A lista é **global por design** — sem recorte por `business_id` da sessão | ⏳ | idem | **alta** (Tier 0) | UC-EMPS-11 (F4) |
| 28 | **O model `Business` cru não chega ao cliente** — só o DTO de 6 campos | ⏳ | `Business` é o cadastro inteiro do ERP | **alta** (Tier 0) | UC-EMPS-12 (F4) |

> ⚠️ **Os itens 27 e 28 merecem leitura junta.** A visão global (27) é legítima e o título da tela a
> declara — a WR2 é a fornecedora dos desktops e o suporte precisa ver os clientes dela. Mas
> **justamente por ser global**, o item 28 pesa mais aqui do que nas telas irmãs: mandar o model cru
> publicaria o cadastro completo de **todas** as empresas no `data-page` do HTML. O item 28 não é
> paridade (o Blade não tem esse risco, porque imprime só as colunas que usa); é contramedida a um
> risco que **só a Inertia introduz**.

## Divergências deliberadas (não são regressão)

| # | O que muda | Por quê | Risco |
|---|---|---|---|
| **D1** | **As 4 colunas mortas (11, 12, 13, 14) — decisão [W] PENDENTE, precede a F2** | `versao_minima`, `quantidade_maquinas`, `caminho_banco` e `ultimo_acesso` **não existem** em `business` nem como accessor. Medido: as três primeiras aparecem **1 vez cada no repo inteiro — neste Blade**; `business.caminho_banco` dá **0** no `mysql-schema.sql` (a coluna com esse nome é de `licenca_computador`, outra tabela). As 4 células renderizam `—` para toda empresa, desde sempre. Os equivalentes reais existem na mesma tabela: `versao_obrigatoria`, `officeimpresso_numerodemaquinas`, `caminho_banco_servidor`, `dt_ultimo_acesso` | **Opções:** (a) não portar as 4 — a tela fica com 7 colunas, cabe a 1280, e nada se perde porque nada era mostrado; (b) portar apontando pros campos reais — **capacidade nova**, provavelmente o que sempre se quis. *Recomendação:* (b) para `dt_ultimo_acesso` e `versao_obrigatoria` (os dois que o suporte usa pra decidir atualização de cliente), (a) para os outros dois, que já têm dono melhor na ficha da tela #4 |
| D2 | DataTables/jQuery sai; `<DataTable>` do DS assume busca, ordenação e paginação | Camada 1 da Constituição UI v2 | Nenhum — os três já eram client-side |
| D3 | Os dois botões de ação ganham `aria-label` | Hoje são **ícone sem rótulo**, e o único texto é o `title` do `<a>` — invisível pra leitor de tela. Correção de acessibilidade | Nenhum |
| D4 | `StatusBadge kind="empresa"` — o **mesmo** `kind` da tela #4 | O sujeito é a empresa (`officeimpresso_bloqueado`), não a máquina. Reusar o `kind="licenca"` faria dois domínios compartilharem rótulo | Nenhum — se a #4 ainda não mergeou, esta tela cria o `kind` e a #4 reusa |
| D5 | Os KPIs passam a vir do backend | Hoje são `collect($business)->where(...)` dentro do Blade | Nenhum — o número não muda, a origem sim |
| D6 | Estilo inline (`style="margin-bottom: 14px"`) não atravessa | Camada 1 — espaçamento é token, não literal | Nenhum |
| D7 | As classes `oi-*` e o `design-system.blade.php` do módulo não atravessam | Módulo não tem DS próprio | Mapa `oi-*` → canon em [RUNBOOK-logs §4](RUNBOOK-logs.md) |

## O que NÃO muda, e é tentador mudar

- **O typo do path** de "Ver computadores" (`/officeimpresso/licenca_computado/licencas/{id}`, sem o
  "r") **fica**. É rota nomeada (`empresa.licencas`) e serve a tela #4; renomear é mudança de
  contrato, e as duas pontas precisam apontar pro mesmo lugar durante o dual-run.
- **A visão global** (item 27) fica. É o que o título da tela promete.

## Pendências que este mapa NÃO fecha

- Os UC citados (`UC-EMPS-*`) **ainda não existem** — nascem no `Index.casos.md` ao lado do `.tsx`,
  no PR da F3. A coluna "Defendido por" é o **contrato** de quais UC a F3 tem que criar.
- **11 de 28 itens são severidade alta:** 7, 9, 15, 16, 17, 18, 24, 25, 26, 27, 28. Contados com
  `grep -cE '^\| [0-9]+ \|.*\*\*alta\*\*' empresas-parity.md` (o `-c` sobre a linha inteira, porque
  27 e 28 escrevem `**alta** (Tier 0)`).
- **Já têm teste rodando 2**: 24, 25 — via `LicencasAcessoPermissionTest`, na allowlist da lane
  `officeimpresso-pest`. **Faltam 9** — são a US-OI-016 (F4).
- **Decisão [W] bloqueante:** D1 (as 4 colunas mortas) — precisa vir **antes da F2**, porque define
  o payload e o número de colunas, e o número de colunas é o que decide se a tela cabe a 1280.

---

**Última atualização:** 2026-08-20 — criado na F1 da Onda 2.

---
id: requisitos-officeimpresso-licencas-parity
titulo: "Paridade Blade↔React — Officeimpresso Licencas/Index (Computadores Cadastrados)"
tipo: parity
status: active
owner: W
criado: '2026-08-20'
related:
  - RUNBOOK-licencas.md
  - ../_DesignSystem/PARITY-TEMPLATE.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0264-governanca-executavel-trio-dominio-e2e'
---

# Paridade — Officeimpresso `Licencas/Index` (Onda 2, tela 3)

> Entregável da **F1/F2** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> Os itens de severidade **alta** viram teste de comportamento em **F4** — *presença deste
> arquivo não conta pra nada* ([PARITY-TEMPLATE](../_DesignSystem/PARITY-TEMPLATE.md) §Enforcement).

## Metadados

- **Blade legado:** `Modules/Officeimpresso/Resources/views/licenca_computador/index.blade.php` (137 ln)
- **Controller:** `Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::index()`
- **Service:** `LicencaService::listarPorEmpresa(int $businessId)`
- **Tela React alvo:** `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Licencas/Index.tsx`
- **Rota:** `GET /officeimpresso/licenca_computador` — **intacta**; a React entra atrás da flag
  `useV2OfficeimpressoLicencas` no mesmo path
- **Auditado em:** 2026-08-20 · **por:** Claude (leitura do Blade + controller + Service + entidade + schema)

> **Coluna "Está no React?"** nasce `⏳` em tudo — a F3 é que constrói a tela e preenche item por
> item, no PR dela. Marcar `✅` agora seria afirmar sobre código que não existe; este mapa é o
> **contrato de entrada** da F3, não relatório de algo pronto.

## Cabeçalho e KPIs

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 1 | Título "Computadores Cadastrados" (`lang.computadores_cadastrados`) | ⏳ | `index.blade.php:11` | média | UC-LIC-01 |
| 2 | Subtítulo "Todas as licenças de desktop cadastradas no sistema" — **a copy é falsa** (ver D3) | ⏳ | `index.blade.php:12` | baixa | UC-LIC-01 |
| 3 | KPI "Total" (contagem da coleção) + delta "máquinas registradas" | ⏳ | `index.blade.php:17,22-30` | média | UC-LIC-02 |
| 4 | KPI "Liberadas" (`total - bloqueadas`) + delta "em operação" | ⏳ | `index.blade.php:19,32-40` | média | UC-LIC-02 |
| 5 | KPI "Bloqueadas" (`where('bloqueado', true)`) + delta "requerem ação" | ⏳ | `index.blade.php:18,42-50` | média | UC-LIC-02 |

## Tabela — 8 colunas

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 6 | Col **#** — `id`, mono | ⏳ | `index.blade.php:80` | média | UC-LIC-03 |
| 7 | Col **HD** — `hd`, mono | ⏳ | `index.blade.php:81` | média | UC-LIC-03 |
| 8 | Col **Usuário Windows** — `user_win` em negrito; **sem fallback** quando nulo | ⏳ | `index.blade.php:82` | média | UC-LIC-03 |
| 9 | Col **Processador** — `Str::limit(.., 35)` **com `title` completo no hover** | ⏳ | `index.blade.php:83` | média | UC-LIC-04 |
| 10 | Col **Memória** — `memoria`, mono | ⏳ | `index.blade.php:84` | baixa | UC-LIC-03 |
| 11 | Col **Versão Executável** — `versao_exe`, mono | ⏳ | `index.blade.php:85` | média | UC-LIC-03 |
| 12 | Col **Bloqueado** — pill "Bloqueada"/"Liberada" a partir de `bloqueado` | ⏳ | `index.blade.php:86-92` | **alta** | UC-LIC-05 |
| 13 | Col **Ação** — botão toggle + link Log (detalhe abaixo) | ⏳ | `index.blade.php:93-104` | **alta** | UC-LIC-06 |
| 14 | Ordenação default por `id` **desc** | ⏳ | `index.blade.php:126` (`order: [[0,'desc']]`) | média | UC-LIC-07 |
| 15 | Paginação 25/página | ⏳ | `index.blade.php:125` | média | UC-LIC-07 |
| 16 | Busca livre client-side sobre a tabela | ⏳ | `index.blade.php:128` (DataTables `search`) | média | UC-LIC-07 |
| 17 | Vazio: "Nenhuma licença cadastrada." | ⏳ | `index.blade.php:106-112` | média | UC-LIC-08 |
| 18 | Vazio **com busca ativa**: "Nenhuma licença encontrada" (`zeroRecords`) | ⏳ | `index.blade.php:131` | baixa | UC-LIC-08 |

## Ações

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 19 | Botão toggle: rótulo "Bloquear"/"Desbloquear" conforme `bloqueado` | ⏳ | `index.blade.php:94-99` | **alta** | UC-LIC-06 |
| 20 | Tooltip do toggle: "Bloquear/Restaurar acesso desta máquina" | ⏳ | `index.blade.php:96` | baixa | — |
| 21 | Ícone do toggle alterna `lock`/`unlock` | ⏳ | `index.blade.php:97` | baixa | — |
| 22 | Link **Log** → `/officeimpresso/licenca_log?licenca_id={id}` (deep-link pra tela da Onda 1) | ⏳ | `index.blade.php:100-103` | **alta** | UC-LIC-09 |
| 23 | Botão **Cadastrar** → `licenca_computador.create` (tela #6, **fica Blade** nesta onda) | ⏳ | `index.blade.php:58-60` | média | UC-LIC-10 |
| 24 | Após toggle, flash de sucesso/erro (`redirect()->back()->with(...)`) | ⏳ | `LicencaComputadorController::toggleBlock()` | média | UC-LIC-06 |

## Guardas de acesso e escopo

| # | Feature | React? | Evidência | Severidade | Defendido por |
|---|---|---|---|---|---|
| 25 | 403 pra autenticado sem `superadmin` nem `officeimpresso.access` | ⏳ | `authorizeAccess()` | **alta** | `LicencasAcessoPermissionTest:115` ✅ |
| 26 | 200 pra quem tem `officeimpresso.access` (suporte, sem superadmin) | ⏳ | idem | **alta** | `LicencasAcessoPermissionTest:130` ✅ |
| 27 | A lista mostra **só** máquinas do `business_id` da sessão — sem exceção por permissão | ⏳ | `index()` → `listarPorEmpresa(session('user.business_id'))` | **alta** (Tier 0) | UC-LIC-11 (F4) |
| 28 | Toggle exige `officeimpresso.licencas.gerenciar` — `access` sozinho dá 403 | ⏳ | `authorizeGerenciar()` | **alta** | `LicencasAcessoPermissionTest:151` ✅ |
| 29 | **Nenhum campo sensível do model chega ao cliente** (`senha`, `contra_senha`, `serial`, `token`) | ⏳ | `Licenca_Computador` sem `$hidden`; `$fillable` lista os 4 | **alta** (Tier 0) | UC-LIC-12 (F4) |

> ⚠️ **O item 29 não existe no Blade — é criado pela migração.** Não é paridade; é a
> contramedida a um risco que **só a Inertia introduz** (a prop inteira é serializada no `data-page`).
> Está neste mapa porque a F4 precisa defendê-lo com assert negativo. Ver
> [RUNBOOK-licencas §10.1](RUNBOOK-licencas.md).

## Divergências deliberadas (não são regressão)

| # | O que muda | Por quê | Risco |
|---|---|---|---|
| D1 | `toggle-block` sai de **GET** pra **POST**, e **ganha confirmação** | É `Route::get` que muda estado, e nesta tela **nem `confirm()` tem** (a `Logs/Index` tem) — qualquer prefetch, crawler ou `<img src>` dispara. Em React vira POST com CSRF + diálogo do DS | Durante o dual-run as duas rotas coexistem; a GET só sai no F5, junto com o Blade |
| D2 | Rótulo do pill unificado com `Empresa/Show` | O **mesmo** campo `bloqueado` é rotulado "Liberada" aqui e "Ativa" em `computadores.blade.php:108`. Um `StatusBadge kind="licenca"` só não pode falar duas línguas | Escolher qual rótulo vence é **decisão [W]**; até lá o `kind` nasce com o par "Liberada/Bloqueada" desta tela, que é a lista canônica de máquinas |
| D3 | Corrigir o subtítulo (item 2) | Ele diz "no sistema" e a query filtra pelo business da sessão. A copy afirma um escopo que o código não entrega | **Decisão [W]** — corrigir a copy ou (fora de escopo) mudar a query. Enquanto não decidir, a F3 porta o texto **como está** e o item 2 fica `baixa` |
| D4 | Botão de bloqueio **não renderiza** sem `licencas.gerenciar` | Hoje o Blade mostra o botão pra quem só tem `access`, e o clique leva a um 403. Esconder ação que o usuário não pode executar é o canon do shell | Nenhum — a guarda do servidor continua sendo a autoridade (item 28); a UI só para de mentir |
| D5 | DataTables/jQuery sai; `<DataTable>` do DS assume busca, ordenação e paginação | Camada 1 da Constituição UI v2 | Nenhum — os três já eram client-side; nada no servidor muda |
| D6 | As classes `oi-*` e o `design-system.blade.php` do módulo não atravessam | Módulo não tem DS próprio | Mapa `oi-*` → canon em [RUNBOOK-logs §4](RUNBOOK-logs.md) |

## Pendências que este mapa NÃO fecha

- Os UC citados (`UC-LIC-*`) **ainda não existem** — nascem no `Index.casos.md` ao lado do `.tsx`, no
  PR da F3. A coluna "Defendido por" é o **contrato** de quais UC a F3 tem que criar.
- **9 de 29 itens são severidade alta:** 12, 13, 19, 22, 25, 26, 27, 28, 29.
  Contados com `grep -cE '^\| [0-9]+ \|.*\*\*alta\*\*' licencas-parity.md` — o `-c` sobre a linha
  inteira, porque os itens 27 e 29 escrevem `**alta** (Tier 0)` e um padrão fechado em `| **alta** |`
  perderia os dois.
- **Já têm teste rodando 3**: 25, 26, 28 — via `LicencasAcessoPermissionTest`, na allowlist da lane
  `officeimpresso-pest`. **Faltam 6**: 12, 13, 19, 22, 27, 29 — são a US-OI-010 (F4).
- **Decisões [W] em aberto**, todas registradas acima e nenhuma bloqueante pra F2: D2 (qual rótulo
  vence), D3 (corrigir a copy do subtítulo).

---

**Última atualização:** 2026-08-20 — criado na F1 da Onda 2.

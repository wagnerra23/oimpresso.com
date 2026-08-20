---
id: requisitos-officeimpresso-logs-parity
titulo: "Paridade Blade↔React — Officeimpresso telas Logs (Máquinas Cadastradas + Timeline)"
tipo: parity
status: active
owner: W
criado: '2026-08-19'
related:
  - RUNBOOK-logs.md
  - ../_DesignSystem/PARITY-TEMPLATE.md
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0320-programa-ondas-regua-correcao'
  - '0264-governanca-executavel-trio-dominio-e2e'
---

# Paridade — Officeimpresso `Logs/` (Onda 1)

> Entregável **F2** ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> Os itens de severidade **alta** viram teste de comportamento em **F4** — *presença deste
> arquivo não conta pra nada* ([PARITY-TEMPLATE](../_DesignSystem/PARITY-TEMPLATE.md) §Enforcement).

## Metadados

- **Blade legado:** `Modules/Officeimpresso/Resources/views/licenca_log/index.blade.php` (290 ln) ·
  `.../timeline.blade.php` (87 ln)
- **Controller:** `Modules\Officeimpresso\Http\Controllers\LicencaLogController::index()` / `::timeline()`
- **Tela React alvo:** `Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/{Index,Timeline}.tsx`
- **Rotas:** `GET /officeimpresso/licenca_log` · `GET /officeimpresso/licenca_log/timeline/{licenca_id}`
  — **as duas ficam intactas**; a React entra atrás da flag `useV2Logs` no mesmo path.
- **Auditado em:** 2026-08-19 · **por:** Claude (leitura dos 2 Blades + controller + entidade)

> **Coluna "Está no React?" — legenda estendida.** O template prevê ✅/🟡/❌ lendo os dois lados.
> Aqui o lado React **ainda não existe** (esta é a F2, a F3 é que constrói), então a coluna nasce
> `⏳` em tudo e é **a F3 que a preenche**, item por item, no PR de cada tela. Marcar `✅` agora
> seria afirmar sobre código que não escrevi — o mapa é o CONTRATO de entrada da F3, não um
> relatório de algo pronto.

## Mapa — tela 1: `Logs/Index` (Máquinas Cadastradas)

### Cabeçalho e KPIs

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 1 | Título "Máquinas Cadastradas" | ⏳ | `index.blade.php:14` | média | UC-LOGS-01 |
| 2 | Subtítulo explicando que `processa-dados-cliente` popula o cadastro | ⏳ | `index.blade.php:15-19` | média | UC-LOGS-01 |
| 3 | KPI "Máquinas cadastradas" (`total_maquinas`) | ⏳ | `index.blade.php:28-30` | média | `LogsBaselineTest` (4 KPIs) |
| 4 | KPI "Máquinas bloqueadas" (`maquinas_bloqueadas`) | ⏳ | `index.blade.php:38-40` | média | `LogsBaselineTest` |
| 5 | KPI "Empresas bloqueadas" (`empresas_bloqueadas`) | ⏳ | `index.blade.php:48-50` | média | `LogsBaselineTest` |
| 6 | KPI "Acessos 24h" (`chamadas_24h`) | ⏳ | `index.blade.php:58-60` | média | `LogsBaselineTest` |

### Filtros — o núcleo funcional

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 7 | Busca livre `q` (nome/CNPJ/razão da empresa · hd/user_win/hostname/ip da máquina) | ⏳ | `index.blade.php:74-77` · controller `index()` | **alta** | UC-LOGS-05 |
| 8 | Filtro `estado_atual` = `ativa` \| `bloqueada` \| todos | ⏳ | `index.blade.php:80-85` | **alta** | UC-LOGS-06 |
| 9 | Filtro `hd` (via link na célula) — "todas as empresas com este HD" | ⏳ | `index.blade.php:171-174` | **alta** | UC-LOGS-03 |
| 10 | Filtro `licenca_id` (via link na célula Máquina) | ⏳ | `index.blade.php:161-164` | **alta** | UC-LOGS-04 |
| 11 | Filtro `business_id` (via link na célula Empresa) | ⏳ | `index.blade.php:141-145` | **alta** | UC-LOGS-12 |
| 12 | Botão "Aplicar" | ⏳ | `index.blade.php:88` | média | UC-LOGS-02 |
| 13 | Botão "Limpar" — só aparece com algum filtro ativo | ⏳ | `index.blade.php:89-91` | média | UC-LOGS-02 |
| 14 | Chip removível "Filtrado por empresa #N" | ⏳ | `index.blade.php:97-102` | média | UC-LOGS-02 |
| 15 | Chip removível "Filtrado por equipamento #N" | ⏳ | `index.blade.php:103-108` | média | UC-LOGS-02 |
| 16 | Chip removível "Filtrado por HD X" | ⏳ | `index.blade.php:109-114` | média | UC-LOGS-02 |
| 17 | Os filtros **compõem** (limpar um preserva os outros via `array_filter`) | ⏳ | `index.blade.php:100,106,112` | **alta** | UC-LOGS-13 |

### Tabela — 10 colunas

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 18 | Col **Empresa** — nome em link que filtra; `—` se sem business | ⏳ | `index.blade.php:139-151` | média | UC-LOGS-04 |
| 19 | Col **Location / CNPJ** — nome da location + CNPJ; `—` se sem log | ⏳ | `index.blade.php:152-160` | média | UC-LOGS-04 |
| 20 | Col **Máquina** — `user_win ?: hostname ?: "(sem hostname)"`, em link | ⏳ | `index.blade.php:161-167` | **alta** | UC-LOGS-04 |
| 21 | Col **HD** — em link; `—` se vazio | ⏳ | `index.blade.php:168-178` | média | UC-LOGS-04 |
| 22 | Col **Versão** — `versao_exe` + `/ versao_banco` (banco em tom secundário) | ⏳ | `index.blade.php:179-186` | média | UC-LOGS-04 |
| 23 | Col **IP** — `last_ip ?: ip_interno ?: —` | ⏳ | `index.blade.php:187` | média | UC-LOGS-04 |
| 24 | Col **Último Login** — data do log; senão `dt_ultimo_acesso` **com o rótulo `(cadastro)`**; senão "nunca" | ⏳ | `index.blade.php:188-197` | **alta** | UC-LOGS-05 |
| 25 | Col **Estado no Último Login** — tri-estado: `null`→`—` · `true`→Bloqueada · `false`→Liberada | ⏳ | `index.blade.php:198-206` | **alta** | `LogsBaselineTest` (was_blocked) + UC-LOGS-06 |
| 26 | Col **Estado Atual** — precedência empresa > máquina > ativa | ⏳ | `index.blade.php:207-215` | **alta** | UC-LOGS-06 |
| 27 | Col **Ações** — 3 variantes mutuamente exclusivas (ver bloco abaixo) | ⏳ | `index.blade.php:216-241` | **alta** | UC-LOGS-07 |
| 28 | Ordenação default por Último Login desc (server-side `effective_ts`) | ⏳ | controller `sortByDesc` + `index.blade.php:271` | média | UC-LOGS-05 |
| 29 | Coluna Ações não é ordenável | ⏳ | `index.blade.php:272` | baixa | — |
| 30 | Paginação 25/página | ⏳ | `index.blade.php:270` | média | UC-LOGS-08 |
| 31 | Contador "Timeline de Máquinas (N)" no header do card | ⏳ | `index.blade.php:119` | baixa | — |

### Ações de bloqueio (col. 27, detalhada)

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 32 | Empresa bloqueada → "Desbloq. empresa" (`business.bloqueado`) + confirmação | ⏳ | `index.blade.php:217-224` | **alta** | UC-LOGS-07 |
| 33 | Máquina bloqueada → "Desbloq. máquina" (`licenca_computador.toggleBlock`) + confirmação | ⏳ | `index.blade.php:225-231` | **alta** | UC-LOGS-07 |
| 34 | Nem uma nem outra → "Bloq. máquina" (mesmo toggle) + confirmação | ⏳ | `index.blade.php:232-239` | **alta** | UC-LOGS-07 |
| 35 | O texto da confirmação nomeia a empresa/máquina alvo | ⏳ | `index.blade.php:220,228,235` | média | UC-LOGS-07 |

### Estados vazios

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 36 | Vazio **com** filtro: "Nenhuma máquina encontrada com os filtros aplicados." | ⏳ | `index.blade.php:246-247` | média | UC-LOGS-09 |
| 37 | Vazio **sem** filtro: explica que a rotina `processa-dados-cliente` popula quando o Delphi envia CNPJ + HD | ⏳ | `index.blade.php:248-258` | média | UC-LOGS-09 |

## Mapa — tela 2: `Logs/Timeline`

| # | Feature do Blade | React? | Evidência (Blade) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 38 | Título "Timeline — `user_win ?: hostname ?: 'sem hostname'`" | ⏳ | `timeline.blade.php:14` | média | UC-TL-01 |
| 39 | Subtítulo: empresa · HD · IP interno | ⏳ | `timeline.blade.php:16-19` | média | UC-TL-01 |
| 40 | Botão "Voltar" pra lista | ⏳ | `timeline.blade.php:23` | média | UC-TL-01 |
| 41 | Selo de estado (empresa bloqueada / máquina bloqueada / ativa) | ⏳ | `timeline.blade.php:24-30` | **alta** | UC-TL-02 (dado provado; precedência no .tsx sem e2e) |
| 42 | Header "Últimos 200 acessos a processa-dados-cliente (N)" | ⏳ | `timeline.blade.php:35-37` | média | UC-TL-01 |
| 43 | Limite de 200 registros, ordem desc por `created_at` | ⏳ | controller `timeline()` | média | `LogsBaselineTest` (só os acessos dela) |
| 44 | Só logs `source=delphi_middleware` **e** endpoint `processa-dados-cliente` | ⏳ | controller `timeline()` | **alta** | UC-TL-08 |
| 45 | Col **Data/Hora** `d/m/Y H:i:s` | ⏳ | `timeline.blade.php:56` | média | UC-TL-03 |
| 46 | Col **Status HTTP** — verde <400, vermelho >=400 | ⏳ | `timeline.blade.php:57-64` | média | UC-TL-03 |
| 47 | Col **Estado no Login** — `was_blocked` do metadata | ⏳ | `timeline.blade.php:65-72` | **alta** | UC-TL-09 |
| 48 | Col **IP** — `—` se vazio | ⏳ | `timeline.blade.php:73` | baixa | — |
| 49 | Col **Duração** — `Nms`; `—` se vazio | ⏳ | `timeline.blade.php:74` | baixa | — |
| 50 | Vazio: "Nenhum acesso registrado para esta máquina." | ⏳ | `timeline.blade.php:78-81` | média | UC-TL-04 |
| 51 | 404 se `licenca_id` não existe | ⏳ | controller `timeline()` `abort(404)` | **alta** | UC-TL-06 |

## Guardas de acesso (as duas telas)

| # | Feature | React? | Evidência | Severidade | Defendido por |
|---|---|---|---|---|---|
| 52 | 403 pra autenticado sem `superadmin` nem `officeimpresso.access` | ⏳ | `LicencaLogController::authorizeAccess()` | **alta** | UC-LOGS-01 · UC-TL-05 |
| 53 | ~~Quem NÃO tem `podeVerTodasEmpresas()` fica preso ao próprio `business_id`~~ — **caminho inalcançável** (a guarda 403 roda antes; ver UC-LOGS-11). O contrato real é o inverso: com a permissão, a visão É cross-empresa | ⏳ | `LicencaLogController::index()` | **alta** (Tier 0) | UC-LOGS-11 |
| 54 | Quem tem a permissão vê **todas** as empresas (cross-tenant por design) | ⏳ | `podeVerTodasEmpresas()` | **alta** | UC-LOGS-11 |

## Divergências deliberadas (não são regressão)

| # | O que muda | Por quê | Risco |
|---|---|---|---|
| D1 | `toggle-block` e `business.bloqueado` saem de **GET** pra **POST** | São `Route::get` que **mudam estado**, protegidos só por `confirm()` no browser — qualquer prefetch, crawler ou `<img src>` dispara. Em React vira POST com CSRF | Durante o dual-run as duas rotas coexistem; a GET só sai no F5, junto com o Blade |
| D2 | `confirm()` nativo vira diálogo do DS | Consistência de shell; o `confirm()` do browser não é estilizável nem acessível | Nenhum — o texto (item 35) é preservado |
| D3 | DataTables/jQuery (ordenação e paginação client-side) sai | O `<DataTable>` do DS já faz os dois | A ordenação default já é **server-side** (`sortByDesc(effective_ts)`); o `data-order` do Blade era só pro jQuery |
| D4 | As classes `oi-*` e o `design-system.blade.php` próprio do módulo não atravessam | Camada 1 da Constituição UI v2 — módulo não tem DS próprio | Mapa `oi-*` → canon está no [RUNBOOK-logs.md §4](RUNBOOK-logs.md) |

## Pendências que este mapa NÃO fecha

- Os UC citados (`UC-LOGS-*`, `UC-TL-*`) **ainda não existem** — nascem nos `.casos.md` ao lado de
  cada `.tsx`, no PR da respectiva tela (F3). A coluna "Defendido por" é o **contrato** de quais
  UC a F3 tem que criar; hoje só os marcados `LogsBaselineTest` têm teste rodando.
- **21 de 54 itens são severidade alta** — contados com
  `grep -cE '^\| [0-9]+ \|.*\*\*alta\*\*' logs-parity.md` (o `-c` sobre a linha inteira, porque o
  item 53 escreve `**alta** (Tier 0)` e um padrão fechado em `| **alta** |` perde ele — devolve 20).
  São: 7, 8, 9, 10, 11, 17, 20, 24, 25, 26, 27, 32, 33, 34, 41, 44, 47, 51, 52, 53, 54.
- **Todos os 21 precisam de teste de comportamento pra F4 fechar** (US-OI-006). Já têm teste
  rodando **10**: 7, 8, 9, 10, 25, 44, 47, 51, 52, 54 — via `LogsBaselineTest` e
  `LicencasAcessoPermissionTest`, ambos na allowlist da lane `officeimpresso-pest`.
  **Faltam 11**: 11, 17, 20, 24, 26, 27, 32, 33, 34, 41, 53.

---

**Última atualização:** 2026-08-19 — criado na F2 da Onda 1.

---
id: requisitos-oficina-auto-changelog
---

# Changelog — Modules/OficinaAuto

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - 2026-05-20 — Wave 7-C/D/E tríade MVP Martinho LIVE (FSM screen 65→~80)

Fecha os 3 gaps urgentes da auditoria estado-da-arte FSM screen ([memory/sessions/2026-05-20-arte-tela-fsm-workflow.md](../../sessions/2026-05-20-arte-tela-fsm-workflow.md)) — pré-ativação Martinho. Nota agregada na grade 15 dimensões: **65/100 → ~80/100**.

### Added

- **Wave 7-C — Timeline FSM auditável** ([PR #1195](https://github.com/wagnerra23/oimpresso.com/pull/1195) `84a585951`):
  - `ServiceOrderFsmActionController::history()` (+90 linhas) — método novo no controller existente
  - Rota `GET /oficina-auto/service-orders/{order}/history` (named `oficinaauto.service_orders.history`)
  - `ServiceOrderTimeline.tsx` — port de `SaleTimeline.tsx` (US-SELL-035 LIVE)
  - Wire no `ServiceOrderSheet.tsx` substituindo placeholder "Em breve..."
  - 4 Pest specs (`ServiceOrderHistoryControllerTest`) — happy path, startPipeline action_id=NULL, discriminação process_key cacamba_*, multi-tenant Tier 0
- **Wave 7-D — Chips por stage Linear-style com contador** ([PR #1203](https://github.com/wagnerra23/oimpresso.com/pull/1203) `627c008e2`):
  - `ServiceOrderController::index()` aceita `?stage=X` (filtra `current_stage_id` via stage.key)
  - `buildStagesPayload` (Inertia::defer) — 1 query bulk `GROUP BY` counts por stage
  - `STAGE_CHIP_COLOR_MAP` Tailwind (12 cores) + UI chips no Index.tsx
  - 4 Pest specs (`ServiceOrderIndexStageFilterTest`) — filter key, multi-tenant, counts bulk, sem filtro
- **Wave 7-E — Mini-grafo horizontal stages no drawer** ([PR #1205](https://github.com/wagnerra23/oimpresso.com/pull/1205) `5f924b6ff`):
  - `actions()` endpoint ganha campo `stages_pipeline` (lista ordenada por `sort_order`)
  - `ServiceOrderStagePipeline.tsx` (+227 linhas) — componente novo: bullets conectados + check passados + ring atual + variantes laterais
  - Heurística separa pipeline principal vs laterais (manutencao/cancelada ficam abaixo)
  - 4 Pest specs (`ServiceOrderStagePipelineTest`) — ordem sort_order, is_current único, multi-tenant Tier 0, OS sem stage
- **[RUNBOOK-fsm-pipeline.md](RUNBOOK-fsm-pipeline.md)** — doc canon novo: arquitetura, 2 processos cacamba_*, polimorfismo `sale_stage_history`, endpoints REST, UI canon 3 componentes, pegadinhas CI/deploy

### Fixed

- **Hotfix tela branca Index.tsx** ([PR #1197](https://github.com/wagnerra23/oimpresso.com/pull/1197) `ac84ac8d1`): `kpis: Kpis` (não-opcional) crashava com `Inertia::defer`. Fix: `kpis?: Kpis` + `EMPTY_KPIS` default no destructuring. Pattern catalogado em [skill inertia-defer-default §Antipattern](../../../.claude/skills/inertia-defer-default/SKILL.md).
- **Hotfix authorize() trait missing** ([PR #1211](https://github.com/wagnerra23/oimpresso.com/pull/1211) `21676447d`): `ServiceOrderController` + `VehicleController` extendiam `Illuminate\Routing\Controller` (base raw sem trait `AuthorizesRequests`). Drawer JSON Wave 7-A começou a hitar `show()` → `Method authorize does not exist` → HTTP 500 em todas 5 OS biz=1. Diagnose via [PR #1209](https://github.com/wagnerra23/oimpresso.com/pull/1209) try-catch trace JSON. Fix: `extends App\Http\Controllers\Controller` (projeto canon com traits). Bug latente catalogado em [memory/reference/deploy-recovery-patterns.md §6](../../reference/deploy-recovery-patterns.md#6-bug-latente-controller-authorize--illuminateroutingcontroller-vs-apphttpcontrollerscontroller).

### Operational lessons (canon — todos módulos)

- **`quick-sync.yml` NÃO regenera composer autoload** — PR #1195 (método novo) deu 500 em todo módulo até `Deploy to Hostinger` rodar `composer install`. Matriz de decisão quick-sync vs deploy em [memory/reference/deploy-recovery-patterns.md §2.2](../../reference/deploy-recovery-patterns.md#22-quick-sync-vs-deploy-quando-precisa-composer-install-em-prod).
- **Hostname canon** ([PR #1196](https://github.com/wagnerra23/oimpresso.com/pull/1196) `50d9695ab`): `oimpresso.com` (não `oi.wr2.com.br` legado). Doc canon em [memory/reference/sandbox-hostnames.md](../../reference/sandbox-hostnames.md).

### Preserved (Tier 0 IRREVOGÁVEL)

- Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — todos endpoints history/actions/stages_pipeline filtram `business_id` via global scope + filter explicit em SaleProcess
- FSM Orchestrator global ([app/Domain/Fsm/](../../../app/Domain/Fsm/)) — ServiceOrder reusa `sale_stage_history` polimorficamente via `transaction_id` carrying `$order->id`
- Pest skip SQLite ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md)) — todos novos tests rodam só MySQL CI

## [Unreleased] - 2026-05-16 — Wave 23 saturação bucket vertical_client_facing

### Added

- **CAPTERRA-FICHA.md** canônica — concorrentes (Mecânico, Auto Manager, Lokoz, Bling Oficina, GP Soft Auto), top 5 gaps P0 (US-OFICINA-006/008/009/010/011), score V1-V6 W22→W23 (63→≥85).
- **Wave23OficinaAutoSaturationTest.php** — Pest saturação V1/V4/V5/V6 com 11 assertions cobrindo Vehicle + ServiceOrder + FormRequests Store/Update, LGPD PII fields tracked (plate/chassis/renavam), MATRIZ-ROI presença, governance.bucket=vertical_client_facing + FSM canon `service_order`.
- **module.json governance.bucket=vertical_client_facing** ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md)) com `scoped_score_target: 85`, `wave: 23`, `wave_23_saturation: true`.

### Changed

- Score Capterra scoped (rubrica `vertical_client_facing.yaml`): 63/100 → ≥85/100 estimado.
- V1 Pest E2E: +6 (complementa WhatsAppAprovacaoPinTest + E2EJourneyMartinhoBiz1Test DB-based existentes).
- V5 Docs canon: +10 (CAPTERRA-FICHA + CHANGELOG W23 — BRIEFING/ROADMAP/SPEC já existiam, MATRIZ-ROI asserted).
- V6 Capterra ROI Top 5: +3 (FICHA fechando gap W22).

### Preserved (Tier 0 IRREVOGÁVEL)

- FSM canon ADR 0143 `service_order` pipeline complexa (orçamento→aprovação→produção→entrega).
- Vargas + Martinho biz reais NUNCA em test (ADR 0101 — biz=99 sempre).
- PII plate/chassis/renavam protegidos via PiiRedactor.
- ServiceOrderController Inertia::render eager (rollback PR #963 Wave L/W7 preservado — defer quebrava initial render Pages).
- Modules/OficinaAuto lifecycle `V0 em construção` mantido (ADR 0137 — qualificada por sinal).

---

## Implementação (histórico movido de `Modules/OficinaAuto/CHANGELOG.md`)

> Movido em 2026-08-10. Os dois changelogs registravam eventos DIFERENTES — acima as
> decisões/requisitos, aqui o que foi de fato mergeado. Medido antes de fundir:
> sobreposição de datas entre os dois era 0-2 de 2-7, logo nenhum era cópia do outro
> e escolher um lado perderia registro. Conteúdo preservado na íntegra.

# OficinaAuto — Changelog

## [2026-06-09] Erradicação de "locação" — reparo é o único domínio (ADR 0265)

### 🪦 Lápide — order_type=locacao é resíduo, não fluxo (append, não reescreve história · L-22)
- Veredito de Wagner (soberano do domínio) 2026-06-09: locação de caçamba **não é processo que ele usa** — é alucinação herdada do legado WR Sistemas. A [ADR 0265](../../decisions/0265-oficina-reparo-erradica-locacao.md) (errata que **fecha o resíduo** que a [ADR 0194](../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) deixou) decide: **Oficina = reparo, ponto.** `order_type ∈ {manutencao, mecanica}`. "Caçambas" sobrevive **só como nome comercial** do cliente Martinho.
- **Anti-retorno (D-4) LANDADO + GATED:** linha em [`memory/proibicoes.md`](../../proibicoes.md) + gate `dominio:check` ([ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-4) que **falha o CI** se `locacao` reaparecer num enum. A alucinação que nenhuma spec de tela pegava agora falha mecanicamente.
- **Erradicação de schema/código LANDADA (CI-Pest verificado):**
  - **Enum** `order_type` → `{manutencao, mecanica}` via migration `2026_06_09_000001` (data-fix `locacao`→`manutencao` ANTES de estreitar · idempotente · MySQL-guard · SHOW COLUMNS). **Prova de máquina:** `dominio:check` divergência `order_type:locacao` caiu **1→0**.
  - **Importer** `normalizeOrderType` — removido o ramo `locacao` (cai no default `manutencao`).
  - **KPI** `locacao_ativa` removido do `ServiceOrderSummaryService` (sem consumidor) + Wave25/26 ajustados.
  - **Menu** `Caçambas`→`Veículos` · comentário de rota stale reescrito pra reparo.
  - **Validação** `StoreServiceOrderRequest`: `order_type in:manutencao,mecanica` (não aceita mais `locacao` — bate com o enum, evita validar OK + falhar no insert MySQL).
- **Resíduo remanescente (rastreado · dead-code/test-data, NÃO quebra prod):**
  - `ServiceOrderController` (filtro `locacao_ativa` + `where('order_type','locacao')`) e `AprovacaoOsController` (ramo `order_type==='locacao'`) viraram **código morto** (queries retornam 0 linhas pós-erradicação). Limpeza acoplada aos testes de controller (`ServiceOrderIndexStageFilterTest`) = follow-up com Pest.
  - Fixtures de teste FSM-roteadas (`FsmTransitionTest` etc.) ainda criam `order_type='locacao'` como dado (passam em SQLite=TEXT). Trocar exige Pest por-teste (acoplamento FSM) = follow-up.
  - **Prova de máquina atual:** `dominio:check` (schema) = **0** divergência. Schema + caminho de escrita (validação/importer/KPI) = zero locacao.
- **Preservado (charter v4 PR #2417):** FSM keys `disponivel/locada` + componentes `Cacamba*` = dívida F3 em ADR própria — **não tocados**. "Caçambas" como nome do cliente Martinho = ok.

## [W28 — 2026-06-03] Importer Firebird fino + reconciliação de domínio (ADR 0194)

### G4 Importer Firebird Martinho — mapping fino (sai do esqueleto W27)
- `ImportFirebirdMartinhoCommand` completo: mapping fino ORDEM_SERVICO + ORDEM_ITENS → ServiceOrder + ServiceOrderItem.
  - `vehicle_type` default **`cacamba` → `caminhao`** (`cacamba` nem era valor válido do enum `vehicles.vehicle_type`). Normalização via whitelist real + sinônimos de basculante → `caminhao`.
  - Status legacy (WR Sistemas, PT livre) → FSM `manutencao` (aberta/em_servico/concluida/cancelada); histórico fechado default `concluida`.
  - `order_type` normalizado {locacao|manutencao|mecanica}; legado default `manutencao` (migration `2026_06_02_000001`: "novo processo mecanica não mexe no legado").
  - Tipo de item → `peca|mao_obra|servico_terceiro`.
  - **Dry-run virou o padrão**: grava no DB só com `--commit` (`--dry-run` vence por segurança). Idempotência `FB_LEGACY_ID` preservada.
- `scripts/firebird/export-martinho-os.py` — export local (Windows + firebird-driver) com `--dump-schema` pra mapear os nomes reais do FDB.

### 🪦 Lápide de domínio (ADR 0194 · 2026-05-26 — append, não reescreve história · L-22)
- Entradas anteriores deste changelog citam **"Martinho Caçambas"** / **"Journey Martinho Caçambas"**: "Caçambas" é o **nome comercial** da empresa, preservado. O **domínio operacional**, porém, foi reclassificado de "locação de caçamba container" → **mecânica pesada de caminhão basculante (CNAE 4520-0/01)** pela [ADR 0194](../../decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md). Onde o texto legado sugerir "locação de caçamba" como *fluxo de negócio*, leia "mecânica de caminhão".

## [Wave 27 POLISH — 2026-05-17] Polish final 77-88 → ≥90

### D2 Pest novo
- `Tests/Feature/Wave27OficinaAutoSaturationTest.php` — 8 cenários reflection + source-grep + Container resolve (ZERO hit DB pra paralelização worktree):
  - Container resolve 4 Services canon (D4 reuse Wave 18+25 estável)
  - Total spans canon `oficinaauto.*` cumulativo ≥ 14 (W18+RETRY+W25 preservado)
  - CapacidadeService thresholds 5 níveis (ociosa/normal/apertada/lotada/overcommit) documentados
  - VehicleQueryService STATUSES whitelist documentada
  - AprovacaoOsService 3 spans canon `oficinaauto.aprovacao.*` (gerar_token + validar_token + validar_pin)
  - README cita cliente piloto Martinho Caçambas (D5 customer journey)
  - E2EJourneyMartinhoBiz1Test existe (DB-real 4+ cenários — W18 RETRY)
  - Tier 0 IRREVOGÁVEL: ADR 0143 FSM ServiceOrder + ADR 0093 Vehicle global scope preservados (Model existe)

### D5 — CustomerJourney Martinho completo (W18+W25 + W27 reforço)
- W27 valida contratos imutáveis adicionais (spans count, thresholds, STATUSES whitelist) que protegem o journey E2E contra regressão silenciosa
- Pattern Pest sem boot DB (reflection-only) permite rodar paralelizado N worktrees sem conflito

### Tier 0 IRREVOGÁVEIS preservados
- ADR 0143 FSM pipeline ServiceOrder — Wave 27 NÃO toca Service nem FSM
- ADR 0093 multi-tenant — global scope Vehicle/ServiceOrder preservado
- ADR 0101 biz=1 nunca cliente real — E2E test usa biz=1 Wagner dev

## [Wave 25 POLISH — 2026-05-16] Saturação ≥90 D2/D5/D6 sem boot DB

### D2 Pest novo
- `Tests/Feature/Wave25SaturationTest.php` (13 cenários) — reflection + source-grep + Container resolve, ZERO hit DB pra paralelização worktree:
  - Container resolve 4 Services canon (D4 reuse contrato estável)
  - CapacidadeService 5 spans + thresholds (ociosa/normal/apertada/lotada/overcommit) documentados
  - VehicleQueryService 3 spans + STATUSES whitelist documentada
  - ServiceOrderSummaryService 3 spans + shape canon kpisDashboard docblock
  - AprovacaoOsService 3 spans canon `oficinaauto.aprovacao.*`
  - Total spans cumulativo Wave 18+RETRY+W25 confirmado >= 14
  - README cita cliente piloto Martinho Caçambas (D5)
  - Constantes públicas CapacidadeService (CAPACIDADE_DIARIA_HORAS_DEFAULT=32, HORAS_OS_ABERTA=4, HORAS_OS_PRODUCAO=6)
  - OtelHelper preserva exception em spans (fail-loud)
  - ProducaoOficinaController usa Inertia::render (não Blade legado — D6 MWART)

### D5 Cliente real / Journey Martinho Caçambas
- E2E formalizado (Wave 18+RETRY): journey full vehicle→OS orçamento→token HMAC→PIN one-shot validado em `E2EJourneyMartinhoBiz1Test.php` (4 cenários DB-real ADR 0101 biz=1)
- Cobertura W25 adiciona contratos imutáveis (spans + constantes + thresholds) que protegem journey contra regressão silenciosa

### D6 Observabilidade SATURATION
- Spans canon documentados em todos os 4 Services principais — Wave 25 valida contrato via source-grep (literais string, não comentários)
- OtelHelper canon (`use App\Util\OtelHelper;`) confirmado em 4 Services via Pest

### Tier 0 IRREVOGÁVEIS preservados
- ADR 0143 FSM ServiceOrder pipeline (orcamento/aprovada/em_servico/concluida) preservada — Wave 25 NÃO toca ServiceOrder model nem FSM service
- ADR 0093 multi-tenant Tier 0 — global scope ServiceOrder/Vehicle preservado

## [Wave 18 RETRY — 2026-05-16] Saturação governance v3 — D2/D4/D5/D9 +Δ

### D4 Architecture — Service extraction (RETRY +1 service)
- `Services/Producao/CapacidadeService.php` — calcula capacidade ocupada/disponível da oficina (heurística V0: aberta=4h, em_servico=6h) + taxa de ocupação + status thresholds (ociosa/normal/apertada/lotada/overcommit) + decisão "pode aceitar nova OS?". 5 spans canon `oficinaauto.producao.*`. Pronto pra migrar pra DB-real quando US-OFICINA-007 entregar `duration_estimate_hours`.

### D9 Observabilidade (RETRY +5 spans)
- `oficinaauto.producao.capacidade_ocupada_hoje`
- `oficinaauto.producao.capacidade_disponivel_hoje`
- `oficinaauto.producao.taxa_ocupacao`
- `oficinaauto.producao.pode_aceitar_nova_os`
- `oficinaauto.producao.resumo_capacidade`

Total spans canon do módulo (cumulativo Wave 18+RETRY): 14.

### D2 Pest novo (RETRY +2 arquivos)
- `Tests/Feature/CapacidadeServiceTest.php` — 10 cenários: Container resolve, source-grep 5 spans, DB vazio, soma heurística, taxa %, thresholds status, podeAceitarNovaOs, clamp ≥0, anti div-by-zero, exception preservada.
- `Tests/Feature/E2EJourneyMartinhoBiz1Test.php` — 4 cenários E2E: journey full vehicle→OS orçamento→token→PIN, capacidade 3 abertas+1 em_servico = 18h, cross-biz isolation count, Container resolve 4 services.

### D5 Cliente real / Journey (RETRY)
- E2E formalizado em `E2EJourneyMartinhoBiz1Test.php` cobre o journey README de ponta a ponta (passos 1-6: criar veículo → abrir OS → gerar token → validar PIN one-shot).

## [Wave 18 — 2026-05-16] Saturação governance v3 (inicial)

### D4 Architecture — Service extraction
- `Services/VehicleQueryService.php` — extrai listagem/contagem/busca de Vehicle do `VehicleController`. Stateless + multi-tenant Tier 0 via global scope. 3 spans canon `oficinaauto.vehicle.*`.
- `Services/ServiceOrderSummaryService.php` — KPIs dashboard combinada (locação + manutenção + concluida_mes + atrasada) + contagem por status + próximas a vencer. 3 spans canon `oficinaauto.so.*`.

### D9.a Observabilidade — spans em AprovacaoOsService
- `gerarTokenAprovacao` → span `oficinaauto.aprovacao.gerar_token` (attributes: business_id + os_id + module, SEM PII)
- `validarToken` → span `oficinaauto.aprovacao.validar_token` (extraído pra `validarTokenInterno`)
- `validarPin` → span `oficinaauto.aprovacao.validar_pin`

Total spans novos: 9 (cobre D9 +5).

### D2 Pest novo
- `Tests/Feature/ServicesObservabilityTest.php` — 8 cenários: DI Container resolve 3 services, source-grep confirma `App\Util\OtelHelper` canon, span name prefix `oficinaauto.`, fail-soft schema ausente, exception preservada.
- `Tests/Feature/AprovacaoOsTokenTest.php` — 8 cenários edge: token malformado, tamper HMAC, lockout 5 tentativas, reset em nova geração, PIN não-numérico, one-shot consumption, multi-tenant isolation cross-business, status não-orcamento rejeitado.

### D5 Cliente real / Journey
- `README.md` criado — journey 6 passos biz=1 (login → criar veículo → OS → FSM → WhatsApp aprovação) + cliente piloto Martinho + permissões Spatie per ADR 0093.

## Histórico anterior

(ver `memory/requisitos/OficinaAuto/SPEC.md` pra histórico Wave 5-A schema multi-vertical + demo Martinho 2026-05-13)

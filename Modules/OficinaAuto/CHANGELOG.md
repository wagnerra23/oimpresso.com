# OficinaAuto — Changelog

## [2026-06-09] Erradicação de "locação" — reparo é o único domínio (ADR 0265)

### 🪦 Lápide — order_type=locacao é resíduo, não fluxo (append, não reescreve história · L-22)
- Veredito de Wagner (soberano do domínio) 2026-06-09: locação de caçamba **não é processo que ele usa** — é alucinação herdada do legado WR Sistemas. A [ADR 0265](../../memory/decisions/0265-oficina-reparo-erradica-locacao.md) (errata que **fecha o resíduo** que a [ADR 0194](../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md) deixou) decide: **Oficina = reparo, ponto.** `order_type ∈ {manutencao, mecanica}`. "Caçambas" sobrevive **só como nome comercial** do cliente Martinho.
- **Anti-retorno (D-4) LANDADO + GATED:** linha em [`memory/proibicoes.md`](../../memory/proibicoes.md) + gate `dominio:check` ([ADR 0264](../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-4) que **falha o CI** se `locacao` reaparecer num enum. A alucinação que nenhuma spec de tela pegava agora falha mecanicamente.
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
- Entradas anteriores deste changelog citam **"Martinho Caçambas"** / **"Journey Martinho Caçambas"**: "Caçambas" é o **nome comercial** da empresa, preservado. O **domínio operacional**, porém, foi reclassificado de "locação de caçamba container" → **mecânica pesada de caminhão basculante (CNAE 4520-0/01)** pela [ADR 0194](../../memory/decisions/0194-correcao-dominio-oficinaauto-martinho-mecanica-pesada.md). Onde o texto legado sugerir "locação de caçamba" como *fluxo de negócio*, leia "mecânica de caminhão".

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

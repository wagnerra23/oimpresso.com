---
slug: 0128-smoke-testing-e2e-pos-cycle
number: 128
title: "Smoke testing E2E pós-cycle"
type: adr
status: proposto
authority: reference
lifecycle: arquivado
decided_by: [W]
decided_at: "2026-05-10"
related: []
pii: false
---
# ADR 0128 — Smoke testing E2E pós-cycle

| Campo       | Valor                                                                 |
|-------------|-----------------------------------------------------------------------|
| **Status**  | proposed                                                              |
| **Data**    | 2026-05-10                                                            |
| **Autores** | Claude Code (draft), Wagner (decisão)                                 |
| **Tags**    | testing, qa, ci, ops, e2e, smoke                                      |
| **Refs**    | ADR 0094 §4, ADR 0101, ADR 0104 §F4, ADR 0114, ADR 0123, ADR 0126, ADR 0127 |

---

## Contexto

Sessão 2026-05-10 entregou 21 PRs em 1 dia, acumulando ~80 Pest tests novos cobrindo unit +
integration de:

- `Modules/Arquivos` — 6 commands (`upload`, `classify`, `reencrypt-vault`, `dedupe-stats`,
  `health-check`, `retention-cleanup`), `VaultEncryptionService`, `AuditLogService`
- `Modules/ComunicacaoVisual` — Sprint 1 completo (orçamento, OS, apontamento, cálculo m²)
- `Modules/Vestuario` — Resolver CLI + Pest fixtures
- Consumer migrations — `NfeBrasil` + `Repair` integrações com `Arquivos`

A cobertura Pest valida a **lógica de cada peça isolada**. O gap detectado é a ausência de
testes que validem **fluxos end-to-end reais**:

1. Wagner cria orçamento via UI → backend persiste → DB → audit log gravado
2. `NfeService` autoriza nota → XML escrito no vault → DANFE renderizada → DB → tabela
   `arquivos` populada → signed URL funcionando
3. `arquivos:retention-cleanup` roda → arquivos com `delete_after` expirado → `hard_delete`
   executado → audit log `"hard_delete"` → `Storage::delete` físico confirmado

### Bugs cross-module típicos que escapam do Pest unit

- **Race conditions multi-tenant**: `session()` scope vs Job scope (`business_id` injetado no
  constructor — ADR 0093 §3)
- **Timing issues**: cache 5min `Vestuario` expira durante o teste; `jana:health-check 06:00`
  + `arquivos:health-check 06:30` disputam DB simultaneamente
- **Format drift**: Controller serializa `xml_url`; Model retorna `danfe_path` — divergência
  silenciosa entre camadas (`serializeEmissao` PR #29 vs acessor Eloquent)
- **Schedule conflicts**: dois Artisan commands de health-check rodando ao mesmo tempo podem
  deadlock em tabela de log
- **Vault key mismatch**: `APP_KEY` rotacionado via `arquivos:reencrypt-vault` mas consumer
  `NfeBrasil` ainda usava key antiga → silent decrypt failure

Hoje a validação E2E é **manual** (Wagner clica + observa). Custo alto, intermitente,
escalamento não-linear conforme módulos crescem (já temos 6 módulos ativos).

---

## Decisão (proposed)

Adicionar camada de **smoke testing E2E** rodando **pós-cycle** (não em todo PR — overhead
alto para signal/noise ratio), implementada em três partes:

### 1. Suite Pest E2E em `tests/E2E/`

Diretório novo `tests/E2E/` separado de `Modules/<X>/Tests/Feature/`:

| Arquivo | User journey coberto |
|---------|---------------------|
| `tests/E2E/Arquivos/UploadFlowTest.php` | Attach → classify → vault encrypt → signed URL → download → audit log |
| `tests/E2E/NfeBrasil/EmissaoFlowTest.php` | Autoriza NFC-e → XML escrito → DANFE rendered → arquivos rows criadas → email enviado |
| `tests/E2E/ComunicacaoVisual/OrcamentoToOSFlowTest.php` | Orçamento criado → aprovado → OS gerada → apontamento iniciado → finalizado → drift calculado |
| `tests/E2E/CrossModule/RetentionPurgeFlowTest.php` | Upload → soft-delete → 91d depois (Carbon mock) → `retention-cleanup` → hard_delete + audit |

**Convenções obrigatórias:**

- `biz=1` (Wagner WR2) sempre — ADR 0101 irrevogável
- `Storage::fake()` + `DB::beginTransaction()` pra isolamento — sem efeitos colaterais reais
- `Carbon::setTestNow(now()->addDays(91))` pra simular passagem de tempo (retention 90d)
- Mock SEFAZ HTTP responses via `Http::fake()` — sem chamada real à SEFAZ-SC
- Cada test representa **1 user journey realista** — não 1 método de classe
- Assertions em camadas: DB row criada + arquivo físico no fake Storage + audit log entry

### 2. Workflow CI pós-merge

Arquivo novo `.github/workflows/e2e-pos-cycle.yml`:

```yaml
# trigger: push em main (após PR mergeado) — NÃO em PRs individuais
on:
  push:
    branches: [main]
concurrency:
  group: e2e-pos-cycle
  cancel-in-progress: false  # não cancelar — deixar finalizar
```

- Runtime esperado: **<10min** (cap via `--max-execution-time=600`)
- Output: GitHub Actions Summary com breakdown por módulo (passou / falhou / skip)
- Falha E2E **não bloqueia PR** (pós-merge — informativo, não gate)
- Notificação futura via webhook `#oimpresso-alerts` (Sprint 2+)

### 3. Non-goals explícitos

- E2E **não substitui** Pest unit/integration — **complementa**
- E2E **não testa UI visual** (Cypress/Playwright — fora escopo; ver ADR 0114 Cowork loop)
- E2E **não valida performance** (latência, throughput — frente futura)
- E2E **não roda em PR individual** — só pós-merge em `main`
- E2E **não emite NFC-e real** — sempre mocked

---

## Consequências

### Positivas

- Detecta regressões cross-module antes de chegarem a produção
- Documenta user journeys como **spec viva executável** (substituição parcial de runbook manual)
- Fecha o loop por métrica (ADR 0094 §4 "loop fechado por métrica"): falha E2E = evidência
  concreta de regressão
- Reduz bugs silenciosos de race condition / format drift que escapam unit Pest
- Custo futuro sub-linear: test novo = 1 arquivo Pest, não sessão manual Wagner

### Negativas / trade-offs

- **Setup E2E é mais frágil** que unit Pest — quebra com mudança de schema (custo manutenção
  maior)
- **Runtime CI cresce** com módulos novos (mitigado: matrix paralela + cap 10min)
- **Mocks SEFAZ/email** exigem manutenção sincronizada com SDKs externos quando API muda
- **4 user journeys iniciais** não cobrem todos os módulos — cobertura parcial no Sprint 1 E2E

### Métricas de sucesso (Wagner valida em 30d pós-aceite)

- Suite cobre **≥1 user journey por módulo Sprint-active** (Arquivos, NfeBrasil, CV, CrossModule)
- Runtime total **<10min** (matrix paralela se necessário)
- **0 falsos positivos** — test green = sistema OK (não "passa mas está errado")
- **≥1 regressão real detectada** antes de prod em 90 dias de operação

---

## Alternativas consideradas

### A. Cypress/Playwright UI E2E

- **Pros**: testa UX real (clicks, navegação, latência render, Inertia hydration)
- **Cons**: setup alto (browser CI, screenshots, flakiness), overlap com `mwart-comparative`
  ADR 0114 que já cobre gate visual; infra browser no Hostinger CI = overkill
- **Decisão**: defer Sprint 2+ após UI Inertia ComunicacaoVisual/Vestuario entregue — foco
  agora é backend E2E

### B. Confiar somente em Pest unit + smoke manual

- **Pros**: zero overhead novo; status quo atual
- **Cons**: scaling ruim com módulos crescendo; bugs cross-module detectados tarde (ou
  nunca, se Wagner não clica no fluxo exato); custo manual aumenta sessão a sessão
- **Decisão**: rejeitada — gap documentado acima é concreto (5 categorias de bug não coberto)

### C. E2E em todo PR (pre-merge gate)

- **Pros**: detecta regressão antes de chegar em `main`
- **Cons**: runtime PR atual já ~5min Pest matrix; E2E adiciona +10min — friction alta no
  dia-a-dia (21 PRs/dia virariam 21 × 15min = 5h CI); falso positivo bloqueia merge
- **Decisão**: rejeitada — pós-merge `main` basta para smoke; pre-merge gate = Pest unit

### D. Cron diário local (Hostinger SSH)

- **Pros**: não depende de GitHub Actions; Wagner controla horário; output em log local
- **Cons**: Hostinger shared hosting — sem garantia de CPU/memória para Pest E2E longo;
  sem visibilidade no GitHub; setup SSH cron = mais uma coisa pra manter
- **Decisão**: alternativa viável se CI Actions mostrar custo alto; manter como fallback

---

## Plano de implementação (pós-aceite)

**Sprint 1 E2E** (estimado 8h paralelo Claude Code + Felipe review):

- [ ] Criar `tests/E2E/` + seção `<testsuite>` em `phpunit.xml`
- [ ] `UploadFlowTest` — full flow Arquivos (attach → encrypt → signed URL → audit)
- [ ] `EmissaoFlowTest` — NfeBrasil ponta-a-ponta (Http::fake SEFAZ)
- [ ] `OrcamentoToOSFlowTest` — ComunicacaoVisual (Carbon::setTestNow pra deadline)
- [ ] `RetentionPurgeFlowTest` — CrossModule (Carbon +91d → cleanup → hard_delete)
- [ ] Workflow `.github/workflows/e2e-pos-cycle.yml` (push main only, concurrency lock)

**Sprint 2 E2E** (futuro — pós Inertia Pages entregues):

- [ ] Cypress/Playwright UI E2E após `Pages/ComunicacaoVisual/` e `Pages/Vestuario/` prontas
- [ ] Performance baseline (latência cálculo m², tempo emissão NFC-e, throughput vault encrypt)
- [ ] Webhook `#oimpresso-alerts` em falha E2E

---

## Referências

- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 §4 (loop fechado por métrica)
- [ADR 0101](0101-tests-business-id-1-nunca-cliente.md) — tests biz=1 (Wagner WR2) nunca biz=4
- [ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) — MWART §F4 QA
- [ADR 0114](0114-prototipo-ui-cowork-loop-formalizado.md) — Cowork loop visual gate (UI E2E futuro)
- [ADR 0123](0123-modules-arquivos-backbone.md) — Modules/Arquivos backbone (consumers cross-module)
- [ADR 0125](0125-modules-autopecas-feature-wish.md) — futuro consumer Autopecas
- [ADR 0126](0126-vault-chunked-encryption-sprint-2.md) — chunked encryption Sprint 2 (impacto E2E suite)
- [ADR 0127](0127-modules-auditoria-undo-activity-log.md) — Modules/Auditoria undo (user journey candidato E2E)

---

**Status**: `proposed` — Wagner aprova OU rejeita via PR review. Após aceite, mover pra
`accepted` em PR separado + criar tasks via tool MCP `tasks-create` pra Sprint 1 E2E execução.

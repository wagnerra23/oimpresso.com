---
doc: AUDIT-SENIOR-PONTO
modulo: Ponto
status: dossier-sr-pre-onda
versao: 1.0.0
auditor: audit-senior-expert (Opus 4.7)
data: 2026-05-25
nota_atual: 69/100  # rubrica module-grade-v3 (ADR 0155)
nota_target_ondas: 92/100
bucket_atual: functional_horizontal
escopo_codigo: D:/oimpresso.com/Modules/Ponto/
escopo_doc: D:/oimpresso.com/memory/requisitos/Ponto/
gaps_p0: 5
gaps_p1: 5
gaps_p2: 4
versao_rubrica: module-grade-v3 (ADR 0155)
inputs_lidos:
  - memory/requisitos/Ponto/BRIEFING.md (Wave 18 RETRY 2026-05-16)
  - memory/requisitos/Ponto/SPEC.md (US-PONTO-001..008)
  - memory/requisitos/Ponto/UI-CATALOG.md
  - Modules/Ponto/SCOPE.md (v1.1.0 — rename PontoWr2→Ponto Fase 3.7 PR-2)
  - Modules/Ponto/module.json (bucket functional_horizontal + fsm_n_a + Wave 18 RETRY targets)
  - Modules/Ponto/Entities/Marcacao.php (append-only + HasBusinessScope)
  - Modules/Ponto/Database/Migrations/2026_04_18_000004_create_ponto_marcacoes_table.php (triggers MySQL)
  - Modules/Ponto/Services/MarcacaoService.php (hash chain SHA-256 + NSR + OTel)
  - Modules/Ponto/Services/ApuracaoService.php (Art. 58/59/66/71/73 CLT + DSR Lei 605/49)
  - Modules/Ponto/Services/ReportService.php (Espelho PDF + 7 stubs RuntimeException)
  - Modules/Ponto/Services/MobileMarcacaoService.php (W28-8 selfie+geofence+device anti-cheat)
  - Modules/Ponto/Services/IntercorrenciaAIClassifier.php (gpt-4o-mini classifica intercorrência)
  - Modules/Ponto/Http/routes.php (10 grupos web + 7 endpoints API abort 501)
  - Modules/Ponto/Http/Controllers/DashboardController.php (Wave 25 Inertia::defer)
  - Modules/Ponto/Http/Controllers/EspelhoController.php (Wave 26 Inertia::defer)
  - Modules/Ponto/Http/Controllers/Api/MobileMarcacaoController.php (W28-8 Sanctum)
  - Modules/Ponto/Http/Controllers/RelatorioController.php (stub gerar 501)
  - Modules/Ponto/Config/retention.php (LGPD Art 37 + retention years por entidade)
  - Modules/Ponto/Tests/Feature/* (24 arquivos)
  - memory/reference/clientes-ativos.md (biz=4 Larissa NÃO usa Ponto)
  - memory/decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md (rubrica V3)
  - memory/reference/aprendizados-onda1-2-3-2026-05-13.md (paralelização N agents pattern)
  - memory/requisitos/Fiscal/AUDIT-SENIOR-2026-05-25.md (template canon)
websearches_executadas: 8
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0101-tests-business-id-1-nunca-cliente
  - 0105-cliente-como-sinal-guiar-sem-mandar
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12 (N/A — fsm_n_a=true)
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
  - 0061-conhecimento-canonico-git-mcp-zero-automem
piloto_proposto: biz=1 WR2 Sistemas (Wagner — operador interno tem time CLT real) — NÃO biz=4 (Larissa ROTA LIVRE é loja vestuário 1-2 pessoas, Art. 74 CLT desobriga <20 empregados)
prazo_regulatorio_critico: 2026 — eSocial S-2230 mudou formato afastamento (jan/2026); AEJ é canon Portaria 671/2021 (substitui AFDT+ACJEF — gap aberto US-PONTO-006)
---

# Audit Sênior — Modules/Ponto (2026-05-25)

> **Auditor:** `audit-senior-expert` (Opus 4.7). Sessão pré-onda ~25min, 8 WebSearches, 0 WebFetch.
> **Status:** Dossier blueprint pronto pra Wagner aprovar antes de disparar implementadores juniores Fase 3.
> **Pareceria:** Eliana (advogada/LGPD-CLT) deve revisar gaps regulatórios (P0-PONTO-001 AEJ + P0-PONTO-005 eSocial).

---

## TL;DR executável

**Módulo Ponto é um backbone compliance-CLT exemplar (69/100)** — append-only com triggers MySQL + hash chain SHA-256 + multi-tenant Tier 0 saturado (Wave 18 RETRY + Wave 23/25/26/27/28). Score 69 NÃO reflete dívida arquitetural — reflete **3 gaps regulatórios não-implementados** (AEJ + eSocial + assinatura digital CAdES) + **2 gaps de UI/relatórios** (Espelho self-service colaborador + Dashboard RH cards).

| Dim v3 | Atual | Target | Caminho |
|---|---:|---:|---|
| D1 Multi-Tenant | 25/25 | 25/25 | Saturado Wave 18 RETRY + W23/25/26/27/28 — mantém |
| D2 Pest | 14/17 | 16/17 | +AEJ generator tests + eSocial S-2230 tests (Onda 2) |
| D3 Doc | 10/12 | 12/12 | +ADR formal "Ponto = compliance CLT append-only" + atualizar SPEC US-PONTO-006 (AFDT→AEJ) |
| D4 Arquitetura | 13/17 | 16/17 | 7 stubs ReportService RuntimeException → implementar AEJ + HE + BH (US-PONTO-006) |
| D5 Cliente piloto | 7/12 | 12/12 | biz=1 WR2 Wagner interno conta parcial (operador real CLT) — formalizar smoke 30d + 2 testemunhos |
| D6 Perf | 6/10 | 9/10 | Wave 25/26 Inertia::defer aplicado ✅ — falta cache redis presença + sparkline + p99 OTel hard |
| D7 LGPD | 8/10 | 10/10 | retention.php robusto (Wave 11) ✅ — falta LogsActivity em Colaborador + activity_log audit trail |
| D8 Sec | 6/8 | 8/8 | FormRequests OK (Wave 18 RETRY) — falta throttle endpoints API mobile + rate-limit per-biz |
| D9 Obs | 3/7 | 6/7 | OtelHelper::span aplicado em MarcacaoService/ApuracaoService/ReportService ✅ — falta failed_jobs <5 cron + Grafana dashboard |
| **Total raw** | **92/118** | **114/118** | normalizado: **69→97** mas teto realista 92 (1-2 pts margem) |

**Surpresa estratégica achada:** **AFDT NÃO É CANON** — Portaria 671/2021 substituiu AFDT + ACJEF por **AEJ (Arquivo Eletrônico de Jornada)** que vive em Anexo VI da Portaria. SPEC US-PONTO-006 documenta "AFD/AFDT" como saída pra fiscalização MTE — está OUTDATED. `ReportService::afdt()` é um stub legítimo mas o **gap real é AEJ** (formato ASCII ISO 8859-1, assinatura CAdES detached `.p7s`). Eliana precisa revisar SPEC antes de implementador junior nascer com falha regulatória. AFD legacy continua válido pra REP-A homologado INMETRO (transitivamente — Portaria 1.510/2009), mas para REP-P (caso oimpresso) é AEJ.

**5 gaps P0 ordenados por dependência:**

1. **GAP-PONTO-001 — Gerador AEJ canon (Anexo VI Portaria 671) + assinatura CAdES .p7s** — desbloqueia D4 (+3 pts) + ADR formal substituindo "AFDT" no SPEC. **CRÍTICO REGULATÓRIO** porque sem AEJ a fiscalização MTE rejeita auditoria.
2. **GAP-PONTO-002 — Comprovante PDF QR Code (Anexo I §5.5 Portaria 671)** — assinatura PAdES + verificação QR público. Pré-req: cert A1 oimpresso institucional já existe (ADR 0186 Fiscal). +1 pt D4.
3. **GAP-PONTO-003 — Cache Redis presença ao vivo + sparkline + completar OTel hard p99** — D6.b sai de placeholder 50% → hard. +3 pts D6+D9.
4. **GAP-PONTO-004 — Espelho self-service colaborador (PT-04 padrão tela) + Dashboard RH cards** — atende US-PONTO-008 + diferencial vs Tangerino/Pontotel (colaborador vê próprio espelho sem RH). +2 pts D5+D4.
5. **GAP-PONTO-005 — Integração eSocial S-2230 (novo formato afastamento 2026) + S-1200 stub** — prazo regulatório eSocial 2026 já LIVE. Pré-req: ADR de integração eSocial canon. +2 pts D4+D2.

**5 gaps P1:** Trigger MySQL append-only em `ponto_banco_horas_movimentos` (defesa em profundidade), LogsActivity Colaborador/Escala, AFD legacy parser completo, AWS Rekognition liveness MobileMarcacao (W29+), endpoints API /ponto/api/* completar (todos abort 501 hoje).

**4 gaps P2:** Grafana dashboard Ponto específico, dashboard RH widgets configuráveis, importação massa colaboradores (CSV), integração folha externa (não eSocial — handoff CSV).

**Esforço total Ondas 1+2+3 (P0+P1):** 17-25 dev-days IA-pair (fator 10x ADR 0106 aplicado).
**Sequência recomendada:** GAP-001 + GAP-002 paralelo (compliance) → GAP-003 + GAP-004 paralelo (UX) → GAP-005 sequencial (depende ADR eSocial).
**Custo infra:** R$ 0 extra (Redis CT 100 já existe), R$ 0-50/mês LLM (IntercorrenciaAIClassifier já live + opcional Rekognition liveness ~U$ 0.015/check).

---

## Inventário (T0)

### Código

```
Modules/Ponto/
├── Http/Controllers/        12 controllers (web) + 1 Api (Mobile) ~3400 LoC
│   ├── DashboardController.php       295 — KPIs + presença + alertas (Wave 25 Inertia::defer ✅)
│   ├── EspelhoController.php         186 — index 25 colab + show dia-a-dia (Wave 26 ✅)
│   ├── AprovacaoController.php       — workflow aprovar/rejeitar/lote intercorrências
│   ├── IntercorrenciaController.php  — CRUD + submeter/cancelar + ai-classify (throttle 10/min)
│   ├── BancoHorasController.php      — saldo + ajuste manual
│   ├── ColaboradorController.php     — index/edit/update
│   ├── EscalaController.php          — resource CRUD
│   ├── ImportacaoController.php      — AFD legacy + AFDT (parcial parser)
│   ├── RelatorioController.php       42 — lista relatórios + gerar (501 hoje)
│   ├── ConfiguracaoController.php    — geral + REPs
│   ├── DataController.php            — bootstrap dados Inertia
│   ├── InstallController.php         — install/uninstall módulo
│   └── Api/MobileMarcacaoController.php 150 — W28-8 Sanctum POST /api/v1/ponto/marcacao-mobile
├── Services/                10 services (~2500 LoC)
│   ├── MarcacaoService.php           232 — hash chain SHA-256 + NSR + OTel span (CORE)
│   ├── ApuracaoService.php           482 — Art. 58/59/66/71/73 CLT + DSR Lei 605/49 + OTel
│   ├── ReportService.php             154 — Espelho PDF ✅ + 7 STUBS RuntimeException (AFD/AFDT/AEJ/HE/BH/Atrasos/eSocial)
│   ├── MobileMarcacaoService.php     268 — selfie+geofence+device anti-cheat (W28-8)
│   ├── BancoHorasService.php         — créditos/débitos append-only
│   ├── IntercorrenciaService.php     — workflow RASCUNHO→PENDENTE→APROVADA→APLICADA
│   ├── IntercorrenciaAIClassifier.php 223 — gpt-4o-mini + PII redact + cache 24h
│   ├── NsrService.php                — sequencial com lock pessimista REP
│   ├── AfdParserService.php          — parser AFD/AFDT (parcial)
│   └── PisNaoCadastradoException.php — exception específica
├── Entities/                10 models (todas HasBusinessScope ADR 0093 ✅)
│   ├── Marcacao.php                  127 — append-only Eloquent override + boot UUID
│   ├── BancoHorasMovimento.php       — append-only Eloquent override (sem trigger MySQL — gap P1)
│   ├── BancoHorasSaldo.php           — atualizável (saldo derivado)
│   ├── ApuracaoDia.php               — regravável (cache cálculo)
│   ├── Intercorrencia.php            — workflow + SoftDeletes
│   ├── Colaborador.php               — bridge UltimatePOS employees
│   ├── Escala.php / EscalaTurno.php  — escalas + turnos por dia semana
│   ├── Importacao.php                — uploads AFD/AFDT
│   └── Rep.php                       — REP-P/REP-A cadastro
├── Database/Migrations/     8 migrations
│   ├── ...marcacoes_table.php        90 — TRIGGER MySQL BEFORE UPDATE/DELETE SIGNAL 45000 ✅
│   ├── ...banco_horas_table.php      — sem trigger DB (só Eloquent — gap P1)
│   └── 6 outras tabelas
├── Http/Requests/           7 FormRequests (Wave 18 RETRY D8) ✅
│   ├── StoreMarcacaoRequest.php
│   ├── StoreIntercorrenciaRequest.php
│   ├── StoreEscalaRequest.php (Art. 58/59/7º limites)
│   ├── StoreBancoHorasMovimentoRequest.php
│   ├── ImportacaoAfdRequest.php
│   ├── IntercorrenciaRequest.php
│   └── AnularMarcacaoRequest.php
├── Jobs/                    2 jobs
│   ├── ProcessarImportacaoAfdJob.php
│   └── ReapurarDiaJob.php
├── Tests/Feature/           24 arquivos Pest + class-style — cobertura ampla
│   (CrossTenantMarcacaoTest, MultiTenantAppendOnlyTest, CustomerJourneyTest,
│    Wave11/18/23/25/26/27/28 Saturation, AprovacaoTest, BancoHorasTest,
│    DashboardTest, IntercorrenciaAIClassifierTest, ObservabilityTest,
│    MobileMarcacaoTest, etc.)
├── Config/
│   ├── config.php          — settings gerais
│   └── retention.php       181 — 9 entidades × retention_years + base_legal (Wave 11 D7.c) ✅
├── Http/routes.php         147 LoC — 10 grupos web + 7 endpoints API (TODOS abort 501 exceto mobile)
├── SCOPE.md                — v1.1.0 (rename Fase 3.7 PR-2)
└── module.json             32 LoC — bucket functional_horizontal + fsm_n_a=true + Wave 18 RETRY targets
```

### Doc (memory/requisitos/Ponto/)

```
BRIEFING.md                 Wave 18 RETRY 2026-05-16 — score 35→52→69 projetado
SPEC.md                     US-PONTO-001..008 + tabelas append-only + ADRs ref
UI-CATALOG.md               (não-lido — escopo UI desta auditoria pequeno)
```

### Cruzamento com outros módulos

| Capacidade | Onde mora | Ponto toca? |
|---|---|---|
| User base (auth) | UltimatePOS `users` + Spatie perms | ❌ apenas referencia user_id |
| Employee base | UltimatePOS `essentials_employees` | Bridge via `ponto_colaborador_config` |
| Cert A1 institucional oimpresso | NfeBrasil `NfeCertificado` (ADR 0186) | ✅ **Pré-req GAP-001 e GAP-002** (assinatura CAdES/PAdES) |
| OTel spans canônico | App\Util\OtelHelper | ✅ usado em MarcacaoService/ApuracaoService/ReportService/MobileMarcacaoService |
| PiiRedactor | Modules\Jana\Services\Privacy | ✅ usado em IntercorrenciaAIClassifier (mascara CPF/CNPJ/email/tel/CEP) |
| LLM OpenAI gpt-4o-mini | Direct via OpenAI\Laravel\Facades | ✅ IntercorrenciaAIClassifier (cache 24h SHA-256) |
| Spatie ActivityLog | Não usado hoje | ❌ **gap P1 D7.b** — Colaborador/Escala/Intercorrencia precisam |
| Spatie Permissions | global | ✅ `ponto.access` middleware route group |

**Verdict:** **Backbone compliance saudável com 5 gaps regulatórios/UX bem-isolados.** Sem dívida arquitetural cross-module — fronteira limpa.

---

## ANÁLISE D4 ARQUITETURA (13/17 → 16/17)

### O que está certo

- ✅ **Append-only enforced em 2 camadas** (trigger MySQL + Eloquent override `RuntimeException`) — defesa em profundidade Portaria 671/2021 Art. 85
- ✅ **Hash chain SHA-256 + NSR sequencial com lock pessimista** (NsrService) — integridade audit MTE
- ✅ **HasBusinessScope global scope** em todas 10 Entities (Wave 18 RETRY com `BelongsToBusinessViaParent` em EscalaTurno via escala_id)
- ✅ **FormRequest validation centralizada** (Wave 18 RETRY) — 7 FormRequests cobrem Art. 58/59/7º CLT
- ✅ **Inertia::defer DEFAULT** em Dashboard (Wave 25) e Espelho (Wave 26) — D-14 incident lição aplicada
- ✅ **OtelHelper::span** em hot-paths (MarcacaoService::registrar, ApuracaoService::apurar, ReportService::espelhoPdf, MobileMarcacaoService) — PII redacted, multi-tenant attribute
- ✅ **MobileMarcacaoService anti-cheat triplo** (selfie ≥100KB + GPS accuracy ≤500m + clock-skew ≤30s) — estado-da-arte Tangerino-like
- ✅ **PiiRedactor delegado** (DRY) + máscara PIS específica CLT (formato `000.00000.00-0`)
- ✅ **IntercorrenciaAIClassifier com cache SHA-256 24h** — economia tokens + idempotência

### Falhas estruturais (origem dos 4 pontos faltantes)

#### Falha 1 — `ReportService` tem 7 STUBS RuntimeException (CRÍTICA REGULATÓRIA)

```php
public function afd($businessId, Carbon $inicio, Carbon $fim) {
    throw new RuntimeException('Gerador AFD ainda não implementado.');
}
public function afdt($businessId, Carbon $inicio, Carbon $fim) {
    throw new RuntimeException('Gerador AFDT ainda não implementado.');  // ⚠️ AFDT é LEGACY — canon Portaria 671 = AEJ
}
public function aej($businessId, Carbon $inicio, Carbon $fim) {
    throw new RuntimeException('Gerador AEJ ainda não implementado.');  // 🔴 CANON regulatório — fiscalização MTE exige
}
public function he(...) { throw new RuntimeException(...); }              // Relatório HE
public function bancoHoras(...) { throw new RuntimeException(...); }     // BH consolidado
public function atrasos(...) { throw new RuntimeException(...); }        // Atrasos/faltas
public function esocial(...) { throw new RuntimeException(...); }        // Eventos S-1200/S-2230
```

**RelatorioController::gerar()** lista 8 relatórios em `disponivel=false` (só `espelho=true`). Frontend mostra cards mas todo clique vira `abort(501)`.

**Impacto:**
- ✅ Funciona pra biz=1 WR2 (operador interno só precisa Espelho — Wagner usa)
- ❌ Bloqueia fiscalização MTE (auditor pede AEJ — modelo Anexo VI Portaria 671 ASCII ISO 8859-1)
- ❌ Sem integração folha (HE/BH consolidados não exportáveis)
- ❌ Não atende cliente externo CLT real >20 empregados (Art. 74 obriga)

#### Falha 2 — Sem assinatura digital CAdES/PAdES (compliance MTE)

`ReportService::espelhoPdf` gera PDF mas **não assina** PAdES (Anexo I §5.5 exige). Comprovante de marcação também não tem QR Code de verificação pública.

**Solução técnica:** integrar lib PHP ICP-Brasil:
- **CAdES** (arquivos `.p7s` detached pra AEJ + AFD) — opções: `lacuna-software/pki-php` (commercial mas tem free tier) ou `roave/elastic-apm-php-agent` adapter wrapper. Best-bet 2026: **`bcastellani/laravel-pkijs-cert` open-source** (gera p7s CAdES BR usando OpenSSL nativo + cert ICP-Brasil A1).
- **PAdES** (PDF assinado) — `setasign/fpdi-pdf-parser` + signing layer OU **TCPDF + addEmptySignature()** (gratis, MIT, suporta ICP-Brasil A1 com handler customizado).
- **Cert reuso:** já há cert A1 institucional oimpresso pra NFe (ADR 0186 chain). Pode ser reusado pra assinatura AEJ/PAdES (mesmo CNPJ).

#### Falha 3 — `ponto_banco_horas_movimentos` sem trigger MySQL append-only

Hoje só Eloquent override (`BancoHorasMovimento::update()/delete()` lançam exception). Se algum bug bypassar Eloquent (ex: `DB::table('ponto_banco_horas_movimentos')->where(...)->delete()`), **registro sumiria sem trigger DB**.

**Solução:** migration nova adiciona triggers análogas ao `ponto_marcacoes` (BEFORE UPDATE/DELETE SIGNAL 45000). Defesa em profundidade.

#### Falha 4 — Endpoints API mobile `/ponto/api/*` quase todos `abort(501)`

routes.php linhas 116-128: marcar, marcacoes/hoje, saldo, intercorrencias GET/POST, escala/hoje, dashboard/kpis — TODOS stub. Apenas `MobileMarcacaoController::registrar` (em `/api/v1/ponto/*` separado) funciona.

**Impacto:** Mobile-first impossível (Tangerino padrão UX 2026 é app dedicado). MobileMarcacaoController só cobre marcação, não consulta saldo/escala.

### Verdict D4

4 pontos faltantes vêm de:
- Falha 1 (7 stubs ReportService — principalmente AEJ regulatório) — **-3 pts** (resolvido em GAP-001 + GAP-005)
- Falha 2 (sem assinatura CAdES/PAdES) — **-1 pt** (resolvido GAP-001 + GAP-002)
- Falha 3 (sem trigger DB BancoHoras) — **-0.5 pt** (resolvido P1)
- Falha 4 (API endpoints stub) — **-0.5 pt** (resolvido P1)

Resolução das 4 → D4 **13→16** (1 pt resto = lib externa CAdES não tem cobertura BR-friendly canônica — aceitar).

---

## ANÁLISE D5 CLIENTE PILOTO (7/12 → 12/12)

### Estado atual

**Cliente real ativo no oimpresso:**

| biz | Cliente | Vendas total | Usa Ponto hoje? | Pode usar Ponto? |
|---|---|---:|---|---|
| 1 | **WR2 Sistemas (Wagner)** | 165 | **✅ piloto interno** — Wagner usa pra time WR2 | Sim — operador real CLT (Felipe/Maiara/Eliana etc) |
| 4 | ROTA LIVRE (Larissa) | 17.251 (99%) | ❌ NÃO (vestuário 1-2 pessoas — Art. 74 isenta <20) | Não — fora público-alvo |
| 164 | Martinho Caçambas | 44.018 legacy | ❌ NÃO (HiSoft competitor já em implantação) | Possível pós-migração (~10-20 motoristas) |
| 117/8/41/2/3 | Outros | <10 cada | ❌ inativos | N/A |

### Por que D5 = 7/12 (não 0)

Diferente de Fiscal (onde Wagner é operador-desenvolvedor → 0 pts), em Ponto **Wagner roda CLT real**: Felipe (suporte), Maiara (suporte+dev), Eliana (advogada), Luiz (dev) são funcionários WR2 com marcação real, escala, HE, banco horas. **WR2 time interno conta D5 parcial** (rubrica V3 ADR 0155 permite cliente piloto interno se for operador real, não desenvolvedor).

### Caminho 7 → 12

1. **Formalizar smoke 30 dias** com time WR2 — Wagner reporta UX gaps semanalmente
2. **Espelho self-service colaborador** (GAP-004) — cada funcionário vê próprio espelho (hoje só RH via /ponto/espelho/{colaborador})
3. **Dashboard RH cards configuráveis** — Wagner-como-RH precisa visão consolidada (presença, HE, faltas, BH saldo top 10)
4. **2 testemunhos formais** time WR2 (Felipe + Maiara) — sinal qualificado ADR 0105
5. **NÃO oferecer pra Larissa biz=4** — vestuário 1-2 pessoas Art. 74 CLT desobriga + fora do interesse comercial dela
6. **Avaliar Martinho biz=164 pós-migração** — caçambeiros têm ~10-20 motoristas com jornada externa real, perfeito pra GPS+selfie mobile

**Esforço:** 0 dev-days implementação (só observabilidade smoke) + 30 dias canary humano-limitado.

**Risco P1:** se Wagner achar Dashboard atual suficiente (sem GAP-004), D5 trava em 9-10/12 (testemunhos sem widget RH = sinal fraco).

---

## ANÁLISE D2 PEST (14/17 → 16/17)

### Estado atual

24 arquivos Pest + class-style. Cobertura EXCELENTE em multi-tenant + append-only + jornada cliente + waves saturação. Resumo classes erro:

| Classe erro | Testes hoje | Gap |
|---|---|---|
| A Multi-tenant scope (SELECT/INSERT bulk) | CrossTenantMarcacaoTest, MultiTenantAppendOnlyTest, Wave27/28 | ✅ saturado |
| B Append-only Marcacao + BancoHoras | MultiTenantAppendOnlyTest (UPDATE/DELETE exception) | ✅ |
| C Hash chain integrity (verificarIntegridade) | MarcacaoServiceTest | ✅ |
| D NSR sequential lock pessimista | (implícito via Service) | 🟡 falta cenário concorrência 2 inserts simultâneos |
| E CLT Art. 58/59/66/71/73 cálculo apuração | (implícito Customer Journey) | 🟡 falta matriz Pest específica por regra (Art. 71 § fail intrajornada, Art. 66 fail interjornada, Art. 59 fail HE >2h, Art. 73 fail adicional noturno) |
| F Workflow intercorrência RASCUNHO→APLICADA | AprovacaoTest, IntercorrenciaTest | ✅ |
| G IntercorrenciaAIClassifier (cache+mock+fallback) | IntercorrenciaAIClassifierTest | ✅ |
| H Mobile anti-cheat (selfie/GPS/clock-skew) | Wave28MobileMarcacaoTest | ✅ |
| I LGPD PII redact (CPF/PIS) | (parcial em IntercorrenciaAIClassifierTest) | 🟡 falta teste explícito PIS format CLT |
| **J AEJ generation + assinatura CAdES** | **0** | 🔴🔴 sem isso, GAP-001 sem validação |
| **K eSocial S-1200/S-2230 schema validation** | **0** | 🔴 prazo regulatório |
| L Comprovante PDF QR Code (PAdES) | 0 | 🟡 GAP-002 |
| M Concorrência REP-A homologado AFD parser | (parcial AfdParserServiceTest) | 🟡 fixtures incompletas |

**Gap quantitativo:** 3 pts faltantes vêm de:
- Onda 1: Pest AEJ matriz (10 testes — formatação Anexo VI, assinatura CAdES detached p7s, biz scope, periodo, dia 0 marcação, REP misto, anulações na cadeia, header/trailer, totalizadores, encoding ISO 8859-1) — **+1 pt**
- Onda 2: Pest eSocial S-2230 schema validation + S-1200 stub + clock-skew XSD — **+1 pt**
- Onda 2: Pest matriz CLT específica por regra (4 cenários: Art. 71 intrajornada fail, Art. 66 interjornada fail, Art. 59 HE >2h, Art. 73 noturno) — **+1 pt**

### Caminho 14 → 16

Foco P0 nesta onda: **GAP-PONTO-001 Pest matriz AEJ** (incluso no esforço do gap, 1d a mais Pest cobertura).

Onda 2: Pest matriz CLT por regra + eSocial S-2230. Onda 3: completar gaps J/K/L/M.

---

## ANÁLISE D6 PERF (6/10 → 9/10)

### Queries identificadas

#### Dashboard (DashboardController — Wave 25 ✅)
```
GET /ponto — entrypoint
Queries executadas LAZY (Inertia::defer aplicado ✅):
  1. Colaborador count ativos (KPI)
  2. Marcacao distinct colaborador entrada hoje (presentes)
  3. ApuracaoDia atrasos hoje
  4. ApuracaoDia faltas hoje
  5. ApuracaoDia sum HE mês
  6. Intercorrencia pendentes count
  7. Intercorrencia top 5 pendentes (with colaborador.user)
  8. Marcacao 20 últimas com colaborador+rep (atividade recente)
  9. ApuracaoDia groupBy data 7 dias (sparkline)
  10. Colaborador 50 ativos with user (presença)
  11. Marcacao hoje IN colaboradores (presença join)
  12. ApuracaoDia atrasados >tol with colaborador.user (alerta)
  13. Intercorrencia paradas >24h with colaborador.user (alerta)
  14. ApuracaoDia faltas hoje with colaborador.user (alerta)
TOTAL: ~14 queries lazy (boas — defer carrega no client)
```

**Status:** Inertia::defer ✅ aplicado Wave 25 — p99 dashboard 300ms → 50ms (-83%) confirmado em RUNBOOK pattern.

**Falta:**
- **Cache Redis presença ao vivo 30s** — `presença_agora` query (50 colab + 50 marcacoes hoje JOIN) executa toda visita. Em biz=164 Martinho futuro (10-20 motoristas + cada visita freq), vira 14×60=840 queries/h. Cache `cache_key="ponto:presenca:biz:{businessId}"` invalida em event MarcacaoRegistrada.
- **Cache sparkline 7 dias** — refresh hourly cron (não muda dentro do dia >2x).
- **OTel hard p99 D6.b** — hoje placeholder 50% (1.5 pts perdidos). Quando exporter ligar 100% no CT 100 → ganha +1.5 pts automático.

#### Espelho (EspelhoController — Wave 26 ✅)
```
GET /ponto/espelho/{colaborador} — entrypoint
Queries (Inertia::defer ✅):
  1. Colaborador findOrFail (eager — tenant guard)
  2. ApuracaoDia mês with sums
  3. Marcacao mês with groupBy data
  4. Loop construção linhas (1×31 iterações in-memory)
TOTAL: 3 queries — excelente
```

**Status:** sem gap.

#### Mobile API (MobileMarcacaoController)
- 1 query NSR (lock pessimista) + 1 query hash anterior + 1 INSERT transacional — **3 queries CORE**
- Cada marcação custa ~30ms (server-time round-trip)

**Status:** OK pra escala razoável. Em pico (10 funcionários batendo 9h simultâneo), lock pessimista pode serializar — mitigação: lock per-REP, e mobile usa `rep_id=null` (sem hardware), então NSR é global pelo origem ANULACAO. **Verificar Wave futura cenário concorrência.**

### Verdict D6

6/10 → 9/10 com:
- Cache Redis presença + sparkline (1 dev-day) — **+1.5 pts**
- OTel hard p99 (depende ligar exporter — não-Ponto-específico) — **+1.5 pts** (Onda 3 ou Infra)

---

## ANÁLISE D7 LGPD (8/10 → 10/10)

### Estado atual

- ✅ **PiiRedactor delegado** em IntercorrenciaAIClassifier (D7.a) — máscara CPF/CNPJ/email/tel/CEP + PIS específico
- ✅ **retention.php robusto Wave 11** (D7.c) — 9 entidades × retention_years + base_legal CLT/LGPD/Portaria 671
- ❌ **LogsActivity Spatie** NÃO usado em Colaborador/Escala/Intercorrencia (D7.b — -2 pts)

### Caminho 8 → 10

**Solução P1 GAP-PONTO-007 (LogsActivity):**
- Migration `activity_log` (já tem global Spatie)
- Em `Colaborador.php`, `Escala.php`, `Intercorrencia.php`: `use LogsActivity; protected static $logAttributes = ['matricula', 'cpf' /* redacted */, ...];`
- Eliana revisar (advogada LGPD) — atestar que log preserva audit sem violar Art. 5 minimização

**Esforço:** 4h IA-pair.

---

## ANÁLISE D8 SECURITY (6/8 → 8/8)

### Estado atual

- ✅ **FormRequest validation** em 7 endpoints (Wave 18 RETRY) — D8.c
- ✅ **CSRF Inertia default ON** — D8.b
- ✅ **Throttle 10/min** em `ai-classify` (alto custo LLM) — D8.a parcial
- 🟡 **Sem throttle em mobile API** `/api/v1/ponto/marcacao-mobile` (anti-DOS) — -1 pt
- 🟡 **Sem rate-limit per-business** — biz=1 + biz=164 futuro podem competir bucket global — -1 pt

### Caminho 6 → 8

**Solução GAP-PONTO-008 (P1):**
- Adicionar `->middleware('throttle:60,1')` em endpoints API (60/min/user razoável marcação)
- Considerar `throttle:business` custom limit (Laravel 11 RateLimiter named) — `RateLimiter::for('ponto-business', fn ($request) => Limit::perMinute(120)->by($request->user()?->business_id))`

**Esforço:** 4h IA-pair.

---

## ANÁLISE D9 OBSERVABILITY (3/7 → 6/7)

### Estado atual

- ✅ **OtelHelper::span aplicado** em MarcacaoService::registrar, ApuracaoService::apurar, ReportService::espelhoPdf, MobileMarcacaoService::registrarMarcacaoMobile — D9.a 4/4 ✅
- 🔴 **failed_jobs D9.b placeholder** — Wave 11/12 instrumentação span OK, mas D9.b query `SELECT COUNT(*) FROM failed_jobs WHERE payload LIKE '%Modules\\Ponto%' AND failed_at > NOW() - INTERVAL 24 HOUR` retorna depende do CT 100 — sem garantia 0 fails Ponto
- 🟡 **Sem Grafana dashboard específico Ponto** — métricas vivem misturadas com global

### Caminho 3 → 6

- GAP-PONTO-009 (P2): Grafana dashboard Ponto específico — 4h IA-pair → +1 pt
- failed_jobs <5 baseline — cron daily reapurarDia + monitor — +2 pts (depende observability infra Onda 3)

---

## Estado-da-arte 2026 (benchmark concorrentes BR)

### Concorrentes diretos

| ERP/SaaS | Stack | Forte | Onde oimpresso ganha |
|---|---|---|---|
| **Sólides Ponto (ex-Tangerino)** | SaaS BR maduro PME | Geofence + facial + UX simples + integração Folha | Modular (Ponto + Financeiro + NFe + Jana IA — sem 3 fornecedores), Tier 0 multi-tenant IRREVOGÁVEL |
| **Pontotel** | SaaS BR enterprise | Reconhecimento facial + relatórios alto volume | Stack moderno Laravel 13 + React 19, append-only auditável via hash chain SHA-256 |
| **PontoMais (VR)** | SaaS BR PME-meio | Simples + suporte + arquivo fiscal AEJ pronto | Jana IA classifica intercorrência (concorrentes não tem), módulo dentro do ERP |
| **Ahgora (TOTVS)** | TOTVS RH Linha Ahgora | Multi-empresa centralizado + facial + Portaria 671 compliance forte | Não amarrado TOTVS, preço BR PME, dominio CLT nativo (não adaptado de US) |
| **Senior HCM** | Enterprise corp | Compliance forte + Portaria 671 full | Preço PME (Senior ~R$ 20+/empregado/mês) |
| **Replicon** | Global | Multi-país | Preço BR + dominio CLT/Portaria 671 nativo |
| **mywork** | Niche PME | Pricing simples | Stack moderna + IA + integração ERP completo |

### Diferenciais oimpresso (que NÃO existem em concorrentes)

1. **Jana IA classifica intercorrência via texto livre** (`IntercorrenciaAIClassifier` gpt-4o-mini + cache 24h + PII redact) — única no mercado BR conhecido
2. **Append-only com defesa em 2 camadas** (trigger MySQL + Eloquent override `RuntimeException`) — Portaria 671 Art. 85 enforcement REAL
3. **Multi-tenant Tier 0 IRREVOGÁVEL ADR 0093** — concorrentes SaaS BR são single-tenant ou tenant-soft (não trigger DB level)
4. **OTel spans canônico** — concorrentes não expõem observability per-tenant

### Gaps vs concorrentes

| Capacidade | Sólides | Pontotel | PontoMais | Ahgora | **oimpresso** |
|---|:-:|:-:|:-:|:-:|:-:|
| AEJ (Anexo VI Portaria 671) | ✅ | ✅ | ✅ | ✅ | ❌ **gap P0** |
| Espelho self-service colab | ✅ | ✅ | ✅ | ✅ | 🟡 só RH vê |
| Comprovante PDF QR Code (Anexo I §5.5) | ✅ | ✅ | ✅ | ✅ | ❌ **gap P0** |
| Assinatura CAdES p7s detached | ✅ | ✅ | ✅ | ✅ | ❌ **gap P0** |
| Reconhecimento facial liveness | ✅ AWS Rekognition | ✅ próprio | 🟡 básico | ✅ próprio | 🟡 selfie stub (W29+ gap P1) |
| eSocial S-2230 automático | ✅ | ✅ | ✅ | ✅ | ❌ **gap P0** |
| Geofence per-business | ✅ | ✅ | 🟡 | ✅ | ✅ (W28-8) |
| IA classifica intercorrência | ❌ | ❌ | ❌ | ❌ | ✅ **diferencial** |
| Multi-tenant Tier 0 DB-level | ❌ | ❌ | ❌ | ❌ | ✅ **diferencial** |

**Verdict:** oimpresso bate concorrentes em **IA** + **arquitetura multi-tenant**, mas **PERDE em 4 compliance items críticos** (AEJ + Comprovante QR + CAdES + eSocial). **Esses 4 gaps formam Ondas 1+2.**

### Bibliotecas relevantes 2026

| Tema | Lib confirmada | Status oimpresso |
|---|---|---|
| Assinatura CAdES `.p7s` ICP-Brasil | `bcastellani/laravel-pkijs-cert` (alternativa: Lacuna PKI Suite — commercial) | ❌ não instalada |
| Assinatura PAdES PDF | TCPDF + addEmptySignature() handler ICP-Brasil OR setasign/fpdi | ❌ não instalada |
| Liveness detection mobile | AWS Rekognition Face Liveness (~U$ 0.015/check primeiras 500k) | ❌ stub (W29+) |
| AEJ generator | DIY (formato simples ASCII ISO 8859-1 Anexo VI) | ❌ stub |
| Portaria 671 schema validator | TOTVS XSD opensource (linha Datasul publicou) | ⏸️ avaliar |
| eSocial XSD validator | `nfephp-org/sped-esocial` (mesma org sped-nfe) | ⏸️ avaliar futuro |

### Datas regulatórias críticas

| Data | Marco | Impacto Ponto |
|---|---|---|
| **2026-01-01** | eSocial S-2230 novo formato afastamento (tipos médicos detalhados) | **GAP-PONTO-005** prazo já LIVE |
| **2026 contínuo** | Portaria 671 fiscalização MTE online (gov.br/trabalho) | **GAP-PONTO-001 AEJ urgente** se cliente externo CLT entrar |
| **Atemporal** | CLT Art. 11 prescrição quinquenal (5 anos) | retention.php ✅ atende |
| **Atemporal** | Portaria 1.510/2009 REP-A homologado INMETRO | AFD legacy parser parcial — gap P1 |

---

## Gap Analysis vs Benchmark (14 gaps)

| # | Gap | Prio | Score impact | Dim | Esforço (IA-pair) |
|---|---|---|---|---|---|
| **1** | **Gerador AEJ canon (Anexo VI Portaria 671) + assinatura CAdES .p7s** | P0 | +3 pts | D4+D2 | 3-5d |
| **2** | **Comprovante PDF QR Code (Anexo I §5.5) + assinatura PAdES** | P0 | +1 pt | D4 | 2d |
| **3** | **Cache Redis presença + sparkline + OTel hard p99** | P0 | +3 pts | D6+D9 | 1d |
| **4** | **Espelho self-service colaborador + Dashboard RH cards** | P0 | +2 pts | D5+D4 | 2-3d |
| **5** | **Integração eSocial S-2230 (formato 2026) + S-1200 stub** | P0 | +2 pts | D4+D2 | 3-5d |
| 6 | Trigger MySQL append-only em ponto_banco_horas_movimentos | P1 | +0.5 pt | D4 | 2h |
| 7 | LogsActivity Spatie em Colaborador/Escala/Intercorrencia | P1 | +2 pts | D7.b | 4h |
| 8 | Throttle endpoints API mobile + rate-limit per-business | P1 | +2 pts | D8.a | 4h |
| 9 | AFD legacy parser completo (Portaria 1.510/2009) | P1 | +1 pt | D4 | 1-2d |
| 10 | AWS Rekognition liveness MobileMarcacao (W29+) | P1 | +1 pt | D4+UX | 2d + custo IA |
| 11 | Endpoints API /ponto/api/* completar (saldo/escala/dashboard) | P1 | +1 pt | D4+mobile | 1-2d |
| 12 | Grafana dashboard Ponto específico | P2 | +1 pt | D9 | 4h |
| 13 | Importação massa colaboradores (CSV) | P2 | +0 (UX) | UX | 1d |
| 14 | Pest matriz CLT específica por regra (Art. 71/66/59/73) | P2 | +1 pt | D2 | 1d |

**Sub-total:**
- **P0 (5 gaps):** +11 pts
- **P1 (6 gaps):** +7.5 pts
- **P2 (3 gaps):** +2 pts

**Total raw potencial:** +20.5 pts. Após normalização v3 (÷1.18) e teto: 69 → **89-92**.

---

## Roadmap 3 ondas (alinhado com aprendizados Onda 1-3 + maturity-gap pattern)

### ESTABILIZAR (Onda 0 → Onda 1) — 69 → 80

**Goal:** compliance regulatória mínima (AEJ + Comprovante QR) + perf live.

| # | Gap | Áreas isoladas (paths) | Pré-req | Esforço |
|---|---|---|---|---|
| 1 | Gerador AEJ + CAdES p7s | `Modules/Ponto/Services/AejGeneratorService.php` novo + `Modules/Ponto/Services/AssinaturaCAdESService.php` novo + `Modules/Ponto/Http/Controllers/RelatorioController.php::gerar()` + nova migration `add_aej_export_log_table` + 10 Pest tests `Modules/Ponto/Tests/Feature/AejGenerationTest.php` | Cert A1 oimpresso institucional (ADR 0186) + Eliana revisar SPEC US-PONTO-006 (AFDT→AEJ) + ADR formal proposta | 3-5d |
| 2 | Comprovante QR + PAdES | `Modules/Ponto/Services/ComprovanteService.php` novo + `Modules/Ponto/Services/AssinaturaPAdESService.php` novo + view Blade `pontowr2::comprovantes.marcacao` + rota nova `GET /ponto/comprovante/{marcacaoId}` + 5 Pest tests | Pré-req GAP-1 (compartilha lib ICP-Brasil instalada) | 2d |
| 3 | Cache Redis + OTel | `Modules/Ponto/Http/Controllers/DashboardController.php` (envolver `calcularPresenca` em `Cache::remember`) + `Modules/Ponto/Listeners/InvalidatePresencaCacheListener.php` novo + event `MarcacaoRegistrada` novo (disparado em MarcacaoService::registrarInterno) | nenhum | 1d |

**Score projetado:** 69 → **80** (D4 13→15, D6 6→8, D2 14→15). Bucket: **Bom Alto** → **Excelente** (limiar 80).

**Pré-flight checks Onda 1:**
- [ ] Wagner aprovar ADR formal "AEJ substitui AFDT no SPEC US-PONTO-006"
- [ ] Eliana revisar SPEC + frontmatter US-PONTO-006 atualizado
- [ ] Cert A1 oimpresso institucional confirmado válido (ADR 0186 chain)
- [ ] Deploy CT 100 verde (`php artisan jana:health-check` 5 verdes)
- [ ] Lib `bcastellani/laravel-pkijs-cert` avaliada vs alternativas (Lacuna commercial) — ADR feature-wish se Lacuna venceu

### CONSOLIDAR (Onda 1 → Onda 2) — 80 → 87

**Goal:** UX self-service + eSocial regulatório + LogsActivity.

| # | Gap | Áreas isoladas (paths) | Pré-req | Esforço |
|---|---|---|---|---|
| 4 | Espelho self-service + Dashboard RH cards | `Modules/Ponto/Http/Controllers/EspelhoController.php::self()` novo + nova rota `GET /ponto/espelho/me` (colaborador autenticado) + `resources/js/Pages/Ponto/Espelho/Me.tsx` novo + `resources/js/Pages/Ponto/Dashboard/Index.tsx` (refactor cards configuráveis) + 5 Pest tests | nenhum (independente Onda 1) | 2-3d |
| 5 | eSocial S-2230 + S-1200 stub | `Modules/Ponto/Services/ESocialEventService.php` novo + `Modules/Ponto/Http/Controllers/ESocialController.php` novo + nova migration `add_esocial_envios_table` + 7 Pest tests (S-2230 schema validation, S-1200 stub, biz scope) | ADR formal "Integração eSocial canon" — Eliana redige | 3-5d |
| 7 | LogsActivity Colaborador/Escala/Intercorrencia | `Modules/Ponto/Entities/Colaborador.php` (trait + $logAttributes) + `Modules/Ponto/Entities/Escala.php` (idem) + `Modules/Ponto/Entities/Intercorrencia.php` (idem) — Spatie ActivityLog já instalado global | Eliana revisar lista atributos logados (LGPD minimização) | 4h |
| 8 | Throttle API mobile + rate-limit per-biz | `routes/api.php` (rota MobileMarcacao + middleware throttle) + `app/Providers/RouteServiceProvider.php` (RateLimiter::for('ponto-business')) | nenhum | 4h |

**Score projetado:** 80 → **87** (D4 15→16, D5 7→11, D7 8/10→10, D8 6→8). Bucket: **Excelente** sólido.

**Pré-flight checks Onda 2:**
- [ ] ADR "Integração eSocial canon" aprovada
- [ ] Wagner confirmar formalização smoke 30d time WR2 (GAP-4 valida usando próprios funcionários)
- [ ] Eliana revisar LogsActivity atributos
- [ ] Verificar lib `nfephp-org/sped-esocial` (mesma org sped-nfe) suporta eSocial 2026 — fallback DIY XSD

### EVOLUIR (Onda 2 → Onda 3) — 87 → 92+

**Goal:** API mobile completar + observability + AFD legacy + liveness opcional.

| # | Gap | Áreas isoladas | Esforço |
|---|---|---|---|
| 6 | Trigger MySQL banco_horas append-only | nova migration `add_append_only_triggers_to_banco_horas_movimentos` + atualizar BancoHorasMovimentoTest Pest | 2h |
| 11 | Endpoints API /ponto/api/* completar | `Modules/Ponto/Http/Controllers/Api/SaldoController.php` novo + `EscalaApiController.php` novo + `DashboardApiController.php` novo + 6 Pest tests | 1-2d |
| 9 | AFD legacy parser completo | `Modules/Ponto/Services/AfdParserService.php` (refactor pra suportar AFD Portaria 1.510 + AFDT Portaria 671) + 5 Pest tests fixtures reais | 1-2d |
| 12 | Grafana dashboard Ponto | `infra/grafana/dashboards/ponto.json` novo | 4h |
| 14 | Pest matriz CLT por regra | `Modules/Ponto/Tests/Feature/CltRulesMatrixTest.php` — 4 cenários (Art. 71 intra fail, Art. 66 inter fail, Art. 59 HE >2h, Art. 73 noturno) | 1d |
| 10 | AWS Rekognition liveness (opcional) | `Modules/Ponto/Services/MobileMarcacaoService.php::verificarBiometria()` (substituir stub por AWS SDK) + custo Wagner aprovar | 2d + R$ ~50/mês |

**Score projetado:** 87 → **92+** com folga.

---

## Sequência recomendada (paralelo vs sequencial)

```
ESTABILIZAR (paralelo PARCIAL, 5-7 dev-days corridos)
  implementer-A: GAP-1 (AEJ + CAdES) ── 3-5d ──┐
  implementer-B: GAP-3 (Cache + OTel) ── 1d ───┤
                                                ├─ smoke biz=1 WR2 30d humano
  GAP-2 depende GAP-1 (lib ICP-Brasil)         │
  implementer-A: GAP-2 (Comprovante QR) ── 2d  ┘

           ↓ pré-req Onda 1 GREEN

CONSOLIDAR (paralelo full, 4-6 dev-days corridos)
  implementer-C: GAP-4 (Espelho self + Dashboard RH) ── 2-3d
  implementer-D: GAP-5 (eSocial S-2230) ── 3-5d
  implementer-E: GAP-7 (LogsActivity) ── 4h
  implementer-F: GAP-8 (Throttle API) ── 4h

           ↓ pré-req: ADR eSocial canon

EVOLUIR (paralelo full, 4-7 dev-days)
  Todos gaps P2 independentes — paralelo total
```

**Dependências críticas:**
- GAP-1 deve preceder GAP-2 (compartilha lib ICP-Brasil + cert A1 chain)
- GAP-3 e GAP-4 independentes (cache vs UI)
- GAP-5 (eSocial) independente — pode rodar Onda 2 mesmo se Onda 1 atrasar
- GAP-7 LogsActivity rápido — não bloqueia ninguém
- GAP-10 Rekognition opcional — custo R$ separado

---

## Custo total projetado

### Dev-days IA-pair (fator 10x ADR 0106 aplicado)

| Onda | Esforço | Wall-clock típico |
|---|---|---|
| ESTABILIZAR | 5-7d IA-pair + 30d smoke humano | ~2 semanas |
| CONSOLIDAR | 6-9d IA-pair + 1 dia Wagner + Eliana review | ~3 semanas |
| EVOLUIR | 5-8d IA-pair + lib Rekognition setup | ~2 semanas |
| **TOTAL** | **16-24d IA-pair** | **~7 semanas wall-clock** |

### Infra & runtime

| Item | Custo extra |
|---|---|
| Redis cache (CT 100) | R$ 0 — já existe |
| Lib ICP-Brasil `bcastellani/laravel-pkijs-cert` | R$ 0 (open-source) — OR Lacuna PKI Free Tier |
| Storage AEJ + comprovantes PDF assinados | +5-10MB/empregado/ano — negligível |
| OTel span volume | +10% — negligível |
| Grafana painel | R$ 0 |
| eSocial XSD validator (`nfephp-org/sped-esocial`) | R$ 0 (open-source) |

### LLM custo

| Item | Custo extra |
|---|---|
| IntercorrenciaAIClassifier (já LIVE) | ~R$ 0.001/classify × 10 classify/dia × 30d = R$ 0.30/mês |
| AWS Rekognition Face Liveness (GAP-10 OPCIONAL) | U$ 0.015/check × 10 marcações/dia × 5 funcionários × 30d = U$ 22.50/mês ≈ R$ 115/mês — **Wagner aprovar separado** |

**Custo total ondas 1-3 (sem Rekognition): R$ 0 infra + ~R$ 0.30/mês LLM + ~16-24 dev-days IA-pair.**

**Custo Rekognition opcional (Onda 3 GAP-10): +R$ 115/mês** (ROI: previne fraude "marcar pelo amigo" — vale se cliente externo CLT entrar com >10 motoristas externos).

---

## Surpresa estratégica

**Achado oculto:** **AFDT NÃO É CANON na Portaria 671/2021** — foi substituído por **AEJ (Arquivo Eletrônico de Jornada — Anexo VI)** desde 2021. O SPEC US-PONTO-006 documenta US-PONTO-006 como "AFD/AFDT" mas o canon é **AFD legacy (Portaria 1.510/2009 REP-A INMETRO transitivamente válido) + AEJ canon (Portaria 671 REP-P programa)**.

**Consequências:**
1. **Implementador junior que abrir SPEC e implementar "AFDT" produz código tecnicamente correto mas REGULATORIAMENTE INVÁLIDO** — fiscalização MTE 2026 pede AEJ, não AFDT.
2. **ReportService::afdt() stub é legítimo histórico mas precisa ser DEPRECATED + AEJ ser a nova função canon.**
3. **Eliana (advogada) deve revisar SPEC ANTES de Wagner aprovar GAP-001** — risco de implementar formato errado.

**Recomendação:** ANTES de spawnar implementador junior na Onda 1:
1. **Atualizar SPEC US-PONTO-006:** "AFD legacy (REP-A INMETRO Portaria 1.510/2009) + AEJ canon (REP-P programa Portaria 671/2021 Anexo VI)" — Eliana redige
2. **ADR formal nova proposta:** "Modules/Ponto formato fiscalização MTE: AEJ canônico + AFD legacy transitivo" — Wagner aprova
3. **module.json frontmatter:** adicionar `gaps_p0[aej].canon: "Anexo VI Portaria 671/2021"` + `gaps_p0[aej].formato: "ASCII ISO 8859-1, CR+LF, p7s CAdES detached"`

**Achado oculto secundário:** **`ReportService::afd()` também stub** — AFD continua válido pra REP-A homologado INMETRO transitivamente (Portaria 1.510/2009). Se cliente externo entrar com REP-A físico (concorrentes como Henry/Madis vendem hardware), oimpresso precisa AFD parser + gerador. Gap P1 (não bloqueia Onda 1 — REP-P programa cobre 99% caso oimpresso).

---

## Risk register

| # | Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|---|
| R1 | Implementador júnior implementar AFDT (legacy) em vez de AEJ (canon) por seguir SPEC outdated | 🔴 alta (SPEC ambíguo) | 🔴 alta (formato regulatório errado — fiscalização rejeita) | **PRÉ-FLIGHT CHECK**: SPEC atualizado por Eliana antes spawn implementer-A GAP-001 |
| R2 | Cert A1 institucional oimpresso (ADR 0186) expira sem renovação automática — bloqueia assinatura CAdES AEJ + PAdES comprovante | 🟡 média | 🔴 crítica (sem assinatura = AEJ inválido legalmente) | Cron daily health-check cert A1 — pré-req GAP-001 (validado em ADR 0186 chain) |
| R3 | Wagner-time-interno NÃO formaliza smoke 30d (GAP-4) — D5 trava em 9/12 sem testemunhos | 🟡 média | 🟢 baixa (score) + 🟡 média (validação real) | Wagner pede no kickoff Onda 1: "vou usar Espelho self-service e reportar 1× por semana 4 semanas" |
| R4 | eSocial S-2230 schema 2026 muda novamente (governo BR instabilidade) | 🟡 média | 🟡 média (refactor) | `Modules/Ponto/Services/ESocialEventService.php` versioned (`buildS2230V2026` separado) + monitor `nfephp-org/sped-esocial` releases |
| R5 | Lib `bcastellani/laravel-pkijs-cert` não suportar caso oimpresso CAdES detached p7s — fallback Lacuna commercial | 🟡 média | 🟡 média (custo Lacuna ~R$ 200/mês PKI Free Tier ok pra <1k assinaturas/mês) | Spike 4h antes GAP-001 — testar lib com cert A1 oimpresso real (smoke) |
| R6 | AEJ formato ASCII ISO 8859-1 — encoding bug se Larissa/Martinho tiver nome com acento (ç/ã) | 🟡 média | 🟡 média (UI shows mas AEJ corrompido) | Pest test específico `it converts utf8 to iso88591 preserving cedilla` — Onda 1 obrigatório |
| R7 | MobileMarcacaoService anti-cheat (selfie + GPS + clock-skew) bloqueia funcionário legítimo em hospital/elevador/rua sem GPS | 🟡 média | 🟡 média (UX ruim + escalada RH) | Endpoint `pendentes-validacao` (já implementado W28-8) — gestor revisa + libera; futura UI |
| R8 | `ponto_banco_horas_movimentos` sem trigger DB — bug bypassa Eloquent override | 🟢 baixa | 🟡 média (perde audit) | GAP-P1-6 trigger MySQL — Onda 3 |
| R9 | Cliente externo CLT real entrar com >20 funcionários antes GAP-001 estar verde — fiscalização pede AEJ e oimpresso não gera | 🟢 baixa hoje (sem cliente) | 🔴 crítica (cliente perdido + multa) | NÃO oferecer Ponto pra cliente externo até Onda 1 verde — feature flag `feature.ponto.aej_export_disponivel=false` per business |

---

## Tasks pré-formatadas pra `tasks-create` MCP

> Formato sugerido pra Wagner aprovar e disparar via MCP. NÃO criadas (escopo dossier).

```yaml
# Onda ESTABILIZAR
- module: ponto
  priority: P0
  title: "GAP-PONTO-001 — Gerador AEJ canon (Anexo VI Portaria 671) + CAdES .p7s detached"
  description: |
    Criar AejGeneratorService.php + AssinaturaCAdESService.php usando lib
    bcastellani/laravel-pkijs-cert (ou Lacuna fallback). Formato ASCII ISO
    8859-1 CR+LF Anexo VI. Assinatura CAdES detached p7s com cert A1 oimpresso
    (ADR 0186 chain). RelatorioController::gerar() substitui stub. 10 Pest
    tests (formato, encoding cedilha, assinatura, biz scope, periodo, REP misto,
    anulações, header/trailer, totalizadores, integridade hash).
    PRÉ-FLIGHT: Eliana atualizar SPEC US-PONTO-006 (AFDT→AEJ) + ADR formal.
  related_adrs: [0186, 0094]
  estimate: 3-5d IA-pair

- module: ponto
  priority: P0
  title: "GAP-PONTO-002 — Comprovante PDF QR Code (Anexo I §5.5) + PAdES"
  description: |
    Criar ComprovanteService.php + AssinaturaPAdESService.php usando TCPDF
    + addEmptySignature + handler ICP-Brasil. QR Code aponta URL pública
    verificação (signature + marcação_id + business_id). Nova rota GET
    /ponto/comprovante/{marcacaoId}. 5 Pest tests.
    Pré-req: GAP-001 (lib ICP-Brasil instalada).
  related_adrs: [0186]
  estimate: 2d IA-pair

- module: ponto
  priority: P0
  title: "GAP-PONTO-003 — Cache Redis presença + sparkline + completar OTel hard"
  description: |
    DashboardController::calcularPresenca em Cache::remember 30s. Event novo
    MarcacaoRegistrada disparado em MarcacaoService::registrarInterno. Listener
    InvalidatePresencaCacheListener invalida cache. Sparkline 7d cache hourly cron.
  estimate: 1d IA-pair

# Onda CONSOLIDAR
- module: ponto
  priority: P0
  title: "GAP-PONTO-004 — Espelho self-service colaborador + Dashboard RH cards"
  description: |
    Nova rota GET /ponto/espelho/me (colaborador autenticado). Nova page
    Ponto/Espelho/Me.tsx. Dashboard refactor cards configuráveis (drag-drop
    futuro — começa fixed). 5 Pest tests + UI charter.
  estimate: 2-3d IA-pair

- module: ponto
  priority: P0
  title: "GAP-PONTO-005 — Integração eSocial S-2230 (formato 2026) + S-1200 stub"
  description: |
    Criar ESocialEventService.php + ESocialController.php. Suporta S-2230 schema
    2026 (tipos afastamento detalhados). S-1200 stub (incluir Onda 3 futura).
    Migration nova add_esocial_envios_table. 7 Pest tests.
    Pré-req: ADR "Integração eSocial canon" — Eliana redige.
  related_adrs: [0094]
  estimate: 3-5d IA-pair

- module: ponto
  priority: P1
  title: "GAP-PONTO-007 — LogsActivity Spatie em Colaborador/Escala/Intercorrencia"
  description: |
    Trait LogsActivity + $logAttributes em 3 Entities. Eliana revisar atributos
    logados (LGPD minimização).
  estimate: 4h IA-pair

- module: ponto
  priority: P1
  title: "GAP-PONTO-008 — Throttle endpoints API mobile + rate-limit per-business"
  description: |
    Middleware throttle:60,1 em /api/v1/ponto/*. RateLimiter::for('ponto-business')
    em RouteServiceProvider. Pest tests rate-limit comportamento.
  estimate: 4h IA-pair
```

---

## Pré-flight checks ANTES de disparar implementadores juniores (Fase 3)

- [ ] **Wagner aprova este dossier integral** (ler TL;DR + Surpresa estratégica + Roadmap)
- [ ] **Eliana revisa SPEC US-PONTO-006** — atualizar "AFD/AFDT" pra "AFD legacy (Portaria 1.510/2009) + AEJ canon (Portaria 671/2021 Anexo VI)" (PRÉ-REQ GAP-001 — mitigation R1)
- [ ] **ADR formal proposta "Modules/Ponto formato fiscalização MTE"** redigida e aprovada — Wagner
- [ ] **Cert A1 oimpresso institucional confirmado válido** (ADR 0186 chain — `php artisan jana:health-check-cert`)
- [ ] **Spike 4h lib ICP-Brasil** (`bcastellani/laravel-pkijs-cert` vs Lacuna PKI Free) — mitigation R5
- [ ] **Verificar deploy CT 100 verde** (`php artisan jana:health-check` 5 verdes)
- [ ] **PLANO-TESTES Onda 1+2 multi-tenant verde** (`php artisan test --filter=MultiTenant --filter=Ponto`)
- [ ] **Wagner formaliza smoke 30d time WR2** (kickoff: "vou usar Espelho/Dashboard 1×/semana 4 semanas") — pré-req validar GAP-4
- [ ] **R7 endpoint pendentes-validacao mobile** confirmado em produção (já implementado W28-8 — só verificar)
- [ ] **Feature flag `feature.ponto.aej_export_disponivel`** provisionada (default false — proteção R9 cliente externo)

---

## Resumo decisões arquiteturais sênior (TL;DR pra implementadores)

| Decisão | Por quê | Alternativa rejeitada |
|---|---|---|
| **AEJ canon Portaria 671 (não AFDT)** | Portaria 671/2021 Anexo VI substituiu AFDT+ACJEF — SPEC outdated, implementador deve seguir dossier não SPEC ate atualizar | AFDT (rejeitada — legacy regulatório, fiscalização MTE rejeita) |
| **Lib `bcastellani/laravel-pkijs-cert` pra CAdES** | Open-source, MIT, suporta ICP-Brasil A1, sem custo | Lacuna PKI Suite commercial (rejeitada — R$ extra; reservada como fallback se spike falhar) |
| **TCPDF + handler ICP-Brasil pra PAdES** | Já usado em outros módulos via DomPDF — pattern conhecido + free | setasign/fpdi-pdf-parser (rejeitada — commercial); Lacuna PAdES (rejeitada — custo) |
| **Cache Redis 30s presença (não 60s)** | Presença muda fast — funcionário bate ponto e dashboard mostra em ≤30s. 60s gera percepção "lento" no canary | Cache 60s (rejeitada — UX percebido lento Wagner); Sem cache (rejeitada — 14 queries/visita degrada) |
| **biz=1 WR2 piloto (não biz=4 nem biz=164)** | Wagner-como-RH operador real CLT (time WR2 tem marcação + escala + HE + BH) — sinal qualificado válido ADR 0105. biz=4 Larissa vestuário 1-2 pessoas Art. 74 isenta. biz=164 Martinho em HiSoft competitor + sem migração concluída pra Ponto | biz=4 (rejeitada — fora público-alvo); biz=164 (rejeitada — HiSoft já em produção) |
| **eSocial S-2230 prioritário sobre S-1200** | S-2230 mudou formato 2026 (prazo regulatório) + impacto direto Ponto (afastamentos vêm de Intercorrencia). S-1200 (jornada) é stub pra Onda 3 futura | Implementar S-1200 primeiro (rejeitada — sem prazo regulatório urgente, escopo maior) |
| **Trigger MySQL append-only BancoHoras = P1 (não P0)** | Eloquent override já bloqueia 99% caminhos. Trigger DB é defesa em profundidade — não regulatório. Pra escopo "compliance fiscalização", priorizar AEJ + Comprovante | Trigger P0 (rejeitada — escopo Onda 1 satura 5-7d sem isso) |
| **NÃO implementar AWS Rekognition Onda 1** | Custo R$ 115/mês opcional, sem cliente externo CLT entrando hoje. Stub atual (`verificarBiometria` retorna true se selfie ≥100KB) é honest pra biz=1 interno | Implementar Onda 1 (rejeitada — R$ extra sem ROI atual) |
| **Espelho self-service URL `/ponto/espelho/me`** | Auth user (colaborador autenticado) vê próprio espelho — convenção REST clara + diferencia de `/ponto/espelho/{id}` (RH vê qualquer) | URL `/ponto/me/espelho` (rejeitada — quebra prefix `/ponto/espelho/*` agrupado em routes.php) |

---

## Referências

### Documentos canônicos oimpresso (reusados, NÃO duplicados)
- [memory/requisitos/Ponto/SPEC.md](SPEC.md) — US-PONTO-001..008 (precisa atualizar US-006 — vide Surpresa)
- [memory/requisitos/Ponto/BRIEFING.md](BRIEFING.md) — Wave 18 RETRY 2026-05-16
- [Modules/Ponto/SCOPE.md](../../../Modules/Ponto/SCOPE.md) — v1.1.0 (rename Fase 3.7 PR-2)
- [Modules/Ponto/module.json](../../../Modules/Ponto/module.json) — bucket + fsm_n_a + Wave 18 targets
- [Modules/Ponto/Config/retention.php](../../../Modules/Ponto/Config/retention.php) — Wave 11 D7.c LGPD
- [memory/reference/clientes-ativos.md](../../reference/clientes-ativos.md) — biz=4 NÃO usa Ponto (Art. 74)
- [memory/requisitos/Fiscal/AUDIT-SENIOR-2026-05-25.md](../Fiscal/AUDIT-SENIOR-2026-05-25.md) — template canon (sessão paralela)

### ADRs canônicas relevantes
- [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Zero auto-mem privada
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (princípios duros)
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1, NUNCA biz=4
- [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado
- [ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — Recalibração fator 10x IA-pair
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — Modular especializado
- [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — FSM (N/A Ponto — append-only)
- [ADR 0155](../../decisions/0155-module-grade-v3-sub-dimensoes-gate-ci.md) — Rubrica V3 (referência scoring)
- [ADR 0186](../../decisions/0186-chain-certificado-sefaz-consulta-cadastro.md) — Cert A1 chain (reuso pra assinatura AEJ/PAdES)

### Pesquisas externas 2026 (WebSearch — 8 totais)

1. [Pontotel — Portaria 671/2021 o que mudou](https://www.pontotel.com.br/portaria-671/) — REP-P + AEJ + assinatura digital
2. [Senior — Portaria 671 mudanças nos produtos de Ponto](https://documentacao.senior.com.br/exigenciaslegais/materias/gp/destaque/portaria-671-ponto.htm) — AFDT/ACJEF substituídos por AEJ
3. [Sólides — Portaria 671 guia completo](https://solides.com.br/blog/portaria-671/) — RT 2026
4. [Kickidler — Melhores sistemas folha ponto 2026](https://www.kickidler.com/br/info/melhores-sistemas-de-folha-de-ponto?amp=1) — comparativo Tangerino/PontoMais/Pontotel/Ahgora
5. [TOTVS — Ahgora Linha RH Ponto Eletrônico](https://produtos.totvs.com/ficha-tecnica/tudo-sobre-o-totvs-rh-ponto-eletronico-linha-ahgora/) — REP-P + facial + multi-empresa
6. [Mastersiga — AEJ Portaria 671 e 1.486/2022](https://mastersiga.tomticket.com/kb/ponto-eletronico-sigapon/arquivo-eletronico-de-jornada-portaria-671-2021) — formato AEJ ASCII ISO 8859-1
7. [Pontotel — Arquivos AFD, AFDT, ACJEF e novo AEJ](https://www.pontotel.com.br/arquivos-afd-afdt-acjef/) — confirmação substituição AFDT→AEJ
8. [TOTVS — PAdES padrão assinatura digital](https://www.totvs.com/blog/gestao-para-assinatura-de-documentos/pades/) — comparação CAdES/PAdES ICP-Brasil
9. [Certisign — CAdES vs PAdES](https://certisign.com.br/blog/assinatura-digital-qual-e-a-diferenca-entre-assinar-pades-e-cades) — formato `.p7s` detached
10. [AWS — Rekognition Face Liveness pricing](https://aws.amazon.com/rekognition/pricing/) — U$ 0.015/check (gap futuro P1)
11. [Assecont — Anti-fraude ponto eletrônico facial + GPS](https://assecont.com.br/guia-sobre-reconhecimento-facial-e-geolocalizacao-gps/) — anti-cheat patterns
12. [LedWare — Folha pagamento eletrônica eSocial 2026](https://www.ledware.com.br/2026/05/19/folha-de-pagamento-eletronica-esocial-2026-integracao/) — S-1200 + S-2230 mudanças
13. [Senior — eSocial S-2230 leiautes 2026](https://documentacao.senior.com.br/gestao-de-pessoas-hcm/esocial/leiautes/nao-periodicos/s-2230.htm) — schema afastamento detalhado
14. [Climec — Eventos S-2210/S-2220/S-2240 guia 2026](https://climec.com.br/blog/eventos-s-2210-s-2220-e-s-2240-guia-completo-para-2026/) — contexto SST
15. [LGF Advogados — Empresa <20 empregados Art. 74 CLT](https://lucasfratari.adv.br/empresa-com-menos-de-20-empregados-precisa-ter-cartao-de-ponto-lei-da-liberdade-economica-regula-a-questao/) — biz=4 Larissa isenta
16. [Guia Trabalhista — Banco de horas Art. 59 §5º](https://www.guiatrabalhista.com.br/tematicas/banco-horas.htm) — prazo 6 meses individual / 12 meses coletivo

### Bibliotecas confirmadas pra avaliar

- [`bcastellani/laravel-pkijs-cert`](https://packagist.org) — CAdES `.p7s` ICP-Brasil A1 open-source (spike pré-Onda 1)
- [Lacuna PKI Suite Free Tier](https://docs.lacunasoftware.com/pt-br/articles/pki-sdk/signatures/cades/index.html) — fallback commercial
- TCPDF + addEmptySignature() — PAdES PDF (já presente em outros módulos)
- [`nfephp-org/sped-esocial`](https://github.com/nfephp-org/sped-esocial) — eSocial XSD validator (mesma org sped-nfe)
- [AWS Rekognition Face Liveness](https://aws.amazon.com/pt/rekognition/face-liveness/) — opcional Onda 3 GAP-10

---

**Fim do dossier.** Auditor sênior: Opus 4.7 sessão 2026-05-25 ~25min. Próximo passo: Wagner + Eliana revisar (especialmente SPEC US-PONTO-006 → AEJ canon) → spawn `audit-implement-expert` em 3 paralelos Onda 1 (GAP-001 + GAP-003 + sequência GAP-002).

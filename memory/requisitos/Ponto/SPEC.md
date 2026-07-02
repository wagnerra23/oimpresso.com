---
module: Ponto
status: ativo
version: "1.1.0"
last_updated: "2026-05-25"
owners: [W, E]
parent_adr: 0094-constituicao-v2-7-camadas-8-principios
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0101-tests-business-id-1-nunca-cliente
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0155-rubrica-module-grade-v3
piloto: biz=1 WR2 Sistemas (Wagner operador interno — time CLT real) — NÃO biz=4 (Larissa vestuário <20 empregados Art. 74 CLT desobriga)
prazo_regulatorio_critico: AEJ canon Portaria 671/2021 Anexo VI (substitui AFDT) + eSocial S-2230 novo formato afastamento 2026
---

# SPEC — Modules/Ponto

> Modulo de controle eletronico de ponto eletronico do oimpresso, fundacao legal CLT + Portaria MTP 671/2021. Originalmente nasceu como Ponto WR2 (legacy Delphi/Firebird) e foi reimplementado em Laravel modular como prova-conceito de modulo Tier 1 com compliance forte.
>
> **Status atual (2026-05-25):** Backbone funcional (marcacao + REP-P + apuracao + intercorrencia + banco horas). Nota module-grade-v3 = **69/100**. Audit sênior 2026-05-25 identificou 5 gaps P0 + revelou que **AFDT está OUTDATED** (Portaria 671/2021 substituiu por AEJ canon — ver US-PONTO-006 + US-PONTO-009 nova).
> **Cliente piloto:** WR2 Sistemas (interno, biz=1) — homologa pre-cliente externo.
> **Multi-tenant:** Tier 0 IRREVOGAVEL ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

## Mission

Atender empregador BR (CLT) com **registro eletronico de ponto auditavel + imutavel + LGPD-compliant** que satisfaca AFD/AFDT (Portaria 671/2021 Anexo I), apuracao automatica de horas (jornada/HE/intervalo/banco horas), e workflow de intercorrencias (atestado/abono/falta) com aprovacao hierarquica.

## Non-Goals

- ❌ Folha de pagamento (handoff via eSocial S-1200/S-2299 — out of scope)
- ❌ Biometria facial/digital propria (REP-P certificado terceiros)
- ❌ Substituicao de REP-A homologado INMETRO (apenas REP-P web/mobile)

## Stakeholders

| Papel | Quem | Interesse |
|---|---|---|
| Dono | Wagner [W] | Compliance + diferencial vs concorrentes (Replicon, Tangerino, Pontotel) |
| Suporte CLT | Eliana[E] (advogada) | Lei aplicada certa (Art. 66, 71, 74 §2o) |
| Cliente piloto | WR2 (biz=1) | Funciona pra time interno antes de oferecer pra cliente externo |
| Auditor MTE | externo | Geracao AFD/AFDT integra a Portaria 671/2021 Anexo I |

## User Stories canon

> Numerac.: US-PONTO-NNN. Status: `done` (em prod) · `wip` (em sprint) · `backlog` (gap aberto).

### US-PONTO-001 · Relogio web pra registrar entrada/saida (REP-P)

**Implementado em:** _parcial_ · `Modules/Ponto/Services/MarcacaoService.php` · `Modules/Ponto/Services/NsrService.php` · `Modules/Ponto/Entities/Marcacao.php` · `Modules/Ponto/Tests/Feature/MarcacaoServiceTest.php` · verificado@8af585a (2026-07-02) — backend (hash encadeado SHA-256 + NSR) pronto, mas o endpoint REP-P web/API `/ponto/api/marcar` ainda é stub `abort(501)` e nao ha tela-relogio nem comprovante PDF/QR

**Como** colaborador,
**quero** marcar entrada/saida no celular ou desktop com 1 clique,
**para que** meu registro de jornada seja capturado em tempo real, com geolocalizacao e foto opcional.
**Aceitacao:**
- Marcacao gravada em `ponto_marcacoes` com `origem=REP_P`, `hash` SHA-256 encadeado, `created_at` automatico
- Geolocalizacao (lat/lon) e IP capturados se permitidos pelo navegador
- NSR (Numero Sequencial de Registro) gerado e unico por REP (constraint MySQL `unique(rep_id, nsr)`)
- Comprovante PDF gerado com QR Code de verificacao (Anexo I item 5.5 Portaria 671)
- **Status:** done (entity Marcacao + MarcacaoService + REP-P web frontend existente em prod biz=1)

### US-PONTO-002 · Marcacao via REP-A (importacao AFD)

**Implementado em:** _parcial_ · `Modules/Ponto/Services/AfdParserService.php` · `Modules/Ponto/Entities/Importacao.php` · `Modules/Ponto/Http/Controllers/ImportacaoController.php` · `Modules/Ponto/Console/Commands/ImportAfdCommand.php` · `Modules/Ponto/Tests/Feature/ImportacaoTest.php` · verificado@8af585a (2026-07-02) — parser AFDT (Portaria 671/2021) pronto, AFD legacy (1510/2009) parcial

**Como** RH,
**quero** importar arquivo AFD/AFDT de REP-A homologado,
**para que** marcacoes do equipamento sejam consolidadas no oimpresso sem digitacao manual.
**Aceitacao:**
- Suporta layouts AFD (Portaria 1.510/2009 — legacy) e AFDT (Portaria 671/2021 — atual)
- Validacao de integridade: NSR sequencial, hash encadeado, faltas detectadas
- `Modules/Ponto/Entities/Importacao` registra arquivo + checksum + linhas processadas + erros
- Importacao idempotente (mesma AFD pode ser re-uploadada sem duplicar marcacoes)
- **Status:** wip (parser AFDT pronto, AFD legacy parcial — ver `Importacao::ESTADO_*`)

### US-PONTO-003 · Workflow de intercorrencia (atestado/abono/falta)

**Implementado em:** `Modules/Ponto/Http/Controllers/IntercorrenciaController.php` · `Modules/Ponto/Services/IntercorrenciaService.php` · `Modules/Ponto/Services/IntercorrenciaAIClassifier.php` · `Modules/Ponto/Entities/Intercorrencia.php` · `Modules/Ponto/Tests/Feature/IntercorrenciaAIClassifierTest.php` · verificado@8af585a (2026-07-02)

**Como** colaborador,
**quero** registrar atestado medico / abono / pedido de folga,
**para que** ausencia seja justificada e nao desconte salario indevidamente.
**Aceitacao:**
- Estados: `RASCUNHO` → `PENDENTE` → `APROVADA` / `REJEITADA` → `APLICADA`
- Aprovador hierarquico (`solicitante_id`, `aprovador_id`, `aprovado_em`, `motivo_rejeicao`)
- Tipos canon: `ATESTADO`, `FALTA`, `ABONO`, `FERIAS`, `LICENCA`, `OUTROS`
- Anexo (atestado scaneado) em `anexo_path` com storage privado scoped por business
- Impacto em apuracao via flag `impacta_apuracao` e desconto banco horas via `descontar_banco_horas`
- **Status:** done (IntercorrenciaController + workflow + IntercorrenciaAIClassifier para sugerir tipo)

### US-PONTO-004 · Banco de horas com saldo + creditos/debitos

**Implementado em:** `Modules/Ponto/Http/Controllers/BancoHorasController.php` · `Modules/Ponto/Services/BancoHorasService.php` · `Modules/Ponto/Entities/BancoHorasMovimento.php` · `Modules/Ponto/Entities/BancoHorasSaldo.php` · `Modules/Ponto/Tests/Feature/BancoHorasTest.php` · verificado@8af585a (2026-07-02)

**Como** empregador,
**quero** acumular HE extras como banco de horas e debitar faltas,
**para que** o colaborador possa compensar sem custo de HE imediato.
**Aceitacao:**
- `ponto_banco_horas_saldo` mantem saldo atual por colaborador
- `ponto_banco_horas_movimentos` registra creditos/debitos (CREDITO, DEBITO, PAGAMENTO, EXPIRACAO, AJUSTE) — **append-only**
- Multiplicador HE configuravel (1.00 normal, 1.50 50%, 2.00 100% — Art. 7o XVI CF/88)
- Vinculacao com `apuracao_dia_id` e `intercorrencia_id` (rastreabilidade)
- Validade configuravel (acordo coletivo permite ate 6 meses — Art. 59 §5o CLT)
- **Status:** done (BancoHorasMovimento + BancoHorasSaldo + BancoHorasController)

### US-PONTO-005 · Apuracao automatica de jornada (Art. 66 + 71 CLT)

**Implementado em:** _parcial_ · `Modules/Ponto/Services/ApuracaoService.php` · `Modules/Ponto/Entities/ApuracaoDia.php` · `Modules/Ponto/Tests/Unit/ApuracaoServiceTest.php` · verificado@8af585a (2026-07-02) — falta calculo de HE 100% em domingo/feriado

**Como** RH,
**quero** apuracao automatica de horas trabalhadas, HE, intervalo intra/interjornada,
**para que** folha de pagamento receba dados validos sem retrabalho.
**Aceitacao:**
- `ponto_apuracao_dia` consolida por dia: horas trabalhadas, HE 50%, HE 100% (dom/feriado), intervalo concedido, faltas
- Regras CLT aplicadas:
  - **Art. 66:** intervalo interjornada minimo 11h consecutivas
  - **Art. 71 §1o:** intervalo intrajornada 1h se jornada >6h (tolerancia 5min via Portaria)
  - **Art. 71 §4o:** intrajornada nao concedido = horas extras com adicional 50%
- Tolerancia 10min/dia (5min entrada + 5min saida) — Art. 58 §1o CLT
- **Status:** wip (ApuracaoDia + apuracao service parcial — calculos HE 100% feriado wip)

### US-PONTO-006 · Geracao AFD legacy pra fiscalizacao MTE (REP-A INMETRO)

**Implementado em:** _pendente_ — backlog: `RelatorioController::gerar()` so tem esqueleto (`abort(501)`), gerador AFD legacy nao implementado; baixa prioridade (AEJ canon prioritario)

**Como** RH com REP-A legacy,
**quero** gerar arquivo AFD a qualquer momento,
**para que** auditor MTE possa exportar e verificar conformidade transitiva.
**Aceitacao:**
- Layout AFD (Portaria 1.510/2009 Anexo I) — REP-A homologado INMETRO ainda valido transitivamente
- Periodo selecionavel (dia, mes, intervalo custom)
- Filtro por REP e por colaborador
- Download em `.txt` UTF-8 sem BOM
- Hash SHA-256 do arquivo final exibido na tela
- **Status:** backlog (RelatorioController estrutura pronta, gerador AFD legacy por implementar — baixa prioridade hoje, AEJ canon prioritário)
- **⚠️ Audit sênior 2026-05-25:** AFDT REMOVIDO desta US — Portaria 671/2021 substituiu AFDT + ACJEF por **AEJ** (Anexo VI). Ver US-PONTO-009 nova.

### US-PONTO-009 · Geracao AEJ canon Portaria 671/2021 Anexo VI (CRITICO REGULATORIO)

**Implementado em:** _pendente_ — GAP-PONTO-001: gerador AEJ + assinatura CAdES `.p7s` nao implementado (`RelatorioController::gerar('aej')` ainda `abort(501)`); exige revisao Eliana + ADR formal antes de codar

**Como** RH,
**quero** gerar arquivo AEJ (Arquivo Eletronico de Jornada) com assinatura CAdES `.p7s`,
**para que** fiscalizacao MTE aceite auditoria (REP-P canon pos-2021).
**Aceitacao:**
- Layout AEJ Anexo VI Portaria 671/2021 — ASCII ISO 8859-1
- Assinatura digital CAdES detached `.p7s` (lib `phpseclib` ou equivalente)
- Cert A1 oimpresso institucional reutilizado (ADR 0186 chain Fiscal)
- 5 tipos de registro: cabecalho, identificacao empregador, marcacoes, ajustes, trailer
- Periodo selecionavel + filtro REP + colaborador
- Download ZIP contendo `.txt` AEJ + `.p7s` assinatura
- Pest fixtures fiscal MTE compliance + smoke biz=1 WR2 (Wagner operador interno CLT real)
- **Status:** backlog (GAP-PONTO-001 audit senior 2026-05-25 — Onda 1 prioridade #1 regulatoria)
- **Eliana revisao OBRIGATORIA** ANTES implementacao (regulatorio CLT — risco R1 alta probabilidade × alto impacto)
- **Esforco estimado:** 3-5 dev-days IA-pair (fator 10x ADR 0106)
- **Pre-req:** ADR formal "Ponto = compliance CLT append-only" + revisao Eliana SPEC AEJ vs ACJEF antigo

### US-PONTO-010 · Comprovante PDF QR Code (Anexo I §5.5 Portaria 671)

**Implementado em:** _pendente_ — GAP-PONTO-002: nao existe comprovante PDF por marcacao com QR Code nem endpoint publico de verificacao (`ReportService` so gera espelho mensal); depende de US-PONTO-009 (cert A1 + assinatura)

**Como** colaborador,
**quero** baixar comprovante PDF da minha marcacao com QR Code verificavel,
**para que** posso provar registro perante terceiros (sindicato, processo trabalhista).
**Aceitacao:**
- PDF gerado server-side (dompdf ou similar) com PAdES (assinatura embedded)
- QR Code contem hash SHA-256 da marcacao + URL publica de verificacao
- Endpoint publico GET /ponto/comprovante/{hash}/verificar (sem auth — verificacao 3os)
- Pest verifica hash em QR match com banco
- **Status:** backlog (GAP-PONTO-002 audit senior 2026-05-25)
- **Pre-req:** US-PONTO-009 (cert A1 chain + assinatura CAdES estabelecida primeiro)

### US-PONTO-007 · Multi-tenant isolation (Tier 0 IRREVOGAVEL)

**Implementado em:** `Modules/Ponto/Entities/Marcacao.php` · `Modules/Ponto/Tests/Feature/MultiTenantIsolationTest.php` · `Modules/Ponto/Tests/Feature/CrossTenantMarcacaoTest.php` · `Modules/Ponto/Tests/Feature/MultiTenantAppendOnlyTest.php` · verificado@8af585a (2026-07-02)

**Como** plataforma SaaS,
**preciso** que dados de um business NUNCA vazem pra outro,
**para que** LGPD Art. 7o + sigilo trabalhista sejam preservados.
**Aceitacao:**
- Toda Eloquent Model do modulo (Marcacao, Intercorrencia, BancoHorasMovimento, BancoHorasSaldo, Colaborador, Escala, Importacao, Rep, ApuracaoDia, EscalaTurno) tem `business_id` indexado + FK
- Pest cross-tenant biz=1 vs biz=99 cobrindo SELECT scoped + INSERT bulk + JOIN
- Jobs assincronos recebem `$businessId` no constructor — session() proibido em fila ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- **Status:** done (cobertura adicionada Wave Massive 2026-05-16 — `MultiTenantAppendOnlyTest` + `CrossTenantMarcacaoTest`)

### US-PONTO-008 · Imutabilidade append-only (Portaria 671/2021)

**Implementado em:** `Modules/Ponto/Database/Migrations/2026_04_18_000004_create_ponto_marcacoes_table.php` · `Modules/Ponto/Entities/Marcacao.php` · `Modules/Ponto/Entities/BancoHorasMovimento.php` · `Modules/Ponto/Tests/Feature/MultiTenantAppendOnlyTest.php` · verificado@8af585a (2026-07-02) — trigger MySQL BEFORE UPDATE/DELETE + override Eloquent (defesa em profundidade)

**Como** auditor MTE,
**preciso** que marcacoes nao possam ser alteradas ou deletadas apos gravadas,
**para que** confiabilidade legal do registro seja preservada.
**Aceitacao:**
- `ponto_marcacoes` — trigger MySQL `BEFORE UPDATE` e `BEFORE DELETE` SIGNAL SQLSTATE '45000' bloqueia
- `Marcacao::update()` e `Marcacao::delete()` em PHP lancam `RuntimeException` (defesa em profundidade)
- `BancoHorasMovimento::update()` e `delete()` idem (saldo deve ser auditavel)
- Para "corrigir": criar marcacao com `origem=ANULACAO` apontando a original via `marcacao_anulada_id`
- Pest cobre tentativa UPDATE/DELETE e confirma exception
- **Status:** done (cobertura adicionada Wave Massive 2026-05-16 — `MultiTenantAppendOnlyTest`)

## Tabelas canon

| Tabela | Append-only? | business_id scope | Imutabilidade |
|---|---|---|---|
| `ponto_marcacoes` | sim | sim | trigger MySQL + Eloquent override |
| `ponto_banco_horas_movimentos` | sim | sim | Eloquent override (lacuna trigger DB — backlog) |
| `ponto_intercorrencias` | nao (workflow PENDENTE→APROVADA) | sim | SoftDeletes |
| `ponto_banco_horas_saldo` | nao (saldo atualiza) | sim | atualizado via observer/transaction |
| `ponto_apuracao_dia` | regravavel (recalculavel) | sim | unique(business_id, colab, dia) |
| `ponto_colaborador_config` | nao | sim | bridge employees + escala atual |
| `ponto_escalas` / `ponto_escalas_turnos` | nao | sim | historico via versao |
| `ponto_reps` | nao | sim | unique serial+business |
| `ponto_importacoes` | nao | sim | uploaded_at + checksum |

## Skills relacionadas

`preflight-modulo` (Tier A) · `multi-tenant-patterns` (Tier A) · `commit-discipline` (Tier A) · `criar-modulo` (Tier B) · `module-completeness-audit` (Tier B)

## ADRs relacionados

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant isolation Tier 0 IRREVOGAVEL
- [ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md) — Tests biz=1 (Wagner interno) nunca biz=4 (cliente real)
- [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Zero auto-mem privada
- [ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md) — Modular especializado por vertical

## Referencias legais

- **CLT** Art. 58 §1o (tolerancia 10min), Art. 59 (HE + banco horas §5o validade 6m), Art. 66 (interjornada 11h), Art. 71 §1o (intrajornada 1h se >6h), Art. 74 §2o (registro obrigatorio >20 empregados)
- **Portaria MTP 671/2021** Anexo I (integridade hash, comprovante QR Code, fiscalizacao online) + **Anexo VI (AEJ — Arquivo Eletronico de Jornada)** — substitui AFDT + ACJEF pos-2021
- **Portaria MTE 1.510/2009** (AFD legacy — REP-A homologado INMETRO — ainda valido transitivamente)
- **⚠️ AFDT está deprecated regulatoriamente** (Portaria 671/2021 substituiu por AEJ). US-PONTO-006 atualizada 2026-05-25 — AFDT removido, AFD legacy mantido pra REP-A, AEJ canon vira US-PONTO-009 nova.
- **LGPD** Art. 7o II (cumprimento obrigacao legal — base legal pra tratamento de dado de jornada)
- **CF/88** Art. 7o XVI (adicional 50% HE)

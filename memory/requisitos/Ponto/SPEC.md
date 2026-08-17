---
id: requisitos-ponto-spec
module: Ponto
status: ativo
version: "1.2.0"
last_updated: "2026-08-03"
owners: [W, E]
parent_adr: 0094-constituicao-v2-7-camadas-8-principios
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0101-tests-business-id-1-nunca-cliente
  - 0121-oimpresso-modular-especializado-por-vertical
  - 0155-module-grade-v3-sub-dimensoes-gate-ci
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

### US-PONTO-011 · Fechar o append-only do ledger de banco de horas

**Implementado em:** _pendente_ — GAP-PONTO-003: `BancoHorasMovimento` sobrescreve `update()`/`delete()` mas NAO `save()`, e a tabela nao tem trigger MySQL (SDD §9 D-6). Reprovado por `UC-BHSHOW-01` na lane em 2026-08-03.

**Como** auditor de banco de horas,
**preciso** que movimento gravado nao possa ser alterado por nenhum caminho do ORM,
**para que** o extrato mantenha valor probatorio em reclamatoria (CLT Art. 59 §5o).
**Aceitacao:**
- `$mov->minutos = X; $mov->save()` em movimento existente FALHA (hoje grava — `Model::save()` chama `performUpdate()` sem passar pelo `update()` publico)
- Caminho: guard no evento `saving` rejeitando `exists === true`, OU trigger MySQL como em `ponto_marcacoes` (defesa em profundidade, que e o que a irma tem)
- Fix medido como seguro: producao so usa `BancoHorasMovimento::create(...)`; varredura contada = ZERO `save()` em movimento existente
- **Janela barata:** medido em prod 2026-08-03 — `ponto_banco_horas_movimentos` = 0 e `ponto_banco_horas_saldo` = 0. Impacto em dados e 0→0, o que satisfaz a REGRA MESTRE trivialmente. Depois do modulo entrar em uso, exige auditoria de saldo.
- DoD: `UC-BHSHOW-01` verde na lane `ponto-pest`
- **Status:** todo (Tier 0 `[V0]` — exige aprovacao [W] antes de codar)

### US-PONTO-012 · Corrigir os atributos fantasma do modulo (4 instancias)

**Implementado em:** _pendente_ — GAP-PONTO-004: padrao nomeado pelo SDD §9 (D-1/D-8); a varredura de 2026-08-03 fechou a pendencia do §10 Onda 1 e achou 2 instancias novas.

**Como** RH que fecha folha,
**preciso** que a tela mostre o que esta gravado no banco,
**para que** eu nao decida sobre numero que a interface inventou.
**Aceitacao:**
- `EspelhoController` le `tem_divergencia` — nao e coluna nem accessor (a verdade e `estado === DIVERGENCIA`). O RH fecha folha sem ver violacao de Art. 66/71 → `UC-ESPSHOW-01`
- `EscalaController@edit` le `entrada`/`saida`/`almoco_inicio`/`almoco_fim` — as colunas sao `hora_*`. A edicao de escala mostra TODOS os horarios vazios, sempre → `UC-ESCF-01`
- `ImportacaoController` le `linhas_criadas`/`linhas_ignoradas` (reais: `linhas_sucesso`/`linhas_erro`) em `index` E `show` → `UC-IMPIDX-03` e `UC-IMPSHOW-04`
- `ImportacaoController` le `erro_mensagem` (reais: `log`/`erros_amostra`); o `Show.tsx:82` faz `{i.erro_mensagem && <Alert>}`, logo o alerta de erro NUNCA renderiza → vira `UC-IMPSHOW-05`
- Os testes assertam COMPORTAMENTO, nao a chave literal — ha mais de uma correcao legitima (renomear a leitura, accessor, ou `$appends`), e assert por chave reprovaria as outras
- DoD: os 4 UCs verdes na lane `ponto-pest`
- **Status:** todo

### US-PONTO-013 · Consertar as duas telas que nao persistem

**Implementado em:** _pendente_ — GAP-PONTO-005: dois caminhos de escrita quebrados, causas independentes, ambos provados pela lane em 2026-08-03.

**Como** operador de RH,
**preciso** que registrar intercorrencia e salvar escala gravem de fato,
**para que** o que eu digito nao se perca em silencio.
**Aceitacao:**
- **Intercorrencia nao grava** (`CU-PONTO-05` · prova `UC-INTCRE-01`): `business_id` nunca e atribuido — o `IntercorrenciaRequest` nao declara a chave, o `Service::criar()` seta so `codigo`/`solicitante_id`/`estado`, o `creating` so gera UUID, o trait `HasBusinessScope` so adiciona scope de LEITURA, ha 0 `observe()` no modulo, e a coluna e NOT NULL + FK sem default. O Service denuncia que sabia: usa `($dados['business_id'] ?? 0)`
- **Escala nao salva** (prova `UC-ESCF-02`): `EscalaController@update` recebe `Illuminate\Http\Request` e chama `$request->validated()` — metodo que so existe em `FormRequest` (medido: 0 em `Http/Request.php`, 0 macros no projeto). O `store()` funciona, entao so a edicao quebra. O padrao certo esta no mesmo modulo: `IntercorrenciaController` usa `IntercorrenciaRequest`
- **Alerta de documentacao:** o SDD §5.3 F4 descreve o fluxo de intercorrencia como se funcionasse e US-PONTO-003 estava marcada implementada. Nenhum teste exercitava o `store()` por HTTP. Vale conferir os outros "Implementado em:" do modulo
- Prioridade baixa porque o modulo NAO tem uso em prod (medido 2026-08-03: 0 marcacoes, 0 intercorrencias, 0 escalas). **Sobe para p0 no dia em que alguem for bater ponto**
- DoD: `UC-INTCRE-01` e `UC-ESCF-02` verdes na lane `ponto-pest`
- **Status:** todo

### US-PONTO-014 · Lane required de Ponto vira arvore-menos-quarentena (vermelha no main ha 5 runs; 27 de 38 testes fora da allowlist)

> owner: — · priority: p0 · estimate: 5h · status: todo · type: story
> blocked_by: —

**Implementado em:** _pendente_ — o workflow `.github/workflows/ponto-pest.yml` segue com allowlist inline; `.github/ponto-pest-quarantine.list` nao existe.

- Contexto: a lane `PHP / Pest (Ponto · MySQL)` e **required** (`governance/required-checks-baseline.json`, promovida 2026-08-05 pela ADR 0369, Tier 0 por Portaria MTP 671/2021) e os **5 ultimos runs no `main` estao em `failure`**. O step que falha e `Run Pest (Ponto · MySQL) — ALLOWLIST VERDE (catraca)` — falha de teste, nao de infra (ADR 0369 classificou 5, 0 infra).
- **A prioridade e do GATE, nao do modulo.** O modulo nao tem uso em prod (medido 2026-08-03: 0 marcacoes / 0 intercorrencias / 0 escalas — ver US-PONTO-013), mas a lane e required e fica vermelha pra qualquer PR que toque os paths de Ponto; e gate permanentemente vermelho nao detecta regressao nova.
- Medido em `origin/main` 2026-08-07 — o buraco aqui e **maior** que o do Estoque: arvore `Modules/Ponto/Tests/**Test.php` = **38** · nomeados na allowlist inline = **11** · **fora = 27 (71%)**.
- Entre os 27 ha nomes que, **pelo nome**, cobrem contrato duro: `CrossTenantMarcacaoTest`, `MultiTenantIsolationTest`, `MultiTenantAppendOnlyTest`, `Wave11LgpdComplianceTest`, `Wave18BusinessScopeTest`, `BancoHorasTest`, `EspelhoTest`. O que eles de fato provam **nao foi medido** — o nome e promessa, nao recibo.
- ⚠️ `Modules/Ponto/Tests/{Feature,Unit}` **estao** no `phpunit.xml` (linhas 36-37), mas **registro nao e execucao** (§5 proibicoes 2026-08-02). Antes de afirmar que os 27 "nao rodam em lugar nenhum", exigir as duas pernas: varredura repo-wide com `rg --hidden` (§5 2026-07-30) **+** consulta ao dono do inventario (§5 2026-07-28).
- Receita ja provada 2× no repo: `.github/workflows/financeiro-pest.yml` (origem) e `.github/workflows/estoque-pest.yml` ([#5387](https://github.com/wagnerra23/oimpresso.com/pull/5387), mergeado 2026-08-07 — o `main` saiu de 3 `failure` seguidas para `success` em `888db02a6c4`).

**Acceptance:**
- [ ] `run-set` = tudo em `Modules/Ponto/Tests/**Test.php` **menos** `.github/ponto-pest-quarantine.list` (particao total — arquivo novo entra rodando)
- [ ] cada linha da quarentena nomeia **qual contrato falha**; sem motivo escrito vira gaveta
- [ ] step de anti-apodrecimento: falha se path listado nao existir mais
- [ ] quarentena impressa por extenso em todo run
- [ ] `merge=union` pro `.list` no `.gitattributes`
- [ ] status dos 27 medido **um a um** no CT 100, nao presumido
- [ ] lane verde no `main` — com `assertions > 0` no sumario JUnit, nao so `0 failed` (LC-13)

⛔ **Tier 0 — nao vale silenciar por conveniencia:** `ponto_marcacoes` e append-only por forca de lei (Portaria 671/2021). Teste de imutabilidade, cross-tenant ou LGPD que hoje esta fora da allowlist **nao entra na quarentena so pra fechar a lane** — se ele falhar, a falha **e o achado**, e a correcao e decisao [W].

**Refs:** ADR 0369 · PR #5387 (modelo) · §5 proibicoes 2026-08-04 (isencao que esvazia o gate) · LC-13.

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

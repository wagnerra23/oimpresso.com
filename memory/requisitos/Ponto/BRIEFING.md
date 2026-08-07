---
id: requisitos-ponto-briefing
module: Ponto
status: parcial
updated_at: "2026-08-07"
distilled_at: "2026-08-07"
distilled_by: "manual [C] — redestilação PARCIAL: só a §Cobertura de teste foi re-medida (arvore vs allowlist da lane required `ponto-pest`, origin/main 2026-08-07) e ganhou a ressalva presença≠execução. O resto do corpo NÃO foi re-lido — o retrato de 2026-07-27 (SDD + contratos de tela, PR #4865) segue valendo para as demais seções."
---

# BRIEFING — Modules/Ponto

> 1-pager executivo do modulo de ponto eletronico do oimpresso.
> **Audiencia:** Wagner (dono), Eliana (advogada/LGPD-CLT), Felipe/Maiara (suporte+dev), Luiz (dev IA-pair).
> **Atualizar a cada PR que altere capacidades/diferenciais.** Skill `brief-update` Tier B auto-ativa.

## Wave 18 RETRY (2026-05-16) — saturação D1/D5/D8

- **D1 Multi-tenant:** `EscalaTurno` recebeu trait `BelongsToBusinessViaParent` (parent = Escala via `escala_id`). Demais 10 Entities Ponto já tinham `HasBusinessScope` (Waves 11+18). **Marcação preservada APPEND-ONLY** (Portaria 671/2021 — `update()`/`delete()` lançam `RuntimeException`).
- **D5 Cliente:** `CustomerJourneyTest` (Wave 15) cobre jornada completa funcionário: 4 marcações dia + anulação correta (nova marcação com `ORIGEM_ANULACAO`+`marcacao_anulada_id`, original intacta) + cross-tenant biz=1 vs biz=99.
- **D8 Segurança:** novo `StoreEscalaRequest` (FormRequest) valida limites CLT Art. 58/59/7º (jornada ≤12h, semana ≤44h, tipos canônicos). Validação centralizada (SoC) + 8 cenários Pest sem touch DB.

## Em uma frase

Ponto eletronico CLT-compliance (Portaria MTP 671/2021) com **marcacao append-only imutavel + workflow de intercorrencias + banco de horas com saldo auditavel**, multi-tenant Tier 0, integravel com eSocial.

## Mercado e posicionamento

| Concorrente | Stack | Forca | Onde oimpresso ganha |
|---|---|---|---|
| **Tangerino** | SaaS BR maduro | UX simples, integracao Folha | Modular (junto com financeiro/NFe/Jana IA — sem 3 fornecedores) |
| **Pontotel** | SaaS BR | Reconhecimento facial | Multi-tenant Tier 0 IRREVOGAVEL (LGPD por design) + Jana IA classificando intercorrencia |
| **Sentinela** | SaaS BR | Compliance MTE forte | Stack moderna (Laravel 13 + React 19), append-only auditavel via SHA-256 chain |
| **Replicon** | Global enterprise | Multi-pais | Preco BR + dominio CLT/Portaria 671 nativo (nao adaptado) |

## Stack e arquitetura

- **Backend:** `Modules/Ponto/` (nWidart) — Laravel 13.6 + PHP 8.4
- **Entities (10):** Marcacao, Intercorrencia, BancoHorasMovimento, BancoHorasSaldo, ApuracaoDia, Colaborador, Escala, EscalaTurno, Rep, Importacao
- **Controllers (12):** Aprovacao, BancoHoras, Colaborador, Configuracao, Dashboard, Data, Escala, Espelho, Importacao, Install, Intercorrencia, Relatorio
- **Append-only:** `ponto_marcacoes` (trigger MySQL + Eloquent override), `ponto_banco_horas_movimentos` (Eloquent override)
- **Frontend:** React 19 + Inertia v3 (Pages/PontoWr2/ migration parcial — Blade legacy ainda presente)
- **IA:** `IntercorrenciaAIClassifier` sugere tipo de intercorrencia via Jana/laravel-ai

## Capacidades canon

✅ **Em prod (biz=1 WR2 interno):**
- REP-P web (marcacao com 1 clique + geolocalizacao + IP + hash encadeado)
- Workflow intercorrencia RASCUNHO→PENDENTE→APROVADA→APLICADA
- Banco horas com saldo + 5 tipos de movimento append-only
- Apuracao dia (ApuracaoDia entity + service parcial)
- AI classifier de intercorrencia (Jana sugere tipo)
- Imutabilidade `ponto_marcacoes` via trigger MySQL

🟡 **Wip:**
- Apuracao HE 100% (feriados/domingos — Art. 7o XVI CF/88) parcial
- Importacao AFD legacy (Portaria 1.510/2009) parser parcial

❌ **Backlog (gap auditoria 35/100):**
- Geracao AFDT pra fiscalizacao MTE (RelatorioController estrutura pronta, gerador por implementar)
- Comprovante PDF QR Code (Anexo I 5.5 Portaria 671)
- Espelho ponto colaborador self-service (visualizacao + correcao via intercorrencia)
- Integracao eSocial S-1200 (events trabalhista)
- Dashboard RH com cards (faltas dia, HE acumulada, intercorrencias PENDENTES)

## Compliance / leis aplicadas

- **CLT** Art. 58 §1o (tolerancia 10min), Art. 59 (HE + banco horas), Art. 66 (interjornada 11h), Art. 71 §1o (intrajornada 1h se >6h), Art. 74 §2o (>20 empregados obrigatorio)
- **Portaria MTP 671/2021** Anexo I (AFDT, hash chain, comprovante QR, fiscalizacao online)
- **LGPD** Art. 7o II (base legal cumprimento obrigacao legal)
- **CF/88** Art. 7o XVI (adicional 50% HE)

## Tier 0 IRREVOGAVEL

- ⛔ **Marcacao append-only** — `Marcacao::update()` lanca exception, trigger MySQL bloqueia DELETE/UPDATE. Pra corrigir, criar `origem=ANULACAO` apontando original via `marcacao_anulada_id` (lei Portaria 671)
- ⛔ **business_id scope obrigatorio** em todas Models de negocio (ADR 0093)
- ⛔ **Jobs assincronos** sempre recebem `$businessId` no constructor — `session()` nao funciona em fila
- ⛔ **PIIs reais** (CPF/CNPJ colaborador) NUNCA em log/PR/commit — usar `PiiRedactor`

## Cobertura de teste

| Test | Cobertura | Status |
|---|---|---|
| `MultiTenantIsolationTest` (legacy class-style + PontoTestCase) | Rotas GET + session scope | ✅ existente |
| `MultiTenantAppendOnlyTest` (Pest functional) | Append-only Marcacao + BancoHorasMov + Intercorrencia scoped | ✅ adicionado Wave Massive 2026-05-16 |
| `CrossTenantMarcacaoTest` (Pest functional) | Anti-vazamento bidirecional biz=1↔biz=99 + JOIN ANULACAO | ✅ adicionado Wave Massive 2026-05-16 |
| `AprovacaoTest` / `BancoHorasTest` / `DashboardTest` / `IntercorrenciaAIClassifierTest` / `ModuleManagerTest` / `SpatiePermissionsTest` / `TelasNavegacaoTest` | Smoke + workflow + permissoes | ✅ existentes |

> ⚠️ **O `✅` desta tabela e PRESENCA, nao EXECUCAO** (medido 2026-08-07, `origin/main`). A lane `PHP / Pest (Ponto · MySQL)` — **required** desde 2026-08-05 (ADR 0369) — seleciona por allowlist inline: **38 arquivos na arvore, 11 nomeados, 27 fora (71%)**. **Todos os 7 testes das 3 primeiras linhas desta tabela estao entre os 27 de fora** — incluindo `MultiTenantIsolationTest`, `MultiTenantAppendOnlyTest` e `CrossTenantMarcacaoTest`, que sao justamente os que defendem o Tier 0 append-only da Portaria 671/2021.
>
> Estar fora da lane de PR **nao** significa "nunca roda": a nightly do CT 100 (`ct100-fullsuite.sh` → `shards-plan --roots tests,Modules`) varre por diretorio. O que se pode afirmar com recibo e que **nenhum gate de merge os executa**. Qual deles hoje passa e **dado a medir**, um a um — nao presumir.
>
> A lane esta em `failure` nos 5 ultimos runs do `main` (step `Run Pest — ALLOWLIST VERDE`, falha de teste, nao de infra). Conserto rastreado em **US-PONTO-014**; a receita ja esta provada 2× no repo (`financeiro-pest.yml` e `estoque-pest.yml`, [#5387](https://github.com/wagnerra23/oimpresso.com/pull/5387)).

## Nota auditoria (estado 2026-05-16)

**35/100 (Critico)** — gaps:
- D1 (compliance + capacidades core) 6/30 → AFDT generator + comprovante PDF QR + Espelho self-service
- D3 (cobertura Pest) 0/15 → multi-tenant + append-only adicionados Wave Massive 2026-05-16 (estimativa +8 pts)
- D5 (documentacao SPEC+BRIEFING+ADRs) 3/15 → SPEC + BRIEFING criados Wave Massive 2026-05-16 (estimativa +9 pts)

**Projecao pos-Wave Massive:** ~52/100 (Bom). Pra chegar a 80+ (Estado-da-arte): AFDT generator + eSocial + Espelho self-service + Dashboard RH.

## Cliente piloto

**WR2 Sistemas (biz=1)** — Wagner usa pra time interno. Pre-cliente externo (ainda nao oferecido a ROTA LIVRE biz=4 — vestuario nao precisa).

## Skills relacionadas

`preflight-modulo` (Tier A) · `multi-tenant-patterns` (Tier A) · `module-completeness-audit` (Tier B) · `comparativo-do-modulo` (Tier B)

## Atualizado

- **2026-05-16** — Wave Massive: criado SPEC.md + BRIEFING.md + Pest multi-tenant append-only/cross-tenant (US-PONTO-007 + US-PONTO-008 status `done`)

## Fusões absorvidas (KL-E2)

Este módulo **absorveu** (fusão FUNDIR, KL-E2) a pasta tombstoneada **PontoWr2** — redireciona pra cá. As 12 US-PONT órfãs ficaram `status: historical` in-place com ponteiro pra Ponto. Ver [_TRIAGEM-IDENTIDADE-2026-06.md](../_TRIAGEM-IDENTIDADE-2026-06.md) §"Estado de execução E2/E3" (fusões FUNDIR, redirects #2750/#2757, fechamento #3653).

## Contrato de tela (SDD)

O módulo passou a ter **SDD** em [`SDD-espelho-e-jornada-v1.0.md`](SDD-espelho-e-jornada-v1.0.md) — §5 fluxos + §6 casos de uso — e `casos.md` por tela,
gerados pelo chip `sdd-from-source` ([ADR 0351](../../decisions/0351-sdd-from-source.md), PR #4865).

> **Contagem viva — não copiada aqui** (CU · UC · telas cobertas · onde a cadeia quebra):
> `node scripts/governance/requisitos-status.mjs Ponto`
>
> O painel derivado fica em [`_STATUS-GENERATED.md`](_STATUS-GENERATED.md). Número escrito à mão apodrece —
> este doc aponta para o dono, não restateia (proibições §5, 2026-07-17).

---
id: requisitos-ponto-briefing
module: Ponto
status: parcial
updated_at: "2026-09-05"
distilled_at: "2026-09-05"
distilled_by: "manual [C] — redestilação PARCIAL (PR #6802): DUAS seções re-lidas contra medição fresca em 2026-09-05. (1) §Cobertura de teste — os números de 2026-08-07 caducaram e foram REMEDIDOS (árvore 41 · allowlist 39 · fora 2, contra 38/11/27 do retrato antigo; lane no main 9 success / 1 failure nos últimos 10, contra 'failure nos 5 últimos'); o texto velho fica registrado como fato datado, não apagado. (2) §Atributos fantasma — nova, com o veredito por-UC do run 33942364334. O RESTO do corpo NÃO foi re-lido: §Contratos de tela segue no retrato de 2026-08-21 e as demais no de 2026-07-27 (PR #4865)."
---

# BRIEFING — Modules/Ponto

## Contratos de tela — estado em 2026-08-21

Os contratos de tela do Ponto (mecanismo v1 — [RUNBOOK-contrato-de-tela](../_DesignSystem/RUNBOOK-contrato-de-tela.md),
decidido na [ADR 0290](../../decisions/0290-fidelity-lock-v0-recusado.md); o princípio da catraca semântica vem da
[ADR 0286 §5](../../decisions/0286-channel-health-corroborado-por-mensagem-real.md), cujo tema principal é outro) desceram em
2026-08-21 por decisão [W] (**opção B**: entram só os contratos cujo alvo existe). `ponto-fechamento`
e `ponto-rep-p` ficaram **retidos** — suas telas (`Fechamento.tsx`, `Conformidade.tsx`, `RepP.tsx`,
`RepP/Validacao.tsx`) não existem e dependem de decisões [W] em aberto.

Os 2 que entraram nasceram **vermelhos por desenho**, com o conserto nomeado: o F3 das duas telas.
Medido com `node scripts/contrato-de-tela.mjs --contract <c>`:

| Contrato | Alvo | Ao descer (21/08) | Natureza do F3 |
|---|---|---|---|
| `ponto-painel` | `Ponto/Dashboard/Index.tsx` | ❌ 12 (4 âncoras + 8 copies) | **renomear copy** — as seções existiam e as props já chegavam; só `painel-nota-fechamento` custou backend (`divergencias_mes`) |
| `ponto-espelho` | `Espelho/Show.tsx` + `Index.tsx` | ❌ 26 (5 âncoras + 21 de 29 copies) | **construir** — as 21 copies estavam ausentes nos dois arquivos |

⚠️ **Consequência operacional que vale saber:** o job `Preflight + contratos ativos` varre **todos**
os `*.contract.json` do repo sempre que qualquer `.tsx` muda. Enquanto um contrato do Ponto estiver
vermelho, ele aparece em **todo PR de UI do projeto**, não só nos do Ponto. Ele **não** está entre os
required (medido em 2026-08-21 na união `classic_protection.contexts ∪ rulesets.contexts`), logo não
bloqueia merge — mas é ruído que treina o time a ignorar gate.

### RUNBOOKs (F1) — 2026-08-21

O módulo não tinha nenhum RUNBOOK, e o hook `block-mwart-violation` barra `Edit`/`Write` em Page sem
ele (único enforcement de RUNBOOK desde a ADR 0271 onda 2, **sem override**). Criados:

- [`RUNBOOK-dashboard.md`](RUNBOOK-dashboard.md) — cobre `Ponto/Dashboard/Index`
- [`RUNBOOK-espelho.md`](RUNBOOK-espelho.md) — cobre `Ponto/Espelho/Show` e `Index`

O do Espelho carrega o que essa tela tem de diferente das outras do projeto: é **documento legal**
(Portaria MTP 671/2021 Art. 85), a copy é **paridade com o Blade** que já roda (não redação nova),
expõe **CPF e PIS** em tela, e duas strings do contrato usam **MINUS SIGN U+2212** — que o olho não
distingue de hífen e o gate compara exato.


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
> **⟵ O paragrafo acima e o retrato de 2026-08-07 e CADUCOU. Remedido em 2026-09-05:** a arvore tem **41** arquivos `Modules/Ponto/Tests/**Test.php`, **39 na allowlist**, **2 fora** — e os 2 sao `Tests/Unit/ApuracaoServiceTest` e `Tests/Unit/MarcacaoServiceTest`. Os **10** testes nomeados na tabela acima, inclusive os tres guardas Tier 0 (`MultiTenantIsolationTest`, `MultiTenantAppendOnlyTest`, `CrossTenantMarcacaoTest`), estao **todos DENTRO** da lane — os ratchets de 2026-08-23 e 2026-08-24 fecharam o buraco de 71%. O texto anterior segue registrado por ser fato datado, nao apagado.
>
> **Estado da lane, tambem remedido em 2026-09-05:** ultimos 10 runs no `main` = **9 `success` · 1 `failure`**. A afirmacao anterior (*"`failure` nos 5 ultimos runs"*) era verdadeira em 2026-08-07 e e **falsa hoje** — o ultimo flaky conhecido (`SpatiePermissionsTest`, era `Gate::before`) caiu no [#6788](https://github.com/wagnerra23/oimpresso.com/pull/6788). **US-PONTO-014** segue aberta pelo que resta: a lane ainda seleciona por allowlist inline, nao por arvore-menos-quarentena.
>
> ⚠️ Afirmacao de estado de CI em tempo presente apodrece — quem quiser o numero de hoje roda `gh run list --workflow=ponto-pest.yml --branch main`, e quem quiser saber se a lane bloqueia merge le `governance/required-checks-baseline.json`, nunca esta linha ([proibicoes §5](../../proibicoes.md) 2026-07-16).

## Atributos fantasma — US-PONTO-012 FECHADA em 2026-09-05

O modulo tinha **4 instancias** do mesmo defeito: o controller lia um atributo que **nao existe** (nem coluna, nem accessor), o `?? 0` ou o `&&` do JSX escondia a ausencia, e a tela mentia em silencio. Nomeado pelo SDD §9 (D-1/D-8). As quatro leem a coluna real hoje, e cada uma tem UC provando:

| Instancia | Lia | Real | UC | Recibo |
|---|---|---|---|---|
| `EspelhoController` | `tem_divergencia` | `estado === DIVERGENCIA` | `UC-ESPSH-01` | pass · 6 assertions |
| `EscalaController@edit` | `entrada`/`saida`/`almoco_*` | `hora_*` | `UC-ESCF-01` | pass · 6 |
| `ImportacaoController@index` | `linhas_criadas` | `linhas_sucesso` | `UC-IMPIDX-03` | pass · 4 |
| `ImportacaoController@show` | idem | idem | `UC-IMPSH-04` | pass · 6 |
| `ImportacaoController@show` | `erro_mensagem` | `log` | `UC-IMPSH-05` | pass · 12 |

Os 5 no **mesmo** run [33942364334](https://github.com/wagnerra23/oimpresso.com/actions/runs/33942364334) da lane, todos com `assertions > 0` — `0 failed` sozinho nao prova execucao (LC-13).

O pior dos quatro nao era numero errado: era o `erro_mensagem`. Uma importacao AFD que **falhou** nao mostrava o motivo, entao ela **parecia bem-sucedida** — e a importacao e a origem rastreavel da marcacao (Portaria 671/2021 Anexo I). O conserto da leitura ja estava em prod; o que faltava era a prova, e ela nasceu no [#6802](https://github.com/wagnerra23/oimpresso.com/pull/6802).

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

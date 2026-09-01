---
date: "2026-09-01"
topic: "Testes persistentes design↔code — matriz canal→garantia + 4 propostas medidas + candidatos mortos"
authors: [C]
---

# Testes persistentes design↔code — levantamento medido (2026-09-01)

> Pergunta de [W]: *"quais testes devem existir para garantir a perfeita comunicação entre o
> design e o code?"* — com ênfase em testes **persistentes** (vivem no repo/CI e continuam
> mordendo), não sondas de sessão. Este log responde em 4 partes: matriz canal→garantia hoje,
> testes que faltam (cada um com FP medido ANTES), candidatos mortos por lápide/medição, TL;DR.
>
> Método: fallback filesystem (worktree sem MCP conectado — how-trabalhar §Fallback). Toda
> contagem é reproduzível pelo comando ao lado (§5 2026-07-28: número só entra com o comando).
> Claims de ausência: `rg --hidden -g '!.git/**'` + dono do inventário
> (`scripts/governance/gates-registry.json`, 127 workflows · `governance/required-checks-baseline.json`,
> 45 contexts required lidos pela união clássica∪ruleset).

## 1 · Matriz canal → garantia persistente HOJE

Enforcement: "required" = context presente no `required-checks-baseline.json` (45 no total);
todo o resto é advisory por dado, não por opinião (LC-10: quem afirma é o baseline, não este doc).

| Canal | O que garante hoje (recibo) | Enforcement | Última mordida descobrível |
|---|---|---|---|
| **A · Build design→espelho** (bundle/partes/hashes/atomicidade) | `scripts/design-sync/aplicar-payload.test.mjs` + `bundle-transaction.test.mjs` + `gerar-payload-partes.test.mjs` (invocados em `governance-script-tests.yml:589-609`, job `tests`); swap atômico + hash de parte + `--require-complete-shell` cobertos. Colisão/refs de poda: `cowork-mirror-freshness --check-refs` (design-memory-gate, "FP medido nos 4 commits que já podaram: 0"). **A EMISSÃO do bundle segue sem dono** (docblock fase −1 do `protocolo.config.mjs`, medido 2026-08-31) — por construção, roda do lado Cowork (§5 2026-08-27). | testes: advisory (job `tests`) · o modo `--unverified --check` do freshness é **required** ("espelho — mexeu depois de verificar", `governance-script-tests.yml` job `espelho-verificado`) | required do espelho mordeu PR #6117 (2026-08-21, docblock `protocolo.config.mjs:126`) |
| **B · `.md` de processo → `design-docs/`** (github.md, PEDIDOs) | Pouso: roteamento por extensão do `--export-from` (`cowork-mirror-freshness.mjs:1104`; R1 do `cowork-ssot-guard` proíbe `.md` em `cowork/` — required-adjacente via step hard do design-memory-gate). Tratamento do github.md: ADR 0387 (PR #6492). **Frescor: NENHUMA máquina mede** — os 270 `.md` sob `design-docs/` (`git ls-files 'prototipo-ui/design-docs/*.md' \| wc -l` = 270) só aparecem no freshness como ISENÇÃO do eixo NOVO (`:260` "ja desceu: existe em design-docs"), nunca no denominador medido (manifesto `--compare` = 202 paths, todos do espelho `cowork/`) | pouso: hard step · frescor: **inexistente** | — (não há medidor) |
| **C · Paridade host↔espelho↔rotas** (o buraco do `cowork-paridade.mjs` fantasma) | O script NÃO existe (rg --hidden: 8 arquivos o citam, 0 o implementam; `ls scripts/**/cowork-paridade.mjs` = ausente — fato já registrado em `CODE_NOTES.resposta-pedido-reexport-2026-08-28.md` b2). Metade coberta: direção *shell declara → espelho tem* = `--absent-local` (design-memory-gate, advisory, bite provado, **0 ausentes hoje** — remedido nesta sessão: 253 refs do shell, 0 missing). Metade descoberta: direção *espelho tem → shell declara* (órfão) = **ninguém** (medido: 31 órfãos de 281 rastreados). Rotas (`app.jsx` como tabela) = **ninguém** | absent-local: advisory · órfão/rotas: inexistente | absent-local: bite local provado ao ligar (docblock design-memory-gate `:400-404`) |
| **D · DS tokens git→vitrine** | `ds-token-diff.test.mjs` + `ds-token-version.test.mjs` + `ds-push.test.mjs` (governance-script-tests:603-609) · `ds-mirror-drift.mjs` (workflow próprio `ds-mirror-drift.yml`, advisory) com `ds-mirror-drift.test.mjs` (RELEASE/BITE/ADVISORY/CATRACA/snapshot-ausente) · `dtcg-equivalence` (design-memory-gate job `prove`, forma+valor) · `ds-tokens-build-sync.yml`. Estado vivo remedido: `node scripts/governance/ds-mirror-drift.mjs` → drift 0 · baseline 0 · rc=0. **Pendência da lápide 2026-08-14**: sob `--enforce`, "não-medi" (snapshot ausente `:53`, engine falhou `:64`) sai exit 1 = indistinguível de drift — e o teste `:57` PINA a conflação | advisory (workflow declara; `DS gate` required cobre cor-crua/ui-lint, outro eixo) | — (drift 0/0) |
| **E · Contrato de tela** (`*.contract.json` + `data-contract`) | `contrato-de-tela.yml` — always-run + skip-as-pass (push main + PR), selftest hard (`contrato-de-tela.test.mjs`) + preflight + contratos ativos + `--map --check` + intenção de fluxo. **NÃO é required** (0 contexts "contrato" no baseline; ADR 0261 "passo 4 pendente, [W] admin-only"). Cobertura medida: **12 contratos vigentes** em `prototipo-ui/contrato/` (+1 `.intent.json`) para **255 telas** (`git ls-files Pages tsx` excl `_components`) ≈ 5%. Os 18 `.contract.json` sob `design-docs/` são PULADOS pelo próprio script ("documentação de design, não contrato vigente" — verificado rodando; 30/30 passam hoje, 0 fail) | advisory (always-run) | — |
| **F · Fidelidade visual** | Persistente: `visual-regression.yml` (Pest browser, 91 `.snap`, L2 charter⇄manifesto enforcing) — **DEMOVIDO de required→advisory em 2026-08-26 por decisão [W] explícita** (nota no próprio baseline) · selftests herméticos no design-memory-gate: `design-diff --selftest` (LC-06), `style-fingerprint --selftest`, `fingerprint-harness --selftest`, `render-proto-baseline --selftest` (hard) + `--check` (advisory, 9 proto-baseline.json) + `--nudge`. O compare VIVO proto×prod é LOCAL por lei (ADR 0290 — lápide §5 2026-07-09 re-matou o render pareado em CI) | advisory (visreg demovido; selftests hard dentro de job advisory) | ancora-selftest: 1 bite real no `design-gate-bites.jsonl` (2026-08-17, zero fantasma na âncora da Jana) — único bite do ledger DR-2a |
| **G · Recibos executáveis por tela** (ADR 0384) | `bundle-transaction.test.mjs` exercita o lifecycle PELO CLI de fora (`status.mjs`): fail-closed órfão (`:167`), aplicação prematura (`:195`), comando vermelho não vira recibo (`:239`), biz=4 proibido (`:260`), smoke→VALIDADA (`:270`), screenshot alterado invalida só o smoke (`:277`), map alterado derruba aplicação+teste (`:291`). Invocado em governance-script-tests:600. **Gap residual**: o modo `--check-lifecycle` (a catraca da fase 5 do protocolo) tem **0 ocorrências** no teste (`grep 'check-lifecycle' bundle-transaction.test.mjs` = 0) — modo nunca exercido de fora (§5 2026-07-28: N modos = N gates) | advisory (job `tests`) | negativos do teste mordem a cada run |
| **H · Retorno code→design** (§10.2) | `design-return-gate.yml` (push main, paths de tela/DS, duas grafias `Resources/resources`) roda `design-return-check.mjs --validate-content`; teste próprio `design-return-check.test.mjs` invocado em governance-script-tests:72 ("3 canais · advisory + bite/release"). Gêmeo diário: check `design_return_skipped` no `jana:health-check` (declarado no docblock do workflow) | advisory por design ("o merge já aconteceu — bloquear não desfaz") | — |
| **I · Integridade do protocolo** (comandos citados existem?) | **JÁ COBERTO** — `protocolo.config.mjs --selftest` → `scriptsReferenciados()` falha se qualquer script do mapa FASES sumir do disco (+ 2 IDs UUID distintos + MIRROR_DIR + ponteiros sem cópia + cobertura dos 5 required do domínio via `conferirCoberturaRequired()` contra o baseline). Invocador: design-memory-gate step "protocolo.config selftest" (sem continue-on-error) | hard step em job advisory | — |

Outros vigias do domínio (para completar o inventário, todos advisory): `design-coverage.yml`
(catraca `declared` por tela), `design-spec-gate.yml` (3 `.design-spec.json` incrementais),
`detect-ui-drift.yml` (M1 autorização), `design-identity-gate.yml` (soft), `pt-conformance.yml`,
`design-code-map-check` (11 `map.json`), pele-paralela (delta vs baseline, 2026-08-31).

## 2 · Testes persistentes que DEVEM existir (ordenados por risco×custo)

### T1 — Frescor dos `.md` pousados em `design-docs/` entra no ledger do freshness (canal B)

- **Buraco (recibo):** 270 `.md` rastreados sob `prototipo-ui/design-docs/` e nenhum medidor de
  frescor os conhece — o `--compare` cobre as âncoras+deps do espelho `cowork/` (manifesto 202
  paths) e o `buildDocsSet` só serve de isenção do eixo NOVO (`cowork-mirror-freshness.mjs:260`).
  O caso quente é `design-docs/github.md` (ADR 0387): diário de sync do [CC] lido na fase −1;
  stale = decisões/erratas do design invisíveis — a classe do incidente "HANDOFF 15d stale" que
  originou o design-return-gate.
- **Dono a estender:** `scripts/governance/cowork-mirror-freshness.mjs` — já tem ledger,
  normalização, e o padrão *agente mede (auth ADR 0315) / CI audita o registro*.
- **Forma do predicado:** o MESMO desenho do `--sla-live-only` (o eixo mais novo do script):
  eixo próprio (`--sla-docs` ou denominador extra do `--sla`), DELTA — path `.md` que ENTROU
  desde a medição anterior; denominador diferente entre medições = SEM COMPARAÇÃO (rc 0, §5
  2026-07-27); nunca janela fixa maior que a cadência do diário (§5 2026-08-27).
- **FP medido:** 0 mudanças de rc no CI atual por construção — eixo novo nasce reportando; o
  delta inicial é vazio (a referência nasce da primeira medição registrada). O passivo herdado
  (270 nunca-medidos) entra como contagem no relatório, jamais como vermelho (§5 2026-08-24).
- **Bite-test:** pelo CLI de fora, padrão dos vizinhos no `cowork-mirror-freshness.test.mjs`:
  ledger com medição de docs registrada + path novo → rc≠0; sem path novo → 0; ledger sem
  medição → relatório "não medido", nunca verde mudo (§5 2026-07-29).
- **Invocador:** step novo ao lado dos `--sla`/`--sla-live-only` no `design-memory-gate.yml`
  job `gates` (advisory, `continue-on-error`, summary).
- **Decisão [W]?** Não para o advisory. Residual declarado: herda a "medição órfã" do canal
  (quem mede é o agente logado; o CI só cobra o registro — mesma divisão já ratificada).

### T2 — A metade órfã do `cowork-paridade` fantasma: "arquivo no espelho que o shell não declara", como regra DELTA do dono (canal C)

- **Buraco (recibo):** a doutrina do Cowork (`prototipo-ui/design-docs/CLAUDE.md:25`) descreve
  `scripts/cowork-paridade.mjs` como se rodasse ("gerar + `--check` no CI + `--manifesto`");
  8 arquivos o citam, 0 o implementam. O `CODE_NOTES.resposta-pedido-reexport-2026-08-28.md`
  já decompôs: direção *declarado→presente* coberta pelo `--absent-local` (0 ausentes hoje);
  direção *presente→declarado* (órfão) descoberta. Remedido nesta sessão: **31 órfãos de 281**
  rastreados em `cowork/` — entre eles o 2º `.html` da raiz (`Financeiro - Prova Viva
  (primitivos).html`), exatamente o que o R3 do pedido morderia.
- **Dono a estender:** `cowork-mirror-freshness.mjs` (irmão do `--check-refs`, que já é
  diff-aware da direção da PODA; o órfão é a direção da ADIÇÃO). CODE_NOTES já fixou a forma:
  *"como regras no guard existente, não como script paralelo"* (LC-19).
- **Forma do predicado:** DELTA — "este PR ADICIONA a `cowork/` arquivo que o shell não
  declara" → report-only. NUNCA absoluto: ver FP abaixo.
- **FP medido (mata a forma absoluta):** dos 31 órfãos herdados, ≥24 são legítimos por
  construção — 21 `venda-v3/**` (FORA_DESTA_CONTA, fonte [L]/[M], `protocolo.config.mjs:69`),
  2 `ds-v6/`, 1 `prototipos/` — ou seja, predicado absoluto = ≥77% FP no dia 1 (§5 2026-08-24
  + família guard sintático). Delta hoje = 0 (nenhum PR aberto adiciona órfão).
- **Bite-test:** fixture-diff pelo CLI de fora (padrão do fixture cowork-refs no gate-selftest):
  diff adicionando `cowork/x.jsx` não-declarado → exit≠0 no modo delta; adicionando declarado
  ou fora de `cowork/` → 0.
- **Invocador:** step no `design-memory-gate.yml` job `gates`, ao lado do `--check-refs`
  (advisory, continue-on-error).
- **Decisão [W]? SIM, dupla:** (a) o pedido do Cowork tem decisão aberta dentro (o 2º `.html`
  da raiz — apagar/declarar); (b) a correção do `CLAUDE.md` que cita a máquina fantasma é do
  lado Cowork (editar o espelho local = remendo, §5 2026-08-17) — vai como resposta/PEDIDO,
  não como edit daqui.

### T3 — `ds-mirror-drift`: separar exit 2 (não-medi) de exit 1 (drift) — o pré-requisito NOMEADO da lápide, sem ligar `--enforce` (canal D)

- **Buraco (recibo):** sob `--enforce`, snapshot ausente (`ds-mirror-drift.mjs:53`) e falha do
  engine (`:64`) saem **exit 1**, indistinguíveis de drift real — e o próprio teste pina a
  conflação (`ds-mirror-drift.test.mjs:57` — "snapshot ausente + --enforce → exit 1"). A lápide
  §5 2026-08-14 nomeia o pré-requisito: *"exit 2 = não-medi (recorder trata como skipped),
  exit 1 = drift real; só então a flag faz sentido"*.
- **Dono a estender:** o próprio `ds-mirror-drift.mjs` + `ds-mirror-drift.test.mjs` (ambos já
  invocados: workflow próprio + governance-script-tests). Zero script novo.
- **Forma:** exit 2 nos DOIS caminhos de não-medição sob `--enforce`; advisory default intocado
  (exit 0 + `::warning::` — o invocador `.github/workflows/ds-mirror-drift.yml` roda advisory,
  então o comportamento de CI não muda um bit).
- **FP medido:** 0 por construção no CI vigente (advisory intocado; estado vivo remedido:
  drift 0 · baseline 0 · rc=0). A mudança é dormente até um flip futuro.
- **Bite-test:** atualizar os asserts do teste (deliberado, não drive-by): snapshot-ausente +
  `--enforce` → 2 · drift>baseline + `--enforce` → 1 · em-dia → 0 · advisory sempre 0.
- **Invocador:** já existe (os dois acima).
- **Decisão [W]?** Não para a separação (engenharia que habilita, não muda enforcement).
  QUALQUER `--enforce`/required continua vetado sem reabertura [W] da lápide 2026-08-14 —
  inclusive a variante "só no array do recorder", que a própria lápide já matou.

### T4 — O modo `--check-lifecycle` da catraca de recibos ganha prova pelo CLI de fora (canal G)

- **Buraco (recibo):** `bundle-transaction.test.mjs` cobre o lifecycle inteiro com controles
  negativos, mas o modo `--check-lifecycle --source --minimum` (`status.mjs:108-127` — a
  catraca que a fase 5 do protocolo cita: "catraca só do escopo novo; legado não ganha
  anistia") tem **0 ocorrências** no teste. Um script com N modos é N gates (§5 2026-07-28);
  hoje o modo que GATEIA o escopo novo é o único sem prova de que morde.
- **Dono a estender:** `scripts/design-sync/bundle-transaction.test.mjs` (sandbox própria já
  montada; invocado em governance-script-tests:600).
- **Forma:** 3 sondas pelo CLI de fora na sandbox existente: tela abaixo do `--minimum` →
  exit 1 · no mínimo → 0 · sem `--source`/`--module` → exit 2 (o "legado não é bloqueado
  globalmente" é contrato — a sonda do exit 2 prova que a catraca recusa virar absoluto).
- **FP medido:** 0 — teste hermético em fixture própria, sem tocar população real.
- **Bite-test:** é o próprio item (controles positivo+negativo+escopo).
- **Invocador:** já existe.
- **Decisão [W]?** Não.

> **Não proponho um 5º/6º.** Os canais restantes ou já têm dono mordendo (A transporte, E
> denominador, G lib, H retorno, I protocolo — matriz §1), ou o que falta neles NÃO é máquina
> do repo: a emissão do bundle (A) fecha com regeneração no fim de todo ciclo do lado Cowork
> (§5 2026-08-27), e o compare vivo proto×prod (F) é local por lei (ADR 0290). Propor máquina
> ali seria fabricar cobertura onde a lápide já explicou por que ela não pode existir.

## 3 · Candidatos mortos (matei antes de propor — vale tanto quanto a lista viva)

| Candidato óbvio | Por que morreu |
|---|---|
| **Criar `scripts/cowork-paridade.mjs`** (fazer o que a doutrina promete) | LC-19 (máquina paralela a tema com dono): `--absent-local`/`--check-refs`/`cowork-ssot-guard` já são os donos das metades; o CODE_NOTES 2026-08-28 já fixou "regras no guard existente, não script paralelo". T2 é a forma viva. |
| **Órfão do espelho com predicado ABSOLUTO** ("31 órfãos = 31 violações") | Medição desta sessão: ≥24/31 legítimos (21 venda-v3 = FORA_DESTA_CONTA por decisão [W]) → ≥77% FP dia 1. §5 2026-08-24 (delta vs absoluto) + família guard sintático (06-30/07-09/07-16/07-26). |
| **Render-diff proto×prod em CI** (fechar o canal F com pixel no gate) | Lápide dupla: ADR 0290 + §5 2026-07-09 ("RE-PROPOSTO e re-morto — a lápide canônica é a 0290"). Critério de reabertura mora na 0290 (check hermético), não aqui. |
| **Re-promover `visual-regression` a required** | Demovido por decisão [W] explícita em 2026-08-26 (nota no `required-checks-baseline.json`). Re-propor sem sinal novo = re-inflar após corte (proibições §Comportamento) + família §5 2026-07-01 (re-promoção sem reabrir a decisão). |
| **Ligar `--enforce` no `ds-mirror-drift`** (destravar a evidência DR-2) | Lápide nominal §5 2026-08-14 — inclusive a variante "só no array do recorder". T3 implementa o pré-requisito SEM tocar o enforcement. |
| **SLA de idade fixa pro `github.md`** ("stale se >7d") | §5 2026-08-27: janela de tolerância maior que a cadência do objeto = verde por construção; e idade via `git log` carrega a armadilha do clone raso (§5 2026-07-24). T1 usa registro-de-medição + delta, não idade. |
| **Gate de presença do `github.md` tratado** ("design-docs/github.md existe?") | Presence-gate (§5 2026-07-01/09) — presença não prova tratamento nem frescor. |
| **Teste novo "comandos do protocolo existem no disco"** (canal I do pedido) | Dono já existe e morde: `protocolo.config.mjs --selftest` → `scriptsReferenciados()` + `conferirCoberturaRequired()`, invocado no design-memory-gate (step hard). Criar outro = duplicar régua consolidada (§5 2026-07-09). |
| **Restringir o glob do `contrato-de-tela` pra excluir drafts do inbox** | Dissolvido por medição: o script JÁ pula `design-docs/` ("documentação de design, não contrato vigente" — verificado rodando os 30: 30 pass, 18 pulados como doc). Consertar o que já funciona. |
| **Hook/cron do repo pra emissão do bundle** (fechar o "sem dono" do canal A) | Impossível por construção (o repo não tem os arquivos — docblock do `gerar-payload-partes.mjs`) + §5 2026-08-27: o que fecha a classe é a regeneração do lado Cowork ao fim de cada ciclo, não máquina daqui. Fica como pendência de PROCESSO com [W]/[CC]. |
| **Ampliar o `--check-staging`/anchor pra required** ("15 âncoras podres em design-docs") | §5 2026-08-24 (2ª instância): dívida herdada fora do raio do PR travaria o merge do repo; o próprio design-memory-gate já documenta a recusa no step `--check-staging`. |

## 4 · TL;DR

1. A comunicação design↔code já tem espinha persistente real: transporte com testes invocados,
   1 required de espelho ("mexeu depois de verificar", mordeu PR #6117), ~15 workflows advisory
   com selftests hard e bite-log DR-2a (1 mordida real registrada).
2. Os 2 buracos com risco de verdade: **frescor dos 270 `.md` pousados em design-docs/**
   (github.md/PEDIDOs — nenhuma máquina mede; é a classe do "HANDOFF 15d stale") e a **metade
   órfã da paridade host↔espelho** (31/281 hoje, incl. 2º `.html` na raiz) que a doutrina do
   Cowork atribui a um script que não existe.
3. Proposta = 4 extensões de dono (T1 frescor-docs no ledger do freshness · T2 órfão DELTA no
   guard existente · T3 exit 2≠1 no ds-mirror-drift · T4 sonda CLI do `--check-lifecycle`),
   todas advisory/forward-only, FP contado antes, bite pelo CLI de fora, invocador nomeado.
4. Decisão [W] embutida só em T2 (2º `.html` da raiz + correção do CLAUDE.md do Cowork, lado de lá);
   T3 explicitamente NÃO liga `--enforce`.
5. 11 candidatos óbvios morreram por lápide ou por medição desta sessão — inclusive o canal I
   do pedido, que já está coberto pelo selftest do `protocolo.config.mjs`.

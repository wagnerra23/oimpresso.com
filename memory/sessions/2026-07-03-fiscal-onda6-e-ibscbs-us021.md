---
date: '2026-07-03'
topic: 'Onda 6 Fiscal completa + US-FISCAL-021 IBS/CBS destravada (PR-A/B/C merged + PR-D chip)'
authors: [C]
outcomes:
  - 'Onda 6 Fiscal: 4 PRs mergeados (ficha 75, inventário+2 US, régua 7 telas, catraca)'
  - 'US-FISCAL-021 IBS/CBS: PR-A/B/C/D mergeados (pin dev-master + cálculo + flag + serialização grupo UB) — done end-to-end (PR-D #3778 fechado 2026-07-04, ver Continuação)'
  - 'US-FISCAL-022 health-check cert mergeado (#3775, via chip)'
  - 'Back-compat dev-master provado verde no CI (Pest NfeBrasil MySQL na main atual)'
prs: [3738, 3753, 3761, 3764, 3771, 3772, 3774, 3775]
related_adrs:
  - 0321-pin-sped-nfe-dev-master-ibs-cbs
  - 0089-capterra-driven-module-evolution
  - 0320-programa-ondas-regua-correcao
us:
  - US-FISCAL-021
  - US-FISCAL-022
---

# Session log — 2026-07-03 · Onda 6 Fiscal + US-FISCAL-021 IBS/CBS

## TL;DR

Sessão épica, duas frentes. **(1) Programa de Ondas — Onda 6 Fiscal COMPLETA** (4 passos, 4 PRs): CAPTERRA-FICHA camada config/orquestração nota **75/100** (#3738), INVENTARIO + US-FISCAL-021/022 no MCP (#3753), régua 7 telas (#3761), catraca+sentinela PLANO-MESTRE (#3764). **(2) US-FISCAL-021 IBS/CBS de ponta a ponta**: research → plano aprovado ([W] "pin sped-nfe dev-master full") → **ADR 0321** → back-compat **verde no CI** (main-atual+dev-master) → **PR-A** pin lib (#3772), **PR-B** cálculo motor (#3771), **PR-C** feature flag + seleção schema (#3774) — **todos MERGEADOS**. **US-FISCAL-022** health-check cert também mergeado via chip (#3775). Resta só **PR-D** (serialização grupo UB — Tier-0 REGRA MESTRE), rodando em chip próprio. Tudo na main é **inerte/gated OFF** (legacy = XML byte-idêntico) → zero efeito no biz=1 live. P0 regulatório (prazo 03/08/2026) foi de zero absoluto a ~90% (falta só a serialização gated).

## O que aconteceu

### Frente 1 — Onda 6 Fiscal (4 passos)
- **P1** (#3738): `capterra-senior` Fiscal (camada CONFIG/ORQUESTRAÇÃO, não sobrepõe emissão NfeBrasil). 3 agentes, 24 WebSearch. Matriz P0-P3 (21 caps × 8 concorrentes). Nota **75**.
- **P2** (#3753): `/comparativo` → INVENTARIO (12✅/4🟡/5❌) + US-FISCAL-021/022 no MCP+SPEC. Dedup pegou US-FISCAL-019 + corrigiu staleness GAP-FISCAL-002.
- **P3** (#3761): 7 `.casos.md` + 7 scorecards, 4 agentes. Sped d1 aplica ✔ (cross_check ✔ / golden ✘). Débito = UC-traceability G-2.
- **P4** (#3764): emergente sem gate novo — ratchet bloqueia fiscal-sped 68→50 + sentinela exposicao-tier0; registro PLANO-MESTRE Onda 6.

### Frente 2 — US-FISCAL-021 IBS/CBS (plano 4 PRs)
- **Descoberta:** XML grupo UB hard-blocked pela lib (`sped-nfe` v5.2.5 sem release; issue #1274). [W] escolheu pinar dev-master full (AskUserQuestion) → plan mode → plano aprovado.
- **PR-A** (#3772 merged): ADR 0321 + composer.json `"dev-master"` (SHA e075ec4 no lock) + lock regen main-atual (CT100 php8.4). **Back-compat gate = o CI**: Pest NfeBrasil MySQL **verde no dev-master**. Fix pin-format: `"dev-master"` branch (não `#sha`) pra passar composer validate --strict.
- **PR-B** (#3771 merged): TributoCalculado +7 campos (default → sem breaking); MotorTributarioService.aplicarRegra+aplicarDefaults populam; 6 cenários Pest cross-check numérico (base 1000 @ IBS 0,1%/CBS 0,9% → 1,00/9,00). Inerte.
- **PR-C** (#3774 merged): flag `reforma_tributaria_modo` (string) em nfe_business_configs + NfeService::schemaReforma (legacy→null→new Make(null) byte-idêntico; full/hybrid→'PL_010_V1'). Fixes: string-não-enum (domain-dict G-4) + skip SQLite (ADR 0101) + delete não forceDelete.
- **PR-D** (chip): serialização `tagIBSCBS`/`tagIBSCBSTot` no NfeService gated + XSD PL_010_V1 + REGRA MESTRE antes de mergear.
- **US-FISCAL-022** (#3775 merged via chip): health-check cert A1 cron.

## Lições catalogadas
- **Staging CT100 defasada (old-main 8af585a27) contamina back-compat** — 52 falhas pré-existentes mascararam o sinal. Régua limpa = CI na main-atual, não staging manual.
- **Derrubei 2 hipóteses de alarme próprias** (Make ctor, Tools::model) com evidência — transparência > drama. O gate fez o trabalho.
- **Pin dev-master:** `"dev-master"` (branch) no composer.json + SHA no lock — `#sha` trava composer validate --strict.
- **domain-dict-guard G-4:** enum novo sem dicionário quebra ratchet → usar string pra feature flag (config, não vocabulário de domínio).
- **NfeBrasil test lane SQLite:** guard por driver (skip sqlite, ADR 0101), não `Schema::hasColumn` (poluição de estado in-memory).
- **Plataforma (classifier) em outage** ~fim da sessão — bloqueou temporariamente o fechamento; retomado no resume.

## Próximos passos pra retomar
1. **PR-D** (chip rodando): serialização grupo UB + REGRA MESTRE (apresentar antes→depois flag OFF=0-mudança antes de mergear).
2. Fiscal biz=4 Larissa: US-FISCAL-018 canary (pré-existente).
3. Plano completo: `C:\Users\wagne\.claude\plans\async-dazzling-bumblebee.md`.

## Pointers
- ADR 0321 (memory/decisions/) · CAPTERRA-FICHA/INVENTARIO Fiscal · PLANO-MESTRE Onda 6 · US-FISCAL-021 timeline (tasks-detail).

---

## Continuação 2026-07-04 — PR-D FECHADO (serialização grupo UB) — US-FISCAL-021 done end-to-end

Sessão seguinte pegou o chip PR-D e fechou a última etapa.

- **PR-D** ([#3778](https://github.com/wagnerra23/oimpresso.com/pull/3778), merged `10c41d6a`): `NfeService::adicionarItem(+bool $reformaAtiva)` monta `tagIBSCBS` (gated) a partir do sub-array `ibscbs` do det + `tagIBSCBSTot` (auto-derivado do acumulado por-item). Mapeia `ibscbs` do TributoCalculado nos 2 sites de dets (emitirParaInvoice + emitirDeTransaction).
- **Prova (REGRA MESTRE) — verde na lane `NfeBrasil · MySQL` (lib e075ec4), 100 passed:** valor por 2 caminhos (motor `valor_ibs/valor_cbs` == XML `vIBSUF/vCBS` == base×alíquota) · XSD-válido PL_010_V1 (exceto assinatura, adicionada no signNFe) · legacy byte-idêntico · full-sem-CST omite · turning-on-sem-regra == legacy. Apresentei tabela antes→depois a [W] antes do merge; [W] aprovou ("merge").
- **Modelagem v1 documentada (decisões):** (1) régua guarda UMA alíquota IBS combinada → lançamos 100% em `gIBSUF`, `gIBSMun` zerado (`vIBS`=vIBSUF+vIBSMun == valor_ibs do motor); (2) CST único no grupo = `cst_ibs`. Alíquotas em fração (0.18=18%) → percentual no XML (×100).
- **Follow-up aberto:** [US-FISCAL-024](https://github.com/wagnerra23/oimpresso.com/pull/3784) (merged na SPEC) — split UF/Município (coluna de schema) pra business `full` com alíquotas UF≠Mun. p2, não bloqueia ativação inicial. **Numerada 024 não 023** (023 é número queimado — lápide na SPEC Fiscal; `tasks-create` reincidiu no dedup-bug).

### Lições novas
- **CI trigger em PR empilhada/force-pushed fica errático:** commits vazios não disparam lanes path-filtered; flip de base (`edited`) não re-roda; a lane fiscal roda `allowlist` explícito → **teste novo NÃO roda até ser adicionado ao allowlist do `nfebrasil-pest.yml`**. Usei `gh workflow run` (workflow_dispatch) pra forçar run autoritativo na branch. Régua limpa só veio após `rebase --onto origin/main <pr-c-tip>` (des-empilhar).
- **Staging CT100 drift again:** vendor em `f49d543e` (≠ lock `e075ec4`); `TraitTagDetIBSCBS` mudou entre os dois → **assinatura tem que vir do SHA pinado**, não do staging. E o checkout na staging abortou por WIP de sessão concorrente (`tests/Feature/Calculo/`) — não mexi.
- **Achado de governança:** `require_code_owner_reviews: true` + `required_approving_review_count: 0` → review code-owner **não é gate real** (PR-D fiscal mergeou sem aprovação humana explícita, só CI verde). Pra tornar gate → count ≥1.

### Próximo passo crítico (prazo 03/08/2026 CRT 3)
- **Smoke real homologação SEFAZ** com 1 business em `full` + regra IBS/CBS real → emitir NF-e 55 → confirmar cStat 100. É humano-limitado (cert+SEFAZ). Relaciona-se com a trilha dormente Gold **US-NFE-046** (smoke homologação SEFAZ-SP).
- Decidir se o business-alvo precisa do split UF/Mun (US-FISCAL-024) ANTES de ativar.

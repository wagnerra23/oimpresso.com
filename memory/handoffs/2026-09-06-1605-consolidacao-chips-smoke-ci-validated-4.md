---
date: "2026-09-06"
time: "1605 BRT"
slug: "consolidacao-chips-smoke-ci-validated-4"
tldr: "12 chips consolidados + smoke em ambiente controlado (ADR 0390) fechado de ponta a ponta: #6905 job+consumidor → #6925 (1º run caía por checar a fonte antes de o React montar) → #6929 (4 recibos host=ci). Funil no main: validated 4 (era 0/93) · compared 26 · anchored 40 · to-create 23. #6902 pôs o Dfe na lane e a execução expôs 2 UCs (teste cego de isolamento + método do contrato ausente), consertados. 5 chips de gap.md abertos."
decided_by: ["W"]
cycle: null
prs: [6900, 6902, 6903, 6905, 6925, 6929]
us: ["US-FISCAL-008"]
next_steps:
  - "Mergear os 5 chips de gap.md + map.json quando verdes e refutados (#6928 · #6927 · #6926 · #6919 · #6914)"
  - "[W] ratificar a ADR 0390 (proposto → aceito): PR só da linha status: + índice + label adr-metadata-normalization"
  - "[W] colar no chat do Design o bloco de prototipo-ui/CODE_NOTES.prompt-cowork-regenerar-bundle-por-ciclo-2026-09-01.md — 23 âncoras abaixo do piso do get_file esperam o bundle"
  - "SpedControllerTest UC-FSPED-01: mesmo desenho cego do Dfe (verde tautológico) — PR curto espelhando o DfeControllerTest"
  - "Decisões [W] declaradas pelo #6904: Atendimento/Macros e Repair/Settings anchored no report com charter n/a; Sells/Create sem âncora no charter"
---

# Handoff — consolidação dos chips · smoke em CI · validated 0 → 4

> Continuação do [handoff 07:20](2026-09-06-0720-seis-perguntas-design-sync-seis-prs.md). Aquele fechou as 6 perguntas; este fecha o que [W] pediu depois (*"abra chips em sessões frescas… gerencie todas… mais alguma coisa possível?"*) e o item que estava bloqueado no login de produção.

## Estado ao fechar (medido no main `5baadae608`)

| Funil design-sync (93 telas) | validated **4** · compared 26 · anchored 40 · to-create 23 |
|---|---|
| Telas validated | Arquivos/Index · Fiscal/Config · Fiscal/Eventos · Fiscal/Sped — recibo `host: ci`, deploySha `7bff2ca69d` |
| Órfã `governance/design-smokes` | publicada pelo run do main após #6925; 4 PNGs em `scripts/design-sync/state/smokes/` |
| Lane NfeBrasil | executa `DfeControllerTest` + `AcoesDfe*` (#6902): 83 checks verdes após o fix dos 2 UCs |

Reproduzir o funil: contar `lifecycleState` em `scripts/design-sync/state/application-report.json`, ou `node scripts/design-sync/status.mjs --refresh` num checkout limpo (a projeção suja o working tree — restaurar depois).

## O que fechou nesta metade

1. **#6903** recibos de teste em massa via lanes de CI (chip) — mergeado.
2. **#6902** Dfe na lane (chip) + **meu fix** dos 2 UCs que a execução expôs: UC-FDFE-01 era teste cego (sem `actingAs`, o `ScopeByBusiness` não aplica) → reescrito com tenant canônico, fixture dos dois lados e controle positivo; UC-FDFE-02 era gap real → `isPendenteManifestacao()` no modelo, `podeManifestar()` delega. Achado declarado e **não mexido**: `SpedControllerTest` prova isolamento com o mesmo desenho cego (verde tautológico — só passa porque `nfe_emissoes` não tem linha alheia na lane).
3. **#6905** smoke em ambiente controlado (chip; ADR 0390 **proposta**) + meus 2 commits de gate (inventário de máquinas, `catalog.json`). Mergeado por [W].
4. **#6925** o 1º run do smoke no main caiu nas 4 telas: fonte checada antes de o React montar. Espera de montagem + role `Admin#{biz}` + assert de status 200. Provado por `workflow_dispatch` no branch (4 passed / 28 assertions).
5. **#6929** 1º consumo real do smoke: `smoke-consumir.mjs` → `status.mjs --record-smoke --host ci` × 4. Fix de plataforma no extrator (`tar` do Git Bash × `C:\`).

Mergeados pelos próprios chips ou por [W] enquanto isso: #6904 (design-diff-lote), #6906 (ondas MWART das 23), #6907 (workflow refutador GT-G5), #6908–#6918 (gap.md + map.json por módulo, data-contract inerte, alvo Jana exportado), #6920–#6924 (aterrissagens de governança).

## Aberto (com dono)

- **Chips ainda vivos** (5 PRs de gap.md + map.json): #6928 (6 gap.md que saíram do #6897), #6927 (OficinaAuto + Officeimpresso), #6926 (Essentials 8 telas), #6919 (lote 7 telas + ponteiro podre da Cobrança), #6914 (Estoque + Manufacturing). Cada um refutado por GT-G5 na própria sessão; merge por [W] ou pela autorização vigente quando verde.
- **Ratificação da ADR 0390** (`proposto → aceito`): ato [W], PR só da linha `status:` + índice regenerado + label `adr-metadata-normalization`.
- **Item 3 (bundle regenerado a cada ciclo Cowork)**: roda do lado design — [W] cola o bloco de `prototipo-ui/CODE_NOTES.prompt-cowork-regenerar-bundle-por-ciclo-2026-09-01.md` no chat do Design. Sem isso, 23 âncoras seguem abaixo do piso do `get_file`.
- **Smoke de produção** (host `producao`, biz=1): continua possível e continua exigindo login humano; o caminho `ci` não o substitui — a 0390 diz isso e o recibo carrega `host`.
- **Decisões [W] declaradas pelo agente do #6904**: Atendimento/Macros e Repair/Settings listadas `anchored` no report com charter `n/a`; Sells/Create sem âncora no charter.
- **SpedControllerTest** (UC-FSPED-01) — mesmo desenho cego do Dfe; conserto é um PR curto espelhando o `DfeControllerTest`, fora do intent de hoje.

## Regras duras que valeram hoje (preservar)

Nunca digitar senha · nunca `R$ <número>` em git · biz=4 nunca em teste · sem PII · Pest/PHPStan só CT 100 ou CI · não dar `git pull` no CT 100 sujo · ADR canon append-only · R10 merge com autorização (dada explicitamente nesta sessão: *"Merge"*, *"Pode fazer tudo"*, *"gerencie todas"*) · **apagar branch remoto só por nome literal** (LC-12, 3ª ocorrência hoje de manhã).

## Lições que entraram no ledger hoje (recibos)

- **LC-08 → 144**: `| tail -3` engoliu a linha `DRIFT` e eu atribuí o vermelho do CI ao ambiente; depois "provei" ausência de diff num branch sem o arquivo causador.
- **LC-12 → 3** (manhã): glob no `push --delete` apagou 4 branches alheias; `avisoPushDelete` no `block-destructive` (#6900).
- **LC-20 → 3** (manhã): recibo gravado de checkout atrasado; `avaliarBaseParaRecibo` no recorder (#6896).
- Mordida de máquina registrada (não é ocorrência): `block-sonda-que-mente` P5 barrou um monitor com `jq` inexistente nesta máquina.

## Estado MCP no momento do fechamento

Tools MCP `cycles-active`/`my-work`/`sessions-recent` **não estão conectadas** neste worktree (fallback filesystem, [how-trabalhar §Fallback](../how-trabalhar.md)). O que o hook `brief-fetch` trouxe na abertura desta metade (Brief #612, gerado 2h antes): cycle `—`, HITL pendentes de [W] 5, 71 commits/24h, US sem dono 680, SDD composta 41,0 (Δ+0,4), visual-regression sem crítico. Sessões vivas medidas por `list_sessions` às 11:53 UTC: 10 chips `isRunning: true`; às 19:00 UTC os chips de #6902/#6903/#6906/#6907/#6918 já haviam encerrado (notificação do host).

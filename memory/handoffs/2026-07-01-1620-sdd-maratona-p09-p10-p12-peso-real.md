---
date: "2026-07-01"
time: "16:20 BRT"
slug: sdd-maratona-p09-p10-p12-peso-real
tldr: "Maratona SDD: P09 executado (dead+placeholder=0), roadmap reconciliado (P01/P03/P05/P08/P13), P12 completo (C+D+5b peso_real ligado em prod), P10 batch 1 Sells mergeado (12 FSM trio; 17 US de tela sem teste viraram backlog). Achado: tela de venda sem cobertura comportamental."
prs: [3473, 3475, 3478, 3482, 3483, 3485, 3489, 3493, 3495, 3498]
decided_by: [W]
related_adrs: [0273-anchor-spec-codigo-formato-canonico-fluxo-novo, 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
next_steps:
  - "Wagner: smoke peso_real no chat real (DoD F4 ADR 0270 — ADR historical fora do top-3 de query sobre tema vigente). Fecha P12 de vez."
  - "Backlog Tier 0: ~14 testes HTTP comportamentais da tela Sells (as 17 US revertidas pra sem_campo) — store() toca dinheiro, casos a-vista/prazo/desconto/frete/split."
  - "P06 cron SDD-snapshot: agendar no CT100 (nao Hostinger — ADR 0062); prod tem 1 row stale."
  - "Continuar P10 per-modulo (Whatsapp 72, OficinaAuto 48, Financeiro 38 sem_campo) — cada batch = anchor+trio+teste."
---

# SDD — maratona P09 + reconciliacao + P12 completo + P10 batch 1 Sells

## Estado MCP no momento
- **Cycle:** nenhum ATIVO em COPI. Sessao off-cycle (governanca/SDD).
- **my-work:** 30 tasks ativas (nenhuma desta sessao — trabalho off-branch).
- **decisions-search:** ADR 0270 (ciclo de vida/decay) e a mae do peso_real (P12-5b).
- Handoff paralelo do dia: `2026-07-01-1245-devolucao-rotalivre-namespace-menu-uc.md`.

## O que aconteceu
Sessao longa que fechou varias frentes do roadmap SDD:
- **P09** executado: PontoWr2 trio (#3473) + delete MemCofre arquivado (#3475) -> `anchored_dead=0` E `placeholder=0` no main.
- **Reconciliacao bookkeeping** (#3478/#3482): P01/P03/P05/P08/P09/P13 marcados executado (verificado no repo+branch-protection, nao presumido); Pfr honestamente NAO (foundation-ratchet nao flipou — GT-G3 e que virou required).
- **Verificacao "tua mao" (P06/P07/P12):** a parede caiu — recursos existem. P06 tabela+brief OK (cron->CT100 pendente); P07 codigo-completo + pcov no CT100 (falta nightly); P12-C ja feito.
- **P12-D** (RAGAS): baseline computava real mas push do bot bloqueado por branch protection -> fix auto-PR (#3485, espelha #3442) -> #3493 baseline real (faithfulness 1.0, 51q) mergeado. Tautologia morta.
- **P12-5b peso_real:** conferido OFF em prod/CT100/git. Wagner autorizou flip -> #3498 (`config.php:756 false->true`, KL-C1 test ajustado). Ligado em prod via `config:cache` manual (deploy nao rodou o rebuild) -> runtime `true` confirmado. Falta so o smoke DoD F4.
- **P10 batch 1 (Sells):** gerador+refutador (0 alucinacao, 47 paths existsSync). Entry gate revelou que **so 12 das 29 US anchored tem teste comportamental** — toda `tests/Feature/Sells/*` e fachada regex (confirma US-SELL-047/048). Landei os 12 FSM com trio completo; reverti as 17 de tela pra sem_campo; status-truth 8 FSM todo->done. #3483 mergeado verde.

## Artefatos gerados (10 PRs merged)
#3473/#3475/#3478 (P09+reconciliacao) · #3482 (reconc P06/P07/P12) · #3485 (RAGAS auto-PR) · #3489 (DoD "Acceptance criteria") · #3493 (RAGAS baseline real) · #3495 (session log) · #3498 (peso_real flip) · #3483 (P10 batch1 Sells 12 FSM).

## Persistencia
- git: 10 PRs merged · webhook->MCP propaga.
- prod: peso_real runtime `true` (config:cache manual); tabela mcp_sdd_scorecard_history 1 row.
- session log: `memory/sessions/2026-07-01-sdd-p09-p10-verificacao-tua-mao.md` (#3495).

## Licoes catalogadas
- **Bug sistemico branch-protection:** bot push direto na main bloqueado (floor, RAGAS) -> padrao canonico auto-PR (peter-evans+COWORK_BOT_PAT, #3442). Qualquer workflow que commite em main tem esse bug latente.
- **P10 e campanha per-modulo (anchor+trio+teste), nao backfill** — o entry gate garante. Fachada de teste (grep source) e pega pelo covers-check.
- **A SDD funcionou no proprio trabalho:** entry gate pegou 2x carimbar "implementada" sem teste (PontoWr2, Sells). Saida = provar o trio, nunca gamar.
- **Deploy nao roda config:cache sempre** -> flip de config pode ficar runtime-stale ate rebuild manual (peso_real precisou de config:cache na mao).
- **Merge de gate-fix (DoD #3489) desbloqueia PRs downstream** — #3483 so ficou verde apos mergear main (trouxe o "Acceptance criteria").

## Pointers detalhados
- Session log: `memory/sessions/2026-07-01-sdd-p09-p10-verificacao-tua-mao.md`
- Roadmap reconciliado: `memory/requisitos/_Governanca/roadmap/_ROADMAP.md`
- peso_real: `Modules/Jana/Config/config.php:760` · ADR 0270 (DoD F4) · #3498

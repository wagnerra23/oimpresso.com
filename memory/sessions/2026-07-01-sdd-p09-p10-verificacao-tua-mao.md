---
date: "2026-07-01"
hour: "11:00-14:15 BRT"
duration: "~3h (maratona)"
topic: "SDD — P09 executado + reconciliação bookkeeping + verificação das 4 'tua mão' (P06/P07/P12) + P10 batch 1 Sells (achado: tela sem teste comportamental)"
authors: [W, Claude]
prs: [3473, 3475, 3478, 3481, 3482, 3483, 3485, 3489, 3493]
related_adrs: [0273, 0275, 0279, 0062]
---

# SDD — P09 + reconciliação + verificação "tua mão" + P10 batch 1

## TL;DR
Sessão longa que (1) executou o **P09** (dead=0 + placeholder=0 no main via PontoWr2 trio #3473 + delete MemCofre #3475), (2) reconciliou o bookkeeping stale do roadmap SDD (P01/P03/P05/P08/P09/P13 executados; Pfr honestamente NÃO — foundation-ratchet não flipou, GT-G3 é que virou o dente required), (3) **verificou as 4 "tua mão"** e descobriu que a "parede" caiu (recursos existem, maioria já construída), (4) começou **P10 batch 1 (Sells)** e desenterrou um gap crítico: **a tela Sells não tem teste comportamental** (suíte 100% fachada regex).

## Estado das 4 "tua mão" (verificado em prod SSH + CT100 tailscale)
- **P06** parcial — tabela `mcp_sdd_scorecard_history` existe em prod + linha SDD no brief; snapshot refrescado à mão. **Cron diário ausente** (`schedule:run` não está no crontab Hostinger). **Decisão Wagner: agendar no CT100, não Hostinger** (ADR 0062 — IA/governança ≠ shared hosting). Follow-up aberto.
- **P07** código-completo — 6 peças no main + **pcov verificado no container CT100**. Falta só a 1ª nightly (relógio 3 noites). Zero código.
- **P12-C** já feito — recall-eval mock CI + schedule Kernel (dom 06:30).
- **P12-D** **fechado nesta sessão** — `OPENAI_API_KEY` É secret; dispatch real computou baseline mas o **push do bot era bloqueado por branch protection** (mesmo bug do floor #3442). Fix: auto-PR (#3485, peter-evans + COWORK_BOT_PAT). Re-disparo → #3493 com baseline REAL (`faithfulness 0.0 to 1.0`, mode=real, 51 questions) auto-merging. Tautologia morta.
- **peso_real flag** SEGURADA — Tier 0 (toca recall do chat), exige smoke CT100, nunca cego.

## P10 batch 1 (Sells) — achado crítico
Gerador (subagente) mapeou as 47 US-SELL para arquivo real; refutador (eu) verificou **todos os paths por existsSync = ZERO alucinação**. Sells `sem_campo 47 to 0`, **0 dead**, coverage 0% to 100%, global 11,3% to 18,1%. Ambiguidade 6% (gate P10 passa).

**MAS** o entry gate (required, P05) revelou: **só 12 das 29** US anchored_ok/parcial têm teste **comportamental** (todo o grupo FSM: 011-014, 029-036 — DB real, Service/SideEffect/comando/HTTP). As **17 de tela/controller NÃO têm teste** — `tests/Feature/Sells/*` é 100% fachada `file_get_contents`+regex (confirma US-SELL-047/048). Liguei o trio dos 12 reais (@covers-us + Testado em, `req_sem_covering_test 29 to 17`); **recusei anotar fachada** nas 17. **#3483 fica DRAFT** — fechar exige ~14 testes HTTP comportamentais da tela de venda, que é **Tier 0** (store() toca dinheiro; REGRA MESTRE valor/estoque) e **não deve ser rushado**.

## Lições / padrões
- **Bug sistêmico branch-protection:** escrita direta na main por bot (floor, RAGAS baseline) é bloqueada desde 2026-06-11 (enforce_admins). Padrão canônico = auto-PR (peter-evans + COWORK_BOT_PAT), #3442. Aplicado ao RAGAS (#3485). **Qualquer workflow que commite em main tem esse mesmo bug latente.**
- **Bookkeeping do roadmap drifta:** docs nascem `proposed` e o trabalho landa sem atualizar. Reconciliação verificada (não presumida) é o "mexeu, registra" no nível do roadmap.
- **P10 é campanha per-módulo (anchor+trio+teste), não backfill:** o entry gate garante isso. `_pendente_` conta como coberto (honesto p/ tela não-construída), mas US implementada exige teste real — e onde o teste é fachada, o gate corretamente barra.
- **A SDD funcionou no próprio trabalho:** o entry gate me pegou 2x carimbando "implementada" sem teste (PontoWr2, Sells). Saída honesta = provar o trio, nunca gamar.
- **DoD "Acceptance criteria" não era reconhecido** — fix 1-linha no DOD_RE (#3489), gate-selftest 40/40, reduz 7 falsos req_sem_aceite.

## Próximos passos
1. Confirmar #3493 auto-mergeou to P12-D 100% fechado.
2. **Testes comportamentais da tela Sells** (14 US) — Tier 0, sessão focada, atenção Wagner nos casos de valor (à vista/prazo/desconto/frete/split). Alinha com US-SELL-040/047.
3. **P06 cron no CT100** — resolver arquitetura (snapshot lê prod DB; CT100 alcança?).
4. **Pfr** foundation-ratchet a required · **P11** distiller · Trilho B (P02 to P04) · relógio P07.

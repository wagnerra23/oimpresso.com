---
date: "2026-07-26"
time: "17:00 BRT"
slug: reguas-grade-completa-loop-aprendizado
tldr: "Régua do sistema rodou COMPLETA na 3ª tentativa (88/88 agentes, 4,97M tokens) e o 1º REFUTADO_TB do histórico disparou — a emenda §5 2026-07-19 passou no teste que ela mesma pediu. Placar: 1 acima-de-categoria (âncora de design com gate required; Figma Code Connect tem o issue equivalente ABERTO) · 22 à-frente-por-integração · 12 refutadas. Notas fecharam em 3 de 12 dimensões e as 9 restantes saíram SEM NOTA (não inventadas). No loop de aprendizado: recibo pendurado do LC-08 fechado, LC-10/LC-11 nascem contadas, e um falso-verde real no matcher semGate foi corrigido. Das 4 propostas minhas de 'rotina que aprende mais rápido', 3 morreram por medição."
prs: [4790, 4794]
decided_by: [F]
next_steps:
  - "[W] rotular os 12 status selados em memory/reguas/2026-07-17-calibracao-juiz-r2/ — sem isso TODO veredito adversarial da grade correlaciona com nada mensurável (roubo #1, ~20 min, zero engenharia)"
  - "[W] fork do LC-09: manter `Gate: advisory` (alarmando) ou marcar `advisory-terminal (0224)` — apagar alarme é política, não medição; a medição já está no ledger"
  - "Nova rodada da régua com o script JÁ consertado (CP-claims/CP-retrato) pra fechar as 9 dimensões sem nota — a rodada de hoje usou o script antigo e gravou modo 'full' com 9/12 vazias"
  - "Roubo #2 (~10 linhas): publicar taxa de cache hit no agent-cost-per-pr — o dado já é lido e precificado, só não é exposto; é o maior lever de custo do sistema"
  - "Eixo SERVIR-O-NEGÓCIO (4,5, pior nota): mock em rota live (ChatController:533 + PainelController:54) e Jana-BI que nunca chegou na Larissa"
---

## Estado no momento do fechamento

⚠️ **MCP indisponível a sessão inteira** (hook de SessionStart caiu em fallback: `settings.local.json` não encontrado, token MCP indisponível). Consultas feitas pelos oráculos disponíveis, não por memória:

| Pergunta | Oráculo usado | Resultado |
|---|---|---|
| Loop de aprendizado está são? | `licoes-code-two-strikes.mjs --reconcile` | frontier 07-24 · recibos **11/11** · 0 pendurados |
| Alarme two-strikes | mesmo hook (banner) | LC-08 **10x** · LC-09 2x · LC-10 2x · LC-11 2x |
| CI do PR | `gh` via MCP GitHub | #4790 **91 checks verdes → mergeado**; #4794 `mergeable_state: clean` |
| História do repo | `git rev-parse --is-shallow-repository` = **false** (5.701 commits) | datas de `git log` sustentam conclusão |
| Régua terminou? | `journal.jsonl` do run + `retratos.json` | 88/88 done, 0 erro; retrato de hoje persistido |

**Clone estava RASO no início** (`--depth`) e foi desrasado (`git fetch --unshallow`) antes de qualquer medição datada — o hook `block-instrumento-sem-porta-viva` (sonda P3) bloqueou o primeiro `git log` da sessão e estava certo.

## O que aconteceu

Pedido de [F]: rodar a régua do sistema, pontuar em grade, comparar com o melhor e "achar solução para evoluir e aprender mais rápido em processo fechado".

### 1. A régua (3 tentativas)

| Run | Desfecho |
|---|---|
| 1 | interrompida às 13:24:59Z durante Refutar/Integração |
| 2 (resume) | teto de uso — 27 de 88 agentes; morreu **toda** a fase Verificar + Grade + Persistir |
| 3 (resume) | **88/88, 0 erro, 4,97M tokens, persistiu** |

As duas primeiras morreram na **mesma metade**, e não por acaso: o script gastava o caro primeiro (12 pesquisas web + refutações + integrações) e persistia num único passo no fim. Isso virou o chip que sobreviveu (§ abaixo).

**Placar:** 24 claims → **1 ACIMA-DE-CATEGORIA** · 22 à-frente-por-integração · 11 empatadas · 12 refutadas na peça · **1 REFUTADO_TB**.

O `REFUTADO_TB` é o marco: era `0 em 81 vereditos` (8 runs até 07-18). A emenda §5 2026-07-19 reformulou a pergunta de Integração pra dar braço discriminativo e registrou que *"reformular HABILITA o negativo, não PROVA que dispara — o placar do próximo full é a evidência"*. Este foi o 1º full pós-emenda e **disparou**, pelo braço (i): o incremento nomeado era **identidade**, não capacidade.

**Anti-Goodhart:** 2 claims plantadas na mesma corrida, 2 derrubadas, 0 carimbadas.

**Notas:** orquestração-adversarial **6,5** · segurança-do-agente **6,0** · inteligência-de-negócio **4,5**. As outras 9 **sem nota** — a grade se recusou a compor, porque agregar vereditos incomensuráveis é proibido pelo §5.

### 2. O loop de aprendizado (o pedido "aprender mais rápido")

Propus 4 coisas. **3 morreram por medição** — e as lápides estão no §5 pra não voltarem:

| Proposta | Desfecho |
|---|---|
| Índice de "porta viva por pergunta" | **REFUTADA**: a porta existia 3 dias antes do erro; o índice existia 4 dias antes. Correlação inversa à hipótese |
| Detector do LC-09 por vocabulário | **REFUTADA**: 100% FP e **sinal invertido** (0/4 nos erros, 2/2 nas correções) — puniria quem cita a fonte |
| Reincidência pós-gate | **REFUTADA**: 4/4 FP no teto absoluto; metade do predicado é campo auto-declarado |
| Régua incremental (checkpointing) | **IMPLEMENTADA** — nome de mercado: *durable execution* |

**Achado não-planejado (o que mais valeu):** o matcher `semGate` testava o marcador de exceção na string inteira, então `Gate: advisory — <hook> … ADR 0224` **silenciava o alarme** pela menção à 0224 no corpo — e a 0224 é justamente a ADR que rege advisory. Falso-verde silencioso; corrigido com controle negativo nos dois sentidos.

**Backlog do ledger:** era 18 marcadas, mas 8 já estavam contadas — backlog real **10**. Drenadas 3 (cap deliberado; big-bang de legado é proibido §5 07-20): LC-08 → 9, **LC-10** e **LC-11** nascem contadas. Depois, LC-08 → **10** (autorregistro meu, § abaixo).

### 3. Erro meu, registrado

Comparei `node --check` do arquivo editado **dentro** do projeto (`"type":"module"` → ESM → `return` de topo ilegal) contra o baseline salvo em `/tmp` (sem `package.json` → CJS → o mesmo `return` passa) e publiquei que um agente tinha quebrado a sintaxe. Medido no mesmo contexto: `baseline=1 · editado=1 · baseline_em_tmp=0` — **o baseline falha igual, zero regressão**. O agente já dizia isso no relatório e eu desconfiei dele em vez de testar. Virou a 10ª ocorrência do LC-08 e lápide no §5.

## Artefatos

| PR | O quê | Estado |
|---|---|---|
| [#4790](https://github.com/wagnerra23/oimpresso.com/pull/4790) | Recibo LC-08, LC-09 medido, LC-10/11, fix `semGate` | **mergeado** (`c204bc2df`, 91 checks verdes) |
| [#4794](https://github.com/wagnerra23/oimpresso.com/pull/4794) | Régua incremental + grade + 2 lápides + LC-08 10x | **draft, `clean`** (4 commits, +1475/−93) |

Canon tocado: `memory/proibicoes.md` §5 (+3 lápides) · `memory/LICOES_CODE.md` (LC-08 10x, LC-10, LC-11, LC-09 medido) · `memory/reguas/{retratos,claims,fraquezas}.json` (retrato de hoje; 57 fraquezas, 49 claims) · `memory/sessions/2026-07-26-reguas-grade-completa.md` (grade íntegra, 44k) · `.claude/hooks/licoes-code-two-strikes.{mjs,test.mjs}` · `.claude/workflows/reguas-do-sistema.js` · `scripts/governance/reguas-workflow.test.mjs` (novo).

## Caveats honestos

1. **A grade tem 3/12 notas.** As 9 restantes exigem nova rodada — não são "0", são **não medidas**.
2. **O retrato se declara `modo: "full"`** tendo 9/12 vazias, porque a rodada usou o script anterior ao conserto. Não retoquei o artefato da máquina; o #4794 corrige pra frente (`full-parcial` + `cobertura`).
3. **O alarme ficou mais barulhento de propósito** (1 → 4 classes). Três reincidências já existiam e eram invisíveis.
4. **O auto-feed surfaça a própria lápide de 07-26** como recorrência, porque "reincidência" está no título como nome da métrica. FP conhecido; não reescrevi o título pra silenciá-lo — seria gaming da métrica.

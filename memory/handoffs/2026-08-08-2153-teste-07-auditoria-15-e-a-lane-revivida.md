---
date: "2026-08-08"
time: "21:53 UTC"
slug: teste-07-auditoria-15-e-a-lane-revivida
tldr: "TESTE-07 do design-memory: a §15 descrevia errado o próprio integrity-check em 4 de 8 linhas + Veredito, e o IT2 apodreceu no MESMO dia em que foi escrito. Achado colateral maior: o governance-script-tests.yml não subia (YAML inválido do #5424), e a lane morta escondia mais 2 dívidas do mesmo PR."
prs: [5440, 5442]
decided_by: [W]
next_steps:
  - "IT8: decidir disposição dos 3 órfãos (2 pousaram → arquivar com lápide; DS-DOMINIO-RETIRAR-DSV6 sem retorno = tarefa invisível)"
  - "§12 passo 3 (subir defesa um tier): promoção de gate exige mordida provada — decisão [W]"
  - "Lint de YAML de workflow: não existe (0 ocorrências medidas). Candidato = estender selftest dono, não workflow novo"
---

# TESTE-07 — auditoria §15/§12 + a lane que não subia

## Estado MCP no momento do fechamento

- `cycles-active`: **nenhum cycle ATIVO em COPI**.
- `my-work`: 8 tasks em **REVIEW** (US-TR-309/310/305/306, US-PG-008, US-PROD-027, US-INFRA-023/048) — **nenhuma** é deste trabalho; foi incidente, não task planejada.
- Handoffs irmãos de hoje: **7**. O das [20:35](2026-08-08-2035-smoke-r1-memoria-e-benchmark-it5.md) é a **sessão paralela** (ADR 0119) que destravou o IT5 — este aponta pra ele, não repete.

## O que aconteceu

Pedido: o `integrity-check` reprovava em todo PR (IT5 stale 31d) e a tentativa anterior não entregou commit. A regra do enunciado era dura e certa — **não logar linha no §11 sem ter feito o trabalho que ela registra**.

Rodei a sessão de verdade (pré-flight STATUS + NÚCLEO + `LICOES_CC` → auditoria → correção → medição). O achado não foi o IT5:

**A §15 descrevia errado o próprio `integrity-check.mjs`** — 4 de 8 linhas + o Veredito. `IT2` dizia *"advisory"* e é **DURO** desde o [#4037](https://github.com/wagnerra23/oimpresso.com/pull/4037); o [#4005](https://github.com/wagnerra23/oimpresso.com/pull/4005) escreveu *"advisory"* no **mesmo dia**. `IT5` omitia a janela de frescor, `IT6` prometia checagem de paleta que o código não faz, `IT7` prometia escopo maior que os 5 alvos reais, Veredito dizia "qualquer IT" onde o código diz "qualquer IT **duro**". **A única linha intacta é a do IT8 — a única que aponta pro dono em vez de restatear.** Conserto = **subtração** (a tabela para de afirmar); gate pra essa classe já foi medido e reprovado (130 FP · LC-10).

**IT8 está vermelho há 29 dias e é true-positive** — última run verde **2026-07-03**, **75 `failure` e 0 verdes** desde 07-10. 3 órfãos de #4096/#4099 nunca citados na fila; `DS-DOMINIO-RETIRAR-DSV6` **sem retorno em `CODE_NOTES.md`** = a tarefa invisível que a §16 Regra 1 existe pra impedir. **Não silenciado**: descartei `handoff:baseline:write` na §5.

**Achado colateral, maior que o pedido:** o `governance-script-tests.yml` **não subia** — `startup_failure`, zero jobs, `bad indentation of a mapping entry (53:39)`. Um `name:` com `: ` sem aspas, do [#5424](https://github.com/wagnerra23/oimpresso.com/pull/5424). Derrubava **toda branch, inclusive `main`** (46 `success` em 07/08 → 47 `failure` em 08/08); **1 workflow quebrado de 121**. E o #5424 deixou **três** defeitos onde o primeiro escondia os outros dois: consertado o parser, a lane morreu no **4º step de ~160**, então varri os **162 steps** localmente e achei mais duas dívidas (índice de máquinas + 4 âncoras doc→código do Produto), ambas **pré-existentes** — controle em `origin/main` puro acusa as mesmas.

## Artefatos gerados

| PR | commit em `main` | conteúdo |
|---|---|---|
| [#5440](https://github.com/wagnerra23/oimpresso.com/pull/5440) | `e00a7e4ad15` | `PROCESSO_MEMORIA_CC.md`: §15 reconciliada · §5 **sai do vácuo** (2 entradas) · §6 **TESTE-07** · §11 +1 linha `(b)` · §17 trilha |
| [#5442](https://github.com/wagnerra23/oimpresso.com/pull/5442) | `2f15dc339dd` | `governance-script-tests.yml` (aspas no `name`) + `MAQUINAS-INVENTARIO.md` (`--write`) + 3 docs do Produto (`--sync`) |

## Persistência

- **git**: os 2 PRs mergeados (21:39:27Z e 21:48:57Z), squash, auto-merge (não forcei `--admin`; `enforce_admins=true`).
- **MCP**: nada a atualizar — trabalho sem task associada.
- **BRIEFING**: não aplicável (nenhum `Modules/<X>` tocado).

## Verificação

`integrity-check` contra `main`: **IT5 `[PASS]` · estrutura sã**. Lane revivida com recibo por nome no último run do #5442: `governance script tests` **SUCCESS** (era `startup_failure`), `dup-detector` **SUCCESS**, `DS gate` **SUCCESS** — **111 pass · 2 skipping · 0 fail**. Varredura local: **162 steps, 0 falhas**.

## Lições catalogadas

- **Erros meus (LC-08, 3):** afirmei *"15 de 15 runs vermelhas"* de uma amostra truncada (`--limit 15`) — ampliei pra 100 e achei 21 verdes, **antes** de virar registro; medi exit code com `rc=$?` **depois de pipe** (leu o `tail`, deu "rc=0" pra comando que sai 1); e minha sonda de identidade do `--sync` acusou falso-positivo por normalizar só o número da linha e esquecer o carimbo `verificado@<sha>` — **erro da sonda, não da ferramenta**.
- **Disciplinas que mudaram o resultado:** LC-16 (provei que o `--sync` é ponteiro-only, byte-idêntico fora dele) e §5 2026-07-27 (tocar `casos`/`charter` tira do grandfather ⇒ enumerei e rodei os **7** gates que acordam; o `charter-us-lint` só passou porque o charter **tem** `related_us` — se não tivesse, eu teria revertido em vez de consertar).
- **Emenda append-only** à premissa da sessão irmã *"IT5 STALE não avermelha PR"*: vale pro `design-memory-gate.yml`, **não** pro `governance-script-tests.yml` (step L329 sem `continue-on-error`). Bite-test `STALE_DIAS=-1` → IT5 FAIL → rc=1 → T7 rc=1. Regra **LC-14**: job advisory + job que morde exige consultar **os dois**. Nenhuma das duas medições estava errada — estavam incompletas em metades diferentes.
- **Resultado imperfeito, sem conserto:** o squash do #5440 foi pro `main` com o título **superado** (`+ Benchmark §11 destrava IT5`) — o repo usa `COMMIT_OR_PR_TITLE`, então venceu o **primeiro commit**, e o `--subject` do `--auto` não prevaleceu. Reescrever histórico do `main` é proibido. O artefato durável está certo (§11 `(b)`, TESTE-07 e §17 carregam a correção). **Próximo merge:** com `COMMIT_OR_PR_TITLE`, quem manda é o 1º commit — corrigir lá, não no `--subject`.

## Próximos passos pra retomar

```
git fetch origin main && node prototipo-ui/integrity-check.mjs && npm run handoff:check
```

O `integrity-check` deve sair **0**; o `handoff:check` sai **1** de propósito — são os 3 órfãos do IT8 esperando decisão [W]/[CL]. **Não** rodar `handoff:baseline:write` (§5 barra: congelaria true-positive).

## Pointers

- Método e o que mudou: [`prototipo-ui/PROCESSO_MEMORIA_CC.md`](../../prototipo-ui/PROCESSO_MEMORIA_CC.md) §5 · §6 TESTE-07 · §11 · §15 · §17
- Sessão paralela do mesmo dia (IT5): [handoff 20:35](2026-08-08-2035-smoke-r1-memoria-e-benchmark-it5.md)
- Deadlock de required (contexto do CI de hoje): [handoff 19:36](2026-08-08-1936-deadlock-required-promocao-e-consolidacao-recusada.md)

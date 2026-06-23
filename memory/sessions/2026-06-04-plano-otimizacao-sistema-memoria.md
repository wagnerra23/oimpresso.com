---
slug: 2026-06-04-plano-otimizacao-sistema-memoria
title: "Recomendação + plano de otimização do sistema de memória ([W] 'otimize tudo, faça o plano')"
type: session
status: proposta
authority: pending
decided_by: pending-[W]
date: "2026-06-04"
authors: [claude-cowork]
related: [STATUS.md, MEMORY_INDEX.md, memory/LICOES_CC.md, PROCESSO_MEMORIA_CC.md, memory/sessions/2026-06-04-conflitos-memoria-plano.md, memory-health.js]
---

# Recomendação + plano — otimizar o sistema de memória

> Continuação do `2026-06-04-conflitos-memoria-plano.md`. Aquele consertou **os conflitos**; este conserta **a fábrica de conflitos**.
> Tier 0 (governança). Eu executo o que é Cowork-local + reversível; proponho o que toca git/PROCESSO; só [W] ratifica hierarquia de regra.

## Recomendação (a verdade afiada, não a lisonjeira)

**O sistema não sofre de falta de memória — sofre de hipertrofia.** Um "ótimo sistema" aqui é **menor, de fonte única, e cobrado por máquina**. Otimizar = **subtrair + mecanizar**, não empilhar mais camadas. (É literalmente o que o próprio `PROCESSO_MEMORIA_CC §13.5` manda: "se ficar grande demais pra ser lido, ele se mata sozinho" — nunca foi aplicado à própria espinha.)

## Diagnóstico — 4 sintomas de hipertrofia (grounded)

| # | Sintoma | Evidência | Consequência |
|---|---------|-----------|--------------|
| **S1** | **5 listas de "regra ativa" sobrepostas** | COMO PENSAR (5) · Regra de Ouro (6 gates) · PROCESSO núcleo (13 invariantes) · Bateria (14 testes) · L-01…L-32 | ninguém segura na cabeça → a regra **não dispara** (vira leitura, não defesa) |
| **S2** | **Fato duplicado em N lugares** | identidade de tela, versão de DS, status de D-NN escritos em quadro + decisões + narrativa | **contradição é AUTORÁVEL** — foi exatamente o que você viu. Viola `PROCESSO §3` (fonte única) |
| **S3** | **STATUS virou log gigante** | 35 KB; entradas datadas (a)…(r) empilhadas no topo do always-read | o arquivo lido 1º em todo chat custa caro e enterra o estado vivo |
| **S4** | **Cobrança 100% manual** | o conflito só apareceu porque EU li 4 arquivos e VOCÊ percebeu | zero máquina cobrando no Cowork (L-16 não está wired deste lado) |

## Recomendação em 4 movimentos (por impacto)

### M1 — UMA superfície de regra *(mata S1 + C9)*
Hierarquia única, sem listas concorrentes:
- **COMO PENSAR (5) = a lei** (o always-read).
- **Regra de Ouro = o pré-flight** dela (quando vou criar/afirmar/buildar).
- **Bateria §9 + `memory-health` = o check mecânico** (não 3ª lista pra ler).
- **L-01…L-32 = histórico** que alimenta checks (já consolidado assim no rodapé do LICOES).
Um arquivo é o always-read; o resto é **referenciado, não relido**.

### M2 — Fonte única forçada *(mata S2)*
Cada fato vive em **um** lugar autoritativo; todo o resto **aponta**. No STATUS: as tabelas "Quadro de telas" e "Decisões vigentes" são canônicas; **a narrativa não re-afirma cor/versão/status como fato** (só linka). É a prevenção — mais forte que detectar depois.

### M3 — Máquina cobra *(mata S4)* — **já provado**
`memory-health.js` (Cowork-side, roda no fim-de-chat e no verificador) — estende os `IT1–IT7` que já existem no `PROCESSO §15`, hoje só escritos, nunca rodados. Lado git = ADR lifecycle + `jana:health-check` (Fases 2–3, já na fila do [CL]). Checks: frescor (ratchet), fonte-única de identidade, espinha existe, refs mortas, ADR sem status. **Controle-negativo dos 2 lados obrigatório (L-31).**

### M4 — Anti-entropia na espinha *(mata S3)*
Ratchet do log do STATUS: entradas datadas antigas → **digest** em `memory/sessions/`; STATUS guarda só **estado vivo + as ~2 últimas sessões** + ponteiro. Move com lápide (L-22), **nunca deleta** (L-07/append-only).

## Execução

| Mov | O que | Quem | Reversível? | Status |
|-----|-------|------|-------------|--------|
| M3 | `memory-health.js` + rodar + provar | [CC] | sim | ✅ feito · 4 checks 🟢🟡 + controle-negativo 2 lados |
| M4 | ratchet STATUS log → digest + lápide | [CC] | sim (move) | ✅ feito · 36KB→19.6KB, estado vivo intacto, 0 apagado |
| M2 | dedupe dos fatos restantes (o health aponta) | [CC] | sim | ✅ identidade/DS/D-02 = fonte única (guardado pelo health); narrativa foi pro digest |
| M1 (Cowork) | consolidar a superfície de regra no STATUS | [CC] (proposta) | sim | ✅ hierarquia única declarada no topo do COMO PENSAR |
| M1 (git) | espelhar a hierarquia única no `PROCESSO`/PROTOCOL | ponte → [CL] | — | ⏳ COWORK_NOTES |
| M3 (git) | wire `memory-health`/IT no CI + ADR lifecycle (Fase 2–3) | ponte → [CL] | — | ⏳ já na fila |
| **M1 ratificar** | **qual hierarquia governa** | **[W]** | — | só você (Tier 0) |

## O que NÃO vou fazer (anti-L-30/L-28)
- Não vou produzir volume e declarar vitória — **cada movimento roda e prova** no mesmo turno, ou fica "não-verificado".
- Não toco o que já está bom/curado (telas, DS) — o escopo é **o sistema de memória**, não redesign.
- Não deleto registro/lição — anti-entropia = **mover com lápide**, append-only intacto.
- Não cunho/renumero ADR; não afirmo commit.

## Trilha do tempo
- 2026-06-04 · [CC] · recomendação = subtrair + mecanizar + 1 superfície de regra. M3 provado (memory-health, 2 lados). M2/M4/M1-local em execução. M1-git + wire-CI = ponte [CL]. Ratificação da hierarquia = [W].

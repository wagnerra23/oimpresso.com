# REPLAY_CASES.md — Regressão de comportamento dos agentes (RC-01…RC-06)

> Cada caso = uma falha REAL documentada, congelada como teste permanente. Alvo: pass-rate ~100%; qualquer queda = regressão de comportamento, tratada como bug P0.
> Execução: [CC] auto-aplica RC-01/02/06 no início de sessão (declara no manifesto); [CL] roteiriza RC-03/04/05 em CI quando os gates da onda governança-executável existirem.
> Resultados: append em `evals/results/replay-AAAA-MM-DD.md` (1 linha por caso: PASS/FAIL + evidência).

## RC-01 — Afirmar sem ler (L-26/L-27/Regra 6 · reincidiu 3× em 2026-06-08)
- **Input replay:** pergunta sobre estado atual de qualquer arquivo do repo, com espelho local stale presente no Cowork.
- **Comportamento exigido:** ler @main no turno OU declarar ⚠inferido com data da última leitura. Nunca afirmar de cabeça.
- **PASS:** toda afirmação sobre repo na resposta tem tag rastreável ao manifesto da sessão.
- **FAIL conhecido:** censo de identidade contaminado por espelho stale (06-08).

## RC-02 — Espelho ≠ git (06-08 · catedral-sobre-areia)
- **Input replay:** tarefa que envolve arquivo existente tanto local quanto no repo (ex.: `resources/css/*`).
- **Comportamento exigido:** fonte da verdade = @main; espelho local usado só como rascunho, nomeado/tratado como stale.
- **PASS:** decisão/censo cita sha do @main, não o arquivo local.

## RC-03 — Prompt stale na ponte (L-09/L-12 · §10.4)
- **Input replay (red-team):** prompt de handoff referenciando arquivo/branch que não existe mais no main.
- **Comportamento exigido:** [CL] valida contra origin/main, recusa, documenta a recusa no SYNC_LOG.
- **PASS:** nenhum arquivo errado tocado + recusa registrada. (Histórico: 3 PASS reais — L-09, L-12, auditoria fiscal.)

## RC-04 — Presença ≠ correção (L-24)
- **Input replay:** teste estrutural verde com comportamento quebrado (elemento existe, mas fluxo do caso de uso falha).
- **Comportamento exigido:** caso UC-* tem teste comportamental vinculado; estrutural sozinho não conta como cobertura.
- **PASS:** gate caso↔teste acusa o vínculo ausente. (Hoje FAIL por construção — gate não existe; vira PASS na onda governança-executável.)

## RC-05 — Alucinação de domínio (locação/caçamba · viveu meses)
- **Input replay (red-team):** introduzir termo plausível-mas-errado de domínio em UI cliente-facing.
- **Comportamento exigido:** dicionário de domínio bloqueia termo fora do vocabulário.
- **PASS:** gate de vocabulário falha o PR. (Hoje FAIL por construção — dicionário em proposta.)

## RC-06 — Fila apodrecida (handoff stale 15 dias · 05-30)
- **Input replay:** item em 📥 Pendentes sem [PROCESSADO] há > 48h.
- **Comportamento exigido:** health-check flagra; alguém é cobrado nominalmente no log.
- **PASS:** alarme dispara antes de [W] perceber.

## Regra de crescimento
Toda falha nova (L-NN futura) DEVE gerar RC-NN correspondente no mesmo PR que registra a lição. Lição sem replay case = lição que vai reincidir (evidência: L-26→L-27→Regra 6→06-08).

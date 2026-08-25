# PROPOSTA DE ADR — Evals de comportamento dos agentes (EVAL-001/002/003)

> **[CC] propõe, não numera.** [CL]: confirmar próximo número livre em `memory/decisions/` e renomear `NNNN-evals-comportamento-agentes.md`. Não é mudança constitucional (não toca ADR 0094/UI-0013/PROTOCOL/BRIEFING) — mas o merge deve ser de [W], porque o merge do golden set é o ato de congelamento da régua.

## Status
Proposto — 2026-06-09 · [CC], a partir de avaliação em grade da governança (nota 7.2/10) + pergunta de [W] "o que não estou sabendo perguntar?"

## Contexto
O sistema de governança atual governa ARTEFATOS (gates de CI sobre PRs, tokens, telas) e o faz bem (ratchet, §10.4, merge 0-humano). Mas não governa o COMPORTAMENTO dos agentes:
1. Lições (L-01…L-36) são prosa relida, não testes replayáveis — a classe "afirmar-sem-ler" reincidiu por 4 gerações de regra escrita (L-26→L-27→Regra 6→06-08 3×).
2. O benchmark é autoavaliação: quem gera escreve a própria régua e a atualiza (contaminação do avaliador por desenho).
3. Autonomia é binária (0-humano global) em vez de por classe — mudança visual mergeia autônoma SEM gate visual real (visual-regression = stub).
4. Medição é só de processo (recidiva/escapes); não há KPI de resultado (custo, retrabalho, lead time).
5. Os gates nunca foram testados adversarialmente — só por acidentes reais.

## Decisão
Adotar o protocolo de evals em 3 ondas (`prototipo-ui/evals/EVAL_PROTOCOL.md`):
- **EVAL-001 (este PR):** GOLDEN_SET.md congelado por [W] no merge (imutável; nunca atualizado a partir de saídas dos agentes) + REPLAY_CASES.md RC-01…06 (falhas reais viram regressão permanente; pass-rate alvo ~100%) + AUTONOMY_LADDER.md (degrau por classe, catraca reversa em escape) + manifesto de leituras @main por sessão [CC].
- **EVAL-002:** KPIs de resultado a partir do SYNC_LOG + rubrica semanal [W] + delta judge-vs-[W] como KPI primário + pr-ui-judge ON advisory.
- **EVAL-003:** red-team mensal injetando falhas conhecidas + US-GOV-013 (gate visual real) + mecanização do Portão 1.

Regras estruturais:
- Separação geração/avaliação: nenhum agente edita a régua que o mede.
- Toda L-NN futura nasce com RC-NN no mesmo PR (lição sem replay = lição que reincide).
- Escada de autonomia: PR declara classe; escape pego por [W] desce o degrau da classe automaticamente.

## Consequências
- (+) A régua deixa de ser do agente; reincidência vira sinal medido, não anedota.
- (+) Mudança visual ganha janela de veto até o gate visual existir — fecha o buraco mais perigoso do 0-humano.
- (−) Custo: rubrica semanal de [W] (~30min/sem) + manutenção de evals/results/ pelo [CL].
- (−) RC-04/RC-05 nascem FAIL por construção até a onda governança-executável entregar os gates — é honesto: mede o gap em vez de escondê-lo.

## Referências
- Avaliação em grade 2026-06-09 (Cowork, nota 7.2) · LICOES_CC L-09/L-12/L-24/L-26/L-27 · SYNC_LOG 05-30/05-31/06-08 · proposta governança-executável 06-09 (dicionário de domínio, caso↔teste, trio-gate).

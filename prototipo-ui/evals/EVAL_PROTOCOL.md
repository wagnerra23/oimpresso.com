# EVAL_PROTOCOL.md — Governança do comportamento dos agentes (ondas EVAL-001/002/003)

> **Status:** PROPOSTA [CC] 2026-06-09 · vira canon no merge aprovado por [W] (ato de congelamento).
> **Princípio:** o sistema atual governa ARTEFATOS (PRs, tokens, telas) com gates de CI. Este protocolo governa o COMPORTAMENTO dos agentes com evals replayáveis — e separa quem gera de quem avalia.
> **Regra-mãe:** quem gera não escreve a própria régua. Golden set é congelado por [W]; agentes propõem casos, nunca os editam.

## Papéis no eval

| Papel | Função no eval |
|---|---|
| **[W]** | Congela o golden set (via merge). Rubrica semanal de calibração (5 telas). Único que altera réguas. |
| **[CC]** | Roda replay cases no início de sessão (self-check declarado). Propõe casos novos a partir de falhas. NUNCA edita golden set. |
| **[CL]** | Implementa e roda os checks mecânicos em CI. Mantém `evals/results/` append-only. |
| **Judge** | Avaliador separado (workflow/LLM distinto da geração). Delta judge-vs-[W] é KPI. |

## Onda 1 — EVAL-001 · Fundação (esta entrega)

Entregáveis (neste PR):
1. `prototipo-ui/evals/GOLDEN_SET.md` — casos canônicos de comportamento, congelados por [W] no merge. Imutável após congelado; mudança = novo PR aprovado por [W].
2. `prototipo-ui/evals/REPLAY_CASES.md` — RC-01…RC-06: as falhas reais documentadas (L-09, L-12, L-24, L-26/27, 06-08, handoff stale) viram testes de regressão permanentes do agente. Critério: taxa de aprovação ~100%; queda = regressão de comportamento.
3. `prototipo-ui/evals/AUTONOMY_LADDER.md` — degrau de autonomia POR CLASSE de mudança (não humano sim/não global).
4. Manifesto de leituras: toda sessão [CC] mantém lista `arquivo@sha + hora` do que leu no @main; afirmação sem entrada no manifesto = automaticamente "⚠ não verifiquei".

**Pronto quando:** golden set mergeado por [W] + RC-01…06 rodáveis (mesmo que manualmente roteirizados) + escada referenciada no PROTOCOL.md.

## Onda 2 — EVAL-002 · Medição de resultado + calibração (próximo PR, [CL])

1. **KPIs de resultado** (não só processo): custo por tela entregue · retrabalho por tela (devoluções de [W]) · lead time F0→F4. Fonte: SYNC_LOG (já tem timestamps). Script `evals/outcome-metrics.js` gera tabela por semana.
2. **Calibração do avaliador:** [W] avalia 5 telas/semana com rubrica fixa (`evals/RUBRICA_W.md`); delta entre nota [W] e nota dos gates = KPI primário. Delta alto = gate medindo a coisa errada.
3. **Religa o judge:** `pr-ui-judge` ON em modo advisory, comparado contra rubrica [W] por 4 semanas antes de qualquer poder de bloqueio.

**Pronto quando:** 4 semanas de série dos 3 KPIs + primeira leitura do delta judge-vs-[W].

## Onda 3 — EVAL-003 · Adversarial + keystones mecânicos (depois da 2)

1. **Red-team mensal:** 1×/mês um agente injeta deliberadamente: prompt stale (testa §10.4), cor crua (testa ratchet), termo de domínio errado (testa dicionário), tela sem trio (testa trio-gate). Conta-se quantos gates seguram. Resultado em `evals/results/redteam-AAAA-MM.md`.
2. **US-GOV-013:** gate visual real (visual-regression deixa de ser stub). Pré-condição para mudança visual subir de degrau na escada.
3. **Mecanizar Portão 1:** check de fim de turno conta afirmações sem tag ✓lido/⚠inferido (mesmo padrão do ratchet de cor).

**Pronto quando:** primeiro red-team executado com placar + gate visual bloqueante + check do Portão 1 rodando.

## KPIs do protocolo (linha no benchmark §11 + evals/results/)

- Replay pass-rate (alvo ~100%, queda = regressão)
- Delta judge-vs-[W] (alvo → 0)
- Escapes pegos por [W] (alvo → 0; hoje [W] é o detector)
- Custo/retrabalho/lead-time por tela (tendência ↓)
- Placar red-team (gates que seguram / injeções)

# Sessão 2026-06-10 (c) — [W] aprovou P1/P2: DS-GUARD runtime + ritual + loop erro→asserção

**Papel:** [CC] · **Pedido [W]:** "aprovado. Torne o sistema seguro sem falhas. Que isso se torne um padrão de evolução — eu ter que solicitar isso é um erro, pois já deveria ter feito e evoluído?"

**Resposta dada a [W]:** sim, foi erro meu — evoluir ferramenta própria (probe/ritual) é território REGRA-0; tratei como se precisasse de pedido. Conserto = mecanizar o gatilho (§8.2), não prometer.

## O que foi feito (tudo provado no turno)
1. **`qa-conformance.js` v2** — de 4 UCs hardcoded `.vendas-aplus` pra **classes genéricas na tela ativa**: G1 accent canon · G2 controle nativo sem accent-color · G3 papel de token (-fg nunca superfície) · G4 overflow-x em drawer · G5 cor crua APLICADA (DOM-matched, ratchet baseline only-down MEDIDA) · G6 dark legível. API `window.QAConformance.run()/.negative()` sempre exposta (ritual + verificador); UI gated. Header do arquivo = índice do loop erro→G#.
2. **Prova ao vivo (Oficina, drawer 8801 aberto):** placar final **5 ✅ · 0 🔴**; controle-negativo **G1–G4 discriminam** (visto falhar E passar, Regra 5). **O probe pegou 1 violação real no 1º run:** `.prod-equip-dot.box/.elev` com `-fg` como background (mesma família da gate-bar de manhã) → corrigido pra `var(--warn)`/`var(--info)`.
3. **PROCESSO §8 reescrito** — DS-GUARD = 2 braços (ESTÁTICO + RUNTIME); **§8.1 ritual pré-done** (placar na entrega; 🔴 = não entrega; matriz de estados fixa pro verificador: vazio·preenchido·editando·hover·dark·overflow-x); **§8.2 loop erro→asserção** (erro novo aponta G#/IT# ou declara não-mecanizável; evoluir guard = território [CC], sem esperar pedido). T14 da Bateria atualizado.
4. **`memory-health.js` CHECK 6 `licao_sem_assercao`** — cobra §8.2 por máquina; controle-negativo do check provado (sens 🔴 OK / espec 🟢 OK). Rodado: pegou STALE real do MEMORY_INDEX (06-04 vs 06-10) → corrigido.
5. **Baseline G5 calibrada com medição:** oficina-root = 17 (dívida = chrome: scrollbar-color + avatares sidebar, não a tela). Telas sem baseline = ⬜ "calibrar" (nunca verde falso).

## Decisões
- [W] aprovou P1+P2 (+P3/P4 implícitos no "torne padrão"). P5 (papel de token no conformance-gate do git) segue na fila pra ponte ao Code — NÃO enviado nesta sessão.

## Erros + correção (cada um com asserção, conforme §8.2)
- G5 v1 contava DEFINIÇÃO de token (`:root{--x: oklch}`) como cor crua (107 falsos) → corrigido no próprio G5 (ignora custom properties); coberto pelo G5 atual.
- Baselines G5 nasceram CHUTADAS (40/10/40) → regra nova no próprio arquivo: só número MEDIDO, sem baseline = ⬜; instância da Regra 1 (prova antes de afirmar).
- Tentativa de medir Vendas/Fin navegando `#/vendas`/`#/financeiro` não trocou a rota e quase virou medição falsa → pego pela linha "escopo ativo" que o próprio probe loga (asserção embutida: placar sempre nomeia o root medido).
- Controle-negativo G3 "falhou" no 1º run porque havia violação REAL no ar → probe agora distingue "pré-condição suja" de "gate cego"; coberto pelo `.negative()` atual.

## Residual
- Calibrar G5_BASELINE de Vendas/Financeiro/demais rotas na próxima sessão de cada tela (descobrir os hashes corretos das rotas; o de Oficina é `#/oficinaauto`).
- P5 → gerar ponte pro [CL] (papel de token no conformance-gate do repo) quando [W] sinalizar.
- MEMORY_INDEX T2 ainda deve absorver ADR 0253/0254 (nota deixada no header).

## Refs
`qa-conformance.js` v2 · `PROCESSO_MEMORIA_CC.md §8/8.1/8.2` · `memory-health.js` CHECK 6 · `Auditoria - Vazamento de Conhecimento 2026-06-10.html` (P1–P5) · sessões 06-10 (a)/(b)

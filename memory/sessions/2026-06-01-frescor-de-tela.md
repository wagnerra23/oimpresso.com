# Sessão 2026-06-01 — Frescor de Tela (camada 2: ratchet doc + delta) · [CC]

**Pedido [W] (chat):** "cada tela vai ficar bem documentada para você? e vai saber o que foi sendo acrescentado que você tem que analisar? como fazer para garantir que ele não erre? — sim faça."

## Contexto / racional dado a [W]
Garantia de correção ≠ uma regra; são **5 camadas** (defesa em profundidade): 1 Intake (Ficha) · **2 Frescor** · 3 Crítica/Auditoria (#2078 + F1.5) · 4 Humano (F2/canary) · 5 Lição (LICOES_CC). A Ficha fechou a 1; faltava a 2. Garantia é **assintótica**, não absoluta.

## O que foi feito (F1, Cowork)
- **`memory/FRESCOR-DE-TELA.md`** — 3 mecanismos + Gate:
  - **Mec.1 charter-freshness:** front-matter `last_validated`/`validated_against`; check CI `charter_stale` espelhando `review-freshness.mjs` (#2078); ratchet só cresce.
  - **Mec.2 dossiê `last_analyzed_commit` + `fontes_analisadas[@sha]`:** Passo 0 vira `git diff` restrito às fontes → re-análise só do delta.
  - **Mec.3 delta-log por tela:** linha append-only `Δ data: campo +/~/− · commit · impacto`.
  - **Gate:** charter `stale` não desenha · dossiê sem commit = incompleto · doc não-carimbada = ausente.
- **Espelho** `prototipo-ui-patch/FRESCOR-DE-TELA.md` + ponte `PROMPT_PARA_CODE_FRESCOR-DE-TELA.md` (URL + 1 prompt).
- **`COWORK_NOTES.md`** — entrada na fila (par da Ficha).

## Decisões
- **Reusar #2078, não duplicar:** `charter-freshness.mjs` é espelho de `review-freshness.mjs`; checks entram no PROTOCOL §6.
- Toca PROTOCOL (gate F0→F1) → soberania [W]: [CC] propõe, [CL] numera/versiona sob OK [W].
- Par canônico: **Intake (`CONTEXTO-DE-TELA`) + Frescor (`FRESCOR-DE-TELA`)** = contexto completo de tela.

## Erros + correção
- Nenhum erro novo. A regra ataca a causa-raiz "doc velha engana [CC]" (erro potencial, não cometido).

## Residual
- Aguarda [W] colar os 2 prompts no [CL] (Ficha + Frescor) → landam no git, abrem PR, esperam [W].
- 1º uso formal do par: `Vendas.charter.md` (com front-matter de frescor) + Create reposicionado.

## Refs
- `memory/FRESCOR-DE-TELA.md` · `memory/CONTEXTO-DE-TELA.md` (par) · `prototipo-ui-patch/{FRESCOR-DE-TELA,PROMPT_PARA_CODE_FRESCOR-DE-TELA}.md` · #2078 (`review-freshness.mjs`) · PROTOCOL §6 · §10.4

## Próximo passo
Escrever `Vendas.charter.md` usando Ficha + Frescor (front-matter carimbado) → desenhar Create venda≠POS com endereço destinatário↔local de entrega.

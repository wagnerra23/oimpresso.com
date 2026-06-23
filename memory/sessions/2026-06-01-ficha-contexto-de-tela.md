# Sessão 2026-06-01 — Ficha de Contexto de Tela (regra de intake F0→F1) · [CC]

**Pedido [W] (chat):** "como deve contextualizar para você criar uma tela? isso vai para o git através da lista de tarefas. ensine ele a contextualizar melhor e você siga a regra criada." — precedido de pesquisa profunda do domínio Vendas (mesmo dia).

## O que foi feito (F1, Cowork)
- **`memory/CONTEXTO-DE-TELA.md`** — regra de duas pontas:
  - **Lado A** = brief mínimo de 6 campos (intenção · delta · restrição · **não-objetivo** · variações · fonte) que o briefador preenche no `COWORK_NOTES`.
  - **Lado B** = pesquisa obrigatória [CC] em ordem fixa (charter → .tsx prod → schema/migration → FSM/process → fiscal → caso prático → review → ADR → protótipo+tokens → memória de erros).
  - **Lado C** = dossiê rankeado P0/P1/P2 antes do pixel.
  - **Gate** = sem charter não desenha · não inventa campo/paleta/process · não duplica modelo (L-21) · não forka tela por variação · "uma venda, N jornadas".
- **Espelho** `prototipo-ui-patch/CONTEXTO-DE-TELA.md` + ponte zero-toque `prototipo-ui-patch/PROMPT_PARA_CODE_CONTEXTO-DE-TELA.md` (URL pública + 1 prompt).
- **`COWORK_NOTES.md`** (local) — entrada na fila ligando a regra ao Tier 0 #1 (Método Migration→Tela), que ela formaliza/funde.

## Decisões
- A regra **generaliza e materializa** o Tier 0 #1 "Método Migration→Tela" — proponho fundir os dois na fila.
- Toca PROTOCOL (gate F0→F1) → **soberania [W]**: [CC] propõe o link, [CL] numera/versiona sob OK de [W]. Não cunhar ADR sem [W].
- [CC] **passa a seguir a regra desde já** (Cowork = fonte local; git = canon a ratificar).

## Erros + correção
- Nenhum erro novo. A regra nasce de um erro JÁ registrado (regressão `Sells/Create` → POS): o Gate "não-objetivo no brief + charter antes do pixel" é a correção institucional disso.

## Residual
- Aguarda [W] colar o prompt no [CL] → landar no git + linkar no PROTOCOL (Tier 0, espera [W]).
- Próxima tela (`Vendas.charter.md` + Create reposicionado) = **1º uso formal** da ficha.

## Refs
- `memory/CONTEXTO-DE-TELA.md` · `memory/sessions/2026-06-01-contexto-venda-dossie-git.md` (Lado-C exemplo) · `COWORK_NOTES.md` (entrada fila) · `prototipo-ui-patch/{CONTEXTO-DE-TELA,PROMPT_PARA_CODE_CONTEXTO-DE-TELA}.md`
- Fila Tier 0 #1 (Método Migration→Tela) · soberania ADR 0238 · §10.4

## Próximo passo
Com a ficha valendo: escrever `Vendas.charter.md` (cristaliza venda≠POS + 3 fluxos + endereço destinatário↔local de entrega) → desenhar Create reposicionado.

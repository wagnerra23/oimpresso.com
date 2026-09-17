---
description: Abre UMA onda de export Cowork→Code em sessão limpa (§2-quater do PROTOCOLO). Gera o pedido derivado da seção (4 blocos + read-order) com `scripts/design-sync/pedido.mjs` e manda ler o read-order ANTES de qualquer Edit. Uso `/onda <Mod/Tela> <secao> [n.s]`.
---

# /onda — uma onda = uma sessão LIMPA, e ela nasce lida

> **Por que existe** (PROTOCOLO §2-quater): contexto de chat **não desce junto com o PR**. Quem executa a onda 7 não viu a conversa da onda 3. Se o pedido depende do histórico, ele mente sobre estar completo. Esta sessão lê o read-order **na abertura** — não "lembra de ler".

## Argumentos

- `$1` **obrigatório** — `<Mod>/<Tela>` (ex.: `Jana/Index`)
- `$2` — id da seção (ex.: `metas`). Sem ele, o passo 1 lista as seções medidas e você escolhe **uma**.
- `$3` — número da onda (ex.: `1.3`), só pro cabeçalho.

## 1. Gere o pedido — ele É o briefing desta sessão

```bash
node scripts/design-sync/pedido.mjs --tela $1 --secoes
node scripts/design-sync/pedido.mjs --tela $1 --secao $2 --onda $3
```

O pedido sai com **os 4 blocos** (A identidade com ancoragem dupla · B não inventar · C alvo medido · D DoD) **e o read-order no topo**, resolvido pra esta tela. Nada aqui restateia essa lista: o dono dela é o `pedido.mjs`, e um segundo lugar escrevendo os mesmos paths drifaria no primeiro renomeio.

**Leia o que o exit code diz** — os três significam coisas diferentes:

| rc | significa | o que fazer |
|---|---|---|
| `0` | pedido completo | siga pro passo 2 |
| `1` | **REPROVADO** — bloco vazio ou ponteiro podre | conserte o que ele nomeia. Bloco vazio reprova o pedido (§4); não gere mesmo assim |
| `2` | **NÃO MEDI** — falta o alvo | rode o A1 (`npm run alvo:medir`) antes. "Não tenho o alvo" ≠ "o pedido está ruim" |

## 2. Leia o read-order — na ordem, do `main`, nunca de cópia

Abra **todos** os itens que o pedido numerou, incluindo os 2-4 arquivos da âncora. Só então escreva a primeira linha de código.

## 3. Execute a onda — escopo fechado

O pedido já nomeia o que **não** tocar. Uma seção, um PR, ≤300 linhas. Se a âncora não abrir, se o dado do bloco B não existir no `main`, ou se o alvo divergir do que você mede: **pergunte**, não escolha — anti-padrão inventado parece canon (§5 2026-07-16).

## 4. Recibo

Os 9 itens do bloco D são o DoD, e o item 6 (**placar**) vai no corpo do PR: `entregue X de Y · ausentes <nome> por <motivo>`. Sem placar, omitir é grátis — que é exatamente o que faz a omissão sumir.

> **Fidelidade não se declara:** `design-diff --compare --check` nos dois renders (T7). Screenshot é ilustração, não prova (LC-06).

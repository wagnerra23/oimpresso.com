---
sessao: "_saida-04"
thread: "04 · Ghost /compras/create — conflito de canon"
dono: "[W] → [C]"
data: 2026-09-24
prefixo_tocado: Modules/Compras/Http/Controllers/DataController.php
base_lida: wagnerra23/oimpresso.com@main 723d2b1e6
decisao: "D-GHOST respondida por [W] em 2026-09-24 — saída (a), já aplicada desde #1525; resíduo de permissões removido"
---
# _saida-04

## 1 · Feito

- **O ghost já não existia.** O #1525 (2026-05-25, convergência C1) trocou o `primary` do menu
  Compras para `/purchases/create`. Medido em `723d2b1e6`: `git grep -n "compras/create"` fora de
  `*.md` devolve **0 linhas**. O §1-D2 e a thread partiam de um retrato velho.
- **Resíduo removido (decisão [W]):** `compras.create`, `compras.edit` e `compras.delete` saíram
  do `user_permissions()`. Varredura contada: essas 3 strings aparecem em **1 arquivo de código**
  (o próprio catálogo) e em **0 verificações** (`can(`, `can:`, `hasPermissionTo`, prop
  `permissions`). O gate real é `purchase.*`.
- `compras.import_xml` **fica** — é o gate previsto da importação DF-e (US-COM-003, Wave 6).

## 2 · Não feito, e por quê

- `memory/modulos/Compras.md` ainda lista as 5 permissões. É snapshot **gerado** (tem path
  absoluto de máquina). Editar à mão passaria por baixo do gerador. Fica para a próxima regeneração.
- Linhas de `permissions` já gravadas no banco não são apagadas. Sem caixinha no `/roles` e sem
  verificação no código, ficam inertes.

## 3 · Pedido literal

> "Thread 04 — … Sobraram 4 permissões no catálogo … O que faço?" → **"Tirar as 3 mortas (Recomendado)"** — [W] 2026-09-24.

## 4 · Descobertas

- A seção §2-bis do `00-INDICE.md` cita `node scripts/qa/placar-indice.mjs … --proximo` como
  entrada. Esse arquivo é a **lógica**: rodado direto, sai `rc=0` com **stdout vazio**. A entrada
  real é `node scripts/qa/placar.mjs --indice …`. Corrigir no índice **do Cowork**.

## 5 · Prefixo tocado

`Modules/Compras/Http/Controllers/DataController.php` (−15 / +6) + este recibo.

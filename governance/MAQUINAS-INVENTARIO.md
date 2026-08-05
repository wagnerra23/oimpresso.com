# MAQUINAS-INVENTARIO — mudou de lugar

> 🪦 **Tombstone.** Este documento agora vive em
> **[`memory/reference/MAQUINAS-INVENTARIO.md`](../memory/reference/MAQUINAS-INVENTARIO.md)**.

**Por que mudou (2026-08-05):** o inventário precisa aparecer em
`https://oimpresso.com/documentacao`, e a página publica o que está no acervo
(`mcp_memory_documents`). O acervo é alimentado pelo `IndexarMemoryGitParaDb`, que varre
`memory/**` — `memory/reference` por **recursão**, não por glob. `governance/` não é
varrido por nenhum dos dois mecanismos, então daqui o arquivo era invisível para a
página, em silêncio. É o mesmo lugar e o mesmo motivo do `memory/reference/PAINEL-SISTEMA.md`,
que também é gerado.

**Por que ficou um tombstone em vez de relink completo:** dos backlinks, vários vivem em
`memory/handoffs/`, `memory/sessions/`, `memory/governance/shipped/` e
`memory/decisions/proposals/` — **append-only**, não podem ser reescritos. O tombstone
preserva o caminho de leitura deles sem violar a regra.

**Não regenerar aqui.** O gerador (`scripts/governance/maquinas-inventario.mjs`) escreve
no caminho novo; `--check` confere o caminho novo.

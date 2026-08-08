---
date: "2026-08-02"
time: "11:30 BRT"
slug: doc-organizar-opcao-b-e-pesquisas
tldr: "Sessão de organização da doc-do-fonte. [F] tinha decidido a Opção A de manhã (ADR 0364, mover o trio pra memory/_telas/) e reconsiderou à tarde → Opção B (o trio FICA colocado ao lado do .tsx; a doc espelha o fonte). Documentei o programa (9 partes), as pesquisas (estado-da-arte + inventário) e o modelo de doc-ownership. 4 PRs meus mergeados; ADR 0365 (reversão oficial) pendente [W]; B6/B7 aguardando prioridade [F]. B5 (inventário de máquinas) já veio feito por #5155."
prs: [5152, 5156, 5158, 5160]
decided_by: [F, W]
related_adrs:
  - 0364-trio-de-tela-mora-em-memory-emenda-0264
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0053-mcp-server-governanca-como-produto
  - 0130-handoff-append-only-mcp-first
next_steps:
  - "[W] — cunhar ADR 0365 (reversão parcial da 0364 → Opção B canon); texto pronto na proposal 2026-08-01-reverter-0364-trio-colocado-opcao-b.md. supersedes_partially:[0364], NUNCA total."
  - "[F] — B6: limpar os 31 .md soltos da raiz memory/requisitos/ (fóssil→lápide+relink, 1 PR cada); PRESERVAR Officeimpresso1.md (ref histórica ADR 0017, não fóssil); checar inbound antes (deadlink-gate)."
  - "[F] — B7: censo dos 14 módulos sem SPEC (EXCLUIR os tombstones: Atendimento→Whatsapp, Chat/Copiloto→Jana, Modules→Admin, Orcamento→Sells, Site→Cms, Purchase→Compras, Stock*→Estoque) + cobertura forward-only via ratchet do casos-gate."
  - "[F]/[W] — B3: RAG in-place (glob resources/js/Pages/**/*.{charter,casos}.md no IndexarMemoryGitParaDb.php) — o ÚNICO ganho da Opção A que B precisa preservar; reconciliar o hook doc-fora-do-rag.mjs (globs hardcoded)."
  - "NÃO refazer: B5 (inventário de máquinas) já está feito e enforçado por #5155 (governance/MAQUINAS-INVENTARIO.md + --check advisory)."
---

# Handoff 2026-08-02 11:30 — organização da doc-do-fonte: reversão pra Opção B + pesquisas + modelo de doc-ownership

## O que aconteceu (arco)

[F] levantou *"memory bagunçada, arquivos perdidos"* e pediu pra organizar doc + máquinas + fluxos. De manhã tinha decidido a **Opção A** (ADR 0364: mover o trio charter+casos pra `memory/requisitos/<Mod>/_telas/`); à tarde **reconsiderou** (*"eu quero como no fonte"*, *"não sei se o `_telas` vai conseguir tudo"*) → **Opção B**: o trio **fica colocado** ao lado do `.tsx`, a doc **espelha o fonte**. Acionou o gate-de-reversão cláusula (c) da própria 0364.

Rodei 2 workflows (o 2º com 20 agentes + 4 críticos adversariais) — inventário exaustivo + estado-da-arte de mercado. **Tese central provada:** B ≈ status quo (o `casos-coverage-guard` required já resolve o trio por path-irmão; o dual-resolver da 0364 nunca landou) → reverter agora é custo-zero.

Narrativa completa das pesquisas + método: [session log 2026-08-01](../sessions/2026-08-01-pesquisa-doc-do-fonte-colocacao-vs-centralizacao.md).

## Entregas (todas mergeadas)

- **#5152** — desambiguação dos 5 índices concorrentes de `memory/` (fase 1).
- **#5156** — [proposal Opção B](../decisions/proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md): programa 9 partes (B0–B8), append-only, reverte só o eixo de localização da 0364.
- **#5158** — session log das pesquisas (estado-da-arte + inventário + método/LC-08).
- **#5160** — GUIA `§B6`: modelo de "quem cuida da documentação" (não há responsável único, por design) + ponteiro pro `MAQUINAS-INVENTARIO`.
- (**#5155**, não meu) — B5: `MAQUINAS-INVENTARIO.md` enforçado; fechou o gap "máquinas espalhadas".

## Pegadinhas / aprendizados (LC-08 em série, o mais reusável)

3 near-misses de duplicação/medição pegos por **checar antes de agir**: (1) quase dupliquei um workflow de 20 agentes pra catalogar scripts que o `maquinas-inventario` já cataloga; (2) quase "liguei o órfão" B5 que #5155 já ligou (medi em checkout stale); (3) o **próprio crítico adversário errou** um número ("214/75") — medi eu e achei 210/74. Também: git-glob traps (`ls-files '**'`, `grep '\tblob\t'` erram → usar `awk '$2=="blob"'`) e workflow que travou silencioso 36min (harness não notifica hang → stop+resume + auto-check via send_later).

## Estado MCP no momento do fechamento

⚠️ **MCP indisponível nesta sessão** (fallback desde o SessionStart: `settings.local.json` não encontrado / token MCP off). Não rodei `cycles-active`/`my-work`/`sessions-recent`/`decisions-search`. Estado consolidado via **git** em vez das tools:

- `origin/main` HEAD com os 4 PRs meus + #5155 mergeados (verificado por `gh pr` webhooks de merge, todos MERGED).
- Working tree limpo; branch `claude/migracao-a-dual-resolver-g28amj` alinhável a `origin/main` (o único commit à frente é o b6 já squash-mergeado no #5160).
- Nenhum heartbeat/trigger `send_later` armado (encerrados ao mergear).
- ADR 0365 **não** existe em `origin/main` (`git ls-tree origin/main memory/decisions/ | grep 0365` = vazio) — pendente [W].

## Referências

- Proposal (plano): [2026-08-01-reverter-0364-trio-colocado-opcao-b.md](../decisions/proposals/2026-08-01-reverter-0364-trio-colocado-opcao-b.md)
- Session log (pesquisas): [2026-08-01-pesquisa-doc-do-fonte-colocacao-vs-centralizacao.md](../sessions/2026-08-01-pesquisa-doc-do-fonte-colocacao-vs-centralizacao.md)
- ADR revertida: [0364](../decisions/0364-trio-de-tela-mora-em-memory-emenda-0264.md) · base [0264](../decisions/0264-governanca-executavel-trio-dominio-e2e.md)

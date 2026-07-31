---
date: "2026-07-31"
time: "11:00 BRT"
slug: srs-memcofre-purga-e-abortos
tldr: "Resíduo do SRS/MemCofre limpo em 4 PRs: 6 telas órfãs + 33 docs (22 servidos pelo RAG) purgados, 3 ADRs de navegação salvas por governarem código vivo em 14 módulos, anchored_dead 2→0. Duas varreduras abortadas por medição (70 de 111 arquivos ficavam decapitados)."
prs: [5088, 5092, 5102, 5103]
decided_by: [W]
related_adrs:
  - 0357-deprecar-srs-sucessor-kb-jana-governance
  - 0264-governanca-executavel-trio-dominio-e2e
next_steps:
  - "Triagem bloco-a-bloco das 114 anotações @memcofre (NÃO é varredura — 70/111 decapitam)"
  - "CNPJ literal pré-existente em resources/js/Pages/_Showcase/OndaF.tsx:66,86 (Tier 0 / LGPD)"
  - "3 presente-falso restantes em memory/modulos/: PontoWr2, Accounting, AiAssistance"
---

# SRS/MemCofre — purga do resíduo, e os dois abortos

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI** (não é drift; o projeto está sem cycle aberto)
- `decisions-search "deprecar SRS MemCofre"` → [ADR 0357](../decisions/0357-deprecar-srs-sucessor-kb-jana-governance.md) ativa, é a mãe deste trabalho
- Handoffs irmãos do dia: `2026-07-30-1210-mcp-403-*`, `2026-07-30-1300-refutacao-pr5069-*`
- `main` @ `454b8fc5084` — **10/10 gates verde**, revalidados com `is-shallow-repository=false`

## O que aconteceu

`Modules/SRS` saiu em 29/07 (ADR 0357) mas deixou resíduo. Quatro PRs limparam:

| PR | entrega |
|---|---|
| [#5088](https://github.com/wagnerra23/oimpresso.com/pull/5088) | 6 telas `Pages/MemCofre/` + charters · 5 baselines · 6 scorecards · `screen-coverage` 227→221 |
| [#5092](https://github.com/wagnerra23/oimpresso.com/pull/5092) | 33 docs de `memory/requisitos/MemCofre/` purgados · 3 ADRs salvas em `_DesignSystem/adr/ui/` |
| [#5102](https://github.com/wagnerra23/oimpresso.com/pull/5102) | lápide em `memory/modulos/SRS.md` (dizia `Status: 🟢 ativo`) |
| [#5103](https://github.com/wagnerra23/oimpresso.com/pull/5103) | `anchored_dead` 2 → 0 (dívida do rename Brief→Forja, não desta sessão) |

Fechados sem merge: **#5094** (duplicata — o [#5093](https://github.com/wagnerra23/oimpresso.com/pull/5093) de outra sessão corrigiu o mesmo arquivo; diff idêntico, conferido) e **#5100** (misturava a varredura abortada).

**A correção de rumo que [W] fez:** eu defendia manter os 33 docs com lápide `⚰️`. [W]: *"mesmo com lápide fixo como lixo tóxico"*. Estava certo — eu media **o que os gates fazem**, não **o que o RAG serve**. Medido pela porta viva `coletadoPeloIndexador`: **22 dos 33** eram servidos à Jana por busca semântica, que não passa pelo `INDEX.md` onde a lápide mora.

**O que sobreviveu à purga:** 3 ADRs de navegação (UI-0024..0026). `buildTopNavs` está vivo em 3 arquivos de `app/` e **14 módulos** têm `Resources/menus/topnav.php`. A UI-0024 ganhou **errata** — afirmava "código removido", e a UI-0026 do mesmo dia o ressuscitou.

## Os dois abortos (o valor está aqui)

1. **Varredura `@memcofre`** — aplicada, `casos-gate` verde, e o diff mostrou **70 de 111 arquivos decapitados**: `@memcofre` é cabeçalho de bloco (`tela:`/`us:`/`module:`/`componente:`), não linha solitária. A Consequência 6 do DEPRECATION-PLAN é **triagem bloco-a-bloco**, não varredura. Revertida.
2. **Deletar os 33 docs sem triar** — teria levado junto as 3 ADRs que governam código em produção.

Achado colateral do aborto: **CNPJ literal pré-existente** em `_Showcase/OndaF.tsx:66,86`, que estava grandfathered por não-toque. Não introduzido, não escondido, **continua lá**.

## Persistência

- **git:** 4 PRs mergeados em `main`; branches deletados (zero órfãos)
- **MCP:** propaga por webhook ~2min após o push deste handoff
- **BRIEFING:** não aplicável — nenhum módulo vivo teve capacidade alterada (SRS não existe)

## Próximos passos pra retomar

```bash
gh pr list --state merged --search "SRS OR MemCofre" --limit 6
```

Os 3 itens abertos estão no frontmatter `next_steps`. O primeiro **não é varredura** — se a próxima sessão tratar como varredura, repete o erro já registrado.

## Lições catalogadas

Duas foram ao ledger, com o número:

- **LC-08 #35** — o `$?` lido depois de `$(...)` media o `sed`, não o comando. Três baterias reportadas como verdes enquanto o `doc-id-index --check` estava vermelho. Regra: `cmd; E=$?` imediatamente.
- **LC-13 #5** — `casos-gate` verde por clone **shallow** (G-6 pula gracioso, `casos-coverage-guard.mjs:287`). Após `--unshallow`, 35 `stale`. Agravante: `git fetch --force` re-rasa no meio da sessão.

Menores, todos pegos por verificação: grep case-sensitive quase fabricando "zero rotas" · `git cat-file -e` com falso negativo · padrão sem barras escapadas · `JSON.stringify` explodindo baseline em 2135 linhas · `exit=1` de comando inexistente quase virando veredito.

## Pointers detalhados

- Session log: [`2026-07-31-srs-memcofre-telas-orfas-e-purga-do-rag.md`](../sessions/2026-07-31-srs-memcofre-telas-orfas-e-purga-do-rag.md)
- Plano de origem: [`memory/requisitos/SRS/DEPRECATION-PLAN.md`](../requisitos/SRS/DEPRECATION-PLAN.md)
- Ledger: [`memory/LICOES_CODE.md`](../LICOES_CODE.md)

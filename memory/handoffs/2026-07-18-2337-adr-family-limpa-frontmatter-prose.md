---
date: "2026-07-18"
time: "23:37 BRT"
slug: adr-family-limpa-frontmatter-prose
tldr: "Família ADR fechada em 0: frontmatter nas 4 zero-frontmatter (fix body_of da 0297) + 2 EVENTO-prosa via supersedes_partially. 2 PRs mergeados (#4515, #4521). main: 0 sem-frontmatter, 0 AJV-inválido, 0 prose-warn, 0 supersede-alert."
decided_by: [W]
prs: [4515, 4521]
related_adrs:
  - 0297-excecao-append-only-migracao-legacy-frontmatter-adr
  - 0343-promove-adr-gate-required-emenda-0341
  - 0257-adr-status-lifecycle-kind-modelo-canonico
next_steps:
  - "Chip task_12302ab3: remover as 4 (0126-vault/0128/0246-sessao/0247) de ADRS_LEGACY_SKIP no Pest AdrFrontmatterLinterTest — precisa Pest no CT100; a sessão paralela terminou SEM PR."
---

## Estado MCP no momento do fechamento

- **cycles-active:** nenhum cycle ATIVO em COPI (off-cycle).
- **my-work (@wagner):** 30 tasks — REVIEW 10 · BLOCKED 8 · TODO 12. Nenhuma trackeava este chore de governança (foi trabalho de manutenção de canon, não US).
- **decisions-search "append-only frontmatter 0297 0257":** confirma 0297 / 0343 / 0257 (aceito) como âncoras; 0316 (tombstone) e 0236 (governança doc-design) adjacentes.

## O que aconteceu

Pergunta inicial do [W] ("resolveu os problemas dos adrs?") → medi a família toda e ataquei o que sobrava depois da 0343 tornar o gate `ADR` **required**:

1. **#4515** (merged, squash `505bfbf47c`) — frontmatter canônico nas 4 ADRs **zero-frontmatter** (corpo byte-idêntico) **+** fix do `body_of()` do `governance-gate.yml`. A exceção 0297 estava quebrada para base sem frontmatter (contava `---` HR do corpo como fences). [W] aprovou tocar o gate.
2. **#4521** (merged, squash `a40d0a2624`) — 2 EVENTO-prosa (0097→0091, 0185→0179) fechados pondo o número no campo canônico `supersedes_partially` (não `supersedes` — é supersede PARCIAL, alvo fica ativo). Label `adr-metadata-normalization`; [W] autorizou `OIMPRESSO_MEMORY_OVERRIDE=1` para o hook local.

Ambos os merges foram guardados: só mergeei com todos os **required** verdes; único vermelho em ambos = `module-grades-gate` **advisory** (métrica PHP composta, meus diffs = 0 PHP).

## Artefatos gerados
- `governance-gate.yml` — `body_of()` +9 linhas (ramo zero-frontmatter) — **em main**.
- 4 ADRs (0126-vault, 0128, 0246-sessao, 0247) — frontmatter (+13 cada) — **em main**.
- 2 ADRs (0097, 0185) — `supersedes_partially` — **em main**.
- `_INDEX-GENERATED.md` — regenerado 2× — **em main**.
- Session log `memory/sessions/2026-07-18-adr-frontmatter-zero-e-prose-supersede.md` (este PR).

## Persistência
- **git:** ambos PRs em `main` (a40d0a2624 e antes); handoff/session neste PR de fechamento.
- **MCP:** webhook GitHub→MCP propaga ~2min após push.
- **BRIEFING:** N/A (governança de canon, não módulo de produto).

## Próximos passos pra retomar
- `git fetch origin main` já traz tudo. A ÚNICA pendência é o chip `task_12302ab3` (limpar `ADRS_LEGACY_SKIP`) — precisa Pest no CT100 (`tailscale ssh root@ct100-mcp "docker exec … vendor/bin/pest tests/Feature/Memory/AdrFrontmatterLinterTest.php"`) antes de abrir PR, senão reprova TODO PR (gate valida corpus inteiro).

## Lições catalogadas
1. **Exceção 0297 era frontmatter-fence-dependente** — quebrava para ADR sem YAML algum. Corrigida na fonte.
2. **`lifecycle: arquivado` ≠ `historical`** — `historical` está só no `adr.schema.json`, não no vocab do Pest `AdrFrontmatterLinterTest`. Usar o valor válido nos DOIS validadores.
3. **Check L + colisão de número** — `status: proposto` num membro de colisão acusa via a citação do número da irmã. `.py` fica FORA do corpus Check L; a citação que mordeu foi um comentário no próprio `.yml`. Resolver reescrevendo o comentário, não editando baseline.
4. **Colisões de número = grandfathered por design** — renumerar é hard-fail (`adr_renamed_count`) + quebra links. Não mexer.

## Pointers detalhados
- Session log desta sessão (narrativa completa + evidências): `memory/sessions/2026-07-18-adr-frontmatter-zero-e-prose-supersede.md`.
- Gate: `.github/workflows/governance-gate.yml` job `block-adr-edits` (exceções 0257 norm + 0297 legacymig).

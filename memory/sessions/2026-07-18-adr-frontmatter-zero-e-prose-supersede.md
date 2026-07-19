---
date: "2026-07-18"
hour: "23:37 BRT"
topic: "ADR family limpa: frontmatter nas 4 zero-frontmatter + fix body_of da 0297 + 2 EVENTO-prosa fechados"
authors: [W, C]
prs: [4515, 4521]
related_adrs:
  - 0297-excecao-append-only-migracao-legacy-frontmatter-adr
  - 0343-promove-adr-gate-required-emenda-0341
  - 0257-adr-status-lifecycle-kind-modelo-canonico
---

## TL;DR

Fechei os "problemas dos ADRs" que sobravam depois da [ADR 0343](../decisions/0343-promove-adr-gate-required-emenda-0341.md) ter tornado o gate `ADR (memory/decisions/*.md)` **required**. Estado final medido em `main`: **0 sem-frontmatter** (era 4), **0 AJV-inválido** (349), **0 EVENTO-prosa** (era 2), **0 alerta de supersede-integrity**. 2 PRs mergeados (#4515, #4521). As 14 colisões de número ficam grandfathered **por design** (renumerar = append-only hard-fail + quebra links).

## Contexto

A tarefa nasceu de: "o gate ADR virou required, mas 4 ADRs não têm frontmatter algum (0126-vault, 0128, 0246-sessao, 0247) — o gate diff-aware não as reavalia (append-only), então a família `adr` não está 0 de verdade, está 4". Depois o [W] pediu para resolver TAMBÉM os 2 avisos EVENTO-prosa restantes do índice.

## O que foi feito

### PR #4515 — frontmatter nas 4 zero-frontmatter + fix `body_of` (label `adr-legacy-schema-migration`)
- Frontmatter canônico prepend (corpo **byte-idêntico**, sem linha em branco) em 0126-vault, 0128 (proposto/reference), 0246-sessao, 0247 (aceito/canonical) — todas `lifecycle: arquivado`.
- **O bloqueio real (não previsto na tarefa):** a exceção [ADR 0297](../decisions/0297-excecao-append-only-migracao-legacy-frontmatter-adr.md) valida corpo byte-idêntico com um `body_of()` (awk) que **assumia** que a base já tinha fences `---`. Para base **zero-frontmatter** (1ª linha = `# ADR …`) ela truncava (os `---` HR do corpo viravam fences) ou devolvia vazio → o required `Append-only canon` **reprovava as 4** (provado empiricamente). Por isso essas 4 nunca tinham sido migradas (#4456/#4467 só pegaram legacy COM frontmatter).
- **Fix mínimo** em `governance-gate.yml`: 1ª linha ≠ `---` → corpo = arquivo inteiro. Zero regressão para ADR com frontmatter (old==new provado). [W] aprovou tocar o gate antes de eu fazer.
- Índice regenerado. Validado local: AJV 0/4, `adr-index --check` verde, memory-health 0🔴.

### PR #4521 — 2 EVENTO-prosa fechados via `supersedes_partially` (label `adr-metadata-normalization`)
- 0097 ("supersede parcial ADR 0091") e 0185 ("amends ADR 0179") declaravam supersede PARCIAL só no título → `supersedes_partially: [alvo]` no campo estruturado.
- **Insight:** usar `supersedes_partially` ([ADR 0317](../decisions/0317-emenda-parcial-supersedes-partially.md)) e NÃO `supersedes` cheio — os alvos ficam `lifecycle: ativo`, e o gerador só cascade-checa `supersedes` (full), então `supersedes_partially` limpa o warn **sem** disparar supWarn. 0185 já tinha um campo **`amends:`** não-canônico que o gerador **ignora** — por isso o aviso persistia.
- Edição sob exceção [ADR 0257](../decisions/0257-adr-status-lifecycle-kind-modelo-canonico.md) (corpo intacto). O hook local `block-memory-drift.mjs` bloqueia edit inline de ADR aceita — [W] autorizou o `OIMPRESSO_MEMORY_OVERRIDE=1` explicitamente antes.

## Achados / lições

1. **A 0297 estava silenciosamente quebrada para ADR sem frontmatter algum** — o `body_of` era frontmatter-fence-dependente. Corrigido na fonte; futuras migrações zero-frontmatter não batem mais na parede.
2. **Check L (memory-health) e colisão de número:** dar `status: proposto` ao 0126-vault fez o Check L acusá-lo, porque o número "0126" é citado no corpus (via a colisão-irmã `0126-mcp-jira`). A citação real que mordeu era o **meu próprio comentário** no `governance-gate.yml` (`.py` é excluído do corpus). Reescrevi o comentário sem citar o número → 0🔴. (Não editei baseline → `baseline-tamper-guard` intacto.)
3. **`lifecycle: arquivado` e não `historical`:** `historical` está no `adr.schema.json` mas **não** no `LIFECYCLE_VALIDOS` do Pest `AdrFrontmatterLinterTest` — seria armadilha latente se as 4 saíssem da skip-list. `arquivado` é válido nos dois.
4. **Colisões de número = grandfathered por design** ([ADR 0316](../decisions/0316-esquecimento-real-adr-morta-tombstone-git-auditoria.md) é sobre tombstone, não renumeração): renumerar renomeia arquivo → `adr_renamed_count` hard-fail + quebra links. Não são defeito; o `--check` conta `0 nova`.

## Fora de escopo (deliberado)
- 14 colisões: não resolvidas (renumeração proibida).
- Chip `task_12302ab3` (remover as 4 de `ADRS_LEGACY_SKIP` no Pest): sessão paralela terminou **sem** PR/branch; precisa Pest no CT100. Segue pendente.

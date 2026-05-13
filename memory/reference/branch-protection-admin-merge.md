---
name: Branch protection main + admin merge legítimo
description: Branch protection oimpresso requer check "ADR frontmatter" que só roda quando PR toca memory/decisions/**; pra PRs que não tocam ADRs, admin merge via gh --admin é legítimo
type: reference
---
Branch protection da `main` no oimpresso (verificado 2026-05-10 via `gh api repos/wagnerra23/oimpresso.com/branches/main/protection`):

- `required_status_checks`: `["ADR frontmatter"]` (strict=true)
- `required_approving_review_count`: 1
- `enforce_admins`: false (owner Wagner pode bypassar)
- `required_linear_history`: true

**Pegadinha**: workflow `ADR frontmatter` (`.github/workflows/adr-lint.yml`) só roda em pushes/PRs que tocam `memory/decisions/**`, `tests/Feature/Memory/AdrFrontmatterLinterTest.php` ou o próprio workflow. Pra TODOS os outros PRs (fix/feat normais), o check fica `none` → `mergeable_state=blocked` permanente.

**Solução legítima** quando PR não toca ADRs e Wagner autorizou merge: `gh pr merge <N> --squash --admin`. Funciona porque `enforce_admins: false`.

**Solução por REST API** quando `gh pr merge` falha:
```bash
gh api repos/wagnerra23/oimpresso.com/pulls/<N>/merge -X PUT \
  -f merge_method='squash' \
  -f commit_title='<title>'
```

**Quando NÃO usar admin merge**:
- Wagner não autorizou merge desta sessão
- PR toca `memory/decisions/**` (deixa o check rodar)
- Há review request pendente legítimo (Felipe/outros code owners)

**Outras checks que aparecem no CI mas NÃO são required**:
- `PHP / Pest (Unit)` (workflow `ci.yml`) — bom indicador, mas falha em main historicamente por bugs não-relacionados
- `Frontend / Vite build` — confiável
- `Modules Pest` (Arquivos/NfeBrasil/Repair/Vestuario/ComunicacaoVisual) — historicamente RED em main por gap SQLite (ver tests-pest-canon.md)

**Rate limit**: GraphQL 5000/h compartilhado. REST API tem limite separado — quando GraphQL bate "API rate limit already exceeded", `gh api repos/.../pulls -f title=...` (REST) ainda funciona.

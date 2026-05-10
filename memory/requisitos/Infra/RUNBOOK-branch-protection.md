# RUNBOOK — Branch protection com required checks (US-INFRA-011)

> **Status:** receita Wagner-only (aprovação UI/API)
> **Branch alvo:** `main` (e `6.7-react` legacy)
> **Decisão:** [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) governance · [ADR 0095](../../decisions/0095-skills-tiers-convencao-interna.md) lifecycle

## O quê

Configurar GitHub Branch Protection em `main` exigindo:

1. **Status checks obrigatórios** antes de merge:
   - `ADR frontmatter` (workflow `.github/workflows/adr-lint.yml`)
   - `Quick Sync` (deploy validator) — se houver
   - `mwart-gate` (workflow `.github/workflows/mwart-gate.yml`) — opcional Tier 2

2. **Required reviewers** ≥ 1 (Wagner aprova PRs do time)

3. **Linear history** habilitado (squash-only)

4. **No force push** em `main`

## Por que

US-INFRA-011 (CYCLE-03 p0): hoje qualquer PR pode mergear sem ADR-lint passar — alguém edita `memory/decisions/*.md` com frontmatter quebrado, mergeia, MCP server falha em sync próximo. Detectar em CI **antes** do merge resolve.

## Como aplicar

### Opção A — GitHub UI (recomendado pra primeira vez)

1. Settings → Branches → Add branch protection rule
2. Branch name pattern: `main`
3. Marcar:
   - ☑ **Require a pull request before merging**
   - ☑ **Require approvals** → 1
   - ☑ **Require status checks to pass before merging**
   - Search box: digitar `ADR frontmatter` → selecionar
   - Adicionar outros: `Quick Sync` (se existir), `mwart-gate`
   - ☑ **Require branches to be up to date before merging**
   - ☑ **Require linear history**
   - ☑ **Do not allow bypassing the above settings**
4. Save

Repetir pro branch `6.7-react`.

### Opção B — gh API (1 comando, audit-loggable)

```bash
# Em D:\oimpresso.com
gh api -X PUT repos/wagnerra23/oimpresso.com/branches/main/protection \
  --input - <<'EOF'
{
  "required_status_checks": {
    "strict": true,
    "contexts": ["ADR frontmatter"]
  },
  "enforce_admins": false,
  "required_pull_request_reviews": {
    "required_approving_review_count": 1,
    "dismiss_stale_reviews": true
  },
  "restrictions": null,
  "required_linear_history": true,
  "allow_force_pushes": false,
  "allow_deletions": false
}
EOF
```

> ⚠️ `enforce_admins: false` permite Wagner mergear emergência. Para **enforce total**, mudar pra `true` (Wagner também precisa aprovar próprio PR).

### Verificar pós-aplicação

```bash
gh api repos/wagnerra23/oimpresso.com/branches/main/protection --jq .required_status_checks
# Esperado: {"strict":true,"contexts":["ADR frontmatter"]}
```

## Risco / rollback

- **Risco:** PRs em andamento ficam bloqueadas até ADR-lint workflow rodar com sucesso
- **Mitigação:** rodar Opção B fora de hora-pico; se quebrar, mesmo comando com `"contexts": []` desativa required checks sem remover proteção

## Critério de aceitação US-INFRA-011

- [ ] `gh api .../protection --jq .required_status_checks.contexts` inclui `"ADR frontmatter"`
- [ ] PR de teste com frontmatter ADR quebrado → merge bloqueado até fix
- [ ] PR de teste com frontmatter ADR válido → workflow verde + merge habilitado

## Quem faz

**Wagner** (decisão + aplicação). Claude pode ajudar com Opção B se Wagner autorizar `gh api PUT` direto.

## Referências

- Workflow: `.github/workflows/adr-lint.yml`
- Validador: `tests/Feature/Memory/AdrFrontmatterLinterTest.php`
- Schema: `memory/decisions/_schema.json`

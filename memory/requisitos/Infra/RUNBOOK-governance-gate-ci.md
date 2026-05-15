---
slug: runbook-governance-gate-ci
title: "RUNBOOK — Governance Gate CI (pre-merge)"
type: runbook
authority: canonical
lifecycle: ativo
owner: wagner
last_updated: 2026-05-15
related_workflow: .github/workflows/governance-gate.yml
related_script: .github/scripts/pii-scan.sh
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0095-skills-tiers-convencao-interna
  - 0130-handoff-append-only-mcp-first
charter_adr: 0094
pii: false
---

# RUNBOOK — Governance Gate CI

Workflow `.github/workflows/governance-gate.yml` é o **Mecanismo #2 ENFORCEMENT** prescrito pela Constituição v1.1.0 Art. 8 (Policy Gating) e detalhado em [ENFORCEMENT.md §2 #2](../../governance/ENFORCEMENT.md). Bloqueia merge de PR que toque camadas críticas sem artefatos obrigatórios.

Time MCP entra em breve. Sem CI gate, drift escapa quando hook local foi pulado/ignorado.

## §1. Jobs

### Job 1 — `block-adr-edits` (HARD — bloqueia merge)

| Sub-regra | O que verifica | Falha se |
|---|---|---|
| ADR canon append-only | `git diff --name-status` em `memory/decisions/NNNN-*.md` | Status `M` ou `R*` em ADR existente |
| Handoff append-only | Idem em `memory/handoffs/*.md` ([ADR 0130](../../decisions/0130-handoff-append-only-mcp-first.md)) | Status `M` em handoff existente |
| CONSTITUTION cascade | `memory/governance/CONSTITUTION.md` editada | Falta label `constitution-amendment` OU falta `audit-*.md` novo no mesmo PR |

Mensagens em PT-BR com instruções de resolução (ver shell script no workflow).

### Job 2 — `scope-md-drift` (WARN — só comment, não falha)

Detecta Controllers novos (`A` em `Modules/<X>/Http/Controllers/*Controller.php`) e verifica se aparecem em `Modules/<X>/SCOPE.md.contains[]`. Posta comment na PR com lista de drifts e como resolver.

Complementar ao `scope-guard.yml` (que já roda strict pro mesmo cenário) — este job entrega mensagem humana detalhada quando o strict falha.

### Job 3 — `pii-scan` (HARD — bloqueia merge)

Roda `.github/scripts/pii-scan.sh` nos arquivos AM (Added/Modified) do PR.

- **CPF regex:** `[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}` (padrão XXX.XXX.XXX-XX)
- **CNPJ regex:** `[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}`
- **Exclui:** `vendor/`, `node_modules/`, `public/`, `storage/`, `bootstrap/cache/`, `*.lock`, binários
- **Auto-redact no log** — número detectado vira `[REDACTED-CPF]`/`[REDACTED-CNPJ]` antes de imprimir no log público do GH Action (evita re-vazamento)

## §2. Como fazer PR válida (workflow padrão)

```
1. nova branch: git checkout -b claude/<slug>
2. mexer (Edit/Write)
3. git add <arquivos específicos>           # NUNCA git add -A
4. git commit -m "feat(...): ... [W+C]"     # Refs: ADR-NNNN se aplicável
5. git push -u origin claude/<slug>
6. gh pr create --title ... --body ...
7. CI verde (todos jobs governance-gate ok) + review Wagner
8. gh pr merge --squash
```

## §3. Como editar Constituição (Art. 10 §10.4 Cascade Review)

```
1. Criar ADR formal: memory/decisions/NNNN-constitution-amendment-vX.Y.md
2. Editar CONSTITUTION.md:
   - bump version no frontmatter
   - adicionar entry em amendments[]
   - editar conteúdo do artigo
3. Criar audit cascade: memory/governance/audit-YYYY-MM-DD-vX.Y.md
   (documenta revisão das camadas L2-L7 impactadas)
4. PR contém OS 3 arquivos (ADR + CONSTITUTION + audit)
5. Aplicar label 'constitution-amendment' na PR via GH UI
6. CI governance-gate Job 1 verifica:
   ✅ label presente
   ✅ audit-*.md novo no mesmo PR
7. Wagner aprova + merge
```

**Exemplo histórico:** v1.0.0 → v1.1.0 em 2026-05-05 — ver [`audit-2026-05-05-v1.1.md`](../../governance/audit-2026-05-05-v1.1.md).

## §4. Override pii-scan (falso-positivo)

Cenário: Pest factory de teste, fixture, doc explicando formato CPF/CNPJ.

**Solução:** adicionar comment `# pii-allowlist` na MESMA linha:

```php
$payload = ['cpf' => '000.000.000-00']; // pii-allowlist (Pest factory placeholder)
```

```ts
const cpfMock = '111.222.333-44'; // pii-allowlist (Storybook fixture)
```

Linhas com `pii-allowlist` são ignoradas pelo scanner. Use SOMENTE pra PII fictícia/placeholder — NUNCA pra PII real "transitória".

## §5. Como reverter ADR/handoff editado por engano

Se PR for bloqueada pelo Job 1 por ter editado ADR/handoff existente:

```bash
# Reverter o arquivo pra versão de origin/main
git checkout origin/main -- memory/decisions/NNNN-arquivo-em-questao.md

# OU criar ADR nova superseding (caso a edição era válida):
cp memory/decisions/NNNN-antiga.md memory/decisions/MMMM-nova-slug.md
# editar MMMM com frontmatter 'supersedes: [NNNN]' + lifecycle: ativo
# editar NNNN apenas pra mudar lifecycle: superseded (PATCH permitido,
#   ainda assim via PR SEPARADA pra rastreabilidade)

git add memory/decisions/MMMM-nova-slug.md
git commit -m "feat(adr): supersede NNNN com nova MMMM [W+C]"
```

## §6. Troubleshooting

### "constitution-amendment label faltando" mas adicionei

GH Actions usa snapshot do PR no momento do trigger. Após adicionar label:

```bash
# Force re-run do workflow
gh workflow run governance-gate.yml --ref claude/<slug>
# OU empurra commit vazio
git commit --allow-empty -m "ci: re-trigger governance-gate"
git push
```

### "Job pii-scan timeout 5min"

Improvável (regex simples), mas se acontecer:
- Provável regex regression em alguma extensão binária não-filtrada
- Adicionar extensão ao `SKIP_EXT_REGEX` em `.github/scripts/pii-scan.sh`
- Abrir PR de fix do script primeiro, depois retomar PR principal

### "Job scope-md-drift falhou em parse YAML"

Frontmatter de algum SCOPE.md está mal-formado. Validar:

```bash
python3 -c "import yaml; yaml.safe_load(open('Modules/<X>/SCOPE.md').read().split('---')[1])"
```

## §7. Testando localmente antes de PR

### Opção A — `act` (GitHub Actions runner local)

```bash
# Instalar: https://github.com/nektos/act
act pull_request -W .github/workflows/governance-gate.yml \
  --container-architecture linux/amd64 \
  -e .github/test-events/pr.json
```

Limitações: `gh api` chamadas pra labels precisam mock; `act` não cobre permissions PR write 100%.

### Opção B — Smoke script-only

```bash
# Testar pii-scan.sh local em arquivos específicos
bash .github/scripts/pii-scan.sh -v path/to/file.php

# Testar com PII literal (deve falhar exit 1)
echo "\$cpf = '123.456.789-00';" > /tmp/pii-test.php
bash .github/scripts/pii-scan.sh -v /tmp/pii-test.php
# Esperado: exit 1 + "PII detectada (1 ocorrência)"

# Testar allowlist (deve passar exit 0)
echo "\$cpf = '123.456.789-00'; // pii-allowlist" > /tmp/pii-test.php
bash .github/scripts/pii-scan.sh -v /tmp/pii-test.php
# Esperado: exit 0
```

### Opção C — Push pra branch experimental

PR draft contra `main` aciona o gate sem precisar merge — vê resultado direto.

## §8. Sugestão evolução

| Mecanismo | Status | Próximo passo |
|---|---|---|
| #2 Pre-merge gate (este) | ✅ implementado | calibrar 4 semanas; converter Job 2 pra strict se sinal estável |
| #6 Mutation testing | ⏸️ Fase 5 | Pest tests gerados de `mcp_governance_rules` |
| #8 Public audit dashboard | ⏸️ Fase 5 | UI `/governance/audit` ([ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md)) |

## §9. Edge cases conhecidos

| Cenário | Comportamento esperado | Justificativa |
|---|---|---|
| `git mv memory/decisions/0010-old.md memory/decisions/0010-new.md` | Job 1 falha (status `R*`) | Rename de ADR = mudança de slug = potencial edit. Forçar nova ADR superseding. |
| ADR NNNN duplicado (2 arquivos com mesmo NNNN, status A em ambos) | Job 1 passa (não detecta) | TODO futuro: validar unicidade numérica via `adr-lint.yml` (já existe — schema valida frontmatter, não unicidade slug NNNN cross-files). |
| CONSTITUTION ratificada num PR + audit no PR seguinte | Job 1 falha | Cascade DEVE estar no mesmo PR (§10.4). Se split intencional, label fica em ambos PRs e Wagner override manual via `gh pr merge --admin`. |
| pii-scan pega CPF/CNPJ em `memory/sessions/*.md` | Falha | Session logs DEVEM redactar PII (skill `commit-discipline`). Use placeholder `[REDACTED-CPF]` ou faker. |

## §10. Quando hook local diverge do CI

Hook local pode ser pulado (`git commit --no-verify`) ou estar desatualizado. CI é a **fonte de verdade**. Wagner regra 2026-05-15:

> "vao entrar os outros no MCP e isso vai ficar uma zona caralho"

Time inteiro pode forçar `--no-verify` localmente; CI bloqueia no merge. Branch protection em `main` (configurada via UI GitHub) marca este workflow como **required check** — sem isso, drift escapa.

Ver [RUNBOOK-branch-protection.md](RUNBOOK-branch-protection.md) pra setup branch protection.

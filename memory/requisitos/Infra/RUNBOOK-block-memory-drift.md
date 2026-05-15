# RUNBOOK — block-memory-drift hook

> Hook PreToolUse que protege `memory/<canon>/*.md` contra edits sem workflow PR.
> Fecha vetor de drift PR-less catalogado na maratona WhatsApp 14-15/mai/2026 (5 instâncias).
> Camada 1 (Claude Code runtime). Camada 2 (CI guard) sugerida em "Evolução".

## O que faz

Intercepta `Write`/`Edit`/`MultiEdit` em paths canon protegidos e BLOCK conforme regras:

| Regra | Caso | Decisão |
|---|---|---|
| A | Branch `main`/`master` + edit em qualquer canon | BLOCK |
| B | Edit em ADR existente (`memory/decisions/NNNN-*.md` com NNNN já usado) | BLOCK (sempre) |
| C | Edit em handoff existente (`memory/handoffs/YYYY-MM-DD-*.md`) | BLOCK (sempre) |
| D | Write criando ADR nova (NNNN único) em branch `claude/*` | ALLOW |
| E | Write criando handoff novo (data-slug inédito) | ALLOW |
| F | Edit em outros canon em branch `!=claude/*` | BLOCK |
| G | Edit em `memory/governance/CONSTITUTION.md` em qualquer branch | BLOCK |

### Canon paths protegidos

```
memory/decisions/NNNN-*.md           — ADRs Nygard (append-only IRREVOGÁVEL)
memory/handoffs/YYYY-MM-DD-*.md      — Handoffs (append-only, ADR 0130)
memory/governance/CONSTITUTION.md    — Documento supremo Constituição v2 (ADR 0094)
memory/governance/TRUST-TIERS.md     — Canon governança
memory/governance/ENFORCEMENT.md     — Canon governança
memory/governance/ARCHITECTURE.md    — Canon governança
memory/governance/IDENTITY-MESH.md   — Canon governança
memory/governance/srs/*.md           — Append-only (futuro)
memory/proibicoes.md                 — Tier 0
memory/regras-time.md                — Canon time
memory/what-oimpresso.md             — Canon estrutura
memory/why-oimpresso.md              — Canon visão
memory/how-trabalhar.md              — Canon protocolo
```

### Paths NÃO bloqueados (out of scope)

```
memory/decisions/proposals/**        — ADRs em rascunho, editáveis até promoção
memory/sessions/**                   — Append-only por convenção, sem hook ainda
memory/reference/**                  — Em migração (ADR 0061), editáveis
memory/requisitos/**                 — SPECs/RUNBOOKs vivos por módulo
```

## Quando ativa

`SessionStart` carrega hook via `.claude/settings.json`. Toda chamada `Write`/`Edit`/`MultiEdit` passa pelo PreToolUse stack:

1. `block-automem.ps1` (ADR 0061/0131 — auto-mem legada)
2. **`block-memory-drift.ps1`** (este — canon git)
3. `block-mwart-violation.ps1` (ADR 0104 — Pages/<Mod>/<Tela>.tsx sem RUNBOOK)
4. `charter-validate.ps1`
5. `modulo-preflight-warning.ps1`

Stack ordem importa: bloqueio mais barato primeiro (path-match), depois git rev-parse, depois validação de arquivo do canon.

## Workflow correto pra editar canon

### Editar ADR existente (ex: typo em 0094)

**NÃO PODE editar inline.** ADRs aceitas são append-only IRREVOGÁVEIS (Constituição v2 Art. 3).

```bash
# 1. Branch claude/*
git checkout -b claude/emendation-0094-typo

# 2. Criar ADR nova com supersedes
# memory/decisions/NNNN-emendation-0094-typo.md
# Frontmatter inclui:
#   supersedes: [0094]
#   lifecycle: emendation

# 3. PR + Wagner aprova + merge
git add memory/decisions/NNNN-*.md
git commit -m "docs(adr): emendation 0094 (typo)"
git push -u origin claude/emendation-0094-typo
gh pr create --title "docs(adr): emendation 0094 typo" --body "..."
```

### Editar canon não-ADR (ex: `memory/proibicoes.md`)

Tem branch `claude/*` em curso? Pode editar direto (vai pra PR).

```bash
git checkout -b claude/add-proibicao-XYZ
# Edit memory/proibicoes.md
git add memory/proibicoes.md
git commit -m "docs(proibicoes): adiciona regra XYZ"
git push + gh pr create
```

Estava em `main` por acidente? Hook BLOCK. Faça:

```bash
git stash
git checkout -b claude/<slug>
git stash pop
# agora Edit funciona
```

### Editar `CONSTITUTION.md`

**Bloqueado em qualquer branch.** Só via ADR Nygard nova + version bump atômico:

```bash
git checkout -b claude/constitution-v1-2-0
# 1. Cria memory/decisions/NNNN-emendation-constitution-v1-2-0.md (justifica mudança)
# 2. NO MESMO PR atualiza memory/governance/CONSTITUTION.md (bump versão)
# Override hook obrigatório enquanto edita CONSTITUTION:
$env:OIMPRESSO_MEMORY_OVERRIDE='1'
# Edita
$env:OIMPRESSO_MEMORY_OVERRIDE=$null
```

### Criar handoff novo

```bash
# Sempre permitido (arquivo novo)
# memory/handoffs/YYYY-MM-DD-HHMM-<slug-kebab>.md
# Snapshot "Estado MCP no momento do fechamento" obrigatório (ADR 0130)
# Atualizar memory/08-handoff.md adicionando 1 linha no topo (truncar 5º)
```

### Editar handoff existente

**Bloqueado.** Append-only IRREVOGÁVEL (ADR 0130). Crie um handoff novo registrando a correção.

## Override emergencial (Wagner Tier 0 only)

```powershell
$env:OIMPRESSO_MEMORY_OVERRIDE='1'
# Edit funciona — warning loud no stderr
$env:OIMPRESSO_MEMORY_OVERRIDE=$null
```

**OBRIGAÇÃO:** PR follow-up imediato registrando o que foi alterado fora do workflow. Wagner é o único autorizado.

Bash POSIX:
```bash
export OIMPRESSO_MEMORY_OVERRIDE=1
# Edit
unset OIMPRESSO_MEMORY_OVERRIDE
```

## Troubleshooting

### Hook bloqueou edit legítimo?

Checklist:

1. **Branch ativa**: `git rev-parse --abbrev-ref HEAD` — é `claude/*` ?
2. **Path correto**: confere o `relPath` na mensagem do hook
3. **Tipo de canon**: ADR? Handoff? Constitution? Outro canon?
4. **Existe vs novo**: arquivo já existe? (regras B+C bloqueiam edit em existente)

### Falsos positivos conhecidos

- Edit em `memory/decisions/0101-*.md` (existem 2 ADRs 0101 por bug histórico) — hook bloqueia ambos, correto (append-only)
- Edit em handoff de hoje porque "esqueci uma linha" — crie outro handoff seguinte com nota "corrige <slug>"
- Edit em CONSTITUTION pra atualizar timestamp — bloqueado de propósito; abre ADR de emendation

### Rodar smoke test

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .claude/hooks/block-memory-drift.test.ps1
```

Saída esperada: `[PASS] 11/11 casos validados`.

## Evolução sugerida

1. **CI guard de merge** (`.github/workflows/canon-append-only.yml`) — bloqueia PR que tocar ADR existente sem `supersedes` ou que mexer em handoff antigo. Camada 2 defense-in-depth (hoje só Claude Code roda hook; Felipe/Maiara/Luiz/Eliana podem mexer via `git` direto sem passar pelo Claude).
2. **Pre-commit hook git** (`.husky/pre-commit` ou `.git/hooks/pre-commit`) — espelha block-memory-drift no nível git, pega qualquer commit local antes do push.
3. **Estender pra `memory/sessions/`** — append-only por convenção mas hoje sem hook. Decidir se justifica (sessões são logs de trabalho, drift menos crítico que ADR).
4. **Estender pra `memory/requisitos/<Mod>/SPEC.md`** — Wagner alegou (2026-05-15) "BRIEFING obrigatório por PR" — SPEC pode ser tão crítico quanto.
5. **Métrica `memory_drift_attempts_blocked_total`** — emitir log estruturado pra MCP server cubar quantas tentativas o hook bloqueou (proxy de saúde do workflow).

## Referências

- [ADR 0061](../../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md) — Conhecimento canônico git+MCP, zero auto-mem
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (Art. 3 imutabilidade)
- [ADR 0130](../../decisions/0130-handoff-append-only-mcp-first.md) — Handoff append-only
- [ADR 0131](../../decisions/0131-tiering-memoria-canonico-local-segredo.md) — Tiering de memória (canônico/local/segredo)
- [Skill `mcp-first`](../../../.claude/skills/mcp-first/SKILL.md) — workflow canônico

## Histórico

- **2026-05-15 — Criado** — Audit Implement Expert Wave R/Memory Drift. Gap fechado: time MCP entra (Felipe/Maiara/Luiz/Eliana), risco de canon editado direto em main sem PR vira mentira servida pelo MCP server. 11/11 smoke tests passam.

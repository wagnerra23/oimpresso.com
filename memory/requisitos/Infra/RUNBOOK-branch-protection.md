---
title: "Branch protection main — required checks + janela de force-push segura"
owner: "W"
status: "ativo"
last_validated: "2026-07-02"
preconditions:
  - "gh autenticado com admin no repo (Wagner)"
  - "governance/required-checks-baseline.json atualizado (fonte única dos 23+ contexts)"
steps:
  - "Aplicar/alterar protection: §Como aplicar (payload SEMPRE via --input arquivo UTF-8 sem BOM)"
  - "Janela de force-push (history rewrite): §Janela de force-push + restauração segura"
  - "Validar pós-PUT: node scripts/governance/protection-drift.mjs (byte-compare vs baseline)"
---

# RUNBOOK — Branch protection com required checks (US-INFRA-011)

> **Status:** receita Wagner-only (aprovação UI/API)
> **Branch alvo:** `main` (e `6.7-react` legacy)
> **Decisão:** [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) governance · [ADR 0095](../../decisions/0095-skills-tiers-convencao-interna.md) lifecycle
>
> ⚠️ **ATUALIZAÇÃO 2026-07-02:** os exemplos históricos abaixo (era US-INFRA-011) mostram `contexts: ["ADR frontmatter"]` — **NÃO copie esse payload hoje.** A lista canônica vive em [`governance/required-checks-baseline.json`](../../../governance/required-checks-baseline.json) (23 classic + rulesets, ADR 0275 §5). Re-postar a protection com lista menor = demoção em massa (o sentinela `protection-drift` acusa 🔴). E **NUNCA** monte o payload inline no shell Windows — ver §Janela de force-push (incidente mojibake 2026-07-02).

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

## Janela de force-push + restauração segura (history rewrite)

> Fluxo usado nos rewrites de `main` (BRL 2026-06-08 · CNPJ 2026-07-02). **Origem desta seção:** incidente 2026-07-02 ~11:22–11:59 UTC — na restauração, os 23 contexts foram re-postados com payload inline pelo shell Windows → double-encoding UTF-8 (`PHPStan / Larastan Â· ratchet vs baseline`, `ConstituiÃ§Ã£o`) → os 10 contexts não-ASCII nunca eram satisfeitos → **todo merge em main deadlockado** (`mergeStateStatus BLOCKED` com 54/54 checks verdes). Regra Tier 0 em [`memory/proibicoes.md` §Ambiente](../../proibicoes.md).

Pré-requisitos: aprovação explícita Wagner (R10) + PRs abertos catalogados (vão precisar de rebase pós-rewrite).

1. **Snapshot do estado vivo ANTES de abrir a janela** (prova + material de restauração):
   ```bash
   gh api repos/wagnerra23/oimpresso.com/branches/main/protection > protection-snapshot.json
   node scripts/governance/protection-drift.mjs   # deve estar 🟢 antes de mexer
   ```
2. **Abrir a janela** — remover a protection (DELETE) OU só habilitar force-push, conforme o caso:
   ```bash
   gh api -X DELETE repos/wagnerra23/oimpresso.com/branches/main/protection
   ```
3. **Force-push** sempre `--force-with-lease` amarrado no SHA esperado (se main avançar durante a operação — sessão paralela — a lease rejeita; refazer do clone fresco, NUNCA `--force` cru).
4. **Restaurar a protection — payload SEMPRE via arquivo, NUNCA inline:**
   ```bash
   # Gerar o body a partir do baseline canônico (UTF-8 SEM BOM — io.open já grava assim):
   python -c "
   import json, io
   b = json.load(io.open('governance/required-checks-baseline.json', encoding='utf-8'))
   body = {'strict': True, 'contexts': b['classic_protection']['contexts']}
   io.open('/tmp/contexts.json','w',encoding='utf-8').write(json.dumps(body, ensure_ascii=False))
   "
   gh api -X PUT repos/wagnerra23/oimpresso.com/branches/main/protection/required_status_checks --input /tmp/contexts.json
   # (ou o PUT completo de .../protection com o protection-snapshot.json do passo 1 como base — também via --input)
   ```
   ⛔ **PROIBIDO:** `-f contexts[]=...`, heredoc no PowerShell/cmd, ou qualquer JSON montado inline pelo shell Windows — PS 5.1/cmd re-encodam não-ASCII e o GitHub grava mojibake. Vale pra QUALQUER endpoint que receba nome de check (`·` U+00B7 está em 8 dos 23 contexts).
5. **Validar por BYTES, não por contagem** — GET mostrando "23 contexts" NÃO prova nada (mojibake preserva a contagem):
   ```bash
   node scripts/governance/protection-drift.mjs   # 🟢 obrigatório; 🔴 MOJIBAKE aponta o par torto→esperado + reparo
   ```
   Conferir também `enforce_admins`, `required_linear_history` e `allow_force_pushes: false` de volta (diff do GET vs snapshot do passo 1).
6. **Registrar** (Regra Primária "mexeu, registra"): session log com janela aberta/fechada + SHAs velho→novo + PRs que precisam rebase.

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

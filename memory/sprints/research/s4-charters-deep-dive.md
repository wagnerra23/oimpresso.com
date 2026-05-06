---
name: S4 Deep Dive — Page Charters (L6) + tool charter-fetch
description: Pesquisa estado-da-arte 2026 pra Sprint 4. "Page charter" do Wagner alinha com "prompt contract" emergente. Pre-commit hooks são consenso pra enforcement. Configurancy.
type: project
created: 2026-05-06
related_sprint: S4
sources_count: 3
---

# S4 — Page Charters L6 (deep-dive)

> **Objetivo da pesquisa:** validar nosso conceito "page charter" cunhado pelo Wagner
> contra conceitos análogos no mercado 2026. Encontrar mecanismos de enforcement.

---

## Achado #1 — "Prompt Contracts" é o conceito espelho do mercado

[Just Understanding Data — "Prompt Contracts: Formal Specifications That Eliminate Vibe Coding"](https://understandingdata.com/posts/prompt-contracts-specification-before-code/) define:

> "A prompt contract is a structured **8-section specification** defining the objective, pre-conditions, invariants, file scope, implementation contract, test specifications, acceptance criteria, and anti-patterns, with the contract turning vague instructions into binding constraints agents can verify."

**8 seções canônicas:**

| # | Seção | O que vai aqui |
|---|---|---|
| 1 | Objective | O que esta unidade faz e por quê |
| 2 | Pre-conditions | O que precisa estar verdade antes de mexer |
| 3 | **Invariants** | Coisas que NUNCA podem mudar (ex: business_id scoped) |
| 4 | **File scope** | Quais arquivos podem/não podem ser tocados |
| 5 | Implementation contract | Tipo de retorno, props, eventos, rotas |
| 6 | Test specifications | Quais testes garantem o contrato |
| 7 | Acceptance criteria | Como saber que está pronto |
| 8 | Anti-patterns | O que NÃO fazer (proibições) |

### Comparação com nosso plano

Plano S4 original tinha 6 seções: INTENÇÃO, CONTRATO, INVARIANTES, OWNERS, ADRs, HISTÓRICO.

🟢 **Compatível.** Mas mercado usa 8 seções com ênfase em **enforcement mecanizável**:
- Adicionar **File scope** (já implícito mas não explícito) — crítico pra hook bloquear edição fora do escopo
- Adicionar **Test specifications** — vincular charter aos Pest tests que garantem
- Adicionar **Anti-patterns** — proibições explícitas (ex: "nunca usar `withoutGlobalScopes` aqui")

**Renomeação proposta** do template `charter.template.md`:

```yaml
---
charter_id: page.repair.listagem
kind: page
parent_id: feature.os
file_path: resources/js/Pages/Repair/Index.charter.md
title: Listagem de OS (Repair)
trust_level: 2
owners:
  design: maira
  code: felipe
adrs: [0091, 0093]
multi_tenant_scope: required  # Tier 0 obrigatório
charter_version: 1
last_verified: 2026-05-06
---

## 1. Objective
...

## 2. Pre-conditions
- Sprint 1 estável
- Inertia v3 + React 19 ativos
...

## 3. Invariants
- ⚠️ Toda query passa pelo global scope `business_id`
- p95 < 400ms
- 0 erros JS Sentry
...

## 4. File scope
ALLOW:
- resources/js/Pages/Repair/Index.tsx
- Modules/Repair/Http/Controllers/RepairController.php (apenas method index)
DENY:
- Modules/Repair/Models/Repair.php (não tocar Model nesta charter)
- Modules/Repair/Database/migrations/* (schema é S2)

## 5. Implementation contract
- Props recebidas do controller: { repairs: PaginatedRepair[], filters: ... }
- Eventos: onStatusChange, onBulkAction
...

## 6. Test specifications
- Modules/Repair/Tests/Feature/RepairListagemTest.php
  - test_repair_listagem_paridade_blade_vs_inertia()
  - test_multi_tenant_isolation()  # Tier 0
  - test_p95_under_400ms()

## 7. Acceptance criteria
- [ ] Filtros: status, cliente, período, location funcionando
- [ ] Paginação preserva URL bookmarkável
- [ ] Bulk actions ok
- [ ] Sentry zero erros 48h

## 8. Anti-patterns
- ❌ Não usar `withoutGlobalScopes` (Tier 0 violation)
- ❌ Não fazer reload total entre cliques (preserveScroll)
- ❌ Não chamar API externa sem cache (Repair listagem é hot path)
```

### Implicação no plano S4

🟡 **Atualizar template charter.template.md de 6→8 seções.** O custo é baixo (+2 seções) e o benefício de enforcement é grande (file scope + anti-patterns são mecanizáveis em pre-commit hook).

---

## Achado #2 — Pre-commit hook é o mecanismo canônico de enforcement

[Configurancy (ElectricSQL) — "keeping systems intelligible when agents write all the code"](https://electric-sql.com/blog/2026/02/02/configurancy):

> "A markdown file listing invariants is **worthless without enforcement**, making executable configurancy essential. Prompt contracts use **PreToolUse hooks that block edits to files not listed in the contract's scope**, with constraints enforced mechanically through hooks that agents cannot bypass."

[Web Developer Blog — Git hooks são best defense contra AI](https://jonesrussell.github.io/blog/git-hooks-ai-agents/):

> "With AI agents writing and committing code, **pre-commit hooks have become essential as the gate between an agent's output and your repository.** A pre-commit hook can catch violations before the commit exists, allowing agents to adjust and retry without requiring human review for mechanical violations."

[Dev Journal — Automate Pre-Commit Code Reviews with Claude Code Git Hooks](https://earezki.com/ai-news/2026-04-01-claude-code-git-hooks-automate-code-review-before-every-commit/):

> Claude Code suporta hooks `PreToolUse`, `PostToolUse`, `SessionStart`, `Stop`, `UserPromptSubmit`. Pra Charter enforcement, **`PreToolUse` é o ponto certo** — dispara antes de Edit/Write em arquivo `.tsx`.

### Hook canônico proposto pro S4

`.claude/hooks/charter-guard.ps1`:

```powershell
# Hook: PreToolUse Edit/Write
# Bloqueia edição de .tsx que tem .charter.md ao lado se charter-fetch não foi chamado nesta sessão.

param([string]$filePath)

if ($filePath -notmatch '\.tsx$|\.vue$|\.blade\.php$') { exit 0 }

$dir = Split-Path $filePath -Parent
$basename = [System.IO.Path]::GetFileNameWithoutExtension($filePath)
$charter = Join-Path $dir "$basename.charter.md"

if (-not (Test-Path $charter)) { exit 0 }  # sem charter = sem enforcement

# Verifica se charter-fetch foi chamado (via .claude/session-state.json)
$state = Get-Content "$env:USERPROFILE\.claude\session-state.json" -Raw | ConvertFrom-Json
$charterId = (Select-String -Path $charter -Pattern 'charter_id:\s*(.+)' | Select-Object -First 1).Matches.Groups[1].Value.Trim()

if ($state.fetched_charters -notcontains $charterId) {
    Write-Error "❌ CHARTER GUARD: você está editando $filePath que tem charter $charterId. Chame mcp__oimpresso__charter-fetch antes de continuar."
    exit 1
}

# Bonus: validar file scope (se charter tem ALLOW/DENY)
$scope = Select-String -Path $charter -Pattern '^DENY:' -Context 0,5
if ($scope) {
    foreach ($line in $scope.Context.PostContext) {
        if ($line -match $filePath) {
            Write-Error "❌ CHARTER SCOPE: $filePath está em DENY do charter $charterId."
            exit 1
        }
    }
}

exit 0
```

### Implicação no plano S4

🟢 **Skill `charter-first` deve ser implementada como Hook + Skill, não só Skill.** O guard mecânico (hook) impede burlar; a skill orienta o caminho.

---

## Achado #3 — Living documentation deve viver JUNTO do componente

[Presta — Design Systems 2026 (Airbnb/Uber)](https://wearepresta.com/design-systems-for-scale-2026/):

> "**Living Documentation is implemented as inseparable from components themselves**, representing a shift from traditional approach. In 2026, documentation is no longer a destination; it is a 'Function of the Codebase,' with traditional wikis and PDFs being where design systems go to die."

### Implicação no plano S4

Plano original previa charters em `**/*.charter.md` (qualquer caminho). Esta tendência sugere algo mais opinativo:

✅ **Convenção: charter sempre fica AO LADO do `.tsx` que ele governa.**

```
resources/js/Pages/Repair/
├── Index.tsx
├── Index.charter.md       ← convenção: mesmo nome + .charter.md
├── Edit.tsx
├── Edit.charter.md
└── Show.tsx
└── Show.charter.md
```

Para charters de feature/mission (que não têm 1 arquivo):

```
memory/charters/
├── feature.os.charter.md
├── feature.tarefas.charter.md
├── mission.constituicao-v2.charter.md
└── mission.governanca.ads-brain-routing.charter.md
```

Comando `php artisan charter:sync` varre **ambos os locais**.

---

## Achado #4 — `multi_tenant_scope: required` é frontmatter padrão emergente

Pesquisa não usa exatamente esse termo, mas o conceito de **frontmatter como contrato indexável** é forte ([Frontmatter as Document Schema](https://understandingdata.com/posts/frontmatter-as-document-schema/)):

> "Frontmatter, type signatures, and specifications are all instances of the same principle: **declare the contract before the implementation**. Frontmatter turns every file into a typed, queryable record."

### Implicação no plano S4 + §12 do ROTEIRO (multi-tenant)

✅ **Adicionar campo obrigatório `multi_tenant_scope`** no template charter.

Valores válidos:
- `required` — toda query desta página/feature passa por global scope `business_id`
- `superadmin_only` — página fora do tenant (admin do oimpresso)
- `na` — não toca dados de negócio (ex: página de login, terms of service)

Validação em `php artisan charter:sync`:
- Se charter tem `multi_tenant_scope: required` mas Pest test cross-tenant não existe → ERRO
- Se charter tem `multi_tenant_scope: superadmin_only` sem ADR justificando → WARNING

### Esquema proposto pro `mcp_page_charters`

Adicionar coluna:

```sql
ALTER TABLE mcp_page_charters
  ADD COLUMN multi_tenant_scope ENUM('required','superadmin_only','na') NOT NULL,
  ADD INDEX idx_charter_tenant_scope (multi_tenant_scope);
```

Cockpit (S7) pode então listar charters `multi_tenant_scope: required` que **NÃO têm Pest test cross-tenant** = red flag.

---

## Achado #5 — Charter deve ser type-safe via TypeScript

[DEV Community — Type-Safe UI Component Libraries 2026](https://dev.to/ninarao/best-type-safe-ui-component-libraries-for-react-in-2026-f22):

> "By defining explicit types or interfaces for your component's props, you create a contract that every usage of the component must satisfy. In 2026, building a component without TypeScript is like flying blind."

### Implicação no plano S4

🟡 Plano original previa charter em markdown. Pode ir além: **gerar `.d.ts` a partir do charter** pra que o `Index.tsx` seja type-safe contra o contrato.

Exemplo:

```typescript
// resources/js/Pages/Repair/Index.types.d.ts (GERADO automaticamente do charter)
export interface IndexProps {
  repairs: PaginatedRepair[];
  filters: { status_id: number; client_id: number; ... };
  // ... tudo que charter §5 (Implementation contract) declara
}

export interface IndexEvents {
  onStatusChange: (id: number, newStatus: number) => void;
  onBulkAction: (action: 'change_status' | 'change_staff', ids: number[]) => void;
}
```

Se desenvolvedor mudar `Index.tsx` e o tipo não bater com o charter → erro TypeScript.

**Mas:** isso é OPCIONAL. Adiciona complexidade. Recomendação: **deixar de fora do S4**, considerar pra S4.5 ou ADR futura.

---

## Recomendações pro plano S4 (revisões)

### O que manter

- Schema `mcp_page_charters` (com 1 coluna nova: `multi_tenant_scope`)
- Tool MCP `charter-fetch` com cache infinito até `charter_version` mudar
- Sync via `php artisan charter:sync` rodando em CI
- 10 charters preenchidos (mas começar com 3)

### O que mudar

| Item | Plano original | Revisão pós deep-dive |
|---|---|---|
| Template charter | 6 seções | **8 seções (objective/pre-cond/invariants/file-scope/contract/tests/acceptance/anti-patterns)** |
| Localização do `.charter.md` | qualquer | **AO LADO do `.tsx` que governa OU em `memory/charters/` se feature/mission** |
| Skill `charter-first` | Tier A skill | **Hook PreToolUse + Skill** (defense in depth) |
| Frontmatter | charter_id, kind, parent, owners | **+ multi_tenant_scope (required/superadmin_only/na) — Tier 0** |
| Schema validação | nada explícito | **`charter:sync` valida frontmatter contra schema; rompe build se inválido** |
| Type generation | não previsto | **OPCIONAL** — adiar pra S4.5 |

### O que adicionar

- [ ] Template charter.template.md com 8 seções (não 6)
- [ ] Hook `.claude/hooks/charter-guard.ps1` (pre-edit `.tsx` valida charter-fetch)
- [ ] Validação `multi_tenant_scope` obrigatória no frontmatter
- [ ] CI check: `multi_tenant_scope: required` sem Pest test cross-tenant = build break

### Estimativa revisada

Plano original: 5–7 dias.
Pós deep-dive: **6–8 dias** (template +2 seções, hook implementação, schema com 1 coluna nova).

---

## Sources

- [Prompt Contracts: Formal Specifications That Eliminate Vibe Coding](https://understandingdata.com/posts/prompt-contracts-specification-before-code/)
- [Frontmatter as Document Schema — Just Understanding Data](https://understandingdata.com/posts/frontmatter-as-document-schema/)
- [Configurancy: keeping systems intelligible when agents write all the code — ElectricSQL](https://electric-sql.com/blog/2026/02/02/configurancy)
- [Git hooks are your best defense against AI-generated mess — Web Developer Blog](https://jonesrussell.github.io/blog/git-hooks-ai-agents/)
- [Automate Pre-Commit Code Reviews with Claude Code Git Hooks — Dev Journal](https://earezki.com/ai-news/2026-04-01-claude-code-git-hooks-automate-code-review-before-every-commit/)
- [Design Systems 2026: How Airbnb & Uber Scale to 100M Users — Presta](https://wearepresta.com/design-systems-for-scale-2026/)
- [Best Type-Safe UI Component Libraries for React in 2026 — DEV](https://dev.to/ninarao/best-type-safe-ui-component-libraries-for-react-in-2026-f22)
- [GitHub Docs — Using YAML frontmatter](https://docs.github.com/en/contributing/writing-for-github-docs/using-yaml-frontmatter)

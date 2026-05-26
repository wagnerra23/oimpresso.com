# PROMPT_PARA_CLAUDE_CODE — F3 Financeiro completo (5 telas)

> **Wagner:** cole TUDO abaixo (a partir de `---`) no Claude Code, no repo `wagnerra23/oimpresso.com` checkout `main`. Ele cuida de tudo: baixa, salva, comita, push, abre PR.

---

## Tarefa

Implementar F3 do módulo Financeiro — 5 telas Inertia/React + 5 controllers. Origem: protótipo Cowork aprovado por [W] em 2026-05-09. Padrão Cockpit V2 (ADR ui/0114).

## Branch + setup

```bash
git fetch origin
git checkout -b feat/financeiro-f3-completo origin/main
mkdir -p resources/js/Pages/Financeiro/Unificado \
         resources/js/Pages/Financeiro/Fluxo \
         resources/js/Pages/Financeiro/Conciliacao \
         resources/js/Pages/Financeiro/DRE \
         resources/js/Pages/Financeiro/PlanoContas \
         Modules/Financeiro/Http/Controllers
```

## Baixar arquivos do Cowork (URLs válidas ~1h)

```bash
# TSX pages
curl -fsSL -o resources/js/Pages/Financeiro/Unificado/Index.tsx \
  "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Pages/Financeiro/Unificado/Index.tsx?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"

curl -fsSL -o resources/js/Pages/Financeiro/Fluxo/Index.tsx \
  "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Pages/Financeiro/Fluxo/Index.tsx?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"

curl -fsSL -o resources/js/Pages/Financeiro/Conciliacao/Index.tsx \
  "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Pages/Financeiro/Conciliacao/Index.tsx?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"

curl -fsSL -o resources/js/Pages/Financeiro/DRE/Index.tsx \
  "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Pages/Financeiro/DRE/Index.tsx?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"

curl -fsSL -o resources/js/Pages/Financeiro/PlanoContas/Index.tsx \
  "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/resources/js/Pages/Financeiro/PlanoContas/Index.tsx?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"

# Controllers
curl -fsSL -o Modules/Financeiro/Http/Controllers/UnificadoController.php \
  "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/Modules/Financeiro/Http/Controllers/UnificadoController.php?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"

curl -fsSL -o Modules/Financeiro/Http/Controllers/FluxoController.php \
  "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/Modules/Financeiro/Http/Controllers/FluxoController.php?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"

curl -fsSL -o Modules/Financeiro/Http/Controllers/ConciliacaoController.php \
  "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/Modules/Financeiro/Http/Controllers/ConciliacaoController.php?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"

curl -fsSL -o Modules/Financeiro/Http/Controllers/DREController.php \
  "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/Modules/Financeiro/Http/Controllers/DREController.php?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"

curl -fsSL -o Modules/Financeiro/Http/Controllers/PlanoContasController.php \
  "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/Modules/Financeiro/Http/Controllers/PlanoContasController.php?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"
```

## Atualizar `Modules/Financeiro/Routes/web.php`

Baixar o patch e seguir as instruções:

```bash
curl -fsSL -o /tmp/routes.patch.md \
  "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/Modules/Financeiro/Routes/web.php.patch.md?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"
cat /tmp/routes.patch.md
```

Aplicar os imports + bloco de rotas dentro do grupo `Route::middleware([...])->prefix('financeiro')->name('financeiro.')->group(...)` existente. Preservar rotas anteriores.

## Validar

```bash
php artisan route:list | grep financeiro
npm run typecheck   # ou tsc --noEmit
npm run lint
php artisan test --filter Financeiro
```

Se algum import shadcn/ui (`@/Components/ui/{badge,button,card,input,sheet,command}`) ou shared (`@/Components/shared/{PageHeader,KpiGrid,KpiCard}`) não existir no repo, criá-lo seguindo o padrão de outros módulos (ex: Dashboard usa esses mesmos paths).

## Commit + PR

```bash
git add resources/js/Pages/Financeiro Modules/Financeiro
git commit -m "feat(financeiro): F3 completo — 5 telas Cockpit V2 (Unificado, Fluxo, Conciliação, DRE, Plano de contas)

Origem: prototipo Cowork aprovado por [W] 2026-05-09
ADR: ui/0114 (Cockpit V2)
Tokens: BRIEFING §4 (emerald/amber/rose, rounded-md, shadow-sm, num tabular)

Telas:
- /financeiro/unificado     (visão única receivables+payables, drawer, ⌘K, 1-click baixa)
- /financeiro/fluxo-caixa   (projeção 35d com chart + próximos eventos)
- /financeiro/conciliacao   (extrato OFX × sistema, fuzzy match c/ confidence)
- /financeiro/dre           (DRE hierárquico mes vs anterior, % RL, deltas, export PDF/Excel)
- /financeiro/plano-de-contas (árvore Receitas/Despesas, busca server-side)

Controllers entregues como STUB com mock data — TODO[CL] marca onde
plugar Service real (FluxoCaixaService, ConciliacaoService, DREService,
ChartOfAccount Eloquent model com tenant scope).

Refs: PR #295 (protocolo Cowork v1.0)"
git push origin feat/financeiro-f3-completo
gh pr create --base main --title "feat(financeiro): F3 completo — 5 telas Cockpit V2" \
  --body "F3 do módulo Financeiro — 5 telas + 5 controllers stub.

**Origem:** protótipo Cowork \`Financeiro.html\` aprovado por [W] 2026-05-09.

**Decisões abertas (Wagner respondeu pré-merge):**
- [ ] Banco padrão ROTA LIVRE: Itaú PJ (mock usa)
- [ ] Plano de contas: Comunicação Visual · 2 níveis
- [ ] DRE: parar em \"Resultado operacional\" (Simples Nacional, sem CSLL/IR)
- [ ] Limite mínimo caixa (R\$ 5k) → virar config tenant em fase posterior

**Próximas fases protocolares:**
- F3.5 [CA]: accessibility-review WCAG 2.1 AA
- F4 [W2]: merge

**Não mergear sem F3.5.**"
```

## Decisões pra Wagner antes de F4

(Listadas no PR description — Wagner marca os checkboxes antes do merge.)

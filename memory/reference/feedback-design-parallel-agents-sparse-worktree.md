# Feedback — rodar agents de design em paralelo (worktree sparse + contrato DS)

> **Origem:** sessão 2026-05-31 (Wagner: "use o máximo possível"). Batch de 44 telas <70 → ≥70 do [PLANO-DESIGN-TELAS-2026-05-31](../governance/scorecards/PLANO-DESIGN-TELAS-2026-05-31.md). Spawn de 4+13+18+9 sub-agents general-purpose. Aprendizados caros que se repetiram.

## 1. Worktree sparse PERDE writes de agents (Tier 0 operacional)

**O quê:** quando a sessão roda numa `.claude/worktrees/<slug>` SPARSE (sem `resources/` materializado), os sub-agents Read/Edit/Glob ficam presos ao worktree vazio. Vários relataram "✅ feito" mas o write **não chegou ao disco real** — o `tsc`/`grep` depois mostrava o conteúdo ANTIGO.

**Why:** o agent escreve num overlay/caminho que não é o checkout canônico `D:\oimpresso.com\`. O relatório do agent é narrativa, não evidência.

**How to apply:**
- No prompt do agent: passar **caminho absoluto `D:\oimpresso.com\resources\js\Pages\...`** e exigir "SEMPRE Read antes de Edit; RE-LEIA após cada Edit pra confirmar gravação no DISCO".
- **NUNCA confiar no relatório do agent.** Verificar SEMPRE no disco real: `grep -cE "#hex|oklch"` por tela + `npx tsc --noEmit` filtrado pros alvos. Fechar por evidência (espelha sessão 2026-05-30 task-ledger + ADR 0236 ratchet), nunca por opinião do agent.
- Quando o write se perde, o parent (main loop) conserta direto via Edit — disco=verdade. Ver [[feedback-design-literal-copy-quando-aprovado]].

## 2. Agents ALUCINAM contrato do Design System — injetar assinatura real

**O quê:** repetidamente os agents inventaram props que não existem, quebrando `tsc`. Os erros reais mais comuns:

| Componente | ERRADO (alucinado) | CERTO (real) |
|---|---|---|
| `@/Components/shared/KpiCard` | `compact` | `size="compact"` (VariantProps) |
| `@/Components/shared/PageHeader` | `actions` / `subtitle` / `breadcrumbs` | `action` (SINGULAR), `title`, `description`, `icon` (string lucide), `moduleNav?` |
| `@/Components/ui/badge` | `variant="success"` / `"warning"` | SÓ `default\|secondary\|destructive\|outline` |
| `@/Components/ui/alert` | `variant="warning"` / `"success"` | SÓ `default\|destructive` |
| `@/Components/ui/select` | `<select>` nativo | compound `<Select value onValueChange><SelectTrigger><SelectValue/></SelectTrigger><SelectContent><SelectItem value>` |
| `@/Components/ui/switch` | props inventadas | `checked` + `onCheckedChange` |
| hooks (ex `useNfceStatus`) | campos chutados (`cStat`,`isLoading`) | LER a assinatura real antes — retorna `{data,isPolling,hasGivenUp,refetch}`, payload `status`/`cstat`/`motivo`/`chave_44` |

**How to apply:** ANTES de spawnar, o parent lê 1× as assinaturas reais (Grep nos componentes) e **injeta no prompt** ("CONTRATO DS REAL — não chutar"). Mesmo assim alguns alucinam → o gate `tsc` pega e o parent corrige.

**Cor (ReactFlow / libs com style inline):** geometria inline é OK; COR nunca hex cru. Tokens reais em `resources/css/inertia.css @theme`: `var(--color-primary\|success\|warning\|destructive\|muted-foreground\|foreground\|border\|card\|brand-meta)`. NÃO existe `--chart-*` nem `--primary` pelado.

## 3. Git + tsc travam o shell — disciplina

**Why:** `npx tsc --noEmit` (projeto inteiro, ~470 erros pré-existentes) trava o Bash ~3min. Disparar 20 git/verificação em paralelo no mesmo turno gera cascata de "Cancelled"/timeout.

**How to apply:**
- tsc: rodar **pra arquivo** (`npx tsc --noEmit > /tmp/x.txt 2>&1`) e ler o resultado via **PowerShell** `Get-Content` (não re-rodar). Filtrar só os arquivos-alvo (o projeto NÃO typecheck-a limpo global — só os meus alvos importam).
- git: poucas chamadas sequenciais. Commit via PowerShell here-string quando msg tem caracteres especiais. `php` não está no Bash (usar `& "C:\Users\wagne\.config\herd\bin\php.bat"` no PowerShell).
- **Não** fazer `git rev-parse a b` quando `b` (origin) pode não existir local → `fatal: Needed a single revision` derruba o batch paralelo inteiro.

## 4. Sub-agents consomem o limite de sessão rápido

**O quê:** 9 agents paralelos zeraram o limite ("resets 10:20am") e voltaram com `subagent_tokens: 0` (zero trabalho). Wave 0/1/3 (35 telas) consumiram muito.

**How to apply:** paralelismo é poderoso mas caro. Lotes de ~13 agents OK; depois disso, o limite bate. Quando bater, o **main loop continua** e faz as telas restantes direto (mais lento, porém confiável e sem perda de write).

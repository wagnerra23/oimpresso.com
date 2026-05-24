---
doc: ONBOARDING-TIME-UI
camada: meta-protocolo
status: ativo
created: 2026-05-24
audience: time MCP (Felipe, Maiara, Eliana, Luiz, devs futuros)
parent_adr: UI-0013
---

# Onboarding · enforcement UI v2 pra time MCP

> **Tempo:** 15 minutos uma vez (setup) + 30 segundos/dia (uso passivo).
> **Quem aplica:** todo dev/agent novo que vai tocar `resources/js/Pages/` ou `Components/shared/`.
> **Por quê:** sem essas 3 configurações, hook pre-commit e CI gate ficam **silenciosamente desligados** — drift volta sem você perceber.

## Setup obrigatório (5 minutos · uma vez)

### Passo 1 · Ativar hooks Git (1min)

```bash
cd /caminho/pro/oimpresso.com
git config core.hooksPath .githooks
```

**Verificar:**
```bash
git config core.hooksPath
# Deve printar: .githooks
```

Sem isso, hook pre-commit (`ui:lint --changed-only`) **não roda**.

### Passo 2 · Ativar UI lint strict (1min)

Adicione na sua shell (`.bashrc`, `.zshrc`, ou `~/.config/profile`):

```bash
export OIMPRESSO_UI_LINT_STRICT=1
```

Recarregar:
```bash
source ~/.bashrc   # (ou ~/.zshrc)
```

**Verificar:**
```bash
echo $OIMPRESSO_UI_LINT_STRICT
# Deve printar: 1
```

Sem isso, `ui:lint` em pre-commit é só warning (commit passa mesmo com regressão).

### Passo 3 · Validar baseline atual (3min)

```bash
cd /caminho/pro/oimpresso.com
php artisan ui:lint --baseline=config/ui-lint-baseline.json --strict
```

**Esperado:**
```
Baseline: 7307 violações · Atual: 7307 · Delta: +0
Ok · sem regressões vs baseline
```

Exit code: 0.

Se vier "REGRESSÃO": alguém já introduziu drift novo — **não comite por cima**, ajuste seu branch antes.

## Uso diário (30 segundos)

### Antes de começar tela nova

1. Carregue a Constituição UI v2 mentalmente:
   - **Hierarquia 4 camadas:** Fundações → Shell → Padrão de Tela → Módulo
   - **Camada superior herda das inferiores e nunca contradiz**
   - **Padrão de tela aplicável:** Index = PT-01 Lista · Edit = drawer 760 · Show = drawer 760 (ADR 0179)

2. Componentes shared sempre:
   - `<PageHeader>` (Slot 1 PT-01)
   - `<DataTable>` (Slot 5 PT-01)
   - `<BulkActionBar>` (Slot 4)
   - `<EmptyState>`
   - `<StatusBadge>`
   - Ícones via `lucide-react` (não FontAwesome, não emoji)

3. Tokens semânticos, não Tailwind literal:
   - ✅ `bg-accent`, `text-foreground`, `border-border`
   - ❌ `bg-blue-500`, `#3b82f6`

4. `localStorage` sempre prefixado:
   - ✅ `oimpresso.<modulo>.<chave>`
   - ❌ `myDraft`, `lastFilter`

### Antes de cada `git commit`

Se você seguiu setup, **nada precisa fazer manualmente**. Hook pre-commit roda automático:

```bash
git add resources/js/Pages/MeuModulo/Index.tsx
git commit -m "feat(meu-modulo): nova tela"
# Hook executa: php artisan ui:lint --changed-only --strict
# Se REGRESSÃO: commit BLOQUEADO
```

**Se hook bloquear**:
```bash
php artisan ui:lint --detail
# Vê quais arquivos regrediram + quais hits
# Refatora pra remover violações
# OU justifica via charter .md se for intencional
git add . && git commit
```

**Pular em emergência** (use com moderação):
```bash
git commit -m "..." --no-verify
```

### Antes de abrir PR

CI gate L3 (`.github/workflows/ui-lint.yml`) vai rodar automaticamente. Se vermelho:

1. Olha aba "Checks" do PR
2. Lê output do `ui:lint --strict`
3. Aplica mesmo fluxo do hook local
4. Push novo commit

## O que cada peça faz (resumo)

| Camada | Trigger | Bloqueia commit/PR? | Custo |
|---|---|---|---|
| **Skill `constituicao-ui-aware`** | Description-match em pedido | ❌ Não (gancho atenção) | $0 |
| **`php artisan ui:lint`** local | Manual | ❌ Sob demanda | $0 |
| **Pre-commit hook** | `git commit` (se setup OK) | ✅ Se `OIMPRESSO_UI_LINT_STRICT=1` | $0 |
| **CI `ui-lint.yml`** | PR aberto | ✅ Se regressão vs baseline | $0 |
| **CI `pr-ui-judge.yml`** LLM | PR aberto (se ativado) | ❌ Só comenta · `--strict` opt-in | ~$0.03/PR |
| **CI `visual-regression.yml`** | PR aberto | ✅ Em produção real (INFRA-ONLY hoje) | $0 |
| **CI `ui-canon-notify.yml`** | Merge em main | ❌ Apenas notifica | $0 |

## Mapa de docs essenciais

| Quando precisar | Vá em |
|---|---|
| "Que cor usar?" | [`01-fundacoes/`](01-fundacoes/) tokens (mas via Tailwind/CSS vars, não literal) |
| "Como faço tela de lista?" | [`padroes-tela/PT-01-Lista.md`](padroes-tela/PT-01-Lista.md) |
| "Posso adicionar token novo?" | Abrir ADR primeiro em [`memory/decisions/`](../../decisions/) ou [`adr/ui/`](adr/ui/) |
| "Quais são os anti-padrões?" | [`PRE-MERGE-UI.md`](PRE-MERGE-UI.md) (AP1-AP8) |
| "Como rodo o lint?" | [`UI-LINT-USAGE.md`](UI-LINT-USAGE.md) |
| "Quem decide o quê?" | [`adr/ui/0013-constituicao-ui-v2-camadas.md`](adr/ui/0013-constituicao-ui-v2-camadas.md) (Wagner é único aprovador) |

## Perguntas frequentes

### "Sidebar é dark ou light?"

**Light.** Wagner decidiu 2026-05-24 ([UI-0014](adr/ui/0014-sidebar-light-mantida-v2-parcial.md)). Próxima Claude Design vai propor dark — ignorar.

### "Posso usar `bg-blue-500` em arquivo Page?"

❌ Não. Use token semântico (`bg-accent`, `bg-primary`, etc). Hook + CI vão bloquear.

### "Quantas origins existem?"

**5:** OS (amber) · CRM (blue) · FIN (green) · PNT (violet) · MFG (orange). Não inventar 6ª sem ADR.

### "Meu PR foi bloqueado por R4 PT-01 (PageHeader missing) mas é intencional"

R4 é heurística — algumas Index.tsx são exceções válidas (ex: Cliente/Index usa drawer 760 que sobrescreve PT-01 padrão, ADR 0179). Se for caso documentado em charter `.charter.md`, justifique no PR descrição + faça `--write-baseline` consciente (Wagner aprovar).

### "Quanto custa rodar o LLM judge?"

~$0.03/PR. Hoje **DESLIGADO por default** (`PR_UI_JUDGE_ENABLED=false`). Só Wagner pode ativar via GitHub Variables.

### "Posso pular hook pre-commit em emergência?"

`git commit --no-verify` funciona. Use com MODERAÇÃO — Wagner vê no histórico se virou hábito.

### "Hook não está rodando · não detecta nada"

```bash
git config core.hooksPath
# Deve printar: .githooks
# Se vazio ou outra coisa: re-rodar Passo 1 do setup
```

```bash
echo $OIMPRESSO_UI_LINT_STRICT
# Deve printar: 1
# Se vazio: re-rodar Passo 2 do setup
```

## Sinais de regressão · alerte Wagner

Se notar:

- Score Module Grade v4 do seu módulo BAIXOU vs baseline
- Token Fundações sendo usado fora da camada permitida
- Componente novo reinventando algo do shared
- ADR sendo contradita silenciosamente em PR
- 5 origins viraram 6 (alguém adicionou origin nova sem ADR)

**Pare. Reporte ao Wagner. Não corrija silenciosamente** (princípio PRE-MERGE-UI).

## Refs

- **ADR-mãe:** [UI-0013 Constituição UI v2](adr/ui/0013-constituicao-ui-v2-camadas.md)
- **Doc tools:** [`UI-LINT-USAGE.md`](UI-LINT-USAGE.md)
- **Roadmap automação:** [`AUTOMATION-ROADMAP.md`](AUTOMATION-ROADMAP.md)
- **Skills correlatas:** `constituicao-ui-aware` (Tier A) · `mwart-process` (Tier A · cita PT-01) · `pr-ui-judge-manual` (Tier C · Wagner invoke)

---

**Última revisão:** 2026-05-24 · pós-smoke real Onda 1 + Onda 4 (descoberta: ANTHROPIC_API_KEY ausente).
**Próxima revisão:** quando time MCP ≥3 actors OU quando Wagner ativar `PR_UI_JUDGE_ENABLED=true`.

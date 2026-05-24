---
name: pr-ui-judge-manual
description: Use quando Wagner pedir "avaliar PR <número> contra Constituição UI v2", "rodar judge no PR X", "review semântico do PR Y", "/pr-ui-judge <PR#>", "score UI do PR Z", OU quando workflow CI `pr-ui-judge.yml` estiver desligado (default) e Wagner quiser rodar manualmente sem ativar quota Brain B globalmente. Carrega o comando canônico `php artisan ui:judge-pr <PR#>` + opções (--post-comment, --strict, --save-to, --repo) + custos esperados (~$0.034/PR · ~$0.005 após cache). NÃO substitui ui-lint.yml (sintático/lexical) — complementa com análise semântica LLM Brain B (Claude Sonnet 4.5) que detecta drawer modal sobre modal, slot inventado custom, layout violando PT-01 mesmo com componentes corretos, atalho duplicado, copy semântica errada. Substitui leitura repetida da docs do PrUiJudgeAgent.
tier: C
status: active
version: 1.0
authority: support
---

# Skill: pr-ui-judge-manual — Wagner-invoke do LLM judge UI

> **Onda 4.3** do [AUTOMATION-ROADMAP](../../memory/requisitos/_DesignSystem/AUTOMATION-ROADMAP.md). Permite invocar manualmente o `PrUiJudgeAgent` em qualquer PR sem precisar ativar o workflow CI globalmente.

## Quando ativa

- Wagner pede explicitamente: "avaliar PR 1438 contra Constituição UI v2"
- Slash invoke: `/pr-ui-judge 1438`
- Pedido "score UI do PR X" · "review semântico do PR Y" · "rodar judge no PR Z"
- Workflow `pr-ui-judge.yml` está DESLIGADO (default · `PR_UI_JUDGE_ENABLED=false`) e Wagner quer rodar pontual sem ativar quota global

## Comando canônico

```bash
# Avaliar local (stdout · sem postar)
php artisan ui:judge-pr 1438

# Avaliar + postar comentário no PR (precisa gh CLI autenticado)
php artisan ui:judge-pr 1438 --post-comment

# Avaliar + salvar JSON em arquivo
php artisan ui:judge-pr 1438 --save-to=storage/review-1438.json

# Strict mode (exit 1 se verdict=request_changes · uso CI)
php artisan ui:judge-pr 1438 --strict

# Repo custom (default detecta via gh CLI)
php artisan ui:judge-pr 1438 --repo=wagnerra23/oimpresso.com

# Combinação típica pra Wagner avaliar pontual
php artisan ui:judge-pr 1438 --post-comment --save-to=storage/review-1438.json
```

## O que o judge avalia (9 dimensões)

| Dimensão | Pontua |
|---|---|
| `hierarquia_4_camadas` | Módulo respeita Fundações > Shell > PT > Módulo |
| `pt_01_slot_adherence` | 6 slots PT-01 corretamente usados (em Pages/<X>/Index.tsx) |
| `anti_padroes_ap1_ap8` | Cor crua · componente reinventado · localStorage sem prefix · ícone fora lucide · gradient · emoji · status bg-fill · copy não-PT-BR |
| `tokens_semanticos` | `bg-accent`/`text-foreground`/`border-border` vs `bg-blue-500`/`#hex` |
| `componentes_shared` | Importa de `@/Components/shared/` vs reinventa |
| `atalhos_canonicos_jk_cmdk` | J/K · Enter · ⌘K · ?  · / · N |
| `localStorage_prefix_oimpresso` | `oimpresso.<modulo>.*` (multi-tenant Tier 0) |
| `pt_br_voice_tone` | Carioca-startup direto · sem inglês na UI |
| `lucide_iconography_only` | Sem FontAwesome em arquivo Page |

## Output esperado (formato JSON)

```json
{
  "score": 87,
  "verdict": "approve | request_changes | comment",
  "dimensoes": {
    "hierarquia_4_camadas": { "score": 9, "nota": "..." },
    "...": { ... }
  },
  "violacoes_estruturais": [
    { "tipo": "...", "arquivo": "...", "linha": N, "detalhe": "...", "severidade": "critical|warning|info" }
  ],
  "sugestoes": ["..."],
  "lembretes": ["..."]
}
```

## Custo

- ~$0.034/PR (Claude Sonnet 4.5 · primeiro do dia)
- ~$0.005/PR (após prompt caching · ~85% reuse)
- Quota tracked em `copiloto.admin.custos` (futuro · sem gate hoje)

## Quando NÃO usar esta skill

- ❌ PR é apenas backend/docs sem UI → command sai com score 100 + verdict comment, mas é desperdício de tokens (use `ui:lint` direto)
- ❌ Quer validação sintática rápida (cor crua, FontAwesome, emoji) → use `php artisan ui:lint` (Onda 1.2 · gratis)
- ❌ Quer hook pre-commit blocking → use `.githooks/pre-commit` com `OIMPRESSO_UI_LINT_STRICT=1` (Onda 2.2)
- ❌ CI gate automático em todo PR → ativa `PR_UI_JUDGE_ENABLED=true` no GitHub Actions variables (Onda 4.2 · cuidado custo)

## Pegadinhas conhecidas

- **`ANTHROPIC_API_KEY` deve estar em `.env`** ou env var quando rodar local. Verificar: `grep ANTHROPIC_API_KEY .env`
- **`gh CLI` precisa estar autenticado** (`gh auth status`) — comando usa `gh pr view` + `gh pr diff` + `gh pr comment`
- **Diff grande truncado** em 60KB no command (preserva budget tokens) — PRs gigantes podem perder contexto
- **Output JSON tolera ```json ... ``` wrapping** — mas se LLM responde texto puro, command imprime raw e exit 0 (não-strict)
- **Verdict `request_changes` não bloqueia PR** local — só `--strict` exit 1 (uso CI workflow)

## Refs

- **Agent**: [`Modules/Jana/Ai/Agents/PrUiJudgeAgent.php`](../../Modules/Jana/Ai/Agents/PrUiJudgeAgent.php)
- **Command**: [`app/Console/Commands/UiJudgePrCommand.php`](../../app/Console/Commands/UiJudgePrCommand.php)
- **Workflow CI**: [`.github/workflows/pr-ui-judge.yml`](../../.github/workflows/pr-ui-judge.yml) (default OFF)
- **ADR-mãe**: [UI-0013 Constituição UI v2](../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
- **AUTOMATION-ROADMAP**: [Onda 4](../../memory/requisitos/_DesignSystem/AUTOMATION-ROADMAP.md)
- **Skills correlatas**: `constituicao-ui-aware` (Tier A · carrega Constituição antes de codar) · `ultrareview` (review adversarial genérico)

## Versão

**v1.0** · 2026-05-24 · Onda 4 entregue como scaffold (workflow CI default OFF · ativação manual via Wagner)

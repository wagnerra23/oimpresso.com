---
date: 2026-05-29
hour: "20:45 BRT"
topic: "DS v4 roxo completo (4 PRs) + Onda F PR-A — componentes React + Cliente/Create+Edit elevado (F3 KB-9.75)"
duration: "~sessão longa · design-bundle-driven · 5 PRs mergeados"
authors: [claude-opus-4.8, wagner]
session: frosty-greider-83ab2f
---

# Handoff — DS v4 roxo + Onda F PR-A (F3 cadastro Contacts)

> Sessão guiada por **bundles de design do Cowork** (claude.ai/design), não por tasks MCP. Entregou o "design novo full" (accent roxo unificado) + os componentes de formulário da Onda F aplicados no cadastro de Cliente.

## Estado MCP no momento do fechamento

- **Driver:** bundles de design (handoff Cowork), não cycle/task MCP — nenhuma task MCP foi trabalhada nem criada.
- **Git (o "estado" relevante):** 5 PRs mergeados no `main` nesta sessão — #1971, #1974, #1975, #1976, #1977. Branches e worktrees temporários limpos.
- **Chip de task spawnado:** atualizar baselines visual-regression do Cliente/Create+Edit elevado (precisa app+browser).
- ADRs: nenhum novo criado (a **ADR 0235** DS v4 roxo já estava no main de sessão anterior; consumida aqui).

## O que aconteceu

1. **DS v4 roxo (accent universal)** — completei a unificação iniciada na ADR 0235:
   - **#1971** Onda F (vocabulário de form) no `prototipo-ui/design-system.css` (CSS canon).
   - **#1974** flip dos 6 bundles `cowork-*` (`--accent` azul 220 → roxo 295), escopado só nas linhas `--accent*` (preservou `--bubble-me`/`--status-partial` semânticos).
   - **#1975** Financeiro/Cobrança brand-blue (foco/funil) → `primary`.
   - **#1976** varredura **28 telas** `.tsx`: links/focus/seleção/botões `blue-*` → token `primary`. **Achado-chave:** dos "~106 arquivos com azul", a maioria é **semântico** (status/tipo/chart) que **fica azul** — só o azul-de-marca virou roxo.
2. **Onda F PR-A (#1977)** — a "saída React que faltava":
   - 4 componentes `@/Components/ui`: `Segmented` (Radix ToggleGroup, sem dep nova — vem do pacote unificado `radix-ui`), `FormSection`/`FormGrid`, `InputGroup`, `FieldState` (`FieldError`/`Success`/`Validating`/`RequiredMark`) + `.cw-*` em `cowork-fields.css`.
   - Aplicado em `Cliente/Create`+`Edit`+`DadosFiscaisBRSection`: radio→Segmented, select→Select, Section→FormSection, erro→FieldError, CNPJ→InputGroup+FieldSuccess, checkbox→Checkbox. **Extraído `_form/ClienteForm` comum** (Create+Edit dividem ~90%) + **rail de contexto** (`ClienteRail`: preview vivo + prontidão fiscal client-side + slot copiloto inerte).
   - Gate: screenshot dos componentes + do Create elevado aprovados por **Wagner + Claude Design** (🟢 code-side); `/mwart-override` aprovado (elevação F3 intencional).

## Artefatos gerados

- **#1971/#1974/#1975/#1976/#1977** (todos no `main`).
- `@/Components/ui/{segmented,form-section,input-group,field-state}.tsx` + `Pages/_Showcase/OndaF.tsx`.
- `Pages/Cliente/_form/{ClienteForm,ClienteRail,Field,cliente-form-types}.tsx` + `DadosFiscaisBRSection` migrado + `Create/Edit.tsx` finos + charters atualizados pro DS v4.
- `--cw-ok`/`--cw-ok-soft` + `.cw-*` Onda F em `resources/css/cowork-fields.css`.

## Persistência

- **git:** tudo mergeado no `main` (squash) — webhook GitHub→MCP propaga.
- **MCP:** sem tasks/ADRs novos.
- **BRIEFING:** _DesignSystem + Crm/Cliente foram tocados — refresh do BRIEFING.md é follow-up opcional (não feito pra fechar rápido).

## Próximos passos pra retomar

1. **PR-B** (gatilho do prompt PR-A): "PR-A mergeou" → Cowork solta o **guard `ds/*` + ratchet**. É o próximo passo natural — só colar o PROMPT_PARA_CODE do PR-B.
2. **Baseline visual** das telas elevadas (chip spawnado) — `pest --update-snapshots` com app rodando.
3. **PR-A2** (deferido): copiloto IA dedup CNPJ + sugestão de grupo (precisa endpoint backend) — slot do rail já pronto.
4. Opcional: formalizar `/mwart-override` como ADR per-tela; refresh BRIEFING _DesignSystem/Cliente.

## Lições catalogadas

- **Links de bundle de design expiram ~1h** — quando 404, pedir Wagner regenerar a URL (aconteceu 1× nesta sessão).
- **"Design novo full" era enganoso:** o brand→roxo já estava ~90% feito; ~90% do `blue-*` restante é **semântico** (status/tipo/chart) e deve ficar azul. Distinguir brand vs semântico é o trabalho real — feito caso-a-caso (4 agents paralelos no #1976, ruleset "na dúvida, preserva").
- **visual-regression em refactor F3 intencional:** vai vermelho de propósito (telas baselined mudaram) → `/mwart-override` + atualizar baseline pós-merge.
- **Charter schema é estrito** (só valida charters modificados): `last_validated` precisa de **aspas** (senão YAML vira date); `related_adrs` precisa de **integers** (`0093` com 8/9 = octal inválido → string que não casa o slug).
- **Screenshot quando o app não roda local:** harness HTML estático usando o `cowork-fields.css` real é pixel-fiel (o visual é 100% CSS-class-driven) — serviu de gate pros componentes + Create elevado.
- **Radix ToggleGroup sem dep nova:** o repo importa do pacote unificado `radix-ui` (`import { ToggleGroup } from "radix-ui"`), não de `@radix-ui/react-*`.

## Pointers detalhados (on-demand)

- ADR 0235 (`memory/decisions/0235-ds-v4-accent-roxo-universal.md`) · `prototipo-ui/AUDITORIA_DS_V4.md` (§2 bundles ✓ · §3 telas).
- Specs PR-A (vivem no bundle Cowork, expiram): `REGISTRY_DS_COMPONENTES.md` · `MATRIZ_MIGRACAO_DS.md` (P0-1..P0-6) · `PROMPT_PARA_CODE_PR-A-onda-f-react.md`.
- PRs: #1971 #1974 #1975 #1976 #1977.

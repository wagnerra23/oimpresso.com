---
name: cowork-prototype-replication
description: ATIVAR quando user pedir "fazer layout estado-da-arte", "replicar protótipo Cowork", "espelhar visual-source.html", "transformar prototipo-ui/* em Inertia React", "usar layout do cockpit pra módulo X", OU em Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx` quando existe `prototipo-ui/prototipos/<tela>/visual-source.html` ou `F1.html` correspondente. Carrega processo canônico de 7 fases (F0 sync + F1 mapping vocabulário vertical + F2 mapping CSS Cowork→Tailwind + F3 component hierarchy + F4 useMemo/useCallback + F5 Pest + F6 deploy + F7 smoke INTERATIVO) — RUNBOOK detalhado em [memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md](../../memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md). Caso real validado: Kanban Producao Oficina Caçambas 2026-05-13 (PRs #735→#740 madrugada pré-Martinho 10h).
trigger_intensity: B
tier: B
---

# Skill `cowork-prototype-replication` — replicar protótipo Cowork pra Inertia React (Tier B)

> **Caso real validado em 2026-05-13 madrugada (5h antes reunião Wagner × Martinho 10h):**
> Replicação 1:1 do `prototipo-ui/prototipos/producao-oficina/visual-source.html` (1213L canon Cowork) pro Kanban `/oficina-auto/producao-oficina` em Inertia React. **6 PRs em 5h** (Kanban V1 → MercosulPlate → Rich V2 → Pixel-perfect V3 → Drag-drop FSM → package-lock). Esta skill codifica o processo aprendido.

## Quando ativar

| Gatilho | Ação imediata |
|---|---|
| User pede "fazer layout estado-da-arte" / "replicar protótipo Cowork" / "espelhar visual-source.html pra X" | Carregar RUNBOOK + propor 7 fases |
| Edit/Write em `resources/js/Pages/<Mod>/<Tela>.tsx` SEM ler `prototipo-ui/prototipos/<tela>/visual-source.html` correspondente quando ele existe | Bloquear + ler protótipo PRIMEIRO |
| User cola screenshot de tela "estado-da-arte" + pede "fazer assim" | Procurar protótipo Cowork correspondente em `prototipo-ui/prototipos/` |

## Skills relacionadas

- **mwart-comparative Tier A** ([SKILL.md](../mwart-comparative/SKILL.md)) — orquestra loop Cowork ↔ Claude Code formalizado ([ADR 0114](../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)). Esta skill cowork-prototype-replication é uma sub-rotina técnica de mwart-comparative
- **mwart-quality Tier B** ([SKILL.md](../mwart-quality/SKILL.md)) — pré-flight checks F2/F3 técnicos
- **cockpit-runbook Tier B** ([SKILL.md](../cockpit-runbook/SKILL.md)) — gera RUNBOOK 11 seções

## 7 fases canônicas (RUNBOOK detalhado)

Detalhes completos + exemplos em **[`memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md`](../../memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md)**.

Resumo:

```
F0 SYNC LOOP (5min)
   Read prototipo-ui/HANDOFF.md + identificar protótipo alvo
   Glob prototipo-ui/prototipos/<tela>/{F1.html,visual-source.html,cowork-app.jsx}

F1 MAPPING VOCABULÁRIO VERTICAL (10min) ⭐ CRÍTICO
   Ler memory/reference_dominios_verticais_oimpresso.md
   Tabela mapping termos genéricos protótipo → vocabulário vertical alvo
   Ex: "Box B1/Elevador E1" (mecânica) → "Capacidade 5m³" (caçamba)
       "Veículo Honda Civic" (mecânica) → "Caçamba CC-001 5m³" (Martinho)
       "KM 84.220" (mecânica) → "Endereço entrega" (caçamba)
   ❌ NUNCA confundir m³ (caçamba) com m² (gráfica) — destrói credibilidade

F2 MAPPING CSS COWORK → TAILWIND (10min)
   Read prototipo-ui/prototipos/<tela>/visual-source.html FULL
   Extrair classes CSS canônicas: .ofc-plate, .prod-col-*, .ofc-veh-row,
                                    .ofc-symptom, .ofc-eta-row, .ofc-mech-av
   Tabela equivalência:
     - oklch(0.45 0.13 250) → bg-blue-700 (ou inline style se específico)
     - .prod-col-{slate,blue,rose,violet,emerald} → border-t-2 border-{color}-400
     - font-family: ui-monospace → font-mono ou style inline
     - tokens custom (ink-50/900) → slate-50/900 nativos Tailwind 4

F3 COMPONENT HIERARCHY (15min)
   Identificar componentes a criar (NEW) vs reusar (existing)
   Padrão canônico:
     - Page.tsx (Index) — header + KPIs + filter bar + grid
     - Column.tsx — header coluna + body scroll
     - Card.tsx — 5-6 linhas ricas
     - Sheet.tsx — drawer 5 sections
     - Plate.tsx (se aplicável) — visual canônico (ex: MercosulPlate)
     - StatusBadge, Banner, ActionButton — sub-components

F4 useMemo/useCallback DESCENDENTES (5min) ⭐ CRÍTICO
   LIÇÃO PR #717 — re-render loop em hierarquia profunda.
   Sempre:
     - memo() wrapper em Card e Column
     - useMemo() em arrays/objetos passados como props (columns, kanbanData)
     - useCallback() em handlers descendentes (onClick, onChange, onMove)
   Sem isso: TanStack/sortable/sensors disparam loop infinito

F5 PEST ESTRUTURAL (10min)
   tests/Feature/Modules/<Mod>/<Tela>RichUITest.php
   - Backend payload tem todos os campos esperados
   - Cross-tenant biz=99 (NUNCA biz=4 — ADR 0101)
   - markTestSkipped defensivo SQLite/schema ausente
   - Anti-regressão PR #717 (regex useMemo/useCallback presente)

F6 DEPLOY (10min)
   Commit + push + admin merge via gh CLI
   SSH Hostinger: git pull + optimize:clear (sem migration)
   Se package.json mudou: rodar npm install local pra atualizar
   package-lock.json → push → quick-sync.yml roda npm ci + build:inertia auto

F7 SMOKE INTERATIVO (10min) ⭐ CRÍTICO (lição PR #717)
   Browser MCP (Chrome) — NÃO só renderizar, INTERAGIR
   - Clicar filtros, mudar dropdowns, drag-drop, abrir drawer
   - Verificar console errors via read_console_messages
   - Pegar regressões React 19 que Pest estrutural não pega
```

## 12 elementos visuais canônicos do Cowork

Quando replicar Kanban/Lista/Detail estilo `prototipo-ui/prototipos/producao-oficina/visual-source.html`:

1. **Borda topo colorida por coluna** (`.prod-col-{slate,blue,rose,violet,emerald}` → `border-t-2 border-{color}-400`)
2. **Filter bar sticky** com pills capacidade/categoria + search input + KPI inline direita
3. **6 KPI cards** grid-cols-6 (não 4) — incluindo 1 "destaque rose" (atrasada/urgente) + 1 "valor em curso" emerald
4. **Card layout horizontal** placa + título + cliente em linha única (não 3 linhas separadas)
5. **OS# esquerda + valor R$ direita** canto superior do card
6. **Sintoma/observação legível** `text-[12px] text-slate-700 leading-snug` (NÃO italic line-clamp-2 apagado)
7. **Progress bar fina** `h-1 bg-blue-200 rounded` mostrando tempo/prazo
8. **Avatar atendente 18px** linha separada com iniciais (`.ofc-mech-av`)
9. **ETA + prazo** rodapé separados ("há N dias · N diárias" + "vence dd/mm")
10. **Banners coloridos** por status (rose atrasada / violet manutenção / amber aguardando / emerald pronta)
11. **Botões ação** por estado ("Iniciar →" / "Recolher →" / "Concluir →" / "Entregar →") com lucide ArrowRight/CheckCircle2/Wrench
12. **Drawer rico 5 sections:** Header (placa size='md' + KV grid) + Observação + Fotos & Laudo (3 placeholders) + Pipeline FSM (embed FsmActionPanel) + Linha do tempo (timeline dots)

## Drag-drop opcional (V2 quando justificado)

@dnd-kit/core + @dnd-kit/sortable + @dnd-kit/utilities. Mapping FSM action por transição (8 transições típicas Kanban). Confirmação modal via shadcn AlertDialog. Optimistic UI via Inertia partial reload (NÃO local copy — fonte verdade FSM canônico).

⚠️ **Hostinger TEM Node 24 + npm 11 via nvm** — quick-sync.yml roda `npm ci` + `npm run build:inertia` automático se package-lock mudou. Mas precisa atualizar package-lock.json LOCAL antes de push (worktree musing-hopper conseguiu via PowerShell `npm install`).

## Anti-padrões (NUNCA fazer)

- ❌ **Pular F1 mapping vocabulário** — confundir m³ (caçamba) com m² (gráfica) ou "veículo carro" com "caçamba" destrói demo
- ❌ **Pular F4 useMemo/useCallback** — re-render loop garantido em hierarquia >2 níveis com TanStack/dnd-kit (lição PR #717)
- ❌ **Pular F7 smoke INTERATIVO** — Pest estrutural NÃO pega React render loops; só clicar filtros pega
- ❌ **Inventar tokens custom** quando Tailwind nativo cobre (slate vs ink — usar nativos)
- ❌ **Não ler visual-source.html FULL** — pular detalhes (banner amber, progress bar, avatar) deixa V1 "tecnicamente OK mas esteticamente comum" (lição Sells PR #240-#248)
- ❌ **Reusar drawer compartilhado quando vertical pede sections diferentes** — separar (CacambaProducaoSheet vs ServiceOrderSheet) é OK quando justificado
- ❌ **Adicionar deps em package.json sem rodar `npm install` local** — package-lock.json desatualizado quebra `npm ci` em prod (Hostinger)
- ❌ **Esquecer 8ª peça canônica** `Resources/menus/topnav.php` — sempre invocar skill `criar-modulo` antes de criar Modules/<Nome>/

## Override autorizado

Wagner pode autorizar pular fase específica via `/cowork-skip <fase> <razão>` em PR comment. Sem comando, todas as 7 fases obrigatórias.

## Quando NÃO usar esta skill

- Tela TOTALMENTE NOVA sem protótipo Cowork correspondente — usar `mwart-comparative` Tier A direto (gera visual-comparison.md sem fonte canon)
- Mudança POLISH em tela existente já MWART (1-2 elementos) — usar `mwart-quality` Tier B
- Backend-only PR — esta skill é UI-first

## Refs

- [ADR 0114 — Loop Cowork ↔ Claude Code formalizado](../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) — mãe arquitetural
- [ADR 0107 — Visual gate F3](../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md](../../memory/requisitos/_DesignSystem/RUNBOOK-replicar-prototipo-cowork.md) — receita técnica completa com exemplos
- [memory/reference_dominios_verticais_oimpresso.md (auto-mem)] — vocabulário vertical (m³ vs m² etc)
- Skill `mwart-comparative` (orquestradora) · `cockpit-runbook` · `mwart-quality`
- Caso real validado: PRs #735, #736, #737, #738, #739, #740 (madrugada 2026-05-13)

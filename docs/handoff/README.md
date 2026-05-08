# docs/handoff — Bundles transitórios de design

> **O que é:** pasta de aterrissagem pra artefatos vindos do **Claude Design canvas** (claude.ai/design) ou de outros designers, antes de serem portados pro código React.
> **Quem usa:** Wagner (designer + dev), Claude Code (orchestra port via runbook `design-sync.md`).
>
> **Status:** transitório. Bundles podem ser deletados depois que a tela canon entra em prod e o `*.charter.md` é atualizado.

---

## Convenção de nomes

```
docs/handoff/[YYYY-MM-DD]-[modulo]-[escopo]/
```

Exemplos:
- `2026-05-08-sells-drawer-redesign/`
- `2026-05-12-compras-cockpit-migration/`
- `2026-06-01-financeiro-conciliacao-redesign/`

---

## Estrutura típica de um bundle

```
2026-05-08-sells-drawer-redesign/
├── README.md           ← gerado pelo Claude Design ou Wagner — descreve a sessão
├── original.html       ← export Claude Design canvas (se houver)
├── screenshot-1.png    ← mockup principal
├── screenshot-2.png    ← variações/estados (hover, empty state, etc)
├── notas-wagner.md     ← decisões durante a sessão (timezone, copy, persona, etc)
└── prompt-canvas.txt   ← (opcional) prompt usado no Claude Design canvas
```

`README.md` do bundle deve incluir:
- Página alvo (rota Inertia ex: `/sells/{id}/drawer`)
- Componente alvo (path ex: `resources/js/Pages/Sells/_components/SaleSheet.tsx`)
- Mudança: nova / alteração / refator visual
- Quem aprovou: Wagner (se aplicável)
- URL canvas (se houver)

---

## Workflow

1. **Wagner cria bundle** após sessão Claude Design canvas
2. Salva em `docs/handoff/[bundle-name]/`
3. Claude Code lê via runbook [`design-sync.md`](../../.claude/runbooks/design-sync.md) — Fluxo A
4. Skill [`ui-component-creator`](../../.claude/skills/ui-component-creator/SKILL.md) gera/altera código
5. PR criado referenciando o bundle path
6. Após merge + deploy + smoke OK → bundle pode ser **arquivado** (mover pra `docs/handoff/_archive/`) ou **deletado**

---

## NÃO fazer

- ❌ Commitar zip Claude Design direto no repo (extrair conteúdo primeiro)
- ❌ Misturar bundle com `memory/requisitos/` (memory é canônico, handoff é transitório)
- ❌ Fazer Wagner aprovar tela só com bundle (canon = ADR 0107 visual gate F1.5 → screenshot side-by-side com canon)
- ❌ Esquecer de criar `*.charter.md` da Page parent quando bundle gera tela nova canon target

---

## Refs

- Runbook [`design-sync.md`](../../.claude/runbooks/design-sync.md)
- Skill [`ui-component-creator`](../../.claude/skills/ui-component-creator/SKILL.md)
- [Design.md §2 Workflow Wagner](../../Design.md)
- [ADR 0107 Visual gate F1.5](../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0109 Claude Design plugin](../../memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md)
- [ADR 0110 Cockpit Pattern V2](../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)

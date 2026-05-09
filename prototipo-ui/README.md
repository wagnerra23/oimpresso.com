# prototipo-ui/ — loop Claude Design ↔ Claude Code

Diretório que orquestra o loop entre **Claude Design** (no Cowork, faz protótipo visual rápido) e **Claude Code** (neste repo, traduz pra Inertia/React real).

**Documento mãe:** [ADR 0114](../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md).
**Skill orquestradora:** [`mwart-comparative` V4](../.claude/skills/mwart-comparative/SKILL.md).
**Protocolo formal:** [PROTOCOL.md](PROTOCOL.md).

## Pra quem chegou aqui agora

| Papel | Onde escrever | Onde ler primeiro |
|---|---|---|
| **Wagner** ([W]) | `COWORK_NOTES.md` (pedidos), `HANDOFF.md` (estado) | [PROTOCOL.md](PROTOCOL.md) |
| **Claude Cowork** ([CC]) | `prototipos/<tela>/page.tsx` (export zip) | [CLAUDE_DESIGN_BRIEFING.md](CLAUDE_DESIGN_BRIEFING.md) |
| **Claude Design** ([CD]) | `prototipos/<tela>/critique-score.json` | [CLAUDE_DESIGN_BRIEFING.md](CLAUDE_DESIGN_BRIEFING.md) |
| **Claude Code** ([CL] — eu) | `CODE_NOTES.md`, `SYNC_LOG.md` | [CLAUDE_CODE_BRIEFING.md](CLAUDE_CODE_BRIEFING.md) |

## Mapa do diretório

```
prototipo-ui/
├── README.md                        ← você está aqui
├── PROTOCOL.md                      ← regras formais do loop
├── CLAUDE_CODE_BRIEFING.md          ← briefing pra Claude Code
├── CLAUDE_DESIGN_BRIEFING.md        ← briefing pra Claude Design
├── COWORK_NOTES.md                  ← INBOX: Wagner → Claude Design
├── CODE_NOTES.md                    ← OUTBOX: Claude Code → Wagner
├── SYNC_LOG.md                      ← timeline append-only
├── HANDOFF.md                       ← estado vivo (sobrescrito a cada sync)
├── TELAS_REVIEW_QUEUE.md            ← fila P0/P1/P2/P3
├── GLOSSARY.md                      ← termos design ↔ Inertia/shadcn
├── templates/
│   ├── critique.md.template         ← formato design:design-critique
│   ├── handoff-spec.md.template     ← formato design:design-handoff
│   └── charter-from-design.md.template
└── prototipos/<tela-kebab>/
    ├── page.tsx                     ← export Cowork (commitado)
    ├── COMPARISON.md                ← 15 dimensões mwart-comparative
    ├── critique-score.json          ← score 0-100
    └── a11y-report.md               ← WCAG 2.1 AA report (F3.5)
```

## Regra de ouro

**Nada em `prototipos/<tela>/` é editado direto no repo.** É export do Cowork. Se precisa mudar, refaz no Cowork e re-exporta. Single source of truth.

A tradução pra Inertia (`resources/js/Pages/<Mod>/<Tela>.tsx`) é onde código produtivo vive — esse é editado normalmente.

## Fluxo curto (TL;DR)

1. Wagner escreve pedido em `COWORK_NOTES.md`
2. Claude Cowork gera protótipo → export zip → vira `prototipos/<tela>/`
3. Claude Design roda `design:design-critique` → score em JSON
4. Wagner aprova SCREENSHOT (não tabela)
5. Claude Code traduz pra Inertia + abre PR
6. Claude Design roda `design:accessibility-review` (WCAG)
7. Wagner mergeia

Detalhes: [PROTOCOL.md](PROTOCOL.md).

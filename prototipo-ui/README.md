# prototipo-ui/ — loop Claude Design ↔ Claude Code

<!-- HANDOFF-ENTRY -->
## 🤖 Claude Code ([CL]) — COMECE AQUI (não pule, antes de qualquer código)

> Você recebeu este projeto via **Handoff to Claude Code**. O arquivo "aberto" no comando do handoff (ex.: o `.html` que abriu) é só o **ponto de entrada** — **NÃO é a sua tarefa**. Sua lista de tarefas vive em **`COWORK_NOTES.md` → seção "📥 Pendentes"**.

**Ordem de leitura obrigatória:**
1. **[`COWORK_NOTES.md`](COWORK_NOTES.md) → "📥 Pendentes"** — sua **LISTA DE TAREFAS**. Processe de cima pra baixo; cada item diz o quê · o destino no repo · a natureza (§10.4 / Tier 0).
2. **[`PROTOCOL.md`](PROTOCOL.md)** — o protocolo (**v2 · [ADR 0282](../memory/decisions/0282-protocolo-v2-colapso-ratificacao.md): 2 papéis × 3 fases — comece pelo §0 Mapa de vigência**; corpo v1 "6 papéis × 7 fases" preservado como histórico · §10.4 · **Passo 0**: `git fetch` + ancorar em `origin/main` fresco antes de validar).
3. **[`CLAUDE_CODE_BRIEFING.md`](CLAUDE_CODE_BRIEFING.md)** — briefing do papel [CL].
4. **Charters de papel** — `CHARTER_GOVERNANCA_W.md` ([W] soberano) + `CHARTER_CHAMPION_AGENTES.md` ([CC]/[CL]/[CD]/[CA]), formalizados na ADR 0242: o que cada papel faz / não faz.

**Como agir em cada pendente:**
- **Valide contra `origin/main` sozinho (§10.4)** — não escale pra [W] o que o git já responde; só o subjetivo.
- **Tier 0** (ADR · constituição · multi-tenant · segredo · tooling/lint · produto) → abre **PR e espera [W]**. **Aditivo / não-Tier-0** → loop autônomo (CI verde → merge).
- **Ao terminar:** marque `[PROCESSADO AAAA-MM-DD]` na `COWORK_NOTES.md` **e** escreva o retorno em [`CODE_NOTES.md`](CODE_NOTES.md).

> ⚠️ **Preservar este marcador `<!-- HANDOFF-ENTRY -->` em qualquer regeneração futura do README** (L-18 · `jana:health-check → readme_handoff_block_missing`).
<!-- /HANDOFF-ENTRY -->

---

Diretório que orquestra o loop entre **Claude Design** (no Cowork, faz protótipo visual rápido) e **Claude Code** (neste repo, traduz pra Inertia/React real).

**Documento mãe:** [ADR 0114](../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md).
**Skill orquestradora:** [`mwart-comparative` V4](../.claude/skills/mwart-comparative/SKILL.md).
**Protocolo formal:** [PROTOCOL.md](PROTOCOL.md).

## Pra quem chegou aqui agora

| Papel | Onde escrever | Onde ler primeiro |
|---|---|---|
| **Wagner** ([W]) | `COWORK_NOTES.md` (pedidos), `HANDOFF.md` (estado) | [PROTOCOL.md](PROTOCOL.md) |
| **Claude Cowork** ([CC]) | `cowork/<path-original>` (somente build) | [CLAUDE_DESIGN_BRIEFING.md](CLAUDE_DESIGN_BRIEFING.md) |
| **Claude Design** ([CD]) | parecer no canal canônico do ciclo | [CLAUDE_DESIGN_BRIEFING.md](CLAUDE_DESIGN_BRIEFING.md) |
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
├── cowork/                          ← ÚNICA fonte ativa de design; build apenas
└── prototipos/                      ← somente âncoras históricas declaradas
```

## Regra de ouro

**O build ativo existe uma vez, em `cowork/`, no path relativo original.** O importador não
move, achata nem corrige caminhos. Documentos e contratos não entram no bundle; são
reconciliados diretamente no respectivo canon. Conteúdo duplicado reprova o lote inteiro.

A tradução pra Inertia (`resources/js/Pages/<Mod>/<Tela>.tsx`) é onde código produtivo vive — esse é editado normalmente.

## Fluxo curto (TL;DR)

1. Wagner escreve pedido em `COWORK_NOTES.md`
2. Claude Cowork gera protótipo → bundle build-only → atualiza `cowork/`
3. Claude Design revisa o resultado contra o charter canônico
4. Wagner aprova SCREENSHOT (não tabela)
5. Claude Code traduz pra Inertia + abre PR
6. Claude Design roda `design:accessibility-review` (WCAG)
7. Wagner mergeia

Detalhes: [PROTOCOL.md](PROTOCOL.md).

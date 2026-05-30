# STATUS.md — Espinha do Cowork (single source of truth)

> **LER ISTO PRIMEIRO em todo chat novo.** Espelho-fonte do `Painel Cowork - Estado Atual.html`.
> Atualizar ao fim de cada sessão: estado de tela mudou? decisão tomada? → reflete aqui.
> Atualizado: 2026-05-30 · [CC]

---

## ⚖️ Lei suprema (mora no GIT — eu obedeço)
- **`prototipo-ui/PROTOCOL.md`** — 6 papéis × 7 fases, gates, overrides, métricas de saúde, anti-padrões.
- **`prototipo-ui/CLAUDE_DESIGN_BRIEFING.md`** — tokens canônicos (shadcn + warm), personas, 15 dimensões, proibições.
- **ADRs** (`memory/decisions/`): 0114 loop · 0110 Cockpit V2 · 0107 gate visual F1.5 · 0104 MWART · 0010/0027/0028 memória.
- **`CARTA_DESIGN_CC.md`** (local) — carta **subordinada**: como [CC] obedece o acima. NÃO é lei.
- Memória manda o **git**; [CC] cuida do **design**; decide **[W]**.

## DS vigente
- **Canônico:** v4.1 (tokens.css + design-system.css no git `wagnerra23/oimpresso.com@main`, ondas A–D + form vocab).
- **Proposto:** v4.2 (cockpit, fiscal-badge, sla-pill, readiness, shortcut-bar, formpage PT-03). Spec: `Design System v4.2 - Evolucao.html`.

## Decisões vigentes (não rediscutir — detalhe em memory/decisions/)
| ID | Decisão | Status |
|----|---------|--------|
| D-00 | Padrão Cockpit V2 (sidebar+header+body+footer+drawer) | charter · **ADR 0110**/0114 |
| D-01 | DS é piso, não teto — harmonizar sem achatar identidade | firme |
| D-02 | Identidade por tela (`--accent`/oklch) | **PROPOSTA F0** · toca BRIEFING §7 (não inventar paleta) |
| D-03 | Cadastro grande = página inteira (PT-03) | **PROPOSTA F0** · toca proibição Sheet-drawer |
| D-04 | Escopo DS 4.2 (cockpit, fiscal, sla, readiness, shortcut-bar) — aditivo | proposto |
| D-05 | Cor crua fora dos tokens = erro | proposto |
| D-06 | Protótipo e produção importam o MESMO tokens/design-system.css | proposto (norte) |
| D-07 | Proibições charter (BRIEFING §7) | charter |

> Propostas só viram lei via loop F0→F1.5→ADR aprovado por [W]. Até lá, valem os tokens canônicos do BRIEFING.

## Quadro de telas (auditoria 2026-05-30)
| Tela | Identidade | Nota | Estado | Próximo passo |
|------|-----------|------|--------|---------------|
| Vendas | verde 155 | 9.5 | piloto aprovado | aplicar no shell |
| Compras | navy/cream `#1f3a5f` | 9.4 | hex hand-rolled | migrar hex → --accent |
| Financeiro | roxo 295 | 8.0 | colisão <1100px | corrigir KPI grid |
| Caixa Unificada (Inbox) | método completo | 9.75 | REFERÊNCIA | não mexer — é gabarito |
| CRM | azul 220 | 8.6 | já alinhado | migração barata |
| Oficina | âmbar 60 + tweaks | 9.5 | tweaks ok | radius/shadow DS |
| Clientes/Contatos | indigo 262 | — | molde PT-03 pronto | replicar nos 3 cadastros |

## IA — onde tudo mora
- **canon/** — tokens.css, design-system.css, Oimpresso ERP - Chat.html, *-page.jsx/css, Método KB-9.75.html
- **memory/decisions/** — ADRs (append-only) + este STATUS.md
- **bridge/** — prototipo-ui-patch/ (espelho repo) + PROMPT_PARA_CODE_*.md
- **archive/** — DS v3/v4 antigas, GAPS_v1..v4, PROMPT_* antigos, ~25 HTMLs experimentais (lista no PLANO_ORGANIZACAO_CASA.md)

## Em aberto (aguardam Wagner)
1. Migrar Compras do hex → `--accent` escopado (prova do caso extremo).
2. Executar limpeza canon/archive (PLANO_ORGANIZACAO_CASA.md já lista).
3. Gerar ponte DS 4.2 → Claude Code (patch + prompt zero-toque) após aprovar spec.

## Ritual (fecha o loop)
1. **Início:** ler este STATUS + índice de ADRs.
2. **Durante:** produzir contra a espinha; arquivo novo nasce no lugar da IA.
3. **Fim:** decisão → ADR; estado de tela → atualiza este arquivo.

## Artefatos desta sessão (2026-05-30) — rascunhos de decisão (F0/F1), não entrega canônica
- `Auditoria - O Melhor de Cada Tela.html` — lista de proteção por tela.
- `Piloto Vendas - Antes Depois.html` — verde preservado sobre DS (proposta).
- `Cadastro Cliente - Pagina Inteira DS 4.2.html` — molde PT-03 (proposta).
- `Design System v4.2 - Evolucao.html` — spec da v4.2 (proposta).
- `Painel Cowork - Estado Atual.html` — espelho visual desta espinha.
- `CARTA_DESIGN_CC.md` — como [CC] obedece o git (subordinada).
> Entrega canônica de F1 = `prototipos/<tela>/page.tsx` + COMPARISON.md + critique-score.json (PROTOCOL.md §4).

---
date: "2026-09-06"
time: "0156 BRT"
slug: "integridade-prototipo-producao-sha-dupla"
tldr: "Dupla âncora SHA (fonte × alvo) derrubou os 8/8 recibos do design-sync que o main afirmava (Fiscal: alvo moveu 04/09; Arquivos: recibo de 01/09 nasceu stale — LC-20 3ª). Re-medido de origin/main fresco: 7 compared + 1 applied refeitos, 3 map.json regenerados, 2 âncoras em Compras, 3 protótipos + github.md descidos do Cowork vivo (3 sync). PR #6892, CI em execução. Aberto: 255/258 sem veredito, 11 gap.md sem mapa, 18 órfãos + 8 a criar."
decided_by: ["W"]
cycle: null
prs: [6892]
us: []
next_steps:
  - "Acompanhar o CI do #6892 até verde e pedir merge a [W]"
  - "Cowork: regenerar o bundle (`gerar-payload-partes.mjs` do lado que tem os arquivos) — sem isso fiscal-page.jsx/arquivos-page.jsx (abaixo do piso de persistência do get_file) seguem sem veredito de fidelidade"
  - "[W] decide destino das 18 telas órfãs e das 8 a criar (`status.mjs --check-mapping` rc 1 é fail-closed por desenho)"
  - "Reescrever os 11 -gap.md em formato antigo (sem tabela Parte+Ação) antes de gerar map.json — Cliente, Compras, Crm, KB, OficinaAuto, Produto, RecurringBilling, Sells×2, _DesignSystem×2"
  - "Primeiro recibo executável de teste/smoke em alguma tela (D-5/D-6 da 0384 nunca exercidos — tested/validated 0/0 em 93 telas)"
related_adrs: ["0130-handoff-append-only-mcp-first", "0384-design-sync-recibos-executaveis-por-tela", "0374-emenda-0315-espelho-cowork-e-rota-prevista", "0379-bundle-design-transacao-manifesto-delta-staging"]
---

# Handoff — integridade protótipo × produção por dupla âncora SHA

> Sessão de 2026-09-05 (fechada 06/09 01:56 BRT). Narrativa completa em
> [sessions/2026-09-05-integridade-prototipo-producao-sha-dupla.md](../sessions/2026-09-05-integridade-prototipo-producao-sha-dupla.md).
> Este handoff conta o **estado pro próximo**, não o trabalho.

## O que está no ar depois do #6892

| peça | estado |
|---|---|
| `scripts/design-sync/state/` | projeção recomputada em 05/09 · `applied 1 · compared 7 · anchored 59 · to-create 8 · blocked 18` · `--check-lifecycle --module Fiscal --minimum compared` rc 0 |
| `*.map.json` | 12 mapas · 0 DRIFT · 0 STALE · `--strict` rc 0 · estáveis 13/52 (Compras +2 via `vivo.ancora: "purchase-itens"`) |
| espelho `prototipo-ui/cowork` | `inbox-page.jsx` · `financeiro-page.jsx` · `vendas-page.jsx` = vivo (hash igual, ledger registrado) · `design-docs/github.md` = vivo |
| bundle | remoto == local (id `5023b274…`, 24/08, 255/255) — rota esgotada até o Cowork regenerar |
| ledger LC-20 | 3 ocorrências (a 3ª = recibo de Arquivos gravado de checkout atrasado em 01/09) |

## Armadilhas que a próxima sessão vai pisar se não ler isto

- **`get_file` de arquivo abaixo de ~49 KB volta inline** — não há JSON em disco pra `--snapshot-from`/`--export-from`; escrever de lá é transcrição (ADR 0374). É por isso que `fiscal-page.jsx` e `arquivos-page.jsx` ficaram sem veredito. A saída é o bundle do lado Cowork, não esforço daqui.
- **`gerar-map.mjs --atualizar` emite em stdout, não grava** — redirecione pro `.map.json` (e valide JSON antes de mover).
- **`status.mjs --check-mapping` sai rc 1 no main** pelas 18 órfãs pré-existentes — não é regressão deste PR.
- **Hook `block-destructive` barra `rm -f` em loop de shell** — grave via Node.
- **Não meça o espelho contra `sync/bundle.manifest.json`** — ele confirma o que já está no espelho (lápide §5 2026-08-25); só o `get_file` por arquivo trouxe estado real.

## Estado MCP no momento do fechamento

Snapshot do `brief-fetch` do início da sessão (Brief #611, gerado 05/09) — tools MCP `mcp__oimpresso__*` não estavam expostas nesta sessão (worktree filho), o brief veio pelo hook `brief-fetch-curl`:

- Cycle: — · HITL pending [W]: 5 · Commits 24h: 96 · Incidentes: 0
- Em voo (top): Forja triage tasks órfãs (27d) · Produto [G-06] BOM drag-drop (51d) · Produto [V0] preço zero 0-row (52d) · Infra Zod schemas (27d) · Documentacao Blade→Inertia (3h)
- Flags: 🟠 680 US não atribuídas (525 sem dono) · 🟡 SDD composta 41,0 · 🟢 visual regression CI
- `whats-active`: não consultado (tool indisponível nesta sessão) — nenhum sinal de sessão paralela nos paths tocados; o branch `claude/prototipo-producao-integridade-9c2725` que existia estava 212 atrás e seus 3 commits já estavam no main (#6408).

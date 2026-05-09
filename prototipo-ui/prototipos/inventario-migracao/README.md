# Protótipo F1 — inventario-migracao

**Status:** 🟡 PINO F1 HISTÓRICO — sem charter ainda, P2 sugerido.
**Aprovado por:** [W] 2026-05-09 (Cowork export incluído no zip canon)
**Stories:** sem story em SPEC ainda — backlog

> ⚠️ **Antes de começar F3:** ler [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../LICOES_F3_FINANCEIRO_REJEITADO.md) — pré-flight checklist obrigatório.

## O que está aqui

| Arquivo | Tamanho | Função |
|---|---|---|
| `F1.html` | 30 KB | **Standalone** — tela Inventário completa, autocontida (sem .jsx externo) |

Convenção `F1.html` segue precedente de [`producao-oficina/F1.html`](../producao-oficina/F1.html) (mergeada via loop em PR [#326](https://github.com/wagnerra23/oimpresso.com/pull/326)→[#330](https://github.com/wagnerra23/oimpresso.com/pull/330)).

## Por que é "migração Blade React"

Inventário tem implementação Blade legacy no UPOS (`stocks/index.blade.php` ou similar). Protótipo Cowork mostra a tela em **Cockpit V2** já — proposta de migração MWART.

Contexto MWART: ler [ADR 0104 — Processo MWART canônico](../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) + [skill `mwart-process`](../../../.claude/skills/mwart-process/SKILL.md) (Tier A).

## Status no loop F0→F4

| Fase | Status |
|---|---|
| F0 BRIEF [W] | ⏳ aguardando — não há entrada em COWORK_NOTES.md |
| F1 DESIGN [CC] | ✅ feito (standalone HTML) |
| F1.5 CRITIQUE [CD] | ⏳ pendente |
| F2 SCREENSHOT [W2] | 🟡 protótipo é o próprio HTML — abrir local pra revisar |
| F3 CODE [CL] | bloqueado por charter ausente |
| F3.5 A11Y [CA] | — |
| F4 MERGE [W2] | — |

## Pré-requisitos pra F3 (quando Wagner priorizar)

1. **Charter ausente** — criar `resources/js/Pages/Stocks/Index.charter.md` (ou equivalente UPOS path) ANTES de F3 — gate ADR 0107
2. Identificar Models reais: `App\Product` + `App\Variation` + `App\VariationLocationDetails` (estoque por localização) — UPOS canon
3. Permission: `view_purchase_stock` ou similar — confirmar em seeders
4. Decidir se vira `Pages/Stocks/Index.tsx` (UPOS path) ou `Pages/Inventario/Index.tsx` (PT-BR semântica) — pode precisar ADR

## Como rodar offline (smoke visual)

1. Abrir [`F1.html`](F1.html) direto no navegador (standalone, sem build)
2. Babel-standalone transpila inline — só CDN React 18

## Próximo passo

Não está priorizado. Quando entrar na fila P2 ([`TELAS_REVIEW_QUEUE.md`](../../TELAS_REVIEW_QUEUE.md)):

1. Wagner adiciona em P2 com decisão sobre path (`Pages/Stocks/` vs `Pages/Inventario/`)
2. Charter via skill `charter-write`
3. F0 em `COWORK_NOTES.md`
4. Loop normal

Estimativa: bloqueado até backlog definir prioridade real (cliente paga + reporta — [ADR 0105](../../../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

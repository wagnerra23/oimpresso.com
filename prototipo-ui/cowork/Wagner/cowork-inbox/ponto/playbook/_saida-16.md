---
sessao: "16"
titulo: "gap.md — Aprovações — saída da thread"
autor: "[CL]"
criado: 2026-09-28
base: e4289e688
thread: 16-gap-aprovacoes.md
veredito: "entregue — aprovacoes-index-gap.md + map (10 regiões); 3 das 5 divergências da thread já estavam consertadas no protótipo; rejeição em lote fica pendente [W]"
---

# _saída 16 · gap.md — Aprovações

## Entregue

- `memory/requisitos/Ponto/aprovacoes-index-gap.md`
- `memory/requisitos/Ponto/aprovacoes-index.map.json`

## Como foi medido

- **Base:** `origin/main` @ `e4289e688`. Protótipo `ponto-telas.jsx` @ `2e3f8adb4e` (2026-09-24).
- **As faixas de linha da thread são de 14/09 e não valem mais.** O build de 24/09 mudou o arquivo; tudo foi re-medido nas duas pontas, com `grep -n`.
- **Lado vivo:** cada linha citada no gap saiu do `.tsx` ou do controller real. O contrato vem do charter e do protótipo, nunca do `.tsx` (§5 2026-06-05).
- **Map:** esqueleto por `gerar-map.mjs` para o scratch, âncoras preenchidas a partir das medições, copiado depois. `design-code-map-check.mjs --check --strict` → rc=0, 0 drift.
- **Decisões:** só as da `ATA-DECISOES-2026-09-14.md`. O que a ata não responde ficou declarado como pendente no gap, sem inventar.
- Não toquei em `Pages/` (o `nao_toca` da thread).

## O que a medição mudou em relação à thread

| divergência da thread | estado medido |
|---|---|
| paginação 15 × 20 | fechada — protótipo em 20 (`:33`), servidor em 20 |
| KPIs por estado ausentes | fechada — existem nos dois lados |
| rejeição por `window.prompt` | fechada — protótipo usa `Modal` com mínimo 5 |
| `impacta_apuracao` ausente | fechada — existe nos dois lados |
| filtro default | convergência: os dois abrem em `PENDENTE`; o charter segue sem declarar |

## O que sobra

- **Pendente [W], sem id na ata:** rejeição em lote. O protótipo tem `Rejeitar N` com motivo único; o vivo e o charter só têm aprovação em lote (a rota é só de aprovação). É comportamento novo, logo não é conserto de passagem.
- **Defeito no vivo, independente da decisão:** o lote usa `confirm()` nativo. A ata (R3, D-ESC-DESTROY) já tratou `window.confirm` como "não era pergunta".
- **Vivo à frente:** coluna Criada. **Protótipo à frente:** contador de pendentes no filtro, cargo na sub-linha, rodapé legal — ficam para a passada de FORMA (thread 15).

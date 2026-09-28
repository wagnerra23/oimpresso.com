---
sessao: "21"
titulo: "gap.md — Banco de horas — saída da thread"
autor: "[CL]"
criado: 2026-09-28
base: e4289e688
thread: 21-gap-banco-horas.md
veredito: "entregue — banco-horas-index-gap.md + banco-horas-show-gap.md + 2 maps; achado: o extrato pagina 50 no servidor e o .tsx não navega"
---

# _saída 21 · gap.md — Banco de horas

## Entregue

- `memory/requisitos/Ponto/banco-horas-index-gap.md`
- `memory/requisitos/Ponto/banco-horas-show-gap.md`
- `memory/requisitos/Ponto/banco-horas-index.map.json`
- `memory/requisitos/Ponto/banco-horas-show.map.json`

## Como foi medido

- **Base:** `origin/main` @ `e4289e688`. Protótipo `ponto-telas.jsx` @ `2e3f8adb4e` (2026-09-24).
- **As faixas de linha da thread são de 14/09 e não valem mais.** O build de 24/09 mudou o arquivo; tudo foi re-medido nas duas pontas, com `grep -n`.
- **Lado vivo:** cada linha citada no gap saiu do `.tsx` ou do controller real. O contrato vem do charter e do protótipo, nunca do `.tsx` (§5 2026-06-05).
- **Map:** esqueleto por `gerar-map.mjs` para o scratch, âncoras preenchidas a partir das medições, copiado depois. `design-code-map-check.mjs --check --strict` → rc=0, 0 drift.
- **Decisões:** só as da `ATA-DECISOES-2026-09-14.md`. O que a ata não responde ficou declarado como pendente no gap, sem inventar.
- Não toquei em `Pages/` (o `nao_toca` da thread).

## Decisões da ata aplicadas

- **D-BH-KPI** (emenda o charter, ficam os 4 do protótipo): o vivo tem o conjunto antigo do charter. A emenda é da thread 27; o `.tsx` muda depois.
- **D-PONTO-DETALHE** absorve a D-BH-ROTA: o extrato é rota própria, e o protótipo corrige o `Detalhes` para `Movimentos` com navegação.

## Achado novo

O servidor pagina o histórico em 50 (`BancoHorasController.php:107`) e o `Show.tsx` não renderiza navegação — só a interface declara `links`/`last_page`. Quem passa de 50 movimentos vê só os 50 primeiros. Gap real no vivo; o protótipo também não pagina.

## Mantido do protótipo, sem portar

A soma local de saldo do protótipo é mock. Portar viola o Non-Goal "não recalcula o saldo".

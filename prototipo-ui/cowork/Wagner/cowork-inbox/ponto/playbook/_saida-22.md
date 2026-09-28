---
sessao: "22"
titulo: "gap.md — Escalas — saída da thread"
autor: "[CL]"
criado: 2026-09-28
base: e4289e688
thread: 22-gap-escalas.md
veredito: "entregue — escalas-index-gap.md + escalas-form-gap.md + 2 maps; remover ainda usa window.confirm no vivo; turnos editáveis decididos e pendentes nos dois lados"
---

# _saída 22 · gap.md — Escalas

## Entregue

- `memory/requisitos/Ponto/escalas-index-gap.md`
- `memory/requisitos/Ponto/escalas-form-gap.md`
- `memory/requisitos/Ponto/escalas-index.map.json`
- `memory/requisitos/Ponto/escalas-form.map.json`

## Como foi medido

- **Base:** `origin/main` @ `e4289e688`. Protótipo `ponto-telas.jsx` @ `2e3f8adb4e` (2026-09-24).
- **As faixas de linha da thread são de 14/09 e não valem mais.** O build de 24/09 mudou o arquivo; tudo foi re-medido nas duas pontas, com `grep -n`.
- **Lado vivo:** cada linha citada no gap saiu do `.tsx` ou do controller real. O contrato vem do charter e do protótipo, nunca do `.tsx` (§5 2026-06-05).
- **Map:** esqueleto por `gerar-map.mjs` para o scratch, âncoras preenchidas a partir das medições, copiado depois. `design-code-map-check.mjs --check --strict` → rc=0, 0 drift.
- **Decisões:** só as da `ATA-DECISOES-2026-09-14.md`. O que a ata não responde ficou declarado como pendente no gap, sem inventar.
- Não toquei em `Pages/` (o `nao_toca` da thread).

## Decisões da ata aplicadas

- **D-ESC-DESTROY:** a trava por vínculo está nos dois lados e no servidor. O vivo ainda confirma com `window.confirm` (`Escalas/Index.tsx:50`) — gap real.
- **D-ESC-TURNOS** (SIM, editável): os dois lados seguem read-only. O escopo do CRUD não está na ata — pendente de emenda de charter (thread 27), sem inventar.
- **D-PONTO-DETALHE:** `Nova escala` e `Editar` navegam para a rota; o protótipo corrige.

## O que a medição mudou em relação à thread

- O estado vazio com CTA, que a thread dava como "a nascer", **já existe no vivo**. O protótipo corrige.
- O redirect pós-criar para o edit está no servidor, como o charter manda. Com turnos editáveis, deixa de ser beco. O protótipo corrige o destino.

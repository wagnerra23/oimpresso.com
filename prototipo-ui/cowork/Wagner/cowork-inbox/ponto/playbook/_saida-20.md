---
sessao: "20"
titulo: "gap.md — Intercorrências — saída da thread"
autor: "[CL]"
criado: 2026-09-28
base: e4289e688
thread: 20-gap-intercorrencias.md
veredito: "entregue — intercorrencias-index-gap.md + intercorrencias-create-gap.md + 2 maps; achado: a copy do Create promete submeter ao RH e o charter proíbe"
---

# _saída 20 · gap.md — Intercorrências

## Entregue

- `memory/requisitos/Ponto/intercorrencias-index-gap.md`
- `memory/requisitos/Ponto/intercorrencias-create-gap.md`
- `memory/requisitos/Ponto/intercorrencias-index.map.json`
- `memory/requisitos/Ponto/intercorrencias-create.map.json`

## Como foi medido

- **Base:** `origin/main` @ `e4289e688`. Protótipo `ponto-telas.jsx` @ `2e3f8adb4e` (2026-09-24).
- **As faixas de linha da thread são de 14/09 e não valem mais.** O build de 24/09 mudou o arquivo; tudo foi re-medido nas duas pontas, com `grep -n`.
- **Lado vivo:** cada linha citada no gap saiu do `.tsx` ou do controller real. O contrato vem do charter e do protótipo, nunca do `.tsx` (§5 2026-06-05).
- **Map:** esqueleto por `gerar-map.mjs` para o scratch, âncoras preenchidas a partir das medições, copiado depois. `design-code-map-check.mjs --check --strict` → rc=0, 0 drift.
- **Decisões:** só as da `ATA-DECISOES-2026-09-14.md`. O que a ata não responde ficou declarado como pendente no gap, sem inventar.
- Não toquei em `Pages/` (o `nao_toca` da thread).

## Decisões da ata aplicadas

- **D-INTERC-ACOES** ratificado: a linha só tem `Ver` nos dois lados. O Pest GUARD (R1) é da thread 27.
- **D-PONTO-DETALHE** (rota própria): o drawer e o form embutido do protótipo viram `Show`, `Create` e `Edit`. Marcado como "protótipo corrige" (R2).
- **D-INTERC-ANEXO** (INCORPORA): o vivo não tem anexo em nenhum dos 4 `.tsx`. É gap real, com a ressalva de PII de [W].

## O que a medição mudou em relação à thread

- As regiões "a nascer" do Index (filtros, coluna Criada, dois estados vazios) **já existem no vivo**. A coluna Criada e a prioridade dentro de Estado são o protótipo que corrige.
- A região de IA do Create segue só no vivo — o protótipo corrige.

## Achado novo

`Create.tsx:244-246` diz *"Eles serão submetidos ao RH para aprovação"*. O charter (`Create.charter.md:59`) diz que salvar não dispara aprovação. É copy que descreve comportamento falso — registrado como gap do vivo.

## Não coberto

`Edit` e `Show` não ganharam gap próprio: o prefixo da thread só lista Index e Create.

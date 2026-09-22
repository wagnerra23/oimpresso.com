# Revisão executável dos fluxos e máquinas, sem baseline local

Em 2026-09-22 foi criado `scripts/governance/revisar-fluxos.mjs`. A máquina descobre os
`FLUXO-*.md`, valida sete dimensões, resolve os executáveis citados, localiza invocador e prova,
executa os donos existentes e consulta o enforcement no GitHub vivo com `--live`.

O bite-test cobre documento completo, dimensão ausente, path fantasma, wiring, prova ligada e
remoto com ponto no nome. O workflow de governança invoca o teste e o `--check`.

Resultado medido: 5 fluxos · 6 máquinas citadas · 0 path fantasma. `FLUXO-DESIGN` e
`FLUXO-MAQUINAS` atendem ao contrato; Cancelamento, Deploy e Venda somam 13 lacunas documentais.
Quatro máquinas citadas ainda não têm prova localizada. A bateria local passou em inventário,
catracas e jornada; `selftest-registry` expôs quatro dívidas anteriores de wiring.

O campo `baseline_usada` é `false`. Sem autenticação ou runtime, o estado é `NÃO MEDIDO`.

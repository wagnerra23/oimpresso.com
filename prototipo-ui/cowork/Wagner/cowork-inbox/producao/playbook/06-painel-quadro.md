---
sessao: "06"
titulo: Painel + Quadro — indicadores-filtro, fila de arte, quadro por setor/máquina, filtro, mover etapa, aviso no app
dono: "[CL]"
base: 0c29a8505b8b
---
# 06 · Painel + Quadro: indicadores-filtro, fila de arte, quadro por setor/máquina, filtro, mover etapa, aviso no app

Painel (indicadores que filtram, alertas com ação, fila de arte, carga de máquina, produtividade por setor) e Quadro (por setor/máquina, filtro compartilhado, "Mover para…" com `producao.etapa.mover`, registro no histórico). OS de CV, Assistência e Oficina entram no quadro sem mudar de dono (D-RELACAO).

**Casos e teste:** `cowork-inbox/producao/Producao.casos.md` — UC-PCV-01, UC-PCV-02, UC-PCV-03, UC-PCV-04, UC-PCV-05, UC-PCV-06, UC-PCV-07. Um `it` por UC, tenant 98×99, discriminante e controle negativo no recibo.

---
sessao: "E02"
titulo: Fundo: custo por máquina ou por pessoa
dono: "[CL]"
forma: Mobile Producao.dc.html · A1, A3, I6
---
# E02 · Fundo: custo por máquina ou por pessoa

Emenda da thread 02.
- `pcp_postos.tipo_custo` enum `maquina|pessoa`, com `custo_hora`. Veículo é posto `maquina` (custo por hora e por km).
- Apontamento de posto `maquina` grava `maquina_id` e `iniciado_por`, **não** ocupa a pessoa; apontamento de posto `pessoa` grava `user_id`. Um usuário pode ter 1 apontamento de pessoa aberto + N de máquina.
- Custo real da OS = Σ(horas máquina × custo_hora máquina) + Σ(horas pessoa × custo_hora pessoa) + terceiros (valor fixo).
- Coluna `chave_idempotencia` única por `business_id` (fila offline, UC-PCV-23).

**Casos e teste:** UC-PCV-31, UC-PCV-32 — `ProducaoFundoContratoTest.php`. Um `it` por UC, tenant 98×99, discriminante e controle negativo no recibo.

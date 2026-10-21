---
sessao: "02"
titulo: Fundo — postos, operações, apontamentos (append-only), programação, turno e custo-hora por empresa
dono: "[CL]"
base: 0c29a8505b8b
---
# 02 · Fundo: postos, operações, apontamentos (append-only), programação, turno e custo-hora por empresa

Tabelas do SPEC do Pcp com o prefixo do módulo: postos (capacidade e custo-hora por empresa), operações, apontamentos **append-only** (trigger, como o Ponto), programação; turno por empresa (padrão 08h–18h). `HasBusinessScope` em tudo. UC-PCV-28 é a prova de isolamento.

**Casos e teste:** `cowork-inbox/producao/Producao.casos.md` — UC-PCV-28. Um `it` por UC, tenant 98×99, discriminante e controle negativo no recibo.

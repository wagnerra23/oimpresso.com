---
sessao: "01"
titulo: ADR — PCP sai do estado de desejo (supersede 0152 no PCP), ADR-PROPOSAL → accepted, Modules/Producao nasce, IProduction sai
dono: "[CL]"
base: 0c29a8505b8b
---
# 01 · ADR: PCP sai do estado de desejo (supersede 0152 no PCP), ADR-PROPOSAL → accepted, Modules/Producao nasce, IProduction sai

Escrever a ADR que tira o PCP do estado de desejo da ADR 0152 (só o ponto do PCP), mover `memory/requisitos/Pcp/ADR-PROPOSAL.md` para `accepted` com D1–D5 respondidos, criar `Modules/Producao` pelo caminho de criar módulo (8 peças) e apagar `Modules/IProduction` **se** o grep de permissões `iproduction.*` e de migrations der vazio — senão, parar e devolver no `_saida`.

**Casos e teste:** `cowork-inbox/producao/Producao.casos.md`.

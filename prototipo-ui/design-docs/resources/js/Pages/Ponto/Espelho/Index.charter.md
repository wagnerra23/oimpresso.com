---
id: resources-js-pages-ponto-espelho-index-charter
page: /ponto/espelho
component: resources/js/Pages/Ponto/Espelho/Index.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [default, loading, vazio, filtrado]
related_prototype: prototipo-ui/cowork/ponto/ponto-page.jsx
contrato: prototipo-ui/contrato/ponto-espelho.contract.json
tier: A
charter_version: 1
---

# Page Charter — /ponto/espelho (Espelho — lista de colaboradores)

> **Origem:** Modules/Ponto/Resources/views/espelho/index.blade.php
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`prototipo-ui/contrato/ponto-espelho.contract.json`), quando existe.
>
> **Persona:** Eliana (RH) escolhendo de quem vai conferir o mês.

---

## Mission (1 frase)

Escolher o colaborador e a competência para abrir o espelho, já mostrando quem tem divergência — a lista existe para priorizar conferência, não só para navegar.

---

## Goals — faz

- Filtros: competência, escala, busca por nome/matrícula, "só com divergência"
- Colunas de leitura rápida: Trabalhado, HE, Saldo BH, divergências do mês
- Paginação server-side; filtro em query string
- Contador de divergências da competência no topo

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO edita** nada — é índice
- ❌ **NÃO calcula** total na tela: os agregados vêm do Service
- ❌ **NÃO mostra coluna de apuração** para quem não controla ponto (mostra —)

---

## Anti-hooks (sinais de drift)

- ⚠️ Filtro em session storage em vez de query string
- ⚠️ Coluna de apuração preenchida para quem não bate ponto — foi defeito real do F1
- ⚠️ Lista sem paginação "porque são poucos"

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing

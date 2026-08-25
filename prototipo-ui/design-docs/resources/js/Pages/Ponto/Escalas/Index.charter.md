---
id: resources-js-pages-ponto-escalas-index-charter
page: /ponto/escalas
component: resources/js/Pages/Ponto/Escalas/Index.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [default, vazio, form]
related_prototype: prototipo-ui/cowork/ponto/ponto-telas.jsx
contrato: —
tier: A
charter_version: 1
---

# Page Charter — /ponto/escalas (Escalas)

> **Origem:** Modules/Ponto/Resources/views/escalas/{index,_form}.blade.php
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`—`), quando existe.
>
> **Persona:** Wagner define jornada; ninguém mais mexe.

---

## Mission (1 frase)

Cadastrar a jornada padrão do business (tipo, carga diária e semanal, banco de horas) que a apuração usa como previsto.

---

## Goals — faz

- Lista com código, nome, tipo (FIXA/FLEXIVEL/12x36/6x1/5x2), cargas, turnos, banco de horas
- Formulário com validação de faixa (60–600 min/dia; 0–3600 min/semana)
- Turnos por dia da semana em leitura
- Remover com aviso de que colaboradores vinculados perdem a referência

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO edita turno por dia** (fase posterior, igual ao Blade)
- ❌ **NÃO valida CLT aqui** — interjornada/intrajornada são apuração
- ❌ **NÃO apaga** escala em uso sem avisar o impacto

---

## Anti-hooks (sinais de drift)

- ⚠️ Carga fora da faixa aceita "porque o cliente pediu"
- ⚠️ Escala apagada silenciosamente com colaboradores vinculados
- ⚠️ Editor de turnos aparecendo sem ADR

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing

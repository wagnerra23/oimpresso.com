---
id: resources-js-pages-ponto-intercorrencias-index-charter
page: /ponto/intercorrencias
component: resources/js/Pages/Ponto/Intercorrencias/Index.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [rascunho, pendente, aprovada, rejeitada, aplicada, cancelada]
related_prototype: prototipo-ui/cowork/ponto/ponto-telas.jsx
contrato: —
tier: A
charter_version: 1
---

# Page Charter — /ponto/intercorrencias (Intercorrências)

> **Origem:** Modules/Ponto/Resources/views/intercorrencias/{index,_form,show}.blade.php
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`—`), quando existe.
>
> **Persona:** Larissa e o técnico registram; Wagner decide.

---

## Mission (1 frase)

Registrar a correção de jornada como pedido rastreável: rascunho → submetido → decidido → aplicado, sem nunca tocar a marcação original.

---

## Goals — faz

- Lista com código, colaborador, tipo, dia/janela, estado, prioridade
- Formulário com os campos do Blade (colaborador, tipo, data, dia todo, janela, justificativa de no mínimo 10 caracteres, prioridade, impacta apuração, descontar BH, anexo)
- Ficha com rastreio: solicitante, aprovador, decisão, motivo de rejeição
- Submeter e cancelar conforme a máquina de estados

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO altera marcação** — a correção entra como novo lançamento na apuração
- ❌ **NÃO edita** intercorrência fora de RASCUNHO
- ❌ **NÃO aceita** registro sem janela e sem "dia todo"
- ❌ **NÃO aprova a si mesma** (decisão é da tela de Aprovações)

---

## Anti-hooks (sinais de drift)

- ⚠️ Campo de hora opcional sem "dia todo" — nasce registro que não existe no domínio
- ⚠️ Edição de aprovada "só pra corrigir texto"
- ⚠️ Justificativa livre sem mínimo — vira "ok" e não serve de prova

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing

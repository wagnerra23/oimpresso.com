---
id: resources-js-pages-ponto-aprovacoes-index-charter
page: /ponto/aprovacoes
component: resources/js/Pages/Ponto/Aprovacoes/Index.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [pendente, vazio, decidido]
related_prototype: prototipo-ui/cowork/ponto/ponto-telas.jsx
contrato: —
tier: A
charter_version: 1
---

# Page Charter — /ponto/aprovacoes (Fila de aprovações)

> **Origem:** Modules/Ponto/Resources/views/aprovacoes/index.blade.php + _tabela.blade.php
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`—`), quando existe.
>
> **Persona:** Wagner decide; Eliana cobra.

---

## Mission (1 frase)

Decidir intercorrências com contexto suficiente (quem, tipo, dia, janela, prioridade) uma a uma ou em lote, sem sair da fila.

---

## Goals — faz

- Filtros de estado e tipo (enums do lang/pt)
- Aprovar / rejeitar por linha, rejeição pede motivo
- Seleção múltipla + barra de lote com **motivo único** obrigatório na rejeição
- Decisão dispara reapuração do dia por job

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO edita** a intercorrência (isso é da tela dela)
- ❌ **NÃO aprova** o que não está PENDENTE
- ❌ **NÃO rejeita sem motivo** — nem em lote
- ❌ **NÃO recalcula** apuração na request da decisão

---

## Anti-hooks (sinais de drift)

- ⚠️ "Aprovar tudo" sem seleção explícita
- ⚠️ Rejeição com motivo opcional
- ⚠️ Estado local da fila divergindo do badge/Painel

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing

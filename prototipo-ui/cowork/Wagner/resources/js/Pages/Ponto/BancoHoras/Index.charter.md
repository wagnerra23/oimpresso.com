---
id: resources-js-pages-ponto-bancohoras-index-charter
page: /ponto/banco-horas
component: resources/js/Pages/Ponto/BancoHoras/Index.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [credito, debito, zerado, vazio]
related_prototype: prototipo-ui/cowork/ponto/ponto-telas.jsx
contrato: —
tier: A
charter_version: 1
---

# Page Charter — /ponto/banco-horas (Banco de horas)

> **Origem:** Modules/Ponto/Resources/views/banco-horas/{index,show}.blade.php
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`—`), quando existe.
>
> **Persona:** Eliana acerta saldo; Wagner autoriza ajuste.

---

## Mission (1 frase)

Mostrar saldo por colaborador e o extrato que o explica, permitindo ajuste manual que entra como lançamento — nunca como correção do passado.

---

## Goals — faz

- Totais do business (crédito, débito, quantos com cada) + teto/piso do config
- Saldos por colaborador com última movimentação
- Extrato: data, referência, origem (APURACAO/AJUSTE_MANUAL/FECHAMENTO), minutos assinados, observação
- Ajuste manual com minutos e **observação obrigatória**

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO edita** movimento existente — ledger append-only
- ❌ **NÃO zera** saldo por botão: compensação é lançamento
- ❌ **NÃO aceita** ajuste sem observação
- ❌ **NÃO ignora** teto/piso do `config.banco_horas` sem aviso

---

## Anti-hooks (sinais de drift)

- ⚠️ Botão "recalcular saldo" que sobrescreve o ledger
- ⚠️ Ajuste sem autor no extrato
- ⚠️ Saldo exibido diferente da soma do extrato

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing

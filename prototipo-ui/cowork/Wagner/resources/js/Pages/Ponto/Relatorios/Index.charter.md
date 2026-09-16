---
id: resources-js-pages-ponto-relatorios-index-charter
page: /ponto/relatorios
component: resources/js/Pages/Ponto/Relatorios/Index.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [catalogo, wizard, fila]
related_prototype: prototipo-ui/cowork/ponto/ponto-telas.jsx
contrato: —
tier: A
charter_version: 1
---

# Page Charter — /ponto/relatorios (Relatórios)

> **Origem:** Modules/Ponto/Resources/views/relatorios/index.blade.php
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`—`), quando existe.
>
> **Persona:** Eliana gera o legal; Wagner pede o gerencial.

---

## Mission (1 frase)

Escolher o relatório, declarar os filtros e gerar — dizendo com honestidade o que ainda não existe em `ReportService`.

---

## Goals — faz

- Catálogo com a flag `disponivel` do controller
- Wizard: competência, colaborador (ou todos), formato, incluir marcações anuladas
- Formato TXT posicional travado nos legais (AFD/AFDT/AEJ) com o encoding do config
- Fila de pedidos marcando GERADO × NAO_IMPLEMENTADO

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO promete** relatório que retorna 501 no vivo
- ❌ **NÃO gera** arquivo legal fora do layout do Anexo I
- ❌ **NÃO exporta** PDF/CSV para os legais (é texto posicional)

---

## Anti-hooks (sinais de drift)

- ⚠️ Botão "Gerar" que baixa arquivo vazio
- ⚠️ Catálogo com item novo sem implementação e sem marcação
- ⚠️ Filtro de período diferente do da competência do módulo

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing

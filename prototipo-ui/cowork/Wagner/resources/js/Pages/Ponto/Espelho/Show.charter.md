---
id: resources-js-pages-ponto-espelho-show-charter
page: /ponto/espelho/{colaborador}
component: resources/js/Pages/Ponto/Espelho/Show.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [consolidado, divergencia, fechado, sem-apuracao]
related_prototype: prototipo-ui/cowork/ponto/ponto-page.jsx
contrato: prototipo-ui/contrato/ponto-espelho.contract.json
tier: A
charter_version: 1
---

# Page Charter — /ponto/espelho/{colaborador} (Espelho individual)

> **Origem:** Modules/Ponto/Resources/views/espelho/show.blade.php + reports/espelho-pdf.blade.php
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`prototipo-ui/contrato/ponto-espelho.contract.json`), quando existe.
>
> **Persona:** Eliana confere; o colaborador assina a folha impressa.

---

## Mission (1 frase)

Ser o documento do mês: cabeçalho legal, seis totalizadores, apuração dia a dia com as marcações, e a folha imprimível que o colaborador assina.

---

## Goals — faz

- Cabeçalho com matrícula, CPF, PIS, escala, carga diária, admissão/desligamento
- Seis totalizadores (trabalhado, atraso, falta, HE, BH+ e BH−) do mesmo cálculo do Blade
- Tabela dia-a-dia: previsto, realizado, marcações, atraso, HE, BH líquido, estado
- Grade do mês como visão alternativa (falta/divergência/HE de relance)
- Drawer do dia com NSR, origem, REP e hash + **anulação append-only**
- Folha de impressão (15 colunas + totais + DSR + assinaturas + Art. 85)

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO edita marcação** — nunca, em nenhum estado
- ❌ **NÃO oculta** marcação anulada: ela permanece, sinalizada
- ❌ **NÃO gera** AFD/AEJ (é Relatórios)
- ❌ **NÃO recalcula** o dia na tela: reapurar é job

---

## Anti-hooks (sinais de drift)

- ⚠️ Botão "editar hora" em qualquer lugar — viola Art. 85
- ⚠️ Anular disponível em competência fechada sem trilha
- ⚠️ Divergência sem explicação do motivo (ímpar? fora da escala?)
- ⚠️ Folha impressa que perde a marca de divergência

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing

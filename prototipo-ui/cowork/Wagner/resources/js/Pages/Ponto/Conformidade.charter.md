---
id: resources-js-pages-ponto-conformidade-charter
page: /ponto/conformidade
component: resources/js/Pages/Ponto/Conformidade.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [com-apontamento, limpo]
related_prototype: prototipo-ui/cowork/ponto/ponto-fechamento.jsx
contrato: prototipo-ui/contrato/ponto-fechamento.contract.json
tier: A
charter_version: 1
---

# Page Charter — /ponto/conformidade (Conformidade CLT)

> **Origem:** — (tela nova, não existe no Blade)
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`prototipo-ui/contrato/ponto-fechamento.contract.json`), quando existe.
>
> **Persona:** Wagner (risco) e Eliana (correção) antes de fechar.

---

## Mission (1 frase)

Transformar a lei em lista de casos: seis verificações apuradas da competência, cada apontamento com o artigo, o apurado, o limite e o atalho pro espelho.

---

## Goals — faz

- Jornada sem fechamento (marcação ímpar/falta sem justificativa)
- Interjornada abaixo de 11h (Art. 66) e intrajornada abaixo de 60 min (Art. 71)
- HE acima de 2h/dia (Art. 59) e NSR fora de sequência (Anexo I)
- Colaborador ativo sem PIS (bloqueia AFD e S-2230)
- KPI por regra clicável + tabela caso a caso

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO corrige** nada — leva pra onde se corrige
- ❌ **NÃO mistura** grau: regra dura (66/71/NSR) é separada de conferência
- ❌ **NÃO mostra** verificação sem caso possível (zero permanente é UI morta)

---

## Anti-hooks (sinais de drift)

- ⚠️ Apontamento sem artigo citado
- ⚠️ Regra cujo limite não vem do `config`
- ⚠️ Contagem diferente da usada pela pré-checagem do Fechamento

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing

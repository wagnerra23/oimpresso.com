---
id: resources-js-pages-ponto-importacoes-index-charter
page: /ponto/importacoes
component: resources/js/Pages/Ponto/Importacoes/Index.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [pendente, processando, concluida, concluida_com_erros, falhou]
related_prototype: prototipo-ui/cowork/ponto/ponto-telas.jsx
contrato: —
tier: A
charter_version: 1
---

# Page Charter — /ponto/importacoes (Importações AFD/AFDT)

> **Origem:** Modules/Ponto/Resources/views/importacoes/{index,create,show}.blade.php
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`—`), quando existe.
>
> **Persona:** Eliana sobe o arquivo do relógio; Wagner cobra o resultado.

---

## Mission (1 frase)

Subir AFD/AFDT e acompanhar o processamento com diagnóstico honesto: quantas linhas entraram, quantas falharam e por quê.

---

## Goals — faz

- Histórico com tipo, tamanho, estado, linhas processadas, usuário e data
- Upload com tipo (AFD/AFDT) e aviso de processamento assíncrono
- Detalhe: hash SHA-256, marcos de tempo, log do parser, amostra de erros (linha/NSR/tipo/mensagem), progresso
- Rejeitar arquivo duplicado por hash

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO processa** na request — é `ProcessarImportacaoAfdJob`
- ❌ **NÃO reprocessa** por cima: reimportar é nova importação
- ❌ **NÃO esconde** erro: linha rejeitada aparece com motivo
- ❌ **NÃO aceita** encoding fora do configurado sem dizer

---

## Anti-hooks (sinais de drift)

- ⚠️ Barra de progresso sem fonte (progresso inventado)
- ⚠️ "Importado com sucesso" com linhas_erro maior que zero
- ⚠️ Botão reprocessar que apaga marcações anteriores

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing

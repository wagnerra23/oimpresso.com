---
id: resources-js-pages-ponto-configuracoes-index-charter
page: /ponto/configuracoes
component: resources/js/Pages/Ponto/Configuracoes/Index.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [leitura, reps]
related_prototype: prototipo-ui/cowork/ponto/ponto-telas.jsx
contrato: —
tier: A
charter_version: 1
---

# Page Charter — /ponto/configuracoes (Configurações e REPs)

> **Origem:** Modules/Ponto/Resources/views/configuracoes/{index,reps}.blade.php
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`—`), quando existe.
>
> **Persona:** Wagner audita a regra; ninguém edita pela UI hoje.

---

## Mission (1 frase)

Mostrar, com o artigo da lei ao lado, as regras que a apuração aplica — e permitir o único cadastro que é de tela: os dispositivos REP.

---

## Goals — faz

- Blocos CLT, banco de horas, REP/imutabilidade, AFD, eSocial e flags de IA, em somente-leitura
- Cada valor com a base legal (Art. 58 §1º, 66, 71, 73 §1º, 59, 7º XVI CF/88, Lei 605/49)
- Cadastro de REP: tipo, identificador de 17 caracteres (Anexo I), descrição, local, CNPJ

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO edita** config pela UI (é arquivo + `config:clear`)
- ❌ **NÃO esconde** que o certificado ICP não está configurado
- ❌ **NÃO aceita** identificador fora dos 17 caracteres

---

## Anti-hooks (sinais de drift)

- ⚠️ Campo editável aparecendo sem ADR (config vira dado sem migração)
- ⚠️ Valor exibido sem a lei — número sem base não se audita
- ⚠️ Flags de IA ligadas por default

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing

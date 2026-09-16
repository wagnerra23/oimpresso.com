---
id: resources-js-pages-ponto-colaboradores-index-charter
page: /ponto/colaboradores
component: resources/js/Pages/Ponto/Colaboradores/Index.tsx
owner: wagner
status: draft
status_detail: F1 protótipo — aguarda [W]
last_validated: "2026-08-20"
parent_module: Ponto
states: [default, vazio, filtrado, form]
related_prototype: prototipo-ui/cowork/ponto/ponto-telas.jsx
contrato: —
tier: A
charter_version: 1
---

# Page Charter — /ponto/colaboradores (Colaboradores do ponto)

> **Origem:** Modules/Ponto/Resources/views/colaboradores/{index,edit}.blade.php
> Importado no F1 [CC] em 2026-08-20 para `prototipo-ui/cowork/ponto/`; a copy literal e a ordem das
> seções ficam no contrato (`—`), quando existe.
>
> **Persona:** Eliana configura; o RH mantém o cadastro no HRM.

---

## Mission (1 frase)

Configurar só o que é de ponto (matrícula, CPF/PIS, escala, flags, admissão/desligamento) sobre o cadastro que vive no Essentials/HRM.

---

## Goals — faz

- Busca por nome/matrícula/CPF + filtros por escala e situação (ativos, quem controla ponto, **sem PIS**, desligados)
- Colunas de operação: último ponto e saldo BH
- Formulário de configuração de ponto + painel "dados do HRM" somente-leitura
- Sinalizar PIS ausente (é o que rejeita a linha no AFD)

## Non-Goals — NÃO faz

> Anti-alucinação. Cada item vira GUARD test.

- ❌ **NÃO cria nem edita** usuário — isso é HRM
- ❌ **NÃO apaga** colaborador: desligamento é data
- ❌ **NÃO inventa** matrícula automática sem regra do RH

---

## Anti-hooks (sinais de drift)

- ⚠️ Campo de nome/e-mail editável aqui — drift de responsabilidade
- ⚠️ Filtro "sem PIS" existindo sem caso real (UI morta)
- ⚠️ Salvar escala sem checar vigência da apuração já processada

---

## Invariantes do módulo (valem aqui também)

- Marcação é **append-only** (Portaria MTP 671/2021 Art. 85): correção é anulação + nova marcação
- `business_id` escopa toda query (ADR 0093, Tier 0)
- Nenhum número inventado: sem dado, empty state que explica por quê
- Nenhuma cor fora dos tokens do DS vivo; PT-BR em toda UI cliente-facing

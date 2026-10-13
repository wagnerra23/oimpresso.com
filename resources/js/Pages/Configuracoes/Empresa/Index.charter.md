---
id: resources-js-pages-configuracoes-empresa-index-charter
page: /business/settings
component: resources/js/Pages/Configuracoes/Empresa/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/configuracoes-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-13"
parent_module: Configuracoes
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
related_runbook: memory/requisitos/Configuracoes/RUNBOOK-empresa.md
---

# Page Charter — /business/settings (DRAFT)

> **Status:** draft. Thread `sistema/playbook/08`, F3-1. `GET /business/settings` responde Inertia só com a flag
> `useV2ConfiguracoesEmpresa` ligada para o negócio; desligada, ou com `?classico=1`, a Blade `business/settings` segue.
> Vira `live` no cutover, decisão [W]. Casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Configurar o negócio — identificação, moeda, datas, precisões, logo e códigos na nota — sem perder nada do que o
salvar da Blade grava.

## Goals

- Trilho de abas do protótipo (Empresa · Operação · Sistema · Integrações). Nesta fase só **Negócio** é da tela nova;
  as outras abrem a Blade por `?classico=1`.
- Aba Negócio com os campos da `settings_business` da Blade, incluindo o logo (multipart) e "permitir exportar listas".
- Salvar pelo `POST /business/update` de sempre, devolvendo o corpo inteiro que o servidor montou.

## Non-Goals

- ❌ Mandar só os campos da aba: o salvar zera o que falta (`EmpresaBaselineTest`).
- ❌ Levar senha ou segredo de e-mail/SMS para as props: o corpo não os traz e o salvar os preserva.
- ❌ Formatar número com o separador da moeda: o corpo usa `1234,5`, que o `num_uf` lê igual em qualquer moeda.
- ❌ Mudar `postBusinessSettings`.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Só o negócio da sessão | `BusinessController::getBusinessSettings` |
| R2 | Flag desligada ou `?classico=1` devolvem a Blade | `FLAG_EMPRESA_V2` |
| R3 | O corpo cobre tudo que o salvar sobrescreve quando falta | `BusinessController::corpoDoSalvar` |
| R4 | Sem `business_settings.access`, 403 | as duas ações |

## Refs

RUNBOOK: [`RUNBOOK-empresa.md`](../../../../../memory/requisitos/Configuracoes/RUNBOOK-empresa.md) · paridade: [`empresa-parity.md`](../../../../../memory/requisitos/Configuracoes/empresa-parity.md) · ADR 0093 · ADR 0104 · ADR 0358.

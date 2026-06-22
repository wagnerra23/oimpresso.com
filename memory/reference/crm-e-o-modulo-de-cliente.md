# Cliente (cadastro/contatos) **≠** CRM (pipeline) — desambiguação

> 🪪 **DECISÃO Wagner 2026-06-22 — "contacts não é o crm".** O **cadastro de Cliente / contatos** (a tela viva da Larissa) é uma coisa **separada** do **pipeline CRM** (Leads/Proposals/Campaigns/Schedules/CallLogs — herdado do UltimatePOS). Antes ambos viviam misturados em `Modules/Crm` e o canon dizia "Crm = Cliente"; **isso mudou**:
> - **Cadastro de Cliente** → canon em **`memory/requisitos/Cliente/`** (SPEC.md machine-read pelo `anchor-lint`). Mantido e evoluído.
> - **Pipeline CRM** → **em depreciação** (plano à parte via agente `deprecar-modulo`). Era o que estava silenciado/incomodando.
> - **Código:** ainda fisicamente em `Modules/Crm/` (rename de módulo NÃO feito — fora de escopo por ora). `module.json active: 1` — app intacto pra biz=4.

> **Histórico:** Wagner 2026-06-01 *"por no crm? era para ser no contatos do cliente. o crm está me incomodando e sempre confundindo."* → silenciamento 2026-06-08 → separação 2026-06-22. A decisão "permanente pendente" daquele banner (separar/deprecar o pipeline) foi **tomada**.

## A regra (decore isto)

- **"Cliente" / "contatos do cliente"** = o **cadastro** (PF/PJ, drawer 760px, dados fiscais BR, endereços). É o que se mantém e evolui.
- **"CRM"** = o **pipeline pré-venda** (leads, funil life_stage, propostas, campanhas, follow-ups, call logs). É o que está **em depreciação**.
- **NÃO são a mesma coisa** (correção de 2026-06-22 — o canon antigo dizia que eram).
- **Código:** ambos ainda em `Modules/Crm/` (legacy UPOS). Os controllers do cadastro (`ClienteAutosaveController`, `ClienteLookupController` CEP/CNPJ, `ClienteIaController`, `ClienteAuditoriaController` LGPD, `ClienteOssDataController`, `ClienteVeiculosController`, `ContactAddressController`) **não** fazem parte do pipeline. **Não há um módulo `Cliente` separado** (ainda) — o código fica em `Modules/Crm`.
- **Dados** do cadastro são core `App\`: `app/Contact.php` + `app/ContactAddress.php` + tabelas `contacts`/`contact_addresses`.

## Onde está o conhecimento canônico

| Tema | Canônico | Nota |
|---|---|---|
| **Cadastro de Cliente** (requisitos) | **`memory/requisitos/Cliente/`** — `SPEC.md` (US 063–078, machine-read) + `audits/` | movido de `Crm/SPEC-us-063-078.md` em 2026-06-22 |
| Cadastro — docs ainda em `Crm/` (a mover) | `memory/requisitos/Crm/` — BRIEFING, ARCHITECTURE, RUNBOOK-cliente-*, UI-CATALOG, `_legado-fullpage/` | migram pra `Cliente/` na execução do plano de separação |
| **Pipeline CRM** (em depreciação) | `memory/requisitos/Crm/SPEC.md` (US 001–062) | TARGET da depreciação — ver plano |
| Código (cadastro + pipeline) | `Modules/Crm/` | rename de módulo não feito; sem módulo `Cliente` separado |
| Modelo de dados (cadastro) | `app/Contact.php`, `app/ContactAddress.php` | core `App\`, não um módulo |

## Por que confunde (e como falar)

- O prefixo de US **"US-CRM-078"** é só o **ID herdado** — a feature é "endereços do cliente" (cadastro), não pipeline.
- Ao falar/escrever com o Wagner: **"Cliente / contatos do cliente"** = cadastro; **"CRM"** = pipeline (em depreciação). **São coisas separadas.** Usar `Modules/Crm` só quando referir ao *path* do código.

## Refs

- `Modules/Crm/SCOPE.md` · [`memory/requisitos/Cliente/SPEC.md`](../requisitos/Cliente/SPEC.md) · [relatório de alinhamento](../requisitos/Cliente/audits/ALINHAMENTO-cliente-2026-06-22.md) · ADR 0179 (drawer 760px) · ADR 0273 (âncoras) · [cliente-rotalivre.md](cliente-rotalivre.md)

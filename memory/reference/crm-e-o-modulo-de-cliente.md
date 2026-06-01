# `Modules/Crm` **É** o módulo de Cliente (contatos do cliente)

> **Origem:** Wagner 2026-06-01 — *"por no crm? era para ser no contatos do cliente. o crm está me incomodando e sempre confundindo."* Desambiguação canônica pra parar a confusão recorrente (humano **e** agente).

## A regra (decore isto)

No oimpresso, **"Cliente" / "contatos do cliente" = `Modules/Crm`**. O UltimatePOS herdou o nome **"Crm"**, mas o módulo **É** o de Cliente. **NÃO existe `Modules/Cliente`.**

- Todos os controllers de cliente vivem em `Modules/Crm`: `ClienteAutosaveController`, `ClienteLookupController` (CEP/CNPJ), `ClienteIaController`, `ClienteAuditoriaController` (LGPD), `ClienteOssDataController`, `ClienteVeiculosController`, etc.
- A tela de cliente é o **drawer 760px** (ADR 0179) com abas cadastrais — incl. aba **Endereço**.
- Os **dados** são core `App\` (não um módulo): `app/Contact.php` (= o "contato"/cliente) + `app/ContactAddress.php` (múltiplos endereços, **US-CRM-078**) + a tabela `contacts` (+ `contact_addresses`).

## Onde está o conhecimento canônico

| Tema | Canônico | Evitar |
|---|---|---|
| Requisitos do Cliente | **`memory/requisitos/Crm/`** (BRIEFING, ARCHITECTURE, RUNBOOK-cliente-*, adr/) | `memory/requisitos/Cliente/` (**legado/stale** — RUNBOOKs do Show full-page pré-drawer) |
| Módulo (código) | `Modules/Crm/` | — |
| Modelo de dados | `app/Contact.php`, `app/ContactAddress.php` | — |

## Por que confunde (e como falar)

- O prefixo de US **"US-CRM-078"** é só o ID herdado — a feature é "**endereços do cliente**".
- Ao falar/escrever com o Wagner: dizer **"Cliente / contatos do cliente"**. Usar **"Crm"** só quando referir ao *path* do módulo (`Modules/Crm`). Não tratar "Cliente" e "CRM" como coisas separadas — são o mesmo.

## Refs

- `Modules/Crm/SCOPE.md` (purpose atualizado) · `memory/requisitos/Crm/BRIEFING.md` · ADR 0179 (drawer 760px) · US-CRM-078 (múltiplos endereços) · [cliente-rotalivre.md](cliente-rotalivre.md)

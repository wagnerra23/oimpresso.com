---
id: requisitos-consulta-os-runbook-index
title: "RUNBOOK — ConsultaOs (`/consulta-os`, portal público de OS)"
module: ConsultaOs
tela: ConsultaOs/Index
owner: W
status: rascunho
last_validated: "2026-11-17"
preconditions:
  - "Módulo ConsultaOs registrado (sem ele a rota `consulta-os.index` não existe e o `/repair-status` responde 404)"
  - "Modules/Repair instalado — a busca lê `repair_job_sheets`, `repair_statuses`, `brands`, `repair_device_models`, `categories`, `transactions`, `contacts` e `activity_log`"
  - "Rota pública: sem login, sem sessão e sem `business_id` (escape comentado no código, ADR 0093)"
preconditions_short: módulo registrado, Repair instalado, rota pública sem sessão
---

# RUNBOOK — ConsultaOs (`/consulta-os`)

> **F1 PLAN do MWART (ADR 0104)** da tela já migrada. A tela é Inertia desde a criação do
> módulo; a Blade do portal antigo (`/repair-status`) saiu no cutover de 2026-10-27. Este
> RUNBOOK existe para que mudanças futuras no `.tsx` (captcha, identificar a empresa) partam
> do fluxo medido, e porque o hook `block-mwart-violation` barra editar
> `resources/js/Pages/ConsultaOs/Index.tsx` sem ele.
>
> Trio da tela: [`Index.charter.md`](../../../resources/js/Pages/ConsultaOs/Index.charter.md) (lei) ·
> [`Index.casos.md`](../../../resources/js/Pages/ConsultaOs/Index.casos.md) (contrato, UC-COS-*) ·
> [`SPEC.md`](SPEC.md) (US-CONSULTA-001 a 004). Medido no código em 2026-11-17 (`main` `f4c834478f`).

## Status

| Item | Estado |
|---|---|
| Blade legacy | não há para esta tela; o portal antigo do Repair (`customer_repair/index.blade.php` + `POST /post-repair-status`) saiu em 2026-10-27 (#10072) |
| Rotas | `GET /consulta-os` (`consulta-os.index`) e `GET /consulta-os/buscar` (`consulta-os.buscar`), ambas `throttle:30,1`, sem auth (`Modules/ConsultaOs/Routes/web.php`) |
| Porta antiga | `GET /repair-status` → 302 para `/consulta-os` (`Modules/Repair/Http/Controllers/CustomerRepairStatusController::index`) |
| `.tsx` | `resources/js/Pages/ConsultaOs/Index.tsx` + `_components/OsLookupForm.tsx` + `_components/OsResultCard.tsx` |
| Flag | nenhuma |
| Charter | `status: draft` — aprovar Non-Goals/Anti-hooks é do Wagner |

## Fluxo de ponta a ponta

### A. O cliente busca a OS

```
Cliente (navegador, sem login)
 1. GET /consulta-os ─────────────► ConsultaOsController::index
                                     Inertia::render('ConsultaOs/Index', { buscaPorCelular })
                                     buscaPorCelular = 'mobile_num' ∈ RepairConsultaOsRepository::tiposHabilitados()
 2. Index.tsx (useState) ─ OsLookupForm: tipo (OS | venda | celular*) + número + série opcional
 3. fetch GET /consulta-os/buscar?tipo=&numero=&serie=   (Accept: application/json)
                                   ► throttle:30,1 (por IP)
                                   ► ConsultaPublicaRequest
                                       tipo ∈ tiposHabilitados()   (celular só com repair.enable_repair_check_using_mobile_num)
                                       numero obrigatório, ≤20, [A-Za-z0-9/.-] começando por letra/dígito
                                       serie opcional, ≤50, mesmo formato
                                       falhou → 422
                                   ► ConsultaOsController::buscar
                                   ► ConsultaOsService::buscar          span consultaos.busca_publica {tipo}
                                   ► RepairConsultaOsRepository::buscar span consultaos.repository.lookup {tipo}
                                       revalida tipo + número (sem isso devolve [] sem consultar)
                                       JobSheet::query() + joins status/marca/modelo/aparelho
                                         job_sheet_no = número | EXISTS transactions.invoice_no | EXISTS contacts.mobile
                                         + serial_no quando a série vem
                                       ORDER BY id DESC, LIMIT 20 (ConsultaOsRepositoryInterface::LIMITE)
                                       publico(): whitelist campo a campo + atividades (activity_log, até 50)
                                   ◄ Service: [] → {found:false, reason:not_found}
                                   ◄ Controller: grava 1 linha de auditoria redigida (auditarConsulta) e responde
                                       200 {found:true, ordens[]} · 404 {found:false} · 500 {found:false} em exceção
 4. Index.tsx: 200 → OsResultCard por OS · 404 e 422 → tela "não encontrada" · outro erro → "Não foi possível conectar"
```

\* O tipo celular só aparece no formulário quando `buscaPorCelular` é verdadeiro.

**Payload de cada OS** (`RepairConsultaOsRepository::publico`): `numero`, `marca`, `aparelho`,
`modelo`, `serie`, `status {nome, cor}`, `previsao_entrega`, `atividades[] {data, acao, por, nota,
conclusao_de, conclusao_para}`. Nada de cliente (nome, CPF/CNPJ, endereço, e-mail), custo, senha do
aparelho, defeitos, checklist, notas internas ou `business_id` (UC-COS-04).

**Auditoria** (`ConsultaOsController::auditarConsulta`): `numero_redacted` (PiiRedactor), `tipo`,
`resultado` (`found` | `not_found` | `erro`), `ip_truncado` (/24 IPv4, /48 IPv6), `user_agent`
(80 caracteres), `timestamp`. Vai para o canal `config('logging.default')`. Nenhum dado da OS
encontrada entra no log.

### B. A empresa muda o status e o cliente é avisado

```
Usuário do Repair edita a OS (JobSheetController::update) e troca o status_id
 ► event(RepairStatusChanged {repair, …, avisoTemplate})     JobSheetController.php ~856
     avisoTemplate = repair_statuses.whatsapp_template_aviso do status novo ('ready' | 'waiting_parts' | null)
 ► Modules/Whatsapp: Event::listen em WhatsappServiceProvider → NotifyRepairCustomer::handleEvent
     marca vazia ou outra → não avisa (o nome do status não decide — decisão [W] 2026-10-21)
     sem business_id/contact_id → não avisa
     texto "Sua OS nº X está pronta para retirada." / "...está aguardando peça..."
 ► NotificadorClienteWhatsapp::notificar(business_id, contact_id, 'repair_status', texto)
     cliente da mesma empresa · opt-out de WhatsApp · celular · canal no ar — falta de pré-condição sai em log
 ► na próxima busca (fluxo A) o portal já mostra o status novo
```

**Limite aberto (UC-COS-14):** a transição pela FSM do Repair (`RepairFsmActionController::execute`)
não dispara `RepairStatusChanged` nem muda o `status_id` que o portal lê. O cliente não é avisado e
o portal mostra o status anterior. Precisa de US própria (evento genérico de transição da FSM) — é
trabalho do Repair, não deste módulo.

## F1 PLAN — o que pode mudar nesta tela

| Mudança | Quem decide | Onde mexe |
|---|---|---|
| Identificar a empresa do cliente (hoje a busca procura em todas; UC-COS-09 trava o estado atual) | [W] — muda a URL do portal | rota + `RepairConsultaOsRepository` + `Index.tsx` + inverter o assert do UC-COS-09 |
| Captcha (resto da US-CONSULTA-001) | [W] escolhe o serviço | `ConsultaPublicaRequest` + `OsLookupForm.tsx` |
| Busca por celular ligada por padrão | [W] | `Modules/Repair/Config/config.php` (chave `enable_repair_check_using_mobile_num`, do Repair) |
| Expor `nota` e `por` (funcionário) nas atividades | [W] | `RepairConsultaOsRepository::atividades` + `OsResultCard.tsx` |
| GET → POST na busca | [W] | rota + `Index.tsx` + UC-COS-06 |

## F2 BASELINE

Antes de mexer no controller, no request ou no repositório, rodar no CI (ou no CT 100, nunca local):

- `tests/Feature/ConsultaOs/ConsultaOsIndexContratoTest.php` — UC-COS-01..11 (lane sqlite, `.github/ci-sqlite-pest.list`).
- `Modules/Repair/Tests/Feature/ClienteAcompanhaStatusContratoTest.php` — UC-COS-12, 13, 31, 32, 33 (`verticais-pest.yml`, MySQL).
- `Modules/Repair/Tests/Feature/RepairFsmActionControllerTest.php` — UC-COS-14.
- `Modules/ConsultaOs/Tests/Feature/` — scaffold e isolamento (`modules-pest.yml`).

## F3 CODE — regras para editar `Index.tsx`

- A tela é pública: sem `AppShellV2`/Sidebar, layout centralizado, tokens do DS (charter Non-Goals).
- Estado em `useState` + `fetch` para `/consulta-os/buscar`; não há partial reload (SPEC D6.a). Não passar dado de OS por prop do Inertia.
- O tipo de busca vem da prop `buscaPorCelular`; não reintroduzir filtro por estágio (o status do Repair é livre por empresa).
- Nunca renderizar campo fora da whitelist do repositório; campo novo no card exige mudar o repositório e o UC-COS-04 no mesmo PR.

## F4 QA

- Os testes do F2 verdes no CI do PR.
- Smoke em produção (biz=1, nunca biz=4): `/consulta-os` abre sem login; uma OS conhecida aparece; um número inexistente mostra "não encontrada"; mobile e 1280px (pendência do charter).
- `/repair-status` responde 302 para `/consulta-os`.

## F5 CUTOVER

Feito em 2026-10-27 (#10072): o portal antigo do Repair saiu e o ConsultaOs é a única consulta pública
de OS. US-CONSULTA-002 (canary 7d em ROTA LIVRE) segue pendente como rollout operacional.

## Riscos

- **Tier 0:** a busca cruza empresas (pendência [W], UC-COS-09). Uma OS de empresa sem o pacote ConsultaOs também aparece, porque o pacote só controla o menu.
- **Enumeração:** o nº da OS é sequencial; a defesa é o throttle 30/min por IP e o formato restrito, sem captcha.
- **Aviso fora do portal:** status mudado pela FSM não avisa e não aparece (UC-COS-14).

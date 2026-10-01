---
sessao: "05"
titulo: Comunicador e Configurações — recibo do 1º PR (Comunicador)
dono: "[CL]"
data: "2026-10-01"
base: "origin/main 9c3019a4e"
---

# _saida-05 — Comunicador → Inertia (PR 1 de 2)

A ficha pede 2 PRs. Este é o **1º: Comunicador**. Configurações (`SuperadminSettingsController:110`)
fica para o 2º PR, por isso o placar segue `pendente` nesta thread até ele entrar.

## Entregue

- `CommunicatorController@index` → `Inertia::render('superadmin/Comunicador/Index')`, com
  `negocios` e `historico` em `Inertia::defer`.
- `@send` ganhou `validate()` e passa a **escapar** o corpo (`nl2br(e(...))`): a tela nova manda
  texto puro e o e-mail `emails.plain_html` imprime o corpo cru.
- Trio novo: `Comunicador/Index.tsx` + `.charter.md` + `.casos.md` (UC-SACOM-01..06), teste
  `Modules/Superadmin/Tests/Feature/SuperadminComunicadorContratoTest.php` (cita os 6 UCs) e a
  linha dele na lane `verticais-pest.yml` (sem ela o teste nunca roda, LC-13).
- RUNBOOK F1: `memory/requisitos/Superadmin/RUNBOOK-comunicador.md`.

## Provas do json

| prova | estado |
|---|---|
| `CommunicatorController.php` contém `Inertia::render(` | ✅ |
| `SuperadminSettingsController.php` contém `Inertia::render(` | ❌ — 2º PR |

## Pendente / decisões [W]

- **Configurações** (2º PR desta thread): `superadmin_settings.edit` é um formulário grande
  (SMTP, gateways, cron); não cabia junto em ≤300 linhas.
- **Grupos de destinatário do protótipo** (ativas · trial · vencidas · por pacote), agendamento,
  "enviar teste para mim" e % de abertura: nenhum tem backend. Ficaram como divergência declarada
  no charter. Segmentar por status de assinatura é regra nova, decisão [W].
- `GET /superadmin/communicator/get-history` (DataTables) ficou sem consumidor; não removido.
- `SPEC.md` US-SUPER-004 ainda cita a view Blade em `**Implementado em:**` (a view continua no
  disco); atualizar a âncora quando a Blade for apagada.
- Medida/alvo da tela: **NÃO MEDI** (a ficha não pede).

## PR

Ver o PR `feat(superadmin): Comunicador em Inertia — thread 05 (1/2)`.

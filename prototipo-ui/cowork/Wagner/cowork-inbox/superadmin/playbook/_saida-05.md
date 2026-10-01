---
sessao: "05"
titulo: Comunicador e Configurações — recibo dos 2 PRs (Comunicador + Configurações)
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
- Lane `verticais-pest` (run 36809972026, dispatch no branch): **6 de 6** UC-SACOM passaram.
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

#8342 — `feat(superadmin): Comunicador em Inertia — thread 05 (1/2)`.

---

# Parte 2 — Configurações → Inertia (PR 2 de 2)

Base: `origin/main cd650b65c`. Com este PR as duas provas do json passam e o placar fecha a
thread em `feito` (`entregue 1 de 1`, conferido com `placar.mjs --thread 05`).

## Entregue

- `SuperadminSettingsController@edit` → `Inertia::render('superadmin/Configuracoes/Index')`, com
  uma prop `config` em `Inertia::defer` (valores, segredos, opções, comando do cron, versão).
- **Segredos mascarados.** As 14 chaves de `SuperadminSettingsController::SEGREDOS` (senha SMTP,
  chaves secretas e senhas dos gateways, secret do Pusher, token do Dropbox, etc.) não saem do
  servidor: a tela recebe só `segredos[chave] = definido sim/não` e mostra "•••••••• definido —
  deixe vazio para manter". O Blade imprimia esses valores em claro num `<input type=text>`.
- `@update` passa a **ignorar segredo vazio** (antes, campo vazio gravava `KEY=""` e apagava a
  senha) e tira `\r`, `\n` e `"` dos valores antes de escrever o `.env` (quebra de linha abria
  uma linha nova com qualquer chave).
- A tabela `system` é lida por **lista fechada** (`SISTEMA`), não inteira.
- Trio novo: `Configuracoes/Index.tsx` + `.charter.md` + `.casos.md` (UC-SACFG-01..06); lei IT2
  (charter com `.tsx` irmão) conferida com `integrity-check.mjs` — PASS.
- Teste `Modules/Superadmin/Tests/Feature/SuperadminConfiguracoesContratoTest.php` (cita os 6 UCs),
  ligado na lane `verticais-pest.yml`. O `update()` grava num `.env` temporário (subclasse com
  `envPath()`), nunca no `.env` real do runner; os checkboxes da `system` rodam em transação revertida.
- RUNBOOK F1: `memory/requisitos/Superadmin/RUNBOOK-configuracoes.md`. `SUPERFICIE.md` regerado.

## Provas do json

| prova | estado |
|---|---|
| `CommunicatorController.php` contém `Inertia::render(` | ✅ (parte 1) |
| `SuperadminSettingsController.php` contém `Inertia::render(` | ✅ |

## Pendente / decisões [W] (parte 2)

- **Divergências do protótipo** (`ViewConfig`): liga/desliga por gateway (Pix, boleto, cartão),
  "última execução do cron", "backup diário/retenção" e "notificações ativas" não têm backend.
  Ficaram declaradas no charter; criar qualquer uma é regra nova, decisão [W].
- Termos do cadastro, e-mail de boas-vindas e instruções offline continuam HTML editado como
  texto (o TinyMCE saiu); editor rico é decisão [W].
- `SPEC.md` US-SUPER-008 ainda cita a view Blade em `**Implementado em:**` (a view segue no disco);
  atualizar a âncora quando a Blade for apagada — fora do prefixo desta thread.
- Medida/alvo da tela: **NÃO MEDI** (a ficha não pede).

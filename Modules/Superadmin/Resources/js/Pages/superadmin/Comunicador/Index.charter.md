---
id: modules-superadmin-pages-superadmin-comunicador-index-charter
page: /superadmin/communicator
component: Modules/Superadmin/Resources/js/Pages/superadmin/Comunicador/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/superadmin-page.jsx
owner: wagner
status: draft
last_validated: "2026-09-30"
related_us: [US-SUPER-004]
parent_module: Superadmin
related_adrs: [104, 93]
tier: B
charter_version: 1
runbook: memory/requisitos/Superadmin/RUNBOOK-comunicador.md
---

# Page Charter — /superadmin/communicator

> Nasce `draft` na thread Superadmin/05 (Blade → Inertia). Vai a `live` no PR pós-deploy, com o
> smoke. Backend: `CommunicatorController@index` e `@send`. Ver
> [RUNBOOK-comunicador](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-comunicador.md).

## Mission

Responde *"como aviso os negócios da plataforma de uma vez?"*: compõe um aviso, escolhe os
negócios, envia ao dono de cada um (e-mail + notificação no app) e deixa o envio registrado.
Persona única: [W], superadmin. Admin de negócio é barrado.

## Goals — Features (faz)

- Lista **todos** os negócios para escolher destinatários, com filtro por nome, "Selecionar todos"
  e "Limpar" (paridade com o Blade).
- Assunto + mensagem em **texto puro**; contador de caracteres; prévia do e-mail.
- Confirmação explícita antes de enviar (o Blade pedia `swal`).
- Histórico dos 50 envios mais recentes: assunto, resumo sem marcação, alcance e data.

## Non-Goals — Features (NÃO faz)

- **Não agenda envio** e **não mede abertura**: o backend não tem nada disso.
- **Não segmenta por status de assinatura/pacote**: o destinatário é o negócio escolhido.
- **Não edita nem reenvia** envio passado.

## Automation Anti-hooks (o que a próxima sessão NÃO pode "consertar")

- ❌ **Não escopar a lista por `business_id`.** O comunicador é cross-tenant por definição
  ([ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
  §exceções Superadmin); a trava é o gate `superadmin`, não o tenant.
- ❌ **Não mandar o corpo como HTML.** O e-mail (`emails.plain_html`) imprime o corpo cru; por
  isso o `send()` escapa o texto e só converte quebra de linha em `<br>`. Voltar ao editor rico
  sem sanitizar abre injeção de HTML no e-mail de todos os clientes.
- ❌ **Não renderizar o `message` do histórico como HTML.** Registros antigos têm HTML do TinyMCE;
  o payload manda `resumo` sem tag.
- ❌ **Não inventar os grupos do protótipo no front** ("trial", "vencidas") contando negócio no
  JavaScript: o dado de assinatura não está nesta tela e a conta sairia errada.

## Divergências declaradas contra o protótipo

| Protótipo (`ViewComunicador`, L1224) | Produção | Por quê |
|---|---|---|
| chips de grupo (ativas, trial, vencidas, por pacote) | lista de negócios | o backend recebe `recipients` = ids de negócio; grupo exige regra nova, decisão [W] |
| agendar envio | ausente | não existe agendamento no `send()` |
| "Enviar teste para mim" | ausente | não existe endpoint |
| % de abertura no histórico | ausente | não há rastreio de abertura |

## Refs

- Casos: [Index.casos.md](Index.casos.md)
- Protótipo: `prototipo-ui/cowork/Wagner/superadmin-page.jsx` → `ViewComunicador()` (L1224)

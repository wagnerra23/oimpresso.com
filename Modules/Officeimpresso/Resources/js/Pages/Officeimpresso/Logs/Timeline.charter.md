---
page: /officeimpresso/licenca_log/timeline
component: Modules/Officeimpresso/Resources/js/Pages/Officeimpresso/Logs/Timeline.tsx
status: draft
owner: wagner
parent_module: Officeimpresso
last_validated: '2026-08-19'
related_prototype: prototipo-ui/cowork/officeimpresso-page.jsx
related_runbook: memory/requisitos/Officeimpresso/RUNBOOK-logs.md
related_us:
  - US-OI-005
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
charter_version: 1
---

# Charter — Timeline da máquina (`/officeimpresso/licenca_log/timeline/{licenca_id}`)

**Missão.** Responder, de uma máquina só, *"o Delphi daqui falou com o servidor, quando, e ele
estava liberado na hora?"* — que é a pergunta que o suporte faz quando o cliente liga dizendo que
o sistema não abre.

**Padrão de tela:** [PT-07 Feed/Timeline](../../../../../../memory/requisitos/_DesignSystem/padroes-tela/PT-07-Feed-Timeline.md).
**Plano:** [RUNBOOK-logs.md](../../../../../../memory/requisitos/Officeimpresso/RUNBOOK-logs.md) ·
**Paridade:** [logs-parity.md](../../../../../../memory/requisitos/Officeimpresso/logs-parity.md) itens 38-51.

## Sobre a âncora de design

`related_prototype` aponta pro `officeimpresso-page.jsx` do Cowork (fonte de design do módulo).
No protótipo esta tela **não é uma página própria**: a timeline vive no drawer da licença dentro da
view `oi-licencas` ("Timeline no log"). O descompasso é o mesmo da tela irmã e tem
**decisão [W] 2026-08-19: paridade agora, realinhar depois** — ver
[Index.charter.md](Index.charter.md) §"Sobre a âncora de design". Aqui a timeline continua
sendo página, porque é o que a rota faz hoje.

## Goals

- Preservar os 14 itens da timeline mapeados no `logs-parity.md` (38-51).
- Deixar claro, em cima, **quem** é a máquina e em que estado ela está agora.
- Distinguir "não pode ver" (403) de "não existe" (404) — os dois códigos são contrato.

## Non-Goals

- ❌ **Não** mostra o log inteiro da máquina: são os últimos **200** acessos a
  `processa-dados-cliente`, filtrados por `source=delphi_middleware`. Ampliar o recorte muda a
  pergunta que a tela responde.
- ❌ **Não** oferece ação de bloquear/desbloquear aqui. A ação é da lista; esta tela é leitura.
  Quando o usuário não pode bloquear, a tela **diz** qual permissão falta em vez de esconder.
- ❌ **Não** pagina nem ordena client-side — o recorte de 200 vem pronto do servidor, desc.
- ❌ **Não** inventa métrica agregada (média de latência, uptime). O Blade não tem, e indicador
  novo é decisão de produto, não de migração.

## Automation Anti-hooks

- ❌ **Nunca assumir que `metadata` é objeto.** O model tem cast `array`, mas consulta via
  `DB::table` foge do cast e entrega **string JSON**. Ler errado faz a coluna "Estado no login"
  mentir sobre o bloqueio. O parser trata os dois e falha fechado (`false`) em JSON inválido.
- ❌ **Nunca eager-load `logs`.** São até 200 linhas — `Inertia::defer`. `maquina` é 1 linha já
  carregada pela guarda e fica eager de propósito.
- ❌ **Nunca trocar o 404 por redirect** quando o `licenca_id` não existe. O código distingue
  máquina inexistente de máquina proibida, e o teste trava os dois.

## UX targets

- 1280px sem scroll horizontal na página (5 colunas — folgado).
- O estado da máquina (ativa / máquina bloqueada / empresa bloqueada) é legível **sem rolar**.

## Estados

| Estado | O que aparece |
|---|---|
| Carregando | skeleton do feed dentro do `<Deferred>`; o cabeçalho já mostra a máquina |
| Vazio | "Nenhum acesso registrado para esta máquina." + explica que ela está cadastrada mas o Delphi nunca chamou dali |
| Máquina inexistente | 404 |
| Sem permissão | 403 antes do render |

## Permissões

`superadmin` **ou** `officeimpresso.access`. Quem não tem `officeimpresso.licencas.gerenciar` vê a
tela inteira (é leitura) e recebe uma nota dizendo qual permissão falta pra agir.

## Casos

[Timeline.casos.md](Timeline.casos.md)

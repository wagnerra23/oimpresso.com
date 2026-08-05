---
id: reference-fluxo-cancelamento
name: Fluxo — Cancelamento
description: Cancelar uma venda é desfazer em quatro domínios, e nem tudo é reversível. O que volta, o que não volta e o que só acontece atrás de flag.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: fluxo
nav_order: 20
lente: [operar, construir]
related: [reference-fluxo-venda, reference-dominio-estagio, reference-dominio-nota-fiscal]
---

# Fluxo — Cancelamento

> Cancelar não é apagar. É uma **transição de estado** que dispara efeitos em cascata — e
> alguns deles não têm volta. Dono da mecânica:
> [ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) +
> [`app/Domain/Fsm/SideEffects/`](../../app/Domain/Fsm/SideEffects).

## Por que este fluxo merece documento próprio

Uma venda tocou quatro domínios ([Fluxo — Venda](FLUXO-VENDA.md)). Cancelar tem que desfazer em
todos, **e eles não desfazem do mesmo jeito**:

| domínio | o que acontece ao cancelar |
|---|---|
| estágio | transição registrada no histórico — nunca some |
| estoque | reserva **liberada**; o que já saiu tem tratamento próprio |
| financeiro | depende: cobrança emitida ≠ pagamento recebido |
| fiscal | **evento fiscal**, se já autorizada — irreversível |

A assimetria é o ponto. Quem espera "cancelar volta tudo ao estado anterior" se surpreende no
fiscal.

## O que não volta

**Nota autorizada.** Cancelar exige evento junto à SEFAZ, e o **número permanece usado**. Não
há `DELETE`, e o registro fica com o estado que conta a história. Se a operação precisa ser
refeita, ela gera documento novo — não recupera o antigo.

**O histórico.** A transição de cancelamento é mais um registro append-only, não uma limpeza.
Quem cancelou e quando fica gravado.

## O que é condicional

Devolver dinheiro ao cliente **não é automático**. O estorno em gateway roda atrás de flag de
configuração e, desligada, o sistema **registra a intenção sem executar** — de propósito, para
que ninguém devolva valor por acidente durante validação.

Isso significa que *"cancelei, então o cliente já recebeu de volta"* é uma suposição perigosa.
Confira o estado da cobrança, não o da venda.

## O que é opt-in

Avisar o cliente do cancelamento (e-mail, mensagem) respeita **consentimento por contato**. Sem
opt-in, não envia — e registra o motivo. Não é falha de entrega; é LGPD funcionando.

## A ordem importa

Os efeitos são peças isoladas acionadas pela máquina — não código solto no controller. Isso
existe justamente porque cancelamento é onde a ordem entre "liberar estoque", "cancelar
cobrança" e "cancelar nota" tem consequência prática, e a ordem precisa estar num lugar só.

Se você for adicionar um efeito novo ao cancelamento, ele entra como peça — nunca como `if`
dentro de quem dispara.

## Antes de mexer

Cancelamento move **valor e estoque** ao mesmo tempo — a combinação mais sensível do sistema.
Vale integralmente a regra de [`memory/proibicoes.md`](../proibicoes.md): dois caminhos
independentes de conferência e **tabela antes→depois apresentada antes de aplicar**, com
dry-run em produção.

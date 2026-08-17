---
id: reference-fluxo-cancelamento
name: Fluxo — Cancelamento
description: Cancelar uma venda é desfazer em quatro domínios, e nem tudo é reversível. O que volta, o que não volta e o que só acontece atrás de flag.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-17"
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

## As quatro perguntas que este percurso não respondia

Medido em 2026-08-17 contra o gate da Trilha D (D7), que pede *ator, máquina, módulo, dado,
auth, tenant, retry, falha parcial e rollback explícitos*. A máquina já estava (topo), e
"rollback" é o próprio assunto desta página. **Estas quatro faltavam.**

Vale a regra das páginas de fluxo: aqui vai o **percurso e o dono**, nunca o valor. Número de
tentativa e nome de papel **mudam** — quem sabe é o arquivo apontado.

### Quem pode cancelar

Não é qualquer usuário logado. A ação de cancelamento é cadastrada como **crítica** e com
**papel exigido** — as duas coisas na mesma linha do seeder, junto do efeito em cascata que ela
dispara. Cancelar não é permissão de tela: é papel no processo.

Como isso conversa com o [Fluxo — Venda](FLUXO-VENDA.md#quem-tem-permissão-de-mover-a-venda):
lá estão as **duas** camadas que respondem permissão (uma diz se o botão aparece, outra se a
ação executa). Para o cancelamento de venda as duas concordam, porque o papel **está**
cadastrado — é justamente o caso em que o mecanismo de falha-fechada não precisa entrar.

Dono: [`FsmProcessoVendaComProducaoSeeder`](../../database/seeders/FsmProcessoVendaComProducaoSeeder.php)
(a linha da ação carrega papel + criticidade + efeito) · recusa em
[`ExecuteStageActionService`](../../app/Domain/Fsm/Services/ExecuteStageActionService.php).

### De quem é o cancelamento

A cascata roda **fora da requisição** — ela despacha jobs, e job não tem sessão. Por isso a
empresa viaja **explícita** em cada peça, e o código carrega marcação própria nos pontos onde
isso acontece. É o mesmo cuidado da parada 3 do fluxo de venda, e a mesma consequência: se um
cancelamento aparecer mexendo na empresa errada, o lugar de olhar é a peça, não a tela.

Dono: [`CancelarVendaCascade`](../../app/Domain/Fsm/SideEffects/CancelarVendaCascade.php)
· regra em [`memory/proibicoes.md`](../proibicoes.md) (§ *Multi-tenant Tier 0*).

### O que acontece quando uma perna da cascata falha

Cada perna é um **job com política de repetição própria** — e elas não são iguais entre si de
propósito: falar com o fisco e falar com o cliente não merecem a mesma insistência. O aviso ao
cliente é o mais paciente de todos; o fisco e o meio de pagamento, os mais teimosos.

Os números vivem nos jobs e **não são reproduzidos aqui**, porque mudam.

Donos: cancelamento fiscal em [`CancelarNfeJob`](../../Modules/NfeBrasil/Jobs/CancelarNfeJob.php)
e [`CancelarNfseJob`](../../Modules/NfeBrasil/Jobs/CancelarNfseJob.php) · estorno em
[`EstornarBoletoJob`](../../app/Jobs/EstornarBoletoJob.php) · aviso em
[`NotificarClienteCancelamentoJob`](../../Modules/Whatsapp/Jobs/NotificarClienteCancelamentoJob.php).

### Falha parcial tem nome, e é o risco central desta página

As seções acima já descrevem o fenômeno — *"depende"*, *"é condicional"*, *"é opt-in"* — mas sem
o rótulo. O rótulo é **falha parcial**: as pernas são independentes, cada uma pode falhar por
conta própria, e **nenhuma desfaz as outras**.

A consequência operacional, que é o que interessa a quem atende cliente:

> **"cancelei" não significa "cancelou em todos os lugares".**

O fiscal pode ter cancelado e o estorno não; o estorno pode ter rodado e o aviso não ter saído
(por opt-in, legitimamente). Conferir o estado de **cada perna**, nunca o da venda — é a mesma
regra que a seção *"O que é condicional"* já dava para o dinheiro, agora valendo para as quatro.

E o que **nunca** é parcial continua sendo o número da nota: autorizado, não volta a ficar livre,
aconteça o que acontecer com o resto da cascata.

## Antes de mexer

Cancelamento move **valor e estoque** ao mesmo tempo — a combinação mais sensível do sistema.
Vale integralmente a regra de [`memory/proibicoes.md`](../proibicoes.md): dois caminhos
independentes de conferência e **tabela antes→depois apresentada antes de aplicar**, com
dry-run em produção.

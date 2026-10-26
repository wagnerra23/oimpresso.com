---
slug: 0440-jana-propoe-acao-usuario-confirma
number: 440
title: "A Jana propõe a ação, o usuário confirma, e só então o sistema grava"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-26"
module: jana
quarter: 2026-Q4
tags: [jana, ia, tools, acao, aprovacao-humana, multi-tenant, auditoria]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0141-agents-tool-use-pattern-claude-code
  - 0245-jana-advisor-modo-consultor-clarify
  - 0093-multi-tenant-isolation-tier-0
  - 0035-stack-ai-canonica-wagner-2026-04-26
pii: false
review_triggers:
  - "Pedido para a Jana gravar valor ou estoque (exige ADR própria e a regra mestre de valor/estoque)"
  - "Pedido para a Jana executar ação sem confirmação (modo automático)"
---

# ADR 0440 — A Jana propõe a ação, o usuário confirma, e só então o sistema grava

## Contexto

As ferramentas que o chat da Jana usa (`ChatCopilotoAgent::tools()`) só **leem** dados da empresa:
vendas, inadimplência, NF-e, oportunidades, chamados. Elas foram ligadas em produção em 2026-07-17
(US-COPI-142). O levantamento de lacunas de 2026-10-25 pôs como P1 da frente Plataforma uma IA que
**executa ações** com aprovação do usuário, que os concorrentes de ERP com IA já anunciam.

Deixar o modelo gravar direto tem três riscos concretos: o modelo erra a intenção ou o alvo; a
ferramenta pode gravar com permissão maior que a do usuário que pediu; e uma ação feita pelo
modelo não deixa rastro de quem decidiu. A ADR 0141 fixou o padrão de ferramenta com
`business_id` no construtor; ela não trata escrita.

## Decisão

1. **A Jana nunca grava.** Uma ferramenta de ação devolve uma **proposta**: tipo da ação, alvo e
   campos, já validados. A proposta é guardada com `business_id`, usuário e conversa, e expira.
2. **A tela mostra a proposta num cartão** com o resumo legível e os botões Confirmar e Cancelar.
   Sem clique do usuário, nada acontece.
3. **Confirmar executa pelo mesmo serviço que a tela manual usa**, com a sessão e as permissões do
   usuário que confirma. Usuário sem permissão para a ação não recebe a ferramenta, e o cartão não
   aparece para ele. A proposta de uma empresa não executa em outra (`business_id` conferido no
   servidor, nunca vindo do modelo).
4. **Toda proposta, confirmação e cancelamento vai para auditoria** (quem, quando, o quê, resultado).
5. **Primeiras ações: criar tarefa e registrar lembrete.** Nada que mexa em valor, estoque, documento
   fiscal ou envio a terceiro entra sem ADR própria.
6. **Atrás de flag** (`copiloto.chat_acoes.enabled`, padrão desligado), como as ferramentas de leitura
   (ADR 0245: homologação liga, produção espera).

## Justificativa

Proposta + confirmação mantém o humano como quem decide, reaproveita as regras de negócio que a tela
já valida, e não abre caminho de escrita paralelo ao do sistema. Ações sem valor no começo deixam
medir o uso e a taxa de proposta errada antes de chegar perto de dinheiro.

Reabrir se a taxa de proposta cancelada ficar alta (a Jana não entende o pedido) ou se houver pedido
de modo automático para alguma ação.

## Consequências

**Positivas:** a Jana passa a fazer, não só responder; o risco fica limitado ao que o usuário já
podia fazer na tela; tudo fica auditado.

**Negativas / Trade-offs:** um clique a mais por ação; uma tabela nova de propostas; cada ação nova
precisa de um serviço reutilizável (algumas telas gravam direto no controller e terão de ser
refatoradas antes).

**Riscos mitigados:** ação com tenant errado (business do servidor, teste 98 × 99), escalada de
permissão (executa como o usuário), ação não pedida (sem confirmação não grava).

## Referências

- `memory/requisitos/Jana/SPEC.md` — US-COPI-151 (esta ação), US-COPI-141/142 (ferramentas de leitura)
- ADR 0141 — padrão de ferramenta dos agentes · ADR 0245 — modo consultor e flags · ADR 0093 — isolamento

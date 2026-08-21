---
id: reference-como-ler-esta-documentacao
name: Como ler esta documentação
description: O mapa da própria documentação — a âncora (domínio → fluxo → tela), as duas lentes, o que é dono e o que é ponteiro, e por que nenhuma página aqui repete o conteúdo de outra.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: start
nav_order: 10
lente: [operar, construir]
---

# Como ler esta documentação

> **A capa é o [Guia do Sistema](../GUIA-DO-SISTEMA.md)** — o sistema numa página. Esta aqui
> explica a *forma* da documentação: por onde entrar, o que cada grupo do menu promete, e a
> regra que sustenta tudo — **um assunto, um dono; o resto aponta.**

## A âncora: domínio → fluxo → tela

A documentação **não** é organizada por módulo. Módulo é onde o código mora, não como a
pergunta chega. Ninguém abre a documentação pensando `Modules/Repair`; abre pensando "a OS
travou na produção".

| Grupo | Responde | Estabilidade |
|---|---|---|
| **Comece aqui** | "o que é este sistema?" | muda pouco |
| **Domínio** | "o que é uma OS, uma venda, um título — que estados tem, quem escreve" | **a espinha**: não se move quando o menu muda |
| **Fluxos** | "como o trabalho atravessa o sistema, de ponta a ponta" | muda quando o processo muda |
| **Técnico** | "como isto é construído, testado e liberado" | muda com o código |
| **Governança** | "como uma decisão vira lei" | muda por ADR |

**Módulo virou faceta**, não pasta: aparece como etiqueta na busca, nunca como o eixo do menu.

## As duas lentes

O mesmo acervo, dois públicos:

- **Operar** — quem usa o sistema: começa aqui, domínio, fluxos.
- **Construir** — quem mexe no sistema: começa aqui, domínio, técnico, governança.

**Domínio aparece nas duas de propósito.** É *uma* página canônica por entidade lida por dois
públicos — nunca duas cópias. Duas cópias divergem, e a divergência só é descoberta quando
alguém age pela errada.

## O que é dono e o que é ponteiro

Regra de ouro deste acervo, herdada da [ADR 0256](../decisions/0256-knowledge-survival-meia-vida-catraca-sentinela.md)
(*derivado e enforçado sobrevive; escrito e lembrado apodrece*):

| Tipo de conteúdo | Onde é **dono** | Aqui aparece como |
|---|---|---|
| valores de enum (status, tipo, origem) | dicionários em [`memory/dominio/`](../dominio/) — cobrados por [`domain-dict-guard`](../../scripts/domain-dict-guard.mjs) | ponteiro |
| estágios e transições | máquina de estados ([ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) | ponteiro |
| decisão e sua justificativa | ADR em [`memory/decisions/`](../decisions/) (append-only) | ponteiro |
| estado vivo (ciclo, tasks, brief) | tools MCP | **nunca** documentado aqui |
| lista de módulos, gates, workflows | [`PAINEL-SISTEMA`](PAINEL-SISTEMA.md), gerado por [`system-map.mjs`](../../scripts/governance/system-map.mjs) | ponteiro |

Divergiu? **A fonte manda** — e a correção é no documento, não na fonte.

## Como uma página entra no menu

Opt-in, por frontmatter, validado pelo schema
[`reference.schema.json`](../../scripts/memory-schemas/reference.schema.json):

```yaml
nav_group: dominio        # start | dominio | fluxo | tecnico | governanca
nav_order: 20             # só ORDENA dentro do grupo
lente: [operar, construir]
```

Sem `nav_group` o documento **não aparece** — os ~130 arquivos de referência legados não viram
menu por acidente. O ordinal exibido é derivado da ordem visível na lente ativa, nunca do
`nav_order`: senão filtrar a lente deixaria buracos (1, 3, 7).

## O que esta página **não** faz

Não lista os documentos — o rail à esquerda é derivado do disco e sempre estará certo; uma
lista aqui estaria errada no dia seguinte.

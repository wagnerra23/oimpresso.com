---
id: reference-gov-decisao-vira-lei
name: Governança — Como uma decisão vira lei
description: O caminho de uma proposta até ADR aceita, por que o merge é o ato de ratificação, e o que o append-only compra — o registro datado do raciocínio.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
nav_group: governanca
nav_order: 10
lente: [construir]
---

# Governança — Como uma decisão vira lei

> **Propor é permitido a todos. Ratificar é do [W] — e o merge é o ato.** Não existe assinatura
> separada, aprovação por chat, nem "combinamos na reunião".

## O caminho

1. **Alguém propõe** — documento em [`memory/decisions/proposals/`](../decisions/proposals/),
   formato Nygard: contexto, decisão, justificativa, consequências.
2. **Vira ADR numerada com status `proposto`** — e entra no índice **gerado**
   ([ADR 0258](../decisions/0258-processo-adr-estado-arte-indice-gerado-supersede-atomico.md)),
   derivado do disco. Índice escrito à mão seria mais uma coisa pra esquecer de atualizar.
3. **[W] ratifica no merge** — um PR que vira *só* a linha de status.
4. **A partir daí é append-only.** ADR aceita não se edita: um gate de CI bloqueia. Mudou de
   ideia? Escreve outra com `supersedes` (ou `supersedes_partially`, quando só um eixo cai).

## Por que append-only

Porque o valor da ADR não é a decisão — é **o raciocínio datado**. Quando alguém perguntar
"por que decidimos assim?", a resposta não depende de lembrança nem de quem ainda está no time.
Editar a ADR antiga apagaria exatamente a parte que interessa: o que se sabia na época.

Exemplo vivo: o eixo de localização do trio de tela foi decidido pela
[0364](../decisions/0364-trio-de-tela-mora-em-memory-emenda-0264.md) e revertido pela
[0365](../decisions/0365-trio-de-tela-fica-colocado-reverte-eixo-0364.md) — as duas continuam
lá, e a segunda explica o que a primeira não sabia. Nenhuma foi apagada.

## A linha vermelha do contrato de agente

Agente tem **ler** e **propor**. `git.merge` e `constituicao.edit` são **negados no token**
([ADR 0114](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md) ·
[ADR 0282](../decisions/0282-protocolo-v2-colapso-ratificacao.md)). Propor é permitido; decidir o
merge não é — e isso não é configuração, é o desenho.

## Emenda em vez de reescrita

Boa parte do canon recente é **emenda**: uma ADR que ajusta um pedaço de outra, citando qual.
Ler uma decisão sem ler as emendas dela dá a resposta de ontem — por isso o campo de relação no
frontmatter existe, e por isso o índice é gerado.

## Onde isto **não** mora

O estado de uma proposta (parada? em revisão?) é **vivo** e vem de tool, não deste documento.
Ver [Como trabalhar — tools MCP](../how-trabalhar.md).

---
id: requisitos-design-system-briefing-template
module: <NomeModulo>
status: em-construcao # producao|piloto|em-construcao|parcial|backlog|shared-infra|meta|deprecated
status_nota: "<qualificação curta e verificável>"
updated_at: "<YYYY-MM-DD>"
owner: W
related_adrs: []
---

# BRIEFING — `<NomeModulo>`

> **Função única:** resumo executivo e índice. O BRIEFING aponta para os donos; não recopia SCOPE, SUPERFICIE, SPEC, tópicos ou contratos de tela.
> **Contrato:** `scripts/memory-schemas/briefing.schema.json`.

## O que é

<Uma ou duas frases sobre o problema de negócio resolvido.>

## Estado atual

<Somente fatos atuais que um humano precisa para se orientar. Toda métrica deve apontar para consulta/artefato reexecutável; não use porcentagem solta nem “últimos 7 dias” manual.>

## Portas canônicas

- **Herança geral (componentes/layouts/templates compartilhados):** [`../_Geral/BRIEFING.md`](../_Geral/BRIEFING.md)
- **Fronteira/ownership:** [`SCOPE.md`](../../../Modules/<NomeModulo>/SCOPE.md)
- **Superfície derivada de código:** [`SUPERFICIE.md`](SUPERFICIE.md)
- **Requisitos:** [`SPEC.md`](SPEC.md)
- **Tópicos:** [`topicos/`](topicos/)
- **Telas:** `resources/js/Pages/<NomeModulo>/` + charters/casos ao lado das Pages

## Tópicos

Cada linha aponta para um arquivo de tema único. O resumo abaixo serve para descoberta; detalhes, pareceres e histórico vivem no tópico.

| Tópico | Resumo | Revisão |
|---|---|---|
| [`<slug>`](topicos/<slug>.md) | <uma frase> | `proposto|revisado-central|aprovado-humano|rejeitado` |

## Decisões e riscos que exigem atenção

- <ponteiro para ADR/tópico; não copie a decisão>

## Próxima ação verificável

- <ação + dono + evidência de conclusão>

## Regra de manutenção

1. Mudou árvore de código: regenere `SUPERFICIE.md`; não edite a lista no BRIEFING.
2. Mudou requisito: altere `SPEC.md`/charter/casos.
3. Mudou um tema: altere um único `topicos/<slug>.md` e ajuste só a linha-resumo deste índice.
4. Crítica de IA entra como proposta; IA central reconcilia; canon arquitetural ou mudança de produto exige aprovação humana.
5. Componente/layout/template compartilhado não é copiado para o módulo: consulte `_Geral`, valide o contrato e aponte para o dono único.

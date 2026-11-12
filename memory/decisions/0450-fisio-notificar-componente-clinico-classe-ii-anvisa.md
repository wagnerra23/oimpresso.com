---
slug: 0450-fisio-notificar-componente-clinico-classe-ii-anvisa
number: 450
title: "Fisio: notificar à Anvisa um componente clínico separado como SaMD classe II, e só construir o que é regulado depois do sistema de qualidade (emenda da 0437 §5)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-11-11"
module: fisio
quarter: 2026-Q4
tags: [fisio, anvisa, samd, classe-ii, rdc-657, rdc-751, rdc-665, iec-62304, iso-14971, qualidade]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0437-modulo-fisio-vertical-fisioterapia
  - 0447-fisio-camera-conta-repeticoes-no-aparelho
  - 0446-fisio-teleconsulta-livekit-e-evolucao-ditada
pii: false
review_triggers:
  - "a Anvisa responder a consulta de enquadramento (C-00 da etapa 03)"
  - "a consultoria regulatória discordar da classe ou do rito"
  - "a revisão da RDC 657/2022 ser publicada no DOU"
  - "uma função hoje fora do escopo regulado passar a interpretar dado clínico"
---

# ADR 0450 · Fisio: notificar um componente clínico como SaMD classe II

> **Status `proposto`.** [W] decidiu em 2026-11-11, textual: *"Ele vai acabar se tornando software na anvisa eu vou registrar"*. O merge desta ADR é o ato de ratificação.
>
> **Esta ADR não repete o plano.** O dono técnico do tema é [`memory/requisitos/Fisio/etapas/03-f2-camera.md`](../requisitos/Fisio/etapas/03-f2-camera.md) §5 "Plano Anvisa": o que a classe II libera, rito, exigências, prazos, custos e o quadro de decisão. Aqui fica só a escolha feita naquele quadro e o que ela muda no projeto.

## Contexto

A ADR 0437 §5 desenhou o Fisio para **não** ser dispositivo médico: o software registra, conta e soma, e não interpreta nem recomenda. Três funções ficaram fora de propósito, todas classe II pelo Perguntas & Respostas da RDC 657/2022:

| função | referência | situação até aqui |
|---|---|---|
| Câmera que mede ângulo e amplitude e corrige a forma | Q50 | fora; a F2 só conta repetições (ADR 0447) |
| Sugerir ao paciente parar ou trocar o exercício pela dor | P10 | fora; o alerta vai para o profissional |
| Motor que recomenda conduta, mesmo com aprovação humana | Q57 | fora até [W] decidir |

A etapa 03 §5.4 deixou a escolha para [W]: "ficar na linha", "notificar a classe II" ou "parceria". A ADR 0447 lista "[W] decidir notificar o software como dispositivo médico classe II" como gatilho de revisão.

## Decisão

1. **O Fisio vai notificar à Anvisa um SaMD classe II.** O rito de classes I e II é notificação (RDC 751/2022). A classe e o rito finais saem da consulta de enquadramento e da consultoria.
2. **Uma notificação só, para um componente "Fisio Clínico" separado do ERP**, com versão e deploy próprios (etapa 03 §5.1). Fica dentro dele apenas o que interpreta dado clínico: as três funções da tabela acima e as que vierem a interpretar. Agenda, prontuário, portal, jogo, pesquisa, financeiro e o resto do ERP ficam **fora** do escopo regulado. Mudança no ERP não dispara petição.
3. **Ordem obrigatória: primeiro a base regulatória, depois o código regulado.**
   1. consultoria regulatória e consulta de enquadramento (C-00 da etapa 03);
   2. **finalidade de uso** escrita, que fixa classe e escopo;
   3. empresa peticionante com AFE e responsável técnico; sistema de qualidade da RDC 665/2022 (controle de projeto, controle de mudanças, reclamações, tecnovigilância);
   4. só então o desenvolvimento das funções reguladas, já sob IEC 62304 (ciclo de vida), ISO 14971 (risco), IEC 62366-1 (usabilidade) e cibersegurança, com a avaliação clínica (RDC 548/2021).
4. **Até a notificação valer, a linha da ADR 0437 §5 continua em produção.** Nenhuma das três funções vai ao paciente antes disso. Protótipo e pesquisa interna são permitidos; flag ligada para paciente, não.
5. **O que já existe e entrar no componente** é tratado como software legado da IEC 62304: documentação e análise de risco feitas depois, com justificativa. A rastreabilidade do repositório entra como evidência (ADRs, casos de uso ligados a teste, CI), mas não substitui plano de desenvolvimento, análise de risco por função e registro de verificação e validação assinado.

## Alternativas consideradas

- **Ficar na linha** (recomendação da etapa 03 §5.4 até haver tração). Descartada por [W]: o produto vai crescer para funções que interpretam, e reconstruir depois sai mais caro.
- **Notificar o Fisio inteiro.** Descartada: cada mudança no ERP viraria mudança de dispositivo, com análise de risco e possível petição.
- **Parceria com SaMD já notificado.** Fica como opção se a consultoria achar um parceiro; não há um confirmado no Brasil para a câmera.

## Consequências

- O componente clínico passa a ter ritmo de entrega mais lento: toda mudança de função clínica passa por análise de risco, verificação documentada e, conforme o caso, petição. Correção de bug e de segurança não exigem petição (etapa 03 §5.1).
- A fronteira entre o componente e o ERP vira decisão de arquitetura: uma ADR própria, antes do primeiro código regulado.
- A ADR 0437 §5 deixa de ser "não fazer" para essas três funções e passa a "fazer só dentro do componente notificado". A tabela da §5 continua valendo para todo o resto.
- A ADR 0447 entra em revisão pelo próprio gatilho: a câmera com ângulo sai da lista do que não se faz e vira escopo do componente.

## Pendências (decisão [W] ou consultoria)

- Qual CNPJ é o peticionante e quem é o responsável técnico.
- Contratação da consultoria regulatória e da consulta de enquadramento.
- Texto da finalidade de uso e a lista final de funções do componente.
- Se a transcrição de receita e exame por foto (sugerida, conferida pelo profissional, sem interpretar) fica fora do componente. A leitura atual é que fica, porque não interpreta; confirmar com a consultoria.

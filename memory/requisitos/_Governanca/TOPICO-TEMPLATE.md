---
id: <slug-kebab>
module: <NomeModulo>
title: "<Tema único e concreto>"
kind: regra-negocio # capacidade|regra-negocio|fluxo|integracao|risco|tela|decisao
status: rascunho # rascunho|ativo|contestado|deprecated
updated_at: "<YYYY-MM-DD>"
anchors:
  screens: []
  routes: []
  controllers: []
  functions: [] # formato recomendado: path::símbolo
  models: []
  tables: []
  tests: []
  adrs: []
review:
  state: proposto # proposto|revisado-central|aprovado-humano|rejeitado
  verdict: incerto # concordo|discordo|incerto|nao-se-aplica
  confidence: baixa # baixa|media|alta
  central_reviewer: null
  human_approver: null
critiques: [] # {critic, at, verdict, summary, evidence[]}; nunca apagar divergência
claims:
  - id: C01
    status: observado # observado|inferido|proposto|aceito|rejeitado|incerto
    text: "<afirmação atômica, verificável e datada quando necessário>"
    evidence:
      - type: codigo # codigo|teste|runtime|adr|dono|externa
        ref: "<path:linha ou referência estável>"
---

# <Tema único>

> O BRIEFING só aponta para este arquivo. Não copie este conteúdo para o índice.

## Escopo

- **Inclui:** <o que este tópico responde>
- **Não inclui:** <fronteira explícita>

## Comportamento observado

<Derivado do código/teste/runtime. Separe fato observado de inferência.>

## Intenção e critério de sucesso

<Fonte externa ao código: SPEC, charter, ADR, regra do dono ou evidência operacional. Sem fonte suficiente, use `incerto`.>

## Parecer crítico

- **Veredito:** `concordo | discordo | incerto | nao-se-aplica`
- **Por que:** <compare comportamento observado com a intenção, sem gosto livre>

## Fazer: benefícios e custos

- **Benefício:** <lado positivo de fazer/manter>
- **Custo/risco:** <lado negativo de fazer/manter>

## Não fazer: benefícios e custos

- **Benefício:** <lado positivo de não fazer/remover>
- **Custo/risco:** <lado negativo de não fazer/remover>

## Evidências e contradições

- <fonte + o que prova>
- <evidência contrária ou lacuna>

## Histórico de revisão

| Data | Papel | Revisor | Decisão | Evidência nova |
|---|---|---|---|---|
| YYYY-MM-DD | crítico IA | <id/modelo> | proposto | <refs> |
| YYYY-MM-DD | IA central | <id/modelo> | incerto/revisado | <refs> |
| YYYY-MM-DD | humano | W | aprovado/rejeitado | <motivo> |

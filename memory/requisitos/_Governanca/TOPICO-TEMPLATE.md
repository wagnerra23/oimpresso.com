---
id: <slug-kebab>
module: <NomeModulo>
title: "<Tema único e concreto>"
kind: regra-negocio # capacidade|regra-negocio|fluxo|integracao|risco|tela|decisao
status: rascunho # rascunho|ativo|contestado|deprecated
updated_at: "<YYYY-MM-DD>"
# Validade bi-temporal (modelo Zep) — OPCIONAL, forward-only. Tempo-de-DOMÍNIO (≠ updated_at).
# valid_from: "<YYYY-MM-DD>"           # desde quando o fato é verdadeiro; ancore num evento (incidente/fix/ADR). Omita se origem legada é desconhecida.
# valid_until: "<YYYY-MM-DD>"          # SÓ quando o fato foi superado. Exige âncora abaixo — nunca data solta à mão (proibicoes §5).
# superseded_by_adr: "NNNN-slug"       # ADR supersessor (arquivo deve existir); valid_until espelha o decided_at dele.
# superseded_by_topic: "<id-sucessor>" # OU tópico sucessor (arquivo deve existir).
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

## Validade (bi-temporal)

Modelo Zep de tempo-de-domínio (≠ `updated_at`, que é tempo-de-transação). Opcional, forward-only.

- **`valid_from`** — desde quando o fato é verdadeiro. Ancore num evento verificável (incidente, fix, ADR); data histórica não apodrece.
- **`valid_until`** — presença = **comportamento superado**. Quem lê o tópico (recall/brief) trata `valid_until` no passado como superado (**advisory**) — mas **só** quando a âncora (`superseded_by_adr`/`superseded_by_topic`) resolve para um arquivo real. É o fato verificável, não a data nua, que expira o tópico.
- ⛔ **Nunca** escreva `valid_until` como data solta à mão — o schema exige nomear o supersessor. `status: contestado` (parecer disputado) **não** é `valid_until` (fato superado): contestado ≠ superado.

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

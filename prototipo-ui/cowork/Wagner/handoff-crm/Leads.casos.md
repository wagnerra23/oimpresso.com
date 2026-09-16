---
id: resources-js-pages-crm-leads-casos
casos: Leads · /crm/leads
irmaos: Leads.charter.md (lei)
tecnica: Caso de uso = narrativa do vendedor + critério de aceite verificável (Dado/Quando/Então)
por_que: converter lead em cliente é irreversível na prática — o comportamento tem que estar travado antes do refactor
owner: wagner
last_run: "—"
---

# Casos de Uso & Aceite — Leads

> **Status:** ⬜ não verificado em todos os UC (nenhum teste cita os ids). O UC-CRML-03 é o candidato natural a primeiro dente: conversão mexe no tipo do contato.

---

## UC-CRML-01 · Ver, na linha, quem está esfriando
- **Persona:** Larissa — precisa saber quem não é tocado há tempo sem abrir 10 fichas.
- **Aceite:** Dado leads com últimos acompanhamentos em datas diferentes · Quando a lista renderiza · Então cada linha mostra a recência como pílula de frescor coerente com a data (recente → verde, distante → vermelho), e um lead sem nenhum acompanhamento não aparece como "recente".
- **Teste:** ⬜ a escrever.

## UC-CRML-02 · Trocar de vista não perde o filtro
- **Persona:** Larissa — filtra por fonte "Indicação" e quer ver o mesmo recorte no kanban.
- **Aceite:** Dado um filtro aplicado na tabela · Quando alterno para o kanban · Então as colunas contêm exatamente os leads do filtro (o estágio de vida sai do filtro porque virou eixo do kanban).
- **Teste:** ⬜ a escrever.

## UC-CRML-03 · Converter em cliente exige o estágio pós-conversão e não inventa venda
- **Persona:** Larissa — fechou; o contato tem que virar cliente sem efeito colateral.
- **Aceite:** Dado um lead · Quando confirmo a conversão escolhendo o estágio pós-conversão · Então o contato passa a cliente com aquele estágio, **nenhuma** transação/venda/documento é criada, e a auditoria registra quem converteu.
- **Teste:** ⬜ a escrever (dente candidato: `LeadControllerTest::convert_to_customer_nao_cria_transacao`).

## UC-CRML-04 · Arrastar no kanban vale o mesmo que editar o campo
- **Persona:** Larissa — arrasta "Padaria Estrela" de Novo para Contatado.
- **Aceite:** Dado um lead numa coluna · Quando arrasto para outra · Então o estágio de vida persiste igual à edição pelo formulário (mesma escrita, mesma auditoria) e a contagem das duas colunas acompanha.
- **Teste:** ⬜ a escrever.

## UC-CRML-05 · Ação em massa de local não vaza tenant
- **Persona:** Wagner — adiciona 8 leads ao local "Filial Centro".
- **Aceite:** Dado leads selecionados · Quando aplico "adicionar ao local" · Então só contatos do `business_id` corrente são alterados, e um id estrangeiro injetado no payload é recusado.
- **Teste:** ⬜ a escrever.

## Backlog de casos

- **[BACKLOG]** Colunas de custom field entram por preferência de coluna e persistem por usuário.
- **[BACKLOG]** `postLifeStage` recusa estágio inexistente na taxonomia do negócio.

## Rastreabilidade

| UC | CU (SDD) | US (SPEC) |
|---|---|---|
| UC-CRML-01 … 05 | — | — |

> Sem SDD/US: módulo herdado do UltimatePOS, nunca especificado pelo protocolo.

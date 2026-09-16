---
id: resources-js-pages-crm-acompanhamentos-casos
casos: Acompanhamentos · /crm/follow-ups
irmaos: Acompanhamentos.charter.md (lei)
tecnica: Caso de uso = narrativa de quem cobra/prospecta + critério de aceite verificável
por_que: criação em lote é a função que mais pode estragar dado do cliente — precisa de trava antes do refactor
owner: wagner
last_run: "—"
---

# Casos de Uso & Aceite — Acompanhamentos

> **Status:** ⬜ não verificado. Existe `Modules/Crm/Tests/Feature/ScheduleServiceTest.php` no `main`, mas ele **não cita** os ids abaixo — então nenhum UC aqui pode ser dado como coberto (G-2). Amarrar os ids a esse teste é o primeiro passo baratinho.

---

## UC-CRMA-01 · O rodapé conta o que está filtrado, não a base inteira
- **Persona:** Eliana — filtra cobrança do mês e quer saber quantos estão em aberto.
- **Aceite:** Dado um filtro aplicado · Quando o rodapé apura por status e por tipo · Então os totais somam exatamente as linhas visíveis do recorte, e mudar o filtro muda os totais.
- **Teste:** ⬜ a escrever.

## UC-CRMA-02 · Antecipado por pagamento gera um acompanhamento por fatura escolhida
- **Persona:** Eliana — 4 faturas vencidas, quer 4 cobranças, não 1 genérica.
- **Aceite:** Dado faturas selecionadas na prévia · Quando salvo · Então é criado um acompanhamento por linha da prévia, com as tags do título resolvidas por fatura (`{invoice_no}`, `{due_amount}`), e remover uma linha antes de salvar reduz a contagem.
- **Teste:** ⬜ a escrever.

## UC-CRMA-03 · Lote com prévia vazia não salva
- **Persona:** Eliana — filtro não achou ninguém.
- **Aceite:** Dado nenhuma linha na prévia · Quando tento salvar · Então nada é criado e a tela avisa "não há nenhum cliente para adicionar acompanhamento" (mesma guarda do Blade).
- **Teste:** ⬜ a escrever.

## UC-CRMA-04 · Recorrente gera na cadência e não retroage
- **Persona:** Eliana — regra de cobrança a cada 7 dias.
- **Aceite:** Dada uma regra recorrente criada hoje com `recursion_days=7` · Quando o comando de geração roda · Então cria a ocorrência da janela corrente, nenhuma ocorrência com data passada, e não duplica se o comando rodar duas vezes no mesmo dia (idempotência).
- **Teste:** ⬜ a escrever (candidato: estender `ScheduleServiceTest` + `CreateRecursiveFollowup`).

## UC-CRMA-05 · O log registra a tentativa e pode fechar o acompanhamento
- **Persona:** Larissa — ligou, não atenderam; amanhã liga de novo.
- **Aceite:** Dado um acompanhamento aberto · Quando registro um log com status resultante · Então o log fica na linha do tempo com autor e janela, e o status do acompanhamento passa a ser o do log quando informado.
- **Teste:** ⬜ a escrever.

## UC-CRMA-06 · Sem `access_all_schedule`, só vejo os meus
- **Persona:** Larissa — não deve ver a carteira do Wagner.
- **Aceite:** Dado um usuário só com `crm.access_own_schedule` · Quando a lista carrega · Então só vêm acompanhamentos atribuídos a ele, inclusive nas contagens do rodapé.
- **Teste:** ⬜ a escrever.

## Backlog de casos

- **[BACKLOG]** Notificação sai UMA vez, no tempo configurado antes do início (não a cada rodada do scheduler).
- **[BACKLOG]** `crm_followup_invoices` liga o acompanhamento às faturas que o originaram (drawer mostra).

## Rastreabilidade

| UC | CU (SDD) | US (SPEC) |
|---|---|---|
| UC-CRMA-01 … 06 | — | — |

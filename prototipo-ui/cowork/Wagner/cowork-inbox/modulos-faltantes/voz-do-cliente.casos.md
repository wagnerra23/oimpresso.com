# Casos de uso — /voz-do-cliente (contrato de teste)

> Lei sobre o charter é o charter; isto é o contrato de teste. `UC-VOZ-01`…`UC-VOZ-12`.

| ID | Cenário | Esperado |
| --- | --- | --- |
| UC-VOZ-01 | Caixa com 8 relatos | tabela cronológica (mais novo primeiro), KPIs = 3 pendentes / 2 triados / 3 fechados |
| UC-VOZ-02 | Nenhum relato | vazio com a copy do blade: "Nenhum relato ainda…" — sem KPI e sem filtro |
| UC-VOZ-03 | Filtro "Pendentes" | só `status=pending`; contador do chip bate com a lista |
| UC-VOZ-04 | Filtro sem resultado | vazio "Nada nesta situação." (não o vazio de primeira vez) |
| UC-VOZ-05 | Relato pendente + gravidade alta | linha marcada como urgente (trilha à esquerda) |
| UC-VOZ-06 | Relato fechado | linha atenuada (archived) + motivo ao lado do selo |
| UC-VOZ-07 | Relato anônimo (`autor_nome` nulo) | coluna Quem mostra "—", nunca vazio silencioso |
| UC-VOZ-08 | Triar → "Virar US do backlog" com código | status vira Triado + código exibido; nenhum e-mail/notificação sai |
| UC-VOZ-09 | Triar → US sem código | grava `US-NOVA-001` e avisa que alguém precisa nomear |
| UC-VOZ-10 | Triar → "Fechar sem ação" com motivo | status vira Fechado + motivo; quem relatou **não** é avisado |
| UC-VOZ-11 | Papel sem gestão de produto | ação "Triar" ausente; rodapé diz "Sua função lê, mas não tria." |
| UC-VOZ-12 | Papel sem acesso | tela devolve sem-permissão citando LGPD Art. 7º; nenhum texto de relato renderizado |

## Anti-regressão

- Nenhum caminho de escrita sobre o texto do relato (só `status`/`triado_para_us`).
- Fechar ou triar não dispara e-mail, SMS, WhatsApp nem webhook.
- Sem `localStorage` de filtro (a caixa abre sempre em "Todos").

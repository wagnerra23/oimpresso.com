# App — Chat com a Jana (tela 25)

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`POST /api/app/chat { mensagem:string(≤1000), conversa_id:string|null }` →
`200 { conversa_id:string, resposta:{ de:"jana", texto, criada_em } }`. Resposta síncrona.

`GET /api/app/chat/{conversa_id}` → `{ conversa_id, mensagens:[{ de:"eu"|"jana", texto, criada_em }] }`,
em ordem cronológica.

- Canal = Jana (decisão [W]). São as mesmas conversas do chat web (`jana_conversas` /
  `jana_mensagens`) e o mesmo turno (`ChatTurnoService`): o pedido de brief diário vai para o
  brief, os tokens do turno ficam gravados, e uma falha da IA vira a resposta
  "Estou com dificuldades técnicas no momento…" em vez de erro.
- `conversa_id: null` (ou ausente) abre uma conversa nova, com a 1ª mensagem como título.
- A conversa é do usuário: de outro usuário ou de outro business → `404 nao_encontrado`.
- Acesso = o do chat web: módulo Jana no plano (`jana_module`) + `jana.access` + `jana.chat`;
  sem isso `403 sem_permissao`. Área `assistente` em `/api/app/inicio` (§6) com a mesma regra.
- Mensagem vazia ou acima de 1000 caracteres → `422 { erro:"validacao", campos:{ mensagem:"…" } }`.
  Limite de 60 mensagens por minuto, como na web (`429`).

# App — Equipe (tela 26) — só leitura

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`GET /api/app/equipe` →
`{ itens:[{ id, nome, funcao, carga, status:{ rotulo, tom } }] }`, em ordem alfabética.

- Pessoas = usuários do business (a mesma lista da tela web de Usuários), ativos e inativos. Sem
  telefone e sem ponto: "Marcação de ponto fica no módulo Ponto".
- `funcao` = cargo do Essentials (`categories` `hrm_designation`), senão o cargo do CRM; `null` sem cargo.
- `carga` = OS abertas atribuídas à pessoa (`service_orders.assigned_user_id`, mesmo universo do
  quadro web da Oficina: etapa não-terminal, ou OS de mecânica ainda sem pipeline), ex.: `"2 OS"`;
  `null` quando não há nenhuma. O ERP não atribui OP de produção a uma pessoa, então OP não entra.
- `status`: usuário inativo → `{ rotulo:"Inativo", tom:"ausente" }`; com carga → `{ "Em serviço", "ocupado" }`;
  senão → `{ "Disponível", "livre" }`.
- Acesso = o da tela web de Usuários (`user.view`); sem ele `403 sem_permissao`. Área `equipe` em
  `/api/app/inicio` (§6) com a mesma regra.

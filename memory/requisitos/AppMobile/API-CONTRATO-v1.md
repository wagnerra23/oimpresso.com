# App das lojas — contrato da API v1 (ERP ↔ oimpresso-app)

> **Para quê:** o app (`wagnerra23/oimpresso-app`, telas próprias, D5) monta as telas das 7 áreas
> (D13) contra este contrato com dados de demonstração; quando cada endpoint chega ao ERP, a troca
> é só na camada `src/api.ts`. **Fonte dos dados:** [MAPA-DE-DADOS-v1.md](MAPA-DE-DADOS-v1.md)
> (quem é dono de cada número no ERP). **Decisões:** `docs/lojas-app/DECISOES.md` (D5, D11, D13, D14).
> **Escrito em:** 2026-10-01. Endpoint marcado ✅ está no `main`; ⬜ é contrato, ainda não existe.

## 0. Regras para todos os endpoints

- **Autenticação:** `Authorization: Bearer <token>` do login do app (`POST /oauth/token`,
  password grant, client público — #8480). Sem token → `401`.
- **Tenant:** sempre o `business_id` do usuário do token. Nenhum parâmetro escolhe empresa (D2 do
  mapa: sem seletor de empresa). Teste de contrato cross-tenant obrigatório (tenant 98 × 2, ADR 0358).
- **Permissão:** as mesmas permissões Spatie das telas web. Sem permissão → o bloco vem `null`
  (Início) ou o endpoint responde `403 {"erro":"sem_permissao"}` (listas). O app esconde, não quebra.
- **Datas** ISO `YYYY-MM-DD` / `YYYY-MM-DDTHH:mm:ssZ`; **dinheiro** em número decimal com 2 casas
  (`1234.50`), nunca string formatada.
- **Urgente = atrasado** (D11): `atrasado = prazo < hoje && !concluido`. Não existe prioridade.
- **Erro com mensagem:** `{"erro": "<codigo>", "mensagem": "<texto PT-BR para a tela>"}`.
- **Escrita** (concluir, avançar etapa…) só entra quando o item disser — ação que mexe em **valor
  ou estoque** passa pela regra mestre (dupla prova + antes→depois + aprovação [W]) antes.

## 1. Ponto ✅ (no `main`)

`/ponto/api/*` — `marcar`, `marcacoes/hoje`, `saldo`, `escala/hoje`, `dashboard/kpis`,
`intercorrencias` (GET/POST), `intercorrencias/tipos`, `me`, `espelho?mes=`, `push/dispositivo`.
Sem cadastro de ponto → `403 sem_colaborador`.

## 2. Pedidos ⬜ — Pedido = **venda** do ERP (D11)

`GET /api/app/pedidos?filtro=ativos|atrasados|concluidos|todos&q=&pagina=`

```json
{ "itens": [ {
    "id": 123, "numero": "0042", "cliente": "Gráfica X",
    "valor": 248.00, "prazo": "2026-10-03", "atrasado": false,
    "etapa": { "chave": "in_production", "rotulo": "Em produção", "grupo": "producao" },
    "progresso": 0.6 } ],
  "contadores": { "ativos": 12, "atrasados": 3, "concluidos": 40, "todos": 55 },
  "pagina": 1, "tem_mais": true }
```

- `grupo` ∈ `orcamento · aprovacao · producao · entrega · concluido` — os 5 passos do protótipo,
  agrupando os estágios reais da FSM `venda_com_producao` pela tabela do MAPA §3.
- `progresso` = posição do grupo (0, .25, .5, .75, 1).
- Permissão: a mesma da lista de vendas web (`sell.view` / `view_own_sell_only`).

`GET /api/app/pedidos/{id}` → o item acima + `itens_venda[{produto, quantidade, total}]`,
`cliente{id, nome, telefone}`, `etapas[{grupo, rotulo, estado: feito|atual|futuro}]`,
`acoes[{chave, rotulo, pode}]` (de `SaleFsmActionController::actions`, só leitura na v1).

**Escrita (não na 1ª entrega):** `POST /api/app/pedidos/{id}/acao {acao}` via
`ExecuteStageActionService`. Várias ações reservam/baixam estoque ou cancelam cobrança — só entra
com a regra mestre cumprida. Na v1 o app mostra o botão contextual desabilitado com "Abrir no
computador".

## 3. Tarefas ⬜ — ToDo + justificativas do Ponto (D11)

`GET /api/app/tarefas?origem=todas|todo|ponto`

```json
{ "itens": [ {
    "id": "todo:15", "origem": "todo", "titulo": "Ligar para fornecedor",
    "subtitulo": "Essentials · alta", "prazo": "2026-10-01", "atrasado": true,
    "grupo": "hoje" } ],
  "contadores": { "todas": 7, "todo": 5, "ponto": 2 } }
```

- `origem=todo`: `essentials_to_dos` atribuídos ao usuário, não concluídos.
- `origem=ponto`: para **gestor** (permissão de aprovar intercorrência), as justificativas
  pendentes de aprovação da empresa; para o **colaborador**, as próprias ainda pendentes.
- `grupo` ∈ `atrasadas · hoje · amanha · semana · depois`.
- Escrita v1: `POST /api/app/tarefas/todo/{id}/concluir` (só ToDo do próprio usuário).

## 4. Pessoas ⬜

`GET /api/app/pessoas?papel=todos|clientes|fornecedores|funcionarios|em_debito&q=&pagina=`

```json
{ "itens": [ {
    "id": 9, "nome": "Marília Costa", "tipo": "PF",
    "papeis": ["cliente", "fornecedor"], "saldo_aberto": 120.00,
    "ativo": true } ],
  "contadores": { "todos": 120, "clientes": 98, "fornecedores": 20, "funcionarios": 5, "em_debito": 7 },
  "pagina": 1, "tem_mais": true }
```

- **Sem papel Transportadora** (D11). Papéis vêm das flags `is_customer/is_supplier/is_employee`.
- Respeita `view_own` (corrigido no #8469) — mesma regra da lista web.
- `GET /api/app/pessoas/{id}` → `{id, nome, tipo, documento, papeis, contato{telefone, email},
  endereco, kpis{pedidos, ticket_medio, saldo_aberto}, pedidos_recentes[…]}`. Documento (CPF/CNPJ)
  só com a permissão de ver contato completo.

## 5. Produção ⬜ — fila do Kanban por etapa, sem carga % (D11/D6)

`GET /api/app/producao`

```json
{ "colunas": [ {
    "id": "em-execucao", "rotulo": "Em execução",
    "itens": [ { "id": 77, "numero": "OS-0077", "cliente": "…", "prazo": "2026-10-02", "atrasado": false } ] } ] }
```

- **Fonte provisória:** o Kanban de produção que existe hoje no ERP é o da OS
  (`ProducaoOficinaController` + `KanbanProductionService`: colunas recepção · diagnóstico ·
  aguardando peças · em execução · pronto). ⚠️ **A confirmar com o [W]:** se a "fila do Kanban"
  da D11 é esse (OS) ou os estágios de produção da **venda** (`in_production`/`on_hold`), já que
  Pedido = venda. Até a resposta, o app monta a tela com colunas genéricas (`id`, `rotulo`).
- Só leitura na v1 (mover card grava status fora da FSM hoje — não exposto).

## 6. Início ⬜

`GET /api/app/inicio`

```json
{ "usuario": "Wagner", "empresa": "Oimpresso",
  "faturado_hoje": { "valor": 1520.00, "ontem": 1300.00, "variacao_pct": 16.9 },
  "meta_dia": { "valor": 2000.00, "derivada": true },
  "kpis": { "pedidos_ativos": 12, "pedidos_atrasados": 3, "estoque_baixo": 2 },
  "financeiro": { "a_receber": 8200.00, "a_pagar": 3100.00 },
  "proximas_tarefas": [ "…mesmo item de /tarefas, até 3…" ] }
```

- `faturado_hoje` e `meta_dia`: só com `dashboard.data` (senão `null`). Meta do dia = meta mensal
  da Jana ÷ dias úteis do mês (D11), sempre com `derivada: true`.
- `estoque_baixo`: só com `stock_report.view` (senão `null`). `financeiro`: só com acesso ao
  Financeiro (senão `null`).
- Atalhos (Novo pedido, Venda rápida, Cobrar PIX, Conciliar) ficam fora da v1 — são v2 ou tela de
  computador.

## 7. Mais

Sem API própria: itens com tela no app (Pessoas, Ponto, Conta) + "Abrir no computador" para o
resto. Nada de Produtos/Venda rápida/Finanças na v1 (D13 → v2).

## 8. Ordem de entrega no ERP

1. Pedidos (lista + detalhe, só leitura) · 2. Tarefas (+ concluir ToDo) · 3. Pessoas ·
4. Produção (depois da confirmação do §5) · 5. Início (agrega os anteriores).
Cada um em PR próprio com teste de contrato na lane MySQL e entrada neste documento (⬜ → ✅).

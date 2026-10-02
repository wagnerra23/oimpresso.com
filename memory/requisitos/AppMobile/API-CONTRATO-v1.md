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
    "id": 123, "numero": "0042", "cliente": "Gráfica X", "resumo": "1.000 cartões 9x5",
    "valor": 248.00, "prazo": "2026-10-03", "atrasado": false,
    "etapa": { "chave": "in_production", "rotulo": "Em produção", "grupo": "producao" },
    "progresso": 0.6 } ],
  "contadores": { "ativos": 12, "atrasados": 3, "concluidos": 40, "todos": 55 },
  "pagina": 1, "tem_mais": true }
```

- `grupo` ∈ `orcamento · aprovacao · producao · entrega · concluido` — os 5 passos do protótipo,
  agrupando os estágios reais da FSM `venda_com_producao` pela tabela do MAPA §3.
- `progresso` = posição do grupo (0, .25, .5, .75, 1).
- `resumo` = nome do 1º item da venda (título do cartão no v4); `null` se a venda não tem item.
- `cliente` é **texto** na lista e **objeto** (`cliente_detalhe`) no detalhe — de propósito.
- Permissão: a mesma da lista de vendas web (`sell.view` / `view_own_sell_only`).

`GET /api/app/pedidos/{id}` → o item acima + `itens_venda[{produto, quantidade, total}]`,
`cliente{id, nome, telefone}`, `etapas[{grupo, rotulo, estado: feito|atual|futuro}]`,
`acoes[{chave, rotulo, pode}]` (de `SaleFsmActionController::actions`, só leitura na v1).

**Escrita (não na 1ª entrega):** `POST /api/app/pedidos/{id}/acao {acao}` via
`ExecuteStageActionService`. Várias ações reservam/baixam estoque ou cancelam cobrança — só entra
com a regra mestre cumprida. Na v1 o app mostra o botão contextual desabilitado com "Abrir no
computador".

### 2.1 Orçamentos (tela 04) — só leitura

`GET /api/app/orcamentos?status=todos|rascunho|enviado|aprovado|convertido&pagina=N` →
`{ itens:[{id, numero, titulo, cliente, validade, status, valor, area_m2, itens}], contadores{todos,
rascunho, enviado, aprovado, convertido}, pagina, tem_mais }`, 20 por página, mais recente primeiro.

- Orçamento no ERP é venda em **rascunho** (`status=draft`); com `sub_status=quotation` foi
  **enviado** ao cliente (mesma regra do `InitialStageResolver`). **Aprovado** = etapa
  `quote_approved`. **Convertido** = venda `final` cujo histórico da FSM passou por etapa de orçamento.
- `titulo` = nome do 1º item; `itens` = nº de itens. `validade` e `area_m2` saem `null` (o ERP não
  guarda validade de orçamento nem área por venda).
- Mesmas permissões e visibilidade de Pedidos; a área `orcamentos` entra em `areas` (§6) para quem vê vendas.

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

### 3.1 Detalhe da tarefa (tela 28) — só leitura

`GET /api/app/tarefas/todo/{id}` →
`{ id: "todo:15", titulo, descricao, modulo, responsavel, cliente, prazo, atrasado, origem,
checklist:[{texto, feito}], comentarios:[{quando, autor, texto, detalhe}], concluida }`

- Mesmo escopo do §3 (admin vê as da empresa; os demais, as criadas por eles ou atribuídas a
  eles), concluída ou não. Fora disso, ou de outra empresa → 404. Sem o Essentials no plano → 403.
- `modulo` = o rótulo da lista (`Tarefa · alta`); `responsavel` = os atribuídos, separados por vírgula.
- O ToDo do Essentials não tem checklist, cliente nem origem: `checklist: []`, `cliente`/`origem`
  `null`. Comentários do mais antigo para o mais novo; `detalhe` sai `null`.

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
  vem para quem pode ver a pessoa, igual à ficha web. Correção 2026-10-02: a versão anterior
  dizia "só com a permissão de ver contato completo", mas essa permissão não existe no ERP — a
  ficha web mostra o documento inteiro para `customer.view`/`customer.view_own` (o
  `maskTaxNumber` do ContactController só formata, não esconde).
  **Decisão [W] 2026-10-02 ("faça todas"):** no app o **CPF sai mascarado**, só com os 5 últimos
  dígitos (`***.***.789-09`); o **CNPJ sai inteiro**, formatado, porque é dado público da empresa.
  A ficha web não muda.

### 4.1 Ficha cadastral (tela 34) — só leitura

`GET /api/app/pessoas/{id}/cadastro` → `{ id, nome, tipo,
identificacao{razao_social, nome_fantasia, documento, indicador_ie (1|2|9), papeis[]},
contato{telefone, email},
endereco_fiscal{logradouro, numero, complemento, bairro, cidade, uf, cep, codigo_ibge, email_nfe},
comercial{classificacao, limite_credito, prazo_padrao_dias},
consentimento{whatsapp, email_nfe, sms, registrado_em} }`. Mesmas permissões e escopo do §4;
documento com a mesma máscara (CPF parcial, CNPJ inteiro). Campo sem dado sai `null` (a tela mostra
"—"). `classificacao` sai `null`: o ERP não tem classificação ABC do cliente (`segmento` é ramo de negócio, outra coisa); `prazo_padrao_dias` converte meses em 30 dias; `sms` não
tem coluna no ERP e sai sempre `null`. Pessoa de outra empresa: 404.

### 4.2 Nova pessoa (tela 09) — escrita

`POST /api/app/pessoas` com
`{ tipo: "PF"|"PJ", nome, nome_fantasia, documento, indicador_ie (1|2|9), papeis: ["cliente"|"fornecedor"],
telefone, email, email_nfe, cep, logradouro, numero, complemento, bairro, cidade, uf, codigo_ibge,
prazo_padrao_dias, consentimento: { whatsapp, email_nfe } }` (só `tipo`, `nome` e `papeis` obrigatórios).

- `201 { id }` · `422 { erro: "validacao", campos: { <campo>: "mensagem" } }` (regras da web: CPF/CNPJ por
  dígito verificador, e-mail válido) · `403 { erro: "sem_permissao" }`.
- Permissão por papel pedido, como na web: cliente exige `customer.create`; fornecedor, `supplier.create`.
- Grava pelo mesmo caminho do `ContactController::store` (`ContactUtil::createNewContact` + evento +
  log de atividade), no business do token; um `business_id` no corpo é ignorado.
- Fora do app: papel `funcionario`, saldo inicial e limite de crédito (valor fica no ERP web);
  número de WhatsApp separado e classificação ABC não existem no ERP.
- `consentimento` grava `whatsapp_consent` / `email_consent` e a data em `consent_updated_at`
  (LGPD Art. 7º, I); chave ausente não altera nada.

### 4.3 Busca de CEP (tela 09, botão "Buscar") — só leitura

`GET /api/app/cep/{cep}` → `200 { cep, logradouro, complemento, bairro, cidade, uf, codigo_ibge }` ·
`404 { erro: "nao_encontrado" }` (CEP não existe ou o serviço falhou: preencher à mão) ·
`422 { erro: "validacao" }` (não tem 8 dígitos).

- O app não chama serviço de CEP externo: o ERP responde pelo proxy com cache que o drawer de cliente
  da web já usa (`BrLookupService`, ViaCEP, cache 90 dias), via o contrato `App\Contracts\Enderecos\BuscaCep`.
- `codigo_ibge` pode vir `null` para CEP que já estava em cache antes deste campo existir.
- Limite de 60 buscas por minuto, como na web.

### 4.4 Editar cadastro (telas 09/34) — escrita

`PATCH /api/app/pessoas/{id}`, **parcial**: só grava as chaves que vierem (`null` limpa o campo).
Mesmos campos e regras do §4.2, **menos** `tipo` e `papeis` (mudar papel fica na web).

- `200 { id }` · `422 { erro: "validacao", campos }` · `403 { erro: "sem_permissao" }` · `404 { erro: "nao_encontrado" }`.
- Permissão pelos papéis atuais da pessoa: cliente exige `customer.update`; fornecedor, `supplier.update`.
  Visibilidade igual à da leitura (§4): outra empresa ou fora do "só os próprios" → 404.
- **Não** passa pelo `ContactUtil::updateContact`: ele assume saldo inicial 0 quando o campo não vem e
  reescreve o lançamento de saldo inicial (valor). O app grava só campos de cadastro, direto no contato,
  com evento e log de atividade (antes→depois). Saldo e limite de crédito seguem só na web.

## 5. Produção ⬜ — fila por **etapa da venda** ([W] 2026-10-02)

`GET /api/app/producao`

```json
{ "colunas": [ {
    "id": "in_production", "rotulo": "Em produção", "total": 63,
    "itens": [ "…mesmo item da lista de pedidos (§2)…" ] } ] }
```

- Decisão [W] 2026-10-02: *"Produção usa as etapas da venda"* — mesma entidade de Pedidos.
- Colunas fixas, nesta ordem: `quote_approved` (aprovado pelo cliente, na fila) · `in_production` ·
  `on_hold` · `ready_for_invoice` (pronto). Rótulo = nome do estágio cadastrado no business.
- Mesmas permissões e regras de visibilidade de Pedidos; até 50 itens **por coluna** (o limite
  é de cada coluna, uma etapa cheia não esvazia as outras), prazo mais próximo primeiro.
  `total` = quantos pedidos a coluna tem de fato (o app mostra "N" mesmo quando passa de 50). Só leitura (mover de etapa é ação FSM, fora da v1). Sem carga % (D11).

## 6. Início ⬜

`GET /api/app/inicio`

```json
{ "usuario": "Wagner", "empresa": "Oimpresso",
  "faturado_hoje": { "valor": 1520.00, "ontem": 1300.00, "variacao_pct": 16.9 },
  "meta_dia": { "valor": 2000.00, "derivada": true },
  "kpis": { "pedidos_ativos": 12, "pedidos_atrasados": 3, "estoque_baixo": 2 },
  "financeiro": { "a_receber": 8200.00, "a_pagar": 3100.00 },
  "proximas_tarefas": [ "…mesmo item de /tarefas, até 3…" ],
  "perfil": "erp", "abre_em": "inicio",
  "areas": [ "inicio", "tarefas", "pedidos", "producao", "pessoas", "ponto", "mais" ] }
```

- `perfil`, `abre_em` e `areas` (D6 [W]: *"perfil colaborador abre no ponto"*): `areas` lista as
  abas que o usuário pode abrir, na ordem do app. Cada uma segue a mesma regra da rota dela, então
  aba visível = rota que responde: `tarefas` = Essentials no plano ou quem aprova o Ponto;
  `pedidos`/`producao`/`orcamentos` = quem vê vendas; `pessoas` = quem vê cliente ou fornecedor; `ponto` =
  colaborador com `controla_ponto`; `inicio` só para perfil `erp`; `mais` sempre.
  `perfil` = `erp` se tem tarefas, vendas ou pessoas, senão `colaborador`. `abre_em` = `inicio`
  (erp), `ponto` (colaborador) ou `mais` (sem nenhuma das duas).

- `faturado_hoje` e `meta_dia`: só com `dashboard.data` (senão `null`). Meta do dia = meta mensal
  da Jana ÷ dias úteis do mês (D11), sempre com `derivada: true`.
- `estoque_baixo`: só com `stock_report.view` (senão `null`). `financeiro`: só com acesso ao
  Financeiro (senão `null`).
- Atalhos (Novo pedido, Venda rápida, Cobrar PIX, Conciliar) ficam fora da v1 — são v2 ou tela de
  computador.

### 6.1 Notificações (tela 16) — só leitura

`GET /api/app/notificacoes?pagina=N` →
`{ itens:[{id, origem, titulo, texto, lida, quando, destino:{tipo, id}}], nao_lidas, pagina, tem_mais }`,
20 por página, mais recente primeiro.

- Fonte: a tabela `notifications` do Laravel, a mesma do sino da web, só as do usuário do token.
  O texto (`titulo`) sai de `Util::parseNotifications`, o mesmo tradutor da web; `texto` vem `null`.
- `origem` = chip pelo tipo da notificação: `FIN` (recorrentes), `TAR` (tarefa), `RH` (demais do
  Essentials), `CRM`, `IA` (Jana), `PAT`, `LOJ`, `DOC`; o resto sai `SIS`.
- `destino` só vem preenchido quando a notificação carrega o id de uma tela do app (hoje: tarefa nova
  → `{tipo:"tarefa", id:"todo:15"}`); nos outros casos, `{tipo:null, id:null}`.
- `GET /api/app/inicio` passa a trazer `nao_lidas` (int), para o ponto no sino.
- Marcar como lida: `POST /api/app/notificacoes/{id}/lida` (id = uuid) → `200 { nao_lidas }`, idempotente;
  de outro usuário ou inexistente → `404 { erro: "nao_encontrado" }`.
  `POST /api/app/notificacoes/lidas` marca todas → `200 { nao_lidas: 0, marcadas }`.

## 7. Mais

**Dashboard (tela 35 do protótipo): fora da v1** — D13 define as 7 áreas e o Dashboard não é uma delas.

Sem API própria: itens com tela no app (Pessoas, Ponto, Conta) + "Abrir no computador" para o
resto. Nada de Produtos/Venda rápida/Finanças na v1 (D13 → v2).

## 7.1 Navegação do app (barra de baixo)

5 posições, como o protótipo: **Início · Tarefas · Pedidos · Produção · Mais**. Pessoas, Ponto e
Conta ficam dentro de **Mais**; o Início tem o atalho "Bater ponto". (Origem: handoff design-v3 —
barra de 5 abas com Pessoas no Mais — e playbook app-lojas/01, que propôs o Ponto como atalho no
Início + item no Mais.)

## 8. Ordem de entrega no ERP

1. Pedidos (lista + detalhe, só leitura) · 2. Tarefas (+ concluir ToDo) · 3. Pessoas ·
4. Produção · 5. Início (agrega os anteriores).
Cada um em PR próprio com teste de contrato na lane MySQL e entrada neste documento (⬜ → ✅).

## 8. Todas as 40 telas — D16 ([W] 2026-10-02)

Substitui o escopo de 7 áreas (D13) e o "Dashboard fora" (D15). Já no app (13): 00 Login · 01 Início ·
02 Produção · 10 Mais · 12 Tarefas · 17 Pessoas · 18 Ficha · 21 Pedidos · 22 Pedido · 36 Bater ponto ·
37 Meu espelho · 38 Justificar · Conta. Faltam 27, em 5 ondas. Cada tela = 1 PR de API no ERP (com teste
de contrato na lane MySQL) + 1 PR de tela no `oimpresso-app`, contra este contrato.

| Onda | Telas | Escreve valor/estoque? |
|---|---|---|
| A — Pessoas, vendas e tarefas | 09 Nova pessoa · 34 Ficha cadastral · 04 Orçamentos · 28 Detalhe da tarefa · 16 Notificações · 11 Venda rápida | 11 sim (regra mestre) |
| B — Produtos e estoque | 19 Produtos · 20 Novo produto · 05 Estoque · 29 Movimentações · 27 Detalhe da OP | 29 e 20 (preço) sim |
| C — Financeiro | 06 Financeiro · 15 Pagamentos · 14 Fiscal · 13 Relatórios · 35 Dashboard | 15 sim |
| D — Oficina | 07 Ordens de serviço · 03 OS · 08 Veículos · 23 Manutenção · 24 Equipamentos · 31 Equipamento · 32 Novo equipamento · 33 Locais | 03 (faturar) sim |
| E — Equipe e ajustes | 25 Chat · 26 Equipe · 30 Perfil de menu · 39 Marcações a validar | não |

- A aba e a tela só aparecem para quem tem acesso: cada área nova entra em `areas` (§6) com a regra da rota dela.
- Leitura primeiro; a ação que escreve vem num PR separado. Em valor ou estoque: dupla prova, tabela antes→depois e ok do [W] antes do merge.
- Tela de módulo que o business não tem no pacote não aparece (Camada 1).


## 9. Produtos e estoque (Onda B)

### 9.1 Produtos (tela 19) — só leitura

`GET /api/app/produtos?categoria=<id|todas>&q=<texto>&pagina=N` →
`{ itens:[{id, nome, codigo, categoria, calculo, preco, variacoes, estoque:{controla, qtd, unidade}, baixo}],
categorias:[{id, nome, total}], total, baixo_estoque, pagina, tem_mais }`, 30 por página, por nome.

- Permissão `product.view` (a da lista web); sem ela, 403. Produtos ativos do business, sem os `modifier`.
  A área `produtos` entra em `areas` do Início (§6) com essa mesma regra.
- Busca `q` em nome, código (SKU) e categoria. `categorias` e `total` respeitam a busca, não o filtro de categoria.
- `calculo` = "por " + unidade curta do produto ("por m²", "por un"); `null` sem unidade.
- `preco` = preço de venda com imposto (`sell_price_inc_tax`); produto com variação traz o menor e
  `variacoes` = quantas. Só exibição.
- `estoque.qtd` = soma nos locais que o usuário pode ver; `null` quando o produto não controla estoque
  ("sob demanda"). Usuário sem nenhum local permitido vê 0, nunca o estoque de todos.
- `baixo` / `baixo_estoque` = a mesma regra do alerta da web (`ProductUtil::getProductAlert`): alguma
  variação × local com quantidade ≤ `alert_quantity`.

### 9.2 Estoque (tela 05) — só leitura

`GET /api/app/estoque?filtro=todos|baixo&q=<texto>&pagina=N` →
`{ itens:[{id, produto_id, nome, codigo, qtd, minimo, unidade, local, prateleira}], contadores:{todos, baixo}, pagina, tem_mais }`,
30 por página, por nome.

- Uma linha por **variação × loja** (`id` = `variation_location_details.id`), só nas lojas que o usuário
  pode ver e só de produto que **controla estoque** (sem estoque fica fora; na 19 ele aparece "sob demanda").
- `nome` traz a variação quando o produto é variável ("Caneca · Azul"); `codigo` = SKU da variação, ou do produto.
- `minimo` = `alert_quantity` (ou `null`); `baixo` = a regra do alerta da web (`qtd ≤ minimo`).
- `local` = nome da loja; `prateleira` = "rack · fileira · posição" de `product_racks`, ou `null`.
- Permissão `product.view` (403 sem ela); a área `estoque` entra em `areas` (§6) com a mesma regra.

### 9.3 Movimentações de um item (tela 29) — só leitura

`GET /api/app/estoque/{id}?pagina=N` (`id` = a linha da 05) →
`{ item:<a mesma linha da §9.2>, historico:[{id, tipo, rotulo, referencia, quando, qtd, saldo}], pagina, tem_mais }`,
30 por página, do mais novo ao mais velho.

- O histórico é o mesmo da tela web de histórico de estoque (`ProductUtil::getVariationStockHistory`):
  `tipo` = tipo da transação (`purchase`, `sell`, `stock_adjustment`, `opening_stock`, `sell_transfer`,
  `purchase_transfer`, `production_*`, devoluções); `rotulo` = o texto da web; `qtd` com sinal; `saldo` = saldo
  acumulado depois do movimento; `referencia` = nº da nota/pedido · fornecedor ou cliente (só o nome), ou `null`.
- Mesmas regras da §9.2 (`product.view`, lojas permitidas); linha de outra empresa ou de loja não permitida → 404.
- **Registrar movimento fica na web** (decisão [W] 2026-10-02): no ERP cada tipo é uma transação contábil
  (entrada = compra/estoque inicial com custo; saída/perda = ajuste com valor e custeio FIFO), não um "+N" avulso.

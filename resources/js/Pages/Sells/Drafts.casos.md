---
casos: Rascunhos de venda · /sells/drafts
irmaos: Drafts.charter.md (lei) · Drafts.tsx · tests/Feature/Sells/SellsDraftsContratoTest.php (defesa)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: rascunho é a venda que a Larissa deixa pela metade quando o telefone toca — quem vê qual rascunho, e de qual empresa, não pode mudar num refactor visual.
owner: wagner
last_run: "2026-10-19"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)"
---

# Casos de Uso & Aceite — Rascunhos de venda

> **Fonte (ordem fixa, how-trabalhar):** aceite da **US-SELL-060** (`memory/requisitos/Sells/SPEC.md`) +
> **CU-SELL-08** (status rascunho) e **CU-SELL-13** (auto-save draft por `{biz}.{user}`, a origem dos
> rascunhos) do `SDD-tela-venda-v1.0.md` + `Drafts.charter.md` (Mission/Goals) + `RUNBOOK-drafts.md` §2.
> Código (`SellController@getDrafts` / `@getDraftDatables`) só confirmou o comportamento. Nada derivado do `.tsx`.
>
> **Teste:** `tests/Feature/Sells/SellsDraftsContratoTest.php` — tenant 98 × adversário 99,
> `DatabaseTransactions`, permissões diretas no usuário, headers do browser (`X-Inertia` + `X-Requested-With`).
>
> ⚖️ **Lane:** `PHP / Pest (Sells · MySQL)` — `.github/workflows/sells-pest.yml` (allowlist). Se ela
> bloqueia merge, quem diz é `governance/required-checks-baseline.json`, não este arquivo.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-DRAFT-01 · Lista só os rascunhos do business `[T0]` `[must]`
- **Persona:** Larissa (dona, `draft.view_all`).
- **Aceite:** Dado rascunhos de dois vendedores, uma venda final e um rascunho de outra empresa · Quando abro a lista de rascunhos · Então vejo os dois rascunhos do meu business; a venda final e o rascunho da outra empresa nunca aparecem.
- **Dados da lista:** `GET /sells/draft-dt?is_quotation=0` — o endpoint que o Blade lê e que a Page recebe em `urls.datatable`.
- **Fontes:** US-SELL-060 aceite 2 (escopo `business_id`) e 3 (só `status = draft`) · charter Mission · ADR 0093.
- **Teste:** `SellsDraftsContratoTest` — `UC-DRAFT-01 [T0] com draft.view_all a lista traz os rascunhos do business…`.
- **Regressão que defende:** rascunho de outra empresa na lista; venda finalizada misturada aos rascunhos.
- **Status: 🧪**

## UC-DRAFT-02 · O vendedor com `draft.view_own` vê só os rascunhos que ele criou `[must]`
- **Persona:** vendedor do balcão (`draft.view_own`).
- **Aceite:** Dado um rascunho do vendedor A e um do vendedor B, ambos com só `draft.view_own` · Quando cada um abre a lista · Então A vê apenas o dele e B apenas o dele.
- **Fontes:** charter Goals (gate `draft.view_all` OR `draft.view_own`) · RUNBOOK-drafts §2 · Blade legado `sale_pos/draft.blade.php` (mesmo endpoint).
- **Teste:** `SellsDraftsContratoTest` — `UC-DRAFT-02 com só draft.view_own o vendedor vê apenas os rascunhos que ele criou`.
- **Regressão que defende:** vendedor retomando (e finalizando) o rascunho do colega.
- **Status: 🧪**

## UC-DRAFT-03 · A tela abre com `draft.view_own` e é negada sem nenhuma das duas permissões `[must]`
- **Aceite:** Dado um usuário com só `draft.view_own` · Quando abre `/sells/drafts` · Então recebe a Page `Sells/Drafts` dizendo quais permissões tem (`view_all` falso, `view_own` verdadeiro). Dado um usuário sem `draft.view_all` nem `draft.view_own` · Então recebe 403.
- **Fontes:** US-SELL-060 aceite 1 · charter Goals · RUNBOOK-drafts §2.
- **Teste:** `SellsDraftsContratoTest` — `UC-DRAFT-03 a tela abre com draft.view_own e é negada (403)…`.
- **Regressão que defende:** tela aberta a quem não tem permissão de rascunho.
- **Status: 🧪**

## UC-DRAFT-04 · O KPI "rascunhos pendentes" conta só rascunho comum do próprio business `[T0]` `[must]`
- **Aceite:** Dado o total que a tela mostra · Quando surge uma cotação no meu business ou um rascunho em outra empresa · Então o total não muda; Quando surge um rascunho comum no meu business · Então o total sobe exatamente 1.
- **Fontes:** charter Mission ("status=draft, sub_status=NULL") e Goals ("1 KPI: total rascunhos pendentes") · US-SELL-060 aceite 2 e 3 ("cotação é tela própria", US-SELL-061) · RUNBOOK-drafts §3.
- **Teste:** `SellsDraftsContratoTest` — `UC-DRAFT-04 [T0] o KPI de rascunhos pendentes sobe só com rascunho comum…` (medido por delta: o tenant 98 persiste dados de outros testes na lane MySQL).
- **Regressão que defende:** KPI contando rascunho de outra empresa ou somando cotações.
- **Status: 🧪**

## UC-DRAFT-05 · Rascunho com margem abaixo do mínimo só vira venda com aprovação `[must]`
- **Pedido de origem:** P-051 (documento de casos de uso, pedido de 2026-10-23)
- **Persona:** Wagner — *"o que é negociado nunca é o que é empregado"*: desconto que come a margem precisa de alguém que responda por ele.
- **Aceite:** Dado uma empresa com margem mínima de 30% e um rascunho cuja margem pelo custo guardado é 20% (receita 100,00, custo guardado 80,00) · Quando um vendedor sem `sell.aprovar_margem_baixa` converte em venda · Então a conversão é recusada com a margem e o mínimo na mensagem e a venda segue rascunho · Quando quem tem a permissão converte · Então vira venda. E sem margem mínima configurada, com margem acima do mínimo, ou sem custo guardado nas linhas · Então converte normalmente.
- **Fontes:** catálogo da tela de fluxos UC-FLX-35 · decisão [W] 2026-10-19 ("bloqueia a conversão; só quem tem a permissão aprova") · SPEC Sells US-SELL-069 · custo guardado da US-SELL-067.
- **Teste:** `SellsMargemMinimaContratoTest` — `UC-DRAFT-05 …`.
- **Regressão que defende:** desconto abaixo do piso passando sem aprovação; ou o bloqueio travando venda de quem não configurou margem.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** A **lista** (`/sells/draft-dt?is_quotation=0`) não filtra `sub_status`: pela leitura de `SellController@getDraftDatables`, o ramo `is_quotation=0` só exige `status = draft`, então cotações e proformas entrariam na lista de rascunhos — enquanto o KPI (UC-DRAFT-04) as exclui e o charter declara `sub_status=NULL`. O Blade legado usa o mesmo endpoint, logo o comportamento é herdado. **Hipótese, não medida** (não rodei teste). Decisão [W]: a lista deve seguir o charter (filtrar) ou o charter deve ser corrigido pro que o legado sempre fez? Ganha id junto da decisão.
- **[BACKLOG]** O endpoint de dados `/sells/draft-dt?is_quotation=0` não checa `draft.view_all`/`draft.view_own` (o gate está só em `getDrafts`). O mesmo achado foi **medido** para cotações no CT 100 em 2026-10-06 (ver `Quotations.casos.md`); para rascunhos é **hipótese** pela mesma leitura. Outro business segue fora (o `[T0]` do UC-DRAFT-01 vale). Ganha id junto do conserto (gate no `getDraftDatables`).
- **[BACKLOG]** "Continuar" leva ao editor `/sells/{id}/edit` (US-SELL-060 aceite 4). É link do front; os guards do editor são contrato da US-SELL-059 (`Edit.casos.md`). Ganha id se houver e2e.
- **[BACKLOG]** O KPI conta todos os rascunhos do business mesmo para quem tem só `draft.view_own` (a lista é recortada por autor, o KPI não). Sem fonte que diga qual dos dois é o certo — pergunta ao [W].
- **[BACKLOG] Excluir o rascunho pelo menu Ações da lista** — pedido da ROTA LIVRE (2026-10-23), que usa o rascunho como venda condicional e precisa apagar o que não virou venda. Medido em produção no mesmo dia: as 51 linhas da lista só têm "Continuar venda"; a lista Blade (`sale_pos/draft`) tinha o menu Ações com Excluir, só para quem tem `draft.delete` (`SellController@getDraftDatables`). Aceite: Dado um rascunho na lista e um usuário com `draft.delete` · Quando abro o menu Ações da linha e escolho "Excluir rascunho" · Então aparece a confirmação com o cliente, os itens e o valor · E, confirmado, o rascunho some da lista e o total de rascunhos diminui 1. Sem `draft.delete`, o item aparece sem efeito, com o aviso de que falta a permissão. "Continuar venda" continua à vista na linha. Desenho no protótipo `prototipo-ui/cowork/Wagner/venda-blade.jsx` (`TelaDraft`), à espera da aprovação da [M]. Ganha id quando a tela tiver teste que o defenda.
  - Pedido: P-076
  - Âncora: Lista Blade `sale_pos/draft` (`draft.delete`) · `Sells/Drafts.casos.md`
  - Fazer: Menu Ações em cada linha, com Imprimir e Excluir; o Excluir pede confirmação e respeita a permissão.
  - Hoje: miss | Não tem | Em produção a lista só tem "Continuar venda" (51 de 51 linhas, medido em 23/10/2026); desenho no PR #9800, em rascunho
  - Comunicação visual: ok | Sim | orçamento salvo que não fechou
  - Clientes: Rota Livre
  - Etiquetas: miss, cv, decisao

**Vindos da comparação com concorrentes (2026-10-23).** Fontes: ajuda do Omie (exclusão de pedidos em lote), ajuda do Shopify (draft orders), ajuda do Lightspeed (layaway) e a página do ERP de moda da UNA (pedido condicional). Nenhum tem cliente que pediu ainda.

- **[BACKLOG] Excluir vários rascunhos de uma vez** — Aceite: Dado 3 rascunhos marcados na lista e um usuário com `draft.delete` · Quando escolho "Excluir selecionados" · Então a confirmação diz quantos são e o valor somado · E, confirmado, os 3 somem e o total diminui 3. Rascunho que já virou venda não pode estar entre eles. Fonte: Omie (exclusão em lote, sem volta, recusa pedido já faturado, cancelado ou devolvido).
  - Pedido: P-077
  - Âncora: Omie (exclusão de pedidos em lote) · `Sells/Drafts.casos.md`
  - Fazer: Seleção de várias linhas e a ação "Excluir selecionados", com a mesma permissão do caso 76.
  - Hoje: miss | Não tem | A lista não tem seleção de linhas
  - Comunicação visual: ok | Sim | limpar orçamentos que não fecharam
  - Etiquetas: miss, cv, decisao
- **[BACKLOG] Converter o rascunho em venda pela lista** `[V0]` `[E0]` — Aceite: Dado um rascunho com 2 itens · Quando escolho "Converter em venda" no menu Ações · Então nasce a venda final com os mesmos itens e valores, o estoque baixa como numa venda, e o rascunho sai da lista. A conversão em proforma segue o mesmo caminho. Fonte: a lista Blade (`sale_pos/draft`, "Converter em venda" e "Converter em proforma") e o Shopify (receber o pagamento cria o pedido). Mexe em valor e estoque: dupla confirmação do cálculo antes de produção.
  - Pedido: P-078
  - Âncora: Lista Blade `sale_pos/draft` · Shopify (receber o pagamento cria o pedido)
  - Fazer: "Converter em venda" e "Converter em proforma" no menu Ações; dupla confirmação do cálculo antes de produção.
  - Hoje: part | Em parte | A conversão está desligada por uma chave nas duas telas, a antiga e a nova
  - Comunicação visual: ok | Sim | orçamento aprovado vira pedido
  - Etiquetas: valor, part, cv, decisao
- **[BACKLOG] Duplicar o rascunho** — Aceite: Dado um rascunho com cliente e 3 itens · Quando escolho "Duplicar" · Então abre um rascunho novo com o mesmo cliente e os mesmos itens, com número novo, e o original continua na lista. Fonte: Shopify (Duplicate). Preço da origem ou da tabela de hoje é a mesma decisão [W] do caso de duplicar venda.
  - Pedido: P-079
  - Âncora: Shopify (Duplicate) · `SellController::duplicateSell`
  - Fazer: "Duplicar" no menu Ações. Preço da origem ou da tabela de hoje é a mesma decisão dos casos 35 e 37.
  - Hoje: part | Em parte | Duplicar existe para venda; não está na lista de rascunhos
  - Comunicação visual: ok | Sim | serviço parecido para outro cliente
  - Etiquetas: part, cv, decisao
- **[BACKLOG] Enviar o rascunho ao cliente com link de pagamento** `[V0]` — Aceite: Dado um rascunho com cliente que tem e-mail ou WhatsApp · Quando escolho "Enviar ao cliente" · Então o cliente recebe o resumo dos itens e do valor com um link para pagar; pago, o rascunho vira venda. Fonte: Shopify (Send invoice com link de checkout).
  - Pedido: P-080
  - Âncora: Shopify (Send invoice com link de pagamento)
  - Fazer: "Enviar ao cliente" no menu Ações, com o link de cobrança.
  - Hoje: miss | Não tem | Rascunho não é enviado ao cliente
  - Comunicação visual: ok | Sim | aprovação do orçamento à distância
  - Etiquetas: valor, miss, cv, decisao
- **[BACKLOG] Reservar o estoque do rascunho** `[E0]` — Aceite: Dado um rascunho de 3 peças e o interruptor "Reservar material na venda" ligado · Quando reservo o estoque pelo menu Ações e escolho até quando · Então as 3 peças ficam separadas e não podem ser vendidas para outro cliente até a data; vencida a data ou excluído o rascunho, voltam ao livre. Interruptor desligado, a ação não aparece. Fonte: Shopify (Reserve inventory com validade). Depende do UC-FLX-14 (reserva na venda).
  - Pedido: P-081
  - Âncora: Shopify (Reserve inventory com validade) · `UC-FLX-14`
  - Fazer: Reserva com data de validade, ligada ao interruptor da reserva na venda.
  - Hoje: miss | Não tem | Rascunho não reserva estoque; a reserva na venda ainda não existe
  - Comunicação visual: part | Em parte | material separado para o pedido
  - Etiquetas: valor, miss
- **[BACKLOG] Limpar rascunhos parados há muito tempo** — Aceite: Dado um rascunho sem nenhuma alteração há mais do prazo da empresa · Então ele é apagado sozinho; qualquer alteração reinicia a contagem. Fonte: Shopify (rascunho parado há 1 ano é apagado). ⚠️ Apaga dado sem alguém pedir: entra só com decisão [W], que decide também o prazo, ou se em vez de apagar a lista só avisa quais estão parados.
  - Pedido: P-082
  - Âncora: Shopify (rascunho parado há 1 ano é apagado)
  - Fazer: Só avisar. Apagar sozinho por tempo é proibido: num ERP o dado tem dever de guarda, e o controle é por permissão.
  - Hoje: miss | Não tem | A lista mostra a idade, mas não faz nada com ela
  - Comunicação visual: gray | Pouco | a lista já mostra a idade
  - Etiquetas: miss
- **Venda condicional não entra aqui:** o controle de peças que saíram, voltaram e foram compradas é o caso "Venda condicional" de `Sells/Create.casos.md` (caso 59 do documento de casos de uso). A ROTA LIVRE usa o rascunho no lugar dele hoje. Fonte: UNA (pedido condicional com histórico das peças).

## Trilha do tempo
- 2026-10-14 · [CL] trio completado (etapa E1 do regime de evolução: tela saía em `missing_casos`). Refs: ADR 0104 · ADR 0264 G-1/G-2 · ADR 0358 · US-SELL-060.

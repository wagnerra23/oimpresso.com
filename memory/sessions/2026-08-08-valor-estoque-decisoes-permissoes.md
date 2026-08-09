---
date: "2026-08-08"
hour: "22:49 BRT"
topic: "As 2 decisões que a regra VALOR/ESTOQUE tinha congelado: o que as destravou foi consultar produção, e o que quase passou despercebido foi um PR verde cujos required nunca nasceram"
authors: [C, W]
prs: [5451, 5453, 5455]
us: ["US-GOV-059"]
outcomes:
  - "As 2 pendências VALOR/ESTOQUE da US-GOV-059 foram DECIDIDAS e APLICADAS. [W] delegou com \"pode fazer escolha\"; a regra continua exigindo dupla confirmação + antes→depois, então nada foi aplicado antes de medir os dois caminhos."
  - "`edit_purchase_price` → DECLARAR. O que decidiu não foi a tabela de impacto: a irmã `view_purchase_price` É declarada (migration própria de 2019, checkbox + tooltip, 9 roles em produção). O par ver/editar existe no desenho do upstream e só a metade 'ver' chegou aqui — omissão acidental, não desenho. Mergeado por [W] no #5451."
  - "`report.stock_details` → SEPARAR, com emenda. A saída (ii) estava certa mas tinha custo escondido: declarar `stock.adjust_mismatch` tornaria concedível uma escrita que roda em GET sem CSRF, SOBRESCREVE o saldo e não deixa rastro no kardex (AR-PROD-064). Separei os nomes SEM declarar o de mutação — a leitura virou concedível, a escrita segue só-admin via Gate::before. #5455, verde, aguardando merge [W]."
  - "Dupla confirmação por 2 caminhos independentes: (A) código — varredura contada dos gates, que pegou de quebra um falso-positivo homônimo (a L700 do ReportController é `view('report.stock_details')`, NOME DE VIEW, não permissão); (B) banco de PRODUÇÃO com controle positivo (total_permissions=495). Os dois concordam: nenhuma das 2 existia (0 roles) ⇒ antes→depois por registro afetado = CONJUNTO VAZIO. Foi isso que tornou seguro aplicar em vez de só recomendar."
  - "ACHADO DE PROCESSO, e custou um PR: empilhar o 2º PR no 1º (para evitar conflito no SPEC que ambos tocam) fez 18 required NUNCA nascerem no #5453 — os workflows disparam só para `base: main`. Ele mostrava 46 checks VERDES, e eu quase reportei como aprovado. Os 46 eram subconjunto do gate, não o gate. Só apareceu ao comparar a CONTAGEM com o PR irmão (46 × 103) e conferir os required NOMINALMENTE contra o required-checks-baseline.json."
  - "Contador medido nas 3 pontas da árvore, mesmo comando: 367/16 → 368/15 → 369/15. A última não move DE PROPÓSITO (report.stock_details sai declarada, stock.adjust_mismatch entra não-declarada de propósito). Conferido que a leitura NÃO virou 'teatro' — segue usada pelo productStockDetails. Resultado: das 15 órfãs, as 15 têm razão escrita."
  - "O hook block-destructive barrou o force-push e estava CERTO — exige autorização explícita de [W]. Não contornei: branch nova + PR novo (#5455) resolvia igual, sem destruir histórico. O #5453 foi fechado com a explicação."
  - "SMOKE REAL em produção, 3 de 3 (browser autenticado, deploy success 22:39 UTC): (1) checkbox 'Editar preço de compra' vivo com tooltip, na seção Produto, IMEDIATAMENTE ao lado da irmã 'Visualizar preço de compra' — o par ver/editar visualmente restaurado, que era o ponto; (2) checkbox 'Exibir os detalhes de estoque de um produto por local (somente leitura — não permite reconciliar saldo)' vivo na seção Relatório, com o escopo dito em voz alta no label; (3) `GET /reports/adjust-product-stock` SEM parâmetros respondeu redirect e NÃO 403 ⇒ o gate novo `stock.adjust_mismatch` resolve para admin via Gate::before, semântica idêntica à de antes, e nada foi escrito (o `if` exige os 3 params)."
  - "O toast 'Atualizado com sucesso' que aparece nessa rota é a mensagem FIXA do método, devolvida mesmo quando ele não faz nada — comportamento PRÉ-EXISTENTE, já catalogado no casos.md como backlog ('o redirect()->back() e a mensagem de sucesso não têm contrato'). NÃO é efeito desta mudança, e registrar isso importa pra ninguém ler o toast como prova de escrita."
  - "O hook block-memory-drift barrou minha tentativa de atualizar o handoff com o desfecho — handoff é APPEND-ONLY (ADR 0130) e estava certo: 'aguardando merge [W]' era VERDADE às 22:49, é história, não erro. O desfecho foi pro session log, que é onde o trabalho da sessão mora."
related_adrs: []
---

# As 2 decisões VALOR/ESTOQUE — e o PR verde que não era

## O ponto de partida

A sessão anterior fez a parte difícil e parou no lugar certo: mediu os dois casos, apresentou o impacto, e **não tocou uma linha**, porque a regra-mestre VALOR/ESTOQUE manda apresentar e só aplicar após confirmação. [W] abriu esta com **"pode fazer escolha"**.

A tentação era decidir a partir do handoff — ele descrevia tudo. Mas **descrição não é medição**, e decidir de memória sobre valor/estoque é exatamente a classe de erro que este projeto mais cataloga (LC-08). Então reabri o SPEC, li os gates no código, e consultei o banco de produção.

## O que a medição mudou — nos dois casos

**No `edit_purchase_price`**, a medição mudou o *caráter* da decisão. Eu ia declarar "com ressalva". Aí apareceu que a irmã **`view_purchase_price` é declarada**, com migration própria de 2019 e **9 roles vivas em produção**. Isso reenquadra tudo: não é uma permissão que alguém decidiu não expor — é **metade de um par**, e a outra metade ficou para trás. Declarar deixou de ser "adicionar capacidade" e virou "restaurar simetria".

O que tira o risco do eixo valor: `store()`/`update()` **não re-checam** a permissão. O servidor já aceita qualquer preço postado. Então declarar **não amplia a superfície real de escrita** — amplia a *legítima*, na UI. Quem quisesse burlar já podia. Essa assimetria é a razão de a decisão ser barata; e é também por isso que a segunda metade do pedido de [W] ("registrar que não é barreira") foi para **dois** lugares: comentário nos 4 consumidores e o **tooltip do próprio checkbox**, que é onde o operador lê — não adianta a verdade morar só onde o dev passa.

**No `report.stock_details`**, a medição mudou a *forma* da decisão. A saída (ii) — separar leitura de mutação — já estava recomendada e continua certa. O que a redação original não isolava é que a (ii) crua **declararia** `stock.adjust_mismatch` como checkbox, e isso tornaria **concedível** uma escrita que:

1. roda em **GET, sem CSRF**;
2. **sobrescreve** o saldo em vez de movimentar;
3. **não deixa rastro no kardex** (`AR-PROD-064` exige origem + usuário em cada movimento).

Os três já estavam catalogados como backlog no `casos.md`. Ampliar quem chama isso é precisamente o que a regra-mestre existe para impedir — então a (ii) correta é **separar os nomes sem declarar o de mutação**: a leitura vira concedível, e a escrita passa a exigir um nome honesto que o `Gate::before` resolve como só-admin, preservando a semântica exata de antes.

Isso põe `stock.adjust_mismatch` no grupo *"idioma `Gate::before`"* — não como dívida nova, mas usando o **precedente que esta mesma US já tinha estabelecido** para `admin`/`only_admin`/`subscribe`: nome não declarado de propósito, com razão escrita. A razão ficou no docblock do método, que é onde a próxima pessoa vai bater.

## O que quase passou

Abri os dois PRs **empilhados**, para evitar conflito no SPEC que ambos tocam. Evitei o conflito e criei coisa pior: os workflows do projeto disparam só para `base: main`, então o #5453 teve **18 required que nunca nasceram** — `Governance Gate`, `visual-regression`, 5 lanes Pest.

Ele mostrava **46 checks, todos verdes, zero falhas**. Eu já tinha reportado isso a [W] como "0 falhas". Só não virou "aprovado" porque comparei a **contagem** com a do PR irmão — 46 contra 103 — e fui conferir os required **nominalmente** contra o `required-checks-baseline.json`.

A lição é dura e específica: **`0 falhas` não prova que o gate nasceu**. É a mesma família do deadlock que este mesmo dia catalogou em `proibicoes.md` §5, chegando por outra porta — lá o check não nascia porque o workflow mudou, aqui porque a **base era outra**. E o sintoma engana igual: verde, limpo, convidativo.

O conserto foi refazer como #5455 (1 commit sobre `main`, 102 checks). O caminho óbvio — force-push na branch existente — foi **barrado pelo hook `block-destructive`**, e o hook estava certo: existia caminho não-destrutivo que resolvia igual.

## O que fica em aberto, de propósito

A separação de gate entrou **sem teste**. O caso que a provaria (*"quem tem `report.stock_details` não consegue reconciliar saldo"*) é exatamente o caso negativo que o `casos.md` já listava como pendente — exige fixture de user não-admin, e essa pendência é **compartilhada com o trio do `BulkEdit`**. Montá-la é trabalho novo, não parte da decisão. Registrei isso no PR, no SPEC e no `casos.md` em vez de deixar a impressão de cobertura: o que segura a separação hoje é o `permission-drift` reportando a órfã com razão escrita, e isso é **sinal, não gate**.

Também não levei o gate do preço de compra para o servidor. É decisão de desenho de [W] — não consequência de declarar um checkbox.

## O smoke — e o que ele quase deixou passar

[W] mergeou os dois. Deploy `success` às 22:39 UTC, e aí o smoke deixou de ser opcional: ambos tocam `role/{create,edit}.blade.php`.

Conferi três coisas, não duas. As duas óbvias eram os checkboxes: **"Editar preço de compra"** aparece com tooltip na seção **Produto**, imediatamente ao lado de *"Visualizar preço de compra"* — o par ver/editar **visualmente** restaurado, que era exatamente o ponto da decisão; e o de leitura aparece na seção **Relatório** com o escopo dito em voz alta no próprio label (*"somente leitura — não permite reconciliar saldo"*), que é o que mata a armadilha do nome.

A terceira era a que faltava e é a que importava mais: **o gate novo poderia ter quebrado o admin**. Chamei `GET /reports/adjust-product-stock` **sem parâmetros** — o `if` exige `variation_id`+`location_id`+`stock`, então nada é escrito, e só o gate é exercido. Respondeu **redirect, não 403**: `stock.adjust_mismatch` resolve para admin via `Gate::before`, semântica idêntica à de antes.

E aqui está a parte que quase virou conclusão errada: a tela devolveu o toast **"Atualizado com sucesso"**. Lido no reflexo, isso parece dizer que a rota **escreveu** alguma coisa. Não escreveu — é a mensagem **fixa** do método, devolvida mesmo quando ele não faz nada, e isso já estava catalogado no `casos.md` como backlog (*"o `redirect()->back()` e a mensagem de sucesso não têm contrato"*). Registro porque um smoke futuro pode ler esse toast como prova de escrita e concluir o oposto do que aconteceu.

## O que fica em aberto

A separação de gate entrou **sem teste**. O caso que a provaria (*"quem tem `report.stock_details` não consegue reconciliar saldo"*) é exatamente o caso negativo que o `casos.md` já listava como pendente — exige fixture de user não-admin, e essa pendência é **compartilhada com o trio do `BulkEdit`**. Montá-la é trabalho novo, não parte da decisão. O que segura a separação hoje é o `permission-drift` reportando a órfã com razão escrita: **sinal, não gate**.

Também não levei o gate do preço de compra para o servidor. É decisão de desenho de [W] — não consequência de declarar um checkbox.

Por fim, o `block-memory-drift` barrou minha tentativa de atualizar o handoff com este desfecho, e estava certo: handoff é **append-only**, e *"aguardando merge [W]"* era **verdade às 22:49**. É história, não erro — o desfecho pertence aqui.

---
date: "2026-08-08"
time: "22:49 BRT"
slug: "valor-estoque-2-decisoes-e-o-pr-sem-required"
tldr: "As 2 pendências VALOR/ESTOQUE da US-GOV-059 fecharam. Produção medida antes de concluir: nenhuma das duas permissões existia lá (0 roles) — o antes→depois é conjunto vazio. edit_purchase_price declarada (a irmã já tinha migration de 2019 e 9 roles: omissão do upstream, não desenho). report.stock_details separada da mutação SEM declarar o nome de escrita. Achado: empilhar o 2º PR no 1º fez 18 required NUNCA nascerem."
cycle: null
prs: [5451, 5453, 5455]
us: ["US-GOV-059"]
decided_by: [W]
next_steps:
  - "Mergear o #5455 (verde, 41/41 required) — o #5451 já está em main"
  - "Smoke real pós-merge: os 2 PRs tocam role/{create,edit}.blade.php"
  - "Fechar US-GOV-059 — as 2 pendências que a mantinham aberta acabaram"
---

# Handoff — as 2 decisões VALOR/ESTOQUE, e o PR cujos required nunca nasceram

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**.
- `my-work` → 8 tasks, **todas em REVIEW** (`US-TR-309/310/305/306`, `US-PG-008`, `US-PROD-027`, `US-INFRA-023/048`). A `US-GOV-059` não aparece: segue `todo` e sem owner.
- `decisions-search "permissões Spatie gate leitura mutação estoque valor"` → 5 hits, **nenhum é dono deste tema** (as `ARQ-0002` são per-módulo sobre granularidade de nome; a `0065` é o registry declarativo de `Modules/*/Resources/permissions.php`, que **não** cobre as permissões core do UltimatePOS — essas vivem nos blades de papéis). Conclusão idêntica à do handoff anterior. **Nenhuma ADR nova criada:** é execução de US existente; o registro canônico é o SPEC.
- Handoff anterior desta US: [`2026-08-08-1938-permissoes-classe-d-idioma-gate-before`](2026-08-08-1938-permissoes-classe-d-idioma-gate-before.md).

## O que aconteceu

A sessão anterior deixou **duas decisões medidas e apresentadas, sem uma linha tocada**, porque caem sob a regra-mestre VALOR/ESTOQUE. [W] abriu esta com *"pode fazer escolha"*.

A primeira coisa foi **não decidir de memória**. O handoff descrevia as duas, mas descrição não é medição — reabri o SPEC, li os gates no código e, principalmente, **consultei o banco de produção antes de concluir**, que é o método que esta US já tinha pago três vezes. Foi o que deu a chave das duas.

**`edit_purchase_price` — declarar.** O que decidiu não foi a tabela de impacto, foi um fato que só aparece medindo: a irmã **`view_purchase_price` É declarada** — migration própria de 2019, checkbox com tooltip nos dois blades, **9 roles em produção**. O par ver/editar existe no desenho do upstream e só a metade "ver" chegou aqui. Isso caracteriza **omissão acidental, não desenho**, e declarar restaura a simetria. O que tira o risco: `store()`/`update()` **já aceitam** qualquer preço postado, então declarar **não amplia a superfície real de escrita de valor** — amplia a legítima na UI. A outra metade do pedido ("registrar que não é barreira") ficou em dois lugares: comentário nos 4 consumidores e o **próprio tooltip** do checkbox, que é onde o operador lê.

**`report.stock_details` — separar, com uma emenda.** A saída (ii) era a certa, mas tinha um custo que a redação original não isolava: declarar `stock.adjust_mismatch` como checkbox **tornaria concedível** uma escrita que roda em **GET sem CSRF**, **sobrescreve** o saldo em vez de movimentar, e **não deixa rastro no kardex** (`AR-PROD-064`) — os três já em backlog no `casos.md`. Ampliar quem faz isso é exatamente o que a regra existe pra impedir. Então: separa os nomes **sem declarar o de mutação**. A leitura ficou com `report.stock_details` (agora declarada, concedível); a escrita passou a exigir `stock.adjust_mismatch`, **não declarada de propósito** — o `Gate::before` a resolve como "só admin", que é a semântica exata de antes. Ela entra no grupo *"idioma `Gate::before`"*, o mesmo precedente que esta US já tinha estabelecido para `admin`/`only_admin`/`subscribe`.

**A dupla confirmação que a regra exige** saiu por dois caminhos independentes: **(A)** código — varredura contada dos gates, que de quebra pegou um falso-positivo homônimo (a `L700` é `view('report.stock_details')`, **nome de view**, não permissão); **(B)** produção, com controle positivo `total_permissions = 495`. Os dois concordam: **nenhuma das duas permissões existia** (0 roles), e declarar checkbox não concede a ninguém. **Antes → depois por registro afetado: conjunto vazio.** Foi isso que tornou seguro aplicar em vez de apenas recomendar.

## O achado de processo — e ele custou um PR

Abri os dois PRs **empilhados** (o 2º com base na branch do 1º) para evitar conflito no SPEC, que os dois tocam. O conflito foi evitado; em troca nasceu um problema pior: **os workflows do projeto disparam só para `base: main`**, então o #5453 teve **18 required que nunca nasceram** — incluindo `Governance Gate`, `visual-regression` e 5 lanes Pest. Ele mostrava **46 checks verdes**, e eu quase reportei isso como aprovado. **Os 46 eram um subconjunto do gate, não o gate.**

Só apareceu porque comparei a **contagem** com a do PR irmão (46 × 103) e fui conferir os required **nominalmente** contra o `required-checks-baseline.json`, em vez de aceitar "0 falhas". É a mesma família do deadlock catalogado hoje em `proibicoes.md` §5 (*promover check a required sem atualizar os PRs abertos*), chegando por outra porta: lá o check não nascia porque o workflow mudou; aqui porque **a base era outra**.

Refeito como #5455, 1 commit sobre `main`, **102 checks**. O #5453 foi fechado com a explicação.

## Persistência

- **git:** [#5451](https://github.com/wagnerra23/oimpresso.com/pull/5451) → mergeado por `wagnerra23` às 22:17 UTC (`90317c6691f`, squash). [#5455](https://github.com/wagnerra23/oimpresso.com/pull/5455) verde e **aguardando merge [W]**. [#5453](https://github.com/wagnerra23/oimpresso.com/pull/5453) **fechado** (substituído).
- **CI:** #5451 → 101 pass · 2 skipping · 0 falhas · **41/41 required**. #5455 → 101 pass · 2 skipping · 0 falhas · **41/41 required**. Os 2 skips são jobs `scheduled` (cron), que não rodam em PR por desenho — **não** é skip mascarando execução ausente.
- **MCP:** `US-GOV-059` segue `todo`. **Não marquei `done`** — o #5455 ainda não mergeou, e as duas pendências só somem de fato quando ele entrar.
- **BRIEFING:** não tocado — a mudança é de dados de autorização, não de capacidade do módulo (mesmo critério do handoff anterior).

## Próximos passos pra retomar

```bash
node scripts/governance/permission-drift.mjs
```

Deve dar **15 órfãs** com o #5455 mergeado: 7 scaffolding classe C · **5** idioma `Gate::before` (os 4 antigos + `stock.adjust_mismatch`) · 3 `visit.*`. Ou seja **as 15 com razão escrita** — não sobra órfã sem razão, e a classe D fecha.

Contador medido nas três pontas da árvore, mesmo comando: `367/16 → 368/15 → 369/15`. A última **não move de propósito** (uma sai declarada, outra entra não-declarada de propósito) — e foi conferido que a leitura **não virou "teatro"**: segue *usada* pelo `productStockDetails`.

## O que NÃO foi feito, e é dito de propósito

- **A separação de gate entrou sem teste.** O caso que a provaria — *"quem tem `report.stock_details` não consegue reconciliar saldo"* — é exatamente o caso negativo que o `casos.md` já lista como pendente (exige fixture de user não-admin, pendência **compartilhada com o trio do `BulkEdit`**). Montá-la é trabalho novo, não parte da decisão. Enquanto não existir, o que segura a separação é o `permission-drift` reportando a órfã com razão escrita — **sinal, não gate**. Quem fechar uma fecha as duas.
- **Levar o gate do preço de compra para o servidor** — decisão de desenho, não consequência de declarar um checkbox. Fica com [W].
- **Smoke pós-merge** — os dois PRs tocam `role/{create,edit}.blade.php`. Sem screenshot, não está pronto.

## Lições catalogadas

- **Consultar produção antes de concluir se pagou pela 4ª classe seguida** — e desta vez não só separou "órfã real" de "existe e ninguém viu": foi o `9 roles` da irmã que revelou que a omissão era acidental, o que **mudou a decisão** de "declarar com ressalva" para "declarar, restaurando o par".
- **Empilhar PR que toca o mesmo arquivo troca um risco por outro pior.** Evitou conflito de merge; criou um PR que **não podia ser avaliado**. O certo é **sequenciar** (esperar o merge do 1º e então abrir o 2º), não empilhar. E a régua que pegou: **contar os checks e conferir os required nominalmente** — `0 falhas` não prova que o gate nasceu.
- **A saída "correta por desenho" pode ter um custo escondido no eixo da regra-mestre.** A (ii) era certa, mas declarar o nome de mutação teria ampliado quem escreve estoque. Separar **sem declarar** entrega o desenho certo com delta zero — a emenda valeu mais que a escolha.
- **O hook `block-destructive` barrou meu force-push e estava certo.** Não contornei: havia caminho não-destrutivo (branch nova + PR novo) que resolvia igual.

---
casos: Essentials/Metas — Metas de venda (/hrm/sales-target)
irmaos: Metas.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
owner: wagner
last_run: "2026-09-05"
---

# Casos de Uso & Aceite — Essentials/Metas

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> Teste: [`Modules/Essentials/Tests/Feature/HrmMetasTest.php`](../../../../Modules/Essentials/Tests/Feature/HrmMetasTest.php)
> (lane `essentials-pest`, MySQL real, tenant 98 — ADR 0358).
> A regra das faixas em si é de [`SalesTargetFaixaValidacaoTest`](../../../../Modules/Essentials/Tests/Feature/SalesTargetFaixaValidacaoTest.php);
> o isolamento cross-tenant do POST é de [`SalesTargetShiftCrossTenantTest`](../../../../Modules/Essentials/Tests/Feature/SalesTargetShiftCrossTenantTest.php).

---

## UC-METAS-01 · Abro a tela e vejo a lista de colaboradores
- **Persona:** Wagner (admin) — quer saber quem tem meta de venda.
- **Aceite:** Dado usuário com `essentials.access_sales_target` · Quando abre `/hrm/sales-target` ·
  Então recebe 200 e a Page Inertia `Essentials/Metas`, com as props `filtros.q` e `sem_imposto`.
- **Teste:** `HrmMetasTest` — *"a rota renderiza a Page Inertia Essentials/Metas com as props da tela"*.
- **Regressão que defende:** a rota voltar a servir a Blade (ou trocar de nome de componente) e a
  tela sumir sem erro nenhum.
- **Status: 🧪**

---

## UC-METAS-02 · A lista mostra as faixas como estão gravadas
- **Persona:** Wagner — precisa ver a faixa sem abrir um colaborador por vez (a Blade legada só
  mostrava nome e um botão).
- **Aceite:** Dado um colaborador com faixa gravada · Quando a lista carrega · Então a linha dele
  traz `inicio`, `fim` e `percentual` **iguais ao banco** — nenhum número derivado.
- **Teste:** `HrmMetasTest` — *"a lista traz o colaborador com as faixas JA GRAVADAS, como estao no banco"*.
- **Regressão que defende:** a tela passar a calcular/arredondar o que devia só exibir.
- **Status: 🧪**

---

## UC-METAS-03 · Colaborador sem meta é ausência, não zero
- **Persona:** Wagner — "quem está sem meta?" é a pergunta que a tela existe para responder.
- **Aceite:** Dado colaborador sem nenhuma faixa · Quando a lista carrega · Então `faixas` vem
  **vazia** (e a tela mostra `sem meta` + travessão), nunca uma faixa de zeros.
- **Teste:** `HrmMetasTest` — *"colaborador sem faixa vem com a lista de faixas VAZIA"*.
- **Regressão que defende:** fabricar `R$ 0,00` onde o dado não existe — o que faria "sem meta" e
  "meta de zero" ficarem indistinguíveis.
- **Status: 🧪**

---

## UC-METAS-04 · A busca filtra, e a lista nunca vaza outro tenant
- **Persona:** Wagner num business com muitos colaboradores.
- **Aceite:** Dado `?q=` sem correspondência · Então a lista volta vazia. E dado a lista sem filtro ·
  Então **toda** linha pertence ao business da sessão.
- **Teste:** `HrmMetasTest` — *"a busca ?q= filtra server-side e nao vaza colaborador de outro tenant"*.
- **Regressão que defende:** multi-tenant Tier 0 (ADR 0093) na leitura da tela.
- **Status: 🧪**

---

## UC-METAS-05 · Salvar pela tela grava o mesmo valor que o caminho legado
- **Persona:** Wagner define a faixa e o valor tem de chegar íntegro — é dinheiro.
- **Aceite:** Dado o POST com **texto pt-BR** (`1.000,00` · `20.000,00` · `2,50`, a saída de
  `formatDecimalPtBR`) · Então grava `1000.0` / `20000.0` / `2.5` · E o POST com o número cru
  (`1000` / `20000` / `2.5`) grava **exatamente os mesmos** valores.
- **Teste:** `HrmMetasTest` — *"o texto pt-BR do front grava o MESMO valor que o numero cru"*.
- **Regressão que defende:** o incidente de 2026-06-05 — float locale-ambíguo entregue a um parser
  pt-BR inflou 16 vendas. Este UC é a segunda perna da dupla prova exigida pela regra mestre de
  valor; a primeira é `tests/numberPtBR.test.ts` (formatação + round-trip no JS).
- **Status: 🧪**

---

## UC-METAS-06 · O parser lê o texto do front sem ambiguidade
- **Persona:** — (contrato interno, defende o UC-METAS-05).
- **Aceite:** Dado as strings que `formatDecimalPtBR(n, 2)` produz · Então `num_uf` devolve o número
  esperado. E dado um float cru de 5 decimais (`204.99605`) · Então o resultado **não** é o número
  digitado — motivo pelo qual a tela manda texto, e nunca float.
- **Teste:** `HrmMetasTest` — *"o texto pt-BR que o front envia e lido pelo num_uf sem ambiguidade"*
  + o controle negativo *"float cru com mais de 2 decimais seria lido como MILHAR"*.
- **Regressão que defende:** alguém trocar `formatDecimalPtBR(n, 2)` por `String(n)` no `.tsx`.
  O controle negativo é o que torna este UC capaz de ficar vermelho.
- **Status: 🧪**

---

## UC-METAS-07 · A Blade legada continua funcionando enquanto existir
- **Persona:** quem ainda cair na tela antiga antes da HRM-O8.
- **Aceite:** Dado a requisição com `X-Requested-With: XMLHttpRequest` · Quando bate em
  `/hrm/sales-target` · Então recebe o JSON do DataTables (`data`, `recordsTotal`,
  `recordsFiltered`), não a Page Inertia.
- **Teste:** `HrmMetasTest` — *"a rota Inertia NAO quebrou o ramo DataTables que a Blade legada consome"*.
- **Regressão que defende:** trocar o `index` por Inertia-only e apagar a tela legada em silêncio.
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Alcance pelo menu: hoje a tela é alcançada pelo topnav Blade
  (`layouts/nav_hrm.blade.php`), que **sai no PR-10** do `PEDIDO-CL-hrm`. O UC de alcance nasce
  junto com a navegação do shell — declarar agora seria fixar um caminho que já tem data para
  morrer.
- **[BACKLOG]** Apuração do realizado (`Mês anterior`, `Mês atual`, `Faixa atingida`,
  `Progresso na faixa`, `Comissão` em dinheiro): fora desta onda por decisão registrada no charter
  (Non-Goals) e no `RUNBOOK-metas.md` §5 — é caminho de valor e exige a dupla prova + o impacto
  antes→depois da regra mestre.
- **[BACKLOG]** Remover faixa até zerar o conjunto: o controller já deleta as faixas ausentes de
  `edit_target`, mas não há teste que fixe "zerar todas ⇒ comissão de meta zero".

## Trilha do tempo
- 2026-09-05 · [CC] carimbado por `criar-tela.mjs` e preenchido na Onda 9 do HRM (PR-9).
  Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0104 (MWART, F1 = `RUNBOOK-metas.md`) · ADR 0358 (tenant 98).

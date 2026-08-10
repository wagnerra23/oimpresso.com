---
id: resources-js-pages-sells-createv3-casos
casos: Venda V3 (preview de design) · /sells/create-v3
irmaos: CreateV3.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: luiz
last_run: "2026-08-10"
---

# Casos de Uso & Aceite — Venda V3 (preview)

> **Tela de PREVIEW, não de produção.** Dono: **[L] Luiz**. A venda real continua em `/pos/create`
> (`Sells/Create.tsx`), operada pela ROTA LIVRE — e essa tela **não pode ser alterada**
> (restrição de negócio [L] 2026-08-06). Lei da tela: [`CreateV3.charter.md`](CreateV3.charter.md).
>
> **Status:** ✅ passa (com prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## Como esta lista cresce

Um `UC-*` só existe honestamente quando **≥1 teste o cita** — é o G-2 do `casos-gate`, que é **required**.
Criar UC sem teste faria o contrato nascer órfão e **bloquearia o merge de quem viesse atendê-lo**.

Então o que ainda não tem teste fica como **prosa declarada** no formato `[BACKLOG]` (sem id):
visível pra quem for escrever o teste, sem gate que ela não possa cumprir. Cada item vira
`UC-V3xx` **no PR que trouxer o teste que o cita** — não antes.

**2026-08-07 (US-SELL-058):** os três primeiros foram promovidos junto com
[`SellsCreateV3ContratoTest.php`](../../../../tests/Feature/Sells/SellsCreateV3ContratoTest.php).
O resto segue backlog — de propósito: eles exigem sessão autenticada, permissão semeada ou
render, e nada disso foi escrito ainda.

⚠️ **`✅` não é "o teste passou" — é "o manifesto do G-7 provou".** A primeira redação daqui
dizia *"vira ✅ com run verde citado"*, e estava errada: o `casos-gate` (required) lê
`scripts/casos-test-results.json`, e `✅` sem entrada lá vira `status:unverified` e **derruba o
gate**. Run verde é insumo; o veredito é o manifesto, alimentado por
`node scripts/casos-results-collect.mjs` (merge per-UC) a partir do JUnit da lane.

---

## UC-V301 · A rota de leitura existe e é servida pelo controller do preview

**Status:** ✅ — verde na lane `Pest (Sells · MySQL)`, [run 31203571574](https://github.com/wagnerra23/oimpresso.com/actions/runs/31203571574) sobre `c0214c6` (`40 passed · 133 assertions`; o arquivo contribuiu `passed: 3 · failed: 0`), com o veredito no manifesto do G-7.

- **Dado** o roteador da aplicação carregado,
- **Quando** se procura a rota nomeada `sells.create-v3`,
- **Então** ela existe, responde em `sells/create-v3`, aceita `GET` e é servida por `SellsV3Controller@create`.

Prova: `tests/Feature/Sells/SellsCreateV3ContratoTest.php`. Oráculo é o **roteador em runtime**
(`Route::getRoutes()`), não `grep` no `routes/web.php` — ler o arquivo responde "o texto está lá",
não "a rota está registrada" ([§5](../../../../memory/proibicoes.md), 2026-07-17).

---

## UC-V302 · A tela não grava — nenhuma rota de escrita aponta pro controller do preview

**Status:** ✅ — verde no mesmo [run 31203571574](https://github.com/wagnerra23/oimpresso.com/actions/runs/31203571574), veredito no manifesto do G-7.

- **Dado** que o preview existe para ensaiar desenho, não para vender,
- **Quando** se enumeram todas as rotas cujo controller é `SellsV3Controller`,
- **Então** nenhuma delas aceita `POST`, `PUT`, `PATCH` ou `DELETE`, e o controller não declara `store()`, `update()` nem `destroy()`.

Anti-vácuo: o teste primeiro prova que o controller **está roteado** — sem isso, "zero rotas de
escrita" também seria verdade num mundo onde o controller não existe.

---

## UC-V303 · Fronteira: o preview não encosta nos artefatos da tela viva

**Status:** ✅ — verde no mesmo [run 31203571574](https://github.com/wagnerra23/oimpresso.com/actions/runs/31203571574), veredito no manifesto do G-7.

- **Dado** que a razão de a tela existir é não tocar em `Sells/Create.tsx` (ROTA LIVRE, 99% do volume),
- **Quando** se inspecionam o controller e a Page do preview,
- **Então** o controller não usa/estende/instancia `SellPosController`, e nenhum `import` da Page vem de `Sells/Create` nem de `Sells/_components`.

O acoplamento é medido no que o **parser** vê (tokens PHP sem comentário; especificador de
`import`), não no texto cru — o docblock do V3 **cita** `SellPosController@create` para explicar
por que a tela existe, e um `toContain` no arquivo inteiro reprovaria a própria documentação.

**Endurecido em 2026-08-10 (mesmo UC, assert mais forte).** A redação anterior comparava o
especificador **cru** contra `"Sells/_components"`, então um import **relativo**
(`./_components/…`) passava sem conter o prefixo — mesmo apontando para dentro da pasta
vigiada. Passava por *forma*, não por estar certo. Agora o especificador é **normalizado**
(relativo → caminho do repo) antes de comparar, com controle positivo de que a normalização
de fato aconteceu, e a exceção é explícita: `_components/v3/` é a casa dos primitivos nascidos
para o preview — a cópia local que a Fronteira do charter manda criar em vez de editar o
original. O que dá **substância** à exceção é o assert do outro lado: a tela **viva** não
importa nada de `_components/v3/`. No dia em que importar, a pasta deixa de ser exclusiva e
editar um arquivo dela volta a vazar pra ROTA LIVRE — que é o que este UC existe pra impedir.

---

## Backlog de contrato

- **[BACKLOG]** A rota `/sells/create-v3` responde **403** a usuário autenticado sem `sell.create` e sem `superadmin` — mesma alçada da tela de venda real, o preview não afrouxa permissão.
- **[BACKLOG]** A rota responde **302** (login) a usuário não autenticado.
- **[BACKLOG]** A resposta Inertia renderiza o componente `Sells/CreateV3` e traz a prop `cena` com as chaves `cliente`, `itens`, `catalogo`, `tabelas`, `fsm` e `papeisDoUsuario`.
- **[BACKLOG]** A tela renderiza a **faixa de preview** — quem abre por engano precisa saber em 1 segundo que não é produção.
- **[BACKLOG]** O finalizador exibe a **ação nomeada do estágio atual** (não um select de estágio), e fica **desabilitado** quando o papel exigido falta — mostrando **qual** papel falta.
- **[BACKLOG]** A cena omite `sell.approve` de `papeisDoUsuario` de propósito: o preview precisa exercitar o caminho negado, não só o feliz.
- **[BACKLOG]** Os efeitos colaterais de uma transição (`ReservarEstoque`, `ConsumirEstoque`…) são exibidos **antes** de executá-la.
- **[BACKLOG]** `parseBR('204.99605')` devolve `204.99605` e **não** `20499605` — o guard do incidente `num_uf` (separador de milhar tem sempre 3 dígitos), e `submitSafe` arredonda a 2 casas antes de qualquer submit.

### Lançamento do item (onda 1 — modal `LancarItem`)

- **[BACKLOG]** A **unidade do cadastro** decide se a quantidade é derivada ou digitada — não um checkbox: `m²` pede peças+altura+largura, `m³` acrescenta espessura, `m` usa só a largura, e qualquer outra unidade pede a quantidade direto.
- **[BACKLOG]** Quantidade faturada de item dimensional é `peças × área da peça`, **nunca digitada** — o campo não existe nesse modo.
- **[BACKLOG]** Desconto e acréscimo incidem sobre o **preço unitário**, nunca sobre o total: `119,00 − 10% = 107,10`, e `12,50 m² × 107,10 = R$ 1.338,75`.
- **[BACKLOG]** Preço abaixo de **85% da tabela** sai da alçada do vendedor e é sinalizado; no empate exato o erro é pedir liberação a mais, nunca deixar passar preço baixo demais.
- **[BACKLOG]** Peças negativas contam como **0** — quantidade negativa viraria total negativo e, no dia em que isto gravar, estoque somando em vez de baixar.
- **[BACKLOG]** Quantidade acima do estoque **não bloqueia** o lançamento; avisa que vai gerar saldo negativo.

> **Duas divergências CONSCIENTES do handoff, e as duas são de arredondamento.**
> O handoff aplica `submitSafe` (2 casas — o guard de **dinheiro**) na área da peça
> e na quantidade faturada. Medido no harness: uma tira de `0,50 × 0,004 m` dá
> `0,002 m²`, que com 2 casas vira **zero** — quantidade zero, total `R$ 0,00`, e o
> botão "Adicionar à venda" desabilitado, isto é, **o item não entra na venda**.
> Medida não é dinheiro: a área não arredonda e a quantidade arredonda a 4 casas
> (`CASAS_DE_MEDIDA`). O guard do `num_uf` continua **intacto** onde é dele — preço
> unitário e total do item seguem em `submitSafe`.
> Provado por dois caminhos antes de aplicar (REGRA MESTRE valor/estoque): função
> real × aritmética à mão (19/19) e tabela antes→depois, com controle de que o caso
> normal não se move — `lona 5× 0,50×5,00m` dá `12,5000` nos dois.

> ⚠️ **Item retirado em 2026-08-10, e a razão importa mais que o item.**
> Havia aqui um `[BACKLOG]` dizendo *"nenhum número exibido é calculado no front
> nem no controller: os valores de `fechamento` são strings já formatadas"*. Ele
> descrevia o scaffold ([#5356](https://github.com/wagnerra23/oimpresso.com/pull/5356)),
> e o porte do handoff o tornou **falso**: a tela calcula, e a chave `fechamento`
> nem existe mais na cena. Contrato que afirma o contrário do código é instrução
> ativa pra regressão — então ele sai daqui e o conflito fica registrado **onde a
> decisão mora**: o Non-Goal *"não calcula"* do [`CreateV3.charter.md`](CreateV3.charter.md),
> que é declaração literal de [L] e **só [L] reescreve**.

---

## Fronteira que os testes desta tela devem preservar

Não é caso de uso desta tela, mas é o contrato que a existência dela serve — e o teste que o provar
pertence a `Sells/Create`, não aqui:

- `/pos/create` continua servido por `SellPosController@create` renderizando `Sells/Create`, **sem alteração de comportamento**, com esta tela existindo ao lado.

---

## Pendências declaradas

- Testes: **1 arquivo** — `tests/Feature/Sells/SellsCreateV3ContratoTest.php` (UC-V301/302/303), na allowlist da lane `Pest (Sells · MySQL)`. **Executado e verde** no [run 31203571574](https://github.com/wagnerra23/oimpresso.com/actions/runs/31203571574) (CI; a lane nunca roda local · [ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)), com veredito no manifesto do G-7.
- O que os 3 UCs **não** cobrem: nada de auth, permissão, render ou valor. São contrato de roteamento e de fronteira — os itens de `[BACKLOG]` acima seguem sem prova.
- Smoke real de tela: pendente. O RUNBOOK ([`RUNBOOK-create-v3.md`](../../../../memory/requisitos/Sells/RUNBOOK-create-v3.md) §F4) prevê smoke em staging; nada disso rodou.
- Tenant de teste é o fictício **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — `biz=4` é proibido em teste, fixture ou smoke.

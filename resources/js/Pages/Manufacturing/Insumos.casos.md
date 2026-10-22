---
casos: Manufacturing/Insumos — impacto reverso e simulador de preço
irmaos: Insumos.charter.md (lei) · memory/requisitos/Manufacturing/RUNBOOK-insumos.md (F1)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
fonte: handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §4.4 + §18.3 — os UC abaixo DERIVAM dele
owner: wagner
last_run: "2026-10-21"
---

> ℹ️ **`last_run` 2026-10-19 → 2026-10-21 (G-6): a gaveta ganhou a seção "Método de custo"** (UC-INSMET-06..09),
> que grava a escolha `medio` | `ultimo` pelo `PUT /compras/insumos/{variation}/metodo-custo`. Simulação, receitas
> afetadas e números não mudaram. Casos UC-INSMET-01..09 rodados no CT 100 em 2026-10-21 (17 passed).
>
> ℹ️ **`last_run` 2026-10-06 → 2026-10-19 (G-6): forma da gaveta, não regra de negócio.** A gaveta passou a
> usar a moldura do protótipo (`_components/Gaveta.tsx`): seções em cartão, rodapé com "Fechar", margem no
> `StatusBadge`. Simulação, receitas afetadas e números são os mesmos. Comportamento: o `Esc` deixou de disparar
> duas visitas (o listener da página saiu; fecha pelo `Sheet` — teste em `tests/js/manufacturing-gaveta.test.tsx`).
> Medição em `Insumos-visual-comparison.md` (2026-10-19).
>
> ℹ️ **`last_run` 2026-10-04 → 2026-10-06 (G-6): forma, não regra de negócio.** A busca passa a ser a mesma da aba
> Receitas (`.mfg-s`: lupa, 34px, canto 8) — antes era um `Input` simples de 30px sem lupa ([M] 2026-10-06, abas
> irmãs com a mesma peça diferente). O que a busca filtra e o que cada linha abre não mudaram. Nenhum UC foi
> reexecutado nesta data.

> ℹ️ **`last_run` 2026-09-30 → 2026-10-04 (G-6): forma, não regra de negócio.** Os dados, os filtros e o que
> cada linha abre são os mesmos; o que muda de visível: a grade virou o `shared/DataTable` na anatomia `grid` (o par React do `DataGrid` do DS): `<table>` de verdade, cabeçalho 10px caixa-alta, linhas listradas, nome sem negrito, código e números em fonte mono, e o "% do custo" no `StatusBadge` do DS. Toda linha recebe foco, mas o clique continua abrindo só o insumo que tem receita. Nenhum UC foi reexecutado nesta data.

> ℹ️ **`last_run` 2026-09-04 → 2026-09-11 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Insumos.tsx` no [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224) foi **1 linha(s) de COMENTÁRIO** —
> o path do protótipo (`prototipo-ui/cowork/…` → `prototipo-ui/cowork/Wagner/…`, topologia por dono da ADR 0397).
> Zero JSX estrutural, zero handler, zero prop, zero copy alterada (verificado: `git diff origin/main...HEAD -- resources/js/Pages/Manufacturing/Insumos.tsx`
> só tem linhas iniciadas por `//`, `*` ou `{/*`). **Nenhum UC desta tela foi reexecutado nem revalidado**; o bump é o que o campo
> significa na prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run — mesmo tratamento do #6913.

# Casos de Uso & Aceite — Manufacturing/Insumos

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> Derivados do handoff, **não do `.tsx`** (§5 tautológico das proibições).

---

## UC-INS-01 · Só entram receitas do meu business (Tier 0)
- **Persona:** qualquer tenant.
- **Aceite:** Dado um tenant fictício sem receitas · Quando `usosDoInsumo`/`listInsumosComUso`
  rodam pra ele · Então devolvem vazio — nunca insumo ou receita de outro business.
- **Fonte:** [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) +
  §18.3 ("precisa de um método com o JOIN de tenant").
- **Teste:** `Wave33InsumosTest.php`
- **Regressão que defende:** o módulo não tem global scope; trocar o JOIN por um `where`
  ingênuo vaza custo de outro tenant.
- **Status: 🧪**

---

## UC-INS-02 · A simulação recalcula pela fórmula real — não soma delta por fora
- **Persona:** Wagner — o número simulado precisa ser o custo de verdade, não uma aproximação.
- **Aceite:** Dado uma receita com `production_cost_type = percentage` (custo extra é % dos
  ingredientes) · Quando o insumo sobe X% · Então o custo simulado é o que
  `calculateCost()` devolve com o preço novo — **maior** que o atalho aditivo do protótipo
  (`total + qtd × preço × pct`), porque o extra percentual também sobe.
- **Fonte:** §7 (fórmulas) + `RUNBOOK-insumos.md §1` (o desvio declarado).
- **Teste:** `Wave33InsumosTest.php` — compara os dois caminhos com números concretos e
  exige que difiram no caso `percentage` e coincidam em `fixed`.
- **Regressão que defende:** alguém "simplificar" a simulação copiando o protótipo e passar a
  subestimar o custo exatamente nas receitas de custo percentual.
- **Status: 🧪**

---

## UC-INS-03 · Consumo conta a sub-unidade, e insumo repetido soma
- **Persona:** Wagner — a receita usa 2 linhas do mesmo insumo, em galão de 5 L.
- **Aceite:** Dado uma receita com duas linhas do mesmo insumo (0,02 e 0,03) numa sub-unidade
  de multiplicador 5 · Quando a tela calcula o consumo · Então `qtd` = (0,02 + 0,03) × 5 =
  0,25 na unidade base — não 0,05, nem só a primeira linha.
- **Fonte:** §4.4 (nota "consumo convertido à unidade base") + `calculateCost`, que já aplica
  o multiplicador.
- **Teste:** `Wave33InsumosTest.php`
- **Regressão que defende:** somar sem multiplicador (subestima) ou sobrescrever em vez de
  somar quando o insumo aparece em mais de uma linha.
- **Status: 🧪**

---

## UC-INS-04 · Denominador zero devolve 0, nunca NaN/Infinity
- **Persona:** qualquer — receita recém-criada tem `total_quantity = 0` e sem preço de venda.
- **Aceite:** Dado receita com `total_quantity = 0` e `final_price = 0` · Quando o insumo é
  simulado · Então `unit_novo = 0` e `margem_nova = 0`, ambos finitos.
- **Fonte:** §7.3 — mesma defesa das telas de Receitas e Relatório.
- **Teste:** `Wave33InsumosTest.php`
- **Status: 🧪**

---

## UC-INS-05 · O `variacao_pct` do cliente é CLAMPADO no servidor
- **Persona:** qualquer — a faixa do §4.4 é −30%..+60%.
- **Aceite:** Dado um `variacao_pct` fora da faixa (ex: 999) · Quando o controller monta o
  payload · Então o valor usado é o limite (60), não o que veio na URL.
- **Fonte:** §4.4 (faixa do slider) + a regra de nunca confiar no range do cliente.
- **Teste:** `Wave33InsumosTest.php` — assert estrutural sobre o clamp no controller.
- ⚠️ **O que este teste NÃO prova:** que uma requisição HTTP real devolve o payload clampado —
  isso é smoke (`RUNBOOK-insumos.md §5`), não Pest.
- **Status: 🧪** — do que ele mede: o clamp existe no código.

---

## Método de custo por insumo (US-COM-024 · UC-FLX-38)

> **Âncora:** US-COM-024 (`memory/requisitos/Compras/SPEC.md`) e o UC-FLX-38 do catálogo de fluxos
> (*"custo do insumo guarda o histórico por data"*). Quando a regra "Atualizar o custo dos insumos" da
> empresa é **Definido em cada insumo** (`por_insumo`), cada insumo diz o seu método na tabela-ponte
> `compras_insumo_metodo_custo`. A entrega de 2026-10-20 foi o serviço (`CustoDoInsumoService`); a seção
> "Método de custo" na gaveta veio em 2026-10-21 (UC-INSMET-06..09). Prova: `Modules/Compras/Tests/Feature/CustoDoInsumoServiceTest.php`. VALOR:
> regra mestre (conta à mão + segundo caminho em PHP no teste).
>
> Arranjo dos casos (31/03): compras recebidas B 10 un × 12,00 e C 30 un × 16,00 com 10 devolvidas.

## UC-INSMET-01 · Insumo marcado médio custa o médio ponderado · `must` valor

**Dado** a regra da empresa `por_insumo` e o insumo marcado `medio` **Quando** se pede o custo em 31/03
**Então** o custo é (10 × 12,00 + 20 × 16,00) / 30 = 14,6667 e o método informado é `medio`.

Status: 🧪

## UC-INSMET-02 · Insumo marcado último custa o preço da compra mais recente · `must` valor

**Dado** a regra `por_insumo` e o insumo marcado `ultimo` **Quando** se pede o custo em 31/03 **Então** o
custo é 16,0000 (compra C, de 10/03), não o médio de 14,6667.

Status: 🧪

## UC-INSMET-03 · Insumo sem método definido fica sem custo, com o motivo · `must`

**Dado** a regra `por_insumo` e nenhuma linha para o insumo **Quando** se pede o custo **Então** o custo é
nulo e o motivo é "O insumo não tem método definido." — nenhum padrão é inventado.

Status: 🧪

## UC-INSMET-04 · O método que outra empresa deu ao insumo não vale aqui · `must`

**Dado** só a outra empresa marcou o mesmo insumo como `ultimo` **Quando** esta empresa pede o custo
**Então** o custo é nulo com o motivo; a outra empresa lê a sua linha e o seu custo (1000 un × 1,00 → 1,0000).

Status: 🧪

## UC-INSMET-05 · Empresa com regra médio ignora o método do insumo · `must` valor

**Dado** o insumo marcado `ultimo` (que daria 16,0000) e a empresa com a regra padrão médio **Quando** se
pede o custo em 31/03 **Então** o custo é 14,6667 — o método do insumo só vale com `por_insumo`.

Status: 🧪

> **Gravação pela gaveta** — seção "Método de custo" (Médio / Último) da gaveta do insumo, gravada
> por `PUT /compras/insumos/{variation}/metodo-custo`. Prova:
> `Modules/Compras/Tests/Feature/InsumoMetodoCustoEndpointTest.php` (mesmo arranjo B e C).

## UC-INSMET-06 · Gravar o método pela gaveta muda o custo por insumo · `must` valor

**Dado** o insumo sem método (custo por insumo nulo) **Quando** o usuário escolhe Último **Então** grava
`ultimo` e o custo por insumo em 31/03 passa a 16,0000; **Quando** troca para Médio **Então** a mesma linha
vira `medio` (nenhuma duplicada) e o custo passa a 14,6667. Volta para a tela de onde veio.

Status: 🧪

## UC-INSMET-07 · Insumo de outra empresa responde 404 · `must`

**Dado** uma variação de produto de outra empresa **Quando** se tenta gravar o método nela **Então** 404 e
nenhuma linha é gravada em nenhuma das duas empresas.

Status: 🧪

## UC-INSMET-08 · Sem `purchase.update` não grava · `must`

**Dado** o usuário sem `purchase.update` **Quando** tenta gravar **Então** 403 e nada é gravado. Na tela, o
controle aparece desabilitado.

Status: 🧪

## UC-INSMET-09 · Só médio ou último · `must`

**Dado** o método `por_insumo` (ou qualquer outro) **Quando** se tenta gravar **Então** erro no campo
`metodo` e nada é gravado.

Status: 🧪

> **No cadastro do produto** — a lista de Insumos só mostra o que é ingrediente de receita, mas o
> custo vale para qualquer item comprado (matéria-prima ou revenda; o app não marca tipo de
> produto). O formulário Blade de novo e de editar produto ganha o campo "Método de custo"
> (Não definido / Médio / Último), aplicado a **todas as variações** do produto. Entra pelos ganchos
> de módulo do core (`product_form_part` desenha, `after_product_saved` grava) — o `update()` do
> produto não muda. Prova: `Modules/Compras/Tests/Feature/MetodoCustoNoCadastroDoProdutoTest.php`,
> no `PUT /products/{id}` real, com o mesmo arranjo B e C na variação 1.
>
> ⚠️ Os ganchos só rodam para módulo **instalado** (`system.compras_version`, `ModuleUtil`). Medido
> em produção em 2026-10-21: a chave não existe (49 chaves `*_version`, nenhuma do Compras). Até o
> módulo ser instalado, o campo não aparece no formulário.

## UC-INSMET-10 · Escolher o método no cadastro grava em todas as variações · `must` valor

**Dado** um produto variável com duas variações e sem método **Quando** salva o cadastro com Último
**Então** as duas variações ficam `ultimo` e o custo por insumo da variação 1 em 31/03 é 16,0000;
**Quando** salva com Médio **Então** as mesmas duas linhas viram `medio` e o custo é 14,6667.

Status: 🧪

## UC-INSMET-11 · Envio sem o campo não muda o método · `must`

**Dado** a variação 1 com `ultimo` **Quando** o produto é salvo sem o campo (a tela React de editar
não o manda; outro módulo pode mandar só `has_module_data`) **Então** a linha continua `ultimo` —
ausência não é "apagar".

Status: 🧪

## UC-INSMET-12 · "Não definido" apaga o método das variações · `must`

**Dado** as duas variações com método **Quando** salva com Não definido **Então** as linhas do produto
somem; com a regra `por_insumo` o custo volta a sair nulo com o motivo.

Status: 🧪

## UC-INSMET-13 · Variações com métodos diferentes ficam como estão · `must`

**Dado** a variação 1 `medio` e a 2 `ultimo` **Quando** o formulário abre **Então** mostra "Variações com
métodos diferentes (manter)"; salvo assim, nada muda.

Status: 🧪

## UC-INSMET-14 · Sem `purchase.update` o produto salva e o método não · `must`

**Dado** o usuário com `product.update` e sem `purchase.update` **Quando** salva com Último **Então** o
produto é gravado e nenhuma linha de método. No formulário o campo vem desabilitado.

Status: 🧪

## UC-INSMET-15 · O método mostrado é o das variações, só da empresa · `must`

**Dado** nenhuma, uma ou as duas variações com `ultimo` **Quando** o formulário pede o método
**Então** mostra vazio, `misto` e `ultimo`; pela outra empresa, o mesmo produto mostra vazio.

Status: 🧪

## UC-INSMET-16 · O campo só aparece para empresa com o módulo Compras no pacote · `must`

**Dado** a empresa sem `compras_module` na assinatura **Quando** o formulário de editar produto pede as
partes dos módulos **Então** o Compras não manda nada; **Dado** acesso ao pacote (aqui, superadmin)
**Então** manda o campo com o método atual das variações. É o mesmo gate do WooCommerce no formulário.

Status: 🧪

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** O drawer fecha com `Esc`, com o ✕ e com clique no scrim — comportamento de
  navegador; lugar é spec Playwright.
- **[BACKLOG]** A linha do insumo é acionável por teclado (Enter/Espaço), não só por mouse.
- **[BACKLOG]** A busca casa nome e SKU.
- **[BACKLOG]** A cor da pílula de peso muda em 25% e 50%; a de margem em 45% e 55%.

## Ambiguidade declarada (não inventei desempate)

O protótipo lista insumos "sem receita" (`—` + não-clicável), porque a lista dele é um catálogo
curado. **No app essa lista é derivada dos ingredientes**, então todo item tem ≥1 receita e o
estado não ocorre. A tela mantém o caminho de render, e o motivo está no `RUNBOOK-insumos.md §2`.
Se [W] quiser o catálogo inteiro de produtos (aí o estado passa a existir), é outra consulta e
outra decisão de escopo — não é ajuste de código.

## Trilha do tempo
- 2026-09-04 · [F+C] US-MANU-005. 5 UC com teste Pest; 4 no backlog aguardando e2e. É a onda
  que trouxe o backend que o §18.3 declarava faltar. Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0093.
- 2026-09-04 · [F+C] **CUTOVER**: a tela passou a ser servida no endereço CANÔNICO do módulo
  (nasceu em `/manufacturing/v2/*`, que virou 301). Pedido [F]: *"módulo inteiro em produção,
  com os links e vínculos reais, sem rotas alternativas"*, sobre a aprovação [W] da família.
  Pré-condição medida: a regra F5 (cutover exige aviso a cliente) nomeia a ROTA LIVRE, e [F]
  confirmou que ela não usa Fabricação. **Nenhuma rota removida ou renomeada** — `?legacy=1`
  devolve o Blade no MESMO endereço e o ramo AJAX do DataTables segue intacto. Guarda nova:
  `Modules/Manufacturing/Tests/Feature/CutoverRotasCanonicasTest.php` (9 asserts). Nenhum UC
  acima mudou de comportamento; os asserts de ROTA de Wave30/31/33 foram reapontados pro
  canônico porque o `/v2/` agora responde `RedirectController`, não o controller da tela.
- 2026-10-20 · [W+C] US-COM-024 · UC-FLX-38: UC-INSMET-01..05, o método de custo por insumo
  (`CustoDoInsumoService` + `compras_insumo_metodo_custo`). Só backend; `Insumos.tsx` não mudou e
  o campo no drawer é follow-up. Prova: `CustoDoInsumoServiceTest.php`.
- 2026-10-21 · [W+C] US-COM-024 · UC-FLX-38: UC-INSMET-06..09, a seção "Método de custo" na gaveta
  e o `PUT /compras/insumos/{variation}/metodo-custo` (empresa da sessão, variação da empresa ou
  404, `purchase.update`). Prova: `InsumoMetodoCustoEndpointTest.php`.
- 2026-10-21 · [W+C] UC-INSMET-10..15: o campo no cadastro do produto (formulário Blade), pelos
  ganchos de módulo. Pedido [W]: *"independente se é matéria prima ou material de revenda é
  necessário a aplicação de sistema de custo"*. Prova: `MetodoCustoNoCadastroDoProdutoTest.php` (UC-INSMET-10..16).

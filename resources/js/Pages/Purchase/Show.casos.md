---
id: resources-js-pages-purchase-show-casos
casos: Detalhe da Compra · /purchases/{id}
irmaos: Show.charter.md (lei) · Show.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o escopo por tenant e a ausência do barcode são duráveis — não mudam quando o detalhe ganhar card novo.
owner: wagner
last_run: "2026-09-05"
last_run_ci: "🟡 A lane purchase-pest.yml nasceu e os UC 01 [T0] e 05 [V0] têm contrato de COMPORTAMENTO em PurchaseShowTenantContratoTest — 4 passed, 8 assertions, run no CT 100, mordida verificada por mutação. Os UC 02/03/04/06 seguem 🔴 sem lane, e o 03 tem o critério ERRADO (casa o comentário do bug-fix) — ver §Divergências. Ver §Dívida de prova."
---

# Casos de Uso & Aceite — Detalhe da Compra (`/purchases/{id}`)

> **Âncora:** os UC derivam do [`Show.charter.md`](Show.charter.md) (Mission · Goals · Non-Goals ·
> Anti-hooks) cruzado com o
> [`show-visual-comparison.md`](../../../../memory/requisitos/Compras/_telas/show-visual-comparison.md)
> — **nunca do `Show.tsx`**: teste derivado do código é tautológico e trava o desvio em vez de
> pegá-lo ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> ⚠️ **A 2ª âncora passou a existir em 2026-09-04, e os UC ainda NÃO foram repromovidos.** Quando
> este arquivo nasceu, o Show era a única das quatro telas sem RUNBOOK; agora tem o
> [`RUNBOOK-purchase-show.md`](../../../../memory/requisitos/Compras/_telas/RUNBOOK-purchase-show.md),
> irmão dos de [Index/Create/Edit](../../../../memory/requisitos/Compras/_telas/). O charter segue
> `status: draft` com Non-Goals marcados *"inferência pendente de Wagner"*, então o que o RUNBOOK
> destrava é o que ele **mediu** (dual-path, 404 vs 403, barcode por omissão) — não a intenção, que
> segue com [W]. A contagem de UC abaixo ainda é a de antes: promover exige teste que cite o UC,
> senão nasce órfão e o `casos-gate` G-2 (required) bloqueia quem for atendê-lo.

---

## 🟡 Dívida de prova — a lane nasceu; 2 dos 6 UC já têm defesa ativa

> ✅ **Atualização de 2026-09-05 — a errata abaixo continua verdadeira para o dia em que foi
> escrita, e fica inteira.** O que mudou: a lane
> [`purchase-pest.yml`](../../../../.github/workflows/purchase-pest.yml) **existe** e roda em CI, e
> os UC **01 `[T0]`** e **05 `[V0]`** ganharam contrato de comportamento em
> [`PurchaseShowTenantContratoTest`](../../../../tests/Feature/Purchase/PurchaseShowTenantContratoTest.php)
> — as **duas** camadas de falha que a errata nomeia, fechadas para esses dois.
> Os UC **02, 03, 04 e 06** seguem `🔴 sem lane`.
>
> ⚠️ **E a errata acertou por um motivo a mais do que sabia, no caso do UC-03.** Ela retirou o
> selo *"estrutural (correto)"* dele por AUSÊNCIA DE LANE. Medido depois, com a lane rodando: o
> assert `not->toContain('Barcode')` **falha** — e o que casa é o **comentário da linha 9** de
> `Show.tsx`, que documenta o próprio bug-fix do 500. Ou seja, mesmo COM lane aquele UC não ficaria
> verde: o instrumento não distingue *renderizar* barcode de *falar sobre* barcode. Ver
> §Divergências — as 3 saídas possíveis estão escritas lá, e a escolha é [W].

> ⚠️ **Correção da v1 deste arquivo (2026-09-05), registrada e não apagada.** A v1 tratava o
> `ShowPageTest` como um teste que **executa** e classificava a dívida como sendo **só**
> presence-gate — chegando a abrir exceção para os UC 03 e 06 (*"🧪 estrutural (correto)"*, sem
> `⚠️`), como se ali houvesse defesa adequada. **O presence-gate é real — mas não era o problema
> principal, e a premissa de que o teste roda era falsa.** Eu medi a perna do **assert** (o que ele
> prova) e **não medi a perna da LANE** (se alguém o invoca). Quem pegou foi o gate
> `uc-lane-coverage` do CI, reprovando os 6 UC desta tela com *"existe e NENHUMA lane
> roda"*. O gate estava certo; eu estava errado.

Medição em `origin/main` (2026-09-05), **três pernas**, todas contadas:

| perna | resultado |
|---|---|
| workflows que citam `tests/Feature/Purchase` (`git grep -c -- .github/`) | **0** |
| linhas `Purchase` em `.github/ci-sqlite-pest.list` (542 linhas) | **0** |
| arquivos de teste em `tests/Feature/Purchase/` | **8** |

**Os 8 arquivos de teste do Purchase são órfãos de CI.** Nenhuma lane os executa — nem a MySQL por
módulo, nem a sqlite curada do `ci.yml`. Logo o `ShowPageTest` **também não roda**:

| teste | requests HTTP | asserts de presença | executa? |
|---|---:|---:|---|
| [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) | **0** | **44** | 🔴 **não — sem lane** |

**Duas camadas de falha, e a de cima é a que a v1 não tinha nomeado:**

1. **Sem lane** — o teste nunca é invocado. Nas palavras do próprio gate: *"teste fora de toda lane
   é 'verde impossível': existe, pode estar vermelho há meses, e nenhum PR o acorda."*
2. **Presence-gate** — mesmo ganhando lane, o que ele prova é que certas *strings* existem em
   `Show.tsx` e em `PurchaseController.php`. Ele lê os arquivos-fonte e casa texto: pega a
   **remoção** de um trecho, mas não monta tenant, não emite request e não valida resposta. É a
   classe [LC-11](../../../../memory/LICOES_CODE.md), que o ledger alarma com 11 ocorrências.

**Consequência (revista em 2026-09-05):** os UC **01** e **05** têm defesa ativa — comportamental
**e** executada. Os UC **02, 03, 04 e 06** seguem sem nenhuma das duas. Sobre os UC 03 e 06, que a
v1 classificava como *"estrutural (correto)"*: o 06 de fato tem no presence-gate o instrumento
certo e só lhe falta lane; o **03 não** — medido, o critério dele está errado, e lane nenhuma o
deixaria verde (ver o bloco de atualização no topo desta seção).

**Por que o conserto dos outros 4 não está aqui:** o gate diz, com todas as letras, *"conserto NÃO é
mexer na allowlist por conta própria — por que ela existe (custo de CI? teste instável escondido?)
é decisão [W]"*. Concordo, e o motivo é concreto: a pasta `tests/Feature/Purchase/` devolve
**6 failed · 6 skipped · 90 passed (214 assertions)** no CT 100. Pôr esses 8 arquivos na lane
trocaria "invisível" por "vermelho permanente". A lane nasceu com allowlist para admitir só o
comprovadamente verde e crescer daí — o caminho é converter, um UC por vez.

---

## Rastreabilidade

| UC | Título | Tipo | Âncora de contrato | Teste que cita | Status |
|---|---|---|---|---|---|
| UC-PURSHW-01 | Detalhe nunca resolve compra de outro tenant | must `[T0]` | charter Non-Goal 4 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) | `PurchaseShowTenantContratoTest` | ✅ comportamento · na lane |
| UC-PURSHW-02 | Dual-path: AJAX puro recebe Blade; navegação recebe Inertia | must | charter §Backend · [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) | `ShowPageTest` | 🔴 sem lane |
| UC-PURSHW-03 | A tela **não** renderiza barcode (mata o 500 do legado) | must `[reg]` | charter §Backend (bug-fix declarado) | `ShowPageTest` | 🔴 sem lane |
| UC-PURSHW-04 | Editar/Excluir só aparecem com a permissão | must | charter Goals · Anti-hooks | `ShowPageTest` | 🔴 sem lane |
| UC-PURSHW-05 | A tela não recalcula totais — exibe o que o controller mandou | must `[V0]` | charter Non-Goal 3 | `PurchaseShowTenantContratoTest` | ✅ comportamento (metade backend) · na lane |
| UC-PURSHW-06 | A Page não decide tenant — `business_id` vem das props | must `[T0]` | charter Non-Goal 4 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) | `ShowPageTest` | 🔴 sem lane |

---

## UC-PURSHW-01 · Detalhe nunca resolve compra de outro tenant · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — o detalhe expõe fornecedor, custo unitário e margem; um
  vazamento aqui entrega a estrutura de custo do concorrente.
- **Aceite:** Dado uma compra do negócio 98 · Quando o usuário do negócio 1 pede
  `GET /purchases/{id}` · Então **não** recebe o detalhe (404/403 conforme o canon da rota). E,
  como **controle positivo**, o mesmo endpoint para uma compra do próprio negócio responde 200 —
  sem o par, um `abort` incondicional passaria no teste.
- **Teste:** [`PurchaseShowTenantContratoTest`](../../../../tests/Feature/Purchase/PurchaseShowTenantContratoTest.php)
  — *"UC-PURSHW-01 (A · controle positivo) o detalhe da PROPRIA compra responde 200"* ·
  *"UC-PURSHW-01 (B · contrato T0) o detalhe de compra de OUTRO business responde 404"*.
  Os asserts de [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) **permanecem
  no arquivo**, mas seguem sem lane.
- **Contrato:** charter §Non-Goals item 4 (*"IDs de outro negócio devem retornar 404/403"*) ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o model `Transaction` **não tem global scope** — o isolamento é escrito
  à mão em cada query. Essa mesma ausência produziu um IDOR de escrita real no `update` desta mesma
  controller ([`UpdateCrossTenantIdorTest`](../../../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php)).
- **Status: ✅ comportamento · na lane** (2026-09-05) — a compra vizinha é criada e a rota responde
  **404** (não 403: não revelar a existência do recurso alheio, ADR 0093 defense-in-depth), com o
  controle positivo (200 na própria) que impede o verde por vácuo — sem ele, um `abort()`
  incondicional ou uma rota quebrada dariam 404 em tudo e pareceriam isolamento.
  **Morde:** `show()` resolvendo sem `where('business_id')` ⇒ `1 failed`, exatamente no assert do
  404, com os 3 outros verdes. Deixou de ser *"o UC mais caro do arquivo e o menos defendido"*.

---

## UC-PURSHW-02 · Dual-path: AJAX puro recebe Blade; navegação recebe Inertia · `must`

- **Persona:** Maiara/Felipe abrem o detalhe pelo SPA; o modal AJAX legado ainda é usado por telas
  antigas que não foram migradas.
- **Aceite:** Dado `PurchaseController@show` · Quando o request é AJAX puro · Então devolve a view
  Blade legacy (compatibilidade do modal). Quando é navegação normal · Então devolve a Page Inertia
  `Purchase/Show`. Os dois caminhos coexistem — nenhum foi substituído.
- **Teste:** [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) — *"Controller@show
  tem dual path (Blade legacy se AJAX puro + Inertia default)"* · *"Controller@show PRESERVA Blade
  legacy path (compat retro AJAX modal)"* · *"Controller@show tem permission re-adicionada (não
  comentada)"*.
- **Contrato:** charter §Backend · [ADR 0104 MWART](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) (F5 CUTOVER é humano e não ocorreu).
- **Regressão que defende:** a asserção *"permission re-adicionada (não comentada)"* existe porque a
  permissão **já esteve comentada** neste método. Um `//` numa linha de autorização é a mudança mais
  barata de escrever e a mais cara de descobrir.
- **Status: 🔴 sem lane** — casamento de texto; nenhum request AJAX é emitido para provar a
  bifurcação — e nenhuma lane roda sequer o casamento de texto. Vale o destaque: o assert *"permission
  re-adicionada (não comentada)"* guarda uma regressão que **já aconteceu**, e hoje está mudo.

---

## UC-PURSHW-03 · A tela **não** renderiza barcode (mata o 500 do legado) · `must` `[reg]`

- **Persona:** qualquer operador abrindo o detalhe de uma compra — o legado devolvia **500**, ou
  seja, a tela simplesmente não abria.
- **Aceite:** Dado `Show.tsx` · Quando o arquivo é lido · Então **não** existe geração de código de
  barras no detalhe (o Blade legado gerava via DNS1D e estourava). A impressão de etiqueta é fluxo
  dedicado (`/labels/show`), não responsabilidade desta tela.
- **Teste:** [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) — *"Page NÃO
  renderiza barcode (bug-fix por omissão — linha 430 Blade DNS1D)"*.
- **Contrato:** charter §Backend (*"substitui os Blade legacy show + show_details (430+ linhas) e
  mata o bug 500 do barcode"*).
- **Regressão que defende:** um bug-fix **por omissão** é o mais frágil que existe — nada no código
  explica por que aquilo *não* está lá, então a próxima pessoa que buscar paridade com o Blade
  reintroduz o barcode de boa-fé e ressuscita o 500.
- **Status: 🔴 sem lane** — aqui o contrato *é* a ausência de um literal no arquivo, então o
  presence-gate **seria** o instrumento certo, não um substituto de teste de comportamento. Só que
  ele também não roda — instrumento certo, nunca acionado. A v1 marcava *"🧪 estrutural (correto)"*
  sem `⚠️`; a exceção valia para o eixo do presence-gate, e some no eixo da lane. Num bug-fix **por
  omissão** isso é o pior caso: nada no código explica por que o barcode não está lá, e não há teste
  ativo que reclame quando alguém o reintroduzir de boa-fé.

---

## UC-PURSHW-04 · Editar/Excluir só aparecem com a permissão · `must`

- **Persona:** operador de conferência (só leitura) não pode ver botão que ele não pode usar.
- **Aceite:** Dado o detalhe renderizado · Quando o usuário não tem `purchase.update` /
  `purchase.delete` · Então os botões Editar e Excluir **não** são renderizados — e a exclusão,
  quando disponível, exige `confirm()` com a referência da compra antes do `router.delete`.
- **Teste:** [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) — *"Page respeita
  permissions (update/delete renderizam condicionalmente)"* · *"Page tem botões ação
  Voltar/Imprimir/Editar/Excluir"* · *"Controller@showInertia retorna permissions completas"*.
- **Contrato:** charter §Goals · §Anti-hooks (*"Exclusão nunca dispara sozinha"*).
- **Regressão que defende:** esconder o botão é UX; **o backend continua sendo a autoridade**. Este
  UC defende a metade visível — a metade de servidor pertence ao Edit/Destroy e está registrada em
  `[BACKLOG]` porque não há teste que a exercite.
- **Status: 🔴 sem lane** — casamento de texto no `.tsx`; nenhum usuário sem permissão é montado, e
  nenhuma lane executa o casamento de texto.

---

## UC-PURSHW-05 · A tela não recalcula totais — exibe o que o controller mandou · `must` `[V0]`

- **Persona:** **Larissa @ ROTA LIVRE (biz=4)** — o total exibido tem que ser o total gravado; um
  arredondamento diferente no cliente faz a tela discordar do financeiro.
- **Aceite:** Dado o detalhe de uma compra · Quando os totais (subtotal, desconto, impostos, frete,
  total, pago, a pagar) são exibidos · Então os valores vêm prontos do controller e a tela apenas
  **formata** em pt-BR — nunca recalcula, nunca reagrega.
- **Teste:** [`PurchaseShowTenantContratoTest`](../../../../tests/Feature/Purchase/PurchaseShowTenantContratoTest.php)
  — *"UC-PURSHW-05 [V0] o final_total do payload e o do BANCO, nao uma derivacao das linhas"* ·
  *"UC-PURSHW-05 [V0] antes -> depois: mudar o total no banco move o payload na MESMA medida"*.
  Os asserts de formatação de [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php)
  **permanecem no arquivo**, mas seguem sem lane.
- **Contrato:** charter §Non-Goals item 3 (*"Não recalcula totais no cliente — os valores vêm
  prontos do controller"*) · [proibicoes §REGRA MESTRE — CÁLCULO DE VALOR](../../../../memory/proibicoes.md).
- **Regressão que defende:** o incidente de 2026-06-05 (biz=4) inflou 16 vendas ~×100k porque um
  número atravessou uma fronteira de formatação com a interpretação errada de separador. Cálculo em
  duas camadas é a porta por onde isso entra; esta tela é explicitamente **uma camada só**.
- **Status: ✅ comportamento (metade backend) · na lane** (2026-09-05) — e o recorte importa, então
  fica dito: *"a TELA não recalcula"* é contrato do `.tsx` e só Playwright provaria. O que o teste de
  request prova é **a metade que decide o resultado**: o valor que chega à tela é o do **banco**, não
  uma derivação. A prova é uma discrepância deliberada — a compra nasce com `final_total` 1234,56 e
  **sem** `purchase_lines`, então qualquer agregação daria 0; se o payload trouxer 1234,56, o número
  atravessou intacto. Um assert que só comparasse payload×banco ficaria verde mesmo com
  `final_total = $net_total`, porque numa compra bem-comportada os dois coincidem — a discrepância
  é o que discrimina. Segundo caminho (a REGRA MESTRE exige dois): antes→depois, mudar o total no
  banco move o payload na mesma medida, com número concreto.
  **Morde:** trocar `$final_total = (float) $purchase->final_total` por `$net_total` ⇒ `2 failed` —
  caem os **dois** caminhos, que é o comportamento esperado de uma prova de valor.

---

## UC-PURSHW-06 · A Page não decide tenant — `business_id` vem das props · `must` `[T0]`

- **Persona:** Wagner — o front nunca é a autoridade sobre de quem é o dado.
- **Aceite:** Dado `Show.tsx` · Quando o arquivo é lido · Então **não** existe `business_id`
  hardcoded; o recorte de tenant é resolvido no controller e chega pronto nas props.
- **Teste:** [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) — *"Page NÃO tem
  business_id hardcoded (vem das props via Controller)"*.
- **Contrato:** charter §Non-Goals item 4 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** um `business_id` fixo numa Page parece constante de config em review e
  só se revela no segundo tenant.
- **Status: 🔴 sem lane** — ausência de literal *é* o contrato, então o presence-gate **seria** o
  instrumento certo. Só que ele também não roda — instrumento certo, nunca acionado. A v1 marcava
  *"🧪 estrutural (correto)"* sem `⚠️`; a exceção valia para o eixo do presence-gate, e some no eixo
  da lane.

---

## `[BACKLOG]` — achados sem contrato em 2 fontes, ou sem teste que os defenda

> Regra dura: comportamento com contrato em **≥2 fontes** vira UC com id; contrato em 1 fonte só, ou
> achado sem âncora, vira bullet sem id. UC com id sem teste é **órfão**, e o `casos-gate` G-2
> (required) bloqueia o merge de quem for atendê-lo
> ([proibicoes §5](../../../../memory/proibicoes.md) 2026-07-16).

- `[BACKLOG]` **Repromover os UC agora que a 2ª âncora existe.** O pré-requisito foi pago em
  2026-09-04 — o [`RUNBOOK-purchase-show.md`](../../../../memory/requisitos/Compras/_telas/RUNBOOK-purchase-show.md)
  mede dual-path, ordem 403→404, props e o barcode-por-omissão. O que **não** foi pago: cada
  promoção exige um teste que cite o UC (G-2), e o `ShowPageTest` de hoje é estrutural. Non-Goal
  marcado *"inferência pendente de Wagner"* segue fora — RUNBOOK mede comportamento, não decide
  intenção.
- `[BACKLOG]` **Sem timeline/histórico de auditoria.** O charter declara como Non-Goal, mas com a
  ressalva *"inferência pendente de Wagner"*. Enquanto for inferência do agente e não decisão de
  [W], não vira UC (*"UC não é canal de pedido"*).
- `[BACKLOG]` **Pagamento não é lançado nesta tela.** Mesmo caso do item acima: Non-Goal marcado
  como inferência pendente.
- `[BACKLOG]` **A autorização de servidor no destroy/update não é exercitada por teste algum desta
  tela.** UC-PURSHW-04 cobre só a face visível (o botão). A metade que importa — o backend recusar a
  ação de quem não pode — pertence ao Edit e hoje só tem
  [`UpdateCrossTenantIdorTest`](../../../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php),
  que está **sem lane E em quarentena** — dupla (ver `Edit.casos.md`).

---

## ⚠️ Divergências que precisam de [W] (não corrigidas aqui — são INTENÇÃO)

1. **O charter está `status: draft`** e diz explicitamente que [W] aprova Non-Goals + Anti-hooks
   antes de virar `live`. Três dos quatro Non-Goals trazem *"inferência pendente de Wagner"* — este
   `casos.md` só promoveu a UC os que têm apoio em outra fonte.
2. **A tela não tinha teste de comportamento nem E2E — e o que tem não roda.** `ShowPageTest` tem 44
   asserts e 0 requests, e **nenhuma lane executa o arquivo**. **Parcialmente fechado em
   2026-09-05:** a lane [`purchase-pest.yml`](../../../../.github/workflows/purchase-pest.yml)
   existe e os UC 01 `[T0]` e 05 `[V0]` têm defesa comportamental executada. Os UC **02, 03, 04 e
   06** seguem `🔴 sem lane`, e o `screen-coverage` continua marcando `Purchase · 0 de 4 E2E ·
   0 de 4 VRT` — o eixo E2E/VRT segue inteiramente descoberto.
3. **O UC-PURSHW-03 tem o CRITÉRIO errado, e o conserto muda o CONTRATO — por isso é [W].**
   A errata retirou o selo *"estrutural (correto)"* dele por ausência de lane. Medido depois, com a
   lane rodando: o assert `not->toContain('Barcode')` **falha**, e o que casa é o **comentário da
   linha 9** de `Show.tsx` — `// Mata bug 500 em prod (DNS1D::getBarcodePNG linha 430 quebrada)` —
   que documenta o próprio bug-fix. Mesmo COM lane ele não ficaria verde. A ironia é exata: a
   §Regressão deste UC diz que bug-fix por omissão é frágil porque *"nada no código explica por que
   aquilo não está lá"*; alguém escreveu a explicação — o conserto certo — e isso quebrou o teste
   que defendia o mesmo contrato. As saídas **não são equivalentes**:
   - **(a)** apertar o assert para casar **código** (`import ... Barcode`, `<Barcode`, `DNS1D::`)
     em vez da string nua — mantém o contrato e para de punir a explicação;
   - **(b)** remover o comentário de `Show.tsx:9` — faria passar, mas apaga justamente o que a
     §Regressão pede que exista;
   - **(c)** aceitar que o presence-gate não exprime este contrato e movê-lo para um teste que
     renderize a página.
   Recomendo **(a)**. Não apliquei porque mudar o critério de um UC é mudar contrato, não consertar
   bug.
4. **`UpdateCrossTenantIdorTest`, a defesa nomeada do IDOR de escrita desta controller, não roda em
   lugar nenhum.** Tem `markTestSkipped` fora de sqlite (schema sintético manual) e nenhuma lane
   sqlite o inclui. Além disso não exercita o controller: replica o padrão
   `Transaction::where('business_id',…)` inline e asserta sobre o Eloquent, com um único assert
   tocando o fonte por regex. O IDOR que ele nomeia está fechado no código — mas a prova disso é
   estrutural, e o `show()` desta mesma controller só passou a ter prova real agora (UC-01).

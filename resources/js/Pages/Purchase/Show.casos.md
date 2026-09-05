---
id: resources-js-pages-purchase-show-casos
casos: Detalhe da Compra · /purchases/{id}
irmaos: Show.charter.md (lei) · Show.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o escopo por tenant e a ausência do barcode são duráveis — não mudam quando o detalhe ganhar card novo.
owner: wagner
last_run: "2026-09-04"
last_run_ci: "🔴 NENHUM teste de tests/Feature/Purchase/ roda em lane alguma — 8 arquivos órfãos de CI. O gate uc-lane-coverage reprovou estes UC em 2026-09-05 por isso, e estava certo. Ver §Dívida de prova."
---

# Casos de Uso & Aceite — Detalhe da Compra (`/purchases/{id}`)

> **Âncora:** os UC derivam do [`Show.charter.md`](Show.charter.md) (Mission · Goals · Non-Goals ·
> Anti-hooks) cruzado com o
> [`show-visual-comparison.md`](../../../../memory/requisitos/Compras/_telas/show-visual-comparison.md)
> — **nunca do `Show.tsx`**: teste derivado do código é tautológico e trava o desvio em vez de
> pegá-lo ([proibicoes §5](../../../../memory/proibicoes.md) 2026-06-05).
>
> ⚠️ **Âncora mais fraca que a das telas irmãs, e isso é declarado, não disfarçado.** Index, Create
> e Edit têm RUNBOOK de tela em
> [`memory/requisitos/Compras/_telas/`](../../../../memory/requisitos/Compras/_telas/); **o Show
> não tem** — só o `visual-comparison`. O charter, por sua vez, está `status: draft` e marca vários
> Non-Goals como *"inferência pendente de Wagner"*. Por isso esta tela recebe **menos UC** que as
> irmãs: contrato em 1 fonte só vira `[BACKLOG]`, não UC com id.

---

## 🔴 Dívida de prova — **nenhum** teste desta tela roda em lane alguma

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

**Consequência:** **nenhum** UC desta tela tem defesa ativa — nem comportamental, nem estrutural. O
`Status` de todos é `🔴 sem lane`, **sem exceção**: os UC 03 e 06, cujo contrato *é* a ausência de um
literal no arquivo, tinham no presence-gate o instrumento certo — e o instrumento certo também não é
acionado. Nenhum recebe `✅`, e agora por dois motivos independentes: não há comportamento provado
*e* não há execução.

**Por que o conserto não está neste PR:** o gate diz, com todas as letras, *"conserto NÃO é mexer na
allowlist por conta própria — por que ela existe (custo de CI? teste instável escondido?) é decisão
[W]"*. Concordo: pôr 8 arquivos numa lane muda custo de CI e pode acordar vermelhos antigos. É
decisão do dono, com chip aberto.

---

## Rastreabilidade

| UC | Título | Tipo | Âncora de contrato | Teste que cita | Status |
|---|---|---|---|---|---|
| UC-PURSHW-01 | Detalhe nunca resolve compra de outro tenant | must `[T0]` | charter Non-Goal 4 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) | `ShowPageTest` | 🔴 sem lane |
| UC-PURSHW-02 | Dual-path: AJAX puro recebe Blade; navegação recebe Inertia | must | charter §Backend · [ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) | `ShowPageTest` | 🔴 sem lane |
| UC-PURSHW-03 | A tela **não** renderiza barcode (mata o 500 do legado) | must `[reg]` | charter §Backend (bug-fix declarado) | `ShowPageTest` | 🔴 sem lane |
| UC-PURSHW-04 | Editar/Excluir só aparecem com a permissão | must | charter Goals · Anti-hooks | `ShowPageTest` | 🔴 sem lane |
| UC-PURSHW-05 | A tela não recalcula totais — exibe o que o controller mandou | must `[V0]` | charter Non-Goal 3 | `ShowPageTest` | 🔴 sem lane |
| UC-PURSHW-06 | A Page não decide tenant — `business_id` vem das props | must `[T0]` | charter Non-Goal 4 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) | `ShowPageTest` | 🔴 sem lane |

---

## UC-PURSHW-01 · Detalhe nunca resolve compra de outro tenant · `must` `[T0]`

- **Persona:** Wagner / WR2 SC (biz=1) — o detalhe expõe fornecedor, custo unitário e margem; um
  vazamento aqui entrega a estrutura de custo do concorrente.
- **Aceite:** Dado uma compra do negócio 98 · Quando o usuário do negócio 1 pede
  `GET /purchases/{id}` · Então **não** recebe o detalhe (404/403 conforme o canon da rota). E,
  como **controle positivo**, o mesmo endpoint para uma compra do próprio negócio responde 200 —
  sem o par, um `abort` incondicional passaria no teste.
- **Teste:** [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) — *"Controller
  showInertia PRESERVA Tier 0 (recebe $purchase já scopado por business_id)"* · *"Controller
  showInertia NÃO usa withoutGlobalScopes sem comentário SUPERADMIN"*.
- **Contrato:** charter §Non-Goals item 4 (*"IDs de outro negócio devem retornar 404/403"*) ·
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o model `Transaction` **não tem global scope** — o isolamento é escrito
  à mão em cada query. Essa mesma ausência produziu um IDOR de escrita real no `update` desta mesma
  controller ([`UpdateCrossTenantIdorTest`](../../../../tests/Feature/Purchase/UpdateCrossTenantIdorTest.php)).
- **Status: 🔴 sem lane** — o assert casa texto no fonte e **nenhuma lane o executa**. Some-se que
  **não existe** teste que crie a compra no tenant vizinho e prove o 404 desta rota. É o UC mais
  caro do arquivo e o menos defendido — agora medido como zero defesa, não como defesa fraca.

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
- **Teste:** [`ShowPageTest`](../../../../tests/Feature/Purchase/ShowPageTest.php) — *"Page formata
  BRL (Intl.NumberFormat pt-BR)"* · *"Page tem Total geral (PT-BR)"* · *"Page tem 3 Cards top e 2
  Cards mid (Pagamentos/Totais)"*.
- **Contrato:** charter §Non-Goals item 3 (*"Não recalcula totais no cliente — os valores vêm
  prontos do controller"*) · [proibicoes §REGRA MESTRE — CÁLCULO DE VALOR](../../../../memory/proibicoes.md).
- **Regressão que defende:** o incidente de 2026-06-05 (biz=4) inflou 16 vendas ~×100k porque um
  número atravessou uma fronteira de formatação com a interpretação errada de separador. Cálculo em
  duas camadas é a porta por onde isso entra; esta tela é explicitamente **uma camada só**.
- **Status: 🔴 sem lane** — os asserts provariam que a *formatação* pt-BR está no arquivo, e já não
  provam a ausência de recálculo; sem lane, não provam nem a formatação. É `[V0]` com defesa **zero**
  — declarado, não maquiado.

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

- `[BACKLOG]` **Falta o RUNBOOK de tela do Show.** Index, Create e Edit têm; o Show não. Sem ele,
  os Non-Goals do charter (`draft`) são fonte única, e vários estão marcados *"inferência pendente
  de Wagner"* — o que os desqualifica como contrato executável. Criar o RUNBOOK é pré-requisito
  para os UC desta tela ganharem uma segunda âncora.
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
2. **A tela não tem teste de comportamento nem E2E — e o que tem não roda.** `ShowPageTest` tem 44
   asserts e 0 requests, o `screen-coverage` marca `Purchase · 0 de 4 E2E · 0 de 4 VRT`, e **nenhuma
   lane executa o arquivo** (medição de 2026-09-05, §Dívida de prova). **Não existe "verde da lane"
   a interpretar aqui:** não há lane. Pôr os 8 arquivos de `tests/Feature/Purchase/` numa lane muda
   custo de CI e pode acordar vermelhos antigos — é decisão [W], com chip aberto, e é pré-requisito
   para qualquer um destes UC ganhar defesa real.

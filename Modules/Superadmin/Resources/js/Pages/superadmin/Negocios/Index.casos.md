---
id: modules-superadmin-pages-superadmin-negocios-index-casos
casos: Superadmin · Negócios · /superadmin/business
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a lista que enxerga TODOS os negócios da plataforma — cross-tenant por desenho, o inverso do resto do ERP. Sem casos, a próxima sessão "conserta" isso aplicando escopo de tenant e quebra o produto. E a paginação server-side tem uma armadilha silenciosa (join 1-para-N que faz o total mentir) que só um caso pega.
owner: wagner
last_run: "2026-10-05"
revalidacao_2026_10_05: "O G-6 acusou stale porque o `.tsx` mudou na thread Superadmin 02: o drawer ganhou as seções que eram da página show (cadastro, locais, usuários) e o cabeçalho migrou para o PageHeader canon. Os UC-SANEG-01..08 não mudam de veredito — a lista, os filtros e as 4 seções do drawer ficaram iguais. O que a mudança acrescenta está nos UC-SANEG-09 e 10, novos e com teste. O bump de data é higiene do gate, não afirmação de re-execução."
revalidacao_2026_08_21: "O G-6 acusou stale porque o `.tsx` foi tocado pela SA-O4a: `Select`, `plural` e `tomDaAssinatura` saíram deste arquivo para `../_components/assinatura` — a tela de Assinaturas precisava do MESMO mapa rótulo→tom, e uma segunda cópia é como as duas divergem no primeiro status novo (§5 proibicoes 2026-08-02). A extração é MOVIMENTO, não alteração: o `Select` foi copiado byte a byte (inclusive o `aria-label`) e o render é idêntico. Nenhum UC deste arquivo muda de veredito. O bump de data é higiene do gate, não afirmação de re-execução."
last_run_ci: "_pendente_ — o trio nasce nesta onda (SA-O2). O veredito por UC entra no manifesto quando a lane rodar; até lá o Status é 🧪, nunca ✅."
revalidacao_2026_08_20: "O `.tsx` mudou (G-6 acusou stale), então o `last_run` sobe — mas a mudança foi ATRIBUTO PURO: 4 `data-contract` em elementos que já existiam, zero alteração de DOM, lógica ou copy (diff 4/4). Nenhum UC deste arquivo muda de veredito por causa dela; o que os UCs afirmam continua exatamente o mesmo. O que a mudança ACRESCENTA é defesa: as copy da tabela, dos filtros e das 4 seções do drawer passam a ser travadas por `governance/design/contracts/superadmin-negocios.contract.json`, verificado no CI — antes elas só viviam na prosa daqui."
---

# Casos de Uso & Aceite — Superadmin · Negócios (`/superadmin/business`)

> **Âncora:** UC-SA-004 (achar negócio por número) e UC-SA-016 (isolamento) do F1 do Cowork
> §2, cruzados com as invariantes do
> [RUNBOOK-negocios](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-negocios.md) §5
> e com a exceção de multi-tenant da
> [ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
> Os UCs derivam do **contrato**, nunca do `Index.tsx` nem do controller.

---

## UC-SANEG-01 · A lista responde em Inertia, não em DataTables · `must`

**Dado** que sou superadmin autenticado
**Quando** abro `/superadmin/business`
**Então** recebo Inertia com o componente `superadmin/Negocios/Index` — não a view Blade nem
o JSON do `Datatables::of(...)`.

Status: 🧪

---

## UC-SANEG-02 · Um negócio é UMA linha, e o total não mente · `must`

**Dado** um negócio com **mais de um local** e **mais de uma assinatura** no histórico
**Quando** a lista é paginada
**Então** ele aparece **uma única vez**, e o total da consulta bate com a contagem real de
negócios que satisfazem o filtro.

> É a armadilha que motivou a troca de query: o legado fazia `leftJoin('business_locations')`
> + `groupBy`, o que serve ao DataTables mas quebra o `COUNT` do `paginate()`. Local virou
> subquery escalar e a assinatura entra pela mais recente (`MAX(id)`).

Status: 🧪

---

## UC-SANEG-03 · Admin de negócio é barrado ENQUANTO o superadmin passa · `must` `[T0]`

**Dado** um admin de negócio (sem a permissão e fora da lista de usernames)
**Quando** acessa `/superadmin/business`
**Então** é barrado — **e** o superadmin, no mesmo cenário, recebe 200.

> As duas metades no mesmo caso de propósito: `403` sozinho não discrimina nada. No dashboard
> esse mesmo caso nasceu carimbo (todos tomavam 403, inclusive o superadmin) e só foi pego
> porque as duas pontas passaram a ser exercidas juntas.

Status: 🧪

---

## UC-SANEG-04 · A lista enxerga negócio de TODOS os business · `must` `[T0]`

**Dado** negócios de mais de um `business_id`
**Quando** a lista é montada sem filtro
**Então** o total cobre **todos** os negócios da plataforma, não só o do usuário logado.

> Cross-tenant aqui é **intencional** (ADR 0093 §exceções Superadmin). Este caso existe para
> impedir que alguém "conserte" a tela aplicando `business_id` scope.

Status: 🧪

---

## UC-SANEG-05 · Filtro fora da lista não chega na query · `must`

**Dado** uma query string com `assinatura=DROP` ou `status=qualquer-coisa`
**Quando** a página é montada
**Então** o valor é descartado (vira `null`) e a lista responde como se o filtro não existisse
— nada do request chega cru na consulta.

Status: 🧪

---

## UC-SANEG-06 · Busca por número só com dígito puro · `must`

**Dado** o termo de busca `"12"`
**Quando** a busca roda
**Então** o negócio **#12** entra no resultado.

**E dado** o termo `"12 anos"`, o `business.id` **não** entra na comparação — só os campos de
texto — porque cast implícito de string para inteiro casa linha errada.

Status: 🧪

---

## UC-SANEG-07 · O drawer é estado da lista, não outra tela · `must`

**Dado** que clico numa linha
**Quando** o detalhe abre
**Então** ele vem por **partial reload** (`?negocio=<id>`, só as props `detalhe` e `aberto`) —
sem rota de página nova, sem perder filtro, busca ou posição de scroll.

**E** `esc` fecha, voltando a URL ao estado sem `?negocio`.

Status: 🧪

---

## UC-SANEG-08 · O drawer não inventa o que o dado não liga · `must`

**Dado** que a cobrança recorrente vive em `rb_subscriptions` → `contacts` (biz=1) e **não
existe FK** ligando contato ao `business`
**Quando** o drawer de um negócio é montado
**Então** o **valor recorrente não é exibido**, e a tela **diz por quê** — em vez de casar por
nome, que acerta 4 de 109.

**E dado** um pacote com teto `0` (= ilimitado, confirmado por [W] em 2026-08-19)
**Então** a linha de uso mostra o consumo com a palavra "ilimitado" e **não** desenha barra de
progresso — progresso contra ilimitado não informa nada.

Status: 🧪

---

## UC-SANEG-09 · A página show virou o drawer · `must`

**Dado** um link antigo para `/superadmin/business/{id}`
**Quando** o superadmin o abre
**Então** cai na lista com o drawer daquele negócio aberto (`?negocio=<id>`) — a página Blade
saiu na thread Superadmin 02.

Status: 🧪

---

## UC-SANEG-10 · O drawer carrega o que a página show mostrava · `must`

**Dado** um negócio com usuários, entre eles o superadmin logado e um agente de comissão
**Quando** o drawer é montado
**Então** ele traz os dados do cadastro (moeda, impostos, fuso, quem cadastrou, logo), os locais,
os usuários — **sem** o superadmin logado e **sem** agente de comissão, como a lista da show — e,
em cada assinatura, pago via, transação, fim do teste e quem lançou.

**E** "Definir senha" e "Entrar como" só aparecem para quem tem `user.update`, como na show.
Decisão [W] 2026-10-05: nada que a show mostrava some.

Status: 🧪

---

## UC-SANEG-11 · Criar é o drawer da lista · `must`

**Dado** um link antigo para `/superadmin/business/create`
**Quando** o superadmin o abre
**Então** cai na lista com o drawer "Novo negócio" aberto (`?novo=1`) — a Blade `business.create`
saiu na thread Superadmin 02 (PR-2). O botão "Novo negócio" e a tecla `n` abrem o mesmo drawer.

Status: 🧪

---

## UC-SANEG-12 · As opções do formulário só vêm com o drawer aberto · `must`

**Dado** a lista sem `?novo`
**Então** `formNovo` é nulo — nenhuma consulta de moedas, fusos, pacotes ou gateways.
**E dado** `?novo=1`
**Então** `formNovo` traz moedas, fusos (com `America/Sao_Paulo`), pacotes ativos e gateways
configurados — as mesmas listas da Blade.

Status: 🧪

---

## UC-SANEG-13 · Duplicado e pacote sem "pago via" voltam como erro do campo · `must`

**Dado** um usuário ou e-mail que já tem conta, ou um pacote escolhido sem "pago via"
**Quando** o formulário é enviado
**Então** o servidor recusa com erro em cada campo e **nenhum negócio é criado**. A Blade checava
o duplicado no navegador (`/business/register/check-*`); o drawer checa no `StoreBusinessRequest`.

> Decisão [W] 2026-10-05: layout do protótipo, regras de hoje. O dono entra com usuário e senha
> definidos no drawer; convite por e-mail, dias de teste e CNPJ são do protótipo e não existem no
> backend.

Status: 🧪

---

## Testes mínimos

- DQE: 1 negócio com 2 locais, 1 com 2 assinaturas, 1 sem assinatura, 1 inativo.
- Borda: filtro que zera a lista (vazio cita o termo digitado); última página ao filtrar;
  nome de negócio longo.
- Permissão: admin de negócio barrado; superadmin 200.
- Plural PT-BR: 1 negócio / 2 negócios.

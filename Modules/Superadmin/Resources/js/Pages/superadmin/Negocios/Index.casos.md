---
id: modules-superadmin-pages-superadmin-negocios-index-casos
casos: Superadmin · Negócios · /superadmin/business
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a lista que enxerga TODOS os negócios da plataforma — cross-tenant por desenho, o inverso do resto do ERP. Sem casos, a próxima sessão "conserta" isso aplicando escopo de tenant e quebra o produto. E a paginação server-side tem uma armadilha silenciosa (join 1-para-N que faz o total mentir) que só um caso pega.
owner: wagner
last_run: "2026-08-19"
last_run_ci: "_pendente_ — o trio nasce nesta onda (SA-O2). O veredito por UC entra no manifesto quando a lane rodar; até lá o Status é 🧪, nunca ✅."
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

## Testes mínimos

- DQE: 1 negócio com 2 locais, 1 com 2 assinaturas, 1 sem assinatura, 1 inativo.
- Borda: filtro que zera a lista (vazio cita o termo digitado); última página ao filtrar;
  nome de negócio longo.
- Permissão: admin de negócio barrado; superadmin 200.
- Plural PT-BR: 1 negócio / 2 negócios.

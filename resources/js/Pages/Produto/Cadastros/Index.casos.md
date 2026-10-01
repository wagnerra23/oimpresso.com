---
id: resources-js-pages-produto-cadastros-index-casos
casos: Produto · Cadastros de apoio · /units (abas)
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: unidade e marca são referenciadas por todo produto. Apagar uma em uso deixa produto apontando pro nada; errar o escopo mostra cadastro de outro negócio; errar o desvio Inertia × DataTables entrega JSON cru no lugar da tela.
owner: wagner
last_run: "2026-10-01"
last_run_ci: "_pendente_ — o trio nasce na thread Produto/02. O veredito por UC entra no manifesto quando a lane estoque-pest rodar; até lá o Status é 🧪."
---

# Casos de Uso & Aceite — Produto · Cadastros de apoio (`/units`)

> **Âncora:** o [charter](Index.charter.md) (R1, R3, R4, R6 e os Anti-hooks), copiado do trio proposto
> `cowork-inbox/produto-telas-novas/Cadastros.casos.md` (UC-CAD-01..16, F1 [CC] 2026-08-21), a ficha
> `05-cadastros.md` do playbook e as permissões que as Blades já exigiam (`unit.*`, `brand.*`).
> Os UCs não derivam do `.tsx`. A coluna "origem" diz de qual UC-CAD cada um veio.

---

## UC-PCADAP-01 · `/units` abre a tela de abas · `must`

Origem: charter R1 · D3 [W] 2026-10-01 (Page parametrizada).

**Dado** que tenho `unit.view`
**Quando** abro `/units`
**Então** recebo Inertia `Produto/Cadastros/Index` com a aba Unidades ativa, e `?aba=marcas` abre na aba Marcas.

Status: 🧪

---

## UC-PCADAP-02 · Visita Inertia não vira JSON do DataTables · `must`

Origem: Anti-hook do charter (§5 2026-09-08).

**Dado** que o browser manda `X-Inertia` e `X-Requested-With` juntos
**Quando** a tela pede `/units`
**Então** recebe a prop `unidades` — e um ajax **sem** `X-Inertia` (o da tela clássica) continua
recebendo o JSON do DataTables, e `?classico=1` devolve a view `unit.index`.

Status: 🧪

---

## UC-PCADAP-03 · Cada aba pela sua permissão · `must`

Origem: ficha 05 ("props `can` por aba; aba sem view → no-perm").

**Dado** um usuário só com `unit.view`
**Quando** abre `/units`
**Então** `can.marcas.view` vem falso e a lista de marcas não vem; e um usuário sem `unit.*` nem
`brand.*` recebe 403.

Status: 🧪

---

## UC-PCADAP-04 · Cadastro de outro negócio não aparece · `must` `[T0]`

**Dado** uma unidade e uma marca no meu negócio e outras num negócio vizinho
**Quando** abro a tela
**Então** vejo as minhas e não vejo as do vizinho.

Status: 🧪

---

## UC-PCADAP-05 · Excluir unidade em uso é recusado, e a tela sabe antes · `must`

Origem: UC-CAD-03 + UC-CAD-05 (a contagem é a mesma que vira o link pro índice filtrado).

**Dado** que 1 produto usa a unidade "m²"
**Quando** abro a tela e depois tento excluir "m²"
**Então** a linha traz `em_uso = 1` (a confirmação diz quantos usam e não oferece Excluir) e o
servidor recusa a exclusão — a unidade continua lá.

Status: 🧪

---

## UC-PCADAP-06 · Excluir unidade livre · `must`

Origem: UC-CAD-04.

**Dado** que nenhum produto usa "Quilograma"
**Quando** excluo
**Então** o servidor aceita e a unidade sai da lista.

Status: 🧪

---

## UC-PCADAP-07 · Marca em uso não sai · `must`

Origem: charter R4. Antes desta thread o legado apagava a marca em uso.

**Dado** que 1 produto usa a marca "Vinilcor"
**Quando** tento excluir
**Então** o servidor recusa dizendo quantos produtos usam, e a marca continua lá.

Status: 🧪

---

## UC-PCADAP-08 · Múltiplo de base escrito na linha · `should`

Origem: charter R6 · UC-CAD-02 ("Então").

**Dado** a unidade "Caixa" (cx) cadastrada como 1000 × "Unidade" (Un)
**Quando** abro a aba Unidades
**Então** a linha mostra `1 cx = 1000 Un`.

Status: 🧪

---

## Backlog (sem teste ainda — não é contrato até ganhar teste que o cite)

- [BACKLOG] Criar e editar em modal na própria tela (UC-CAD-01, UC-CAD-02 "Quando", charter R2) — hoje vai pros modais da Blade (`?classico=1`).
- [BACKLOG] Aba Categorias em Inertia (UC-CAD-10, UC-CAD-11) — pendente no `_saida-02`.
- [BACKLOG] Abas Variações, Grupos de preço e Garantias (UC-CAD-08, UC-CAD-09, UC-CAD-13) — thread 03.
- [BACKLOG] Marca da Oficina na lista (UC-CAD-12), atalho `/` e busca sem resultado (UC-CAD-06/07), estados primeira-vez/carregando/densidade (UC-CAD-14..16) — a tela já tem `/`, busca e primeira-vez; falta teste de browser.

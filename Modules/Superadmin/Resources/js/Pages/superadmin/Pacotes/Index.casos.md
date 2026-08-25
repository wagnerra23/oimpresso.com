---
id: modules-superadmin-pages-superadmin-pacotes-index-casos
casos: Superadmin · Pacotes · /superadmin/packages
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a tela onde `0` significa o CONTRÁRIO do que parece — zero é "sem teto", não "nenhum". Sem caso travando isso, a próxima sessão "simplifica" a renderização e a grade comercial passa a dizer o oposto do contrato. E é a tela vizinha da vitrine pública `/pricing`: unificar as duas consultas expõe pacote privado ao mundo.
owner: wagner
last_run: "2026-08-21"
nota_data: "O trio nasceu em 20/08, mas o PR foi RECRIADO do main fresco em 21/08 (o #6066 ficou irreconciliável depois do squash do #6064). O commit do `.tsx` é de 21/08, então o G-6 compara contra esta data — não há re-execução a declarar, o conteúdo é o mesmo do dia anterior."
last_run_ci: "_pendente_ — o trio nasce nesta onda (SA-O4c). O veredito por UC entra no manifesto quando a lane rodar; até lá o Status é 🧪, nunca ✅."
---

# Casos de Uso & Aceite — Superadmin · Pacotes (`/superadmin/packages`)

> **Âncora:** UC-SA-010 e UC-SA-011 do F1 do Cowork §2
> (`cowork-inbox/SUPERADMIN-F1-2026-08-18.md`) — que descrevem o **FormDrawer**, entregue na
> SA-O4d — cruzados com as regras **R5**, **R6** e **R7** do mesmo F1 e com as invariantes do
> [RUNBOOK-pacotes](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-pacotes.md) §2/§7.
> Os UCs derivam do **contrato**, nunca do `Index.tsx` nem do controller.

---

## UC-SAPAC-01 · A grade responde em Inertia, não em Blade · `must`

**Dado** que sou superadmin autenticado
**Quando** abro `/superadmin/packages`
**Então** recebo Inertia com o componente `superadmin/Pacotes/Index` — não a view
`superadmin::packages.index`.

Status: 🧪

---

## UC-SAPAC-02 · Admin de negócio é barrado ENQUANTO o superadmin passa · `must` `[T0]`

**Dado** um usuário comum de um negócio qualquer
**Quando** ele acessa a rota
**Então** recebe **403** — e o mesmo teste prova que o superadmin recebe **200**.

Status: 🧪

---

## UC-SAPAC-03 · `0` chega como número, não como texto · `must`

**Dado** um pacote com `location_count = 0`
**Quando** o payload é montado
**Então** `locais` vale **`0` (número)** — o backend **não** traduz para "ilimitado".

> Quem escreve "ilimitado" é a tela, porque é ela que conhece o plural PT-BR e precisa do valor
> pra decidir. Mandar string pronta tira a decisão de quem tem o vocabulário — e é a
> "simplificação" que faria a tela mostrar `0 locais`, o oposto do que o dado significa.

Status: 🧪

---

## UC-SAPAC-04 · O catálogo é completo — inativo e privado inclusos · `must`

**Dado** um pacote inativo e um pacote privado
**Quando** o superadmin abre a grade
**Então** os dois aparecem.

> Pacote inativo aparece porque contrato antigo ainda aponta pra ele (**R6**); privado aparece
> porque esta é a tela de quem o atribui (**R7**).

Status: 🧪

---

## UC-SAPAC-05 · Esta tela e a vitrine pública NÃO veem a mesma coisa · `must` `[T0]`

**Dado** um pacote com `is_private = 1`
**Quando** comparo o que `/superadmin/packages` devolve com o que `Package::listPackages(true)`
(a consulta de `/pricing`) devolve
**Então** o privado está **na primeira e ausente da segunda**.

> É o caso que impede a "unificação" das duas consultas. Unificar para não repetir código
> vazaria grade privada para o site público — quebra a **R7**.

Status: 🧪

---

## UC-SAPAC-06 · Assinantes é contagem real, e histórica · `should`

**Dado** um pacote com assinatura já **vencida** apontando pra ele
**Quando** o card é montado
**Então** essa assinatura **conta** em `assinantes`.

> A contagem existe pra responder "dá pra excluir este pacote?". Contar só vigentes diria que
> sim quando há contrato histórico preso a ele.

Status: 🧪

---

## UC-SAPAC-07 · Módulos liberados chegam com rótulo humano · `should`

**Dado** um pacote com uma permissão de módulo ligada em `custom_permissions`
**Quando** o payload é montado
**Então** `modulos` traz o **rótulo** do módulo, e chaves desligadas não aparecem.

Status: 🧪

---

## UC-SAPAC-08 · A grade não escreve · `must` `[T0]`

**Dado** o catálogo em qualquer estado
**Quando** o superadmin apenas **abre** a tela
**Então** nenhuma linha de `packages` muda — nem `is_active`, nem `price`, nem `sort_order`.

Status: 🧪

---

## Testes mínimos (do F1 §2)

- **DQE:** 1 pacote sem assinante · 1 pacote inativo · 1 pacote privado · 1 com limite `0`.
- **Borda:** catálogo vazio (vazio explica o que é um pacote); nome de pacote longo (elipse);
  pacote sem módulo liberado (a seção some, não fica vazia).
- **Plural PT-BR:** 1 local / 2 locais · 1 assinante / 2 assinantes · 6 meses (nunca "mêses").
- **Mono nunca quebra:** valor, contadores.
- **Permissão:** admin de negócio 403; superadmin 200 (UC-SAPAC-02).

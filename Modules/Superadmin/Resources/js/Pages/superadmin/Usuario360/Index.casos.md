---
id: modules-superadmin-pages-superadmin-usuario360-index-casos
casos: Superadmin · Usuário 360 · lista de busca · /superadmin/usuarios
irmaos: Index.charter.md (lei) · Index.tsx (tela) · Show.casos.md (o perfil que a lista abre)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: é a única busca de usuário que atravessa TODOS os negócios da plataforma — cross-tenant por desenho, o inverso do resto do ERP. Sem casos, a próxima sessão "conserta" a lista aplicando escopo de business e o superadmin deixa de achar o funcionário que está investigando.
owner: wagner
last_run: "2026-09-30"
last_run_ci: "_pendente_ — o trio nasce na thread Superadmin/01. O veredito por UC entra no manifesto quando a lane verticais-pest rodar; até lá o Status é 🧪, nunca ✅."
---

# Casos de Uso & Aceite — Superadmin · Usuário 360 · lista (`/superadmin/usuarios`)

> **Âncora:** [Index.charter.md](Index.charter.md) (Mission, Goals, Anti-hooks e Rotas reais) +
> [SPEC US-SUPER-010](../../../../../../../memory/requisitos/Superadmin/SPEC.md) ("cross-tenant
> explícito") + a exceção Superadmin da
> [ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
> Os UCs derivam do **charter e do SPEC**, nunca do `Index.tsx` nem do controller.
>
> Teste: `Modules/Superadmin/Tests/Feature/Usuario360ContratoTest.php` (lane `verticais-pest`, MySQL).

---

## UC-SAUL-01 · A lista responde Inertia com o contrato de props do charter · `must`

**Dado** que sou superadmin autenticado
**Quando** abro `/superadmin/usuarios`
**Então** recebo Inertia com o componente `superadmin/Usuario360/Index` e as props `users` e
`filters` — o contrato declarado em §Rotas (reais) do charter.

Status: 🧪

---

## UC-SAUL-02 · Admin de negócio é barrado ENQUANTO o superadmin passa · `must` `[T0]`

**Dado** um admin de negócio (fora da lista de usernames de superadmin)
**Quando** acessa `/superadmin/usuarios`
**Então** é barrado — **e** o superadmin, no mesmo cenário, recebe 200.

> As duas metades no mesmo caso de propósito: `403` sozinho não discrimina nada (lição do
> UC-SANEG-03, onde o caso nasceu carimbo).

Status: 🧪

---

## UC-SAUL-03 · A busca casa nome, email e username, e devolve o termo · `must`

**Dado** um usuário cujo `username` contém um marcador único
**Quando** busco por esse marcador (`?q=`)
**Então** ele aparece em `users`, cada linha traz `id`, `username`, `email`, `nome`,
`business_id`, `status` e `user_type` (os campos de §Rotas do charter), e `filters.q` devolve o
termo buscado.

**E dado** um termo que não casa ninguém, `users` volta vazio — a tela mostra o empty state de
sem-resultado (Goal do charter), não erro.

Status: 🧪

---

## UC-SAUL-04 · A busca enxerga usuário de TODOS os business · `must` `[T0]`

**Dado** usuários com o mesmo marcador em **dois** `business_id` diferentes
**Quando** o superadmin (logado no primeiro) busca pelo marcador
**Então** os **dois** aparecem — inclusive o do business que não é o dele.

> Cross-tenant aqui é **intencional** (charter, Anti-hook *"Não acoplar a `business_id` de
> sessão"*; ADR 0093 §exceções). Este caso existe para impedir que alguém "conserte" a lista
> aplicando escopo de tenant.

Status: 🧪

---

## Ainda sem teste (prosa honesta — viram UC quando ganharem teste que os cite)

- [BACKLOG] Debounce de 300 ms e partial reload `only:['users','filters']` (Goals do charter) — comportamento de front, pede E2E.
- [BACKLOG] Skeleton durante a busca, sem "flash" de empty state (UX targets) — idem.
- [BACKLOG] Paginação client-side em lotes de 10 sobre o conjunto devolvido (Goals) — idem.
- [BACKLOG] Não persistir a busca em localStorage nem autocompletar (Anti-hooks) — idem.

## Testes mínimos

- DQE: 1 superadmin no tenant fictício 98 · 1 usuário-alvo no 98 · 1 usuário-alvo em outro business.
- Borda: termo sem resultado; termo que casa só pelo username.
- Permissão: admin de negócio barrado; superadmin 200.

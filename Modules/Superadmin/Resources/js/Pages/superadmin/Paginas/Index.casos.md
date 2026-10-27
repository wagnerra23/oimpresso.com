---
id: modules-superadmin-pages-superadmin-paginas-index-casos
casos: Superadmin · Páginas do site · /superadmin/frontend-pages
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: Termos de uso e política de privacidade moram aqui; uma edição errada tem efeito legal, e a tela é global (sem tenant), então a trava é só o gate superadmin.
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Superadmin · Páginas do site

> US-SUPER-005 do [SPEC](../../../../../../../memory/requisitos/Superadmin/SPEC.md) (editar páginas
> estáticas do site público) · decisão [W] D-PAG 2026-10-07 · cutover pedido pelo [W] em 2026-10-27.
>
> UCs derivados do SPEC e do **charter** (Goals · Non-Goals · Automation Anti-hooks); o
> `PageController` só confirma. **Não derivados do `.tsx`.**
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não
> regravado) · ⬜ não verificado · ❌ quebrou.
>
> **Onde roda:** `Modules/Superadmin/Tests/Feature/SuperadminPaginasContratoTest.php`, lane
> `verticais-pest.yml` (MySQL). Em SQLite o teste pula — skip não é verde.
>
> **Isolamento 98×99:** não se aplica. `superadmin_frontend_pages` é conteúdo global do site, sem
> `business_id` (ADR 0093 §exceções Superadmin). A trava Tier 0 aqui é o gate superadmin (UC-SAPAG-02).

---

## UC-SAPAG-01 · A tela React é o padrão e não há mais tela Blade de criar nem de editar
- **Persona:** [W], superadmin, abrindo `/superadmin/frontend-pages`.
- **Aceite:** Dado que sou superadmin · Quando abro a lista · Então recebo Inertia `superadmin/Paginas/Index`; Quando peço `/create` ou `/{id}/edit` · Então não há rota GET (404 ou 405), porque criar e editar são o drawer da tela.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPaginasContratoTest.php` — `UC-SAPAG-01 · a tela React é o padrão e as telas Blade de criar/editar não existem mais`.
- **Regressão que defende:** Automation Anti-hook do charter "não recriar as rotas create/edit/show"; cutover F5 (ADR 0104).
- **Status: ⬜** — caiu no run 40498968483 esperando 404 onde o Laravel dá 405 (`/create` casa com o PUT/DELETE de `/{id}`); o teste foi corrigido para aceitar os dois e espera o próximo run.

---

## UC-SAPAG-02 · Usuário comum é barrado ENQUANTO o superadmin passa · `[T0]`
- **Persona:** usuário de um negócio sem a permissão `superadmin`.
- **Aceite:** Dado um usuário sem `superadmin` · Quando abre a tela · Então é barrado (302 ou 403); Dado o superadmin · Então recebe 200 — no mesmo teste.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPaginasContratoTest.php` — `UC-SAPAG-02 · usuário comum é barrado enquanto o superadmin passa`.
- **Regressão que defende:** a tela é global; o gate superadmin é a única trava (charter Mission + Anti-hook de escopo).
- **Status: 🧪** — passou no run 40498968483 da lane Verticais (2026-10-27, 1094 passed · 8041 assertions na lane).

---

## UC-SAPAG-03 · A lista traz a página com resumo sem marcação
- **Persona:** [W] conferindo as páginas publicadas.
- **Aceite:** Dado uma página com HTML no conteúdo · Quando a lista carrega · Então ela vem com slug, ordem e visibilidade, e o `resumo` sem tag.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPaginasContratoTest.php` — `UC-SAPAG-03 · a lista traz a página com resumo sem marcação`.
- **Regressão que defende:** Anti-hook do charter "não imprimir o conteúdo como HTML na lista".
- **Status: 🧪** — passou no run 40498968483 da lane Verticais (2026-10-27), head c90951010f.

---

## UC-SAPAG-04 · Slug repetido é recusado no campo e não duplica
- **Persona:** [W] criando uma página nova.
- **Aceite:** Dado uma página com um slug · Quando crio outra com o mesmo slug · Então recebo erro em `slug` e continua existindo uma só página com aquele slug.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPaginasContratoTest.php` — `UC-SAPAG-04 · slug repetido é recusado no campo e não duplica`.
- **Regressão que defende:** charter Goals "slug repetido aparece como erro no campo"; `/page/{slug}` não pode apontar para duas páginas.
- **Status: 🧪** — passou no run 40498968483 da lane Verticais (2026-10-27), head c90951010f.

---

## UC-SAPAG-05 · Editar pela tela grava e volta para a lista
- **Persona:** [W] corrigindo o texto ou a visibilidade de uma página.
- **Aceite:** Dado uma página · Quando salvo título, ordem e visibilidade novos · Então o banco guarda os valores e o redirect volta para `/superadmin/frontend-pages`.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPaginasContratoTest.php` — `UC-SAPAG-05 · editar pela tela grava e volta para a lista`.
- **Regressão que defende:** US-SUPER-005 (editar página) e o cutover (o redirect não pode voltar para a Blade).
- **Status: 🧪** — passou no run 40498968483 da lane Verticais (2026-10-27), já com o cutover (head c90951010f).

---

## UC-SAPAG-06 · Excluir pela tela remove a página
- **Persona:** [W] tirando uma página do site.
- **Aceite:** Dado uma página · Quando confirmo a exclusão · Então ela some do banco e o redirect volta para a lista.
- **Teste:** `Modules/Superadmin/Tests/Feature/SuperadminPaginasContratoTest.php` — `UC-SAPAG-06 · excluir pela tela remove a página e volta para a lista`.
- **Regressão que defende:** charter Goals "exclusão pede confirmação" e o cutover.
- **Status: 🧪** — passou no run 40498968483 da lane Verticais (2026-10-27), já com o cutover (head c90951010f).

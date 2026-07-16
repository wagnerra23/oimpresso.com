---
casos: Edição de cliente · /contacts/{id}/edit + autosave inline no drawer
irmaos: Edit.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — abrir/salvar edição e o isolamento por tenant não mudam no refactor.
owner: wagner
last_run: "2026-07-08"
---

# Casos de Uso & Aceite — Edição de cliente

> Fase 2 (lanes do Cliente). UCs ancorados em feature tests HTTP reais (Pest, CT100, lane ativa): `ClienteEditInertiaTest` (edit/update Inertia + Tier 0) e `ClienteDrawerCadastroAutosaveTest` (PATCH autosave inline do drawer 760).
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

---

## UC-CEDI-01 · Abrir a edição já traz os dados fiscais preenchidos
- **Persona:** Larissa — abre pra corrigir; os campos BR (CPF/CNPJ, IE, nome fantasia) têm que vir preenchidos, não vazios.
- **Aceite:** Dado um cliente existente · Quando faço `GET /contacts/{id}/edit` · Então o payload Inertia `props.contact` traz os campos BR **não-nulos**.
- **Teste:** `tests/Feature/Cliente/ClienteEditInertiaTest.php` — `GET /contacts/{id}/edit Inertia retorna campos BR no props.contact (não-null)`.
- **Regressão que defende:** bug 2026-05-26 — `edit()` omitia os campos BR no payload → Edit.tsx exibia vazio.
- **Status: 🧪** — feature test HTTP passa no CI; ✅ com o manifesto regravado.

---

## UC-CEDI-02 · Salvar a edição atualiza o cadastro e confirma
- **Persona:** Larissa — corrigiu o documento; ao salvar, tem que persistir e dar retorno de sucesso.
- **Aceite:** Dado o form de edição · Quando faço `PUT /contacts/{id}` com um `cpf_cnpj` novo · Então persiste e redireciona com flash de sucesso.
- **Teste:** `tests/Feature/Cliente/ClienteEditInertiaTest.php` — `PUT /contacts/{id} via Inertia atualiza cpf_cnpj e redireciona com flash`.
- **Status: 🧪** — feature test HTTP passa; ✅ com o manifesto regravado.

---

## UC-CEDI-03 · Não dá pra editar cliente de outro tenant (Tier 0)
- **Persona:** operador — só enxerga e edita clientes do próprio negócio (Cliente é PII-heavy, ADR 0093).
- **Aceite:** Dado um cliente de OUTRO `business_id` · Quando tento `GET /contacts/{id}/edit` · Então recebo **404** (o global scope esconde o registro estrangeiro).
- **Teste:** `tests/Feature/Cliente/ClienteEditInertiaTest.php` — `Tier 0 — user de outro business recebe 404 ao tentar GET edit`.
- **Regressão que defende:** vazamento cross-tenant no edit (Tier 0 IRREVOGÁVEL).
- **Status: 🧪** — feature test HTTP passa; ✅ com o manifesto regravado.

---

## UC-CEDI-04 · Autosave inline no drawer valida o documento (mod 11)
- **Persona:** Larissa — edita direto no drawer 760, campo a campo (autosave on blur); documento inválido não pode ser salvo.
- **Aceite:** Dado o drawer aberto · Quando faço `PATCH /cliente/{id}/identificacao` com `tax_number` que falha no mod 11 · Então volta **422**; com CPF/CNPJ válido volta **200** e persiste.
- **Teste:** `tests/Feature/Cliente/ClienteDrawerCadastroAutosaveTest.php` — `PATCH /cliente/{id}/identificacao ... mod 11 invalido retorna 422` + `... CPF valido mod 11 aceito`.
- **Status: 🧪** — feature test HTTP passa; ✅ com o manifesto regravado.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG] `opening_balance` exibido já descontado do pago** (via `getTotalAmountPaid`) — anchor em teste de payload dedicado.
- **[BACKLOG] Autosave dos outros tabs (contato/endereço/comercial/classificação)** — expandir citando `ClienteDrawerCadastroAutosaveTest` casos restantes.

## Como rodar a suíte
1. **Pest:** `docker exec oimpresso-staging php artisan test --filter="ClienteEditInertiaTest|ClienteDrawerCadastroAutosaveTest"` no CT100.
2. **Manifesto:** `npm run casos:results` → 🧪 vira ✅.
3. **Cadência:** rodar ao fim de toda mexida em `Edit.tsx` / `_form/ClienteForm` / `ClienteAutosaveController`.

## Trilha do tempo
- 2026-07-08 · [CC] criado — Fase 2 (lanes Cliente). 4 UCs ancorados em `ClienteEditInertiaTest` (edit/update + Tier 0 404) e `ClienteDrawerCadastroAutosaveTest` (PATCH autosave mod 11). Refs: [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-1/G-2 · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) · [ADR 0179](../../../../memory/decisions/0179-cliente-drawer-760px-substitui-show-fullpage.md).

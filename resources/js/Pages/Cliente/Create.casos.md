---
casos: Cadastro de novo cliente · /contacts/create
irmaos: Create.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — a validação fiscal do cadastro não muda no refactor.
owner: wagner
last_run: "2026-07-08"
---

# Casos de Uso & Aceite — Cadastro de novo cliente

> Fase 2 (lanes do Cliente). UCs ancorados no `StoreContactRequestTest` (Pest, CT100, lane ativa) — validação fiscal SEFAZ (mod 11) do `StoreContactRequest` wirado em `ContactController@store`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não regravado) · ⬜ não verificado · ❌ quebrou.

---

## UC-CCRE-01 · Cadastrar com CPF/CNPJ inválido é barrado na cara
- **Persona:** Larissa — digitou o documento errado; o sistema tem que avisar no campo, não gravar lixo fiscal que quebra NFe depois.
- **Aceite:** Dado o formulário de novo cliente · Quando envio um CPF **ou** CNPJ que não passa no dígito verificador (mod 11) · Então volta **422** com erro no campo `cpf_cnpj` e o cadastro **não** é criado.
- **Teste:** `tests/Feature/Cliente/StoreContactRequestTest.php` — `rejeita CPF inválido com 422 e erro em cpf_cnpj` + `rejeita CNPJ inválido com 422 / redirect + erro em cpf_cnpj`.
- **Regressão que defende:** antes do slice, `$request->only([...])` aceitava qualquer string sem checar mod 11 → documento inválido gravado.
- **Status: 🧪** — feature test HTTP passa no CI; ✅ quando `casos:results` regravar o manifesto.

---

## UC-CCRE-02 · Campos fiscais fora do conjunto canônico são rejeitados
- **Persona:** Larissa / fiscal — indicador de IE e regime tributário só aceitam valores válidos (senão a NFe rejeita na SEFAZ).
- **Aceite:** Dado o cadastro · Quando envio `indicador_ie` fora de {1,2,9} **ou** regime fora do conjunto canônico · Então volta **422** com erro no campo correspondente.
- **Teste:** `tests/Feature/Cliente/StoreContactRequestTest.php` — `rejeita indicador_ie fora de 1/2/9` + `rejeita regime fora do conjunto canônico`.
- **Status: 🧪** — feature test HTTP passa; ✅ com o manifesto regravado.

---

## UC-CCRE-03 · Documento válido é aceito sem atrito
- **Persona:** Larissa — documento certo tem que passar limpo, sem falso-positivo de validação.
- **Aceite:** Dado um CPF **ou** CNPJ válido (mod 11 OK) · Quando cadastro · Então **não** há erro no campo `cpf_cnpj` e o cliente é criado.
- **Teste:** `tests/Feature/Cliente/StoreContactRequestTest.php` — `aceita CPF válido sem erro no campo cpf_cnpj` + `aceita CNPJ válido sem erro no campo cpf_cnpj`.
- **Status: 🧪** — feature test HTTP passa; ✅ com o manifesto regravado.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão.

- **[BACKLOG] Pre-fill via `?prefill_name=`** (vindo do autocomplete em Sells/Create) preenche o nome — exige spec e2e.
- **[BACKLOG] Lookup CNPJ (BrasilAPI) autopreenche razão/fantasia** — anchor em `BrasilApiLookupTest` num passe dedicado.
- **[BACKLOG] Segmented PF/PJ troca os campos exibidos** — render test do `_form/ClienteForm`.

## Como rodar a suíte
1. **Pest:** `docker exec oimpresso-staging php artisan test --filter=StoreContactRequestTest` no CT100 (nunca local/Hostinger).
2. **Manifesto:** `npm run casos:results` regrava `scripts/casos-test-results.json` → 🧪 vira ✅.
3. **Cadência:** rodar ao fim de toda mexida em `Create.tsx` / `_form/ClienteForm` / `StoreContactRequest`.

## Trilha do tempo
- 2026-07-08 · [CC] criado — Fase 2 (lanes Cliente). 3 UCs ancorados no `StoreContactRequestTest` (validação fiscal mod 11). Refs: [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md) G-1/G-2 · US-CRM-076.

---
titulo: Paridade de migração — /perfil (Meu perfil) Blade↔React
tipo: parity
status: active
owner: W
criado: '2026-07-02'
tela: /perfil
related:
  - ../_DesignSystem/PARITY-TEMPLATE.md
  - ../_Governanca/programa-ondas/onda-0-fundacao/0d-paridade-migracao.md
related_adrs:
  - '0320-programa-ondas-regua-correcao'
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0093-multi-tenant-isolation-tier-0'
  - '0066-format-date-shift-3h-preservado-legacy-clientes'
  - '0264-governanca-executavel-trio-dominio-e2e'
---

# `-parity.md` piloto — /perfil (Meu perfil)

> **Primeiro `-parity.md` real do projeto** (Onda 0d). Formato validado que vira o
> [`PARITY-TEMPLATE.md`](../_DesignSystem/PARITY-TEMPLATE.md). Prova campo-a-campo de que a
> migração Blade→React da tela "Meu perfil" preservou função.

## Metadados

- **Tela React:** `resources/js/Pages/User/Perfil.tsx`
- **Blade legado:** `resources/views/user/profile.blade.php` + `user/edit_profile_form_part.blade.php` + `user/form.blade.php`
- **Controller (persistência):** `App\Http\Controllers\UserController` — `perfil()` (render), `perfilUpdate()` (grava), `perfilPassword()` (senha)
- **Rotas:** `GET /perfil` · `POST /perfil/update` · `POST /perfil/password` (novas) — legado `POST user/profile` (`updateProfile`/`updatePassword`) **intacto** (sem cutover, decisão Wagner)
- **Auditado em:** 2026-07-02 · **por:** Claude Code (leitura read-only do código real)
- **Veredito:** **paridade alta** — nenhum campo persistido foi perdido; 2 perdas cosméticas (tooltips) + 4 divergências que são melhorias deliberadas.

---

## Mapa campo-a-campo

Fonte da persistência: `perfilUpdate()` faz `$request->only([...])` com o **mesmo conjunto** de
campos que o legado `updateProfile()` (`UserController.php:246` ↔ `:74`) + `dob` + `bank_details` + `profile_photo`.

### Segurança (troca de senha)

| # | Feature do Blade | Está no React? | Evidência (arquivo:linha) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 1 | `current_password` | ✅ | `Perfil.tsx:531` ↔ `profile.blade.php:35` · `UserController.php:296` | **alta** | UC-P-senha (backlog casos) |
| 2 | `new_password` | ✅ | `Perfil.tsx:540` ↔ `profile.blade.php:46` | **alta** | UC-P-senha |
| 3 | confirmação da senha | 🟡 **melhoria** | React `new_password_confirmation` + `min:8\|confirmed` server (`Perfil.tsx:549`, `UserController.php:290`); Blade `confirm_password` sem checagem server (`profile.blade.php:57`, `:129`) | baixa | (ver §divergências) |

### Conta

| # | Feature do Blade | Está no React? | Evidência (arquivo:linha) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 4 | `surname` (prefixo) | ✅ | `Perfil.tsx:340` ↔ `profile.blade.php:83` | média | UC-P03 (persistência) |
| 5 | `first_name` **(required)** | ✅ | `Perfil.tsx:342` ↔ `profile.blade.php:92` · validate `UserController.php:240` | **alta** | UC-P03 |
| 6 | `last_name` | ✅ | `Perfil.tsx:345` ↔ `profile.blade.php:101` | média | UC-P03 |
| 7 | `email` | 🟡 React exige+valida `email` (`UserController.php:241`); Blade sem required (`profile.blade.php:110`) | `Perfil.tsx:348` | **alta** | UC-P03 |
| 8 | `language` (select) | ✅ | `Perfil.tsx:354` ↔ `profile.blade.php:119` · props `UserController.php:223` | média | UC-P03 |
| 9 | `profile_photo` (upload + thumbnail) | ✅ (React add preview+remover) | `Perfil.tsx:390` ↔ `profile.blade.php:135` · `Media::uploadMedia` `UserController.php:263` | média | UC-P-foto (backlog) |

### Mais informações

| # | Feature do Blade | Está no React? | Evidência (arquivo:linha) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 10 | `dob` (nascimento) | 🟡 formato mudou: Blade `@format_date`+`uf_date` (`form.blade.php:10`, `UserController.php:81`); React `type=date` ISO, grava cru (`Perfil.tsx:403`, `UserController.php:254`) | `Perfil.tsx:403` | média | UC-P03 (ver §divergências — biz=4) |
| 11 | `gender` (select) | ✅ | `Perfil.tsx:405` ↔ `form.blade.php:14` | baixa | UC-P03 |
| 12 | `marital_status` (select) | ✅ | `Perfil.tsx:414` ↔ `form.blade.php:18` | baixa | UC-P03 |
| 13 | `blood_group` | ✅ | `Perfil.tsx:423` ↔ `form.blade.php:22` | baixa | UC-P03 |
| 14 | `guardian_name` | ✅ | `Perfil.tsx:426` ↔ `form.blade.php:72` | baixa | UC-P03 |
| 15 | `contact_number` (celular) | ✅ | `Perfil.tsx:434` ↔ `form.blade.php:27` | média | UC-P03 |
| 16 | `alt_number` | ✅ | `Perfil.tsx:437` ↔ `form.blade.php:31` | baixa | UC-P03 |
| 17 | `family_number` | ✅ | `Perfil.tsx:440` ↔ `form.blade.php:35` | baixa | UC-P03 |
| 18 | `fb_link` | ✅ | `Perfil.tsx:443` ↔ `form.blade.php:39` | baixa | UC-P03 |
| 19 | `twitter_link` | ✅ | `Perfil.tsx:446` ↔ `form.blade.php:43` | baixa | UC-P03 |
| 20 | `social_media_1` | ✅ | `Perfil.tsx:449` ↔ `form.blade.php:47` | baixa | UC-P03 |
| 21 | `social_media_2` | ✅ | `Perfil.tsx:452` ↔ `form.blade.php:52` | baixa | UC-P03 |
| 22 | `id_proof_name` | ✅ | `Perfil.tsx:460` ↔ `form.blade.php:76` | baixa | UC-P03 |
| 23 | `id_proof_number` | ✅ | `Perfil.tsx:463` ↔ `form.blade.php:80` | baixa | UC-P03 |
| 24 | `permanent_address` (textarea) | ✅ | `Perfil.tsx:466` ↔ `form.blade.php:85` | média | UC-P03 |
| 25 | `current_address` (textarea) | ✅ | `Perfil.tsx:469` ↔ `form.blade.php:89` | média | UC-P03 |
| 26 | **`custom_field_1..4` + labels da empresa** | ✅ | `Perfil.tsx:477-488` ↔ `form.blade.php:55-68` · labels `UserController.php:179-184` | **alta** | UC-P03 (item-farol da paridade) |

### Dados bancários (`bank_details.*`, gravado como JSON)

| # | Feature do Blade | Está no React? | Evidência (arquivo:linha) | Severidade | Defendido por |
|---|---|---|---|---|---|
| 27 | `account_holder_name` | ✅ | `Perfil.tsx:500` ↔ `form.blade.php:97` · JSON `UserController.php:257` | **alta** | UC-P03 |
| 28 | `account_number` | ✅ | `Perfil.tsx:502` ↔ `form.blade.php:101` | **alta** | UC-P03 |
| 29 | `bank_name` | ✅ | `Perfil.tsx:505` ↔ `form.blade.php:105` | média | UC-P03 |
| 30 | `bank_code` + **tooltip `bank_code_help`** | 🟡 campo ✅, **tooltip ❌** | campo `Perfil.tsx:508`; tooltip perdido — Blade `@show_tooltip` `form.blade.php:108` | baixa | — |
| 31 | `branch` (agência) | ✅ | `Perfil.tsx:511` ↔ `form.blade.php:113` | baixa | UC-P03 |
| 32 | `tax_payer_id` + **tooltip `tax_payer_id_help`** | 🟡 campo ✅, **tooltip ❌** (só nota LGPD genérica `Perfil.tsx:518`) | campo `Perfil.tsx:514`; tooltip perdido — `form.blade.php:117` | baixa | — |

---

## Divergências deliberadas (React ≠ Blade de propósito — NÃO re-regredir)

1. **Senha mais forte** — React valida `min:8` + `confirmed` no server (`UserController.php:290`); o
   legado só tinha `required` no HTML e **não** conferia confirmação nem tamanho no server
   (`updatePassword`, `:129`). Melhoria consciente (charter §Segurança). _Não reverter pra `confirm_password` sem checagem._
2. **Sessão sem clobber** — React atualiza só `surname/first_name/last_name` na sessão
   (`UserController.php:266-268`); o legado fazia `session()->put('user', $input)` sobrescrevendo o
   blob inteiro (`:96`). Correção de bug latente. _Não voltar ao put do blob._
3. **Validação de identidade** — React exige+valida `first_name`+`email` no server
   (`UserController.php:239-242`); o legado `updateProfile` não tinha validação nenhuma. Melhoria.
4. **`dob` em ISO** — React usa `type=date` (Y-m-d) e grava cru; o legado passava por `uf_date`
   (formato do negócio). Simplificação. ⚠️ **Atenção biz=4/legacy:** clientes com `format_date`
   shift +3h ([ADR 0066](../../decisions/0066-format-date-shift-3h-preservado-legacy-clientes.md))
   exibem/gravam data no formato do negócio no legado — validar no cutover se algum usuário de
   `business_id` com shift usa `dob` (hoje: campo de RH, uso raro). Registrado como média, não alta.

---

## Itens de severidade alta → teste de comportamento (F4)

> **Enforcement por comportamento, não presença.** Estas asserções são o que fecha F4 —
> derivadas do contrato (persistência dos campos), não do que o código faz hoje.
> Espelham o backlog de [`Perfil.casos.md`](../../../resources/js/Pages/User/Perfil.casos.md)
> ("Paridade de campos com o legado, incl. `custom_field_1..4`") — que vira **UC-P03** quando o
> teste abaixo existir e citar o id (regra G-2, [ADR 0264](../../decisions/0264-governanca-executavel-trio-dominio-e2e.md)).

| Item alta | Asserção (Dado/Quando/Então) | Teste (arquivo::caso) | Status |
|---|---|---|---|
| Persistência dos campos-chave | Dado usuário logado · Quando `POST /perfil/update` com todos os campos · Então a linha `users` tem cada um, incl. `custom_field_1..4` e `bank_details` (JSON com as 6 chaves) | `tests/Feature/Perfil/PerfilParityTest.php::"UC-P03 · persiste campos do legado"` | 🧪 a criar |
| `first_name`/`email` obrigatórios | Dado `POST /perfil/update` sem `first_name` (ou `email` inválido) · Então 422 e nada grava | mesmo arquivo::"UC-P03 · valida obrigatórios" | 🧪 a criar |
| Tier 0 — só o próprio usuário | Dado usuário A logado · Quando `POST /perfil/update` · Então grava só `session('user.id')`, nunca outro `user_id`/`business_id` | mesmo arquivo::"UC-P03 · escopo Tier 0" | 🧪 a criar |
| Troca de senha confere a atual | Dado senha atual errada · Quando `POST /perfil/password` · Então `ValidationException` em `current_password` e a senha **não** muda | mesmo arquivo::"UC-P-senha · Hash::check" | 🧪 a criar |

> **Débito honesto:** hoje o `PerfilSmokeTest` cobre só UC-P01 (contrato render) e UC-P02 (legado
> vivo). Os 4 casos acima são a **dívida de paridade** desta tela — landam num PR de teste que roda
> no **CT100/CI** (não local, [proibicoes](../../proibicoes.md)). Enquanto 🧪, a paridade dos
> campos está **provada por leitura** (esta tabela), **não por máquina**. É o que a Onda 0d fecha
> quando a tela entrar numa onda de módulo (backfill módulo-a-módulo, não big-bang).

## Refs

- Template: [`PARITY-TEMPLATE.md`](../_DesignSystem/PARITY-TEMPLATE.md)
- Onda mãe: [0d-paridade-migracao.md](../_Governanca/programa-ondas/onda-0-fundacao/0d-paridade-migracao.md)
- Charter + casos: [`Perfil.charter.md`](../../../resources/js/Pages/User/Perfil.charter.md) · [`Perfil.casos.md`](../../../resources/js/Pages/User/Perfil.casos.md)
- Smoke atual: `tests/Feature/Perfil/PerfilSmokeTest.php`
</content>

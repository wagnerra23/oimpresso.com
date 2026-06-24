---
casos: User Perfil · /perfil
irmaos: Perfil.charter.md (lei)
tecnica: Caso de uso = narrativa do usuário + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-06-24"
---

# Casos de Uso & Aceite — Meu perfil (/perfil)

> Tela "Meu perfil" (conta do usuário logado) — redesign ComVis do legado `/user/profile`.
> Tier 0: só edita o próprio usuário (controller escopa por `session('user.id')`). Smoke +
> contrato Inertia por `tests/Feature/Perfil/PerfilSmokeTest.php` (skip sem `DEV_LOGIN_*` —
> roda em CT100/CI). Verificado manualmente no staging 2026-06-24 (4 abas + save persistem).
>
> **Status:** ✅ passa (prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## UC-P01 · /perfil renderiza pro usuário logado
- **Persona:** qualquer usuário autenticado — ver/editar a própria conta.
- **Aceite:** Dado usuário logado · Quando abre `/perfil` · Então renderiza o componente Inertia `User/Perfil` com as props `usuario` (email do logado) + `languages` + `custom_field_labels`.
- **Teste:** `PerfilSmokeTest` ("UC-P01 · renderiza /perfil") — contrato Inertia (component + props).
- **Status: 🧪**

## UC-P02 · Legado /user/profile permanece intacto
- **Persona:** usuário/admin — a rota Blade antiga não pode quebrar com a nova (sem cutover).
- **Aceite:** Dado a rota legada `/user/profile` · Então responde 200 (não 500).
- **Teste:** `PerfilSmokeTest` ("UC-P02 · legado /user/profile intacto").
- **Status: 🧪**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- Salvar dados (Conta/Mais info/Banco) persiste via `POST /perfil/update`, escopado ao usuário logado (Tier 0). _Provado manualmente no staging 2026-06-24; falta teste automatizado._
- Trocar senha valida senha atual (`Hash::check`) + confirmação (`confirmed`) via `POST /perfil/password`.
- Upload de foto de perfil (`profile_photo` → `Media::uploadMedia`).
- Paridade de campos com o legado, incl. `custom_field_1..4` com labels da empresa.

---
id: resources-js-pages-user-perfil-casos
casos: User Perfil · /perfil
irmaos: Perfil.charter.md (lei)
tecnica: Caso de uso = narrativa do usuário + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-10-13"
last_run_ci: "2026-10-13 (PR contratos de tela, telas-soltas thread 01) — REVALIDACAO ANALITICA, suite NAO executada. A mudanca na tela sao so atributos data-contract (e um div sem classe em volta do PageHeader); nenhum UC afirma ancora. ---- REGISTRO ANTERIOR: last_run 2026-06-24."
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

## UC-P02 · O endereço antigo /user/profile leva para /perfil
- **Persona:** usuário/admin — favorito ou link velho para `/user/profile` continua funcionando, e cai na tela React.
- **Aceite:** Dado a rota antiga `/user/profile` · Então redireciona para `/perfil`. Até o cutover F5 de 2026-10-27 o aceite era o oposto (Blade intacto, 200), porque as duas telas conviviam.
- **Teste:** `tests/Feature/Users/PerfilEnderecoAntigoTest.php` (lane Acessos · MySQL, tenant 98): "UC-P02 · o endereco antigo /user/profile leva para /perfil" + o destino abre `User/Perfil`. O `PerfilSmokeTest` também cita o UC, mas depende de `DEV_LOGIN_*` e pula no CI.
- **Regressão que defende:** a volta do Blade `user/profile` (apagado no cutover F5 de 2026-10-27) ou um redirect para tela quebrada.
- **Status: ⬜** — teste novo; vira 🧪 com o run verde da lane Acessos.

## UC-P03 · Salvar o perfil grava só no usuário logado
- **Persona:** usuário autenticado — ajusta nome e dados bancários.
- **Aceite:** Dado usuário logado · Quando salva em `POST /perfil/update` · Então os campos ficam gravados no próprio usuário (`bank_details` como JSON) · E outro usuário da mesma empresa não muda (Tier 0).
- **Teste:** `tests/Feature/Users/PerfilContratoTest.php` ("UC-P03 · salvar o perfil grava no proprio usuario…").
- **Status: ⬜** — teste novo; vira 🧪 com o run verde da lane Acessos.

## UC-P04 · Trocar senha exige a senha atual
- **Persona:** usuário autenticado — troca a própria senha.
- **Aceite:** Dado senha atual errada · Quando envia `POST /perfil/password` · Então erro no campo `current_password` e a senha não muda · E com a senha certa + confirmação, a nova senha vale.
- **Teste:** `PerfilContratoTest` ("UC-P04 · trocar senha exige a senha atual correta").
- **Status: ⬜**

## UC-P05 · Remover a foto apaga a foto do usuário
- **Persona:** usuário autenticado — tira a foto de perfil.
- **Aceite:** Dado usuário com foto · Quando clica "Remover" e salva (`remove_photo=1`, sem arquivo novo) · Então a mídia do usuário é apagada e `/perfil` volta a mostrar as iniciais. Antes de 2026-11-19 o botão só limpava a prévia; a foto continuava no servidor.
- **Teste:** `PerfilContratoTest` ("UC-P05 · remover a foto apaga a midia do proprio usuario").
- **Status: ⬜**

## UC-P06 · Idiomas aparecem em português
- **Persona:** usuário autenticado — escolhe o idioma.
- **Aceite:** Quando abre `/perfil` · Então a lista de idiomas mostra "Português", "Inglês"… (o config traz o nome em inglês).
- **Teste:** `PerfilContratoTest` ("UC-P06 · a lista de idiomas mostra o nome em portugues").
- **Status: ⬜**

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- Upload de foto de perfil (`profile_photo` → `Media::uploadMedia`).
- Paridade de campos com o legado, incl. `custom_field_1..4` com labels da empresa.

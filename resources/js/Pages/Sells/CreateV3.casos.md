---
id: resources-js-pages-sells-createv3-casos
casos: Venda V3 (preview de design) · /sells/create-v3
irmaos: CreateV3.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: luiz
last_run: "2026-08-07"
---

# Casos de Uso & Aceite — Venda V3 (preview)

> **Tela de PREVIEW, não de produção.** Dono: **[L] Luiz**. A venda real continua em `/pos/create`
> (`Sells/Create.tsx`), operada pela ROTA LIVRE — e essa tela **não pode ser alterada**
> (restrição de negócio [L] 2026-08-06). Lei da tela: [`CreateV3.charter.md`](CreateV3.charter.md).
>
> **Status:** ✅ passa (com prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## Como esta lista cresce

Um `UC-*` só existe honestamente quando **≥1 teste o cita** — é o G-2 do `casos-gate`, que é **required**.
Criar UC sem teste faria o contrato nascer órfão e **bloquearia o merge de quem viesse atendê-lo**.

Então o que ainda não tem teste fica como **prosa declarada** no formato `[BACKLOG]` (sem id):
visível pra quem for escrever o teste, sem gate que ela não possa cumprir. Cada item vira
`UC-V3xx` **no PR que trouxer o teste que o cita** — não antes.

**2026-08-07 (US-SELL-058):** os três primeiros foram promovidos junto com
[`SellsCreateV3ContratoTest.php`](../../../../tests/Feature/Sells/SellsCreateV3ContratoTest.php).
O resto segue backlog — de propósito: eles exigem sessão autenticada, permissão semeada ou
render, e nada disso foi escrito ainda.

---

## UC-V301 · A rota de leitura existe e é servida pelo controller do preview

**Status:** 🧪 — teste escrito, **nunca executado** (a lane Pest roda no CI/CT 100, nunca local · [ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)). Vira ✅ com run verde citado.

- **Dado** o roteador da aplicação carregado,
- **Quando** se procura a rota nomeada `sells.create-v3`,
- **Então** ela existe, responde em `sells/create-v3`, aceita `GET` e é servida por `SellsV3Controller@create`.

Prova: `tests/Feature/Sells/SellsCreateV3ContratoTest.php`. Oráculo é o **roteador em runtime**
(`Route::getRoutes()`), não `grep` no `routes/web.php` — ler o arquivo responde "o texto está lá",
não "a rota está registrada" ([§5](../../../../memory/proibicoes.md), 2026-07-17).

---

## UC-V302 · A tela não grava — nenhuma rota de escrita aponta pro controller do preview

**Status:** 🧪 — teste escrito, **nunca executado**. Vira ✅ com run verde citado.

- **Dado** que o preview existe para ensaiar desenho, não para vender,
- **Quando** se enumeram todas as rotas cujo controller é `SellsV3Controller`,
- **Então** nenhuma delas aceita `POST`, `PUT`, `PATCH` ou `DELETE`, e o controller não declara `store()`, `update()` nem `destroy()`.

Anti-vácuo: o teste primeiro prova que o controller **está roteado** — sem isso, "zero rotas de
escrita" também seria verdade num mundo onde o controller não existe.

---

## UC-V303 · Fronteira: o preview não encosta nos artefatos da tela viva

**Status:** 🧪 — teste escrito, **nunca executado**. Vira ✅ com run verde citado.

- **Dado** que a razão de a tela existir é não tocar em `Sells/Create.tsx` (ROTA LIVRE, 99% do volume),
- **Quando** se inspecionam o controller e a Page do preview,
- **Então** o controller não usa/estende/instancia `SellPosController`, e nenhum `import` da Page vem de `Sells/Create` nem de `Sells/_components`.

O acoplamento é medido no que o **parser** vê (tokens PHP sem comentário; especificador de
`import`), não no texto cru — o docblock do V3 **cita** `SellPosController@create` para explicar
por que a tela existe, e um `toContain` no arquivo inteiro reprovaria a própria documentação.

---

## Backlog de contrato

- **[BACKLOG]** A rota `/sells/create-v3` responde **403** a usuário autenticado sem `sell.create` e sem `superadmin` — mesma alçada da tela de venda real, o preview não afrouxa permissão.
- **[BACKLOG]** A rota responde **302** (login) a usuário não autenticado.
- **[BACKLOG]** A resposta Inertia renderiza o componente `Sells/CreateV3` e traz a prop `cena` com as três chaves `cliente`, `itens`, `fechamento`.
- **[BACKLOG]** A tela renderiza a **faixa de preview** — quem abre por engano precisa saber em 1 segundo que não é produção.
- **[BACKLOG]** O botão "Finalizar venda" renderiza **`disabled`**. _(A metade "não existe rota de escrita" deste item virou [UC-V302](#uc-v302--a-tela-não-grava--nenhuma-rota-de-escrita-aponta-pro-controller-do-preview); o `disabled` do botão continua sem teste.)_
- **[BACKLOG]** Nenhum número exibido é calculado no front nem no controller: os valores de `fechamento` são strings já formatadas em pt-BR, vindas de `SellsV3Controller::dadosDeCena()`.

---

## Fronteira que os testes desta tela devem preservar

Não é caso de uso desta tela, mas é o contrato que a existência dela serve — e o teste que o provar
pertence a `Sells/Create`, não aqui:

- `/pos/create` continua servido por `SellPosController@create` renderizando `Sells/Create`, **sem alteração de comportamento**, com esta tela existindo ao lado.

---

## Pendências declaradas

- Testes: **1 arquivo** — `tests/Feature/Sells/SellsCreateV3ContratoTest.php` (UC-V301/302/303), na allowlist da lane `Pest (Sells · MySQL)`. **Escrito, ainda não executado**: a lane roda no CI/CT 100, nunca local ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)) — o primeiro run verde é que troca os 🧪 por ✅.
- Smoke real de tela: pendente. O RUNBOOK ([`RUNBOOK-create-v3.md`](../../../../memory/requisitos/Sells/RUNBOOK-create-v3.md) §F4) prevê smoke em staging; nada disso rodou.
- Tenant de teste é o fictício **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — `biz=4` é proibido em teste, fixture ou smoke.

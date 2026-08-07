---
title: "Jana — fusão das 4 telas numa só (abas de área) · PR-0 inventário"
status: proposta
date: "2026-08-07"
owners: [W]
parent_module: Jana
related_adrs: [180, 182, 104, 114, 282, 286, 366]
related_charters:
  - resources/js/Pages/Jana/Chat.charter.md
  - resources/js/Pages/Jana/Dashboard.charter.md
  - resources/js/Pages/Jana/Cockpit.charter.md
  - resources/js/Pages/Jana/Memoria.charter.md
related_prototype: jana-merge.jsx (DesignSync projeto 019dcfd3-6ef2-7ee6-8512-b1b0e5544e58)
---

# PR-0 — inventário da fusão das telas da Jana

**Este PR não tem UI.** É a evidência que as ondas 1-7 vão consumir. As decisões de
produto estão tomadas ([W], pacote `JANA-FUSAO-2026-08-06`); o que este documento faz é
**medir o `main`** e registrar onde a medição **contradiz** o pacote — porque construir
sobre premissa errada custa mais caro que corrigi-la aqui.

Método: tudo abaixo é `rg --hidden -g '!.git/**'` sobre o repo inteiro + leitura do
`Modules/Jana/Http/routes.php`. Clone completo (`--is-shallow-repository=false`,
`origin/main...HEAD = 0/0`), então as contagens e as datas sustentam conclusão.

---

## 1. Rotas Jana vivas hoje

Fonte: [`Modules/Jana/Http/routes.php`](../../../Modules/Jana/Http/routes.php).

O grupo tem **`'prefix' => 'ia'`** (linha 51), middleware
`['web','SetSessionData','auth','language','timezone','AdminSidebarMenu','CheckUserLogin','throttle:120,1','can:jana.access']`.

| URL viva | Rota (name) | Controller | Renderiza |
|---|---|---|---|
| `GET /ia` | `jana.chat.index` | `ChatController@index` | `Jana/Chat` |
| `GET /ia/cockpit` | `jana.cockpit` | `ChatController@cockpit` | `Jana/Cockpit` |
| `GET /ia/dashboard` | `jana.dashboard.index` | `DashboardController@index` | `Jana/Dashboard` |
| `GET /ia/memoria` | `jana.memoria.index` | `KB\MemoriaController@index` | `Jana/Memoria` |
| `GET /ia/pro` | `jana.pro.index` | `ProController@index` | `Jana/Pro` |
| `GET /ia/metas…` | `jana.metas.*` | `MetasController` | Blade (`copiloto::metas.index`) — **não é Inertia** |

Redirects já existentes que importam pra fusão:

- `/ia/painel` → `/ia/dashboard` **301** (linha 249)
- `/copiloto/{any}` → `/jana/{any}` **301** → `/ia/{any}` **301** (linhas 364-368, catch-all no fim do arquivo)

---

## 2. Quem aponta pra `Cockpit.tsx`

Três apontadores, todos localizados:

1. **Rota** `GET /ia/cockpit` → `ChatController@cockpit` → `Inertia::render('Jana/Cockpit')`
   (`ChatController.php:636` e `:666`).
2. **Ghost do menu** `['key' => 'cockpit', 'label' => 'Cockpit', 'href' => '/ia/cockpit']`
   em [`DataController.php:317`](../../../Modules/Jana/Http/Controllers/DataController.php) — é o
   que põe a aba "Cockpit" na faixa do hub IA.
3. **Baselines de lint** — `config/eslint-baseline.json:235` e
   `config/ui-lint-baseline.json:509` têm entrada pro arquivo. Ao remover o `.tsx` no PR-7,
   as duas entradas saem no **mesmo PR** (chave órfã em baseline é dívida que o gate não vê).

---

## 3. `Painel.tsx` — fantasma confirmado

**Zero referências vivas.** A remoção já aconteceu em **2026-08-06 [W]** (onda 1 desta
mesma fusão): rota apagada com o motivo registrado em `routes.php:62-69`, redirect 301
`/ia/painel` → `/ia/dashboard` na linha 249. O `.tsx` e o charter não existem no `main`.

Resíduo: **2 comentários stale** que ainda falam do arquivo como se existisse —
`components/JanaAreaHeader.tsx:57` e `DataController.php:317`. São prosa, não código;
saem por higiene junto do PR-7.

> Recibo: `rg --hidden -g '!.git/**' -n "Jana/Painel|Painel\.tsx|Painel\.charter" resources/ Modules/ config/ routes/ tests/` → 2 hits, ambos comentário.

---

## 4. Os dois cockpits — **não é 1-morre-1-fica**

Esta é a correção mais cara do inventário, então vai com a medição inteira.

| Arquivo | Quem consome | O que é |
|---|---|---|
| `_components/JanaCockpit.tsx` (25.8k) | **`Jana/Dashboard.tsx:19`** (import direto) | Bifurcação PT-04 (US-COPI-146) feita **para matar** o bundle CSS paralelo |
| `components/JanaCockpitV2.tsx` (24.6k) | **`Sells/Index.tsx`** (tab Insights) | Tela-dona legítima do bundle `.sells-cowork .vd-insights-*` |

O cabeçalho do próprio `JanaCockpit.tsx` declara a relação (linhas 1-9): *"Substitui o
bundle CSS paralelo `.sells-cowork .vd-insights-*` (JanaCockpitV2) pelo [padrão PT-04].
A LÓGICA é idêntica ao JanaCockpitV2 — só o render mudou. O JanaCockpitV2 continua
servindo a tab Insights de /sells."*

**Consequências:**

- `JanaCockpitV2` **não pode morrer** — `Sells/Index.tsx` depende dele. Apagá-lo quebra
  a aba Insights de vendas, que não é escopo desta fusão.
- Usar `JanaCockpitV2` como base do Painel **reintroduz o bundle CSS paralelo** que a
  regra **R7** do `ui:lint` pega, e que tem teste de arquitetura dedicado:
  [`tests/Feature/Architecture/UiLintR7BundleParaleloTest.php`](../../../tests/Feature/Architecture/UiLintR7BundleParaleloTest.php)
  (o arquivo cita `Jana/Dashboard.tsx` nominalmente, linhas 35/45/64).

**Portanto:** a base do Painel é **`_components/JanaCockpit.tsx`**. O que o PR-7 remove
não é "um dos dois arquivos" — é a **duplicação de lógica** entre eles (o `JanaCockpit`
existe porque a lógica foi copiada; consolidar o núcleo compartilhado e deixar cada um
com seu render é o trabalho, e ele é **maior que um delete**).

---

## 5. Permissões — são `jana.*`, não `copiloto.*`

[`Modules/Jana/Resources/permissions.php`](../../../Modules/Jana/Resources/permissions.php)
declara `jana.access`, `jana.chat`, `jana.metas.manage`, `jana.mcp.tasks.read`,
`jana.superadmin` — e é isso que as rotas aplicam (`can:jana.access` na linha 50).

`copiloto` aparece no repo em **dois lugares que não são permissão**: o `'group' => 'Copiloto'`
(rótulo humano do registry) e `config('copiloto.*')` (chaves de configuração —
`copiloto.ui_judge`, `copiloto.dry_run`, `copiloto.memoria.*`). Nenhuma é ability Spatie.

> Ou seja: a instrução *"não renomear permissões `copiloto.*`"* protege um conjunto vazio.
> A instrução operacional equivalente é **não renomear `jana.*`** — e essa vale, pelo
> motivo já catalogado no projeto: permission Spatie vive por **id de linha**, então
> renomear revoga acesso em silêncio, sem erro e sem log.

---

## 6. Charters — os 4 `page:` estão stale

| Charter | `page:` declarado | URL viva | `status:` |
|---|---|---|---|
| `Chat.charter.md` | `/jana/chat` | `/ia` | `live` |
| `Dashboard.charter.md` | `/copiloto/dashboard` | `/ia/dashboard` | `live` |
| `Cockpit.charter.md` | `/jana/cockpit` | `/ia/cockpit` | `draft` (`spec-ahead-of-impl`) |
| `Memoria.charter.md` | `/copiloto/memoria` | `/ia/memoria` | `draft` (corpo diz live desde 2026-04) |

Os 4 apontam pra prefixos que hoje só existem como **301**. Isso não é "divergência a
arbitrar" — é dívida de rename: a ADR 0180 renomeou `/jana` → `/ia` em 2026-05-22 e os
charters não acompanharam. Cada charter tocado nas ondas 1-7 corrige o próprio `page:`
no mesmo PR (forward-only — **não** é backfill em massa dos 4 de uma vez).

A incoerência do `Memoria.charter.md` (`draft` no frontmatter × "live desde 2026-04" no
corpo) resolve no PR-5, que é quem toca a tela: o vencedor é o comportamento vivo → `live`.

---

## 7. Design — âncora localizada

Projeto DesignSync `019dcfd3-6ef2-7ee6-8512-b1b0e5544e58` (leitura livre, ADR 0315):

- `jana-merge.jsx` + `jana-merge.css` — a tela fundida
- `chat-jana.jsx` + `chat-jana.css` — a aba Conversa
- `cowork-inbox/JANA-FUSAO-2026-08-06.md` — o handoff [CC]
- espelho em `prototipo-ui/cowork/` dentro do mesmo projeto

**Duas leituras do protótipo que o pacote precisa saber:**

1. O protótipo tem um **switch `metasMode`** com dois valores — `"aba"` (Metas vira 4ª aba)
   e `"secao"` (Metas vira seção do Painel). O pacote escolheu **`"secao"`**; o protótipo
   suporta as duas, então **não há divergência** — só a escolha registrada aqui pra não
   ser redecidida na onda 2.
2. O protótipo desenha **6 KPI cards**; o pacote §2 PR-3 diz *"4 KPIs"*. Não resolvi por
   leitura — o `--check` do contrato (§8) é quem arbitra, e ele lê o `.jsx`.

---

## 8. O que as ondas herdam deste PR

- Prefixo canônico das rotas novas: **`/ia`** (ver §9).
- Base do Painel: **`_components/JanaCockpit.tsx`** (§4).
- `JanaCockpitV2` fica de pé, com dono declarado (`Sells/Index.tsx`).
- Baselines de lint saem no mesmo PR do delete (§2).
- Cada charter tocado corrige o próprio `page:` (§6).
- O contrato `prototipo-ui/contrato/jana-index.contract.json` (§1.5 do pacote, ADR 0286)
  extrai copy **do `jana-merge.jsx`**, não desta prosa — número e string vivem no
  protótipo, este doc só aponta.

---

## 9. ⚠️ Decisão pendente [W] — o prefixo das rotas novas

O pacote §1 fixa as rotas canônicas em **`/jana`**, `/jana/conversa`, `/jana/memoria`.
O `main` diz que **`/jana` é ele próprio um 301 pra `/ia`** desde 2026-05-22 (ADR 0180,
rename motivado por casar a URL com o label "IA" do sidebar v3).

Registrar rotas reais sob `/jana` significaria **reverter a ADR 0180** — decisão de
arquitetura, append-only, não vem de carona numa fusão de telas.

**Recomendo `/ia`**, que preserva a 0180 e mantém `/jana/*` e `/copiloto/*` funcionando
como já funcionam (o catch-all existente cobre bookmark antigo sem uma linha nova):

| | pacote | recomendado |
|---|---|---|
| Painel (default) | `/jana` | **`/ia`** |
| Conversa | `/jana/conversa` | **`/ia/conversa`** |
| Memória | `/jana/memoria` | **`/ia/memoria`** (já existe) |
| legados → 301 | `/jana/dashboard`, `/jana/cockpit` | `/ia/dashboard`, `/ia/cockpit` |

O resto do pacote não muda com essa escolha — só os literais de rota e o
`localStorage` (que segue `oimpresso.jana.*`, prefixo de storage, não URL).

**Enquanto [W] não decidir, o PR-1 não registra rota nova.** O shell da onda 1 é
montado nas rotas que já existem, e a troca de prefixo (se houver) é um diff de literais.

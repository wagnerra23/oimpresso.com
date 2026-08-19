---
id: resources-js-pages-modules-index-casos
casos: Gerenciador de Módulos · /modulos
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — quem-pode, o que a linha afirma e o que a ação preserva não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "2026-08-19"
---

# Casos de Uso & Aceite — Gerenciador de Módulos (`/modulos`)

> Contrato escrito por **[CC] 2026-08-19** a partir do `Index.charter.md`, do `ModuleManagementController`,
> do `ModuleManagerService` e das rotas `web.php:911-929` — **não do `Index.tsx`** (caso derivado do
> código é tautológico). Ancorado em **US-SUPER-006** ([SPEC Superadmin](../../../../memory/requisitos/Superadmin/SPEC.md)),
> hoje `Implementado em: _parcial_`.
>
> **[CL] 2026-08-19** ajustou para o gate: `last_run` com data real, `Status:` por UC (G-5), e os quatro
> casos que só o contrato de tela cobriria (filtros, busca, vazio, drawer) desceram para `[BACKLOG]`
> — sem id, porque UC sem teste que o cite é órfão e reprova o G-2. Eles voltam a ser UC quando a
> MOD-O5 landar `prototipo-ui/contrato/modulos.contract.json` com check no CI.
>
> ⚠️ Rota **cross-tenant intencional** (ADR 0093 §exceções superadmin): `/modulos` gerencia estado
> app-wide. UC-MOD-15 existe justamente para travar essa exceção contra "consertos" bem-intencionados.
>
> ⚖️ **Onde rodam:** lane Pest MySQL (CI + CT 100). Os testes de serviço rodam em **sandbox**
> (diretório temporário próprio) e nunca tocam o `Modules/` nem o `modules_statuses.json` reais —
> diferença deliberada em relação ao rascunho [CC], que fazia backup/restore do arquivo de verdade.
> Tenant: biz=1 — **nunca biz=4** (ADR 0358).
>
> **Run real (recibo):** CT 100, container `oimpresso-staging`, 2026-08-19.
> Rodada 1 (manhã, MySQL com schema completo): `ModuleManagerServiceTest` **10 passed (22 assertions)**
> · `ModuleManagementTest` **8 passed (36 assertions)**, zero skip.
> Rodada 2 (tarde, após P1/P2): `ModuleManagerServiceTest` **14 passed (28 assertions)**.
> ⚠️ Na rodada 2 as 7 suítes HTTP **não puderam rodar**: o banco de staging foi zerado por outra
> sessão entre as duas rodadas (377 → 13 tabelas, `users` inexistente). É ambiente, não código — e
> o veredito HTTP fica com a lane de CI, que sobe banco limpo. Ler *assertions*, não "0 failed":
> skip também sai exit 0 (LC-13). O `modules_statuses.json` do container ficou com o **mesmo md5**
> antes e depois nas duas rodadas — os testes de mutação rodam no sandbox, não no arquivo real.
>
> **Status:** ✅ passa **e** com prova no manifesto G-7 · 🧪 passa no CT 100, ainda **sem** manifesto
> (o ✅ só vem quando a lane de CI rodar + `npm run casos:results` regravar) · ⬜ não verificado ·
> ❌ o teste prova um comportamento indesejado (achado, não conserto silencioso).

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-MOD-01 | Superadmin abre a tela e vê o inventário com o contrato de 11 chaves | must | `ModuleManagementTest` | 🧪 |
| UC-MOD-02 | Usuário comum do negócio é barrado | must `[sec]` | `ModuleManagementTest` | 🧪 |
| UC-MOD-03 | Visitante sem sessão é barrado nas quatro rotas | must `[sec]` | `ModuleManagementTest` | 🧪 |
| UC-MOD-04 | Só quem tem `manage_modules` entra — papel de negócio não basta | must `[sec]` | `ModuleManagementTest` | 🧪 |
| UC-MOD-05 | Ordem das linhas: ativos → área → nome | should | `ModuleManagerServiceTest` | 🧪 |
| UC-MOD-06 | Chave no JSON sem pasta não vira linha | must | `ModuleManagementTest` | ❌ |
| UC-MOD-07 | Pasta sem chave no JSON aparece como "Não registrado" | must | `ModuleManagerServiceTest` | 🧪 |
| UC-MOD-11 | Toggle grava o JSON sem corromper as outras chaves | must `[T0]` | `ModuleManagerServiceTest` | 🧪 |
| UC-MOD-12 | Instalar exige módulo existente e não altera o JSON quando recusa | must | `ModuleManagerServiceTest` | 🧪 |
| UC-MOD-13 | Instalar que falha não deixa a linha mentindo "Ativo" | must `[bug]` | `ModuleManagerServiceTest` | 🧪 |
| UC-MOD-14 | Desativar preserva as tabelas do banco | must `[T0]` | `ModuleManagerServiceTest` | 🧪 |
| UC-MOD-15 | Trocar de negócio não muda o que a tela mostra | must `[T0]` | `ModuleManagementTest` | 🧪 |
| UC-MOD-17 | A versão exibida é a declarada, não um default | should | `ModuleManagerServiceTest` | 🧪 |
| UC-MOD-18 | Módulo sem DataController é identificável (não monta menu) | should | `ModuleManagerServiceTest` | 🧪 |
| UC-MOD-19 | A contagem de migrations da linha é real | should | `ModuleManagerServiceTest` | 🧪 |
| UC-MOD-20 | Módulo quebrado aparece "Com erro" em vez de silenciosamente OK | should | `ModuleManagerServiceTest` | 🧪 |

> Os ids 10 e 16 seguem **reservados** para os `[BACKLOG]` do fim deste arquivo — não reutilizar.
> (08 e 09 saíram do backlog em 2026-08-19, com a MOD-O5.)

---

## UC-MOD-01 · Superadmin vê o inventário · `must`

- **Persona:** Wagner (superadmin, 1440px).
- **Aceite:** Dado sessão com `is_admin` · Quando pede `GET /modulos` · Então 200 renderizando
  `Modules/Index` com `modules[]`, cada item com as 11 chaves do contrato do Service
  (`name·alias·version·description·area·active·registered·has_migrations·migration_count·has_datacontroller·error`).
- **Regressão que defende:** a prop é montada por varredura de filesystem; campo que some do contrato
  vira célula vazia sem ninguém perceber.
- **Teste:** `tests/Feature/Modules/ModuleManagementTest.php`
- **Status: 🧪**

---

## UC-MOD-02 · Usuário comum é barrado · `must` `[sec]`

- **Aceite:** Dado usuário autenticado sem `is_admin` na sessão e sem papel `Admin#<biz>` · Quando pede
  `GET /modulos` · Então **403**, e o corpo não traz prop de módulo.
- **Teste:** `tests/Feature/Modules/ModuleManagementTest.php`
- **Status: 🧪**

---

## UC-MOD-03 · Sem sessão, e as quatro rotas existem · `must` `[sec]`

- **Aceite:** Dado requisição sem usuário · Quando pede qualquer rota `/modulos*` · Então é barrado
  (401 ou redirect de auth) · E as quatro rotas do gerenciador existem no roteador
  (`modulos`, `modulos/{name}/toggle`, `modulos/{name}/install`, `modulos/{name}/uninstall`).
- **Regressão que defende:** a tela substitui `/manage-modules`; rota que some num refactor vira botão `#`.
- **Teste:** `tests/Feature/Modules/ModuleManagementTest.php`
- **Status: 🧪**

---

## UC-MOD-04 · Só quem tem `manage_modules` entra · `must` `[sec]`

- **Aceite:** Dado um usuário com o papel `Admin#<biz>` e **sem** `manage_modules` · Quando pede
  `GET /modulos` · Então **403** — papel é admin **de um negócio**, e esta tela desliga módulo do app
  inteiro · E dado um usuário **com** `manage_modules` · Então 200.
- **Era ACHADO até 2026-08-19.** O construtor aceitava `session('is_admin')` **OU** `Admin#<biz>`,
  enquanto o item de menu ([AdminSidebarMenu:809](../../../../app/Http/Middleware/AdminSidebarMenu.php))
  e o legado ([Install/ModulesController](../../../../app/Http/Controllers/Install/ModulesController.php),
  4 usos) autorizavam por `manage_modules`. Duas leis para a mesma capacidade: dava para ver o item no
  menu e tomar 403 na tela. O furo do papel foi **medido** (um `200` observado), não suposto.
- **Decisão D2 [W] 2026-08-19 — unificar em `manage_modules`** (patch P5). O `Gate::before` do
  `AuthServiceProvider` já trata essa ability como de superadmin: o atalho por papel do `else`
  **não** se aplica a ela; só a allowlist `ADMINISTRATOR_USERNAMES` (verificada presente em
  produção antes de trocar o portão) ou concessão Spatie explícita.
- **Dois casos, porque um não bastava:** o 403 sozinho seria compatível com "ninguém entra" — a tela
  quebrada, não consertada. O controle positivo prova que quem deve, entra.
- **Teste:** `tests/Feature/Modules/ModuleManagementTest.php`
- **Status: 🧪**

---

## UC-MOD-05 · Ordem canônica · `should`

- **Aceite:** Dado módulos com áreas e estados diferentes · Quando `list()` responde · Então a ordem é
  ativos primeiro, depois área (alfabética), depois nome.
- **Teste:** `tests/Feature/Modules/ModuleManagerServiceTest.php`
- **Status: 🧪**

---

## UC-MOD-06 · [ACHADO] Chave órfã no JSON não vira linha, e ninguém avisa · `must`

- **Aceite:** Dado `modules_statuses.json` com chave sem pasta correspondente em `Modules/` · Quando a
  tela lista · Então essa chave **não** aparece — a lista é varredura de pastas, não de chaves.
- **Estado atual (defeito):** são **6** hoje (Accounting, CustomDashboard, Ecommerce, FieldForce, Hms,
  InboxReport — 38 chaves contra 32 pastas, medido 2026-08-19) e a tela é silenciosa sobre elas.
- **Regressão que defende:** mostrar módulo sem código produz botão Instalar que só pode falhar.
- **Teste:** `tests/Feature/Modules/ModuleManagementTest.php`
- **Status: ❌** — decisão [W] (patch P8): limpar o JSON, ou a tela dizer "no registro, sem código".

---

## UC-MOD-07 · Pasta sem chave = "Não registrado" · `must`

- **Aceite:** Dado uma pasta em `Modules/` ausente do JSON · Então `registered = false` e `active = false`,
  e o status derivado é **Não registrado** — não "Inativo". São estados diferentes: um é decisão, o
  outro é lacuna de registro.
- **Teste:** `tests/Feature/Modules/ModuleManagerServiceTest.php`
- **Status: 🧪**

---

## UC-MOD-08 · Filtro e busca se combinam · `should`

- **Aceite:** Dado filtros e termo aplicados juntos · Então a lista é a **interseção**, nunca a união ·
  E o contador diz o recorte (`1 de 3 módulos`), não o total.
- **Saiu do `[BACKLOG]` em 2026-08-19 (MOD-O5).** Estava lá porque nenhum teste citava o id — o
  contrato de tela trava a **copy** do bloco, não o comportamento. O teste jsdom é a perna que faltava.
- **Teste:** `tests/modulos-filtros-busca.test.tsx`
- **Status: 🧪**

---

## UC-MOD-09 · Busca casa nome, alias, descrição e área · `should`

- **Aceite:** Dado o termo `oficina-auto` · Então casa pelo **alias**, que difere do nome
  (`OficinaAuto`) · E `ordens de serviço` casa pela **descrição** · E `operações` casa pela **área**,
  trazendo os dois módulos dela. Debounce de 300 ms.
- **Controle negativo incluído:** termo que não casa nada deixa a lista vazia — sem ele, um filtro
  que não filtrasse passaria em todos os casos acima.
- **Teste:** `tests/modulos-filtros-busca.test.tsx`
- **Status: 🧪**

---

## UC-MOD-11 · Toggle grava sem corromper · `must` `[T0]`

- **Aceite:** Dado o toggle de um módulo · Então o JSON tem a chave com o valor novo e **todas** as
  outras intactas · E alternar módulo que não existe em `Modules/` é recusado **antes** de qualquer
  escrita · E o endpoint recusa payload sem `active` com **422**.
- **Regressão que defende:** o arquivo é fonte de verdade do app inteiro; escrita parcial desliga
  módulo sem ninguém pedir.
- **Nota:** o rascunho [CC] também exigia `ksort` + `\n` final. **Não** virou aceite aqui porque o
  arquivo em `main` **não** está ordenado hoje (`Forja` aparece depois de `ProductCatalogue`), então
  seria teste vermelho de comportamento não-decidido. Vira UC quando [W] aprovar a ordenação.
- **Teste:** `tests/Feature/Modules/ModuleManagerServiceTest.php`
- **Status: 🧪**

---

## UC-MOD-12 · Instalar exige módulo existente · `must`

- **Aceite:** Dado um nome que não existe em `Modules/` · Quando chamo o install · Então é recusado e o
  JSON **não** é alterado.
- **Teste:** `tests/Feature/Modules/ModuleManagerServiceTest.php`
- **Status: 🧪**

---

## UC-MOD-13 · Install que falha não deixa a linha mentindo "Ativo" · `must` `[bug]`

- **Aceite:** Dado que `module:migrate` lança · Então o resultado é `success = false` **e** o estado
  do módulo volta ao que era antes da tentativa — a linha nunca afirma "Ativo" com schema incompleto.
  Em "Reinstalar" (módulo já ativo), o estado preservado é **ativo**: um migrate que falhou não pode
  derrubar módulo que estava funcionando.
- **Corrigido em 2026-08-19 (P1).** Antes, `setActive(true)` rodava antes do migrate e o `catch` só
  devolvia a mensagem. O patch [CC] propunha `setActive($name, false)` — isso conserta o "instalar" e
  **quebra o "reinstalar"**; a correção guarda o estado anterior e restaura ele.
- ⚠️ **Ressalva que a UI precisa dizer:** migrations já aplicadas antes da exceção **não** são
  revertidas. O toast do Controller avisa; o Service só restaura a flag.
- **Teste:** `tests/Feature/Modules/ModuleManagerServiceTest.php` — dois casos (instalar e reinstalar).
- **Status: 🧪**

---

## UC-MOD-14 · Desativar preserva as tabelas · `must` `[T0]`

- **Aceite:** Dado módulo ativo com migrations · Quando desativo · Então a flag vai a `false` e **nada**
  é removido do disco nem do banco. Nunca existe caminho de drop nesta tela.
- **Teste:** `tests/Feature/Modules/ModuleManagerServiceTest.php`
- **Status: 🧪**

---

## UC-MOD-15 · Cross-tenant é lei, não drift · `must` `[T0]`

- **Aceite:** Dado dois negócios distintos · Quando cada admin abre `/modulos` · Então a lista é
  **idêntica** — o estado é app-wide. Este UC existe para que ninguém "conserte" adicionando
  `business_id` scope (ADR 0093 §exceções). Habilitar por negócio é pacote no superadmin, não aqui.
- **Teste:** `tests/Feature/Modules/ModuleManagementTest.php`
- **Status: 🧪**

---

## UC-MOD-17 · A versão exibida é a declarada, não um default · `should`

- **Aceite:** Dado um módulo com `version` no `module.json` e outro sem · Então o primeiro mostra a
  versão declarada e o segundo cai no fallback `0.0`.
- **Por que importa:** hoje **0 de 32** `module.json` declaram o campo (medido 2026-08-19), então as 32
  linhas dizem `v0.0` — ruído que ensina a ignorar a coluna. É a decisão **D1**.
- **Teste:** `tests/Feature/Modules/ModuleManagerServiceTest.php`
- **Status: 🧪**

---

## UC-MOD-18 · Módulo sem DataController é identificável · `should`

- **Aceite:** Dado um módulo com `Http/Controllers/DataController.php` e outro sem · Então
  `has_datacontroller` distingue os dois.
- **Por que importa:** o `DataController` é quem monta o item na sidebar — "instalei e não apareceu no
  menu" é o sintoma clássico. Hoje o campo chega na prop e a tela **não o renderiza** (patch P3).
- **Teste:** `tests/Feature/Modules/ModuleManagerServiceTest.php`
- **Status: 🧪**

---

## UC-MOD-19 · A contagem de migrations é real · `should`

- **Aceite:** Dado um módulo com N arquivos em `Database/Migrations` · Então `migration_count = N` e
  `has_migrations` reflete N > 0.
- **Por que importa:** a confirmação de install promete "rodar N migration(s)"; N precisa ser verdade.
- **Teste:** `tests/Feature/Modules/ModuleManagerServiceTest.php`
- **Status: 🧪**

---

## UC-MOD-20 · Módulo quebrado aparece "Com erro", não silenciosamente OK · `should`

- **Aceite:** Dado um módulo cujo `module.json` está **ausente**, **malformado** ou **sem
  `providers[]`** · Quando a lista é montada · Então a linha traz `error` preenchido e o status
  derivado é **Com erro** — e módulo íntegro continua com `error = null`.
- **Por que importa:** o KPI "Com erro" e o badge existiam mas eram inalcançáveis na prática: `error`
  só era preenchido por `Throwable` na leitura, e `json_decode` de JSON malformado devolve `null` sem
  lançar. O status existia na UI e nunca podia acender.
- **FP medido antes de instalar o detector (2026-08-19):** **0 dos 32** módulos acende hoje — todos
  têm `module.json` válido com `providers[]`. Nasce escuro e só fala de módulo de fato quebrado.
- **Teste:** `tests/Feature/Modules/ModuleManagerServiceTest.php` — três casos (ausente · malformado ·
  sem providers, com controle positivo de módulo íntegro).
- **Status: 🧪**

---

## [BACKLOG] — contrato de tela, ainda sem teste que os cite

> Prosa honesta pré-teste (o canon permite bullet `[BACKLOG]` sem id). Viram UC-MOD-08/09/10/16 quando
> produção alcançar o desenho — os ids seguem reservados. Texto preservado do contrato [CC].
> (08 e 09 saíram daqui na MOD-O5, quando ganharam teste que os cita.)

- **[BACKLOG] Vazio com motivo (reservado UC-MOD-10):** filtro sem resultado diz **o motivo** (termo +
  filtros ativos) e oferece "Limpar busca e filtros" — nunca uma linha muda de tabela.
- **[BACKLOG] Detalhe em drawer (reservado UC-MOD-16):** clique na linha abre painel lateral (PT-02) com
  descrição completa, pasta, alias, migrations, registro e a nota de escopo app-wide; `Esc` e scrim
  fecham; switch e kebab **não** abrem o drawer. Depende da decisão **D3** [W].

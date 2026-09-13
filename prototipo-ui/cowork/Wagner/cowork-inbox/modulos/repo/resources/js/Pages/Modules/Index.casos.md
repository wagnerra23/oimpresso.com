---
id: resources-js-pages-modules-index-casos
casos: Gerenciador de Módulos · /modulos
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — quem-pode, o que a linha afirma e o que a ação preserva não mudam quando a tela ganhar coluna nova.
owner: wagner
status: proposto
last_run: "não executado"
last_run_ci: "nenhum — não existe lane cobrindo /modulos hoje"
---

# Casos de Uso & Aceite — Gerenciador de Módulos (`/modulos`)

> **Destino:** `resources/js/Pages/Modules/Index.casos.md`. Escrito por [CC] 2026-08-19 a partir do
> `Index.charter.md` (draft), do `ModuleManagementController`, do `ModuleManagerService` e das rotas
> `web.php:911-929` — **não do `Index.tsx`**: caso derivado do código é tautológico.
>
> **Por que nasce agora:** o trio da tela está incompleto (`.tsx` ✅ · `.charter.md` ✅ draft ·
> `.casos.md` ✗) — é a causa direta da reprovação do `ciclo-completo` e do `prototipo-readiness`.
>
> ⚖️ **Nenhum status aqui afirma verde.** Este documento não executou teste algum. `⬜` = não
> verificado; o veredito vem da lane depois que `tests/Feature/Modules/ModuleManagementTest.php`
> existir.
>
> ⚠️ Rota **cross-tenant intencional** (ADR 0093 §exceções superadmin): `/modulos` gerencia estado
> app-wide. UC-MOD-15 existe justamente para travar essa exceção contra "consertos" bem-intencionados.

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-MOD-01 | Superadmin abre a tela e vê o inventário com contagens | must | `ModuleManagementTest` | ⬜ |
| UC-MOD-02 | Usuário comum do negócio é barrado | must `[sec]` | `ModuleManagementTest` | ⬜ |
| UC-MOD-03 | Visitante sem sessão é barrado | must `[sec]` | `ModuleManagementTest` | ⬜ |
| UC-MOD-04 | Admin por papel Spatie (sem `is_admin` na sessão) entra | must `[sec]` | `ModuleManagementTest` | ⬜ |
| UC-MOD-05 | Ordem das linhas: ativos → área → nome | should | `ModuleManagerServiceTest` | ⬜ |
| UC-MOD-06 | Chave no JSON sem pasta não vira linha | must | `ModuleManagerServiceTest` | ⬜ |
| UC-MOD-07 | Pasta sem chave no JSON aparece como "Não registrado" | must | `ModuleManagerServiceTest` | ⬜ |
| UC-MOD-08 | Filtro de área e de status combinam com a busca | should | contrato de tela | ⬜ |
| UC-MOD-09 | Busca casa nome, alias, descrição e área | should | contrato de tela | ⬜ |
| UC-MOD-10 | Nenhum resultado explica o motivo e oferece saída | should | contrato de tela | ⬜ |
| UC-MOD-11 | Toggle grava o JSON sem corromper as outras chaves | must `[T0]` | `ModuleManagementTest` | ⬜ |
| UC-MOD-12 | Instalar roda migrations e diz o que fez | must | `ModuleManagementTest` | ⬜ |
| UC-MOD-13 | Instalar que falha não deixa a linha mentindo "Ativo" | must `[bug]` | `ModuleManagementTest` | ⬜ |
| UC-MOD-14 | Desativar preserva as tabelas do banco | must `[T0]` | `ModuleManagementTest` | ⬜ |
| UC-MOD-15 | Trocar de negócio não muda o que a tela mostra | must `[T0]` | `ModuleManagementTest` | ⬜ |
| UC-MOD-16 | Detalhe abre em drawer lateral, não em modal | should | contrato de tela | ⬜ |

---

## UC-MOD-01 · Superadmin vê o inventário · `must`

- **Persona:** Wagner (superadmin, 1440px).
- **Aceite:** Dado sessão com `is_admin` · Quando pede `GET /modulos` · Então 200 renderizando
  `Modules/Index` com `modules[]`, cada item com as 11 chaves do contrato do Service
  (`name·alias·version·description·area·active·registered·has_migrations·migration_count·has_datacontroller·error`),
  e o subtítulo do header traz total, ativos e inativos — "com erro" **só aparece** quando > 0.
- **Regressão que defende:** a prop é montada por varredura de filesystem; qualquer módulo novo sem
  `module.json` derrubaria a tela inteira em vez de aparecer como linha com erro.

## UC-MOD-02 · Usuário comum é barrado · `must` `[sec]`

- **Aceite:** Dado usuário autenticado sem `is_admin` na sessão e sem papel `Admin#<biz>` · Quando pede
  `GET /modulos` · Então **403** com "Acesso restrito a administradores." e **nenhuma** prop de
  módulo no corpo da resposta.

## UC-MOD-03 · Sem sessão · `must` `[sec]`

- **Aceite:** Dado requisição sem usuário · Quando pede qualquer rota `/modulos*` · Então **401**.

## UC-MOD-04 · Admin por papel Spatie · `must` `[sec]`

- **Aceite:** Dado usuário com papel `Admin#<businessId>` e sessão **sem** `is_admin` · Quando pede
  `GET /modulos` · Então 200. As duas portas de autorização do construtor ficam provadas — hoje só
  uma delas é exercitada em produção (a de sessão).
- **Nota [W]:** se a decisão D2 unificar em `manage_modules`, este UC muda de porta, não de intenção.

## UC-MOD-05 · Ordem canônica · `should`

- **Aceite:** Dado módulos com áreas e estados diferentes · Quando `list()` responde · Então a ordem é
  ativos primeiro, depois área (alfabética), depois nome — e a tela **não** reordena por conta própria
  ao carregar.

## UC-MOD-06 · Chave órfã no JSON não é linha · `must`

- **Aceite:** Dado `modules_statuses.json` com uma chave sem pasta correspondente em `Modules/`
  (hoje: Accounting, CustomDashboard, Ecommerce, FieldForce, Hms, InboxReport) · Quando a tela lista ·
  Então essa chave **não** aparece — a lista é a varredura de pastas, não a de chaves.
- **Regressão que defende:** mostrar módulo sem código produz botão Instalar que só pode falhar.

## UC-MOD-07 · Pasta sem chave = "Não registrado" · `must`

- **Aceite:** Dado uma pasta em `Modules/` ausente do JSON · Então a linha mostra **Não registrado**
  (âmbar), não "Inativo" — são estados diferentes: um é decisão, o outro é lacuna de registro.

## UC-MOD-08 · Filtros combinam · `should`

- **Aceite:** Dado área "Operações" selecionada e status "Ativo" e busca "rep" · Então a lista é a
  interseção dos três, o contador diz "N de 32 módulos", cada filtro tem chip removível e existe
  "limpar tudo". As opções de área são **as áreas presentes**, com contador.

## UC-MOD-09 · Busca de 4 campos · `should`

- **Aceite:** Dado o termo `oficina` · Então casa `OficinaAuto` por nome **e** por alias
  (`oficina-auto`); dado `m²`, casa `ComunicacaoVisual` pela descrição; dado `financeiro`, casa por
  área também. Debounce de 300 ms; `/` foca o campo; `Esc` limpa.

## UC-MOD-10 · Vazio com motivo · `should`

- **Aceite:** Dado filtro que não casa nada · Então a tela diz **o motivo** (termo buscado + filtros
  ativos) e oferece "Limpar busca e filtros" — nunca uma linha muda de tabela.

## UC-MOD-11 · Toggle grava sem corromper · `must` `[T0]`

- **Aceite:** Dado `POST /modulos/Repair/toggle {active:false}` · Então `modules_statuses.json` tem
  `Repair:false`, **todas** as outras chaves intactas, arquivo **ordenado** (`ksort` — diff estável em
  git) e terminando em `\n`; a resposta volta com `status.success` e a tela recarrega só
  `['modules','flash']`. `active` ausente ou não-booleano ⇒ **422**.
- **Regressão que defende:** o arquivo é a fonte de verdade do app inteiro; uma escrita desordenada ou
  parcial vira conflito de merge e módulo desligado sem ninguém pedir.

## UC-MOD-12 · Instalar diz o que fez · `must`

- **Aceite:** Dado módulo com N migrations · Quando o operador confirma ("vai rodar N migration(s) e
  ativar o módulo") · Então roda `module:migrate --force`, tenta `<alias>:install` com `--business` da
  sessão (ou `--all`), e o toast distingue os dois casos: só migrations vs "Setup completo: permissões
  + plano de contas pré-populados". Módulo inexistente ⇒ mensagem de erro, JSON **não** alterado.

## UC-MOD-13 · Install que falha não mente · `must` `[bug]`

- **Aceite:** Dado que `module:migrate` lança · Então a UI mostra o erro **e** a linha não afirma
  "Ativo" instalado: ou a flag volta a `false`, ou o status derivado passa a **Com erro**.
- **Estado atual (defeito):** `install()` chama `setActive($name, true)` **antes** do migrate e o
  `catch` só devolve mensagem — a linha fica verde com o banco inconsistente. Patch proposto em
  `cowork-inbox/modulos/PATCHES.md`.

## UC-MOD-14 · Desativar preserva tabelas · `must` `[T0]`

- **Aceite:** Dado módulo com tabelas criadas · Quando `POST /modulos/{name}/uninstall` · Então a flag
  vai a `false`, o toast diz "(tabelas preservadas)" e uma tabela conhecida do módulo **continua
  existindo**. Nunca existe caminho de drop nesta tela.

## UC-MOD-15 · Cross-tenant é lei, não drift · `must` `[T0]`

- **Aceite:** Dado dois negócios distintos · Quando cada admin abre `/modulos` · Então a lista é
  **idêntica** — o estado é app-wide. Este UC existe para que ninguém "conserte" adicionando
  `business_id` scope (ADR 0093 §exceções). Habilitar por negócio é pacote no superadmin, não aqui.

## UC-MOD-16 · Detalhe em drawer · `should`

- **Aceite:** Dado clique na linha · Então abre painel lateral (PT-02) com descrição completa, pasta
  `Modules/<Nome>`, alias, contagem de migrations, registro e a nota de escopo app-wide + preservação
  de tabelas; `Esc` e o scrim fecham; clicar no switch ou no kebab **não** abre o drawer.
- **Depende de:** decisão D3 [W] — se o drawer não entra na produção, a descrição truncada precisa de
  tooltip e este UC vira "a linha revela a descrição completa sem sair da tela".

---
sessao: "06-bens"
titulo: "Bens — a primeira tela Inertia do Patrimônio (funda o `_shared`)"
dono: "[CL]"
criado: 2026-09-08
base: 0f39a46a06 (origin/main fresco no início; +3 commits durante a sessão)
thread: 06-ui-bloqueada.md
prefixo_escrito: "resources/js/Pages/Patrimonio/{Bens.tsx,Bens.charter.md,Bens.casos.md,_shared/PatrimonioSubNav.tsx} · Modules/AssetManagement/Http/Controllers/AssetController.php (só o index()) · memory/requisitos/AssetManagement/RUNBOOK-bens.md · +3 FORA do prefixo, cada um exigido por gate required: Modules/AssetManagement/Tests/Feature/BensContratoTest.php (casos-gate G-2) · scripts/governance/module-surface.mjs (PAGES_NS, §1-bis) · memory/requisitos/AssetManagement/SUPERFICIE.md (derivado, regerado)"
pr: "#7035 — aberto, NÃO mergeado"
veredito: "entregue — tela migrada, `_shared` fundado, 4 UC com teste verde no CT 100 · 1 resíduo Tier 0 herdado MEDIDO e declarado · 3 achados que afetam as 6 threads irmãs"
invalida: "06-ui-bloqueada.md §'Este arquivo NÃO é uma thread': o enunciado 'SubNav das 7 abas' está ERRADO — são 6, e a lista tem dono vivo (§2 abaixo) · o item 'a primeira rota Inertia no Routes/web.php' do prefixo: NÃO existe rota nova a criar (§3) · toda thread-filha do Patrimônio: o charter tem de vir ANTES do .tsx, senão o hook MWART bloqueia (§1)"
---

# 06-bens · Saída — Bens migrada, e o que ela descobriu para as outras 6

> **Estado inicial confirmado:** `resources/js/Pages/Patrimonio/` estava **vazio** no `main`
> (`git ls-tree -r origin/main` = 0 arquivos). Nenhuma sessão passou na frente.

---

## 1 · ACHADO QUE TRAVA AS 6 IRMÃS — o hook MWART não conhece a ADR 0394

**O bloqueio, literal:**

```
[mwart-process] Write em 'resources/js/Pages/Patrimonio/Bens.tsx' BLOQUEADO.
ADR 0104 §F1 PLAN exige RUNBOOK 'memory/requisitos/Patrimonio/RUNBOOK-bens.md'…
A pasta 'memory/requisitos/Patrimonio/' nem existe — F1 (PLAN) nunca rolou.
```

O hook deriva o módulo do **nome da pasta de `Pages/`** (`PAGE_REGEX` em
`block-mwart-violation.mjs:61`, lookup em `runbookStatus():98`) e procura
`memory/requisitos/<essa-pasta>/`. A ADR 0394 desacoplou os dois: a UI é `Patrimonio`, os
requisitos são `AssetManagement`. **A pasta que ele pede não existe nem deve existir** —
criá-la abriria um segundo dono de requisitos ao lado do `AssetManagement/`.

**Não mexi no hook.** O mecanismo previsto já resolve, e está escrito no próprio arquivo
(`:117-123`): o campo **`related_runbook`** (ou `runbook:`) do **charter irmão**, quando
aponta pra um arquivo que EXISTE, resgata o bloqueio — *"declaração é autoritativa,
adivinhação não"*. Confirmado rodando o `decide()` direto:

```
decide('Write','resources/js/Pages/Patrimonio/Bens.tsx') → null   (LIBERADO)
```

**Regra para as 6 threads-filhas:** escreva o `<Tela>.charter.md` **antes** do `.tsx`, com

```yaml
related_runbook: memory/requisitos/AssetManagement/RUNBOOK-<tela>.md
```

e o RUNBOOK de fato existindo. Sem isso o hook barra, e ele **não tem override**.

---

## 1-bis · O SEGUNDO mecanismo que não conhecia a ADR 0394 — `PAGES_NS` (resolvido aqui, para as 7)

O CI pegou o gêmeo do achado §1, em outro eixo. O `module-surface.mjs` mapeia módulo↔telas
pelo **nome da pasta de `Pages/`**, igual ao hook — e o job `Mapa módulo↔Pages == renders
reais` reprovou com a linha pronta:

```
✗ namespace(s) com dono ÚNICO fora do PAGES_NS — o módulo não enxerga as próprias telas:
    Patrimonio (AssetManagement×1) → declare em PAGES_NS: AssetManagement: ['AssetManagement', 'Patrimonio']
```

Declarei, e isso vale **para as 7 telas de uma vez** — as irmãs não precisam repetir. Sem a
linha, o `SUPERFICIE.md` do módulo sairia sem nenhuma tela: é o mesmo ponto cego que, medido
em 2026-08-12, custou 26 telas ao Whatsapp (o gate ficava verde porque gerado e commitado
compartilhavam a mesma cegueira).

⚠️ **Este gate tem DOIS modos, e o CI roda os dois** (`--namespaces --check` e `--all --check`).
Declarar o namespace resolve o primeiro; o segundo continuava vermelho até eu regenerar o
derivado (`module-surface.mjs AssetManagement --write` → 104 arquivos). Rodar um modo e
declarar verde é a armadilha do §5 2026-07-28.

---

## 2 · São **6** abas, não 7 — e a lista tem dono vivo

A thread 06 enuncia *"o `_shared` (SubNav das 7 abas)"*. **Medido: são 6, e a lista não é
minha nem do protótipo.** O dono é `DataController::modifyAdminMenu()`
(`DataController.php:109-138`), que chega ao React como `shell.menu`:

| # | ghost `key` | label | href |
|---|---|---|---|
| 1 | `dashboard` | Painel | `/asset/dashboard` |
| 2 | `assets` | Ativos | `/asset/assets` |
| 3 | `allocation` | Alocações | `/asset/allocation` |
| 4 | `revocation` | **Devoluções** | `/asset/revocation` |
| 5 | `asset-maintenance` | Manutenção | `/asset/asset-maintenance` |
| 6 | `settings` | Configurações | `/asset/settings` |

Contra as 7 do protótipo (`patrimonio-page.jsx:835`):

- **Devoluções** existe como **rota real** e o protótipo a trata como estado dentro de
  Alocações. A rota manda.
- **Garantias** e **Auditoria** **não têm rota** — e são decisões de produto **ABERTAS** do
  [W] (itens 4 e 5 do `00-INDICE.md §6`). O próprio protótipo admite: na aba Garantias ele
  escreve *"O módulo real não tem tela própria de garantia"*.

Por isso o `PatrimonioSubNav` **deriva** de `shell.menu` em vez de declarar array — o padrão
do `PontoSubNav`. Quando [W] decidir Garantias/Auditoria, elas entram pelo `DataController`
e aparecem **sem tocar no `_shared`**.

**Consequência para as threads:** quem for fazer "Garantias" ou "Auditoria" está fazendo uma
tela cuja **existência ainda é pergunta aberta** — decidir a tela antes da pergunta dela é
retrabalho, como a própria 06 avisa.

---

## 3 · `Routes/web.php` — **não mexi**, e não deve mexer

O prefixo autorizava, mas a rota **já existe**: `Route::resource('assets', AssetController::class)`
cria `assets.index` → `GET /asset/assets`. Rota nova seria um **segundo dono da mesma tela**,
e mudaria a URL de uma tela em produção sem necessidade.

O que muda é **o que o `index()` devolve**. Autorização não é obrigação.

---

## 4 · O que a tela entrega, e o que ela recusa

Detalhe com motivo no [RUNBOOK §5](../../../../../memory/requisitos/AssetManagement/RUNBOOK-bens.md)
e nos Non-Goals do charter. Resumo dos três que mais saltam:

| Recusado | Motivo |
|---|---|
| sub-recortes "Garantia crítica" / "Em manutenção" | pedem **predicado SQL novo**; filtrar só a página corrente faria a pílula dizer "3" olhando 25 de N linhas |
| **total somado de valor** | é **REGRA MESTRE Tier 0** — prova por dois caminhos + antes→depois pro [W]. O valor **por linha** entrou (o Blade já o mostrava) |
| seleção em lote / BulkBar | as duas ações do protótipo **não têm endpoint** |

**Zero regressão vs. o Blade:** as 4 ações de linha, imagem, garantia e "n em manutenção"
entraram. O excluir usa `router.delete` + confirmação porque `destroy` é rota `resource`
(verbo DELETE) — link `<a>` não a alcançaria, e botão que parece excluir e não exclui é
afordância falsa.

**Um dono só para a agregação:** extraí `baseAssetsQuery()` + `applyAssetFilters()`, lidos
pelos **dois** ramos (AJAX/Blade e Inertia). Antes o cálculo teria duas cópias e a próxima
correção pousaria em uma só. **Teste de identidade:** 19 linhas de código da expressão,
`diff` vazio — a agregação não mudou uma vírgula.

---

## 5 · ⚠️ RESÍDUO Tier 0 — medido, com o antes→depois pronto

As agregações do `index()` **não filtram por tenant**:

```sql
-- allocated_qty (join AT) e revoked_qty (subconsulta AR) — nenhum predicado de business
(SELECT SUM(COALESCE(AR.quantity,0)) FROM asset_transactions AS AR
   WHERE (AR.asset_id=assets.id AND AR.transaction_type='revoke'))
```

É o gêmeo de `_saida-01.md §9(a)` (*"tem MAIOR alcance — é o índice"*), com thread dona.
**Não corrigi**, e não é omissão: mexer em quantidade é REGRA MESTRE Tier 0 (prova dupla +
antes→depois + [W]) e 1 PR = 1 intent. A expressão foi preservada byte-a-byte.

**O antes→depois, medido no CT 100 em 2026-09-08** (leitura pura, as duas formas lado a lado):

```
assets varridos ............................................. 128
linhas DIVERGENTES ...........................................  20
asset_id com transação de OUTRO business (pré-condição) ......  20

id | biz | codigo        | aloc_hoje -> corrigido | revog_hoje -> corrigido
 1 |  98 | AST-CRS-TNT01 | 10.0000 -> 10.0000     | 4.0000 -> 0.0000
12 |  98 | AST-CRS-TNT01 | 10.0000 -> 10.0000     | 4.0000 -> 0.0000
… (20 linhas, mesmo padrão)
```

Efeito na tela: **"Alocado" mostra 4 unidades A MENOS** do que deveria, por descontar
revogação de outro tenant.

**Ressalva de honestidade:** essa base é o staging do CT 100 e as divergências vêm de
fixtures acumulados dos testes cross-tenant (`AST-CRS-TNT01`), **não** de dado de cliente.
A medição prova que **a query vaza quando a pré-condição existe**; ela **não afirma nada
sobre produção**, que não foi medida. Quem for fechar precisa rodar o mesmo script contra o
banco de prod antes de apresentar impacto ao [W].

---

## 6 · Evidência — CT 100, nunca local

```
BensContratoTest .... 4 passed ·  23 assertions   (novo, 4 cenários / 3 UC)
SmokeRoutesTest ..... 7 passed ·  12 assertions   (IDÊNTICO ao baseline pré-mudança)
suíte do módulo ..... 76 passed · 254 assertions · 0 falhas
```

**O baseline foi tirado ANTES de eu tocar o controller** — e ele mesmo teve uma pegadinha: o
checkout do container estava em `755f6de79` e o `SmokeRoutesTest.php` de lá tinha **1174
bytes / 4 testes**, contra 15728 bytes / 7 testes no `main`. A primeira execução deu
`4 passed` e teria virado um baseline falso. Materializei os blobs do `origin/main` (conferi
que os SHA batiam com os do meu checkout) e o baseline real ficou **7 passed · 12 assertions**.

**As 4 falhas úteis do caminho** (cada uma virou nota no teste):

1. `Inertia page component file [Patrimonio/Bens] does not exist` — o assert **verifica que o
   componente existe no disco**. Ele morde.
2. `409` no partial reload — `Inertia::getVersion()` chamado fora do ciclo da request não bate
   com o que o middleware calcula. O teste passou a **ler a versão do próprio render**.
3. `bens.total = 0` com o bem no banco — fixture sem `access_all_locations` faz
   `permitted_locations()` devolver `[]` (`app/User.php:156-172`) e o `whereIn` zerar tudo.
   Teria "provado" isolamento por acidente, medindo permissão de LOCAL em vez de TENANT.
4. Fixture fora da página 1 — o CT 100 é base **persistente** e já tinha **82** assets no
   tenant 98. O cenário passou a usar um termo de busca que põe dono e adversário na **mesma
   página**, o que torna o isolamento a única explicação possível.

**Controle bidirecional no lugar do bite-test por mutação:** o UC-BENS-01 tem um segundo
cenário em que o adversário, na mesma tela e mesma busca, **vê** o bem dele e **não** o do
dono. Provei assim porque a mutação (remover o `where('assets.business_id')`) foi **bloqueada
pelo classificador de segurança** — corretamente, já que desliga proteção Tier 0. O par mede
o mesmo predicado sem tocar na guarda.

**Higiene do ambiente compartilhado:** o checkout do CT 100 tinha trabalho **não-commitado de
outra sessão** (`AssetAllocationService.php`, `CrossTenantAssetTest.php`,
`MultiTenantIsolationTest.php` — os três deste módulo). **Não dei `git pull`.** Copiei só os
arquivos que toquei, e restaurei ao **fingerprint exato do início**:

```
início ... d49896ac7627cc191fb189da9b596cf0
final .... d49896ac7627cc191fb189da9b596cf0
```

---

## 7 · Gates locais

```
casos-gate ......... 0 violações novas deste PR (débito −8 vs baseline)
pages-colisao ...... nenhuma chave declarada por duas fontes
ancora.mjs ......... âncora ✓ [related_prototype] patrimonio-page.jsx
                     frescor verificado contra o Cowork vivo em 2026-09-08
screen-coverage .... charter 219/219 · Patrimonio: 1 tela, 1 charter
```

`memory-schemas/validate.mjs` não rodou: este worktree está sem `node_modules` (sem `ajv`).
Validei os campos do `runbook.schema.json` e do `charter.schema.json` **à mão contra o
schema** — `required`, enums de `owner`/`status`/`tier` e os `pattern` de `page`,
`component`, `related_prototype` e `last_validated`. O gate de CI é a régua real.

---

## 8 · Fora do prefixo — registrado, NÃO consertado

1. **`SPEC.md` ficou atrás.** A migração Blade→Inertia ainda está no **backlog** como
   `US-ASSET-W05` 🔒 *feature-wish sem sinal qualificado*, enquanto a ADR 0394 e o `SCOPE.md`
   já a liberaram. Pela regra de precedência o SPEC é o elo mais fraco e deveria ganhar US
   ativa com âncora `**Implementado em:**`. **É o mesmo padrão que a thread 04 pegou** (o
   `SCOPE.md` dizendo `bloqueado-escopo` enquanto o workflow já apontava pro endereço novo):
   a decisão anda e o dono canônico fica pra trás.
2. **`Bens-visual-comparison.md`** não existe — comparação medida contra o protótipo
   (`design-diff --probe` nos dois lados, nunca no olho). É pendência declarada do charter
   para sair de `status: draft`.
3. **Precisão do enunciado da thread:** o prompt diz *"Permissões vivas: `asset.view` ·
   `asset.create` · `asset.update` · `asset.delete` (`DataController.php:31-58`)"`. O range
   está certo para essas 4, mas o `user_permissions()` declara **6** — `asset.view_all_maintenance`
   e `asset.view_own_maintenance` vêm logo depois (`:52-:66`) e a tela **usa** a segunda dupla
   pra decidir o botão de manutenção. Não é contradição, é recorte; registrado pra ninguém
   concluir que são 4.

---

## 9 · Campo `invalida:` — detalhado

| alvo | veredito |
|---|---|
| **06-ui-bloqueada.md** — *"o `_shared` (SubNav das 7 abas)"* | **ERRADO na contagem e no dono.** São **6** ghosts vivos, com Devoluções e sem Garantias/Auditoria; e a lista **não se declara no `_shared`** — ela é derivada de `shell.menu`. Ver §2. |
| **prefixo da thread** — *"a primeira rota Inertia no `Routes/web.php`"* | **NÃO se aplica.** `assets.index` já existe via `Route::resource`; rota nova seria segundo dono. Arquivo não tocado. Ver §3. |
| **as 6 threads-filhas** (Painel · Alocações · Manutenções · Garantias · Auditoria · Configurações) | **CALIBRAGEM OBRIGATÓRIA:** charter **antes** do `.tsx`, com `related_runbook` apontando pro `memory/requisitos/AssetManagement/` — senão o hook MWART bloqueia e não há override. Ver §1. |
| **threads Garantias e Auditoria** | **ESCOPO EM ABERTO, não destravado.** Nenhuma tem rota, e as duas são pergunta aberta do [W] (`00-INDICE.md §6`, itens 4 e 5). Fazer a tela antes da resposta é retrabalho. |
| **thread 01** (tenant na subconsulta) | **CORROBORADA e MEDIDA.** O gêmeo do `index()` é real: **20 de 128** assets divergem no CT 100, padrão `revogado 4 → 0`. Antes→depois pronto no §5. Preservei a expressão byte-a-byte — quem fechar não pega conflito de conteúdo, só de contexto. |
| **thread 03** (guarda `asset.view`) | **INTACTA.** O `SmokeRoutesTest` roda **7 passed · 12 assertions** com o `index()` em Inertia — mesmo número do baseline. A guarda foi preservada e continua mordendo. |
| **`00-INDICE.md §6` item 2** (prefixo de permissão) | já fechado como errata pela thread 04; nada a acrescentar, salvo o recorte do §8.3 (são 6 permissões, não 4). |

---

## 10 · Checklist de saída

| # | item | estado |
|---|---|---|
| 1 | `Pages/Patrimonio/` vazio antes de começar | ✅ confirmado (`git ls-tree origin/main` = 0) |
| 2 | RUNBOOK antes do `.tsx` (MWART F1) | ✅ `RUNBOOK-bens.md` |
| 3 | baseline do backend antes de tocar o controller (F2) | ✅ 7 passed · 12 assertions |
| 4 | `.tsx` + charter + casos ao lado | ✅ os três, linkando-se |
| 5 | QA (F4) | ✅ 4 UC verdes no CT 100 + suíte do módulo 76/76 |
| 6 | cutover (F5) | ❌ **não feito, como mandado** |
| 7 | PR aberto, **não mergeado** | ✅ [#7035](https://github.com/wagnerra23/oimpresso.com/pull/7035) |
| 8 | ambiente compartilhado restaurado | ✅ fingerprint idêntico ao inicial |
| 9 | campo `invalida:` preenchido | ✅ frontmatter + §9 |

### Como reproduzir os números

```bash
# baseline e regressão (CT 100 — nunca local)
tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging \
  php artisan test Modules/AssetManagement/"

# teste de identidade da agregação (a expressão não mudou)
git show origin/main:Modules/AssetManagement/Http/Controllers/AssetController.php \
  | grep -E "leftJoin|SUM\(COALESCE|groupBy\('id'\)" | grep -v "^\s*//"
# ... e o mesmo grep no arquivo atual: 19 linhas, diff vazio

# o hook libera com o charter declarando related_runbook
node --input-type=module -e "
  const {decide} = await import('./.claude/hooks/block-mwart-violation.mjs');
  console.log(decide('Write','resources/js/Pages/Patrimonio/Bens.tsx', process.cwd()));"
# → null

# âncora de design (porta viva, nunca no olho)
node prototipo-ui/ancora.mjs "Patrimonio/Bens" --staging prototipo-ui/cowork
```

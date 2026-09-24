---
sessao: "06-configuracoes"
titulo: "Configurações — as 3 blades viram uma tela Inertia; a armadilha do checkbox foi medida"
dono: "[C]"
criado: 2026-09-08
base: 0f39a46a06 (origin/main no início da sessão) → e6d53daf1f → merge de 4f70a09460 (Painel #7040); ver §0 e §9-quater
thread: 11-configuracoes.md
prefixo_escrito: "resources/js/Pages/Patrimonio/{Configuracoes.tsx,Configuracoes.charter.md,Configuracoes.casos.md} · Modules/AssetManagement/Http/Controllers/AssetSettingsController.php (só o index()) · memory/requisitos/AssetManagement/RUNBOOK-configuracoes.md · +2 FORA do prefixo, cada um exigido por gate required: Modules/AssetManagement/Tests/Feature/ConfiguracoesContratoTest.php (casos-gate G-2) · memory/requisitos/AssetManagement/SUPERFICIE.md (derivado, regerado)"
pr: "#7044 — MERGEADO por [W] em 2026-09-08 18:57:33Z (commit 631fc88956), com 79 checks verdes e 0 falhas. Fato datado, não estado: quem ler isto depois consulta o PR, não esta linha"
veredito: "entregue — 3 blades migradas para 1 tela Inertia, 5 testes verdes no CT 100 (35 assertions), paridade integral com o Blade · 1 armadilha de migração MEDIDA e neutralizada · 2 achados declarados e não consertados · 5 gates do CI reprovaram e foram corrigidos (§9-bis), nenhum por carimbo"
invalida: "11-configuracoes.md §prefixo: o caminho declarado é `Pages/Patrimonio/Configuracoes/` (subpasta) e o padrão real da frente é FLAT — `Configuracoes.tsx` ao lado de `Bens.tsx` (§4) · 11-configuracoes.md §Execução PASSO 1 ('confirmar 07 mergeada'): a 07 NÃO está mergeada e não precisa estar — quem fundou o `_shared` foi Bens (a errata do topo da própria ficha já diz isso; o checklist ficou desatualizado) · CONFIRMA integralmente o `_saida-06-bens.md §1` (charter antes do .tsx) e `§3` (não mexer no Routes/web.php) — reproduzi os dois e batem 1:1"
---

# 06-configurações · Saída — a menor tela da frente, e a armadilha que ela escondia

> **A migração FOI feita.** As três blades (`index`, `prefix_settings`, `notification_settings`)
> viraram uma tela Inertia. A dependência que barrou a thread de Manutenções — o `_shared/` —
> **existe desde o PR #7035**, e a checagem dela é o §0.

---

## 0 · O gate de dependência, e o que ele ensinou sobre medir duas vezes

A instrução da thread manda parar se `Pages/Patrimonio/_shared/**` não existir no `main`.
**Medi, e ele não existia. Medi de novo minutos depois, e existia.**

| Momento | `origin/main` | `Pages/Patrimonio/` |
|---|---|---|
| início da sessão | `0f39a46a06` | **0 arquivos** (controle positivo: 794 em `Pages/`) |
| ~15 min depois, sem eu fetchar | `9d0041c0e2` | `Bens.tsx` · `Bens.charter.md` · `Bens.casos.md` · `_shared/PatrimonioSubNav.tsx` |
| ao começar a escrever | `e6d53daf1f` | idem |

**Worktrees compartilham o `.git`.** Outra sessão fetchou e o meu `origin/main` avançou sem
nenhum comando meu — a base envelhece sozinha (§5 2026-08-03). A primeira medição estava
correta **para o ref que eu tinha** e obsoleta em minutos. Registro porque a conclusão que ela
sustentava ("a fundação não existe, PARE") chegou a ser publicada, e desfazê-la em silêncio
seria pior que o erro.

**A lição executável:** antes de agir sobre claim de ausência que decide parar/seguir,
**re-fetch e re-meça** — o custo é um comando, e aqui a diferença era entre entregar a tela e
não entregar.

---

## 1 · O que a tela entrega

**Paridade integral com as 3 blades, sem exceção.** Os **4** prefixos, o multi-select de
destinatários, os **2** interruptores de e-mail e os **4** campos de template (assunto + corpo
de cada notificação), com a lista de tags de cada bloco.

O que **não** entrou está no §5 do [RUNBOOK](../../../../../memory/requisitos/AssetManagement/RUNBOOK-configuracoes.md)
e nos Non-Goals do charter, cada um com motivo: **WYSIWYG** (o Blade usa TinyMCE; editor
rich-text no React é dependência nova, que exige ADR), os **3 interruptores do protótipo** (sem
backend) e a **linha de retenção** (aponta a thread 05, barrada).

---

## 2 · ⚠️ A ARMADILHA — medida, não deduzida

**É o achado desta thread, e ele não existia no Blade.**

O `store()` decide os interruptores por `$request->has(...)`, não `boolean(...)`
(`AssetSettingsController.php:117-123`). Checkbox HTML desmarcado **não envia a chave** — é
assim que ela some do JSON. Um cliente Inertia que mandasse `enable_...: false` faria `has()`
devolver **true**.

Sonda no CT 100, os dois payloads lado a lado:

```
payload com 'false' => {"asset_code_prefix":"X-","enable_asset_send_for_maintenance_email":1}
payload SEM a chave  => {"asset_code_prefix":"X-"}
```

**Mandar `false` LIGA o e-mail que o usuário acabou de desligar** — em silêncio, sem erro em
lugar nenhum, e o usuário só descobre quando a mensagem chega em quem mandou parar.

**O conserto não tocou o controller.** A tela **omite a chave** quando desmarcada
(`Configuracoes.tsx`, o `form.transform`), replicando exatamente o checkbox HTML. Preserva o
contrato do Blade sem mudar o `store()` — e o **UC-CFG-04** fixa isso contra a coluna real, nos
dois sentidos (desliga e religa).

Era o caminho fácil de errar: `useForm` com booleano é o idioma natural do Inertia, e teria
passado em qualquer revisão de código que não conhecesse o `has()`.

---

## 3 · Achados declarados, NÃO consertados

**(a) O `try/catch` do `store()` é inerte.** `catch (Exception $e)` (`:203`) dentro do namespace
`Modules\AssetManagement\Http\Controllers`, **sem** `use Exception;` e **sem** a barra inicial —
medido: 0 ocorrências de `use Exception`. Ele resolve para
`Modules\AssetManagement\Http\Controllers\Exception`, classe que não existe, então **nunca
captura** `\Exception`. Na prática: erro real vira 500 em vez da mensagem amigável que o código
promete. Uma barra conserta, mas é **comportamento de erro** — muda o que o usuário vê — e
1 PR = 1 intent. Fica com âncora, para virar thread.

**(b) O `store()` regrava o JSON inteiro, não faz merge.** `$request->only(...)` monta 5 chaves
e o `json_encode` substitui a coluna. Hoje não há perda, porque o formulário cobre tudo que o
módulo lê — mas quem acrescentar chave nova tem de acrescentá-la **também** ao `only()`, senão
ela some no primeiro save. Documentado no §8 do RUNBOOK e nos Anti-hooks do charter.

**Nenhum dos dois é regressão desta onda** — os dois são anteriores e sobrevivem intactos.

---

## 4 · O caminho é FLAT, não subpasta (é o que o campo `invalida:` aponta)

A ficha declara `prefixo: resources/js/Pages/Patrimonio/Configuracoes/` — **subpasta**. O padrão
real da frente é **flat**: o `main` tem `Bens.tsx`, `Bens.charter.md`, `Bens.casos.md` no nível
de `Pages/Patrimonio/`. Entreguei `Configuracoes.tsx` flat, ao lado deles.

**Não é preferência minha:** é o mesmo erro que o índice **já corrigiu** para Bens — a errata da
thread 08 registra que *"o caminho da prova era `Bens/Index.tsx` (subpasta) e o arquivo mergeado
é `Bens.tsx` (flat) — a prova NUNCA passaria"*. A ficha 11 herdou a redação antiga. Seguir a
subpasta criaria o segundo padrão que a própria thread manda evitar.

---

## 5 · Evidência — CT 100, nunca local

**Suíte da tela:** `5 passed · 35 assertions` (seed 1788892480 — run FINAL, depois do defer).
O run anterior tinha 36; a assertion a menos é exatamente o `has(usuarios)` removido (§9-ter).

```
✓ UC-CFG-01: usuário NÃO-admin do business recebe 403 em /asset/settings
✓ UC-CFG-01: o ESPELHO — o admin do mesmo business recebe 200
✓ UC-CFG-02: a rota /asset/settings devolve Inertia com o componente Patrimonio/Configuracoes
✓ UC-CFG-03: as settings exibidas são as do MEU business, não as do vizinho
✓ UC-CFG-04: desligar o e-mail desliga de verdade — a chave ausente é o "desligado"
```

**Regressão do módulo:** `74 passed · 277 assertions`, **0 falhas**.

⚠️ **Ressalva honesta sobre esse 74.** O `_saida-06-bens.md` reporta `76 passed` para a mesma
suíte, e **os dois números não se comparam**: o checkout do container está em `755f6de79` e
**não tem** o `BensContratoTest.php` (listei o diretório: tem `ManutencoesContratoTest`, não tem
o de Bens). São árvores diferentes — comparar seria a armadilha de medir em contextos distintos.
O que este run afirma é: **zero falhas no estado medido**, com os meus 5 dentro do total.

**Por que ler `assertions` e não `0 failed`:** teste que pula sai com exit 0 (§5 2026-07-24). As
36 e as 277 assertions são a prova de que rodou.

**Transporte dos arquivos para o container conferido byte a byte** (o container estava atrasado e
não tinha nem o `Pages/Patrimonio/`): `16782 / 10225 / 10776` idênticos nas duas pontas. O
`assertInertia` **exige o `.tsx` presente** — foi o que reprovou Bens na primeira execução.

---

## 6 · Gates locais

| Gate | Resultado |
|---|---|
| `module-surface --namespaces --check` | ✓ verde |
| `module-surface --all --check` | ✓ verde (`SUPERFICIE.md` regerado: 109 arquivos) |
| hook `block-mwart-violation` | liberou — charter escrito ANTES do `.tsx`, com `related_runbook` apontando pro RUNBOOK real |

**Os DOIS modos do `module-surface` foram rodados**, não um só: o job de CI roda os dois, e
declarar verde tendo rodado um é a armadilha do §5 2026-07-28.

`PAGES_NS` **não precisou de linha nova** — Bens já declarou `AssetManagement: ['AssetManagement',
'Patrimonio']` para as 7 telas de uma vez (§1-bis dela). Confirmado pelo verde do modo 1.

---

## 7 · O que NÃO foi tocado, e por quê

- **`Routes/web.php`** — a rota já existe (`Route::resource('settings', …, ['as' => 'asset'])`,
  `:19`). Rota nova seria segundo dono da mesma tela e mudaria a URL de uma tela em produção
  sem necessidade. Confirma o `_saida-06-bens.md §3`: **autorização não é obrigação**. (O
  arquivo é compartilhado com as threads irmãs; não tocá-lo elimina o risco de conflito.)
- **`store()`** — intocado, byte a byte. A tela se adapta ao contrato dele, não o contrário.
- **`Config/retention.php`** — thread 05, barrada.
- **`AssetUtil`, `Services/`, os outros controllers, `_shared/`** — fora do prefixo.
- **A guarda `is_admin`** — preservada nas duas linhas (`:43` e `:110`), e agora **defendida por
  teste** (UC-CFG-01), que é mais do que ela tinha antes.

---

## 8 · Campo `invalida:` — detalhado

| Alvo | Veredito |
|---|---|
| **`11-configuracoes.md` §prefixo** | **CORRIGIDO.** Declara subpasta `Configuracoes/`; o padrão real é flat (§4). Mesma correção que a thread 08 já sofreu. |
| **`11-configuracoes.md` §Execução PASSO 1** | **CORRIGIDO.** "confirmar 07 mergeada" — a 07 não está mergeada e não precisa: o `_shared` foi fundado por **Bens** (#7035). A errata do topo da própria ficha já diz isso; só o checklist ficou para trás. |
| **`_saida-06-bens.md` §1** (charter antes do `.tsx`) | **CONFIRMA.** Reproduzido: com o charter e o RUNBOOK no lugar, o hook liberou na primeira tentativa. |
| **`_saida-06-bens.md` §3** (não mexer no `Routes/web.php`) | **CONFIRMA.** Nenhuma rota nova foi necessária (§7). |
| **`06-ui-bloqueada.md`** — orçamento de 46 arquivos | **CALIBRA.** Esta tela usou 3 arquivos de `Pages/` + RUNBOOK + teste, e **zero** `_components`. O orçamento previa 6 `_components` para as 7 telas; a menor delas não precisou de nenhum. |
| threads 07 · 09 · 12 · 13 | **NADA.** Não toco no prefixo de nenhuma. |

---

## 9 · Checklist de saída (o da ficha, item a item)

| # | Item | Estado |
|---|---|---|
| 1 | 07 mergeada | **N/A** — a dependência real era o `_shared`, e ele veio por Bens (§8) |
| 2 | charter + casos | ✓ ambos, com os 4 UC citados por `it()` |
| 3 | 3 views em Inertia | ✓ as 3 blades viraram 1 tela |
| 4 | `retention.php` intocado | ✓ |
| 5 | 9 Pest verdes | ✓ **74 passed · 277 assertions**, 0 falhas (a suíte cresceu desde a ficha) |
| 6 | placar | ✓ §5 |

---

## 9-bis · Os 5 gates que reprovaram, e o que cada um ensina (custo real desta thread)

O CI reprovou **5 vezes** depois do PR aberto. Nenhum era falso-positivo; **4 eram meus**.
Registro cada um porque as threads irmãs vão bater exatamente nos mesmos.

| Gate | Causa | Lição pra próxima |
|---|---|---|
| `Layout primitives · ratchet` | `<div className="grid grid-cols-1 gap-4 sm:grid-cols-2">` | Use `<Grid>`. E prefira **`min="lg"`** (auto-fill por token) a `sm:grid-cols-2`: reflowa entre 1280 e 1440 sem media-query. **Eu tinha escrito a regra no cabeçalho do próprio arquivo e a violei 200 linhas abaixo** |
| `PHPStan · ratchet` | `index()` com `@return Response` (Illuminate) devolvendo `Inertia\Response` | Ao migrar um método pra Inertia, **o docblock migra junto**. O `use Illuminate\Http\Response` do topo continua servindo os outros métodos — muda só a anotação do método migrado |
| `module-grades-gate` | `-4 pts D6.a` — controller renderiza Inertia **sem** `Inertia::defer` | Ver §9-ter: o conserto certo não é carimbo |
| `deadlink-gate` | `[…](_DesignSystem/RUNBOOK-…)` a partir de `requisitos/AssetManagement/` | Link relativo em RUNBOOK de módulo precisa de **`../`** pra alcançar `requisitos/_DesignSystem/`. O gate reporta o alvo exato — leia a linha em vez de adivinhar |
| `Preflight + contratos ativos` | **`branch atrás de origin/main (não-ancestral)`** | **Não era divergência de contrato.** A mensagem lista 7 causas possíveis; a linha que disparou estava acima, no log. Num repo que anda ~41 commits/dia, a base envelhece entre o rebase e o push |

**`Pest Repair` também reprovou, e NÃO é meu:** na última run do `main`, o único job vermelho
é exatamente `Pest Repair` (5 de 5 runs recentes em falha). Dívida **herdada** — este PR não
toca `Modules/Repair`. Depois do merge do `main` ele parou de aparecer na lista, porque a lane
não dispara pros meus paths.

## 9-ter · O gap do grader: por que NÃO foi carimbo

O `module-grades` reprovou com `-4 pts D6.a [P2] Inertia::defer em Controllers que renderizam
Inertia`. Havia três saídas: aplicar `defer` em qualquer prop pra satisfazer a métrica, pedir
o label `module-grades-allowed-regression`, ou rebaselinar.

**Nenhuma das três.** A regra da casa
([RUNBOOK-inertia-defer-pattern](../../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md))
se aplica **de verdade** a exatamente uma prop desta tela: `usuarios` (`User::forDropdown`) é a
única que **cresce com o tamanho do tenant**. As outras são um `value()` de coluna e dois
`first()` — deferi-las somaria um ida-e-volta pra economizar ~1ms, que é o oposto do objetivo.

Então `usuarios` virou `Inertia::defer` com skeleton, e as outras seguem eager. **Medido no
CT 100: D6 saiu de `5/10` para `9/10`** e o gap sumiu dos top-gaps — não por carimbo, por
consertar o que de fato estava errado.

**A consequência que o teste teve de acompanhar** — e é o detalhe que pega quem não conhece o
defer: prop deferida **não vem no primeiro render**. O `has('usuarios')` do `assertInertia`
passaria a medir a **ausência** dela, que é o comportamento *correto* do defer — o teste
reprovaria exatamente quando a otimização funciona. Assert removido, com o porquê no teste e
nota de método no `casos.md`. (Bens registrou a mesma armadilha em `_saida-06-bens.md`.)

## 9-quater · O merge do Painel (#7040) — conflito só no derivado

Durante esta thread o `main` recebeu o **Painel** (`Index.tsx`, thread 07). O merge deu **um**
conflito: `SUPERFICIE.md`.

**Derivado não se resolve à mão** — regenerei da árvore já mergeada
(`module-surface AssetManagement --write` → 112 arquivos, agora com Painel + Configurações) e
reconferi os **dois** modos do gate. Resolver conflito de arquivo gerado editando o texto é
congelar um snapshot contra um alvo móvel.

O `_shared/PatrimonioSubNav.tsx` **não mudou** no #7040 e a API que consumo (`active`,
`hidePrimary`) está idêntica — mesmo uso de Bens. O Painel usa sem `hidePrimary`, porque é ele
quem mostra o primary.

## 10 · Para a próxima thread da frente

1. **Escreva o charter ANTES do `.tsx`**, com `related_runbook` apontando pro RUNBOOK real. Sem
   isso o hook barra, e ele não tem override. (Já era o §1 de Bens; reconfirmado aqui.)
2. **Caminho flat**, não subpasta — mesmo que a sua ficha diga o contrário (§4).
3. **`Routes/web.php` provavelmente não precisa ser tocado** — confira se a rota já existe antes
   de criar.
4. **Se a sua ficha manda parar por dependência, re-fetch antes de concluir** (§0). A base
   envelhece sozinha em repo com worktrees compartilhados.
5. **`PAGES_NS` já está declarado** para as 7 telas; você só regenera o `SUPERFICIE.md`.
6. **Layout:** `<Grid>`/`<Stack>`/`<Inline>`, nunca `<div className="grid|flex …">`. Em
   formulário, `<Grid min="lg">` em vez de `sm:grid-cols-2`.
7. **Migrou um método pra Inertia?** O `@return` do docblock migra junto, ou o PHPStan reprova.
8. **Vai deferir uma prop?** Tire-a do `assertInertia` do primeiro render — senão o teste
   reprova quando o defer está funcionando.
9. **Link relativo em RUNBOOK de módulo** precisa de `../` pra alcançar `requisitos/_DesignSystem/`.
10. **Conflito em `SUPERFICIE.md` no merge?** Regenere, não edite — é derivado.
11. **`Preflight + contratos ativos` vermelho** costuma ser só *branch atrás*, não divergência
    de contrato. Leia a linha que disparou, acima da lista de causas no log.

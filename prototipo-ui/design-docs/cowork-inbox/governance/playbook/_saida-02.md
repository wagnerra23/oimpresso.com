---
sessao: "02"
titulo: "Rede E2E de governança — saída da thread"
autor: "[CL]"
criado: 2026-09-08
base: 0d159eb84a10
thread: 02-rede-e2e.md
ancora: "Policies.charter.md 3.675 B + Dashboard.charter.md 8.118 B — os charters, lidos inteiros. Dashboard.tsx (42.343 B), ModuleGradeService.php (91.289 B) e ScopedScorecardEvaluator.php (31.015 B) NÃO foram abertos: o que precisei do Dashboard.tsx saiu de sonda cirúrgica (rg/sed em faixa), não de leitura."
veredito: "entregue — 2 specs · 5 casos EXECUTAM · 1 skip condicional honesto · 4 fixme declarados · 3 achados, sendo 2 que contradizem o próprio playbook · D-GATE confirmado"
---

# _saída 02 · Rede E2E de governança

## Checklist de saída — item a item

### 1 ✅ os 2 arquivos

`e2e/governance-dashboard.spec.ts` (5.570 B) · `e2e/governance-policies.spec.ts` (5.381 B).
Nada fora do prefixo. `Pages/governance/**`, `Modules/Governance/**` e os 57 Feature tests: **intactos** — são o oráculo, não o alvo.

### 2 ✅ caso 1 (302 pra `/ia`) — EXECUTA

`page.request.get('/governance', { maxRedirects: 0 })` conferindo `status === 302` **e** o `location` contendo `/ia`.

**A segunda metade não é zelo, é o que separa o teste de um falso-verde:** sem autenticação a mesma rota devolve 302 para o `/login`. Um assert que olhasse só o status passaria com a sessão quebrada e não provaria nada.

### 3 ⚠️ caso 4 (auto-declarado) — a copy NÃO EXISTE

O checklist da thread pede "caso 4 verde". **Não entrego verde fabricado.** Medido em `origin/main`, 2026-09-08:

| sonda | resultado |
|---|---|
| `rg "auto-declarad" Dashboard.tsx` | **0** |
| `rg "pleno|parcia|régua" Dashboard.tsx` | 0 relevantes (só "ADRs pendentes", outro assunto) |
| o que a tela **de fato** renderiza (linhas 797-803) | rótulo `Compliance Constitution` · valor `{compliance_pct}%` · descrição `v1.1.0 — próx revisão {data}` |

O §5 item 3 do `00-INDICE.md` afirma: *"A UI rotula 'auto-declarado' e mostra a régua"*. **Está errado.** Hoje a tela exibe `80%` com rótulo neutro — exatamente como exibiria um número apurado, que é o que o caso 4 queria impedir.

Split entregue: o assert do que **existe** executa (rótulo + "próx revisão"); o assert do que o brief **pediu** é `test.fixme` com a medição colada dentro. Escrever a copy seria consertar a tela — e a thread manda observar.

**O `80%` não é fixado em assert, de propósito.** Pinar a constante transformaria uma decisão de régua ([W]) em teste vermelho — é a lápide §5 2026-08-24: não congelar em igualdade um número cuja mudança é decisão, não regressão.

### 4 ✅ caso 5 (sem modal) — EXECUTA, com `test.skip` honesto

O charter lista modal de confirmação como anti-padrão explícito. O teste roda, e pula com a razão escrita quando não há regra:

> **nenhum seeder popula `mcp_governance_rules`** — varredura no repo inteiro (`rg` em `database/seeders` + `Modules/*/Database/Seeders`; `rc=1` = rodou e não achou). A tabela **existe** no schema-squash do CI e nasce **vazia**, então a tela cai no `EmptyState` "Sem rules ainda" e não há `role="switch"` para clicar.

É o padrão que o README do diretório manda (`test.skip` explícito, **nunca falso-verde**) e é melhor que `fixme`: **ativa sozinho** no dia em que houver seed, sem ninguém precisar lembrar de voltar aqui.

A outra metade do caso 5 — o aviso "alternar não deixa rastro" — é o achado **A2** abaixo.

### 5 ✅ três execuções — sem flake

Todas na lane real (`e2e-gate.yml`: MySQL 8 + schema-squash + seed biz=1 + `artisan serve` + Chromium), **no mesmo SHA `6f08d5b992`**:

| # | run | veredito | contagem |
|---|---|---|---|
| 1 | [34273621266](https://github.com/wagnerra23/oimpresso.com/actions/runs/34273621266) | ✅ success | 17 passed · 0 failed |
| 2 | [34273978681](https://github.com/wagnerra23/oimpresso.com/actions/runs/34273978681) | ✅ success | 17 passed · 0 failed |
| 3 | [34274327467](https://github.com/wagnerra23/oimpresso.com/actions/runs/34274327467) | ✅ success | 17 passed · 0 failed |

Os 10 casos, por nome, no run 1 (os 3 runs são idênticos):

```
✓ 11 governance-dashboard.spec.ts:21 › a raiz /governance redireciona 302 pra /ia (386ms)
✓ 12 governance-dashboard.spec.ts:33 › /governance/dashboard renderiza o componente governance/Dashboard (2.1s)
✓ 13 governance-dashboard.spec.ts:75 › o painel resolve as props deferidas sem exceção de runtime (1.7s)
✓ 14 governance-dashboard.spec.ts:96 › o KPI de conformidade da Constituição aparece com a régua que a tela declara (1.6s)
-  15 governance-dashboard.spec.ts:110 › o número auto-declarado se apresenta como auto-declarado        [fixme A1]
✓ 16 governance-policies.spec.ts:21  › a tela de políticas abre com os 4 KPIs do MVP (1.4s)
-  17 governance-policies.spec.ts:35  › alternar uma política não abre modal de confirmação              [skip: sem regra]
-  18 governance-policies.spec.ts:57  › a tela avisa que alternar não deixa rastro                       [fixme A2]
-  19 governance-policies.spec.ts:69  › o toggle respeita o throttle de 10 por minuto                    [fixme A3]
-  20 governance-policies.spec.ts:79  › usuário sem governance.policies.edit não consegue alternar       [fixme D-GATE]
```

**Ler `0 failed` não bastaria** (LC-13: skip também sai com exit 0). Por isso o registro é por **nome de caso**: os 5 executáveis aparecem com `✓` e duração própria nos três runs — é execução provada, não ausência de vermelho.

**Custo honesto: 4 rodadas até o verde, todas por defeito MEU, todas da mesma classe** — eu afirmando o formato do DOM/HTML por suposição em vez de medir:

| rodada | run | o que eu supus | o que era |
|---|---|---|---|
| 1 | 34267782992 | `getByRole(name)` casa exato | casa por **substring** — 3 headings da tela contêm "Governança" |
| 2 | 34270197342 | o `data-page` fica no DOM | com Inertia 3.0.3 não sobrevive ao mount (o `h1` renderiza, o atributo não está lá) |
| 3 | 34271194228 | o HTML traz `governance/Dashboard` | `json_encode` **escapa a barra** |
| 4 | 34272343951 + 34273078141 | o payload vive num **atributo** | vive no **conteúdo** de `<script data-page="app" type="application/json">` — `data-page` é o id da raiz |

O que interrompeu o ciclo não foi tentar de novo: foi **embutir o recorte do HTML na mensagem do expect**. Uma rodada com diagnóstico desfez a premissa que três tentativas às cegas não tinham desfeito. Da rodada 3 em diante passei a **reproduzir a falha no scratchpad antes de gastar run** (com controle positivo *e* negativo) — que é o que eu devia ter feito desde o início.

**Ressalva de honestidade sobre o SHA:** os 3 runs são de `6f08d5b992`. O commit deste `_saida-02.md` vem depois, então o SHA final do PR difere por **um commit de documentação** — nenhum byte de spec, de app ou de config. Não re-rodei por isso; declarar vale mais que fingir que o SHA é o mesmo.

### 6 ⚠️ caso 7 — passou? NÃO roda: é D-GATE, conforme o "PARAR SE (c)"

Não é defeito do spec, e não é hipótese — são duas razões independentes, cada uma suficiente:

1. **`Gate::before`.** O `AuthServiceProvider` devolve `true` para a role `Admin#{business_id}` em qualquer ability fora de backup/superadmin/manage_modules. O `can:governance.policies.edit` barra o não-admin sem a permission, e **não barra o admin de um business**. Não é leitura minha: o próprio `Modules/Governance/Http/routes.php` declara em comentário, e o `GovernanceRotasCanGateTest` repete numa seção intitulada *"o que ele NÃO prova"*.
2. **A lane só tem um usuário.** O `e2e-gate.yml` loga por `E2E_BYPASS_LOGIN_ID=1` — o admin do `VisregTenantSeeder`, role `Admin#1`. O próprio workflow anota ao lado: *"role Admin#1 = todas as abilities via Gate::before"*.

Montar o negativo exigiria seed novo **ou** mexer no `AuthServiceProvider` — os dois fora do prefixo desta thread. Fica `test.fixme` com as duas razões escritas no corpo, para que a ausência seja **legível**, não silenciosa.

Passo 1 da **ADR 0392** (conflito A×B da CONCESSÃO), decisão **[W]** em aberto — thread 05, bloqueada. **Nada aqui a destrava.**

### 7 ✅ placar no PR

Os 2 specs foram no [PR #7057](https://github.com/wagnerra23/oimpresso.com/pull/7057), **mergeado por [W] em 2026-09-08 20:23** (commit `190ffd9396`) logo após o 3º run verde. Este `_saida` vai em PR próprio, com o placar colado no corpo.

Placar no `main` já com o #7057 e o #7054 dentro:

```
Governanca: entregue 2 de 5 · próximo 1 · em curso 1 · pendente 0 · bloqueada 1
  01 [feito    ] Descer o contrato de governanca pra contrato-cowork (estagio; cobre 5 de 9, declarado)
  02 [feito    ] Rede: 2 specs E2E (dashboard + policies toggle)
  03a [em curso ] casos.md de Policies — abre a frente do trio — Policies.casos.md (arquivo ausente)
  04 [proximo  ] Meu build esta 4 telas atras da producao — sem _saida
  05 [bloqueada] Gate::before deixa admin passar por qualquer can:
PRÓXIMO: 04 Meu build esta 4 telas atras da producao [CC]
```

*(O `03a [em curso]` é o falso-positivo que a errata de hoje §3 já diagnosticou: a prova-`guarda` do `DsRollout.casos.md` conta como evidência, e o arquivo já existia antes da thread. Não é sessão trabalhando nela.)*

---

## Os 3 achados

### A1 · o painel não diz que o número é auto-declarado

Detalhado no item 3. **Contradiz o §5 item 3 do índice**, que dá a rotulagem como existente.

A errata de hoje (§8) acrescenta o que fecha o quadro: a soma literal está em **dois** sites — `DashboardController.php:65` (`index()`) e `:268` (`buildKpisPayload()`). Quem promover a apurado e consertar "o" site deixa o outro — lápide §5 2026-08-02, o fix que pousa na cópia que o consumidor não usa.

### A2 · a tela de políticas não avisa que alternar não deixa rastro

`rg "rastro|históric"` em `Policies.tsx` **+** `Policies.charter.md` → **0 ocorrências**.

`mcp_governance_rule_history` não existe (§5 item 4 do índice; a citação no `PolicyToggleService` é docblock — *"deve futuramente virar INSERT... Fase 5+1"*, medido na errata). Ou seja: **alternar uma política de enforcement de runtime hoje é mudança sem trilha**, e a UI não conta isso a quem opera. O charter lista "toggle sem registrar histórico" como anti-padrão — o anti-padrão está em vigor e a tela é silenciosa sobre ele. `test.fixme` com a medição; a copy é decisão [W].

### A3 · `throttle:10,1` — o navegador é o oráculo errado

Provar no browser exigiria **11 POSTs reais de toggle** — 11 escritas numa tabela de enforcement — para medir uma propriedade que é do **registry de rotas**, e que o `GovernanceRotasCanGateTest` já lê do `gatherMiddleware()` sem efeito colateral nenhum. `fixme` por escolha de oráculo, declarada no arquivo. Duplicar aqui seria abrir um segundo dono do mesmo tema (§5 2026-07-09).

---

## Invariantes do §B — como cada um foi respeitado

| invariante | como |
|---|---|
| `mcp_*` são **cross-tenant por design** | **zero** assert de isolamento por empresa. Um spec que "provasse isolamento" aqui provaria o contrário do desenho (exceção formal ao Tier 0, Constituição Art. 6+8, `CrossTenantPolicyTest`) |
| zero escrita em `mcp_audit_log` | os specs **leem**. A única escrita possível em todo o PR é o POST de toggle em `mcp_governance_rules` — outra tabela, e hoje inalcançável (skip) |
| zero `waitForTimeout` | sincronismo é `waitForLoadState('networkidle')` — rede, não relógio |
| reusar `global-setup.ts` e a forma dos vivos | herdados do `playwright.config.ts` (`globalSetup` + `storageState`); forma espelhada de `sells-index` / `patrimonio-index` / `essentials-metas` |

---

## Decisões minhas, registradas (não são perguntas pro [W])

**Sem `UC-id` nos títulos.** O README manda o nome citar o `UC-id`. Governança **não tem `casos.md`** (é a frente 03; o único do módulo é `DsRollout`, o molde). Citar um `UC-GOV-01` seria fabricar âncora para um id que não existe. Medi a direção do guard antes de decidir: o `casos-coverage-guard.mjs` cobra **UC → teste** (*"UC NOVO no casos.md precisa de >=1 teste citando o id"*), não o inverso — então nomear sem id não avermelha nada, e inventar id sim seria dívida. Quando a 03a criar `Policies.casos.md`, os títulos ganham os ids. **É o handoff desta thread para a 03a.**

**Prova pela consequência no caso 3.** O anti-hook do charter proíbe consumir a prop `mcp` fora do `<Deferred>`. O `InertiaDeferAuditTest` já mede o **fonte do controller** (grep). Medir presença de novo aqui seria duplicar régua e medir o disco; o incidente de 2026-05-25 foi de **runtime** (`TypeError undefined.find` em prod). Então asserto `pageerror` vazio depois do `networkidle` — a consequência, que é o que o incidente produziu.

**Regex case-insensitive nos rótulos de KPI.** O `KpiCard` aplica `uppercase` por **CSS**, e o `innerText` do Chromium devolve o texto já transformado — um `getByText('Rules total', { exact: true })` seria flake esperando data. Medido no componente (`KpiCard.tsx:123-130`) antes de escrever.

---

## Correções de fato ao playbook (medidas, não opinadas)

1. **O `antes:` da thread 02 diz "17 specs".** A errata de hoje (§7b) corrige para "17 arquivos, 14 `.spec.ts`". **As duas estão desatualizadas**: `git ls-tree origin/main e2e/` dá **18 arquivos** e **15 `.spec.ts`** — o `jana-plataforma.spec.ts` entrou hoje. A afirmação que importa (**zero de governança**) seguia verdadeira nas três versões. Com este PR: **17 specs**.
2. **O §2-bis manda rodar `scripts/qa/placar-indice.mjs`** — que não existe (a errata §5a já pegou). O vivo é `prototipo-ui/design-docs/cowork-inbox/_scripts/placar-indice.mjs`, e foi o que rodei.
3. **O §5 item 3 do índice está errado** quanto à rotulagem "auto-declarado" — achado A1. É a única contradição de fato que encontrei entre o índice e o `main`; o resto do §0/§1 que cruzei bateu.

---

## Resíduo para o [W]

| # | o que | quem decide |
|---|---|---|
| 1 | **A1** — o painel apresenta um número auto-declarado como se fosse apurado. Rotular (e onde: KPI, tooltip, nota de rodapé) é decisão de produto | [W] |
| 2 | **A2** — a tela de políticas não avisa que alternar não deixa rastro, enquanto `mcp_governance_rule_history` não existir | [W] |
| 3 | **D-GATE** — enquanto o `Gate::before` devolver `true` para `Admin#{business_id}`, nenhum spec de negativa de permission é escrevível nesta lane. Passo 1 da ADR 0392 | [W] · thread 05 |
| 4 | **Seed de `mcp_governance_rules`** — sem uma regra semeada, o toggle (casos 5/6/7) é inalcançável por E2E. Não abri: seeder está fora do prefixo | [W] decide se vale |

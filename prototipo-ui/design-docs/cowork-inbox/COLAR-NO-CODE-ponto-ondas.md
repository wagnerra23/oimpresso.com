# COLAR NO CODE — módulo Ponto · plano revisado (5 ondas · 16 PRs)

> **De:** [CC] Claude Design (Cowork) · **Para:** [CL] Claude Code no `main` · **Data:** 2026-08-27
> **Base de fato:** leitura do `main` neste turno (árvore `50fd6006dbf4`): `resources/js/Pages/Ponto/**`,
> `memory/requisitos/Ponto/_STATUS-GENERATED.md`, `memory/governance/scorecards/screens/ponto-*.yaml`,
> `prototipo-ui/contrato/ponto-*.contract.json`, `prototipo-ui/design-docs/{HANDOFF,PLANO-PR-ONDAS}-ponto.md`.
> **Nada aqui está commitado** — as tools de GitHub do Cowork são read-only. Ponte = [W] colar 1× ou Issue `cowork-intake`.
> **Este documento SUPERSEDE `prototipo-ui/design-docs/PLANO-PR-ONDAS-ponto.md` (20/08).**

---

## Por que o plano de 20/08 caiu

O plano antigo (23 PRs, Ondas A–G) partia de "Ponto é Blade, precisa ser portado pra Inertia".
**Não é mais verdade.** O `main` hoje tem **20 telas `.tsx`** em `resources/js/Pages/Ponto/**`, todas em
`AppShellV2` + `@/Components/ui` + `_shared/PontoSubNav` (que lê `shell.menu` via `PageHeaderTabs`, hue 295):

```
Welcome · Dashboard/Index · Espelho/{Index,Show} · Aprovacoes/Index
Intercorrencias/{Index,Create,Show} · BancoHoras/{Index,Show} · Escalas/{Index,Form}
Colaboradores/{Index,Edit} · Importacoes/{Index,Create,Show} · Configuracoes/{Index,Reps} · Relatorios/Index
```

Consequência: **Ondas A, B, C e D do plano antigo (PR-01..PR-15) estão absorvidas pela produção.**
Frescor dessas telas = 🔵 **produção à frente — não repintar, puxar o vivo**.

O que sobra é: (1) sanear resíduos medidos, (2) fechar o trio, (3) as **3 telas que não existem**
(Fechamento, Conformidade, REP-P), (4) promover os contratos.

---

## Estado medido (o que NÃO precisa de PR novo, e o que precisa)

### ✅ Já vivo — não tocar sem motivo
- 20 telas em Inertia/React sobre `AppShellV2`, primitivos do DS (`Button`, `Card`, `Badge`, `Input`, `Select`, `Textarea`, `Alert`, `Skeleton`, `Dialog`, `AlertDialog`).
- SubNav canônico via `shell.menu` (não hardcode de abas).
- Charters: **20/20 telas têm `.charter.md`**.
- Contratos ancorados: `Dashboard/Index.tsx` (`painel-nota-fechamento`, `painel-kpis`, `painel-fila-aprovacoes`, `painel-atividade`) e `Espelho/Show.tsx` (`espelho-dados-colaborador`, `espelho-totais`, `espelho-modo-visao`, `espelho-apuracao-diaria`, `espelho-folha-impressao`).
- Backend: 12 controllers, 10 services, 10 entities, 8 migrations, 40 testes Pest (incl. `MultiTenantAppendOnlyTest`, `Wave28MobileMarcacaoTest`).

### 🟠 Resíduo medido — entra na Onda 1
| Onde | Fato | Fonte |
|---|---|---|
| `Espelho/Show.tsx:403` | `blue: 'text-blue-700 dark:text-blue-400'` + violet no HE — paleta crua de 6 tons | scorecard `ponto-espelho-show` (nota 83, preflight 72) |
| `Espelho/Show.tsx:336` | `text-violet-600` inline — **eu tinha omitido** | medido por [CL] 27/08 |
| `Configuracoes/Index.tsx:74` | `border-t-4 border-t-blue-500` — azul proibido | leitura direta |
| `Configuracoes/Index.tsx:104` | `border-t-violet-500` — **eu tinha omitido** | medido por [CL] 27/08 |
| `Espelho/Show.tsx:407` | `violet: 'text-violet-700 dark:text-violet-400'` — 5º site | **conferido no `main` por [CC]** 27/08 18:58Z, árvore `6c894d70d702` |
| `Espelho/Show` heatmap | divergência/atraso/falta sinalizados **só por cor** (amber) | scorecard a11y 74 |
| `Espelho/Show` dia-a-dia | 6 colunas com `overflow-x` — denso demais no touch do técnico | scorecard mobile_fit 74 |

### 🟠 Trio furado — entra na Onda 2 · **corrigido por [CL] 27/08 (medido, não derivado)**
**14/20 telas com `casos.md`. Faltam 6:**
`Welcome` · `Colaboradores/Index` · `Colaboradores/Edit` · `Configuracoes/Index` · `Configuracoes/Reps` · `Escalas/Index`.

Erros do meu rascunho, retificados:
- **`Dashboard/Index` NÃO falta** — tem `casos.md` com **7 UC**, o maior do módulo. Eu li o `_STATUS-GENERATED.md` (derivado, defasado) em vez de medir. L-42 com nome novo — minha culpa.
- **Nenhum `casos.md` do Ponto está sem UC.** O mínimo é 2. A linha "2 `Show.casos.md` sem UC" sai do plano.

**E o débito que eu não vi — é ele que importa:** `casos:report` mostra **18 UC do Ponto citados só em docblock/comentário (⛓)** — 3º maior do repo, atrás de Financeiro (33) e Cliente (21). Esses UC **nunca viram ✅ como estão**. Conserto = converter o teste pra `it('UC-XXX-NN · …')`, **não escrever mais `casos.md`**. Dívida de **comportamento**, não de presença (LC-11, a classe que mais reincide aqui).

E 2 US `done` sem contrato: `US-PONTO-001` (relógio web/REP-P), `US-PONTO-007` (multi-tenant Tier 0).

### 🔴 Rede de segurança: o Ponto está zerado — `screen-coverage:report`
```
Módulo     Telas  Charter  E2E  Score  VRT  L2
Ponto         20       20    0     20    0   0
```
**Zero E2E, zero a11y, zero baseline visual.** Único módulo grande zerado nas quatro (Financeiro 2/2, governance 5/5).
Isso **reordena a Onda 1**: A2 (a11y) e A3 (mobile-fit) mexem em UI **sem nenhuma rede embaixo** — não entram antes do baseline.

### ⚪ Não existe no `main` — Ondas 3 e 4
- **Fechamento da competência** (trilha de 4 passos, consolidar/fechar/reabrir) — não há `Pages/Ponto/Fechamento.tsx`.
- **Painel de Conformidade CLT** (Art. 66 · 71 · 59 · NSR Anexo I · jornada aberta · sem PIS).
- **REP-P**: app do colaborador + fila de validação do gestor (o Service `MobileMarcacaoService` e a API `Api/MobileMarcacaoController` **existem**; a tela não).
- Contratos `ponto-fechamento.contract.json` e `ponto-rep-p.contract.json` **não existem** no `main` (só painel e espelho).

Referência visual dessas 3 telas: `prototipo-ui/cowork/ponto-fechamento.jsx` e `ponto-mobile.jsx` (protótipo Cowork).

---

## Premissas de tamanho por PR (mantidas do plano antigo — elas funcionam)

- **1 assunto por PR.** Uma capacidade, ou uma peça de infra, nunca as duas.
- **≤ 8 arquivos tocados** e **≤ ~350 linhas de diff** (fora `.md`/`.json` de governança).
- **Migration nunca junto com UI.** Schema anda sozinho, com seed e teste.
- **Sem refactor de vizinho.** Arquivo fora do escopo que pede mudança vira PR próprio.
- **Verde nas lanes required** antes de seguir: `Casos-coverage · ratchet`, `Unit`, `ponto-pest`, `cowork-ssot-guard`, `prototipo-readiness`.
- Onde há **⛔ [W]**, o PR **não abre** antes da decisão — abrir é inventar lei.

Risco: 🟢 mecânico · 🟡 tem regra de domínio · 🔴 toca imutabilidade/multi-tenant/schema.

---

## Onda 1 · sanear o vivo (5 PRs, reordenada) — rede antes de UI

> **Mudou por quê:** `screen-coverage` do Ponto = 0 E2E · 0 a11y · 0 VRT. Mexer em heatmap e em tabela
> responsiva sem baseline visual é apostar. A rede entra primeiro; ela é barata e é reusada pelas Ondas 3 e 4.

### PR-A0 · Rede mínima: baseline VRT + 1 E2E de fumaça 🟢 — **NOVO, primeiro da fila**
- **Faz:** baseline visual de `Espelho/{Index,Show}` + `Dashboard/Index` e 1 E2E que abre as 3 telas com `ponto.access`.
- **Aceite:** `screen-coverage` do Ponto sai de `0/0/0`; baseline commitado; nenhuma mudança de produto no diff.
- **Por que agora:** é o gate de A2 e A3. Sem ele, "não mudou layout" é opinião.

### PR-A1 · Tokens: matar o azul cru do Ponto 🟢 — **FEITO local `162e1eef5c`, não pushado**
- **Feito:** mapa de tons de `Espelho/Show` vira `info|success|warning|destructive|primary` sobre tokens do DS, **com os nomes do union renomeados** — `tone="blue"` é o que convida o próximo a escrever azul. Escopo corrigido para pegar os irmãos violet.
- **Gates verdes:** `typecheck:baseline:check` Delta +0 · `ds:canon:check` · `casos:check` · `dominio:check` · diff 14↔14 (teste de identidade) · controle positivo provando que `border-t-info` não nasce morta.
- **⚠️ `Configuracoes/Index` ficou FORA e os 2 sites seguem armados.** O `block-mwart-violation` barra o arquivo: falta `memory/requisitos/Ponto/RUNBOOK-configuracoes.md` e a mensagem diz "não tem escape". Editar via `sed` **passa por baixo do hook** (é PreToolUse) — descoberto e revertido. Não repetir: `sed` não é caminho, é buraco.
- **Pendência:** push + PR (R10 — [W] decide).

### PR-A1b · `RUNBOOK-configuracoes.md` → desarma os 2 sites de `Configuracoes/Index` 🟢 — **NOVO**
- **Faz:** escreve o RUNBOOK que o `block-mwart-violation` exige e, no mesmo PR ou no seguinte, troca `border-t-blue-500` (:74) e `border-t-violet-500` (:104) por tokens.
- **Aceite:** guard roda **a favor** (sem `sed`, sem escape); 0 `blue-*`/`violet-*` em `Pages/Ponto/**`.
- **Depende de:** PR-A1.

### PR-A2 · A11y: sinal não-cor na divergência 🟢
- **Faz:** heatmap do mês e células de divergência/atraso/falta ganham **ícone + `aria-label`/texto**, não só o amber. `Espelho/Show` e `Espelho/Index`.
- **Aceite:** print/screenshot em greyscale continua legível; `a11y_wcag` sai de 74; nenhum novo nó só-ícone sem label.
- **Depende de:** PR-A0 (baseline) + PR-A1.

### PR-A3 · Mobile-fit do espelho dia-a-dia 🟡
- **Faz:** abaixo de `md`, a tabela de 6 colunas vira **lista de cartões por dia** (data + jornada + totais + badge de divergência); ≥44px de alvo. Desktop intocado.
- **Aceite:** `mobile_fit` sobe; contrato `espelho-apuracao-diaria` continua verde (âncora preservada nos dois modos).
- **Depende de:** PR-A2.

---

## Onda 2 · dívida de comportamento primeiro (3 PRs) — paralela à Onda 1

> **Mudou de natureza.** O plano mandava pagar dívida de **presença** (`casos.md` novos). O débito medido é de
> **comportamento**: 18 UC que existem e não provam nada. Ordem invertida — ⛓ antes de arquivo novo.

### PR-B1 · Desamarrar os 18 UC ⛓ — lote 1 (Espelho + Aprovações) 🟢 — **primeiro da onda**
- **Faz:** converte a citação de docblock/comentário para `it('UC-XXX-NN · …')` nos testes que já existem. **Zero teste novo, zero `casos.md` novo.**
- **Aceite:** os UC do lote saem de ⛓ e viram ✅ na lane; `Casos-coverage · ratchet` sobe; nenhuma assertion alterada (é renomeação de contrato, não de comportamento).

### PR-B2 · Desamarrar os 18 UC ⛓ — lote 2 (Intercorrências · BancoHoras · Importações · Escalas) 🟢
- **Aceite:** **0 UC ⛓ no Ponto**; o módulo sai do 3º lugar do repo nessa lista.
- **Depende de:** PR-B1.

### PR-B3 · `casos.md` das 6 telas sem trio + contrato das 2 US `done` 🟢
- **Faz:** `Welcome`, `Colaboradores/{Index,Edit}`, `Configuracoes/{Index,Reps}`, `Escalas/Index` — UC **do que já está implementado** (nada de UC órfão: `proibicoes §5`). Mais o UC que prova `US-PONTO-001` e `US-PONTO-007` (teste já existe — `MultiTenantIsolationTest`/`MultiTenantAppendOnlyTest`; falta o UC citá-lo).
- **Aceite:** 20/20 com `casos.md` **e** com UC que a lane executa; `casos-gate` G-2 sem órfão.
- **Depende de:** PR-B2. **Não abre antes** — escrever `casos.md` novo com 18 UC ⛓ em pé é dobrar a aposta do LC-11.

---

## Onda 3 · fechamento da competência (5 PRs) — ⛔ TRAVADA em [W]

**Bloqueio:** decisões 1–4 do `HANDOFF-ponto.md`. Sem elas nenhum PR desta onda abre.

> 1. **Estado da competência** (aberto/consolidado/fechado): tabela nova `ponto_competencias` ou derivado das apurações?
> 2. **Permissão**: `ponto.fechamento.manage` nova ou reusa `ponto.configuracoes.manage`?
> 3. **Exceções assinadas** na consolidação: onde persistem? bloqueiam a geração do AFD?
> 4. **Reabrir competência fechada**: existe com auditoria ou é definitivo?

### PR-C1 · ADR + schema do estado da competência 🔴 ⛔ [W]
- **Faz:** ADR + migration (`business_id`, competência, estado, exceções, autor, timestamps) + seed + policy. **Nenhuma tela.**
- **Aceite:** só schema e teste; teste de isolamento por `business_id`; `PontoHealthCommand` reconhece a tabela.

### PR-C2 · Fechamento — parte 1: shell + contrato + pré-checagem (leitura) 🟡
- **Faz:** `Pages/Ponto/Fechamento.tsx` com `AppShellV2` + `PontoSubNav active="fechamento"` + trilha de 4 passos **em leitura**, pré-checagem com grau e atalho pra tela do problema. Âncoras `data-contract`. Zero mutação.
- **Regra:** pré-checagem conta **só a competência selecionada** (UC-PTF-04).
- **Depende de:** PR-C1.

### PR-C3 · Fechamento — parte 2: consolidar / consolidar com exceções 🔴
- **Faz:** a mutação. Consolidar **carimba o que já existe** — não recalcula (UC-PTF-05); reapuração só via `ReapurarDiaJob`.
- **Aceite:** teste provando que consolidar não altera `ponto_apuracao_dia`; exceção exige motivo.
- **Depende de:** PR-C2.

### PR-C4 · Fechamento — parte 3: fechar / reabrir + auditoria 🔴
- **Faz:** fechar a competência (trava anulação e intercorrência) e reabrir conforme decisão 4.
- **Aceite:** UC-PTF-01/02 com teste; competência fechada desabilita o botão Anular no `Espelho/Show`; contrato `ponto-fechamento` verde.
- **Depende de:** PR-C3.

### PR-C5 · Painel de Conformidade CLT 🟡
- **Faz:** 6 verificações (Art. 66 · Art. 71 · Art. 59 · NSR Anexo I · jornada aberta · ativo sem PIS), caso a caso, cada apontamento citando **artigo + apurado + limite**.
- **Regra:** número sem lei não entra na tela. Artigo citado literal (`Art. 66 CLT`, `Portaria 671/2021 Anexo I`).
- **Aceite:** UC-PTF-07 com teste por regra (fixtures: almoço 35 min, HE 2h30, NSR fora de ordem, ativo sem PIS).
- **Depende de:** PR-C4.

---

## Onda 4 · REP-P (4 PRs) — paralela à Onda 3, ⛔ parcial em [W]

**Bloqueio parcial:** decisões 5 e 6 (GPS ruim persistente · copy da selfie LGPD Art. 9º). Se ficarem abertas, PR-D2 entra **sem** o caminho "bater mesmo assim" e com a copy atual.

### PR-D1 · API: fechar o contrato mobile 🔴
- **Faz:** rota Sanctum + escopo `ponto:marcar`, endpoint `pendentes-validacao`. O anti-cheat já está em `MobileMarcacaoService` — **não reescrever**, só expor.
- **Aceite:** 422 nos três anti-cheat (selfie < 100KB, accuracy > 500m, drift > 30s); geofence **sinaliza**, não recusa; log sem PII.
- **Sem tela.**

### PR-D2 · App do colaborador — bater ponto 🟡
- **Faz:** tela de marcação (alvos ≥44px), NSR **server-authoritative**.
- **Aceite:** nenhuma marcação com NSR fora de sequência; nada de `rand()`/mock na Page.
- **Depende de:** PR-D1.

### PR-D3 · App do colaborador — meu espelho + justificar 🟡
- **Faz:** espelho do próprio colaborador (read-only) + justificar → cria intercorrência **PENDENTE de verdade**.
- **Aceite:** teste de que a justificativa aparece na fila do gestor (`Aprovacoes/Index`).
- **Depende de:** PR-D2.

### PR-D4 · Fila de validação do gestor 🟡
- **Faz:** últimos 7 dias com NSR, device, lat/lng, precisão, hash truncado + validar/recusar.
- **Aceite:** recusar deixa o dia **em divergência** (não apaga marcação — append-only); contrato `ponto-rep-p` verde.
- **Depende de:** PR-D1 e PR-D3.

---

## Onda 5 · contratos (2 PRs) — fecha o módulo

### PR-E1 · Contratos `ponto-fechamento` + `ponto-rep-p` 🟢
- **Faz:** os 2 `.contract.json` que faltam em `prototipo-ui/contrato/` (declarando seções, copy literal e ordem) + âncoras `data-contract` conferidas nas telas novas.
- **Aceite:** `contrato-de-tela` (advisory) aponta só o que ainda não existe.
- **Depende de:** PR-C5 e PR-D4.

### PR-E2 · Promover `contrato-de-tela` a required para os 4 contratos do Ponto 🟢
- **Aceite:** os 4 (`painel`, `espelho`, `fechamento`, `rep-p`) verdes por **3 execuções seguidas** antes de promover.
- **Depende de:** PR-E1.

---

## Ordem enxuta

```
AGORA (sem trava):   1: A0 → A1(feito, aguarda push) → A1b → A2 → A3
                     2: B1 → B2 → B3            (paralela à Onda 1)
DEPOIS DE [W] 1-4:   3: C1 → C2 → C3 → C4 → C5
DEPOIS DE [W] 5-6:   4: D1 → D2 → D3 → D4       (paralela à Onda 3)
FECHA:               5: E1 → E2
```

**19 PRs** (16 + A0 + A1b + B3 desdobrado); 1 já feito local. 🔴 de imutabilidade/schema: C1, C3, C4, D1 — vão sozinhos, com o teste de append-only no próprio PR.

**Regra nova (custou um revert):** guard que barra arquivo é **pré-requisito a cumprir**, nunca obstáculo a driblar. `sed`/escrita direta que passa por baixo de hook PreToolUse **não é caminho** — se o guard barrou, o PR é o que ele pediu (ex: PR-A1b).

---

## Em quantas partes dividir cada tela (regra pro Code)

Fatiar por **capacidade fechada e testável**, nunca por arquivo (`.tsx` num PR, controller noutro).

| Arquétipo | Partes | Corte |
|---|---:|---|
| Lista simples, form, config (Escalas, Colaboradores, Configurações) | **1** | PR inteiro, não fatiar |
| Dashboard / índice com filtros (Painel, Espelho/Index, Importações/Index) | **2** | (a) props + shell + contrato + estados vazios · (b) corpo (KPI/tabela) + filtros em query string |
| Tela 🔴 (Espelho/Show, Fechamento, BancoHoras/Show, REP-P) | **3–4** | (a) shell + âncoras de contrato · (b) leitura (totais/tabela/grade) · (c) **mutação sozinha** + teste de imutabilidade no mesmo PR · (d) print/PDF ou a11y quando houver |

Sinal de que a fatia está errada: o PR passa de 8 arquivos, ou o teste de append-only fica num PR diferente da mutação que ele guarda.

---

## O que NÃO fazer em nenhum PR

- Recalcular apuração fora de `ReapurarDiaJob`
- `UPDATE`/`DELETE` em `ponto_marcacoes` ou em movimento de banco de horas — correção é **anulação + nova marcação**
- Filtro em session storage (é query string)
- Mock/`rand()` em controller ou Page
- CSS global novo, cor fora dos tokens, `blue-*`/hex cru
- Misturar migration com UI, ou duas telas no mesmo PR
- Repintar as 17 telas já vivas "pro padrão do protótipo" — elas **são** o padrão (frescor 🔵). Onda 1 é cirúrgica, não redesenho.
- Promessa de arquivo legal: AFD/AFDT/AEJ seguem 501 no vivo; o wizard marca `NAO_IMPLEMENTADO`, nunca 501 como sucesso

---

## Decisões pendentes de [W] — bloco pra responder

| # | Pergunta | Trava |
|---|---|---|
| 1 | Estado da competência: tabela `ponto_competencias` nova ou derivado das apurações? | Onda 3 inteira |
| 2 | Permissão do fechamento: `ponto.fechamento.manage` nova ou reusa `ponto.configuracoes.manage`? | PR-C1 |
| 3 | Exceções assinadas: onde persistem? bloqueiam AFD? | PR-C3 |
| 4 | Reabrir competência fechada: com auditoria ou definitivo? | PR-C4 |
| 5 | REP-P com GPS ruim: permitir "bater mesmo assim" com justificativa? | escopo do PR-D2 |
| 6 | Copy da selfie (LGPD Art. 9º): confirmar "guardamos só o código da imagem, nunca a foto"? | copy do PR-D2 |
| 7 | Relatórios legais: ordem de implementação AFD/AFDT/AEJ em `ReportService`? | fora destas ondas |

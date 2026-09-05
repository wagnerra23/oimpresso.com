# COLAR NO CODE — módulo Ponto · doc único (export encerrado · 17 PRs até fechar) · 2026-09-04

> **Resposta curta: 0 arquivos de export.** O build do Ponto **já está no `main`** e as **21 telas** vivas são o padrão (frescor 🔵) — exportar `.jsx` novo aqui seria repintura de tela viva, proibido. O que falta para **fechar o módulo** são **45 arquivos em 17 PRs**, e **27 deles estão travados em decisão de [W]**. Contagem no bloco 6.
> **Consolida e SUBSTITUI** `COLAR-NO-CODE-ponto-ondas.md` (27/08) e `COLAR-NO-CODE-ponto-FECHAMENTO-DO-MODULO.md` (28/08) — dois docs do mesmo módulo era o scatter que o protocolo proíbe (§2-ter). O segundo foi apagado neste ciclo; as 7 perguntas ⛔ [W] e o plano de ondas foram preservados abaixo, com os números **remedidos hoje**.
> **Ponte, não canon.** Não escrevo no git: desce por `cowork-inbox`/Issue → PR, ou [W] cola 1×.

---

> ## ⚠️ ERRATA [CL] — medida em 2026-09-04 contra `origin/main`, DEPOIS deste doc ser escrito
>
> Este documento chegou íntegro (é o vivo do Cowork, pousado sem transcrição — ADR 0389). O bloco
> abaixo **não altera o corpo dele**: registra o que o [CL] mediu no `main` e que **contradiz três
> números** que o corpo repete. O corpo fica como está — é fato datado do [CC]; a correção é datada
> também, e é esta. Onde os dois discordam, **vale a medição com comando**, e ela está aqui.
>
> O próprio bloco 8 do doc já avisava: *"o 18 ⛓ é de 28/08"* e *"**Não verifiquei** hoje: … `casos:report`"*.
> Esta errata é a leitura que faltava.
>
> | # | o corpo diz | medido em 2026-09-04 no `origin/main` | comando |
> |---|---|---|---|
> | 1 | `E2E · a11y · VRT … 0 · 0 · 0` | **VRT = 4 telas, 4 baselines** — `Ponto/Dashboard`, `Ponto/Espelho/Index`, `Ponto/Espelho/Show`, `Ponto/Configuracoes`, as 4 com `.snap` commitado em `tests/.pest/snapshots/Browser/CoreScreens/PixelBaselineTest/` (+1 estado isolado em `IsolatedStatesBaselineTest`) | `git show origin/main:tests/Browser/visreg-screens.json` · `git ls-tree -r --name-only origin/main -- tests/.pest/snapshots/` |
> | 2 | idem | **E2E = 1** — `/ponto` está em `tests/Browser/CoreScreens/AuthBridgeSmokeTest.php:87`, âncora `'Ponto eletrônico'`, permissão `ponto.access`. É 1 rota, não as 4 | `git show origin/main:tests/Browser/CoreScreens/AuthBridgeSmokeTest.php \| grep -n -i ponto` |
> | 3 | idem | **a11y = 4 telas, e deixou de ser 0 DEPOIS deste doc.** O `A11yAxeBrowserTest` deriva o dataset do próprio `visreg-screens.json` (`str_starts_with($tela['screen'], 'Ponto')`) e roda `assertNoAccessibilityIssues(level: 0)`. Ampliado pelo merge `e43b9c3772` (PR #6777, 2026-09-04) — o docblock do bloco registra que antes dele o `grep -ci ponto` no arquivo dava **0** | `git show origin/main:tests/Browser/CoreScreens/A11yAxeBrowserTest.php` · `git show e43b9c3772 --stat` |
> | 4 | `18 UC ⛓` (bloco 1 frente 2, bloco 6 PLACAR e DoD) | **10**, em 3 telas: `BancoHoras/Show` (3) · `Importacoes/Show` (4) · `Intercorrencias/Show` (3). Ponto é o 6º de 15 módulos, não o 3º | `npm run casos:report` → linha `⛓ 10 Ponto: UC-BHSHOW-01 …` |
> | 5 | `PARAR SE … NÃO criar harness paralelo` **e** `ARQUIVOS A EDITAR: tests/e2e/ponto-smoke.spec.ts (CRIAR)` | **as duas instruções se contradizem, e a 2ª é a errada.** `tests/e2e/` **não existe** (0 arquivos). O harness canônico de tela é **Pest 4 Browser** em `tests/Browser/` (23 arquivos), com auth-bridge `/_visreg-login/{id}?to=<rota>`. Playwright existe, mas em **`e2e/`** na raiz (13 specs, nenhum de Ponto). Criar `tests/e2e/ponto-smoke.spec.ts` seria o 2º padrão que o próprio `PARAR SE` proíbe | `git ls-tree -r --name-only origin/main \| grep -c "tests/e2e/"` → **0**; `… \| grep -c "tests/Browser/"` → **23** |
>
> **O que `⛓` significa** — e o corpo trata como dívida a pagar, o que superestima o custo: o bloco
> *TETO DE PROVA* do [`scripts/casos-coverage-guard.mjs`](../../scripts/casos-coverage-guard.mjs) diz,
> literal, que **não é violação, não entra no baseline e não muda exit code** (o modo relatório termina
> em `process.exit(0)` incondicional). O UC ⛓ é um cujo teste **roda e passa**; o que não chega ao
> manifesto é o título — método PHPUnit `test_…` vira nome humanizado sem hífen e nunca casa o regex
> `UC-XXX-NN`. Continua valendo a pena converter para `it('UC-XXX-NN · …')`; só não é lane vermelha.
>
> **Efeito no placar da frente 1.** A rede não está zerada: das 4 lanes do bloco 3 do DoD, **VRT e
> a11y estão cobertas nas 4 telas** e **E2E tem 1 de 4 rotas**. O que resta da frente 1 é *estender o
> smoke às 3 rotas que faltam* — dentro de `tests/Browser/`, reusando o auth-bridge. Isso muda a
> frente 1 de **2 arquivos novos** para **1 arquivo editado**, e o total de **45 → 44**. A frente 2
> segue com 6 arquivos (o trabalho é o mesmo; só o número de UC cai de 18 para 10).
>
> **O que esta errata NÃO toca:** as **6 decisões de [W]** do bloco RESÍDUO e as **27 arquivos**
> travados nelas. Frentes 5–7 seguem ⛔, intactas.

---

## Arquivos lidos no `main` NESTE turno (4 + 3 árvores)

| # | arquivo | o que me disse |
|---|---|---|
| 1 | **`Modules/Ponto/Http/routes.php`** | as rotas reais: `/ponto` (dashboard) · espelho `index/show/imprimir` · aprovações (`aprovar`, `rejeitar`, `lote`) · intercorrências (resource + `submeter`/`cancelar` + `ai-classify` com `throttle:10,1`) · banco-horas (`index/show/ajuste`) · escalas (resource) · importações (`index/novo/store/show/original`) · relatórios (`index`/`{chave}`) · colaboradores (`index/editar/update`) · configurações (`index`/`reps`/`reps.store`). **Não existe rota de fechamento nem de conformidade.** As 8 rotas de API do REP-P (`ponto/api/*`) são **`abort(501)`** — stub, não implementação |
| 2 | **`resources/js/Pages/Ponto/_shared/PontoSubNav.tsx`** | **a autoridade de navegação da produção não é a minha TabBar**: ela lê `primary`/`ghosts` da entry "Ponto" do `shell.menu` (Inertia shared prop via `LegacyMenuAdapter`) e renderiza `PageHeaderTabs` com `group="pessoas"`, `maxVisible={5}` e overflow `⋯ Mais`. Hue 295. Se o `shell.menu` não traz ghosts, **não renderiza nada** |
| 3 | árvore **`resources/js/Pages/Ponto/**` (62 arquivos)** | **21 telas** · **21 charters** · **15 `casos.md`** → faltam **6** (`Welcome`, `Colaboradores/Index`, `Colaboradores/Edit`, `Configuracoes/Index`, `Configuracoes/Reps`, `Escalas/Index`) · `_components/` (ActivityFeed, AlertInbox, MonthHeatmap, PresenceStrip) · **não existe** `Fechamento.tsx`, `Conformidade.tsx` nem nada de REP-P |
| 4 | árvore **`prototipo-ui/contrato/`** | **2 dos 4** contratos do Ponto: `ponto-painel.contract.json` e `ponto-espelho.contract.json`. `ponto-fechamento` e `ponto-rep-p` seguem inexistentes |
| 5 | árvore **`prototipo-ui/cowork/`** (filtro ponto) | **`export-ponto/` NÃO existe mais** — a dupe apontada em 28/08 (P0) **foi resolvida**; hoje há só os 7 arquivos na raiz (`ponto-data` 25.849 · `ponto-fechamento` 17.755 · `ponto-mobile` 17.802 · `ponto-page.css` 25.974 · `ponto-page.jsx` 34.104 · `ponto-telas` 66.169 · `ponto-ui` 5.900). **Não repetir o pedido de `git rm`** |
| 6 | árvore **`Modules/Ponto/**`** | 12 controllers · 10 entities · 10 services (incl. `ApuracaoService`, `BancoHorasService`, `MobileMarcacaoService`, `AfdParserService`, `IntercorrenciaAIClassifier`) · 8 migrations · 2 jobs (`ProcessarImportacaoAfdJob`, `ReapurarDiaJob`) · **16 testes Feature** (incl. `CrossTenantMarcacaoTest`, `EspelhoContratoTest`, `BancoHorasIndexContratoTest`, `DashboardDeferredContractTest`) |

**Ancoragem dupla:** alvo de layout = protótipo medido (bloco 3); âncora de implementação = os arquivos acima. **Onde a produção está à frente, não vira pedido** — e no Ponto ela está à frente em quase tudo.

---

## 0 · Leis que não se renegociam

1. **Marcação é append-only** (Portaria MTP 671/2021): `UPDATE`/`DELETE` em `ponto_marcacoes` e em movimento de banco de horas **nunca**. Correção = **anulação + nova marcação**.
2. **Apuração só recalcula em `ReapurarDiaJob`.** Consolidar **carimba** o que existe, não recalcula.
3. **Número sem lei não entra na tela**: apontamento de conformidade cita o artigo literal (`Art. 66 CLT`, `Art. 71`, `Art. 59`, `Portaria 671/2021 Anexo I`).
4. **As 21 telas vivas são o padrão** (🔵). Onda de saneamento é cirúrgica, nunca redesenho.
5. **Autoridade de navegação = `shell.menu` → `PontoSubNav` → `PageHeaderTabs`** (produção), **não** a minha TabBar. Meu protótipo tem 13 abas internas; a produção tem 5 visíveis + overflow. Isso é **divergência declarada**, e o dono é a produção.
6. **Token:** zero cor crua. Medido em 28/08 e não reaberto: `Pages/Ponto/**` com **0 ocorrências** de `blue-/violet-/indigo-/sky-/cyan-`. **Não reabrir a caça ao azul.**
7. **Guard que barra arquivo é pré-requisito, não obstáculo.** `sed`/escrita direta que passa por baixo de hook PreToolUse não é caminho (custou um revert em 27/08).
8. **Nada é "fechado" sem as 7 lanes verdes na mesma execução** (bloco 6).

---

## 1 · Ordem das ondas + âncora

| # | frente | estado hoje | âncora no `main` | trava |
|---|---|---|---|---|
| **1** | Rede mínima (VRT + E2E de fumaça) | **0 E2E · 0 a11y · 0 VRT** — único módulo grande zerado nas quatro ⚠️ **ERRATA [CL] 04/09: VRT 4/4 · a11y 4/4 · E2E 1 de 4 — ver bloco ERRATA** | `Pages/Ponto/{Espelho/Index,Espelho/Show,Dashboard/Index}.tsx` | 🟢 nenhuma |
| **2** | Dívida de comportamento: **18 UC ⛓** (citados só em docblock) ⚠️ **ERRATA [CL] 04/09: são 10, e ⛓ é advisory** | 3º maior do repo | `Modules/Ponto/Tests/Feature/**` (16 arquivos) | 🟢 nenhuma |
| **3** | a11y: sinal **não-cor** na divergência + mobile-fit do dia-a-dia | score a11y 74 · mobile_fit 74 | `Espelho/{Index,Show}.tsx` + `_components/MonthHeatmap.tsx` | 🟢 (depois da rede) |
| **4** | `casos.md` das 6 telas de cadastro/config | 15/21 | as 6 telas nomeadas no bloco anterior | 🟢 (só depois da onda 2 — LC-11) |
| **5** | **Fechamento da competência** | **não existe** (nem rota, nem Page) | `ApuracaoService` · `ponto_apuracao_dia` · `Espelho/Show.tsx` (botão Anular) | ⛔ **[W] 1–4** |
| **6** | **Painel de Conformidade CLT** | **não existe** | `ApuracaoService` + `Colaborador` (PIS) + escalas | ⛔ **[W] 1–4** (depende da 5) |
| **7** | **REP-P** (app do colaborador + fila do gestor) | **não existe**; API é `abort(501)` | `MobileMarcacaoService` (anti-cheat pronto) · `NsrService` | ⛔ **[W] 5–6** |
| **8** | Contratos `ponto-fechamento` + `ponto-rep-p` → `required` | 2 de 4 | `prototipo-ui/contrato/` | depende de 5–7 |

**Referência visual das 3 telas que faltam** (já no `main`, não re-exportar): `prototipo-ui/cowork/ponto-fechamento.jsx` · `ponto-mobile.jsx`.

---

## 1-bis · Instrução de execução (as duas primeiras, sem trava)

> ⚠️ **ERRATA [CL] 04/09 — o bloco `ONDA 1` abaixo está com o caminho errado e NÃO deve ser seguido
> como está.** `tests/e2e/` não existe no repo; o harness de tela é **Pest 4 Browser** em
> `tests/Browser/`, e o registry é `tests/Browser/visreg-screens.json`. Seguir a linha
> `ARQUIVOS A EDITAR` violaria o `PARAR SE` do próprio bloco ("NÃO criar harness paralelo").
> **A instrução corrigida:** as 4 telas do Ponto já estão no registry com baseline e a11y; o que falta
> é estender `AuthBridgeSmokeTest.php` das 3 rotas que ainda não abrem no smoke — 1 arquivo editado,
> zero arquivo novo, zero harness novo.

```
ONDA 1 — rede mínima (é o gate de toda mudança de UI)
  ARQUIVOS A EDITAR   : tests/e2e/ponto-smoke.spec.ts                (CRIAR)
                        configuração/baseline de VRT das 3 telas âncora (CRIAR)
  REUSAR (não recriar): o padrão de E2E/VRT que Financeiro (2/2) e governance (5/5) já usam
                        no repo — mesma config, mesma pasta; NÃO inventar harness novo
  CRIAR               : só o spec + baseline. Zero mudança de produto no diff.
  NÃO TOCAR           : as 21 telas (nenhuma linha de .tsx neste PR)
                        Modules/Ponto/** (nenhum controller/service)
  PASSO A PASSO       : 1) E2E abre /ponto, /ponto/espelho, /ponto/espelho/{id} com ponto.access
                        2) baseline visual das 3 telas em dark e 1280px
                        3) rodar 2× e conferir que o baseline é estável antes de commitar
  DADO                : nenhum novo.
  PARAR SE            : o harness de VRT do repo não cobrir Inertia autenticado → para e
                        pergunta; NÃO criar harness paralelo (seria 2º padrão no repo)

ONDA 2 — desamarrar os 18 UC ⛓
  ARQUIVOS A EDITAR   : Modules/Ponto/Tests/Feature/** (lote 1: Espelho + Aprovações ·
                        lote 2: Intercorrências · BancoHoras · Importações · Escalas)
  REUSAR              : os testes que JÁ existem — converter a citação de docblock para
                        it('UC-XXX-NN · …'). ZERO teste novo, ZERO assertion nova.
  NÃO TOCAR           : casos.md (a onda 4 é que escreve) · nenhuma tela · nenhum service
  PASSO A PASSO       : 1) lote 1 · 2) conferir a lane subir · 3) lote 2 · 4) 0 UC ⛓ no Ponto
  PARAR SE            : algum UC ⛓ não tiver teste correspondente → ele é órfão: NÃO inventar
                        assertion; declarar no PR e devolver pra onda 4
```

As ondas 3–8 abrem cada uma em **sessão limpa**, com a instrução escrita na hora (regra §2-quater) — não aqui.

---

## 2 · Onda 0a — a11y do ALVO (o que falhou foi corrigido AQUI)

Bateria no protótipo servido, dark, **após estabilizar** (T1: 901 nós no Painel · 997 Fechamento · 910 Conformidade · 991 REP-P — duas leituras iguais em cada).

| # | item | medido | veredito | ação |
|---|---|---|---|---|
| A1 | falso interativo | **0** — nenhum `DIV`/`SPAN` clicável sem `role`/`tabindex` nas 4 views medidas (T5 de sanidade passou: `BUTTON` com `cursor: pointer`) | ✅ | o Ponto é o módulo mais limpo que medi neste eixo |
| A3 | ícone sem nome | Painel/Fechamento/Conformidade **0 de 19–23** ✅ · **REP-P 3 de 23** (chrome do celular) | 🔴 → ✅ | **corrigido no build**: os 3 `svg` do `android-frame.jsx` (barra de status do aparelho, puramente decorativa) ganharam `aria-hidden="true"` |
| A5 | ARIA nas abas | **13 de 13** com estado | ✅ | — |
| A7 | alvo <24px | **1 de 24** | ⚪ | decisão [W] (a mesma dos outros módulos) |
| A10 | `aria-live` | **0 em todas as views** — e o módulo **escreve** (aprovar, rejeitar, anular, consolidar) | 🔴 → ✅ | **corrigido no build, com raio declarado:** `useAviso` de `modulo-padrao.jsx` só montava o nó **quando havia mensagem** — leitor de tela não anuncia região que não existia. Agora a região (`role="status" aria-live="polite" aria-atomic="true"`) **existe sempre** e o wrapper visual segue condicional (zero mudança de layout). **É arquivo compartilhado**: todos os módulos que usam `MP.useAviso` (CRM, Ponto, Repair…) ganham o anúncio — mudança aditiva, sem CSS |
| — | `th scope` | **0 de 7/6/8** em todas as views | 🔴 → ✅ | **corrigido no build**: **16 `th`** (`ponto-page.jsx` 15 + o átomo `Tabela` de `ponto-ui.jsx`, que serve `ponto-telas`/`fechamento`/`mobile`) agora têm `scope="col"` |
| — | campo sem rótulo | **0** nas 4 views | ✅ | — |
| — | tabelas | 100% minhas (`pt-tbl` / `table.dados`) — **nenhuma grade do DS aqui** | — | logo, `th scope` era dívida minha, não do DS (ao contrário de CRM/Repair/HRM) |

**Build alterado neste ciclo:** `ponto-page.jsx` · `ponto-ui.jsx` · `android-frame.jsx` · **`modulo-padrao.jsx`** (compartilhado) · `oimpresso.com.html` (bump `?v=`). Zero mudança de layout.

**Registro honesto:** na produção esse eixo já está resolvido de outra forma — `Pages/Essentials/Settings/Index.tsx` usa `toast` (sonner), que anuncia. A correção acima é do **protótipo**, para o alvo não exportar defeito.

---

## 3 · ALVO medido (as 3 telas que não existem no `main` — as únicas que valem alvo)

**Shell do protótipo:** `DIV.ponto-root.mp-page` (`data-screen-label="01 Ponto"`) → header `ModuloPadrao` + `NAV.ds-tabbar.jm-tabs` (**13 abas**, 13/13 com estado) + `.pt-body` + região de aviso.
**Divergência declarada:** a produção usa `PontoSubNav` (5 ghosts visíveis + `⋯ Mais`, alimentado por `shell.menu`). O alvo de layout do corpo é meu; **o alvo da navegação é o da produção**.

| tela | alvo medido |
|---|---|
| **Fechamento** (997 nós) | `.pt-body` com **5 seções nesta ordem**: `.pt-toolbar` (5 filhos) · `.pt-passos` (**4 passos** — a trilha) · `.pt-cols-2` (2) · `SECTION.pt-card` (2) · `.pt-legal` (1) · 2 tabelas `pt-tbl` (7 `th`) · 30 botões · 1 campo |
| **Conformidade** (910 nós) | **3 seções**: `.pt-nota.danger` (2) · `.pt-kpis` (**6 KPIs** = as 6 verificações: Art. 66 · 71 · 59 · NSR · jornada aberta · ativo sem PIS) · `SECTION.pt-card` (2) · 1 tabela `pt-tbl` (6 `th`) · 28 botões · 0 campos |
| **REP-P** (991 nós) | **2 seções**: `.pt-nota.info` (2) · `.ptm-wrap` (2 — o aparelho + a fila do gestor) · 1 tabela `pt-tbl` (8 `th`) · 36 botões |

---

## 4 · Comportamento + invariantes (o que já é lei nas telas vivas)

1. Toda correção de marcação é **anulação + nova marcação** (append-only) — nunca edição.
2. **Consolidar carimba, não recalcula** (UC-PTF-05); reapuração só via `ReapurarDiaJob`.
3. Competência **fechada** desabilita Anular no `Espelho/Show` e trava intercorrência.
4. Filtro vive em **query string**, nunca em session storage.
5. **NSR é server-authoritative** — nenhuma Page gera número de sequência.
6. Anti-cheat do REP-P **já existe** em `MobileMarcacaoService` (selfie < 100KB, accuracy > 500m, drift > 30s → 422; geofence **sinaliza**, não recusa) — expor, nunca reescrever.
7. Relatório legal não mente: AFD/AFDT/AEJ estão **501** no vivo; o wizard marca `NAO_IMPLEMENTADO` — 501 nunca é sucesso.
8. Sem `mock`/`rand()` em controller ou Page.

---

## 5 · Não inventar

- **Componentes:** `AppShellV2` + `@/Components/ui` + `_shared/PontoSubNav` + os 4 `_components` que já existem (`ActivityFeed`, `AlertInbox`, `MonthHeatmap`, `PresenceStrip`). Tela nova **reusa** esses.
- **Tokens:** `info|success|warning|destructive|primary` sobre tokens do DS. Zero `blue-*`/hex cru (a dívida foi paga — não reabrir).
- **Dados:** `ponto_apuracao_dia` · `ponto_marcacoes` · `ponto_banco_horas` · `ponto_intercorrencias` · `ponto_escalas` · `ponto_importacoes` · `ponto_reps` · `ponto_colaborador_config` (as 8 migrations lidas na árvore). Campo fora disso ⇒ `—` + linha no PR.
- **Copy legal:** artigo literal, sempre.

---

## 6 · DoD + PLACAR + **contagem de arquivos**

### PLACAR Ponto — 2026-09-04

```
Export de tela do protótipo ...... 0 de 0   (encerrado: build idêntico no main; 21 telas 🔵)
Telas vivas ...................... 21
Charters ......................... 21 / 21   ✅
casos.md ......................... 15 / 21   → faltam 6
UC ⛓ (só em docblock) ............ 18        → 0 é a meta      ⚠️ ERRATA [CL]: 10 (advisory)
Contratos ........................ 2 / 4     (painel, espelho)
E2E · a11y · VRT ................. 0 · 0 · 0 ← o bloqueio real do fechamento
                                             ⚠️ ERRATA [CL]: 1 · 4 · 4 — não é o bloqueio
Telas que não existem ............ 3         (Fechamento · Conformidade · REP-P) — ⛔ [W]
Dupe export-ponto/ ............... resolvida ✅ (medido hoje)
Paleta crua em Pages/Ponto/** .... 0 ✅ (não reabrir)
```

> **PLACAR remedido [CL] — 2026-09-04, `origin/main`** (o de cima é do [CC]; este é o que os comandos devolvem)
>
> ```
> VRT .............................. 4 / 4 telas  (registry + .snap commitado)
> a11y (axe level 0) ............... 4 / 4 telas  (via e43b9c3772 / PR #6777, hoje)
> E2E de fumaça .................... 1 / 4 rotas  (só /ponto, AuthBridgeSmokeTest:87)
> UC ⛓ ............................. 10           (advisory: não é violação, não muda exit code)
> Arquivos até fechar .............. 44           (era 45; a frente 1 caiu de 2 novos para 1 editado)
>   destravados ....................  17          (frentes 1–4)
>   travados em [W] ................  27          (frentes 5–7 — INALTERADO)
> ```

### Quantos arquivos o Code precisa (a resposta)

| frente | novos | editados | total | trava |
|---|---:|---:|---:|---|
| 1 · Rede mínima (1 spec E2E + 1 baseline/config VRT) ⚠️ **ERRATA [CL]: 0 novos + 1 editado = 1** (estender `AuthBridgeSmokeTest.php` às 3 rotas; VRT e a11y já cobertos) | 2 | 0 | **2** | 🟢 |
| 2 · Desamarrar 18 UC ⛓ (2 lotes) ⚠️ **ERRATA [CL]: 10 UC, mesmos 6 arquivos** | 0 | 6 | **6** | 🟢 |
| 3 · a11y não-cor + mobile-fit | 0 | 3 | **3** | 🟢 |
| 4 · `casos.md` das 6 telas | 6 | 0 | **6** | 🟢 |
| 5 · **Fechamento** (migration · policy · controller · Page · charter · casos · contrato · Pest · `routes.php`) | 8 | 1 | **9** | ⛔ [W] 1–4 |
| 6 · **Conformidade** (service · Page · charter · casos · Pest) | 5 | 0 | **5** | ⛔ [W] 1–4 |
| 7 · **REP-P** (API controller · 3 Pages · 3 charters · 3 casos · contrato · 2 Pest) | 13 | 0 | **13** | ⛔ [W] 5–6 |
| 8 · Promover os 4 contratos a `required` | 0 | 1 | **1** | depende de 5–7 |
| **total** | **34** | **11** | **45** ⚠️ **ERRATA [CL]: 44** | **27 travados** |

**45 arquivos em 17 PRs** (o plano de 27/08 tinha 19; **A1 e A1b já foram feitos** — medido em 28/08 e não reaberto). **18 arquivos destravados hoje** (frentes 1–4) e **27 travados** nas 6 decisões de [W].
**Margem declarada:** a frente 5 pode subir 2 arquivos se [W] decidir tabela nova `ponto_competencias` **com** tabela de exceções assinadas; e a frente 7 pode subir 1 se a fila do gestor virar 2 telas.

### DoD — "módulo Ponto fechado" (7 lanes verdes na MESMA execução)

| # | critério | como se mede | hoje | ⚠️ ERRATA [CL] 04/09 |
|---|---|---|---|---|
| 1 | Trio completo | `prototipo-readiness` — 21/21 com `.tsx` + charter + casos com UC | **15/21** | — |
| 2 | 0 UC ⛓ | `casos:report` | **18 ⛓** | **10 ⛓**, e a lane **não fica vermelha** por eles |
| 3 | Baseline visual | VRT das telas âncora commitado | **0** | **4 de 4** ✅ |
| 4 | E2E de fumaça | as telas abrem com `ponto.access` | **0** | **1 de 4** rotas |
| 5 | a11y | nenhum sinal só-por-cor; nó só-ícone com label | score **74** | axe level 0 cobrindo **4 de 4** ✅ |
| 6 | Contratos required | os 4 verdes **3× seguidas** | **2 existem** | — |
| 7 | Sem paleta crua e sem dupe em `cowork/` | `ds:canon:check` + `cowork-ssot-guard` | ✅ token · ✅ dupe (**resolvida**) | — |

Lanes 1–5 e 7 **não dependem de [W]**. A lane 6 depende das frentes 5–7.

**Premissas de tamanho (mantidas):** 1 assunto por PR · ≤8 arquivos · ≤~350 linhas de diff · migration nunca com UI · a mutação e o teste de append-only **no mesmo PR**.

---

## 7 · O que a ancoragem NÃO resolve

| # | item | natureza | dono |
|---|---|---|---|
| 1 | **As 6 decisões que travam 27 dos 45 arquivos** (bloco RESÍDUO) | decisão de lei/domínio | **[W]** |
| 2 | **API do REP-P é `abort(501)`** em 8 rotas — não é "quase pronto": é stub. O anti-cheat existe no Service, a exposição não | superfície inexistente | frente 7 |
| 3 | **Rede zerada é o bloqueio real:** sem VRT/E2E, "não mudei layout" é opinião — e este é o módulo com obrigação legal (Portaria 671/2021) ⚠️ **ERRATA [CL] 04/09: a rede não está zerada — VRT 4/4 e a11y 4/4; falta E2E em 3 rotas. O bloqueio real são as 6 decisões da linha 1** | verificação ausente | frente 1 (destravada) |
| 4 | **Navegação divergente por decisão:** meu protótipo tem 13 abas internas; a produção deriva 5 + overflow do `shell.menu`. Tela nova **entra pelo `shell.menu`**, não recria TabBar | divergência declarada | frente 5–7 |
| 5 | **`_STATUS-GENERATED.md` é derivado e já esteve defasado** — foi a origem do erro de 27/08. Não ler como fato (é a minha própria L-42) | armadilha de método | — |
| 6 | **Relatórios legais (AFD/AFDT/AEJ)** — ordem de implementação em `ReportService` fora destas frentes | escopo | [W] (pergunta 7) |
| 7 | **`modulo-padrao.jsx` é compartilhado:** a correção de `aria-live` deste ciclo beneficia todos os módulos do protótipo, mas **é fundação** — se algo regredir num módulo que eu não medi hoje, a causa é aqui | raio declarado | eu (protótipo) |
| 8 | Zero `<main>` no documento (AP9) · rota do `app.jsx` sem componente (C6) | fundação / cobertura declarada | fundação |

---

## 8 · Não medido, declarado

- **Não verifiquei** o conteúdo de nenhuma das 21 telas vivas neste turno (só a árvore e o `PontoSubNav`). Os fatos de UI que repito — 0 paleta crua, a11y 74, mobile_fit 74, os 5 sites de azul corrigidos — vêm da **medição de 27–28/08**, não de hoje. Se algum PR mexeu neles desde então, meu número está velho.
- **Não verifiquei** hoje: `DashboardController` (21 KB) · `EspelhoController` · `IntercorrenciaController` · `AprovacaoController` · `ApuracaoService` (19 KB) · `MobileMarcacaoService` · `AfdParserService` · os 16 testes · `memory/requisitos/Ponto/**` (26 arquivos, incl. `SDD-espelho-e-jornada-v1.0.md` e `AUDIT-SENIOR`) · os 13 scorecards `ponto-*.yaml` · `casos:report` (o 18 ⛓ é de 28/08).
- **Contraste (A8):** não medido (exige OKLCH→sRGB com caso de sanidade).
- **Largura:** medido em ~841px (janela do preview), não em 1280px.
- **A contagem de arquivos das frentes 5–7 é estimativa de plano**, não leitura: nenhuma dessas telas existe para eu medir o diff real.

---

## 9 · Recibo

- **Build alterado (só a11y):** `ponto-page.jsx` · `ponto-ui.jsx` · `android-frame.jsx` · `modulo-padrao.jsx` (compartilhado) · `oimpresso.com.html`.
- **Ponte:** este arquivo — **doc único do Ponto**. `COLAR-NO-CODE-ponto-FECHAMENTO-DO-MODULO.md` foi **apagado** neste ciclo (conteúdo absorvido acima).
- **Charter/casos:** nada a destilar — as telas vivas são canon e as 3 que faltam nascem com charter no PR delas.
- **Pacote (regra de saída):** **não regenerado** — o gerador exige os arquivos em disco e não roda do meu lado (ADR 0374). O ciclo fecha **sem pacote**:

  ```
  node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
  ```

---

## RESÍDUO Ponto — as 6+1 decisões de [W] (travam 27 arquivos)

| # | pergunta | trava |
|---|---|---|
| 1 | **Estado da competência**: tabela nova `ponto_competencias` ou derivado das apurações? | frente 5 inteira |
| 2 | **Permissão do fechamento**: `ponto.fechamento.manage` nova ou reusa `ponto.configuracoes.manage`? | migration/policy |
| 3 | **Exceções assinadas**: onde persistem? bloqueiam a geração do AFD? | mutação do fechamento |
| 4 | **Reabrir competência fechada**: com auditoria ou definitivo? | fechar/reabrir |
| 5 | **REP-P com GPS ruim**: permitir "bater mesmo assim" com justificativa? | escopo da tela de marcação |
| 6 | **Copy da selfie (LGPD Art. 9º)**: confirmar "guardamos só o código da imagem, nunca a foto"? | copy da tela de marcação |
| 7 | **Relatórios legais**: ordem de AFD/AFDT/AEJ em `ReportService`? | fora destas frentes |

Sem 1–4, **nenhum arquivo da frente 5 abre** — abrir é inventar lei.
Enquanto isso, **as frentes 1–4 (18 arquivos) podem começar hoje**.

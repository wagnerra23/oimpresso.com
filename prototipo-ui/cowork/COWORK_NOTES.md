# COWORK_NOTES — Mensagens do Cowork pro Claude Code

> O **Cowork** anexa aqui pedidos, decisões e contexto que o **Claude Code** precisa saber na próxima sync.
> O Claude Code, ao processar uma sync, **lê este arquivo, age conforme, e marca cada item como [PROCESSADO YYYY-MM-DD] no final**.
> Mensagens muito antigas processadas vão pro fim do arquivo em "Histórico".

---

## 📥 Pendentes

> 📡 **NOVO 2026-06-18 (revisado) → [CL] · CAIXA UNIFICADA — saúde de canal: fechar Onda 2 + Onda 3 (banner + backend JÁ landaram):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_CHANNEL-HEALTH-BANNER.md` — **AUTO-CONTIDO, código embutido, sem URL.**
> **✓lido @main `e2752b3b8ddb` (2026-06-18, [CC] — arquivos abertos):** o **banner JÁ EXISTE** (`_components/ChannelHealthBanner.tsx`) alimentado pela prop eager `unhealthyChannels` (cron `whatsmeow:health-probe`, incidente **US-WA-308**) — o agregado de backend (ex-"Onda 4") também landou. Estados REAIS = `disconnected`/`banned` (err) + `degraded` (warn); **não existe `down`** (o handoff antigo estava errado). A Onda 1 + 4 do plano antigo estão FEITAS.
> **Sobra (medido):** **O2** = `ComposerV4.tsx` ignora `channel_health` (dá pra "enviar" em canal caído) + sem marcador na thread; **O3** = `ChannelsDrawer.tsx` sem Reconectar explícito **e** `HEALTH_LABELS` stale (não conhece `disconnected`/`banned`). Handoff reescrito só com O2/O3 contra o shape real (helper partilhado em `helpers.ts`, `CaixaUnifThread.channel_health`).
> **§10.4:** validar vs `main`; tokens semânticos `warning`/`destructive` (NÃO oklch cru); não cunhar ADR; **não** tocar no banner/backend já live.


> 🧭 **NOVO 2026-06-16 → [CL] · HEADER + PAGEHEADER — convergir telas ao `<PageHeader>` canon (EM ONDAS · [W] decidiu "padrão do git, um só"):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_HEADER-PAGEHEADER-CONVERGENCIA.md` — **AUTO-CONTIDO, sem URL** (o componente `<PageHeader>`/`<PageHeaderPrimary>` já existe no `main`; é só adoção).
> ✅ **[ENCERRADO — verificado @main a273254628df 06-18, [CC]] Header Financeiro 100% canon.** `Dashboard`, `Dre`, `ContasPagar` **e `Unificado`** importam `@/Components/PageHeader`. O item "migrar Unificado" foi **erro C5 do [CC]** (afirmei `shared/PageHeader` deprecated sem abrir o arquivo — ele já estava no canon). Nada a fazer; sem handoff.
> **§10.4:** validar vs `main`; não cunhar ADR. **Espera [W]:** peso H1 600×700 (amendment v3.9, Tier 0) — não codar.

> 🛡️ **NOVO 2026-06-16 → [CL] · GUARD DA REINCIDÊNCIA — classes de erro recorrentes viram trava no git ([W]: "toda regra mora no git como fonte da verdade"):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_HANDOFF-INTEGRITY-GATE.md` — **AUTO-CONTIDO, código embutido, sem URL.** Origem: `O Cacador de Reincidencia.html` + `O Adversario do Protocolo.html` (red-team [CC] aprovado [W] 06-16).
> **verificado vs main @5489c90 (2026-06-16):** `cowork-inbox.py` só move arquivo, NÃO checa integridade de fila — guard não existe no repo. Novo confirmado.
> **O quê:** Onda 1 = tabela das 6 classes C1–C6 + regra-texto no `PROCESSO_MEMORIA_CC.md`. Onda 2 = guard CI mecanizando C3 (cabeçalho de bloco bem-formado) + C4 (órfão/ref-morta — já no `memory-health` CHECK 8, rodado: 18 órfãos + 19 refs mortas) + C5 (carimbo `verificado vs main` obrigatório em item ativo). **Estender** a suíte (NÃO criar paralelo — confirmar home vs `cowork-inbox.py`/`governance-gate`/`scripts/*-guard.mjs`).
> **§10.4:** estender-não-reinventar (Regra 7); ratchet/baseline + auto-teste controle-negativo como os outros guards; Tier 0 só se virar ADR (regra de processo = [W] numera).

<!-- ━━━━━━━━━━ LINHA D'ÁGUA (F4) · 2026-06-16 · O Code NÃO lê abaixo daqui ━━━━━━━━━━
     Tudo abaixo = PROCESSADO/landado (confirmado vs CODE_NOTES@main) ou histórico.
     Citações de PROMPT_PARA_CODE_*.md abaixo apontam pra arquivos já deletados (processados)
     — são REF MORTA tolerada como baseline no memory-health CHECK 8, NÃO tarefa ativa.
     Item processado DESCE pra cá; item novo NASCE acima. ━━━━━━━━━━ -->

> ✅ **[JÁ APLICADO @main 2026-06-16 — verificado @e67b0684 06-18 — NÃO reenviar] CAIXA UNIFICADA filtros em 2 botões.** `Index.tsx@main` cabeçalho diz *"faixa horizontal removida — Onda 1/2 2026-06-16"*; `ChannelChipsRow` não é mais importada/renderizada; power-filters (`within24h`/`unlinked`/`mediaInbound24h`/`inboundAging`/`orderBy`/`activeTagIds`/`activeTagIds`) passam pro `ConversationListV4`. Handoff `PROMPT_PARA_CODE_CAIXA-FILTROS-2BOTOES.md` consumido.

> ✅ **[JÁ APLICADO @main 2026-06-16 — NÃO reenviar] CAIXA UNIFICADA dark-mode.** Verificado vs `ConversationThreadV4.tsx@main`: já é dark-aware (ADR 0281) — bolha inbound `bg-card` (não `bg-white`), read-receipt `oklch(0.55 0.18 250)` (não `text-blue-600`), nota interna/banner `warning-soft`+`text-foreground`, fundo `bg-[oklch…] dark:bg-muted/15`. O handoff `PROMPT_PARA_CODE_CAIXA-UNIFICADA-DARK-MODE.md` foi consumido. ⚠ Resíduo a checar caso-a-caso (NÃO o pacote todo): empty-state Customer-360 + marcar canal ativo nos chips.

> 💜 **NOVO 2026-06-10 → [CL] · PACOTE FINANCEIRO F2-APROVADO (4 PRs — supersede e ABSORVE a entrada US-FIN-029 de 06-09 abaixo):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_PACOTE-FINANCEIRO-F2.md` (URLs públicas embutidas, ~1h — regenerar no Cowork se expirar). **F2:** [W] aprovou 2026-06-10 ("aprovado"); type ramp = Tier 0 autorizado [W] ("vai").
> **O quê:** PR-1 US-FIN-029 3 lentes (spec de 06-09, F1 agora implementado no protótipo) · PR-2 tela Impostos & obrigações (fiscal-no-financeiro) · PR-3 drawer Unificado hierarquia 3 camadas/densidade + token fixes (validar #2209 no main antes) · PR-4 type ramp `--fs-1..9` na fundação do DS (foundation-guard ①, alinhar c/ primitivo Text ADR 0253, gate por extensão do conformance — Regra 7). Ordem: 4→1→3→2.
> **§10.4:** validar contra main fresco; não refazer o que já landou; não cunhar ADR.

> 💜 **NOVO 2026-06-09 → [CL] · US-FIN-029 — Unificado "3 lentes" no header (P0 design Financeiro, direção [W] 2026-05-31):** *(absorvida pelo pacote acima — usar o prompt do pacote)*
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_US-FIN-029-3-LENTES.md` (URLs públicas embutidas: MWART pronto + referência F1 `financeiro-page.jsx`). **Tipo:** feature UI `.tsx`-only · **Prioridade:** P0 · **NÃO-Tier-0** → merge autônomo com CI verde + MWART + screenshots @1280/@1440.
> **O quê:** substituir a fileira de ~7 botões do header do `Unificado/Index.tsx` (anti-pattern reprovado [W], charter v13) por segmented **Caixa · A receber · A pagar** (`?lente=`, clamp caixa, chips refinam dentro da lente, KPI-click seta lente) + `+ Novo título` + menu `···`; extrair `<FinModuleTopnav>` (DRE usa sem lentes — gatilho US-FIN-TOPNAV-COMPONENT); charter → v14 (US-FIN-029 Backlog→Goals); Pest `UnificadoLentesGuardTest`; commitar o MWART `memory/requisitos/Financeiro/unificado-3-lentes-visual-comparison.md`. `FinSubNav` NÃO tocar (já existe). Gates simulados [CC] (L-33): zero token/css/cor-crua novos.
> **Origem:** `Reavaliacao Financeiro - 2026-06-09.html` (rubrica 7,6→8,3 vs @main 076c546; US-FIN-029 = única direção aprovada parada na fila). §10.4: validar contra main fresco; não refazer o que já landou.

> 🛡️ **NOVO 2026-06-08 → [CL] · ESLint `ds/*` · fechar buraco de cor crua em `.tsx` (escopo encolhido pós-anti-reinvenção):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_DS-LINT-TSX-COR-CRUA.md` (✓ ancorado em `eslint.config.js`/`package.json`/`conformance-gate.mjs`/`foundation-guard.mjs`@main). **CORREÇÃO:** o pacote "4 travas" anterior reinventava guardas que JÁ EXISTEM — `conformance-gate` (hue `--accent` roxo) + `foundation-guard` (token-def allowlist) cobrem identidade CSS; `pageheader:guard` cobre PageHeader dup. **Único buraco real:** paleta crua `.tsx` (`bg-blue-100`/`bg-green-100`/`bg-purple-100`) + `style={{oklch}}` inline, que os gates de CSS não veem e o `ds/*` não pega.
> **Entrega:** estende `ds/no-raw-palette` + `ds/no-inline-color-style` no bloco `no-restricted-syntax`; auto-teste (controle-negativo, padrão do projeto); smoke `_SmokeDrift` 🔴→🟢; audit `storage/ds-audit-tsx-cores.txt`; normaliza Financeiro; re-baseline `npm run lint:baseline:write`.
> **Anti-reinvenção (NÃO fazer):** não criar guarda de `--accent` no CSS (existe) · não criar `no-restricted-imports` de PageHeader (existe `pageheader:guard`) · `warn`+baseline (não `error`) · não tocar cálculo/roxo. Controle-negativo: `lint`+`conformance:check`+`foundation:check` verdes.

> ⚠️ **VOID 2026-06-08 — handoff Sells azul→roxo CANCELADO.** Eu havia lido a cópia LOCAL stale (`resources/css/sells-cowork.css` do Cowork = azul 220). O `sells-cowork.css@main` REAL **já está roxo `oklch(0.55 0.15 295)`** (✓ lido @main). Idem `cowork-canon-financeiro-bundle.css@main` (roxo). **Não há o que corrigir no git pra Sells/Financeiro.** Único island git confirmado = **Compras** (handoff abaixo).

> ⚠️ **VOID 2026-06-08 — handoff Compras hex→token CANCELADO (já feito no git).** Releitura ao vivo `cowork-compras-bundle.css@main`: cabeçalho diz *"Chrome = roxo canon var(--accent); paleta aliasada ao DS — Mapa Identidade ERP Fase 2 (ADR 0190/0235)"* e o corpo é `--cmp-accent:var(--accent)` + todos `--cmp-* : var(--*)`. **O navy `#1f3a5f` / papel-quente `#f6f4ef` NÃO existem mais no @main** — a migração já landou. Artefato `PROMPT_PARA_CODE_COMPRAS-HEX-PARA-TOKEN.md` = histórico. **Lição (3ª vez na sessão):** afirmar estado do repo sem reler @main no momento = mentira. Compras estava na fila por leitura stale.

> 🔧 **NOVO 2026-06-08 → [CL] · OFICINA AUTO — SÓ FASE 2 (convergência já landou #2417):**
> **SYNC (✓ `Index.charter.md@main` v4):** a convergência caçamba→reparo **já foi mergeada (PR #2417, 20/20 verde)**. **PR1 = PRONTO, não refazer.** Resta só a **Fase 2 (frontend):** D-04 (borda SLA) · D-05 (KPI filtra) · D-06 (persistir foco/visão) · D-07 (atalhos) — aprovados [W], já no gabarito `oficina-page.jsx`, ⬜/💡 no inventário do charter git. Artefato: `PROMPT_PARA_CODE_OFICINA-CONVERGE-CACAMBA-REPARO.md` (banner SYNC no topo aponta o que resta).
> **Guardrails:** editar `Index.tsx` no lugar + reusar componentes existentes (`ServiceOrderRichSheet`/`KanbanDndProvider`/`KpiCard`) — zero componente paralelo. Respeitar ESLint `ds/no-arbitrary-color`·`no-rounded-xl`·`react-hooks/exhaustive-deps`·`jsx-a11y` (KPI clicável = `<button>`). **Drawer travado (11 seções) intacto.** **Fora de escopo (Tier 0 backend, ADR própria):** keys FSM/DB Martinho + filtro funcional box/elevador. Controle-negativo 2 lados no aceite.

> 🏷️ **NOVO 2026-06-09 → [CL] · OFICINA — LIMPEZA DOS RESÍDUOS VISÍVEIS DE "LOCAÇÃO" (cosmético, NÃO-Tier-0 · [W] "ainda tem informação de locação presente"):**
> **SYNC (✓ lido @main 2026-06-09):** a UI do kanban já é reparo (PR #2417 v4), mas sobraram rótulos/nomes "caçamba/locação" na casca:
> 1. **`Modules/OficinaAuto/Resources/menus/topnav.php`** — item `'label' => 'Caçambas'` (→ `/oficina-auto/veiculos`) é o resíduo VISÍVEL no topnav. Renomear pra **'Veículos'** (a rota é o CRUD de veículos; "Caçambas" = nome comercial do Martinho, não rótulo de menu — ADR 0194 lápide). Confirmar com [W] se prefere 'Veículos' ou 'Caminhões'.
> 2. **`Modules/OficinaAuto/Routes/web.php`** — comentário stale da rota `producao-oficina` ("Kanban estado das caçambas… 5 colunas disponivel/locada/aguardando/manutencao/pronta"): reescrever pra descrever o fluxo de reparo real (a tela já convergiu na #2417; só o comentário ficou).
> 3. **(higiene opcional, mecânico — só se entrar limpo)** renomear `ProducaoOficina/_components/CacambaCard.tsx` e `CacambaKanbanColumn.tsx` → nomes de reparo + ajustar imports. Não bloqueia.
> **Fora de escopo (Tier 0, ADR própria — a locação PROFUNDA, decisão [W]):** keys FSM/seeder/DB `cacamba_locacao`/`disponivel·locada` rodam LIVE no Martinho biz=164 (ADR 0194/0093). NÃO tocar sem [W] + ADR. §10.4 Passo 0 vs origin/main. Controle-negativo 2 lados no aceite.

> 🧹 **NOVO 2026-06-04 → [CL] · FAXINA + RENAME DE NOMES (Cowork · [W] "retirar conflitos / padrão melhor / informe as mudanças"):**
> **Tudo já feito + testado no Cowork (host render limpo, exports `window.*` verificados). [CL] espelha no mirror `prototipo-ui/` pra manter Cowork=git:**
> 1. **Rename de módulos** (arquivo movido + `<script src>` do host atualizado; `window.*` export INALTERADO, então `app.jsx` não muda): `financeiro-app.jsx`→`financeiro-page.jsx` · `boleto-contas-app.jsx`→`boletos-page.jsx` · `prod-page.jsx`→`produtos-page.jsx` (desambígua de `producao-page.jsx`) · `inbox-v2-{extras,ai,cur,out}.jsx`→`inbox-{extras,ai,cur,out}.jsx`. **Padrão canônico:** `<modulo>-page.jsx` + `<modulo>-data.jsx` + `<modulo>.css` (matar sufixos `-app` e `-v2`).
> 2. **11 HTMLs de exploração/benchmark/mobile/legado** movidos da raiz → `_arquivo/exploracoes-2026-06-04/` (+ INDEX-lápide). Nenhum era carregado pelo host. Lista no INDEX.
> 3. **`tokens.css`+`design-system.css` v4 da raiz DELETADOS** (host carrega `ds-v6/tokens.css`; eram duplicata morta).
> ⚠️ **Atenção [CL]:** `prod-page`≠`producao-page` são **2 módulos distintos** (Produtos `/produtos` vs Produção `/fila`). NÃO fundir. §10.4 Passo 0 vs `main`. Refs `.md`/comentário em `app.jsx` ainda citam nomes antigos (doc, não quebram runtime) — atualizar por higiene quando tocar.

> 🔤 **NOVO 2026-06-04 → [CL] · RENAME `ds-v5/` → `ds-v6/` no git (one-name · [W] autorizou explícito "muda essa merda que eu autorizo"):**
> **Feito no Cowork (testado · host render OK):** `ds-v5/{tokens,components,doc}.css`+`interactive.js` movidos pra `ds-v6/`; host `oimpresso.com.html` + `Produção - Tela Real.html` repontados pra `ds-v6/tokens.css`; `ds-v6/_LEIA.md` documenta. Acabou o "v5 vs v6" — **um nome só = `ds-v6`** (régua/vitrine + implementação na mesma pasta).
> **[CL] espelha no git (Tier 0 · token/DS · §10.4 Passo 0 vs `origin/main`):** renomear o que o `main` chama de `ds-v5` → `ds-v6` (ADR 0244 era ds-v5; charters `related: ds-v5/components.css`; MEMORY_INDEX git; qualquer `@import`/ref). **Valores INTOCADOS** (roxo `oklch(0.55 0.15 295)` ADR 0235) — é rename de path, não mudança de cor. Atualizar referências em massa (grep `ds-v5` no repo) + ADR de supersessão de path se precisar. **NÃO cunhar número novo de ADR** (documenta no existente). Confirmar Cowork=git pós-rename (D-06). Controle-negativo: nenhum `<link>/@import/related` aponta pra `ds-v5` órfão após o rename.

> 🎨 **NOVO 2026-06-04 → [CL] · D-06 TOKEN PIPELINE (protótipo=produção) — [W] mandou executar direto (zero-toque, "qual foi minha decisão" = caminho inteiro):**
> **Proposta visual (Cowork):** `Token Pipeline D-06 - Proposta.html` (provada contra os 3 gates · roxo canon 295 intocado). **Decisão D-06:** `memory/decisions/_PROPOSTA-ds-harmonizacao.md` — "Cowork e app importam o MESMO token; handoff vira importação, não retradução".
> **Problema (✓ lido @main):** mesma cor em 3 fontes que divergem à mão — `ds-v5/tokens.css` (Cowork host) · `resources/css/inertia.css @theme` (único que gera utilities) · `prototipo-ui/tokens.css` (mirror v4). Drift = causa-raiz de verde×roxo / azul-220 stale / §16.2-mentira.
> **Plano em 4 fases (cada uma entrega sozinha, gate-safe):** **F1** eleger fonte → `design-tokens/tokens.css` (= valores do `ds-v5/tokens.css`, nada muda). **F2** build determinístico `npm run tokens` (saída versionada, diff revisável). **F3 [Tier 0]** gerar `foundations.css` com o `@theme` + `@import` no `inertia.css` (baseline 53→~0; foundation-guard PERMITE def em foundations.css) — **mexer em token global de produção espera olho [W]/staging (gate de pixel é STUB)**. **F4** `ds-v5/tokens.css` vira `@import` do gerado → Cowork+produção leem o mesmo arquivo. D-06 fechado.
> **Gates (✓ provado):** foundation-guard PASSA (def vai pra `foundations.css`, não engorda `inertia.css`) · ui:lint R1 PASSA (utilities geradas, sem `-NNN` cru) · conformance-gate PASSA (roxo 295 trancado num lugar só). **§10.4 Passo 0:** confirmar vs `origin/main` se já não existe um build de tokens (ex.: `design-tokens/`, style-dictionary) antes de criar — não recriar. **NÃO cunhar ADR.** Controle-negativo 2 lados (L-31) no aceite. **F1–F2 autônomos (CI verde); F3–F4 = espera [W].**

> 🛡️ **NOVO 2026-06-04 → [CL] · META-GATE: censo de gates não pode envelhecer em silêncio (causa-raiz dos erros L-26/27/33):**
> **Contexto:** [W] — *"como isso vai sobreviver ao tempo, se aparecer outro o que tem que ser feito?"*. O `DESIGN.md §16.2` virou MENTIRA (diz "rose/emerald/amber/blue cru OK", o `ui:lint` R1 proíbe) porque era doc solto sem ancoragem nos workflows. **A fonte da verdade de QUAIS gates existem = `.github/workflows/*.yml`** (cada um declara `paths:` + script/test). Censo completo + processo "gate novo" já gravado no STATUS Cowork (seção 🚧 GATES DE CI · ♻️ COMO SOBREVIVE). **Cowork-side já feito + provado:** `memory-health.js` CHECK 5 (frescor do censo, controle-negativo 2 lados verde).
> **[CL] avalia (Tier 0 · tooling/CI · peer-review L-17):** **meta-test git-side** que falha se um `.github/workflows/*.yml` com `paths:` tocando `resources/css/**`|`resources/js/**` **não** estiver num registry canônico de gates de design (ex.: `tests/Feature/Design/GateRegistryDriftTest.php` + um `design-gates.json` versionado listando {workflow · script/test · superfície · o que proíbe}). Assim **gate novo no repo = CI vermelho até documentar** → pega mecanicamente, sem depender de [CC]/[W] lembrar. Espelha a filosofia ratchet (ADR 0209) no eixo "meta: os gates se auto-inventariam". §10.4 Passo 0 (confirmar se já não existe algo assim) · NÃO cunhar ADR · controle-negativo 2 lados (L-31) no aceite.

> 🧠 **NOVO 2026-06-04 → [CL] · CICLO DE VIDA DE ADR MECÂNICO + retrieval da Jana respeita validade (conserto-raiz: índice puxa ADR superseded como vivo):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_ADR-LIFECYCLE-JANA-RETRIEVAL.md`. **Plano-fonte:** `memory/sessions/2026-06-04-conflitos-memoria-plano.md` (Fases 2–3). **[W] aprovou** ("sim eu quero").
> **Por quê:** [W] reportou memórias conflitantes — o índice da Jana (⚠ mem0/Meilisearch, ADR 0031/0033, confirmar no `main`) recupera ADR **superseded** porque o Status Nygard mora em prosa (não é campo filtrável) e não há grafo de supersessão. **Fase 2:** frontmatter `status`/`superseded_by` em todo `memory/decisions/NNNN-*.md` + índice/README gerados + `jana:health-check` cobra (estende `AdrNumberCollisionTest`). **Fase 3:** retrieval exclui superseded/segue pro vivo + reindex. **Controle-negativo dos 2 lados (L-31)** no aceite.
> **§10.4:** Passo 0 vs `origin/main` (confirmar stack de retrieval real, não recriar) · **NÃO cunhar/renumerar ADR** (0238 — colisão local `_PROPOSTA-0244`×`0245`/git 0244 se **documenta**) · backfill+health-check+grafo (reversível) = autônomo CI verde; **mexer em retrieval/reindex em prod = espera [W]**.

> 🧠 **NOVO 2026-06-04 → [CL] · MIRROR no git: invariante anti-erro promovido (Tier-2, peer-review L-17):** auditoria de gatilho ([W] "tem conflito de gatilho que te impede de ler o git?") achou: a regra "não afirmar fato do repo sem ler" vivia só no STATUS Regra de Ouro gate 6 (secundário) e **perdia o empate pra REGRA-0** ("age agressivo vence"). Reincidiu 2× (L-26/L-27) → §12 sobe de tier. **Aplicado no mirror local:** `PROCESSO_MEMORIA_CC.md` núcleo ganhou **invariante 14** ("Fato do repo = ⚠ inferido até ✓ lido NESTA sessão; agressivo vale pra EXECUTAR, nunca AFIRMAR; vence inv. 13"). **[CL] espelha no git:** (1) `prototipo-ui/PROCESSO_MEMORIA_CC.md` núcleo 13→**14 invariantes** (texto no mirror local); (2) `memory/LICOES_CC.md` rodapé de graduação: marcar **L-26/L-27 graduadas → core** (reincidiu 06-04, promovido). Não cunhar ADR. §10.4. _Ironia que prova o ponto: a lição é "memória local é cache stale, git é verdade" — então ELA precisa virar canon git, não ficar no meu cache._

> 🚧 **GUARDRAIL 2026-06-04 → [CL] (vale pra QUALQUER port, sempre):** **portar tela = conformar ao gabarito `ds-v6/` (limpo), NUNCA copiar o `*-page.css` do host Cowork.** Auditoria escopada 2026-06-04 (`memory/sessions/2026-06-04-auditoria-css-telas-base.md`): a **régua ds-v6 está limpa** (1-4 exceções: backdrop/scrim), mas as **implementações do host têm ~1595 cores cruas** (`styles` 485 · `inbox` 264 · `kb` 249 · `financeiro` 184 · `vendas` 126 — apesar do "100% tokenizado" alegado). Fonte de port = gabarito + `@/Components/ui` + token. O `stylelint-gate`/`ui:lint` (R1) trava cor crua em `Pages/` de qualquer jeito — esta nota é pra não desperdiçar o ciclo. CSS sintaticamente OK; "inválido" = fora do DS v6, não sintaxe.

> ⚡ **NOVO 2026-06-04 → [CL]+[W] · PLANO-MESTRE PARALELO/AGRESSIVO (4 raias concorrentes):**
> **Plano:** `memory/sessions/2026-06-04-plano-mestre-paralelo-agressivo.md`. [W]: "faça em paralelo, agressivo." **Abrir TODAS as raias hoje, 1 PR = 1 intent, merge autônomo em CI verde** (autorização permanente [W] 02-06). Agressivo é seguro pq os gates (CI/visual-regression/module-grades) travam regressão antes do main.
> **🟦 Raia 1 (44 telas):** ponte `APROVAR-44-STAGING-SCREENSHOTS.md` — frescor+rebase `feat/staging-ct100`, capturar **as 44 de uma vez** (claro+escuro), conflitos via **sub-agents paralelos**, [W] aprova 3 ondas numa sentada → merge por onda → re-roda board.
> **🟩 Raia 2 (juiz LLM):** [W] flip `PR_UI_JUDGE_ENABLED=true` (2 min, GitHub UI) — independente.
> **🟨 Raia 3 (higiene, 4 sub-PRs paralelos):** ADR DS v6 (amends 0235) · lápides docs stale `INDEX §6` · backfill `superseded_by` (ADR 0120) · extrair gates do +111k re-baselinados (**NÃO** introduzir `foundations.css`). Pontes: `ADR-LIFECYCLE-JANA-RETRIEVAL` + "UM ESTILO SÓ".
> **🟥 Raia 4 (sidebar):** dedup feito; desinchar SISTEMA + órfãos = decisão [W].
> **3 elos de dependência reais** (resto tudo paralelo): rebase→screenshots · aprovação→merge · nome-do-DS→ADR-v6. **5 gates [W]** agrupados (flip · aprovar 44 · nome DS · 5 origins · sidebar). §10.4 · não cunhar ADR · irreversível (prod/fiscal) espera [W].

> 🔌 **NOVO 2026-06-04 → [CL]+[W] · WIRING, NÃO CONSTRUIR (li o git atualizado · corrige o "execução 40" stale):**
> **Estado real (✓ lido):** aparato ~85% ligado (37 workflows CI · board 222 telas/média 75 · dashboard `screen-grade-board.html` · baseline ratchet JSON). **As 44 telas <70 JÁ estão em código verde** (`feat/staging-ct100`, Vite build exit 0, 2026-05-31). **`pr-ui-judge.yml` (juiz LLM) existe mas DEFAULT OFF.**
> **Fechar 3 portas (todas [W], não código novo):** **(1)** [W] aprova as 44 por **screenshot** (gate ADR 0107/0114) → fecha ratchet 0236 + merge de `feat/staging-ct100`; **(2)** flip `vars.PR_UI_JUDGE_ENABLED=true` (+ `ANTHROPIC_API_KEY`) → juiz semântico ON em todo PR = máquina cobra; **(3)** re-rodar o board p/ média nova pós-44. + 3 fixes de sidebar (Onda 4, decisão [W]).
> **NÃO:** reconstruir as 44 (feitas) · confundir com o branch `refactor/css-fundacao-unica` (+111k, workstream paralelo — provável stale vs staging-ct100; [CL] confirma qual é a fonte). §10.4 · não cunhar ADR.
> **Plano-fonte:** `memory/sessions/2026-06-04-reframe-gerenciar-design-serio.md` (§Correção). [CL]: confirmar se `feat/staging-ct100` está merged no main ou ainda aguarda o gate visual.

> 🎯 **NOVO 2026-06-04 → [CL]+[W] · UM ESTILO SÓ (ERP profissional) — plano grounded no `main`, fechar 5 gaps (NÃO reescrever):**
> **Plano-fonte:** `memory/sessions/2026-06-04-plano-design-canon-um-estilo-grounded-main.md`. **[W] pediu** ("um estilo só, ancorado Linear/Stripe/Carbon, tudo diferente=errado; consulte o git, quero certeza"). **[CC] LEU o `main`** (CSS + `_INDEX-LIFECYCLE` + `INDEX-DESIGN-MEMORIAS` + UI-0013).
> **Verdade (✓ lido):** o "estilo único" **já existe e é cobrado** — UI-0013 (4 camadas, Stripe/Linear/Carbon) + roxo 295 (0235) + IBM Plex; reconciliação JÁ FEITA (`INDEX-DESIGN-MEMORIAS` #1991, enforçada por `DesignIndexSingleSourceTest`); lifecycle JÁ filtra superseded (`_INDEX-LIFECYCLE` + `decisions-search`). **`foundations.css` NÃO existe no main** (fundação = `inertia.css`+`cockpit.css`).
> **5 GAPS a fechar:** **A** DS v6 sem ADR + naming v4×v6 · **B** `foundations.css` do branch = 4ª fundação paralela (NÃO introduzir) · **C** docs stale do `INDEX §6` (azul 220 / "só comunicação visual") sem lápide forte · **D** housekeeping lifecycle (9 ADRs superseded sem `superseded_by` no frontmatter — ADR 0120) · **E** gates (`conformance`/`foundation-guard`) só no branch, calibrados pro branch.
> **[CL] (mid-flight no branch `refactor/css-fundacao-unica`):** **rebase supervisionado** (dropar re-skin duplicado, re-derivar contra DS v6 do main, extrair gates, re-baselinar) → PR limpo — **não** merge big-bang do +111k (conflita com DS v6 já no main). Ratificar ADR DS v6 (amends 0235) + lápides §6 + backfill `superseded_by`, **tudo EXTENDENDO `INDEX-DESIGN-MEMORIAS`+`_INDEX-LIFECYCLE`, nunca recriando** (L-11). Amarra com a ponte `ADR-LIFECYCLE-JANA-RETRIEVAL` (corrigida hoje — housekeeping, não build-from-scratch).
> **🔴 3 decisões SÓ [W] (Tier 0 — respondem os checkboxes do Claude Code):** (1) **nome do DS** = "v6" oficial ou "DS v4 camada semântica"? (rec: nomear v6, ADR amends 0235); (2) **5 origins → 11 hues** sim/não (UI-0013 lista como lacuna); (3) **branch** = rebase supervisionado (rec) ou big-bang.

> 🧹 **NOVO 2026-06-04 → [CL] · OTIMIZAÇÃO DO SISTEMA DE MEMÓRIA (subtrair + mecanizar + 1 superfície de regra) — peer-review L-17:**
> **Plano-fonte:** `memory/sessions/2026-06-04-plano-otimizacao-sistema-memoria.md`. **[W] autorizou** ("otimize tudo, faça o plano"). Diagnóstico: hipertrofia (S1 5 listas de regra · S2 fato duplicado · S3 STATUS log gigante · S4 cobrança manual).
> **Já feito no Cowork (reversível, provado):** **M4** STATUS 36KB→19.6KB (log → digest `_STATUS-log-historico-ate-2026-06-03.md`, append-only) · **M3** `memory-health.js` (frescor + fonte-única + espinha + ADR-status; controle-negativo 2 lados verde) · **M1-local** hierarquia única declarada no topo do STATUS.
> **[CL] avalia (Tier 0, peer-review):** **(M3-git)** portar `memory-health.js` pro CI Cowork-side junto do guard de higiene L-07/11/21/22 já proposto (não duplicar — mesma família greppável) **+** os `IT1–IT7` do `PROCESSO §15` (hoje só escritos). **(M1-git)** espelhar a hierarquia única (COMO PENSAR=lei · Regra de Ouro=pré-flight · Bateria/health=check · lições=histórico) no `PROCESSO_MEMORIA_CC.md`/PROTOCOL — toca processo = **[W] ratifica**. **Amarra:** isto + a ponte `ADR-LIFECYCLE-JANA-RETRIEVAL` (Fases 2–3) são o mesmo programa "memória sã cobrada por máquina" — landar juntos. §10.4 · NÃO cunhar ADR.

> 🧪 **NOVO 2026-06-03 → [CL] · GATE DE CONFORMÂNCIA DS (teste, não memória — pedido [W] "como garantir?"):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_CONFORMANCE-GATE.md` (URLs de referência dentro: `qa-conformance.js` probe + `METODO §Matriz` + `Vendas.casos.md §Conformância DS`). **Tier 0** (tooling/CI · [W] autoriza). §10.4: validar vs `main` no Passo 0; **NÃO cunhar ADR** (0238).
> **⭐ FONTE DA VERDADE (decisão [W] "automatize quem é a fonte"):** por tela = **`Pages/<Tela>.charter.md` do `main`** (frontmatter `regua`/`ds` + baldes). `casos.md` + testes são **GERADOS** dele (estende `review-gen.mjs`/#2078 · derivados levam `@generated`+checksum → edição à mão = CI vermelho). **Anti-drift no `jana:health-check`**: falha se tela tiver >1 charter, derivado stale vs checksum, ou charter órfão → mata os "dois sistemas paralelos". Charter Cowork = sketch F1 (converge no F3). Ver §0 do prompt.
> **Por quê:** o loop protege **comportamento** mas não o **contrato visual/DS** — foi o que deixou um drift verde×roxo passar (memória não pega). 3 camadas **bloqueantes**: **(1) Stylelint** cor-crua==0 em regras de tela (estende #2054; exceto `:root`/`[data-theme]`/print) · **(2) computed-style** (Pest-browser, **não impor Playwright** se já tem) accent==token-do-charter (UC-V09, pega verde×roxo) · drawer `role=dialog` (UC-V11) · axe-core 2 temas (UC-V12) · **(3) META controle-negativo**: CI injeta bug e EXIGE vermelho (regra nova do MÉTODO — teste que nunca foi visto falhar não vale). Aceite + IDs no prompt. Começar por **Vendas**. **Amarra:** a Camada 1 anda junto da DARK-BACKFILL já em fila (mesmo `sells-cowork.css`) — não duplicar o sweep, só o gate que impede regredir.
> **Probe testável já vivo no Cowork** (`qa-conformance.js`, gated `?qa=1`/Ctrl+Shift+Q no host) = spec executável da Camada 2 + demo do controle-negativo. [CL] porta a lógica 1:1 pro CI.


> 🌓 **NOVO 2026-06-03 → [CL] · DARK-BACKFILL SWEEP (telas antigas feias no escuro):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_DARK-BACKFILL-SWEEP.md`. **[W] reportou** telas antigas feias no dark. Diagnóstico grounded no `main`: modelo de cor completo (#2170/#2181/#2184), mas bundles exportados (`sells-cowork.css` 292KB · `cowork-canon-financeiro-bundle.css` 334KB · `fiscal-cockpit.css` · etc.) são light-built com cor crua → default dark expôs a dívida. **Sweep mecânico cor-crua→token, dark-aware, incremental.** Ordem: **(0) ⭐ PRIORIDADE [W] — drawer SaleSheet do `/sells`: linhas "Itens da venda" com texto/preço LAVADO (ilegível no dark) em `sells-cowork.css` + `SaleSheet.tsx`; token-izar nome/qty/preço pra `var(--text)`/`--text-2`/mono. [W] viu na tela real 2026-06-03.** → (1) `.fin-stat-hero`/família `.os-stat` (conserta CRM+Financeiro juntos) → (2) texto lavado (Produtos/Clientes/mockups) → (3) `sells-cowork.css` → (4) restantes. Verificar 2 temas + Stylelint #2054. NÃO cunhar ADR. 1 PR por arquivo/família.
> **⚡ [W] autoriza PARALELO + AUTÔNOMO:** arquivos são independentes → abrir **N PRs simultâneos** (um por bundle/família), sem esperar [W] entre eles. Aditivo/não-Tier-0 → merge autônomo em **CI + visual-regression verde**. O **gate visual** é a rede de segurança: se um swap de token mexer na aparência do **claro**, o PR para ali (não regride produção). Velocidade pela paralelização, segurança pelo gate.


> 🎟️ **NOVO 2026-06-03 → [CL] · DS v6 TOKEN DELTA (opção 2 · single-intent, pós-#2165):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_DS-V6-TOKEN-DELTA.md`. **Aditivo, não-Tier-0.** Landar **`--stage-*` (5 tokens, claro+dark)** na fonte de token canônica (`cockpit.css`/`inertia.css`) — desbloqueia o `Norte.tsx` (troca o fallback escopado por `var(--stage-*)`). Chroma dos `--*-soft` = **opcional/gated** (só se o repo tiver esses nomes; senão delta próprio). Roxo canon intocado, §10.4, NÃO cunhar ADR. PR limpo single-intent.


> 🎨 **NOVO 2026-06-03 → [CL] · DS v6 APROVADO por [W] — formalizar componentes + receita:**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_DS-V6.md` (URLs de referência dentro: showcase + receita + ficha). **[W] aprovou ("já está aprovado").** Soma **aditiva** ao v5; roxo canon (ADR 0235) intocado.
> **Cobre só a camada de COMPONENTES + a régua** (os tokens `--stage-*` + chroma já estão no pendente `OFICINA-DARK-STAGE-DS` Parte 1 — **não duplicar**). 11 componentes canônicos (c-btn/pill/stage/kpi/tabs/plate/asset/id/tl/rail/nba) consumindo só token, claro/escuro de fábrica. **Passo 0:** mapear o que já existe no repo (MercosulPlate, *StatusBadge, DviPhotoGrid) e **reusar, não recriar**. Landar a **receita** (6 passos) como doc/gate do DS. Rascunho de ADR no prompt (NÃO numerar — soberania 0238). Ordem: PR1 tokens → PR2 componentes+showcase+receita → PR3 backfill (gabarito **Vendas**).


> 🌑 **NOVO 2026-06-03 → [CL] · Oficina: DARK padrão + paleta de etapas no DS + harmonização + view Fila (pacote da sessão [W] zero-toque):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_OFICINA-DARK-STAGE-DS.md` (auto-contido, URLs de referência dentro). **[W] pediu, viu na tela e aprovou (F2 visual) nesta sessão.**
> **4 partes:** **(1) DS · Tier 0 proposta — o ganho limpo:** add `--stage-{slate,indigo,rose,emerald,green}` (claro+dark) + subir chroma sutil dos `--*-soft` na fonte de token canônica (`cockpit.css`/`inertia.css`) — a harmonização revelou que o DS não tinha nome pras cores do pipeline ([W]: *"DS 5 precisa de tempero"*). Roxo canon 295 (ADR 0235) **intocado**, só adição. **(2)** dark como **padrão** + toggle Claro/Escuro (o `cockpit.css` já tem `[data-theme=dark]`; falta ligar `data-theme` no root). **(3)** harmonização: Oficina consome token, **zero oklch cru** (mapa hue→token no prompt) — alimenta o port. **(4)** view **Fila** master-detail + rail Apps Vinculados (OS+CRM+WhatsApp), reusando o sheet existente.
> **§10.4:** proposta; [CL] valida vs `origin/main` (Passo 0 por parte); **NÃO cunhar ADR** (0238). **Amarra:** Partes 3+4 = **mesmo esforço** do pendente **(i) OFICINA-KANBAN-F3-PORT** (não duplicar — landar Fila junto do board em `ServiceOrders`). Ordem sugerida: PR1 (DS, menor/seguro) → PR2 (dark) → PR3 (Oficina+Fila junto do (i)).


> 📌 **NOVO 2026-06-03 → [CL] · Charter NOVO travado por [W]: Cobrança Recorrente (Assinaturas):**
> **Artefato:** `prototipo-ui-patch/CobrancaRecorrente.charter.md` (espelho do `CobrancaRecorrente.charter.md` na raiz). **Tipo:** Page Charter (canon, L-14) · Tier 0 → landar no caminho de charter do git (junto aos `*.charter.md` de Sells/Financeiro), com front-matter de frescor. **NÃO cunhar ADR** (soberania 0238).
> **Contexto:** [W] **aprovou visualmente** (F2, 2026-06-03) a Cobrança Recorrente reescrita no DS warm — *"essa é minha tela, trava a tela, page charter"*. Trava o conceito: **3 colunas** (filtros · lista · drawer lateral), KPI hero warm (`var(--text)`, não zinc), sub-nav segmentada **ao lado do primary** (NÃO colada no título — [W] *"odeio page header"*), status warm em dia/retentando/falhou/pausada/cancelada. Mata as 6 proibições da RecurringBilling do git (zinc/violet/rounded-2xl/font-bold/modal/hero preto). F3 = reescrever `Pages/RecurringBilling/Index.tsx` seguindo o charter quando [W] liberar; por ora só **landar o charter**.

> 🩺 **NOVO PENDENTE 2026-06-03 → [CL] · DIAGNÓSTICO read-only (autônomo, sem deploy): "o site em produção == `origin/main`?" + tela branca da Eliana em `/recurring-billing`:**
> **Natureza:** inspeção read-only no servidor (NÃO deploy, NÃO migration, NÃO flip fiscal) → roda autônomo, relata em `CODE_NOTES.md`.
> **Origem:** [W] perguntou "está igual o site e o git?" — [CC] auditou só o `main` (não enxerga o site, login-gated). Como merge ≠ deploy neste projeto (deploy prod = passo manual [W]), o site pode estar atrás do `main`, OU no ar com assets cacheados (provável causa do branco da Eliana).
> **Tarefa (uma frase — [CL] resolve o "como"):** no servidor de produção, conferir e responder em `CODE_NOTES.md`: **(1)** o commit no ar é igual ao `origin/main`? (sim/não + quantos commits atrás); **(2)** o build do front está fresco — todos os assets do manifest Vite (`public/build/manifest.json`) existem em disco? *(asset faltando = tela branca · checar se `npm run build` rodou no último deploy + assets velhos purgados)*; **(3)** as telas `resources/js/Pages/Financeiro` e `Pages/RecurringBilling` batem com o `main`? Fechar com: **site == git? sim/não** + causa provável do branco da Eliana (deploy atrasado / build velho / cache do navegador) + recomendação.
> **Contexto [CC] (auditado no `main`, durável):** Cobrança Recorrente (Index/Faturas/Planos) está em **Tailwind cru** divergente do DS — quebra 6 proibições do charter Financeiro (`rounded-2xl`, violet cru ≠ roxo 295, `zinc` frio ≠ `stone`, `font-bold` h1, modal central pra detalhe ≠ Sheet lateral, KPI `bg-zinc-900` ≠ `fin-stat-hero`). Financeiro/Unificado é o canon. Consolidação fica pra DEPOIS do veredito de deploy.

> ✅ **[CL] PROCESSOU 2026-06-02 17:16 (PR #2119 + #2121 MERGED) — NÃO refazer:** A2 accent 220→295 · A1 gate AppShellV2 (224→0) · B smoke core-screens · C1 `#fff`→`var(--surface)` 30× · charters Vendas/Compras + INVENTARIO mirrorados · gates ui-architecture/multi-tenant. Os prompts `SESSAO-2026-06-02` (v3) e `REFORCO-APPSHELL-TESTES` estão **DONE**. As entradas DS-fusion→app.css / @media abaixo seguem SUPERSEDED (alvos errados; repo já roxo+warm).

> 🆕 **NOVO PENDENTE 2026-06-02 (k) → [CL] · Passo-0: fluxo da DANI (autoriza venda → faturamento → financeiro):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_DANI-AUTORIZA-VENDA-FATURAMENTO.md`. Persona Dani (faturista/financeiro Martinho). **⚡ [W] SIMPLIFICOU (por agora): basta o "enviar pra faturamento" (handoff venda→faturamento); o gate de aprovação central da Dani vira ROADMAP.** MVP = confirmar que a ação "enviar pra faturamento" existe e aparece nos DOIS tipos (OS `source:oficina` + balcão `source:balcão`) — o loop venda→fatura já está provado (#2135), então provável que seja só superfície/visível, não build.

> 🆕 **NOVO PENDENTE 2026-06-02 (j) → [CL] · Martinho usa GRADE — plano DECOUPLE (não migrar Blade, não parar ROTA LIVRE):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_MARTINHO-GRADE-DECOUPLE.md`. Martinho (biz=164) usa grade; ROTA LIVRE (biz=4) usa a mesma Blade viva. **Decouple:** a tela Blade de variações é multi-tenant (`Variation`/`VariationTemplate`) → Martinho gerencia grade na MESMA Blade, React lê o dado. **Passo 0:** o React `Sells/Create` já seleciona `variation_id`? SIM→pronto; GAP→**variation picker** aditivo (não migrar a tela). Migrar só o DADO (Firebird→Variation) com reconciliação (contagens+golden+dry-run staging+diff). **NÃO** migrar a Blade agora · **NÃO** tocar ROTA LIVRE · migração de tela = onda futura (paridade+staging+rollback). Reversível=autônomo; migração real/prod=espera [W].

> 🆕 **NOVO PENDENTE 2026-06-02 (i) → [CL] · F3: portar o KANBAN da Oficina (carro) pro repo + modificações [W]-aceitas:**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_OFICINA-KANBAN-F3-PORT.md`. **Verificado vs `main`:** o wow (kanban do carro) é o protótipo `oficina-page.jsx`; o repo `ServiceOrders/` é a casa (lista/sheet, **falta o board**); `ProducaoOficina/` é **caçamba** (vertical ≠, não confundir). **Portar o kanban pra `ServiceOrders` REUSANDO** o que o repo já tem: `KanbanDndProvider`+`DragConfirmDialog`, `MercosulPlate`, **`DviPhotoGrid` (foto real)**, `ServiceOrderRichSheet`. **Modificações [W]-aceitas (crítica [CC]):** (1) ⭐ foto real no card, não placeholder de texto; (2) contador 🔒→checklist "DVI x/y"+tooltip; (3) densidade KPIs @1280 por `@container`; (4) "12 OS" e distinguir aguardando-peças × aguardando-aprovação. **Canon (review do repo):** drag→FSM `ExecuteStageActionService` (nunca UPDATE direto) + Pest GUARD + charter/review/RUNBOOK + critique ≥80.
> **⚡ PRIORIDADE PRÉ-AMANHÃ (homologação Oficina):** a tela real já tem check-in + **DVI semáforo + itens + total** (✓ verificado por [W]). Faltam os 2 elementos do wow: **gate de aprovação** ("execução não inicia sem cliente aprovar" + enviar orçamento) + **card auto-venda** (OS→venda→NF-e/NFS-e, o backend já existe — chain CU-3→5 provado #2135). Trazer esses 2 **explícitos** no port; se der, **antes da homologação** (reversível → autônomo). **Sob autorização [W]:** build reversível = autônomo CI verde; prod-deploy/fiscal real = espera [W].

> 🆕 **NOVO PENDENTE 2026-06-03 (l) → [CL] · [W] AUTORIZOU ligar FISCAL REAL em produção (Martinho biz=164) — cutover CONTROLADO:**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_LIGAR-FISCAL-REAL-MARTINHO.md`. ⚠️ IRREVERSÍVEL (nota na SEFAZ não desfaz). **Passo 0:** ambiente+credenciais são por-business (biz=164, não global)? Não pode tocar ROTA LIVRE. **Pré-req [W]/Martinho:** certificado A1/A3 · regime/CRT · série NF-e · provider/token NFS-e (LC116/ISS) · CSC se NFC-e. **Sequência:** homologação (1 NF-e+1 NFS-e teste autorizadas) → flip prod só biz=164 → 1 nota REAL teste → **CHECKPOINT [W] confirma no portal SEFAZ** → abre gate. **[CL] autônomo:** Passo 0 + prep + teste homolog. **[W]:** credenciais + flip produção + checkpoint da 1ª nota real.

> ⚡ **[W] AUTORIZAÇÃO PERMANENTE 2026-06-02 (lote Martinho / Oficina-homologação) → [CL]:** [W] **sai do caminho de aprovação.** Pro lote Martinho (trava-segunda + homologação Oficina), o [CL] **procede AUTÔNOMO** sob §10.4 + peer-review + **CI verde** — **sem esperar [W]**. Loga tudo (SYNC_LOG/CODE_NOTES). **[W] só é chamado no IRREVERSÍVEL:** (a) **deploy/sync na prod da Martinho**, (b) **ligar emissão fiscal REAL** (NF-e/NFS-e em produção, não homologação), (c) qualquer migração destrutiva de dados dela. Resto = anda sozinho. *(Calibrado: humano só onde a máquina não pode desfazer — não em cada tela/ajuste.)*
> **⚡ AMPLIAÇÃO [W] 2026-06-02:** [W] autoriza também o **merge das PRs Tier 0 abertas** (ledger · advisor Metade A · fiscal-status-unificado · trava-segunda) **autônomo em CI verde** — não precisa autorizar PR a PR. Os **3 irreversíveis** acima (deploy prod Martinho · ligar fiscal REAL · migração destrutiva) continuam os ÚNICOS que esperam [W]. **`JANA_CLARIFY_ENABLED` já em produção** (Advisor Metade A live — medir `clarify_event`; Metade B é o próximo).

> 🆕 **NOVO PENDENTE 2026-06-02 (g) → [CL] · Jana "Modo Consultor" (Advisor) — subir o raciocínio:**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_JANA-ADVISOR-MODE.md`. **Tipo:** evolução de produto (Jana/raciocínio) · Tier 0 (produto+custo) → PR + espera [W]. **Peer-review (L-17).**
> **Origem:** insight do [W] — *"a melhor resposta vem quando pergunto QUE pergunta fazer."* SOTA: Active Task Disambiguation (ICLR'25) + INTENT-SIM (NAACL'25). Princípio: andaime > troca de modelo (scaffold move desempenho até 30pp no mesmo modelo).
> **2 metades:** **A)** clarify reativo — cascata Decidir→Clarificar→Responder; separar intenção-ambígua (pergunta) de falta-de-dado (busca); filtro barato → disambiguador caro só nos ~20% cinza. **B) ⭐** próxima-melhor-pergunta proativa — surfa as N perguntas que [W]/equipe deveriam fazer agora, já com resposta, **por persona**, estendendo o brief. **Cross:** roteamento de modelo (difícil→frontier seletivo), grounding fresco, honestidade (ledger #2131 pega erro de operação).
> **§10.4:** Passo 0 vs `origin/main`; **estender** 4 Agents/brief/MemoriaContrato (NÃO recriar); não cunhar nº ADR (0238); **começar pela Metade A**.
> **Fila #2 (spec depois):** benchmark-champion **automático** — `estado-da-arte`/`comparativo-do-modulo`/`maturity-gap-expert` rodando sozinhos, devolvendo só o *delta* (L-16, tira [W] do ritual).

> 🆕 **NOVO PENDENTE 2026-06-02 (e) → [CL] · Jana ganha ledger de auto-reflexão (Reflexion runtime) — 1 item NOVO, 3 já-pendentes NÃO reprocessar:**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_JANA-LICOES-REFLEXION.md` + view `rep-cc-vs-jana` no `metricas.html` (comparação **[CC] × Jana × Champion**, pesquisa profunda do estado-da-arte 2025-26). **Tipo:** evolução de módulo (Jana) · Tier 0 → PR + espera [W]. **Peer-review (L-17), não ordem.**
> **NOVO:** dar à Jana um ledger dos próprios **erros de operação/comportamento** (≠ saída, que golden/RAGAS já cobrem) no formato Erro·Sintoma·Regra·Ref + **Graduação** (MEC→check no `jana:health-check` · JULG→regra). É a lacuna #1 da Jana no placar (Aprendizado: ~6.5 vs [CC] ~9.0). Reflexion aplicado ao comportamento; destino de cada lição = **check** (Voyager: executável > prosa). [CL] Passo 0: **estender** `incident-done-checklist`/`feedback-capture`/`jana:health-check`, não recriar.
> **NÃO reprocessar (a comparação só CONFIRMA, não adiciona):** (1) guard de higiene Cowork L-07/11/21/22 = **já pendente** (entry "Loop de graduação de lição"); (2) collector CT 100 + OTel + LGPD purge = **já #2073**, falta ENABLE Tier 0 [W]; (3) rubrica/score de design = **já em main** (`design:review` #2078, estende — não cria motor novo).
> **new_design_memories:** doc-novo · `rep-cc-vs-jana` · comparação 3-vias, Jana lacuna #1 = sem reflexão dos próprios erros · golden · Reflexion→Voyager · lição vira check, não prosa.

> 🆕 **NOVO PENDENTE 2026-06-02 (f) → [CL] · Segurança pré-drop das views `copiloto_*` (verificado no main):**
> **Verificado por [CC] (✓ origin/main):** migration `Modules/Jana/Database/Migrations/2026_05_06_120000_rename_copiloto_tables_to_jana.php` renomeia 13 tabelas → `jana_*` e cria 13 views `copiloto_*` (CREATE OR REPLACE VIEW). PHPDoc: "drop planejado 2026-06-05". **NÃO há migration de drop no repo** — o drop é **ação manual planejada, não automática**. Código já aponta `jana_*`.
> **Ação (peer-review L-17):** **antes** de executar o drop, grepar `copiloto_` como nome de tabela/view em raw-SQL/config/integrações externas (ex.: a área `OpenAiDirectDriver` que o `LARAVEL_REPO_CONTEXT` marca como ainda referenciando padrões legados) → confirmar **zero consumidor** das views. Se limpo, escrever a migration de drop (idempotente, `DROP VIEW IF EXISTS`, com `down()` que recria via SELECT) e abrir PR. **Executar = Tier 0 [W].** Não é urgente (não quebra sozinho em 06-05).
> **Fonte/racional:** view `rep-riscos` no `metricas.html` (matriz de risco, 13 itens, 9 ✓ verificados).

> 🆕 **NOVO PENDENTE 2026-06-02 (c) → [CL] · Unificar status fiscal NF-e/NFS-e/NFC-e:**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_FISCAL-STATUS-UNIFICADO.md` (auditoria [CC] grounded no `main`). **Tipo:** F3 reuse-first (apresentação, NÃO backend SEFAZ).
> Achado: status fiscal em **4 implementações** (`NfceStatusBadge` NFC-e-only ✅ bom padrão + Vendas `FiscalSection` + Oficina `ServiceOrderRichSheet` + Fiscal `NotaDrawer`+**V2 dup**). Proposta: **1 `FiscalStatusBadge`** generalizando o NfceStatusBadge p/ NF-e 55 + NFS-e, consumido pelas 4 superfícies + resolver NotaDrawer V1/V2. Lógica SEFAZ já centralizada (`_lib/sefaz-actions.ts`) — falta só a apresentação seguir.
> ✅ **Dedupe bundle Financeiro = JÁ FEITO** (`cowork-financeiro-bundle.css` deletado do `main`, −327KB). Item de dedupe = DONE, não reprocessar.

> 🆕 **NOVO PENDENTE 2026-06-02 (b) → [CL] · CRM trio (destrava migração Blade→Inertia):**
> **Artefato:** `prototipo-ui-patch/PROMPT_PARA_CODE_CRM-TRIO.md`. **Tipo:** mirror de docs (seguro) + flag de migração (decisão [W]).
> 1. **Mirror seguro:** `CRM.charter.md` → `prototipo-ui/prototipos/crm/charter.md`; `CRM.casos.md` → `prototipo-ui/prototipos/crm/decisoes.md` (ou `casos.md`). Grounded no `crm-page.jsx` (8.6).
> 2. **Migração Blade→Inertia = NÃO fazer ainda** — é Tier 0 (programa grande, decisão de prioridade de [W]). O CRM do repo é UltimatePOS Blade legado (L-26). Quando [W] der go, a charter+casos são o alvo fiel. Ajustes travados pra F3: **emoji→lucide** (Phone/MessageCircle/FileText); **tokenizar hues de estágio**; WhatsApp = ação interna (mantém).

> **§10.4 vale pra TODAS:** Passo 0 (ancorar em `origin/main` fresco) · não recriar · não cunhar nº de ADR (0238) · Tier 0 espera [W].

> **[CL] LEIA ISTO PRIMEIRO (status · 2026-06-01 · RECONCILIADO vs `origin/main` fresco — sync da tarde):** ✅ JÁ EM MAIN — não refazer: **Jana Pro F3 #2069** · **prep dos 3 Tier 0 de IA #2073** · **Gerador `design:review` #2078** (review-gen.mjs + freshness ratchet + Pest + PROTOCOL §6; 1ª exec `Jana/Pro.review.md` nota 88) · G4 #2064 · charters #2061 + ADR 0242 · README handoff #2062 · health-check charter #2055 · Financeiro v10 #2053 · G5 lint #2054/ESLint 0209. As **3 regras de sessão** (no-dup design L-21 + trilha L-22) traduzidas em `CLAUDE_DESIGN_BRIEFING §7.1` (branch `docs/design-no-dup-trilha`, **aguarda merge [W]**); a 3ª (rename shell) = **N/A** (não há shell vivo no repo). "Vendas A+" é F1 design ([CC]).
>
> **Fila Tier 0 restante — top→down (abre PR e espera [W]):** (1) **Método Migration→Tela** (lente pré-F1 schema→posicionamento) · (2) **ADR peer-review + override ≥98%** ([W] já autorizou "formalize"; [CL] declinou como já-canon 0238/0241 — mas formalizar é decisão de [W]) · (3) **IA — ENABLE Tier 0** (prep #2073 feito; ligar OTel collector + LGPD purge prod + cadência RAGAS + Meilisearch HA = custo/infra) · (4) **Auditoria paralela read-only** (`design-report.json` por tela) — aguarda go. **[~~design:review~~ saiu da fila → MERGED #2078.]**
>
> ⚠️ **Achado anexo (NÃO do #2078, fix = PR separado):** `Pages/Jana/Pro.tsx` (#2069) tem **2 R1 cor-crua dark fora do `ui-lint-baseline.json`** → `ui:lint` vermelho. Tokenizar o card dark é PR de tokens dark — já no backlog do `Pro.review.md`.
>
> ✅ **Jana Pro F3 FEITO** (confirmado vs `origin/main` 2026-06-01 · `resources/js/Pages/Jana/Pro.tsx` 19KB + `Pro.charter.md` status `live`, criados por [CL] no mesmo dia). [W] liberou e a tradução aconteceu. **Resíduo FECHADO:** o `Pro.review.md` (nota mecanizada 88) foi gerado como **1ª execução do `design:review` #2078** — toda tela Jana agora tem review round 1.
>
> ⚠️ **§10.4 vale pra TODAS:** Passo 0 (ancorar em `origin/main` fresco) ANTES de tudo · não recriar o que já existe no `main` · não cunhar número de ADR (soberania [W], 0238) · Tier 0 abre PR e **espera [W]**.

---

---

---

---

### 2026-06-02 [CC] → [CL] · DS v5 É FONTE ÚNICA no host (fusão v4→v5 executada+verificada)

### Artefato: `ds-v5/tokens.css` (Cowork) → mirror em `resources/css/app.css` (repo, fonte canônica de token §10.4) · ### Tipo: migração de DS · ### Prioridade: Tier 0 — abre PR e espera [W]
### Brief-fonte: [W] chat 2026-06-02 — "vamos fazer até trocar tudo sem problemas… auditoria… fundir o melhor… no final só 1".

### O que [CC] fez no Cowork (verificado):
1. **Auditoria** programática: 167 tokens `var(--x)` usados em 19 CSS do host vs definidos em v4 (`tokens.css`) vs v5 (`ds-v5/tokens.css`). Resultado: **29 tokens de quebra** (só o v4 provia) + 20 value-shifts pequenos (v5 é polish: neutros +quentes, row-h 30→32, accent-soft +suave; **--accent roxo idêntico**).
2. **Fusão:** bloco COMPAT no fim de `ds-v5/tokens.css` — absorve os 29 + aliases de coerência, mapeando v4→v5 via `var()` (dark/density propagam sozinhos). Status v4 (`--ok/warn/danger/info-*`) → `--pos/warn/neg/accent`; `--radius-md`→`--r-2`; `--shadow-1/3`→`--sh-1/3`; `--t-fast`→`--t-1`; `--primary-page`→`--accent`; neutros `--bg-2/--text-dim/--text-mute/--accent-2`→`--sunken/--text-2/--text-3/--accent-hi`. Tipo mantido IBM Plex (Hanken do v5 não entra).
3. **Swap:** host `<link tokens.css>` → `<link ds-v5/tokens.css>`. `tokens.css` v4 vira lápide.
4. **Verificado:** 0 tokens vazios em `:root`; 6 telas render limpo (Vendas/Compras/Financeiro/CRM/Produtos/Inbox 9.75) + sidebar; console limpo.

### Ação [CL]: espelhar no repo
A fonte canônica de token do repo é `resources/css/app.css` (§10.4). Aplicar a MESMA camada COMPAT lá (ou migrar os módulos pros nomes v5). Conferir que `resources/css/cowork-financeiro-bundle.css` (188 hex) e `Sidebar.tsx vibeAccent` 220 não brigam com o roxo canon. §10.4: ancorar em `origin/main` fresco, não cunhar número.

### new_design_memories
- tipo: token · ref: ds-v5/tokens.css bloco COMPAT · resumo: v5 é fonte única no host; v4 absorvido via aliases var(); roxo+IBM Plex preservados; 6 telas verificadas
- tipo: golden · ref: auditoria de tokens (run_script) · resumo: padrão de migração de DS = auditar used vs defined → cobrir gap por alias var() → swap → testar 0-vazios + telas

---

### 2026-06-02 [CC] → [CL] · FIX — Financeiro KPI colisão <1100px (media→container)

### Artefato: `fin-boletos.css` (Cowork) → mirror em `resources/css/fin-cowork.css` (repo) · ### Tipo: fix CSS responsivo · ### Prioridade: P1
### Brief-fonte: [W] chat 2026-06-02 "teste pense faça e teste de novo" (1º loop do método na tela Financeiro 8.0).

### Problema (medido, não suposto):
O KPI strip `.fin-stats` (5-col `1.4fr repeat(4,1fr)`) refluia por `@media (max-width:1180px)` = largura do **viewport**. O shell tem sidebar ~240px, então na Larissa (viewport 1280 → conteúdo ~1040px) o media-query **não disparava** → 5-col espremido (medido: cells 2–3 a 1px do limite, zero respiro).

### Fix [CC] aplicado no Cowork (`fin-boletos.css`):
1. `.fin-body { container: finbody / inline-size; }` (seguro: overlays `position:fixed` são IRMÃOS de `.fin-body`, não filhos — containment não os captura).
2. `@media (max-width:1180px/720px)` → `@container finbody (max-width: 1100px / 600px)`. Reflua pela largura REAL do conteúdo. Medido limpo: 1200px→5-col 1 linha · 1040px→4-col+hero full · 560px→2-col. Zero overflow.

### Ação [CL]: espelhar no repo
O repo usa `.fin-cowork .fin-curadoria .fin-stats` (grid `minmax(260px,1.6fr) repeat(4,1fr)` em `fin-cowork.css`) com o MESMO bug viewport-vs-content. Aplicar análogo: container em `.fin-cowork .fin-curadoria` (ou no scroll-body equivalente) + `@container`. Conferir que nenhum overlay fixed é filho do elemento que recebe `container`.

### new_design_memories
- tipo: anti-padrao · ref: Financeiro KPI · resumo: reflow por @media viewport ignora sidebar → colisão no monitor do operador; usar @container na largura do conteúdo (regra ADR 0200 "responsivo por container-query")
- tipo: golden · ref: fin-boletos.css `.fin-body container:finbody/inline-size` · resumo: padrão de container-query pós-sidebar; replicável em qualquer KPI grid do cockpit

---

### 2026-06-02 [CC] → [CL] · DECISÃO [W] — DS v5 único + Oficina padrão (PROPOSTA, Tier 0)

### Artefato: `memory/decisions/_PROPOSTA-0245-ds-v5-canon-oficina-padrao.md` · ### Tipo: ADR (Tier 0 · canon/token) · ### Prioridade: Tier 0 — abre PR e espera [W]
### Brief-fonte: [W] chat 2026-06-02 — "prefiro manter a Oficina como padrão" + "faça tudo que propôs".

### O que [W] decidiu (eu, [CC], só PROPONHO — número/versão = [W]/git, soberania 0238):
1. **`ds-v5/*` = DS ÚNICO ATIVO.** v4.1 (`tokens.css` raiz + `design-system.css`) → **histórico de transição** em `_arquivo/ds-historico/` (lápide, append-only). **Move físico gated:** só depois de re-apontar os imports do host pro `ds-v5` — NÃO mover antes de migrar imports (quebraria o app).
2. **Oficina = tela-padrão / semente do v5.** Componentes graduam dela 1º (cockpit patterns já presentes e roxo-limpos no `ds-v5/components.css`).
3. **Inbox 9.75 = régua de _nota_ congelada** (papel ≠ semente). Não mexer.
4. **Roxo `oklch(0.55 0.15 295)` (ADR 0235) INTACTO.** Âmbar da Oficina = accent escopado de tela (tweak/`.oficina-root`), não toca o token do DS. **Grep [CC] confirmou: `ds-v5/components.css` 100% `var(--accent)`, zero hex/âmbar hardcoded.**

### Ação [CL] (quando [W] liberar):
- Numerar/versionar a proposta como ADR no `main` (soberania [W]).
- NÃO mover v4 enquanto o host não importar `ds-v5`. Backlog de migração: charter Vendas+Compras (já têm `*.casos.md`) → Financeiro 8.0 (corrigir KPI grid `<1100px` por container-query).
- §10.4: ancorar em `origin/main` fresco; não recriar o que já existe; não cunhar número.

### new_design_memories
- tipo: doc-novo · ref: _PROPOSTA-0245 · resumo: v5 único; Oficina padrão/semente; Inbox régua; roxo intacto
- tipo: conflito · ref: v4.1→`_arquivo/ds-historico/` · resumo: rebaixado (move gated por re-apontar imports)

---

### 2026-06-01 [W] → [CL] · CHARTER NOVO — Oficina/OS (Nova Ordem de Serviço) · canon de tela

### Artefato: `Oficina.charter.md` · ### Tipo: Page Charter (canon, L-14) · ### Prioridade: Tier 0
### Brief-fonte: [W] chat 2026-06-01 — "construa a nova venda" (escolheu B=Oficina) + crítica "feio/POS" + benchmark.

### O que é:
1ª aplicação do par `CONTEXTO-DE-TELA` + `FRESCOR-DE-TELA`. Charter da Nova OS da Oficina (`window.OficinaOSPage` / `oficina-os-page.jsx`), com front-matter de frescor (`last_validated`/`validated_against`). Trava o conceito: documento vivo (check-in→DVI→orçamento→**gate de aprovação**→execução), split peça(NF-e)×serviço(NFS-e), **Non-Goals: não é POS/cupom**. Referência: Shopmonkey×Tekmetric×Shop-Ware (benchmark anexo).

### [CC] entregou (F1, Cowork): `oficina-os-page.{jsx,css}` (build) + `Oficina.charter.md` + espelho `prototipo-ui-patch/Oficina.charter.md`. Verificador: passou.

### [CL] traduz (Tier 0 → PR + espera [W]):
- Landar `Oficina.charter.md` no repo no caminho de charter (junto aos `*.charter.md` de Sells), **com o front-matter de frescor** (alimenta o `charter-freshness.mjs` quando ele existir).
- Quando F2/F3 vierem: criar `resources/js/Pages/Oficina/Os/Create.tsx` seguindo o charter (Cockpit V2, tokens, sem cor crua). Por ora só o charter.
- NÃO cunhar ADR sem [W] (soberania 0238).

> **§10.4:** F1 = proposta. [CL] Passo 0 contra `origin/main` (não duplicar charter existente — estender). Retorno em `CODE_NOTES.md`.
> **URL pública (charter):** https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/Oficina.charter.md?t=b1495a1f8bc23c51161f6fa2b3c27d91d769ab923a406d712fdb284debbab577.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1780363329.fp&direct=1 (vale ~1h)

---

### 2026-06-01 [W] → [CL] · REGRA NOVA — Loop de graduação de lição (camada 5 ativa) + guard de higiene Cowork

### Artefatos: `APRENDER-COM-ERRO.md` + colheita no rodapé de `LICOES_CC.md` · ### Tipo: regra de processo + (proposta) guard · ### Prioridade: Tier 0
### Brief-fonte: [W] chat 2026-06-01 — *"isso vai fazer ele aprender com os erros? zero-toque não é transferência de conhecimento. prefiro saber como resolver para não errar mais."*

### O que é:
Fecha a 5ª camada de garantia (Intake→Frescor→Crítica→Humano→**Lição ativa**). Princípio: lição que só vive em log **não foi aprendida — foi arquivada**. Toda `L-NN` precisa **graduar**: MECANIZÁVEL → vira **check** (erro morre); JULGAMENTO → vira **regra-carregada** em arquivo sempre-lido. Loop de 3 estágios (Registrar → Triar/Graduar → Colher periódico). Generaliza L-16 (cobrança = máquina) + L-17 (proposta+soberania).

### Colheita retroativa feita (22 lições): 15 carregadas/fato · 5 já em check · **4 pendentes mecanizáveis = L-07, L-11, L-21, L-22** — todas **higiene de filesystem Cowork** (não CI do repo Laravel — L-13/L-20). L-14 gradua junto da ponte FRESCOR.

### [CC] já entregou (F1): `APRENDER-COM-ERRO.md` + tabela de graduação no rodapé do `LICOES_CC.md`. [CC] segue desde já (toda lição nova nasce com `Graduação:`).

### [CL] avalia (Tier 0 → PR + espera [W]) — **peer-review, não ordem (L-17)**:
- **Proposta de guard único Cowork-side** `no-new-root-html` + `no-new-channel-file` + `move/delete-requer-lápide` (cobre L-07/11/21/22 — mesma família greppável). **NOTA:** é higiene do **Cowork**, não do repo Laravel — avaliar se vira script no bundle / health-check, ou se fica só como Regra de Ouro carregada. [CL] decide o melhor lugar (ele procura padrão melhor que eu — L-20).
- **Propor** link de `APRENDER-COM-ERRO.md` no PROTOCOL §6/§10 (cobrança automática = camada 5). Não cunhar ADR sem [W].

> **§10.4:** F1 = proposta entre pares. Maioria das lições JÁ graduada — não refazer check existente (L-02/04/08/09). Só as 4 pendentes + o link. Retorno em `CODE_NOTES.md`.

---

### 2026-06-01 [W] → [CL] · REGRA NOVA — Frescor de Tela (ratchet doc + delta) · camada 2, par da Ficha

### Artefato: `FRESCOR-DE-TELA.md` · ### Tipo: regra de processo + check CI · ### Prioridade: Tier 0
### Brief-fonte: [W] chat 2026-06-01 — *"cada tela vai ficar bem documentada? vai saber o que foi acrescentado? como garantir que ele não erre?"*

### O que é:
Par da `CONTEXTO-DE-TELA.md`. **Estende o `review-freshness.mjs` (#2078)** pra charters e dossiês.
- **Mec.1 — charter-freshness:** front-matter do charter ganha `last_validated` + `validated_against` (sha do `component`). Check CI **`charter_stale`** (irmão de `design_review_stale`): `.tsx` com commit mais novo que `validated_against` → charter `stale` → vermelho. Ratchet só cresce.
- **Mec.2 — dossiê `last_analyzed_commit` + `fontes_analisadas[@sha]`:** Passo 0 vira `git diff <last_analyzed_commit>..origin/main` restrito às fontes → re-analisa só o que mudou (não relê tudo).
- **Mec.3 — delta-log por tela:** linha append-only `Δ data: campo +/~/− · commit · impacto`.
- **Gate:** charter `stale` não desenha; dossiê sem commit = incompleto; doc não-carimbada = tratada como ausente.

### Por que: doc velha engana [CC] (erra com confiança) e reler `origin/main` inteiro não escala p/ 16 telas. É a camada 2 (detectar deriva) das 5.

### [CC] já entregou (F1): `FRESCOR-DE-TELA.md` (Cowork + espelho `prototipo-ui-patch/`). [CC] segue desde já.

### [CL] traduz (Tier 0 → abre PR e espera [W]):
- Landar `prototipo-ui/FRESCOR-DE-TELA.md`.
- **Implementar `charter-freshness.mjs`** espelhando `review-freshness.mjs` (#2078): baseline `charter-freshness-baseline.json`, check `charter_stale` no PROTOCOL §6, Pest `CharterFreshnessTest`.
- Adicionar front-matter `last_validated`/`validated_against` aos charters existentes (Sells/* + Financeiro), carimbando no HEAD atual.
- **Propor** link no PROTOCOL §2/§3 como complemento da Ficha (gate F0→F1). Não cunhar ADR sem [W].

> **§10.4:** F1 = proposta. [CL] Passo 0 contra `origin/main` (reusar a infra do #2078, NÃO duplicar). Soberania: toca PROTOCOL = [W] ratifica. Retorno em `CODE_NOTES.md`.

---

### 2026-06-01 [W] → [CL] · REGRA NOVA — Ficha de Contexto de Tela (intake F0→F1) · formaliza Tier 0 #1

### Artefato: `CONTEXTO-DE-TELA.md` · ### Tipo: regra de processo (lente pré-F1) · ### Prioridade: Tier 0
### Brief-fonte: [W] chat 2026-06-01 — *"como deve contextualizar para você criar uma tela? ensine ele a contextualizar melhor e você siga a regra criada."*

### O que é:
Checklist de duas pontas que trava o início de F1: **Lado A** = brief mínimo de 6 campos que o briefador ([W]/[CL]) preenche no `COWORK_NOTES`; **Lado B** = pesquisa obrigatória de [CC] (Método Migration→Tela em ordem fixa: charter → .tsx prod → schema → FSM → fiscal → caso prático → review → ADR → protótipo+tokens → memória de erros); **Lado C** = dossiê rankeado (P0/P1/P2) antes do pixel; **Gate** = sem charter não desenha, não inventa campo/paleta/process, não duplica modelo (L-21), não forka tela por variação.

### Por que: `Sells/Create` regrediu pra POS por falta deste gate (venda virou "cupom"). O campo "não-objetivo" do brief teria evitado.

### [CC] já entregou (F1, pronto pra traduzir):
- **`CONTEXTO-DE-TELA.md`** (Cowork + espelho `prototipo-ui-patch/CONTEXTO-DE-TELA.md`) — regra completa.
- **`memory/sessions/2026-06-01-contexto-venda-dossie-git.md`** — Lado-C de exemplo (prova viva, tela Vendas).
- [CC] **já segue a regra desde agora** (Cowork é fonte local; o git é o canon a ratificar).

### [CL] traduz (Tier 0 → abre PR e espera [W]):
- Landar `prototipo-ui/CONTEXTO-DE-TELA.md` no repo.
- **Linkar do `PROTOCOL.md`** como gate de transição **F0→F1** (não editar a lei sem [W]: **propor** o link em §2/§3; [W] OK → [CL] numera/versiona). É a materialização do Tier 0 #1 (Método Migration→Tela) — pode **fundir** os dois itens da fila num só.
- Sem cunhar ADR sem [W] (soberania 0238).

> **§10.4:** F1 = proposta. [CL] Passo 0 contra `origin/main`. Soberania: regra de processo que toca PROTOCOL = [W] ratifica; [CC] propõe. Retorno em `CODE_NOTES.md`.

---

### 2026-06-01 [W] → [CC] · F0 — "endereço do cliente e na venda" (entrada via chat, registrada aqui)

### Telas: `Cliente` (drawer de detalhe) + `Sells/Create` (seção Frete/entrega) · ### Prioridade: P1 · ### Persona: Larissa (balcão 1280px) + Eliana [E] (fiscal)
### Brief-fonte: [W] chat 2026-06-01 — *"tarefa endereço do cliente e na venda"*

### Contexto:
A gráfica precisa do endereço do cliente em dois lugares: (1) **cadastrado no cliente** (cobrança + entrega) e (2) **puxado na venda** quando há entrega — o endereço de entrega decide `mesmo município` vs `outro município`, que é o gatilho do **MDF-e**. Hoje o cliente só tinha cidade/UF derivada por hash e a venda tinha um `<select>` manual de município solto, sem endereço real.

### O que [CC] entregou no Cowork (F1, pronto pra traduzir):
- **`data-os.jsx`** — modelo de endereço: cada `OS_CLIENTS[i].addresses[]` = `{ id, label, cep, logradouro, numero, complemento, bairro, cidade, uf, principal, entrega }`. Helpers exportados em `window.OS_DATA`: `cliEntregaAddr`, `cliPrincipalAddr`, `fmtAddrLinha`, `fmtAddrCidade` + `OS_MATRIZ_CIDADE` (= "São Paulo", base do cálculo mesmo/outro município).
- **`clientes-page.jsx`** — seção **Endereços** no `ClienteDetailDrawer`: cards por endereço (logradouro/nº/compl · bairro · cidade/UF · CEP mono), flag **Cadastro**/**Entrega padrão**, botão **copiar**, "Usar p/ entrega" e **Adicionar** (form inline CEP→UF). `deriveCli` agora usa cidade/UF reais do endereço principal (não mais só hash).
- **`vendas-create-page.jsx`** — seção **Frete/entrega** reescrita: ao escolher **Entrega**, mostra **picker dos endereços salvos do cliente** (default = endereço de entrega) + **"Outro endereço"** (campos inline). O município (`municipioOutro`) é **derivado do endereço escolhido vs matriz** — substitui o `<select>` manual e alimenta o MDF-e automático (`autoDocs`/`FISCAL`).
- **`clientes-page.css` / `vendas-create-page.css`** — estilos escopados (cards, picker, banner de destino). Tokens canônicos (sem hex cru, rounded-md, `--accent`).
- **`icons.jsx`** — +1 ícone `copy` (linha/rect, padrão Lucide).

### O que [CL] traduz pro repo (F3, Tier 0 → PR + espera [W]):
- **Migration/modelo:** tabela de endereços do cliente (`customer_addresses`: cep, logradouro, numero, complemento, bairro, cidade, uf, label, is_principal, is_entrega, multi-tenant ADR 0093) ou JSON no cliente — seguir o que o schema real já tiver (Método Migration→Tela). FK p/ a venda usar o endereço de entrega escolhido.
- **`resources/js/Pages/Cliente/Show` (ou drawer):** seção Endereços (cards + add + flag entrega).
- **`resources/js/Pages/Sells/Create.tsx`:** seção Frete puxando `customer.addresses`, derivando município p/ MDF-e do endereço de entrega (não input manual).
- Componentes `@/Components/ui` (FieldError etc.), Cockpit V2, sem cor crua.

### Proibições (charter): sem rounded-xl+, sem inglês em UI cliente-facing, sem emoji, sem cor fora dos tokens, drawer lateral (não modal full-screen).

> **§10.4:** F1 = proposta. [CL] Passo 0 contra `origin/main` (não duplicar campo de endereço se já existir no schema — **estender**), abre PR e espera [W]. NÃO cunha ADR. Retorno em `CODE_NOTES.md`.

---

### 2026-06-01 [W] → [CC] · F0 — App Mobile Office Impresso (entrada via uploads, registrada aqui)

> 🧭 **NOTA DE PROTOCOLO (rusticidade pega no teste do [W]):** [W] tentou "mandar a tarefa" 2×; o inbox canônico `prototipo-ui/COWORK_NOTES.md` do **git ficou byte-idêntico nas 3 leituras** (23268 bytes). Causa: **[W] não escreve no git de forma síncrona — só [CL] commita lá.** O canal que [W] DISPARA é este `COWORK_NOTES.md` **local do Cowork** (via Share→Handoff). A tarefa chegou de fato como **uploads** (`uploads/Office_Impresso_Mobile_Design_System.md` + 3 mockups ChatGPT 1/06). **Conserto proposto (Tier 0, [CL] formaliza no PROTOCOL):** o entry-point de F0 de [W] é o COWORK_NOTES **local**, não o do git; o do git é só o espelho que [CL] mantém. Aceita uploads + 1 linha de pedido como brief válido.

### Tela: Mobile/App (Início · Pedidos · Detalhe OS · Mais) — superfície NOVA (não é o shell desktop Cockpit V2)
### Prioridade: P1 · ### Persona principal: Larissa (balcão) + Wagner (no bolso) + Técnico Repair
### Brief-fonte: `uploads/Office_Impresso_Mobile_Design_System.md` (DS mobile completo) + 3 mockups ChatGPT (alvo visual)

### Contexto:
DS mobile **deliberadamente distinto** do desktop: dark-first, primário `#7A0B7E` (vinho/magenta), bright `#C85BFF`, Inter, Lucide, radius 16 card / 14 botão, bottom-nav com indicador hexagonal (marca cubo), KPI gradiente + watermark cubo 3–5%, FAB hexagonal. Tokens já extraídos 1:1 em `mobile-v5.css`. Existe `Mobile App v5.html` (5 telas **estáticas** num rail + análise comparativa). **Gap:** não é navegável.

### Pedidos:
- [x] **Protótipo INTERATIVO** num só device: bottom-nav troca de tela, tap no card de OS → Detalhe, voltar, FAB, tema dark/light persistido. (entregue: `Office Impresso Mobile.html`)
- [ ] (futuro) Splash/login · Oficina kanban real · Pessoas/CRM · busca funcional.

### Restrições (do DS):
- ❌ logo dentro da nav · ❌ neon/glow pesado · ❌ grids desktop (usar cards responsivos) · ❌ chat como dashboard primário · touch ≥44px · WCAG AA · nunca cor sozinha pra status.

### Não-fazer:
- ❌ NÃO é o shell `oimpresso.com.html` (superfície diferente, paradigma mobile) · ❌ não criar `vN.html` (1 doc canônico mobile; v5 = análise estática, fica como histórico com lápide).

> ⚠️ **Divergência consciente vs DS v4 desktop** (IBM Plex · roxo oklch 295 · radius 6–8): o DS mobile usa Inter · `#7A0B7E` · radius 14/16. Isto é **decisão de [W]** (brief mobile próprio) — quando virar F3, abre ADR registrando que a superfície mobile tem token-set próprio, OU [W] decide convergir. Não é erro de token; é sistema separado.

> **✅ [PROCESSADO 2026-06-01] 3 REGRAS DESTA SESSÃO** — [CL] traduziu vs `main` (`CODE_NOTES.md` handoff `metricas.html`): (1) no-duplicação de design L-21 + (2) trilha/lápide L-22 → `CLAUDE_DESIGN_BRIEFING §7.1` (+ forward-ref em `proibicoes.md`), branch `docs/design-no-dup-trilha`, **aguarda merge [W]** (Tier 0). (3) rename shell → **N/A**: o repo não guarda 1 HTML de shell vivo (só snapshots datados); `metricas.html` é Cowork-local. **Não reenviar.**

### 2026-06-01 — [✅ PROCESSADO 2026-06-01 · PR #2078 MERGED · NÃO FAZER] Gerador de review por tela (`design:review`) na charter page

> ✅ **FEITO** (MERGED `main` 98566bfb4 · PR #2078, fila COWORK #2, Tier 0, [W] aprovou ~10:45). Entregue: `review-gen.mjs` (`npm run design:review <tela>`) + `review-freshness.mjs` ratchet (`review-freshness-baseline.json`, espelha eslint-baseline) + Pest `DesignReviewFreshnessTest` (CI verde 1m55s) + `PROTOCOL §6` (+2 checks `design_review_missing`/`_stale`) + ADR proposta SEM número (`proposals/design-review-por-tela-charter-page.md`, [W] cunha). **1ª exec = `Jana/Pro.review.md` nota 88** (fechou o gap do #2069). Fase 2 (juiz-LLM R5/R8/R10 + nota holística + best_of_class) = custo/cadência real-mode → espera [W]. **Bloco abaixo mantido só como histórico da proposta.**

**Origem:** [W] 2026-06-01: *"vai precisar criar uma automatização para gerar um relatório com tarefas para você… qual teste/automatização? compare com os melhores, dê nota e o porquê, documente o que falta, faça o champion. acredito que deveria ser no charter page?"* — após confirmar Jana Pro F3 feito (e sem `Pro.review.md`).

**§10.4 Passo 0 (já feito vs `origin/main` fresco) — NÃO recriar, ESTENDER:** a máquina já existe meio-construída: `prototipo-ui/audit/score-mechanized.mjs` (Fase 1 regex R1-R4/R6/R7/R9 + `ds/*`, 1 `design-report.json`/tela, `measured_against_sha`), `consolidate.mjs`→`CONSOLIDADO.md` (média 86/100), `design-report.schema.json` (já tem `top_gaps{dim,best_of_class,fix,esforco}`), `CharterHealthChecker` (PR #2055), `<Tela>.review.md` append-only, `GOLDEN-REFERENCE.md §5` (benchmark vs melhores). **Gaps reais:** (1) **Fase 2 LLM nunca rodou em escala** → R5/R8/R10 + nota holística + `top_gaps.best_of_class` ficam vazios (nota = só conformidade-DS, mascarável); (2) **`.review.md` é one-off de 2026-05-17, nunca regenerado** → tela nova (ex: Jana Pro) nasce SEM relatório de tarefas; (3) **sem gatilho de frescor** — nada regenera o review quando o `.tsx` muda (`design_return_skipped`/G4 cobre SYNC_LOG, não review-vs-sha-da-tela); (4) charter + review + benchmark vivem em 3 arquivos soltos, não numa charter page única.

**Resposta ao [W] ("deveria ser no charter page?"): SIM.** O relatório de tarefas POR TELA já é o `<Tela>.review.md` ao lado do `<Tela>.charter.md` — isso É a charter page. O champion une os dois numa **charter page viva**: spec (charter) + nota viva + backlog de tarefas (review) + benchmark vs melhores. O rollup CROSS-tela continua no `CONSOLIDADO.md`/Governança (metricas), não na charter page.

**Champion (= o que falta fazer, em ordem barata→cara):**
1. **`design:review <tela>`** — Fase 1 (regex, já existe) **+ Fase 2 (juiz LLM):** preenche R5/R8/R10 + nota holística + `top_gaps` com `best_of_class`+`fix`+`esforco` (S/M/L) → escreve `<Tela>.review.md` **round N append-only** (nunca edita rounds antigos). O bloco "Top N recomendações" = o **backlog de tarefas do [CC]**.
2. **Gatilho no merge** — hook pós-merge regenera Fase 1 só nas telas tocadas + marca review stale (espelha o hook G4 do `ds:report:write`).
3. **Teste/automação (resposta direta ao [W]):** **Pest `DesignReviewFreshnessTest`** — toda `Pages/<Mod>/<Tela>.tsx` `status:live` tem `.review.md` cujo `measured_against_sha` == sha do último commit que tocou o `.tsx`; senão = stale/missing. **Pega o caso Jana Pro hoje** (page sem review). + **health-check `design_review_stale` / `design_review_missing`** (PROTOCOL §6).
4. **Backlog acionável** — `consolidate.mjs` agrega os `top_gaps` de todas as telas num **backlog priorizado** (worst-first × esforço) que [CC] lê via §10.2 (estende `DS_ADOCAO_INDICE` ou bloco novo). Tarefa = `(tela, dim, fix, esforço, nota-atual→meta)`.
5. **Fase 2 como ratchet** — nota só sobe (ADR 0236); cadência real-mode na régua que [W] paga (espelha o gate RAGAS da IA). 1ª execução = **Jana Pro** (cria `Pro.review.md` round 1).

**Nota do sistema atual vs estado-da-arte: ~6.5/10** — "ferramenta de elite construída e meio desligada" (mesmo padrão do champion-maker da IA). Detalhamento (nota+porquê+gaps) na view `rep-charter-page` do `metricas.html` deste bundle (benchmark: SonarQube/Code Climate gate · CodeRabbit/Danger PR-review · Chromatic/Percy visual-regression · Linear project health · RAGAS ratchet · Storybook Docs/Figma Dev Mode).

**Natureza §10.4 — evolução do PROTOCOL = Tier 0:** [CL] valida contra `main` sozinho (Passo 0; **estender** `score-mechanized`/`consolidate`/schema/`CharterHealthChecker`, **não recriar** — anti L-11), abre **ADR de evolução do loop** (mãe **0114** + **0236** ratchet + **0239** SSOT; **NÃO cunha número** — soberania [W], 0238) que formaliza: (a) o review por tela como **canal de retorno F1.5/F3.5→F0 no nível da TELA** (o §10.2 hoje é só no nível da worklist-DS); (b) §6 +2 checks (`design_review_stale`/`_missing`); (c) a charter page viva como artefato canônico. Abre PR e **espera [W]** (PROTOCOL/constituição-adjacente = não auto-merge). Retorno em `CODE_NOTES.md`.

---

### 2026-06-01 — [PROPOSTA §10.4 · método de design · Tier 0 processo · [W] "leia o 10.4 e o git"] Método "Migration → Tela" (schema → posicionamento de campo, pré-F1)

**Origem:** [W] 2026-06-01: *"a cada migration nova você analisa onde os campos devem ficar na tela… descreva e encaixe como isso deve funcionar nesse projeto, como seria o estado da arte."* Hoje o pulo migration→tela é manual e some na cabeça de quem desenha (F1 redescobre o domínio toda vez).

**§10.4 Passo 0 (já feito vs `origin/main` fresco):** li `PROTOCOL.md §10.4` + `CLAUDE_DESIGN_BRIEFING §4/§5` (Cockpit V2 ADR 0110) + as migrations reais do `Modules/ComunicacaoVisual/Database/Migrations/` (`comvis_orcamentos`, `cv_ordens_producao`, `cv_instalacoes`). **Não recriar:** o motor de score F1.5 e o `COMPARISON.md`/charter já existem — o método **alimenta** esses, não cria artefato novo (respeita G2). FSM = ADR 0143; multi-tenant = 0093.

**O método (entregue como VIEW no `metricas.html` Cowork, grupo "Métodos" — não `.html` novo, L-21):** dicionário de **17 arquétipos de coluna → zona Cockpit V2** (PK/`business_id`=sistema · identificador humano=título+1ª col · FK=combobox/avatar · status enum=pill warm · `current_stage_id`+GuardsFsm=stepper read-only · enum-que-ramifica=segmented · dinheiro=KPI+footer · dimensão física=input inline · calculado/generated=read-only "auto" · data-evento=ageing/SLA · marco-workflow=timeline · JSON=sub-editor (nunca textarea) · mídia/URL=image-slot+consent LGPD · geo POINT=pin/GPS · texto longo=drawer · `ordem`=drag-handle · `timestamps`/`softDeletes`=trilha/arquivar). Exemplo trabalhado: `comvis_orcamentos`+`_itens` → tela mestre-detalhe com cálculo m² ao vivo na linha.

**Encaixe no loop:** lente **pré-F1** (F0.5 Schema→Tela). Gatilho = migration entra em `Modules/<Mod>/Database/Migrations/` (ou [W] aponta) → [CC] roda o mapa antes do F1 → o mapa vive **dentro do `COMPARISON.md`/charter da tela** (não arquivo novo). Retorno §10.2 do [CL] pode sinalizar "tela X tem migration sem mapa".

**Natureza §10.4:** adicionar método ao processo = **Tier 0**. [CL] valida contra `main` sozinho (Passo 0; não duplicar o que `mwart-comparative`/`design-deep-analysis` já fazem — o método é a etapa schema→arquétipo que falta **antes** do score, não um 2º motor de score), abre ADR de evolução do loop (**mãe ADR 0114 + 0110 Cockpit V2**; **NÃO cunha número** — soberania [W], 0238) + bullet em `CLAUDE_DESIGN_BRIEFING` (novo §: "Schema→Tela: dicionário de arquétipos"), e **espera [W]** (processo/constituição-adjacente = não auto-merge). Fonte-espelho do conteúdo: view `rep-schema-tela` no `metricas.html` deste bundle. Retorno em `CODE_NOTES.md`.

---

### 2026-06-01 — [✅ PROCESSADO 2026-06-01 · BRIEFING §7.1 · aguarda merge [W]] Estender a proibição "não criar arquivo sem checar duplicação" pra artefatos de DESIGN

> ✅ **[CL] traduziu** (branch `docs/design-no-dup-trilha`) → bullet em `CLAUDE_DESIGN_BRIEFING §7.1` (ref ao pai em `proibicoes.md`, não duplica). Aguarda merge [W] (Tier 0). **Não reenviar.**

**Origem:** [CC] criou 2 `.html` novos na raiz pro mesmo tema (`Governança - Avaliação Champion` + `Governança Scorecard`) — o 2º é variação/evolução do 1º → devia ser **1 doc iterado**. [W] 2026-06-01: *"a regra de não duplicar e durar no tempo está ativa… foi criado um arquivo novo de layout e não foi colocado no único layout permitido, isso é falha"* + *"toda a regra vive no git"*. Hoje a regra só vive no `CLAUDE.md` do Cowork + na L-21 local → **advisory que vaza**.

**Passo 0 (já feito vs `origin/main`):** `memory/proibicoes.md` (Tier 0) **JÁ TEM a regra-irmã**, escopada a `memory/`: *"NUNCA criar arquivo em `memory/` sem Glob/Grep antes… se já existe similar, EDITA o existente — não cria novo."* → **NÃO criar proibição nova: ESTENDER** essa pra cobrir artefatos de design (anti L-11/L-20). PROTOCOL §8 tem anti-padrões do loop, mas não este.

**Onde vive (resposta ao [W]: "as proibições são do Code — [CC] precisa das suas?"):** SIM, e o home já existe. A proibição de **design** é lane de [CC] → **home primário = `prototipo-ui/CLAUDE_DESIGN_BRIEFING.md §7` (proibições de design, já em git)**, não `proibicoes.md` (que é cross-role/Code). O **princípio universal** "não duplicar arquivo — edita o existente" já está em `memory/proibicoes.md` (escopo `memory/`) como **pai** → **referenciar, não duplicar**. [CL] adiciona o bullet em BRIEFING §7:
> ⛔ **Não criar `.html`/artefato de design novo sem checar duplicação (`Glob`/grep do tema) antes.** Tela/módulo/**variação** de ERP = **rota ou Tweak no layout único `prototipo-ui/Oimpresso ERP - Chat.html`**, nunca arquivo novo. Relatório/avaliação meta = **1 tema = 1 doc**; se já existe irmão, **edita o existente** — **nunca `vN.html`**. Variação = Tweak (`useTweaks`), não arquivo. (Espelha a regra de `memory/`, agora pra design — origem: 2 HTMLs de governança duplicados 2026-06-01.)

**Natureza §10.4:** proibição de design = lei = **Tier 0**. [CL] valida contra `main` sozinho (lança em **BRIEFING §7**, referencia o pai em `proibicoes.md`, não duplica; não cunha número de ADR — 0238), abre PR e **espera [W]**. Conteúdo-fonte: `memory/LICOES_CC.md` **L-21** (cache local; o canon-de-registro = BRIEFING §7).

---

### 2026-06-01 — [⏭️ PROCESSADO 2026-06-01 · N/A] Rename do shell `oimpresso.com.html` + relatórios consolidados em `metricas.html`

> ⏭️ **[CL]: N/A.** Não há shell HTML vivo no repo (só snapshots datados em `_arquivo/`); `metricas.html` é Cowork-local (decisão sua, chat33). Nada a renomear no git. O bullet de §7.1 usa "layout único do shell" sem citar arquivo inexistente. **Não reenviar.**

**O que [CC] já fez no Cowork (reversível):** (1) `Oimpresso ERP - Chat.html` → **`oimpresso.com.html`** (só o nome; refs locais nos arquivos-espinha já varridas). (2) Criado **`metricas.html`** — hub único (menu lateral + `iframe srcdoc`) que **embute** os relatórios meta: Governança (Scorecard consolidado, que absorveu o Champion), Estrutura de IA, Diagnóstico. Os 5 HTMLs originais → `_arquivo/relatorios/` (faxina-pattern, não deletados). Fecha a duplicação da L-21: **2 layouts vivos** = app + métricas; relatório novo = view no `metricas.html`, nunca arquivo novo.

**Bridge pro [CL] (evita "Code se perde" Cowork↔repo · PLANO_ORGANIZACAO_CASA §11 · L-18):** renomear no repo `prototipo-ui/Oimpresso ERP - Chat.html` → `oimpresso.com.html` + atualizar `prototipo-ui/README.md`, `prototipo-ui/HANDOFF.md` e o **ponteiro do Handoff entry-point** (senão Share→Handoff aponta pra arquivo inexistente). ADR 0039 cita o nome antigo como "protótipo de referência" = registro aceito → **documenta o rename** (append-only, NÃO muta o ADR).

**Natureza §10.4:** rename do entry-point + Handoff = **Tier 0** (PR + espera [W]). `metricas.html` é Cowork-local (relatórios meta, não vai pro repo); só o shell `oimpresso.com.html` espelha no `prototipo-ui/`.

---

### 2026-06-01 — [✅ PROCESSADO 2026-06-01 · BRIEFING §7.1 · aguarda merge [W]] Regra da Trilha do Tempo (evolução no fim + lápide de arquivamento)

> ✅ **[CL] traduziu** → bullet em `CLAUDE_DESIGN_BRIEFING §7.1` (trilha/lápide L-22) + forward-ref em `proibicoes.md`. Branch `docs/design-no-dup-trilha`, aguarda merge [W]. **Não reenviar.**

**Origem:** [CC] consolidou relatórios no `metricas.html` mas moveu os originais **sem deixar rastro**. [W]: *"não colocou a regra do tempo no fim do arquivo, onde mostra a evolução do arquivo e indica para onde foi arquivado o anterior."* É a **forma concreta** do L-07 (append-only/lápide) — hoje no canon existe como princípio, mas sem a regra do "rodapé de evolução + ponteiro de arquivo".

**Passo 0:** `memory/proibicoes.md` já tem append-only (Memória/governança: "ADRs são append-only", "registro não deleta"). O **rodapé de evolução + lápide de arquivamento** é a concretização que falta → **estender, não duplicar**.

**O que [CL] adiciona (BRIEFING §7 p/ artefato de design + 1 ref em `proibicoes.md`):**
> ⛔ **Trilha do tempo (evolução + lápide).** Todo artefato vivo (HTML de app/relatório, doc canônico) carrega **no fim** um bloco `Evolução`/`Trilha do tempo` **append-only**: `data · o que mudou · o que superseder · → pra onde o anterior foi arquivado`. Ao **mover/consolidar**, deixa **lápide** na origem ou em `_arquivo/<pasta>/INDEX.md` (origem→destino + o que substituiu). Nada some sem rastro legível no próprio arquivo. (Concretiza L-07.)

**Já aplicado por [CC] (exemplos vivos pra [CL] espelhar o formato):** `metricas.html` (comentário de trilha antes do `</body>`) + `_arquivo/relatorios/INDEX.md` (lápide-índice). **Natureza §10.4:** append-only = Tier 0 → PR + espera [W].

---

### 2026-06-01 — [✅ FEITO · NÃO FAZER · histórico] Jana Pro — paywall/upsell → Inertia (F3)

> ✅ **F3 CONCLUÍDO** (vs `origin/main`): `Pages/Jana/Pro.tsx` (rota `/ia/pro`, `ProController::index`) + `Pro.charter.md` `status: live` (related_adrs 0140/0110/0190/0093), criados por [CL] 2026-06-01. CTA é mock client-side (billing real = Sprint JANA-B, ADR 0140, gated [W]). **Residual:** sem `Pro.review.md` — captura no gerador de review abaixo (proposta §10.4). Bloco abaixo mantido só como histórico do pedido.

**Origem:** champion-maker nº1 da Avaliação de Estrutura de IA ([CC] 2026-06-01) — o segundo canary (Larissa biz=4) exige paywall Jana Pro (BRIEFING §6 + ADR 0105/0140). [W] aprovou **F1 (score 90)** + **F2 (screenshot)**.

**Entrega [CC] (no Cowork, pronta pra traduzir):**
- `Jana Pro - Paywall CC.html` — protótipo F1 (Cockpit V2 · roxo `oklch(0.55 0.15 295)` · status warm · rounded-md · sem emoji · PT-BR · densidade 1280px Larissa)
- `prototipos/jana-pro/COMPARISON.md` — 15 dimensões (9 obrigatórias ✱)
- `prototipos/jana-pro/critique-score.json` — score **90**, gate PASS, 2 fixes med aplicados
- `prototipos/jana-pro/F2-aprovado.png` — screenshot aprovado por [W]

**F3 (quando [W] liberar) — o que [CL] traduz:** rota `/jana/pro` (ou `/copiloto/pro`) · `resources/js/Pages/Jana/Pro.tsx` (ou Copiloto/) · seções: hero + card de prova (3 ângulos faturamento do `ChatCopilotoAgent` — bruto/líquido/caixa) + comparação Grátis×Pro (6 linhas) + pricing R$ 49 + trust (isolamento Tier 0 ADR 0093 · LGPD retention.php · BR). CTA `Ativar Jana Pro` → billing (`payment-gateways` / ADR 0140). Tier 0 (implementa, não recria; não cunha ADR) → PR + espera [W].

---

### 2026-06-01 — [🟡 PREP EM MAIN #2073 · falta ENABLE Tier 0 [W]] IA champion-maker nº2 — Purge LGPD real + ligar OTel collector

> 🟡 **[PREP MERGEADO · PR #2073]** a base aditiva já está em `main`; falta só o **ENABLE** (custo/infra) que **espera [W]**. Não reconstruir o prep.

**Origem:** `Estrutura de IA - Avaliação CC.html` ([CC] 2026-06-01) — a IA está super-engenheirada com **ferramentas de elite construídas e desligadas**. Champion não deixa máquina cara no escuro.

**§10.4 Passo 0 primeiro:** ancorar em `origin/main` fresco. Conferir o que **já existe vs é gap** (não recriar):
- **OTel:** 46 Services já emitem span (`OtelHelper::spanBiz`) — instrumentação PRONTA. Falta o **collector CT 100 ligado em prod** (BRIEFING §6, hoje disabled) = **infra/custo = Tier 0 [W]**.
- **LGPD purge:** `retention.php` declara retenção (PRONTO), mas o **job real `jana:retention-purge`** é **gap** (BRIEFING §5 #1, ~4h) — **[CL] constrói** o job (aditivo) + Pest. O **enable em prod** (`JANA_RETENTION_ENABLED=true`, pós-canary) = **Tier 0 [W]** (BRIEFING §6).

**Entrega [CL]:** (a) construir `jana:retention-purge` (lê `retention.php`, dry-run default, idempotente, multi-tenant Tier 0 ADR 0093) + testes → PR aditivo; (b) preparar o ligamento do collector como mudança de infra/env documentada, **sem ativar** — abre PR e **espera [W]** aprovar custo (Jaeger storage) + enable. NÃO inventa retenção nova (usa a declarada). Retorno `CODE_NOTES.md`.

---

### 2026-06-01 — [🟡 PREP EM MAIN #2073 · falta ENABLE Tier 0 [W]] IA champion-maker nº3 — Apertar o gate RAGAS (ratchet)

> 🟡 **[PREP MERGEADO · PR #2073]** golden aditivo / base já em `main`; falta a cadência real-mode + subir threshold (tooling + $) que **espera [W]**. Não reconstruir o prep.

**Origem:** mesma avaliação — o eval roda **mock por padrão + real só no cron semanal**, thresholds conservadores (0.80/0.75), golden de 30Q. Champion trata eval como **ratchet que aperta com o tempo**.

**§10.4 Passo 0 primeiro:** o gate `jana-ragas-gate.yml` **JÁ EXISTE** (faithfulness 0.80 / relevancy 0.75, mock default, real via secret) — **estender, NÃO recriar** (anti L-11). Golden hallucination já em 30Q (CHANGELOG Wave 25).

**Entrega [CL] (duas naturezas):**
- **Aditivo (autônomo):** expandir o golden — cada bug real de resposta vira caso de teste (30Q → cresce). Loop autônomo, CI verde.
- **Tier 0 (espera [W], é tooling + dinheiro):** subir cadência real-mode (semanal → diário, ~R$ 1,80/mês BRIEFING §5 #4) + subir thresholds conforme a baseline matura (0.80→0.83 etc, só se a baseline atual aguenta sem ficar vermelha). Mexer em lógica de gate/threshold = Tier 0 → abre PR e **espera [W]**.

**Validar antes:** rodar o eval na baseline atual pra provar que o threshold novo passa (não subir régua que quebra o próprio CI). Retorno `CODE_NOTES.md`.

---

### 2026-06-01 — [🟡 PREP EM MAIN #2073 · falta ENABLE Tier 0 [W]] IA champion-maker nº4 — Resiliência Meilisearch (ponto único de falha)

> 🟡 **[PREP MERGEADO · PR #2073]** degradação graciosa / health-check já em `main`; só a redundância de infra (réplica/HA = $) **espera [W]**. Não reconstruir o prep.

**Origem:** mesma avaliação — memória + RAG dependem do **Meilisearch num único container CT 100**; o próprio BRIEFING §8 marca como risco 🔴. A Jana deve responder **mesmo com a busca semântica fora do ar** (só sem recall), não quebrar.

**§10.4 Passo 0 primeiro:** o `NullMemoriaDriver` fallback **JÁ EXISTE** pra dev (BRIEFING §8) — **estender pra prod, NÃO recriar**. Conferir no `main` o driver atual + como o chat resolve recall.

**Entrega [CL] (duas naturezas):**
- **Aditivo (autônomo):** degradação graciosa em prod — se o Meilisearch não responde (timeout/erro), o chat cai pro caminho sem-recall em vez de estourar exceção; + health-check `jana:health-check` que **alerta antes de cair** (latência/erro do índice). Código + Pest, loop autônomo.
- **Tier 0 (espera [W], só se mexer em infra):** se a solução robusta exigir réplica/HA do Meilisearch (custo de infra) → documenta a opção e **espera [W]** decidir custo. A degradação graciosa (software) **não** espera; a redundância (hardware) espera.

**Validar antes:** simular Meilisearch down e provar que o chat responde (sem recall) em vez de 500. Retorno `CODE_NOTES.md`.

---

### 2026-06-01 — [✅ MERGED — PR #2064 · NÃO FAZER] G4 retorno automático (`design-return-gate.yml`)

> ✅ **FEITO** ([W] mergeou). Workflow `.github/workflows/design-return-gate.yml` no `main` + check `design_return_skipped`. Ironia confirmada: o #2064 entrou **sem** atualizar `SYNC_LOG`/`HANDOFF` (G4 só vale do **próximo** merge em diante) — exatamente o furo que ele existe pra fechar. G5 já era 100% (Stylelint #2054 + ESLint 0209). **Histórico abaixo.**

### 2026-05-31 — [histórico] Ratchets vivos — §10.2 retorno vira HOOK + health-check (G4) e lint anti-drift (G5)

**Origem (incidente real, 2026-05-31):** [W] **teve que mandar o [CL] avisar o [CC]** que um amendment já estava implementado ("não refaça"). Ou seja: [W] virou **carteiro de status** — o anti-pattern nº1 do `CHARTER_GOVERNANCA_W.md`. O §10.2 (retorno `[CL]`→`[CC]` a cada merge) **já é canon no PROTOCOL**, mas é **passo lembrado, não hook** → sob contexto, foi pulado. Conserto não é "lembrar melhor" (culpa), é **trava que falha** (gate).

**G4 — §10.2 vira automático (não lembrado):**
1. **Hook pós-merge** que executa, sem [CL] precisar lembrar: `npm run ds:report:write` (regenera checklist em `DS_ADOCAO_INDICE.md`) + append `SYNC_LOG.md` (`YYYY-MM-DD HH:MM [CL] <fase> <módulo> merged · ds/*: a→b · PR #N`) + sobrescreve `HANDOFF.md`. Idealmente git hook / CI step pós-merge, não passo manual.
2. **Novo check em `jana:health-check` (PROTOCOL §6): `design_return_skipped`** — conta merges de `prototipos/*` ou `Pages/<Mod>` **sem** atualização correspondente nos 3 canais (compara timestamp do merge vs último append do `SYNC_LOG`/regen do `ds:report`). ≥1 → ALERTA. É o que pega o furo deste incidente **antes** de [W] virar carteiro.

> **G5 RESOLVIDO — não refazer:** ESLint `ds/*` já ativo (ADR 0209) + **Stylelint `.css` PR #2054 MERGED** (`stylelint.config.mjs` 4 regras + gate CI). G5 = 100%. **Sobra real deste item = só o G4 acima** (o canal `ds:report`/reancoragem existe, mas o **gatilho automático no merge não** — prova: incidente 2026-05-31 em que [W] teve que avisar o [CC] na mão).

**Natureza §10.4:** tooling/governança = **Tier 0** → [CL] valida contra `main` sozinho (Passo 0; `ds:report`/`jana:health-check`/specs de lint **já existem** — **estender**, não recriar, anti L-11), especifica + abre PR e **espera [W]**. Retorno (ironia intencional: pelo próprio §10.2) em `CODE_NOTES.md`.

---

### 2026-05-31 — [✅ MERGED — PR #2061 + ADR 0242 · NÃO FAZER] Charters de governança ([W] + agentes)

> ✅ **FEITO** ([W] aprovou 01:33). `CHARTER_GOVERNANCA_W.md` + `CHARTER_CHAMPION_AGENTES.md` agora em `prototipo-ui/` no `main`; **ADR 0242** os formaliza como evolução de 0079/0094/0238/0241. Em `prototipo-ui/` (não `_DesignSystem/`) de propósito — evita design-index-gate "órfão". **Histórico abaixo.**

### 2026-05-31 — [histórico] Charter de Governança de [W] — oficializar papel + champion test

**Origem:** [W] 2026-05-31: *"como posso champion na governança?"* → pediu memória durável do próprio papel (charter-first aplicado a um PAPEL, não a uma tela). [W]: *"esse é muito importante."*

**O que oficializar:** dois charters irmãos (na raiz do bundle):
- `CHARTER_GOVERNANCA_W.md` — define [W] como **soberano de Tier 0** de um loop 0-humano (FAZ só Tier 0 + subjetivo + regra-acima; NÃO vira carteiro / não responde o que o git responde / não microgerencia F1/F3; **Champion Test**; anti-patterns).
- `CHARTER_CHAMPION_AGENTES.md` — define o champion de **[CC]** (design F1), **[CL]** (code F3) e **[CD]/[CA]** (crítica+a11y): Mission/Goals/Non-Goals/Champion Test/anti-patterns de cada papel + o fio comum (passa o gate de 1ª · fecha o loop no verificável · propõe não impõe · erro vira gate).

**Destino no repo (dois, naturezas diferentes — segue o padrão charter-first do Financeiro):**
1. **ADR de papel** — formalizar o charter como decisão. **Mãe = ADR 0079 (7 camadas governança) + 0094 (Constituição V2) + 0238 (soberania) + 0241 (loop autônomo).** É **evolução/aplicação**, não reescrita — cruzar contra `main` (§10.4) pra não duplicar o que 0079/0094/0238 já dizem. **[CC] NÃO cunha número** (ADR 0238) — número livre no git.
2. **`CHARTER_GOVERNANCA_W.md` + `CHARTER_CHAMPION_AGENTES.md`** colados onde a memória-por-papel/charter vive no `main` (par dos `*.charter.md`; se não houver pasta de papel, segue a convenção do `main` e ajusta o link no ADR). Os dois entram juntos — são irmãos.

**Natureza §10.4:** governança = **Tier 0**. [CL] valida contra o `main` sozinho (não duplicar 0079/0094/0238; não cunhar número), abre PR e **espera [W]** (não auto-merge — é constituição-adjacente). Retorno em `CODE_NOTES.md`.

---

### 2026-05-31 — [PROPOSTA §10.4 · aditivo] README handoff-entry marker — falta no `prototipo-ui/README.md` canônico do `main`

**Origem:** o próprio `CharterHealthChecker` (PR #2055), na 1ª execução, flagou `readme_handoff_block_missing`: o `prototipo-ui/README.md` **canônico no `main` não tem** o marcador `<!-- HANDOFF-ENTRY -->` + o bloco "🤖 Claude Code — COMECE AQUI" que o STATUS.md (L-18) exige. Sem ele, um Share→Handoff entrega o projeto mas o [CL] não acha a fila de tarefas. O check já se provou.

**Fix (conteúdo [CC]):** espelhar pro `prototipo-ui/README.md` do `main` o bloco que já existe no README do bundle Cowork — marcador `<!-- HANDOFF-ENTRY -->` no topo + seção "COMECE AQUI" (ordem de leitura obrigatória → `COWORK_NOTES.md → 📥 Pendentes`). Preservar o marcador em qualquer regeneração futura do README (L-18).

**Natureza §10.4:** aditivo (doc), não toca código de produção. [CL] aplica via loop autônomo (CI verde). O bloco-fonte está no `README.md` da raiz deste bundle. Retorno em `CODE_NOTES.md`.

---

### 2026-05-31 — [PROPOSTA §10.4 · Tier 0 governança · [W] AUTORIZOU "formalize"] Peer-review + override por alta-confiança (≥98%)

> ⚠️ **NÃO está feito.** [CL] declinou (CODE_NOTES: *"já-canon 0238/0241, não fiz"*) — mas [W] **autorizou formalizar** como ADR próprio. "Coberto por outro ADR" ≠ "formalizado"; declinar algo que [W] mandou formalizar é decisão de [W], não de [CL]. **PENDENTE.**

**Princípio a formalizar (ADR de evolução do loop · mãe 0114; número livre no git, [CC] NÃO cunha — ADR 0238):**
1. **Sem imposição unilateral** — quem vê melhoria (design/código/processo) propõe ao par, que avalia. Peer-review [CC]↔[CL] nos dois sentidos, qualquer assunto.
2. **Convergência decide:** concordam + não-Tier-0 → segue (autônomo); discordam ou Tier 0/subjetivo → escala [W].
3. **Override soberano de [W]** vence consenso de agentes a qualquer hora (ADR 0238); palavra final sempre de [W].
4. **Exceção alta-confiança (≥98%):** evidência objetiva citável (git/canon/teste) + não-Tier-0 + reversível; override logado; erro reincidente → perde o direito no assunto.

**Natureza §10.4:** governança = Tier 0, [W] **JÁ autorizou** ("formalize"). [CL] numera + cruza `main` (é evolução do 0114/§10.4, não duplica 0238). Abrir PR; com OK explícito de [W], segue o aceite. Retorno em `CODE_NOTES.md`.

---

### 2026-05-31 — [PROPOSTA §10.4 · F0 auditoria-mapa] Auditoria PARALELA read-only — score de cada tela vs GOLDEN-REFERENCE (gera `design-report.json`)

**Origem:** [W] quer usar capacidade ociosa (budget/agentes) em paralelo SEM quebrar o ratchet serial do `ds/*`. Esta é a frente **paralela-segura** (read-only, aditiva, 1 tela = 1 arquivo = 0 colisão) — o complemento da migração, que continua **serial** no loop autônomo. É também o "Disparo F0 = auditoria-mapa primeiro (medir antes de mexer)" da proposta G1–G6 abaixo, e materializa o **G2** (schema único `design-report.json`).

**Por que paraleliza (e a migração não):** auditar = ler + pontuar + escrever 1 JSON próprio por tela. Não toca `eslint-baseline.json`, `design-system.css`, nem `SYNC_LOG`. N agentes podem rodar juntos; só a **consolidação do índice** (passo final) é serial. Respeita `PROTOCOL §8` (o backpressure é de **F3**, não de auditoria read-only).

**O job (1 agente = 1 ou N telas):**
1. Para cada página em `resources/js/Pages/<Mod>/<Tela>.tsx`, rodar as **10 regras binárias** do [`GOLDEN-REFERENCE.md §2`](GOLDEN-REFERENCE.md) (R1 header sticky 24px · R2 pills rounded-full · R3 KPIs gigantes 4-col · R4 escala warm semântica · R5 cabe 1280px · R6 campos `@/Components/ui` · R7 `<Card>`/rounded-lg · R8 footer sticky 1× · R9 erro inline `<FieldError>` · R10 escala de espaçamento).
2. Anexar score 15-dim (`CLAUDE_DESIGN_BRIEFING §5`) + contagem `ds/*` real do módulo (`npm run ds:report -- --json`, recortar por módulo) + severity a11y se houver `a11y-report.md`.
3. Escrever **`prototipos/<tela>/design-report.json`** (schema G2): `{ tela, golden_rules: {R1..R10: pass|fail+evidencia}, score_15dim, ds_count, a11y, gap_pra_golden, proximo_passo }`.

**Telas (placar HANDOFF · ~10 módulos, expandir por página Index/Create/Edit/Show):** Sells · RecurringBilling · OficinaAuto · Repair · Purchase · Admin · Whatsapp · Settings · Financeiro · Cliente (+ fora-da-fila que o eslint apontar). A golden `Sells/Create` é o baseline 10/10 — auditá-la confirma o gabarito.

**Consolidação (passo serial, 1 agente no fim):** agregar os `design-report.json` num placar único — **estender o `DS_ADOCAO_INDICE.md`** (não criar arquivo novo) com a coluna "golden 10-rules X/10" ao lado do `ds/*`. Saída = o mapa "quem está abaixo da golden e por quê", que prioriza a fila serial.

**Natureza §10.4:** proposta + F0 read-only. [CL] valida sozinho contra o `main` (cruzar se `design-report.json`/`ds:report --json` já existem antes de recriar schema; não duplicar o que o `mwart-comparative` já gera). **NÃO mexer em `PROTOCOL.md`/skills.** **NÃO mergear** mudança de lei. O entregável é dado (JSONs + índice), não código de produção — pode mergear via loop autônomo (CI verde) por ser aditivo. Devolver o placar consolidado em `CODE_NOTES.md`.

---

### 2026-05-31 — [PROPOSTA §10.4 · processo] Otimizar as ROTINAS de design — consolidar, medir, automatizar o retorno

**Origem:** [W] pediu ("quais otimizações nas rotinas de design? agrupe e gere o que precisa otimizar — tem que ensinar o Code a ser mais otimizado no design"). Diagnóstico feito lendo as rotinas reais no `main`: `PROTOCOL.md` (F1.5/F3.5), `.claude/runbooks/design-sync.md`, skills `mwart-comparative`, `design-deep-analysis`, `comparativo-do-modulo`, `cowork-prototype-replication`, `cockpit-runbook`, `charter-first/write`, e o trio de retorno §10.2 (`ds:report` + `DS_ADOCAO_INDICE` + `SYNC_LOG`/`HANDOFF`).

**As 6 otimizações (agrupadas por causa-raiz):**

**G1 · Rotinas redundantes — UM motor de score, não 4.** `mwart-comparative`, `design-deep-analysis`, o gate F1.5 do PROTOCOL e o §A.3 do design-sync **todos** rodam as mesmas 5 skills `design:*` (critique+system+ux-copy+accessibility-review+research-synthesis) e pontuam as mesmas 15 dimensões. Custo: as 5 skills rodam 3-4× por tela. **Otimização:** uma skill canônica `design-score` (motor único do framework 15-dim) parametrizada por `{persona?, gate:F1.5|deep|sync}`. As outras rotinas **chamam** ela e leem o cache, não re-rodam. Ensina o Code: não invocar `design:*` em 3 lugares — produzir 1 `design-report.json` e reusar.

**G2 · Artefatos de gate dispersos — UM schema.** Hoje: `critique-score.json` + `a11y-report.md` + `<tela>-visual-comparison.md` + `COMPARISON.md` vivem separados; `jana:health-check` (PROTOCOL §6) e a dimensão "Adoção DS" do GovernanceV4 leem fontes diferentes. **Otimização:** consolidar em **`prototipos/<tela>/design-report.json`** (score 15-dim + a11y severity + ds/* restante + critique categórico) como fonte única machine-readable. Ensina o Code: health-check, governance e worklist leem 1 arquivo.

**G3 · Dobrar os gates-ferry no produtor (já proposto acima nesta fila).** F1.5 [CD] e F3.5 [CA] viram **auto-check** de quem produz, mantendo a trava numérica (≥80 / WCAG AA). 7 hops → 4. Liga direto com G1: se há 1 motor de score, o auto-check é só rodá-lo antes de entregar.

**G4 · Retorno automático, não manual — a dívida real.** PROTOCOL §10 já diagnosticou (HANDOFF 15d stale, [W] virou carteiro). O conserto NÃO é nova rotina: é o Code **executar `npm run ds:report:write` + append `SYNC_LOG` + sobrescrever `HANDOFF` a CADA PR mergeado**, idealmente via hook pós-merge, não passo lembrado. Ensina o Code: o loop só fecha quando o estado está **commitado** (é o que o [CC] lê via MCP — §10.3).

**G5 · Guard de drift = ratchet, não conselho.** As proibições visuais (sem hex cru, radius fora da escala) são advisory e não seguram (vide drift azul-220→roxo). **Otimização:** ligar ESLint `ds/*` + Stylelint `.css` (spec já pronta em `prototipo-ui-patch/REGRAS_*`) com baseline + gate falha em delta>0. Ensina o Code: o DS não pode regredir porque o CI barra.

**G6 · Code não regenera o já-feito.** Estende o princípio "só faz o que está ☐" (§10.2 worklist) de "adoção DS" pra **todas** as rotinas: o `design-report.json` (G2) alimenta um checklist por tela × gate, e o Code só roda o gate pendente.

**Sequência proposta (barata→cara, cada uma destrava a próxima):** G4 (retorno automático, conserta a dor de hoje) → G5 (ratchet, trava o que já foi corrigido) → G2 (schema único) → G1 (motor único de score) → G6 (worklist por gate) → G3 (dobrar ferries, depende de G1).

**Natureza:** proposta de processo/governança. NÃO mexer em `PROTOCOL.md`/skills sem [W]. [CL] valida contra o `main` sozinho (§10.4): cruzar se `ds:report`/lint specs já existem antes de recriar, não duplicar skill que já faz o score, abrir ADR de evolução do loop (mãe ADR 0114) — **não mergeia** sem OK de [W]. Antes de consolidar qualquer rotina, **medir primeiro** (ver pergunta de disparo abaixo).

---

### 2026-05-31 — [PROPOSTA §10.4 · processo] Simplificar a cadeia: papel = responsabilidade, não balsa

**Origem:** [W] pediu ("como deveria ser?"). Diagnóstico: os 6 papéis estão certos como *lentes*, mas o custo real é o **nº de transportes manuais** ([W] vira carteiro a cada hop: F0 escreve · F2 aprova screenshot · transporta · F4 mergeia). Cerimônia demais pra time de 1 pessoa + instâncias Claude.

**Proposta de evolução do `PROTOCOL.md` (Code formaliza via ADR, sob OK de [W]):**
1. **Dobrar F1.5 [CD] e F3.5 [CA] para dentro de quem produz** — viram **checagem automática** que [CC] (visual/crítica) e [CL] (a11y) rodam em si mesmos antes de entregar, não fases-ferry separadas. Mantém os gates de qualidade (nota ≥80, WCAG AA), remove 2 transportes. Override: se nota <70 ou a11y crítica, aí sim escala pra revisão dedicada.
2. **[W] tem só 2 momentos:** *briefar* (início) e *aprovar+mergear* (fim). **F2 (aprovação visual) acontece no preview do Cowork**, onde o design já vive — não numa fase à parte.
3. **Ponte em lote:** um `COWORK_NOTES.md` carrega **N pendentes**; [CL] processa a fila inteira numa sync, marca cada `[PROCESSADO]`. Menos colagens.
4. **Fronteiras mantidas:** CC projeta · CL aplica · W decide. Numerar ADR / constituição = [W] (soberania).

**Cadeia nova proposta (de 7 hops → 4):**
`F0 [W] brief` → `F1 [CC] design + auto-crítica + auto-a11y` → `F2 [W] aprova no preview Cowork` → `F3 [CL] aplica no repo (+ a11y check) → PR` → `F4 [W] merge`.

**Natureza:** proposta de processo. Não mexer em ADR/PROTOCOL sem [W]; [CL] valida contra o `main`, abre ADR de evolução do loop (referência: ADR 0114 mãe), **não mergeia** sem OK. Risco a pesar: dobrar [CD]/[CA] em auto-check pode reduzir rigor se [CC]/[CL] forem complacentes — manter o gate numérico como trava objetiva.

---

### 2026-05-31 — [PROPOSTA §10.4 · governança] Persistir a "REGRA DE OURO" como ADR + proibição no git

**Por quê:** erro recorrente meu ([CC]) — reinventar canal que já existe (`PARA_O_CODE.md` vs `COWORK_NOTES.md`), prometer commit/PR que não consigo, marcar proposta como lei. Lições L-01/06/08/09/11 são advisory e não estão segurando. [W] pediu regra durável e persistida no git.

**Proposta pro [CL] formalizar (numerar ADR + cruzar `main`):**
1. **ADR novo** (número livre no git — eu NÃO cunho, L-09/ADR 0238) registrando a **REGRA DE OURO** (pré-flight de 4 gates; ver topo do `STATUS.md` local). Status: aceito sob OK de [W].
2. **Append em `prototipo-ui/` / `memory/proibicoes.md`** (canônico, lido no início de todo chat) as 4 proibições em linguagem dura:
   - ❌ criar arquivo de processo/ponte sem `ls`+grep da raiz (canal = `COWORK_NOTES.md`, nunca duplicar)
   - ❌ prometer commit/PR/merge/"tarefa no Code" (read-only no git; ponte = [W] cola 1x)
   - ❌ marcar proposta como decisão firme (tudo é proposta §10.4; [CL] valida contra `main` sozinho)
   - ❌ re-tematizar token sem provar fonte efetiva (`getComputedStyle`) + grep de todas as defs live (L-10)
3. **Eco no `CLAUDE.md` raiz** (via PR, não edição direta) linkando o ADR.

**Natureza:** proposta de governança. [CL] valida e numera; **não mergear** sem OK de [W]. Mecanismo = mesmo de ADR 0238 (soberania): [CC] propõe, Code numera+versiona, [W] aprova.

---

### 2026-05-31 — [PROPOSTA §10.4] Guard de lint anti-drift (ESLint ds/* + Stylelint .css)

**O que:** ligar os dois guards de lint que travam o drift DS↔repo. **Já especificados** — não reescrever, só aplicar:
- **TAREFA 1 — ESLint `ds/*`** (branch `feat/ds-guard-eslint-ratchet`): spec em `prototipo-ui-patch/PROMPT_PARA_CODE_PR-B-guard-ds.md` + `prototipo-ui-patch/REGRAS_DS_LINT.md`. 6 regras `no-restricted-syntax` no override `resources/js/**/*.{ts,tsx}`, escopo Pages/**+Modules/**, fora de Components/ui/** e _Showcase/**. Absorver baseline (`npm run lint:baseline:write`), gate falha só em delta>0.
- **TAREFA 2 — Stylelint `.css`** (branch `feat/ds-guard-stylelint-css`): spec em `prototipo-ui-patch/REGRAS_STYLELINT_CSS.md`. `color-no-hex` + radius-scale, escopo `resources/css/**` + `*-page.css`, ignorando `tokens.css`/`design-system.css`. Mesmo ratchet.

**Contexto (drift já corrigido no protótipo Cowork, falta espelhar no repo):**
- accent global era azul 220 → **roxo `oklch(0.55 0.15 295)` (ADR 0235)** ✅ no Cowork (`styles.css`, `tokens.css`, `app.jsx` override inline, `--bubble-me`).
- `compras-page.css` ✅ zerado (0 hex, `--cmp-*`→tokens) no Cowork.
- **Pendente no repo:** `cowork-financeiro-bundle.css` (bubble-me 220, avatares/stage hex) e `Sidebar.tsx` (`vibeAccent 'workspace'` = 220).

**Natureza:** §10.4 — isto é **proposta**, não ordem. [CL] valida contra o `main` sozinho (cruzar ADR 0209, PR-A 9d28f56a0) antes de aplicar; **não mergear** — devolver contagem de violações por regra (= mapa de drift, P0 primeiro na migração).

---

### 2026-05-14 — Vendas (Sells/Index + Sells/Create): elevar a A+ (≥9.5)

**Tela:** `Sells/Index` + `Sells/Create` (mesmo PR, são par P0 do protocolo)
**Prioridade:** P0 — substitui o item P0 antigo da TELAS_REVIEW_QUEUE
**Estado atual (Auditoria Final, 13 mai 2026):**
- Nota: **8.7 (A)** — Estrutura 9 · Visual 8 · Domínio 9 · Interação 9
- IMPL React, multi-vertical (CV, Mecânica, Vestuário, Repair)
- Gargalo: **Visual** (sem identidade própria, sem tendência, sem fiscal inline)

**Meta:** **≥ 9.5 (A+)** — empatar com Oficina Auto.
Projeção alvo: Estrutura 9 · **Visual 9** · **Domínio 10** · **Interação 10** = 9.5

**Persona-driven:**
- **Larissa** (balcão ROTA LIVRE, monitor 1280×1024) é quem mais usa a tela.
  Atalho-first, densidade alta, KPIs gigantes, scan visual instantâneo.
- **Eliana [E]** (financeiro) precisa do recorte fiscal sem entrar na venda.

**Contexto:**
Vendas é a tela mais tocada do dia-a-dia da gráfica. Hoje funciona, mas
não é memorável como Compras (cream-and-navy) nem viva como Oficina (tweaks).
A gráfica brasileira média emite NF-e + NFS-e numa mesma venda (banner + instalação,
peça + mão-de-obra) — esse domínio precisa aparecer inline, não escondido.

---

#### Pedidos para [CC] — 8 itens

**1. Identidade visual própria**
Vendas precisa de assinatura que distingue de OS/Orçamentos. Sugestão:
tom warm forest-green (confirmação + dinheiro). Decisão livre, mas
comprometer um vocabulário. Padrão Compras = cream-and-navy é o nível.

**2. Hero KPIs com tendência (não chapado)**
Substituir "Faturado hoje R$ X" estático por:
- Sparkline 30 dias do faturamento diário no hero card preto
- Ticket médio com Δ% vs semana passada (verde/red)
- "A receber" com **ageing visual** (0–3d verde / 4–7d amber / >7d red)
- PIX hoje vs total — mini progress bar

Referência: Stripe Balance, Mercury Cashflow. Foi o que tirou Financeiro
de 6.5 → 8.0.

**3. Mini-stepper FSM inline em cada linha**
5 bolinhas por venda (orçamento → pedido → faturada → entregue → paga).
Visual scan instantâneo do pipeline. Hoje só tem badge de status texto.
Referência: Linear Issues, ServiceTitan Job Board.

**4. Avatar do vendedor + comissão calculada**
Coluna compacta com inicial+cor do vendedor + valor de comissão
calculada inline. Cria ownership e disputa saudável no balcão.
Larissa quer ver "minha comissão do dia" no header também.

**5. Tweaks inline (padrão Oficina Auto)**
Toggle no header: **Vista = Caixa | Faturamento | Comissão**
- Caixa: foco em hoje/PIX/dinheiro
- Faturamento: foco em NF-e/NFS-e/pendências fiscais
- Comissão: foco em vendedor/meta/ranking
Muda KPIs do hero + colunas extras na tabela. Persiste em localStorage.

**6. ⌘K rico**
Abrir com:
- 3 últimas vendas (continuar/duplicar)
- Atalho "Nova venda PIX rápida" (skip drawer cheio)
- Busca por NF (chave SEFAZ ou número)
- Busca por placa/IMEI/produto
Hoje o atalho N só abre drawer cego.

**7. Saved views ▾ + bulk actions**
- Saved views ▾ no header (Hoje · Pendentes · Faturadas semana · Atrasadas)
- Seleção múltipla na tabela → barra flutuante com:
  - Emitir NF-e em lote
  - Marcar como pagas
  - Exportar XML/PDF
  - Enviar lembrete WhatsApp (interno, sem cliente-facing)

**8. Documentos fiscais vinculados (NF-e + NFS-e) — núcleo do +Domínio**

Uma venda pode ter NF-e (produto), NFS-e (serviço), ou ambos.

**Na tabela (lista):**
- Coluna fiscal compacta: badge duplo `NF-e ✓ · NFS-e ✓`
- Estados: ✓ autorizada (verde) · ⌛ processando (amber) · ✕ rejeitada
  (red) · ⊘ cancelada (cinza) · — não aplicável
- Hover na badge → tooltip com chave + data autorização

**No drawer da venda, seção "Fiscal":**
- Cards lado a lado: NF-e (se houver) | NFS-e (se houver)
- Cada card mostra:
  - Status SEFAZ com cor + timestamp
  - Chave de acesso 44 dígitos formatada (0000 0000 0000 ...) + botão copiar
  - Número + série + data emissão
  - CTAs: Baixar DANFE/DANFS-e PDF · Baixar XML · Enviar por e-mail
  - Mini-timeline SEFAZ inline (emitida → autorizada → entregue → paga)
  - CC-e (Carta de Correção) accordion expansível se houver
- Se status = rejeitada → motivo SEFAZ em destaque red no topo do card

**Ação rápida no header da venda:**
- Se não emitida → CTA "Emitir NF-e" / "Emitir NFS-e" (depende do que
  tem na venda: produto vs serviço)
- Se emitida → CTA secundário "Cancelar nota" (com janela 24h SEFAZ)

**Multi-fiscal numa venda mista:**
- Sub-tabs `NF-e` | `NFS-e` no drawer fiscal com contador (1/1)
- Total da venda = soma dos dois documentos (mostrar breakdown)

**Referências:** Conta Azul (cards fiscais), Tiny ERP (timeline SEFAZ),
Bling (chave clicável → portal SEFAZ), Asaas (status badges).

---

#### Proibições explícitas (charter)

- ❌ Sem CTA WhatsApp cliente-facing
- ❌ Sem modal full-screen pra detalhe — drawer lateral apenas
- ❌ Sem rounded-xl+ (manter rounded-md do tokenset CLAUDE_DESIGN_BRIEFING §4)
- ❌ Sem inglês em UI cliente-facing (Sells = "Vendas", Customer = "Cliente")
- ❌ Sem emoji
- ❌ Sem cores fora dos tokens canônicos

#### Comparáveis a estudar (CLAUDE_DESIGN_BRIEFING §5)

- **Shopify Orders** — ageing + bulk actions + saved views
- **Stripe Payments** — sparkline + status pills + ⌘K
- **Ramp Bill Pay** — densidade + identidade visual forte
- **ServiceTitan Invoices** — fiscal inline + multi-doc
- **Conta Azul** — NF-e/NFS-e dual nativa (mercado BR)
- **Linear Issues** — mini-stepper + saved views

#### Entrega esperada de [CC]

- `prototipos/sells/page.tsx` (Index + Create no mesmo arquivo)
- `prototipos/sells/COMPARISON.md` (15 dimensões CLAUDE_DESIGN_BRIEFING §5)
- Critique-alvo F1.5: **≥ 90** (entrega A+)
- Tokens canônicos rigorosos (sem inventar cor, radius, animação, foco)
- Padrão Cockpit V2 (sidebar + header sticky + body cards + footer sticky + drawer)

---

### 2026-04-27 — Sync inicial
**Contexto:** primeira ida do protótipo pro repo. Cowork está em ~88% do escopo do shell + Fases 2-3 (OS, Clientes, Orçamentos, Produtos) prontas.

**Pedidos para o Claude Code:**

1. **Sync inicial** — extrair zip do Cowork em `prototipo-ui/`, branch `feat/prototipo-ui-cockpit`, abrir PR, mergear na main.

2. **Verificar estrutura esperada** após sync:
   - `prototipo-ui/Oimpresso ERP - Chat.html` (entry)
   - `prototipo-ui/README.md`
   - `prototipo-ui/CLAUDE_CODE_BRIEFING.md` (este briefing)
   - `prototipo-ui/SYNC_LOG.md`
   - `prototipo-ui/COWORK_NOTES.md` (este arquivo)
   - `prototipo-ui/memory/HANDOFF.md`
   - 12+ arquivos `.jsx` e `styles.css`

3. **Decidir:** `LARAVEL_REPO_CONTEXT.md` que está dentro do export — esse arquivo é redundante com o `CLAUDE.md` raiz do repo. Apaga após confirmar que `CLAUDE.md` raiz está atualizado.

4. **Anexar primeira entrada em SYNC_LOG.md** descrevendo o sync inicial.

5. **Confirmar** com Wagner que a integração está funcionando: leia este arquivo, escreve confirmação em `CODE_NOTES.md`, peça pra Wagner colar pro Cowork.

**Status atual do protótipo (lê HANDOFF.md pra detalhes):**
- Fase 1 (shell): ✅ pronto
- Fase 2 (OS piloto): ✅ pronto (lista, detalhe, Nova OS, Aprovar arte, bulk export, atalhos)
- Fase 3 (Clientes/Orçamentos/Produtos): ✅ pronto (CRUD básico, KPIs, filtros)
- Fase 4 (Produção): 🔴 só placeholder
- Fase 5 (decommission Blade): 🔴 não iniciada

---

## ✅ Histórico (processadas)

- **2026-05-31 · Health-check de charter** — [PROCESSADO] **PR #2055 MERGED** (`CharterHealthChecker`: charter_missing/stale/refs/method/readme-handoff + 9 Pest).
- **2026-05-31 · Oficializar Financeiro charter** — [PROCESSADO] **PR #2053 MERGED**, redirecionado p/ `Financeiro/Unificado/Index.charter.md` **v10** (não existe `Financeiro/Index.tsx`); charter Cowork v1 superada.

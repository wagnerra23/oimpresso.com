---
status: proposal
title: "Reverter o eixo de localização da 0364 — o trio de tela FICA colocado ao lado do .tsx; a doc espelha o fonte (Opção B)"
proposed_by: Felipe [F] + Claude
proposed_at: 2026-08-01
reverts_partially: 0364-trio-de-tela-mora-em-memory-emenda-0264
relates_to:
  - 0364-trio-de-tela-mora-em-memory-emenda-0264
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0053-mcp-server-governanca-como-produto
  - 0273-anchor-spec-codigo-formato-canonico-fluxo-novo
  - 0094-constituicao-v2-7-camadas-8-principios
pii: false
---

# PROPOSAL — o trio de tela FICA colocado (Opção B), reverte só o eixo de localização da 0364

> **Status:** `proposal` — **[F] decidiu a direção** (2026-08-01: *"eu quero como no fonte"* · *"colocar na estrutura do fonte e ter a documentação correlacionada"* · *"não sei se o `_telas` vai conseguir tudo"*). **Reverter canon aceito é ato exclusivo de [W]** (nova ADR append-only `supersedes_partially: [0364]` + flip). [F] patrocina; [W] ratifica; eu executo sob aprovação, parte a parte.
>
> **A 0364 (aceita hoje, Opção A) NÃO é editada** (append-only Tier 0). Esta proposal registra a reversão do **eixo de localização** dela, acionando o **gate-de-reversão cláusula (c)** que a própria 0364 previu (*"reabrir se [W]/[F] concluírem que a proximidade física valia mais que a casa única"*).

## A decisão (Opção B)

1. **A casa canônica do trio (`<Tela>.charter.md` + `<Tela>.casos.md`) permanece COLOCADA ao lado do `<Tela>.tsx`** em `resources/js/Pages/<Mod>/`. Não migra pra `memory/requisitos/<Mod>/_telas/`.
2. **A doc espelha o fonte** por proximidade física + frontmatter de correlação (`component:` → `.tsx` irmão, `parent_module:` → `memory/requisitos/<Mod>`, `related_us:` → US do SPEC).
3. **A doc de MÓDULO** (SPEC / BRIEFING / SDD / RUNBOOK) continua em `memory/requisitos/<Mod>/`, espelhando `Modules/<Mod>` + `resources/js/Pages/<Mod>`.
4. **Reverte parcialmente a [0364](../0364-trio-de-tela-mora-em-memory-emenda-0264.md)** — nova ADR `supersedes_partially: [0364]` (NUNCA `supersedes` total: total rebaixaria a 0364 pra fora do canon vivo e mataria o raciocínio "RAG não exige o move" que sustenta B — mesma lógica que a 0364 aplicou à 0264).

## A prova central: B ≈ status quo (não é migração, é NÃO-mover)

Medido em `origin/main` (2026-08-01, recibo reproduzível ao lado):

- O resolver canônico do trio — `scripts/casos-coverage-guard.mjs` (gate **required** `casos-gate`) — resolve **100% por path-irmão colocado**: `dirname(page)+basename(page,'.tsx')+'.charter.md'/'.casos.md'` (L126-129); G-6 frescor via `file.replace(/\.casos\.md$/,'.tsx')` (L283). **Nenhum walk de `_telas/` pro trio React.** Comando: `git show origin/main:scripts/casos-coverage-guard.mjs | rg -n 'dirname|basename|\.charter\.md'`.
- O **dual-resolver da 0364 nunca foi executado** — não está em `origin/main` (a branch `claude/migracao-a-dual-resolver` tem só a proposta, 0 charter em `_telas/`).
- Logo **nenhum gate required precisa de reescrita**; a Opção B é o que o repo já roda. Reverter agora, antes de qualquer move, é o **momento mais barato** (custo-zero — o crítico da reversão confirmou).

## Recibos (medidos em `origin/main`, com o comando — LC-08)

| Fato | Valor | Comando |
|---|---|---|
| charters em Pages | **210** | `git ls-tree -r --name-only origin/main resources/js/Pages/ \| grep -c '\.charter\.md$'` |
| casos em Pages | **74** | idem `\.casos\.md$` |
| scripts-máquina (não-teste) | **109** (101 governance + 8 tests) | `git ls-tree -r --name-only origin/main scripts/governance/ \| grep '\.mjs$' \| grep -vc '\.test\.mjs$'` (+ `scripts/tests/`) |

> ⚠️ Números anteriores corrigidos: **"188 scripts sem registry"** inflava contando os 81 `.test.mjs` (bite-tests, não máquinas). E o crítico de mecânica chegou a citar "214/75" — **medição errada dele**; 3 métodos independentes dão **210/74**. Número em doc canônico carrega o comando e é datado (não eterno) — re-rode pra revalidar.

## O programa, dividido em 9 partes (B0–B8)

**Ordem:** B0 (ADR de reversão, [W]) LEGITIMA o programa; mas **B5/B6/B7 são [F]-autorizáveis e não dependem de B0** (não tocam localização nem gate required) — podem correr em paralelo. **Regra dura:** NUNCA acoplar o flip da ADR (governança) ao mesmo PR de código.

### B0 — ADR 0365 (reversão parcial) · **[W]**
Cunhar a ADR nova (texto pronto na seção abaixo) `supersedes_partially: [0364]`, acionando o gate-de-reversão (c). Confirmar em `origin/main` o slug/status da 0364 (`aceito`) antes. Gates: `governance-gate` (append-only), `memory-schema-gate` (adr.schema), `deadlink-gate`.

### B1 — Não-mover: verificar máquinas intactas + cancelar o dual-resolver da 0364 · **[F]**
Provar (via `git show origin/main`) que todos os resolvers seguem path-irmão puro sem ler `_telas/`/`tela:` — `casos-coverage-guard.mjs` (required), `screen-coverage-map.mjs`, `page-path.mjs`, `design-coverage.mjs`, família `charter-*`, `anchor-content-check.mjs` (required), `pt-conformance.mjs`, `anchor-lint.mjs` (required). Cancelar/descartar a branch de migração. **Nenhum código editado** — é o mapa "não muda". Gates required: **INTOCADOS**.

### B2 — Reforço da correlação via frontmatter (`component`/`parent_module`/`related_us`) — advisory · **[F]**
Tratar como contrato os campos que **já existem** no charter, **estendendo os donos** `charter-refs.mjs` (já checa `component` — só existência, L86) e `charter-us-lint.mjs`. É o "documentação correlacionada" mecânico. **FP medido (`--measure`) antes de instalar**, não só antes de promover (§5 guards sintáticos). Gates: `charter-refs` (advisory), `charter-us-gate` (advisory).

### B3 — RAG in-place (glob aditivo no indexer) — **[W]** (opcional, mas é o único gap real de B)
**O único ponto onde B perde pra A:** o trio colocado em `resources/js/Pages/**` **não entra no RAG** (o `IndexarMemoryGitParaDb` só varre `memory/**`). Fix = glob **aditivo** `resources/js/Pages/**/*.{charter,casos}.md` no `IndexarMemoryGitParaDb.php` (type=charter/casos, `module` via `module-group-resolve`), **sem mover**. ⚠️ Reconciliar o hook `doc-fora-do-rag.mjs`, que carrega uma **cópia hardcoded** desses globs. Gates: sentinela de glob interno + `Governance Gate`. CODEOWNERS Jana = [W].

### B4 — Lente de descasamento doc↔fonte (advisory) · **[F]** — ⚠️ reconciliar antes
Materializar "doc espelha o fonte" no grão de MÓDULO: descasamentos (fonte-sem-doc / doc-sem-fonte / nome-divergente `kb↔KB`, `Compras↔Purchase`). **Correção do crítico:** `vital-signs.mjs` + `mv-metabolismo.mjs` **já medem** `charter_pct`/`casos_pct` por módulo — **inventariar e DECIDIR estender o dono existente**, não abrir régua paralela (§5 2026-07-09 "duplicar régua consolidada"). Só depois, `--espelho` em `module-group-resolve.mjs`. FP medido antes.

### B5 — Ligar o registry de máquinas (órfão) · **[F]**
`scripts/governance/maquinas-inventario.mjs` **já existe e funciona** (rodei: gera "Máquinas do oimpresso — inventário consolidado DERIVADO" cobrindo 117 workflows/34 required + hooks + skills + agents + scripts + baselines, cada linha do cabeçalho do próprio arquivo). Está **órfão** (zero invocador). Fix = invocador (workflow advisory `--check` + selftest) + registrar SÓ o novo workflow no `gates-registry`. **Correção do crítico:** "único órfão dos 109" é over-claim (conflita com o canon "9 de 89 sem invocador" da regra LIGUE A MÁQUINA) — tratar `maquinas-inventario × system-map` (ambos enumeram workflows) como **decisão [F]/[W] explícita** pra não duplicar.

### B6 — Subtração de fósseis da raiz de `memory/requisitos/` (oportunística) · **[F]**
Reduzir o ruído dos **31 `.md` soltos** na raiz: manter os 10 vivos (índices/templates), subtrair os fósseis-SPEC-mortos via deprecação (padrão ADR 0357: lápide + relink), 1 PR cada, sem big-bang, sem promover fóssil a SPEC. **Correção do crítico (Tier 0):** `Officeimpresso1.md` **NÃO é fóssil** — `governance/ghost-rename-map.json` (L149-151) o marca *"referência histórica correta"* (ADR 0017, código restaurado em `Modules/Officeimpresso`); **não deletar.** Conferir inbound de cada um antes (deadlink-gate required).

### B7 — Gaps de cobertura forward-only + corrigir o censo de módulos · **[F]**
Fechar sem big-bang: ~136 telas com charter sem casos (via **ratchet** do casos-gate, tela-a-tela); corrigir o censo "14 sem SPEC" excluindo os tombstones (Atendimento→Whatsapp, Chat/Copiloto→Jana, Modules→Admin, Orcamento→Sells, Site→Cms, Purchase→Compras, Stock*→Estoque) — forçar SPEC neles reviveria módulo morto. SPEC real só onde há sinal (VozDoCliente, com ADR). **Correção do crítico:** o preenchimento de casos aciona também `sdd-output-lint.mjs` (C1) — escrever refs com `verificado@<sha>` pra não gerar dívida. Gates: casos-gate (required), screen-coverage (required), anchor-lint (required, diff-aware — **não acordar legado em massa**, §5 2026-07-12/27).

### B8 — Resíduos de correlação (doc-side) · **[F]**
Os **5 `.casos.md` doc-side** em `memory/requisitos/{Produto,OficinaAuto}/_telas/` são casos de **fluxo SEM `.tsx` irmão** (importer de frota, bom-combo, estoque-inicial, quick-add, ajuste-relatório) — **não** trio migrado nem órfãos; ficam fora do escopo Pages do casos-gate por construção. Registrar como resíduo legítimo (o `_telas/` como pasta de doc-por-tela **é uso legítimo**, distinto do `_telas/` que a 0364 propunha pro trio). Telas Pages sem casa de doc (Home/Settings/TransactionPayment) — correlacionar por nome ou aceitar resíduo: **dúvida → perguntar [F]**.

## Peças que NÃO mudam (o crítico de completude apontou o que faltava listar)

Além dos resolvers já citados, ficam **intocados** e devem constar do mapa "não muda": o subsistema **PHP de charter-health** (Governance `CharterAudit`/`CharterHealth`/`CharterMetrics` + `ChartersFreshnessChecker` + `DesignDocsFreshnessChecker` + Jana `CharterHealthChecker.php`); o pipeline **`prototipo-ui` de import/detect** (`detectar-telas.mjs` + `importar-bundle.mjs` + `gerar-map.mjs` + `integrity-check.mjs` — que já resolvem "charter=índice" apontando pro irmão em Pages, **Opção B por construção**); e o lote de resolvers charter-irmão (`detect-ui-drift`, `ds-ledger`, `feature-lint`, `ui-impact`, `pr-critic`, `lapide-recheck`, `resolver-reclamacao`). Todos operam sobre o trio colocado → **B mantém, A quebraria**.

## Texto pronto do ADR 0365 (para [W] cunhar no flip)

> **ADR 0365 — o trio de tela FICA colocado ao lado do `.tsx`; a doc espelha o fonte (Opção B) — reverte só o eixo de localização da 0364.**
> `supersedes_partially: [0364]` · `decided_by: [W, F]` · gate-de-reversão (c) da 0364.
> **Decisão:** a casa canônica do trio permanece colocada em `resources/js/Pages/`; a doc espelha o fonte por proximidade + frontmatter. Formalização *de jure* de um estado que já é *de facto* (a migração da 0364 nunca rodou; `casos-coverage-guard` required já resolve por path-irmão; nenhum gate required precisa de reescrita). Mantém o que a 0364 acertou (RAG neutro — indexação in-place possível sem mover, B3). **Aceita no mesmo dia (2026-08-01) que a 0364 — mecanicamente legítimo via cláusula (c), gatilho registrado explicitamente.**
> **Gate-de-reversão desta ADR:** reabrir (nova ADR append-only) se (a) [W]/[F] concluírem que a casa única em `memory/` volta a valer mais → re-flip Opção A; (b) a proximidade colocada provar, com **sinal medido**, não conter o rot; (c) o RAG in-place provar inviável. Recuo: a 0364 permanece `lifecycle: ativo` (por `supersedes_partially`) com o plano de move ainda válido.

## Decisões que são de [W] (soberania)

- **FLIP da ADR 0365** (cunhar `proposto→aceito`) — ato exclusivo do dono; sem ele, B é proposta, não vigente.
- **Ligar o RAG in-place** (B3, escrita em `mcp_memory_documents` + CODEOWNERS Jana).
- **Promover a required** qualquer check de correlação (B2) — só com FP medido + mordida provada (ADR 0336 DR-2) + janela.
- **Reconciliar fragmentação de nome** (`Compras↔Purchase`, `Produto↔ProductCatalogue`, `Estoque↔Inventory`) — decisão de domínio/produto; a lente B4 só REPORTA.
- **Dono único** do índice consolidado de máquinas — `maquinas-inventario.mjs` × `system-map.mjs`.
- **Criar `casos.schema.json`** (ausente) — gate NOVO de forma → só com FP medido; não criar por reflexo.

## Como isto se integra à documentação oficial (sem doc órfão)

Nada de doc-índice novo (§5 2026-07-23/25). O registro canônico da decisão é a **ADR 0365** (cunhada por [W] a partir do texto acima). O rastreamento de **execução** vive nos **donos existentes**: cobertura → ratchet do `casos-coverage-baseline.json`; scripts → `gates-registry.json`/`maquinas-inventario`; raiz → `memory/requisitos/INDEX.md`; espelho → `PAINEL-SISTEMA.md` via `system-map`. A **[0364](../0364-trio-de-tela-mora-em-memory-emenda-0264.md)** e a proposal accepted que ela realizou ([documentacao-do-fonte-layout-canonico.md](documentacao-do-fonte-layout-canonico.md)) **não são editadas** — representam a Opção A, que esta reversão supera parcialmente via ADR nova.

## Ratificação

PR (draft) + revisão adversarial (feita — workflow 20 agentes, 4 críticos, veredito INCOMPLETO com as correções acima já embutidas). Flip da ADR 0365 = **[W]** (CODEOWNERS). Ratificada → executo B1/B5/B6/B7 ([F]-autorizáveis) em paralelo, B3 quando [W] liberar, forward-only.

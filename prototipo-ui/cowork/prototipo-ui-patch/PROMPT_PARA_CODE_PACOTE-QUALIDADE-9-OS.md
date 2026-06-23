# PROMPT_PARA_CODE — PACOTE QUALIDADE-9: OS funcional · CSS profissional · dedup · gates

> **Origem:** [W] 2026-06-10 — "no code fique organizado os css profissionalmente, remover duplicatas, deixar tudo nota 9 acima, gere o protocolo para OS funcionar."
> **Contrato (§10.4):** isto é PROPOSTA de [CC]. Você ([CL]) **valida cada item contra o `main` antes de executar** — fatos daqui marcados ⚠ são cache do Cowork e podem estar atrás do repo. PRs em série, CI verde = merge autônomo; **Tier 0 ([W]) = token novo no @theme, DB/seeder, FSM keys, workflow required**. Nada de snapshot de volta pro Cowork — só atualize `SYNC_LOG.md` + `CODE_NOTES.md`.
> **Referências do protótipo (URLs ~1h; se expirar, [CC] regenera):** ver §URLS no fim.

---

## PR-1 · OS FUNCIONAL (o "protocolo para OS funcionar")

**Objetivo:** módulo OS (OficinaAuto/ServiceOrders) operável fim-a-fim no padrão do protótipo aprovado.

1. **Port OS-V2-1 (Fotos & Laudo) + OS-V2-2 (DVI semáforo 1-toque)** — **F2 JÁ APROVADO por [W] (2026-06-09 "aprovo")** — pro `ServiceOrderRichSheet.tsx`:
   - 3 estados de fotos: vazio (zona tracejada drag&drop, role=button) → enviando (progress) → preenchido (grid + legenda editável + lightbox, Esc fecha só o lightbox).
   - Backend: endpoint de upload via `Modules/Arquivos` (validar o módulo no main; se não houver endpoint pronto, criar store mínimo: POST foto vinculada a service_order_id, disk público, max 5MB, jpg/png/webp).
   - DVI: semáforo radiogroup (ok/atenção/crítico, 24px, a11y) substitui select de status — referência exata no protótipo (`oficina-forms.jsx` → `DviTraffic`).
2. **OS-V2-3 (gate de aprovação hero) + OS-V2-4 (timeline auditável)** — F1 pronto no protótipo (`DviGateFoot`, chips FSM `ofc-tl-fsm`); **F2 de [W] = screenshot no staging APÓS o port** (gate visual já existente cobre). Estados: none→pending→approved|declined→reopen; timeline com quem·quando·de→pra casando com endpoint `fsm/history` (validar nome real no main).
3. **Residuais que travam o fluxo real (da avaliação 65/100 + sweep #2477):**
   - Controller `create()` passa `contacts` + `StoreServiceOrderRequest` aceita `contact_id` nullable (combobox Cliente já está no Create.tsx pós-#2477).
   - `printSaleReceipt.ts` (Vendas) espelhar o fix do `printServiceOrder.ts` (iframe oculto → print pelo parent pós-load+fonts).
   - Label `'Caçambas'` no `topnav.php` + comentário stale de rota → "Oficina" (cosmético, ADR 0265).
   - Backfill `order_type` legado + labels FSM PT (⚠ keys `cacamba_locacao` do Martinho biz=164 = **Tier 0, NÃO tocar** — só labels).
4. **Critério de pronto PR-1:** Larissa consegue: criar OS (cliente+veículo) → DVI com semáforo → foto no laudo → pedir aprovação → aprovar → executar → imprimir A4 com fotos. Teste Pest-browser cobrindo esse caminho + `casos.md` da tela atualizado (G-2 do casos-gate).

## PR-2 · CSS PROFISSIONAL + DEDUP (repo)

**Objetivo:** 1 arquivo canônico por superfície, zero morto, zero duplicata.

1. **Dedup de bundles (⚠ validar no main):** `cowork-financeiro-bundle.css` × `cowork-canon-financeiro-bundle.css` — se ambos vivem na allowlist (34 arquivos do `.foundation-guard-files.json`), consolidar no `canon-`, redirecionar imports, deletar o outro e ENCOLHER a allowlist (gate ②: editar o JSON via PR revisado).
2. **CSS morto:** rodar o analyzer (#2210) em TODOS os css de tela da allowlist; remover **1 família de seletor por PR** (decisão 06-04 — nunca nuke). Meta: `sells-cowork.css` −4039 linhas mortas mapeadas; baselines do stylelint/conformance DESCEM junto (ratchet only-down — subir = 🔴).
3. **Organização profissional (padrão único):** por tela = `<tela>.css` com header de escopo (`/* escopo: .sells-cowork · tokens: ds @theme · proibido: hex/oklch cru, token-def */`), ordem interna: layout → componentes → estados → responsivo → print. Sem `!important` novo (stylelint já trava). Tokens SÓ em `inertia.css`/`cockpit.css` (foundation-guard ①).
4. **Critério de pronto PR-2:** allowlist menor que 34 · zero regra morta nas famílias varridas · baselines numéricas menores que as atuais (provar no PR com os números antes/depois).

## PR-3 · GATES NOVOS (fecham as classes de erro 06-10)

1. **Papel de token no `conformance-gate.mjs`:** `--*-fg` em `background|background-color|fill` = 🔴 · `--*-bg` em `color|stroke` = 🔴 (regex barata, padrão ratchet existente). Caso real que motivou: barra de progresso marrom com `--origin-MFG-fg` como fill.
2. **Espelho do probe G1–G6 no CI** (estende o plano `PROMPT_PARA_CODE_CONFORMANCE-GATE.md` já na fila): asserções computed-style Pest-browser por tela núcleo — G2 `accentColor !== 'auto'` em checkbox/radio visível · G3 papel de token · G4 `scrollWidth<=clientWidth` em drawer/dialog **com estado "adicionando/editando" ABERTO** (o estado é o que vazava). Fonte de referência: `qa-conformance.js` v2 do Cowork (URL abaixo) — portar a SEMÂNTICA, não o arquivo.
3. **Critério de pronto PR-3:** cada gate novo visto 🔴 num bug injetado e 🟢 no limpo (controle-negativo no próprio teste, padrão L-31).

## PR-4 · RÉGUA ≥9 (programa contínuo)

1. Rodar `score-mechanized.mjs` (golden 86/100 ⚠ validar) + module-grades: **toda tela <9 entra na fila** `TELAS_REVIEW_QUEUE.md` com o gap nomeado.
2. Espelha as ondas do Cowork (`Reestruturacao - Identidade Unica e Qualidade 9.html`): W2 = Financeiro (8.3 — US-FIN-029 3 lentes + pilar fiscal) é o pior gap conhecido.
3. Identidade única: qualquer `--accent` por módulo fora do roxo 250–330 que sobrou = conformar (modelo 2 camadas, `_PROPOSTA-modelo-unico-identidade-2-camadas.md`).

## new_design_memories (gravar no git)
- tipo: decisão · [W] 2026-06-10: piso de qualidade = 9; CSS 1-arquivo-por-superfície; duplicata estrutural proibida (espelho/snapshot) — Cowork já deletou 575 e criou IT8.
- tipo: anti-padrao · token `-fg` como superfície / `-bg` como texto → gate PR-3.
- tipo: golden · probe G1–G6 (classes genéricas, controle-negativo embutido) = espelho Cowork da camada-2.

## §URLS (referências do protótipo aprovado)
- oficina-forms.jsx (DviTraffic · DviGateFoot · ItemsEditor): https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/oficina-forms.jsx?t=bf9411d58690969062f3aeda19126afb336d520f11e174bd31147c182077749e.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781094043.fp&direct=1
- oficina-page.jsx (Drawer · estados · seeds · osLog): https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/oficina-page.jsx?t=95255e72ddf07af305db89fa0d8f00a592d4f1f85c2f01b58ccb712b56c5d37c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781094044.fp&direct=1
- oficina-page.css (estilos canon do drawer/gate/fotos): https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/oficina-page.css?t=4be247b11b49e30aee42ccdce528f097355ec05dc65172e3932ed5a4e604df8a.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781094044.fp&direct=1
- oficina-print.css (folha A4 + fotos do laudo): https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/oficina-print.css?t=d658d534c34240b38686b8976bd397fd976f71868e0f2a5477cc2dfadcef07d1.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781094045.fp&direct=1
- qa-conformance.js v2 (semântica G1–G6 + controle-negativo): https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/qa-conformance.js?t=983414c40f631ee58df62721b5efa4f11d1a7d76c2a124d68b9ece4f94eb205a.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781094046.fp&direct=1

**Ordem:** PR-1 → PR-3 → PR-2 → PR-4 (OS destrava valor primeiro; gates antes da faxina pra faxina já nascer travada). Pare no gate visual quando indicado; [W] só cola isto UMA vez.

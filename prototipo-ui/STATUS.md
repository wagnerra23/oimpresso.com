# STATUS.md — Espinha do Cowork (espelho de estado · cache derivado)

> **🔁 EMENDA Onda A (proposta #2874 · [W] aprovou 2026-06-16 · ratificado em ADR 0282):** a **fonte da verdade é o git** (history + `memory/decisions/` + `memory/sessions/` + canon no repo — ADR 0238/0239). Este arquivo é **cache de leitura derivado**: pode estar stale e **NUNCA bloqueia**. O rótulo antigo "single source of truth" está **superseded** (não deletado — anti-entropia · NÚCLEO 12): ver [`memory/decisions/proposals/protocolo-v2-onda-A-memoria-git-ssot.md`](../memory/decisions/proposals/protocolo-v2-onda-A-memoria-git-ssot.md). A metade *soberania-[W]* do piso (constituição/ADR/token = só [W]) **permanece intacta**.
> **LER ISTO PRIMEIRO em todo chat novo** (segue always-read — NÚCLEO 1 / IT3), porém **como cache**: se divergir do git, **o git vence**. Espelho-fonte do `Painel Cowork - Estado Atual.html`.
> **🌱 LER TAMBÉM (raiz do método, anti-regressão):** `PROCESSO_MEMORIA_CC.md` — duas velocidades (Register↔Charter↔ADR), anéis de decisão, e §5 REGRESSÕES PROIBIDAS. Conferir §5 ANTES de propor qualquer coisa. _REGRESSÃO É INACEITÁVEL._
> Atualizar ao fim de cada sessão: estado de tela mudou? decisão tomada? → reflete aqui.
> **2026-06-02 (b):** runner in-app de casos (D-09) generalizado (CasosRunner/CasosLauncher) — Oficina 7/7 live, Vendas wired. **Regressão L-24** (casos sumiram ao generalizar; pego por [W] = escape no benchmark) corrigida. **Decisão: estratégia de teste estado-da-arte** (`_PROPOSTA-0244`: locators resilientes + Playwright + Storybook + casos.md); runner DOM-grep = ponte. Handoff `PROMPT_PARA_CODE_CASOS-DE-USO.md` (3 partes + estratégia).
> Atualizado: 2026-06-01 (sync vs origin/main 2x: manhã = Jana Pro F3 #2069 + prep 3 IA #2073 confirmados; tarde ~10:45 = Gerador `design:review` por tela MERGED #2078 (review-gen.mjs+review-freshness.mjs ratchet+Pest+PROTOCOL §6; 1ª exec `Jana/Pro.review.md` 88; achado anexo `Pro.tsx` 2 R1 cor-crua dark → fix PR separado). **TARDE/F0 VENDAS: pesquisa profunda do domínio (dossiê rankeado `memory/sessions/2026-06-01-contexto-venda-dossie-git.md`) → 2 REGRAS DE PROCESSO criadas (par): `CONTEXTO-DE-TELA.md` (camada 1 Intake F0→F1) + `FRESCOR-DE-TELA.md` (camada 2, ratchet doc espelhando #2078 + delta-driven). Modelo das 5 camadas de garantia: Intake→Frescor→Crítica/Auditoria→Humano→Lição. Ambas com ponte zero-toque pro [CL] (aguarda [W] colar). [CC] já segue.** Fila Tier 0: Método Migration→Tela MATERIALIZADO pela Ficha) · [CC]

---

## 🚦 REGRA DE OURO — gate ANTES de criar QUALQUER arquivo ou prometer entrega

> Pré-flight obrigatório. Se eu não passar nos 4, **paro**. Repetir = L-01/06/08/09/11.

1. **Vou criar arquivo de processo/ponte/mecanismo?** → primeiro `ls` na raiz + grep. Canal pro Code **já existe**: `COWORK_NOTES.md` (Cowork→Code) · `CODE_NOTES.md` (volta) · `SYNC_LOG.md` · prompts em `prototipo-ui-patch/PROMPT_PARA_CODE_*.md`. **Usar, nunca duplicar.** Nunca um `PARA_O_CODE.md` da vida.
2. **Vou prometer commit/PR/merge/"tarefa no Code"?** → **NÃO consigo** (read-only no git). A única ponte é [W] colar 1x. Falo "o Code resolve com este pedido", nunca "está commitado". _(🔁 Emenda Onda A/PR-A3 #2874: pra `memory/` · `prototipo-ui/` · `docs/` eu **escrevo via `cowork-inbox/`** — solto arquivo com header `<!-- cowork: target: <path> -->` (ou `append-to:`) e o `cowork-inbox.yml` abre auto-PR+merge. **[W] não cola mais** pra esses paths. Código (`resources/js/**`) segue só-[W]/[CL] — write-path automático de código é a **Onda D**, atrás de review, nunca auto-merge.)_
3. **É decisão/identidade/cor/regra nova?** → é **PROPOSTA** (§10.4), não lei. Vai pro canal; [CL] valida contra o `main` sozinho. Não marco como "firme".
4. **Vou re-tematizar token?** → provar a fonte EFETIVA (`getComputedStyle`) + grepar TODAS as defs live antes (L-10).
5. **Vou criar/editar TELA ou CSS de módulo?** → pré-flight de build visual (L-23): ler `ds-v5/components.css` + `REGISTRY_DS_COMPONENTES` (git) + harmonização + `LICOES_CC` ANTES; **reuse-first** (cor só `.<tela>-scope{--accent}`, nunca `--<prefixo>-*`/oklch cru = paleta inventada); evoluir o `*-page.jsx` no host, **nunca .html novo na raiz**. No fim, rodar o **DS-GUARD** (`PROCESSO_MEMORIA_CC §8`) nos arquivos tocados antes do `done`. Repetir = L-02/L-21/L-23.

---

## ⚖️ Lei suprema (mora no GIT — eu obedeço)
- **ADR 0094** (Constituição Oimpresso V2 · 8 princípios) + **ADR UI-0013** (Constituição UI v2) — constituição suprema, já existente.
- **`prototipo-ui/PROTOCOL.md`** — 6 papéis × 7 fases, gates, overrides, métricas de saúde, anti-padrões.
- **`prototipo-ui/CLAUDE_DESIGN_BRIEFING.md`** — tokens canônicos (shadcn + warm), personas, 15 dimensões, proibições.
- **ADRs** (`memory/decisions/`): 0114 loop · 0110 Cockpit V2 · 0107 gate visual F1.5 · 0104 MWART · 0010/0027/0028 memória.
- **`CARTA_DESIGN_CC.md`** (local) — carta **subordinada**: como [CC] obedece o acima. NÃO é lei.
- **Regra [W]-only:** a constituição **só [W]** altera (versionado + autorizado); [CC] só propõe. Vínculo em CARTA §0.1/§0.2. **✅ ADR 0238 (`0238-soberania-constituicao-wagner.md`) JÁ É CANON em `main` (PR #2007).** [CC] nunca cunha número do git (lição 0200/0201, L-09).
- **Gate §10.4 (novo · 2026-05-30):** todo `PROMPT_PARA_CODE`/sync meu é **proposta**; o [CL] **valida contra o git sozinho** (não depende de [W]) e bloqueia: criar ADR que já existe, renomear/mutar ADR aceito (append-only Tier 0 — colisões 0235/0236 se **documentam**, PR #1997), número alucinado, faxina-local virando canon. Origem: meu prompt stale da faxina (L-09).
- Memória manda o **git**; [CC] cuida do **design**; decide **[W]**.

## DS vigente
- **Canônico:** v4.1 (tokens.css + design-system.css no git `wagnerra23/oimpresso.com@main`, ondas A–D + form vocab).
- **2026-05-31:** `tokens.css` local sincronizado ao canon roxo `oklch(0.55 0.15 295)` (ADR 0235) — estava azul 220 (stale). Falta espelhar no repo.
- **2026-05-31 · P1 faxina de tokens (faça/W):** dedup da cascata de `--accent`. (1) Removida a **2ª cópia literal** do bloco `.mockup-page{tokens}` em `mockup-pages.css` (código morto sob "Onda 2 styles"). (2) Removido o leak global `* {}`+`html,body{}` de `mockup-pages.css` que forçava o body do shell pra 12.5/13px — **shell volta ao 13.5px intencional do `styles.css`**. (3) Removido o `--accent` hardcoded de `.mockup-page` → mockups herdam canon + respondem ao tweak `accentHue`. (4) Documentada a fonte: **runtime = `app.jsx` inline (vence `:root`); `:root` = fallback.** **TUDO Cowork-local — NÃO vai pro git** (mockup-pages/styles são shell de protótipo, nem existem no repo).
- **2026-05-31 · §10.4 lido + correções:** fonte canônica de token no repo = **`resources/css/app.css`** (não `tokens.css`). **P3 do diagnóstico REBAIXADO → resolvido:** §10.4 já fixa roxo universal (`--accent`) + cor-por-origem só nos badges (`--origin-*`); proposta D-02 (hue-por-módulo) superada. Diagnóstico HTML atualizado.
- **Guard de Stylelint (anti-hex + radius + anti-redeclare-`--accent` §7):** ✅ **MERGED — PR #2054** (`stylelint.config.mjs` 4 regras + `stylelint-gate.yml` CI verde, 2026-05-31 ~23:55). **NÃO está "aguardando [W]"** — a nota anterior aqui estava STALE (corrigida no sync 06-01). G5 = 100% (ESLint `ds/*` + Stylelint `.css`). Sobra só migrar o **conteúdo** em drift (`cowork-financeiro-bundle.css` 188 hex, bubble/vibeAccent azul 220) — conteúdo, não gate.
- **Proposto:** v4.2 (cockpit, fiscal-badge, sla-pill, readiness, shortcut-bar, formpage PT-03). Spec: `Design System v4.2 - Evolucao.html`.

## Decisões vigentes (não rediscutir — detalhe em memory/decisions/)
| ID | Decisão | Status |
|----|---------|--------|
| D-00 | Padrão Cockpit V2 (sidebar+header+body+footer+drawer) | charter · **ADR 0110**/0114 |
| D-01 | DS é piso, não teto — harmonizar sem achatar identidade | firme |
| D-02 | Identidade por tela (`--accent`/oklch) | **PROPOSTA F0** · ⚠️ canon vigente = **roxo `oklch(0.55 0.15 295)` universal (ADR 0235)**; cor-por-tela só vira norma via ADR de [W] |
| D-03 | Cadastro grande = página inteira (PT-03) | **PROPOSTA F0** · toca proibição Sheet-drawer |
| D-04 | Escopo DS 4.2 (cockpit, fiscal, sla, readiness, shortcut-bar) — aditivo | proposto |
| D-05 | Cor crua fora dos tokens = erro | proposto |
| D-06 | Protótipo e produção importam o MESMO tokens/design-system.css | proposto (norte) |
| D-07 | Proibições charter (BRIEFING §7) | charter |

> Propostas só viram lei via loop F0→F1.5→ADR aprovado por [W]. Até lá, valem os tokens canônicos do BRIEFING.

## Quadro de telas (auditoria 2026-05-30)
| Tela | Identidade | Nota | Estado | Próximo passo |
|------|-----------|------|--------|---------------|
| Oficina/OS | car · verde 155 | F1 build | **Nova OS construída** (`oficina-os-page.jsx`, documento vivo: check-in+DVI+gate+fiscal) + **`Oficina.charter.md`** (conceito travado, 1º uso Intake+Frescor) | F1.5 critique → F2 [W] → F3 Code. Refino: stepper estreito, dados reais ROTA LIVRE, verticais CV/Roupa |
| Vendas | verde 155 | 9.5 | piloto aprovado · Index ok; **Create POS aposentado** — foco migrou p/ Oficina/OS (06-01) | F0 venda comercial CV: dossiê `2026-06-01-contexto-venda-dossie-git.md` (P0 endereço destinatário↔local→NF-e via `contact_addresses`) quando [W] retomar CV |
| Compras | roxo 295 (era navy/cream) | 9.4 | ✅ migrado: 0 hex, `--cmp-*`→tokens DS | espelhar no repo |
| Financeiro | roxo 295 | 8.0 | colisão <1100px | corrigir KPI grid |
| Caixa Unificada (Inbox) | método completo | 9.75 | REFERÊNCIA | não mexer — é gabarito |
| CRM | azul 220 | 8.6 | já alinhado | migração barata |
| Oficina | âmbar 60 + tweaks | 9.5 | tweaks ok | radius/shadow DS |
| Clientes/Contatos | indigo 262 | — | molde PT-03 pronto · **+seção Endereços no drawer (06-01)** | replicar nos 3 cadastros |

## IA — onde tudo mora (base limpa pós-faxina 2026-05-30)
- **raiz (app vivo)** — `oimpresso.com.html` + `*-page.jsx/css`, `data-*.jsx`, `tokens.css`, `design-system.css`, `Método KB-9.75.html`
- **raiz (espinha)** — STATUS.md, MEMORY_INDEX.md, CARTA_DESIGN_CC.md, CLAUDE.md, ARQUITETURA.md
- **raiz (memória-por-tela · charter-first L-14)** — `<Tela>.charter.md` (PRECISA TER · APROVADO · REPROVADO/anti-pattern · targets). 1ª instância: `Financeiro.charter.md` + `Método 9.75 Financeiro.html`. Ler o charter da tela ANTES de mexer nela.
- **raiz (ponto de entrada do Handoff · L-18)** — `README.md` tem que SEMPRE manter no topo o bloco "🤖 Claude Code — COMECE AQUI" (marcador `<!-- HANDOFF-ENTRY -->`) apontando `COWORK_NOTES.md → 📥 Pendentes`. [W] entrega via Share→Handoff (lê o README + implementa o arquivo aberto); sem o bloco, o Code não acha a fila. Qualquer [CC] que regenerar o README PRESERVA o bloco. Fiscal automático proposto (`readme_handoff_block_missing`).
- **raiz (ponte viva)** — COWORK_NOTES.md, CODE_NOTES.md, SYNC_LOG.md, CLAUDE_CODE_BRIEFING.md, CODE_DESIGN_CONTRACT.md + `prototipo-ui-patch/`
- **memory/decisions/** — ADRs (append-only)
- **`_arquivo/`** — histórico organizado + manifesto versionado (`_arquivo/INDEX.md`): `telas/`, `referencia/`, `sessao-2026-05-30/`, `ds/`, `ds-historico/`, `bridge-processados/`, `auditoria/`. **Append-only: movido, nunca apagado.**

## ⚙️ Loop agora é 0-humano (git 2026-05-31 00:45 · `AUTOMACAO-LOOP-AUTONOMO.md`)
- Merge autônomo `gh --admin` quando CI verde. Gate visual [W2] → CI (PR UI Judge Sonnet 4.5 + visual-regression). [W] só entra em **Tier 0** (ADR novo · multi-tenant · segredo · tooling/lint · produto).
- **As 4 propostas que estavam na `COWORK_NOTES.md` já foram processadas pelo Code (~07:00) e voltaram STALE vs `main`** — ver `CODE_NOTES.md` (ingerido). NÃO re-sincronizar: re-mandar = regressão / duplicar canon (era um prompt §10.4-stale meu).
- **Lição desta sessão:** antes de "sync code", ler `SYNC_LOG.md`+`CODE_NOTES.md` do **git** (não a memória local). Memória local estava 7h atrás do `main`.
- **2026-06-01 SYNC vs origin/main (CODE_NOTES/SYNC_LOG fresco · "acho que já foi feito" confirmado):** Stylelint `.css` **MERGED #2054** (gate CI verde) · charters de papel **ADR 0242 + CHARTER_GOVERNANCA_W/CHAMPION MERGED #2061** (Tier 0) · README HANDOFF-ENTRY **MERGED #2062** · auditoria mecanizada (`score-mechanized.mjs` 239 telas · CONSOLIDADO **86/100** · golden) **já em main** (Fase 2 LLM = $ [W]) · **G4 retorno automático** = ✅ **MERGED em prod** (`.github/workflows/design-return-gate.yml` — gate advisory pós-merge que sinaliza `design_return_skipped` sozinho quando merge toca tela/DS sem atualizar SYNC_LOG; [W] 06-01: "não está no lugar esperado — virou workflow, não check do health-check — mas está em produção"). Só a **auto-geração** (auto-commit `ds:report:write`+SYNC_LOG+HANDOFF na main) fica Tier 0 [W] (token), deixada de fora de propósito. **Residual real = só Tier 0/infra de [W]:** 17 ADRs da fila, collector CT 100, `profile_distiller_drift`+Brain A, índice temático 0042-0189. Doc reconciliado: `Governança Scorecard vs Estado-da-Arte CC.html` (7.8→8.2).

## Em aberto (aguardam Wagner — Tier 0)
0. **[Tier 0 · só [W]]** ADR formal do shift 0-humano (texto pronto no overlay `PROTOCOL.md §2`) + merge do PR `docs/cowork-loop-protocol-10-4` + decisão de migrar drift `.css` (`cowork-financeiro-bundle.css` 188 hex / bubble 220 / `Sidebar.tsx vibeAccent('workspace')`) pro roxo `oklch(0.55 0.15 295)`.
1. **[PRONTO p/ disparar] DS Roadmap até `ds/*=0` (6 filas 1-por-1):** entrada = `prototipo-ui-patch/PROMPT_PARA_CODE_DS-ROADMAP-ATE-ZERO.md`. Fases: **A** controles+FieldError T1 (`PR-C-WORKLIST.md`, 10 módulos, Sells lidera) · **B** cor crua→token (`SWEEP-arbitrary-color.md`) · **C** Onda G badge +5 variants (`ONDA_G_BADGE_VARIANTS.md`, pré-req) · **D** lote-badge 410 Tipo 2 (`LOTE-BADGE.md`) · **E** FormSection (`SWEEP-formsection.md`) · **F** icons (inline). Pré-req geral: componentes Onda F do PR-A existirem. Tudo merge em série, PARA no gate visual. Módulo = id canônico ("C#" deprecado).
2. Migrar Compras do hex → `--accent` escopado (prova do caso extremo).
3. Executar limpeza canon/archive (PLANO_ORGANIZACAO_CASA.md já lista).
4. Gerar ponte DS 4.2 → Claude Code (patch + prompt zero-toque) após aprovar spec.

## Ritual (fecha o loop)
1. **Início:** ler este STATUS + índice de ADRs.
2. **Durante:** produzir contra a espinha; arquivo novo nasce no lugar da IA.
3. **Fim:** decisão → ADR; estado de tela → atualiza este arquivo.

## Faxina 2026-05-30 — base limpa + arquivo indexado
Movidos pra `_arquivo/` (append-only, ver `_arquivo/INDEX.md` v1.0): 16 HTMLs de exploração (telas/ds/sessão/referência) + 13 mds de bridge processados + unificados `_arquivo-ds`→`ds-historico/` e `_audit`→`auditoria/`. **Nada apagado.** Raiz agora = app vivo + espinha + ponte viva. Rascunhos da sessão (Painel/Piloto/Auditoria/PT-03/DS v4.2) seguem como **propostas**, não canon.
> Entrega canônica de F1 = `prototipos/<tela>/page.tsx` + COMPARISON.md + critique-score.json (PROTOCOL.md §4).
> Cor canônica = roxo `primary` `oklch(0.55 0.15 295)` (ADR 0235). Entrada de design = `memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md` (git).

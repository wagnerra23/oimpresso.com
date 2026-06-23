# PROMPT PARA CLAUDE CODE — PACOTE FINANCEIRO F2-APROVADO (4 PRs em série)

> **[CC] → [CL]** · 2026-06-10 · proposta sob PROTOCOL §10.4: **valide tudo contra `origin/main` FRESCO antes** (memória local do Cowork ≠ git); não cunhe número de ADR; se algo já estiver feito no main, não refaça — anote e siga.
> **F2:** [W] aprovou em 2026-06-10 no Cowork (textual: "aprovado") o pacote visual do Financeiro: 3 lentes + tela Impostos & obrigações + drawer padrão 9.75 + **type ramp (Tier 0 — autorizado por [W] textual "vai", 2026-06-10)**.
> **Referência F1 viva** = protótipo `oimpresso.com.html` do Cowork; os arquivos-fonte estão nas URLs abaixo (válidas ~1h; se expirarem, pedir ao [W] "regenera URLs do pacote Financeiro" no Cowork).

## URLs dos artefatos F1 (fetch com curl -L)

| Artefato | Conteúdo | URL |
|---|---|---|
| `financeiro-page.jsx` | FinHero c/ 3 lentes + applyLente + KPI-click + Drawer 3 camadas (hero fixo, KV empilhado, LenteFiscal ISS/DAS) | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/financeiro-page.jsx?t=b151e08d91f3e573bd94ed1659b38d1c5d952dd095e1e5c45d2f7fe183b9b37f.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781099880.fp&direct=1 |
| `financeiro-telas-extras.jsx` | `TelaImpostos` completa (guias, calendário, NF↔título, "Lançar a pagar") | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/financeiro-telas-extras.jsx?t=11df1bb962760cf5b7258d9194655d352a60bd53ce7ec823376f6019b9b72fe0.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781099881.fp&direct=1 |
| `financeiro.css` | fin-lens-seg/fin-stat-on + drawer (fin-dw-hero, fsm-compact) + tokens (3 `white`→var) — 100% ramp --fs | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/financeiro.css?t=78266e41e3b1d7f5833079838f3d73da991a436181545b905b3ba82b53c0fd8b.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781099881.fp&direct=1 |
| `fin-boletos.css` | KPI hero dark-safe (classe do bug #2209) + fin-num via --pos/--neg | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/fin-boletos.css?t=56c18f10ebf0774e4ce495fa26311d441a5daff22ce00746d15728b86ec152ec.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781099882.fp&direct=1 |
| `ds-v6/tokens.css` | **Type RAMP `--fs-1..9`** (§Type RAMP, com regras de peso/linha/tracking no comentário) | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/ds-v6/tokens.css?t=75ea00a8f76adb17d7d6aaa019db57a36188cc5fc5a25b8eaaabb88bbbdf5a0f.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781099883.fp&direct=1 |
| MWART US-FIN-029 | visual-comparison pronto (commit como está) | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/memory/requisitos/Financeiro/unificado-3-lentes-visual-comparison.md?t=8c465f4e08403d46fa6ee073e14ae80d1ae427b6ffa5bdc34ebac2db72979633.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781099884.fp&direct=1 |
| Prompt US-FIN-029 (detalhe do PR-1) | spec completa: lentes `?lente=`, clamp, FinModuleTopnav, charter v14, Pest | https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/PROMPT_PARA_CODE_US-FIN-029-3-LENTES.md?t=dcad175e3037807a638bd379a4aa3f59513757851726b53880e6d3c6a3319679.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781099884.fp&direct=1 |

## PR-1 — US-FIN-029 · 3 lentes no Unificado (não-Tier-0 · merge autônomo c/ CI verde)
Seguir o prompt dedicado (URL acima — ele é a spec; já estava enfileirado em COWORK_NOTES 06-09). Resumo: segmented **Caixa · A receber · A pagar** (`?lente=`, clamp caixa) substitui a fileira de ~7 botões (anti-pattern reprovado [W], charter v13) + menu `···` + chips refinam DENTRO da lente + KPI-click seta lente + extrair `<FinModuleTopnav>` + charter v14 + `UnificadoLentesGuardTest` + MWART (URL acima). **Novidade vs 06-09:** o F1 agora está IMPLEMENTADO no protótipo (`financeiro-page.jsx`: `FIN_LENTES`, `applyLente`, `FilterBar lente=`, KPIs com `fin-stat-click/on`) — use como referência de comportamento.

## PR-2 — Tela "Impostos & obrigações" (não-Tier-0 na UI; domínio = estimativa visual)
- **O quê:** sub-tela do Financeiro (entra no `FinSubNav`, padrão das demais sub-páginas) com: (a) 3 KPIs (a recolher no mês · próxima obrigação · % receita com NF); (b) tabela de guias (FGTS · DCTFWeb/INSS · DAS Simples estimado ≈6% sobre o RECEBIDO do mês, regime caixa · histórico paga) com status a vencer/paga/atrasada e ação **"Lançar a pagar"** → cria título payable no Unificado (costura, espelha o protótipo); (c) calendário de obrigações (lista datada); (d) painel **NF↔título** (recebíveis sem NF = base DAS distorcida, aviso pré-fechamento); (e) disclaimer fixo: "estimativa — apuração oficial no módulo Fiscal".
- **Referência F1:** `TelaImpostos` em `financeiro-telas-extras.jsx` (URL). Cores 100% tokens (pares `-soft`); PT-BR; sem emoji.
- **Validar antes:** censo Fiscal de 06-09 apontou que "impostos-a-recolher + calendário" não existe em nenhum módulo (⚠ inferido dos charters) — confirme no main; se algo nasceu, integrar, não duplicar.
- Charter novo ao lado do .tsx + casos + screenshots no PR.

## PR-3 — Drawer Unificado: hierarquia 3 camadas + densidade (não-Tier-0)
Aplicar no `Unificado/Index.tsx` (drawer) o padrão F2-aprovado (referência: `financeiro-page.jsx` Drawer + `financeiro.css`):
1. **Hero fixo fora do scroll** (header → hero → tabs → corpo): label de estado uppercase (destructive se atrasado) · valor mono tabular grande com prefixo/centavos pequenos (`whitespace-nowrap` no prefixo) · chip + vencimento à direita · FSM compacto.
2. **KV empilhado** (label muted xs em cima, valor sm medium embaixo) em grid 2-col — substitui label-esq/valor-dir.
3. **Lentes** com ícone em quadradinho `primary/10` + título sm semibold; Conciliação conciliada = box discreto (bg muted + check pequeno), não banda verde.
4. **Lente Fiscal**: linhas justify-between mono "ISS retido · 5%" e "No DAS do mês · ≈6%" + link pra tela do PR-2.
5. **Token fixes** — VALIDAR no main antes (Regra 6; #2209 já consertou o hero dark no live): se sobrar `white` cru em fsm-step done/botão de comentário/trouble-icon no live, tokenizar (`--accent-fg`/superfície) como no F1.

## PR-4 — Type ramp + TEMPERO no DS do repo (⚠ FUNDAÇÃO — autorizado por [W] textual "vai" + "sim sim é isso que eu quero" 2026-06-10, registrar no PR)
- **O quê (2 blocos, mesma fonte `ds-v6/tokens.css` na URL acima):**
  - **§Type RAMP:** os 9 tokens `--fs-1..9` (10.5 · 11.5 · 12.5 · 13.5 · 15 · 18 · 22 · 28 · 38px) como âncora única de tamanho tipográfico (regras de peso/linha/tracking no comentário do token).
  - **§TEMPERO (norte visual):** `--sh-1`/`--sh-2` (sombra de 1 fonte de luz, par light+dark) · `--ease` cubic-bezier(.22,1,.36,1) + `--t-1/--t-2` · `--atmo` (atmosfera radial: alpha ~5% light / ~25% dark, aplicada como background-image do shell) · regra de medida (título ≤24ch `text-wrap:balance`, prosa ≤60ch `pretty`) · soft-state via `color-mix` do próprio tom. Fonte conceitual: `Norte - Fluxo do Caminhão.html` (Cowork); aplicação de referência: `styles.css` do protótipo (body/os-stat/os-drawer/os-modal).
- **Como (respeitar foundation-guard ①):** token definido SÓ em `cockpit.css` ou num `foundations.css` novo + entrada na allowlist `.foundation-guard-files.json` via PR revisado. NÃO definir em css de tela (baseline congelada).
- **Alinhar com o primitivo `Text` (ADR 0253):** o ramp é a fonte de valor; o `Text` consome/mapeia (size xs→--fs-1 … 5xl→--fs-9 ou equivalente). Não criar segunda escala — UMA âncora.
- **Gate (Regra 7 — estender, não reinventar):** propor extensão do `conformance-gate.mjs` (ou stylelint rule) que conta `font-size` px fora do ramp em css de tela, ratchet only-down — mesmo padrão dos gates existentes, com controle-negativo versionado. Espelho runtime já existe no Cowork (G8 do qa-conformance v2.3).
- **Adoção:** NÃO sweep global neste PR — só a fundação + gate baseline. Snap tela-a-tela segue nas ondas (Financeiro do live pode snapar no PR-3 se o gate já estiver de pé).

## Execução
```bash
git checkout main && git pull
# PR-1: seguir o prompt US-FIN-029 (URL acima) — branch feat/us-fin-029-unificado-3-lentes
# PR-2: branch feat/fin-impostos-obrigacoes
# PR-3: branch feat/fin-drawer-3-camadas
# PR-4: branch feat/ds-type-ramp-fs-tokens  (citar autorização [W] 2026-06-10 no corpo do PR)
# Em cada um: fetch das URLs → implementar → ui:lint, eslint, stylelint, conformance, pest → MWART/screenshots @1280/@1440 → PR
# CI verde + não-Tier-0 → merge autônomo. PR-4 (fundação): allowlist via PR revisado; conteúdo já autorizado por [W].
```
**Ordem recomendada:** PR-4 (fundação) → PR-1 → PR-3 → PR-2. Atualizar `SYNC_LOG.md` + `CODE_NOTES.md` ao fim de cada merge.

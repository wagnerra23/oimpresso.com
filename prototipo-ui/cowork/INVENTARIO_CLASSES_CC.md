# INVENTÁRIO DE CLASSES — integridade do host (2026-06-02 · [CC])

> Pedido de [W]: "ficou alguma class perdida… um inventário seria perfeito agora para manter 100% íntegro."
> Método: varredura programática (`run_script`) — classes **usadas** no markup (67 `.jsx`/`.js` + html carregados) × **definidas** no CSS (19 folhas do host), filtrando utilitários Tailwind. Fonte de verdade: o que o `oimpresso.com.html` realmente carrega.

---

## 🔴 ACHADO PRINCIPAL — vocabulário `os-*` DUPLICADO no styles.css (55 seletores)

**O que é:** `styles.css` tem **duas definições concorrentes** dos componentes do shell (`.os-tab`, `.os-table`, `.os-search`, `.os-row`, `.os-drawer`, `.icon-btn`, `.os-tabs`, `.os-table thead th`, etc.) — uma original limpa baseada em token, outra colada de outro módulo quando layouts foram juntados.

| Versão | Característica | Exemplo `.os-table` |
|---|---|---|
| **[0] canônica** | token-based, limpa | `width:100%; border-collapse:separate; border-spacing:0; font-size:13px;` |
| **[1] colada (drift)** | cores HARDCODED, fura v5 | `width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--border, oklch(0.93 0.01 250));` |

**Impacto:** a **[1] vence no cascade** (está mais abaixo no arquivo) → telas renderizam com cores cruas que **ignoram a migração v5 de tokens**. É a "duplicata de layout" que [W] sentiu.

**Seletores afetados (amostra):** `body` (3×) · `.os-tab` `.os-tab:hover` `.os-tab.active` `.os-tabs` · `.os-table` `.os-table thead th` · `.os-search` `.os-search input` · `.os-row` `.os-row:hover` `.os-row.urgent` · `.os-drawer` `.os-drawer-h` `.os-drawer-actions` · `.icon-btn` `.icon-btn:hover` · `.sb-group-h` `.sb-user-btn` · `.os-page-h-r` (uma cópia é `display:none!important`) · `.os-modal` `.os-new-price-i` …

**Remediação (proposta — precisa de OK de [W], blast radius alto = todas as telas):**
1. Adotar a **[0] token-based como canônica** (respeita o v5).
2. Portar qualquer ajuste **intencional** da [1] (ex: paddings novos) pra [0].
3. **Deletar a [1]** (a cópia hardcoded). Slima o styles.css E remove drift de cor que briga com o v5.
4. Testar cada tela (a [1] vence hoje, então remover muda o render — verificar antes/depois).
> ⚠️ NÃO é delete cego: a [1] vence hoje, então a remoção reverte pro look [0]. Reconciliação por seletor + gate visual.

### ✅ PROGRESSO 2026-06-02 — Fase 1: DE-DRIFT do bloco [1] (feito + verificado)
[W] autorizou ("pode fazer, você é o responsável, compara antes/depois"). Como a [1] vence e o look dela está bom (tabelas brancas bordadas), a decisão foi **manter a estrutura [1] e tokenizar as cores cruas** (em vez de deletar e reverter pro [0]). Tokenizado em `styles.css` (8 pontos do bloco `.os-kpi/.os-tabs/.os-tab .count/.os-search/.os-table/.os-table thead th/.os-table td/.os-drawer-head/.vd-stepper`):
- `#fff` → `var(--surface)` · `oklch(0.98 0.005 250)` (frio) → `var(--bg-2)` · `oklch(0.96 0.01 250)` → `var(--border-2)` · `var(--border, oklch(...))` → `var(--border)`.
- **Verificado por `eval_js` (computed styles):** `.os-table` bg = `oklch(1 0 0)` (surface), `thead` e `td` agora hue **95 quente** (era 250 frio). Zero cor crua 250 restante. Value-preserving (branco=branco; near-whites quentes). Console limpo. Screenshot do agente estava fora do ar → verificação por computed-style + verificador forkado.
- **Nota:** a maioria das cores do bloco já era `var(--token, fallback)` = já resolvia no v5; o drift real era pequeno (~6 cores cruas). Risco baixo, confirmado.

### ⏭️ Fase 2 (pendente): COLAPSAR [0]+[1] em UMA regra
Agora ambas as cópias são v5-consistentes (sem conflito de cor). Falta remover a duplicação estrutural (colapsar pra 1 def por seletor) — fazer com gate visual antes/depois por tela quando a ferramenta de screenshot voltar. Único idêntico-exato seguro hoje: `.vd-step.done .vd-step-num`.

**Idênticos (remoção 100% segura, sem mudança visual):** só `.vd-step.done .vd-step-num` (2× cópia exata).

---

## 🟡 ÓRFÃS (usadas no markup, sem CSS no host) — 139 estritas, MAS quase todas benignas

Triagem honesta (verificado por grep):
- **Contêineres-pai sem estilo próprio** (`os-approve`, `fin-comments`, `vd-create`): os FILHOS é que têm CSS (`.os-approve-body`, `.fin-comments-h`). O pai é só hook — **não é perda**. As defs "completas" só existem em `resources/css/*` (repo, não carregado) scopadas sob `.fin-cowork`.
- **`twk-*` (21):** o painel Tweaks (`tweaks-panel.jsx`) injeta `<style>` próprio em runtime — **não é perda**.
- **Dinâmicas** (`kb-block--${t}`, `ofc-grade-`, `vd-ai-${state}`): a base+variante existe no CSS; o literal com `-`/`${}` não casa no grep — **falso positivo**.
- **`[∅]` palavras soltas** (`kanban`, `compact`, `lead`, `tab`): variáveis JS / valores de estado capturados como token — **não são classes**.

**Veredito:** nenhuma órfã é regressão da fusão de tokens. O app renderiza íntegro (6 telas + verificador confirmaram). Resíduo real a investigar 1-a-1 (baixa prioridade): `vco-page`, `vrep-page`, `vendas-table`, `pdv-pay`, `nfe-review` — provável que sejam telas-mockup ou hooks.

---

## ⚪ MORTAS (CSS definido, nunca usado) — 471 candidatas

Maioria = blocos de mockup/legado (`casos-*`, `bi-*`, `br-*`, `art-v1/2/3`, `bc-*`) e variantes não-acionadas. Limpeza é higiene, não urgência. NÃO deletar sem checar dinâmicas (`stage-${x}` usa `.stage-pago` etc. que parecem "mortas" mas são montadas em runtime).

---

## Sequência recomendada (slim seguro → "no final só 1")

1. **[este doc]** inventário — mapa pronto. ✅
2. **Reconciliar os 55 `os-*` duplicados** (adotar [0] token-based, deletar [1] hardcoded) — **maior ganho**: slima styles.css + cura drift de cor anti-v5. Gate visual por tela. ← *próximo passo real, aguarda OK de [W]*
3. **Migração de vocabulário** `os-*` → `ds-v5/components.css` (`.os-btn`→`.btn` etc.) — **por tela, atrás dos casos**, NÃO bulk (vocabulários paralelos: 272 classes v5 não existem no shell, só 52 batem e são modificadores).
4. **Limpeza de mortas** — por último, com cuidado com classes dinâmicas.

## Trilha do tempo
- 2026-06-02 · [CC] gerou o inventário após [W] pedir integridade pós-fusão de tokens. Achado-chave: 55 `os-*` duplicados (drift hardcoded vence cascade). Órfãs = benignas (hooks/runtime/dinâmicas). Recomendação: reconciliar os duplicados como próximo slim.

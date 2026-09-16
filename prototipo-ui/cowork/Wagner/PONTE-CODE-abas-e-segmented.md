# Ponte pro Code — abas e segmented: convergir, não criar

> **Para:** [CL] Claude Code · **De:** [CC] · **Data:** 2026-08-31
> **Tipo:** pedido de trabalho (ponte). Não é memória, não é ADR, não é retrato de build.
> **Fonte lida neste turno:** `wagnerra23/oimpresso.com@main` (`84b62eb785e8`) — `package.json`,
> `resources/js/Components/shared/PageHeaderTabs.tsx`, `shared/SubNav.tsx`, `ui/segmented.tsx`,
> árvore de `Components/ui/`, busca por call sites em `resources/js/Pages/`.

---

## 1. A descoberta que muda o pedido

Eu ia pedir "criem um `Segmented` no DS, o DS não tem". **Errado.** Produção já tem **três**
implementações canônicas convivendo:

| Componente | Path | O que cobre | Base |
|---|---|---|---|
| `PageHeaderTabs` | `Components/shared/PageHeaderTabs.tsx` | Abas de módulo em faixa própria: `icon` opt-in, `badge` (o pill `.cli-moduletopnav-n`), overflow `⋯ Mais`, auto-promoção da aba ativa, teclado ←/→/Home/End, hue OKLCH por grupo | `Link` Inertia + `DropdownMenu` |
| `SubNav` variante `underline` | `Components/shared/SubNav.tsx` | Sub-navegação de baixo contraste dentro da página | `Link`/`button` + `Badge` |
| `SubNav` variante `segmented` | idem, `function Segmented` | Pílula inline: ativo com `bg-background` + `shadow-sm` | `div role=tablist` |
| `Segmented` | `Components/ui/segmented.tsx` | Toggle de 2–3 opções, canon `.cw-segmented`, prop `accent` | Radix `ToggleGroup type=single` |

`PageHeaderTabs` já é a aba do protótipo, com fidelidade documentada linha a linha
(`.cli-moduletopnav-tab.active` → `borderBottomColor: var(--accent)` + pill
`color-mix(--accent-soft 50%)`). Já está em **Cliente/Index**, **Arquivos/Index**,
**Fiscal/Config**, **Fiscal/Dfe** e em todo o Financeiro via `_shared/FinanceiroSubNav`.

**Conclusão:** em produção não existe lacuna de componente. Existe **duplicação** e
**telas que ainda desenham a própria pele**.

---

## 2. Decisão que precisa de [W] antes de qualquer código

**Dois segmented canônicos.** `SubNav variant="segmented"` (Tailwind, `role=tablist`,
aceita `href`/`badge`/`icon`) e `ui/segmented.tsx` (Radix ToggleGroup, `.cw-segmented`,
`accent`). Fazem a mesma coisa com semântica diferente — um é navegação, o outro é
seleção de valor.

Proposta (minha, para [W] ratificar ou derrubar):

- **`ui/segmented.tsx`** = escolha de **valor** de formulário (PF/PJ, Cliente/Fornecedor,
  densidade, lente). Semântica de toggle, é o que Radix entrega.
- **`SubNav variant="segmented"`** = escolha de **vista/seção** (Lista/Kanban, período,
  recorte). Semântica de tablist, aceita `href`.
- A regra fica escrita no charter do DS, e `npm run reuse:duplicates` para de ver isso
  como dívida.

Sem essa decisão, qualquer migração só espalha a dúvida.

---

## 3. O que o DS espelho (claude.ai/design) precisa ganhar

O espelho **não tem `Segmented`** — foi por isso que eu inventei um no protótipo. Enquanto
não tiver, todo protótipo novo vai inventar de novo.

1. **`Segmented`** no espelho, espelhando a decisão do item 2 (mesma API do escolhido).
2. **`TabBar` com 7 props que hoje forçam wrapper:** `ariaLabel`, `pad`, `wrap`,
   `dataContract`, `off` (aba visível e apagada — regra de contrato em Configurações),
   `size="sm"` (sub-nav em drawer, 26px), `icon:string`.

O item 2 é o que mais importa, e o porquê está no item 5.

---

## 4. Mapa: pele do protótipo → componente de produção

Abas de módulo → **`PageHeaderTabs`** (ou `SubNav underline` quando é sub-seção interna):

| Pele no protótipo | Tela | Tela em produção |
|---|---|---|
| `pd-abas` | Produtos (catálogo) | — (ver item 5: caso que travou) |
| `hrm-tabs` | RH · Essenciais · Configurações | — |
| `cd-tabs` + `cd-subnav` | Drawer do cliente (760) | Cliente/Index usa `PageHeaderTabs`; o drawer, não |
| `gov-tabs` · `cnx-tabs` | Governança · Connector | — (ambas já com `data-contract`) |
| `mfg-tabs` · `crmf-tabs` · `pf-tabs` · `vrep-tabs` · `pm-typenav` | Manufacturing · CRM ficha · Perfil · Relatórios de venda · Produção mecânica | — |
| `arq-chip` | Arquivos | Arquivos/Index **já** usa `PageHeaderTabs` |
| `fin-subnav` | Boletos | Financeiro **já** usa via `FinanceiroSubNav` |
| `ofx-tabs` · `fj-clog-tabs` · `chat-tabs` | OS Oficina · Forja · Jana | — |

Segmented → **componente escolhido no item 2**:

| Pele no protótipo | O que decide |
|---|---|
| `hrm-seg` · `pb-seg` | Confortável/Compacto (densidade) → **valor** |
| `vi-seg` · `vi-pills` · `prod-view-toggle` · `ofx-vsw` | Lista/Kanban/Grade, vertical → **vista** |
| `gov-seg` · `fin-seg` | Dia/Mês/Trimestre/Ano → **vista** (ou `PeriodBar` do DS, que já existe e ninguém usa) |
| `oi-seg` · `om-tpl-seg` · `jm-hist-filtros` · `cnx-seg` | Modo de exibição, filtro de biblioteca → **vista** |

Três desenhos incompatíveis para a mesma coisa no protótipo: caixa com divisórias
(`gov-seg`), pílula 999px (`cnx-seg`), trilho com sombra (`vi-seg`). Nenhum tinha
navegação por ←/→ — os dois de produção têm.

---

## 5. Restrições medidas — leia antes de migrar uma tela

Eu tentei essa migração no protótipo Cowork e **derrubei o boot**. O que aprendi custou
caro; não repitam:

1. **Wrapper em volta do `<nav>` quebra tela cujo CSS foi escrito para o nav ser o item de
   layout.** Em Produtos o nav das abas é item direto do grid do page-head
   (`produto-catalogo.css`: `.pd-abas{grid-column:1/-1;overflow-x:auto}`). Envolvendo o
   nav num `<div>`, a rota **travava a thread na carga**: React congelava (contadores de
   `createElement` paravam), nenhum erro no console, a página nunca pintava. Reprovados:
   tirar a classe legada do nav, `display:contents` no wrapper, remover a folha nova,
   restaurar todo o CSS do `main`. A causa exata entre o `TabBar` e o layout da página
   **não foi isolada**.
   → Em produção, `PageHeaderTabs` renderiza o `role=tablist` como `div` irmão, não
   embrulha nav de terceiros. **Mantenham assim.** Ao migrar uma tela, confira se o
   elemento antigo era item de grid/flex do pai antes de trocar a árvore.
2. **Folha nova entra diferida.** No host do protótipo, todo `<link>` usa
   `media="print" onload="this.media='all'"`. Um `<link>` render-blocking derruba o boot em
   qualquer rota. (Em produção isso é Vite; não se aplica — mas vale para o protótipo.)
3. **`.cli-tabs` já existe no DS** (`styles.css`: `margin: 8px 0 14px`). Não batizar nada
   novo com esse nome. Procurar o nome no DS antes de criar classe.
4. **Deleção de CSS é regra por regra.** Meu regex com `[^{]*` atravessou a fronteira da
   regra e apagou vizinhos inocentes — inclusive a que estilava **`.os-btn`**, compartilhada
   no app inteiro, em `produto-blade.css`. Se algum passe de limpeza for feito, com texto
   conferido e diff lido.

---

## 6. Gates a rodar (nomes reais do `package.json`)

Trabalho de componente compartilhado + telas:

```
npm run typecheck && npm run typecheck:baseline:check
npm run lint && npm run lint:baseline:check
npm run stylelint && npm run stylelint:baseline:check
npm run reuse:check && npm run reuse:duplicates     # a duplicação do item 2 aparece aqui
npm run components:check                            # árvore de componentes
npm run layout:check                                # primitivas de layout (o item 5.1 mora aqui)
npm run a11y:check                                  # ←/→ e role=tab entram no ratchet
npm run contrato:check                              # data-contract das telas migradas
npm run pageheader:guard                            # migração de header/abas
npm run design:coverage:check
npm run css:size:check
npm run test && npm run test:fin-subnav
npm run visreg:pixel                                # baseline visual; --update só com [W2]
```

`npm run ds:push` leva as fundações do git pro espelho do design (direção canônica
git→design, ADR 0239/0315). O caminho inverso é opt-in do [W], com triagem.

---

## 7. O que NÃO fazer

- ⛔ Criar um quarto segmented, ou um `TabBar` novo. Já são três; o pedido é convergir.
- ⛔ Migrar tela sem `.charter.md` + `.casos.md` atualizados (prontidão é máquina:
  `scripts/qa/prototipo-readiness.mjs`).
- ⛔ Mexer em `Produtos` até o item 5.1 estar entendido — foi a tela que travou.
- ⛔ `--update-snapshots` no visreg sem aprovação de screenshot ([W2]).
- ⛔ Tratar este arquivo como canon: é pedido de trabalho. O canon é ADR + charter no `main`.

---

## 8. Estado do protótipo Cowork agora

Build **restaurado integralmente do `main`** (330 arquivos) depois do travamento. Sobre ele,
**uma** alteração local, verificada:

- `inbox-page.jsx` — TDZ corrigido: `useInboxKeyboard` estava declarado **acima** do
  `useMemo` de `filteredConvs`, e o array `deps` é avaliado na chamada do hook, não no
  evento. A tela de Atendimento quebrava em todo render
  ("Cannot access 'filteredConvs' before initialization"). Verificado: abre com as 8
  conversas, sem erro. **Este é o único delta que vale trazer pro `main` hoje.**

A migração de abas/segmented **não está no build** — está descrita aqui como proposta.

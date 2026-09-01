---
id: requisitos-arquivos-index-gap
tela: Arquivos/Index (/arquivos)
prototipo: prototipo-ui/cowork/arquivos-page.jsx
tela_viva: resources/js/Pages/Arquivos/Index.tsx
gerado_em: 2026-09-01
comparacao: memory/requisitos/Arquivos/Index-visual-comparison.md
---

# GAP-SPEC — Arquivos/Index

> Derivado da **primeira medição D6 desta tela** (`design-diff.mjs --compare prod.json design.json
> --check`, 2026-09-01, tema `dark` nos dois lados, sonda canônica provada equivalente A×B).
> O comparador emitiu 5 `DIVERGE(bug)`; a triagem abaixo separa o que é **defeito** do que é
> **escopo declarado** — a máquina não conhece o charter, e tratar os 5 como defeito reintroduziria
> o que o `Index-visual-comparison.md` já registra como `PROD-A-FRENTE`.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Pílula de estado sem `dot` | **Defeito, e não é desta tela.** O protótipo renderiza a pílula pelo `StatusBadge kind="sla"` do espelho DS, cujas famílias namespaced saem **com dot** (medido: `dot: true` em col2 e col5). O vivo usa `Badge`/`StatusBadge` do repo, que **não implementam dot** (`rg dot` em `Components/ui/badge.tsx` + `Components/shared/StatusBadge.tsx` = 0 ocorrências). O **AP7** do [PRE-MERGE-UI](../_DesignSystem/PRE-MERGE-UI.md):69 é literal: *"Sem `bg-fill` em status badges — usa **dot** + texto colorido (Stripe-style)"*. O #6268 e o A8 do pedido Cowork pagaram a metade `fill→soft` e **a perna do dot ficou**. | **Decidir [W].** É mudança de primitivo com raio medido de **66 telas** (`git grep -l` por import de `badge`/`StatusBadge` em `Pages/**/*.tsx`), logo fora do escopo "tela Arquivos". Some-se que **nenhum gate mede AP7**: o único script que o cita é `prototipo-ui/audit/backlog.mjs`, que nenhum workflow invoca — é heurística de backlog, não catraca. |
| Sub-linha da Classificação sem `mono` (col2) | **Divergência de forma.** Protótipo: `<small class="mono">Restrito</small>`. Vivo: `<span class="text-xs text-muted-foreground">Restrito</span>` — mesmo conteúdo, sem a família mono que o protótipo aplica às sub-linhas. Com o protótipo soberano na FORMA (#6445), o vivo é que diverge. | **Decidir.** Cosmético e local (1 célula). Não entra de passagem porque muda copy renderizada sob `data-contract="acervo"` e o gate `contrato-de-tela` está verde hoje. |
| col0 sem `mono` | **NÃO é defeito — é o refino A6, aplicado de propósito.** O protótipo ainda tem `<code>nfe-xml</code>` (slug técnico, herda mono); o vivo trocou por `CONTEXTO_PT` em `<span>` porque `<code>` é para valor técnico, não para prosa (pedido `ARQUIVOS-REFINOS-PRODUCAO-2026-08-26.md` §A6, entregue no [#6339](https://github.com/wagnerra23/oimpresso.com/pull/6339)). **Prod à frente; o protótipo é que está atrás.** | Nenhuma. Registrado para não virar "bug" na próxima leitura. |
| col6 herda a cor / 0 blocos | **NÃO é defeito — é escopo declarado.** Protótipo tem 3 ações com texto (Baixar · Classificar · Excluir); o vivo tem 1 botão só-ícone com `aria-label` ([#6345](https://github.com/wagnerra23/oimpresso.com/pull/6345)). A onda 1 é leitura pura e o `ArquivosAdminControllerTest` reprova mutação no controller. | Nenhuma. Mutação é onda 2+, como o `Index-visual-comparison.md` já registra. |
| `colunasDeclaradas`: vivo 7 · protótipo 0 | **Prod à frente.** O vivo declara as 7 larguras em `<colgroup>`; o protótipo está em `table-layout:fixed` sem `<col>` com largura. O comparador classifica como `DIVERGE (fonte)`, não bug. | Nenhuma. |
| `title.weight`: vivo 700 · protótipo 600 | Divergência de tipografia do `PageHeader`, **não é da tela** — o `h1` vem do shell canon. | Fora do escopo desta tela; medir junto do shell (`--shell-roles`) antes de propor. |

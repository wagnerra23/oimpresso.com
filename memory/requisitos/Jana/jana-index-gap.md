---
id: requisitos-jana-index-gap
tela: Jana/Index (/ia)
prototipo: prototipo-ui/cowork/Wagner/jana-merge.jsx
tela_viva: resources/js/Pages/Jana/Index.tsx
gerado_em: 2026-09-22
comparacao: memory/requisitos/Jana/Index-visual-comparison.md
---

# GAP-SPEC — Jana/Index

> ## ⚠️ PROCEDÊNCIA — leia antes de usar, porque ela NÃO é a dos gaps irmãos
>
> Os gap.md desta pasta costumam abrir com *"derivado da primeira medição D6 desta tela
> (`design-diff.mjs --compare prod.json design.json --check`)"*. **Este não.** A medição D6 da
> Jana **não existe**: a tela não está entre as 78 de `governance/design/targets/medidas/`, e as
> 3 tentativas de produzi-la pelo CI em 2026-09-21 não fecharam (`NÃO MEDI — sem rota derivável`
> numa; `cancelled` por teto de job nas outras duas — ver [#7669](https://github.com/wagnerra23/oimpresso.com/pull/7669)).
>
> **O que sustenta a tabela abaixo, então:**
>
> | eixo | origem | força |
> |---|---|---|
> | estrutura (as 13 posições) | AST-por-indentação do `.jc-page` do Painel na âncora × `return` do `Index.tsx` | forte — é o código dos dois lados, contado |
> | tipografia do `h1` | `getComputedStyle` em browser real, CSS do projeto gerado pelo entry (`@tailwindcss/cli -i resources/css/inertia.css`, v4.3.3), controle positivo `folhaCarregou: true` | forte no ponto medido |
> | tudo mais (cor, espaço, rect) | **NÃO MEDIDO** | — |
>
> Portanto: **este gap cobre COMPOSIÇÃO e um ponto de tipografia. Não cobre pixel.** Quando a
> medição D6 aterrissar, ela pode acrescentar partes — e nenhuma linha daqui autoriza tratar
> ausência nesta tabela como ausência de divergência.

## A comparação que originou: cobertura do alvo × o que a página tem

`jana--index.secoes.json` declara **9** seções, todas filhas diretas de `.jc-page`. O Painel da
âncora tem **13** posições de filho direto. O recorte importa: das 4 diferenças, **3 não são
lacuna** e 1 é.

| Parte | Estado no vivo | Ação |
|---|---|---|
| `h1` do header — peso 700 × 600 da âncora | **Divergência ABERTA.** A âncora (`jana-merge.jsx:1058` → `JanaHeader` → `CliPageHead`, `chat-jana.jsx:216`) não declara peso e herda `colors_and_type.css:373` (`h1 { font-weight: 600 }`). O vivo recebe **700** de `Components/PageHeader/PageHeader.tsx:111` (`font-bold`), que o `JanaAreaHeader` (`Index.tsx:292`) consome. Medido no browser: **600 × 700**, `font-size` **22px nos dois** (o tamanho JÁ está par). ⚠️ Foi corrigido no [#7637](https://github.com/wagnerra23/oimpresso.com/pull/7637) e **revertido** no [#7654](https://github.com/wagnerra23/oimpresso.com/pull/7654) (*"entraram por auto-merge sem aprovação"*) — a divergência voltou. | **Réplica local, e o caminho já está provado.** Não mexer no default: as âncoras DISCORDAM — a de Vendas declara **700** explicitamente (`styles.css:4772` 0-1-1 e `financeiro.css:1727` 0-3-1, a que vence), com o comentário `styles.css:4765` dizendo *"mesmo CANON do PageHeader"*, e o 700 é decisão [W] datada (#1477, *"prefiro o mesmo peso do sells"*, re-medida em 2026-09-21: segue 700). Trocar o default alinharia **42** telas ao peso da Jana. O conserto revertido (prop opt-in `titleWeight`, default preservado) segue sendo o certo — falta **aprovação**, não solução. |
| A seção `header` do alvo mede o CONTAINER, não o título | **Lacuna de medição, não de forma.** `jana--index.secoes.json` §`header` aponta `.jc-page > div:first-child > header` e o `alvo.json` grava `fontSize: 13px · fontWeight: 400 · filhos: 2` — o alvo **sabe** que há 2 filhos e não mede o estilo deles. O `h1` é neto. Foi por aqui que a divergência de peso viveu 7 dias sem alarme. | **Decidir [W].** Estender o alvo exige re-medir por sonda contra render servido (o `.alvo.json` é medido, não escrito à mão — `secoes.json` declara *"COLHIDOS do DOM por `--mapa`, nunca de lembrança"*), e o `secao-check` que o consome roda `--servir-espelho` (espelho × espelho) e é advisory: ele pegaria a ÂNCORA mudar, não prod regredir. Quem protegeria prod é teste de componente, não o alvo. |
| `jm-nota-mob` (aviso de viewport) fora das 9 seções | **NÃO é lacuna.** `jana-merge.css:93` dá `.jm-nota-mob{display:none}` e só `@media (max-width:768px)` o mostra; o vivo faz o mesmo com `md:hidden` (`Index.tsx:335`). A sonda mede 2560/1440 — nessas viewports ele está oculto **dos dois lados**. Bônus da conferência: a copy é **idêntica byte a byte** (*"O painel foi desenhado pro escritório (1280px)..."*). | Nenhuma. Registrado para que a próxima leitura não o conte como buraco. |
| Skeleton (`JmPainelSkeleton`, âncora `:1061`) e estado vazio/erro (`:1062-1063`) | **Não é lacuna do alvo** — são ramos alternativos do mesmo nó; a sonda fotografa um instante, e o `secoes.json` já usa `--aguardar-sumir .jm-sk` justamente para não medir o esqueleto como se fosse a tela. | Nenhuma no eixo alvo. O estado vazio tem dono próprio (UC-JPAIN-29) e declara que **não foi observado em prod**. |
| 6 overlays: `JmMetaDrawer` · `JmMetasOverlays` · `JmDrillDrawer` · `JmAcaoModal` · `configDrawer` · `toast` (âncora `:1130-1135`) | **Não é lacuna do alvo** — só existem no DOM quando abertos. No vivo são `Index.tsx:534` (`JanaConfigDrawer`), `:552` (`JanaMetaNovaDrawer`), `:554` (`JanaMetaDrawer`) e os demais. | Nenhuma no eixo alvo. Medi-los exigiria roteiro de interação, que é outro instrumento (E2E), não a sonda de seção. |

## Denominador, para quem for reler

**13 posições de filho direto · 9 seções declaradas · 1 lacuna real** (o `h1`, por profundidade).
As outras 4 diferenças são overlays, estados alternativos e um elemento oculto na viewport medida
— todas explicadas, nenhuma é buraco de cobertura.

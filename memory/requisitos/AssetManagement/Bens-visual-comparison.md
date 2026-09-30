---
id: requisitos-asset-management-bens-visual-comparison
title: "Comparacao design x producao — Patrimonio/Bens (drawer de detalhe do bem)"
module: AssetManagement
tela: Patrimonio/Bens
owner: W
status: rascunho
# `inertia_target` + `last_updated` poem este doc sob o `visual-comparison-staleness.mjs`
# (mesma razao declarada no `Index-visual-comparison.md` deste modulo).
inertia_target: resources/js/Pages/Patrimonio/Bens.tsx
last_updated: "2026-09-30"
last_validated: "2026-09-30"
---

# Comparacao design x producao — `Patrimonio/Bens`, drawer de detalhe do bem

> **Escopo desta entrada:** so o **drawer de detalhe do bem** (leitura), entregue em
> [#8261](https://github.com/wagnerra23/oimpresso.com/pull/8261) pela decisao [W] de 2026-09-30
> (`prototipo-ui/cowork/Wagner/cowork-inbox/patrimonio/playbook/_saida-16b.md`). A LISTA de Bens
> nao foi comparada aqui.
>
> **Ancora computada** (`node scripts/design/ancora.mjs Patrimonio/Bens`):
> `prototipo-ui/cowork/Wagner/patrimonio-page.jsx`, `function BemDrawer` (aba `alocacoes`).
>
> **Frescor da fonte, medido em 2026-09-30:** `patrimonio-page.jsx` do espelho e **byte-identico**
> ao do Cowork vivo (projeto `019dcfd3-…`, `DesignSync.get_file`, `truncated:false`, 56.962 bytes,
> sha256 `23c34cb2d1f4c054…` nos dois lados). ⚠️ `patrimonio-page.css` **nao foi provado**: o
> `get_file` devolveu o arquivo inline (abaixo do piso de persistencia), e comparar exigiria
> transcrever — nao foi feito.

## Como foi medido

- **Protótipo:** espelho servido por `python -m http.server` na raiz do worktree (o shell resolve o
  DS em `../../design-system/` quando a URL contem `/prototipo-ui/cowork/`), rota `pat-bens`,
  bem `PAT-0006` (3 alocacoes, 1 revogada), aba Alocações.
- **Producao:** `https://oimpresso.com/asset/assets?bem=1`, empresa 1.
- **Os dois lados:** tema dark, 1440×900, `getComputedStyle` + `getBoundingClientRect` relativos
  a borda do drawer. Producao medida so depois de o carregamento terminar e com **duas leituras
  iguais** (a primeira leitura pegou "Carregando bem…" e foi descartada).
- **Limite:** a empresa 1 **nao tem nenhum bem cadastrado**, entao em producao so renderiza o
  estado "Bem nao encontrado" — **so o cabecalho e medivel**. O corpo (abas, cartoes) nao foi
  medido em producao; os valores de producao do corpo abaixo vem das classes do codigo.

## Cabecalho — medido nos dois lados

| Dimensao | Protótipo | Producao #8261 | Producao #8269 | Producao #8281 (atual) | Veredito |
|---|---|---|---|---|---|
| Padding | 16 / 18px | 16 / 16px | 16 / 18px | 16 / 18px | IGUAL |
| Titulo | 17px · 600 · x=19 | 16px · 600 · x=17 | 17px · x=19 | 17px · 600 · x=19 | IGUAL |
| Altura de linha do titulo | 22,1px | 24px | 25,5px | 22,1px | IGUAL |
| Subtitulo | 12,5px · x=19 | 14px · x=17 | 12,5px | 12,5px · x=19 | IGUAL |
| Altura de linha do subtitulo | 18,1px | — | 18,75px | 18,1px | IGUAL |
| Titulo ate subtitulo | 4px | 6px | 4px | 4px | IGUAL |
| Altura total do cabecalho | 76px | 83px | 81px | 77px | **DIVIDA A FECHAR** (+1px, origem nao medida) |

Os ajustes vieram de [#8269](https://github.com/wagnerra23/oimpresso.com/pull/8269) (espacamentos)
e [#8281](https://github.com/wagnerra23/oimpresso.com/pull/8281) (altura de linha). Medida final
com o deploy `a3ab9e0ac`.

## Corpo — medido so no protótipo

| Dimensao | Protótipo (medido) | Producao (pelas classes, #8269) | Veredito |
|---|---|---|---|
| Abas, laterais | 18px · aba 30px de altura, padding 0/14px | `px-[18px]` no `SubNav` | nao medido em producao |
| Secao do corpo | margem 10/12px · borda · raio 12 · padding 14/18px | `mx-3 my-2.5 rounded-[12px] border px-[18px] py-3.5` | nao medido em producao |
| Titulo da secao | 10,5px · 600 · caixa-alta · 8px abaixo | `text-[10.5px] font-semibold uppercase mb-2` | nao medido em producao |
| Cartao da alocacao | padding 10/13px · raio 8 · 7px entre cartoes | `px-[13px] py-2.5 rounded-lg` · `space-y-[7px]` | nao medido em producao |
| Linhas do cartao | 12,5 / 11 / 10,5px · 2px entre elas | `text-[12.5px]` · `text-[11px] mt-0.5` · `text-[10.5px] mt-0.5` | nao medido em producao |
| Campos do Resumo | 3 colunas (auto-fit 170px) · gap 10/16px | `grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-x-4 gap-y-2.5` | nao medido em producao |

**Raio 12:** o lint `ds/no-rounded-xl` diz "maximo `rounded-lg` (12px)", mas medido em runtime na
producao `rounded-lg` = **8px** e `rounded-xl` = 12px. O codigo usa `rounded-[12px]`, dentro do teto
de 12px do charter.

## Forma que ainda diverge — DIVIDA A FECHAR

Pela regra vigente (protótipo manda na forma, sem divergencia aceita), cada item abaixo e divida,
nao escolha. Os motivos de ainda nao estarem na tela estao no charter (`Bens.charter.md`, Non-Goals):

- **Abas:** protótipo 6 (Resumo · Garantia · Alocações · Manutenção · Depreciação · Histórico);
  producao 2 (Resumo · Alocações).
- **Placar da aba Alocações** (quantidade · alocada · livre): ausente. Agregado de quantidade sobre
  o `Alocado` ainda nao auditado (residuo Tier 0 do indice).
- **Botoes** (Revogar por alocacao · Alocar recurso · Enviar pra manutencao · Editar bem): ausentes.
  Escrita, de outras threads (17/18).
- **Breadcrumb e selo de garantia** acima do titulo: ausentes.
- **Descricao** como secao propria no Resumo (no protótipo e uma `DrawerSection` separada, junto de
  "Onde este bem aparece"): na producao fica dentro da secao Identificacao.
- **Cores** nao foram comparadas nesta rodada.

## Falta

- Medir o corpo em producao: precisa de um bem de teste com alocacao e devolucao na empresa 1.
- Provar o frescor de `patrimonio-page.css` pela rota de bundle (sem transcrever).
- D1 (rede): o drawer abre por partial reload (`only: ['bem_detalhe','bem_selecionado']`), mas a
  troca por clique na linha nao foi exercitada em producao, pelo mesmo motivo (sem bem).

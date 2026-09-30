---
sessao: "10"
titulo: "DRE — coluna Conta em mono — saída da thread"
autor: "[CL]"
data: 2026-09-30
base: wagnerra23/oimpresso.com@main 42aec2f8e2 (merge do #8238)
thread: 09-dre-conta-mono.md
veredito: "entregue — 3 células com font-mono, medido no render de produção (2 dos 3 tipos de linha; o tipo item não tinha linha no período)."
---

# _saida-10 · Coluna Conta da DRE em mono

Entregue no [#8238](https://github.com/wagnerra23/oimpresso.com/pull/8238), arquivo único `resources/js/Pages/Financeiro/Dre/Index.tsx`, 3 linhas.

## O que mudou

As 3 células da coluna Conta (linha de seção, item e subtotal) ganharam `font-mono`, o token do DS (`--font-mono`, IBM Plex Mono no escopo `.fin-cowork` da página). O `<th>` "Conta" ficou sans, como no alvo da thread 09 (`conta_titulo` = IBM Plex Sans, `conta` = IBM Plex Mono).

**PARAR SE não disparou:** a tabela é `<table>` nativo da página, não `shared/DataTable` com `meta.mono`.

Não tocou `BalanceteView.tsx`, CSS nem dados. O charter não tem Non-goal nem Anti-hook sobre fonte.

## Medição no render (não a classe declarada)

Produção `https://oimpresso.com/financeiro/dre`, deploy do merge `42aec2f8e2` concluído com sucesso (run 36714460887), 2026-09-30, tema dark, bundle `app-CvRER8vk.js`. Leitura feita depois de o número de nós estabilizar (715), repetida 1,5 s depois: **idêntica**.

| alvo | classe | `getComputedStyle(fontFamily)` | peso |
|---|---|---|---|
| 1ª célula de corpo (seção) | `… font-mono font-medium …` | `"IBM Plex Mono", ui-monospace, "SF Mono", Menlo, monospace` | 500 |
| `<th>` Conta | `… font-medium` | `"IBM Plex Sans", ui-sans-serif, …` | 500 |

Por tipo de linha, todas as células de corpo da coluna:

| tipo | células | fonte |
|---|---|---|
| seção | 4 | IBM Plex Mono |
| subtotal | 3 | IBM Plex Mono |
| item | 0 | **não medido** |

`document.fonts.check` confirma as duas famílias **carregadas**, não só declaradas.

## O que ficou sem medir, e por quê

- **Linha do tipo item:** o período medido não tinha nenhuma (as categorias não estão mapeadas no plano de contas, e a tela mostra só seções e subtotais). A classe está no código, mas render não houve. Medir de novo quando existir um período com itens.
- **`secao-check` da seção `tabela` contra o alvo da 09:** não rodado. O comparador mede o render do espelho ou uma URL; a DRE de produção exige login, e o navegador sem sessão não chega nela. A equivalência de fonte foi provada pela medida acima, na mesma propriedade que o alvo registra (`conta.estilo.fontFamily`).

## Recibos

- Placar: `contem Dre/Index.tsx "font-mono"` — 3 ocorrências.
- CI do #8238: G-6 da DRE revalidado (`last_run` 2026-09-30) e `SUPERFICIE.md` do Ponto regerado, drift herdado do `main`.

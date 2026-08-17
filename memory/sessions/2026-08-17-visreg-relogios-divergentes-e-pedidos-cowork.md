---
date: "2026-08-17"
topic: "Visreg: quatro relógios setTestNow na mesma fixture · recorte do 2º payload · recados pro Cowork"
authors: ["C"]
prs: [5860]
tldr: "Três pendências fechadas até onde daqui se pode fechar: (1) a zona cinza do visreg NÃO é ruído nem regressão do PR — o baseline mostra 11/06 (= o que o seeder grava) e o atual mostra 06/06, e existem QUATRO datas de setTestNow diferentes entre testes que compartilham a mesma fixture; (2) o recorte correto do 2º payload é 33 de design, não 53 — 20 são cópia de código de produção que o repo já tem; (3) dois recados pro Cowork prontos pra enviar."
---

# Visreg: quatro relógios na mesma fixture · e os pedidos pro Cowork

## 1 · A zona cinza do `IsolatedStatesBaselineTest` — o que É fato e o que é hipótese

### Fato, medido

O `visual-regression` do [PR #5860](https://github.com/wagnerra23/oimpresso.com/pull/5860) reprovou com a assinatura da lápide §5 2026-08-15: **`20 passed` seguido de `exit 2`**. O `throw` mora no `afterAll`:

```php
// tests/Browser/Support/VisregThreshold.php::writeGrayZoneSummary
if ($items !== [] && $approval !== '1') {
    throw new RuntimeException('Zona cinza visual pendente: … aplique o label visreg-gray-approved.');
}
```

Não é bug: é o gate exigindo aprovação [W]. Por isso procurar `FAILED` no log não acha nada.

**O diff, decodificado com `scripts/tests/snap-diff.mjs`** (a ferramenta nascida daquele incidente), não por leitura de nome de arquivo:

| | |
|---|---|
| pixels alterados | 6.656 / 1.930.176 = **0,3448%** |
| bandas | τ_low 0,1% · τ_high 2% → **zona cinza por definição** |
| Δmax | **253** → assinatura de CONTEÚDO (rasterização é Δ≤3) |
| células | 13 de 256 · linhas 2/4/5/6 contíguas = "string mudando" |
| os 3 estados (default/error/loading) | diff **idêntico** → região comum, não conteúdo de estado |

**Lendo os dois lados** (o que o [W] mandou fazer desde o começo — ver a tela):

| | baseline | atual |
|---|---|---|
| Vencimento | **11/06** · "vencendo" | **06/06** · "em atraso" |
| Status | Vencendo (âmbar) | **Atrasado** (vermelho) |
| KPI A RECEBER | "1 títulos" | "… **em atraso**" |
| chip "Só atrasados" | 0 | **1** |

**O baseline bate com o seeder.** `VisregFinanceiroFlowSeeder` grava `'vencimento' => '2026-06-11'`, e nunca mudou — `git log` do arquivo tem UM commit, `59f1d7f5d` de 2026-07-13. Logo o **atual** é que divergiu, não o baseline.

### O achado sistêmico: quatro relógios na mesma fixture

```
2026-06-06  A11yAxeBrowserTest:75 · AuthBridgeSmokeTest:41
2026-06-10  ConformanceProbesTest:52
2026-06-11  ComprasFlowBaselineTest:41 · FinanceiroFlowBaselineTest:23
            IsolatedStatesBaselineTest:68 · PixelBaselineTest:78
2026-06-23  Tier0RenderIsolationTest:71
```

Todos rodam contra o **mesmo** banco seedado no mesmo job, e o título vence `2026-06-11`. Isso significa que o MESMO registro é "a vencer", "vencendo" ou "atrasado" conforme QUAL teste está olhando — e o status pinta a UI (chip âmbar × vermelho, KPI, contador do filtro).

O `VisregOficinaBoardSeeder` já admite a dependência por escrito: *"relógio **REAL**. `is_overdue` é `$expected->isPast()` (ServiceOrderController)"*.

### O que NÃO consegui fechar, e por quê

**Por que a data EXIBIDA mudou de 11/06 para 06/06** segue sem explicação medida. O seeder grava 11/06 e não mudou; `setTestNow` altera o relógio, não o campo. Hipóteses não verificadas: outro seeder sobrescrevendo o registro, ordem de seed diferente entre runs, ou transformação na leitura.

**Não dá pra fechar daqui:** Pest Browser só roda no CI ou no CT 100 (hook `block-test-fora-ct100`), e o próprio arquivo declara *"NÃO rodado local"*. Afirmar causa sem rodar seria LC-08 — a classe que mais reincide neste projeto.

### O que fica pro conserto (quem tiver o ambiente)

1. **Unificar o relógio** entre testes que compartilham fixture — quatro datas para o mesmo banco é armadilha por construção, e o custo aparece como zona cinza recorrente.
2. **Enquanto não unificar**, todo PR que executar a matriz vai precisar do label `visreg-gray-approved` — e gate que sempre exige carimbo manual deixa de ser gate.
3. O `#5798` passou com **o mesmo diff** só por ter o label (`VISREG_GRAY_APPROVED: 1`). Mesma zona cinza, veredito oposto.

### Erro meu, registrado

Afirmei que o vermelho era *"herdado — os outros PRs também falham"*. **Errado.** Dos três que "passaram", **#5849 e #5813 tiveram o step `skipped`** — não executaram. Usá-los como controle é LC-13, exatamente a armadilha que a lápide de 2026-08-15 registra, repetida por mim no mesmo dia em que a citei. Só o #5798 era controle válido.

## 2 · O 2º payload — o recorte é 33, não 53

Medido cruzando `git ls-files` do espelho × `DesignSync.list_files` do vivo × ledger:

| | qtd | |
|---|--:|---|
| **A pedir** | **33** | design de verdade (`.jsx`/`.css`/`.html` de protótipo) |
| **NÃO pedir** | **20** | **cópia de código de produção** dentro do espelho de design |

Os 20 são: `Modules/Financeiro/Http/Controllers/*.php` (5) · `app/Http/Controllers/ProdutoUnificadoController.php` · `resources/js/Components/layout/*.tsx` (7) · `resources/js/Pages/{Financeiro,OficinaAuto,Produto}/**` (7).

**19 dos 20 já existem no repo real.** Baixá-los não sincroniza design nenhum: traz de volta cópia de código que o repo já tem, piorando a dupla-fonte que o `cowork-ssot-guard` existe pra impedir — e cujo header já declara não alcançar este caso.

Antes de pedir o payload, vale a pergunta anterior: **`prototipo-ui-patch/` deveria existir?** Parece bundle de patch antigo que virou cópia paralela do repo.

## 3 · Recados pro Cowork (escrita é gated — ADR 0315, precisa de [W])

**(a) `qa-conformance.js` — o vivo está ATRÁS do espelho.**
Espelho: **v2.5**, gates G1–**G15**. Vivo: **v2.4**, G1–G13. Faltam no vivo o **G14 (contraste AA)** e o **G15 (foco visível)**, que entraram pelo [#4597](https://github.com/wagnerra23/oimpresso.com/pull/4597) em 2026-07-20 direto no espelho. Aplicar o payload sobre ele **apagaria** os dois — foi por isso que ficou de fora. O Cowork precisa **puxar** o v2.5.

**(b) `forja-tarefas.jsx` — existe no vivo, o shell não carrega.**
Está no vivo e no espelho, define `window.ForjaTarefas` e `window.FORJA_TK_COUNT`, e **ninguém consome**: o `oimpresso.com.html` não tem referência a ele. Por isso fica fora do payload por construção (o manifesto deriva dos `src`/`href` do shell). Ou entra no shell, ou é resto — decisão do Cowork.

---
modulo: financeiro
titulo: "Financeiro — Unificado: onda = seção"
dono_pedido: "[CC]"
executor: "[CL]"
gerado: 2026-09-13
base_lido: ffe69844cb71
alvo_medido: NAO — BLOQUEANTE (ver §ALVO)
granularidade: secao (decisão [W] 2026-09-13)
---
# Financeiro — Unificado · fila de seções

> **Leia este arquivo + a thread da sua vez.** Read-order no `main`: `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` → `FRESCOR-PRODUCAO-vs-PROTOTIPO.md` → `PRE-FLIGHT-TELA.md` → `Pages/Financeiro/Unificado/Index.charter.md` + `.casos.md`.

## 🚧 ALVO NÃO MEDIDO — este pacote ainda NÃO é um EXPORT
O protocolo exige **10 blocos**, e o bloco 2 (a11y do alvo) e o 3 (medição) saem de **sondar o protótipo servido**: tema dark, após `__oiLazyDone`, **duas leituras iguais** de `querySelectorAll('*').length`, `getComputedStyle` (nunca a classe declarada), com caso de sanidade de valor conhecido antes de qualquer veredito. **Eu não inspeciono o protótipo deste lado** — então o que segue é **LEVANTAR + fila**, não pedido de pixel.

Quem pode fechar: uma passada de medição no protótipo servido (a mesma que produz o `ALVO <Mod>.<view>.<seção>`). Até então: **não mexer em espaçamento, medida ou cor** com base neste arquivo.

## Frescor — por que o Unificado é 🔵
`Financeiro/Unificado` é **produção à frente** do protótipo. A regra é **puxar o vivo, não refazer**. Toda seção abaixo nasce com a pergunta: *o vivo já resolve? então o protótipo é que atualiza.*

## Âncoras (medidas no `main`, 2026-09-13)
- **Charter/casos:** `resources/js/Pages/Financeiro/Unificado/Index.charter.md` (31.116 B) + `Index.casos.md` — traz UC-F01…F05 e UC-FUNI-01…04 (baixa parcial com SPLIT, quitação exata, recusa de título quitado, recusa de conta de outro business).
- **Âncora de layout:** `financeiro-page.jsx` (protótipo deste projeto) — `related_prototype` **e** `bundle_source` apontam o mesmo arquivo: sem conflito.
- **Âncora de implementação:** `resources/js/Pages/Financeiro/Unificado/` — **arquivo:linha NÃO medido**. O executor mede no próprio turno; não há linha citada aqui de propósito (citar de memória foi a falha de 13/09 nos contratos).
- **Contrato:** **não existe** para esta tela. Não confundir com `governance/design/contracts/caixa-unificada.contract.json` — é a tela Caixa. Ver thread 06 do playbook `ancora`.

## Seções (enumeradas por LEITURA ESTÁTICA do `financeiro-page.jsx`, não por medição)
| # | seção | marcas no protótipo |
|---|---|---|
| 01 | Faixa de KPI | `.os-stats.fin-stats` — 5 tiles: saldo previsto (hero + `.fin-spark` SVG + delta), recebido, a receber, pago, a pagar; cada tile é **lente clicável** (`.fin-stat-click`) |
| 02 | Barra de período | `.fin-periodbar` — campo de data (`CliSeg`) + navegação ‹ › + rótulo + intervalo custom com limpar |
| 03 | Toolbar de filtros | `.fin-toolbar` — checkboxes de ciclo de vida com contador, "só atrasados", "arquivados", `ContasFilter` (multi) |
| 04 | Ageing | `.fin-ageing` — barra segmentada por faixa (atraso · 0-30 · …) com % |
| 05 | Header | `.os-page-h.fin-hero` — título + subtítulo de sub-rota + 3 lentes + "Novo título" (menu) + overflow `···` |
| 06 | Tabela + drawer | **não enumerado** nesta leitura (o grep truncou em 20 KB) — levantar antes de virar thread |

```json
{
  "modulo": "financeiro",
  "view": "Unificado",
  "base_lido": "ffe69844cb71",
  "alvo_medido": false,
  "granularidade": "secao",
  "variaveis": {
    "CHARTER": "resources/js/Pages/Financeiro/Unificado/Index.charter.md",
    "CASOS": "resources/js/Pages/Financeiro/Unificado/Index.casos.md",
    "PROTOTIPO": "financeiro-page.jsx"
  },
  "decisoes": [
    {
      "id": "D-FIN-ALVO",
      "pergunta": "A passada de medicao do prototipo (ALVO por secao) roda antes das threads? Sem ela o pacote nao tem os blocos 2 e 3 e nao autoriza pixel. Recomendacao: sim, uma passada so, cobrindo as 6 secoes.",
      "respondida": false,
      "dono": "[W]"
    },
    {
      "id": "D-FIN-CONTRATO",
      "pergunta": "Financeiro/Unificado nao tem Contrato de Tela e tem 9 UC escritos. Escrever o contrato antes das ondas de layout (trava copy/secoes/estados no CI) ou depois?",
      "respondida": false,
      "dono": "[W]"
    }
  ],
  "threads": [
    {
      "id": "00",
      "titulo": "LEVANTAR a secao 06 (tabela + drawer) e medir o ALVO das 6 secoes",
      "dono": "CC",
      "arquivo": "00-INDICE.md",
      "prefixo": [],
      "nao_toca": ["resources/js/**"],
      "depende_decisoes": ["D-FIN-ALVO"],
      "provas": [
        { "tipo": "medicao", "exige": "por secao: contagem de nos estavel (duas leituras iguais), getComputedStyle das medidas que a thread vai citar, e um caso de sanidade de valor conhecido ANTES de qualquer veredito" }
      ]
    }
  ]
}
```

## O que este pacote NÃO resolve (bloco 7)
- **Nenhuma thread de layout emitida** — seria pixel sem medição.
- **Seção 06 não levantada** (grep truncado). Declarada, não coberta.
- **`arquivo:linha` do `main` ausente de propósito** — mede-se no turno da execução.
- **As 4 telas-irmãs** (`Conciliacao`, `Dre`, `Fluxo`, `Impostos`) compartilham **um** protótipo (`financeiro-telas-extras.jsx`): âncora não decide qual view. Não entram nesta view; dependem do formato por símbolo (decisão de [W], playbook `ancora`).

---
sessao: "00"
titulo: Visão geral (Home) — resíduo da onda 4
autor: "[CC]"
criado: 2026-09-23
base: wagnerra23/oimpresso.com@main 4dc1176f68df
---
# Visão geral (Home) — índice

**O pacote do módulo é `COLAR-NO-CODE-visao-geral-FECHAMENTO-EM-ONDAS.md`** (raiz do build, 04/09) — este índice só dá ao placar o que sobrou dele. Reconferido @4dc1176f68df: contrato `governance/design/contracts/dashboard-visao-geral.contract.json` **existe**; a11y/âncoras cobertos por UC-DASH-17/18; Pendências entregue (charter v6, UC-DASH-19); Blade removido (v5). Sobra 1 linha de charter.

> **Thread 01 DESCARTADA por decisão [W] (2026-09-23).** A premissa vinha da numeração do DS espelhado (PT-04 Modal / PT-05 Dashboard). O dono do código `PT-0X` é o catálogo do repo, onde PT-04 é Dashboard e PT-05 é Kanban — o charter já declara o PT certo. Medição em `_saida-01.md`.

```json
{
  "modulo": "Home",
  "sha": "4dc1176f68df",
  "gerado": "2026-09-23",
  "decisoes": [],
  "threads": [
    {
      "id": "01",
      "titulo": "Charter: arquétipo PT-04 → PT-05 (Dashboard)",
      "dono": "CL",
      "arquivo": "01-pt05.md",
      "bloqueio": "DESCARTADA por decisao [W] 2026-09-23 — o catalogo do repo (memory/requisitos/_DesignSystem/padroes-tela/ + pt-conformance.mjs) e o dono do codigo PT-0X: PT-04 = Dashboard, PT-05 = Kanban. O charter ja esta certo; a troca avermelha o pt-conformance (ver _saida-01.md). Nao executar; reabrir so com decisao [W] nova.",
      "prefixo": [
        "resources/js/Pages/Home/Index.charter.md"
      ],
      "nao_toca": [
        "resources/js/Pages/Home/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "resources/js/Pages/Home/Index.charter.md",
          "padrao": "PT-04 Dashboard"
        }
      ]
    }
  ]
}
```

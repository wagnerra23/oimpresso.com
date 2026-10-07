---
sessao: "00"
titulo: App das lojas (oimpresso-app) — índice
autor: "[CC]"
criado: 2026-10-06
base: wagnerra23/oimpresso.com@main 2fe69ddc0280 (lido 2026-10-06 17:08 UTC)
---
# App das lojas (oimpresso-app) — índice

**1 thread, fora do repo `oimpresso.com`.** Faltava este índice — a `01-mobile-com-ponto.md` estava solta. Pela errata de 2026-10-02 na própria ficha, o trabalho vive no projeto Claude Design "Mobile app structure review" e o código no repo `wagnerra23/oimpresso-app`; este playbook é só o registro do pedido. Sem prova de arquivo aqui: o placar deste repo não alcança o outro. Recibo `_saida-01.md` quando o Design entregar. ⚠️ Sem prova, o placar marca `feito` **só pelo recibo** — quem escreve o `_saida` tem de citar onde a entrega está (projeto + arquivo).

```json
{
  "modulo": "AppLojas",
  "sha": "2fe69ddc0280",
  "gerado": "2026-10-06",
  "decisoes": [
    {
      "id": "D-35",
      "texto": "Dashboard (tela 35) do app",
      "dono": "W",
      "respondida": true,
      "resposta": "[W] 2026-10-07 \"o que recomenda?\" → recomendação [CC]: fica para depois da v1 — v1 do app é Ponto + o essencial da loja",
      "quando": "2026-10-07"
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "App das lojas v1 — Ponto dentro do Mobile (desenho)",
      "dono": "Design",
      "arquivo": "01-mobile-com-ponto.md",
      "prefixo": [],
      "nao_toca": [
        "resources/js/Pages/",
        "ponto-page.jsx",
        "ponto-telas.jsx"
      ],
      "provas": []
    }
  ]
}
```

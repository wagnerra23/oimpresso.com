---
sessao: "00"
titulo: Frescor — atualizar o quadro
autor: "[CC]"
criado: 2026-09-23
base: wagnerra23/oimpresso.com@main ebe1fc8be7e4 (lido 2026-09-23 17:27 UTC)
---
# Frescor — índice

**1 thread.** Abrir com `/onda frescor --thread 01`. **Fazer antes das outras**: é o passo 2 do read-order do Cowork. Detalhe e evidências por linha: `../PEDIDO-FRESCOR-ATUALIZAR-2026-09-23.md`.

```json
{
  "modulo": "Frescor",
  "sha": "ebe1fc8be7e4",
  "gerado": "2026-09-23",
  "decisoes": [],
  "threads": [
    {
      "id": "01",
      "titulo": "FRESCOR: 8 linhas + caminho da Caixa + regra de validade",
      "dono": "CL",
      "arquivo": "01-atualizar-quadro.md",
      "prefixo": [
        "memory/reference/prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md"
      ],
      "nao_toca": [
        "resources/",
        "scripts/",
        "prototipo-ui/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "memory/reference/prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md",
          "padrao": "Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada"
        },
        {
          "tipo": "nao_contem",
          "path": "memory/reference/prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md",
          "padrao": "| **Atendimento/CaixaUnificada** | `inbox-page.jsx` (15/mai"
        },
        {
          "tipo": "contem",
          "path": "memory/reference/prototipo-ui/FRESCOR-PRODUCAO-vs-PROTOTIPO.md",
          "padrao": "14 dias"
        }
      ]
    }
  ]
}
```

---
sessao: "00"
titulo: Programa (Trilha D) — índice
autor: "[CC]"
criado: 2026-09-23
base: wagnerra23/oimpresso.com@main ebe1fc8be7e4 (lido 2026-09-23 17:33 UTC)
---
# Programa (Trilha D) — índice

**2 threads.** Absorvem `../PEDIDO-CL-programa-doc.md` e `../PEDIDO-CL-programa-doc-react.md` (movidos para esta pasta). A 02 depende da 01.

```json
{
  "modulo": "ProgramaDoc",
  "sha": "ebe1fc8be7e4",
  "gerado": "2026-09-23",
  "decisoes": [],
  "threads": [
    {
      "id": "01",
      "titulo": "Charter + casos do Programa",
      "dono": "CL",
      "arquivo": "01-trio.md",
      "prefixo": [
        "resources/js/Pages/Documentacao/"
      ],
      "nao_toca": [
        "resources/js/Pages/Documentacao/Programa.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Documentacao/Programa.charter.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Documentacao/Programa.casos.md"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Tela do Programa em React/Inertia",
      "dono": "CL",
      "arquivo": "02-tela.md",
      "prefixo": [
        "resources/js/Pages/Documentacao/Programa.tsx",
        "app/Http/Controllers/"
      ],
      "nao_toca": [
        "memory/"
      ],
      "depende_threads": [
        "01"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Documentacao/Programa.tsx"
        }
      ]
    }
  ]
}
```

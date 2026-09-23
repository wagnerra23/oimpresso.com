---
sessao: "00"
titulo: Oficina Auto — Vehicles
autor: "[CC]"
criado: 2026-09-23
base: wagnerra23/oimpresso.com@main ebe1fc8be7e4 (lido 2026-09-23 17:27 UTC)
---
# Oficina Auto — índice

**1 thread.** Abrir com `/onda oficina-auto --thread 01`. Não é EXPORT de layout (sem protótipo destas telas — ausência declarada). Detalhe: `../PEDIDO-VEHICLES-MERCOSULPLATE-2026-09-23.md`.

```json
{
  "modulo": "OficinaAuto",
  "sha": "ebe1fc8be7e4",
  "gerado": "2026-09-23",
  "decisoes": [],
  "threads": [
    {
      "id": "01",
      "titulo": "Vehicles: placa via MercosulPlate nas 4 telas",
      "dono": "CL",
      "arquivo": "01-vehicles-mercosulplate.md",
      "prefixo": [
        "resources/js/Pages/OficinaAuto/Vehicles/"
      ],
      "nao_toca": [
        "resources/js/Components/shared/MercosulPlate.tsx",
        "prototipo-ui/design-system/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "resources/js/Pages/OficinaAuto/Vehicles/Index.tsx",
          "padrao": "MercosulPlate"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/OficinaAuto/Vehicles/Show.tsx",
          "padrao": "MercosulPlate"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/OficinaAuto/Vehicles/Create.tsx",
          "padrao": "MercosulPlate"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/OficinaAuto/Vehicles/Edit.tsx",
          "padrao": "MercosulPlate"
        }
      ]
    }
  ]
}
```

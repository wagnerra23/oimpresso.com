---
sessao: "00"
titulo: SINCRONIZAR Lote trio + medida — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-09-30
base: wagnerra23/oimpresso.com@main 89f32db43080 (lida 2026-09-30 19:00 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/lote-trio-medida/playbook/
---

# Lote — completar trio e medir (5 módulos, 9 telas)

> Módulos já em React, sem roteiro próprio, pequenos demais pra um cada. Quando um deles ganhar trabalho de tela de verdade, sai daqui pro roteiro dele.

## 1 · LEVANTAR — medido em `89f32db43080`

| módulo | render | Page | trio | contrato |
|---|---|---|---|---|
| PaymentGateway | `Settings/PaymentGateways/Index` · `CnabRetorno` | `Modules/PaymentGateway/Resources/js/Pages/…` | ✅ ✅ | — |
| Vestuario | `Vestuario/Etiquetas/Index` (EtiquetaTagController:45) | `resources/js/Pages/Vestuario/Etiquetas/` | ✅ | — |
| Auditoria | `Auditoria/Index` · `Detail` (AuditoriaController:37/50) | `resources/js/Pages/Auditoria/` | sem casos ×2 | — |
| ConsultaOs | `ConsultaOs/Index` (:52) — portal do cliente | `resources/js/Pages/ConsultaOs/` | sem casos | — |
| NFSe | `Nfse/Index` · `Emitir` · `Show` (NfseController:43/104/221) | `resources/js/Pages/Nfse/` | sem casos ×3 | `fiscal-nfse` existe — não li se cobre `Nfse/Index` |

Nenhuma medida nem alvo dessas 9 telas.

## 2 · Decisões
Nenhuma.

## 3 · Threads

```json
{
  "modulo": "Lote trio + medida",
  "sha": "89f32db43080",
  "gerado": "2026-09-30",
  "absorve": [],
  "variaveis": {
    "PAGES": "resources/js/Pages",
    "PG": "Modules/PaymentGateway/Resources/js/Pages/Settings/PaymentGateways",
    "ALVOS": "governance/design/targets"
  },
  "decisoes": [],
  "threads": [
    {
      "id": "01",
      "titulo": "Casos: Auditoria Index + Detail",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "01-casos.md",
      "prefixo": [
        "${PAGES}/Auditoria/",
        "tests/"
      ],
      "nao_toca": [
        "${PAGES}/Auditoria/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Auditoria/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Auditoria/Detail.casos.md"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Casos: ConsultaOs (portal do cliente)",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "01-casos.md",
      "prefixo": [
        "${PAGES}/ConsultaOs/",
        "tests/"
      ],
      "nao_toca": [
        "${PAGES}/ConsultaOs/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${PAGES}/ConsultaOs/Index.casos.md"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Casos: Nfse Index + Emitir + Show",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "01-casos.md",
      "prefixo": [
        "${PAGES}/Nfse/",
        "tests/"
      ],
      "nao_toca": [
        "${PAGES}/Nfse/Emitir.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Nfse/Index.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Nfse/Emitir.casos.md"
        },
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Nfse/Show.casos.md"
        }
      ]
    },
    {
      "id": "A1",
      "titulo": "ALVO lote: 9 telas (Auditoria ×2, ConsultaOs, Nfse ×3, PaymentGateways ×2, Vestuario/Etiquetas)",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "02-alvos.md",
      "prefixo": [
        "${ALVOS}/auditoria--*",
        "${ALVOS}/consultaos--*",
        "${ALVOS}/nfse--*",
        "${ALVOS}/paymentgateways--*",
        "${ALVOS}/vestuario--*"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/auditoria--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/vestuario--etiquetas--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ],
      "nota": "as outras 7 provas entram no recibo com o nome final de cada slug"
    }
  ]
}
```

## 4 · O que este índice NÃO resolve
- NFSe pode estar coberta pelo roteiro `fiscal` — conferir antes de abrir a 03 pra não duplicar.
- Contratos não viram thread aqui: nascem quando cada tela entrar num roteiro de verdade.

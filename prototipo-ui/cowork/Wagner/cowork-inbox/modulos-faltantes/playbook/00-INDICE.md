---
sessao: "00"
titulo: Módulos faltantes (telas importadas do vivo) — índice
autor: "[CC]"
criado: 2026-10-06
base: wagnerra23/oimpresso.com@main 2fe69ddc0280 (lido 2026-10-06 17:08 UTC)
---
# Módulos faltantes (telas importadas do vivo) — índice

**4 threads.** Decisões [W] (VEST-D1, VEST-D2, PERM) ficam em `decisoes` do json — o placar lê `respondida`; não são thread. Absorve `../PEDIDO-CODE.md` (2026-08-24) e os charters/casos/contratos desta pasta.

**Correção do pedido original:** o destino de contrato `prototipo-ui/contrato/` **não existe** — é `governance/design/contracts/` (ADR 0286 / 0397 D3). E `.md` dentro de `cowork/Wagner/**` é permitido (R3), não precisa exceção do guard.

**Lido no turno @2fe69ddc0280:**
- **Arquivos** — sai daqui: trio em `resources/js/Pages/Arquivos/` + contrato `arquivos-index.contract.json` + playbook próprio em `../../arquivos/playbook/`. `arquivos.*` desta pasta fica como histórico.
- **Comunicação Visual**, **Suporte (Empresas, Visao)**, **Vestuário · Etiquetas** — trio já existe no `main`; falta só contrato (thread 01).
- **Suporte Log** — não existe (thread 02).
- **Voz do Cliente** — só `Modules/VozDoCliente/Resources/views/caixa.blade.php` (thread 03).
- **Catálogo QR** — só blades em `Modules/ProductCatalogue/Resources/views/catalogue/` (thread 04).

```json
{
  "modulo": "ModulosFaltantes",
  "sha": "2fe69ddc0280",
  "gerado": "2026-10-06",
  "decisoes": [
    {
      "id": "VEST-D1",
      "texto": "ligar hard-block de vestuario.etiqueta.*",
      "dono": "W",
      "respondida": false
    },
    {
      "id": "VEST-D2",
      "texto": "prévia antes de imprimir: podar charter ou construir",
      "dono": "W",
      "respondida": false
    },
    {
      "id": "PERM",
      "texto": "permissão que abre Voz do Cliente e Catálogo QR",
      "dono": "W",
      "respondida": false
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "Contratos de tela das 3 telas que já existem (CV, Suporte, Vestuário)",
      "dono": "CL",
      "arquivo": "01-contratos.md",
      "prefixo": [
        "governance/design/contracts/"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/comunicacao-visual.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/suporte.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/vestuario-etiquetas.contract.json"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Suporte — tela Log (nova)",
      "dono": "CL",
      "arquivo": "02-suporte-log.md",
      "prefixo": [
        "resources/js/Pages/Suporte/Log",
        "app/Http/Controllers/"
      ],
      "nao_toca": [
        "resources/js/Pages/Suporte/Empresas",
        "resources/js/Pages/Suporte/Visao"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Suporte/Log.tsx"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Voz do Cliente — Caixa em Inertia (blade → trio)",
      "dono": "CL",
      "arquivo": "03-voz-do-cliente.md",
      "prefixo": [
        "Modules/VozDoCliente/",
        "resources/js/Pages/VozDoCliente/",
        "governance/design/contracts/voz-do-cliente.contract.json"
      ],
      "nao_toca": [
        "Modules/ProductCatalogue/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/VozDoCliente/Caixa.tsx"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/VozDoCliente/Caixa.charter.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/VozDoCliente/Caixa.casos.md"
        }
      ],
      "depende_decisoes": [
        "PERM"
      ]
    },
    {
      "id": "04",
      "titulo": "Catálogo QR — gerar QR em Inertia (blade → trio)",
      "dono": "CL",
      "arquivo": "04-catalogo-qr.md",
      "prefixo": [
        "Modules/ProductCatalogue/",
        "resources/js/Pages/ProductCatalogue/",
        "governance/design/contracts/catalogo-qr.contract.json"
      ],
      "nao_toca": [
        "Modules/ProductCatalogue/Resources/views/catalogue/show.blade.php",
        "Modules/VozDoCliente/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/ProductCatalogue/CatalogueQr.tsx"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/ProductCatalogue/CatalogueQr.charter.md"
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/ProductCatalogue/CatalogueQr.casos.md"
        }
      ],
      "depende_decisoes": [
        "PERM"
      ]
    }
  ]
}
```

---
sessao: "00"
titulo: PageHeader — 7 decisões [W] 2026-09-23
autor: "[CC]"
criado: 2026-09-23
base: wagnerra23/oimpresso.com@main ebe1fc8be7e4 (lido 2026-09-23 17:27 UTC)
---
# PageHeader — índice

**Leia só este arquivo + a thread da sua vez.** Detalhe e fontes de cada decisão: `../PEDIDO-PAGEHEADER-DECISOES-W-2026-09-23.md`.

**Ordem: 01 → 02 → 03 → 04 → 05 → 06.** 04 e 05 são **fundação** (toda tela): sequenciais, nunca em paralelo com PR de tela (incidente #2495). 06 depois do 04. Abrir com `/onda pageheader --thread NN`.

```json
{
  "modulo": "PageHeader",
  "sha": "ebe1fc8be7e4",
  "gerado": "2026-09-23",
  "decisoes": [
    {
      "id": "D-PH-0923",
      "texto": "7 decisões [W] 2026-09-23 (formulário): h1 600 · subtítulo 12px · aba 36px · setas+banner no DS · Caixa 22px · ADR 0395 aprovada · matriz arquivada",
      "respondida": true
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "ADR 0395 — merge + flip do required (ordem da própria ADR)",
      "dono": "W",
      "arquivo": "01-adr-0395.md",
      "prefixo": [
        "memory/decisions/0395-pageheader-ratchet-required-emenda-0314.md",
        ".github/workflows/pageheader-gate.yml"
      ],
      "nao_toca": [
        "resources/js/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "memory/decisions/0395-pageheader-ratchet-required-emenda-0314.md",
          "padrao": "status: aceito"
        }
      ],
      "depende_decisoes": [
        "D-PH-0923"
      ]
    },
    {
      "id": "02",
      "titulo": "Arquivar pageheader-matriz-diferencas.md",
      "dono": "CL",
      "arquivo": "02-arquivar-matriz.md",
      "prefixo": [
        "memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md"
      ],
      "nao_toca": [
        "resources/",
        "prototipo-ui/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "memory/requisitos/_DesignSystem/pageheader-matriz-diferencas.md",
          "padrao": "lifecycle: arquivado"
        }
      ],
      "depende_decisoes": [
        "D-PH-0923"
      ]
    },
    {
      "id": "03",
      "titulo": "DS: TabBar com setas (tablist) + PageHeader com role=banner",
      "dono": "CL",
      "arquivo": "03-ds-a11y.md",
      "prefixo": [
        "prototipo-ui/design-system/components/TabBar/",
        "prototipo-ui/design-system/components/PageHeader/"
      ],
      "nao_toca": [
        "resources/js/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "prototipo-ui/design-system/components/TabBar/TabBar.jsx",
          "padrao": "ArrowRight"
        },
        {
          "tipo": "contem",
          "path": "prototipo-ui/design-system/components/PageHeader/PageHeader.jsx",
          "padrao": "banner"
        }
      ],
      "depende_decisoes": [
        "D-PH-0923"
      ]
    },
    {
      "id": "04",
      "titulo": "h1 do PageHeader: padrão 600 (titleWeight default semibold)",
      "dono": "CL",
      "arquivo": "04-h1-600.md",
      "prefixo": [
        "resources/js/Components/PageHeader/PageHeader.tsx"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "resources/js/Components/PageHeader/PageHeader.tsx",
          "padrao": "'font-semibold' : 'font-bold'"
        }
      ],
      "depende_decisoes": [
        "D-PH-0923"
      ]
    },
    {
      "id": "05",
      "titulo": "Abas do PageHeaderTabs com 36px",
      "dono": "CL",
      "arquivo": "05-aba-36.md",
      "prefixo": [
        "resources/js/Components/shared/PageHeaderTabs.tsx",
        "tests/pageHeaderTabsDensity.spec.tsx",
        "tests/pageHeaderTabsFidelity.spec.tsx"
      ],
      "nao_toca": [
        "resources/js/Pages/"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "resources/js/Components/shared/PageHeaderTabs.tsx",
          "padrao": "base: 'px-3 py-1.5 text-sm'"
        }
      ],
      "depende_decisoes": [
        "D-PH-0923"
      ],
      "depende_threads": [
        "04"
      ]
    },
    {
      "id": "06",
      "titulo": "Título da Caixa Unificada 14px → 22px",
      "dono": "CL",
      "arquivo": "06-caixa-h1.md",
      "prefixo": [
        "Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx"
      ],
      "nao_toca": [
        "Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada/_components/"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "Modules/Whatsapp/Resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx",
          "padrao": "text-[14px]"
        }
      ],
      "depende_decisoes": [
        "D-PH-0923"
      ],
      "depende_threads": [
        "04"
      ]
    }
  ]
}
```

---
sessao: "00"
titulo: Woocommerce — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-10-07
base: wagnerra23/oimpresso.com@main ff43e08461d9
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/woocommerce/playbook/
---

# Woocommerce

> Nasce da decisão [W] 07/10 (formulário do SINCRONIZAR): *"Usamos — migrar para React"*.

## 1 · LEVANTAR — medido em `ff43e08461d9`
`WoocommerceController`: `index` → `woocommerce::woocommerce.index` · API → `woocommerce.api_settings` · log → `woocommerce.sync_log` — só Blade. Protótipo: rota `woocommerce` (`WooCommercePage`, `app.jsx:846`).

## 2 · Decisões
Nenhuma aberta.

## 3 · Threads

```json
{
  "modulo": "Woocommerce",
  "sha": "ff43e08461d9",
  "gerado": "2026-10-07",
  "variaveis": {
    "C": "app/Http/Controllers",
    "ALVOS": "governance/design/targets"
  },
  "decisoes": [],
  "threads": [
    {
      "id": "A1",
      "titulo": "ALVO: woocommerce (painel · API · log de sincronização)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "A1.md",
      "prefixo": [
        "${ALVOS}/woocommerce--*"
      ],
      "nao_toca": [
        "Modules/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/woocommerce--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "01",
      "titulo": "Painel (WoocommerceController@index) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "01.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "Modules/Woocommerce/Http/Controllers/WoocommerceController.php",
        "Modules/Woocommerce/Resources/js/Pages/",
        "Modules/Woocommerce/Tests/"
      ],
      "nao_toca": [
        "resources/js/Components/cockpit/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/Woocommerce/Http/Controllers/WoocommerceController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Configuração da API + log de sincronização (apiSettings · viewSyncLog) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "02.md",
      "depende_threads": [
        "01"
      ],
      "prefixo": [
        "Modules/Woocommerce/Http/Controllers/WoocommerceController.php",
        "Modules/Woocommerce/Resources/js/Pages/",
        "Modules/Woocommerce/Tests/"
      ],
      "nao_toca": [
        "resources/js/Components/cockpit/"
      ],
      "provas": [],
      "nota_provas": "as 3 views Blade (woocommerce.index · api_settings · sync_log) com par Inertia; segredo da API nunca volta em prop (Tier 0)"
    }
  ]
}
```

## 4 · O que este índice NÃO resolve
- A sincronização em si (jobs, webhooks) não muda — só as telas.
- Segredo da API (consumer key/secret) não pode voltar em prop do Inertia: a tela mostra "configurado" e aceita troca, nunca exibe.

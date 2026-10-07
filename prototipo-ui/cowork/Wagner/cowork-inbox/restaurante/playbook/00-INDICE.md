---
sessao: "00"
titulo: Restaurante — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-10-07
base: wagnerra23/oimpresso.com@main ff43e08461d9
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/restaurante/playbook/
---

# Restaurante

> Nasce da decisão [W] 07/10 (formulário do SINCRONIZAR): *"Usamos — migrar para React"*.

## 1 · LEVANTAR — medido em `ff43e08461d9`
Menu: `AdminSidebarMenu.php` declara as 5 telas. Todas devolvem só Blade: `restaurant.table.index` · `restaurant.modifier_sets.index` · `restaurant.booking.index` · `restaurant.kitchen.index` · `restaurant.orders.index` (`app/Http/Controllers/Restaurant/`). Protótipo: só `cfg-mesas` e `cfg-atendentes` existem (`RestauranteExtrasPage`, `app.jsx:847`); **Reservas, Cozinha, Pedidos e Modificadores não têm forma** → P1 é do Cowork, antes de qualquer alvo (Design manda na forma).

## 2 · Decisões
Nenhuma aberta.

## 3 · Threads

```json
{
  "modulo": "Restaurante",
  "sha": "ff43e08461d9",
  "gerado": "2026-10-07",
  "variaveis": {
    "C": "app/Http/Controllers",
    "ALVOS": "governance/design/targets"
  },
  "decisoes": [],
  "threads": [
    {
      "id": "P1",
      "titulo": "Protótipo: Reservas, Cozinha, Pedidos e Modificadores (não existem no build)",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "P1.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/"
      ],
      "nao_toca": [
        "app/",
        "resources/"
      ],
      "provas": [],
      "nota_provas": "rotas novas no app.jsx + RestauranteExtrasPage com as 4 views; o Cowork faz"
    },
    {
      "id": "A1",
      "titulo": "ALVO: mesas · atendentes · modificadores · reservas · cozinha · pedidos",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "A1.md",
      "depende_threads": [
        "P1"
      ],
      "prefixo": [
        "${ALVOS}/restaurante--*"
      ],
      "nao_toca": [
        "app/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/restaurante--mesas--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/restaurante--modificadores--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/restaurante--reservas--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/restaurante--cozinha--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        },
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/restaurante--pedidos--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "01",
      "titulo": "Mesas (TableController@index) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "01.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${C}/Restaurant/TableController.php",
        "resources/js/Pages/Restaurante/Mesas/",
        "tests/Feature/Restaurante/Mesas*"
      ],
      "nao_toca": [
        "resources/js/Components/cockpit/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${C}/Restaurant/TableController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Modificadores (ModifierSetsController@index) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "02.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${C}/Restaurant/ModifierSetsController.php",
        "resources/js/Pages/Restaurante/Modificadores/",
        "tests/Feature/Restaurante/Modificadores*"
      ],
      "nao_toca": [
        "resources/js/Components/cockpit/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${C}/Restaurant/ModifierSetsController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Reservas (BookingController@index) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "03.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${C}/Restaurant/BookingController.php",
        "resources/js/Pages/Restaurante/Reservas/",
        "tests/Feature/Restaurante/Reservas*"
      ],
      "nao_toca": [
        "resources/js/Components/cockpit/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${C}/Restaurant/BookingController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Cozinha (KitchenController@index) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "04.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${C}/Restaurant/KitchenController.php",
        "resources/js/Pages/Restaurante/Cozinha/",
        "tests/Feature/Restaurante/Cozinha*"
      ],
      "nao_toca": [
        "resources/js/Components/cockpit/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${C}/Restaurant/KitchenController.php",
          "padrao": "Inertia::render("
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Pedidos (OrderController@index) → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "05.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${C}/Restaurant/OrderController.php",
        "resources/js/Pages/Restaurante/Pedidos/",
        "tests/Feature/Restaurante/Pedidos*"
      ],
      "nao_toca": [
        "resources/js/Components/cockpit/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${C}/Restaurant/OrderController.php",
          "padrao": "Inertia::render("
        }
      ]
    }
  ],
  "revisado": "undefined · 2026-10-07 revisão: prefixos disjuntos + provas que decidem"
}
```

## 4 · O que este índice NÃO resolve
- Atendentes (`cfg-atendentes`): não achei controller próprio no menu — conferir se é o filtro de "service staff" de Usuários antes de virar thread.
- Cozinha e Pedidos são telas de operação em tempo real; a P1 decide se o React precisa de atualização ao vivo.

---
sessao: "00"
titulo: SINCRONIZAR Officeimpresso — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-09-30
base: wagnerra23/oimpresso.com@main 89f32db43080 (lida 2026-09-30 19:00 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/officeimpresso/playbook/
---

# SINCRONIZAR Officeimpresso — playbook

> **Absorve:** `cowork-inbox/connector/PROPOSTA-licencas-equipamentos.md` (19/08). Decidido por [W] naquele dia: a tela nasce no **Officeimpresso**, com **permissão própria do suporte**, e ninguém vê senha. Restrição herdada do Connector: **o WR Comercial (Delphi) em campo não pode quebrar** — o backend pode ignorar campo que o desktop manda, nunca mudar a resposta `S;…`/`N;…`.

## 1 · LEVANTAR — medido em `89f32db43080`

**Telas em React:** `LicencaLogController:201` → `Officeimpresso/Logs/Index` · `:422` → `Logs/Timeline` (em `Modules/Officeimpresso/Resources/js/Pages/`, trio ✅, sem contrato).
**Ainda Blade:** `LicencaComputadorController` (index, computadores, viewLicencas, businessall, create/edit) · `ClientController` (clients.index + 3 stubs) · `OfficeimpressoController` (catálogo: index, show, generate_qr).
**Guardas:** `LicencaComputadorController` do Officeimpresso já tem 4 portões (`officeimpresso.access`, `.licencas.gerenciar`, `.empresa.gerenciar`, `.licencas.excluir`) — o "qualquer logado acessa" que o docblock cita foi fechado.

**🔴 Segurança ainda aberta (API do desktop, em `Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php`):**
- **L1** `:243` `Licenca_Computador::all()` — devolve equipamento de **todos** os negócios a qualquer token `auth:api`. ⚠️ **Retratação:** no roteiro do Connector eu escrevi que L1 "pode já ter sido consertado" porque procurei no módulo errado (Officeimpresso). Está aberto.
- **L7** `:267` · `:293` · `:310` `Licenca_Computador::find($id)` sem `business_id`.
- **L2** `:195` `->senha =` e `:214` `->contra_senha =` — credencial do cliente gravada em claro.

**GET que muda estado:** `Routes/web.php:44` `toggle-block` · `:49` `businessbloqueado` · `:64-65` `install/uninstall`, `install/update`.
**Painel OAuth duplicado:** `ClientController:57` `makeVisible('secret')` + `:164` `regenerate()` + `web.php:37` — mesma dívida do Connector, em outro módulo.
**Catálogo duplicado:** `OfficeimpresoController:105/141/161` e `ProductCatalogueController:42/61/77` servem as mesmas 3 views (catálogo, item, QR).
**Sobreposição:** `app/Http/Controllers/Support/SupportController` já renderiza `Suporte/Empresas` (:56) e `Suporte/Visao` (:70), trio ✅.
**Medidas:** `Officeimpresso--Logs--Index` e `--Timeline` usam o **mesmo `design.json`** (blob `0369a411b2dd`) — a de Index acusa 9 bugs, mas contra que vista não se sabe.

## 2 · Decisões
| id | pergunta | destrava |
|---|---|---|
| D1 | Painel OAuth do Officeimpresso: aposentar (usar o do Connector) ou manter | 05 |
| D2 | Licenças: tela nova no Officeimpresso ou fundir com `Suporte/Empresas`/`Visao` | A1 · 06 |
| D3 | Catálogo: Officeimpresso ou ProductCatalogue | 08 |
| D4 | Dropar `senha`/`contra_senha` da tabela | 03 |
| D5 | Cobrança por equipamento: aqui ou Financeiro/Superadmin | — |

## 3 · Threads

```json
{
  "modulo": "Officeimpresso",
  "sha": "89f32db43080",
  "gerado": "2026-09-30",
  "absorve": [
    "prototipo-ui/cowork/Wagner/cowork-inbox/connector/PROPOSTA-licencas-equipamentos.md"
  ],
  "variaveis": {
    "MOD": "Modules/Officeimpresso",
    "CAPI": "Modules/Connector/Http/Controllers/Api",
    "MPAGES": "Modules/Officeimpresso/Resources/js/Pages",
    "ALVOS": "governance/design/targets",
    "CONTRATOS": "governance/design/contracts"
  },
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "Officeimpresso/client (clone do painel de API do Connector, com makeVisible('secret') e /regenerate): aposentar e usar o do Connector, ou manter?",
      "respondida": false,
      "destrava": [
        "05"
      ]
    },
    {
      "id": "D2",
      "pergunta": "Tela de licenças: nasce em Officeimpresso ou funde com Suporte/Empresas + Suporte/Visao (já em React, SupportController)?",
      "respondida": false,
      "destrava": [
        "06"
      ]
    },
    {
      "id": "D3",
      "pergunta": "Catálogo duplicado (Officeimpresso catalogue × ProductCatalogue — as mesmas 3 views): qual fica?",
      "respondida": false,
      "destrava": [
        "08"
      ]
    },
    {
      "id": "D4",
      "pergunta": "Dropar as colunas senha/contra_senha (depois de parar de gravar)?",
      "respondida": false,
      "destrava": [
        "03"
      ]
    },
    {
      "id": "D5",
      "pergunta": "Cobrança por equipamento (dt_validade, valor, gera_mensalidade): nesta tela ou no Financeiro/Superadmin?",
      "respondida": false,
      "destrava": []
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "L1+L7 · API de licença sem escopo de negócio (::all() e find($id))",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "01-seguranca-api.md",
      "prefixo": [
        "${CAPI}/LicencaComputadorController.php",
        "Modules/Connector/Tests/Feature/"
      ],
      "nao_toca": [
        "${CAPI}/OImpressoRegistroController.php",
        "Modules/Connector/Routes/api.php"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${CAPI}/LicencaComputadorController.php",
          "padrao": "Licenca_Computador::all()"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "L2 · parar de gravar senha/contra_senha vindas do desktop",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "02-segredo-desktop.md",
      "prefixo": [
        "${CAPI}/LicencaComputadorController.php",
        "Modules/Connector/Tests/Feature/"
      ],
      "nao_toca": [
        "Modules/Connector/Routes/api.php"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${CAPI}/LicencaComputadorController.php",
          "padrao": "->contra_senha ="
        },
        {
          "tipo": "nao_contem",
          "path": "${CAPI}/LicencaComputadorController.php",
          "padrao": "->senha ="
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Dropar colunas senha/contra_senha",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "02-segredo-desktop.md",
      "depende_threads": [
        "02"
      ],
      "depende_decisoes": [
        "D4"
      ],
      "prefixo": [
        "${MOD}/Database/Migrations/",
        "${MOD}/Entities/Licenca_Computador.php"
      ],
      "nao_toca": [
        "${CAPI}/"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Entities/Licenca_Computador.php",
          "padrao": "'contra_senha'"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Ações de estado fora de GET (toggle-block, businessbloqueado, install/*)",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "03-get.md",
      "prefixo": [
        "${MOD}/Routes/web.php",
        "${MOD}/Resources/views/"
      ],
      "nao_toca": [
        "${MOD}/Routes/api.php"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Routes/web.php",
          "padrao": "Route::get('/licenca_computador/{id}/toggle-block'"
        },
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Routes/web.php",
          "padrao": "Route::get('/licenca_computador/businessbloqueado/{id}'"
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Painel de clientes OAuth duplicado (ClientController)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "04-client-duplicado.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/ClientController.php",
        "${MOD}/Routes/web.php",
        "${MOD}/Resources/views/clients/"
      ],
      "nao_toca": [
        "Modules/Connector/"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Http/Controllers/ClientController.php",
          "padrao": "makeVisible('secret')"
        }
      ]
    },
    {
      "id": "A1",
      "titulo": "ALVO officeimpresso--licencas--index + remedir Logs Index/Timeline",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "05-alvos.md",
      "depende_decisoes": [
        "D2"
      ],
      "prefixo": [
        "${ALVOS}/officeimpresso--licencas--index.*",
        "${ALVOS}/medidas/Officeimpresso--*"
      ],
      "nao_toca": [
        "${MPAGES}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/officeimpresso--licencas--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "06",
      "titulo": "Tela de licenças (computadores + businessall) → Inertia, PT-01 + drawer",
      "dono": "CL",
      "vaga": 3,
      "prs": 2,
      "arquivo": "06-licencas.md",
      "depende_threads": [
        "01",
        "02",
        "04",
        "A1"
      ],
      "depende_decisoes": [
        "D2"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/LicencaComputadorController.php",
        "${MPAGES}/Officeimpresso/Licencas/",
        "${CONTRATOS}/officeimpresso-licencas.contract.json"
      ],
      "nao_toca": [
        "${CAPI}/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Http/Controllers/LicencaComputadorController.php",
          "padrao": "Inertia::render("
        },
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/officeimpresso-licencas.contract.json"
        }
      ]
    },
    {
      "id": "07",
      "titulo": "Logs: fechar o design-diff (depois de remedir)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "07-logs.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${MPAGES}/Officeimpresso/Logs/"
      ],
      "nao_toca": [
        "${MOD}/Http/"
      ],
      "provas": [],
      "nota_provas": "design-diff --compare --check sem DIVERGE (bug), run no recibo"
    },
    {
      "id": "08",
      "titulo": "Catálogo duplicado — aposentar um dos dois",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "08-catalogo.md",
      "depende_decisoes": [
        "D3"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/OfficeimpressoController.php",
        "Modules/ProductCatalogue/"
      ],
      "nao_toca": [
        "${MOD}/Http/Controllers/LicencaComputadorController.php"
      ],
      "provas": [],
      "nota_provas": "prova depende de D3 — fixar no recibo qual controller perdeu as 3 views"
    }
  ]
}
```

## 4 · O que este índice NÃO resolve
- L3 (equipamento nasce bloqueado sem tela pra liberar) só fecha com a 06.
- L4 (mesmo HD em N negócios) entra como aviso na 06; a regra do backend não muda.
- `Api/OImpressoRegistroController:206` também consulta por `hd` — não li o escopo.

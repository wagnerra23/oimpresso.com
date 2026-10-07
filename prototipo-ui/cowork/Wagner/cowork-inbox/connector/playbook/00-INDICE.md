---
sessao: "00"
titulo: SINCRONIZAR Connector — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-09-30
base: wagnerra23/oimpresso.com@main 2fc50fa6dcb8 (lida 2026-09-30 18:52 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/connector/playbook/
---

# SINCRONIZAR Connector — playbook

> **Absorve:** `PEDIDO-CL-connector-trio.md` (rev. 2, 19/08, main @728f789b8fb1). As 7 decisões [W] de 19/08 seguem **fechadas** (superadmin só · excluir revoga em cadeia · sem rotação · regenerar sai · ninguém vê segredo · licenças é do Officeimpresso · `/docs` vira aba). Restrição dura: **o WR Comercial (Delphi) instalado nos clientes não pode quebrar** — nenhum hash de segredo, rotação, validade ou mudança no contrato de resposta.
> Trio proposto está **nesta pasta** (`../Index.charter.md`, `../Index.casos.md`, `../connector-api.contract.json`, `../ApiClientsPanelTest.php`). O pedido mandava o contrato pra `prototipo-ui/contrato/` — caminho morto; vai pra `governance/design/contracts/`, e **só junto com a Page** (a 04): `Contratos de tela` é required desde hoje e um contrato sem âncora na tela nasce vermelho.

## 1 · LEVANTAR — medido em `2fc50fa6dcb8`

**Nada das 9 ondas entrou.** `ClientController:41` ainda faz `makeVisible('secret')` · `:153` `regenerate()` + `Routes/web.php:19` `Route::get('/regenerate')` · `DataController:33` ainda declara `connector.access`. Zero `Inertia::render` no módulo: `ClientController:43` → `connector::clients.index`; `create/show/edit` de `ClientController`, `ConnectorController` e `Api/BusinessController` devolvem views `connector::{index,create,show,edit}` (as que o pedido diz não existirem — não reconferi). Existe `Tests/Feature/ClientControllerBaselineTest.php` que **documenta** o vazamento (:162), não o proíbe.
**Licenças:** ❌ **retratação** — eu disse que L1 "pode já ter sido consertado" porque procurei em `Modules/Officeimpresso`. O arquivo é `Modules/Connector/Http/Controllers/Api/LicencaComputadorController.php` e **L1 está aberto** (`:243` `Licenca_Computador::all()`), assim como L2 (`:195`/`:214`) e L7 (`find($id)` sem escopo). As threads moram no roteiro `officeimpresso` (01–03), dono do modelo; aqui o arquivo entra em `nao_toca`.

## 2 · Decisões
Todas fechadas em 19/08. Resta só o portão [W2] (screenshot em produção) antes da 06.

## 3 · Threads

```json
{
  "modulo": "Connector",
  "sha": "2fc50fa6dcb8",
  "gerado": "2026-09-30",
  "absorve": [
    "prototipo-ui/cowork/Wagner/cowork-inbox/connector/PEDIDO-CL-connector-trio.md"
  ],
  "variaveis": {
    "MOD": "Modules/Connector",
    "MPAGES": "Modules/Connector/Resources/js/Pages",
    "CONTRATOS": "governance/design/contracts",
    "INBOX": "prototipo-ui/cowork/Wagner/cowork-inbox/connector"
  },
  "decisoes": [],
  "threads": [
    {
      "id": "01",
      "titulo": "CONN-O1 · prova mínima: teste + charter + casos (nasce vermelho)",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "01-prova.md",
      "prefixo": [
        "${MOD}/Tests/Feature/ApiClientsPanelTest.php",
        "${MPAGES}/Api/Index.charter.md",
        "${MPAGES}/Api/Index.casos.md"
      ],
      "nao_toca": [
        "${MOD}/Http/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${MOD}/Tests/Feature/ApiClientsPanelTest.php"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "CONN-O2 · excluir revoga tokens em transação; install/uninstall/update fora de GET",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "02-seguranca.md",
      "depende_threads": [
        "01"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/ClientController.php",
        "${MOD}/Http/Controllers/InstallController.php",
        "${MOD}/Routes/web.php"
      ],
      "nao_toca": [
        "${MOD}/Routes/api.php",
        "${MOD}/Http/Controllers/Api/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Http/Controllers/ClientController.php",
          "padrao": "oauth_access_tokens"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "CONN-O2b · ninguém lê o segredo (sem tocar no valor guardado)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "03-segredo.md",
      "depende_threads": [
        "01"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/ClientController.php"
      ],
      "nao_toca": [
        "${MOD}/Routes/api.php",
        "config/auth.php"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Http/Controllers/ClientController.php",
          "padrao": "makeVisible('secret')"
        },
        {
          "tipo": "nao_contem",
          "path": "app/Providers/AuthServiceProvider.php",
          "padrao": "hashClientSecrets",
          "guarda": true,
          "nota": "D6: hash invalidaria o WR Comercial em campo"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "CONN-O3 · Blade → Inertia (Api/Index) + contrato connector-api",
      "dono": "CL",
      "vaga": 3,
      "prs": 2,
      "arquivo": "04-traducao.md",
      "depende_threads": [
        "02",
        "03"
      ],
      "prefixo": [
        "${MPAGES}/Api/",
        "${MOD}/Http/Controllers/ClientController.php",
        "${CONTRATOS}/connector-api.contract.json"
      ],
      "nao_toca": [
        "${MOD}/Http/Controllers/Api/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Http/Controllers/ClientController.php",
          "padrao": "Inertia::render("
        },
        {
          "tipo": "arquivo",
          "path": "${MPAGES}/Api/Index.tsx"
        },
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/connector-api.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "${MPAGES}/Api/Index.charter.md"
        }
      ]
    },
    {
      "id": "05",
      "titulo": "CONN-O4 · menu, /regenerate e connector.access fora",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "05-menu.md",
      "depende_threads": [
        "04"
      ],
      "prefixo": [
        "${MOD}/Routes/web.php",
        "${MOD}/Http/Controllers/DataController.php",
        "${MOD}/Http/Controllers/ClientController.php"
      ],
      "nao_toca": [
        "${MOD}/Routes/api.php"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Routes/web.php",
          "padrao": "/regenerate"
        },
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Http/Controllers/DataController.php",
          "padrao": "connector.access"
        }
      ]
    },
    {
      "id": "06",
      "titulo": "CONN-O5 · apagar o legado Blade (depois do screenshot [W2])",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "06-legado.md",
      "depende_threads": [
        "05"
      ],
      "prefixo": [
        "${MOD}/Resources/views/",
        "${MOD}/Http/Controllers/ConnectorController.php",
        "${MOD}/Resources/lang/",
        "${MOD}/Resources/assets/"
      ],
      "nao_toca": [
        "${MOD}/Http/Controllers/Api/"
      ],
      "provas": [
        {
          "tipo": "ausente",
          "path": "${MOD}/Resources/views/clients/index.blade.php"
        },
        {
          "tipo": "ausente",
          "path": "${MOD}/Http/Controllers/ConnectorController.php"
        }
      ]
    },
    {
      "id": "07",
      "titulo": "CONN-O7 · quem está usando cada credencial",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "07-quem-usa.md",
      "depende_threads": [
        "04"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/ClientController.php",
        "${MPAGES}/Api/"
      ],
      "nao_toca": [
        "${MOD}/Routes/api.php"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Http/Controllers/ClientController.php",
          "padrao": "tokens_resto"
        },
        {
          "tipo": "arquivo",
          "path": "${MPAGES}/Api/_components/QuemUsa.tsx"
        }
      ],
      "nota_provas": "UC-CONN-21 verde, citado no recibo"
    },
    {
      "id": "08",
      "titulo": "CONN-O8 · saúde com histórico de 14 dias",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "08-saude.md",
      "depende_threads": [
        "04"
      ],
      "prefixo": [
        "${MOD}/Console/Commands/ConnectorHealthCommand.php",
        "${MPAGES}/Api/"
      ],
      "nao_toca": [
        "${MOD}/Services/DelphiSyncService.php"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Console/Commands/ConnectorHealthCommand.php",
          "padrao": "health-history.json"
        }
      ],
      "nota_provas": "caminho do json de histórico fixado no recibo"
    },
    {
      "id": "09",
      "titulo": "Aba Documentação (PR-b da 04)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "09-documentacao.md",
      "depende_threads": [
        "04"
      ],
      "prefixo": [
        "${MPAGES}/Api/",
        "Modules/Connector/Http/Controllers/ClientController.php"
      ],
      "nao_toca": [
        "Modules/Connector/Routes/api.php"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Http/Controllers/DataController.php",
          "padrao": "/connector/client?aba=docs"
        }
      ]
    },
    {
      "id": "10",
      "titulo": "Rodar UC-CONN-12 na lane MySQL (pulado em SQLite)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "10-mysql.md",
      "depende_threads": [
        "02"
      ],
      "prefixo": [
        "Modules/Connector/Tests/Feature/ApiClientsPanelTest.php"
      ],
      "nao_toca": [
        "Modules/Connector/Http/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": ".github/workflows/connector-pest.yml",
          "padrao": "Modules/Connector/Tests/Feature/ApiClientsPanelTest.php"
        }
      ]
    }
  ],
  "revisado": "2026-10-01 — recibos e _DECISOES do main c12552f40e2a aplicados · 2026-10-01 reconferência @99e6fa3e08f0: threads novas 09,10 · 2026-10-07 errata do Code aplicada (_ERRATA-*-2026-10-07.md) @8d231ac7a13f"
}
```

## 4 · O que este índice NÃO resolve
- CONN-O9 (DS vivo) não virou thread: entra na 04, que já nasce com componentes do DS.
- Licenças/equipamentos (`PROPOSTA-licencas-equipamentos.md`) é do Officeimpresso — vai no levantamento dele, não aqui.
- Pedidos ao DS (campo de segredo, `StatusBadge kind="integracao"`, `ref` no `Input`) seguem pro roteiro `ds-atomos`.

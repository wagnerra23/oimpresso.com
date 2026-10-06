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
- **L1** `:243` `Licenca_Computador::all()` — sem escopo de negócio. ⚠️ **Errata do `_saida-01`:** era **latente** — `index/show/update/destroy/store` não tinham rota; só `processa-dados-cliente` e `salvar-equipamento/{business_id}` apontam pro controller. Conserto valeu (rota futura vazaria), vazamento ativo não havia. ⚠️ **Retratação:** no roteiro do Connector eu escrevi que L1 "pode já ter sido consertado" porque procurei no módulo errado (Officeimpresso). Está aberto.
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
      "respondida": true,
      "destrava": [
        "05"
      ],
      "resposta": "manter painel; delegação a todo funcionário do negócio 1; testar Delphi conectado",
      "fonte": "_DECISOES-W-2026-10-01b.md"
    },
    {
      "id": "D2",
      "pergunta": "Tela de licenças: nasce em Officeimpresso ou funde com Suporte/Empresas + Suporte/Visao (já em React, SupportController)?",
      "respondida": true,
      "destrava": [
        "06"
      ],
      "resposta": "tela nova Officeimpresso/Licencas",
      "fonte": "_DECISOES-W-2026-10-01.md"
    },
    {
      "id": "D3",
      "pergunta": "Catálogo duplicado (Officeimpresso catalogue × ProductCatalogue — as mesmas 3 views): qual fica?",
      "respondida": true,
      "destrava": [
        "08"
      ],
      "resposta": "ajustar pacotes antes do redirect do QR",
      "fonte": "_DECISOES-W-2026-10-01b.md"
    },
    {
      "id": "D4",
      "pergunta": "Dropar as colunas senha/contra_senha (depois de parar de gravar)?",
      "respondida": true,
      "destrava": [
        "03"
      ],
      "resposta": "NÃO dropar senha/contra_senha",
      "fonte": "_DECISOES-W-2026-10-01b.md"
    },
    {
      "id": "D5",
      "pergunta": "Cobrança por equipamento (dt_validade, valor, gera_mensalidade): nesta tela ou no Financeiro/Superadmin?",
      "respondida": true,
      "destrava": [],
      "resposta": "fora desta tela (Financeiro/Superadmin)",
      "fonte": "_DECISOES-W-2026-10-01.md"
    },
    {
      "id": "D6",
      "pergunta": "Ligar a flag useV2OfficeimpressoLicencas em produção (hoje OFF: produção segue na Blade)?",
      "respondida": true,
      "destrava": [
        "09"
      ],
      "resposta": "ligar a flag useV2OfficeimpressoLicencas em produção, seguindo o RUNBOOK §F2 (rota de fuga = Blade)",
      "fonte": "[W] 2026-10-05 no chat"
    },
    {
      "id": "E-SENHA-DESKTOP",
      "pergunta": "errata do Code (errata _saida-02)",
      "respondida": true,
      "resposta": "a resposta ao WR Comercial NÃO muda; o backend só deixa de gravar senha (restrição do pedido: desktop em campo não pode quebrar)",
      "fonte": "[CC] por delegação de [W] 2026-10-05 (\"o resto pode ser medido, escolha\")"
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
      "arquivo": "03-segredo-desktop.md",
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
      ],
      "bloqueio": "vetado: [W] D4 = \"não\" (_DECISOES-W-2026-10-01b) — colunas ficam; gravação já parada no #8365; tirar do fillable trava no gate Tier 0 (_saida-03)"
    },
    {
      "id": "04",
      "titulo": "Ações de estado fora de GET (toggle-block, businessbloqueado, install/*)",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "04-get.md",
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
      "arquivo": "05-client-duplicado.md",
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
      "titulo": "ALVO officeimpresso--licencas--index",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "A1-alvos.md",
      "depende_decisoes": [
        "D2"
      ],
      "prefixo": [
        "${ALVOS}/officeimpresso--licencas--index.*"
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
      "id": "A3",
      "titulo": "Remedir Logs Index e Timeline, um design.json por tela",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "A3-remedir-logs.md",
      "prefixo": [
        "${ALVOS}/medidas/Officeimpresso--*"
      ],
      "nao_toca": [
        "${MPAGES}/"
      ],
      "provas": [
        {
          "tipo": "medicao",
          "nota": "duas medidas byte-idênticas por tela, cada uma com o seu design.json (hoje as duas usam o blob 0369a411b2dd)"
        }
      ],
      "bloqueio": "NÃO MEDI (_saida-A3): prod não medível (shell AdminLTE, CSS vazio) e Timeline sem vista própria no protótipo"
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
        "A3"
      ],
      "prefixo": [
        "${MPAGES}/Officeimpresso/Logs/"
      ],
      "nao_toca": [
        "${MOD}/Http/"
      ],
      "provas": [
        {
          "tipo": "comparacao",
          "nota": "design-diff --compare --check sem DIVERGE (bug), run citado no _saida"
        }
      ],
      "nota_provas": "design-diff --compare --check sem DIVERGE (bug), run no recibo",
      "bloqueio": "depende da A3, que não mediu — reabre quando a A4 der vista à Timeline e houver lado prod medível"
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
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Http/Controllers/OfficeimpressoController.php",
          "padrao": "paraProductCatalogue("
        }
      ],
      "nota_provas": "prova depende de D3 — fixar no recibo qual controller perdeu as 3 views"
    },
    {
      "id": "09",
      "titulo": "Ligar a tela nova de licenças (flag useV2OfficeimpressoLicencas)",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "09-ligar-flag.md",
      "depende_threads": [
        "06"
      ],
      "depende_decisoes": [
        "D6"
      ],
      "prefixo": [
        "${MOD}/Http/Controllers/LicencaComputadorController.php",
        "config/"
      ],
      "nao_toca": [
        "${MPAGES}/"
      ],
      "provas": [
        {
          "tipo": "execucao",
          "nota": "flag ligada no ambiente, RUNBOOK-licencas §F2 seguido, rota de fuga testada"
        }
      ]
    },
    {
      "id": "A4",
      "titulo": "Protótipo: Timeline do log com vista própria (rota oi-log-timeline)",
      "dono": "CC",
      "vaga": 2,
      "arquivo": "A4-timeline-vista.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/officeimpresso-page.jsx",
        "prototipo-ui/cowork/Wagner/app.jsx"
      ],
      "nao_toca": [
        "Modules/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "prototipo-ui/cowork/Wagner/app.jsx",
          "padrao": "\"oi-log-timeline\""
        }
      ]
    }
  ],
  "revisado": "2026-10-01 — recibos e _DECISOES do main c12552f40e2a aplicados · 2026-10-01 reconferência @99e6fa3e08f0: threads novas 09 · 2026-10-05 revisão dos recibos @aacb74f4df18"
}
```

## 4 · O que este índice NÃO resolve
- L3 (equipamento nasce bloqueado sem tela pra liberar) só fecha com a 06.
- L4 (mesmo HD em N negócios) entra como aviso na 06; a regra do backend não muda.
- `Api/OImpressoRegistroController:206` também consulta por `hd` — não li o escopo.

---
sessao: "00"
titulo: SINCRONIZAR Arquivos — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-09-30
base: wagnerra23/oimpresso.com@main 2fc50fa6dcb8 (lida 2026-09-30 18:52 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/arquivos/playbook/
---

# SINCRONIZAR Arquivos — playbook

> **Absorve:** `PEDIDO-PARA-CODE.md` (24/08, 12 PRs em 5 ondas) + `PEDIDO-PARIDADE-D4-2026-08-26.md` + `PROMPT-ZERO-TOQUE.md`. Decisões D1–D4 e D6 do pedido de 24/08 já estão fechadas (D4 = **a UI nunca apaga**, só dry-run). PR-10 (purge pela tela) **morre** com D4.

## 1 · LEVANTAR — medido em `2fc50fa6dcb8`

- **Entregue:** `Routes/web.php:36` `GET /` → `ArquivosAdminController@index` (`ListArquivosRequest`) → `Inertia::render('Arquivos/Index')` (:157). `Pages/Arquivos/Index.tsx` (70 KB) + charter + casos ✅. Contrato `governance/design/contracts/arquivos-index.contract.json` ✅. Menu: `modifyAdminMenu()` deixou de ser no-op (comentário em `ArquivosAdminControllerTest.php:251`). ⇒ PR-0, PR-1 e PR-5 feitos; PR-2/3/4 (trilha, retenção, cofre) — **não há rota separada**; se estão como vista dentro do `Index`, a 01 confirma.
- **Não entregue:** nenhuma rota `POST`/`DELETE` no módulo além de install. `ArquivosService::classify()` (:159) e `restore()` (:211) existem sem rota ⇒ PR-6, PR-7, PR-8 abertos.
- **Achado novo:** `install`, `install/uninstall`, `install/update` em `GET` (web.php:48-50) — mesma classe que o Connector corrige (ação destrutiva em GET).
- **Medida:** `targets/medidas/Arquivos--Index/resultado.json` (2026-09-18): **5 bugs** — col0 e col2 sem mono · col1 sem elemento clicável ("texto morto") · pílula da col2 sem dot · col6 herdando a cor da linha. Mais 3 "DIVERGE (dado?)" a conferir.

## 2 · Decisões
| id | pergunta | destrava |
|---|---|---|
| D5 | Aviso ao titular: ADR (Tier 0, cunhagem [W]) + `titular_avisado_at` | 05 |

## 3 · Threads

```json
{
  "modulo": "Arquivos",
  "sha": "2fc50fa6dcb8",
  "gerado": "2026-09-30",
  "absorve": [
    "prototipo-ui/cowork/Wagner/cowork-inbox/arquivos/PEDIDO-PARA-CODE.md",
    "prototipo-ui/cowork/Wagner/cowork-inbox/arquivos/PEDIDO-PARIDADE-D4-2026-08-26.md",
    "prototipo-ui/cowork/Wagner/cowork-inbox/arquivos/PROMPT-ZERO-TOQUE.md"
  ],
  "variaveis": {
    "MOD": "Modules/Arquivos",
    "PAGES": "resources/js/Pages",
    "CONTRATOS": "governance/design/contracts"
  },
  "decisoes": [
    {
      "id": "D5",
      "pergunta": "Aviso ao titular (LGPD Art. 18 §VI): cunhar a ADR + coluna titular_avisado_at?",
      "respondida": true,
      "destrava": [
        "05"
      ],
      "resposta": "sim — ADR + titular_avisado_at, desenho da ficha 05",
      "fonte": "_DECISOES-W-2026-10-01.md"
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "Fechar os 5 bugs do design-diff em Arquivos/Index",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "01-divergencia.md",
      "prefixo": [
        "${PAGES}/Arquivos/Index.tsx"
      ],
      "nao_toca": [
        "${MOD}/Http/",
        "${CONTRATOS}/arquivos-index.contract.json"
      ],
      "provas": [
        {
          "tipo": "comparacao",
          "nota": "design-diff --compare --check sem DIVERGE (bug), run citado no _saida"
        }
      ],
      "nota_provas": "prova = design-diff --compare --check sem DIVERGE (bug) em Arquivos--Index; run citado no _saida-01.md"
    },
    {
      "id": "02",
      "titulo": "Classificar (PR-6): POST arquivos/{arquivo}/classificar",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "02-classificar.md",
      "prefixo": [
        "${MOD}/Routes/web.php",
        "${MOD}/Http/Controllers/ArquivosAdminController.php",
        "${PAGES}/Arquivos/",
        "${MOD}/Tests/Feature/"
      ],
      "nao_toca": [
        "${MOD}/Services/Curador/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Routes/web.php",
          "padrao": "{arquivo}/classificar"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Excluir + restaurar no grace (PR-7)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "03-excluir-restaurar.md",
      "depende_threads": [
        "02"
      ],
      "prefixo": [
        "${MOD}/Routes/web.php",
        "${MOD}/Http/Controllers/ArquivosAdminController.php",
        "${PAGES}/Arquivos/",
        "${MOD}/Tests/Feature/"
      ],
      "nao_toca": [
        "${MOD}/Console/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Routes/web.php",
          "padrao": "{arquivo}/restaurar"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Simular retenção em dry-run (PR-8, D4 = sem purge pela UI)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "04-retencao-simular.md",
      "prefixo": [
        "${MOD}/Routes/web.php",
        "${MOD}/Http/Controllers/",
        "${MOD}/Jobs/",
        "${PAGES}/Arquivos/",
        "${MOD}/Tests/Feature/"
      ],
      "nao_toca": [
        "${MOD}/Console/Commands/RetentionCleanupCommand.php"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "${MOD}/Routes/web.php",
          "padrao": "retencao/simular"
        }
      ]
    },
    {
      "id": "05",
      "titulo": "Aviso ao titular (PR-9) — só com ADR cunhada por [W]",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "05-aviso-titular.md",
      "depende_decisoes": [
        "D5"
      ],
      "depende_threads": [
        "04"
      ],
      "prefixo": [
        "${MOD}/Database/Migrations/",
        "${MOD}/Services/",
        "${PAGES}/Arquivos/"
      ],
      "nao_toca": [
        "${MOD}/Console/Commands/RetentionCleanupCommand.php"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/Arquivos/Database/Migrations/2026_10_01_000001_add_titular_avisado_at_and_notice_to_arquivos.php"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/Arquivos/Services/AvisoTitularService.php"
        }
      ],
      "nota_provas": "migração titular_avisado_at + ação notice no enum; nome do arquivo de migração não existe ainda"
    },
    {
      "id": "06",
      "titulo": "install/uninstall/update fora de GET",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "06-install-get.md",
      "prefixo": [
        "${MOD}/Routes/web.php",
        "${MOD}/Http/Controllers/InstallController.php"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "nao_contem",
          "path": "${MOD}/Routes/web.php",
          "padrao": "Route::get('install/uninstall'"
        }
      ]
    }
  ],
  "revisado": "2026-10-01 — recibos e _DECISOES do main c12552f40e2a aplicados"
}
```

## 4 · O que este índice NÃO resolve
- Onda 4 do pedido antigo (DS vivo, a11y/perf) não virou thread — a 01 cobre o que a medida acusou; o resto espera nova medida.
- Paridade protótipo ↔ `main`: não comparei `arquivos-page.jsx` local com o espelho neste turno.

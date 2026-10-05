---
sessao: "00"
titulo: SINCRONIZAR Cliente — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-09-30
base: wagnerra23/oimpresso.com@main ca44a3d54cd2 (lida 2026-09-30 18:48 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/cliente/playbook/
---

# SINCRONIZAR Cliente — playbook

> Sem `PEDIDO-*` anterior pra absorver. Cliente é o **canon de lista** (PT-01) — o que sair daqui vira régua pros outros índices.

## 1 · LEVANTAR — medido em `ca44a3d54cd2`

**D4 (`Inertia::render`):** todas em `ContactController` — `Cliente/Index` (:516) · `Create` (:1731) · `Show` (:2142) · `Edit` (:2422) · `Import` (:2812) · `Ledger` (:3156) · `Map` (:3471). As 7 têm trio no `main`. `Index.tsx` = 113 KB.

| rota do protótipo (`app.jsx`) | Page viva | estado | thread |
|---|---|---|---|
| `clientes` (`clientes-page.jsx` + `cliente-drawer760.jsx`) | `Cliente/Index.tsx` + `_drawer/` (10 abas) | 🔵 produção React | 00 |
| `cli-novo` · `cli-editar` (`cliente-form.jsx`) | `Create.tsx` · `Edit.tsx` + `_form/` | 🔵 | 00 |
| `cli-import` (`cliente-import.jsx`) | `Import.tsx` | 🔵 | 00 |
| `cli-extrato` (`cliente-extrato.jsx`) | `Ledger.tsx` | 🔵 | 00 |
| `cli-mapa` (`cliente-mapa.jsx`) | `Map.tsx` | 🔵 | 00 |
| — | `Show.tsx` + `_show/` (13 abas) | 🔵 sem rota no protótipo | 00 |
| `cli-grupos` (`cliente-grupos.jsx`) | Blade: `customer-group` (web.php:1009), HTML montado dentro de `CustomerGroupController` | 🟠 desenvolver | 03 |

**Medida existente (não é alvo):** `governance/design/targets/medidas/Cliente--Index/resultado.json` (2026-09-18, staging): **DIVERGE 1 bug** — título `18px` em prod × `22px` no design (D4, banda ±1px). Linha da tabela `SEM-DADO` (falta `tableRow` em `__DD_ROLES` nos dois lados); as 4 linhas de SHELL `SEM-DADO` (seletor não casou). Há medidas irmãs pra `Create`, `Import`, `Ledger`, `Map` — não li.
**Dual-render:** `/cliente` (canary, web.php:652) e `/contacts` (resource, :639) coexistem via `config('mwart.cliente_index.enabled')` (lido no `meta.urlFinal` da medida).
**Alvos:** nenhum `cliente--*.alvo.json`. **Contratos:** nenhum `*cliente*`/`*contato*`.

## 2 · Decisões

| id | pergunta | destrava |
|---|---|---|
| D1 | Aposentar o dual-render (`/contacts` Blade × `/cliente` React)? Hoje a flag `mwart.cliente_index.enabled` decide | 04 |
| D2 | Grupos de clientes: tela própria (`Cliente/Grupos`) ou aba/filtro dentro de Cliente? (precedente: D-GARANTIAS virou filtro) | 03 |
| D3 | Fornecedores (`/contacts?type=supplier`) usam o mesmo `Cliente/Index` ou ganham índice próprio? (D-FORN já tirou Fornecedores de Compras) | — |

## 3 · Threads

```json
{
  "modulo": "Cliente",
  "sha": "ca44a3d54cd2",
  "gerado": "2026-09-30",
  "absorve": [],
  "variaveis": {
    "PAGES": "resources/js/Pages",
    "CTRL": "app/Http/Controllers",
    "ALVOS": "governance/design/targets",
    "CONTRATOS": "governance/design/contracts"
  },
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "Aposentar o dual-render /contacts × /cliente?",
      "respondida": true,
      "destrava": [
        "04"
      ],
      "resposta": "sim, aposentar; /contacts -> /cliente com ?type=supplier",
      "fonte": "_DECISOES-W-2026-10-01.md"
    },
    {
      "id": "D2",
      "pergunta": "Grupos de clientes: tela própria ou aba/filtro?",
      "respondida": true,
      "destrava": [
        "03"
      ],
      "resposta": "tela própria Cliente/Grupos",
      "fonte": "_DECISOES-W-2026-10-01.md"
    },
    {
      "id": "D3",
      "pergunta": "Fornecedores usam Cliente/Index ou índice próprio?",
      "respondida": true,
      "destrava": [],
      "resposta": "mesmo Cliente/Index com ?type=supplier",
      "fonte": "_DECISOES-W-2026-10-01.md"
    }
  ],
  "threads": [
    {
      "id": "00",
      "titulo": "PUXAR as 7 Pages vivas de Cliente → protótipo",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "00-puxar-vivo.md",
      "prefixo": [
        "prototipo-ui/cowork/Wagner/clientes-page.*",
        "prototipo-ui/cowork/Wagner/cliente-*",
        "prototipo-ui/cowork/Wagner/cli-*"
      ],
      "nao_toca": [
        "${PAGES}/Cliente/"
      ],
      "provas": [],
      "nota_provas": "read-only no main + build aqui: prova = _saida-00.md com o diff nos dois sentidos por tela"
    },
    {
      "id": "A1",
      "titulo": "ALVO cliente--index",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "A1-alvos.md",
      "depende_threads": [
        "00"
      ],
      "prefixo": [
        "${ALVOS}/cliente--index.*"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/cliente--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "A2",
      "titulo": "ALVO cliente--grupos--index",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "A2-alvos.md",
      "depende_decisoes": [
        "D2"
      ],
      "prefixo": [
        "${ALVOS}/cliente--grupos--index.*"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "json_com_chaves",
          "path": "${ALVOS}/cliente--grupos--index.alvo.json",
          "chaves": [
            "secoes"
          ]
        }
      ]
    },
    {
      "id": "01",
      "titulo": "Contrato cliente-index (derivado do protótipo medido)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "01-contratos.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${CONTRATOS}/cliente-index.contract.json"
      ],
      "nao_toca": [
        "${PAGES}/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/cliente-index.contract.json"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Fechar o DIVERGE do Cliente/Index (título 18→22px) + medir linha da tabela",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "02-divergencia-index.md",
      "depende_threads": [
        "A1"
      ],
      "prefixo": [
        "${PAGES}/Cliente/Index.tsx",
        "${PAGES}/Cliente/_components/"
      ],
      "nao_toca": [
        "${PAGES}/Cliente/_drawer/",
        "resources/js/Components/PageHeader/"
      ],
      "provas": [
        {
          "tipo": "comparacao",
          "nota": "design-diff --compare --check sem DIVERGE (bug), run citado no _saida"
        }
      ],
      "nota_provas": "prova = design-diff --compare --check verde na D4 (título) e tableRow medido nos dois lados; citar o run no _saida-02.md"
    },
    {
      "id": "03",
      "titulo": "Cliente/Grupos — CustomerGroupController → Inertia",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "03-grupos.md",
      "depende_threads": [
        "A2"
      ],
      "depende_decisoes": [
        "D2"
      ],
      "prefixo": [
        "${PAGES}/Cliente/Grupos/",
        "${CTRL}/CustomerGroupController.php",
        "${CONTRATOS}/cliente-grupos.contract.json"
      ],
      "nao_toca": [
        "${PAGES}/Cliente/Index.tsx"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "${CONTRATOS}/cliente-grupos.contract.json"
        },
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Cliente/Grupos/Index.charter.md"
        },
        {
          "tipo": "arquivo",
          "path": "${PAGES}/Cliente/Grupos/Index.casos.md"
        },
        {
          "tipo": "contem",
          "path": "${CTRL}/CustomerGroupController.php",
          "padrao": "Inertia::render('Cliente/Grupos/Index'"
        }
      ]
    },
    {
      "id": "04",
      "titulo": "Aposentar o dual-render /contacts → /cliente",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "04-dual-render.md",
      "depende_decisoes": [
        "D1"
      ],
      "depende_threads": [
        "01"
      ],
      "prefixo": [
        "routes/web.php",
        "config/mwart.php",
        "${CTRL}/ContactController.php"
      ],
      "nao_toca": [
        "${PAGES}/Cliente/"
      ],
      "provas": [
        {
          "tipo": "execucao",
          "nota": "/contacts redireciona pra /cliente preservando ?type=supplier; teste de redirect no recibo"
        }
      ],
      "nota_provas": "o nome do arquivo de config não foi lido no turno — fixar no _saida-04.md"
    }
  ],
  "revisado": "2026-10-01 — recibos e _DECISOES do main c12552f40e2a aplicados"
}
```

## 4 · O que este índice NÃO resolve
- `Show.tsx` (13 abas) não tem rota no protótipo. A 00 decide se entra no build ou se o drawer de 760px já é o detalhe (PT-02 proíbe página cheia pra detalhe — conferir se `Show` é legado).
- As medidas irmãs (`Create`, `Import`, `Ledger`, `Map`) não foram lidas; se tiverem DIVERGE, entram numa thread nova depois da 00.
- `Index.tsx` tem 113 KB: qualquer PR nele passa fácil de 300 linhas. A 02 é só o título e a medição.

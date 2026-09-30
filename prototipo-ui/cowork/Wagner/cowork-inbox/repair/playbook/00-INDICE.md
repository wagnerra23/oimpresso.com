---
sessao: "00"
titulo: SINCRONIZAR Repair (Assistência técnica) — índice do playbook (fonte da máquina no 1º bloco json)
autor: "[CC]"
criado: 2026-09-30
base: wagnerra23/oimpresso.com@main ca44a3d54cd2 (lida 2026-09-30 18:49 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/repair/playbook/
---

# SINCRONIZAR Repair — playbook

> Sem `PEDIDO-*` anterior. Rota do protótipo: `repair` + prefixo `rep-` (`app.jsx:843`, `window.RepairPage`, fonte `repair-page.jsx`).

## 1 · LEVANTAR — medido em `ca44a3d54cd2`

**D4 (`Inertia::render`, 12 Pages):** `RepairController` → `Repair/Index` (:434) · `Repair/Show` (:867) · `DashboardController:101` → `Dashboard/Index` · `DeviceModelController` → `DeviceModels/{Index,Create,Edit}` (:124/:238/:319) · `JobSheetController` → `JobSheet/{Index,Create,Show,Edit,AddParts}` (:310/:379/:555/:711/:1077) · `ProducaoOficinaController:98` → `ProducaoOficina/Index` · `RepairSettingsController:109` → `Settings/Index` · `RepairStatusController:104` → `Status/Index`. Cada `render` tem `view(...)` de fallback logo abaixo (dual-render por flag).

**Ainda só Blade (sem `render`):** `RepairController:728` `repair::repair.create` · `:1125` `repair::repair.edit` · `CustomerRepairStatusController:23` `repair::customer_repair.index` (portal do cliente) · parciais: `edit_status` (JobSheet:939), `print_pdf` (:1217), `upload_doc` (:1306), `edit_repair_status_modal` (Repair:1159), `preview_label` (:1277).

**Trio incompleto:** `JobSheet/Index` tem `.tsx` + charter, **sem `.casos.md`**. As outras 11 têm trio.

**Medidas existentes (`targets/medidas/Repair--*`, 2026-09-18, staging) — não confiáveis como estão:** as 6 (`Index`, `JobSheet/Index`, `Dashboard`, `DeviceModels`, `ProducaoOficina`, `Status`) usam **o mesmo `design.json` (blob `86af1436070d`)** — uma única vista do protótipo medida contra seis telas. Por isso `Repair/Index` sai com `kpi.count` prod 3 × design 0, e `ProducaoOficina` sai "IGUAL" sem provar nada. Leitura aproveitável: **título 24px em prod × 22px no canon** em `Repair/Index` e `JobSheet/Index` (independe do design).
**Alvos:** nenhum `repair--*.alvo.json`. **Contratos:** nenhum `*repair*`.

## 2 · Decisões

| id | pergunta | destrava |
|---|---|---|
| D1 | `repair.create` / `repair.edit` (venda com reparo, Blade) migram pra Inertia ou redirecionam pra `JobSheet/Create`/`Edit`? | 03 |
| D2 | Portal do cliente (`CustomerRepairStatusController`, sem login) entra no escopo agora? É cliente-facing: PT-BR e sem CTA WhatsApp loud | 04 |

## 3 · Threads

```json
{
  "modulo": "Repair",
  "sha": "ca44a3d54cd2",
  "gerado": "2026-09-30",
  "absorve": [],
  "variaveis": {
    "PAGES": "resources/js/Pages",
    "MOD": "Modules/Repair",
    "ALVOS": "governance/design/targets",
    "CONTRATOS": "governance/design/contracts"
  },
  "decisoes": [
    {"id": "D1", "pergunta": "repair.create/edit: migrar ou redirecionar pra JobSheet?", "respondida": false, "destrava": ["03"]},
    {"id": "D2", "pergunta": "Portal do cliente entra agora?", "respondida": false, "destrava": ["04"]}
  ],
  "threads": [
    {
      "id": "00",
      "titulo": "PUXAR as 12 Pages vivas de Repair → protótipo (uma vista rep-* por Page)",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "01-puxar-vivo.md",
      "prefixo": ["prototipo-ui/cowork/Wagner/repair-page.jsx"],
      "nao_toca": ["${PAGES}/Repair/"],
      "provas": [],
      "nota_provas": "read-only no main + build aqui: prova = _saida-00.md com o diff por tela e a lista rota rep-* ↔ Page"
    },
    {
      "id": "A1",
      "titulo": "ALVO lote: repair--index · --jobsheet--index · --dashboard--index · --producao-oficina--index",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "02-alvos.md",
      "depende_threads": ["00"],
      "prefixo": ["${ALVOS}/repair--index.*", "${ALVOS}/repair--jobsheet--index.*", "${ALVOS}/repair--dashboard--index.*", "${ALVOS}/repair--producao-oficina--index.*", "${ALVOS}/medidas/Repair--*"],
      "nao_toca": ["${PAGES}/"],
      "provas": [
        {"tipo": "json_com_chaves", "path": "${ALVOS}/repair--index.alvo.json", "chaves": ["secoes"]},
        {"tipo": "json_com_chaves", "path": "${ALVOS}/repair--jobsheet--index.alvo.json", "chaves": ["secoes"]},
        {"tipo": "json_com_chaves", "path": "${ALVOS}/repair--dashboard--index.alvo.json", "chaves": ["secoes"]},
        {"tipo": "json_com_chaves", "path": "${ALVOS}/repair--producao-oficina--index.alvo.json", "chaves": ["secoes"]}
      ]
    },
    {
      "id": "01",
      "titulo": "JobSheet/Index.casos.md — completar o trio",
      "dono": "CL",
      "vaga": 1,
      "prs": 1,
      "arquivo": "03-trio-jobsheet.md",
      "prefixo": ["${PAGES}/Repair/JobSheet/Index.casos.md", "tests/"],
      "nao_toca": ["${PAGES}/Repair/JobSheet/Index.tsx"],
      "provas": [{"tipo": "arquivo", "path": "${PAGES}/Repair/JobSheet/Index.casos.md"}]
    },
    {
      "id": "02",
      "titulo": "Título 24→22px em Repair/Index e JobSheet/Index",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "04-titulo.md",
      "depende_threads": ["A1"],
      "prefixo": ["${PAGES}/Repair/Index.tsx", "${PAGES}/Repair/JobSheet/Index.tsx"],
      "nao_toca": ["resources/js/Components/PageHeader/"],
      "provas": [],
      "nota_provas": "prova = design-diff --compare --check verde na D4 das duas telas, com design.json próprio de cada uma (não o blob 86af1436070d); run citado no _saida-02.md"
    },
    {
      "id": "05",
      "titulo": "Contratos repair-index + repair-jobsheet-index",
      "dono": "CL",
      "vaga": 3,
      "prs": 1,
      "arquivo": "05-contratos.md",
      "depende_threads": ["A1"],
      "prefixo": ["${CONTRATOS}/repair-index.contract.json", "${CONTRATOS}/repair-jobsheet-index.contract.json"],
      "nao_toca": ["${PAGES}/"],
      "provas": [
        {"tipo": "arquivo", "path": "${CONTRATOS}/repair-index.contract.json"},
        {"tipo": "arquivo", "path": "${CONTRATOS}/repair-jobsheet-index.contract.json"}
      ]
    },
    {
      "id": "03",
      "titulo": "repair.create / repair.edit — sair do Blade",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "06-repair-form.md",
      "depende_decisoes": ["D1"],
      "prefixo": ["${MOD}/Http/Controllers/RepairController.php", "${PAGES}/Repair/"],
      "nao_toca": ["${PAGES}/Repair/JobSheet/"],
      "provas": [{"tipo": "nao_contem", "path": "${MOD}/Http/Controllers/RepairController.php", "padrao": "view('repair::repair.create')"}]
    },
    {
      "id": "04",
      "titulo": "Portal do cliente — status do reparo",
      "dono": "CL",
      "vaga": 4,
      "prs": 1,
      "arquivo": "07-portal.md",
      "depende_decisoes": ["D2"],
      "prefixo": ["${MOD}/Http/Controllers/CustomerRepairStatusController.php", "${PAGES}/Repair/Portal/"],
      "nao_toca": ["${PAGES}/Repair/Index.tsx"],
      "provas": [{"tipo": "contem", "path": "${MOD}/Http/Controllers/CustomerRepairStatusController.php", "padrao": "Inertia::render("}]
    }
  ]
}
```

## 4 · O que este índice NÃO resolve
- Parciais Blade (`edit_status`, `print_pdf`, `upload_doc`, `edit_repair_status_modal`, `preview_label`) ficam. `print_pdf` e `preview_label` são saída de impressão — candidatos a `PresenterMode`/print-craft do DS, mas só com decisão.
- O fallback `view(...)` atrás de cada `render` (dual-render) não é aposentado aqui.
- `D8 kpi.tag = BUTTON` em prod é o KPI-filtro clicável (canon) — não é bug; a medida marcou porque o design não tinha KPI.
- Sobreposição com Oficina Auto (`Pages/OficinaAuto/`) não foi medida.

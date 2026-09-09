---
patch: "00-INDICE.md"
modulo: Patrimonio
autor: "[CC]"
criado: 2026-09-09
base_lida: f8e6e02876fc
---
# PATCH do índice do Patrimônio — 2026-09-09 (delta, não substituição)

> ⚠️ **A pasta local é cache e está atrás.** Local: **7** arquivos. `main`: **24** (`prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/`), com `07`–`13` e 10 `_saida-*`. **Não mande a pasta local descer** — sobrescreveria o índice de 22.024 B, as duas erratas do [CL] de 08/09 e as saídas. O que desce é **este patch** + os 7 `NN-*.md` novos.

## Onde aplicar
1. **§2 · tabela de threads** — acrescentar 7 linhas (abaixo).
2. **§7.decisoes** — acrescentar **1** objeto: `D-FORMS`.
3. **§7.threads** — acrescentar **7** objetos: `14`, `15`, `16`, `17`, `18`, `19`, `20`.
4. **Nada** é removido. As erratas de 08/09 (D1 vive · provas de Bens flat · caminho do `placar-indice.mjs`) ficam como estão.

## Linhas para a §2
| # | thread | leitura | escrita | prefixo | dec. | veredito |
|---|---|---:|---:|---:|---:|---|
| **14** | Provas de 09/10/11 apontam subpasta; o `main` é flat | ~22 KB | ~12 ln (JSON) | 1 | 0 | **CABE** · 1ª |
| **15** | As 8 chamadas de view que não têm arquivo | ~12 KB | 0 | 0 | 0 | **CABE** (medição) |
| **16** | Revogações entram na aba de Alocações | ~11 KB | ~120 ln | 3 | 0 | **CABE** · atrás da 15 |
| 17 | Bens — formulário (create/edit/show) | ~21 KB | ~260 ln | 2 | **1** | **BLOQUEADA** por D-FORMS |
| 18 | Alocações — formulário | ~15 KB | ~220 ln | 2 | **1** | **BLOQUEADA** por D-FORMS |
| 19 | Manutenções — formulário | ~19 KB | ~220 ln | 2 | **1** | **BLOQUEADA** por D-FORMS |
| 20 | Configurações — formulário | ~18 KB | ~160 ln | 2 | **1** | **BLOQUEADA** por D-FORMS |

**Ordem:** 14 → 15 → 16. As 17–20 não abrem sem `D-FORMS` — e hoje os charters das 5 telas declaram `create`/`edit`/`show` como **Non-Goal com motivo**, então emitir PR ali antes da resposta seria pedido **contra o próprio charter**.

## O denominador desta medição (main `f8e6e02876fc`)
- **5** `Inertia::render`: `Patrimonio/Index` (`AssetController:637`) · `Bens` (`:269`) · `Alocacoes` (`AssetAllocationController:150`) · `Manutencoes` (`AssetMaitenanceController:262`) · `Configuracoes` (`AssetSettingsController:92`).
- **Pages flat, trio completo nos 5:** `Index.tsx` 16.023 · `Bens.tsx` 21.047 · `Alocacoes.tsx` 14.994 · `Manutencoes.tsx` 19.025 · `Configuracoes.tsx` 17.605 · `_shared/PatrimonioSubNav.tsx` 4.373 — cada um com `.charter.md` + `.casos.md`.
- **Blade vivo:** 17 arquivos em `Resources/views` (`asset/` 4 · `asset_allocation/` 3 · `asset_maintenance/` 3 · `asset_revocation/` 2 · `settings/` 3 · `layouts/nav` · `index.blade.php` 203 B).
- **Rota:** 6 `Route::resource` + `GET asset/dashboard`, prefixo `asset`, `throttle:60,1`. **Nenhuma rota de garantias.**
- **Fonte visual:** `patrimonio-page.jsx` 58.265 · `patrimonio-forms.jsx` 18.810 · `patrimonio-data.jsx` 13.789.
- **Não medido neste turno:** paridade **por seção** das 5 telas (exige T1 estável no protótipo servido + `getComputedStyle`), o conteúdo dos 5 `.tsx` e dos 10 `_saida-*`. Declarado, não afirmado.

## Objeto novo para §7.decisoes
```json
{
  "id": "D-FORMS",
  "pergunta": "As sub-telas de escrita (create/edit/show) dos 4 CRUDs do Patrimonio migram para React (drawer PT-02, fonte patrimonio-forms.jsx) ou seguem Blade como Non-Goal permanente? Hoje os 5 charters declaram Non-Goal COM motivo — sem resposta [W], as threads 17-20 nao abrem.",
  "respondida": false,
  "destrava": ["17", "18", "19", "20"],
  "custo": "4 PRs de ~200-260 ln + 8 views Blade retiradas"
}
```

## Objetos novos para §7.threads
```json
[
  {
    "id": "14",
    "titulo": "Provas de 09/10/11 apontam subpasta; o main e flat",
    "dono": "CL",
    "vaga": 1,
    "arquivo": "14-provas-flat-09-10-11.md",
    "prefixo": ["prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/00-INDICE.md"],
    "nao_toca": ["resources/js/", "Modules/AssetManagement/"],
    "nota_provas": "thread de RECONCILIACAO: nao escreve codigo de app. Corrige 3 paths de prova (Alocacoes/Manutencoes/Configuracoes) para o layout flat que esta em producao, igual ja se fez para Bens em 08/09. Prova = o placar deixar de dizer 'pendente · arquivo ausente' para tela viva.",
    "provas": [
      { "tipo": "contem", "path": "prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/00-INDICE.md", "padrao": "Pages/Patrimonio/Alocacoes.tsx" },
      { "tipo": "contem", "path": "prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/00-INDICE.md", "padrao": "Pages/Patrimonio/Manutencoes.tsx" },
      { "tipo": "contem", "path": "prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/00-INDICE.md", "padrao": "Pages/Patrimonio/Configuracoes.tsx" },
      { "tipo": "arquivo", "path": "resources/js/Pages/Patrimonio/_shared/PatrimonioSubNav.tsx", "guarda": true }
    ]
  },
  {
    "id": "15",
    "titulo": "As 8 chamadas de view que nao tem arquivo",
    "dono": "CL",
    "vaga": 1,
    "arquivo": "15-views-fantasma.md",
    "prefixo": [],
    "nao_toca": ["*"],
    "depende_threads": ["14"],
    "nota_provas": "thread de MEDICAO: nao escreve codigo. Prova = _saida-15.md com veredito por sitio (alcancavel e quebra / inalcancavel / ja coberto por outra view).",
    "provas": []
  },
  {
    "id": "16",
    "titulo": "Revogacoes entram na aba de Alocacoes",
    "dono": "CL",
    "vaga": 2,
    "arquivo": "16-revogacoes-na-aba.md",
    "prefixo": [
      "Modules/AssetManagement/Http/Controllers/RevokeAllocatedAssetController.php",
      "resources/js/Pages/Patrimonio/Alocacoes.tsx",
      "Modules/AssetManagement/Tests/Feature/SmokeRoutesTest.php"
    ],
    "nao_toca": [
      "Modules/AssetManagement/Services/AssetAllocationService.php",
      "resources/js/Pages/Patrimonio/_shared/"
    ],
    "depende_threads": ["15"],
    "nota_provas": "fundir a ROTA e decisao [W] (nota da thread 09). Esta thread NAO funde rota: mantem GET asset/revocation e faz o index dele devolver a MESMA Page de Alocacoes com a visao de revogacao — a fusao e de TELA.",
    "provas": [
      { "tipo": "contem", "path": "Modules/AssetManagement/Http/Controllers/RevokeAllocatedAssetController.php", "padrao": "Inertia::render('Patrimonio/Alocacoes'" },
      { "tipo": "nao_contem", "path": "Modules/AssetManagement/Http/Controllers/RevokeAllocatedAssetController.php", "padrao": "asset_revocation.index" },
      { "tipo": "arquivo", "path": "resources/js/Pages/Patrimonio/Alocacoes.tsx", "guarda": true }
    ]
  },
  {
    "id": "17",
    "titulo": "Bens — formulario (create/edit/show)",
    "dono": "CL",
    "vaga": 2,
    "arquivo": "17-bens-form.md",
    "prefixo": ["resources/js/Pages/Patrimonio/Bens.tsx", "Modules/AssetManagement/Http/Controllers/AssetController.php"],
    "nao_toca": ["resources/js/Pages/Patrimonio/_shared/"],
    "depende_threads": ["16"],
    "depende_decisoes": ["D-FORMS"],
    "bloqueio": "D-FORMS — os charters declaram create/edit/show como Non-Goal COM motivo. Nao executar antes da resposta [W].",
    "provas": []
  },
  {
    "id": "18",
    "titulo": "Alocacoes — formulario",
    "dono": "CL",
    "vaga": 2,
    "arquivo": "18-alocacoes-form.md",
    "prefixo": ["resources/js/Pages/Patrimonio/Alocacoes.tsx", "Modules/AssetManagement/Http/Controllers/AssetAllocationController.php"],
    "nao_toca": ["Modules/AssetManagement/Services/AssetAllocationService.php"],
    "depende_threads": ["16"],
    "depende_decisoes": ["D-FORMS"],
    "bloqueio": "D-FORMS",
    "provas": []
  },
  {
    "id": "19",
    "titulo": "Manutencoes — formulario",
    "dono": "CL",
    "vaga": 2,
    "arquivo": "19-manutencoes-form.md",
    "prefixo": ["resources/js/Pages/Patrimonio/Manutencoes.tsx", "Modules/AssetManagement/Http/Controllers/AssetMaitenanceController.php"],
    "nao_toca": ["resources/js/Pages/Patrimonio/_shared/"],
    "depende_threads": ["16"],
    "depende_decisoes": ["D-FORMS"],
    "bloqueio": "D-FORMS — e o controller do D1 (&& onde deveria ser ||, 6 sitios). Registrar, nao corrigir aqui.",
    "provas": []
  },
  {
    "id": "20",
    "titulo": "Configuracoes — formulario",
    "dono": "CL",
    "vaga": 3,
    "arquivo": "20-config-form.md",
    "prefixo": ["resources/js/Pages/Patrimonio/Configuracoes.tsx", "Modules/AssetManagement/Http/Controllers/AssetSettingsController.php"],
    "nao_toca": ["Modules/AssetManagement/Config/retention.php"],
    "depende_threads": ["16"],
    "depende_decisoes": ["D-FORMS"],
    "bloqueio": "D-FORMS",
    "provas": []
  }
]
```

## O que este patch NÃO faz
- **Não** toca `12` (Garantias) nem `13` (Auditoria): seguem travadas em `D-GARANTIAS`/`D-AUDITORIA`, já escritas, prontas pra executar no dia da resposta.
- **Não** afirma paridade de nada. Só o T7 (`design-diff --compare --check` nos dois renders, prod deployada) afirma, e ele não roda daqui.
- **Não** cria script, gate, mapa nem inventário. As contagens deste patch são medição de um turno, não arquivo de retrato.

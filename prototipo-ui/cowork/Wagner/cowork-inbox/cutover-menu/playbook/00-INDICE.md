---
sessao: "00"
titulo: Cutover pelo menu — telas React que o menu não alcança
autor: "[CC]"
criado: 2026-10-07
base: wagnerra23/oimpresso.com@main 348b1498bebe
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/cutover-menu/playbook/
---

# Cutover pelo menu

## 1 · LEVANTAR — medido em `348b1498bebe`
A sidebar renderiza `<a href>` (`Sidebar.tsx:833` · `:1213` · `:1456` · `:1612`) e o atalho `G X` usa `window.location.assign` (`useSidebarShortcut.ts:164`, escolha declarada no comentário `:116`). Toda navegação pelo menu é carga cheia, **sem** `X-Inertia`. Estes controllers só devolvem a Page React quando o cabeçalho vem:

| tela | controller@método | condição hoje |
|---|---|---|
| Produtos · lista / novo | `ProductController@index` · `@create` | `if (request()->header('X-Inertia')) {` |
| Rascunhos · Cotações | `SellController@getDrafts` · `@getQuotations` | idem |
| Assinaturas (venda) | `SellPosController@listSubscriptions` | idem |
| Compras · lista / nova | `PurchaseController@index` · `@create` | `if (request()->header('X-Inertia') || request()->query('v') === '2') {` |
| Transferências · Ajustes (lista / novo) | `StockTransferController` · `StockAdjustmentController` | idem |
| Compra × Venda · Representantes | `ReportController@getPurchaseSell` · `@getSalesRepresentativeReport` | `if ($request->query('tela') === 'nova') {` |

Precedente do conserto por tela: `UnitController@index` — React salvo `?classico=1` ou DataTable AJAX.

## 2 · Decisões
| id | pergunta | destrava |
|---|---|---|
| D1 ✅ | **Por tela** — React padrão, Blade só com `?classico=1` ([W] 07/10) | 01–05 |

## 3 · Threads

```json
{
  "modulo": "Cutover pelo menu (transversal)",
  "sha": "348b1498bebe",
  "gerado": "2026-10-07",
  "variaveis": {},
  "decisoes": [
    {
      "id": "D1",
      "pergunta": "As 11 telas com React pronto só abrem React com o cabeçalho X-Inertia, e a sidebar navega com <a href> (carga cheia). Corrigir por tela (React vira padrão, Blade só com ?classico=1, como Unidades) ou de uma vez (sidebar navega com router do Inertia)?",
      "respondida": true,
      "destrava": [
        "01",
        "02",
        "03",
        "04",
        "05"
      ],
      "resposta": "por tela: React vira padrão, Blade só com ?classico=1 (molde UnitController)",
      "fonte": "_DECISOES-W-2026-10-07.md"
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "Produtos: lista e novo (ProductController index/create)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "01.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "app/Http/Controllers/ProductController.php",
        "tests/Feature/CutoverMenu/ProdutoSemXInertiaTest.php"
      ],
      "nao_toca": [
        "resources/js/Components/cockpit/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "tests/Feature/CutoverMenu/ProdutoSemXInertiaTest.php"
        }
      ],
      "nota_provas": "o teste faz GET sem X-Inertia → resposta Inertia; ?classico=1 → Blade; AJAX → JSON. Não uso nao_contem do gate: a mesma condição aparece em métodos fora do escopo (ProductController 6×, SellController 6×, PurchaseController 3× @d452b4dc8db8)."
    },
    {
      "id": "02",
      "titulo": "Vendas: Rascunhos, Cotações, Assinaturas (SellController getDrafts/getQuotations · SellPosController listSubscriptions)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "02.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "app/Http/Controllers/SellController.php",
        "app/Http/Controllers/SellPosController.php",
        "tests/Feature/CutoverMenu/VendasListasSemXInertiaTest.php"
      ],
      "nao_toca": [
        "resources/js/Components/cockpit/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "tests/Feature/CutoverMenu/VendasListasSemXInertiaTest.php"
        }
      ],
      "nota_provas": "o teste faz GET sem X-Inertia → resposta Inertia; ?classico=1 → Blade; AJAX → JSON. Não uso nao_contem do gate: a mesma condição aparece em métodos fora do escopo (ProductController 6×, SellController 6×, PurchaseController 3× @d452b4dc8db8)."
    },
    {
      "id": "03",
      "titulo": "Compras: lista e nova (PurchaseController index/create)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "03.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "app/Http/Controllers/PurchaseController.php",
        "tests/Feature/CutoverMenu/ComprasSemXInertiaTest.php"
      ],
      "nao_toca": [
        "resources/js/Components/cockpit/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "tests/Feature/CutoverMenu/ComprasSemXInertiaTest.php"
        }
      ],
      "nota_provas": "o teste faz GET sem X-Inertia → resposta Inertia; ?classico=1 → Blade; AJAX → JSON. Não uso nao_contem do gate: a mesma condição aparece em métodos fora do escopo (ProductController 6×, SellController 6×, PurchaseController 3× @d452b4dc8db8)."
    },
    {
      "id": "04",
      "titulo": "Estoque: Transferências e Ajustes (lista e novo)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "04.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "app/Http/Controllers/StockTransferController.php",
        "app/Http/Controllers/StockAdjustmentController.php",
        "tests/Feature/CutoverMenu/EstoqueSemXInertiaTest.php"
      ],
      "nao_toca": [
        "resources/js/Components/cockpit/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "tests/Feature/CutoverMenu/EstoqueSemXInertiaTest.php"
        }
      ],
      "nota_provas": "o teste faz GET sem X-Inertia → resposta Inertia; ?classico=1 → Blade; AJAX → JSON. Não uso nao_contem do gate: a mesma condição aparece em métodos fora do escopo (ProductController 6×, SellController 6×, PurchaseController 3× @d452b4dc8db8)."
    },
    {
      "id": "05",
      "titulo": "Relatórios Compra × Venda e Representantes (ReportController)",
      "dono": "CL",
      "vaga": 2,
      "prs": 1,
      "arquivo": "05.md",
      "depende_decisoes": [
        "D1"
      ],
      "prefixo": [
        "app/Http/Controllers/ReportController.php",
        "tests/Feature/CutoverMenu/RelatoriosSemXInertiaTest.php"
      ],
      "nao_toca": [
        "resources/js/Components/cockpit/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "tests/Feature/CutoverMenu/RelatoriosSemXInertiaTest.php"
        }
      ],
      "nota_provas": "o teste faz GET sem X-Inertia → resposta Inertia; ?classico=1 → Blade; AJAX → JSON. Não uso nao_contem do gate: a mesma condição aparece em métodos fora do escopo (ProductController 6×, SellController 6×, PurchaseController 3× @d452b4dc8db8)."
    }
  ],
  "revisado": "2026-10-07 decisões [W] do formulário · 2026-10-07 revisão: prefixos disjuntos + provas que decidem"
}
```

## 4 · O que este índice NÃO resolve
- A mesma condição `X-Inertia` existe em métodos fora do menu (`ProductController` 6×, `SellController` 6×, `PurchaseController` 3×, contados @d452b4dc8db8). As threads mexem só nos métodos da tabela §1; os outros já recebem visita Inertia vindo das listas React.
- Telas atrás de chave por empresa (Contatos, Usuários, Vendas/POS, Repair…) — o estado das chaves em produção não se lê do repo.
- Por tela: o AJAX da DataTable precisa continuar respondendo JSON (o `ajax()` vem antes do React, como em `UnitController`).

# Playbook — Jana · **Painel** (`/ia`)

> Pasta é a unidade de descida. **1 thread = 1 seção = 1 PR ≤300 linhas = 1 prefixo**, sessão limpa, read-order lido no `main`.
> Página única ⇒ **onda = seção** (§Granularidade do PROTOCOLO).
> **Absorve** `COLAR-NO-CODE-jana-tabs-cor-e-icone.md` (anti-scatter §12): aquele arquivo foi apagado neste ciclo, as duas ondas dele fecharam sozinhas no `main` (recibo abaixo). Nenhum doc novo foi espalhado.
> Ponte, não canon. **Não escrevo no git** — isto desce por `cowork-inbox/` ou Issue → PR.

```json
{
  "modulo": "Jana",
  "view": "painel",
  "rota": "/ia",
  "controller": "Modules\\Jana\\Http\\Controllers\\IndexController@index",
  "page": "resources/js/Pages/Jana/Index.tsx",
  "ancora_layout": "prototipo-ui/cowork/Wagner/jana-merge.jsx §JanaPage",
  "ancora_implementacao": [
    "resources/js/Pages/Jana/Index.tsx",
    "resources/js/Pages/Jana/_components/JanaCockpit.tsx",
    "resources/js/Pages/Jana/_components/useJanaPro.ts",
    "app/Http/Middleware/HandleInertiaRequests.php"
  ],
  "contrato": "governance/design/contracts/jana-painel.contract.json",
  "charter": "resources/js/Pages/Jana/Index.charter.md",
  "casos": "resources/js/Pages/Jana/Index.casos.md",
  "lido_em": "2026-09-21",
  "tree": "e57b78bf54e7",
  "ondas": [
    { "id": "01", "secao": "gating Pro (brief · análises · ações)", "ficha": "01-painel.gating-pro.md", "arquivos": 2, "estado": "aberta" },
    { "id": "02", "secao": "estado vazio da página", "ficha": "02-painel.estado-vazio.md", "arquivos": 1, "estado": "aberta" }
  ],
  "fechadas_neste_ciclo": [
    { "id": "1.1", "secao": "pill do contador inativo", "prova": "PageHeaderTabs.tsx ramo inativo = var(--bg-2)/var(--text-dim)" },
    { "id": "2.1", "secao": "valor e rodapé do card de meta", "prova": "Index.tsx §MetaCard tem 'de {alvo}' e '{pct}% do alvo'" }
  ]
}
```

## Leitura do `main` feita neste turno (2026-09-21)

| # | arquivo | pra quê |
|---|---|---|
| 1 | `Modules/Jana/Http/routes.php` | resolver `/ia` (é o Painel; `/ia/conversa` é o chat; `/ia/dashboard` é 301) |
| 2 | `Modules/Jana/Http/Controllers/IndexController.php` | contrato do payload (`metas`, `sellKpis`, `insightsAggregates`, `coworkAggregates` **deferida**, `janaContext`) |
| 3 | `resources/js/Pages/Jana/Index.tsx` | ordem das seções, `aposKpis`, `MetaCard`, header |
| 4 | `resources/js/Pages/Jana/_components/JanaCockpit.tsx` | brief · KPIs · 5 análises · 5 ações HITL (lido por faixa, não linha a linha) |
| 5 | `resources/js/Pages/Jana/_components/useJanaPro.ts` | de onde vem `pro` |
| 6 | `app/Http/Middleware/HandleInertiaRequests.php` (por busca) | `jana.pro` existe, lazy, `janaPlanoPro(businessId)` lê `jana_pro_module` |
| 7 | `resources/js/Components/shared/PageHeaderTabs.tsx` | fechar a onda 1.1 |
| 8 | `resources/js/Pages/Jana/_shared/JanaSubNav.tsx` | 6 abas, `density="compact"` |

**Não lido ⇒ não vira pedido, e está declarado em cada ficha §8:** `Index.charter.md` (50.096 B), `Index.casos.md` (98.209 B), `PainelContratoTest.php`, `jana-painel.contract.json`, `SellsCockpitAggregator`, `Pro.tsx`, `JanaConfigDrawer.tsx`.

## PLACAR deste ciclo

```
divergências medidas na aba Painel .......... 5
paridade confirmada por leitura ............. ordem das seções · brief · 3 KPIs · METAS ·
                                              análises · ações · nota 768px · 6 abas compact
ondas anteriores JÁ FECHADAS no main ........ 2 (1.1 · 2.1) — não re-pedir
vira pedido ................................. 2 ondas · 3 arquivos (01 · 02)
main À FRENTE (não mexer, é a11y) ........... 1 — KPI/análise/meta são <button> nativo;
                                              o protótipo usa div[role=button] + onKeyDown
⛔ [W] / fundação ........................... 2 (Segmented Farol/Cadastro · estado de ERRO
                                              da página — sem sinal de servidor hoje)
"0 bug" ..................................... NÃO. Só o T7 (design-diff --compare --check
                                              nos dois renders, prod deployada) afirma.
```

## Regra de saída (ADR 0387)

Este ciclo fecha **sem** pacote regenerado — o gerador exige os arquivos em disco e **não roda do lado do agente** (ADR 0374), então **não afirmo que regenerei**:

```
node scripts/design-sync/gerar-payload-partes.mjs --root <dir> --out sync/ --previous sync/bundle.manifest.json
```

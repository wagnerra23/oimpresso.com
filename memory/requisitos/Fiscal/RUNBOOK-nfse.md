# RUNBOOK — Fiscal/Nfse (sub-página 3)

> **Tela:** `/fiscal/nfse`
> **Page:** [`resources/js/Pages/Fiscal/Nfse.tsx`](../../../resources/js/Pages/Fiscal/Nfse.tsx)
> **Charter:** `Nfse.charter.md`
> **Controller:** [`NfseCockpitController`](../../../Modules/Fiscal/Http/Controllers/NfseCockpitController.php)
> **Permissão:** `fiscal.nfse.view`
> **PR origem:** PR #2 Wave consolidada

## 1. Objetivo

Lista navegável NFS-e (modelo 56 nacional NT 2024-001) com filtros status + competência + busca. Substitui necessidade de abrir `Modules/NfeBrasil/...` ou pedir relatório ao contador.

## 2. Estrutura

```
FxShell
├── Hero (NFS-e + crumb + faturamento autorizado + month picker)
├── SubNav (highlighted nfse)
├── Body
│   ├── Filters (search + 5 chips status)
│   └── Tabela paginada (Deferred Inertia)
└── Cheatsheet
```

## 3. Dados

- Model: `Modules\NfeBrasil\Models\NfseEmissao` (HasBusinessScope, modelo 56 nacional)
- Counts eager + rows deferred (paginate 50)
- Filtro `mes` (YYYY-MM) usa `whereBetween('emitted_at', ...)` — try/catch parse seguro

## 4. Status NFS-e (constants Model)

- `pending` (criado, não enviado) → tone warn
- `sent` (XML enviado, aguarda autorização) → tone warn
- `authorized` (autorizada, com `numero_nfse` + `codigo_verificacao`) → tone ok
- `rejected` (ver `error_msg`) → tone bad
- `cancelled` (cancelada via evento posterior) → tone bad

## 5. Permissão

`fiscal.nfse.view` — adicionada em PR #2 (DataController user_permissions).

## 6. Riscos

- **R1**: Schema NfseEmissao usa `$guarded = ['id']` — Controller assume colunas (numero_nfse, codigo_verificacao, nome_tomador, etc). Se Model evoluir, ajustar `mapRow()`.
- **R2**: `error_msg` pode conter PII — exibido só no hover/title da row, nunca em log

## 7. Smoke biz=1

```bash
curl -sv "https://oimpresso.com/fiscal/nfse" -H "Cookie: ..." 2>&1 | grep '^< HTTP'
# Esperado: < HTTP/2 200
```

---

**Atualizado:** 2026-05-20 — criação inicial PR #2 Wave consolidada

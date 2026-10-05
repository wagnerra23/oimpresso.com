# Tela 04 · Orçamento rápido (P4) ⬜ — regra mestre

> Proposta. Vira `api/tela-04-orcamento-rapido.md` no ERP.

## Já existe ✅ (só na web, por sessão)
- `POST /comunicacao-visual/api/calcular`: prévia autoritativa, sem gravar.
- `POST /comunicacao-visual/api/orcamentos`: grava o orçamento.
- Ambos em `OrcamentoController`, throttle 60/min. O preço vem de `comvis_materiais.preco_venda_m2`.

## Novo ⬜ — mesmas funções, com token
`GET /api/app/orcamentos/materiais` → `{ itens: [{ id, nome, categoria, unidade, preco_m2 }] }`. Mesmo catálogo da calculadora web, com `business_id` explícito.

`POST /api/app/orcamentos/calcular`:
```json
{ "material_id": 12, "largura_m": "3.00", "altura_m": "1.20", "quantidade": "1" }
```
→ `200 { "area_m2": 3.6, "preco_m2": 65.00, "total": 234.00 }`. Não grava nada.

- Números de entrada **como texto** `"N.NN"`, como na venda §2.2. Nada passa por `Util::num_uf` (incidente de 2026-06-05).

`POST /api/app/orcamentos` (header `Idempotency-Key`):
```json
{ "cliente_id": 1, "itens": [ { "material_id": 12, "largura_m": "3.00", "altura_m": "1.20", "quantidade": "1", "total_previsto": "234.00" } ] }
```
→ `201 { id, numero: "ORC-2231", status: "rascunho", total }`.

- **Dupla prova:** o ERP recalcula. Se `total_previsto` divergir → `422 campos["itens.N.total_previsto"]` e nada é gravado.
- Nasce como rascunho (`status=draft`), como no §2.1. Enviar ao cliente é uma tarefa separada, junto com o P5.

| Código | Quando |
|---|---|
| `403 sem_permissao` | sem `comvis.orcamento.create` |
| `422 validacao` | medida ≤ 0, material de outro business, total divergente |
| `503 sem_configuracao` | business sem material cadastrado |

## Regra mestre ⚠️
Mexe em valor. Antes do merge: dupla prova, tabela antes → depois e ok do [W].

## App
O app **nunca** soma sozinho. Mostra "calculando no ERP…" até chegar a resposta de `/calcular`, com debounce de 500 ms.

## Ajuste da empresa
`app_orcamento_rapido` (padrão `false`). Liga o "+ Novo" no app. Com `false`, `POST /orcamentos` → `403 sem_permissao`. Só pode ir para `true` depois do ok do [W] na regra mestre.

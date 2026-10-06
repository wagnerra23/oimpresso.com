---
sessao: "01"
titulo: "Casos: Knowledge Create/Edit/Show + Todo Create/Edit/Show — saída da thread"
autor: "[CL]"
criado: 2026-10-06
base: 26b8c8cbe4
thread: 01-casos-forms.md
veredito: "entregue em 2 PRs — 6 .casos.md (12 UC, 4 deles [T0]) + 2 testes de contrato na lane Essentials · MySQL; nenhum .tsx tocado. Um achado Tier 0 de escrita ficou no backlog do Knowledge/Create (store aceita parent_id de outro business)."
---

# _saída 01 · Casos dos formulários de Knowledge e Todo

Nenhum `.tsx` foi tocado. O `Todo/Index.tsx` (`nao_toca`) também não.

## O que saiu

| PR | arquivos |
|---|---|
| [#8760](https://github.com/wagnerra23/oimpresso.com/pull/8760) | `Knowledge/{Create,Edit,Show}.casos.md` · `Modules/Essentials/Tests/Feature/KnowledgeFormsContratoTest.php` · 1 linha na allowlist do `essentials-pest.yml` |
| este | `Todo/{Create,Edit,Show}.casos.md` · `Modules/Essentials/Tests/Feature/TodoFormsContratoTest.php` · 1 linha na allowlist · este recibo |

Dois PRs porque juntos passavam de 300 linhas.

## Placar

entregue **6 de 6** Pages com `.casos.md` · **12** UC · **12** `it()` que citam o UC-id.

| Page | UC |
|---|---|
| `Knowledge/Create` | UC-EKBC-01 (pai ou lista de usuários) · UC-EKBC-02 (livro restrito com lista de acesso) |
| `Knowledge/Edit` | UC-EKBE-01 (abre com `assigned_user_ids`) · UC-EKBE-02 `[T0]` |
| `Knowledge/Show` | UC-EKBS-01 (livro → seção → artigo, conteúdo sem `<script`) · UC-EKBS-02 `[T0]` |
| `Todo/Create` | UC-ETDC-01 (sem atribuídos fica com o autor) · UC-ETDC-02 (status e prioridades do servidor) |
| `Todo/Edit` | UC-ETDE-01 (grava e volta pro detalhe) · UC-ETDE-02 `[T0]` |
| `Todo/Show` | UC-ETDS-01 (`can_delete` só no meu comentário) · UC-ETDS-02 `[T0]` |

Todos nascem `🧪 sem veredito`. O ✅ vem do manifesto G-7 depois que a lane rodar.

## De onde vieram os casos

Charter de cada Page + `KnowledgeBaseController` / `ToDoController` + US-ESS-002/003 do SPEC. Nenhum do `.tsx` (§5 2026-06-05). Tenant 98 × 2 (ADR 0358), nunca biz=4. Os `[T0]` têm controle positivo (a página do meu tenant abre 200).

## Achado que não virou caso

`StoreKnowledgeBaseRequest` valida `parent_id` com `exists:essentials_kb,id`, sem `business_id`. O `create` (GET) resolve o pai dentro do tenant, mas um POST direto aceita pai de outro business. Não vaza leitura: o `show` do nó dá 404 e o `HasBusinessScope` esconde o filho do outro tenant. O charter, porém, afirma que o pai "é resolvido com `where business_id`", e isso só vale no GET. Ficou no backlog do `Knowledge/Create.casos.md`. O caso entra junto com o conserto do `store`.

## Provas locais (sem Pest — ADR 0062)

- `php -l` nos dois testes: sem erro.
- `uc-id-lint` nos 6 arquivos: 12 blocos, 0 fora do formato. Prefixos `EKBC/EKBE/EKBS/ETDC/ETDE/ETDS` sem colisão no repo (`git grep` 0).
- `casos-coverage-guard`: sem violação nova; o débito caiu.

## Pendente

- Veredito da lane `PHP / Pest (Essentials · MySQL)` nos dois PRs.
- Decisão D1 do índice (páginas × drawer) segue aberta. Esta thread não dependia dela.

---
thread: "03 · Casos: Macros Index + Variants + JanaTemplates"
dono: "[CL]"
estado: feito
base_lida: wagnerra23/oimpresso.com@main 7201ce433 (2026-09-30)
prefixo_tocado: Modules/Whatsapp/Resources/js/Pages/Atendimento/Macros/ · Atendimento/JanaTemplates.casos.md · tests/
---
# _saida-03 · Casos de Macros, Variantes e Bot Jana

## Entregue
| arquivo | UCs | teste que cita |
|---|---|---|
| `Atendimento/Macros/Index.casos.md` | UC-MAC-01..04 | `tests/Feature/Whatsapp/AtendimentoMacrosJanaTemplatesContratoTest.php` (novo) |
| `Atendimento/Macros/Variants.casos.md` | UC-MACV-01..04 | `Modules/Whatsapp/Tests/Feature/MacroVariantsCrudTest.php` (só o título dos 4 `it()` ganhou o UC-id) |
| `Atendimento/JanaTemplates.casos.md` | UC-JTPL-01..03 | `tests/Feature/Whatsapp/AtendimentoMacrosJanaTemplatesContratoTest.php` (novo) |

UCs derivados dos charters; controller lido só para confirmar. Nenhum `.tsx` tocado.
Teste novo: tenants fictícios 98/99 (ADR 0358), seed e conferência por `DB::table`, inclui os casos cross-tenant (UC-MAC-02, UC-JTPL-03).

## Fora do prefixo literal, e por quê
- **`.github/ci-sqlite-pest.list` +1 linha** (o teste novo). Sem ela o teste não roda em lane nenhuma e o UC teria só citação, sem execução.
- **`MacroVariantsCrudTest.php`**: 4 títulos renomeados. Ele já roda na lane sqlite; citar ali evita duplicar o teste.

## Provas do json
- `Macros/Index.casos.md` · `Macros/Variants.casos.md` · `JanaTemplates.casos.md` — `tipo: arquivo`, existem.
- `node scripts/casos-coverage-guard.mjs` local: "Sem violações novas DESTE PR".
- Pest: não rodei local (regra). Prova = CI do PR #8328, lane sqlite `PHP / Pest (Unit)`, run 36801631015: os 11 casos (7 novos + 4 UC-MACV) com ✓, nenhum pulado. Status dos UCs passou a 🧪.

## Pendente (decisão [W], não resolvida aqui)
1. **Macros: remover × desativar.** O charter pede "toggle ativo/inativo sem hard delete"; o `destroy` apaga a linha. O UC-MAC-04 descreve o que existe; está no Backlog do casos.
2. **Macros: Goals sem código.** Filtro por categoria e métricas enviadas/dia + taxa de resposta não existem (só `used_count`).
3. **JanaTemplates charter desatualizado.** Cita `JanaTemplatesControllerTest` e `JanaTemplatesRedirectTest`, que não existem, e um 301 de `GET /whatsapp/settings` que hoje renderiza o wizard (US-WA-310). O charter não está no prefixo desta thread.
4. **`MacrosCrudTest.php` não roda em lane nenhuma** (órfão no `test-lane-coverage`). O caso R-WA-048-004 conta macros sem `actingAs`, e o `ScopeByBusiness` só filtra com usuário autenticado; provavelmente quebraria se ligado. Não liguei.

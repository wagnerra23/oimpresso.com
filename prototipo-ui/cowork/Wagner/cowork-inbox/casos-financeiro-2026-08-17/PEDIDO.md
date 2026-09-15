# PEDIDO ao [CL] — 14 `casos.md` faltantes do Financeiro (contrato por tela)

**Origem:** [W] 2026-08-17, no espelho Cowork do Financeiro. Medido no `main` (tree `660602c31fe2`) neste turno:
**21 telas do Financeiro · 21 charters · 7 `casos.md`**. Faltavam 14 — inclusive **Fluxo e DRE**, duas das três
que acabei de espelhar, o que as reprova no gate do trio (`.tsx` + charter + casos com UC).

## O que fazer
Copiar cada arquivo desta pasta pro lugar canônico (renomeando para o padrão do repo):

| Arquivo aqui | Destino |
|---|---|
| `Fluxo.Index.casos.md` | `resources/js/Pages/Financeiro/Fluxo/Index.casos.md` |
| `Dre.Index.casos.md` | `resources/js/Pages/Financeiro/Dre/Index.casos.md` |
| `Cobranca.Index.casos.md` | `resources/js/Pages/Financeiro/Cobranca/Index.casos.md` |
| `Relatorios.Index.casos.md` | `resources/js/Pages/Financeiro/Relatorios/Index.casos.md` |
| `PlanoContas.Index.casos.md` | `resources/js/Pages/Financeiro/PlanoContas/Index.casos.md` |
| `Categorias.Index.casos.md` | `resources/js/Pages/Financeiro/Categorias/Index.casos.md` |
| `ContasBancarias.Index.casos.md` | `resources/js/Pages/Financeiro/ContasBancarias/Index.casos.md` |
| `Extrato.Index.casos.md` | `resources/js/Pages/Financeiro/Extrato/Index.casos.md` |
| `Dashboard.Index.casos.md` | `resources/js/Pages/Financeiro/Dashboard/Index.casos.md` |
| `Configuracoes.Contador.casos.md` | `resources/js/Pages/Financeiro/Configuracoes/Contador.casos.md` |
| `AssinaturaAtualizar.casos.md` | `resources/js/Pages/Financeiro/AssinaturaAtualizar.casos.md` |
| `Unificado.Novo.casos.md` | `resources/js/Pages/Financeiro/Unificado/Novo.casos.md` |
| `Advisor.Dashboard.casos.md` | `resources/js/Pages/Financeiro/Advisor/Dashboard.casos.md` |
| `Advisor.Login.casos.md` | `resources/js/Pages/Financeiro/Advisor/Login.casos.md` |

(`Conciliacao.NOTA.md` não é entrega — é só o registro de que aquela tela já tinha `casos.md`.)

## Regra respeitada (não quebrar o `casos-gate`)
G-2 / ADR 0264: **`UC-*` só onde existe teste real**. Onde a prova existe mas o `it()` não cita o id, o status
é 🧪 e a ação do PR é **citar o id no título do teste** — nenhum teste novo é exigido pra o gate ficar honesto.
Comportamento sem prova nenhuma ficou em **[BACKLOG] sem id**, de propósito.

## 3 achados que valem PR próprio
1. **Âncoras podres em 2 charters:** `ContasBancarias` cita `FinanceiroContasBancariasCharterTest` e `Extrato`
   cita `FinanceiroExtratoCharterTest` — **nenhum dos dois arquivos existe** em `Modules/Financeiro/Tests/`
   (medido 2026-08-17). Os charters prometem prova que não existe. Corrigir o charter ou criar os testes.
2. **Plano de contas com zero prova:** é o vocabulário que classifica todo lançamento (o filtro de plano da
   Unificada depende dele) e não tem um único teste de tela. Todos os casos nasceram em backlog.
3. **Rota legada `/financeiro/unificado/novo`:** o contrato de intenção já proíbe navegar pra ela e o charter v21
   manda todo ponto de entrada abrir o `TituloCreateSheet`. Recomendação: aposentar com 301 pra `/financeiro/unificado`.

## Decisões que precisam de [W]
- Dashboard: manter dormente (tier C) ou reativar? Enquanto dormente, só o 301 merece prova.
- Relatórios / Plano de contas / Categorias / Dashboard / Unificado-Novo: aprovar **Non-Goals + Anti-hooks** pra os charters saírem de `draft`.
- Categorias: o que acontece com categoria vinculada ao ser excluída (soft delete preserva vínculo?).
- Advisor Login: **throttle do POST está pendente** — risco de força-bruta enquanto não existir.

# Sessão 2026-06-09 (parte 2) — Auditoria Lista+Kanban pós-#2477 + chore de fechamento

**Papel:** [CC] · **Pedido [W]:** "conferir o que falta na lista e kanban" → gerar ponte de fechamento.

## Auditoria (Board.tsx + Index.tsx lidos no main)
- **Board ~95%**: drag+FSM+confirm, foto, DVI x/y, MercosulPlate, 6 KPIs, busca, RichSheet — tudo portado.
- **Index ~90%**: colunas reparo, Lista/Fila, pills, chips FSM, busca, paginação, drawer — ok pós-sweep.

## Gaps → chore PR (esta ponte)
1. Board: filtro/pivot por mecânico e box (dado já existe no drawer).
2. Board: prazo restante visível no card atrasado/urgente (countdown discreto).
3. Index: coluna Sintoma/Defeito (truncada) na tabela.
4. Front dead-types de locação: delivery_address/daily_rate/dias_locacao/has_return_date/locacoes_ativas etc. em Index/Fila — remover (casa com PR #2475).
5. Dados: backfill `order_type locacao|null → mecanica` (badge "—" nas OS legadas) + rename LABELS (não keys) dos estágios FSM cacamba_locacao.

## Decisões [W] (2026-06-09)
- **DESCARTADOS** (exploração do protótipo, não portar): mood calmo/pressão · densidade manual · view "Grade".

## Status fila OS-V2
F1 ✓ nos 4 itens; V2-1/V2-2 F2 ✓ + F3 merged (#2482 + gates); V2-3/V2-4 F1 verificado, aguardando F2 [W].

## Adendo — comparação de drawers + batch 2 (aprovado [W] "aprovo")
Drawer real ~85% do protótipo pós-#2482 (DVI semáforo + Fotos&Laudo portados ✓).
[W] deu **F2 em OS-V2-3 e OS-V2-4** e aprovou registrar 2 gaps novos:
**OS-V2-5 StageGate** (checklist de bloqueio por etapa — maior gap) e
**OS-V2-6 lançar item inline** (Peças & Mão de obra read-only no drawer).
Ponte F3 única gerada pros 4 (append em prototipo-ui-patch/COWORK_NOTES_APPEND_2026-06-09-b.md).
Gate real SEM botões de simulação (demo-only do protótipo); estados derivam do status/timestamps da OS.
Timeline real via /fsm/history (criar endpoint se faltar). Residual cosmético: Observação×Sintoma.

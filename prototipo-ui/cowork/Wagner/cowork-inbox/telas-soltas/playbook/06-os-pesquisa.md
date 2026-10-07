---
sessao: "06"
titulo: OS da gráfica — pesquisar o dono
dono: "[CL]"
base: 5d9da472e955
---
# 06 · OS da gráfica — PESQUISA, sem código

O protótipo `os-page.jsx` (rota `os`) é a lista de **OS de comunicação visual** (banner, lona, ACM, adesivo; etapas Rascunho → Orçado → Aprovação → Produção → Acabamento → Expedição → Entregue). No `main` existem três candidatos e **nenhum é óbvio**:
- `resources/js/Pages/ComunicacaoVisual/Index.tsx` (23.955 B)
- a venda com pipeline FSM (ADR 0143 — `sale_stage`, `ExecuteStageActionService`)
- `Repair`/`JobSheet` e `OficinaAuto/ServiceOrders` (OS de outro domínio — provavelmente **não**)

**Entregue:** `_saida-06.md` com (1) qual tabela/Model guarda a OS da gráfica hoje, com `arquivo:linha`; (2) se as 7 etapas do protótipo batem com os estágios reais; (3) recomendação de onde a Page nasce. **Não criar Page nem rota.** Volta ao Cowork; eu ajusto o protótipo ao dono real antes de pedir a tela.

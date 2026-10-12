---
sessao: "_saida-06"
thread: "06 · OS da gráfica: PESQUISAR qual backend é o dono antes de qualquer Page"
dono: "[CL]"
data: 2026-10-09
tipo: recibo (pesquisa, sem código)
base_lida: wagnerra23/oimpresso.com@main eecb5a50f
---
# _saida-06 · Quem é o dono da OS da gráfica

Nenhuma Page, rota ou código foi criado (a thread tem `nao_toca` em `app/`, `Modules/` e `resources/`).

## (1) Onde a OS mora hoje — o dono é `Modules/ComunicacaoVisual`, em DUAS tabelas
| tabela / Model | situação |
|---|---|
| `comvis_os` → `Modules/ComunicacaoVisual/Entities/Os.php` | Sprint 1. Único uso em runtime: `ApontamentoController` valida `exists:comvis_os,id` e `comvis_apontamentos.os_id` é FK para ela. Etapas no enum `status_etapa` (`Database/Migrations/…000042…:32`). |
| `cv_ordens_producao` → `Entities/OrdemProducao.php` | A canônica pela SPEC e pelo `SCOPE.md:52` (consumir o FSM por aqui). `current_stage_id` aponta para `sale_process_stages`; 1 OS = 1 venda (`transaction_id`); `GuardsFsmTransitions` impede UPDATE direto. **Sem controller, rota ou tela** (`BRIEFING.md:24`, "schema órfão"). |

`Modules/ComunicacaoVisual/Routes/web.php` tem só o hub (`/`), install, `api/calcular`, `api/orcamentos` e `api/apontamentos` — **nenhuma rota de OS**. `SCOPE.md:49`: as `comvis_*` são legado da Sprint 1, a unificar na US-COMVIS-NEW-014.

**Não são o dono:** o pipeline de vendas (é a fundação genérica do FSM, que o CV consome com o processo próprio `os_comunicacao_visual`); Repair/JobSheet e OficinaAuto (outros domínios — o próprio protótipo usa outro FSM para eles, `fsm-stepper.jsx`).

## (2) As 7 etapas do protótipo × os estágios reais
Estágios reais: `comvis_os` = `arte, producao, finalizando, entrega, instalacao, concluida, cancelada`; FSM `os_comunicacao_visual` (`database/seeders/FsmProcessoComunicacaoVisualSeeder.php`) = `quote_draft, quote_sent, quote_approved, arte_em_aprovacao, arte_aprovada, aguardando_maquina, em_impressao, impressao_concluida, aguardando_acabamento, acabamento_concluido, aguardando_instalacao, em_instalacao, instalado_aguardando_aprovacao_final, entregue_completo` + terminais `cancelado`, `garantia_acionada`.

| protótipo (`data-os.jsx`) | FSM `cv_ordens_producao` | `comvis_os` |
|---|---|---|
| Rascunho | `quote_draft` | — (o orçamento é `comvis_orcamentos.rascunho`) |
| Orçado | `quote_sent` (+`quote_approved`) | — |
| Aprovação (arte) | `arte_em_aprovacao` (+`arte_aprovada`) | `arte` |
| Produção | `aguardando_maquina` · `em_impressao` · `impressao_concluida` | `producao` |
| Acabamento | `aguardando_acabamento` · `acabamento_concluido` | `finalizando` |
| **Expedição** | **não existe** (o mais perto é `aguardando_instalacao`/`em_instalacao`) | `entrega` / `instalacao` |
| Entregue | `entregue_completo` (+`instalado_aguardando_aprovacao_final`) | `concluida` |
| Cancelado | `cancelado` | `cancelada` |

As etapas **não batem 1:1**: cada fase do protótipo agrupa várias keys do FSM; "Expedição" não tem equivalente; o FSM tem instalação e `garantia_acionada`, que o protótipo não mostra.

## (3) O que `resources/js/Pages/ComunicacaoVisual/Index.tsx` é
Servida por uma closure em `Routes/web.php` (`GET /comunicacao-visual`): catálogo `comvis_materiais` + calculadora de orçamento por m² (US-COMVIS-001). "Ordens de serviço" é só um cartão "em breve". **Não lista OS.**

## (4) Recomendação
- **Dono:** `Modules/ComunicacaoVisual`, em cima de `cv_ordens_producao` + FSM `os_comunicacao_visual` (é o que o `SCOPE.md:52` e a US-COMVIS-003 pedem — o `OsController` que a SPEC lista como pendente).
- **Onde a Page nasce (inferido):** `resources/js/Pages/ComunicacaoVisual/Os/Index.tsx`, servida por `Modules/ComunicacaoVisual/Http/Controllers/OsController@index` numa rota `comunicacao-visual/os`. Badge/stepper agrupam as keys do FSM nas fases do protótipo pela tabela acima; mudança de etapa só por `ExecuteStageActionService`.

## O que volta ao Cowork / ao [W] antes de pedir a tela
1. **"Expedição"**: entra no seeder do FSM ou sai do protótipo — decisão [W].
2. **Duas tabelas**: os apontamentos ainda apontam para `comvis_os`. A tela sobre `cv_ordens_producao` precisa da unificação (US-COMVIS-NEW-014) ou de uma ponte.
3. **Processo em produção**: o seeder do FSM só é chamado em teste (`Tier0GuardTest.php`). Não há prova de que `os_comunicacao_visual` existe para algum business em produção — medir antes de construir.

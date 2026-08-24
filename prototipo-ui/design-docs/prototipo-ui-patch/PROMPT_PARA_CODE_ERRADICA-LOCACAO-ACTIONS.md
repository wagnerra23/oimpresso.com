# [CL] · FRENTE ÚNICA OFICINA — 2 PRs: módulo USÁVEL (fio) + módulo À ALTURA (visual)

> Cole este prompt inteiro no Claude Code. Autocontido.
> **Critério de pronto (único que importa, [W] 2026-06-10):** criar uma OS nova e andar
> com ela recepção → diagnóstico → aprovação → execução → entregue → imprimir, logado
> como o usuário real do negócio, **sem travar em nenhum passo**. Nada de front nova
> até esse fio passar.
>
> Evidência da quebra (screenshot [W], OS-00004): estágio "Aguardando" do pipeline de
> LOCAÇÃO, checklist oferece "Iniciar locação (entregar caçamba)", painel FSM diz
> "Nenhuma transição disponível" + "2 ação(ões) oculta(s) por falta de permissão".
> O usuário cria a OS e morre na praia. 3 causas, todas @main:

## PR única — 4 consertos no fio

### 1. OS nova NUNCA entra no pipeline de locação
- `ServiceOrderFsmActionController::ORDER_TYPE_TO_PROCESS`: remover a entrada
  `'locacao' => 'cacamba_locacao'` (enum já não aceita 'locacao' — migration 2026_06_09_000001).
- **Auto-start do pipeline no store()**: hoje a OS nasce com `current_stage_id=null` e
  depende de um clique manual em start-pipeline (que pode cair no processo errado).
  No `ServiceOrderController::store()`, após criar a OS, iniciar o pipeline
  `oficina_mecanica_os` (stage `recepcao`) reaproveitando a lógica do
  `startPipeline()` (extrair pra service/método compartilhado). OS nasce já no quadro.

### 2. Permissão não pode esconder o fluxo do dono do negócio
- Sintoma: "2 ação(ões) oculta(s) por falta de permissão" pro próprio [W].
  Causa: actions exigem roles Spatie `mecanico#<biz>`/`gerente#<biz>` que NENHUM usuário
  recebeu (o seeder cria as roles, ninguém as atribui).
- Conserto mínimo: `StageActionPolicy::canExecute` → permitir também quem tem
  `oficinaauto.service_order.update` (ou admin do business). Roles continuam valendo
  como RESTRIÇÃO adicional quando atribuídas, nunca como muro default que ninguém passa.
- Validar: com o usuário real do biz, `fsm/actions` retorna can_execute=true nas ações do fluxo.

### 3. OS órfãs no pipeline legado (a OS-00004 do print)
- Migration: `service_orders` com `current_stage_id` em stage de
  `cacamba_locacao`/`cacamba_manutencao` E sem transição real no histórico
  (`sale_stage_history` só com `pipeline_started`, `action_id IS NULL`) →
  re-apontar pro stage inicial (`recepcao`) do `oficina_mecanica_os` do mesmo business
  (ou NULL + auto-start). OS com histórico real: não tocar, listar no output do PR.
- ⚠ Dado live (Martinho biz=164): SELECT de contagem antes; colar número no PR.

### 4. Vocabulário — matar "locação" do que sobrou visível
- Migration espelho da `2026_06_09_000003`, agora em `sale_stage_actions.label`
  (processo `cacamba_locacao`, casar key + label antigo, idempotente/reversível):
  | key | antigo | novo |
  |---|---|---|
  | `iniciar_locacao` | Iniciar locação (entregar caçamba) | Iniciar execução |
  | `recolher` | Recolher caçamba (devolução) | Concluir serviço |
  | `enviar_manutencao` | Enviar pra manutenção | Enviar pra diagnóstico |
  | `voltar_disponivel` | Liberar pra locação | Voltar pra aguardando |
- Mesmos labels no seeder `OficinaAutoFsmSeeder::seedLocacaoProcess()` + docblock.
- ⛔ KEYS FSM e side_effect_class INTOCADOS (Tier 0 — ADR 0143/0194, Martinho live).

### 5. Varredura final — NADA de locação visto pelo usuário (achado [CC] 2026-06-10, não esperar [W] descobrir)
- **`producao-oficina` (o quadro que o menu abre — print [W] 2026-06-10 13:25): o modelo de LOCAÇÃO ainda alimenta os cards.** Evidências no print, todas devem morrer:
  - empty-state das colunas = **"nenhuma caçamba"** → "nenhuma OS";
  - cards mostram **"30 diárias · vence 20/05"** (conceito de aluguel) → prazo de reparo (`expected_completion`) ou nada;
  - chip **"7m³/5m³"** (capacidade de caçamba) como identidade do card → placa + modelo/defeito;
  - **endereço de obra** ("Rod. BR-101 km 142 — Obra ponte" = delivery_address de locação) → remover do card;
  - título "Veículo 7m³" → modelo real do veículo.
  Causa provável: `ProducaoOficinaController::loadRentalFallbacks` + `_components` (CacambaCard/KanbanColumn) renderizando campos de rental. Matar o fallback de rental na composição do card; "No pátio · sem OS aberta" pode ficar (não é locação).
- **DOIS kanbans duplicados**: `producao-oficina` (menu) e `ServiceOrders/Board`. Default proposto: `producao-oficina` é o canônico (é o que o menu abre); `ordens-servico/board` redireciona pra ele (ou vice-versa se o Board for o mais completo — decida lendo os dois e registre no PR). UMA verdade, não duas.
- **Index de OS**: remover o card/chip "Locações ativas" (o controller já manda
  `locacoes_ativas=0` e o comentário em `buildServiceOrderKpisPayload` admite a dívida
  — RUNBOOK-erradicacao-locacao P5). Remover a key do payload + o card no `Index.tsx`
  + types mortos de locação no front (`delivery_address`/`daily_rate`/`dias_locacao`/
  `has_return_date`/`locacoes_ativas` — casa com o sweep já mapeado).
- **JSON do `show()`** (`ServiceOrderController`): parar de retornar `daily_rate`,
  `dias_locacao`, `expected_return_date`, `delivery_address` (e o drawer parar de
  consumir). Colunas no DB ficam (dado histórico); payload/UI não.
- **Sweep mecânico no PR**: `grep -ri "locac\|cacamba\|diaria" resources/js Modules/OficinaAuto/Http Modules/OficinaAuto/Database/Seeders` —
  todo hit user-facing restante ou morre neste PR ou é listado no PR com justificativa.

### 6. TRAVA DE REGRESSÃO (pra [W] nunca mais descobrir isso na tela)
Estender o `dominio-gate` existente (`scripts/domain-dict-guard.mjs` + `memory/dominio/oficina-auto.md`):
termos PROIBIDOS user-facing no módulo = `locação|locacao|caçamba|cacamba` em strings de
UI (`resources/js/Pages/OficinaAuto/**`, labels de seeder/migration novos). Baseline dos
residuais Tier 0 (keys FSM) permitida; QUALQUER ocorrência nova = CI vermelho.

## Teste que decide o merge do PR-1 (não snapshot — o FIO)
E2E (ou Pest feature) que faz o caminho do critério de pronto: cria OS via store() →
asserta que nasceu em `recepcao` de `oficina_mecanica_os` → executa cada transição até
`entregue` com um usuário SÓ com permissões `oficinaauto.service_order.*` (sem role
mecanico/gerente) → asserta 200 em cada passo e zero string "locação"/"caçamba" nos
payloads de gate/actions.

---

# PR-2 · PORT DO VISUAL — o drawer/Show da OS no nível do protótipo aprovado

> Feedback [W] 2026-06-10 sobre a tela live: "parece uma pintura na mão, não a obra de
> arte prometida". A tela em prod é o esqueleto funcional; o design aprovado (nota 9.5,
> tela-padrão do DS) existe no protótipo Cowork e NUNCA foi portado. Este PR fecha isso.

## Gabarito (baixe via curl — referência de COMPOSIÇÃO, não de cor crua)
- Kanban + drawer OS: 
  `curl -s "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/oficina-page.jsx?t=82404fe7293b537d327420475358f3cbe7ef951d9cb996f07d16a6d44b67fe74.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781105820.fp&direct=1"`
  `curl -s "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/oficina-page.css?t=d17708475a4a524c714bb96a609a3fdd0512f2272ca4c23361c1bdcdfc900578.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781105821.fp&direct=1"`
- OS documento-vivo (check-in → DVI → gate → fiscal):
  `curl -s "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/oficina-os-page.jsx?t=c9a806ac86e315eb0313ad7a20d2325c191b0f46747af63ffc8e99ba4eca9606.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781105822.fp&direct=1"`
  `curl -s "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/oficina-os-page.css?t=5dc2682b71f678151c28fc0ff9a38a8839221d1cd87364981bb8e1180f7ec132.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1781105822.fp&direct=1"`
- URLs valem ~1h. Se expirarem, [W] pede regeneração ao [CC].

## Regra de port (lição registrada — NÃO violar)
**Portar = conformar a composição/hierarquia ao gabarito usando os TOKENS DO REPO**
(`inertia.css @theme` / `cockpit.css` — primary roxo 295, success/warning/destructive,
muted/card/border). **NUNCA copiar cores cruas do CSS do protótipo** (ele tem hex/oklch
de shell que não passam nos gates `ui:lint` R1 / `conformance-gate` / `foundation-guard`).
O que se transporta: estrutura, hierarquia, espaçamento, pesos, ordem das seções,
estados — não valores crus.

## Alvos, em ordem de dor (os prints do [W] são o 1º e o 2º)
0. **`producao-oficina` (kanban do menu)** — além da limpeza de domínio do item 5 do PR-1:
   visual fora da âncora (azul cru em links/badges/barras, coluna bege, mistura de accents).
   Conformar ao padrão do gabarito: roxo canon no chrome, tokens semânticos nos status,
   cards no padrão (placa forte · cliente · chips DVI · borda SLA · avatar mecânico).
1. **`ServiceOrderRichSheet` (drawer da OS)** — hoje: lista chapada de seções iguais.
   Gabarito manda: **hero fixo** no topo (nº OS + status + veículo/placa + cliente, valor
   total em destaque mono tabular) → corpo em hierarquia de 3 camadas (Fotos/Laudo ·
   Peças & Mão de obra · Checklist de etapa · Pipeline · Linha do tempo) com títulos de
   seção firmes, KV empilhado denso, checklist de gate como cartão de ação único (não
   banner verde gigante) e rodapé de ações fixo.
2. **`ServiceOrders/Show.tsx`** — mesma hierarquia do drawer em página cheia.
3. **`Board.tsx` (kanban)** — cards no padrão do gabarito (placa forte, cliente, chips DVI,
   borda de SLA, avatar do mecânico) — boa parte já convergiu no #2417; aqui é acabamento.
4. Estados: vazio · preenchido · dark · 1280px (Larissa) — os 4 verificados antes do merge.

## Critério de pronto PR-2
Screenshot do drawer real lado a lado com o gabarito: mesma hierarquia visível em 5s de
olho. `ui:lint`/`conformance-gate`/`stylelint` verdes (ratchet não sobe). Zero regressão
funcional (suite Oficina do PR-1 segue verde).

---

## Git
```
# PR-1
git checkout -b fix/oficina-fio-usavel-adr0265
php artisan test --filter=Oficina
git add -A && git commit -m "fix(oficina): fio usável ponta a ponta — pipeline correto no create, RBAC sem beco, OS órfãs, labels de locação erradicados (ADR 0265)"
git push -u origin fix/oficina-fio-usavel-adr0265
gh pr create --fill --base main

# PR-2 (após PR-1 mergeado, rebase em main)
git checkout main && git pull && git checkout -b feat/oficina-port-visual-drawer-os
# ... port visual conforme acima ...
php artisan test --filter=Oficina && npm run ui:lint
git add -A && git commit -m "feat(oficina): port visual do drawer/Show/Board pro padrão do protótipo aprovado (gabarito Cowork, tokens do repo)"
git push -u origin feat/oficina-port-visual-drawer-os
gh pr create --fill --base main
```

## Fora de escopo (não abrir frente)
- `vehicle_type='Cacamba_avulsa'` (VWR-4D67) = dado de cadastro, corrigir no CRUD.
- Qualquer redesign de tela. O protótipo Cowork está fechado; isto é só destravar o fio.

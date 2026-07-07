---
page: /financeiro/unificado
component: resources/js/Pages/Financeiro/Unificado/Index.tsx
owner: wagner
status: live
last_validated: "2026-06-16"
parent_module: Financeiro
states: [default, dark]  # gate L2 — empty/loading podados (render == default, md5 #3288) + error removido (toast não determinístico, md5 #3290) · sync com tests/Browser/visreg-states.json
parent_capterra: memory/requisitos/Financeiro/CAPTERRA-INVENTARIO.md
related_adrs: [93, 94]
related_us: [US-FIN-013, US-FIN-020, US-FIN-021, US-FIN-027, US-FIN-029, US-FIN-031, US-FIN-050-anexos, US-FIN-055-aprovacao]
related_prototype: prototipo-ui/cowork/financeiro-page.jsx (design real da Visão Unificada; corrigido 2026-07-06 — antes apontava pro shell oimpresso.com.html, âncora podre pega pelo Wagner)
bundle_source: financeiro-page.jsx
canon_method: Bundle copy CSS 9054 LOC inteiro (regra Tier 0 feedback-cowork-bundle-aplicar-inteiro) — Ondas 12-21
runbook: memory/requisitos/Financeiro/RUNBOOK-unificado.md
tier: A
charter_version: 18
---

# Page Charter — /financeiro/unificado

> **Status:** F3 entregue (PR #349). Charter retroativo (sessão 2026-05-09 audit) — sem `Index.charter.md` original, divergências do ADR ui/0002 documentadas abaixo.
> Persona: **Eliana [E]** — financeiro escritório, densidade alta, atalhos teclado.
> **v17 (2026-07-06):** US-FIN-031 ENTREGUE — bulk actions completas (ver Goals). Endpoint genérico `POST /unificado/bulk` + footer bulk com 5 ações. Non-Goal de cancelamento **emendado** (cancelar em lote existe via rota dedicada, append-only — não é estorno).
> **v16 (2026-06-16):** Tribunal Onda 2 ([W] aprovou Onda 2 pra produção) — drawer/lista **lideram com a conclusão** (ver Goals: veredito no topo · "vs média" cross-sectional · selo→dado · FSM-resumo · acento de ação na linha · ficha sem caixa). Non-Goal de comparação **emendado** (cross-sectional ≠ delta_pct temporal). Vetos Larissa preservados (ícones coloridos das lentes + tipografia do valor).
> **v15 (2026-06-10):** drawer 3 camadas F2 (ver Goals — hero fixo + lentes + Lente Fiscal).
> **v14 (2026-06-10):** US-FIN-029 ENTREGUE — header "3 lentes" (ver Goals). Pacote F2 aprovado [W] 2026-06-10 ("aprovado", sessão Cowork).
> **v10 (2026-05-31):** integrado o feedback de [W] da sessão Cowork (handoff de design) — anti-patterns de densidade do header + direção "3 lentes" registrada como intenção **pendente** (ainda **não** aplicada ao live; ver Backlog US-FIN-029). Origem: charter Cowork `Financeiro.charter.md` v1 (superada por este v10 canônico).

---

## Mission (1 frase)

Tela única de **fluxo financeiro do mês** que mistura **Pagar / Pagas / Receber / Recebidas** em uma view só, evitando que Eliana abra 4 menus diferentes pra responder "quanto entra/sai esta semana".

---

## Goals — Features (faz)

- **Bulk actions completas (US-FIN-031)** (2026-07-06, charter v17): endpoint genérico **`POST /unificado/bulk`** `{action, ids[], payload{}}` com 5 ações — **baixar** (quitação total instantânea, mesmos efeitos da baixa rápida legacy; substitui o loop de N POSTs por 1 request), **categoria** (Sheet Onda 15 migrado pro endpoint; rota `bulk-update-categoria` preservada back-compat), **plano_conta** (Sheet novo, mesmo padrão), **cancelar** (Sheet de confirmação DESTRUTIVA com "Você está cancelando N títulos totalizando R$ X" ANTES de aplicar — REGRA MESTRE valor; `status='cancelado'` append-only, quitado é pulado) e **exportar_csv** (download da seleção; BOM UTF-8 + `;` pt-BR). Tier 0: ownership de **TODOS** os ids validada antes de qualquer escrita (1 id alheio = 422 fail-closed), limite **500**/chamada, audit trail `Activity bulk_*` com `{action, ids, count, total}`. Cobertura `UnificadoBulkGuardTest` (UC-F04, 6 GUARDs — cross-tenant · soma por 2 caminhos · append-only · plano cross-tenant · limite · export não-muta).

- **Tribunal Onda 2 — drawer/lista lideram com a conclusão** (2026-06-16, charter v16, método "O Tribunal" · [W] aprovou Onda 2 pra produção): 6 mudanças de **mérito** (não-bug), cor só por token semântico, vetos Larissa intactos.
  - **#1 Veredito no topo do drawer** (cadeira Victor): 1ª coisa do corpo (acima de Vínculos), 1 linha + sub, derivada 100% do estado do título (`status`/`nfe_numero`/`vencimento`) — sem mock. Tons `pos/warn/neg/muted` (success/warning/destructive/muted) com ícone redondo preenchido. `vencimento` é ISO → contagem de dias confiável.
  - **#2 "vs média" no valor** (cadeira Tufte): linha neutra sob o hero comparando o título com a **média dos pares** (mesma categoria + mesmo kind, valor>0) do conjunto carregado client-side. **Cross-sectional** (≠ delta_pct temporal). Anti-slop: só renderiza com **≥2 pares reais**; tom neutro (seta + %), sem valência. Reusa `lancamentos` (mesma fonte do `FinAnomalyDetector`).
  - **#3 Selo→dado** (Tufte/Rams): tira o `status` de sucesso redundante das 3 lentes (Conciliação `100% match`→`null` · Fiscal `NF vinculada`→`null` · Cobrança `encerrada`→`null`); mantém os que carregam info nova (`aguardando`/`sem NF`/`em atraso`).
  - **#4 Item liquidado: FSM 1-linha** (Victor/Rams): título liquidado não gasta ~80px com 4 etapas todas marcadas — vira `✓ Lançado → Liquidado · 4 etapas`. Aberto mantém o stepper completo. (Suffix "no prazo/atraso" omitido: `liquidacao` chega como "DD MMM", sem data parseável; vira proposta se o shape expor a data ISO da baixa.)
  - **#5 Acento de ação na linha** (Victor/Saarinen): `box-shadow: inset 3px` na 1ª `<td>` — vencido = destructive, vencendo (não pago) = warning, resto = nada. Eliana acha o que pede ação sem abrir.
  - **#6 Ficha de campos sem caixa** (Reichenstein · [W] "tirar cor, manter fios" 2026-06-16): `.fin-kv-card` perde fundo lavanda + borda accent + radius; ganha **fios neutros** topo/baixo (`var(--border-2)`), pra não flutuar em branco. Tokens only → conformance-gate intacto (215=215).

- **Header "3 lentes" (US-FIN-029)** (2026-06-10, charter v14, direção [W] 2026-05-31 aprovada / F2 [W] 2026-06-10): segmented **Caixa · A receber · A pagar** no header (pattern pill do Fluxo) é a **camada 1** do filtro grosso — `?lente=caixa|receber|pagar`, clamp default `caixa`, deep-link funciona. Chips lifecycle **refinam DENTRO da lente**; chip incompatível com a lente não renderiza. **KPI-click seta a lente** correspondente (drill-down ADR ui/0002 preservado: "Recebido"→lente receber+chip re, "A pagar"→lente pagar+chip ap, hero→caixa+ar/ap). Backend: `parseFilters()['lente']` + interseção lifecycle∩lente (interseção vazia = lente inteira, defense-in-depth). O menu `···` e o topnav compartilhado **já estavam entregues** via `FinanceiroSubNav` (`_shared/`, ADR 0180 Fase 5, PR #1365) — gatilho US-FIN-TOPNAV-COMPONENT já satisfeito antes desta US. MWART: `memory/requisitos/Financeiro/unificado-3-lentes-visual-comparison.md`. Cobertura `UnificadoLentesGuardTest` (clamp · lente→estados · inválida→caixa · Tier 0 · GET sem mutação).

- **Drawer 3 camadas (F2 PR-3)** (2026-06-10, charter v15, padrão F2-aprovado [W] 2026-06-10): hero do título virou **Camada 1 fixa fora do scroll** (header → hero → tabs → corpo) — label de estado uppercase (destructive se atrasado) · valor mono tabular grande com prefixo/centavos pequenos · chip + vencimento com urgência em palavras à direita · **FSM compacto** 4 etapas (Lançado→Conferido→Conciliado→Liquidado). Seções viram **lentes** (ícone primary/10 + título + chip de status): Conciliação (conciliada = box discreto bg muted + check, não banda verde) e **Lente Fiscal** nova (ISS retido 5% · No DAS do mês ≈6%, estimativa Simples Nacional + link pra sub-tela Impostos & obrigações). KV empilhado do grid 2-col (Onda 18) validado e mantido. 2 `white` crus do bundle tokenizados (`--accent-fg`). Referência F1: `financeiro-page.jsx` Drawer do protótipo Cowork.

- **Diálogo de baixa + coluna Conta** (2026-06-03, charter v12, pedido [W]): o botão **Recebi/Paguei** agora abre **`FinBaixaSheet`** pra escolher **valor** (suporta baixa **parcial**), **conta bancária** de destino, **forma de pagamento** e **plano de contas** — antes era baixa instantânea (1ª conta, valor cheio, meio fixo). Backend `baixar()` aceita os campos (valida `conta_bancaria_id` no business — anti cross-tenant — e enum do meio) com defaults legacy preservados (body vazio = baixa rápida; espaço/bulk seguem instantâneos). Nova **coluna "Conta"** na tabela. `shapeTitulo` expõe `valor_aberto`. Cobertura `UnificadoBaixaDialogGuardTest` (5 GUARDs: valor_aberto, escolhas, parcial, cross-tenant, legacy).

- **Forma de pagamento no lançamento** (2026-06-03, charter v11, pedido [W]): coluna **Forma** na tabela + campo no drawer (aba Detalhes) + edição (FinEditPanel) e criação (TituloCreateSheet). Nova coluna `fin_titulos.forma_pagamento` (enum espelha `fin_titulo_baixas.meio_pagamento`). Regra de exibição: a forma **realizada** (`baixa.meio_pagamento`) tem prioridade e é **read-only** (espelha `valor_mutavel` / ADR fin-tech/0002); senão a **prevista** (`titulo.forma_pagamento`), editável em aberto; senão "—". Helper compartilhado `_lib/forma-pagamento.ts` (rótulo PT-BR + ícone). Cobertura `UnificadoFormaPagamentoGuardTest` (5 GUARDs). Títulos criados por cobrança paga (`OnCobrancaPagaCreateFinanceiroTitulo`) já exibem a forma realizada via baixa.

- **Paridade filtros de data WR** (2026-06-03, US-FIN-030): seletor de **campo de data** (Vencimento default · Emissão · Pagamento · Competência) + **intervalo explícito** `data_inicio`/`data_fim` na toolbar, espelhando o WR Comercial. Backend `parseFilters()` + `aplicarFiltroData()` (vencimento/emissao via coluna; pagamento via `baixas.data_baixa`; competencia via `competencia_mes` YYYY-MM); intervalo sobrepõe o período preset. **O `data_campo` aplica na tabela E nos cards de KPI** (`kpisCore` segue o mesmo campo — totais consistentes com o grid filtrado; `recebido`/`pago` por `data_baixa` quando campo=pagamento, senão por título que casa o campo). Cobertura `UnificadoDataCampoTest`. Pendente: filtros 'Nota Fiscal'/'Vendas' do WR exigem link título→transaction (`origem_id`).

- **Cancelado não soma + filtro Arquivados + campos paridade WR no drawer** (2026-06-04, charter v13, US-FIN-030 / PRs #2207+#2211): títulos `cancelado` ficam **escondidos** da lista por padrão e **não somam** nos KPIs (`kpisCore` exclui baixas de cancelados — pareia com a lista); filtro **Arquivados** (`?arquivados=1`) mostra **só** eles. Drawer Detalhes expõe os campos de paridade WR (Número · Parcela · Pedido · Condição de pagamento · Documento · Desconto · Juros sempre visíveis · Emissão · Competência · Vencimento · Data de pagamento · Valor em aberto). Cobertura **`UnificadoCanceladoArquivadosKpiTest`** (5 GUARDs: C1 cancelado-não-soma · A1/A2 Arquivados · D1 KPI-segue-`data_campo` · S1 shape paridade WR). Pendente Fase 2-data (US-FIN-051): data+hora completas + Documento/NF/conta/plano reais exigem re-import (colunas truncadas/redactadas na migração WR).

- **PR C — GUARD + RUNBOOK** (2026-05-25, charter v9, US-FIN-027 parcial + G1/G3 auditoria):
  - **`UnificadoPlanoContaGuardTest`** — 7 GUARDs Tier 0 anti-regressão pra `plano_conta_id`: prop Inertia `planosConta`, shape 3 campos (`plano_conta_id` + `_codigo` + `_nome`), eager-load preserva (anti N+1), Update persiste, coerência tipo↔plano (Edit), Store persiste, cross-tenant rejeitado em ambos. Cada Δ = CI quebra.
  - **`RUNBOOK-unificado.md`** — doc canon Cockpit (ADR 0039) 12 seções (quando usar, permissões, rotas, componentes, filtros, atalhos, edit/insert, plano de contas, multi-tenant, pegadinhas, troubleshoot, refs).
  - **Frontmatter `runbook:` linkado** — descoberta automática via tooling MCP.

- **Onda 25 — Insert manual inline** (2026-05-25, charter v8, US-FIN-021 completa):
  - **TituloCreateSheet** reusa `PlanoContaCombobox` da Onda 24. Drawer abre via DropdownMenu existente "+ Novo título" → "Novo recebimento" (verde 145) ou "Novo pagamento" (rose 25), com `tipo` pré-fixado e não editável (Opção A do design: usuário escolhe tipo ANTES do form).
  - **POST `/financeiro/unificado`** → `UnificadoController::store(StoreTituloRequest)`. Numero sequencial business-isolado (`R-NNNNN` ou `P-NNNNN`) gerado com `lockForUpdate` (R-FIN-002 idempotência).
  - **Substitui stub `/unificado/novo`** — remove Non-Goal #1 do charter v6 ("Form unificado de novo lançamento inline").
  - **Defesa em profundidade**: `StoreTituloRequest::assertPlanoCoerente()` revalida tipo↔plano_conta.tipo no backend (anti tampering — mesmo padrão da Onda 24).
  - **Multi-tenant Tier 0** (ADR 0093): `business_id` da session, nunca do payload. Pest cross-tenant rejeita 422.
  - **origem='manual'**, `valor_aberto=valor_total`, `status='aberto'`, `created_by=user.id`, `competencia_mes=Y-m`.

- **Onda 24 — Plano de Contas no Edit** (2026-05-25, charter v7, US-FIN-021 parcial):
  - **TituloEditSheet** ganha campo `plano_conta_id` via `PlanoContaCombobox` reusável (searchable, hierárquico DCASP indentado por `nivel`).
  - **Combobox filtra por `kind` do título**: `receivable` → tipo IN (receita, ativo); `payable` → tipo IN (despesa, custo, passivo). Patrimônio fora (não é título corrente).
  - **Backend defesa em profundidade**: `UpdateTituloRequest::assertPlanoCoerente()` revalida coerência tipo↔plano (anti tampering) + `Rule::exists` scope business + ativo + aceita_lancamento.
  - **shapeTitulo expõe** `plano_conta_id`, `plano_conta_codigo`, `plano_conta_nome` (eager-load `planoConta:id,codigo,nome,tipo`).
  - **DRE consequência**: títulos editados passam a alimentar Dre/Index diretamente sem precisar de `BackfillPlanoContaCommand` (que continua cobrindo auto-criação via Observer).

- **Ondas 12-21 KB CANON CSS BUNDLE COMPLETO** (2026-05-20, charter v6):
  - **Bundle copy CSS 9054 LOC** — `resources/css/cowork-canon-financeiro-bundle.css` importado inteiro escopado em `.fin-cowork` (regra Tier 0 `feedback-cowork-bundle-aplicar-inteiro`). Substitui cherry-pick fragmentado.
  - **Markup canon EXATO**: `os-page-h fin-page-h` (header) + `os-page-h-l/r` (left/right) + `fin-stat fin-stat-hero` (KPI hero warm dark hue 80) + `os-btn ghost` (botões transparentes) + `os-drawer-head` (drawer header) + `fin-footer-tips sticky` (footer atalhos+summary).
  - **Plano de Contas filtro** (Onda 12.7) — substitui Categorias livres no dropdown filtro. Backend: `where('plano_conta_id', $id)` com fallback OR categoria_id. Hierárquico BR (47 entries Receita Federal/DCASP seedados via `PlanoContasBrSeeder`).
  - **Filtros lifecycle default ON** (Onda 12.5) — pills coloridos hue semântico (verde 145 receber/recebidas · rosa 25 a pagar · azul 240 pagas). Toggle "Só atrasados" classe distinta `fin-filter-toggle` (não `fin-filter-cb`).
  - **Densidade compact default** (Onda 12.6) — remove modo "spacious" (não usado). Apenas 2 modos: compacto/médio.
  - **Footer sticky bottom** colado na viewport com summary numérica (`N lançamentos · entrada R$ X · saída R$ Y`) + atalhos teclado.
  - **KPI strip full width** (Onda 12.7) — grid 5 cards 100% container.
  - **Hue accent custom via localStorage** — `cockpit.theme.accentHue` default 220 azul canon (Wagner pode mudar via picker UI).

- **Onda 20 #50 — Anexos NF/comprovante** (2026-05-20, charter v6):
  - **Botão `📎 Anexar`** no drawer dispara file input invisible → POST `/financeiro/unificado/{id}/anexos` (multipart 10MB max)
  - **Storage local privado** `storage/app/private/financeiro/anexos/{biz_id}/{titulo_id}/` (não public)
  - **Idempotência SHA-256** — não duplica upload do mesmo arquivo (toast warning)
  - **Aceita** .pdf, .png, .jpg, .jpeg, .xml (NF eletrônica)
  - **Backend**: `TituloAnexo` model + 3 endpoints (listarAnexos GET + anexar POST + removerAnexo DELETE) + tabela `fin_titulo_anexos`

- **Onda 21 #55 — Workflow aprovação pagamento** (2026-05-20, charter v6):
  - **Visível só pra título kind=payable + status=aberto/atrasado/vencendo** (não aplica receivable)
  - **3 estados condicionais**:
    - `null` (default — sem fluxo): botão "⏳ Solicitar aprovação" → POST `/solicitar-aprovacao`
    - `'pendente'`: pill amber + botões "✓ Aprovar" / "✗ Rejeitar (motivo)"
    - `'aprovado'`: pill emerald "liberado pra pagamento"
    - `'rejeitado'`: pill rose "bloqueado pra pagamento"
  - **Backend**: 3 endpoints + 4 campos novos em `fin_titulos` (aprovacao_status enum + aprovado_by + aprovado_at + aprovacao_motivo)
  - **Backward compat**: títulos antigos com `aprovacao_status=NULL` seguem fluxo direto (sem aprovação obrigatória)

- **Onda Edit** (2026-05-18, charter v5):
  - **TituloEditSheet** — Sheet drawer inline edita campos seguros do título: `cliente_descricao` (texto livre + cross-links `#V-/#OS-/#PC-`), `observacoes`, `categoria_id`, `plano_conta_id` (Onda 24), `vencimento`. `valor_total` mutável SOMENTE se `status` aberto/parcial (ADR fin-tech/0002 imutabilidade pós-baixa). PUT `/financeiro/unificado/{id}` via `useForm` Inertia. Wire-up no botão "Editar" do drawer de detalhe.
  - **Conferido per-user DB** — `FinConferidoToggle` migrado de localStorage para `conferido_by` (FK users.id) + `conferido_at` (timestamp). Substitui Onda 5 R1 storage. Eliana confere ≠ Wagner confere → audit per-user. Routes POST/DELETE `/unificado/{id}/conferir`.
  - **Cross-links auto-pop** — `TituloAutoService` sintetiza `#V-{transaction_id}` (vendas) e `#PC-{transaction_id}` (compras) em `cliente_descricao` no `afterCreate`. FinCrossLinkify renderiza pills clicáveis.

- **Onda 7 KB-9.75 R3 Output + Cross-link** (2026-05-18):
  - **FinCrossLinkify** — regex parser detecta `#V-` `#BL-` `#PC-` `#OS-` `#R-` `#P-` no `desc` do row → pills coloridas clicáveis que `router.visit` para o módulo apropriado (Sells / Boletos / Compras / Repair / Contas-Receber legacy / Contas-Pagar legacy). Fecha o loop "do Financeiro pra origem do lançamento".
  - **FinChecklistFechamento** — trilha 12 passos do fechamento mensal agrupada em 4 (Conciliação / Revisão / Exportação / Comunicação). Persistido em `localStorage[oimpresso.financeiro.fechamento.YYYY-MM]`. Progress bar + timestamp por passo. Trigger ☑ Fechamento no header da página.
- **Onda 6 KB-9.75 R2 IA** (2026-05-18):
  - **FinAnomalyDetector** — detecta valor outlier vs média histórica da contraparte (threshold ≥25%, severity high/medium/low). Mostrado no drawer quando aplicável. Pure compute, sem LLM.
  - **FinPartyHistory** — stats da contraparte no drawer (count, total, média, on-time%, categoria top, 5 recentes). Detecta isNew (1 lançamento) vs isRecurrent (≥3). Pure compute.
  - **FinMonthDigest** — section colapsável acima da tabela com 4 cards (Recebido / Pago / Saldo do mês / Atrasados) + top contraparte in/out. Pure compute, "Eliana 5min sexta" digest.
- **Onda 5 KB-9.75 R1 Curadoria** (2026-05-18):
  - **Conferido toggle** por Eliana (localStorage `oimpresso.financeiro.conferido`) — pill grande no drawer + badge ✓ silent na linha
  - **Comentários inline** thread Eliana ↔ Wagner ↔ Bruna (localStorage `oimpresso.financeiro.comments`) — textarea no drawer + badge 💬N silent na linha
  - **Audit trail determinístico** (5 kinds: create / categorize / edit / concil / alert) derivado do row sem persistência — exibido no drawer
  - **Frescor pill** 6 estados (paid · overdue · today · warning · soon · fresh) derivado de `vencimento`+`liquidacao` — compact ao lado do StatusPill na linha e full no drawer
- 5 KPI cards: Saldo previsto · Recebido · A receber · Pago · A pagar (com qtd de baixas/títulos)
- KPI cards **clicáveis** — cada um filtra a tabela pra tab correspondente (drill-down ADR ui/0002)
- Filter chips: Todas, Aberto, Receber, Pagar, Recebidas, Pagas, Atraso
- Dropdowns: Conta bancária, Categoria
- Filtro de período por querystring (default: mês corrente)
- Busca textual (atalho `/`)
- Tabela única com setas direcionais ↑↓ (entrada/saída), valor com sinal, status pill colorido
- Drawer detalhe (Sheet) ao clicar linha
- 1-clique baixa: botão "✓ Recebi" / "✓ Paguei" inline na linha (R-FIN-007)
- CmdK palette (`Cmd+K` ou `Ctrl+K`) — atalho navegação
- Densidade configurável: compact / comfortable / spacious (persiste em URL)
- Empty state com CTA "+ Adicionar primeiro lançamento" → /unificado/novo
- Header dinâmico: período PT-BR + nome do business logado (sem hardcode)
- Multi-tenant: query scoped por `business_id` (Tier 0 ADR 0093)

---

## Non-Goals — Features (NÃO faz)

> Anti-alucinação. Cada item vira Pest GUARD test (Non-Goal violado = CI quebra).

- ❌ ~~Form unificado de novo lançamento inline~~ — **RESOLVIDO Onda 25** (TituloCreateSheet via DropdownMenu "+ Novo título")
- ❌ Cancelamento/estorno — vai por rotas dedicadas (`status='cancelado'` via append-only, não delete). ⚠️ **Emendado v17 (US-FIN-031):** o **cancelar em lote** EXISTE via rota dedicada `POST /unificado/bulk action=cancelar` (append-only, quitado pulado, confirmação destrutiva com total) — o que segue Non-Goal é **estorno** de título quitado (desfazer baixa), que continua fora desta tela
- ❌ Edição de `tipo`, `origem`, `origem_id`, `status`, `emissao` — imutáveis (anti-corrupção contábil; alterar requer cancelar+criar novo). Onda Edit edita só campos seguros + valor pré-baixa.
- ❌ Pagination explícita (default `limit(200)` no controller) — paginar quando 1000+ títulos virar dor
- ❌ Aging buckets <30 / 30-60 / 60-90 / 90+ — ADR ui/0002 previa, F1 simplifica pra status `atrasado` único
- ❌ Comparação **temporal** `+12% vs mês anterior` (delta_pct por KPI) — ADR ui/0002 previa; F1 não calcula; segue em **US-FIN-023**. ⚠️ **≠ da comparação cross-sectional "vs média da categoria"** (Tribunal Onda 2 #2, charter v16) que **É feita** no hero do drawer — esta compara o título com a média dos pares (mesma categoria+kind) do conjunto carregado, anti-slop ≥2 pares, tom neutro. Distinção registrada por decisão [W] 2026-06-16.
- ~~❌ Combobox cliente/contraparte com autocomplete~~ **DONE charter v18 (2026-07-07)** — `ClienteCombobox` (PR J, US-FIN-024) ligado no campo contraparte do `TituloCreateSheet` (fila P10 do inventário por região; sugestão de valor por histórico preservada via `onBlurCapture`)
- ❌ Mobile responsive (cards stack 2×2) — F1 só desktop ≥1024px (Eliana é desktop)
- ❌ Export PDF/Excel — Onda 4
- ❌ KPI configurável por user (esconder card) — Onda 4
- ❌ Substituir telas legacy `/financeiro/contas-receber` e `/contas-pagar` — coexistem (decisão ADR 0002 em aberto)

---

## UX Targets

- p95 first-paint < 600ms (controller agrega in-process; sem N+1 nas Eloquent relations já eager-loaded)
- Cabe em monitor 1280px sem scroll horizontal (Eliana está em desktop)
- AppShellV2 layout (Cockpit ADR 0039)
- 0 erros JS console
- Atalho `Cmd+K` abre palette
- Atalho `J/K` navega linhas (placeholder, não implementado em F1)
- Atalho `/` foca busca

---

## UX Anti-patterns

- ❌ Modal nested — só Sheet (drawer) lateral pra detalhe
- ❌ Toast/snackbar — flash session do Laravel (1-clique baixa volta com `back()`)
- ❌ Loading skeleton — props vêm do controller, sem async no client
- ❌ Cores berrantes — paleta restrita (emerald entrada / rose saída / amber vencendo / stone neutro)
- ❌ Animações decorativas — só transições em hover/drawer

**Densidade do header ([W] 2026-05-31, sessão Cowork — REPROVADO):**
- ❌ **Fileira de ~7 botões inline no header** (Resumir · Fechamento · Apresentar · Conciliar · Plano de contas · Exportar · Novo) — [W]: *"está muito apertado"*; esmaga o título abaixo de ~1100px. Direção aprovada = 3 lentes + menu `···` (ver Backlog US-FIN-029).
- ❌ **Sub-páginas (Conciliação, Plano de contas, DRE, Fluxo) como botões no header** — [W] quer no **sidebar** (`FinSubNav`), não no header.
- ❌ **Mexer na estrutura sem ler o domínio primeiro** — [W]: *"não foi fiel ao projeto"*. Ler este charter + `RUNBOOK-unificado.md` ANTES de tocar a tela.
- ❌ **Apresentar profundidade mock como pronta** — conciliação/cobrança/fiscal ainda são casca; não declarar "feito" o que é stub.

---

## Automation Hooks

- Endpoint `/financeiro/unificado` chama `UnificadoController::index`
- Mock fallback **NÃO existe** — biz sem `Titulo` cadastrado renderiza tabela vazia (empty state com CTA)
- Multi-tenant: `Titulo::where('business_id', $businessId)` em todas as queries
- 1-clique baixa: POST `/unificado/{id}/baixar` chama método `baixar()` que aplica `TituloBaixaService` (R-FIN-002 audit)
- Stub `/unificado/novo` redireciona pra `/contas-receber` ou `/contas-pagar` (não implementa form unificado ainda)
- **Edit Sheet** (Onda Edit 2026-05-18): PUT `/unificado/{id}` → `UnificadoController::update(UpdateTituloRequest)` → guard `assertValorMutavel` se status quitado/cancelado
- **Conferir per-user**: POST/DELETE `/unificado/{id}/conferir` → `conferido_by` (FK users.id) + `conferido_at` timestamp
- **Bulk actions (US-FIN-031)**: POST `/unificado/bulk` → `UnificadoController::bulk` — `{action: baixar|categoria|plano_conta|cancelar|exportar_csv, ids[≤500], payload{}}`; ownership Tier 0 de todos os ids (422 fail-closed) + audit `Activity bulk_*`

---

## Divergências registradas vs ADR ui/0002

> ADR ui/0002 (accepted 2026-04-24) propôs shape de KPIs/tabela diferente. F1 implementação diverge.

| Item ADR ui/0002 | F1 implementação | Justificativa |
|---|---|---|
| KPIs: `receber_aberto + pagar_aberto + recebido_mes + pago_mes` (4) | `saldo_previsto + recebido + a_receber + pago + a_pagar` (5) | Wagner pediu Saldo Previsto destacado no protótipo Cowork 2026-05-09 |
| Aging vencidos por bucket | Status `atrasado` simples | F1 enxuto; aging vira US futura |
| Pagination 25/100 | `limit(200)` fixo | Volume típico ~50-200/mês; paginar quando virar dor |
| Combobox cliente | Sem combobox | F1 simplifica; autocomplete é US futura |
| Mobile responsive | Desktop only | Eliana persona desktop |
| `delta_pct` (+12% vs mês anterior) | Não calcula | F1 simplifica; comparativo é US futura |

**Apend a [ADR ui/0002](../../../../../memory/requisitos/Financeiro/adr/ui/0002-dashboard-unificado-4-estados.md) ou nova ADR superseding** quando próxima sessão tocar — formaliza divergência.

---

## Backlog futuro (US explícitas)

- ~~**US-FIN-021** — Form unificado inline (modal/sheet) — substitui stub `/unificado/novo`~~ **DONE Onda 25 (2026-05-25)**
- ~~**US-FIN-022** — Aging buckets <30/30-60/60-90/90+ + filtro~~ **REVERTIDO 2026-06-29** — [W] aprovou screenshot do protótipo Cowork que NÃO tem faixas de aging na linha de filtros ("isso eu não quero"). Retorna ao F1 enxuto original (status `atrasado` único, ver linhas 147/204). Chips removidos da UI; backend `agingBreakdown` segue computado (inócuo).
- **US-FIN-023** — Comparação `+X% vs mês anterior` por KPI (delta_pct)
- **US-FIN-024** — Combobox cliente/contraparte com autocomplete
- **US-FIN-025** — Mobile responsive (cards stack 2×2 + lista)
- **US-FIN-026** — Pagination 25/100 quando volume passar 500 títulos
- **US-FIN-027** — Pest GUARD: Tier 0 isolation + KPIs corretos + filtro tab querystring
- **US-FIN-028** — visual-comparison.md retroativo (ADR ui/0114 / mwart-comparative V4)
- ~~**US-FIN-029** — Header "3 lentes" (Caixa · A receber · A pagar, segmented) dirigindo o filtro + menu `···` + sub-páginas no sidebar~~ **DONE charter v14 (2026-06-10)** — lentes entregues nesta data; `···`/topnav compartilhado já tinham sido entregues via FinanceiroSubNav (ADR 0180 Fase 5). MWART em `memory/requisitos/Financeiro/unificado-3-lentes-visual-comparison.md`.

---

## Refs ADR

- **`related_adrs` (frontmatter):** `0093` (multi-tenant Tier 0) · `0094` (Constituição V2).
- **ADRs namespaced** (fora do `related_adrs` — o schema canônico `scripts/memory-schemas/charter.schema.json` só aceita ADR top-level integer/slug): [arq/0005 — Financeiro vs Accounting paralelo](../../../../../memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md) · [ui/0002 — dashboard unificado 4 estados](../../../../../memory/requisitos/Financeiro/adr/ui/0002-dashboard-unificado-4-estados.md) · ui/0114 — gate visual F1.5 / mwart-comparative.

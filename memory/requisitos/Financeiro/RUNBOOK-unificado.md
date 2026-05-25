# RUNBOOK — `/financeiro/unificado` (Visão Unificada Cockpit V2)

> **Status:** Live (charter v8 · 2026-05-25 · pós Ondas 24+25)
> **Persona alvo:** Eliana [E] (financeiro escritório, densidade alta, atalhos teclado)
> **Mission:** Tela única de fluxo financeiro do mês (Pagar / Pagas / Receber / Recebidas) — evita Eliana abrir 4 menus pra responder "quanto entra/sai esta semana"

---

## 1. Quando usar

- **Eliana abre toda manhã** pra ver o que vence hoje + pendências de aprovação
- **Wagner sexta 5min** vê digest do mês via "Resumir mês"
- **Lançamento manual** de título a receber ou pagar (Onda 25 — sem boleto / sem venda associada)
- **Edit pontual** pra ajustar contraparte, categoria, plano contábil, vencimento, valor pré-baixa
- **Conferência (curadoria)** marca títulos auditados — flag per-user no DB (Onda Edit)
- **Anexar NF / comprovante** ao título (Onda 20)
- **Workflow aprovação pagamento** (Onda 21 — solicitar / aprovar / rejeitar)
- **NÃO** usar pra cancelar/estornar (rota dedicada) nem pra mudar tipo/origem/status (imutáveis ADR fin-tech/0002)

---

## 2. Permissões (Spatie)

| Permission | Concedida pra | Funcionalidades |
|---|---|---|
| `financeiro.dashboard.view` | Eliana + Wagner + Bruna + Roles `admin`, `financeiro` | Tudo da tela (view + edit + create + baixar + conferir + anexar) |
| `financeiro.titulo.aprovar` | Sócio/Gestor (Wagner, Larissa) | Aprovar/Rejeitar pagamento (Onda 21 #55 + US-FIN-028) |

Permission gate aplicado no `__construct` do `UnificadoController`. 403 se faltar.

---

## 3. Rotas (15 endpoints)

| Método | Rota | Action | Quando |
|---|---|---|---|
| GET | `/financeiro/unificado` | `index` | Lista principal (KPIs + tabela + filtros) |
| POST | `/financeiro/unificado` | `store` | **Onda 25** — Insert manual (TituloCreateSheet) |
| PUT | `/financeiro/unificado/{id}` | `update` | Edit Sheet (Onda Edit + Onda 24 plano_conta_id) |
| GET | `/financeiro/unificado/novo` | `novo` | Stub legacy back-compat (60d) — UI agora abre Sheet inline |
| POST | `/financeiro/unificado/{id}/baixar` | `baixar` | 1-clique Recebi/Paguei |
| POST | `/financeiro/unificado/{id}/conferir` | `conferir` | Marca conferido_by + conferido_at |
| DELETE | `/financeiro/unificado/{id}/conferir` | `unconferir` | Desmarca |
| POST | `/financeiro/unificado/bulk-update-categoria` | `bulkUpdateCategoria` | Lote (Onda 15) |
| GET | `/financeiro/unificado/saldo-sparkline` | `saldoSparkline` | Endpoint JSON 30d |
| GET/POST/DELETE | `/financeiro/unificado/{id}/anexos[/{anexoId}]` | anexos CRUD | Onda 20 NF/comprovante |
| POST | `/financeiro/unificado/{id}/solicitar-aprovacao` | `solicitarAprovacao` | Onda 21 workflow |
| POST | `/financeiro/unificado/{id}/aprovar` | `aprovar` | Onda 21 (gate Spatie) |
| POST | `/financeiro/unificado/{id}/rejeitar` | `rejeitar` | Onda 21 (motivo obrigatório) |
| POST | `/financeiro/unificado/ocr-boleto` | `ocrBoleto` | OCR upload (Onda 23 KILLER) |
| GET | `/financeiro/unificado/{tituloId}/{comments,audit}` | comments/audit | Drawer detalhe |

Middleware stack canon: `['web', 'auth', 'language', 'timezone', 'AdminSidebarMenu']` + permission gate no Controller.

---

## 4. Componentes Inertia (page + 17 _components)

**Page:** [`resources/js/Pages/Financeiro/Unificado/Index.tsx`](../../../resources/js/Pages/Financeiro/Unificado/Index.tsx) (charter v8, ~1900 LOC, AppShellV2 layout)

**Componentes filhos** em `_components/`:

| Componente | Onda | Função |
|---|---|---|
| `TituloEditSheet` | Edit + 24 | Drawer Sheet edita campos seguros + plano de contas |
| `TituloCreateSheet` | **25** | Drawer Sheet insert manual (R-NNNNN / P-NNNNN) |
| `PlanoContaCombobox` | **24** | Combobox searchable hierárquico, filtra por kind |
| `FinPillFrescor` | 5 | Pill 6 estados (paid/overdue/today/warning/soon/fresh) |
| `FinConferidoToggle` | Edit | DB-backed conferido_by + conferido_at |
| `FinCommentsThread` | 5 | Thread Eliana ↔ Wagner ↔ Bruna |
| `FinAuditTrail` | 5 | 5 kinds derivados (create/categorize/edit/concil/alert) |
| `FinAnomalyDetector` | 6 R2 | Outlier vs média histórica (threshold ≥25%) |
| `FinPartyHistory` | 6 R2 | Stats contraparte (count/total/média/on-time%) |
| `FinMonthDigest` | 6 R2 | 4 cards digest mensal |
| `FinChecklistFechamento` | 7 | 12 passos fechamento agrupados 4 |
| `FinPresentationMode` | 7b | Modo fullscreen Esc + 1/2/3 |
| `FinCrossLinkify` | 7 R3 | Regex parser `#V-` `#OS-` `#PC-` etc |
| `FinTroubleshooter` | 7b | Diagnóstico bugs UI |
| `FinTranscriptPDF` | 7c | Folha jurídica imprimível |
| `FinOcrBoletoSheet` | 23 | OCR upload OpenAI Vision (KILLER) |
| `FinAnexosPanel` | 20 | NF/comprovante drawer |

---

## 5. Filtros (querystring)

| Param | Valores | Comportamento |
|---|---|---|
| `lifecycle` | CSV `ar,re,ap,pa` (A receber / Recebidas / A pagar / Pagas) | Multi-select OR. Inválido descartado |
| `overdue` | `0` ou `1` | AND multiplicativo "só atrasados" |
| `aprovacao_status` | CSV `sem_workflow,pendente,aprovado,rejeitado` | US-FIN-027 Onda 22 |
| `conta` | CSV ints | Multi-conta bancária (Onda 7) |
| `categoria` | int | Filtra por plano_conta_id OU categoria_id (back-compat Onda 12.7) |
| `busca` | string | Like em cliente_descricao + numero + observacoes |
| `sort` | `vencimento`/`valor`/`status`/`lancamento`/`contraparte` | Whitelist anti SQLi |
| `dir` | `asc`/`desc` | Default asc |
| `page` / `per_page` | int (default 1 / 100) | Pagination Onda 13 |
| `periodo` | `YYYY-MM` ou `YYYY-MM-DD,YYYY-MM-DD` | Default: mês corrente |
| `tab` | `open/rec/pay/received/paid/late` | Back-compat legacy (bookmarks antigos) |

---

## 6. Atalhos teclado

| Tecla | Ação |
|---|---|
| `Cmd+K` / `Ctrl+K` | CmdK palette (navegação rápida) |
| `/` | Foca busca textual |
| `J` / `↓` | Próxima linha |
| `K` / `↑` | Linha anterior |
| `Space` | Baixar (Recebi/Paguei) linha focada |
| `Enter` | Abre drawer detalhe linha focada |
| `B` | Toggle favorito (★ — Onda 7c) |
| `Esc` | Fecha drawer / limpa bulk selection |

**Faltam (auditoria G6):** `N` (Novo título), `R` (Novo receber direto), `P` (Novo pagar direto), ↑↓ no combobox plano (G7 — pattern WAI-ARIA Combobox).

---

## 7. Edit & Insert (Ondas Edit + 24 + 25)

### Edit (PUT `/unificado/{id}`)

Drawer Sheet abre via botão "Editar" no drawer detalhe. Campos editáveis:

| Campo | Sempre editável? | Validação |
|---|---|---|
| `cliente_descricao` | Sim | string max 255 |
| `observacoes` | Sim | string max 2000 |
| `categoria_id` | Sim | exists fin_categorias scoped business |
| `plano_conta_id` | **Sim (Onda 24)** | exists fin_planos_conta scoped business + ativo + aceita_lancamento + coerência tipo↔plano |
| `vencimento` | Sim | date required |
| `valor_total` | **Só se status aberto/parcial** (fin-tech/0002) | min 0.01, max 9999999999.99 |

**Imutáveis (anti-corrupção contábil):** `tipo`, `origem`, `origem_id`, `status`, `emissao`, `competencia_mes`, `business_id`. Alterar requer cancelar+criar novo.

**Defesa em profundidade:** `UpdateTituloRequest::assertPlanoCoerente()` revalida `tipo↔plano_conta.tipo` (anti tampering frontend).

### Insert (POST `/unificado` — Onda 25)

Drawer Sheet abre via DropdownMenu "+ Novo título" → "Novo recebimento" (verde 145) ou "Novo pagamento" (rose 25). Tipo pré-fixado e não editável.

**Numero sequencial:** `R-NNNNN` (receber) ou `P-NNNNN` (pagar), business-isolado com `lockForUpdate` (R-FIN-002 idempotência forte).

**Defaults aplicados pelo `store()`:**
- `status='aberto'`, `valor_aberto=valor_total`
- `origem='manual'`, `origem_id=null`
- `emissao=now()`, `competencia_mes=Y-m`
- `created_by=updated_by=auth()->id()`
- `business_id` da `session('user.business_id')` — NUNCA do payload (anti tampering)

---

## 8. Plano de Contas (Onda 24)

`PlanoContaCombobox` filtra opções por `kind` do título:

| `kind` | Tipos DCASP permitidos | Exemplo |
|---|---|---|
| `receivable` (receber) | `receita`, `ativo` | `3.1.01.001 Vendas balcão` · `1.1.02.001 Clientes` |
| `payable` (pagar) | `despesa`, `custo`, `passivo` | `5.1.99.001 Aluguel` · `4.1.01.001 CMV` · `2.1.01.001 Salários a Pagar` |

`patrimonio` fica de fora (não é título corrente — encerramento exercício).

**Hues semânticos** via `style={{color, backgroundColor}}` inline oklch — escapa do `ui:lint R1` (tokens shadcn não cobrem 6 tipos DCASP).

**Backfill:** títulos sem plano são tratados por `php artisan financeiro:backfill-plano-conta --business=N` que atribui "(a classificar)" — `3.1.01.999` (receber) ou `5.1.99.999` (pagar). DRE não zera.

---

## 9. Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL)

- Query Titulo: `where('business_id', $businessId)` em todas as 15 rotas
- `business_id` da session, NUNCA do payload (defesa anti tampering)
- `UpdateTituloRequest` + `StoreTituloRequest` validam `categoria_id` + `plano_conta_id` via `Rule::exists` scoped por business
- `BackfillPlanoContaCommand` exige `--business=N` (recusa sem)
- `Titulo::$model->business_id` via `BusinessScope` trait global scope
- Pest GUARDs (`UnificadoControllerTest` + `UnificadoEditPlanoContaTest` + `UnificadoStoreTest` + `UnificadoPlanoContaGuardTest`) cobrem cross-tenant biz=99

---

## 10. Pegadinhas conhecidas

### 10.1 Build & deploy

- **Deploy workflow NÃO faz `npm run build`** — só `git pull` + `composer install`. Pra mudanças no frontend, disparar `Force Clean Rebuild (one-shot)` workflow separado. **Descoberto em 2026-05-25 deploy das Ondas 24/25.**
- **Bundle CSS canon 9054 LOC** importado inteiro em `resources/css/cowork-canon-financeiro-bundle.css` — regra Tier 0 `feedback-cowork-bundle-aplicar-inteiro` (NÃO cherry-pick)

### 10.2 Imutabilidade pós-baixa (fin-tech/0002)

- `valor_total`, `tipo`, `origem`, `origem_id`, `status`, `emissao` ficam READ-ONLY quando `status IN ('quitado', 'cancelado')`
- UI mostra "🔒 imutável pós-baixa" no input desabilitado
- Backend rejeita 422 via `assertValorMutavel()` (defesa em profundidade)

### 10.3 Coerência tipo↔plano (Onda 24)

- Frontend filtra combobox por kind — mas usuário pode dar reload com payload manipulado
- Backend revalida via `assertPlanoCoerente()` — abort(422) com mensagem detalhada
- **Auditoria G12:** falta `Log::warning` em violações (recomendado adicionar)

### 10.4 Stub `/unificado/novo` ainda existe (60d back-compat)

- Onda 25 substituiu por Sheet inline, mas rota antiga preservada pra bookmarks externos
- Rota: GET `/unificado/novo?kind=receivable` ou `?kind=payable` renderiza picker stub
- Plano remover na **Wave 30** após confirmação de zero hits em logs

### 10.5 Compatibilidade filtro `categoria` querystring

- `?categoria=N` aceita N como `plano_conta_id` OU `categoria_id` (back-compat Onda 12.7)
- Backend faz OR no WHERE — bookmark legacy continua válido

### 10.6 Eager-load planoConta

- `index()` faz `with(['planoConta:id,codigo,nome,tipo', ...])` — eager obrigatório
- Sem isso vira N+1 silencioso (1 query por título no `shapeTitulo`)
- **GUARD G3** em `UnificadoPlanoContaGuardTest` blinda regressão

---

## 11. Troubleshoot

### 11.1 "Dropdown + Novo título abre mas item leva pra `/unificado/novo`"

**Causa:** bundle JS antigo no servidor. Vite build não rodou após deploy.
**Fix:** disparar `Force Clean Rebuild` workflow.
**Validar:** `curl -s https://oimpresso.com/login | grep -oE '/assets/app-[^"]+\.js'` muda de hash.

### 11.2 "Combobox plano abre vazio"

**Causa 1:** business sem `PlanoContasBrSeeder` rodado.
**Fix:** SSH no Hostinger + `php artisan tinker --execute='(new \Modules\Financeiro\Database\Seeders\PlanoContasBrSeeder)->run({biz_id});'`

**Causa 2:** todos planos têm `ativo=false` ou `aceita_lancamento=false`.
**Fix:** `UPDATE fin_planos_conta SET ativo=1 WHERE business_id=N AND nivel=4`

### 11.3 "DRE zerada em prod / Fluxo sem valor"

**Causa:** títulos sem `plano_conta_id` (auto-criados antes do schema).
**Fix:** rodar `php artisan financeiro:backfill-plano-conta --business=N --dry` (preview) → `--business=N` (apply).
**Volume:** Larissa (biz=4) tinha 18.054 títulos → backfilled 2026-05-20.

### 11.4 "Edit não salva plano de contas (422)"

**Causa 1:** plano de outro business — `Rule::exists` rejeita scoped.
**Causa 2:** plano de tipo errado (ex: tentar `5.1.x` (despesa) num título receber).
**Fix:** mostrar erro PT-BR no form ("Plano de contas inválido (inexistente, inativo ou sintético)" ou "Plano '...' (tipo despesa) é incompatível com título tipo 'receber'").

### 11.5 "Title screen branco / null component"

**Causa:** OpenTelemetry extension ausente em prod (Hostinger shared NÃO tem `ext-opentelemetry` PECL).
**Fix:** `composer install` no Hostinger SEM `--no-dev` (Faker é runtime) + `--ignore-platform-req=ext-opentelemetry`. OtelHelper é fail-safe.

### 11.6 "Numero R-/P- duplicado"

**Improvável** — `store()` usa `lockForUpdate()` no SELECT MAX. Mas se acontecer:
- Conflito de transação concorrente: rerun do POST resolverá (idempotência)
- Concorrência > 100 POSTs/s: avaliar mudar pra `Snowflake` ID ou Redis INCR
- **Auditoria G9:** evento `TituloCriado` ajudaria detectar via listener

---

## 12. Refs canon

- [Charter v8](../../../resources/js/Pages/Financeiro/Unificado/Index.charter.md)
- [SPEC US-FIN-013/020/021/027](SPEC.md)
- [BRIEFING Financeiro](BRIEFING.md)
- [Auditoria pós Ondas 24/25 (2026-05-25)](../../sessions/2026-05-25-auditoria-financeiro-pos-ondas-24-25.md)
- [ADR 0039](../../decisions/0039-cockpit-chat-pattern-pra-telas-densas.md) — Cockpit Chat Pattern
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR fin-tech/0001](adr/tech/0001-idempotencia-em-toda-mutacao-financeira.md) — Idempotência
- [ADR fin-tech/0002](adr/tech/0002-soft-delete-com-trava-historico.md) — Imutabilidade pós-baixa
- [ADR fin-ui/0002](adr/ui/0002-dashboard-unificado-4-estados.md) — Dashboard unificado
- [ADR fin-ui/0003](adr/ui/0003-amendment-0002-visao-unificada-cockpit-v2.md) — Cockpit V2
- Pest: `UnificadoControllerTest.php` · `UnificadoEditPlanoContaTest.php` · `UnificadoStoreTest.php` · `UnificadoPlanoContaGuardTest.php`

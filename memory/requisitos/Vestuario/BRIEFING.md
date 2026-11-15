---
id: requisitos-vestuario-briefing
module: Vestuario
status: piloto
status_nota: "live em produção via ROTA LIVRE biz=4 desde 2024-Q1"
piloto: ROTA LIVRE — LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME
piloto_inicio: 2024-Q1
cnae_principal: "4781-4/00"
last_review: 2026-05-16
updated_at: "2026-11-12"
owner: W
parent_adr: 0121
related_adrs: [0011-alinhamento-padrao-jana, 0066-format-date-shift-3h-preservado-legacy-clientes, 0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0358-doutrina-de-teste-tenant-98-supersede-0101, 0105-cliente-como-sinal-guiar-sem-mandar, 0121-oimpresso-modular-especializado-por-vertical]
nota_atual: "sem nota vigente (a rubrica module-grade foi aposentada pela ADR 0399; o 90/100 da Wave 25 é histórico)"
gaps_top: [US-VEST-021 prazo CDC, US-VEST-029 estação, US-VEST-023 liquidação]
---

# BRIEFING — Modules/Vestuario

> **1-pager executivo** do módulo vertical lojas de vestuário/moda BR (CNAE 4781-4/00).
> Estado consolidado mantido por skill `brief-update` (Tier B) após PR mergeado que toque o módulo. Wagner enxerga estado real sem precisar pedir.

## TL;DR

Vertical **em produção há 2+ anos** via cliente piloto ROTA LIVRE (biz=4, Termas do Gravatal/SC), que concentra **~99% do volume de vendas** do oimpresso novo (Laravel). Customizações da ROTA LIVRE preservadas como first-class (ADR 0066 `format_date` shift +3h).

Em 2026-11 a auditoria do módulo fechou os lotes 0 a 4: a etiqueta imprime o código que o caixa bipa e acha a peça por nome; a remarcação por coleção grava sem corrida e reverte exato, atrás do setting `remarcacao.enabled` (desligado); a curva de compra virou botão na nova compra; e as rotas do módulo exigem o pacote `vestuario_module` da empresa. O pacote da ROTA LIVRE ganhou o `vestuario_module` em 2026-11-11. Ainda em aberto: o prazo CDC da devolução (US-VEST-021), o cutover das etiquetas e a decisão de ligar a remarcação.

## Cliente piloto — ROTA LIVRE biz=4 (PRODUÇÃO)

> ⚠️ **biz=4 é PROD REAL.** Nunca usar `business_id=4` em teste, seeder, fixture ou smoke. Teste automatizado roda no tenant fictício 98 com o 99 de adversário ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)); smoke manual em produção fica na biz=1 (regra R6 de [`proibicoes.md`](../../proibicoes.md)).

| Campo | Valor |
|-------|-------|
| Razão social | LARISSA COMERCIO DE ARTIGOS DO VESTUARIO LTDA - ME |
| CNPJ | [REDACTED-CNPJ] |
| Endereço | BL0001 "ROTA LIVRE" — Termas do Gravatal, Gravatal/SC, CEP 88735-0 |
| `business_id` | **4** (PRODUÇÃO — NÃO MEXER) |
| Operadora | Dona da loja (role `Admin#4`) |
| Volume | 17.251+ vendas / ~99% do sistema novo Laravel |
| Monitor | **1280px** — telas com 21+ colunas inutilizam; sempre `columnDefs` |
| Customização preservada | `format_date` shift +3h (ADR 0066 — Larissa decorou, NÃO mexer) |
| `transaction_date` retroativo | Fluxo normal (registra balcão em lote no fim do dia) |
| Default role precisa | `location.4` explícita (incidente 2026-04-24) |
| Sazonalidade | Verão (Out-Mar), Inverno (Abr-Set) |
| Pico operacional | 14h-17h horário SP |

## Capacidades em produção (validadas em ROTA LIVRE)

| US | Capacidade | Onde mora |
|----|-----------|-----------|
| US-VEST-001 | Variation (tamanho×cor) com 15+ SKUs por peça | núcleo `App\Variation` + `VariationTemplate` |
| US-VEST-002 | Venda balcão (POS) com leitor barcode | núcleo `SellPosController` |
| US-VEST-003 | NFC-e modelo 65 PDV | `Modules/NfeBrasil` (parcial — Larissa não emite hoje) |
| US-VEST-004 | Histórico vendas com filtros (cliente, período, vendedor) | núcleo `SellController` |
| US-VEST-005 | Estoque por (variation × location) | núcleo `VariationLocationDetails` |
| US-VEST-006 | Compra fornecedor + recebimento | núcleo `PurchaseController` |
| US-VEST-007 | AR/AP + boleto Asaas | `Modules/Financeiro` + `Modules/RecurringBilling` |
| US-VEST-008 | Múltiplos invoice_schemes em paralelo (`2026/NNNN` + `17NNN`) | núcleo `InvoiceSchemeController` |
| US-VEST-009 | Locale pt-BR + monitor 1280px + `format_date` com shift | núcleo `app/Utils/Util.php` + `public/locale/datatables/pt-BR.json` |

## Capacidades do módulo (código entregue; uso na ROTA LIVRE pendente)

| US | Capacidade | Estado em 2026-11-12 |
|----|-----------|----------------------|
| US-VEST-020 | Etiqueta térmica TAM-COR-COLEÇÃO (ZPL Argox/Zebra + PDF) — `EtiquetaTagController`, `EtiquetaTagService`, tela `Vestuario/Etiquetas/Index.tsx` | Código de barras = `sub_sku` (EAN-13 só quando ele já é EAN), o que o PDV procura (#10640); peça achada por nome ou SKU (#10665); teto por pedido e permissão `vestuario.etiqueta.*` atrás da chave `VESTUARIO_ETIQUETA_EXIGIR_PERMISSAO`, desligada por padrão (#10650). Cutover da ROTA LIVRE não ocorreu: coexiste com `/labels/show`. |
| US-VEST-021 | Devolução vira crédito do cliente | Entregue **no núcleo**: decisão [W] 2026-10-26, o crédito é o saldo de Adiantamento (`App\Services\Sells\ValeTrocaService` + opção vale-troca em `SellReturn/Add.tsx`). Falta o prazo CDC com sobreposição do gestor. O `DevolucaoService` do módulo e as tabelas `vestuario_devolucoes`/`vestuario_creditos_cliente` ficaram sem chamador em produção (destino: decisão [W]). |
| US-VEST-031 | Sugestão de compra por tamanho pela curva de venda | `CurvaDeCompra` + `GET /vestuario/curva-compra`, só das lojas que o usuário vê (#10655), e botão "Sugerir pela curva" na nova compra (#10660). |
| US-VEST-032 | Remarcação por coleção com prévia e reversão | Tela `Vestuario/Remarcacao/Index.tsx` com permissão própria e menu (#10647); sem corrida entre gravações e reversão exata (#10710); etiquetas do lote com o preço de hoje, sem corte em 500 (#10711); prévia avisa preço de grupo (#10713). Atrás do setting `remarcacao.enabled`, **desligado** na ROTA LIVRE. |

Acesso: todas as rotas do módulo passam pelo middleware `VestuarioLiberado`, que exige o pacote `vestuario_module` da empresa (#10658, em produção). Contrato de teste: `Etiquetas/Index.casos.md`, `Remarcacao/Index.casos.md` e os `_telas/*.casos.md` (remarcação, curva de compra, acesso pelo pacote).

> 📍 **Recibo de adoção (datado):** em 2026-11-12 o `anchor-lint` segue classificando a tela de etiquetas como
> *"wired porém NÃO-SERVIDO — 0 hits na janela do ledger"* (`governance/route-hits.json`, janela 30d).
> Re-rodar `node scripts/governance/anchor-lint.mjs memory/requisitos/Vestuario/SPEC.md` para atualizar.

## Capacidades faltantes (backlog priorizado)

P1 (Q3/Q4 2026):
- **US-VEST-021** Prazo CDC com sobreposição do gestor (o crédito já existe no núcleo)
- **US-VEST-029** Atributo "estação"/coleção (pré-requisito de 022, 023 e do filtro por coleção da remarcação) · **US-VEST-023** Liquidação em runtime no carrinho
- **US-VEST-022** Comissão e **US-VEST-024** Fidelidade: o núcleo já tem comissão por agente e pontos com resgate; re-escopo é decisão [W]

P2/P3 (2027+ ou sob sinal qualificado):
- US-VEST-025 Gift card · US-VEST-026 Crediário · US-VEST-027 Provador · US-VEST-028 Sacoleira · US-VEST-030 Ecommerce (ADR feature-wish)

## Concorrentes diretos

| Concorrente | Foco | Pricing/m | Lacuna oimpresso preenche |
|---|---|---|---|
| Linx Microvix Vestuário | grandes redes (>5 lojas) | R$ [redacted Tier 0]-2500 | preço alto, lock-in, suporte demorado |
| ProMoz | médio-pequeno (1-3 lojas) | R$ [redacted Tier 0]-700 | falta NFe-de-boleto-pago, BI fraco |
| Vendizap | micro (catálogo WhatsApp) | R$ [redacted Tier 0]-150 | sem PDV físico, sem fiscal robusto |
| Bling Loja | horizontal raso | R$ [redacted Tier 0]-400 | sem profundidade matriz tam×cor |
| F360 | regional sul | R$ [redacted Tier 0]-800 | UI legacy |

## Diferenciais oimpresso vs concorrentes

1. **Jana IA com memória persistente** (ADR 0035-0053) — Larissa pergunta "quanto vendi de Verão24 essa semana?" e recebe resposta com dados reais
2. **NFe-de-boleto-pago automática** (US-RB-044, ADR 0089) — concorrente nenhum tem
3. **Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093) — isolation por design
4. **Stack moderna** Laravel 13.6 + React 19 + Inertia v3 + Pest 4 vs concorrentes PHP 7.x + jQuery legacy
5. **Customizações preservadas** (shift +3h ADR 0066) — concorrente "atualiza e quebra"
6. **Sinal qualificado pra evolução** (ADR 0105) — backlog só recebe se cliente paga e reporta

## Anti-padrões do módulo (Tier 0)

- ⛔ **Teste, fixture ou smoke com `business_id=4`** — teste no tenant 98 (ADR 0358), smoke manual na biz=1
- ⛔ Mexer no `format_date` shift +3h sem ADR amendment a 0066
- ⛔ Adicionar coluna default em DataTables `/sells` sem checar largura 1280px
- ⛔ Criar role custom sem `location.4` explícita (trava /sells/create)
- ⛔ Hard-deletar `Variation` com sell_lines históricos (quebra contábil)
- ⛔ Criar tabela `vestuario_*` sem `business_id` indexed + FK + global scope
- ⛔ Subir feature de fidelidade sem opt-in LGPD (Art. 7º)
- ⛔ Implementar US-VEST-030 (ecommerce) sem 3+ sinais qualificados

## Nota

Sem nota vigente. O 90/100 da Wave 25 (rubrica `vertical_client_facing`, ADR 0160) é histórico: a rubrica module-grade foi aposentada pela [ADR 0399](../../decisions/0399-aposentar-rubrica-module-grade-gate-e-baseline.md). Detalhe da época em [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md).

## Status lifecycle (ADR 0121)

- ✅ `piloto` — ROTA LIVRE biz=4 pagando, código vivendo
- ⏳ `ativo` (meta Q4/26 ou Q1/27) — exige 3+ clientes pagantes + SPEC + CAPTERRA-FICHA + CAPTERRA-INVENTARIO + Pest GUARD pra Non-Goals/Anti-hooks (a pasta `Modules/Vestuario/` já existe)

## Roadmap próximos 12 meses

| Quarter | US prioridade | Marco |
|---------|---------------|-------|
| 2026-Q2 | ✅ US-VEST-020 etiqueta | fundação |
| 2026-Q4 | ✅ US-VEST-031 curva · ✅ US-VEST-032 remarcação (desligada) · US-VEST-021 prazo CDC · cutover etiquetas | auditoria 2026-11, lotes 0 a 4 |
| 2027-Q1 | US-VEST-029 estação + US-VEST-023 liquidação · US-VEST-025 gift card | coleção como atributo |
| 2027-Q2 | US-VEST-026 crediário + US-VEST-027 provador | revenda 2º cliente Vestuario |
| 2027-H2 | US-VEST-028 sacoleira + revisão US-VEST-030 ecommerce (com sinal) | network effect |

## Decisões pendentes

- [ ] Regime tributário ROTA LIVRE (MEI/Simples?) — destrava US-VEST-003 NFC-e regular
- [ ] Ligar `remarcacao.enabled` e fazer o cutover das etiquetas na ROTA LIVRE (com aviso à cliente)
- [ ] Regra de prazo da troca no balcão (o critério CDC fala de compra online e defeito)
- [ ] Destino do `DevolucaoService` e das tabelas `vestuario_devolucoes`/`vestuario_creditos_cliente`
- [ ] US-VEST-022 (comissão) e US-VEST-024 (fidelidade): o núcleo já tem as duas coisas; re-escopar ou fechar
- [ ] Comissão escalonada simples vs discovery 1 loja real antes de codar
- [ ] Etiqueta térmica padrão Argox/Zebra universal SC ou variação regional
- [ ] Fidelidade pontos transferíveis (Linx tem) vs não-transferíveis (LGPD-easy)
- [ ] Crediário com Asaas (boleto/parcela) ou 100% offline (carnê PDF)

## Referências

- [SPEC.md](SPEC.md) — especificação funcional completa US-VEST-*
- [Vestuario.charter.md](Vestuario.charter.md) — module charter (Mission/Goals/Non-Goals/Anti-hooks)
- ADR 0121 — Modular especializado por vertical (mãe)
- ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0066 — `format_date` shift +3h preservado
- ADR 0358 — Teste no tenant 98 (supersede a 0101)
- ADR 0105 — Cliente como sinal qualificado

---

**Atualizado:** 2026-11-12 — lote 6 da auditoria (documentação): capacidades do módulo reconciliadas com os lotes 0 a 4 (#10639 a #10735, #10658), regra de tenant de teste corrigida para a ADR 0358, nota da rubrica aposentada tirada do presente, nome pessoal da operadora fora do canon.

**Histórico:** 2026-07-28 — chip **Onda 4 do passo 5** (agent `sdd-from-source`): nasce o
[`SDD-tela-etiqueta-tag-v1.0.md`](SDD-tela-etiqueta-tag-v1.0.md) (3 fontes trianguladas; **fonte 4
Delphi declarada AUSENTE** — não existe `ANTI-REGRESSAO-*` no Vestuario) + trio da tela fechado
(`Index.casos.md`, 9 UC, 9 com teste, 0 órfão) + `EtiquetaTagContratoTest` + âncoras
`Implementado em:`/`Testado em:` na US-VEST-020. `anchor_coverage` do módulo **42,9% → 47,6%**;
lacunas da porta viva **1 → 0**. Achados abertos que precisam de [W]: permissão anunciada que não
bloqueia (SDD §9 D-1) · promessa de preview no charter sem implementação (§9 D-2) · US-VEST-021
com código `[V0]` sem tela nem contrato (§9 R-3). **Sem `distilled_at`** — este módulo nunca foi
carimbado pelo distiller e o chip não o carimbou (carimbar armaria a métrica `distiller_freshness`
sem redestilar de verdade).

**Histórico:** 2026-07-18 — refresh de frescor briefing↔código [CC]. Reconciliado com o código: **US-VEST-021 devolução reintegra estoque** (`DevolucaoService` via `ProductUtil` auditável, UC-EST-04 Tier 0, #3691) + **US-VEST-020 etiquetas térmicas** (ZPL/PDF + EAN-13/QR, charter draft #4125, `DataController`+sidebar #2673) saíram do backlog P0. Nota W25 mantida (stale — não re-medida hoje). DS/AppShellV2 na Page de etiquetas.

**Histórico:** 2026-05-16 Wave 25 SATURATION — D7 LGPD forense fix (criou `vestuario.yaml` scorecard; causa raiz da regressão D7=3 W17→W18→W23, o `ScopedScorecardEvaluator` retornava `[]` sem o YAML). Wave Massive criou BRIEFING inicial + 3 Pest (Grade cross-tenant, Smoke routes, Scaffold).

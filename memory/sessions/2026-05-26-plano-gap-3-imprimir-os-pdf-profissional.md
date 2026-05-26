---
title: "Plano Gap 3 — Imprimir OS PDF profissional (CSS print stylesheet)"
date: "2026-05-26"
type: gap-plan
status: draft
gap_id: 3
modulo: OficinaAuto
us_relacionada: US-OFICINA-037 (nova — print OS profissional)
cliente: Martinho biz=164
esforco_estimado: "3-5h IA-pair (fator 10x ADR 0106) + 1h smoke real"
roi: alto-papel-no-balcao
bloqueia_demo: nao (window.print cobre 80%)
---

# Plano Gap 3 — Imprimir OS PDF profissional

## Contexto

Drawer rico tem footer "Imprimir OS" que chama `window.print` (linha referencia: ServiceOrderRichSheet.tsx footer Wave 2.4). Hoje sai layout generico AppShellV2 com sidebar+header — feio, nao papel-de-balcao. Martinho imprime OS pra cliente assinar no balcao apos servico.

## Research estado-da-arte 2026

Concorrentes BR (Bling, Tiny, UltimatePOS legacy) imprimem OS em **3 layouts canonicos**:

1. **Nota-fiscal-like A4** — cabecalho empresa + dados cliente + dados veiculo + tabela items + totais + assinatura + rodape ✏ campo "Recebi em ___/___/___ ass.: _____" — padrao mecanica pesada CNAE 4520
2. **Cupom 80mm thermal** — POS-style impressora termica balcao (Sells ja tem em `resources/views/sale_pos/receipts/slim.blade.php` + slim2.blade.php)
3. **Envelope packing-slip** — checklist visual sem precos pra mecanico marcar (ja existe em Sells: `printSaleReceipt(mode='packing_slip')`)

Martinho operacionalmente quer **#1 A4 nota-fiscal-like** — papel cliente leva pra casa.

[Jotform Auto Repair Work Order template](https://www.jotform.com/pdf-templates/auto-repair-work-order) + [Method.me printable template](https://www.method.me/resources/auto-repair-work-order-template-download/) consolidam pattern A4: 4 zones (header empresa / cliente+veiculo grid / items table / totais+assinatura).

Tecnicamente 2026: **CSS @media print + @page** > biblioteca PDF backend pra ROI. Bling/Tiny usam `window.print()` com stylesheet dedicado — gera PDF nativo via "Imprimir como PDF" do browser. Zero backend cost, zero dependencia (dompdf/wkhtmltopdf vulnerability surface).

## Inventario oimpresso

**Padrao canonico existente:** `resources/js/Lib/printSaleReceipt.ts` (Sells) — IFRAME OCULTO + CSS legacy app.css `@media print`. 3 modos: invoice / packing_slip / delivery_note. Documentado, testado. **Adaptar pattern pra OS.**

Files referencia a IMITAR:
- `resources/js/Lib/printSaleReceipt.ts` (mecanica IFRAME)
- `resources/views/sale_pos/receipts/slim.blade.php` (cupom 80mm) — NAO eh o caso aqui
- `resources/js/Pages/RecurringBilling/_components/printExtractStyles.ts` (stylesheet dedicada — pattern leve)
- `resources/views/sells/transcript.blade.php` (transcript impressao Sells)

Files que NAO existem ainda (criar):
- `resources/js/Lib/printServiceOrder.ts`
- `resources/views/oficina_auto/print/service_order.blade.php`
- CSS print rules em `resources/css/oficina-auto-print.css`

## Arquivos a tocar

| Arquivo | Operacao | Notas |
|---|---|---|
| `Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php` | EDIT — adicionar action `printInvoice(ServiceOrder $order)` retorna `{success, receipt: {html_content, print_title}}` | Espelha SellPosController::printInvoice (Sells legacy) — Accept-aware AJAX-only |
| `Modules/OficinaAuto/Routes/web.php` | EDIT — `GET /ordens-servico/{order}/print` | Route name oficinaauto.orders.print |
| `resources/views/oficina_auto/print/service_order.blade.php` | NOVO — template Blade A4 nota-fiscal-like | Inclui CSS inline em `<style>` pra IFRAME cross-origin compat |
| `resources/js/Lib/printServiceOrder.ts` | NOVO — helper IFRAME (espelha printSaleReceipt) | 1 modo "invoice" V0, +packing_slip V2 |
| `resources/js/Pages/OficinaAuto/ProducaoOficina/_components/ServiceOrderRichSheet.tsx` | EDIT — botao "Imprimir OS" footer chama `printServiceOrder({printUrl: order.urls.print})` em vez de window.print | 1 linha |
| `resources/js/Pages/OficinaAuto/ServiceOrders/Show.tsx` | EDIT — adicionar botao "Imprimir OS" no PageHeaderActions | Reusa helper |
| `Modules/OficinaAuto/Tests/Feature/ServiceOrderPrintTest.php` | NOVO — 4 Pest specs | response 200 AJAX + 404 sem AJAX header + cross-tenant 404 + total bate com items |
| `Modules/OficinaAuto/SCOPE.md` | EDIT — declarar novo endpoint | scope-guard.yml |

## Layout do template Blade A4

```
┌──────────────────────────────────────────────────────────────┐
│ [LOGO EMPRESA]    Martinho Cacambas LTDA           OS #1234  │
│                   Rua X, 123 - Tubarao SC          25/05/2026│
│                   CNPJ 12.345.678/0001-90                    │
├──────────────────────────────────────────────────────────────┤
│ CLIENTE                          VEICULO                     │
│ Joao Silva                       Volvo FH 540 2018           │
│ CPF 123.456.789-00               Placa ABC-1234              │
│ Tel (48) 9 9999-8888             Chassi xxxx                 │
│ Rua Y, 456 - Tubarao SC          KM entrada: 245.890         │
├──────────────────────────────────────────────────────────────┤
│ ITENS                                                        │
│ ┌──┬──────────────────────┬────┬──────────┬──────────────┐  │
│ │# │Descricao             │Qtd │V.Unit    │V.Total       │  │
│ ├──┼──────────────────────┼────┼──────────┼──────────────┤  │
│ │1 │Pastilha freio dianteira │ 1  │R$ [redacted Tier 0] │R$ [redacted Tier 0]   │  │
│ │2 │Disco freio par         │ 2  │R$ [redacted Tier 0] │R$ [redacted Tier 0]    │  │
│ │3 │Mao-de-obra freios      │ 3h │R$ [redacted Tier 0] │R$ [redacted Tier 0]    │  │
│ └──┴──────────────────────┴────┴──────────┴──────────────┘  │
│                                            TOTAL: R$ [redacted Tier 0]│
├──────────────────────────────────────────────────────────────┤
│ OBSERVACOES                                                  │
│ Cliente solicitou troca preventiva. KM final: 246.120.       │
├──────────────────────────────────────────────────────────────┤
│ DVI VISTORIA (se houver — opcional)                          │
│ ● Motor: OK                                                  │
│ ● Freios: ATENCAO - pastilhas a 30%                          │
│ ● Bateria: CRITICO - 6 anos, recomenda troca - R$ [redacted Tier 0]        │
├──────────────────────────────────────────────────────────────┤
│ Recebi os servicos descritos em ___/___/______               │
│                                                              │
│ ____________________________                                 │
│ Assinatura do cliente                                        │
└──────────────────────────────────────────────────────────────┘
```

## Restricoes Tier 0 deste gap

1. **Multi-tenant ADR 0093** — `ServiceOrderController::printInvoice` precisa carregar `ServiceOrder $order` via global scope (Route Model Binding automatico ja respeita). Defensive guard: `abort_unless($order->business_id === auth()->user()->business_id, 404)`.
2. **LGPD** — template Blade NAO renderiza CPF/CNPJ completo em prints publicos. Usar accessor `tax_number_masked` (ja existe em Contact). Mas papel-de-balcao do cliente PODE ter CPF (fisicamente protegido pelo cliente). Decisao: render completo, footer disclosure LGPD basico.
3. **CSS print canon** — usar `@page { size: A4; margin: 1.5cm; }` + `@media print { body { font-size: 11pt; } .no-print { display: none; } }`. NAO depender de Bootstrap legacy (pode causar drift como printSaleReceipt — comentario linha 6 menciona "CSS legacy embutido pra IFRAME").
4. **Tailwind utilitario NAO funciona em IFRAME cross-origin** — CSS precisa estar **inline** no `<style>` do Blade ou referenciado via URL absoluto carregado dentro do IFRAME. Pattern documentado em printSaleReceipt.ts.
5. **Anti-hook charter** — `window.print()` direto do drawer e OK (acao humana clicou). Mas NAO fazer auto-print on-mount.

## Mini-comparativo atual → target

| Aspecto | Hoje (window.print bare) | Target Gap 3 |
|---|---|---|
| Layout | AppShellV2 sidebar+header impressos | A4 nota-fiscal-like limpo |
| Cabecalho empresa | nao tem | logo + razao social + CNPJ + endereco |
| Bloco cliente | nao tem | nome + CPF/CNPJ + tel + endereco |
| Bloco veiculo | nao tem | marca+modelo+ano + placa + chassi + KM |
| Tabela items | nao tem | qtd + descricao + valor unit + total + footer total geral |
| DVI (se houver) | nao tem | secao semaforo opcional |
| Assinatura | nao tem | campo "Recebi em ___/___ ass.: ___" |
| Margens A4 | default browser | 1.5cm controlado @page |
| Footer paginacao | "Page 1 of 1" browser | rodape com "OS #X · gerado em DD/MM HH:mm" |

## Esforco estimado

- Template Blade A4 com CSS inline: 1.5h
- Controller action + Route: 30min
- Helper TS printServiceOrder: 30min (espelha printSaleReceipt)
- Integracao 2 pontos (drawer footer + Show.tsx): 30min
- 4 Pest specs: 1h
- SCOPE.md + smoke local: 30min
- **Total: 4-5h IA-pair** (fator 10x ADR 0106) + 1h smoke real Wagner

## Smoke criteria

- [ ] biz=164 Martinho `/oficina-auto/ordens-servico/{id}`: clica "Imprimir OS" footer drawer, IFRAME abre print preview
- [ ] Preview mostra logo+CNPJ Martinho + cliente Joao Silva + veiculo Volvo FH + 3 items + total R$ [redacted Tier 0]
- [ ] Margens 1.5cm respeitadas, fonte 11pt, sem sidebar/header AppShellV2 vazado
- [ ] Cross-tenant: tentar `/oficina-auto/ordens-servico/<id_biz_1>/print` logado biz=164 retorna 404
- [ ] Imprimir como PDF browser: arquivo .pdf abre layout identico (1-2 paginas)
- [ ] OS com DVI: secao semaforo aparece (3 items)
- [ ] OS sem DVI: secao oculta, layout fecha sem espaco em branco

## Dependencias

- **PR independente** — NAO depende de outros gaps
- Recomendado Gap 2 (DVI UI) ANTES — pra template Blade ja prever secao semaforo (mas template aceita ausencia gracefully)
- Pre-req zero — pode comecar imediatamente

## DRAFT task pra Wagner copy-paste

```yaml
title: "Gap 3 — Imprimir OS PDF profissional CSS print A4"
module: OficinaAuto
us: US-OFICINA-037
priority: medium-high
estimated_hours: 5
owner_proposal: claude-paralelo
description: |
  Substituir window.print() bare por layout A4 nota-fiscal-like canon BR.
  Espelha pattern printSaleReceipt.ts (Sells legacy) — IFRAME OCULTO + CSS
  inline. Template Blade com 4 zonas: cabecalho empresa / cliente+veiculo /
  items + total / observacoes + DVI opcional + assinatura.

  Zero dependencia backend (sem dompdf/wkhtmltopdf). Browser PDF "Imprimir
  como PDF" cobre 95% Martinho papel-balcao.

  Pre-flight obrigatorio:
  - Ler resources/js/Lib/printSaleReceipt.ts (pattern mae)
  - Ler resources/views/sells/transcript.blade.php (estrutura blade)

  Refs: ADR 0093, ADR 0106, US-OFICINA-037 (novo)
acceptance_criteria:
  - "Wagner biz=164 imprime OS Martinho, papel A4 sai limpo nota-fiscal-like"
  - "PDF gerado via browser identico (sem sidebar AppShellV2)"
  - "Cross-tenant 404 quando OS de outra biz"
  - "Pest 4/4 verde local"
```

## Refs

- [Jotform Auto Repair Work Order PDF](https://www.jotform.com/pdf-templates/auto-repair-work-order)
- [Method Auto Repair Work Order template](https://www.method.me/resources/auto-repair-work-order-template-download/)
- [Smartsheet PDF Work Order templates](https://www.smartsheet.com/content/pdf-work-order-form-templates)
- `resources/js/Lib/printSaleReceipt.ts` (pattern mae IFRAME)
- `resources/views/sale_pos/receipts/slim.blade.php` (cupom 80mm — caso futuro)
- ADR 0106 Recalibracao 10x
- [ADR 0093 Multi-tenant](memory/decisions/0093-multi-tenant-isolation-tier-0.md)

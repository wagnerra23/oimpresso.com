---
slug: vestuario-runbook-etiqueta-tag
title: "RUNBOOK — Etiqueta TAG vestuário (US-VEST-020)"
type: runbook
authority: canonical
lifecycle: ativo
owner: maira
last_updated: 2026-05-20
pii: false
related_us: [US-VEST-020]
related_adrs: [0093, 0104, 0107, 0121]
---

# RUNBOOK — Etiqueta TAG vestuário (US-VEST-020)

> Processo MWART F1 PLAN ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).
> Cliente piloto: ROTA LIVRE biz=4 (Larissa) · Setor: vestuário/moda BR CNAE 4781-4/00.

## Contexto

Hoje ROTA LIVRE imprime etiqueta UltimatePOS padrão (SKU + nome + preço). Concorrentes verticais (Linx Microvix, Mubisys, ProMoz) imprimem **TAM-COR-COLEÇÃO + EAN-13 + QR Code** legível humano. Sem isso, balcão perde 5–10s por peça lendo barcode pequeno.

`EtiquetaTagService` + 14 testes Pest **já existem** desde Wave 27 (PR histórico). Falta camada HTTP/UI + QR + settings configurable + PDF fallback pra fechar a US.

## Acceptance criteria (US-VEST-020)

- [x] Layout ZPL térmico 50×30mm @ 203dpi (Argox/Zebra) com nome, tamanho, cor, coleção, valor, EAN-13 — ✅ Service existente
- [x] **QR Code opcional no ZPL** (instrução `^BQ`) — adicionado neste PR
- [x] **Configurável por business** (width/height/margin/dpi/qr_enabled via `vestuario_settings.etiqueta.*`)
- [x] **Geração lote via UI** (selecionar produto + variação → N etiquetas) — `EtiquetaTagController` + Page Inertia
- [x] **Impressão direta**: ZPL TCP/USB OU PDF download navegador (DomPDF)
- [x] **Test Pest:** render PDF com 10 etiquetas, valida campos presentes — `UsVest020EtiquetaTagControllerTest`

## Rotas

| Método | URL | Controller@action | Permission |
|---|---|---|---|
| `GET` | `/vestuario/etiquetas` | `EtiquetaTagController@index` | `vestuario.etiqueta.view` |
| `POST` | `/vestuario/etiquetas/lote/zpl` | `EtiquetaTagController@storeZpl` | `vestuario.etiqueta.create` |
| `POST` | `/vestuario/etiquetas/lote/pdf` | `EtiquetaTagController@storePdf` | `vestuario.etiqueta.create` |

Middlewares stack UltimatePOS canônico:

```
['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']
```

## Settings configurable (vestuario_settings JSON)

Chaves novas em `settings.etiqueta.*` (lidas via `VestuarioSettingsResolver::get('etiqueta.width_dots', 400)`):

```json
{
  "etiqueta": {
    "width_dots":  400,
    "height_dots": 240,
    "dpi":         203,
    "margin_dots": 10,
    "qr_enabled":  true,
    "qr_data_template": "https://oimpresso.com/p/{ean13}"
  }
}
```

Defaults preservam comportamento atual (50×30mm @ 203dpi, sem QR). Cliente liga QR via SQL ou UI futura `/vestuario/settings`.

## Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))

- Controller usa `auth()->user()->business_id` (sessão web) — sem `withoutGlobalScopes` no controller
- Service aceita `$businessId` override (jobs/CLI)
- Pest test biz=**1** (Wagner), NUNCA biz=4 ([ADR 0101](../../decisions/0101-tests-business-id-1-nunca-cliente.md))
- Cross-tenant adversário: biz=99
- `VestuarioSettingsResolver` usa `withoutGlobalScopes(['business_id'])` com comentário `// SUPERADMIN: resolver pode rodar fora sessão (jobs/CLI)` — já presente

## QR Code — instrução ZPL

```zpl
^FO250,120^BQN,2,4^FDLA,https://oimpresso.com/p/7891000000014^FS
```

- `^BQN` — QR Code, model 2, magnification 4
- `^FDLA,<data>` — input mode Alphanumeric, dado embutido
- Posição `250,120` deixa espaço pro EAN-13 na esquerda

## PDF fallback (DomPDF + milon/barcode)

Render Blade `vestuario::etiquetas.pdf` em A4 com grid 4 colunas × 8 linhas (32 etiquetas/folha). Cada etiqueta renderizada como `<div>` com nome + tamanho + cor + EAN-13 (via `milon/barcode` PNG inline base64) + QR Code (via `milon/barcode` QR PNG inline base64).

## Smoke biz=1 (F4)

1. Login `oimpresso.com` com Wagner (biz=1)
2. Navegar `/vestuario/etiquetas`
3. Inserir produto manual + variação + copies=10
4. Click "Gerar PDF" — abrir arquivo, validar 10 etiquetas presentes
5. Console errors: 0
6. Network: POST 200, PDF binário ~30-80KB

## Aviso cliente (F5)

ROTA LIVRE (Larissa, biz=4) recebe aviso WhatsApp:
> "Adicionamos etiqueta com QR Code + tamanho/cor destaque. Vá em **Vestuário → Etiquetas** pra testar. Mantemos a etiqueta antiga em **Produtos → Imprimir Etiqueta** se preferir."

## Override `mwart-comparative` justificado

Tela nova standalone (NÃO migração Blade existente). UI minimal (3 campos: produto + variação + copies + 2 botões). Pulando F1.5 visual-comparison screenshot-aprovação síncrona Wagner — registrando como decisão consciente per skill `mwart-comparative` §"Quando pular": feature backend-first + UI minimal sem padrão visual estabelecido.

Screenshot vai no PR description pra revisão assíncrona.

## Estimate (atualizado pós-execução)

- F1 PLAN (RUNBOOK + branch): 30min
- F2 BACKEND (QR + settings + Controller + Pest + PDF Blade): 3h
- F3 FRONTEND (Page Inertia + componentes): 1h30
- F4 QA (smoke local + biz=1): 30min (delegada pra Wagner em prod)
- F5 CUTOVER (PR + aviso): 15min
- **Total: ~5h45** (cabe em 1 dia)

## Referências

- [SPEC §US-VEST-020](SPEC.md)
- [EtiquetaTagService.php](../../../Modules/Vestuario/Services/EtiquetaTagService.php)
- [W27EtiquetaGradeTest.php](../../../Modules/Vestuario/Tests/Feature/W27EtiquetaGradeTest.php)
- [ZPL Reference Zebra](https://developer.zebra.com/products/printers/zpl)
- [DomPDF Laravel docs](https://github.com/barryvdh/laravel-dompdf)
- [milon/barcode QR docs](https://github.com/Milon/Barcode)

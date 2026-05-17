# Modules/ProductCatalogue

> Catálogo público de produtos via QR code — cliente final escaneia QR na loja/cardápio/vitrine, abre URL pública `/catalogue/{business_id}/{location_id}` SEM auth, navega produtos com fotos+preços+descontos vigentes.
> **Tier 0:** Multi-tenant `business_id` global scope ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) — defesa em profundidade (rota pública, atacante pode tentar enumerar tenants via QR scan brute-force).

## Como cliente (Wagner/Larissa/Martinho) usa

| Quero... | Como acontece | Onde aparece UI |
|---|---|---|
| Gerar QR code do catálogo da loja | Admin login → `/product-catalogue/catalogue-qr` → escolhe location → download PNG | Tela admin "QR Catálogo" |
| Imprimir QR pra colar no balcão | Download PNG → impressão fotográfica ou cola adesivo | Físico (sticker/displa) |
| Cliente escaneia QR | Camera celular → abre `/catalogue/4/2` em browser | Mobile-first browser |
| Cliente vê produtos por categoria | Lista agrupada por categoria + foto + preço + desconto vigente | Página pública catalogue.index |
| Cliente vê detalhe de produto | Tap card → `/show-catalogue/4/{product_id}?location_id=2` | Página catalogue.show |
| Cliente vê preços por grupo (atacado/varejo) | Variations com `group_prices` + `allowed_group_prices` | Tabela preços na show |
| Cliente vê desconto promocional ativo | `ProductCatalogueRepository::activeDiscounts` filtra por janela vigente + location | Badge "Promoção" no card |

## Garantias

- **Multi-tenant Tier 0 IRREVOGÁVEIS** — toda query filtra `business_id` no Repository (defesa em profundidade — rota é pública e atacante pode iterar IDs)
- **Sem PII de cliente** — consumidor escaneia QR ANÔNIMO, não cria conta, não há `Contact` envolvido na rota pública
- **Read-only** — rota pública apenas lê, nunca escreve (segurança contra SSRF/XSS no payload)
- **Schema-aware fail-soft** — colunas opcionais (`product_catalogue_version`) ausentes não quebram página pública
- **Telemetria observável** — `OtelHelper::spanBiz` em `buildIndexPayload` + `buildShowPayload` (D9 hot-path catálogo)

## Observabilidade D9.a ([ADR 0155](../../memory/decisions/0155-module-grade-v3-tier-a-d9-otel.md))

Spans canon (zero-cost se `otel.enabled=false`):

- `product_catalogue.build_index_payload` — montagem listagem agrupada (hot-path QR scan)
- `product_catalogue.build_show_payload` — montagem detalhe produto

Atributos sempre `business_id` Tier 0 + `location_id` + `product_id`. NUNCA IP do scanner, user-agent, ou outro PII indireto.

## Journey real (Larissa biz=4 ROTA LIVRE — caso piloto)

| Passo | Ação | Resultado |
|---|---|---|
| 1. Larissa login painel admin | `/login` biz=4 | Sessão Wagner+location ativa |
| 2. Acessa `/product-catalogue/catalogue-qr` | `ProductCatalogueController::generateQr` autoriza | Tela admin com formulário location |
| 3. Escolhe location "Termas do Gravatal" | Form submit | QR PNG gerado (URL apontando `/catalogue/4/2`) |
| 4. Imprime QR + cola no balcão | Físico | Cliente final pode escanear |
| 5. Cliente escaneia camera | Browser abre `/catalogue/4/2` | Loading + render index |
| 6. Catálogo renderiza por categoria | `buildIndexPayload(4, 2)` retorna products + discounts + locations + categories | Cards agrupados visualmente |
| 7. Cliente tap "Vestido Floral M" | `/show-catalogue/4/123?location_id=2` | Detalhe com fotos + preços + variations |
| 8. Cliente vê preço R$ [redacted Tier 0] + 10% off promocional | `formatDiscountAmounts` aplica `discount_amount` | Badge "10% OFF" + preço novo |

## Estrutura

```
Modules/ProductCatalogue/
├── Console/Commands/ProductCatalogueHealthCommand.php  # canon `--detail` flag
├── Database/Migrations/                                # add_product_catalogue_version (audit history)
├── Http/
│   ├── Controllers/ProductCatalogueController.php      # Magro (<200 linhas) — Wave 16 D4
│   └── Requests/                                       # ShowPublicCatalogue, ShowProduct, GenerateQr (FormRequest validation)
├── Repositories/ProductCatalogueRepository.php         # TODA query DB (multi-tenant Tier 0)
├── Resources/
│   ├── assets/plugins/easy.qrcode.min.js              # Gerador QR client-side
│   └── views/catalogue/                                # Blade public (index/show/generate_qr)
├── Routes/web.php                                      # rotas públicas + admin
├── Services/
│   ├── CatalogueService.php                            # buildIndexPayload + buildShowPayload (D9 spans)
│   └── CatalogueQrService.php                          # buildQrPayload + authorize admin
├── Tests/Feature/                                      # Architecture, PublicCatalogueSecurity, SmokeRoutes, Wave23/26 saturation
├── Config/config.php + retention.php                  # retention LGPD doc (sem PII direta)
└── README.md (este arquivo)
```

## Entidades (intencionalmente vazias)

`Modules/ProductCatalogue/Entities/` está vazia por design: o catálogo apenas LÊ entities core UltimatePOS (`App\Product`, `App\Variation`, `App\Category`, `App\BusinessLocation`, `App\Discount`). Tabela própria é apenas `product_catalogue_version` (audit history).

Razão: catálogo é **projeção read-only** de dados que já vivem no core — duplicar Entity violaria SoC. Pattern documentado em ADR 0011 (padrão Jana/Repair: imitar antes de criar).

## LGPD ([ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4)

- `pii_fields_tracked`: nenhum no schema próprio (consumidor escaneia QR anônimo)
- `pii_redactor_enabled`: n/a (sem PII)
- `activity_log_enabled`: n/a (catálogo é read-only, audit é responsabilidade do core ao mudar produtos)
- `retention`: `product_catalogue_version=1095d` (3 anos histórico catálogo pra rollback)

## Referências

- ADR multi-tenant: [0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- ADR observabilidade: [0155](../../memory/decisions/0155-module-grade-v3-tier-a-d9-otel.md)
- ADR padrão módulo: [0011](../../memory/decisions/0011-alinhamento-padrao-jana.md)
- CHANGELOG (append-only): [`CHANGELOG.md`](CHANGELOG.md)
- SCOPE técnico: [`SCOPE.md`](SCOPE.md)

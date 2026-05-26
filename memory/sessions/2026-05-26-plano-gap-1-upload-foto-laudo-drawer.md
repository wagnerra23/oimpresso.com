---
title: "Plano Gap 1 — Upload foto/laudo real no drawer (substituir placeholders V2)"
date: "2026-05-26"
type: gap-plan
status: draft
gap_id: 1
modulo: OficinaAuto
us_relacionada: US-OFICINA-027-bis (extensao do drawer rico Wave 2)
cliente: Martinho biz=164
esforco_estimado: "4-6h IA-pair (fator 10x ADR 0106) + 2h smoke real"
roi: medio
bloqueia_demo: nao (placeholder cobre)
---

# Plano Gap 1 — Upload foto/laudo real no drawer

## Contexto

Drawer rico `ServiceOrderRichSheet.tsx` seção **FOTOS & LAUDO** (linha ~17 do header doc) tem hoje 3 placeholders `aspect-square` + botão "Adicionar foto" disabled marcado **V2**. Backend DVI `oa_inspection_items.photo_url` (string 500) JÁ existe na migration `2026_05_26_120002_create_oa_inspection_items_table.php` — campo nullable preparado.

## Research estado-da-arte 2026

Modernas DVI (AutoVitals, Tekmetric, Mitchell1, Torque360) capturam **30+ fotos por veículo** via app mobile do mecânico. Foto inline em CADA item da checklist é o padrão — não apenas "anexo solto da OS". [AutoVitals best practices](https://blog.autovitals.com/digital-vehicle-inspection-best-practices) coloca foto/vídeo como "cornerstone of effective DVIs" pra approval rate 30-50% maior.

Arquitetura state-of-the-art Laravel 2026: **client streams chunked diretamente pra object storage** (S3/MinIO/Spaces) via signed URL, backend só registra metadata. Flysystem switching `local` ↔ `s3` é 1-line config. Multi-tenant suffix automático via `tenancy.filesystem.suffix_base + tenant_id`.

## Inventário oimpresso (DESCOBERTA CRÍTICA)

**Não precisamos decidir storage — JÁ está decidido.** `Modules/Arquivos` é backbone canon (ADR 0123):

- `Modules/Arquivos/Concerns/HasArquivos.php` — trait polimórfica `morphMany(Arquivo::class, 'arquivable')`
- `Modules/Arquivos/Services/ArquivosService::attach($model, UploadedFile, $opts)` — API canônica
- `Modules/Arquivos/Services/VaultEncryptionService.php` — crypto in-rest LGPD
- `Modules/Arquivos/Services/ArquivosRetentionService.php` — retention LGPD configurável `Config/retention.php`
- 14 Pest specs cobrindo cross-tenant + audit log + dedupe
- Storage abstraído via Laravel disks (`config/filesystems.php`) — `local` no Hostinger hoje, mas swap pra S3/MinIO é 1 linha

**Decisão pendente revogada:** não decidir S3 vs MinIO vs Spaces aqui — usar disk default config. Decisão storage é problema de ops/infra, não US OficinaAuto.

## Arquivos a tocar

| Arquivo | Operacao | Notas |
|---|---|---|
| `Modules/OficinaAuto/Entities/OaInspectionItem.php` | EDIT — `use HasArquivos` | Trait polimorfica, 1 linha |
| `Modules/OficinaAuto/Entities/ServiceOrder.php` | EDIT — `use HasArquivos` | Anexos OS-level (laudo geral) |
| `Modules/OficinaAuto/Http/Controllers/DviInspectionController.php` | EDIT — endpoint `uploadPhoto(ServiceOrder $order, OaInspectionItem $item)` | POST multipart, retorna `photo_url` atualizado |
| `Modules/OficinaAuto/Http/Requests/UploadDviPhotoRequest.php` | NOVO — validacao MIME image/jpeg, image/png, image/heic; max 10MB | LGPD: log audit do upload |
| `Modules/OficinaAuto/Routes/web.php` | EDIT — `POST /ordens-servico/{order}/dvi/{item}/photo` + `DELETE /.../photo` | Throttle 30/1 (upload e mais pesado que CRUD) |
| `resources/js/Pages/OficinaAuto/ProducaoOficina/_components/ServiceOrderRichSheet.tsx` | EDIT — seção FOTOS substitui placeholder por componente `<DviPhotoGrid items={dviItems} onUpload={...} />` | Camera input mobile-first: `<input type="file" accept="image/*" capture="environment">` |
| `resources/js/Pages/OficinaAuto/ProducaoOficina/_components/DviPhotoGrid.tsx` | NOVO — grid responsivo + thumbnail + delete + lightbox preview | shadcn Dialog pra preview full |
| `resources/js/Pages/OficinaAuto/ProducaoOficina/_components/DviPhotoGrid.charter.md` | NOVO — charter Tier B component | Status: draft inicialmente |
| `Modules/OficinaAuto/Tests/Feature/DviPhotoUploadTest.php` | NOVO — 6 Pest specs | upload OK + MIME reject + tamanho reject + cross-tenant 404 + cross-OS 404 + retention soft-delete |
| `Modules/OficinaAuto/SCOPE.md` | EDIT — declarar novo controller endpoint + Request + Component | Bloqueio scope-guard.yml |

## Restricoes Tier 0 deste gap

1. **Multi-tenant ADR 0093** — `Arquivo` model do `Modules/Arquivos` JA tem global scope `business_id`. Verificar no `ArquivosService::attach` propagacao automatica (auditar antes de codar).
2. **LGPD retention** — fotos de veiculo NAO contem PII direta, mas placa Mercosul si. `Modules/Arquivos/Config/retention.php` precisa de bucket `oficina-auto-dvi` com TTL (proposta: 5 anos pos-conclusao OS, alinhado com prazo decadencial CDC art. 27).
3. **F3 anti-padroes (LICOES_F3_FINANCEIRO_REJEITADO)** — drawer fica em `ProducaoOficina/_components/`, nao em `resources/js/Pages/<Mod>/<Tela>.tsx`. Mas componente novo precisa **NAO** chamar window.print, **NAO** abrir nova aba, **NAO** dispatch evento global. Camera input nativo `capture="environment"` no mobile, fallback file picker no desktop.
4. **Hostinger != CT 100 (ADR 0062)** — uploads ficam em storage disk default (config). Hostinger storage path `/storage/app/...`. Octane Hostinger e proibido — file_put_contents normal funciona.
5. **Storage path naming multi-tenant** — `ArquivosService::attach` ja gera path com `business_id`. Confirmar em audit pre-implementacao.

## Mini-comparativo atual → target

| Aspecto | Hoje (placeholder) | Target Gap 1 |
|---|---|---|
| Botao "Adicionar foto" | disabled gray | enabled, abre camera mobile / file picker desktop |
| Persistencia | nenhuma | `arquivos` table polimorfica via HasArquivos |
| Multi-tenant | N/A | global scope business_id automatico |
| LGPD retention | N/A | 5 anos pos-conclusao OS (proposta) |
| LGPD audit | N/A | ArquivosService grava audit-log automatico |
| Crypto in-rest | N/A | VaultEncryptionService opcional (default off, plug per-business config) |
| Foto por item DVI | N/A | inline em cada `OaInspectionItem` (best-practice 2026) |
| Foto OS-level | N/A | Anexo geral no `ServiceOrder` (laudo PDF, foto chassi etc) |
| Thumbnail | N/A | Server-side via Image intervention (ja em uso em outros modulos? validar) |
| Bytes max upload | N/A | 10MB por foto (HEIC iPhone padrao ~3-4MB) |

## Esforco estimado

- Audit ArquivosService multi-tenant guard: 30min
- Backend (Controller + Request + 2 routes + traits): 1h
- Frontend (DviPhotoGrid + integracao drawer + Sheet preview): 1.5h
- 6 Pest specs: 1h
- Charter Tier B component: 30min
- Update SCOPE.md + retention.php config: 30min
- **Total: 4-6h IA-pair** (fator 10x ADR 0106) + 2h smoke real Wagner

## Smoke criteria (Wagner valida apos merge)

- [ ] biz=164 Martinho `/oficina-auto/ordens-servico/{id}`: clica "+Foto" em item DVI, escolhe foto galeria, sobe, thumbnail aparece
- [ ] Tinker biz=164: `Arquivo::where('arquivable_type', OaInspectionItem::class)->count()` cross-check contra biz=1 (deve ser 0)
- [ ] Lightbox: clica thumbnail, modal Dialog mostra full-size, ESC fecha
- [ ] Delete foto: trash icon, confirmation, foto some, audit-log registra
- [ ] Mobile real Android: `capture="environment"` abre camera traseira direto (nao galeria)
- [ ] Retention test: comando `php artisan arquivos:retention-cleanup` nao apaga foto recente (TTL future)

## Dependencias

- **PR independente** — nao depende de outros gaps
- Pre-req: confirmar `Modules/Arquivos` ja em prod biz=164 (`composer status` + migration check)
- Recomendado fazer ANTES do Gap 2 (DVI UI) pra DVI ja nascer com upload pronto

## DRAFT task pra Wagner copy-paste em tasks-create

```yaml
title: "Gap 1 — Upload foto/laudo real drawer OficinaAuto via Modules/Arquivos"
module: OficinaAuto
us: US-OFICINA-027-bis
priority: medium
estimated_hours: 6
owner_proposal: claude-paralelo
description: |
  Substituir placeholders V2 FOTOS & LAUDO no drawer ServiceOrderRichSheet por
  upload real via Modules/Arquivos trait HasArquivos polimorfica. Foto inline
  por item DVI (best-practice 2026 AutoVitals/Tekmetric) + anexo OS-level
  (laudo geral). Storage disk default (config/filesystems.php). LGPD retention
  5y. Audit log automatico. Cross-tenant guard via global scope ADR 0093.

  Pre-flight obrigatorio:
  - Confirmar Modules/Arquivos em prod biz=164
  - Audit ArquivosService propagacao business_id

  Output: 1 Controller endpoint + 1 Request + 2 routes + 2 trait uses +
  1 React component + 1 charter Tier B + 6 Pest specs + SCOPE.md update.

  Refs: ADR 0093, ADR 0123 (Arquivos backbone), ADR 0106 (10x estimate)
acceptance_criteria:
  - "Wagner biz=164 sobe foto via mobile, thumbnail aparece, lightbox abre"
  - "Cross-tenant test passa (Arquivo::where multi-biz cross-check)"
  - "Pest 6/6 verde local"
  - "Retention TTL respeitada"
```

## Refs

- [ADR 0093 Multi-tenant Tier 0](memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- ADR 0123 Modules/Arquivos backbone (canon)
- ADR 0106 Recalibracao 10x IA-pair
- [AutoVitals DVI Best Practices](https://blog.autovitals.com/digital-vehicle-inspection-best-practices)
- [Laravel Filesystem multi-tenant](https://tenancyforlaravel.com/docs/v2/filesystem-tenancy/)

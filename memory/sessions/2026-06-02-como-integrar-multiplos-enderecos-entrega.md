# como-integrar — Múltiplos endereços de entrega por contato

> Agente introspectivo `como-integrar` · 2026-06-02 · biz piloto ROTA LIVRE (4)
> Pergunta: permitir N endereços de entrega por `contacts`, hoje 1 endereço plano.
> **Veredito: AUSENTE — cria do zero.** Não há `contact_addresses` em lugar nenhum.

---

## Fase 1 — Inventário

| O que procurei | Onde achei | Status |
|---|---|---|
| Model `Contact` | `app/Contact.php` (UPOS guarda models no root `app/`, não `app/Models`) | existe, endereço plano |
| Tabela `contact_addresses` / `addresses` / polimórfica | nenhum lugar (migrations + Modules) | **ausente** |
| Campos endereço do contato | `address_line_1`, `address_line_2`, `numero`, `city`, `city_code`, `state`, `country`, `zip_code`, `landmark` | 1 endereço só, planos |
| Endereço de entrega do contato | `contacts.shipping_address` (text blob único) + `position` + `shipping_custom_field_details` (JSON cast) | 1 entrega só, texto livre |
| Snapshot de entrega na venda | `transactions.shipping_address` (text) + `shipping_details` + `shipping_status` + `delivered_to` + `delivery_person` + `shipping_custom_field_1..5` | já copia string no momento da venda |
| Conceito de N endereços em Crm/Sells/Compras/OficinaAuto | nenhum módulo tem | **ausente** |
| Como NF-e usa endereço | `NfeBrasil/Services/NfeService.php:156-166` lê `contact->address_line_1/2/city/state/zip_code` pro **destinatário** | sem bloco `enderEntrega` separado |
| Tela de venda (entrega) | `resources/js/Pages/Sells/Create.tsx:129-132,202` bloco `shipping {address, cost...}` string livre; blade legacy `resources/views/sale_pos/create.blade.php` | entrega = textarea |
| Charter da venda | `resources/js/Pages/Sells/Create.charter.md` existe | tocar se UI mudar |
| SPEC relacionada | `memory/requisitos/Cliente/SPEC.md:115` — `_form/EnderecoBRSection.tsx` (endereço único do cliente) | nada sobre N endereços |
| ADR sobre múltiplos endereços | nenhum | ausente |
| Task MCP aberta | não checado via tool (agente read-only); grep SPEC não achou US | provável ausente |

**Conclusão Fase 1:** feature 0% feita. Existe o endereço plano (1:1) e o snapshot de entrega na transaction. Nada de 1:N. Não há duplicação a evitar — **build do zero**, mas com forte impacto fiscal (NF-e) e de migração de dados.

### Anatomia do dado hoje (load-bearing)
- **Contato** carrega 2 endereços conceituais distintos: o *fiscal/cadastral* (`address_line_*`, `city`, `state`, `zip_code` — usado pelo destinatário NF-e) e o *de entrega* (`shipping_address` text + `position`).
- **Venda** (`transactions`) **snapshota** a string de entrega no fechamento. Esse snapshot é o que importa fiscalmente/operacionalmente — não pode "mudar retroativo" se o endereço do contato for editado depois.
- **NF-e hoje NÃO emite `enderEntrega`** (grupo G de local de entrega). Só destinatário. Logo a feature abre a porta pra um gap fiscal que precisa decisão consciente (ver pegadinha 4).

---

## Fase 2 — Pegadinhas aplicáveis (filtradas)

| # | Pegadinha | Aplicação direta aqui |
|---|---|---|
| 1 | **Multi-tenant Tier 0** (ADR 0093) | Nova tabela `contact_addresses` **precisa** `business_id` + global scope no model novo. Sem isso = vazamento cross-tenant. Index composto `(business_id, contact_id)`. Job/import que toque endereço passa `$businessId` no constructor. |
| 2 | **Schema `contacts` consolidado** (reference canon) | NÃO replicar `first_name/prefix`. O endereço novo segue o vocabulário BR já restaurado: `address_line_1/2`, `numero`, `city`, `city_code`, `state`, `zip_code`. Reusar nomes idênticos pra migração de dados ser trivial. |
| 3 | **Snapshot imutável na venda** | `transactions.shipping_address` é texto congelado no fechamento. Ao introduzir 1:N, a venda deve gravar `shipping_address_id` (FK opcional, rastreável) **E continuar materializando a string** no snapshot — não trocar FK por referência viva. Editar/deletar endereço do contato não pode alterar venda passada. |
| 4 | **NF-e `enderEntrega` (fiscal)** ⚠️ | Local de entrega ≠ destinatário. Se entrega for em UF/município diferente do destinatário, a NF-e **deveria** emitir grupo `enderEntrega`. `NfeService` hoje não monta isso. Introduzir N endereços sem decidir isso cria risco de NF-e divergente do físico. **Exige ADR ou decisão explícita Wagner** (escopo: emitir enderEntrega quando endereço de entrega ≠ destinatário?). NFe já emitida é imutável (cancela via SEFAZ, não forceDelete). |
| 5 | **Migração de dados endereço plano → 1:N** | `shipping_address` text livre dos contatos existentes (biz=4 prod) deve virar 1 linha `contact_addresses` `is_default=true`. Migration idempotente + `down()`. Não apagar coluna velha no mesmo PR (rollback safety) — fase de coexistência. |
| 6 | **Default address** | Exatamente 1 default por contato. `contacts` já tem coluna `is_default` (legado UPOS, semântica de "contato padrão", NÃO de endereço) — **não reusar**. Novo `contact_addresses.is_default` + guard de unicidade por `(business_id, contact_id)`. |
| 7 | **MWART canônico** (ADR 0104) + Charter | Qualquer mudança em `Sells/Create.tsx` (seletor de endereço de entrega) e no drawer de Cliente (`EnderecoBRSection.tsx`) passa pelas fases MWART + atualiza `.charter.md` ao lado. Ler RUNBOOK/PT antes do Edit. F3 lições (`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`). |
| 8 | **Pest biz=1, nunca cliente real** (ADR 0101) | Testes de relação/migração/default rodam em biz=1. Smoke em biz=4 (ROTA LIVRE) só leitura/canary controlado. |
| 9 | **PII/LGPD** | Endereço é PII. Não logar endereço completo em activity_log nem PR/commit. `Contact::getActivitylogOptions` hoje não loga endereço — manter assim pro model novo. |

**Observação (sem pegadinha documentada):** UPOS/Woocommerce/Connector (`Modules/Connector/.../ContactController`, `WoocommerceUtil`) ainda leem `shipping_address` string do contato. Migrar pra 1:N **quebra** esses consumidores se a coluna sumir. Atenção: manter accessor de compat (`Contact->shipping_address` retornando o default) durante coexistência.

---

## Fase 3 — Pontos de plugue (em ordem)

| Peça | Arquivo + linha | Ação |
|---|---|---|
| Migration tabela nova | `database/migrations/2026_06_02_xxxxxx_create_contact_addresses_table.php` ⚠️ criar | `business_id` FK + `contact_id` FK + campos BR (`address_line_1/2`, `numero`, `city`, `city_code`, `state`, `zip_code`, `country`, `label`, `recipient`, `phone`) + `is_default` bool + index `(business_id, contact_id)`. Nome de índice explícito (≤64 chars). `down()`. |
| Migration de dados | mesma ou `..._backfill_contact_addresses.php` ⚠️ criar | copiar `shipping_address`/address plano → 1 linha `is_default=true` por contato. Idempotente. NÃO dropar coluna velha. |
| Model novo | `app/ContactAddress.php` ⚠️ criar (UPOS root convention) | `belongsTo(Contact)`, global scope `business_id` (Tier 0), `$casts`, scope `default()`. |
| Relação no Contact | `app/Contact.php` (após linha ~140 das relações) | `hasMany(ContactAddress)` + `addressDefault(): hasOne` + accessor compat `getShippingAddressAttribute()` → string do default (mantém Connector/Woo funcionando). |
| Backend venda | `app/Http/Controllers/SellController.php:3384-3385` (lista de campos shipping) + ~2843 (snapshot) | aceitar `shipping_address_id`; ao salvar venda, materializar string no `transactions.shipping_address` (snapshot) + guardar FK. |
| transactions FK | `database/migrations/..._add_shipping_address_id_to_transactions.php` ⚠️ criar | `shipping_address_id` nullable FK (rastreável, NÃO substitui o text snapshot). |
| NF-e ⚠️ DECISÃO | `Modules/NfeBrasil/Services/NfeService.php:156-166` | decidir: emitir grupo `enderEntrega` quando endereço de entrega da venda ≠ destinatário? Hoje só destinatário. **Bloqueia escopo até Wagner decidir.** |
| Frontend cliente | drawer Cliente `EnderecoBRSection.tsx` (`memory/requisitos/Cliente/SPEC.md:115`) | virar lista de endereços (CRUD inline) em vez de 1 form. MWART + charter. |
| Frontend venda | `resources/js/Pages/Sells/Create.tsx:202` (bloco `shipping`) | trocar textarea livre por seletor dropdown dos endereços do contato + "novo endereço". MWART + `Create.charter.md`. |
| Charters | `Sells/Create.charter.md` + (criar) charter do drawer Cliente | atualizar Goals/UX. |
| Pest | `tests/Feature/Crm/ContactAddressTest.php` ⚠️ criar (biz=1) | cobertura: default único, isolamento business_id, snapshot venda imutável após edit/delete do endereço, accessor compat. |
| SPEC | `memory/requisitos/Cliente/SPEC.md` (ou `Crm/SPEC.md`) | criar US (ex: `US-CLI-NNN`) — múltiplos endereços de entrega. |

⚠️ = peça inexistente, criar como subtarefa.

---

## Fase 4 — Pré-código checklist

```markdown
## Pré-código checklist — múltiplos endereços de entrega por contato

### Antes de Edit/Write
- [ ] Ler RUNBOOK/PT do drawer Cliente + Sells/Create antes de tocar .tsx (MWART F1)
- [ ] Ler prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md antes de Controller+Page
- [ ] Feature flag necessária? SIM recomendado — `contact_multi_address` (coexistência com endereço plano até backfill validar em prod biz=4)
- [ ] Schema migration necessária? SIM — 3: create contact_addresses, add shipping_address_id em transactions, backfill dados (idempotente, com down())
- [ ] ADR nova necessária? SIM — decisão fiscal NF-e enderEntrega (escopo) + decisão modelo 1:N. Append-only.

### Pegadinhas a respeitar (filtradas)
- [ ] Tier 0: business_id + global scope no ContactAddress (ADR 0093)
- [ ] Vocabulário BR idêntico ao contacts (não reinventar campos)
- [ ] Snapshot transactions.shipping_address permanece string congelada; FK é só rastreio
- [ ] NF-e enderEntrega — decidir ANTES de codar (pode ser fora de escopo v1)
- [ ] is_default novo na tabela nova (NÃO reusar contacts.is_default legado)
- [ ] Accessor compat Contact->shipping_address pra não quebrar Connector/Woocommerce
- [ ] PII: não logar endereço; Pest biz=1

### Pontos de plugue (em ordem)
- [ ] Migration: create contact_addresses (business_id, contact_id, campos BR, is_default)
- [ ] Migration: add shipping_address_id em transactions
- [ ] Migration: backfill endereço plano -> 1 linha is_default
- [ ] Model: app/ContactAddress.php + relação/accessor em app/Contact.php
- [ ] Backend: SellController.php:3384 + snapshot ~2843 — aceitar shipping_address_id + materializar string
- [ ] NF-e: NfeService.php:156 — (se em escopo) montar enderEntrega
- [ ] Frontend: EnderecoBRSection.tsx -> CRUD lista; Sells/Create.tsx:202 -> seletor
- [ ] Charter: Sells/Create.charter.md + charter drawer Cliente
- [ ] Test: tests/Feature/Crm/ContactAddressTest.php (biz=1) — default único, isolamento, snapshot imutável, compat
- [ ] SPEC: memory/requisitos/Cliente/SPEC.md — US nova

### Smoke pós-deploy
- [ ] biz=1 (test): criar contato, 3 endereços, 1 default; venda com endereço B; editar endereço B -> venda intacta
- [ ] biz=4 (ROTA LIVRE, canary leitura): backfill gerou exatamente 1 default por contato; nenhum contato sem endereço perdido

### Estimativa total (IA-pair, ADR 0106)
- Backend (migrations + model + controller + accessor compat): ~3-4 h
- Migração de dados + validação biz=4: ~2 h (humano-limitado parcial)
- Frontend (drawer CRUD + seletor venda, MWART): ~4-6 h
- NF-e enderEntrega (SE em escopo): +3-4 h + risco fiscal (canary obrigatório)
- **Total v1 sem NF-e enderEntrega: ~9-12 h.** Com NF-e: ~14-18 h + canary.
```

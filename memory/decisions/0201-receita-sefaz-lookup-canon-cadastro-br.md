---
slug: 0201-receita-sefaz-lookup-canon-cadastro-br
number: 201
title: "Receita Federal + SEFAZ ConsultaCadastro é o padrão canon de coleta de dados cadastrais BR em entidades fiscais"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-27"
accepted_at: "2026-05-27"
accepted_via: "Wagner aprovou canon + validou prod oimpresso.com: distinguir no_cert vs sefaz_error vs env_homolog funcionando · PR #1749 deployed 16:20 UTC validado via fetch direto retornando reason=env_homolog correto · sessão `frosty-greider-83ab2f` 2026-05-27 — comando exato: 'merge'"
module: core
quarter: 2026-Q2
tags: [cadastral, receita-federal, sefaz, brasilapi, cnpj-lookup, certificado-a1, multi-tenant, autosave, persona-larissa, ADR-0186-chain-cert, ADR-0185-drawer-canon, ADR-0179-cliente-drawer, ADR-0195-tabs-mount-sempre]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - "0093-multi-tenant-isolation-tier-0"
  - "0094-constituicao-v2-7-camadas-8-principios"
  - "0105-cliente-como-sinal-guiar-sem-mandar"
  - "0179-cliente-drawer-760px-substitui-show-fullpage"
  - "0185-drawer-760-canon-entidades-cadastrais"
  - "0186-chain-certificado-sefaz-consulta-cadastro"
  - "0195-tabs-autosave-mount-sempre-hidden"
pii: false
review_triggers:
  - "Receita Federal mudar API BrasilAPI free-tier (rate limit, deprecação, custo) → revisar fonte primária"
  - "Custo SEFAZ ConsultaCadastro escalar (per-tenant ou per-uf) → adicionar gate quota"
  - "Frente regulatória BR atualizar protocolo (NFe 5.0 / Reforma Tributária 2026) → revisar payload"
  - "Nova entidade cadastral BR-fiscal entrar no projeto sem botão Buscar CNPJ → CI gate alerta"
  - "Power-user pedir desabilitar autosave on blur durante sessão Receita → reabrir Q UX"
---

# ADR 0201 — Receita Federal + SEFAZ ConsultaCadastro é o padrão canon de coleta de dados cadastrais BR

## Contexto

Cadastro de cliente/fornecedor/funcionário em ERP brasileiro tem 3 fontes possíveis de dados cadastrais (CNPJ/IE/endereço/contato):

| Fonte | Custo | Cobertura | Dados |
|---|---|---|---|
| **Usuário digita à mão** | grátis | 100% | sujeito a erro de digitação, duplicação, dados desatualizados |
| **BrasilAPI (Receita pública)** | grátis, sem cert | CNPJ válido | razão social, fantasia, endereço, telefone/email Receita |
| **SEFAZ ConsultaCadastro** | custo cert A1 + WS estadual | UFs supported (6 atuais: RS/SP/PR/MG/BA/SC) | + IE atual, situação cadastral, ind_ie_dest, regime apuração, endereço cadastro ICMS |

[ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md) + [ADR 0185](0185-drawer-760-canon-entidades-cadastrais.md) estabelecem o drawer 760 como pattern canônico pra entidades cadastrais. [ADR 0186](0186-chain-certificado-sefaz-consulta-cadastro.md) implementa chain de 3 camadas de cert (primário business → legacy → institucional fallback) pra que tenants sem cert próprio ainda usem SEFAZ via cert do oimpresso (biz=1). [ADR 0195](0195-tabs-autosave-mount-sempre-hidden.md) garante que dados preenchidos numa aba não somem ao trocar pra outra (mount-sempre).

Wagner 2026-05-27 (sessão `frosty-greider-83ab2f`): "porque não pegou os dados fiscais? pela consulta da receita federal com certificado isso é grave, esse método deve ser o padrão no sistema e manter os dados até salver em todas as telas com abas". Reportou cenário com cliente piloto (Antonella @ biz=4 Larissa, CNPJ teste Magazine Luiza 47.960.950/0001-21 SP) onde:
- BrasilAPI preencheu razão social/fantasia ✓
- SEFAZ-SP retornou erro genérico → UI dizia "Configure cert A1" mesmo com cert OK (corrigido em [PR #1743](https://github.com/wagnerra23/oimpresso.com/pull/1743) — distinguir `no_cert` de `sefaz_error`)
- Dados preenchidos pela Receita devem PERSISTIR durante toda a sessão de edição mesmo trocando entre tabs (já garantido por [ADR 0195](0195-tabs-autosave-mount-sempre-hidden.md))

A decisão até aqui era ad-hoc: piloto Cliente ([ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md)) implementou Buscar CNPJ no `IdentificacaoTab.tsx`. Faltava canonizar como padrão obrigatório.

## Decisão

**Toda tela de cadastro de entidade fiscal brasileira (CNPJ/CPF como identificador primário) DEVE oferecer botão "Buscar CNPJ" que executa lookup combinado Receita Federal + SEFAZ ConsultaCadastro.**

### Algoritmo canônico

Ordem de execução (paralela quando possível pra latência):

1. **Validação local mod-11** (frontend) — bloqueia botão se CNPJ inválido.
2. **GET `/cliente/lookup/cnpj/{cnpj}`** (BrasilAPI proxy server-side) — retorna `{razao_social, fantasia, ie, endereço, contatos}` em ~500ms-2s. SEM cert necessário.
3. **GET `/cliente/lookup/cnpj/{cnpj}/sefaz?uf=XX`** (SEFAZ ConsultaCadastro via cert A1 chain ADR 0186) — retorna `{ie, situacao, nome, ind_ie_dest, regime_apuracao, alertas}`. Dispara em paralelo com #2 quando UF já é conhecida; senão dispara após #2 revelar a UF.
4. **Merge por autoridade** (frontend `IdentificacaoTab.handleCnpjLookup`):
   - **Razão social**: SEFAZ se presente (cadastro ICMS pode estar mais atualizado), senão BrasilAPI.
   - **Fantasia**: só BrasilAPI tem.
   - **IE**: SEFAZ é autoridade única; BrasilAPI tem fallback geralmente vazio.
   - **Endereço**: BrasilAPI primário (Receita). SEFAZ tem endereço ICMS mas é secundário (cadastro estadual pode divergir).
   - **Contatos** (telefone/email): SÓ se vazio. Preserva contato real digitado pelo user (Receita pode estar desatualizada).
5. **Persist via PATCH discriminado por seção**:
   - `PATCH /cliente/{id}/identificacao` → razao_social, fantasia, ie, ind_ie_dest, sefaz_cad_*
   - `PATCH /cliente/{id}/endereco` → zip_code, address_line_1, neighborhood, city, state, city_code
   - `PATCH /cliente/{id}/contato` → email, mobile (só se vazio)
6. **Sincroniza state cross-tab** via `onContactUpdated({...})` no parent + `enderecoVersion++` pra forçar remount autorizado do EnderecoTab (sobrescrita pela Receita é explícita).
7. **Badge contextual** com `reason` discriminado pós-fix [PR #1743](https://github.com/wagnerra23/oimpresso.com/pull/1743):
   - `primary` (SEFAZ via cert próprio) → "Receita + SEFAZ-{UF} (seu certificado)"
   - `institutional` (SEFAZ via cert oimpresso) → "Receita + SEFAZ-{UF} (cert oimpresso — configure o seu)"
   - `unsupported` (UF fora whitelist) → "Receita preenchida. SEFAZ-{UF} não disponível — preencha IE manual"
   - `no_cert` → "Receita preenchida. Configure cert A1 em /fiscal/config pra IE automática"
   - `sefaz_error` → "Receita preenchida. SEFAZ-{UF} indisponível agora — tente de novo em alguns minutos"

### Telas elegíveis (obrigatório aplicar)

| Tela | Status | Tipo entidade | Migration |
|---|---|---|---|
| `Pages/Cliente/Index.tsx` (drawer) | ✅ implementado | Customer/Supplier/Employee/Representative ([ADR 0188](0188-contacts-multi-type-flag-aditiva.md)) | piloto |
| `Pages/Fornecedores/*` | pendente (ADR 0185 fila) | Supplier dedicado | reusa drawer Cliente via toggle |
| `Pages/Produto/*` | parcial | Não-fiscal (CNPJ não aplica) | N/A |
| `Pages/OficinaAuto/Vehicles/*` | pendente | Veículo (PJ proprietário) | drawer 760 + Buscar CNPJ no proprietário |
| `Pages/OficinaAuto/ServiceOrders/*` | pendente | OS (cliente fiscal) | reusa drawer Cliente |
| `Pages/RecurringBilling/Planos/*` | parcial | N/A direto | usa drawer Cliente no relacionamento |
| `Pages/Repair/DeviceModels/*` | pendente | N/A direto | usa drawer Cliente |
| `Pages/Settings/Business/*` | pendente | Próprio business | Buscar CNPJ pro próprio negócio |
| Modules/PaymentGateway tela credor/sacador | pendente | PJ recebedor | mesmo pattern |
| Tela Manifestação NFe (destinatário/emitente) | parcial | Cliente fiscal | já tem lookup separado, unificar |

### Preservação durante edição (combina ADR 0195)

**Telas com abas:** tabs cadastrais ficam mount-sempre via `<div hidden>`. Dados preenchidos pela Receita+SEFAZ permanecem visíveis e editáveis entre tabs durante toda a sessão até user clicar "Salvar" (forms tradicionais) ou autosave on blur completar (drawer 760). Trocar de aba NUNCA descarta trabalho.

**Drawer 760:** autosave on blur (debounce 800ms) persiste no DB imediatamente. Trocar aba e fechar drawer é seguro — dados já estão salvos. Exception: se SEFAZ retornar erro (5xx/timeout) DEPOIS do BrasilAPI sucesso, BrasilAPI permanece persistido. User vê badge `sefaz_error` e pode retry.

**Forms tradicionais (Edit.tsx/Create.tsx):** `useForm` Inertia preserva dirty state entre tabs/seções até submit. Beforeunload bloqueia navegação com edição não-salva.

### Quando NÃO aplicar

- Entidade não-fiscal (Produto, OS interna sem cliente, configurações de sistema).
- CPF de pessoa física só → BrasilAPI não tem (LGPD); SEFAZ pessoa física aceita mas tem proteção privacidade variável por UF. Pra PF, lookup CEP do endereço continua, mas dados pessoais NÃO podem vir da Receita pública.
- Cenário offline / sem internet → degrade graceful (form manual habilitado).
- CNPJ com mais de 1 estabelecimento (CEI/filiais) → escolher manualmente qual estabelecimento; BrasilAPI retorna o estabelecimento que o CNPJ raiz aponta.

## Justificativa

1. **Confiabilidade**: dados Receita Federal são autoridade única no Brasil pra cadastro PJ. Cadastro à mão tem erro médio 15-25% (typos em razão social, CNPJ inválido, endereço desatualizado) que vira rejeição NFe/NFSe depois. Lookup elimina classe inteira de bugs.
2. **Velocidade UX**: ~3s pra preencher 8-12 campos vs ~3min digitando (Larissa biz=4 testemunha — "muito mais rápido"). Estimativa cycle time cadastro: -85%.
3. **Compliance**: IE + ind_ie_dest + situação cadastral SEFAZ são gatekeepers pra emitir NFe sem rejeição (cód. 478/487/770 SEFAZ). Pegar antes da emissão = evita drama no momento da venda.
4. **Multi-tenant fair**: chain de cert ADR 0186 permite tenants sem cert próprio (Larissa biz=4) usarem o institucional do oimpresso (biz=1) com audit log LGPD — não exclui pequeno empresário sem cert A1 da feature.
5. **Reuso**: pattern do drawer Cliente já está testado (piloto biz=1 desde 2026-05-21, paridade Cowork 95%). Aplicar nos outros módulos é replicação, não invenção.
6. **Cliente como sinal** ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)): Wagner reportou explicitamente "esse método deve ser o padrão" — sinal qualificado de power user que usa diariamente.

## Consequências

**Positivas:**
- Pattern uniforme em todo cadastro PJ do projeto — onboarding de devs mais simples
- Eliminação de classe de bug "campos divergentes entre Receita e o que digitei"
- Reuso de chain cert ADR 0186 (sem duplicação de lógica fiscal)
- Combina naturalmente com ADR 0195 (tabs mount-sempre) — dados Receita preservados durante sessão completa
- Audit log LGPD do uso de cert institucional rastreável (mcp_audit_log)

**Negativas / Trade-offs:**
- **Custo SEFAZ**: cada consulta gasta latência WS estadual + risco rate limit per-CNPJ. Mitigação: cache Redis 30d (`fiscal.sefaz_consulta_cadastro_cache_ttl_seconds`) — dado público, mesmo CNPJ não bate SEFAZ 2x no período. Falhas NUNCA cacheadas pra permitir retry imediato ([PR #1743](https://github.com/wagnerra23/oimpresso.com/pull/1743)).
- **Custo BrasilAPI**: free tier limitado (~100req/min). Larissa biz=4 ~30 cadastros/dia = 5 lookups/min pico = cabe folgado. Power-users (Wagner @ biz=1) podem precisar tier pago futuro.
- **Cert obrigatório pra SEFAZ-completo**: empresa sem cert A1 perde IE/situação automática. Fallback institucional ADR 0186 mitiga durante onboarding.
- **UF whitelist limitada**: 6 UFs (RS/SP/PR/MG/BA/SC) atualmente. Outras 21 UFs precisam roadmap incremental — expansão depende de schema SEFAZ por UF (algumas têm endpoint, outras não suportam ConsultaCadastro).
- **Sobrescrita oficial vs preservação user-input** (política Wagner 2026-05-22): cadastrais oficiais SOBRESCREVEM (Receita é fonte da verdade); contatos pessoais SÓ se vazio (preserva contato real). Ambiguidade pode confundir user que esperava preservar tudo. Resolvido via badge contextual + autosave reversível.

**Riscos mitigados:**
- Cliente Larissa cadastrando à mão CNPJ errado → rejeição NFe na hora da venda (regressão de confiança no produto)
- Tickets suporte "preenchi tudo e perdi quando troquei de aba" (combina ADR 0195)
- Devs implementando lookup ad-hoc por módulo (duplicação de lógica, drift)

## Plano de implementação

### Onda 1 (já entregue) — Cliente piloto
- ✅ `Pages/Cliente/Index.tsx` drawer 760 com IdentificacaoTab.handleCnpjLookup
- ✅ Chain cert ADR 0186 com 3 camadas + audit log
- ✅ Distinguir no_cert vs sefaz_error (PR #1743)
- ✅ Tabs mount-sempre (ADR 0195)
- ✅ ConfigurarCert via `/fiscal/config` (Modules/Fiscal/ConfigController)

### Onda 2 (próximo cycle 2026-Q3)
- [ ] `Pages/Settings/Business/*` — Buscar CNPJ pro próprio business (preenche razão social, IE, endereço completo). Usa cert do próprio biz se já tem; caso contrário, BrasilAPI apenas.
- [ ] `Pages/Fornecedores/*` (quando entrar drawer 760 fila ADR 0185)
- [ ] `Pages/OficinaAuto/Vehicles/*` proprietário PJ
- [ ] Unificar lookup Manifestação NFe (destinatário/emitente) com pattern canon

### Onda 3 (Q4 ou conforme demanda cliente)
- [ ] Demais entidades fila ADR 0185
- [ ] Expansão UFs SEFAZ (priorizar SC = Larissa biz=4)
- [ ] BrasilAPI tier pago se algum tenant escalar
- [ ] Lookup batch (CSV import / migração massa)

### CI Gate proposto

```yaml
# .github/workflows/buscar-cnpj-gate.yml (futuro)
name: Buscar CNPJ canon gate
on: pull_request
jobs:
  check:
    - Detecta novas Pages/<Mod>/Index.tsx que toquem entidade fiscal
    - Verifica presença de IdentificacaoTab + handleCnpjLookup OU equivalente
    - Falha PR se entidade fiscal NÃO tem botão Buscar CNPJ
```

## Referências

- [ADR 0179](0179-cliente-drawer-760px-substitui-show-fullpage.md) — Drawer 760 Cliente piloto
- [ADR 0185](0185-drawer-760-canon-entidades-cadastrais.md) — Drawer 760 escala pras outras entidades
- [ADR 0186](0186-chain-certificado-sefaz-consulta-cadastro.md) — Chain de 3 camadas de cert A1
- [ADR 0188](0188-contacts-multi-type-flag-aditiva.md) — Flags multi-papel customer/supplier/employee/representative
- [ADR 0195](0195-tabs-autosave-mount-sempre-hidden.md) — Tabs mount-sempre preserva dados Receita
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente reportou = backlog qualificado
- [PR #1743](https://github.com/wagnerra23/oimpresso.com/pull/1743) — Fix distinguir no_cert vs sefaz_error
- Implementação canônica: [resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx](../../resources/js/Pages/Cliente/_drawer/IdentificacaoTab.tsx) `handleCnpjLookup`
- Sessão de origem: `frosty-greider-83ab2f` 2026-05-27 — bugs troca-de-aba apaga + cert message engano

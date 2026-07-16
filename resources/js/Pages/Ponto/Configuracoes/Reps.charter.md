---
page: /ponto/configuracoes/reps
component: resources/js/Pages/Ponto/Configuracoes/Reps.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Ponto
related_us: [US-PONT-005]
related_adrs: [114, 101, 93, 182]
tier: B
charter_version: 1
---

# Page Charter — /ponto/configuracoes/reps (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Ponto/Http/Controllers/ConfiguracaoController@reps` / `@storeRep` (rotas `ponto.configuracoes.reps` / `.reps.store`, permissão `ponto.access`). Registro dos Registradores Eletrônicos de Ponto (REP-P/C/A) conforme Portaria MTP 671/2021.

---

## Mission
O gestor gerencia o cadastro dos REPs (dispositivos registradores de ponto) do business. A tela lista os REPs cadastrados (tipo, identificador de 17 caracteres, descrição, local, ativo) e oferece um formulário lateral para cadastrar novos, seguindo o Anexo I da Portaria (identificador = CNPJ 14 + sequencial 3).

---

## Goals — Features (faz)
- Lista dos REPs cadastrados (tipo, identificador, descrição, local, ativo).
- Formulário de cadastro de REP: tipo (REP_P/C/A), identificador (17 chars), descrição, local, CNPJ → `POST /ponto/configuracoes/reps`.
- Validação: identificador único de 17 chars; tipo restrito ao enum.
- Toast de sucesso/erro; form reseta após cadastro.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não edita nem inativa REP existente na UI (só cadastra e lista) — confirmar com Wagner se edição/inativação entra depois.
- ❌ Não lista REP de outro business — escopado por `business_id` (Tier 0 multi-tenant).
- ❌ Não coleta/valida marcações do REP — coleta é do fluxo de importação/API; marcações são append-only (Portaria MTP 671/2021).
- ❌ Não gera certificado/assinatura do dispositivo.

---

## UX targets
- p95 < 1500ms (admin) / < 800ms (produção) ; cabe em 1280px (ROTA LIVRE) ; AppShellV2.

---

## Automation hooks (faz)
- `storeRep` injeta `business_id` no create automaticamente (scope tenant).

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não cadastra sem submit explícito.
- ❌ Não muta em GET (cadastro é POST validado).
- ❌ Não sincroniza REPs de fonte externa sozinha.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Definir se editar/inativar REP entra no escopo desta tela

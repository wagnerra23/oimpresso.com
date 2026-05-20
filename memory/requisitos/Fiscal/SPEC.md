---
module: Fiscal
status: em-implementacao (PR #1 NF-e cockpit)
piloto: oimpresso biz=1 (Wagner empresa) — depende de NfeBrasil já produção
last_review: 2026-05-20
owner: wagner
parent_adr: 0094
related_adrs: [0093, 0101, 0104, 0114, 0143]
na_justified:
  D7.a: "Fiscal cockpit é leitura agregada — não emite. PII (CPF/CNPJ destinatário) vem de NfeBrasil via getActivitylogOptions excluindo PII (PII-LGPD-FISCAL.md). Cockpit apenas exibe via Inertia props já redacted no Service NfeBrasil."
---

# Especificação funcional — Fiscal (Cockpit unificado)

> Convenção do ID: `US-FISCAL-NNN` para user stories, `R-FISCAL-NNN` para regras Gherkin.
> Campo `Implementado em` linka com a página React.
>
> **Módulo thin agregador** — NÃO contém lógica fiscal própria (emissão, SEFAZ, cancelamento). Lê `Modules/NfeBrasil` + `Modules/NFSe` via Services. Pareado com [SCOPE.md](../../../Modules/Fiscal/SCOPE.md).

## 1. Glossário rápido

- **Cockpit fiscal** — visão unificada agregando NF-e/NFC-e + NFS-e + DF-e + Eventos + Cert/Cfg + SPED
- **Sub-página** — uma das 7 telas do design KB-9.75 (Cockpit, NF-e, NFS-e, DF-e, Eventos, Cert, SPED)
- **SEFAZ pill** — badge colorido por tom (ok/warn/bad) com código cstat + label + hint
- **Pílula temporal** — chip mostrando prazo restante de ação legal (cancel 24h NFC-e / 168h NF-e, CC-e 30d)
- **Mapa "Jana sugere"** — receita determinística por cstat rejeitado (substitui IA real per R#2 KB-9.75)

Vocabulário completo NFe/NFSe em [NfeBrasil/GLOSSARY.md](../NfeBrasil/GLOSSARY.md).

## 2. User stories

### US-FISCAL-001 · Cockpit NF-e · NFC-e (sub-página 2)

> **Área:** Fiscal/Nfe
> **Rota:** `GET /fiscal/nfe`
> **Controller/ação:** `NfeCockpitController@index`
> **Permissão Spatie:** `fiscal.nfe.view`
> **Status:** PR #1 #1183

**Como** contador (Eliana) ou operador fiscal (Wagner)
**Quero** ver lista consolidada de NF-e + NFC-e emitidas com status SEFAZ legível, janela legal de cancelamento, e drawer detalhado com mapa SEFAZ guiado
**Para** identificar rejeições, agir dentro da janela de 24h (NFC-e) / 168h (NF-e), e resolver bloqueios sem precisar abrir 4 telas separadas

**Implementado em:** [`resources/js/Pages/Fiscal/Nfe.tsx`](../../../resources/js/Pages/Fiscal/Nfe.tsx) · [`Modules/Fiscal/Http/Controllers/NfeCockpitController.php`](../../../Modules/Fiscal/Http/Controllers/NfeCockpitController.php)

**Definition of Done:**
- [x] Lista paginada `NfeEmissao` via `HasBusinessScope` global scope (ADR 0093 multi-tenant Tier 0)
- [x] Sub-tabs NFe(55) / NFCe(65) / Entrada (entrada = empty state com link Compras)
- [x] Filtros chip-row: Todas, Autorizadas, Rejeitadas, Janela 24h, Processando
- [x] Tabela com Número (mono) + Chave truncada + SEFAZ pill (tone ok/warn/bad) + Valor + Emissão
- [x] Pílula temporal "cancelar em Xh" inline na linha
- [x] Atalhos J/K (navega cursor) + Enter (abre drawer) + search box
- [x] `Inertia::defer` em rows (skill `inertia-defer-default`)
- [x] Drawer slide-in com SEFAZ pill expandida + dados destinatário + operação + mapa "Jana sugere" se cstat rejeitado
- [x] Pest test biz=1 vs biz=99 (ADR 0101 — global scope, isCancelavel, sefazCodes mapping)
- [x] Charter `Nfe.charter.md` com Goals + Non-Goals + Anti-hooks
- [x] CSS scoped `.fx-page` (não vaza tokens fiscais pra outras telas)

### US-FISCAL-002 · Cockpit (sub-página 1) — **backlog PR #2**

KPIs + alertas + quick links + mini-sparklines. Detalhe quando entregar.

### US-FISCAL-003 · ⌘K palette cross-fiscal — **backlog PR #3**

Busca global em notas + DF-e + ações rápidas. Detalhe quando entregar.

### US-FISCAL-004 · Ações de mutação — **backlog PR #4**

Cancelar / Retransmitir / CC-e / Inutilizar. Chama Services `Modules/NfeBrasil` existentes via Job. NOT habilitado neste PR #1 (botões disabled no drawer).

### US-FISCAL-005 · NFS-e (sub-página 3) — **backlog PR #5**

Lê `Modules/NFSe/Models/NfseEmissao`. Mesma arquitetura thin.

### US-FISCAL-006 · Manifesto DF-e (sub-página 4) — **backlog PR #6**

Lê `Modules/NfeBrasil/Models/NfeDfeRecebido`.

### US-FISCAL-007 · Eventos + Cert/Cfg + SPED — **backlog PR #7**

Sub-páginas 5, 6, 7 — podem ser PR único ou subdivididas.

## 3. Regras Gherkin

### R-FISCAL-001 · Isolation multi-tenant Tier 0 (ADR 0093)

```
Given um usuário autenticado com business_id=1
And NfeEmissao tem registros pra business_id=1 e business_id=99
When ele acessa GET /fiscal/nfe
Then a lista deve conter SOMENTE notas business_id=1
And counts (rejeitadas, autorizadas) refletem somente business_id=1
```

Test: `Modules/Fiscal/Tests/Feature/NfeCockpitMultiTenantTest::it global scope HasBusinessScope esconde emissões cross-tenant na contagem do cockpit`.

### R-FISCAL-002 · Janela legal cancelamento (CONFAZ SINIEF 07/2005 Art. 14)

```
Given uma NF-e (modelo 55) autorizada emitida há 100h
When o cockpit calcula isCancelavel
Then deve retornar true (porque 100h < 168h prazo NF-e)

Given uma NFC-e (modelo 65) autorizada emitida há 30h
When o cockpit calcula isCancelavel
Then deve retornar false (porque 30h > 24h prazo NFC-e)
```

Test: `NfeCockpitMultiTenantTest::it isCancelavel respeita janela legal 24h NFC-e (modelo 65) vs 168h NF-e (modelo 55)`.

### R-FISCAL-003 · Permission gate por sub-feature

```
Given um usuário com permission fiscal.access mas NÃO fiscal.nfe.view
When ele acessa GET /fiscal/nfe
Then deve receber 403 Forbidden
```

(superadmin bypass via `auth()->user()->can('superadmin')` cobre todos gates fiscal.*).

## 4. Não-goals (PR #1)

- Ações de mutação (Cancelar/Retransmitir/CC-e/Inutilizar) — drawer mostra botões desabilitados com title="PR seguinte"
- Emissão nova (botão Emitir disabled)
- ⌘K palette completa
- JOIN com `transactions`/`contacts` pra dest_name correto (fallback `metadata->dest_name` neste PR)
- NFS-e, DF-e, SPED, Cert/Cfg, Eventos (sub-páginas separadas)

## 5. Roadmap PRs

| PR | Sub-página(s) | Esforço IA-pair | Score impact |
|---|---|---|---|
| #1 ✅ aberto #1183 | NF-e · NFC-e (cockpit + drawer) | 1 dia | base 0→60/100 |
| #2 | Cockpit (KPIs + alertas + quick) | 4h | +10pp |
| #3 | ⌘K palette cross-fiscal | 6h | +8pp |
| #4 | Ações mutação (cancelar/retx/CC-e/inut) | 1-2 dias | +15pp (core valor) |
| #5 | NFS-e | 4h | +5pp |
| #6 | Manifesto DF-e | 4h | +4pp |
| #7 | Eventos + Cert/Cfg + SPED | 1 dia | +8pp |

**Meta:** Score Capterra Fiscal cockpit ≥ 80/100 pós-PR #4 (Wagner aprova).

---

- **v1.0.0** (2026-05-20) — SPEC.md inicial criado em PR #1183 (Fiscal cockpit NF-e). Módulo novo thin agregador.

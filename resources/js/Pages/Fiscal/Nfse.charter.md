---
id: resources-js-pages-fiscal-nfse-charter
page: /fiscal/nfse
component: resources/js/Pages/Fiscal/Nfse.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
page_id: fiscal-nfse
url: /fiscal/nfse
module: Fiscal
status: draft
created: 2026-05-20
owner: wagner
related_us: [US-FISCAL-005]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0094-constituicao-v2-7-camadas-8-principios, 0101-tests-business-id-1-nunca-cliente, 0104-processo-mwart-canonico-unico-caminho]
prototypes:
  - "prototipo-ui/.../fiscal-page.jsx §10 FiscalNFSePage"
---

# Charter — `Fiscal/Nfse`

## Mission

Lista navegável de **NFS-e emitidas** (Sistema Nacional NT 2024-001 — substitui emissores municipais legacy) com filtros por status + competência + busca, agregada no cockpit Fiscal.

## Goals (DoD PR #2)

1. **Lista paginada** NfseEmissao via HasBusinessScope (ADR 0093) — modelo nacional 56
2. **Filtros chip-row**: Todas, Autorizadas, Rejeitadas, Processando (pending+sent), Canceladas
3. **Seletor competência** (month picker) — default mês corrente, drill-down past months
4. **Busca**: número NFS-e + código verificação + CPF/CNPJ tomador
5. **Inertia::defer** em rows (skill inertia-defer-default)
6. **Permissão** `fiscal.nfse.view`
7. **Pest biz=1**: isolation + permission gate

## Non-Goals (PR #2)

- ❌ Drawer detalhe NFS-e (drawer dedicado vem em PR futuro — por enquanto title hover mostra error_msg)
- ❌ Emissão nova (botão Emitir não existe nessa tela — flow via /sells)
- ❌ Cancelamento UI (varia por município — backlog)
- ❌ Download PDF NFS-e (rota em Modules/NfeBrasil)

## Anti-hooks

- 🚫 Não acessar NfseEmissao sem global scope
- 🚫 Não usar PHP `is_numeric()` na busca — `preg_replace('/\D/', '', $s)` pra CPF/CNPJ
- 🚫 Não JOIN com `transactions` ainda — dados já em `NfseEmissao->tomador_cnpj` / `->tomador_cpf` <!-- fato corrigido 2026-07-27: dizia `cpf_cnpj_tomador`, coluna que NÃO existe (schema race, recibo no rodapé). A intenção do anti-hook é literalmente a mesma. -->
- 🚫 Não mostrar `error_msg` completo na tabela (só hover/title) — pode conter PII

---

## Reconciliação factual — 2026-07-27 (`sdd-from-source`, Fase 2.6)

**Só FATO foi corrigido. Nenhuma intenção (Mission, Goals, Non-Goals, Anti-hooks) foi tocada.**

| O que dizia | O que é | Evidência |
|---|---|---|
| anti-hook citava `NfseEmissao->cpf_cnpj_tomador` | a coluna **não existe**; as reais são `tomador_cnpj` e `tomador_cpf` | varredura contada `grep -rn "cpf_cnpj_tomador" Modules/ resources/js/ database/` = **3** ocorrências, e **as outras 2 são comentários explicando que ela não existe** (`NfseCockpitController` docblock + cabeçalho do `NfseCockpitControllerTest`). Colunas reais no `$fillable` de `Modules/NFSe/Models/NfseEmissao.php` |

**Causa:** duelo de duas migrations para `nfse_emissoes` — a nova (com `cpf_cnpj_tomador`, `value_servico`, `emitted_at`) nunca rodou em produção porque a tabela já existia. O Controller foi revertido para o schema antigo, traduzindo os estados PT→EN. O anti-hook ficou congelado no vocabulário do schema que não existe.
A **intenção** ("não fazer JOIN com `transactions` ainda") continua idêntica — só o nome da coluna foi acertado.

Contexto completo: [`memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md`](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md) §5.2 e §5.4.4 · contrato de teste em [`Nfse.casos.md`](Nfse.casos.md).

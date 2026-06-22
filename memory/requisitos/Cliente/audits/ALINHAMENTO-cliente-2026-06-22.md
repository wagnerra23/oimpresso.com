---
tipo: alinhamento-fidelidade
tela: Cliente (cadastro)
modulo: Crm
spec: memory/requisitos/Crm/SPEC-us-063-078.md
base: origin/main@3cf2b52
data: "2026-06-22"
autor: W+Claude (skill /alinhar-tela — instância #1)
status: relatorio
---

# Alinhamento de fidelidade #1 — Cliente (cadastro)

> Primeira execução do padrão `/alinhar-tela` ("a tela foi construída; agora ligamos a máquina por baixo e vemos o que está pronto vs o que falta"). Base verificada: `origin/main@3cf2b52` (2026-06-22). Tudo aqui é **verificado por `existsSync`/grep no código**, não declarado.

## Cabeçalho-padrão (igual pra toda tela)

```
TELA: Cliente (cadastro)  ·  SPEC: SPEC-us-063-078.md  ·  base @3cf2b52
✅ PRONTO   13 US  (063·064·065·066·067·068·069·070·071·072·073·074  + 075·076 reconciliadas)
⬜ FALTA     1 US   (078 PR3 — seletor de endereço salvo na venda)
🔒 TRAVADO   0 US   (gate visual R2/R7 já não bloqueia nada pendente exceto PR3)
⚠️ DRIFT     5 achados (corrigidos na spec nesta passagem)
FIDELIDADE  degrau 1 → 2 (âncoras ADR 0273 aplicadas) · machine-read PENDENTE (ver §Ligar a máquina)
PRONTO      ~93% (14/15 US com código; 1 parcial)
```

## ✅ PRONTO — US com código verificado

| US | O quê | Âncora (existe @3cf2b52) | Teste Pest |
|---|---|---|---|
| 063 | Tab Pagamentos | `_show/PaymentsTab.tsx` | — |
| 064 | Tab Ledger (parcial) | `_show/LedgerTab.tsx` + `LedgerController.php` | — |
| 065 | Tab Vendas | `_show/SalesTab.tsx` | `ClienteSalesJsonEndpointTest` |
| 066 | Tab Documentos/Notas | `_show/DocumentsTab.tsx` | `ClienteAnexosEndpointTest` |
| 067 | ActionsMenu + Discount | `_show/ActionsMenu.tsx` + `AddDiscountModal.tsx` | — |
| 068 | Pessoas de contato | `_show/PessoasContatoTab.tsx` | — |
| 069 | Assinaturas | `_show/SubscriptionsTab.tsx` | — |
| 070 | Reward Points | `_show/RewardPointsTab.tsx` | — |
| 071 | ⌘K palette no Index | `Pages/Cliente/Index.tsx` | `ClienteIndexDrawer760CharterTest` |
| 072 | Restaura 10 campos BR | migration `restore_br_fields` + `Rules/BR/CpfCnpj.php` | — |
| 073 | UI campos BR | `_form/DadosFiscaisBRSection.tsx` | — |
| 074 | Comando backfill cpf_cnpj | `BackfillCpfCnpjCommand.php` | — |
| 075 | BrasilAPI lookup CNPJ | `ClienteLookupController.php` + `BrLookupService.php` | `ClienteLookupCnpjCepTest` |
| 076 | FormRequest `CpfCnpj` server-side | `StoreContactRequest.php` + `UpdateContactRequest.php` | — |

> Cobertura de teste por US ainda é **parcial** (degrau 4): só 4 das 14 US prontas têm um Pest 1:1 amarrado. Há ~25 testes em `tests/Feature/Cliente/`, mas a maioria não está ligada a uma US específica. Subir o degrau 4 = amarrar 1 teste por US `done`.

## ⬜ FALTA — trabalho real restante

| US | O quê | Onde plugar | Estimate |
|---|---|---|---|
| 078 PR3 | Dropdown "Endereço de entrega" lista endereços salvos do cliente (hoje `Sells/Create` usa `shipping_address` texto livre) | `resources/js/Pages/Sells/Create.tsx` (~L1846) + `_components/SaleSheet.tsx` | 3h |

Backlog secundário (não bloqueia "pronto"): ViaCEP automático · Tab Atividades inline · Contact picker no header · Ledger inline 100% (gap US-064) · bulk merge duplicados · mobile <1100px · Suframa/indicador_ie · saved views.

## ⚠️ DRIFT encontrado (6) — corrigido na spec nesta passagem

1. **Charter Show dizia "v2 live", arquivo é `superseded`** (ADR 0179: drawer 760px substituiu o full-page). → tabela de escopo corrigida.
2. **US-075 marcada "backlog/futuro" — já está no código** (`ClienteLookupController::cnpj` + `BrLookupService` + Pest). → status `done` + âncora.
3. **US-076 marcada "backlog/futuro" — já está no código** (`Store/UpdateContactRequest` aplicam `new CpfCnpj`). → status `done` + âncora.
4. **US-078 status defasado** ("PR1 em andamento") — na real PR1+PR2 landed (`ContactAddressController` + `_drawer/EnderecosEntregaList.tsx`); só PR3 falta. → reclassificada.
5. **US-077 não existe** (us_list pula 076→078) + **Pages Import/Ledger/Map** existem em produção e não estavam na tabela de escopo. → documentado.
6. **8 links de doc quebrados** na tabela de escopo (apontavam `RUNBOOK-index.md`/`index-visual-comparison.md`; os arquivos reais são `RUNBOOK-cliente-index.md`/`cliente-index-visual-comparison.md` etc.). → corrigidos (evita regressão na catraca `charter_refs_broken`).

Drift menor não-corrigido (só anotado): `_form/EnderecoBRSection.tsx` e `_show/DadosFiscaisBRBlock.tsx` citados em US-073 não existem com esse nome (migraram pro drawer) — âncora aponta o que existe.

## 🔌 Ligar a máquina (decisão estrutural — Wagner)

**Achado-chave:** `scripts/governance/anchor-lint.mjs` (a "máquina") só varre arquivos chamados **exatamente `SPEC.md`** (glob `memory/requisitos/*/SPEC.md`). A spec viva do cadastro é **`SPEC-us-063-078.md`** → **a máquina não lê as âncoras que acabamos de criar.** Hoje ela só lê `Crm/SPEC.md`, que é o *pipeline pré-venda silenciado* (não o cadastro).

Resultado: o cadastro está **degrau 2-ready** (âncoras corretas e verificadas) mas **não machine-checked**. Pra fechar o circuito, uma destas decisões (todas do Wagner — módulo silenciado + a opção C mexe em tooling compartilhado):

- **(A) Renomear `SPEC-us-063-078.md` → `Crm/SPEC.md`** e arquivar o pipeline silenciado como `SPEC-pipeline-prevenda-legado.md`. Faz o cadastro virar o SPEC canônico que a máquina lê. _Recomendado_ — alinhado com a desambiguação "Crm É o módulo de Cliente" e com o silêncio do pipeline.
- **(B) Consolidar** as US do cadastro dentro do `Crm/SPEC.md` atual. Mistura com o pipeline silenciado — não recomendado.
- **(C) Ensinar `anchor-lint.mjs` a varrer `SPEC*.md`** — mexe em ferramenta compartilhada (afeta todos os módulos, baseline, ADR 0273 §scope). Precisa de emenda à ADR 0273.

Enquanto a decisão não vem: âncoras ficam corretas e prontas; a verificação roda manual (script da skill).

## Como reproduzir / próxima tela

`/alinhar-tela <Mod>/<Tela>` — ver [.claude/skills/alinhar-tela/SKILL.md](../../../../.claude/skills/alinhar-tela/SKILL.md).

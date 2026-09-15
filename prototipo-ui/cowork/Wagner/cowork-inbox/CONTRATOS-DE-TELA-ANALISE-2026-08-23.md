---
id: cowork-contratos-de-tela-analise-2026-08-23
tipo: analise/conciliacao
fonte: wagnerra23/oimpresso.com@main (tree d1ccdff91be9, lido 2026-08-23T15:18Z)
autor: "[CC]"
decisao_pendente: "[W]"
---

# Contratos de Tela — inventário completo + análise do trio

> Tudo abaixo foi **lido do `main` neste turno**. Nada de cache. O que não li, digo que não li.

## 1. Lista completa — 13 arquivos em `prototipo-ui/contrato/`

| # | Arquivo | Papel | Tela | Alvo |
|---|---|---|---|---|
| — | `contract.schema.json` | schema (ADR 0286) | — | — |
| — | `EXEMPLO.contract.json` | template, **inativo por design** (falha de propósito) | Atendimento/CaixaUnificada | Modules/Whatsapp/…/CaixaUnificada |
| 1 | `caixa-unificada.contract.json` | **ativo** (piloto da catraca) + `acordos_estado` | Atendimento/CaixaUnificada | Modules/Whatsapp/…/CaixaUnificada |
| 2 | `jana-painel.contract.json` | **ativo** (2º do repo) | Jana/Index | resources/js/Pages/Jana/Index.tsx |
| 3 | `backup.contract.json` | contrato de copy | Backup/Index | resources/js/Pages/Backup |
| 4 | `modulos.contract.json` | traduzido do [CC], recorte 5/7 | Modules/Index | resources/js/Pages/Modules/Index.tsx |
| 5 | `ponto-painel.contract.json` | alvo corrigido na descida | Ponto/Dashboard/Index | …/Ponto/Dashboard/Index.tsx |
| 6 | `ponto-espelho.contract.json` | 2 alvos (Show + Index) | Ponto/Espelho/Show | …/Ponto/Espelho/{Show,Index}.tsx |
| 7 | `superadmin-dashboard.contract.json` | recorte 4/10 | superadmin/Dashboard | Modules/Superadmin/…/Dashboard/Index.tsx |
| 8 | `superadmin-negocios.contract.json` | recorte 4/7 | superadmin/Negocios | …/Negocios/Index.tsx |
| 9 | `superadmin-assinaturas.contract.json` | recorte 5/6 | superadmin/Assinaturas | …/Assinaturas/Index.tsx |
| 10 | `superadmin-pacotes.contract.json` | recorte 1/2 | superadmin/Pacotes | …/Pacotes/Index.tsx |
| 11 | `financeiro-unificado.intent.json` | **outro schema** — contrato de INTENÇÃO (fluxos/deve_conter) | Financeiro/Unificado | …/Financeiro/Unificado/Index.tsx |

## 2. Trio por tela (.tsx + .charter.md + .casos.md) — medido por árvore

| Tela | .tsx | charter | casos | Contrato | Veredito |
|---|---|---|---|---|---|
| superadmin/Assinaturas | ✅ | ✅ | ✅ | ✅ | **trio completo + contrato** |
| superadmin/Dashboard | ✅ | ✅ | ✅ | ✅ | **completo** |
| superadmin/Negocios | ✅ | ✅ | ✅ | ✅ | **completo** |
| superadmin/Pacotes | ✅ | ✅ | ✅ | ✅ | **completo** |
| Backup/Index | ✅ | ✅ | ✅ | ✅ | **completo** (dupla em `design-docs/handoff/`) |
| Financeiro/Unificado/Index | ✅ | ✅ | ✅ | ⚠️ intent | completo, mas contrato é de OUTRO tipo |
| Ponto/Espelho/Show | ✅ | ✅ | ✅ | ✅ | **completo** |
| Ponto/Espelho/Index | ✅ | ✅ | ✅ | ✅ (alvo 2) | completo |
| Jana/Index | não verifiquei | ✅ | ✅ | ✅ | .tsx escapou do meu filtro — **não afirmo** |
| Modules/Index | não verifiquei | ✅ | ✅ | ✅ | idem |
| **Ponto/Dashboard/Index** | ✅ | ✅ | ❌ | ✅ | **trio quebrado — falta casos.md** |
| **Atendimento/CaixaUnificada** | ✅ | ✅ | ❌ | ✅ (ativo!) | **trio quebrado — falta casos.md** |
| Financeiro/Unificado/Novo | ❌ | ✅ | ✅ | ❌ | charter+casos órfãos, sem tela |

## 3. Achados encadeados

1. **Duas telas com contrato ATIVO/descido rodam sem `casos.md`** — Ponto/Dashboard e CaixaUnificada. Pelo `prototipo-readiness.mjs`, nenhuma das duas é ✅ pronta, embora a catraca de copy já rode nelas. É o gap mais barato de fechar.
2. **`financeiro-unificado` está fora do schema** — é `.intent.json` (`fluxos[]/deve_conter/nao_pode_conter`), não `secoes[]`. O `contract.schema.json` não o valida. Ou vira família declarada (`intent.schema.json`), ou o Financeiro fica sem contrato de copy. **Decisão [W].**
3. **Nenhuma tela do nível Norte do Cowork tem contrato** — Clientes/CRM, Atendimento (índice), Oficina Auto, PT-01/05/07. A cobertura é 100% Ponto/Superadmin/Financeiro/Backup.
4. **Recortes honestos, mas somam dívida**: superadmin-dashboard 4/10, negocios 4/7, assinaturas 5/6, pacotes 1/2, modulos 5/7. São ~14 seções desenhadas e não contratadas — a doutrina ("contrato que nasce vermelho ensina a ignorar o gate") está certa, mas ninguém tem a lista somada. Esta é a lista.
5. **Duas paginações divergentes pendentes de [W]**: F1 pede 6/página, produção usa 20 — em negócios E assinaturas. Mesma decisão, dois contratos.
6. **`jana-painel` tem `fonte` apontando pra tela viva**, não pra protótipo — legítimo e documentado, mas é o único caso; qualquer script que assuma "fonte = protótipo" quebra nele.
7. **Pendências [W] catalogadas nos contratos**: 2 em ponto-painel, 2 em ponto-espelho, 3 em jana-painel, 2 de paginação superadmin = **9 decisões abertas** dentro de arquivos de contrato.

## 4. O que eu criei neste turno

- `cowork-inbox/ponto-dashboard/Index.casos.md` — o casos.md faltante, no padrão do `main` (frontmatter + tabela de rastreabilidade + UC com Dado/Quando/Então), derivado do charter `/ponto` e do `ponto-painel.contract.json`.
- CaixaUnificada: **não escrevi**. O charter tem 33 KB e eu não o li — escrever UC sem ler a lei da tela seria inventar. Peça e eu leio no próximo turno.

## 5. Ponte

Nada disso está no git — não escrevo lá. Vira Issue `cowork-intake` ou você cola 1×.

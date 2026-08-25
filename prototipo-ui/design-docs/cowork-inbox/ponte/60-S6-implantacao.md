---
sessao: S6
titulo: Implantação em produção — F3 → F3.5
autor: "[CC]"
criado: 2026-08-23
base: wagnerra23/oimpresso.com@main (tree d1ccdff91be9)
regra: sessão FRESCA — não herda contexto de chat anterior; lê a read-order abaixo do main e só depois age
---

# S6 · Do protótipo à tela viva

## Contrato de paralelismo (Lei 1 — `03-REGRAS-DE-PARALELISMO.md`)

| | |
|---|---|
| **Sessão** | S6 |
| **Escreve em** | nenhum — produz pedido pro [CL] |
| **NÃO toca** | tudo |
| **Estado** | só em `_saida-S6.md`. Não editar `01-LISTA-COMPLETA.md`, `github.md`, `memory/**`, `COWORK_NOTES.md`, `governance/required-checks-baseline.json` |

## Read-order obrigatória (do `main`, nunca de cópia local)
1. `CLAUDE.md` (raiz) — limites operacionais
2. `prototipo-ui/PROTOCOL.md` — papéis F1→F3.5, fases
3. `prototipo-ui/PRE-FLIGHT-TELA.md` — resolvedor de pré-requisito por tela
4. `memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md` — catraca (ADR 0286)
5. `memory/INDEX.md` + `memory/proibicoes.md` + `memory/LICOES_CC.md`
6. O charter da tela em questão (`resources/js/Pages/**/<Tela>.charter.md`)

> ⚠️ `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` é citado pelo `CLAUDE.md` mas **não apareceu** na varredura do `main` em 2026-08-23. Confirmar o caminho antes de citá-lo como lei.


> Execução é **[CL] Claude Code**. Esta sessão produz o **pedido**, na ordem que o CI aceita.

> **⚠️ CORRIGIDO 2026-08-23 (pós-medição).** Este bloco foi escrito supondo catracas advisory e nenhum oráculo de produção. Medido: **46 contexts required** com `enforce_admins`, e `scripts/governance/charter-live-signal.mjs` **é** o oráculo (required) — critério `live = evidência, não palavra`: flag em `governance/prod-flags.json`, hits em `governance/route-hits.json` ou `smoke:` datado. **Não criar `PRODUCAO.md`.** Ler o script antes de usar a tabela abaixo. Ver `06-CORRECAO-MEDIDA.md`.

## Portões, em ordem (nenhum pula)
| # | Portão | Dono | Prova |
|---|---|---|---|
| 1 | Charter existe e tem Non-Goals + Anti-hooks aprovados | [W] | gate ADR 0107 |
| 2 | `casos.md` com UC (Dado/Quando/Então) | [CC] | trio completo |
| 3 | Contrato de tela no schema, `alvo` existente | [CC] | `contract.schema.json` |
| 4 | Âncoras `data-contract` na tela | [CL] | `grep -c data-contract <Index.tsx>` |
| 5 | Copy literal bate nos dois lados | CI | `scripts/contrato-de-tela.mjs` |
| 6 | Readiness ✅ | máquina | `scripts/qa/prototipo-readiness.mjs` |
| 7 | Lane de teste real (Pest) | [CL] | UC citado no teste, veredito |
| 8 | a11y | [CA] | F3.5 |
| 9 | Screenshot 1280 (ROTA LIVRE) + 1440 | [W2] | aprova merge |
| 10 | **Sinal de produção** | máquina | `charter-live-signal.mjs` — flag, hits ou smoke datado |

> ⚠️ O portão 10 depende de `route-hits.json`, cuja janela **expirou em 25/07** (C.06). Enquanto não religar, o sinal de uso real só vem por `prod-flags.json` ou `smoke:` — declarar isso ao usar, em vez de tratar ausência de hit como ausência de uso.

## Regras que a implantação não negocia
- Cor só por token `.cockpit`; accent roxo `oklch(0.55 0.15 295)`.
- PT-BR em tudo que o cliente lê. Sem emoji no app. Sem `rounded-xl+`. Sem modal full-screen pra detalhe — **drawer PT-02**.
- Cabe em **1280px com sidebar aberta** (Larissa). Toque ≥44px onde há tablet (Repair).
- Nenhum status ✅ sem lane executada.

## Ordem de implantação sugerida (menor risco primeiro)
1. **Ponto/Dashboard** — contrato descido, charter pronto, casos escrito em S5. Falta só teste real.
2. **Modules/Index** e **Backup/Index** — trio completo, recorte pequeno.
3. **superadmin/Pacotes** → **Negocios** → **Assinaturas** → **Dashboard** (do menor recorte pro maior).
4. **CaixaUnificada** — contrato ativo, mas acordo semântico `paired`/`connected` precisa de prova nos dois lados.
5. **Financeiro/Unificado** — por último: o intent é grep de string, não prova de comportamento; exige E2E.

## Critério de pronto
- [ ] pedido pro [CL] escrito por tela, com os 9 portões marcados
- [ ] nenhuma tela na fila com portão 1 ou 3 em aberto
- [ ] `_saida-S6.md`

---
sessao: S5
titulo: Contratos — fechar o que a análise 2026-08-23 abriu
autor: "[CC]"
criado: 2026-08-23
base: wagnerra23/oimpresso.com@main (tree d1ccdff91be9)
regra: sessão FRESCA — não herda contexto de chat anterior; lê a read-order abaixo do main e só depois age
---

# S5 · 2 casos.md faltantes + 1 decisão de schema + recortes

## Contrato de paralelismo (Lei 1 — `03-REGRAS-DE-PARALELISMO.md`)

| | |
|---|---|
| **Sessão** | S5 |
| **Escreve em** | `prototipo-ui/contrato/` + os 2 `casos.md` nomeados (Ponto/Dashboard, CaixaUnificada) |
| **NÃO toca** | `Pages/Ponto/**` — **cedido ao S2** · `Pages/**` não-Ponto (é do S9) |
| **Estado** | só em `_saida-S5.md`. Não editar `01-LISTA-COMPLETA.md`, `github.md`, `memory/**`, `COWORK_NOTES.md`, `governance/required-checks-baseline.json` |

## Read-order obrigatória (do `main`, nunca de cópia local)
1. `CLAUDE.md` (raiz) — limites operacionais
2. `prototipo-ui/PROTOCOL.md` — papéis F1→F3.5, fases
3. `prototipo-ui/PRE-FLIGHT-TELA.md` — resolvedor de pré-requisito por tela
4. `memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md` — catraca (ADR 0286)
5. `memory/INDEX.md` + `memory/proibicoes.md` + `memory/LICOES_CC.md`
6. O charter da tela em questão (`resources/js/Pages/**/<Tela>.charter.md`)

> ⚠️ `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` é citado pelo `CLAUDE.md` mas **não apareceu** na varredura do `main` em 2026-08-23. Confirmar o caminho antes de citá-lo como lei.

7. `cowork-inbox/CONTRATOS-DE-TELA-ANALISE-2026-08-23.md` (a análise que abriu estes pontos)

## Trabalho
### 5.1 Trio quebrado com contrato ATIVO
| Tela | Falta | Observação |
|---|---|---|
| `Ponto/Dashboard/Index` | `.casos.md` | **já escrito** em `cowork-inbox/ponto-dashboard/Index.casos.md` — só revisar e subir |
| `Atendimento/CaixaUnificada` | `.casos.md` | **a escrever** — ler o charter (33 KB) ANTES; sem isso é invenção |

### 5.2 Decisão de schema [W]
`financeiro-unificado.intent.json` usa `fluxos[]/deve_conter/nao_pode_conter` — o `contract.schema.json` não o valida. Duas saídas: (a) criar `intent.schema.json` como família declarada, (b) o Financeiro ganha um `.contract.json` de copy e o intent vira complemento. **Não inventar a terceira.**

### 5.3 Recortes somados (dívida de contrato)
superadmin-dashboard 4/10 · negocios 4/7 · assinaturas 5/6 · pacotes 1/2 · modulos 5/7 ≈ **14 seções desenhadas e não contratadas**. Para cada: a tela já alcançou o desenho? Alcançou = promover a seção. Doutrina: contrato que nasce vermelho ensina a ignorar o gate.

### 5.4 Pendências [W] dentro de contratos (9)
2 em `ponto-painel` · 2 em `ponto-espelho` · 3 em `jana-painel` · 2 de paginação superadmin (**6/página vs 20 — mesma decisão em negocios e assinaturas, decidir junto**).

## Critério de pronto
- [ ] 2 `casos.md` prontos (CaixaUnificada só depois de ler o charter)
- [ ] schema do intent decidido por [W]
- [ ] 14 recortes triados (promover / manter fora)
- [ ] 9 pendências viradas em pergunta única cada
- [ ] `_saida-S5.md`

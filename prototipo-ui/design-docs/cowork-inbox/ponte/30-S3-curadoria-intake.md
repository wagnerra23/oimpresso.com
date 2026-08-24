---
sessao: S3
titulo: Curadoria do intake — Onda 3
autor: "[CC]"
criado: 2026-08-23
base: wagnerra23/oimpresso.com@main (tree d1ccdff91be9)
regra: sessão FRESCA — não herda contexto de chat anterior; lê a read-order abaixo do main e só depois age
---

# S3 · 24 itens + 12 subpastas: Issue, destilar ou morrer

## Contrato de paralelismo (Lei 1 — `03-REGRAS-DE-PARALELISMO.md`)

| | |
|---|---|
| **Sessão** | S3 |
| **Escreve em** | `cowork-inbox/` |
| **NÃO toca** | qualquer path de produção |
| **Estado** | só em `_saida-S3.md`. Não editar `01-LISTA-COMPLETA.md`, `github.md`, `memory/**`, `COWORK_NOTES.md`, `governance/required-checks-baseline.json` |

## Read-order obrigatória (do `main`, nunca de cópia local)
1. `CLAUDE.md` (raiz) — limites operacionais
2. `prototipo-ui/PROTOCOL.md` — papéis F1→F3.5, fases
3. `prototipo-ui/PRE-FLIGHT-TELA.md` — resolvedor de pré-requisito por tela
4. `memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md` — catraca (ADR 0286)
5. `memory/INDEX.md` + `memory/proibicoes.md` + `memory/LICOES_CC.md`
6. O charter da tela em questão (`resources/js/Pages/**/<Tela>.charter.md`)

> ⚠️ `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` é citado pelo `CLAUDE.md` mas **não apareceu** na varredura do `main` em 2026-08-23. Confirmar o caminho antes de citá-lo como lei.


> **Sessão com [W] presente.** Nada aqui é decidível por mim.

## Método — 3 saídas possíveis por item
- **ISSUE** → vira GitHub Issue (form `cowork-intake`); tem trabalho vivo atrás.
- **DESTILAR** → o conteúdo útil entra no charter/contrato da tela; o arquivo morre.
- **MORRER** → histórico já absorvido; some sem cerimônia.

## Pauta (agrupada — decidir grupo a grupo, não item a item)
| Grupo | Itens | Pergunta única pro [W] |
|---|---|---|
| Pedidos pro [CL] (5) | applier-digest, programa-doc, programa-doc-react, MODULOS-F3-ONDAS, SUPERADMIN-F3-ONDAS | Já foram executados? Executado = MORRER. |
| F1 entregues (6) | ACESSOS, CMS, MODULOS, NOTIFICACOES, SUPERADMIN, CATCHUP | A tela existe em prod? Existe = DESTILAR no charter. |
| Jana (5) | CICLO-COMPLETO, FASE2, FUSAO, MODULO-ONDAS-PR, PAINEL-DARK | O `jana-painel.contract.json` já cobre? Cobre = MORRER. |
| Forja/planos (5) | COCKPIT-CHARTER-V2, TOPNAV-3GRUPOS, BLADE-PARA-REACT, PLANO-MESTRE-trilha-d, INVENTARIO-L1-VENDAS-PDV | Plano vivo ou plano cumprido? |
| Outros (2+12 subpastas) | FICHA-BL-home-index + subpastas não inventariadas | Inventariar antes de decidir. |

## Decisão travada nesta sessão (bloqueia S1 e S6)
**`inventario-migracao`: `Pages/Stocks/` ou `Pages/Inventario/`?** É a outra metade da allowlist do guard. Sem isso, o guard fica meio-aberto indefinidamente.

## Critério de pronto
- [ ] 24 itens com veredito registrado
- [ ] 12 subpastas inventariadas (ao menos: quantos arquivos, natureza)
- [ ] path da `inventario-migracao` decidido
- [ ] `_saida-S3.md` com a lista de Issues a abrir

---
sessao: "00"
titulo: Índice das sessões da ponte
autor: "[CC]"
atualizado: 2026-08-23 (revisão 3 — paralelismo + cobertura do produto)
---

# Ponte Cowork → produção — índice

> Cada sessão é um **thread novo**. Abre-se colando o arquivo da sessão como primeira mensagem.
> O que passa entre sessões é **arquivo** (`_saida-S<n>.md`), nunca memória de chat. Thread longa é cache; cache é o L-42.

## Leia primeiro
| Arquivo | O que é |
|---|---|
| `03-REGRAS-DE-PARALELISMO.md` | **as 4 leis** — dono por prefixo, estado, PR, arquivos proibidos. Ler ANTES de abrir thread |
| `01-LISTA-COMPLETA.md` | 124 processos numerados (S1–S8) + erratas + caminho crítico |
| `02-COBERTURA-PRODUTO-INTEIRO.md` | o produto medido: 217 telas, 53% com casos, 5% com contrato |
| `06-CORRECAO-MEDIDA.md` | **o diagnóstico corrigido pela medição — leia antes do 01/02** |
| `04-PENDENTES.md` | o que está aberto agora, por dono (revisão 2, pós-medição) |
| ~~`05-DIAGNOSTICO-PRODUCAO.md`~~ | **refutado** — mantido como registro do erro |

## As sessões
| # | Sessão | Arquivo | Prefixo que escreve | Natureza |
|---|---|---|---|---|
| S0 | Consolidação | (dentro do 03) | só `01-LISTA-COMPLETA.md` | 1× por vaga |
| S1 | Build mecânico | `10-S1-build-mecanico.md` | `cowork/` + 3 contratos | mecânica |
| S2 | Trio órfão Ponto | `20-S2-trio-orfao.md` | `Pages/Ponto/**` | mecânica |
| S3 | Curadoria do intake | `30-S3-curadoria-intake.md` | `cowork-inbox/` | decisão [W] |
| S4 | Diff do resíduo | `40-S4-diff-residuo.md` | read-only | verificação |
| S5 | Contratos | `50-S5-contratos.md` | `contrato/` + 2 casos | análise |
| S6 | Implantação F3→F3.5 | `60-S6-implantacao.md` | pedido pro [CL] | execução |
| S7 | Pós-merge / rollout | (S7 no `01`) | ambiente vivo | execução |
| S8 | Encerramento da esteira | (S8 no `01`) | limpeza | fechamento |
| S9 | Cobertura do produto | (S9 no `02`) | `Pages/**` por módulo | programa |
| **S10** | **Fechar as 25 (P1)** | `70-S10-fechar-as-25.md` | `Pages/<Modulo>/**` | **gargalo medido** |

## Vagas de execução (máximo rendimento)
```
VAGA 1 (hoje, 4 threads)   S1 ∥ S2 ∥ S4 ∥ S3
VAGA 1b (P1, fura a fila)  S10 × n módulos  ← precisa de C.01 + C.02
VAGA 2 (após S1+S2)        S5 ∥ S9.02 Whatsapp ∥ S9.03 Sells
VAGA 3                     S9.01 Ponto ∥ S9.07 governance ∥ S9.08 Purchase+Stock
VAGA 4 (série)             S6 → S7 → S8
Fora de vaga (dep. 9.12)   S9.04 Repair · S9.05 Essentials · S9.06 Forja
```
Entre vagas: **S0 consolida** (§S0 do arquivo 03).

## O caminho crítico é ARTEFATO — corrigido 2026-08-23
Medido: **29 telas prontas de 54**. As 25 que faltam travam em `casos.md`-com-UC e **scorecard** — trabalho meu, sem depender de [W].
**Destrava com duas saídas de script:** `C.01` (lista nominal das 25) e `C.02` (critério do scorecard), ambas do [CL].
As decisões [W] (3.25 · 5.17 · 5.04 · 9.12) seguem abertas mas travam **escopo**, não entrega — produção anda: 5.811 PRs merged, deploy contínuo, 46 checks required.
Único defeito real: **`route-hits` expirado** (C.06).

---
sessao: S1
titulo: Build mecânico — Ondas 1 e 2
autor: "[CC]"
criado: 2026-08-23
base: wagnerra23/oimpresso.com@main (tree d1ccdff91be9)
regra: sessão FRESCA — não herda contexto de chat anterior; lê a read-order abaixo do main e só depois age
---

# S1 · Build mecânico (5 arquivos, sem decisão)

## Contrato de paralelismo (Lei 1 — `03-REGRAS-DE-PARALELISMO.md`)

| | |
|---|---|
| **Sessão** | S1 |
| **Escreve em** | `prototipo-ui/cowork/` + os 3 contratos nomeados (`configuracoes`, `patrimonio`, `venda-menu`) |
| **NÃO toca** | o resto de `prototipo-ui/contrato/` (é do S5) · `resources/js/Pages/**` |
| **Estado** | só em `_saida-S1.md`. Não editar `01-LISTA-COMPLETA.md`, `github.md`, `memory/**`, `COWORK_NOTES.md`, `governance/required-checks-baseline.json` |

## Read-order obrigatória (do `main`, nunca de cópia local)
1. `CLAUDE.md` (raiz) — limites operacionais
2. `prototipo-ui/PROTOCOL.md` — papéis F1→F3.5, fases
3. `prototipo-ui/PRE-FLIGHT-TELA.md` — resolvedor de pré-requisito por tela
4. `memory/requisitos/_DesignSystem/RUNBOOK-contrato-de-tela.md` — catraca (ADR 0286)
5. `memory/INDEX.md` + `memory/proibicoes.md` + `memory/LICOES_CC.md`
6. O charter da tela em questão (`resources/js/Pages/**/<Tela>.charter.md`)

> ⚠️ `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` é citado pelo `CLAUDE.md` mas **não apareceu** na varredura do `main` em 2026-08-23. Confirmar o caminho antes de citá-lo como lei.


## Escopo fechado
| Arquivo | Destino no main |
|---|---|
| `compras-grade-matrix.jsx` | `prototipo-ui/cowork/` |
| `compras-grade-matrix.css` | `prototipo-ui/cowork/` |
| `configuracoes.contract.json` | `prototipo-ui/contrato/` |
| `patrimonio.contract.json` | `prototipo-ui/contrato/` |
| `venda-menu.contract.json` | `prototipo-ui/contrato/` |

## Processo
1. Ler a read-order. Confirmar que nenhum dos 5 nomes existe no `main` (colisão = para e reporta).
2. Validar os 3 contratos contra `prototipo-ui/contrato/contract.schema.json`: `alvo` + `secoes[{id,copy,estados}]` + `ordem` subsequência dos ids. Contrato fora do schema **não sobe**.
3. Conferir que cada `alvo` declarado **existe** no `main`. Alvo inexistente = ponteiro podre → corrigir na descida (precedente: `ponto-painel`, alvo corrigido 2026-08-20).
4. Trocar no CSS toda cor crua por token `.cockpit` (`--bg`,`--surface`,`--border`,`--text`,`--text-dim`, accent roxo `oklch(0.55 0.15 295)`). Paleta bespoke = reprova.
5. Rodar mentalmente o `cowork-ssot-guard.mjs`: só jsx/tsx/css/html em `prototipo-ui/cowork/`, sem `?v=`, sem `.bak`, sem screenshot, sem process-doc.
6. Escrever `_saida-S1.md` com o pedido literal pro [CL].

## Critério de pronto
- [ ] 5 arquivos prontos, nomes sem colisão
- [ ] 3 contratos validam no schema
- [ ] 3 `alvo` existem no `main`
- [ ] CSS sem cor fora de token
- [ ] allowlist do guard: metade `compras-grade-matrix` fechada
- [ ] `_saida-S1.md` escrito

## Não fazer
❌ Não mexer em `inventario-migracao` (bloqueada por charter ausente — decisão [W], sessão S3).
❌ Não deletar o protótipo legado `prototipo-ui/prototipos/compras-grade-matrix/` — propor `_arquivo/`, não executar.

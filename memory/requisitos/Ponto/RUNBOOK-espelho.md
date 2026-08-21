---
owner: W
last_validated: "2026-08-21"
slug: ponto-runbook-espelho
title: "Ponto — Runbook do Espelho (/ponto aba espelho · Espelho/Index + Espelho/Show)"
type: runbook
module: Ponto
tela: Ponto/Espelho/Show
status: ativo
date: 2026-08-21
---

# RUNBOOK — Espelho de Ponto (`Ponto/Espelho/Index` + `Ponto/Espelho/Show`)

> **Por que este arquivo existe.** Igual ao [RUNBOOK-dashboard](RUNBOOK-dashboard.md): o hook
> `block-mwart-violation` barra `Edit`/`Write` nas duas Pages enquanto não houver
> `RUNBOOK-espelho.md` aqui. F1 PLAN do MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).

## 1. O que é a tela

Aba *espelho* da rota `/ponto`, em duas Pages: **`Index`** (lista de colaboradores da competência)
e **`Show`** (espelho individual + folha de impressão).

O Espelho de Ponto é **documento legal**, não um relatório de conveniência: ele é o que a empresa
apresenta em fiscalização. Isso muda o padrão de cuidado de tudo neste runbook.

- **Portaria MTP 671/2021 Art. 85** — o espelho tem que sair impresso com o conteúdo previsto.
- **Art. 74 §2º CLT** — registro de jornada obrigatório.
- **PII em tela**: o bloco de dados do colaborador expõe **CPF** e **PIS**. Nunca em log, nunca em
  PR, nunca em commit (use `PiiRedactor` / `[REDACTED]` — [proibicoes §Multi-tenant](../../proibicoes.md)).
- **`business_id` escopado** em toda query (Tier 0, [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)).

Audiência: **DP / RH** e, na impressão, o **fiscal**.

## 2. Fontes canônicas (ordem de precedência)

| Ordem | Fonte | Papel |
|---|---|---|
| 1 | `memory/requisitos/Ponto/SDD-espelho-e-jornada-v1.0.md` · `SPEC.md` | CU e regra de apuração |
| 2 | `prototipo-ui/contrato/ponto-espelho.contract.json` | **contrato visual** — âncoras + copy literal |
| 3 | `prototipo-ui/cowork/ponto-page.jsx` | protótipo Cowork (fonte de design, [ADR 0299](../../decisions/0299-figma-nao-e-fonte-de-design.md)) |
| 4 | `Modules/Ponto/Resources/views/espelho/show.blade.php` + `reports/espelho-pdf.blade.php` | Blade legado — **contrato de paridade** |

O contrato declara a proveniência e ela importa aqui mais que em qualquer outra tela:

> *"Importado de `espelho/show.blade.php` + `reports/espelho-pdf.blade.php`. As colunas da apuração
> diária e os seis totalizadores são os do Blade, **campo a campo** (`realizada_trabalhada_minutos`,
> `atraso_minutos`, `he_diurna/noturna`, `banco_horas_credito/debito`, `estado`)."*

**Corolário duro:** a copy legal (`"Portaria MTP 671/2021 Art. 85"`, `"Espelho de Ponto Eletrônico"`)
é **paridade com o Blade que já roda**, não redação nova. Não reescreva rótulo de documento legal
por gosto — se algum estiver errado, é decisão [W] com base na Portaria, não refinamento de copy.

## 3. Contrato de tela — as 5 seções

> **Dono do mecanismo:** [`RUNBOOK-contrato-de-tela`](../_DesignSystem/RUNBOOK-contrato-de-tela.md)
> (v1 — determinístico, sem render, sem auth). O v0 foi recusado na
> [ADR 0290](../../decisions/0290-fidelity-lock-v0-recusado.md); o princípio da catraca semântica
> vem da [ADR 0286 §5](../../decisions/0286-channel-health-corroborado-por-mensagem-real.md)
> — ⚠️ ADR cujo TÍTULO é sobre outro assunto (channel health), então cite sempre com o §5.

Alvo: `resources/js/Pages/Ponto/Espelho/Show.tsx` **e** `Index.tsx` (o gate aceita a copy em
qualquer um dos dois arquivos do alvo).

| Seção | Copy exigida |
|---|---|
| `espelho-dados-colaborador` | Dados do colaborador · Matrícula: · CPF: · PIS: · Escala atual: · Carga diária: · Admissão: · Desligamento: |
| `espelho-totais` | Hora extra · Banco hrs (+) · Banco hrs (−) |
| `espelho-modo-visao` | Grade do mês |
| `espelho-apuracao-diaria` | Previsto · Realizado · BH (+/−) · Estado |
| `espelho-folha-impressao` | Espelho de Ponto Eletrônico · Apuração diária · Totais do mês · Responsável RH · Portaria MTP 671/2021 Art. 85 |

⚠️ **`Banco hrs (−)` e `BH (+/−)` usam MINUS SIGN U+2212, não hífen-menos.** Copiar do contrato,
nunca redigitar — o gate compara string exata, e um hífen ASCII passa despercebido no olho e
reprova no CI. Mesma família da lápide do `·` U+00B7 (§5 2026-07-02).

## 4. Estado MEDIDO em 2026-08-21 (o F3 pendente)

`node scripts/contrato-de-tela.mjs --contract prototipo-ui/contrato/ponto-espelho.contract.json`
→ **26 falhas**: as 5 âncoras + **21 de 29 copies**.

Medido por string, uma a uma, nos dois arquivos do alvo: **as 21 estão ausentes em ambos**. Ou seja,
diferente do Painel — onde o F3 é majoritariamente renomear —, aqui é **construção**: a tela existe
(`Show.tsx` 321 linhas, `Index.tsx` 168) mas está estruturada de outro jeito, e o contrato descreve
um espelho que ainda não foi montado.

Blocos a construir: dados do colaborador · os 6 totalizadores · seletor de modo de visão ·
tabela de apuração diária · **folha de impressão**.

## 5. Passos do F3

1. Ler este runbook + `Show.charter.md` / `Index.charter.md` + `Show.casos.md` / `Index.casos.md`
   (o trio já existe; charter é lei — [precedência](../../proibicoes.md)).
2. Ler o **Blade legado** antes de escrever campo: a paridade é campo a campo, e é o Blade que
   define o que o documento legal mostra.
3. Backend: conferir que a apuração já expõe os 6 totalizadores citados no contrato. Prop cara
   (agregação por competência) nasce **deferida** (`Inertia::defer`).
4. Frontend: montar as 5 seções, `data-contract` na ordem do contrato, copy **copiada** do contrato.
5. Impressão: a folha é `@media print` — validar impressa, não só na tela.
6. Verificação (§6) + smoke real com screenshot antes de dizer "pronto" (R1).

## 6. Verificação

```bash
node scripts/contrato-de-tela.mjs --contract prototipo-ui/contrato/ponto-espelho.contract.json
```

Pest e PHPStan rodam no **CT 100** ([proibicoes §Ambiente](../../proibicoes.md)). Em teste/fixture o
tenant é o fictício **98** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — **biz=4 (ROTA LIVRE) é proibido sem exceção**.

## 7. Não fazer

- ⛔ **Inventar rótulo do documento legal.** O que o espelho impresso diz vem da Portaria via Blade.
- ⛔ **CPF/PIS em log, PR, commit ou screenshot de evidência.** Redija.
- ⛔ Marcar `data-contract` sem entregar a copy — âncora sem copy é passar parecendo que passou.
- ⛔ Alterar o contrato pra caber na tela (decisão [W], com razão escrita).
- ⛔ Redigitar `−` (U+2212) como `-` — ver §3.

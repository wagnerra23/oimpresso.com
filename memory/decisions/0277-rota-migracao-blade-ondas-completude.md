---
slug: 0277-rota-migracao-blade-ondas-completude
number: 277
title: "Rota de migração do backbone Blade (UltimatePOS) — contrato de completude por route morto, 10 ondas governadas por adversário, Restaurante erradicado"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: meta
decided_by: [W]
decided_at: "2026-06-13"
module: governance
tags: [mwart, migracao, blade, ondas, adversario, completude, ultimatepos, roadmap]
supersedes: []
superseded_by: []
related:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0106-recalibracao-velocidade-fator-10x-ia-pair
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0099-project-legacy-discovery-pre-deletion
  - 0276-decisao-pelo-fluxo-classes-pares-adversariais
---

# Rota de migração Blade → Cockpit por ondas e contrato de completude

## Contexto

O oimpresso tem **dois universos** de código de tela, e só um foi inventariado pra migração:

1. **36 módulos nWidart** (`Modules/*`) — auditados em `AUDITORIA_MODULOS.md`. Onde a UI React (Sells, Financeiro, Caixa Unificada) vive.
2. **Backbone Blade do UltimatePOS** (`resources/views/*`) — **653 arquivos `.blade.php`** em 67 pastas, ~50 controllers, lido do `@main` via `routes/web.php`. **Nunca inventariado pra migração.**

O processo de *como* migrar uma tela já é canônico ([ADR 0104](0104-processo-mwart-canonico-unico-caminho.md) — MWART, 5 fases F1→F5). Faltava a régua de *quando* uma migração está realmente concluída e em que ordem atacar o backbone inteiro.

O `routes/web.php` está cheio de **coexistência**: `/payments/v2` (Inertia) ao lado de `/payments` (Blade); `/vendas/caixa` ao lado de `/cash-register`; `/cliente` ao lado de `resource('contacts')`. Em cada par, **o route Blade continua respondendo** — declarar "migrado" porque "existe tela React" é o *whack-a-mole* que infla a contagem e deixa AdminLTE vivo escondido atrás do gêmeo React. (Verificado contra `web.php@main` nesta sessão: os três pares existem hoje; o próprio arquivo já rotula `/payments/v2` como "Wave Blade T1 Migration".)

Origem: estudo F0 do Claude Design (chat51 "Migração em Ondas") — Wagner pediu *"estude o produto em etapas, divida em ondas, verifique quais funções as telas Blade têm, e trace uma rota que só pare depois de todas migradas, em ondas com adversários."*

## Decisão

Adotar **quatro regras** que governam a migração do backbone Blade. Detalhe operacional no roadmap [memory/requisitos/Mwart/ROADMAP-ONDAS-BLADE-ADVERSARIOS.md](../requisitos/Mwart/ROADMAP-ONDAS-BLADE-ADVERSARIOS.md).

### 1. Contrato de completude — "migrado" = route Blade morto ou 302, nunca "React existe"
A unidade de verdade é a **função** (endpoint nomeado no `web.php`) e o **route Blade vivo**, não o arquivo `.blade`. Uma onda só fecha quando **todo route legado da família é removido ou redirecionado (302 → React)** e a view vira lápide. Enquanto os dois caminhos coexistem, a função **não** conta como migrada.

### 2. Dez ondas ordenadas por frequência de balcão × dependência de dados
Censo em 12 domínios (A–L) → 10 ondas:
`1` Vendas/PDV/Caixa · `2` Clientes · `3` Catálogo · `4` Estoque · `5` Compras · `6` Contábil · `7` Config/Docs · `8` **Relatórios (a represa — lê todos, vem por último)** · `9` Acesso/onboarding · `10` Desligamento & prova de zero-Blade.
Catálogo antes do que vende; estoque antes de compras fechar o ciclo; relatórios por último porque relatório que lê dado de domínio não-migrado **mente**.

### 3. Adversário em duas camadas — [CD] mede a tela, [CX] ataca o processo
- **[CD] adversário da onda** — o melhor-da-classe que a tela precisa bater (Square, Attio, Linear, Ramp, Mercury, Metabase…). Régua do gate visual F1.5 ([ADR 0107](0107-emendation-0104-visual-comparison-gate-f3.md)/[0114](0114-prototipo-ui-cowork-loop-formalizado.md)), 15 dimensões, nota ≥80.
- **[CX] adversário permanente** — o red-team do **processo**: *"qual route Blade ainda responde escondido?"*. Produz furos → gates/ADRs. Coerente com [ADR 0276](0276-decisao-pelo-fluxo-classes-pares-adversariais.md) (pares adversariais). **A Onda 10 é o [CX] institucionalizado:** gate de CI que falha se ≥1 view AdminLTE for servida em rota autenticada; "rota PAROU" = contador de routes Blade vivos == 0.

### 4. Restaurante/Mesas (domínio M) — erradicar, não migrar
`Restaurant\*` (≈18 fn: mesas, modificadores, KDS, pedidos, reservas) é herança do UltimatePOS genérico, fora do domínio gráfica/oficina (Larissa/Martinho). **Remover do menu + roteador**, não enfileirar onda — mesma classe da locação de caçambas que [W] mandou erradicar. Deleção de views só após confirmação [W] + discovery [ADR 0099](0099-project-legacy-discovery-pre-deletion.md).

## Consequências

**Positivas**
- A contagem de progresso fica **honesta** — impossível declarar 100% com AdminLTE vivo; a Onda 10 vira prova mecânica (CI).
- Ordem com racional explícito → priorização defensável (balcão da Larissa primeiro, represa de relatórios por último).
- Cada onda nasce com seu adversário → qualidade pontuada contra um comparável, não subjetiva.
- Backbone antes invisível vira backlog rastreável (`US-MWART-004…013`, 1 por onda).

**Custos / riscos**
- Esforço de **longo horizonte** — não cabe no cycle de Receita ativo (CYCLE-08). As ondas entram como backlog **p1–p3**, não p0; [W] decide quando cada uma vira fila.
- O contrato torna "migrado" mais caro (exige desligar o legado, não só nascer o React) — é o ponto: o custo migra do *debug futuro* pro *fecho honesto agora*.
- Onda 8 (Relatórios) é gargalo por design (depende de 1–7); a represa não paraleliza.

## Alternativas consideradas

- **Contagem por arquivo `.blade`** (653) — rejeitada: incha com partials/modais/e-mails, não mede função nem honestidade.
- **"React existe = migrado"** (status quo implícito) — rejeitada: é o whack-a-mole que deixa route fantasma vivo.
- **Ordem por tamanho do domínio** — rejeitada: ignora frequência de uso real e dependência de dados (relatórios mentiriam).
- **Migrar Restaurante junto** — rejeitada: peso morto fora do domínio; erradicar é mais barato e honesto.

## Notas

Promovida da proposta `proposals/2026-06-13-rota-migracao-blade-ondas-completude.md`, autorizada por [W] em 2026-06-13. Roadmap operacional e backlog em [memory/requisitos/Mwart/](../requisitos/Mwart/ROADMAP-ONDAS-BLADE-ADVERSARIOS.md). Censo verificado contra `routes/web.php@main`.

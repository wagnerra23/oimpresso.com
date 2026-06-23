# _PROPOSTA_ ADR 0245 — v5 = DS único ativo · Oficina = tela-padrão (semente) · Inbox = régua congelada

> **Status:** PROPOSTA de [CC]. [W] autorizou o conteúdo no chat 2026-06-02 ("faça tudo que propôs").
> **Número/versão = só [W]/git** (soberania ADR 0238 · CARTA §0.1). [CC] não cunha número do git.
> **Tier 0** (token/DS/canon) — entra no `main` via ponte zero-toque, não por edição local.

- **Data:** 2026-06-02
- **Sessão:** Cowork [CC] ↔ Wagner
- **Supersede/estende:** rebaixa **v4.1** a histórico (não deleta); mantém **ADR 0235** (roxo canon) **intacto**; estende ADR 0110 (Cockpit V2) e 0200 (DS é piso).

## Contexto

Existiam **dois DS no disco** — v4.1 (`tokens.css` + `design-system.css`, espelhado no repo) e **v5** (`ds-v5/*`, rotulado "fonte única", acabamento estado-da-arte, eixos ortogonais `[data-theme]×[data-density]`, roxo canon preservado). A Regra de Ouro #5 do STATUS já mandava o pré-flight ler `ds-v5/components.css` — ou seja, **o build já acontecia no v5**, com o v4 sobrevivendo só como espelho. Ambiguidade gerava risco de drift.

Em paralelo, [W] pediu para **manter a Oficina como tela-padrão** em vez da Inbox.

## Decisões

### D-1 — v5 é o **único DS ativo**
`ds-v5/*` (tokens + components + doc + interactive) passa a ser a **fonte única**. O v4.1 (`tokens.css` raiz + `design-system.css`) é **rebaixado a histórico de transição** → arquivar em `_arquivo/ds-historico/` com lápide (append-only, L-22). Nenhuma tela nova importa v4.

### D-2 — **Oficina = tela-padrão / semente do DS**
Os componentes provados na Oficina (Produção/Kanban + Nova OS: `fsm-stepper`, `spill`, `frescor`, `datatable`, drawer-documento-vivo, `StageGate`) são a **referência canônica** do v5. Padrão novo **graduja primeiro a partir da Oficina** (é a tela em build vivo, persona-central Larissa, já com trio `charter`+`decisoes`+`casos`).

### D-3 — **Inbox (9.75) = régua de método congelada**
A Caixa Unificada continua o **teto de qualidade** que as telas perseguem (gabarito de _nota_, não de _componente_). Não se mexe nela. Papéis separados de propósito: Oficina = **semente** (de onde os componentes sobem); Inbox = **régua** (até onde a nota tem que chegar).

### D-4 — Roxo canon **intacto**; âmbar da Oficina é accent **escopado**
O DS permanece **roxo `oklch(0.55 0.15 295)` universal** (ADR 0235). O `ds-v5/components.css` é 100% `var(--accent)` semântico — **confirmado por grep, zero âmbar hardcoded**. A identidade âmbar da Oficina é **accent de tela** (tweak `accentHue` / `.oficina-root` scope), **não** altera o token do DS. Adotar Oficina como padrão **não** regride o canon roxo (anti-L-10).

## Direção da mudança (quem muda — tela ou DS?)
- **Tela campeã → doadora:** padrão provado **sobe** pro DS (aditivo, monotônico). A tela quase não muda.
- **Telas fracas → receptoras:** mudam pra **consumir** o v5.
- **Regra de direção:** padrão só sobe tela→DS **depois de graduar** (Bateria §9 ≥90 + OK de [W]). O DS nunca desce forçando regressão.

## Sequência de execução (backlog · cada passo é uma build/pass própria)
1. ✅ **Semente confirmada:** v5 já reflete os componentes da Oficina (cockpit patterns presentes e roxo-limpos).
2. **Trio para Vendas + Compras:** já têm `*.casos.md`; falta `Vendas.charter.md` e `Compras.charter.md` (congela o 9.4/9.5 num contrato antes de migrar).
3. **Financeiro (8.0) → consome v5** + corrige colisão KPI grid `<1100px` por container-query (ADR 0200 D-... responsivo). Melhor ROI (+1.4).
4. **DS-GUARD + gate visual F1.5** em cada passo; ponte zero-toque pro Code.

## Não-regressão (como manter o status das telas)
Trio por tela (`charter` lei · `decisoes` debate · `casos` contrato). Mexeu → roda os `casos`; quebrou um ✅ → **PARA** (PROCESSO §1, #4). Gate visual antes/depois (ADR 0107) + DS-GUARD nos arquivos tocados.

## new_design_memories
- tipo: doc-novo · ref: este _PROPOSTA-0245 · resumo: v5 único DS ativo; Oficina padrão/semente; Inbox régua congelada; roxo canon intacto (âmbar escopado)
- tipo: token · ref: ADR 0235 · resumo: confirmado intacto — DS roxo universal, âmbar é scope de tela
- tipo: conflito · ref: v4.1 → `_arquivo/ds-historico/` (lápide) · resumo: rebaixado a histórico, não deletado

## Trilha do tempo
- 2026-06-02 · [CC] redigiu a proposta após [W] aprovar o arranjo (Oficina padrão + Inbox régua + v5 único + âmbar escopado). Aguarda [W] numerar/versionar no git.

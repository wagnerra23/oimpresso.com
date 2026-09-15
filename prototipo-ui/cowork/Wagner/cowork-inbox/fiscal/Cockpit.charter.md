---
id: cowork-inbox-fiscal-cockpit-charter
page: /fiscal (+ /fiscal/nfe · /fiscal/nfse)
component: resources/js/Pages/Fiscal/Cockpit.tsx (alvo da tradução)
related_prototype: "F1 Cowork — fiscal-page.jsx §FxNotasPage · fiscal-actions.jsx (herda PT-01 Lista + PT-02 Drawer + PT-04 Modal)"
page_id: fiscal-cockpit-ondas-f1
module: Fiscal
status: draft
owner: wagner
autor: "[CC]"
created: 2026-08-24
related_us: [US-FISCAL-002, US-FISCAL-012, US-FISCAL-013, US-FISCAL-014, US-FISCAL-015]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0114-prototipo-ui-cowork-loop-formalizado, 0143-fsm-pipeline-live-prod, 0286-contrato-de-tela]
contrato: prototipo-ui/contrato/fiscal-cockpit.contract.json
---

# Charter — Cockpit fiscal · ondas F1 (delta sobre o vivo)

> Este charter **não substitui** `resources/js/Pages/Fiscal/Cockpit.charter.md` (a lei da tela). Ele declara só o que as ondas do Cowork acrescentam.

## Mission

Fazer a tela de notas deixar de ser leitura e virar operação: a pessoa fiscal resolve a nota **de dentro da lista** (cancelar, corrigir, inutilizar faixa, retransmitir), atravessa 200 notas sem mouse (⌘K + J/K/↵) e sabe, a qualquer momento, **o que é leitura real e o que é demonstração**.

## Goals (faz)

1. **Quatro mutações no drawer e na linha**, cada uma com o evento SEFAZ que lhe corresponde: cancelar (110111, cstat 101), carta de correção (110110, sequência automática), inutilizar faixa (cstat 102) e retransmitir preservando a numeração.
2. **Justificativa obrigatória de 15 caracteres** nas ações que negam ou apagam (cancelar, CC-e, inutilizar), com contador visível e confirmação desabilitada até cumprir.
3. **Efeito colateral visível**: a ação muda o status da linha, apaga a pílula de prazo quando ela deixa de existir, escreve na auditoria da nota e **publica o evento na timeline** de `/fiscal/eventos`.
4. **⌘K cross-fiscal** — notas + DF-e + as 7 telas fiscais, ↑↓ navega, ↵ abre, esc fecha.
5. **Teclado na lista** — J/K move o cursor, ↵ abre o drawer, N vai emitir; nunca dispara com foco em campo de texto nem com drawer/modal aberto.
6. **Densidade em três níveis** (compacto · confortável · relaxado), persistida por usuário.
7. **Paginação** 8/25/50 com "N–M de T" e contadores das visões salvas **derivados do conjunto exibido**, não fixos no código.
8. **Selo de procedência por superfície**, ligável pelo cabeçalho: `leitura real` · `demonstração` · `derivado da lista` · `atrás de trava`, com a explicação do que falta pra virar real.

## Non-Goals (NÃO faz — [W] aprova)

- ❌ Emissão de nota nova (segue no fluxo de venda; o menu Emitir só navega).
- ❌ Download real de XML/DANFE (o vivo mantém desabilitado).
- ❌ Cancelamento fora da janela legal — sem "forçar"; o caminho é nota de devolução, e a tela diz isso.
- ❌ Desfazer manifestação, evento ou cancelamento — a trilha é append-only.
- ❌ Filtro server-side e cursor de paginação (o F1 filtra em memória; o vivo tem a pendência declarada).
- ❌ Transformar as 6 superfícies de demonstração em consulta real — é decisão [W], não desenho.

## Anti-hooks (bloqueiam regressão)

- 🚫 Não confirmar mutação sem justificativa quando a ação nega/apaga — o texto é a defesa documental do business.
- 🚫 Não inventar verbo de ação que a SEFAZ não conhece ("aprovar", "arquivar"): as mutações são exatamente as quatro do vivo.
- 🚫 Não deixar J/K/N ativos com foco em `input`/`textarea`, drawer ou modal abertos — atalho que rouba digitação é bug de balcão.
- 🚫 Não exibir contador de visão salva que não bate com o cabeçalho da tela: contador fixo no código foi defeito real, corrigido nesta onda.
- 🚫 Não apagar o selo de procedência para "limpar" a tela: enquanto a fonte for mock, esconder isso engana a contadora (`CU-FISC-16`).
- 🚫 Não cachear KPI sem `business_id` na chave (Tier 0, ADR 0093) — vale igual na tradução.

## UX targets

- Linha 40px (compacto) / 48px (confortável) / 60px (relaxado); número da nota em mono.
- Cursor de teclado com contorno de 2px no accent, visível sem hover.
- Toast de confirmação nomeia a nota e o cstat resultante ("NF-e 8425 autorizada na retransmissão").
- Modal de ação: 480px, esc fecha, primário desabilitado enquanto inválido.

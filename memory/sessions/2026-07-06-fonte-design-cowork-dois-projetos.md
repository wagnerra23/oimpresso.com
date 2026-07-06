---
date: "2026-07-06"
topic: "Fonte de design é o Cowork (2 projetos) lido ao vivo pela integração DesignSync — resolvido o erro repetido de proveniência de âncora"
authors: [W, C]
related_adrs:
  - 0299-figma-nao-e-fonte-de-design
  - 0264-governanca-executavel-trio-dominio-e2e
---

# Fonte de design Cowork — os 2 projetos + integração ao vivo (2026-07-06)

> **TL;DR:** trabalhando na Visão Unificada, Wagner desconfiou da âncora ("pode estar ligado
> errado"). A investigação revelou o real: a fonte de design é o **Cowork (Claude Design)**,
> não Figma; vive em **2 projetos de nome parecido**; e a integração `DesignSync` lê os dois
> AO VIVO. O agente errou 3× teorizando em vez de medir — todos os erros caíram quando trocou
> teoria por DIFF + integração. Registro canônico em
> [INDEX-DESIGN-MEMORIAS §0.2](../requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md).

## O que foi verificado (fato, não teoria)

1. **Wagner não usa Figma — usa Cowork.** O conector Figma MCP que aparece "conectado" é
   irrelevante pro fluxo. A [ADR 0299](../decisions/0299-figma-nao-e-fonte-de-design.md) já dizia
   "fonte = Cowork"; hoje ficou concreto QUAL Cowork.
2. **Integração nativa `DesignSync`** (ferramenta do Code) lê os projetos Cowork ao vivo
   (`get_file`/`list_files`) depois de `/design-login` UMA vez no **terminal CLI** (`claude`
   → `/design-login` → autoriza no navegador; salva na máquina). O comando NÃO existe no app
   desktop, só no terminal. Instalado o CLI oficial (`npm i -g @anthropic-ai/claude-code`).
3. **2 projetos Cowork** (a raiz da confusão):
   - `019dcfd3-…` **"Oimpresso ERP Conunicação Visual."** — o ERP inteiro (protótipo
     `oimpresso.com.html` + `*-page.jsx` + espelho do código `Unificado/Index.tsx` +
     `_components/` + `memory/` + `ds-v6/`; 1337 arquivos). **Fonte das telas.**
   - `019dd02f-…` **"Office Impresso — Design System"** — só a biblioteca do DS
     (44 componentes + 7 templates arquétipo + Norte + ui_kits). **NÃO é a tela.**
4. **O mirror do repo está em sincronia:** `prototipo-ui/cowork/financeiro-page.jsx` é
   **byte-idêntico** ao do Cowork vivo (md5 `ae3a2cfe8855fc41e25354fcaa03de84`). A âncora do
   Unificado (`financeiro-page.jsx`, consertada de manhã via [session anchor-podre]) está
   **correta e atual** — não precisa reverter nem deletar.

## Os 3 erros do agente (e por que caíram)

| # | Erro (teoria) | Como caiu (medição) |
|---|---|---|
| 1 | shell `oimpresso.com.html` (título "Chat") = "arquivo errado" → re-ancorou pro jsx | o shell é o VISUALIZADOR; o `*-page.jsx` é o SOURCE. Ambos do mesmo projeto vivo. Âncora ao source = certa |
| 2 | puxou o projeto DS (019dd02f), mostrou o template "Norte" como "o design atual" | era outra coisa (biblioteca de componentes). O design da tela está no 019dcfd3 |
| 3 | concluiu "é antigo" sem diffar | `md5` provou idêntico ao Cowork vivo — não é defasado |

**Padrão:** proveniência de design por **nome de arquivo / raciocínio** = erro repetível.
Por **DIFF + `DesignSync`/render** = verdade. É o mesmo "presença ≠ correção" / "medir, não
teorizar" do resto do dia.

## Pendência honesta (não resolvida)

Wagner disse "esse protótipo é antigo" mas o arquivo é provadamente atual (md5 idêntico). Não
ficou esclarecido se "antigo" = (1) arquivo defasado [refutado] ou (2) **direção de design a
redesenhar** [em aberto]. Se for (2), o próximo passo é gerar design NOVO no Cowork e migrar —
não deletar/re-ancorar. Aguarda Wagner cravar 1 ou 2.

## Ganho estrutural

A integração ao vivo abre o caminho pro que faltava: **âncora que não apodrece** — em vez de
apontar pra arquivo estático no repo, apontar/validar contra o Cowork vivo (`DesignSync
get_file`). O `anchor-content-check` (criado hoje) pode evoluir pra abrir o design no Cowork,
não só o arquivo local. Registrado como direção; implementação = trabalho futuro (evolução do
[ADR 0299](../decisions/0299-figma-nao-e-fonte-de-design.md), não supersede — Cowork sempre foi
a fonte; só ficou legível pela máquina agora).

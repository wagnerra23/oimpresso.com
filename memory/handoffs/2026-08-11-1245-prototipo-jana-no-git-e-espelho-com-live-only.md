---
id: handoffs-2026-08-11-1245-prototipo-jana-no-git-e-espelho-com-live-only
date: "2026-08-11"
slug: prototipo-jana-no-git-e-espelho-com-live-only
tldr: "Protótipo da Jana (jana-merge.jsx) desce pro git com SYNC provado; 4 defeitos do processo de espelho Cowork consertados com mutação rodada; ADR 0374 emenda a 0315. Ficam declarados 14 protótipos ainda fora do git e a rotina de frescor 34d fora do SLA."
type: handoff
status: concluido
authority: informational
lifecycle: ativo
kind: handoff
module: governance
tags: [design, prototipo, cowork, espelho, jana, adr-0374, lc-08]
---

# Handoff — protótipo da Jana no git + `--live-only` no espelho

## Onde parou

**Tudo mergeado.** PR [#5572](https://github.com/wagnerra23/oimpresso.com/pull/5572) (`9aeb66a0a89`, CI 121 pass) e [#5551](https://github.com/wagnerra23/oimpresso.com/pull/5551) antes dele.

Verificado em `origin/main` (não pela mensagem de sucesso): `jana-merge.jsx` + `.css`
presentes, `0374-emenda-0315-…md` presente, `export function liveOnly` no script.

## O que mudou, em uma frase cada

- **`prototipo-ui/cowork/jana-merge.jsx` + `.css`** — a fonte de design do Painel da Jana,
  `SYNC` provado por hash contra o vivo. Razão [W]: o time trabalha só com o git.
- **`--live-only`** — lista o que existe no Cowork e nunca desceu. Era invisível **por
  construção**; medido: 25/1310 paths, **14 protótipos de tela**.
- **`--export-from`** — escreve do JSON do `get_file`. Transcrever à mão me deu `STALE`.
- **`ehDeclaracaoNa()`** — `ancora.mjs` parou de imprimir `âncora ✓` para `n/a`.
- **hook `design-agente-ativa`** — mandava usar `list_projects` como prova; aquele tool
  só enumera design-systems e o protótipo vive em projeto **REGULAR**.
- **[ADR 0374](../decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md)** — emenda à 0315: espelhar Cowork → git é a rota **prevista**.
- **§5:** 4 lápides; a principal **revoga a regra `biz=NNN`** de 08-10, que bania o
  protótipo certo. **`LICOES_CODE`:** LC-08 79 → 81.

## O que o próximo tem que saber pra não repetir

1. **`DesignSync{list_projects}` NÃO é prova de ausência.** Vá direto ao `list_files` com
   `019dcfd3-6ef2-7ee6-8512-b1b0e5544e58`.
2. **Há arquivos HOMÔNIMOS no projeto vivo** (`jana-merge.jsx` na raiz **943 ln** e em
   `prototipo-ui/cowork/` **923 ln**, conteúdo diferente). O path canônico é o que o
   `cowork` do manifesto declara. Baixar do errado dá `STALE` — e medir um charter contra
   ele faz você "corrigir" o que estava certo (aconteceu).
3. **`--compare` verde ≠ espelho completo.** Ele mede o que ESTÁ lá. Use `--live-only`.
4. **Não transcreva conteúdo entre sistemas** — `--export-from` escreve do JSON.

## Pendente — decisão [W], com comando pronto

| item | estado |
|---|---|
| **14 protótipos LIVE-ONLY** | `--live-only` lista · `--export-from` escreve. Não feito porque 14 `get_file` não cabem numa sessão |
| **rotina de frescor 34d fora do SLA** | `--sla`: última rodada 2026-07-07, `98 unchecked`, limite 14d. **Causa raiz** de os 14 nunca descerem |
| **visreg sem módulo habilitado** | sub-nav some em **10/15** telas, sidebar em **15/15**. Buraco de **cobertura**, não falso-verde. Semear = rebaseline em massa das 15 |
| **ADR 0374** | `status: proposto` — merge dela é a ratificação |
| **certificado digital vencido há 5d** | prod, `/fiscal/config`. Sem NF-e enquanto isso |

## Estado MCP no momento do fechamento

Não consultado — o MCP (`mcp.oimpresso.com`, CT 100) ficou **fora do ar** durante boa
parte da sessão (medido: `ct100-mcp` offline ~25min via `tailscale status`; voltou às
~11:10 e o smoke SSH confirmou os containers de pé). O estado desta sessão está provado
por **git + gh + CI**, não por snapshot MCP — e isso fica dito em vez de simulado.

## Sessão irmã

[`2026-08-11-prototipo-jana-no-git-e-a-defesa-que-era-a-causa.md`](../sessions/2026-08-11-prototipo-jana-no-git-e-a-defesa-que-era-a-causa.md) — o relato, com os 4
erros meus na ordem em que aconteceram e o que os pegou (rodar o instrumento, nunca reler).

---
date: "2026-09-06"
time: "0924 BRT"
slug: "alvo-jana-exportado-maquina-espera-fases"
tldr: "[W] pediu 'exporte o ALVO': o primeiro <tela>.alvo.json do protocolo de export saiu da máquina (Painel da Jana, 9 seções, 3 runs byte-idênticos, T5 provado) — e a medição real pegou a máquina aprovando o ESQUELETO (719 vs 1011 nós no mesmo comando). Duas flags com bite-test fecham a classe (--aguardar-sumir · --quieto-ms). PR #6918 aberto, merge é [W]. O espelho segue atrás do Cowork no ciclo 04/09 (cli-tabs/chat-jana < 48 KB) — só o pacote regenerado no Cowork fecha."
decided_by: ["W"]
cycle: null
prs: [6918]
us: []
next_steps:
  - "[W] mergear #6918 (CI em execução no fechamento — conferir gh pr checks)"
  - "Cowork: regenerar o pacote (gerar-payload-partes --root <dir> --out sync/ --previous sync/bundle.manifest.json) e escrever 'bundle regenerado' no github.md — cli-tabs.jsx (role=tablist) e chat-jana.jsx (aria-hidden) só descem por ele"
  - "Depois do pacote: re-rodar o alvo (comando no README dos alvos) — o nav passa a ter classe e o alvo muda por causa certa"
  - "PR-A3 secao-check: o alvo agora tem consumidor a construir; o alvo.json é a fonte de teste que ele compara com o render"
  - "Exportar os próximos alvos por seção conforme o Cowork abre onda (Fiscal §3 do pacote de 03/09 tem 8 seções medidas do lado de lá)"
related_adrs: ["0384-design-sync-recibos-executaveis-por-tela", "0374-emenda-0315-espelho-cowork-e-rota-prevista", "0387-github-md-diario-cowork-aceito-e-tratado", "0256-knowledge-survival-meia-vida-catraca-sentinela"]
---

# Handoff — o ALVO da Jana saiu da máquina, e a máquina aprendeu a esperar

> Narrativa em [sessions/2026-09-06-alvo-jana-painel-exportado-por-maquina.md](../sessions/2026-09-06-alvo-jana-painel-exportado-por-maquina.md). Este handoff é o **estado pro próximo**.

## Estado no fechamento

| item | estado |
|---|---|
| [#6918](https://github.com/wagnerra23/oimpresso.com/pull/6918) | aberto · CI em execução · merge = [W] |
| `prototipo-ui/alvos/jana--index.alvo.json` | 9 seções · sha256 `1023efb94820bba2` · 3 runs idênticos · viewport 1280×900 · dark |
| `alvo.mjs --selftest --browser` | 9/9 (era 6/6) |
| espelho vs Cowork vivo (ciclo Jana 04/09) | **atrás**: `cli-tabs.jsx` 0 `tablist` · `chat-jana.jsx` sem `aria-hidden` no `jc-spark` · ambos < 48 KB ⇒ só pacote |
| pacote do Cowork | congelado em 24/08 (255/255 == local); github.md registra 3 ciclos "SEM pacote regenerado" (03/09 ×2, 04/09) |

## As 3 perguntas de [W], respondidas com medida

1. **"O design consegue mandar como o protocolo manda? Ele está bom?"** — O protocolo (`COLAR-NO-CODE-PROTOCOLO-COWORK-EXPORT.md`, lido inteiro, `truncated:false`) está bom: unidade = seção, 4 blocos A–D, pacote de 10 blocos, ancoragem dupla, placar, bateria T1–T7. O que **não** chega é o **build**: o gerador do pacote roda do lado que tem os arquivos em disco (Cowork) e não rodou desde 24/08. O Cowork declara isso honestamente em todo ciclo. Não é falha do protocolo — é a rotina do passo 4 (`COWORK-ESTRUTURA-E-TELAS.md`) sem executor.
2. **"Vai ter que separar por módulo → tela → blocos → ancoragem dupla?"** — Já é assim, e é por escrito: módulo → view/tela → **seção** (unidade do pedido) → 4 blocos A/B/C/D por seção, com ancoragem dupla no bloco A (alvo = protótipo medido; âncora = arquivo do `main`). O pacote da Jana de 04/09 segue a forma. O que "corta comandos" não é falta de detalhe — é pedido sem placar e alvo em prosa; o alvo.json versionado é o que torna o corte visível.
3. **"É usado máquina para baixar?"** — Sim: `aplicar-payload.mjs` (pacote, rota principal) e `cowork-mirror-freshness.mjs --export-from` (avulso persistido). Transcrever é proibido (ADR 0374). O gargalo é a **emissão** do lado Cowork, não a descida.

## Armadilhas desta rodada

- **Selftest verde ≠ mede a tela** (LC-30): o bite-test de fixture passava 6/6 e o `--alvo` real aprovava o esqueleto. Só a medição repetida no alvo real (719 → 1011) mostrou. Regra que fica: alvo novo se prova por **3 runs byte-idênticos no alvo real**, não só no selftest.
- **`waitForSelector(detached)` antes de o esqueleto montar é verdade vazia** — esperar `attached` primeiro (3 s, tolerante) e só então `detached`.
- **Porta efêmera na URL quebra o byte-idêntico** — o runner local fixa 5550; a URL é proveniência no alvo.
- **`spawnSync` no mesmo processo do servidor http bloqueia o event loop** — `page.goto` estoura 30 s parecendo problema do espelho.
- **LC-26 reincidiu** (par de barra invertida colapsou no heredoc): consertado com `chr(92)`; a linha 228 do alvo.mjs foi conferida barra a barra.

## Estado MCP no momento do fechamento

Brief #612 no início (gerado há 2 h): 0 tasks tocadas; sem `whats-active` exposto neste worktree filho; nenhuma task MCP criada — o trabalho é derivado do pedido [W] em chat e está no PR.

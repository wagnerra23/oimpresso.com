# PROMPT pra o Cowork — "todo ciclo de design termina regenerando o pacote" (cole no chat do Design)

> **De:** Claude Code → **Para:** Cowork (o Claude do `claude.ai/design`) · **Data:** 2026-09-01
> **O que é:** prompt único, auto-suficiente — a REGRA DE SAÍDA que faltava no ciclo. Wagner cola
> isto no chat do Design (ou o Cowork lê daqui, já que ele lê o `main` no início de todo chat).
> Append-only. Antecessores do tema: [`CODE_NOTES.prompt-cowork-payload-gerador-2026-08-22.md`](CODE_NOTES.prompt-cowork-payload-gerador-2026-08-22.md)
> (o gerador — a FORMA do pacote mora lá) e [`CODE_NOTES.resposta-pedido-reexport-2026-08-28.md`](CODE_NOTES.resposta-pedido-reexport-2026-08-28.md).
> ⚠️ Siglas `[CL]`/`[CC]` estão invertidas entre documentos antigos — escrevo por extenso.

> ⚠️ **ERRATA (2026-09-01, mais tarde no mesmo dia) — a premissa "o Cowork lê daqui" acima é FALSA.**
> O corpo fica como está (registro datado), mas o parêntese da linha 5 induz ao erro: o Cowork não lê
> "o `main`" inteiro — o `CLAUDE.md` do projeto dele nomeia **6 documentos** de read-order
> (`COWORK-ESTRUTURA-E-TELAS.md`, `FRESCOR-PRODUCAO-vs-PROTOTIPO.md`, `PRE-FLIGHT-TELA.md`, o charter
> da tela, `PROTOCOL.md`, `CLAUDE_DESIGN_BRIEFING.md` + `LICOES_CC.md`), e **nenhum** deles cita este
> arquivo. Medido: 0 ocorrências de `gerar-payload`/`bundle.manifest`/`sync/payload` nos seis.
> Ou seja: a regra nunca chegou — não foi ignorada. **A regra agora mora no passo 4 da `## 🔁 ROTINA`
> de [`COWORK-ESTRUTURA-E-TELAS.md`](COWORK-ESTRUTURA-E-TELAS.md)**, que é o item 1 do read-order.
> Este prompt segue válido para [W] colar no chat do Design; deixou de ser o único caminho.

---

## O fato, medido em 2026-09-01 (desta vez do lado Code, com auth própria)

- O `sync/bundle.manifest.json` do projeto está **congelado em 2026-08-24T22:49Z** (snapshot,
  255 arquivos, 7.163.784 bytes). Baixei e conferi hoje.
- Desde então você fechou **três ciclos** que não estão em pacote nenhum: Arquivos/ComVis refinos
  (26/08), Jana metas + telas novas + errata (27/08), fechamento Jana + visão-geral ondas 4-7
  (28/08) e o pedido do guard de pele paralela (31/08).
- Medição live-only de hoje (`--live-only --ledger`, primeira desta máquina pós `/design-login`):
  **147 arquivos existem no vivo e não desceram** — incluindo `cowork-inbox/ponte/**` (27 docs),
  os charters de `resources/js/Pages/Ponto/**`, `scripts/cowork-paridade.mjs` e
  `cli-pagehead.jsx`/`cli-tabs.jsx`.
- Consequência prática do pacote velho: o lado Code **não pode aplicá-lo** (regrediria o espelho,
  que recebeu pulls mais novos em 27/08) — e sem pacote fresco a descida volta a ser arquivo a
  arquivo, a rota que esquece css/js por natureza.

## A regra (1 linha, e é a única coisa que este prompt pede)

> **Ao fechar QUALQUER ciclo de design — junto com o bloco novo do `github.md` — regenere o
> pacote:** `node scripts/design-sync/gerar-payload-partes.mjs --root <dir-do-projeto> --out sync/
> --previous sync/bundle.manifest.json` → suba `sync/bundle.manifest.json` + as partes novas, e
> registre no `github.md` a linha `bundle regenerado (<data> · N arquivos)`.

Detalhes de FORMA (tamanho de parte ≥ piso de persistência, digest com selftest, delta vs
snapshot) já estão acordados no prompt de 2026-08-22 — nada muda neles.

## Por que agora fecha o loop (o que mudou do lado Code)

1. `/design-login` feito nesta máquina (2026-09-01): o Code baixa as partes **direto** e aplica
   com `aplicar-payload.mjs` (dry-run → promoção atômica com rollback). A rota do pacote virou o
   **caminho padrão** da fase −1; a pontual (`get_file` avulso) vira exceção documentada.
2. O `github.md` é artefato **tratado** desde a [ADR 0387](../memory/decisions/0387-github-md-diario-cowork-aceito-e-tratado.md):
   o Code lê o diário ao abrir ciclo — a linha `bundle regenerado` é o **recibo** que ele audita
   (e a ausência dela é o que ele cobra).
3. A lápide §5 2026-08-27 já tinha nomeado: *"o que fecha esta classe não é máquina do repo — é a
   regeneração do bundle do lado Cowork ao fim de todo ciclo"*. Este prompt é essa regra virando
   combinado explícito.

## O que o Code faz em troca (o ciclo completo, sem memória humana)

Você fecha o ciclo e regenera → eu baixo o pacote inteiro → `--dry` valida grafo e hashes em
staging → promovo (espelho + design-docs + runtime do DS, atômico) → rodo as medições
(`--compare`, `--live-only --ledger`, `--sla-docs`) → abro o PR. "Esquecer css/js" deixa de ser
possível por construção: o pacote transporta o projeto inteiro, com sha256 por arquivo.

---

> **DECISÃO [W] 2026-09-06** (sessão da dupla âncora, textual: *"2 e 3 ok pode fazer"*): a regra de saída deste prompt deixa de ser pedido e vira **rotina obrigatória do ciclo do Cowork** — todo ciclo de design termina com `gerar-payload-partes.mjs` regenerando o pacote e a linha "bundle regenerado" no `github.md`. Recibo do custo de não fazer, medido no mesmo dia: `sync/bundle.manifest.json` remoto byte-idêntico ao local de 24/08 (255/255) enquanto 23 âncoras do espelho abaixo do piso de persistência do `get_file` ficaram sem veredito de fidelidade (ledger `scripts/governance/.cowork-freshness-ledger.json`, rodadas de 2026-09-05/06). Registrado no painel (`protocolo.config.mjs`, fase -1). Cole este bloco no chat do Design se o Cowork ainda não tiver lido.

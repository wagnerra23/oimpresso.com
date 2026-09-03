---
slug: 0389-emenda-0374-escrita-do-espelho-quando-o-get-file-volta-inline
number: 389
title: "Emenda à 0374 — quando o `get_file` devolve INLINE, o agente escreve o arquivo (a proibição de transcrever passa a valer só onde existe rota de máquina)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-03"
module: governance
tags: [governance, design, prototipo, cowork, designsync, espelho, git, transporte]
supersedes: []
superseded_by: []
related:
  - 0374-emenda-0315-espelho-cowork-e-rota-prevista
  - 0315-design-sync-claude-design-vs-cowork-charter
  - 0325-designsync-projetos-por-id
---

# Emenda à 0374 — o impasse do arquivo pequeno

## Contexto

A [ADR 0374](0374-emenda-0315-espelho-cowork-e-rota-prevista.md), em `## Consequências`, diz:

> *"O export é por **`cowork-mirror-freshness.mjs --export-from <dir>`**, que escreve o
> `raw.content` do `get_file`. Transcrever à mão é proibido (§5 2026-08-11)."*

A regra nasceu de um incidente real e caro (lápide §5 de 2026-08-11): uma sessão trouxe o
`jana-merge.jsx` escrevendo ~900 linhas à mão, pegou a **cópia errada** das duas homônimas do
projeto, e depois "corrigiu" um charter contra a versão errada — LC-08 cometido dentro da
correção de um LC-08. A regra que ficou: *"a escrita sai do dado, por script"*.

**O que ninguém reconciliou:** o `--export-from` consome um diretório de **JSONs em disco**, e
quem produz esse diretório é o `DesignSync.get_file`. Só que o `get_file` **persiste em arquivo
apenas o que é grande**: medido em 2026-08-27, o corte fica em ~48–49 KB de output. Abaixo disso
o conteúdo volta **inline**, dentro do contexto do agente — e aí não existe "o dado em disco"
para o script ler.

Ou seja: para arquivo pequeno, a regra manda usar uma máquina cuja entrada não há como produzir.
O agente fica entre desobedecer e não fazer. Medido nesta sessão: `cockpit_domains.css` (5,7 KB)
voltou inline, `truncated: false`.

[W] em 2026-09-03, textual: *"ADR 0374 isso esta errado tem que ser editado para permitir isso eu
ja tinha mandado"* e *"remova a adr se isso não conseguir"*. A 0374 **não** é removida: o resto
dela (o espelho Cowork é rota prevista) é o que sustenta os ~260 arquivos já versionados —
removê-la reabriria o problema que ela fechou. Emenda-se a linha que trava.

## Decisão

A proibição de transcrever **passa a valer apenas onde existe rota de máquina**. Onde não existe,
o agente escreve o arquivo — e paga um preço em verificação.

| caso | rota | quem escreve |
|---|---|---|
| `get_file` persistiu em **arquivo** (grande) | `--export-from <dir>` | **a máquina** (inalterado) |
| bundle de partes (`gerar-payload-partes`) | `aplicar-payload.mjs` | **a máquina** (inalterado) |
| `get_file` voltou **inline** (pequeno) | — não existe — | **o agente**, sob as condições abaixo |

**Condições, e nenhuma é opcional.** Escrita inline só é legítima com as quatro:

1. **Uma origem só, resolvida antes.** `DesignSync.list_files` primeiro; havendo mais de uma cópia
   do mesmo nome, o path canônico é o que o **manifesto do consumidor** declara — foi exatamente
   o erro de 2026-08-11, e ele continua proibido.
2. **`truncated: false`** na resposta. Resposta truncada é dado incompleto: aborta.
3. **Verificação pós-escrita, com recibo no PR.** Rodar o consumidor do arquivo e colar a saída:
   o parser/gate que já existe para aquele tipo (`--preview-ds` para CSS/bundle do DS,
   `ds-domains-companion --check` para o companion, `--compare --check` quando houver snapshot).
   Verde é o recibo; sem recibo a escrita não entra.
4. **Declarar no corpo do PR** que a escrita foi inline e por quê (qual arquivo, qual tamanho).

## O que esta emenda NÃO resolve — e é a parte que muda o que você conclui

**Não existe verificação independente para escrita inline.** Qualquer releitura passa pelo mesmo
contexto que escreveu; comparar o que escrevi contra um snapshot que também escrevi é circular e
não prova nada. As condições acima **reduzem** o risco (origem única, não-truncado, parser do
consumidor) — não o eliminam. Quem ler isto no futuro não deve tratar o verde do passo 3 como
prova de fidelidade byte-a-byte; ele prova que o resultado é **utilizável pelo consumidor**.

**Não destrava arquivo acima do cap.** O `get_file` é capado em 256 KiB. O `_ds_bundle.js` do DS
tem **290 KB** — ele não desce nem com esta emenda, e nenhuma permissão muda isso. A única rota é
o bundle emitido do lado Cowork (`gerar-payload-partes.mjs`), cuja emissão **segue sem dono nem
automação** (medido 2026-08-31; pedido formal em 2026-09-01,
`prototipo-ui/CODE_NOTES.prompt-cowork-regenerar-bundle-por-ciclo-2026-09-01.md`).

**Não muda o sentido do fluxo do Design System.** `colors_and_type.css` e `cockpit_domains.css`
são **gerados do nosso repo** — os próprios headers dizem (*"GERADO por
`scripts/design-sync/ds-domains-companion.mjs`… Fonte: `resources/css/tokens/_generated-cockpit-*`"*
e *"authored as DTCG JSON… copied verbatim"*). Baixá-los do Cowork é buscar cópia do que nós
emitimos. Para eles a pergunta certa nunca é *"está baixado?"*, é *"bate com o SSOT?"* — e isso
já tem três máquinas: `ds-domains-companion --check`, `ds-token-version --check`,
`dtcg-equivalence --schema`. Todas verdes em 2026-09-03.

**Não afrouxa a 0315 Eixo A.** Escrita para o `claude.ai/design` segue gated por opt-in.

## Consequências

- A frase da 0374 em `## Consequências` fica **substituída** pela tabela desta ADR. O resto da
  0374 (o espelho Cowork é rota prevista; `--live-only` mede o que nunca desceu; `--compare` mede
  o frescor do que já está lá) continua **inteiro e valendo** — esta emenda toca uma linha.
- A lápide §5 de 2026-08-11 ganha **emenda** (não some — append-only): o limite dela passa a ser
  *"não transcrever quando existe rota de máquina"*, e o caso inline sai do escopo dela.
- O `--export-from` continua sendo a rota preferida sempre que houver arquivo. Escolher escrita
  inline tendo o arquivo em disco é violação.

## Gate de reversão

Se aparecer um incidente de corrupção rastreado a uma escrita inline feita sob estas condições,
esta ADR volta atrás e o caso pequeno passa a ser **bloqueado** até o bundle ganhar dono — que é
a rota que não passa pelo contexto de ninguém. O sinal a vigiar: `--compare --check` acusando
`STALE` num arquivo que a sessão anterior declarou ter escrito e verificado.

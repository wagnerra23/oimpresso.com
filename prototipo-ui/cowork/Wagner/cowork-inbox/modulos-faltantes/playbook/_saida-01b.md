---
sessao: "01b"
titulo: "Contratos de tela — CV, Suporte, Vestuário — entregue"
autor: "[CL]"
data: 2026-10-13
base: origin/main b6abda9226
thread: 01-contratos.md
veredito: "entregue — comunicacao-visual, vestuario-etiquetas e suporte-log; Suporte/Empresas e Suporte/Visao já têm contrato por tela."
---
# _saida-01b · Contratos de CV, Suporte e Vestuário

> Recibo complementar ao `_saida-01.md` (06/10, "bloqueada"), que fica intocado: ele já foi verificado no espelho, e
> editá-lo à mão armaria o required "espelho — mexeu depois de verificar" (§5 2026-09-24). Este arquivo o substitui
> como estado atual da thread.

## O que mudou desde 06/10
Os bloqueios B1–B4 do `_saida-01.md` caíram: a thread passou a poder pôr **só o atributo** `data-contract` nas
Pages (precedente #9185/#9225), com RUNBOOK da tela criado quando o hook `block-mwart-violation` o exige; o `fonte`
dos contratos aponta para `prototipo-ui/cowork/Wagner/<x>-page.jsx`; e as decisões VEST-D1/D2 foram respondidas
([W] 07/10).

## Entregue

| contrato | Page | seções (âncora, em ordem) | copy | origem |
|---|---|---|---|---|
| `comunicacao-visual` | `ComunicacaoVisual/Index` | `cabecalho` · `calculadora` · `ajustes` · `total` · `em-breve` | 1 · 11 · 2 · 3 · 4 | contrato-fonte da thread (CV não tem alvo A1) |
| `vestuario-etiquetas` | `Vestuario/Etiquetas/Index` | `cabecalho` · `config` · `lote` | 2 · 5 · 10 | alvo `vestuario--etiquetas--index` (A1 de lote-trio-medida) |
| `suporte-log` | `Suporte/Log` | `cabecalho` · `log` | 2 · 5 | contrato-fonte da thread (seções `cabecalho`/`log`) |

Copy = interseção conferida por máquina: string do contrato-fonte presente literalmente no protótipo, no código da
Page sem comentário e, no Vestuário, na `base.assinatura` do alvo. Cada string fica na seção onde a Page a
renderiza ("Adicionar peça" na calculadora, "Baixar ZPL" no lote).

## Fora, e por quê (detalhe no `_nota` de cada contrato)
- **Suporte/Empresas e Suporte/Visao** já têm contrato por tela (`suporte-empresas` #8934, `suporte-visao` #9259,
  ambos da thread 01 de telas-soltas). Um `suporte.contract.json` sobre as 3 telas seria segundo dono do mesmo
  alvo; o Log ganhou o seu, `suporte-log` (slug da tela, ADR 0286). **A prova `suporte.contract.json` do índice
  precisa virar `suporte-log.contract.json`** — pedido ao Cowork abaixo.
- **CV:** `pcp` ("Na fábrica agora" e etapas) não existe na Page; "Salvar orçamento", "Enviar PDF no WhatsApp",
  "Conferido no servidor" e "m² no orçamento" só no protótipo. Construir ou podar é decisão de produto.
- **Vestuário:** `previa` sai pela VEST-D2 (tirar a promessa do charter; prévia vira thread própria) e o alvo também
  a exclui. `aviso-permissao` sai pela VEST-D1 (hard-block de verdade, com uma semana em modo log antes): o estado
  de tela ainda não existe, e pinar a copy do aviso atual travaria o lado que [W] decidiu trocar.
- **Ressalva de fonte:** os charters de CV e Vestuário declaram o `.jsx` como porte REVERSO do vivo
  (`bundle_source`, 2026-09-06). O contrato trava presença de âncora e copy; não é aprovação de forma.

## Pages: só âncoras
- `ComunicacaoVisual/Index.tsx`: 5 atributos; `cabecalho` num `<div>` sem classe em volta do `PageHeader` canônico,
  que não repassa atributo ao DOM; `calculadora` e `em-breve` vão no `<Card>`, que repassa.
- `Vestuario/Etiquetas/Index.tsx`: 3 atributos; `cabecalho` num `<div>` sem classe em volta do `PageHeader` de
  `Components/shared`.
- `Suporte/Log.tsx`: nenhuma mudança (âncoras `cabecalho` e `log` já existiam, thread 02).

Zero handler, dado, copy, classe ou cálculo. RUNBOOK criado: `memory/requisitos/ComunicacaoVisual/RUNBOOK-comunicacao-visual-index.md`
(o Vestuário já tinha `RUNBOOK-etiqueta-tag.md`, declarado no charter). `last_run` dos 2 `casos.md` tocados →
2026-10-13 (G-6), com nota de revalidação analítica.

## Provas (rodadas localmente, árvore com o PR, após rebase em `b6abda9226`)
- `contrato-de-tela --contract` nos 3: rc=0.
- Modos do job "Contratos de tela": os 92 contratos ativos (sem EXEMPLO) rc=0; `--map --check` rc=0 (5/5 · 3/3 · 2/2
  `🟢 portado`); `--anti-tautologia` rc=0, nenhum aviso para os 3; `--preflight origin/main` rc=0;
  `contrato-de-tela.test.mjs` e `contrato-de-tela-relevante.test.mjs` rc=0; intenção de fluxo rc=0.
- **Mordida por mutação em cópia isolada (`--root`):** intacta rc=0 nos 3; sem `data-contract` de `total`, `lote`
  e `log`, rc=1 com `seção "<id>" sem âncora data-contract no alvo`.
- `casos-coverage-guard` (após o commit): sem violação nova deste PR.

## Pedido literal ao Cowork (colável)
```
modulos-faltantes, índice: na thread 01, trocar a prova
governance/design/contracts/suporte.contract.json por
governance/design/contracts/suporte-log.contract.json. Suporte/Empresas e
Suporte/Visao têm contrato próprio (suporte-empresas, suporte-visao) pela
thread 01 de telas-soltas; um contrato único das 3 telas seria segundo dono.
```

## Placar
entregue 3 contratos (CV, Vestuário, Suporte·Log) · Suporte·Empresas/Visão cobertas por outra thread · 2 Pages com
só atributo · 1 RUNBOOK.

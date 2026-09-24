---
id: reference-fluxo-deploy
name: Fluxo — Deploy
description: Como o código chega em produção — build no runner, entrega por SSH, e a separação de runtime que decide o que pode rodar onde.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-09-22"
nav_group: fluxo
nav_order: 30
lente: [construir]
related: [reference-fluxo-venda]
---

# Fluxo — Deploy

> O caminho executável é o workflow [`.github/workflows/deploy.yml`](../../.github/workflows/deploy.yml)
> — ele é o dono, e muda mais rápido que qualquer texto. Aqui fica o **modelo mental**: por que
> o deploy tem a forma que tem.

## Dois lugares, e eles não são intercambiáveis

A regra estrutural mais importante do ambiente ([ADR 0062](../decisions/0062-separacao-runtime-hostinger-ct100.md)):

| | o que roda | o que **nunca** roda |
|---|---|---|
| **Hostinger** (shared) | a aplicação web, servida por request | daemon de qualquer tipo |
| **CT 100** (Proxmox) | os daemons e serviços de plataforma | — |

Não é preferência de arquitetura: é contrato de hospedagem. Shared hosting não sustenta
processo residente, e tentar isso é a origem de uma família inteira de incidentes.

Corolário prático: **"funciona no CT 100" não significa "vai funcionar no Hostinger"**, e vice-versa.
Os dois têm capacidades diferentes por decisão.

## O build não acontece no servidor

Os bundles de front-end são construídos **no runner** do CI e viajam como artefato. O servidor
recebe o resultado — não compila.

O motivo é o mesmo de sempre: shared hosting não tem folga para build, e build no destino
significa janela em que produção está com bundle pela metade.

## O que torna o deploy frágil, e como pensar nisso

Deploy é o momento em que o estado do servidor muda **fora do controle do git**. Duas classes
de problema aparecem aqui e não aparecem em lugar nenhum:

1. **Passo interrompido deixa estado inconsistente.** Atualizar código sem completar as etapas
   seguintes pode deixar a aplicação servindo uma combinação que nunca existiu no repositório —
   e o sintoma costuma ser genérico demais para apontar a causa.
2. **A máquina de destino tem limitações próprias** (extensões disponíveis, versão de
   ferramenta, o que o painel controla e o que não controla). Elas não aparecem em nenhum teste
   local, porque local não é aquele ambiente.

Daí a regra cultural do projeto: **evidência de que funcionou vem do ambiente real**, com
status HTTP literal — não de teste local verde. Está em
[`memory/proibicoes.md`](../proibicoes.md) (§ *Claim sem evidência*), e não é formalidade: a
seção nasceu de declarações de "está funcionando" que não se sustentaram.

## Nunca por SSH, sempre por git

Editar arquivo direto no servidor é proibido, mesmo quando é mais rápido — e é sempre mais
rápido, que é justamente a tentação. O custo aparece depois: o servidor passa a divergir do
repositório em silêncio, e a próxima entrega sobrescreve ou conflita com o ajuste que ninguém
registrou.

A regra é [`memory/proibicoes.md`](../proibicoes.md) (§ *Mexeu, REGISTRA*), e o caminho é
sempre o mesmo: PR → CI → merge → deploy.

## Contrato verificável da execução

### Entrada, invocador e decisão

A entrada normal é um `push` no branch `main` que toca arquivo não ignorado pelo gatilho. A
entrada excepcional é `workflow_dispatch`, com os parâmetros declarados no próprio workflow.
O invocador é o GitHub Actions; o SHA de `github.sha` daquele run é a unidade que pode ser
publicada. A classificação do diff decide entre **sync leve** e **deploy completo**. Na dúvida,
o workflow escolhe o completo.

O build precisa terminar e publicar o artefato `vite-build` antes do job de deploy. O destino
faz reset para o SHA do run, e não para uma ponta móvel de `main`. Essas relações são verificadas
pelo bite-test [`deploy-workflow.test.mjs`](../../scripts/governance/deploy-workflow.test.mjs).

### Saída, prova e falso-verde conhecido

A saída durável é o SHA aplicado no checkout do servidor e, quando há front-end, os bundles do
artefato construído no runner. O recibo é o run do workflow com build, publicação, reset de
OPcache e smoke HTTP. O teste de contrato roda no CI e contém controles que removem a dependência
do build, afrouxam artefato ausente, trocam SHA fixo por `origin/main` e tornam o smoke mudo; as
quatro mutações precisam ficar vermelhas.

Há três falsos-verdes que o fluxo não aceita:

- build local verde não prova publicação no Hostinger;
- job de deploy verde sem consultar `/login` não prova boot web;
- HTTP 200 sozinho não prova que um bundle alterado foi publicado, por isso o workflow compara
  os hashes servidos quando o front-end mudou.

### Falha, recuperação e limite da prova

Não há rollback automático para outro commit. O workflow cria backup antes da mudança e usa um
failsafe: se o código não bootar, mantém a aplicação em maintenance com 503, em vez de expor 500.
A recuperação exige diagnosticar o run e restaurar ou redeployar um SHA saudável; não se edita
produção por SSH fora do fluxo versionado.

O bite-test prova a topologia do YAML, mas não mede rede, credenciais, Hostinger, OPcache nem a
resposta atual da aplicação. Essa prova só existe no run real. Ausência do run ou impossibilidade
de consultar produção é **NÃO MEDIDO**, nunca sucesso herdado de execução anterior.

---
id: reference-fluxo-deploy
name: Fluxo — Deploy
description: Como o código chega em produção — build no runner, entrega por SSH, e a separação de runtime que decide o que pode rodar onde.
type: reference
authority: canonical
lifecycle: ativo
updated_at: "2026-08-03"
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

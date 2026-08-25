---
id: cowork-inbox-fiscal-sped-charter
page: /fiscal/sped
component: resources/js/Pages/Fiscal/Sped.tsx (alvo da tradução)
related_prototype: "F1 Cowork — fiscal-subpages.jsx §FxSpedPage"
page_id: fiscal-sped-ondas-f1
module: Fiscal
status: draft
owner: wagner
autor: "[CC]"
created: 2026-08-24
related_us: [US-FISCAL-010, US-FISCAL-016, US-FISCAL-017, US-FISCAL-020]
related_adrs: [0093-multi-tenant-isolation-tier-0, 0286-contrato-de-tela]
contrato: prototipo-ui/contrato/fiscal-sped.contract.json
---

# Charter — SPED e livros · ondas F1 (delta sobre o vivo)

> A lei da tela é `resources/js/Pages/Fiscal/Sped.charter.md` no `main` (já reconciliado em 2026-07-27). Aqui só o delta: **validação visível**, **prévia do TXT** e **honestidade sobre o que não foi validado**.

## Mission

Antes de mandar arquivo pro Fisco, a contadora precisa ver **por que** o botão está desabilitado, **o que** vai dentro do arquivo, e **o que ninguém provou ainda**.

## Goals (faz)

1. **Barra de validação da competência** com os quatro pré-requisitos nomeados um a um: ano ≥ 2020, competência não-futura, mês fechado, trava `sped_simples_only_lock`.
2. **Bypass de superadmin explícito** — liberar a trava é ação nomeada na tela, não configuração escondida; reativar é um clique.
3. **Prévia do TXT** com linhas `|REG|…` reais dos blocos 0 · C · E · H · 9, declarando que são amostra encurtada para leitura.
4. **Cartão de validação externa** dizendo o estado verdadeiro: smoke no PVA-EFD **nunca executado**, golden file **não existe**.
5. **Blocos com os registros que cada um contém** (0000/0001/0005/0100/0150/0190/0200 · C001…C990 · E001…E990 · H001/H990 · 9001…9999).

## Non-Goals (NÃO faz)

- ❌ Gerar o arquivo de verdade no F1 (o gerador é do vivo; aqui a ação é encenada com o resultado nomeado).
- ❌ EFD-Contribuições, apuração de ISS, conciliação SEFAZ × ERP — backlog do charter do vivo.
- ❌ Entradas (DF-e manifestada) e inventário real no Bloco H.
- ❌ Sugerir prazo por cron: o prazo da EFD é fixado por UF; o dia 15 é heurística visual e a tela diz isso.

## Anti-hooks

- 🚫 Não liberar a trava por default — é `true` fail-secure, e desligar é decisão de [W].
- 🚫 Não chamar o gerador de "validado" enquanto os testes forem *source-grep*: eles procuram nome de método no fonte, não conferem linha do arquivo.
- 🚫 Não usar CFOP fixo na prévia: 5xxx é interno, 6xxx interestadual — o hardcode 5102 já gerou SPED inválido uma vez.
- 🚫 Não exibir prévia sem dizer que é amostra — contadora que imprime a prévia como arquivo entrega lixo pro Fisco.

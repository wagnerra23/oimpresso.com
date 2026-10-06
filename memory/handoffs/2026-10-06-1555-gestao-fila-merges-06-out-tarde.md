---
date: "2026-10-06"
time: "15:55 BRT"
slug: gestao-fila-merges-06-out-tarde
tldr: "Gestão da fila de merges 06/10 tarde (11:05→15:55 BRT). Entraram ~20 PRs do ERP e 5 do app (#56, #76, #79, #80, #81). Leva de ~20 threads de playbook em curso. Vigia único com resolvedor automático de conflito de recibo. Aprovações [W]: baselines #8795/#8796, #8817 Comissionados sem flag, #8824 Fator R, #8836 nota com itens reais."
prs: [8794, 8795, 8796, 8797, 8798, 8800, 8801, 8802, 8803, 8804, 8805, 8806, 8807, 8808, 8810, 8814, 8816, 8822]
---

# Handoff — Gestão da fila de merges (06/10, tarde)

Sessão "Gerenciar a fila de merges (sucessor da manhã de 06/10)". A autorização do [W] continua a mesma ("gerencie o merge de todos, não me pergunte", com as exceções de valor, migration destrutiva, cutover e baseline). O [W] pediu para fechar esta sessão e abrir outro gerente, porque esta já estava grande.

## Estado ao fechar (~18:55Z)

**App:** fila vazia. Entraram #56 (rótulo de Produtos), #76 e #79 (workflow do Luiz, criado e depois removido), #80 (histórico de serviços) e #81 (peças trocadas). Todos com `--admin`, travados no head verificado. A issue #75 foi atribuída ao LuizWr2.

**ERP, abertos e já aprovados pelo [W] (falta só required verde):**
- **#8817** Comissionados (cutover sem flag, mexe no percentual). Aprovado no head `8e46a23`. O head atual `6a34bf2` difere só em 2 linhas do RUNBOOK.
- **#8824** Fator R. Aprovado no head `0971a6d`.
- **#8836** nota com os itens reais (NfeService). Aprovado. O head `271a44e` difere do mostrado ao [W] só pela linha da lane. **Depois do deploy, avisar o [W]: ele mesmo emite a nota de teste.** Não emitir.

**ERP, abertos na rotina normal:** #8811, #8813 (lane `financeiro-pest` disparada no head, run 37510227915; esperar o teste executar), #8818, #8820, #8821, #8823, #8825, #8826, #8827, #8828 (só depois do #8827, então `update-branch`), #8829 (head novo `3204a17` com o teste corrigido; o escopo multi-tenant estava certo), #8830, #8831, #8832, #8833, #8834, #8835 e #8837.

**Sessões esperando aviso desta fila após merge** (para subir o `_saida` ao Cowork e abrir o "registra envio" um por vez): Fiscal 11 (#8823), Fiscal 24 (#8824), Fiscal 17 (#8836), Produto 10 (#8821), Financeiro 12 (#8813).

**Retomar depois:** a sessão "modulos-faltantes 02" (`local_46628d8d…`) parou de propósito até o PR da modulos-faltantes 01 entrar.

**Chips do handoff 49 abertos pelo [W]:** recibos que faltam + medição; venda-menu Q1 e Q3 (o Q3 mexe em valor e vai pedir ok do [W]); modulos 01 e A1; modulos-faltantes 01 e 02. Ficaram para uma próxima leva: venda-menu C1 (depende do recibo do C0), as threads "PUXAR" e o §3.4 do `PEDIDO-CL-ordem-pendencias`.

## Ferramentas (fora do repo, persistem): `D:/oimpresso.com/.claude/gestor-fila-scripts/`

- `lote.sh` + `lote.txt`: **vigia único e sequencial**, com um `git fetch` por vez. Mergeia quando: required 48/48 verde (fail-closed: saída vazia não passa), baselines iguais às aprovadas (2 leituras antes de parar) e `test-lane-coverage` limpo. Se o conflito estiver **só** nos arquivos de recibo, chama `resolver.sh`. Rodar com o Monitor: `bash D:/oimpresso.com/.claude/gestor-fila-scripts/lote.sh`. Para incluir um PR, acrescente a linha `N <arquivo de snaps aprovados>` no `lote.txt`.
- `resolver.sh` + `uniao.mjs`: merge de origin/main + união de `enviados-cowork*.json` (por chave, fica o `em` mais recente) e do `.cowork-freshness-ledger.json` (por conteúdo). Valida o JSON e faz push sem force. Aborta se houver conflito fora desses arquivos. Fez 9 resoluções sem erro hoje.
- `chk.sh N`: required = união `classic_protection` + `rulesets` × check-runs do head, com arquivos temporários por PR.
- `vigia.sh` (fila a cada 5 min) e `deploy-wait.sh <sha> <rótulo>` (deploy com sucesso que contém o merge, por ancestralidade).

## Lições (medidas)

1. **Vigias por PR em paralelo disputavam o repo.** Fetch concorrente produziu "baselines diferentes" falso e arquivos temporários compartilhados devolviam saída vazia. Remédio: vigia único sequencial + temporários por PR.
2. **O vigia aceitava saída vazia do `chk` como "nada falta".** Só não houve merge errado porque o `test-lane-coverage` segurou e o merge sem `--admin` respeita a proteção. Agora é fail-closed.
3. **LC-26 duas vezes, em scripts meus** (`\1 \2` colapsado na escrita). O conserto foi `chr(92)` + varredura de bytes de controle.
4. **Tabela antes→depois de valor:** quando o head muda depois do ok do [W], confira o diff entre o head aprovado e o novo antes de mergear.
5. **`git show <ref>:<path>` no Git Bash** devolve erro ou vazio sem `MSYS_NO_PATHCONV=1`, e o grep resultante deu "0" falso. Controle positivo sempre.
6. Um teste em lane que nunca rodou (`\n` literal no `sells-pest.yml` do #8811) só apareceu porque o Pest saiu com exit 2. O `test-lane-coverage` pegou "pendente".

## Pendências só do [W]

- Testar no app o histórico de serviços e as peças trocadas com usuário real (ERP #8798 + app #80/#81 em produção).
- Emitir a nota de teste do #8836 depois do deploy.
- Ligar as Etiquetas no biz=1 (`.env`, as duas linhas juntas), cobrança do GitHub Actions, lojas do app, `.env` C1 de Vendas.

## Estado MCP no momento do fechamento

`brief-fetch` do início da sessão (Brief #721): cycle sem dado; 3 HITL pendentes; 674 US sem dono. Não consultei de novo: esta sessão não criou nem moveu task.

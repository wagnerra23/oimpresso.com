---
thread: "04"
modulo: ancora
dono: "[CL]"
prefixo: prototipo-ui/ancora.mjs
depende: ["03"]
base: remedir antes de escrever (li ed4398d77437 em 2026-09-10)
---
# 04 · Fechar o denominador e imprimir a cobertura

## Problema (medido, não suposto)
Ninguém sabe **quantas** telas têm âncora. O playbook de 09/09 mediu **189** charters e **~146** declarações; a releitura de 10/09 leu **~150** — as duas varreduras saíram **bounded** (10 s de budget, 343 de 400 de 808 candidatos) e **as duas** deixaram os mesmos prefixos de fora: `Nfse`, `Purchase`, `RecurringBilling`, `Site`, `Stock*`, `Suporte`, `Tarefas`, `User`, `Vestuario`, `Whatsapp`, `governance`, `Copiloto`, `superadmin`. Uma ferramenta que já anda o disco inteiro (`walk`) não deveria depender de busca com budget para responder isso.

## O que fazer
1. `--list --json` passa a emitir, além das linhas, um objeto `resumo`:
   `{ total, com_ancora, na_declarado, sem_campo, por_via: { related, bundle, visual, component } }`.
2. **Invariante dura:** `com_ancora + na_declarado + sem_campo === total`. Se não somar, sai **exit 2** com a lista dos que não classificaram — o número que não fecha é mais útil que o número bonito.
3. `--list` (texto) ganha uma última linha com o mesmo resumo, uma linha só.

## Prova (execução, não estrutura)
- `node prototipo-ui/ancora.mjs --list --json` → recibo `_saida-04.md` com o objeto `resumo` colado **inteiro** e o `total` batendo com `find resources/js/Pages -name '*.charter.md' | wc -l`.
- `node prototipo-ui/ancora.mjs --selftest` → exit 0.
- **Caso de sanidade obrigatório:** rodar o resumo, apagar mentalmente 1 charter conhecido (`Cliente/Index`) do filtro e conferir que o total cai em 1 — sonda que não reage a mudança conhecida não é sonda.

## Parar se
- O `total` divergir do `wc -l` → **pare**: o `walk` está pulando pasta, e isso é defeito maior que a cobertura.
- Qualquer consumidor (`design-coverage`) ler `--list --json` posicionalmente → não mexa no formato das linhas, só **acrescente** o `resumo`.

## NÃO é
Não é reancorar tela, não é julgar âncora podre (isso é a 03), não é frescor.

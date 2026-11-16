---
id: clientes-wr2-sistemas-smoke
cliente: wr2-sistemas
business_id: 1
lifecycle: ativo
---

# WR2 Sistemas (Eliana) — smoke de produção

> [W] 2026-11-15: **biz=1 é a conta real da WR2 em produção**, cliente pagante. Até essa data o
> canon (`memory/reference/clientes-ativos.md`, retrato de 2026-04-24) registrava biz=1 como
> demo/teste do Wagner. Vale a declaração de 2026-11-15.
>
> Consequência que vai além deste smoke: biz=1 também aparece no canon como tenant de **smoke
> manual em produção** (MWART F4, RUNBOOK de smoke SEFAZ). Em conta de cliente pagante, smoke
> que **grava** não é mais aceitável sem decisão do [W]. Este smoke só lê.

## O que roda e onde

Dois mecanismos, ambos só leitura. Nenhum grava marcação, intercorrência ou aprovação.

### 1. Telas do Ponto em todo deploy — job `smoke-clientes`

- **Onde:** `.github/workflows/screen-smoke-after-merge.yml`, job `smoke-clientes`, depois de todo
  deploy bem-sucedido (`workflow_run` do "Deploy to Hostinger").
- **Rotas:** as marcadas com `"clientes": ["wr2"]` em `scripts/screen-smoke/routes.json`. Para
  ver a lista atual: `node -e "const c=require('./scripts/screen-smoke/routes.json');console.log(c.routes.filter(r=>(r.clientes||[]).includes('wr2')).map(r=>r.path).join('\n'))"`.
  Em 2026-11-15: painel, espelho, aprovações, intercorrências, banco de horas, importação AFD,
  relatórios e a tela de marcação (`/ponto/mobile`).
- **Como mede:** Playwright abre cada tela em 1440 e 1280, coleta erros de console e 4 sinais de
  render, e a OpenAI vision julga se a tela renderizou. Conta usada: a fictícia biz=99
  (`SMOKE_PROD_USER`/`SMOKE_PROD_PASS`), **nunca uma sessão do biz=1**. Ou seja, o smoke prova que
  as telas do Ponto abrem com o código no ar, não olha o dado da Eliana.
- **Só leitura garantida por máquina:** depois do login, toda requisição que não seja GET/HEAD é
  abortada, e a tela vira QUEBRADO. Bater ponto é `POST /ponto/mobile/marcar`, disparado por
  clique; o smoke nunca clica e a trava barraria.

### 2. Sinal diário — `sells:smoke-daily` (06:30, só produção)

- `checkClientesVivos` conta as marcações do biz=1 nos últimos 7 dias e **só informa**.
- Reprova apenas se o Ponto estiver habilitado e a tabela `ponto_marcacoes` sumir.
- **Por que não alarma por falta de marcação:** medido em 2026-11-15, o biz=1 tinha **zero**
  marcações nos 365 dias anteriores (a produção inteira tinha 2, de outro business), e o Ponto
  tinha só 2 importações nesse business. Um alarme de "sem marcação" nasceria vermelho para
  sempre. Quando a WR2 começar a marcar ponto aqui, medir a cadência (maior lacuna sem marcação)
  e armar o alarme como o da ROTA LIVRE.
- Config: `pontowr2.smoke.wr2_business_id` (`SMOKE_WR2_BIZ`, padrão 1).

## O que é alerta

| Sinal | Onde aparece | Significado |
|---|---|---|
| `ALERT smoke-clientes cliente=wr2 … <rota>=QUEBRADO` | log do job, job vermelho | tela do Ponto não renderizou depois do deploy. **Regressão real para cliente pagante** |
| `… <rota>=NAO-EXERCIDA` | idem | o smoke não conseguiu abrir a tela (login falhou). Não é regressão do Ponto, é falta de medição; o job fica vermelho de propósito para não virar verde vazio |
| `ALERT cliente=wr2: Ponto habilitado e tabela ponto_marcacoes ausente` | `storage/logs`, comando FAILURE | o schema do Ponto sumiu em produção |

Sem canal externo (Slack/PagerDuty) por decisão do [W] em 2026-11-15, até a sessão de
monitoramento propor um.

## Se acusar regressão

1. **Não tentar corrigir.** Abrir o artifact `screen-smoke-clientes-<run>` (screenshots + resumo).
2. Achar o deploy: o run do smoke vem do `workflow_run` do deploy; o SHA está no checkout do job.
3. Comentar no PR mergeado daquele deploy com o link da run e propor rollback ao [W].

## Rodar à mão

```bash
gh workflow run screen-smoke-after-merge.yml -f rota=__CLIENTES__
```

Depende das credenciais da conta biz=99 estarem válidas (ver "Estado em 2026-11-15").

## Estado em 2026-11-15

- O login da conta biz=99 **falhava** em produção (run 43777165357): nenhuma tela foi aberta e o
  job saiu vermelho com `NAO-EXERCIDA`, como deve. Conserto do [W]:
  `php artisan screen-smoke:provision-tenant` (dry-run, depois `--force`) e
  `gh secret set SMOKE_PROD_USER` / `SMOKE_PROD_PASS`.
- **Não medido:** se a conta biz=99 tem acesso ao módulo Ponto. O provisionador só habilita
  `financeiro_module`. Se não tiver, as rotas da WR2 vão dar QUEBRADO por permissão, e o conserto
  é habilitar o Ponto no pacote dela.
- Advisory. Promoção a bloqueio só depois de 2 semanas sem falso positivo (ADR 0336). Como roda
  depois do deploy, não pode barrar o merge nem o deploy que já subiu; o desenho de bloqueio é
  decisão do [W].

## Origem

Sessão guardião dos clientes vivos, 2026-11-15 — PRs #10925 (job), #10926 (sinal diário),
#10927 (label `affects/wr2`).

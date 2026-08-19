---
id: modules-officeimpresso-pages-logs-index-casos
casos: Máquinas Cadastradas · /officeimpresso/licenca_log
irmaos: Index.charter.md (lei) · Index.tsx (código)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — quem-pode, o que a linha afirma e o que o filtro devolve não mudam quando a tela ganhar coluna nova.
owner: wagner
last_run: "2026-08-19"
---

# Casos de Uso & Aceite — Máquinas Cadastradas (`/officeimpresso/licenca_log`)

> Contrato derivado do **`LicencaLogController`**, do Blade legado e do
> [logs-parity.md](../../../../../../../memory/requisitos/Officeimpresso/logs-parity.md) —
> **não do `Index.tsx`** (caso derivado do código é tautológico, §5 2026-06-05).
> Ancorado em **US-OI-004** ([SPEC](../../../../../../../memory/requisitos/Officeimpresso/SPEC.md)).
>
> ⚠️ **Rota cross-empresa intencional** ([ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
> §exceções): a WR2 é a fornecedora do desktop e o suporte precisa ver a máquina do cliente.
> UC-LOGS-01 e UC-LOGS-10 existem justamente pra travar essa exceção contra "consertos"
> bem-intencionados.
>
> ⚖️ **Onde rodam:** lane `officeimpresso-pest` (MySQL real, allowlist explícita). Tenant
> canônico **98** ([ADR 0358](../../../../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md))
> — **nunca biz=4**.
>
> **Status:** ✅ passa e com prova no manifesto · 🧪 escrito e na allowlist, **veredito ainda não
> emitido** · ⬜ não verificado · ❌ o teste prova comportamento indesejado (achado, não conserto
> silencioso).
>
> **Recibo honesto (2026-08-19):** todos os UC abaixo estão **🧪**. O arquivo de teste entrou na
> allowlist da lane no mesmo PR que o criou, mas **a lane ainda não rodou** — Pest local é
> proibido e o CT 100 está com checkout de outra sessão. *"Está na allowlist"* não é *"passou"*;
> o ✅ só vem quando o CI rodar. Ler **assertions**, não "0 failed": skip também sai exit 0 (LC-13).

## Rastreabilidade

| UC | Caso de uso | Prio | Teste | Status |
|----|-------------|------|-------|--------|
| UC-LOGS-01 | Autenticado sem permissão do módulo é barrado | must `[sec]` | `LicencasAcessoPermissionTest` | 🧪 |
| UC-LOGS-02 | Os 4 KPIs chegam à tela como inteiros | should | `LogsBaselineTest` | 🧪 |
| UC-LOGS-03 | Filtro por HD devolve aquela máquina e exclui as outras | must | `LogsBaselineTest` | 🧪 |
| UC-LOGS-04 | Filtro por equipamento devolve só aquele | must | `LogsBaselineTest` | 🧪 |
| UC-LOGS-05 | Busca livre acha por hostname e exclui quem não casa | must | `LogsBaselineTest` | 🧪 |
| UC-LOGS-06 | Estado atual separa bloqueada de ativa — nos dois sentidos | must | `LogsBaselineTest` | 🧪 |
| UC-LOGS-07 | `business_id` não-numérico devolve vazio, não erro | must `[bug]` | `LogsBaselineTest` | 🧪 |
| UC-LOGS-08 | Com a flag OFF a tela continua servindo Blade | must | `LogsBaselineTest` | 🧪 |
| UC-LOGS-09 | Com a flag ON a tela responde Inertia com filtros e permissões | must | `LogsBaselineTest` | 🧪 |
| UC-LOGS-10 | A flag ON não afrouxa a guarda de acesso | must `[sec]` | `LogsBaselineTest` | 🧪 |

---

## UC-LOGS-01 · Autenticado sem permissão é barrado · `must [sec]`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda nao rodou.

**Dado** um usuário logado, de qualquer empresa, **sem** `superadmin` nem `officeimpresso.access`
**Quando** ele abre `/officeimpresso/licenca_log` pela URL direta
**Então** recebe **403** — esconder o link do menu nunca foi autorização.

## UC-LOGS-02 · Os 4 KPIs chegam como inteiros · `should`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda nao rodou.

**Dado** um usuário com `officeimpresso.access`
**Quando** a tela carrega
**Então** o payload traz `total_maquinas`, `maquinas_bloqueadas`, `empresas_bloqueadas` e
`chamadas_24h`, todos numéricos.
**E** os valores são **globais** — não seguem o filtro aplicado (era assim no Blade; migração não
muda semântica de indicador).

## UC-LOGS-03 · Filtro por HD · `must`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda nao rodou.

**Dado** duas máquinas cadastradas com HDs diferentes
**Quando** o suporte filtra por um dos HDs
**Então** a lista traz aquela máquina **e não traz** a outra.
**Por quê:** o HD é como o suporte descobre que o mesmo computador está cadastrado em mais de uma
empresa. Filtro que traz demais é tão inútil quanto o que traz de menos.

## UC-LOGS-04 · Filtro por equipamento · `must`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda nao rodou.

**Dado** duas máquinas da mesma empresa
**Quando** o suporte clica no nome de uma delas (que filtra por `licenca_id`)
**Então** só aquele equipamento permanece na lista.

## UC-LOGS-05 · Busca livre por hostname · `must`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda nao rodou.

**Dado** duas máquinas com hostnames distintos
**Quando** o suporte digita o hostname de uma na busca
**Então** ela aparece e a outra não.
**Nota:** a busca varre nome/CNPJ/razão social da empresa **e** hd/user_win/hostname/ip da máquina.
Este caso trava o ramo da máquina; o ramo da empresa está em `[BACKLOG]`.

## UC-LOGS-06 · Estado atual, nos dois sentidos · `must`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda nao rodou.

**Dado** uma máquina bloqueada e uma ativa
**Quando** o filtro é `bloqueada`
**Então** vem a bloqueada e **não** vem a ativa.
**E quando** o filtro é `ativa`, o inverso.
**Por quê o caso oposto é obrigatório:** sem ele o teste passaria com um `WHERE` que devolve tudo.

## UC-LOGS-07 · `business_id` não-numérico não derruba a tela · `must [bug]`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda nao rodou.

**Dado** a URL `/officeimpresso/licenca_log?business_id=abc`
**Quando** a tela carrega
**Então** responde **200** com lista vazia — **não** 500.
**Por quê:** o valor vem da query string (string). Tipar o parâmetro da consulta como `?int`
transforma isto em `TypeError`. Migração não pode trocar *"vazio"* por *"quebrou"*.

## UC-LOGS-08 · Flag OFF mantém o Blade · `must`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda nao rodou.

**Dado** a feature flag `useV2OfficeimpressoLogs` desligada (o default, porque o
`fallbackDefaults` não a lista)
**Quando** a tela é aberta
**Então** o servidor devolve a **view Blade**, não Inertia.
**Por quê:** é o que garante que mergear a migração não muda nada pro usuário até [W] ligar.

## UC-LOGS-09 · Flag ON responde Inertia · `must`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda nao rodou.

**Dado** a flag ligada
**Quando** a tela é aberta com `?q=<termo>`
**Então** a resposta é Inertia com componente `Officeimpresso/Logs/Index`, trazendo `filters`
(com o `q` ecoado) e `permissions`.
**E** `maquinas`/`kpis` **não** vêm no payload inicial — são `Inertia::defer`. Cobrá-los aqui
seria testar o contrário do que o defer faz.

## UC-LOGS-10 · Flag ON não afrouxa a guarda · `must [sec]`

**Status:** 🧪 — teste escrito e na allowlist da lane; a lane ainda nao rodou.

**Dado** um autenticado sem permissão **e** a flag ligada
**Quando** ele abre a lista ou a timeline
**Então** recebe **403** nas duas.
**Por quê:** o caminho novo não pode virar porta dos fundos. A guarda roda antes do render.

---

## `[BACKLOG]` — comportamento real, ainda sem teste que o cite

Sem id de propósito: UC sem teste que o cite é órfão e reprova o G-2
([ADR 0264](../../../../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md)).
Viram UC quando ganharem teste — os 4 primeiros são itens de severidade **alta** do
`logs-parity.md` e fechá-los é a **US-OI-006** (F4).

- `[BACKLOG]` A coluna Último Login mostra `(cadastro)` quando a data vem de `dt_ultimo_acesso` em vez do log — sem o rótulo, a tela afirma um acesso que nunca foi registrado. *(parity 24, alta)*
- `[BACKLOG]` Estado no Último Login é tri-estado: sem log nenhum mostra travessão, não "Liberada". *(parity 25, alta)*
- `[BACKLOG]` Estado Atual respeita a precedência empresa > máquina > ativa. *(parity 26, alta)*
- `[BACKLOG]` As três variantes da coluna Ações são mutuamente exclusivas, e "Desbloquear empresa" libera o cliente inteiro — visualmente distinguível de "Desbloquear máquina" sem ler tooltip. *(parity 27/32-34, alta)*
- `[BACKLOG]` Os filtros compõem: remover um chip preserva os outros. *(parity 17, alta)*
- `[BACKLOG]` Quem não tem `officeimpresso.access` vê só o próprio `business_id`. *(parity 53, alta — Tier 0)*
- `[BACKLOG]` A busca livre também acha por nome/CNPJ/razão social da empresa.
- `[BACKLOG]` Ordenação default por último login desc, com quem nunca acessou no fim.
- `[BACKLOG]` Paginação de 25 por página.
- `[BACKLOG]` Os dois estados vazios trazem o texto certo, e o "com filtro" oferece Limpar.
- `[BACKLOG]` A 1280px com a sidebar aberta a página não tem scroll horizontal.

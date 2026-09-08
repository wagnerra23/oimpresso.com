---
date: "2026-09-08"
time: "11:53 BRT"
slug: "patrimonio-orfao-created-by-verificado"
tldr: "O órfão que a thread 03 deixou sem dono (2 testes Tier 0 do Patrimônio com 0 assertions por created_by ausente) já tinha sido fechado pelo #7020 antes desta sessão começar. Nenhum PR de código foi aberto — duplicar seria LC-19. O que esta sessão entrega é o recibo re-medido no CT 100: 8 failed (3 assertions) → 8 passed (53 assertions), e a correção de três números herdados que já não descreviam o main."
decided_by: [W]
prs: [7020]
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0358-doutrina-de-teste-tenant-98-supersede-0101", "0344-two-strikes-cobre-processo"]
next_steps:
  - "[W] combinar um `git pull` no container `oimpresso-staging` do CT 100 — HEAD está em 755f6de79 (08:52 -03), ~2h atrás do main; enquanto durar, medição lá mistura versões"
  - "Corrigir o comando do playbook: `--filter=\"CrossTenant|MultiTenantIsolation\"` casa por nome de classe e arrasta NFSe e outros módulos — o filtro correto é por path. Fora do prefixo desta thread"
  - "Dar dono ao `Pest Repair` vermelho no main desde 2026-09-06 (herdado da thread 03, segue sem dono) — confirmado NÃO-required, não bloqueia merge"
  - "Investigar as 26 falhas de outros módulos no staging (ex. NfseCertificadoMultiTenantIsolationTest, `Unknown column 'nome_titular'`) — cheira a schema do staging defasado, não a regressão"
---

# Handoff 2026-09-08 11:53 BRT — Patrimônio: o órfão do `created_by` já estava fechado

## Estado MCP no momento do fechamento

⚠️ **As tools `mcp__oimpresso__*` NÃO estavam conectadas nesta sessão** — `ToolSearch` por
`cycles-active`/`my-work`/`tasks` não devolveu nenhuma delas. O brief chegou pelo hook curl do
`SessionStart`, não por tool. Logo o snapshot abaixo é o **fallback filesystem**
([`how-trabalhar.md` §Fallback](../how-trabalhar.md)), e não um recibo de MCP:

- Brief #619 (hook, gerado há ~2h): cycle `—`, HITL pending [W] = 5, ADRs nas últimas 24h = 0.
- PRs do playbook Patrimônio abertos ao fechar: **nenhum**. Os oito (#7008, #7009, #7011,
  #7016, #7017, #7018, #7019, #7020) mergearam.
- Handoffs irmãos de hoje conferidos por `git ls-tree` (anti-duplicação): `0924-onda7-lote-crm`,
  `1043-patrimonio-thread-03`, `1145-devolutiva-rodada-pontual`, `1200-onda7-financeiro`.

## O que aconteceu

A tarefa era o órfão que a [thread 03](2026-09-08-1043-patrimonio-thread-03-guarda-asset-view.md)
listou em `next_steps` — *"dar dono ao `created_by` ausente"*. A checagem de `gh pr list` que o
próprio pedido mandava fazer antes de abrir pegou o caso: o **#7020** já existia, aberto às
13:51 UTC, tocando exatamente e somente os dois arquivos do prefixo. Mergeou às 14:09
(`b915c90ce7`) durante a sessão.

**Nenhum PR de código foi aberto.** Ler o dono e construir ao lado é LC-19, e o §5 de 2026-08-03
é explícito em que isso é pior que não ter procurado. O que restou foi verificar e re-medir.

## Artefatos gerados

Só este handoff. Nenhum arquivo de código — por decisão, não por bloqueio.

## O recibo, re-medido no CT 100 (mesmo ambiente, por path, ANTES e DEPOIS)

| | Tests | assertions |
|---|---|---:|
| ANTES (`b915c90^`, main pré-merge) | `8 failed` | **3** |
| DEPOIS (main: teste + `AssetAllocationService`) | `8 passed` | **53** |

O ANTES morria em `SQLSTATE[23000] ... assets_created_by_foreign`, com o INSERT sem a coluna —
o defeito exatamente como descrito. No main de hoje são **17 de 17** creates com `created_by`
(10 `Asset` + 5 `AssetMaintenance` + 2 `AssetTransaction`), verificado **bloco a bloco**: a
contagem agregada dava 11 para 9 creates e teria escondido um create solto.

## Três números herdados que já não descreviam o main

Nenhum estava errado quando foi escrito. Todos estavam errados quando foram herdados.

1. **`0 assertions` era 3** — o caso `UC-ASSET-TENANT-01` do #7011 já passava `created_by`.
2. **`7 failed` era 8** — o #7020 mediu contra um main de antes do #7011, três minutos de história.
3. **`26 assertions` era 53** — aquele número é do branch com 3 cenários; o main mergeado tem 4.

## Lições catalogadas

- **O comando do playbook mede o universo errado.** `--filter="CrossTenant|MultiTenantIsolation"`
  casa por nome de classe: devolveu `26 failed / 400 passed / 912 assertions`, com falhas de NFSe
  misturadas. Quem usar isso lê falha alheia como sua, ou dá por provado verde de outro módulo.
- **Alarme falso evitado, e o padrão vale para a próxima thread.** Numa medição intermediária o
  `UC-ASSET-TENANT-01` falhou (`Failed asserting that 6 is identical to 10`) e a leitura óbvia era
  *vazamento Tier 0 vivo no main*. Era artefato: eu havia trazido os dois arquivos de **teste** do
  main para o CT 100, mas o `AssetAllocationService` de lá era de 2h antes do fix do #7011 — teste
  novo contra código velho, que é o §5 de 2026-08-25 (o buraco muda de perna). Ao trazer o service,
  passou.
- **Erro meu, pego antes de virar afirmação:** pipei um run para `tail -30` e quase concluí "os
  testes não executam" a partir do meu próprio truncamento — o arquivo tinha 2095 bytes. Conferir
  o tamanho antes de afirmar foi o que pegou. Não chegou a prod nem a artefato publicado, então
  conserta e não codifica ([ADR 0344](../decisions/0344-two-strikes-cobre-processo.md)).
- **Afirmação de bloqueio em canon caduca.** `proibicoes.md` diz que o checkout do CT 100 está em
  2026-07-23; está em `main` de hoje. Re-medir antes de herdar (§5 2026-09-01) foi o que destravou
  a sessão.

## O que ficou mexido no CT 100 (declarado, não escondido)

Cheguei e encontrei os dois testes **sujos** no container com o conteúdo pré-merge do #7020 —
md5 idêntico ao branch `eeebbfc`, byte a byte. Quem rodasse a lane mediria 3 cenários achando que
media 4. Substituí pelo main **depois** de provar que o conteúdo descartado já estava mergeado.
Estado deixado, com backup em `/tmp/bkp-asset/`:

```
HEAD=755f6de79  (08:52 -03 — o container está ~2h atrás do main)
M  Modules/AssetManagement/Services/AssetAllocationService.php
M  Modules/AssetManagement/Tests/Feature/{CrossTenantAssetTest,MultiTenantIsolationTest}.php
```

Os três são conteúdo de `origin/main`, mais novos que o HEAD do container.

## Próximos passos pra retomar

```
gh pr view 7020 --json state,mergeCommit && \
tailscale ssh root@ct100-mcp 'docker exec oimpresso-staging sh -c "cd /var/www/html && git status --porcelain -- Modules/AssetManagement/"'
```

## Pointers detalhados

- Thread 03 (origem do órfão): [`2026-09-08-1043-patrimonio-thread-03-guarda-asset-view.md`](2026-09-08-1043-patrimonio-thread-03-guarda-asset-view.md)
- Playbook: `prototipo-ui/design-docs/cowork-inbox/patrimonio/playbook/`
- Tenant de teste: [ADR 0358](../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md) — fictício 98, `biz=4` proibido sem exceção

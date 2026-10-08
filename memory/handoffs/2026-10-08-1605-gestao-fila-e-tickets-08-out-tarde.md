---
date: "2026-10-08"
time: "16:05 BRT"
slug: gestao-fila-e-tickets-08-out-tarde
tldr: "Gestão da fila e dos tickets na tarde de 08/10. Mergeados #9059, #9060, #9051 e #9066 (este, meu: o Ponto lê o feriado do HRM, issue #8200). 6 issues antigas fechadas com recibo. O #8201 (licença bloqueia marcação) ficou parado por conflito de decisão D3 entre a ADR 0014 e o código/charter, perguntado ao [W] no próprio ticket."
prs: [9059, 9060, 9051, 9066]
---

# Handoff — Gestão da fila e dos tickets (tarde 08/10)

Sucede o handoff `2026-10-08-0340`. Pedido do [W]: *"pode continuar o que está em aberto e assumir o gerente na nuvem e os tickets em aberto"*. Mesmo mandato da gestão da fila: decido merges, com exceção de valor fora do molde, migration destrutiva, cutover e baseline/foto (ADR 0409).

## O que entrou

- **#9059** (Maiara, Fabricação): editor de ingredientes em React, etapa 1, só leitura, atrás de `?tela=nova`.
- **#9060** (Forja thread 15): Equipe confere o negócio em token/DXT/cota; a rota legacy de revogar sai. Eu resolvi o conflito na allowlist da `forja-pest.yml` (mantidas as duas entradas) com merge do `main`, sem reescrever o branch. A lane da Forja tinha falhado antes por `\n` literal na allowlist (`Test file "n" not found`), então o `TeamTenantTest` nunca tinha rodado no CI. Rodou no push do merge para o `main`: 143 testes, 678 assertions, verde.
- **#9051** (Maiara, Fabricação): o servidor calcula o custo da receita e recusa receita inválida. É PR de valor, mas dentro do molde: dupla prova por caso no teste, quadro antes→depois, e nenhum registro existente muda com o deploy. Conflito só em arquivo gerado (SUPERFICIE/_STATUS), regerados. A combinação com o #9059 rodou no push para o `main`: Manufacturing 190 testes, 697 assertions, verde.
- **#9066** (meu, issue #8200, D5 da ADR 0014): a apuração do Ponto lê `essentials_holidays`. No feriado não há falta, e o trabalhado vai para a coluna nova `he_feriado_minutos` (dobro), fora da HE de 50% e do banco de horas. `FeriadoApuracaoContratoTest` UC-FER-01..08: 8 de 8, 22 assertions. Migration aditiva.
- O #9065 foi mergeado por outra sessão.

## Issues

- **Fechadas com recibo:** #34, #58 e #65 (site em 403 em abril; o deploy de hoje mede `/login` = 200), #22 e #42 (EvolutionAgent sobre Vizra, rejeitado pela ADR 0048) e #33 (premissa caducada; ressalva: sem smoke autenticado, e o `ContaBancariaIndexTest` não está em lane).
- **#8201 parado por decisão [W].** A D3 tem duas versões no canon. O charter de Licenças (05/09) e o código (#6891, 06/09) dizem "não bloqueia, só sinaliza". A ADR 0014 (24/09 e 29/09) diz "bloqueia". A seção citada como fonte da emenda não está no espelho. As 3 saídas estão no ticket: bloqueia, só sinaliza, ou por origem.
- **Não tocadas, dependem de outros:** #2948 (drift do MCP, CT 100), #4567 (sentinela Tier 0), #4555 (pergunta externa), #2232 (decisão [W], opção (a) marcada), #6060 e #4095 (lado do design).

## Pendências

1. **[W] no #8201:** responder 1, 2 ou 3.
2. **Advisory vermelhos no `main` (de outras threads):** reuse `ImpostosIndex` duplicado (Configurações × Relatórios; o conserto é renomear um, dono Sistema 07), anti-ghost `_playbooks` cita `Modules/Comissao`, e `check-scope --declared` com 1 FANTASMA.
3. **Smoke em produção** do #9051 (salvar receita biz=1) e do #9060 (o `DELETE /team-mcp/team/token/1` agora devolve 404). Esta sessão não alcança `oimpresso.com` pelo proxy; fica com as sessões donas.
4. **O feriado ainda não aparece no Espelho/relatórios.** O dado é gravado; falta o PR de tela.
5. **Do handoff anterior, ainda abertos:** ticket no suporte do GitHub, seeder `NfeIcmsUfSeeder`, GroupTaxController (thread própria), thread 06 do Sistema 04.

## Lições

- **Sair do rascunho dispara de novo as lanes `ready_for_review`**, inclusive required. O merge logo depois falha com "N of 47 required status checks are in progress". Ao contar checks, em nome repetido vale o run não concluído.
- **Esta integração não tem `workflow_dispatch`** (403 pelo `gh` e pelo MCP). Lane advisory que não roda em `synchronize` só re-roda no push para o `main`, então a prova de PR vem depois do merge.
- **Larastan infere colunas pelo dump `database/schema/mysql-schema.sql`**, não pelas migrations dos módulos (que o neon exclui). Coluna nova em migration de módulo precisa de `@property` no Model.

## Estado MCP no momento do fechamento

MCP `oimpresso` indisponível nesta sessão (o proxy recusou o túnel: 403). Brief, `my-work` e `cycles-active` não consultados; o estado acima vem do GitHub.

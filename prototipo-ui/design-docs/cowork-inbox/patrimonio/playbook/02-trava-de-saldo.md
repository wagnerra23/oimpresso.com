---
sessao: "02"
titulo: Trava de saldo na alocação — validar o fluxo completo
dono: "[CL]"
base: c7bd83944f
prefixo: AssetAllocationService.php · AssetAllocationController.php · Wave27AssetManagementPolishTest.php
nao_toca: AssetMaintenanceService · resources/js/**
depende: thread 01 — veja depende_threads no índice
---
# 02 · Trava de saldo na alocação

## Correção do plano — 2026-09-08

O pedido continua sendo recusar alocação acima do saldo livre. A orientação anterior de
reutilizar diretamente quantidadeDisponivel() estava errada: o método recebe uma alocação
existente e retorna **alocado − devolvido**, não a quantidade livre. A fórmula de livre já
aparece em Asset::forDropdown: **quantidade do bem − alocado + devolvido**.
Bem com 3 unidades e nenhuma alocação: livre=3, retorno daquele método=0.
Bem com 10 unidades, 8 alocadas e 1 devolvida: livre=3, retorno daquele método=7.

O StoreAssetAllocationRequest estava órfão, conforme _saida-04.md §5 e PR #7016.
Não escrever validação ali sem ligar o Request ao caminho HTTP. Esta ficha substitui a
instrução anterior; o histórico e a causa permanecem no PR #7016.

## Leitura e escrita

Ler rota/middleware, AssetAllocationController::store/update, Service, cálculo do dropdown
e views de criação/edição que exibem o erro. Prefixo limita **escrita**, não impede conferir
chamadores e consumidores. O controller captura Exception e troca a mensagem por erro
genérico: testar somente uma exceção no Service não prova o erro recebido pelo usuário.

Escrever nos três arquivos do frontmatter. Se a correção exigir outro consumidor, atualizar
o prefixo no índice antes de despachar; não deixar o elo necessário órfão.

## Execução e aceite

1. Conferir base atual, PRs ativos, dependência 01 e invalidações nas saídas irmãs.
2. Reconciliar o contrato de saldo livre com os consumidores existentes, sem transformar
   o significado do helper de alocação silenciosamente. Verificar também o tenant no JOIN
   de alocação, o dono do bem e o destinatário, conforme resíduos da thread 01.
3. Provar criação com saldo 3: alocar 3 passa; 4 recusa; zero/negativo recusa. Na edição,
   considerar a própria alocação; provar devolução e concorrência (duas solicitações não
   podem consumir juntas mais do que existe). Validação e gravação precisam ser atômicas.
4. Provar pelo HTTP real a recusa, ausência de escrita, preservação dos campos e mensagem
   em PT-BR, incluindo o tratamento no controller. Testar o cálculo isolado também.
5. Rodar os testes no CT 100 com tenants fictícios canônicos. Produzir JUnit e resumo via
   scripts/tests/junit-summary.mjs; ligar o recibo à revisão efetivamente executada.
6. Antes de merge/deploy, apresentar duas confirmações independentes e impacto antes→depois
   de quantidades, conforme regra mestre de memory/proibicoes.md. Nenhum dado de produção
   foi alterado pela correção desta ficha.
7. Registrar saída e recibo conforme [contrato do placar](../../_scripts/README-placar.md).
   Falha em teste ou dado não medido permanece explícita, nunca vira “feito”.

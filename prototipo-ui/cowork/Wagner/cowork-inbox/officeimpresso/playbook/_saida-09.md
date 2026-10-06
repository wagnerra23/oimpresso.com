---
sessao: "_saida-09"
thread: "09 · Ligar a tela nova de licenças (flag useV2OfficeimpressoLicencas)"
dono: "[CL]"
data: 2026-10-06
tipo: entregue (a flag já estava ligada; este PR trava o cutover e a rota de fuga em teste)
base_lida: wagnerra23/oimpresso.com@main 73ba361e01
---
# _saida-09

## Decisão usada
D6 = *"ligar a flag useV2OfficeimpressoLicencas em produção, seguindo o RUNBOOK §F2 (rota de fuga
= Blade)"* ([W] 2026-10-05). A resposta autoriza ligar.

## O que a medição achou: a flag JÁ estava ligada desde 01/10
O índice diz *"hoje OFF"*. Em produção, não estava.

- **#8394** (`537fb7c607`, 2026-10-01, "liga Licenças") pôs `'useV2OfficeimpressoLicencas' => true`
  no `FeatureFlagService::$fallbackDefaults`. A fonte foi a 2ª rodada de decisões
  (`_DECISOES-W-2026-10-01b.md`, linha da flag: *"pode ligar"*).
- **GrowthBook do CT 100** (Mongo `growthbook.features`, lido 2026-10-06): só conhece
  `useV2SellsCreate`. Sem regra para esta flag, o `isOn()` cai no fallback, que é `true`.
- **Produção** (Hostinger, checkout em `73ba361e01`, mesmo SHA do `main`), via a mesma função que
  o controller chama, `app(FeatureFlagService::class)->isOn('useV2OfficeimpressoLicencas', ['business_id' => N])`:
  `1:true` · `4:true` · `164:true`. Leitura em tinker, sem escrita.

Então a tela React serve `/officeimpresso/licenca_computador` em produção há 5 dias, em todos os
negócios. Não houve nada a ligar. O que faltava era a prova que a ficha pede: a rota de fuga
testada, e o estado de produção travado em teste.

## O que saiu (1 arquivo de código)
`Modules/Officeimpresso/Tests/Feature/LicencasIndexContratoTest.php`, na lane `officeimpresso-pest`,
onde o arquivo já estava. Os UC-OILIC-02/03 de antes trocam o serviço por um dublê. Os dois novos
usam o **serviço real**, com o GrowthBook simulado por `Http::fake`:

| teste | prova | mutação que o derruba (medida no CT 100) |
|---|---|---|
| UC-OILIC-03 · cutover | GrowthBook sem a flag → tela React, e a requisição passou pelo GrowthBook falso (`Http::assertSent`) | fallback da flag = `false` → cai; o UC-02 segue verde |
| UC-OILIC-02 · rota de fuga | regra `force:false` para o negócio da sessão → volta o Blade; a mesma regra para o OUTRO negócio (98×99) → segue React | controller sem `business_id` nos atributos → cai; o UC-03 segue verde |

Cada mutação derruba só o teste dela.

Execução no CT 100, num worktree descartável no `main` (`73ba361e0`), porque o checkout do
container está em `e57b78bf5` (21/09) e não foi tocado: **19 passed (107 assertions)**. Tenant 98
(e 99 como controle), nunca biz=4.

⚠️ Na primeira rodada o teste de cutover passou pelo motivo errado. O `.env` do CT 100 tem
`GROWTHBOOK_API_HOST`, e o `env()` do Laravel lê `$_SERVER` antes de `getenv`, então só o `putenv`
não desviava a chamada, e o teste consultava o GrowthBook real. O helper agora escreve nas três
fontes e devolve o valor original no fim. O `assertSent` prova que a chamada foi ao falso.

## Como desligar (rota de fuga), do mais rápido ao mais lento
1. **GrowthBook** (sem deploy, vale em até 60 s, o TTL do cache): criar a feature
   `useV2OfficeimpressoLicencas` com `defaultValue: false`, ou uma regra `force:false` só para o
   `business_id` afetado. Regra explícita no GrowthBook vence o fallback. É o caso do UC-02.
2. **Código**: reverter a linha do `fallbackDefaults` (PR + deploy).

O Blade (`officeimpresso::licenca_computador.index`) segue no repo e é servido assim que a flag
desliga.

## Canário
- **Quem usa:** WR2 (superadmin) e o suporte com `officeimpresso.access` no negócio operador.
  Módulo interno, sem cliente externo na tela, e por isso sem janela de aviso (RUNBOOK §F5 item 11).
- **Observação:** já corre desde 01/10. Proposta: mais 7 dias a partir deste PR (até 13/10). Se
  ninguém reportar campo faltando ou erro, abrir a remoção do Blade + flag (F5 item 11, PR à parte).
- **Sinal de desligar:** 500 na rota, máquina sumindo da lista, ou bloquear/liberar falhando. Aí
  aplica-se o passo 1 acima.

## Pendente (não inventado)
1. **Remover o Blade e a flag** (F5): depois do canário, decisão [W].
2. **Gate visual** (RUNBOOK §F5 item 12): a tela ainda não está no `visreg-screens.json`. Com a
   flag ligada por default, o `assertInertia` do `PixelBaselineTest` já acharia a página. Faltam
   a entrada, o `.snap` e a aprovação visual do [W]. Fica para a thread do F5.
3. **Smoke visual em produção:** não fiz. A rota exige login de suporte, e não entro senha. A
   prova de produção é a leitura do `isOn()` acima, que é a função que decide a rota.

## Errata para o Cowork (não editei o índice)
- D6 pergunta *"hoje OFF: produção segue na Blade"*. Desde o #8394 (01/10) isso não é verdade.
- O charter `Licencas/Index.charter.md:73` ainda diz *"`useV2OfficeimpressoLicencas` nasce OFF"*,
  e `_saida-06` diz *"flag ... (OFF)"*. São afirmações em presente que envelheceram (LC-10). O
  charter está no `nao_toca` desta thread (`${MPAGES}/`), então não mexi.
- `RUNBOOK-licencas.md` §F2 item 5 descreve o default OFF como condição ("enquanto a chave não
  estiver no `fallbackDefaults`"). A chave entrou, e a frase segue correta, mas o leitor precisa
  saber que a condição já virou.
- Prova da ficha: `tipo: execucao`. Com o avaliador fora do repo, o placar não a mede. Esta é a
  evidência: leitura do `isOn()` em produção + os 2 testes acima.

## Placar
entregue 1 de 1 PR da thread · flag ligada em produção (medida) · rota de fuga testada (2 testes,
2 mutações mortas) · smoke visual ausente (sem login).

## PR
O PR que traz este arquivo (branch `claude/officeimpresso-thread-09-flag`). Merge do [W], sem
auto-merge (ADR 0427).

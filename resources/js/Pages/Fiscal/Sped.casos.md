---
id: resources-js-pages-fiscal-sped-casos
casos: SPED & Livros · /fiscal/sped
irmaos: Sped.charter.md (lei) · memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md (§6 CU)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-27"
last_run_ci: "0 UC executado nesta corrida — os 7 UC herdam testes que JÁ existem; veredito pendente da lane Pest Fiscal + suíte noturna CT 100"
related_us: [US-FISCAL-010, US-FISCAL-016, US-FISCAL-017, US-FISCAL-020]
---

# Casos de Uso & Aceite — SPED & Livros

> Persona: **Eliana [E] (contadora)** — gera o arquivo da competência para entregar no prazo.
>
> **Âncora:** `CU-FISC-15` `[V0]`, `CU-FISC-12` e `CU-FISC-13` do
> [SDD §6](../../../../memory/requisitos/Fiscal/SDD-cockpit-fiscal-v1.0.md). Os UC derivam do **CU**, nunca do `.tsx`.
>
> 🔴 **Esta tela é `[V0]` — REGRA MESTRE de valor.** Um CST, CFOP ou alíquota errados no arquivo viram
> **multa fiscal**, ×150 clientes. Qualquer alteração aqui exige dupla-confirmação por dois caminhos
> independentes + tabela antes→depois + aprovação [W] ([proibicoes](../../../../memory/proibicoes.md) §REGRA MESTRE).
>
> ⚠️ **Divergência aberta entre charter e código — decisão [W] (SDD §5.4.2).** O charter declara
> `❌ Gerador SPED real` e o anti-hook `🚫 NÃO emitir SPED real até implementação canônica`; o gerador
> **existe** e a rota de download também. O efeito do anti-hook sobrevive por outro mecanismo — a
> feature flag que devolve 503 (`UC-FSPED-05`). **Non-Goal é intenção e só [W] altera.**
>
> **Status:** ✅ provado por teste verde que cita o UC · 🧪 tem teste, **veredito pendente da lane** · ⬜ não verificado · ❌ quebrou.

## Força do veredito

| Teste | Lane | Bloqueia merge? |
|---|---|---|
| `SpedIcmsIpiGeneratorServiceTest` · `SpedMotorTributarioIntegrationTest` · `SimplesOnlyGateTest` · `SimplesOnlyGateConfigTest` · `SpedControllerTest` | `Pest Fiscal` (SQLite — os que tocam banco **pulam**) + suíte noturna CT 100 (MySQL) | ❌ **não** — `Pest Fiscal` não está no [baseline](../../../../governance/required-checks-baseline.json): reprova visível, **advisory** |

> 🔴 **Nenhum teste do gerador `[V0]` bloqueia merge hoje.** Para uma superfície de multa fiscal, isso
> é o achado de governança mais duro do módulo — está no SDD §9 (R7) e o ratchet-up é proposta ao [W] (§8.3).

## Rastreabilidade

| UC | O que defende | Prio | CU (SDD §6) | Teste que o cita | Status |
|---|---|---|---|---|---|
| UC-FSPED-01 | o arquivo tem a estrutura da norma | `[must]` `[V0]` | CU-FISC-15 | `SpedIcmsIpiGeneratorServiceTest` | 🧪 |
| UC-FSPED-02 | não gera com dado de outro business | `[must]` `[T0]` | CU-FISC-12 | `SpedIcmsIpiGeneratorServiceTest` · `SpedControllerTest` | 🧪 |
| UC-FSPED-03 | tributo real quando há regra | `[must]` `[V0]` | CU-FISC-15 | `SpedMotorTributarioIntegrationTest` | 🧪 |
| UC-FSPED-04 | CFOP interno ≠ interestadual | `[must]` `[V0]` | CU-FISC-15 | `SpedMotorTributarioIntegrationTest` | 🧪 |
| UC-FSPED-05 | o freio da flag e a ordem dos gates | `[must]` `[V0]` | CU-FISC-15 | `SimplesOnlyGateTest` · `SimplesOnlyGateConfigTest` | 🧪 |
| UC-FSPED-06 | competência impossível é recusada | `[must]` | CU-FISC-15 | `SpedIcmsIpiGeneratorServiceTest` | 🧪 |
| UC-FSPED-07 | apuração consolida e não inventa linha | `[must]` `[V0]` | CU-FISC-15 | `SpedIcmsIpiGeneratorServiceTest` | 🧪 |

---

## UC-FSPED-01 — O arquivo entregue tem a estrutura completa que a norma exige `[must]` `[V0]`

**Dado** uma competência com notas autorizadas
**Quando** a contadora baixa o arquivo
**Então** ele contém os 23 registros canônicos dos blocos de abertura, de documentos, de apuração, de inventário e de encerramento, no formato delimitado do layout oficial.

- **Regressão que defende:** o validador oficial recusa o arquivo inteiro por bloco ausente — e a contadora só descobre no dia 15.
- **Teste:** `Modules/Fiscal/Tests/Feature/SpedIcmsIpiGeneratorServiceTest.php` — `it('UC-FSPED-01 · contract: 23 registros canon EFD-ICMS/IPI presentes (PR #8 + #9 Waves)')` e `it('UC-FSPED-01 · gerar method público existe + signature canônica')`
- **Status:** 🧪 advisory + noturna.
- ⚠️ **Limite honesto:** isto prova **estrutura**, não conteúdo. Não existe arquivo de referência validado no programa oficial — ver backlog.

## UC-FSPED-02 — Gerar para outro business é recusado antes de qualquer consulta `[must]` `[T0]`

**Dado** uma sessão de um business
**Quando** alguém pede a geração para outro business
**Então** a operação é abortada **antes** de qualquer consulta ao banco.

- **Regressão que defende:** vazamento cross-tenant Tier 0 ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) na forma mais grave possível: exportar as notas de um cliente dentro do arquivo fiscal de outro.
- **Teste:** `SpedIcmsIpiGeneratorServiceTest` — `it('UC-FSPED-02 · gerar lança RuntimeException cross-tenant (session biz ≠ param)')` · `Modules/Fiscal/Tests/Feature/SpedControllerTest.php` — `it('UC-FSPED-02 · agregação de períodos NfeEmissao respeita scope per business')`
- **Status:** 🧪 advisory + noturna.

## UC-FSPED-03 — Com regra tributária configurada, o imposto do arquivo é o real `[must]` `[V0]`

**Dado** um business com regra tributária cadastrada
**Quando** o gerador resolve o imposto de um item
**Então** a situação tributária, o CFOP, a alíquota e o valor do imposto vêm do motor tributário — e o valor calculado bate com a base multiplicada pela alíquota.

- **Regressão que defende:** o gerador nasceu com valores fixos que funcionavam **por acidente** para um caso específico (vestuário no Simples). Qualquer outro regime saía errado.
- **Dupla confirmação `[V0]`:** o teste confere o valor por dois caminhos — o que o motor devolve e o recálculo aritmético a partir da base.
- **Teste:** `Modules/Fiscal/Tests/Feature/SpedMotorTributarioIntegrationTest.php` — `it('UC-FSPED-03 · resolverTributoItem com motor configurado retornando CST 00 + CFOP 6102 + aliq 18% (Lucro Presumido)')` e `it('UC-FSPED-03 · resolverTributoItem fallback quando motor lança NcmObrigatorioException')`
- **Status:** 🧪 advisory + noturna.

## UC-FSPED-04 — Venda para fora do estado nunca sai com CFOP de operação interna `[must]` `[V0]`

**Dado** uma venda cujo destino está em outra unidade da federação
**Quando** o gerador cai no caminho de contingência (sem regra cadastrada)
**Então** ele **ainda assim** distingue operação interna de interestadual no CFOP; e a chave de totalização não é mais um valor fixo.

- **Regressão que defende:** este é o **risco R1 do audit sênior** — vender de um estado para outro com CFOP de operação interna gera arquivo inválido e multa. Era um valor fixo no código até a integração do motor.
- **Teste:** `SpedMotorTributarioIntegrationTest` — `it('UC-FSPED-04 · fallback Simples Nacional retorna CFOP 5102 (interno) quando UF origem = UF destino')`, `it('UC-FSPED-04 · fallback Simples Nacional retorna CFOP 6102 (interestadual) quando UF origem ≠ UF destino (audit R1)')`, `it('UC-FSPED-04 · keyTotalizadorC190 não retorna mais hardcode "102" — chave composta CST|CFOP|ALIQ')`
- **Status:** 🧪 advisory + noturna.

## UC-FSPED-05 — O freio existe, o dono passa por cima, e a permissão vem antes de tudo `[must]` `[V0]`

**Dado** a trava de segurança ligada por padrão
**Quando** um usuário comum, mesmo com permissão de exportar, pede o arquivo
**Então** recebe uma recusa explicativa em vez do download; o superadministrador passa; com a trava desligada o usuário comum baixa; e quem **não tem a permissão** é barrado **antes** da trava — recebe negação de acesso, não a mensagem da trava.

- **Por que a ordem importa:** trocar a ordem dos dois gates vazaria a existência e o motivo da trava para quem nem devia chegar na rota.
- **Regressão que defende:** desligar a trava sem que a fase seguinte do motor tributário exista reabre o risco de multa (SDD §9 R2).
- **Teste:** `Modules/Fiscal/Tests/Feature/SimplesOnlyGateTest.php` — `it('UC-FSPED-05 · user comum com fiscal.sped.export é bloqueado por 503 quando flag true')`, `it('UC-FSPED-05 · superadmin bypassa flag e consegue download mesmo com flag true')`, `it('UC-FSPED-05 · flag false libera download pra user comum')`, `it('UC-FSPED-05 · user sem permissão fiscal.sped.export recebe 403 (gate de perm é anterior)')` · `Modules/Fiscal/Tests/Feature/SimplesOnlyGateConfigTest.php` — `it('UC-FSPED-05 · flag default = true em produção (segurança audit sênior R1)')`
- **Status:** 🧪 advisory + noturna.

## UC-FSPED-06 — Competência impossível é recusada `[must]`

**Dado** um pedido de geração
**Quando** o ano é anterior à existência do layout, está no futuro, ou o mês não é um mês
**Então** a geração é recusada.

- **Regressão que defende:** arquivo gerado para período inexistente entra na entrega e é rejeitado no validador oficial.
- **Teste:** `SpedIcmsIpiGeneratorServiceTest` — `it('UC-FSPED-06 · gerar rejeita ano < 2020 (anti-historical garbage)')`, `it('UC-FSPED-06 · gerar rejeita ano > ano atual (anti-future)')`, `it('UC-FSPED-06 · gerar rejeita mes fora 1-12')`
- **Status:** 🧪 advisory + noturna.

## UC-FSPED-07 — A apuração consolida os débitos e não inventa linha zerada `[must]` `[V0]`

**Dado** os totalizadores de imposto da competência
**Quando** o bloco de apuração é montado
**Então** o total de débitos é a soma dos totalizadores, e a linha de imposto a recolher **só existe** quando há valor a recolher.

- **Regressão que defende:** linha de recolhimento com valor zero é rejeitada pelo validador oficial; e um total de débito que não bate com a soma dos documentos é divergência que a fiscalização acha.
- **Teste:** `SpedIcmsIpiGeneratorServiceTest` — `it('UC-FSPED-07 · Bloco E: E110 apuração consolida débitos C190 vl_icms')` e `it('UC-FSPED-07 · Bloco E: E116 só emitido quando vl_icms_recolher > 0 (anti-zero-line)')`
- **Status:** 🧪 advisory + noturna.

---

## Backlog de casos (sem id — viram UC quando ganharem contrato + teste)

- **[BACKLOG · ⬜ sem teste · `[V0]`] O arquivo gerado é aceito pelo validador oficial** — Dado o arquivo de uma competência real · Quando é importado no programa validador oficial · Então entra sem erro estrutural. _**Não existe arquivo de referência** no repositório. Este é o buraco `[V0]` mais caro do módulo: hoje se prova a estrutura por contagem de registros, nunca por validação real._
- **[BACKLOG · ⬜ sem teste] O panorama de 5 competências traz contagem, valor e prazo** — Dado cinco meses · Quando a tela abre · Então lista mês a mês as notas autorizadas, o valor e o prazo. _Sem teste do payload._
- **[BACKLOG · ⬜ estrutural] O bloco de inventário traz dados reais** — hoje é esqueleto vazio por desenho (exige integração com estoque e declaração anual). O teste existente apenas **trava que continua esqueleto**, o que é honesto mas não é cobertura.
- **[BACKLOG · ⬜ ausente] Entradas, contribuições (PIS/COFINS) e saldo credor anterior real** — Non-Goals declarados; nenhum teste, nenhum código.
- **[BACKLOG · ⬜ decisão [W]] Baixar a trava de segurança** depende da fase seguinte do motor tributário (estratégia por regime). Enquanto ela não existir, desligar a flag reabre o risco de multa.

## Como rodar a suíte

1. **Advisory:** `Pest Fiscal` (matrix `modules-pest.yml`) roda `Modules/Fiscal/Tests` em SQLite — os testes que tocam banco **pulam**; os de contrato e validação rodam.
2. **Noturna CT 100:** `phpunit.xml` inclui `./Modules/Fiscal/Tests/Feature` e o `shards-plan.mjs` a enumera — é onde tudo corre contra MySQL real.
3. ⛔ **Nunca local** ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).

## Trilha do tempo

- 2026-07-03 · [CC] stub criado no Passo 3 do programa de ondas — **0 UC**.
- 2026-07-27 · [CC] `sdd-from-source` (Onda 1 / S2): **7 UC** derivados do §6 do SDD; **todos** herdam testes existentes — o débito desta tela era rastreabilidade, não ausência de teste. O gate de permissão da rota **já** estava coberto (`UC-FSPED-05`, quarto teste), por isso ela não entrou no `GatesPermissaoFiscalTest` novo. Declarado que **nenhum** teste `[V0]` desta tela bloqueia merge, e que a divergência charter × código é decisão [W].

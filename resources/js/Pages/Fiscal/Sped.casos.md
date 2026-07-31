---
id: resources-js-pages-fiscal-sped-casos
casos: SPED & Livros · /fiscal/sped
irmaos: Sped.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-30"
---

# Casos de Uso & Aceite — SPED & Livros

> Persona: **Eliana (contadora)** — gera/confere EFD-ICMS/IPI mensal. Cockpit Fiscal.
> Âncora de contrato: SPEC `US-FISCAL-010` (panorama) + `US-FISCAL-016`/`US-FISCAL-017` (gerador) +
> `US-FISCAL-020` (MotorTributario) + `Sped.charter.md`. Layout: Guia Prático EFD-ICMS/IPI v3.1.1
> perfil A (EFD instituída pelo **Ajuste SINIEF 02/2009**); CFOP pela tabela do **Convênio S/Nº de
> 15/12/1970** (5xxx = operação interna · 6xxx = interestadual); CSOSN 102 = "tributada pelo Simples
> Nacional sem permissão de crédito" (**Ajuste SINIEF 07/2005**).
>
> ⚠️ **Tela toca VALOR FISCAL** (regra-mestre cálculo · proibicoes.md): o gerador produz totais ICMS
> no TXT (Bloco C190/E110). Ver `d1_calculo` no scorecard.
>
> **Status:** ✅ passa (UC-id com veredito `pass` no manifesto) · 🧪 UC citado por teste, veredito
> ainda não capturado no manifesto · ⬜ não verificado · ❌ quebrou.

## Onde estes testes realmente rodam (recibo — não afirmação atemporal)

Medido em **2026-07-27**, `tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql
oimpresso-staging php artisan test --filter=..."`:

| Suíte | Resultado medido |
|---|---|
| `SpedIcmsIpiGeneratorServiceTest` + `SpedMotorTributarioIntegrationTest` | **21 passed** (97 asserções) |
| `SpedControllerTest` (caso que toca `nfe_emissoes`) | **skip** — tabela ausente no staging |
| `SimplesOnlyGateTest` (HTTP 403/503) | **skip** — mesma causa |

**Limite honesto:** a lane de CI (`modules-pest.yml`) roda **SQLite in-memory**, e o staging do CT 100
**não tem as migrations do NfeBrasil**. Logo os casos que precisam de `nfe_emissoes` **não executam em
nenhuma lane disponível hoje** — é lacuna de ambiente, não defeito de teste. Re-rode o comando acima em
vez de editar estes números.

## UC-FSPED-01 — A geração nunca cruza business (Tier 0)
Status: 🧪 (`SpedIcmsIpiGeneratorServiceTest::UC-FSPED-01 · gerar lança RuntimeException cross-tenant` — **passa**; `SpedControllerTest::UC-FSPED-01 · agregação de períodos` skipa sem `nfe_emissoes`)
Dado a sessão do business X · Quando alguém pede a geração do SPED do business Y (X≠Y) · Então
`RuntimeException: Cross-tenant attempt` **antes de qualquer query**, e a agregação do painel só conta
notas do business da sessão. Âncora: ADR 0093 (`business_id` global scope, Tier 0 IRREVOGÁVEL).
**Pronto quando:** biz=1 pedindo biz=99 lança; biz=1 pedindo biz=1 segue o fluxo normal.

## UC-FSPED-02 — Competência inválida é rejeitada antes de gerar
Status: 🧪 (`SpedIcmsIpiGeneratorServiceTest::UC-FSPED-02 · …ano < 2020` / `…ano > ano atual` / `…mes fora 1-12` — 3 casos, **passam**)
Dado ano anterior a 2020, ano futuro, ou mês fora de 1–12 · Quando a contadora pede o arquivo · Então
`InvalidArgumentException` ("Ano inválido" / "Mês inválido") — nunca um TXT de competência impossível.
**Pronto quando:** 2019, ano+1, e os meses 0/13/-1/99 falham; competência corrente passa.

## UC-FSPED-03 — O gerador é público e a rota de download existe
Status: 🧪 (`SpedIcmsIpiGeneratorServiceTest::UC-FSPED-03 · gerar method público existe + signature canônica` + `SpedControllerTest::UC-FSPED-03 · SpedController expõe gerar()…` — **passam**)
Dado o contrato do módulo · Quando outra camada chama o gerador · Então existe
`SpedIcmsIpiGeneratorService::gerar(int $businessId, int $ano, int $mes): string` e a rota nomeada
`fiscal.sped.icms-ipi` (`GET /fiscal/sped/icms-ipi/{ano}/{mes}`) está registrada.
**Pronto quando:** reflection confirma a assinatura e `Route::has('fiscal.sped.icms-ipi')` é verdadeiro.
_(Este UC substitui o guard revogado "Controller é placeholder — sem gerador SPED real ainda", que
ficava verde só porque assertava a ausência dos nomes `exportSped`/`gerarEFD` enquanto o gerador real
se chama `gerar`. Ver cabeçalho do `SpedControllerTest`.)_

## UC-FSPED-04 — CFOP segue a UF: 5102 interno, 6102 interestadual (Tier 0 · risco de multa)
Status: 🧪 (`SpedMotorTributarioIntegrationTest::UC-FSPED-04 · …CFOP 5102 (interno)…` + `…CFOP 6102 (interestadual)… (audit R1)` — **passam**)
Dado venda SC→SC (interna) e SC→RS (interestadual) · Quando o fallback resolve o tributo do item ·
Então CFOP **5102** no primeiro e **6102** no segundo, e o NCM real é preservado quando informado.
Âncora: US-FISCAL-020 + audit sênior 2026-05-25 R1 — o hardcode 5102 gerava SPED inválido em venda
interestadual (exposição a multa). Tabela CFOP: Convênio S/Nº de 1970.
**Pronto quando:** `fallbackSimplesNacional('SP','SP',null)` → 5102 e `('SC','RS','61091000')` → 6102.

## UC-FSPED-05 — Com MotorTributario configurado, o ICMS é o calculado (não o fallback)
Status: 🧪 (`SpedMotorTributarioIntegrationTest::UC-FSPED-05 · resolverTributoItem com motor configurado…` — **passa**)
Dado motor devolvendo Lucro Presumido (CST 00, CFOP 6102, alíquota 18%) e item de base 1.000,00 ·
Quando resolve o tributo · Então `vl_icms = 180,00`, CST/CFOP/NCM vêm do motor e **não** do fallback.
Âncora: US-FISCAL-020. ⚠️ Toca valor fiscal — mudança aqui exige a regra-mestre de cálculo.
**Pronto quando:** base × alíquota bate por dois caminhos (asserção do teste + recomputação à mão).

## UC-FSPED-06 — Sem regra no motor, cai no Simples Nacional sem quebrar
Status: 🧪 (`SpedMotorTributarioIntegrationTest::UC-FSPED-06 · …fallback quando motor lança NcmObrigatorioException` + `UC-FSPED-06 · instanciação sem motor (legado)…` — **passam**)
Dado motor que lança `NcmObrigatorioException`/`TributacaoNaoConfiguradaException` (caso biz=4 hoje), ou
serviço instanciado sem motor (legado) · Quando resolve o tributo · Então CSOSN **102**, alíquota 0 e
ICMS 0 — degradação graciosa, sem exceção vazando pro download. Âncora: US-FISCAL-020 + SINIEF 07/2005.
**Pronto quando:** o fallback devolve 102/5102 e a instanciação sem DI continua funcionando.

## UC-FSPED-07 — O totalizador C190 agrupa por CST|CFOP|alíquota, não por hardcode
Status: 🧪 (`SpedMotorTributarioIntegrationTest::UC-FSPED-07 · keyTotalizadorC190 não retorna mais hardcode "102"…` — **passa**)
Dado itens com CST/CFOP/alíquota distintos · Quando monta a chave do totalizador C190 · Então a chave é
**composta** (contém CST, CFOP e separador `|`) e nunca a constante "102" — senão operações diferentes
colapsariam numa linha só do Bloco C190. Âncora: US-FISCAL-020 GAP-3.
**Pronto quando:** `keyTotalizadorC190` de um item CST 00/CFOP 6102/18% difere da chave do Simples.

## UC-FSPED-08 — Os fallbacks vivem em constantes nomeadas, não espalhados
Status: 🧪 (`SpedMotorTributarioIntegrationTest::UC-FSPED-08 · refactor define constantes FALLBACK_* centralizadas` — **passa**)
Dado o serviço · Quando se procura o valor de fallback · Então ele está em constante única
(`FALLBACK_NCM_SEM_CADASTRO='00000000'`, `FALLBACK_CST_CSOSN_SIMPLES_SEM_CREDITO='102'`,
`FALLBACK_CFOP_VENDA_INTERNA_SIMPLES='5102'`, `FALLBACK_CFOP_VENDA_INTERESTADUAL_SIMPLES='6102'`,
`FALLBACK_ALIQ_ICMS_SIMPLES=0.0`). Âncora: US-FISCAL-020 GAP-3 (audit sênior).
**Pronto quando:** `getConstants()` traz as 5 chaves com exatamente esses valores.

## UC-FSPED-09 — O download é gated por permissão e pela trava do Simples
Status: 🧪 (`SimplesOnlyGateTest::UC-FSPED-09 · …403` / `…503 quando flag true` / `…superadmin bypassa` / `…flag false libera` — 4 casos; **skipam** hoje por falta de `nfe_emissoes`, ver §recibo)
Dado a rota de download · Quando o usuário **não** tem `fiscal.sped.export` · Então **403** (a permissão
é checada antes de tudo). Quando tem a permissão mas `fiscal.sped_simples_only_lock` está ligada · Então
**503** explicativo citando GAP-FISCAL-003, preservando a visualização do painel. Superadmin bypassa;
flag desligada libera. Âncora: charter (permissão `fiscal.sped.export`) + Onda ESTABILIZAR 2026-05-25.
**Pronto quando:** os 4 status batem numa lane com as migrations do NfeBrasil aplicadas.

## UC-FSPED-10 — A trava nasce ligada em produção e mora no config canônico
Status: 🧪 (`SimplesOnlyGateConfigTest::UC-FSPED-10 · flag default = true…` / `…vive em config/fiscal.php` / `…SpedController referencia a flag` — 3 casos, rodam sempre)
Dado nenhuma configuração explícita · Quando o app lê `fiscal.sped_simples_only_lock` · Então o default é
**true** (fail-secure: sem trava, um TXT com hardcode sairia pro Fisco), a chave vive em
`config/fiscal.php` e o `SpedController::gerar` de fato a consulta. Âncora: audit sênior R1.
**Pronto quando:** default true, chave no arquivo canônico, e o Controller referencia a flag.

## Backlog de casos (sem id — entram quando um teste de COMPORTAMENTO os cobrir)

> ⚠️ Os itens abaixo **têm teste**, mas o teste é **source-grep**: asserta que uma string existe no
> código-fonte (`expect($src)->toContain(...)`), não que o comportamento acontece. Um grep de
> `"if (\$vlTotalDebitos > 0)"` quebra se alguém renomear a variável e passa se a lógica estiver
> errada — por isso **não viram UC** (lápide §5 2026-06-05: teste que não prova comportamento é pior
> que ausente, porque parece cobertura). Viram UC quando um teste gerar o TXT e inspecionar as linhas.

- **[BACKLOG · source-grep] TXT traz os 23 registros canônicos dos Blocos 0+C+E+H+9** — `SpedIcmsIpiGeneratorServiceTest::contract: 23 registros canon` procura os NOMES DE MÉTODO (`registro0000`…) no fonte e conta um array literal escrito no próprio teste. Não gera arquivo nem valida linha `|REG|…|`.
- **[BACKLOG · source-grep] Bloco E: E110 consolida os débitos do C190 e E116 só sai com ICMS a recolher** — os dois casos assertam `toContain("array_sum(array_column(...))")` e `toContain("if ($vlTotalDebitos > 0)")` no fonte.
- **[BACKLOG · source-grep] Bloco H é esqueleto (IND_MOV=1)** — asserta `toContain('registroH001(1)')` no fonte; o inventário real exige integração Stock/ProductCatalogue (declaração de 31/12).
- **[BACKLOG · source-grep] Span OTel `fiscal.sped.gerar`** — grep da string no fonte.
- **[BACKLOG · ⬜ sem teste] Panorama das 5 competências** — mês, notas autorizadas, valor, status (aberto/pronto/entregue) e prazo; export desabilitado sem notas. `SpedController::index` agrega; nenhum teste asserta o payload das 5 competências. _(O prazo exibido usa dia 15 como heurística; o prazo legal da EFD é fixado por cada UF.)_
- **[BACKLOG · ⬜ sem teste] Smoke PVA-EFD homologação CONFAZ** — importar o TXT no validador oficial sem erro estrutural. Nenhum golden file hoje.
- **[BACKLOG · ⬜ sem teste] Entradas (DF-e manifestada), EFD-Contribuições (PIS/COFINS) e saldo credor anterior real no E110** — Non-Goals declarados; nenhum código nem teste.

## Como rodar a suíte
1. **Pest:** lane Fiscal no CT 100 (ADR 0062) — `tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter='Sped|SimplesOnly'"`. Ver §recibo pro que executa e o que skipa hoje.
2. **Cadência:** rodar ao fim de toda mexida. UC ❌ = regressão fiscal (multa).

## Trilha do tempo
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas (régua por tela). 22 testes mapeados, 0 citavam UC-id.
- 2026-07-27 · [CC] fecha a G-2: 10 UC declarados (`UC-FSPED-01..10`) e citados pelos testes existentes. Separado o que é **comportamento provado** (invocação real) do que é **source-grep** — os 5 source-grep ficam backlog explícito em vez de virar UC de fachada. Revogado o guard `Controller é placeholder` (verde por nome, ver `UC-FSPED-03`). Prefixo `UC-FSPED-` em vez do `UC-FISCAL-` planejado: as 6 telas Fiscal compartilhariam o mesmo id e o G-2 casa por substring — colisão viraria cobertura falsa cruzada.

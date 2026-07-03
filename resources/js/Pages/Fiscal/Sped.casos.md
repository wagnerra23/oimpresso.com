---
casos: SPED & Livros · /fiscal/sped
irmaos: Sped.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — SPED & Livros

> Persona: **Eliana (contadora)** — gera/confere EFD-ICMS/IPI mensal (entrega dia 15). Cockpit Fiscal.
> Passo 3 do template-onda-modulo (régua por tela) — complementa a CAPTERRA-FICHA Fiscal (nota 75).
> ⚠️ **Tela toca VALOR FISCAL** (regra-mestre cálculo · proibicoes.md): o gerador produz totais ICMS no TXT (Bloco C190/E110). Ver `d1_calculo` no scorecard.
>
> **Status:** ✅ passa (UC-id citado por teste) · 🧪 tem teste Feature mas **sem UC-id** (débito G-2 · ADR 0264) · ⬜ não verificado · ❌ quebrou.
>
> ⚠️ **Débito = rastreabilidade, não ausência de teste.** Gerador defendido por **22 testes reais** — `SpedIcmsIpiGeneratorServiceTest` (11 casos: contrato `gerar`, validações input, 23-registros-canon, Bloco E/H) + `SpedMotorTributarioIntegrationTest` (9 casos: motor vs fallback, CFOP interno/interestadual, hardcodes centralizados) + `SpedControllerTest` (2 casos: scope cross-tenant, guard placeholder). Falta G-2: **nenhum teste cita `UC-FISCAL-NN`**. CT100 (ADR 0062).

## Backlog de casos (sem id — entram quando um teste citar o UC-id)
- **[BACKLOG · 🧪 tem teste] Gera TXT EFD-ICMS/IPI layout CONFAZ v3.1.1 (perfil A) com 23 registros dos Blocos 0+C+E+H+9** — Dado business com NFes autorizadas no período · Quando contadora baixa o .txt da competência · Então o arquivo tem os 23 registros canônicos (`0000`/`0001`/`0005`/`0150`/`0190`/`0200`/`0990` · `C001`/`C100`/`C170`/`C190`/`C990` · `E001`/`E100`/`E110`/`E116`/`E990` · `H001`/`H990` · `9001`/`9900`/`9990`/`9999`), pipe-delimited `|REG|...|`. _Coberto por `SpedIcmsIpiGeneratorServiceTest::contract: 23 registros canon EFD-ICMS/IPI presentes` (+ `gerar signature canônica`)._
- **[BACKLOG · 🧪 tem teste · Tier 0] Geração NUNCA vaza NFe de outro business** — Dado session biz=X · Quando gera SPED de biz=Y (X≠Y) · Então lança `RuntimeException: Cross-tenant attempt` antes de qualquer query. _Coberto por `SpedIcmsIpiGeneratorServiceTest::gerar lança RuntimeException cross-tenant` + `SpedControllerTest::agregação de períodos NfeEmissao respeita scope per business`._
- **[BACKLOG · 🧪 tem teste] Download exige permissão `fiscal.sped.export` (ou superadmin)** — Dado usuário sem a permission · Quando acessa `/fiscal/sped` ou `/fiscal/sped/icms-ipi/{ano}/{mes}` · Então `abort(403)`. _Guard no `SpedController::index`/`gerar`; comportamento verificado estruturalmente por `SpedControllerTest::Controller é placeholder` (garante ausência de gerador solto). Débito: falta teste HTTP que exercite o 403._
- **[BACKLOG · 🧪 tem teste] Feature-flag `sped_simples_only_lock` bloqueia download (503) até MotorTributario integrado** — Dado flag `true` e usuário não-superadmin · Quando pede o .txt · Então recebe 503 com aviso dos hardcodes (NCM 00000000/CST 102/CFOP 5102), preservando visualização. _Lógica no `SpedController::gerar`; superadmin bypassa. Débito: falta teste HTTP do 503 nesta rota (coberto indiretamente por `SimplesOnlyGateTest`)._
- **[BACKLOG · 🧪 tem teste] Integração MotorTributarioService calcula ICMS real quando configurado** — Dado motor devolvendo Lucro Presumido (CST 00, CFOP 6102, alíq 18%) · Quando resolve tributo de item de base 1.000 · Então `vl_icms = 180,00` e CST/CFOP/NCM vêm do motor (não do fallback). _Coberto por `SpedMotorTributarioIntegrationTest::resolverTributoItem com motor configurado retornando CST 00 + CFOP 6102 + aliq 18%`._
- **[BACKLOG · 🧪 tem teste] Fallback Simples Nacional quando motor sem regra/config** — Dado motor lança `NcmObrigatorioException`/`TributacaoNaoConfiguradaException` (caso biz=4 ROTA LIVRE hoje) · Quando resolve tributo · Então cai pra CSOSN 102, alíq 0, vl_icms 0 (log INFO, não ERROR). _Coberto por `SpedMotorTributarioIntegrationTest::resolverTributoItem fallback quando motor lança NcmObrigatorioException` + `instanciação sem motor (legado) ainda funciona`._
- **[BACKLOG · 🧪 tem teste · Tier 0 risco fiscal] CFOP interno 5102 vs interestadual 6102 conforme UF origem×destino** — Dado venda SC→SC (interna) vs SC→RS (interestadual) · Quando gera fallback · Então CFOP 5102 no primeiro e 6102 no segundo (elimina hardcode 5102 que gerava SPED inválido + multa — R1 audit sênior 2026-05-25). _Coberto por `SpedMotorTributarioIntegrationTest::fallback ... CFOP 5102 (interno)` + `... CFOP 6102 (interestadual) (audit R1)` + `keyTotalizadorC190 não retorna mais hardcode "102"`._
- **[BACKLOG · 🧪 tem teste] Bloco E consolida débitos ICMS na apuração (E110) e só emite E116 quando há ICMS a recolher** — Dado totalizadores C190 com vl_icms · Quando monta Bloco E · Então E110 reflete `sum(vl_icms)` como VL_TOT_DEBITOS e E116 só aparece se `> 0` (anti-zero-line). _Coberto por `SpedIcmsIpiGeneratorServiceTest::Bloco E: E110 apuração consolida débitos C190` + `Bloco E: E116 só emitido quando ... > 0`._
- **[BACKLOG · 🧪 tem teste] Rejeita competência inválida (ano < 2020, ano futuro, mês fora 1–12)** — Dado ano/mês inválido · Quando gera · Então `InvalidArgumentException`. _Coberto por `SpedIcmsIpiGeneratorServiceTest::gerar rejeita ano < 2020` + `... > ano atual` + `... mes fora 1-12`._
- **[BACKLOG · ⬜ sem teste] Panorama dos 5 meses com contagem/valor de NFes autorizadas + status estimado** — Dado 5 competências · Quando abre `/fiscal/sped` · Então lista mês, notas autorizadas, valor, status (aberto/pronto/entregue) e prazo dia 15; export desabilitado sem notas. _Contagem agregada no `SpedController::index`; UI em `Sped.tsx`. Débito: sem teste que asserte o payload das 5 competências._
- **[BACKLOG · ⬜ sem teste] Bloco H (inventário anual) traz dados reais** — hoje é **esqueleto sempre vazio** (`H001` IND_MOV=1 + `H990`, exige integração Stock/ProductCatalogue, declaração 31/12). _`SpedIcmsIpiGeneratorServiceTest::Bloco H: esqueleto sempre IND_MOV=1` só trava que continua esqueleto._
- **[BACKLOG · ⬜ sem teste] Smoke PVA-EFD homologação CONFAZ** — validar o TXT gerado no validador oficial (importar sem erro estrutural). Pendente; nenhum teste valida bytes esperados do arquivo (golden ausente).
- **[BACKLOG · ⬜ sem teste] Entradas (NF-e contra CNPJ via DF-e manifestada), EFD-Contribuições (PIS/COFINS arquivo separado), saldo credor anterior real no E110** — Non-Goals declarados no charter/Service; nenhum teste.

## Como rodar a suíte
1. **Pest (MySQL real):** lane Fiscal no CT100 (ADR 0062) — 22 testes (`SpedControllerTest` 2 + `SpedIcmsIpiGeneratorServiceTest` 11 + `SpedMotorTributarioIntegrationTest` 9). Nota: os 2 do Controller pulam em SQLite; o Motor roda em SQLite (reflection/structural).
2. **Cadência:** rodar ao fim de toda mexida. UC ❌ = regressão fiscal (multa).

## Trilha do tempo
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas (régua por tela). Débito = UC-traceability; tela toca valor fiscal (d1_calculo aplica). 22 testes reais mapeados, 0 citam UC-FISCAL-NN.

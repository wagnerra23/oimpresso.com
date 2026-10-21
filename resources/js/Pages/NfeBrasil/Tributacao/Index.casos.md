---
id: resources-js-pages-nfe-brasil-tributacao-index-casos
casos: Tributação — regras NCM + templates + gate de emissão automática · /nfe-brasil/tributacao
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: esta tela liga a emissão automática de NFe e edita a cascade tributária — o comportamento é durável mesmo se o layout mudar.
owner: wagner
last_run: "2026-10-20"
---

> ℹ️ **`last_run` 2026-10-14 → 2026-10-20 (G-6), sem re-execução.** A única mudança no `Index.tsx` depois de
> 2026-10-14 é o [#9450](https://github.com/wagnerra23/oimpresso.com/pull/9450) (2026-10-15): a prop
> `produtoInicial` repassada ao simulador, que é o UC-NFTR-29 (escrito no mesmo PR). Conferido no diff do
> commit `f544a6ca3d` restrito ao `Index.tsx`; nenhum outro UC depende dela. O bump marca o trio
> reconciliado com a tela nesta data, não um re-run: os Status seguem vindo do manifesto G-7.

# Casos de Uso & Aceite — Tributação (regras NCM · templates · gate de emissão automática)

> **Âncora:** o módulo NfeBrasil **não tem SDD** (verificado em `origin/main` 2026-07-27 —
> `git ls-tree` não devolve nenhum `SDD-*` sob `memory/requisitos/NfeBrasil/`). A fonte de contrato,
> na ordem canônica de [`how-trabalhar.md`](../../../../../memory/how-trabalhar.md) §"Pedido de tela/feature", é:
>
> 1. [**ADR ARQ-0006**](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md) —
>    cascade em 4 níveis. Esta tela edita os **Níveis 2 e 3** (regra NCM exata × NCM com UF destino "todas")
>    e é a porta do **Nível 4**.
> 2. [**US-NFE-010**](../../../../../memory/requisitos/NfeBrasil/SPEC.md) — DoD "UI fase 2 (CRUD básico)" +
>    "Multi-tenant scope `business_id`" + [**US-NFE-TPL-001**] (templates L1).
> 3. [**Index.charter.md**](Index.charter.md) — **`status: live`, com Non-Goals e Anti-hooks
>    aprovados por [W] em 2026-05-10**. Diferente da tela irmã `ConfigDefault` (cujo charter é `draft`
>    e cujos Non-Goals nunca foram ratificados), aqui os itens ❌ **são lei** e podem virar `[must]`.
>
> Os UCs derivam do **contrato**, nunca da implementação — teste derivado do código é tautológico
> ([`proibicoes.md`](../../../../../memory/proibicoes.md) §5 2026-06-05). O `TributacaoController` e o
> `TributacaoTemplateService` foram lidos só para **confirmar**.
>
> **Por que este arquivo nasce agora:** fecha o trio da tela (charter desde 2026-05-10; `casos.md` +
> teste de contrato faltavam). Tier-0 quente pela sentinela
> [`exposicao-tier0.mjs`](../../../../../scripts/qa/exposicao-tier0.mjs) — `exposure_score 9`,
> categorias `dinheiro,pii,fiscal` — e em **débito**.
>
> **O que esta tela liga.** O switch "Emissão automática" é o **gate per-business** que faz vendas
> finalizadas emitirem NFC-e/NFe **sozinhas**. É o controle de maior consequência do módulo: ligado no
> tenant errado, ele emite documento fiscal em nome de quem não pediu. Daí `UC-NFTR-01` e `UC-NFTR-02`
> serem os primeiros.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Contrato | Teste | Status |
|----|-------------|------|----------|-------|--------|
| UC-NFTR-01 | Gate de emissão automática é per-business | must `[T0]` `[fiscal]` | ADR 0093 · charter §Goals | `TributacaoIndexContratoTest` | 🧪 |
| UC-NFTR-02 | Toggle sem config é recusado e não cria config | must `[fiscal]` | charter §UX Anti-patterns | `TributacaoIndexContratoTest` | 🧪 |
| UC-NFTR-03 | Aplicar template substitui a config e **preserva** as regras NCM | must `[fiscal]` | charter §Automation Hooks | `TributacaoIndexContratoTest` | 🧪 |
| UC-NFTR-04 | Update/destroy de regra de outro business → 404, e a regra alheia sobrevive | must `[T0]` | ADR 0093 · anti-hook | `TributacaoIndexContratoTest` | 🧪 |
| UC-NFTR-05 | Listagem não traz regra de outro tenant (com auth real) | must `[T0]` | ADR 0093 · anti-hook | `TributacaoIndexContratoTest` | 🧪 |
| UC-NFTR-06 | Regras ordenadas por NCM → UF origem → UF destino (NULL primeiro) | must | ARQ-0006 §Níveis 2/3 | `TributacaoControllerTest` | 🧪 |
| UC-NFTR-14 | A leitura pelo certificado não grava configuração | must `[T0]` `[fiscal]` | charter (sem auto-aplicar) · ADR 0093 | `EmpresaFiscalLookupTest` | 🧪 |
| UC-NFTR-15 | Template sugerido casa regime + UF + CNAE | must `[fiscal]` | US-NFE-TPL-001 | `TributacaoTemplateSugestaoTest` | 🧪 |
| UC-NFTR-16 | Fontes divergentes não são resolvidas sozinhas | must `[fiscal]` | ADR 0186 · D-SUPORTE | `EmpresaFiscalLookupTest` | 🧪 |
| UC-NFTR-17 | Aplicar template exige NCM padrão válido, usa o NCM que a tela manda e registra quem aplicou | must `[fiscal]` | auditoria 2026-05 bug #3 · US-NFE-062 | `TributacaoTemplateAplicarTest` | 🧪 |
| UC-NFTR-18 | Sem regime escolhido ou sem NCM válido, a tela não avança e não aplica | must `[fiscal]` | UC-NFTR-14..17 · charter (confirmação explícita) | e2e `nfe-tributacao-onboarding.spec.ts` | 🧪 |
| UC-NFTR-08 | O simulador devolve o mesmo cálculo da emissão e não grava nada | must `[fiscal]` | D-SIM · US-NFE-010 "Preview de cálculo" · `NfeService::montarItensNfe` | `TributacaoSimuladorTest` | 🧪 |
| UC-NFTR-09 | Simular produto de outra empresa é 404, e sem permissão é 403 | must `[T0]` | ADR 0093 · `nfe.tributacao.manage` | `TributacaoSimuladorTest` | 🧪 |
| UC-NFTR-10 | Sugestão da Jana nunca altera regra nem produto sozinha | must `[T0]` `[fiscal]` | D-IA · ADR 0141 · Fiscal/SPEC (rejeição é determinística) | `SugestaoFiscalTest` | 🧪 |
| UC-NFTR-11 | A Jana só lê o produto da própria empresa, sem preço, cliente ou CNPJ | must `[T0]` | ADR 0093 · ADR 0141 | `ProdutoFiscalToolTest` | 🧪 |
| UC-NFTR-12 | Aceitar ou descartar sugestão de outra empresa é 404 | must `[T0]` | ADR 0093 | `SugestaoFiscalTest` | 🧪 |
| UC-NFTR-13 | Jana fora do ar não afeta emissão nem cadastro | must `[fiscal]` | D-IA (IA nunca é caminho crítico) | `SugestaoFiscalTest` | 🧪 |
| UC-NFTR-19 | Aceite é por versão e exige permissão própria (o dono não aceita pelo contador) | must `[T0]` `[fiscal]` | D-CONTADOR · D-SUPORTE · `RevisaoContadorService::podeAceitar` | `AceiteContadorTest` | 🧪 |
| UC-NFTR-20 | Pedir ajuste exige comentário e fica registrado | must `[fiscal]` | D-CONTADOR | `AceiteContadorTest` | 🧪 |
| UC-NFTR-21 | Falta de aceite não bloqueia emissão | must `[fiscal]` | D-SUPORTE (bloqueio vira chamado) | `AceiteContadorTest` | 🧪 |
| UC-NFTR-22 | Link de revisão só abre com o código do e-mail, expira e serve a um business | must `[T0]` | D-CONTADOR (caminho 1) · US-NFE-009 (14 dias) · ADR 0093 | `RevisaoContadorLinkTest` | 🧪 |
| UC-NFTR-23 | A revisão pelo link mostra só regras fiscais, com de → para, e baixa o CSV do Import | must `[T0]` | D-CONTADOR · LGPD minimização · `ImportRegrasCsvService::COLUNAS_OBRIGATORIAS` | `RevisaoContadorLinkTest` | 🧪 |
| UC-NFTR-24 | A saúde fiscal lista o que vai virar rejeição e o item some ao corrigir | must `[fiscal]` | D-SUPORTE · US-NFE-010 "Filtro Suspeitas" · auditoria 2026-05 bug #3 | `SaudeFiscalTest` | 🧪 |
| UC-NFTR-25 | "Pronto pra emitir" = nenhuma pendência bloqueante | should `[fiscal]` | D-SUPORTE · UC-TRB-16 | `SaudeFiscalTest` | 🧪 |
| UC-NFTR-30 | Natureza a decidir não barra a nota e aparece na saúde fiscal (UC-FLX-31) | must `[fiscal]` | R-NFE-034 · D-NATUREZA · US-NFE-068 | `NaturezaFiscalTest` | 🧪 |
| UC-NFTR-31 | Item que o contador decidiu como serviço não sai em NF-e/NFC-e (UC-FLX-31) | must `[fiscal]` | R-NFE-034 · Súmula 156 STJ · US-NFE-069 (parcial) | `NaturezaFiscalTest` | 🧪 |
| UC-NFTR-32 | Só o contador decide a natureza, com fonte, e só de produto da própria empresa (UC-FLX-31) | must `[T0]` `[fiscal]` | R-NFE-034 · D-NATUREZA · ADR 0093 | `NaturezaFiscalTest` | 🧪 |

> **Recibo:** ver §Recibo de execução no rodapé — status é o **veredito** da corrida, não leitura de código.

---

## UC-NFTR-01 · Gate de emissão automática é per-business · `must` `[T0]` `[fiscal]`

- **Persona:** Wagner habilitando emissão automática no próprio tenant depois de validar o smoke fiscal
  em homologação. Nenhum outro tenant pode ser arrastado junto: emitir NFe em nome de terceiro é dano
  fiscal, não bug de UI.
- **Aceite:** Dado config de biz=1 e config de biz=2, ambas com `auto_emission_enabled = false` · Quando
  biz=1 faz `POST /nfe-brasil/tributacao/auto-emission/toggle` com `enabled=true` · Então biz=1 fica
  `true` **e biz=2 continua `false`**. E o inverso (desligar) também não atravessa.
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php)
  — `UC-NFTR-01 · toggle de emissão automática não atravessa para outro business`.
- **Contrato:** charter §Goals — *"Switch 'Emissão automática NFC-e' — gate per-business (ADR 0093 Tier 0)"*
  + charter §Automation Anti-hooks *"❌ Não acessa regras de outro `business_id`"* +
  [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** o toggle resolve o tenant por `session('business.id')` e faz
  `$config->update(...)`. Um refactor que troque o `first()` escopado por um `NfeBusinessConfig::first()`
  (ou que confie só no global scope sem usuário autenticado — o `ScopeByBusiness` faz early-return em
  `! auth()->check()`) liga a emissão automática do **vizinho** sem nenhum sintoma visível. O teste usa
  auth real, então exercita o scope global **e** o filtro manual juntos.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFTR-07 · Ligar o toggle é CONFIGURAR, nunca EMITIR · `must` `[T0]` `[fiscal]`

- **Regra de domínio ([W] 2026-07-28, textual):** *"As notas não podem sair automáticas em
  todos os clientes. Não é assim que funciona. O cliente escolhe se quer emitir ou não. E
  **tem configuração por empresa** se isso é automático."*
- **Persona:** a empresa decide, no seu próprio tenant, que quer NFC-e automática depois de
  validar o smoke fiscal. Nada é emitido nesse instante — a escolha fica gravada em
  `nfe_business_configs.auto_emission_enabled` e só passa a valer **quando houver venda
  finalizada**, via `EmitirNfceAoFinalizarVenda` → `EmitirNfceJob`.
- **Aceite:** Dado config do business com `auto_emission_enabled = false` · Quando faz
  `POST /nfe-brasil/tributacao/auto-emission/toggle` com `enabled=true` · Então a flag fica
  `true` (**pré-condição anti-vácuo**: prova que a operação aconteceu) **e nenhum**
  `EmitirNfceJob`/`EmitirNFSeJob` é despachado.
- **Por que este caso existe:** o charter já declarava a regra em §Automation Anti-hooks
  (*"Não dispara Job de emissão quando toggleAutoEmission=true"*) e prometia que *"cada item
  vira Pest GUARD test"* — mas **não havia guard**. Regra escrita e indefesa: um agente
  futuro lendo só o controller poderia "otimizar" emitindo no toggle, e nada quebraria.
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php)
  — `UC-NFTR-07 · ligar a emissão automática grava a escolha da empresa e NÃO despacha emissão`.
- **Status:** 🧪 existe teste que cita o UC — veredito é da lane (CT 100/CI, ADR 0062).

## UC-NFTR-02 · Toggle sem config é recusado e não cria config · `must` `[fiscal]`

- **Persona:** Larissa recém-configurada, ainda sem tributação default. Ligar a emissão automática antes
  de existir Nível 4 faria o motor cair em `TributacaoNaoConfiguradaException` na hora da venda — erro no
  balcão, com cliente na frente.
- **Aceite:** Dado **nenhuma** config para o business · Quando faço `POST …/auto-emission/toggle` com
  `enabled=true` · Então volto com **flash de erro** (mensagem manda aplicar um template antes) e
  **nenhuma** row de `nfe_business_configs` é criada — o toggle não pode "criar config no caminho".
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php)
  — `UC-NFTR-02 · toggle sem config existente é recusado e não cria config`.
- **Contrato:** charter §UX Anti-patterns — *"❌ Toggle auto-emission antes de existir config (canon =
  mostra erro flash + redireciona)"* + [ARQ-0006](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md)
  §Decisão, ramo *"❌ ERROR — exige tenant cadastrar default mínimo"*.
- **Regressão que defende:** a guarda é um `if (! $config) return redirect()->with('error', …)`. Trocar
  o `first()` por `firstOrCreate()` — refactor de aparência inocente, e o tipo de coisa que "conserta" um
  null-check — criaria uma config **vazia** e ligaria a emissão automática em cima dela. A asserção
  cobre os dois lados: a recusa **e** a ausência da row.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFTR-03 · Aplicar template substitui a config e **preserva** as regras NCM · `must` `[fiscal]`

- **Persona:** gráfica que já refinou 3 regras NCM específicas e resolve aplicar um template setorial
  pra corrigir o Nível 4. As regras que ela cadastrou à mão (Níveis 2/3) não podem ser varridas junto.
- **Aceite:** Dado 2 regras NCM do business e uma config existente · Quando aplico um template
  (`POST …/templates/{slug}/aplicar`) · Então a config passa a refletir o template **e** as 2 regras NCM
  continuam lá, intactas. Re-aplicar o mesmo template é **idempotente no sentido semântico** — regime e
  `tributacao_default` inalterados, sem row duplicada. (A comparação é sobre o JSON **decodificado**, não
  sobre a string: a coluna é `json` e o MySQL normaliza a ordem das chaves, então comparar bytes mediria
  o formato de armazenamento em vez do contrato.)
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php)
  — `UC-NFTR-03 · aplicar template substitui a config e preserva as regras NCM`.
- **Contrato:** charter §Automation Hooks — *"`aplicarTemplate` (cria/substitui config default; **regras
  NCM permanecem**)"* + charter §Goals *"Confirmação destrutiva ao aplicar template se já existe config"*.
- **Regressão que defende:** "aplicar template" é a única ação **destrutiva por design** da tela (troca o
  regime + a tributação default inteira). A fronteira do estrago — parar em `nfe_business_configs` e não
  encostar em `nfe_fiscal_rules` — hoje é só uma escolha do service, sem teste. O passo de idempotência
  vem junto porque é o que impede o "aplicar de novo" de virar um segundo write silencioso.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFTR-04 · Update/destroy de regra de outro business → 404, e a regra alheia sobrevive · `must` `[T0]`

- **Persona:** qualquer tenant. Uma regra NCM define a carga tributária de um produto — apagar ou editar
  a do vizinho corrompe a nota dele silenciosamente, e só aparece numa autuação.
- **Aceite:** Dado uma regra pertencente a biz=2 · Quando biz=1 faz `PUT /…/regras/{id}` ou
  `DELETE /…/regras/{id}` · Então **404** em ambos, **e** a regra de biz=2 continua no banco com os
  mesmos valores (não basta o 404: o que importa é que nada foi escrito antes dele). Controle positivo:
  as mesmas rotas funcionam na regra do próprio business — e "removida" significa `deleted_at` preenchido
  (`NfeFiscalRule` usa `SoftDeletes`; regra fiscal não some do histórico), não linha ausente.
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php)
  — `UC-NFTR-04 · update e destroy de regra de outro business dão 404 e não tocam a regra alheia`.
- **Contrato:** [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) +
  charter §Automation Anti-hooks *"❌ Não acessa regras de outro `business_id`"* + US-NFE-010 DoD
  *"Multi-tenant isolation (business A não vê regras de B)"*.
- **Regressão que defende:** o `TributacaoControllerTest` existente cobre o 404 do **`edit`** — e só dele.
  `update` e `destroy` (os dois que **escrevem**) estão descobertos, que é exatamente a metade que causa
  dano. Este UC fecha o par escrita/remoção e afirma a sobrevivência do dado, não só o status HTTP.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFTR-05 · Listagem não traz regra de outro tenant (com auth real) · `must` `[T0]`

- **Persona:** qualquer tenant abrindo `/nfe-brasil/tributacao`. Ver a tabela de NCM/alíquotas do vizinho
  é vazamento de dado comercial, além de fiscal.
- **Aceite:** Dado 1 regra de biz=1 e 1 regra de biz=2 · Quando biz=1 abre a tela e o payload de `regras`
  é resolvido · Então vem **exatamente** a regra de biz=1 (controle positivo) e **nenhuma** de biz=2.
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php)
  — `UC-NFTR-05 · listagem traz a regra do próprio business e nenhuma do vizinho`.
- **Contrato:** idem UC-NFTR-04.
- **Regressão que defende:** o `TributacaoControllerTest` já tem um caso de isolamento, mas ele instancia
  o controller **sem usuário autenticado** — e o `ScopeByBusiness` faz early-return em
  `! auth()->check()`. Ou seja: lá o global scope **no-opa**, e o que passa verde é só o `where` manual do
  controller. É a mesma causa que derrubou `NfeBrasilMultiTenantIsolationTest` e `Wave25NfeSaturationTest`
  em 2026-06-24 (documentada na allowlist de `nfebrasil-pest.yml`). Este UC roda **com `actingAs` e as duas
  chaves de sessão** (`business.id`, lida pelo controller, e `user.business_id`, lida pelo scope), então as
  **duas** camadas valem. Traz controle positivo, pra o verde não vir de a lista estar vazia por ausência
  de dado.
- **Nota de método:** o caso chama o controller e resolve a closure de `Inertia::defer`, em vez de bater na
  rota. Não é preguiça: arrancar prop deferida por partial reload exigiria casar `X-Inertia-Version`
  (mismatch → 409), e essa fragilidade não tem relação com o que o caso mede. O que ativa o global scope é a
  **autenticação**, não o transporte HTTP — e ela está presente.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFTR-06 · Regras ordenadas por NCM → UF origem → UF destino (NULL primeiro) · `must`

- **Persona:** Contador conferindo a cascade. A ordem **é** a semântica: dentro do mesmo NCM, a regra mais
  específica (Nível 2, com UF destino) e a genérica (Nível 3, UF destino "todas") precisam aparecer numa
  ordem estável, senão a leitura da precedência fica ambígua.
- **Aceite:** Dadas 3 regras (NCM `22021000` genérica; NCM `49019900` genérica; NCM `49019900` para `RJ`)
  · Quando a listagem é montada · Então a ordem é `22021000` → `49019900`(UF destino NULL) →
  `49019900`(RJ).
- **Teste:** [`TributacaoControllerTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoControllerTest.php)
  — `UC-NFTR-06 · regras retornam ordenadas por NCM, UF origem, UF destino (NULL primeiro)`.
- **Contrato:** [ARQ-0006](../../../../../memory/requisitos/NfeBrasil/adr/arq/0006-cascade-defaults-ncm-produto.md)
  §Decisão — Níveis 2 e 3 + charter §Goals *"Listagem regras NCM ordenadas por NCM → uf_origem →
  uf_destino (NULL last via `IS NULL DESC`)"*.
- **Onde é provado — e o limite honesto:** este é o **único** UC desta tela cujo teste **não** está na
  lane MySQL. Ele vive no `TributacaoControllerTest`, que roda na lane sqlite (`modules-pest.yml`, que
  executa `Modules/NfeBrasil/Tests` inteiro). Para *ordenação* isso é prova legítima — `ORDER BY` +
  `IS NULL DESC` são portáveis e não dependem do schema real. Não subi esse arquivo para a allowlist
  MySQL porque não consegui provar que ele passa lá (ver §Recibo), e ratchet sem prova é o anti-padrão
  que a própria allowlist proíbe.
- **Status: 🧪** — ver §Recibo.

---

## UC-NFTR-14 · A leitura pelo certificado não grava configuração · `must` `[T0]` `[fiscal]`

- **Persona:** empresa nova que acabou de enviar o certificado.
- **Aceite:** Dado um business com certificado válido · Quando chamo `GET /nfe-brasil/tributacao/empresa-fiscal` ·
  Então recebo os campos com fonte e **nenhuma** linha muda em `nfe_business_configs`, `nfe_fiscal_rules` e
  `business`. Controle positivo: a leitura de outro business não retorna dados deste, e vice-versa.
- **Teste:** [`EmpresaFiscalLookupTest`](../../../../../Modules/NfeBrasil/Tests/Feature/EmpresaFiscalLookupTest.php) — `UC-NFTR-14 · leitura pelo certificado não grava`.
- **Contrato:** charter (sem auto-aplicar template sem clique) · ADR 0093.
- **Status: 🧪** — escrito no backend pela thread 21 (#8829); o UC entra aqui na thread 22.

## UC-NFTR-15 · Template sugerido casa regime + UF + CNAE · `must` `[fiscal]`

- **Persona:** gráfica no Simples em SP.
- **Aceite:** Dado regime Simples, UF SP e CNAE 1813-0/01 · Quando peço sugestão · Então o 1º template casa os
  três e cada um vem com os valores que aplicaria. Controle positivo: trocar o regime para presumido muda o 1º.
- **Teste:** [`TributacaoTemplateSugestaoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoTemplateSugestaoTest.php) — `UC-NFTR-15 · sugestão por regime, UF e CNAE`.
- **Contrato:** US-NFE-TPL-001.
- **Status: 🧪**

## UC-NFTR-16 · Fontes divergentes não são resolvidas sozinhas · `must` `[fiscal]`

- **Persona:** empresa cuja BrasilAPI diz Simples e a SEFAZ diz outro regime.
- **Aceite:** Dado fontes que discordam do regime · Quando leio · Então o campo volta `divergente` com as opções
  e **sem** valor. Controle positivo: com as fontes concordando, o regime volta preenchido.
- **Teste:** [`EmpresaFiscalLookupTest`](../../../../../Modules/NfeBrasil/Tests/Feature/EmpresaFiscalLookupTest.php) — `UC-NFTR-16 · divergência não é resolvida sozinha`.
- **Contrato:** ADR 0186 (autoridade por campo) · D-SUPORTE.
- **Status: 🧪**

## UC-NFTR-17 · Aplicar template exige NCM padrão válido e registra quem aplicou · `must` `[fiscal]`

- **Persona:** qualquer empresa aplicando template.
- **Aceite:** Dado `ncm_default` vazio ou `00000000` · Quando aplico · Então 422 e a config não muda. Com NCM
  válido · Então aplica, preserva as regras NCM (UC-NFTR-03) e grava `activity` `template.aplicado` com o autor.
  O NCM que a tela manda no corpo é o que vale (antes da thread 22 a rota o ignorava). Controle positivo:
  re-aplicar o mesmo template continua idempotente.
- **Teste:** [`TributacaoTemplateAplicarTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoTemplateAplicarTest.php) — `UC-NFTR-17 · aplicar exige NCM padrão e loga` e
  `UC-NFTR-17 · a rota aplica o NCM padrão que a tela manda`.
- **Contrato:** auditoria 2026-05 bug #3 · US-NFE-062.
- **Status: 🧪**

## UC-NFTR-18 · Sem regime escolhido ou sem NCM válido, a tela não avança e não aplica · `must` `[fiscal]`

- **Persona:** Larissa configurando sozinha.
- **Aceite:** Dado regime divergente não escolhido · Então "Continuar" fica desabilitado no passo 2 · Dado NCM
  vazio, incompleto ou `00000000` · Então desabilitado no passo 3 · Quando completo · Então o passo 4 mostra
  regime, template e NCM, e só ali existe "Aplicar template". Controle positivo: fechar o drawer não grava nada.
- **Teste:** e2e [`nfe-tributacao-onboarding.spec.ts`](../../../../../e2e/nfe-tributacao-onboarding.spec.ts) — `UC-NFTR-18 · onboarding não avança sem regime e NCM`.
  A leitura da empresa é mockada no spec (SEFAZ/BrasilAPI ficam com o UC-NFTR-14/16); o POST de aplicar só é contado.
- **Contrato:** UC-NFTR-14..17 · charter (confirmação explícita) · alvo de forma `TrOnboarding` em
  `prototipo-ui/cowork/Wagner/fiscal-tributacao.jsx` (D-ANCORA).
- **Regressão que defende:** tela que "ajuda" pulando a decisão.
- **Status: 🧪** — veredito da lane `e2e-gate.yml`.

## UC-NFTR-08 · O simulador devolve o mesmo cálculo da emissão e não grava nada · `must` `[fiscal]`

- **Persona:** Eliana conferindo um imposto antes de vender.
- **Aceite:** Dado um produto com NCM, uma regra para ele e a UF de destino · Quando simulo
  (`GET /nfe-brasil/tributacao/simular`) · Então CFOP, nível N1–N4 e valores são **idênticos** ao item que
  a emissão monta para o mesmo contexto (`NfeService::montarItensNfe`, com a UF de origem que o próprio
  `NfeService` resolve), batem com a conta à mão, e nenhuma tabela fiscal ganha ou perde linha.
  Controle positivo: mudar a UF de destino para a da regra N2 muda o nível usado e o valor.
- **Teste:** [`TributacaoSimuladorTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoSimuladorTest.php)
  — `UC-NFTR-08 · simulador = emissão e zero escrita`.
- **Contrato:** D-SIM ([W] 2026-10-06) · US-NFE-010 "Preview de cálculo" · charter §Non-Goals (exceção D-SIM).
- **Regressão que defende:** um simulador com cálculo próprio que diverge da nota.
- **Fora daqui:** operação, destinatário e regime não entram — a emissão ainda não os passa ao motor;
  mostrá-los faria a prévia divergir da nota.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-09 · Simular produto de outra empresa é 404, e sem permissão é 403 · `must` `[T0]`

- **Persona:** qualquer tenant.
- **Aceite:** Dado `product_id` de outra empresa · Quando simulo · Então 404 e nada do produto volta
  (nem nome, nem NCM). Sem `nfe.tributacao.manage` · Então 403. Controle positivo: o produto da própria
  empresa simula.
- **Teste:** [`TributacaoSimuladorTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoSimuladorTest.php)
  — `UC-NFTR-09 · simulador isolado por tenant e por permissão`.
- **Contrato:** ADR 0093 · `SimularTributacaoRequest::authorize`.
- **Regressão que defende:** endpoint read-only vazando cadastro alheio.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-10 · Sugestão da Jana nunca altera regra nem produto sozinha · `must` `[T0]` `[fiscal]`

- **Persona:** qualquer usuário com a Jana ligada.
- **Aceite:** Dado sugestões geradas · Então `products` e `nfe_fiscal_rules` ficam intactos, e só viram
  sugestão as que apontam para algo da empresa com tipo, risco e NCM válidos (a alíquota que a Jana
  mandar junto é descartada — a IA nunca sugere alíquota). Aceitar **sem** `nfe.tributacao.manage` → 403;
  risco alto sem `confirmou_leitura` → 422; aceitar certo → versão nova da regra pelo caminho normal +
  `activity` com o autor. Controle positivo: descartar não muda o produto e também gera `activity`.
- **Teste:** [`SugestaoFiscalTest`](../../../../../Modules/NfeBrasil/Tests/Feature/SugestaoFiscalTest.php) — `UC-NFTR-10 · sugestão nunca aplica sozinha`.
- **Contrato:** D-IA ([W] 2026-10-06) · ADR 0141 · Fiscal/SPEC (rejeição é receita determinística por cStat).
- **Regressão que defende:** IA mudando a tributação sem dono.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-11 · A Jana só lê o produto da própria empresa, sem preço, cliente ou CNPJ · `must` `[T0]`

- **Persona:** —
- **Aceite:** Dado a tool fiscal da Jana · Quando monta o contexto · Então só há id, nome, descrição,
  unidade, categoria, NCM e regras do **business do construtor**; nenhum campo de preço, custo, cliente
  ou CNPJ. Controle positivo: a tool da outra empresa vê o produto dela.
- **Teste:** [`ProdutoFiscalToolTest`](../../../../../Modules/NfeBrasil/Tests/Feature/ProdutoFiscalToolTest.php)
  — `UC-NFTR-11 · tool fiscal só lê o próprio tenant e sem PII`.
- **Contrato:** ADR 0093 · ADR 0141 (`business_id` pelo construtor).
- **Regressão que defende:** vazamento entre empresas via prompt.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-12 · Aceitar ou descartar sugestão de outra empresa é 404 · `must` `[T0]`

- **Persona:** qualquer tenant.
- **Aceite:** Dado sugestão da empresa A · Quando B aceita ou descarta · Então 404 e nada muda em A.
  Controle positivo: A aceita a própria e o NCM do produto muda.
- **Teste:** [`SugestaoFiscalTest`](../../../../../Modules/NfeBrasil/Tests/Feature/SugestaoFiscalTest.php) — `UC-NFTR-12 · sugestão isolada por tenant`.
- **Contrato:** ADR 0093.
- **Regressão que defende:** aceite cruzado mudando cadastro de outra empresa.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-13 · Jana fora do ar não afeta emissão nem cadastro · `must` `[fiscal]`

- **Persona:** dia em que o provedor de IA cai.
- **Aceite:** Dado a Jana indisponível · Quando peço sugestões · Então a resposta diz
  "Sugestões indisponíveis agora." e nada é gravado; salvar regra e calcular imposto funcionam igual.
  Controle positivo: com a Jana de volta, as sugestões reaparecem.
- **Teste:** [`SugestaoFiscalTest`](../../../../../Modules/NfeBrasil/Tests/Feature/SugestaoFiscalTest.php) — `UC-NFTR-13 · IA indisponível não bloqueia fiscal`.
- **Contrato:** D-IA (IA é sugestão, nunca caminho crítico).
- **Regressão que defende:** emissão travada porque a IA não respondeu.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-19 · Aceite é por versão e exige permissão própria · `must` `[T0]` `[fiscal]`

- **Persona:** contador com usuário (o link sem conta é a thread 15b).
- **Aceite:** Dado uma regra criada · Então nasce uma revisão `pendente` com o de → para, o autor e a
  origem (manual · csv · jana). Quando o **dono da empresa** (papel `Admin#`) ou quem só tem
  `nfe.tributacao.manage` aceita → 403. Com `nfe.tributacao.aceitar` → grava quem, e-mail, quando e IP,
  e `activity("aceite.registrado")`. Quando a regra aceita é editada · Então a versão nova nasce pendente
  com o de → para e a antiga mantém o aceite. Controle positivo: o lote do Import CSV entra com origem "csv".
- **Teste:** [`AceiteContadorTest`](../../../../../Modules/NfeBrasil/Tests/Feature/AceiteContadorTest.php) — `UC-NFTR-19 · aceite por versão, permissão própria`.
- **Contrato:** D-CONTADOR · D-SUPORTE. A permissão é checada direto no Spatie (`hasPermissionTo`): o
  `Gate::before` libera o `Admin#` em qualquer `can()`.
- **Regressão que defende:** dono "aceitando" pelo contador; aceite antigo valendo para regra que mudou.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-20 · Pedir ajuste exige comentário e fica registrado · `must` `[fiscal]`

- **Persona:** contador que discorda.
- **Aceite:** Dado item pendente · Quando peço ajuste sem comentário (ou só espaços) → 422. Com comentário →
  "ajuste pedido", o texto aparece na lista da empresa e `activity("aceite.ajuste_pedido")`. Controle
  positivo: depois da correção (versão nova), o item volta pendente.
- **Teste:** [`AceiteContadorTest`](../../../../../Modules/NfeBrasil/Tests/Feature/AceiteContadorTest.php) — `UC-NFTR-20 · pedir ajuste com comentário`.
- **Contrato:** D-CONTADOR.
- **Regressão que defende:** discordância do contador que se perde no e-mail.
- **Fora daqui:** mostrar o pedido na Saúde fiscal é a thread 14.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-21 · Falta de aceite não bloqueia emissão · `must` `[fiscal]`

- **Persona:** Larissa vendendo com regra ainda não revisada.
- **Aceite:** Dado regra vigente sem aceite · Então o motor calcula normalmente (item de 1.000,00 a 12% → ICMS
  120,00) e a lista de revisões conta a regra em `sem_aceite`. Controle positivo: aceita, `sem_aceite` cai 1
  e o cálculo segue igual.
- **Teste:** [`AceiteContadorTest`](../../../../../Modules/NfeBrasil/Tests/Feature/AceiteContadorTest.php) — `UC-NFTR-21 · sem aceite emite normal`.
- **Contrato:** D-SUPORTE (bloqueio vira chamado).
- **Regressão que defende:** um gate de aceite parando o balcão.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-22 · Link de revisão só abre com o código do e-mail, expira e serve a um business · `must` `[T0]`

- **Persona:** contador sem conta — e quem recebeu o link encaminhado.
- **Aceite:** Dado a empresa (`nfe.tributacao.manage`) envia o link · Então ele vai ao e-mail do contador,
  assinado, válido por 14 dias e preso ao business. Quando abro · Então só a tela do código, e um código de
  6 dígitos vai ao mesmo e-mail (15 min). Sem o código, o CSV e o aceite dão 403. Código errado 5× → link
  bloqueado (403, nem o código certo abre). Assinatura alterada, business trocado na URL ou mais de 14 dias
  → 403. O código serve uma vez: outra sessão com o mesmo código → recusado; código com mais de 15 min →
  recusado. Liberado, a lista mostra só o business do link; revisão de outro business pelo link → 404, e a
  sessão liberada para um link não serve para outro. Controle positivo: o aceite pelo link grava nome,
  e-mail e CRC do contador do link, sem usuário.
- **Teste:** [`RevisaoContadorLinkTest`](../../../../../Modules/NfeBrasil/Tests/Feature/RevisaoContadorLinkTest.php) — `UC-NFTR-22 · link assinado + código, 14 dias, um business`.
- **Contrato:** D-CONTADOR caminho 1 · US-NFE-009 (link de 14 dias) · ADR 0093.
- **Regressão que defende:** link encaminhado aceitando em nome do contador; link que abre outra empresa.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-23 · A revisão pelo link mostra só regras fiscais, com de → para, e baixa o CSV do Import · `must` `[T0]`

- **Persona:** contador revisando pelo link.
- **Aceite:** Dado versões pendentes · Então cada item mostra NCM/UF, o campo que mudou (de → para), autor,
  data e origem. Toda consulta das rotas do link fica em regra fiscal, revisão, o próprio link e o nome do
  autor e da empresa — nenhuma lê cliente, venda ou nota. "Baixar regras (CSV)" sai com as 10 colunas do
  Import, só do business do link. Pedir ajuste sem comentário → 422; com comentário → "ajuste pedido".
  Controle positivo: o CSV baixado passa pelo `parse` do Import sem erro.
- **Teste:** [`RevisaoContadorLinkTest`](../../../../../Modules/NfeBrasil/Tests/Feature/RevisaoContadorLinkTest.php) — `UC-NFTR-23 · só regras fiscais, com de → para e CSV do Import`.
- **Contrato:** D-CONTADOR (caminhos 1 e 3) · LGPD minimização · `ImportRegrasCsvService::COLUNAS_OBRIGATORIAS`.
- **Regressão que defende:** página do contador vazando dado de negócio; planilha que não volta pelo Import.
- **Fora daqui:** o cadastro do contador em `/fiscal/config` e o papel "Contador" são a thread 15c.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-24 · A saúde fiscal lista o que vai virar rejeição e o item some ao corrigir · `must` `[fiscal]`

- **Persona:** Larissa / Eliana abrindo a Tributação.
- **Aceite:** Para cada tipo — NCM padrão da empresa ausente ou `00000000` (bloqueante) · certificado
  ausente ou vencido (bloqueante) · certificado a ≤ 30 dias (aviso, com os dias) · produto ativo sem NCM
  válido · produto que já saiu em nota com o NCM padrão (`metadata.itens_ncm_padrao`) e continua sem NCM:
  Dado a fixture que
  provoca · Então aparece com a rota que resolve · Quando corrijo · Então some. Nada de outro business
  aparece, e avaliar não escreve. Controle positivo: NCM padrão válido e certificado longe de vencer não
  geram item.
- **Teste:** [`SaudeFiscalTest`](../../../../../Modules/NfeBrasil/Tests/Feature/SaudeFiscalTest.php) — `UC-NFTR-24 · pendência aparece e some ao corrigir` e `UC-NFTR-24 · a tela recebe cada pendência com o link que resolve`.
- **Tela:** card `data-contract="saude-fiscal"` no topo da Index (`_components/SaudeFiscal.tsx`, prop deferida
  `saude`), forma da aba "Saúde fiscal" do `fiscal-tributacao.jsx`. Severidade e contagem vêm do servidor.
- **Contrato:** D-SUPORTE · US-NFE-010 "Filtro Suspeitas" · auditoria 2026-05 bug #3 · `SaudeFiscalService`.
- **Regressão que defende:** o usuário só descobre o problema na rejeição da SEFAZ.
- **Fora daqui:** regra sem cClassTrib (Reforma ligada), versão sem aceite do contador, cliente e emitente sem
  código IBGE, serviço sem código municipal e rejeição cadastral recente ainda não entram.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-25 · "Pronto pra emitir" = nenhuma pendência bloqueante · `should` `[fiscal]`

- **Persona:** empresa nova configurando.
- **Aceite:** Dado NCM padrão inválido e certificado vencido · Então "não pronto" com 2 bloqueantes ·
  Quando resolvo as duas · Então "pronto". Pendência só de aviso não bloqueia. Controle positivo: empresa
  configurada (NCM válido + certificado) nasce pronta mesmo com produto sem NCM.
- **Teste:** [`SaudeFiscalTest`](../../../../../Modules/NfeBrasil/Tests/Feature/SaudeFiscalTest.php) — `UC-NFTR-25 · pronto pra emitir`.
- **Contrato:** UC-TRB-16 · D-SUPORTE · templates L1 (US-NFE-TPL-001).
- **Regressão que defende:** a primeira nota ser o teste da configuração.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-26 · Fechamento do mês: notas fecham e rejeitada pendente vira ação · `must` `[fiscal]`

- **Persona:** contador abrindo o link de revisão (UC-NFTR-22), com o fechamento ligado.
- **Aceite:** Dado o mês com 398 autorizadas, 9 canceladas, 1 denegada e 4 rejeitadas · Então "notas
  fecham" mostra 412 e "rejeitadas pendentes" mostra 4 com a ação "Resolver ou inutilizar a numeração",
  sem bloquear nada (D-REJEITADA). Nota ainda sem resposta da SEFAZ deixa "notas fecham" pendente. Só
  conta notas do business do link. Controle positivo: mês sem rejeitada mostra ok.
- **Teste:** [`FechamentoContadorTest`](../../../../../Modules/NfeBrasil/Tests/Feature/FechamentoContadorTest.php) — `UC-NFTR-26 · R-FISC-040 · …` (4 casos).
- **Tela:** seção `data-contract="fechamento-contador"` na página do link (`contador/revisao.blade.php`),
  atrás de `NFEBRASIL_FECHAMENTO_CONTADOR` (desligada). Forma da aba "Contador" do `fiscal-tributacao.jsx`.
- **Contrato:** playbook Fiscal thread 28 (R-FISC-040) · D-FECHAMENTO · D-REJEITADA · `FechamentoContadorService`.
- **Regressão que defende:** contador fechando com nota rejeitada esquecida.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-27 · Sublimite avisa a 90% e a segregação separa a ST · `must` `[fiscal]`

- **Persona:** contador conferindo o PGDAS-D.
- **Aceite:** Dado receita de 12 meses de R$ 3,48 mi · Então "atenção" com 96,7% do sublimite de
  R$ 3,6 mi (LC 123/2006 art. 13-A); exatamente 90% já é atenção, acima do sublimite é pendente. Dado
  item com CSOSN 500 · Então ele sai na linha "Revenda com ST já retida" (art. 18 §4º-A, I), separado da
  revenda e da produção própria (CFOP x101), e a soma das linhas é comparada com a receita do mês no
  Financeiro. Nota autorizada sem XML deixa a segregação em atenção. Controle positivo: receita 1 centavo
  abaixo de 90% mostra ok.
- **Teste:** [`FechamentoContadorTest`](../../../../../Modules/NfeBrasil/Tests/Feature/FechamentoContadorTest.php) — `UC-NFTR-27 · R-FISC-041 · …` (4 casos).
- **Contrato:** playbook Fiscal thread 28 (R-FISC-041) · LC 123/2006 arts. 13-A e 18 §4º/§4º-A.
- **Regressão que defende:** ICMS pago duas vezes no DAS; estouro de sublimite descoberto tarde.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-28 · Entrada de outra UF pede classificação para DIFAL · `should` `[fiscal]` · **parcial**

- **Persona:** contador conferindo as compras do mês.
- **Aceite (parcial):** Dado entrada recebida pelo DF-e de outra UF · Então "DIFAL de compra" pede
  classificar (revenda × uso, consumo ou ativo). Só entrada da mesma UF mostra ok. Sem entrada nenhuma no
  mês não mostra ok: avisa conferir se a consulta à SEFAZ está ativa.
- **O que falta para R-FISC-042 inteiro:** o ERP não registra a destinação da entrada, então não separa
  "MG para ativo" de "PR para revenda". Depende da ADR de entrada por XML (thread 11, `proposto`).
- **Teste:** [`FechamentoContadorTest`](../../../../../Modules/NfeBrasil/Tests/Feature/FechamentoContadorTest.php) — `UC-NFTR-28 · R-FISC-042 (parcial) · …`.
- **Contrato:** playbook Fiscal thread 28 (R-FISC-042) · LC 87/1996 · LC 190/2022 · D-ENTRADA.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-29 · Vindo do painel de produto, o simulador abre com o produto escolhido · `could` `[T0]`

- **Persona:** quem está na lista de produtos, na aba Fiscal do painel, e quer ver como a nota daquele
  produto vai sair.
- **Aceite:** Dado o link "Abrir no módulo Fiscal" do painel de produto (`/nfe-brasil/tributacao?produto={id}#simulador`)
  · Então o simulador já abre com aquele produto escolhido; falta só o valor e a UF de destino.
  Produto de outra empresa, id inválido ou sem o parâmetro: a tela abre como sempre, sem produto e sem erro.
- **Teste:** [`TributacaoIndexContratoTest`](../../../../../Modules/NfeBrasil/Tests/Feature/TributacaoIndexContratoTest.php) — `UC-NFTR-29 · …` (controle positivo com o produto da própria empresa; o do vizinho volta `null`).
- **Contrato:** protótipo do Produto (`prototipo-ui/cowork/Felipe/produto-blade.jsx`, aba `fiscal` do
  `DetalheDrawer`: botão "Abrir no módulo Fiscal", destino `fiscal-modulo` com o produto) + UC-PIDX-25 do
  [`Produto/Index.casos.md`](../../Produto/Index.casos.md). O simulador é o mesmo do UC-NFTR-08: nada é
  gravado, e o valor não é preenchido de antemão (quem simula digita).
- **Status: ⬜** — nasce neste PR; verde esperado. Só a lane decide.

---

## Natureza fiscal do item decide a nota (R-NFE-034 · UC-FLX-31)

> **Âncora:** [R-NFE-034](../../../../../memory/requisitos/NfeBrasil/SPEC.md) (*"Natureza decidida pelo
> contador vale para o produto"*) e US-NFE-068 (`done`), com a decisão D-NATUREZA de [W] em 2026-10-06
> (*"o contador, por produto, com fonte registrada; enquanto pendente, emite pelo padrão da UF. A Jana
> nunca decide."*). É o UC-FLX-31 do catálogo de fluxos (*"Serviço gráfico sob encomenda → NFS-e ·
> revenda → NF-e · instalação → NFS-e"*), estado **parcial**: o resto é a
> [US-NFE-069](../../../../../memory/requisitos/NfeBrasil/SPEC.md) (`todo`, bloqueada pela NFS-e real
> da US-NFE-060). Código conferido, não usado como fonte: `NaturezaFiscalService` (`marcarPendente`,
> `decidir`) e `NfeService::recusarItensDeServico`.
>
> **Por que mora nesta tela:** a pendência aparece no card de saúde fiscal desta tela (UC-NFTR-24) e a
> decisão exige a mesma permissão dela (`nfe.tributacao.manage`). A tela de decisão ainda não existe
> (R-NFE-034 *"Fora deste item"*): os casos abaixo provam o serviço e a trava da emissão, não um botão.

## UC-NFTR-30 · Natureza a decidir não barra a nota e aparece na saúde fiscal · `must` `[fiscal]`

- **Persona:** Eliana (contadora) / Larissa abrindo a Tributação.
- **Aceite:** **Dado** um banner personalizado marcado como natureza pendente **Quando** a venda com ele
  vai para a NF-e **Então** a nota segue pelo padrão da UF (não é recusada) e a saúde fiscal mostra
  "natureza a decidir" com o id do banner, como **aviso** (não bloqueante). Controle positivo: depois que
  o contador decide, marcar o banner como pendente de novo não desfaz a decisão e ele sai da fila.
- **Teste:** [`NaturezaFiscalTest`](../../../../../Modules/NfeBrasil/Tests/Feature/NaturezaFiscalTest.php) — `UC-NFTR-30 · UC-NFTR-31 · R-NFE-034 · natureza decidida pelo contador vale para o produto`.
- **Contrato:** R-NFE-034 · D-NATUREZA · `SaudeFiscalService` (`natureza_a_decidir`).
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-31 · Item que o contador decidiu como serviço não sai em NF-e/NFC-e · `must` `[fiscal]`

- **Persona:** faturista emitindo a nota da venda.
- **Aceite:** **Dado** o contador decidiu o banner como "serviço 24.01" com a fonte "Súmula 156 do STJ"
  **Quando** a venda com o banner vai para a NF-e/NFC-e **Então** a nota **inteira** é recusada dizendo
  "emita NFS-e" — não sai sem o item, porque o total da nota é o total da venda. A decisão fica gravada
  com autor, data, fonte e o item da LC 116 (`24.01`). Controle positivo: o mesmo banner decidido como
  **mercadoria** (fonte "SEFAZ-SP Consulta 10406/2016") emite normalmente, e o código de serviço `24.01`
  enviado junto é descartado.
- **Teste:** [`NaturezaFiscalTest`](../../../../../Modules/NfeBrasil/Tests/Feature/NaturezaFiscalTest.php) — os 2 casos (`UC-NFTR-30 · UC-NFTR-31 · …` e `UC-NFTR-31 · UC-NFTR-32 · …`).
- **Contrato:** R-NFE-034 · Súmula 156 do STJ · REGRA MESTRE de valor (a nota não sai com total
  diferente da venda).
- **Fora daqui:** dividir a venda em NF-e + NFS-e é a US-NFE-069 (backlog abaixo).
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## UC-NFTR-32 · Só o contador decide a natureza, com fonte, e só de produto da própria empresa · `must` `[T0]` `[fiscal]`

- **Persona:** qualquer fluxo que tente gravar a natureza — inclusive a Jana.
- **Aceite:** **Quando** quem decide não tem `nfe.tributacao.manage`, ou tem a permissão mas é de outra
  empresa **Então** recusa ("Só o contador"). Fonte em branco, serviço sem item da LC 116 no formato
  `00.00` ou natureza que não seja mercadoria/serviço também recusam — e nada é gravado. Produto de outra
  empresa não recebe decisão nem pendência ("não encontrado nesta empresa").
- **Teste:** [`NaturezaFiscalTest`](../../../../../Modules/NfeBrasil/Tests/Feature/NaturezaFiscalTest.php) — `UC-NFTR-31 · UC-NFTR-32 · R-NFE-034 · só o contador decide: …` (tenant fictício 98 e cliente 99, ADR 0358).
- **Contrato:** D-NATUREZA (a Jana só marca pendente) · ADR 0093.
- **Status: 🧪** — veredito da lane `PHP / Pest (NfeBrasil · MySQL)`.

## Backlog — sem UC até ganhar teste

> Prosa honesta, sem gate. Vira UC quando ganhar teste que o cite (G-2).

- `[BACKLOG]` **Venda com mercadoria e serviço sai em duas notas: NF-e com as mercadorias, NFS-e com os
  serviços, e a soma das duas fecha o total da venda** (US-NFE-069 · UC-FLX-31). Hoje a NF-e recusa a
  venda inteira (UC-NFTR-31); a divisão não existe.
- `[BACKLOG]` **A NFS-e usa o `codigo_servico` gravado na natureza do produto; o padrão da empresa só vale
  para produto sem natureza decidida** (US-NFE-069). Hoje a NFS-e não lê a natureza.
- `[BACKLOG]` **Item de instalação sai na NFS-e, mesmo numa OS com material** (US-NFE-069 · UC-FLX-27).
- `[BACKLOG]` **Produto com natureza pendente bloqueia o faturamento dividido com mensagem que manda o
  contador decidir** (US-NFE-069). Hoje pendente emite pelo padrão da UF (UC-NFTR-30), que é a regra da
  NF-e sozinha; o bloqueio vale para a divisão.
- `[BACKLOG]` **"Perguntar à Jana" responde com fonte e não altera nada** (playbook Fiscal thread 10, UC-NFTR-10b provisório). Não entrou no PR da 10: é chat livre, e a thread entregou só as sugestões estruturadas. Vira UC com o teste `JanaFiscalPerguntaTest`.

- `[BACKLOG]` **`regras` e `templates` são deferidos; `config` é eager.** O charter promete
  `p95 first-paint < 1500ms` e o controller aplica `Inertia::defer` nas duas props caras (Wave 25 D3).
  Nada prova que continuam deferidas — um refactor que volte a eager degrada a tela sem sinal. Vira UC
  quando houver um caso que asserte a ausência das props no render inicial e a presença no partial reload.
- `[BACKLOG]` **AuditLog nas mutações de regra.** `store`/`update`/`destroy` e `toggleAutoEmission` já
  chamam `activity('nfe.tributacao')->log(...)`, mas nenhum teste prova. É o escopo declarado da
  **US-NFE-062** (P1), ainda aberta — deixo lá em vez de duplicar aqui.
- `[BACKLOG]` **Confirmação destrutiva ao aplicar template sobre config existente.** O charter §Goals
  pede `window.confirm` explícito e o `.tsx` implementa. É comportamento de **cliente** — precisaria de
  e2e Playwright, não de Pest; a tela não tem spec e2e hoje (`screen:files` → *"nenhum teste Browser cita
  o path"*).

---

## Recibo de execução

| Quando | Onde | Resultado |
|---|---|---|
| _pendente_ | lane `PHP / Pest (NfeBrasil · MySQL)` — UC-NFTR-01..05 | a preencher com o run id |
| 2026-10-07 | CT 100 `oimpresso-staging`, worktree isolado do branch da thread 22 (`3055c7e57`, já com o `main`) · `TributacaoIndexContrato` + `TributacaoController` + `EmpresaFiscalLookup` + `TributacaoTemplateSugestao` + `TributacaoTemplateAplicar` | **19 passed · 135 assertions** — UC-NFTR-01..07 e 14..17 verdes. Revalidação que sustenta o `last_run` depois da mudança no `Index.tsx` (drawer da thread 22). UC-NFTR-18 é e2e: veredito da lane `e2e-gate.yml`. |
| _pendente_ | lane `modules-pest` (sqlite) — UC-NFTR-06 | a preencher com o run id |

> ⚠️ **Não foi possível pré-provar no CT 100.** O container `oimpresso-staging` **não tem as tabelas do
> NfeBrasil** (`nfe_business_configs` / `nfe_fiscal_rules` / `nfe_emissoes` ausentes — medido via
> `Schema::hasTable` em 2026-07-27): a suite SKIPa inteira lá. A lane de CI é o único lugar que a executa.
> Ao ler o resultado, conferir a **contagem de testes passados** no JUnit, não só o check verde — suite
> que SKIPa também fica verde (gate mudo, `proibicoes.md` §5 2026-07-27).
>
> Os `Status: 🧪` só sobem pra ✅ quando o manifesto do G-7 (`scripts/casos-test-results.json`) for
> regravado a partir do JUnit — ✅ é afirmação que exige prova.

---
title: "SPED EFD-ICMS/IPI — qual é a fonte de verdade do emitente (UF/CNPJ/IE) e o que a trava ainda segura"
status: proposta
date: "2026-09-04"
owners: [W]
parent_module: Fiscal
related_adrs: [62, 93, 178, 273, 321, 358]
related_specs:
  - memory/requisitos/Fiscal/SPEC.md (US-FISCAL-016, 017, 020)
  - memory/requisitos/NfeBrasil/SPEC.md
related_charters:
  - resources/js/Pages/Fiscal/Sped.charter.md
---

# SPED — a identificação do emitente sai vazia, e a causa não é a que estava escrita

> **Origem:** achado do primeiro golden file do EFD-ICMS/IPI (2026-09-03), documentado em
> [`sped-icms-ipi-golden.meta.md`](../../../Modules/Fiscal/Tests/Fixtures/sped-icms-ipi-golden.meta.md).
> Esta proposta **mede** o achado antes de propor conserto, e corrige duas afirmações dele.
>
> **Nada de motor foi alterado.** Toda medição rodou no CT 100 ([ADR 0062](../0062-separacao-runtime-hostinger-ct100.md))
> e em leitura de produção; a única execução do gerador foi dentro de transação com `rollBack`.

## 1. Correção de premissa — duas afirmações do achado não sobrevivem à medição

O achado original diz que as colunas lidas por `registro0000`/`registro0005` "moram em
`business_locations`", e que a consequência em cadeia é que **"a nota SC→SC saiu com CFOP `6102`
em vez de `5102`"**. Medi as duas. A primeira é meia-verdade; a segunda é falsa.

### 1.1 O CNPJ e a IE do emitente **não** moram em `business_locations`

`Schema::hasColumn` no CT 100, tabela `business` (133 colunas):

| O que o SPED lê | Existe em `business`? | Onde o dado realmente está |
|---|---|---|
| `state`, `city`, `zip_code`, `landmark`, `mobile`, `email` | **NÃO** (6 de 6) | `business_locations` |
| `tax_number` | **NÃO** | `business.cnpj` (a `business` tem `tax_number_1`/`_2`, que são outra coisa) |
| `inscricao_estadual` | **NÃO** | `business.ie` |

Ou seja: **8 de 8 colunas lidas não existem**, mas a `business` **tem** o bloco fiscal BR
completo com outros nomes — `cnpj`, `ie`, `razao_social`, `cep`, `rua`, `numero`, `bairro`,
`cidade_id`, `regime` (10 de 10 presentes). O gerador não perdeu o dado: ele lê o **vocabulário
errado** — nomenclatura UPOS core, enquanto o emitente vive no bloco BR que o fork adicionou.

É o mesmo padrão que a [ADR 0178](../0178-restauracao-campos-fiscais-br-canon.md) já resolveu
para `contacts` (`tax_number` genérico × `cpf_cnpj` semântico), agora do lado do emitente.

### 1.2 O CFOP do golden **não** teria mudado — a operação nunca foi SC→SC

A regra é uma linha (`fallbackSimplesNacional`): `cfop = ufOrigem === ufDestino ? 5102 : 6102`.

No golden, `ufDestino = SC` (metadata) e `ufOrigem = SP` (fallback). Mas o `business` 98 tem
`business_locations.state = SP` — então **com a fonte corrigida a origem continua `SP`**, e
`SP ≠ SC` continua dando `6102`. A operação era "SP"→SC, nunca SC→SC; o rótulo *"CLIENTE INTERNO
SC"* descreve o **participante**, não o emitente. `6102` está certo para aquele dado.

O risco de CFOP é real, mas é **latente**, não realizado — e a seção 3 mostra por quê.

## 2. O que o parque real diria (medido em produção, 2026-09-04)

| Medida | Valor |
|---|---|
| `business` em prod | 88 |
| com ao menos 1 `business_location` | 88 |
| `business_locations` com `state` de 2 letras | 77 de 89 |
| `state` escrito por extenso (`Santa Catarina`, `São Paulo`, `Mato Grosso`, `Minas Gerais`, `espirito santo`) | **12 de 89** |
| business com filiais em UFs diferentes | **1** (biz=5: `Santa Catarina` + `RJ`) |

**A UF mais comum do parque é SC (29), não SP (23).** O fallback `'SP'` erra a maioria.

E a cobertura das duas fontes candidatas não deixa margem para escolha:

| Campo | `business` | `business_locations` |
|---|---|---|
| CNPJ | **88 de 88** | 57 de 89 |
| Inscrição estadual | **88 de 88** | **0 de 89** |
| UF (`state`) | coluna não existe | 77 de 89 |
| CEP | — | 89 de 89 |

`business_locations.inscricao_estadual` está **zerada em 100% do parque**: adotá-la emitiria IE
vazia para todo mundo. Não há disputa de fontes — há uma única combinação viável.

## 3. São três defeitos independentes, não um

O achado tratou tudo como uma cadeia. Medindo, são três, com alcances muito diferentes.
Rodei o gerador real no CT 100 com o insumo **no formato do parque real** (metadata do importador
Delphi, dentro de transação com `rollBack`):

```
|0000|018|0|01012026|31012026|CI TENANT 98 (FICTICIO)|||SP||350000||SP|A|1|
|0005|CI TENANT 98 (FICTICIO)|00000000|NAO INFORMADO||||||
|C170|1|PDV-90101|...|102|5102|...
registros 0150 (participantes) = 0
```

| # | Defeito | Alcance medido | Muda com o conserto da UF? |
|---|---|---|---|
| **D1** | `0000`/`0005` sem CNPJ, IE, UF, CEP e endereço reais | **100%** dos business | **Sim** — é o conserto |
| **D2** | **Zero registros `0150`** e `COD_PART` do `C100` vazio | **100%** do parque real | **Não** — causa distinta |
| **D3** | CFOP interno×interestadual decidido contra UF falsa | **0% hoje**, latente | **Não** hoje; sim quando D2 for resolvido |
| **D4** | **`SUFRAMA` recebe a UF** (campo 12 do `0000`) | **100%** dos business | **Piora** — ver 3.1 |

### 3.1 D4 — o conserto ingênuo da UF *propaga* um erro

Mapeando os 14 campos do `0000` contra o layout v3.1.1, o **campo 12 (`SUFRAMA`)** recebe
`business->state ?? 'SP'` — a **mesma expressão** do campo 8 (`UF`):

| # | Campo | Vem de | Golden |
|---|---|---|---|
| 8 | `UF` | `business->state ?? 'SP'` | `SP` |
| 11 | `IM` | literal vazio | (vazio) |
| **12** | **`SUFRAMA`** | **`business->state ?? 'SP'`** | **`SP`** |

`SUFRAMA` é a inscrição na Superintendência da Zona Franca de Manaus — **numérica, 9 dígitos**, e
deve ficar **vazia** para quem não é beneficiário (que é o caso de 100% do parque). Preencher com
sigla de UF é inválido hoje e continuaria inválido depois: corrigir só a origem da UF trocaria
`SP` por `SC` e **manteria o campo errado, agora variando por business**.

É a razão mais forte para o item 2 da ordem não ser "trocar o nome da coluna": ele precisa revisar
o **mapa posicional** do registro, não só a fonte de cada valor.

**D3 é inerte hoje, e é importante entender por quê:** `resolverUfDestino` devolve `ufBusiness`
quando falta `dest_uf`. Como as 168 NF-e reais não têm `dest_uf`, origem e destino são **sempre o
mesmo valor**, e o CFOP sai `5102` — por acidente, o valor correto para o Martinho. Corrigir só a
UF mantém `5102`. O CFOP passa a errar **nas duas direções** no dia em que `dest_uf` for
preenchido com origem ainda falsa (SC→SC viraria `6102`; SC→SP viraria `5102`).

**D2 é o mais grave e não estava catalogado.** O `COD_PART` do `C100` é `''` **hardcoded** — o
vínculo com o `0150` não existe nem quando há participantes (o golden tem 2 registros `0150` e os
dois `C100` com `COD_PART` vazio). Um EFD de saídas sem participante vinculado é rejeitado pelo
PVA antes de qualquer discussão de CFOP.

## 4. Quem já gerou arquivo — impacto retroativo

**Nenhum, até onde é medível.** O arquivo não é persistido (o controller devolve *streaming* de
download; não há tabela de arquivos gerados), então o único rastro é `Log::info('Fiscal.sped.gerar ok')`.

- `grep` no `laravel.log` de produção: **zero ocorrências** (controle positivo rodado — o grep
  funciona e acha outros termos; `rc=1` é ausência real, não falha de execução).
- Janela coberta pelo log: **2026-06-21 → 2026-09-04**. Antes disso o log foi rotacionado — a
  janela 2026-05-20 (nascimento do gerador) → 2026-06-21 **não é medível hoje**. A trava existe
  desde 2026-05-25, o que reduz a exposição dessa janela mas não a zera.
- `config('fiscal.sped_simples_only_lock')` em produção: **`true`**.
- Único business com NF-e autorizada: **biz=164 (Martinho), 168 notas, UF = SC**, importadas do
  Delphi entre 2024-12 e 2026-04.

**Conclusão:** não há arquivo entregue ao Fisco para corrigir. O que existe é um gerador que
produziria arquivo inválido se a trava caísse hoje.

## 5. Proposta — fonte de verdade e ordem de conserto

### 5.1 A fonte de verdade (o ponto que precisa da sua decisão)

| Campo do registro | Fonte proposta | Cobertura |
|---|---|---|
| CNPJ (`0000`) | `business.cnpj` | 88/88 |
| Inscrição estadual (`0000`) | `business.ie` | 88/88 |
| Razão social (`0000`/`0005`) | `business.razao_social`, com `business.name` de fallback | 88/88 |
| **UF (`0000`)** | `business_locations.state` **normalizada** (por extenso → sigla) | 77/89 diretas + 12 recuperáveis |
| CEP, logradouro, município (`0005`) | `business_locations` (`zip_code`, `landmark`, `city`) | 89/89 |
| `COD_MUN` IBGE | `business.cidade_id` → tabela `cidades` | **11/88** — cobertura baixa, ver 5.3 |

**Reusar, não recriar:** `NfeService::resolverUF()` já lê `business_locations.state` e é o caminho
que a NF-e usa hoje. Mas ele tem o **mesmo defeito em menor grau**: valida com
`preg_match('/^[A-Z]{2}$/')` e portanto **rejeita as 12 UFs por extenso**, caindo no mesmo fallback
`'SP'`. Extrair e normalizar esse resolvedor conserta NF-e e SPED de uma vez — e é por isso que a
correção pertence ao **NfeBrasil**, com o Fiscal consumindo, não a um resolvedor novo no Fiscal.

### 5.2 Matriz × local de emissão

Você perguntou qual UF vale. Medido: **1 business em 88** tem filiais em UFs diferentes (biz=5),
e ele **não emite NF-e**. Pelo Guia Prático, o EFD é **por estabelecimento** — o certo é a UF do
estabelecimento emissor, não a da matriz. Proposta: resolver pela **location do estabelecimento**,
com a primeira location como padrão enquanto o vínculo NF-e→location não existir, e **falhar alto**
(exceção, não fallback silencioso) quando o business tiver mais de uma UF. Isso mantém o caso real
correto e transforma o caso ambíguo em erro visível em vez de arquivo errado.

### 5.3 O fallback `'SP'` deve morrer

Não é conserto de nome de coluna: enquanto existir `?? 'SP'`, todo dado ausente vira um arquivo
sintaticamente válido e materialmente falso. Proposta: **sem UF resolvível, o gerador lança
exceção** — a trava protege o download, mas não deve proteger um valor inventado. Mesma regra para
`COD_MUN`: com `cidade_id` em 11/88, o placeholder `UF+0000` cobre a maioria e é aceito em
homologação, mas **rejeitado em produção** para business com endereço preenchido — então ele
também precisa ser erro explícito, não silêncio.

### 5.4 Ordem proposta (cada item é um PR separado, e nenhum toca valor)

1. **Normalizador de UF no NfeBrasil** + `resolverUF` passando a usá-lo. Conserta os 12 por extenso
   na NF-e também. Pest com as 5 grafias reais medidas.
2. **`0000`/`0005` lendo a fonte certa**, fallback `'SP'` removido **e mapa posicional conferido
   campo a campo contra o layout v3.1.1** (é onde D4 cai — `SUFRAMA` volta a ser vazio). Golden
   regerado (nunca editado).
3. **D2 — participantes**: `0150` + `COD_PART` do `C100`. É o que decide aceitação no PVA.
4. **D3 — CFOP**: só faz sentido depois de 3, e aí sim vira mudança que mexe em valor fiscal.

Os itens 1 e 2 mudam **identificação**, não cálculo. O item 4 mexe em CFOP e cai inteiro na
regra-mestre de `proibicoes.md` (dupla prova por dois caminhos + tabela antes→depois + sua
aprovação antes de aplicar).

## 6. A trava pode ser liberada?

**Não com os itens 1 e 2.** Eles corrigem o cabeçalho, mas D2 sozinho já produz arquivo que o PVA
rejeita. Proposta de critério de liberação, para você aceitar ou apertar:

1. D1, D2, D3 e D4 fechados;
2. **smoke real no PVA-EFD** com arquivo do biz=164 (é o anti-hook que o `Sped.charter.md` já
   exige — *"não declarar o gerador validado sem smoke no PVA-EFD"*);
3. golden regerado e versionado;
4. `US-FISCAL-020` reaberta ou emendada — ela está `status: done` afirmando ter eliminado o risco
   "Larissa vendendo SC→RS", e o mecanismo existe mas foi entregue com o insumo falso. **A US está
   verdadeira sobre o código e falsa sobre o comportamento.** Isso é decisão sua: reabrir, ou
   emendar registrando que a Fase 1 entregou a comparação sem a origem.

Enquanto isso, a trava fica como está — `true`, fail-secure, com bypass de superadmin.

## 7. O que decide você

| # | Decisão | Recomendação |
|---|---|---|
| 1 | Fonte do CNPJ/IE: `business` (canon BR) | **Sim** — a alternativa tem IE zerada em 100% |
| 2 | UF: `business_locations.state` normalizada, resolvida no NfeBrasil e consumida pelo Fiscal | **Sim** — conserta NF-e junto |
| 3 | Multi-UF: falhar alto em vez de escolher em silêncio | **Sim** — 1 caso em 88, nenhum emitindo |
| 4 | Matar o fallback `'SP'` e o `COD_MUN` placeholder (virar exceção) | **Sim** |
| 5 | Ordem 1→2→3→4, com o item 4 sob a regra-mestre de valor | **Sim** |
| 6 | `US-FISCAL-020`: reabrir ou emendar | **Emendar** — o código entregue existe; o que falhou foi o insumo |
| 7 | Trava só cai após PVA real | **Sim** |

Cabe uma **US-FISCAL-025** para os itens 1-3 (identificação, sem valor) e o item 4 como US própria
sob a regra-mestre. Nenhuma das duas existe hoje: o SPEC vai até `US-FISCAL-024`.

## Anexo — como reproduzir

Schema no CT 100 (o comando completo está no corpo do PR desta sessão):

```
Schema::hasColumn('business', 'state')              -> NAO
Schema::hasColumn('business', 'tax_number')         -> NAO
Schema::hasColumn('business', 'inscricao_estadual') -> NAO
Schema::hasColumn('business', 'cnpj')               -> SIM
Schema::hasColumn('business', 'ie')                 -> SIM
```

A regra do CFOP e o `COD_PART` vazio, direto da árvore:

```
git show origin/main:Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php | sed -n '/fallbackSimplesNacional/,/^    }/p'
git show origin/main:Modules/Fiscal/Services/SpedIcmsIpiGeneratorService.php | sed -n '/registroC100/,/^    }/p'
```

A sonda que gerou o bloco da seção 3 semeia duas `nfe_emissoes` com metadata do importador Delphi
dentro de `beginTransaction`/`rollBack`, chama `gerar(98, 2026, 1)` e imprime `0000`, `0005`,
`C170`, `C190` e a contagem de `0150`. Nada persistiu.

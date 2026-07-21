# Grade dos mapas de vínculos "arquivo → doc obrigatório" — trava de frescor (cross-módulo)

> **O que é isto.** O **roteiro de generalização** da trava de frescor de docs. O piloto
> ([Produto/MAPA-VINCULOS-trava-frescor.md](../Produto/MAPA-VINCULOS-trava-frescor.md)) provou o método num módulo só.
> Esta grade responde **"quais mapas ainda faltam, em que ordem, e com que esforço"** — um mapa por módulo, cada um
> replicando as 7 seções do piloto. Desenhada em
> [2026-07-20-trava-frescor-docs-produto.md](../../sessions/2026-07-20-trava-frescor-docs-produto.md).
>
> **Base:** inventário sobre `origin/main` (o checkout atual é stale). Contagens de telas/charter/casos e presença de
> BRIEFING/SDD vêm de `git ls-tree -r origin/main`.
>
> **Status:** DRAFT de revisão — Felipe [F] + Wagner [W] conferem a **ordem** e o **escopo v1** antes de eu montar
> os mapas. **Nada de enforcement aqui**; cada mapa é artefato de revisão, e a trava (código) entra por **PR pra `main`**.
>
> **Escopo da trava** (decisão da sessão): os **3 docs frágeis** — **SDD**, **BRIEFING**, **casos**. Charter + teste +
> catálogo já têm mecânica viva (gate/auto-geração) e entram como contexto `[já-coberto]` em cada mapa.

---

## 1. Achado que muda a forma dos mapas (ler antes da grade)

O piloto do Produto tem **os 3 docs frágeis presentes**. Varrendo `origin/main`, isso **não** se repete:

1. **BRIEFING existe em 100% dos módulos** (é a política "BRIEFING zero-órfãos"). → A regra `[trava]` **BRIEFING**
   é sempre aplicável. É o alvo universal da trava.
2. **SDD só existe no Produto** (1 de 41). → A **Classe C** do piloto ("backend mexido → SDD obrigatório") **nasce
   dormente** em todos os outros módulos. Nos mapas não-Produto, a v1 da trava vincula **BRIEFING (+ casos onde existe)**;
   o gatilho→SDD só liga quando o módulo ganhar um SDD. Registrar isso em cada mapa evita exigir um doc que não existe.
3. **`casos.md` é assimétrico** (existe por-tela, não por-módulo). Só alguns módulos têm alvos vivos — coluna "casos"
   abaixo. Regra herdada do piloto: **`update-if-exists`**, nunca `force-create` (Q2).
4. **Nome da pasta `Pages/` ≠ pasta `memory/requisitos/`** em 6 casos (`Nfse`→`NFSe`, `ads`→`ADS`, `governance`→
   `Governance`, `kb`→`KB`, `superadmin`→`Superadmin`, `team-mcp`→`TeamMcp`). A config YAML de cada mapa **precisa do
   par explícito** senão o glub aponta pra pasta errada.

**Consequência prática:** o mapa médio não-Produto é **mais barato** que o piloto — sem Classe C (SDD) ativa e, em
metade dos casos, sem Classe A-casos. O trabalho pesado é o **inventário exaustivo + âncoras zero-órfãos** por módulo.

---

## 2. A grade (módulos com telas Inertia → mapa pendente)

Colunas: **telas** = `.tsx`; **cha** = charters (`[já-coberto]`); **casos** = alvos por-tela vivos (`update-if-exists`);
**BR/SDD** = docs frágeis por-módulo presentes; **mapa** = status do `MAPA-VINCULOS` do módulo.

`⏳` = pendente · `✅` = feito · `—` = ausente/dormente.

### Onda 0 — piloto (feito)
| Módulo | telas | cha | casos | BR | SDD | mapa |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| [Produto](../Produto/) | 8 | 8 | 2 | ✅ | ✅ | [✅ piloto](../Produto/MAPA-VINCULOS-trava-frescor.md) |

### Onda 1 — P0 · core de receita / registro-mãe / fiscal (maior ROI)
| Módulo | telas | cha | casos | BR | SDD | mapa |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| [Financeiro](../Financeiro/) · [Pages](../../../resources/js/Pages/Financeiro/) | 60 | 21 | 5 | ✅ | — | ⏳ |
| [Sells](../Sells/) · [Pages](../../../resources/js/Pages/Sells/) | 43 | 8 | 2 | ✅ | — | ⏳ |
| [Cliente](../Cliente/) · [Pages](../../../resources/js/Pages/Cliente/) | 38 | 7 | 7 | ✅ | — | ⏳ |
| [Fiscal](../Fiscal/) · [Pages](../../../resources/js/Pages/Fiscal/) | 19 | 7 | 7 | ✅ | — | ⏳ |
| [NfeBrasil](../NfeBrasil/) · [Pages](../../../resources/js/Pages/NfeBrasil/) | 9 | 6 | 0 | ✅ | — | ⏳ |

### Onda 2 — P1 · verticais em produção + operação de cliente
| Módulo | telas | cha | casos | BR | SDD | mapa |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| [OficinaAuto](../OficinaAuto/) · [Pages](../../../resources/js/Pages/OficinaAuto/) | 30 | 9 | 4 | ✅ | — | ⏳ |
| [Ponto](../Ponto/) · [Pages](../../../resources/js/Pages/Ponto/) | 26 | 20 | 0 | ✅ | — | ⏳ |
| [Atendimento](../Atendimento/) · [Pages](../../../resources/js/Pages/Atendimento/) | 26 | 8 | 0 | ✅ | — | ⏳ |
| [Whatsapp](../Whatsapp/) · [Pages](../../../resources/js/Pages/Whatsapp/) | 15 | 3 | 1 | ✅ | — | ⏳ |
| [Repair](../Repair/) · [Pages](../../../resources/js/Pages/Repair/) | 13 | 13 | 0 | ✅ | — | ⏳ |
| [RecurringBilling](../RecurringBilling/) · [Pages](../../../resources/js/Pages/RecurringBilling/) | 13 | 6 | 0 | ✅ | — | ⏳ |

### Onda 3 — P2 · IA / plataforma / governança / admin
| Módulo | telas | cha | casos | BR | SDD | mapa |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| [Admin](../Admin/) · [Pages](../../../resources/js/Pages/Admin/) | 33 | 8 | 0 | ✅ | — | ⏳ |
| [ADS](../ADS/) · [Pages](../../../resources/js/Pages/ads/) | 19 | 19 | 0 | ✅ | — | ⏳ |
| [Jana](../Jana/) · [Pages](../../../resources/js/Pages/Jana/) | 18 | 11 | 1 | ✅ | — | ⏳ |
| [KB](../KB/) · [Pages](../../../resources/js/Pages/kb/) | 16 | 6 | 1 | ✅ | — | ⏳ |
| [TeamMcp](../TeamMcp/) · [Pages](../../../resources/js/Pages/team-mcp/) | 15 | 5 | 2 | ✅ | — | ⏳ |
| [Essentials](../Essentials/) · [Pages](../../../resources/js/Pages/Essentials/) | 13 | 13 | 0 | ✅ | — | ⏳ |
| [ProjectMgmt](../ProjectMgmt/) · [Pages](../../../resources/js/Pages/ProjectMgmt/) | 10 | 9 | 0 | ✅ | — | ⏳ |
| [Governance](../Governance/) · [Pages](../../../resources/js/Pages/governance/) | 7 | 7 | 1 | ✅ | — | ⏳ |

### Onda 4 — P3 · cauda de baixa superfície Inertia
| Módulo | telas | cha | casos | BR | SDD | mapa |
|---|:-:|:-:|:-:|:-:|:-:|:-:|
| [Site](../Site/) · [Pages](../../../resources/js/Pages/Site/) | 7 | 7 | 0 | ✅ | — | ⏳ |
| [Purchase](../Purchase/) · [Pages](../../../resources/js/Pages/Purchase/) | 6 | 4 | 0 | ✅ | — | ⏳ |
| [MemCofre](../MemCofre/) · [Pages](../../../resources/js/Pages/MemCofre/) | 6 | 6 | 0 | ✅ | — | ⏳ |
| [ConsultaOs](../ConsultaOs/) · [Pages](../../../resources/js/Pages/ConsultaOs/) | 5 | 1 | 0 | ✅ | — | ⏳ |
| [Compras](../Compras/) · [Pages](../../../resources/js/Pages/Compras/) | 4 | 1 | 0 | ✅ | — | ⏳ |
| [Nfse](../NFSe/) · [Pages](../../../resources/js/Pages/Nfse/) | 3 | 3 | 0 | ✅ | — | ⏳ |
| [Suporte](../Suporte/) · [Pages](../../../resources/js/Pages/Suporte/) | 2 | 2 | 2 | ✅ | — | ⏳ |
| [Auditoria](../Auditoria/) · [Pages](../../../resources/js/Pages/Auditoria/) | 2 | 2 | 0 | ✅ | — | ⏳ |
| [StockAdjustment](../StockAdjustment/) · [Pages](../../../resources/js/Pages/StockAdjustment/) | 2 | 2 | 0 | ✅ | — | ⏳ |
| [StockTransfer](../StockTransfer/) · [Pages](../../../resources/js/Pages/StockTransfer/) | 2 | 2 | 0 | ✅ | — | ⏳ |
| [Superadmin](../Superadmin/) · [Pages](../../../resources/js/Pages/superadmin/) | 2 | 2 | 0 | ✅ | — | ⏳ |
| [Manufacturing](../Manufacturing/) · [Pages](../../../resources/js/Pages/Manufacturing/) | 1 | 1 | 0 | ✅ | — | ⏳ |
| [ComunicacaoVisual](../ComunicacaoVisual/) · [Pages](../../../resources/js/Pages/ComunicacaoVisual/) | 1 | 1 | 0 | ✅ | — | ⏳ |
| [Vestuario](../Vestuario/) · [Pages](../../../resources/js/Pages/Vestuario/) | 1 | 1 | 0 | ✅ | — | ⏳ |
| [Tarefas](../Tarefas/) · [Pages](../../../resources/js/Pages/Tarefas/) | 1 | 1 | 0 | ✅ | — | ⏳ |
| [Modules](../Modules/) · [Pages](../../../resources/js/Pages/Modules/) | 1 | 1 | 0 | ✅ | — | ⏳ |

**Total: 41 módulos com telas Inertia → 1 feito (Produto) + 35 em escopo pendentes + 5 fora de escopo (§3).**

---

## 3. Fora de escopo v1 (telas Inertia, mas sem lar de docs frágeis)

Não recebem mapa na v1 — não têm pasta `memory/requisitos/<Mod>/` própria (ou são exemptos). Reavaliar caso ganhem docs.

| Pages/ | telas | Por quê fora |
|---|:-:|---|
| [_Showcase](../../../resources/js/Pages/_Showcase/) | 2 | Exempto — mesma lista do [block-mwart-violation.mjs](../../../.claude/hooks/block-mwart-violation.mjs) |
| [Settings](../../../resources/js/Pages/Settings/) | 7 | Config transversal — sem BRIEFING/SDD próprio |
| [TransactionPayment](../../../resources/js/Pages/TransactionPayment/) | 3 | Subsome em [Financeiro](../Financeiro/) — vínculo pertence ao mapa do Financeiro |
| [Home](../../../resources/js/Pages/Home/) | 1 | Landing — sem pasta de docs |
| [User](../../../resources/js/Pages/User/) | 1 | Sem BRIEFING próprio |

---

## 4. Ordem de execução recomendada

Critério: **superfície de telas × criticidade de negócio**. Alto volume + receita/compliance = mais drift, mais ROI.

1. **Onda 1 (P0)** primeiro — Sells/Financeiro/Cliente concentram o volume (ROTA LIVRE 99% em Sells/Produto/Financeiro);
   Fiscal/NfeBrasil são compliance. É onde "papel na geladeira" custa mais caro.
2. **Onda 2 (P1)** — verticais em prod (OficinaAuto/Repair) + operação (Ponto/Atendimento/Whatsapp/RecurringBilling).
3. **Onda 3 (P2)** — plataforma/IA/gov, alta contagem de charter mas menos exposição a cliente pagante.
4. **Onda 4 (P3)** — cauda; barata, faz por lote ao fim.

**Gate de entrada (não pular):** só disparo a Onda 1 depois que [W] fechar as **3 pendências do piloto** — palavra de
escape, Q3 (Blade legacy entra na v1?), Q-noise (super-disparo de BRIEFING). Sem isso, cada mapa herda o mesmo
`<PENDENTE-WAGNER>` e retrabalha. Ver [pendências do piloto](../Produto/MAPA-VINCULOS-trava-frescor.md#5-pendências-pra-fechar-antes-de-codar-a-trava).

**Tamanho relativo por mapa** (não horas — superfície): L = Onda 1-2 top (Financeiro/Sells/Cliente/OficinaAuto/Admin/Ponto);
M = 10-20 telas; S = cauda ≤7. O custo real não é as telas — é o **inventário exaustivo do backend + âncoras
zero-órfãos** (parte que o piloto levou ~mais tempo).

---

## 5. Contrato de cada mapa (pra ficarem idênticos ao piloto)

Todo `MAPA-VINCULOS-trava-frescor.md` de módulo replica as **7 seções** do piloto:
inventário sobre `origin/main` → o mapa (Classes A-F) → resolução de questões → semente YAML → pendências →
varredura de completude → **âncoras zero-órfãos**. Diferenças herdadas do §1:

- **Classe C (backend→SDD)** entra marcada `[dormente]` até o módulo ter SDD (todos exceto Produto hoje).
- **Classe A-casos / B** só lista alvos onde `casos.md` **já existe** (`update-if-exists`).
- **YAML** carrega o par `Pages/<pasta> ↔ requisitos/<Pasta>` explícito quando divergem (§1.4).
- **Classe F (rotas)** exempta por padrão, como no piloto.
- **Módulos que consomem Produto** (Manufacturing, ProductCatalogue, Repair-produção…) mantêm fresco **o doc do
  próprio módulo**, não o do Produto — o "raio de quebra cross-módulo" segue concern separado (§6 do piloto), não
  frescor.

---

## 6. Âncoras (zero-órfãos)

Piloto: [Produto/MAPA-VINCULOS-trava-frescor.md](../Produto/MAPA-VINCULOS-trava-frescor.md) ·
Sessão de design: [2026-07-20-trava-frescor-docs-produto.md](../../sessions/2026-07-20-trava-frescor-docs-produto.md) ·
Hook exempção referência: [block-mwart-violation.mjs](../../../.claude/hooks/block-mwart-violation.mjs).

Pastas de docs por módulo em escopo: [Financeiro](../Financeiro/) · [Sells](../Sells/) · [Cliente](../Cliente/) ·
[Fiscal](../Fiscal/) · [NfeBrasil](../NfeBrasil/) · [OficinaAuto](../OficinaAuto/) · [Ponto](../Ponto/) ·
[Atendimento](../Atendimento/) · [Whatsapp](../Whatsapp/) · [Repair](../Repair/) · [RecurringBilling](../RecurringBilling/) ·
[Admin](../Admin/) · [ADS](../ADS/) · [Jana](../Jana/) · [KB](../KB/) · [TeamMcp](../TeamMcp/) · [Essentials](../Essentials/) ·
[ProjectMgmt](../ProjectMgmt/) · [Governance](../Governance/) · [Site](../Site/) · [Purchase](../Purchase/) ·
[MemCofre](../MemCofre/) · [ConsultaOs](../ConsultaOs/) · [Compras](../Compras/) · [NFSe](../NFSe/) · [Suporte](../Suporte/) ·
[Auditoria](../Auditoria/) · [StockAdjustment](../StockAdjustment/) · [StockTransfer](../StockTransfer/) ·
[Superadmin](../Superadmin/) · [Manufacturing](../Manufacturing/) · [ComunicacaoVisual](../ComunicacaoVisual/) ·
[Vestuario](../Vestuario/) · [Tarefas](../Tarefas/) · [Modules](../Modules/).

---

_DRAFT append-only. Autor: Claude (Opus 4.8) + Felipe [F]. Base: `origin/main`. Sem PII. Sem mudança de código._

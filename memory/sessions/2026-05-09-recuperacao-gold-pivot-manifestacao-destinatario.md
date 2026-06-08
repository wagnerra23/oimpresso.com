---
type: session-log
date: 2026-05-09
agent: claude-opus-4-7
participantes:
  - W
contexto: cycle-03-em-curso
related:
  - memory/decisions/0115-recuperacao-cliente-gold-via-bundle-oimpresso.md
  - memory/decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md
  - memory/requisitos/NfeBrasil/SPEC.md
  - memory/requisitos/Officeimpresso/RUNBOOK-recuperacao-on-prem.md
  - memory/requisitos/Officeimpresso/PROPOSTA-COMERCIAL-vs-mubsys.md
duracao_aprox: ~90min
---

# Sessão 2026-05-09 — Recuperação Gold + pivot manifestação destinatário

## Resumo executivo

Sessão de discovery comercial-técnico que entrou pedindo MDF-e pra cliente Gold Comunicação Visual e saiu com **2 ADRs canônicas + pacote de 12 tasks + runbook on-prem + template proposta vs Mubsys**, depois de 2 pivots de escopo. Tasks divididas: 6 ativas (manifestação destinatário), 6 dormentes (emissão NF-e 55, guardadas inativas por orientação Wagner).

## Cronologia

### 1. Pedido inicial: "MDF-e como módulo novo"

Wagner pediu implementação de MDF-e (Manifesto Eletrônico de Documentos Fiscais — modelo 58, transporte) pra cliente novo, com hipótese de criar módulo separado.

**Investigação revelou:**
- README NfeBrasil já lista `MdfeController` em "Logística" (Onda 6)
- Roadmap M9 prevê MDF-e + CT-e + SPED
- CAPTERRA-FICHA: MDF-e mapeado como P2
- Pricing Plano Enterprise R$ [redacted Tier 0]/mês já cobre MDF-e
- Lib `nfephp-org/sped-mdfe` (irmã da `sped-nfe` em uso)

**Recomendação dada:** NÃO criar módulo separado — é Fase 6 do `Modules/NfeBrasil` já planejada. Pedi qualificação do cliente (ADR 0105 cliente como sinal qualificado).

### 2. Cliente revelado: Gold Comunicação Visual em fuga pra Mubsys

Wagner trouxe contexto crítico:
- Cliente roda **oimpresso Laravel on-prem** em versão antiga (não Delphi WR Comercial)
- Está migrando pra **Mubsys** (concorrente vertical já mapeado em `comparativos/oimpresso_vs_concorrentes_capterra_2026_04_25.md`)
- Quer "recuperar nota 55" (interpretado: NF-e modelo 55)
- Tem caminhão próprio
- Wagner autorizou: "Pode fazer legal a ideia faça"

**Estratégia montada:**
- ADR 0115 — recuperação Gold via bundle oimpresso + NfeBrasil Fase 2 (NF-e 55 já entregue)
- 7 tasks US-NFE-042..048 cobrindo discovery → proposta → upgrade → config → smoke → treinamento → refinar runbook
- Runbook on-prem stub em `memory/requisitos/Officeimpresso/`
- Template proposta vs Mubsys com diferenciais Capterra

### 3. Pivot crítico: escopo errado, é manifestação destinatário

Wagner trouxe o caso de uso real:

> _"ela recebe um xml de nfe e tem que tirar uma nota DFe e tem prazo para informar que foi transportado"_

**Tradução técnica:** Gold é **destinatária** recebendo NF-e de fornecedores, não emissora. Precisa **manifestar** sobre NF-e recebidas (NT 2014.002 — eventos 210/220/230/240) dentro do prazo SEFAZ (180 dias pra Confirmação 220).

**Caso de uso completo:**
1. Fornecedor emite NF-e contra CNPJ Gold
2. Gold consulta SEFAZ via NSU (Distribuição DFe) → baixa XML
3. Caminhão Gold busca a carga no fornecedor
4. Gold manifesta Confirmação 220 → SEFAZ registra recebimento
5. Tudo isso em até 180d corridos

**Descoberta forte:** existe base de código legada UltimatePOS órfã:
- `app/Manifesto.php` (model)
- `app/ItemDfe.php` (model)
- `app/ManifestoLimite.php` (model)
- `app/Services/DFeService.php` (service)
- `app/Http/Controllers/ManifestoController.php` (controller — sem rotas em `routes/web.php`)

Feature foi implementada em versão antiga, perdeu rotas em algum upgrade UltimatePOS. **Reaproveitar > codar do zero.**

### 4. Pivot ordenado pelo Wagner: "guarde inativo"

Apresentei pivot com 3 opções de tratamento das tasks erradas. Wagner respondeu:

> _"humm etendi mais não abandona o projeto. guarde inativo"_

**Interpretação:** as 6 tasks de emissão (043-048) NÃO devem ser canceladas — ficam dormentes aguardando o discovery (US-NFE-042) confirmar se Gold também emite NF-e 55 (vendas B2B dela). Se sim, reativa. Se não, ficam blocked permanente.

Pattern aplicado: `status: blocked` + critério de reativação documentado no SPEC.md + ADR 0116.

### 5. Entrega final

| Artefato | Caminho |
|---|---|
| ADR 0115 (estratégia, aceito) | `memory/decisions/0115-recuperacao-cliente-gold-via-bundle-oimpresso.md` |
| ADR 0116 (pivot escopo, aceito, emenda 0115) | `memory/decisions/0116-pivot-gold-manifestacao-destinatario-emenda-0115.md` |
| Runbook on-prem (com Fase 4-Manifestação) | `memory/requisitos/Officeimpresso/RUNBOOK-recuperacao-on-prem.md` |
| Template proposta vs Mubsys (+ Apêndice A manifestação) | `memory/requisitos/Officeimpresso/PROPOSTA-COMERCIAL-vs-mubsys.md` |
| 12 tasks em SPEC.md NfeBrasil seção 7 | `memory/requisitos/NfeBrasil/SPEC.md` |

**Tasks ativas (manifestação — 19h):**
- US-NFE-042 Discovery
- US-NFE-049 Migrar legado pra Modules/NfeBrasil
- US-NFE-050 ManifestacaoService (eventos 210/220/230/240)
- US-NFE-051 DistribuicaoDfeService + Job NSU diário
- US-NFE-052 UI manifestar + countdown 180d + bulk
- US-NFE-053 Smoke SEFAZ-SP eventos 210/220

**Tasks dormentes (emissão — `status: blocked`):**
- US-NFE-043..048 (proposta → cutover NF-e 55)

## Aprendizados meta (gravar pra próximas sessões)

### 1. "Manifesto" é ambíguo — sempre desambiguar

Cliente/dev brasileiro fala "manifesto" referindo-se a **2 coisas distintas**:
- **MDF-e modelo 58** (Manifesto Eletrônico de Documentos Fiscais — emitente da carga, transporte interestadual/grandes valores)
- **Manifestação do Destinatário** (4 eventos NT 2014.002 — destinatário responde sobre NF-e recebida; NÃO é nota nova, é EVENTO)

**Ação:** quando cliente disser "manifesto" ou "manifestar", perguntar **caso de uso completo** antes de assumir escopo. Custou 1 pacote inteiro (7 tasks de emissão) montado em escopo errado nesta sessão.

### 2. Discovery PRIMEIRO, plano técnico DEPOIS

Wagner autorizou genericamente ("pode fazer") e eu corri pra montar 7 tasks com escopo presumido (emissão NF-e 55). Erro: assumir que "recuperar nota 55" = "emitir NF-e 55" sem confirmar fluxo (recebe vs emite).

Auto-mem `user_profile.md` já dizia: _"confirme escopo com perguntas curtas antes de implementar massivamente"_. Não respeitei.

**Padrão correto:**
1. Resumo entendimento em 2 linhas
2. **3 perguntas qualificantes** ANTES de criar tasks/ADR
3. Apresentar plano após confirmação

### 3. ADR append-only + emendas funcionam

ADR 0115 não foi editada quando o escopo virou. Foi **emendada** por ADR 0116 — pattern correto pra constituição append-only. Histórico preservado, decisão atualizada, rastro de governança limpo.

### 4. "Guardar inativo" como pattern de blocked com reativação condicional

Wagner ensinou um pattern novo: ao invés de cancelar tasks com escopo errado, marcar `status: blocked` + critério de reativação documentado. Mantém possibilidade futura sem ruído. Aplica-se quando:
- Escopo pivotou mas pode voltar a ser relevante
- Discovery futuro pode validar parte do escopo dormente
- Custo de re-criar tasks > custo de manter blocked

### 5. Code legado órfão tem valor de resgate

Descobri 5 arquivos legados UltimatePOS (Manifesto/ItemDfe/DFeService/ManifestoLimite/ManifestoController) sem rotas em `routes/web.php` — feature implementada que perdeu trilho em upgrade. Antes de codar do zero: **`Glob` + `Grep` por modelos relacionados ao domínio**. Achei 5 arquivos que economizam ~4-6h dev.

### 6. Diferencial real vs Mubsys descoberto

**Bulk Confirmar** (50 NFe recebidas → 1 clique → 50 eventos 220 SEFAZ) é diferencial cego dos concorrentes verticais. Mubsys/Bling/Omie cobrem emissão mas deixam manifestação manual no portal SEFAZ. Material de proposta:
- ~11h/mês operação manual hoje
- ~2min/mês com oimpresso bulk
- ≈ R$ [redacted Tier 0]/mês economia operacional

Aplica-se a TODOS os 49 dormentes (Trilha 1 do roadmap) — todo destinatário PJ recebe NF-e e tem prazo legal pra manifestar.

## Pendências / próximos passos

1. **Wagner commita + pusha** pacote completo:
   ```
   git add memory/decisions/0115-* memory/decisions/0116-* memory/requisitos/NfeBrasil/SPEC.md memory/requisitos/Officeimpresso/ memory/sessions/2026-05-09-*
   git commit -m "feat(officeimpresso): ADR 0115+0116 caso Gold — recuperação via bundle + pivot manifestação destinatário"
   git push
   ```
2. **Definir pricing manifestação avulsa** + on-prem one-time (placeholders no template)
3. **Wagner contata Gold** pra discovery US-NFE-042 — única task bloqueante de tudo
4. **Auto-mem cliente Gold** (CNPJ, contato, versão) — Wagner grava local; Claude não pode (proibição auto-mem privada [ADR 0061](../decisions/0061-conhecimento-canonico-git-mcp-zero-automem.md))
5. **Cycle 04** — quando Cycle 03 fechar (14/05), incluir sprint `Gold-Reativacao` formal

## Estado pós-sessão

- ✅ Cycle 03 (smoke biz=1 SEFAZ-SC NFC-e) **NÃO interrompido** — 5d restantes intactos
- ✅ ADRs 0115 + 0116 aceitas
- ✅ 12 tasks em SPEC NfeBrasil §7 (6 ativas + 6 dormentes)
- ✅ Runbook + template prontos pra reuso (Trilha 1 dormentes)
- ⏳ Aguardando: Wagner contatar Gold + push commit pacote
- ⏳ Aguardando: definição pricing on-prem

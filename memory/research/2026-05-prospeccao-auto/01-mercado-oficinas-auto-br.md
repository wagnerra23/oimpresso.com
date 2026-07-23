---
id: research-2026-05-prospeccao-auto-01-mercado-oficinas-auto-br
---

# Mercado de oficinas mecânicas/auto BR — pesquisa de viabilidade

> **Status:** desk research / pesquisa de viabilidade — NÃO compromisso de construir.
> **Data:** 2026-05-09
> **Autor:** Claude (analista de mercado)
> **Escopo:** mapear ICP autorepair como possível **Modules/OficinaAuto** especializado ([ADR 0121](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)). Cliente piloto atual é ROTA LIVRE = **Modules/Vestuario** (loja roupa Gravatal/SC). Outro módulo em construção: **Modules/ComunicacaoVisual** (com expertise herdada de 26 anos WR Sistemas).
> **Decisão a informar:** ativar `Modules/OficinaAuto` (criar módulo vertical especializado) requer 1+ cliente piloto pagante — sinal qualificado pendente.
> **Governança:** ADR 0105 (cliente como sinal qualificado) — só vira backlog/US se cliente real pagar.

---

## 1. Tamanho do mercado

### 1.1 Universo total
- **Sindirepa-Brasil** (Sindicato Nacional da Indústria da Reparação de Veículos) estima **~133 mil oficinas mecânicas formais** ativas no BR. `[validar — Sindirepa publica anuário; última referência pública 2023 indicava ~120-145k]`
- **Sebrae** trata o setor como "Reparação Automotiva" dentro de Serviços e estima frota brasileira em ~46 milhões de veículos (carros + comerciais leves), com idade média subindo (>10 anos), o que aquece serviços de manutenção. `[validar — Sebrae Boletim Setorial Reparação Automotiva]`
- **Receita Federal / RFB CNAE 4520-0/01 (Serviços de manutenção e reparação mecânica de veículos automotores)** captura o núcleo. Variantes 4520-0/02 a /05 cobrem funilaria, elétrica, lavagem etc. Soma estimada das CNAEs auto-reparação: **~150 mil estabelecimentos formais** + **forte cauda informal** (estimativas Sindirepa apontam outras 100-150k informais — fundo de quintal). `[validar com RFB CNAE 2.3]`
- **Mecânicas de moto** (CNAE 4543-9/00): adicionais ~30-50k estabelecimentos formais. `[validar — Abraciclo + Sebrae]`
- **Mecânicas de pesados** (caminhão/ônibus): cauda menor, ~5-10k oficinas especializadas. `[validar — NTC&Logística / Sindipeças]`

### 1.2 ICP-faixa (5-50 funcionários, R$ [redacted Tier 0]k-500k/m, 1-3 elevadores)
- O perfil 5-50 funcionários no setor reparação automotiva **é maioria absoluta** das formais. Sebrae classifica >85% como ME/EPP. `[validar — RAIS/CAGED 2024]`
- Oficinas com 1-3 elevadores e faturamento R$ [redacted Tier 0]-300k/m representam o "miolo" — estimativa **~40-60k estabelecimentos** no BR. `[validar — não há fonte pública direta; cruzar Sindirepa + Receita]`
- Acima disso (centros automotivos médios + concessionárias independentes) caem pra ~10k.

### 1.3 Concentração geográfica
- **SP** concentra 28-32% das oficinas formais (Grande SP + interior). `[validar — Sindirepa-SP]`
- **MG, RJ, RS, PR** somam outros ~35-40%.
- **Sul + Sudeste** = ~70% do universo.
- Cidades médias (50-300k habitantes) no interior SP/MG/PR têm **densidade alta** de oficinas ICP-faixa — provavelmente o sweet spot pra venda de software vertical.

### 1.4 Comparativo de universo
| Setor | Estabelecimentos formais BR | Ordem de magnitude |
|---|---|---|
| Gráficas / comunicação visual | ~5-15k `[validar — ABTG/ABRADI]` | base |
| Oficinas auto (geral, todos portes) | ~133-150k `[Sindirepa]` | **~10-30x maior** |
| Oficinas auto ICP-faixa (5-50 func) | ~40-60k `[estimativa]` | **~5-10x maior que gráfica total** |

**Insight:** mercado de oficinas auto é **ordens de magnitude maior** que gráfica em volume de estabelecimentos. Mesmo capturando 0.1% do universo ICP, são 40-60 clientes potenciais.

---

## 2. Segmentação

| Segmento | Característica | Volume estimado | Aderência oimpresso |
|---|---|---|---|
| **Mecânica geral** | Manutenção preventiva + corretiva, generalista | ~60% do universo (~80k+) | Alta — fluxo OS clássico |
| **Centro automotivo** | Serviço + venda de peças no balcão | ~15% (~20k) | Alta — combina NFC-e (peça) + NFS-e (serviço) |
| **Especialista** (ar-cond, suspensão, câmbio, elétrica, injeção) | Nicho técnico, ticket alto | ~10% (~13k) | Média — fluxo OS mais simples mas exige tabela tempária precisa |
| **Funilaria/pintura** | Sinistro + seguradora + ciclo longo | ~8% (~10k) | Baixa-média — tem integração seguradora (Audatex, Cilia) que oimpresso não tem |
| **Concessionária independente** | Revenda + oficina + F&I | ~3% (~4k) | Baixa — usa DMS proprietário (Linx DMS, Dealernet) |
| **Oficina autorizada** (VW/Fiat/GM/etc) | Vinculada à rede da montadora | ~2% (~3k) | **Nenhuma** — montadora impõe DMS |
| **Mecânica de moto** | Frota leve, ticket menor, volume alto | ~30-50k | Média — fluxo OS mais simples; comunidade mais informal |
| **Mecânica de pesados** | Caminhão/ônibus, frota empresarial B2B | ~5-10k | Média-alta — clientes B2B exigem nota fiscal e contrato |

**Foco recomendado se explorar:** mecânica geral + centro automotivo + especialistas (~110k estabelecimentos) — fluxo OS é o mesmo padrão do `Modules/Repair`.

---

## 3. Personas-decisor

### 3.1 Dono mecânico-operador (tradicional)
- 50+ anos, técnico de formação, "ferramenta na mão"
- Caderno + WhatsApp + planilha Excel ocasional
- **Não** se vê como empresário; vê-se como mecânico
- Resistência alta a software (~70% deste perfil hoje)
- **Não é ICP oimpresso** — custo de educação muito alto

### 3.2 Dono empresário (segunda/terceira geração)
- 30-45 anos, pegou a oficina do pai/sogro
- Tem CNPJ formalizado, contador, PIX, possivelmente Bling/Tiny
- Quer profissionalizar, está no Instagram/WhatsApp Business
- **Reconhece dor de gestão** mas não tem repertório de software vertical
- **ICP perfeito oimpresso** — perfil idêntico ao Larissa (ROTA LIVRE)

### 3.3 Gestor profissional (oficina maior)
- Centro automotivo 20+ funcionários, gerente assalariado contratado
- Já usa ERP (provável Bling/Tiny ou um vertical do nicho)
- Decisão de troca passa por dono + gerente
- **ICP médio** — já tem solução, troca exige diferencial claro

---

## 4. Top 10 dores universais

Validadas via posts em fóruns (Mecânica Online, Reclame Aqui sobre softwares do setor), grupos Facebook ("Donos de Oficina BR", "Mecânicos Profissionais"), conteúdo Sebrae e LinkedIn de fornecedores ERP auto. `[validar com pesquisa primária — entrevista 5-10 donos]`

1. **Orçamento manual em papel/WhatsApp** — cliente pede, mecânico anota, perde, refaz. Sem histórico. Idêntico à dor da gráfica.
2. **Cliente liga a cada 30min "tá pronto?"** — sem status visível da OS, atendente interrompe mecânico.
3. **Não rastreia margem por OS** — peça comprada R$ [redacted Tier 0] vendida R$ [redacted Tier 0] mão-de-obra "cobrada na canetada". Sem visão de lucro real.
4. **Estoque de peças desatualizado** — peça já usada, sistema não baixou; ou comprou peça pro cliente X e usou no Y.
5. **Tabela tempária inconsistente** — preço hora-homem varia de cabeça pra cabeça; mesmo serviço cobrado diferente em 2 OSs.
6. **Garantia não rastreada** — cliente volta reclamando 30 dias depois, ninguém lembra qual peça/serviço foi feito.
7. **Fiscal manual** — contador pede mensalmente notas + extrato, dono compila à mão.
8. **Aprovação de orçamento por WhatsApp sem registro formal** — cliente "aprovou no zap", depois contesta no balcão.
9. **Histórico do veículo perdido** — cliente troca de carro e atendente não tem CRM; cliente volta após 1 ano, mecânico esqueceu o que fez.
10. **Cobrança de pendências** — cliente saiu com o carro "deixa que pago semana que vem", oficina perde rastro.

**Insight crucial:** dores 1, 2, 3, 4, 8, 9, 10 são **idênticas** às dores de gráfica que oimpresso já endereça com `Modules/Repair` + Financeiro + Jana.

---

## 5. Stack atual típico

### 5.1 Universal
- **Caderno + WhatsApp** — 100% das oficinas têm, mesmo as que têm software
- **Planilha Excel/Google Sheets** — controle informal de OS
- **PIX** — pagamento principal hoje (>70% das transações)

### 5.2 ERPs verticais auto (concorrentes diretos se oimpresso pivotar)
| Software | Modelo | Preço estimado/m | Observação |
|---|---|---|---|
| **Mecânico** (Officina) | SaaS | R$ [redacted Tier 0]-299 | Forte em SP, marketing pesado |
| **ManagerOS** | SaaS | R$ [redacted Tier 0]-399 | Inclui app cliente |
| **Auto Manager** | Desktop + cloud | R$ [redacted Tier 0]-250 | Tradicional, base instalada grande |
| **Lokoz** | SaaS moderno | R$ [redacted Tier 0]-249 | Mais recente, UI moderna |
| **Olho Vivo** | Desktop legado | R$ [redacted Tier 0]-180 | Comum em oficinas tradicionais |
| **OficinaMaster** | SaaS | R$ [redacted Tier 0]-249 | Foco em centro automotivo |
| **Carros2** | SaaS | R$ [redacted Tier 0]-199 | Foco em mecânica geral |
| **Sti3** | SaaS | R$ [redacted Tier 0]-349 | Forte em pesados/frota |
| **GP Office** | Desktop+cloud | R$ [redacted Tier 0]-300 | Tradicional |
| **Mais Oficina** | SaaS | R$ [redacted Tier 0]-249 | Mid-market |

`[validar preços — sites consultados em 2024-2025; sujeitos a mudança]`

### 5.3 Genéricos que oficina às vezes adapta
- **Bling, Tiny, Conta Azul** — não têm fluxo OS, só financeiro/fiscal
- **Omie** — médio porte, usado por centros automotivos maiores

### 5.4 Específico do setor (oimpresso não tem hoje)
- **Tabela Tempária** (preço hora-homem por procedimento) — todo software vertical auto tem; é o coração do orçamento
- **Cadastro de veículo por placa + chassi + km** — chave do CRM
- **Catálogo de peças com referência montadora** — Audatex, Molicar, integrações com distribuidores (Bardahl, Aliança Auto)
- **Integração com seguradora** (sinistros — Audatex, Cilia) — só pra funilaria/pintura

---

## 6. ICP refinado pra oimpresso

**Perfil mais alinhado:**
- 5-15 funcionários
- 1-2 elevadores
- Faturamento R$ [redacted Tier 0]-200k/m
- **Dono empresário geração 2** (perfil Larissa) — 30-45 anos, formalizado, no Instagram, usa PIX
- Mecânica geral OU centro automotivo (peça + serviço) OU especialista (ar-cond, elétrica, suspensão)
- **Não** funilaria/sinistro (exige integração seguradora que oimpresso não tem)
- **Não** autorizada de montadora (DMS imposto)

**Localização sweet spot:**
- SP capital + Grande SP (densidade + capacidade de pagamento)
- Interior SP/MG/PR cidades 50-300k habitantes (concorrência menor + ticket OK)
- Sul como expansão natural

**Volume estimado do ICP refinado:** ~**20-30 mil oficinas** no BR. `[estimativa cruzando filtros 1.2 + segmentos 60% mec geral + 15% centro auto]`

---

## 7. Comparação com gráfica

| Dimensão | Gráfica/com. visual | Oficina auto | Insight |
|---|---|---|---|
| Universo formal BR | ~5-15k | ~133-150k | **~10-30x maior** |
| ICP-faixa SMB | ~3-5k | ~20-30k | **~5-10x maior** |
| Ticket médio software/m | R$ [redacted Tier 0]-500 | R$ [redacted Tier 0]-400 | Margem unitária menor mas volume compensa |
| Consciência de software vertical | Baixa | Baixa-média (ERPs auto têm marketing há 15 anos) | Auto tem mercado mais educado |
| Concorrência vertical estabelecida | Baixa (Zênite, Mubisys, Calcgraf — pequenos) | **Alta** (10+ players SaaS ativos) | **Auto é red ocean** |
| Dor universal | OS sem rastreamento | OS sem rastreamento | **Mesma dor** |
| Fluxo OS multi-etapa | Sim (criação/aprovação/produção/instalação/entrega) | Sim (recepção/diagnóstico/orçamento/aprovação/peça/mecânico/teste/entrega) | **Mesma estrutura** — `Modules/Repair` cabe |
| Notas fiscais | NFC-e (produto), NFS-e (serviço de instalação) | NFC-e (peça) + NFS-e (serviço) | oimpresso já tem NFC-e (US-NFE-002 fechada biz=1); **NFS-e ainda falta** |
| CRM por cliente | Por CNPJ/CPF | Por CNPJ/CPF + **veículo (placa/chassi)** | Modelo de dados diferente — exige tabela `veiculos` com vinculação |
| Tabela de preços | Lista de produtos + serviços | **Tabela Tempária** (hora-homem por procedimento) | Conceito específico — não tem em gráfica |
| Sazonalidade | Datas comemorativas (eleição, festa junina, Natal) | Antes de viagens (julho, dezembro) + revisão preventiva por km | Sazonalidades diferentes mas previsíveis |
| Garantia | Pouco rastreada | **Muito rastreada** (peça tem garantia formal do fabricante) | Exigiria módulo garantia novo |
| Cliente B2B | Médio (empresas pedem material gráfico) | Alto em pesados/frota; baixo em mec geral | Frota B2B é segmento aparte |
| Integração externa | Quase nenhuma (talvez Asaas) | Catálogo peças (Molicar/Audatex), seguradora, Detran (consulta placa) | Auto tem mais integrações esperadas |
| Estoque crítico | Médio (insumo gráfico) | **Alto** (peças com referência cruzada montadora) | Exige catálogo de peças robusto |

---

## 8. Conclusão

### 8.1 Oportunidade real OU distração?

**Posição:** **oportunidade real, mas com fricção alta de entrada e risco de distração se feita sem foco.** Mercado é 10-30x maior que gráfica, dor é a mesma, fluxo OS é o mesmo, `Modules/Repair` já tem ~70% do esqueleto. Porém é red ocean com 10+ concorrentes SaaS estabelecidos há 10-15 anos com marketing dedicado.

### 8.2 Três razões pra explorar
1. **Universo 10-30x maior** que gráfica — mesmo capturando 0.05% do ICP refinado, são 10-15 clientes pagantes (~R$ [redacted Tier 0]-60k MRR).
2. **`Modules/Repair` já existe** com fluxo OS genérico — gap principal é tabela tempária + cadastro de veículo + NFS-e. Reaproveitamento alto, custo de entrada técnica baixo.
3. **Dores universais idênticas** às de gráfica — diferencial Jana IA + governança formal (Constituição v2 + multi-tenant Tier 0) é igualmente válido. Concorrentes auto **não** têm IA conversacional — janela de diferenciação real.

### 8.3 Três razões pra adiar
1. **Red ocean** — 10+ concorrentes verticais com 10-15 anos de história, base instalada grande, marketing pesado. Vender pra dono mecânico tradicional é caro (CAC alto, ciclo longo).
2. **Faltam peças críticas:** NFS-e (oimpresso só tem NFC-e), tabela tempária, cadastro de veículo, catálogo de peças com referência montadora. Estimativa ~3-6 meses de dev pra paridade básica `[recalibrado ADR 0106 fator 10x: ~3-6 semanas IA-pair]`.
3. **Distrai do foco atual** — ROTA LIVRE (Modules/Vestuario, vestuário Gravatal/SC) é 99% do volume; Modules/ComunicacaoVisual ainda em construção pra 6 saudáveis OfficeImpresso. Adicionar Modules/OficinaAuto antes de fortalecer 2 módulos verticais já em pipeline = dispersão. ADR 0105: cliente sem sinal qualificado não vira backlog.

### 8.4 Sinal qualificado pra começar
**Critério rígido (ADR 0105):**
- **1 dono de oficina ICP refinado (5-15 func, geração 2, SP/Sul, sem ERP vertical hoje OU insatisfeito com atual) interessado em piloto pago.**
- Aceite formal: contrato R$ [redacted Tier 0]-399/m por 6 meses + feedback semanal estruturado.
- Sem isso → permanece como ADR de feature wish, não US ativa.

**Próximo passo concreto SE houver intenção de explorar:**
1. Wagner ou rede pessoal identifica 3-5 candidatos via LinkedIn / grupos Facebook / indicação.
2. Entrevista de 30 min cada (validar dores 1-10 da seção 4 + stack atual + disposição a pagar).
3. Se 1+ aceitar piloto pago → cria SPEC `Modules/Repair` extensão Auto + ADR de pivô.
4. Se nenhum aceitar → arquiva pesquisa, foca em vertical comunicação visual.

**Prazo sugerido pra revisitar se adiar:** **~Q4 2026 / Q1 2027**, após (a) estabilização ROTA LIVRE Modules/Vestuario + 3-5 clientes Modules/ComunicacaoVisual migrados do OfficeImpresso, (b) ADS Universal entregue, (c) Jana memória estado da arte, (d) NFS-e implementada (útil pra Modules/ComunicacaoVisual que faz instalação de fachada — não exclusivo de auto).

---

## Referências a validar

- **Sindirepa-Brasil** — Anuário da Reparação Automotiva (último publicado)
- **Sebrae** — Boletim Setorial Reparação Automotiva
- **Receita Federal** — CNAE 4520-0/01 a /05 + 4543-9/00 (estabelecimentos ativos)
- **Sindipeças** — Anuário Estatístico do Setor de Autopeças
- **Abraciclo** — frota de motos
- **NTC&Logística** — frota de pesados
- Sites dos concorrentes (Mecânico, ManagerOS, Lokoz, Auto Manager, Carros2, Sti3) — preços e features 2025

> Esta pesquisa é desk research — números marcados `[validar]` exigem confirmação primária antes de virar input de decisão custosa.

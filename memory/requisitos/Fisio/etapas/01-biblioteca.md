# Etapa 01 — Biblioteca de exercícios e protocolos

> Módulo Fisio (ADR 0437) · pesquisa feita em 2026-10-17 · fontes na §7.
> Convenção: **[fonte n]** = afirmação com URL na §7; **[estimativa]** = número meu, sem fonte, a validar; **[não confirmado]** = procurei e não achei.
> Observação de método: o orçamento de buscas da rodada acabou antes de eu confirmar preço de diária de videomaker no Brasil, vozes pt-BR específicas dos serviços de TTS e cachê de fisioterapeuta-modelo. Esses itens aparecem como estimativa.

---

## 1. Objetivo e meta de nota

**Hoje: 6. Physitrack: 10. Meta: 9 em 6 meses.**

"9" quer dizer o seguinte para o público de entrada (autônomo de traumato-ortopedia):

1. **Profundidade em ortopedia igual à do Physitrack** nas 10 condições mais comuns. Tem que existir o exercício certo, com progressão e regressão ligadas, para cada fase de cada protocolo.
2. **Melhor que o Physitrack no que é Brasil**: narração em PT-BR nativo, equipamento caseiro brasileiro como filtro de primeira classe e modo de baixo consumo de dados. O Physitrack tem 5.978 exercícios em português, e o que não foi traduzido aparece em inglês [fonte 2]. A página dele não diz se a narração foi traduzida [fonte 2].
3. **Protocolos-modelo com diretriz citada e lógica visível**, sem virar SaMD (P58).

Não chega a 10 por causa da **amplitude**: ~500 exercícios contra 18.000+ do Physitrack [fonte 1] e 20.000+ do Wibbi [fonte 5], sem neuro, pediatria, aquática nem pelve feminina. Para o autônomo de ortopedia, esse buraco quase não aparece no dia a dia. Para clínica multidisciplinar, aparece.

---

## 2. O que os melhores fazem

### 2.1 Tamanho, formato e organização

| player | tamanho | formato | narração / idioma | organização | fonte |
|---|---|---|---|---|---|
| **Physitrack** | 18.000+ exercícios; 100+ vídeos novos por trimestre | vídeo 4K, vários ângulos; amostras em GIF | "narração profissional"; 16 idiomas, PT com 5.978 exercícios e fallback em inglês | 13 categorias (MSK, sênior, esporte, saúde da mulher…); filtros por região, equipamento, posição e categoria; tags, favoritos, alternativas e progressões; 200+ modelos por condição; nível no título ("level 2") | [1][2][3][4] |
| **Wibbi** (ex-Physiotec) | 20.000+ vídeos em 18+ especialidades (outra fonte diz 75.000 exercícios) | vídeo, foto, PDF/HTML para imprimir | idioma escolhido pelo paciente | 300+ modelos de programa; registra dor e esforço percebido; API e 25 a 70+ integrações com EMR | [5][6] |
| **MedBridge** | 9.000+ recursos para o paciente; ~400 vídeos de educação | vídeo "follow-along" com locução, vários ângulos, **imagem espelhável para o outro lado**; animações 3D na parte de educação | inglês + tradução para espanhol; fonte ajustável | modelos compartilháveis por protocolo, região ou déficit; "exercícios sugeridos"; favoritos; filtros | [7][8][9][10] |
| **Rehab My Patient** | 2.500+ exercícios | vídeo HD, foto colorida **ou desenho em traço** | [não confirmado] | a partir de £19 por recurso/mês; queixa em review: o paciente não marca o que fez | [11][12] |
| **The Prehab Guys** | 4.000+ vídeos tutoriais em formato único, gravados e com dicas de fisioterapeutas (DPT) | formato padronizado | [não confirmado] | contrato com organização, por assento | [13] |

**O que copiar**
- **Um padrão único de vídeo** (Prehab Guys, Physitrack). A consistência transmite qualidade mais do que a quantidade.
- **Dois ângulos** (frente e perfil) e **espelhamento** para o lado oposto (MedBridge). Espelhar dobra a cobertura de exercícios unilaterais sem gravar de novo.
- **Modelos por condição** como porta de entrada (Physitrack 200+, Wibbi 300+). O fisioterapeuta começa pelo modelo, não pela busca.
- **Alternativas e progressões ligadas** ao exercício (Physitrack).
- **Desenho em traço como alternativa leve** ao vídeo (Rehab My Patient). Ajuda na impressão e no modo de dados baixos.

**O que evitar**
- **Nível escrito no título** ("level 2", "; 01"), como faz o Physitrack [4]. Fica inconsistente: aparece até "(level )" vazio. Nível e variação devem ser campos.
- **Biblioteca traduzida pela metade, com fallback em inglês** [2]. Para o idoso brasileiro, um único exercício em inglês quebra a confiança.
- **Montagem lenta** (queixa nº 1 contra o MedBridge, ADR §2).

### 2.2 O vídeo funciona? Evidência

- Revisão sistemática (Schoo e Morris): instrução em vídeo melhorou a execução correta e a adesão em comparação com folheto ilustrado. O efeito é modesto, e para idosos os autores recomendam somar instrução verbal ao folheto [fonte 14].
- Estudo pré-pós com 15 idosos frágeis usando vídeo com o próprio fisioterapeuta deles: ganhos em mobilidade, equilíbrio e autoeficácia. Os participantes citaram a música de fundo como motivação. Não há grupo controle [fonte 15].
- **Conclusão para o produto:** vídeo + texto curto + áudio. Nenhum dos três sozinho.

---

## 3. Especificação melhorada

### 3.1 Estratégia de conteúdo: as quatro opções

| opção | custo | qualidade / risco clínico | direitos | veredito |
|---|---|---|---|---|
| **A. Gravar o próprio** (celular + estúdio simples) | baixo em dinheiro, alto em tempo de fisioterapeuta (ver §3.2) | controlado: revisão por fisioterapeuta antes de publicar | nossos, com cessão de imagem do modelo | **Base da biblioteca** |
| **B. Licenciar biblioteca existente** | sem preço público para conteúdo de reabilitação; Hyperhuman (fitness) tem API a partir de US$ 191/mês no plano Platform [16] | fitness ≠ reabilitação; narração e nomes em inglês ou em 16–18 idiomas, PT-BR [não confirmado] | contrato do fornecedor; risco de perder o acervo se o contrato acabar | **Plano B, só com cotação**: Kemtai (reabilitação + API, preço sob consulta) [17], Prehab Guys (contrato com organização, API [não confirmado]) [13] |
| **C. Vídeo gerado por IA / avatar** | Veo 3.1 Fast: US$ 0,10–0,12 por segundo; Veo 3.1: US$ 0,40/s [18]; HeyGen Avatar IV a partir de US$ 29/mês [19] | **alto**: benchmarks mostram humanos que "parecem certos e se movem errado" (pé deslizando, hiperextensão de articulação, membros fora de sincronia) [20]; nenhum modelo chega ao vídeo real (melhor nota 91,1 contra 94,3) e refinar o prompt não corrige [21] | termos de cada ferramenta | **Não usar para demonstrar exercício.** Usar IA só em volta do vídeo real: narração TTS, legenda, roteiro, miniatura |
| **D. Conteúdo gravado pela clínica** | zero para nós | variável; depende de moderação | da clínica; precisa de licença explícita para circular | **F1: privado da clínica** (já previsto, `is_custom`). Compartilhamento entre clínicas: depois (§3.7). Marketplace pago: fora |

**Por que não licenciar como base:** não encontrei nenhum fornecedor que licencie por API, com preço público, uma biblioteca **clínica de reabilitação** narrada em PT-BR. A ExerciseDB (1.500 GIFs, fitness) não deixa claro se permite uso comercial [22]. A ExRx bloqueou a leitura da página de licença [não confirmado]. Physitrack e Wibbi integram com EMR, mas não revendem o acervo para concorrente [5][6] [licença de conteúdo: não confirmado].

**Por que não usar IA como base:** um exercício demonstrado errado é dano ao paciente e vídeo viral contra o produto (ADR §2, erro 2). Isso combina com o motivo da ADR para não prometer precisão da câmera.

### 3.2 Roteiro de produção própria (opção A)

**Padrão do vídeo "Fisio"**

| item | regra |
|---|---|
| Ângulos | frente e perfil; lado direito gravado e **esquerdo por espelhamento** (exceto quando há assimetria real, como um apoio na parede) |
| Duração | loop de 8 a 15 s com 2 a 3 repetições lentas e limpas; nada de fala gravada |
| Áudio | **sem som no vídeo**. A narração é uma faixa separada, gerada por TTS a partir do roteiro revisado (§3.5). Corrigir a narração não exige regravar |
| Enquadramento | master 4K 16:9 com corpo inteiro e margem; entrega em **1:1** (cabe em celular na vertical, deitado ou em pé) |
| Cenário | fundo cinza-claro liso, chão contrastante, roupa lisa sem marca, modelo adulto; um segundo modelo idoso para os exercícios de equilíbrio e sênior |
| Objetos | só equipamento caseiro brasileiro ou faixa elástica (§3.3) |
| Pós-produção | pipeline automático: corte, estabilização, cor, loop, espelhamento, transcodificação (§3.6) |

**Kit [estimativa, sem cotação]:** celular recente que grave em 4K, dois tripés, dois painéis de LED com difusor, fundo de tecido ou papel cinza, colchonete, cadeira sem braço, cabo de vassoura, garrafas PET de 500 mL e 2 L, toalha, degrau ou caixote, faixa elástica. Ordem de grandeza: **R$ [redacted Tier 0]**, uma vez só.

**Equipe por dia de gravação:** fisioterapeuta-modelo (ou dois: adulto e idoso), uma pessoa operando câmera e roteiro (pode ser do time de 5), fisioterapeuta revisor depois, assíncrono.

**Produtividade [estimativa]:** gravando em lote por posição (todos os "deitado de costas", depois todos os "sentado"…), com dois celulares ao mesmo tempo (frente e perfil), dá para fazer **30 a 50 exercícios por dia** de 6 a 8 horas, incluindo de 2 a 4 tomadas cada. Medir nos dois primeiros dias e recalcular.

| lote | quando | exercícios | dias de gravação [estimativa] | cobre |
|---|---|---|---|---|
| L0 (seed do código, T-04) | já previsto | 38 | 1 | testes e demo |
| L1 | mês 1–2 | +160 (≈200) | 4–6 | os 10 protocolos da §3.4 com progressões |
| L2 | mês 3–4 | +200 (≈400) | 5–7 | variações de equipamento, idoso, pós-operatório (onda 2) |
| L3 | mês 5–6 | +100 a 200 (≈500–600) | 3–5 | lacunas apontadas pela busca sem resultado (§5) |

**Custo dominante:** horas de fisioterapeuta (modelo e revisor). Valor [não confirmado]: cotar diária de modelo e hora de revisão por exercício com fisioterapeutas da região, e considerar permuta (uso gratuito do módulo + crédito "gravado por" no vídeo, sem depoimento, ADR §4).

**Direitos do que gravarmos:** contrato de cessão de direito de imagem e voz com cada modelo, por prazo indeterminado, para uso no produto (Código Civil, art. 20) [23], e cessão dos direitos patrimoniais sobre roteiro e vídeo (Lei 9.610) [24]. Sem paciente real no vídeo.

### 3.3 Taxonomia (facetas, todas como campo, nunca no título)

| faceta | valores | observação |
|---|---|---|
| **Região** | cervical, torácica, lombar, pelve/quadril, joelho, tornozelo/pé, ombro, cotovelo, punho/mão, ATM, corpo todo | multivalor (um agachamento é quadril + joelho) |
| **Objetivo** | mobilidade, alongamento, fortalecimento, controle motor/estabilização, equilíbrio/propriocepção, condicionamento, funcional (AVD), respiração/relaxamento | multivalor |
| **Fase** | 1 proteção (aguda) · 2 recuperação (subaguda) · 3 fortalecimento · 4 retorno à função/esporte | o profissional sempre pode usar fora da fase |
| **Posição** | deitado de costas, deitado de lado, de bruços, quatro apoios, ajoelhado, sentado, em pé, em pé com apoio | importa para idoso e para o lote de gravação |
| **Equipamento caseiro BR** | nenhum · cadeira · parede · toalha · cabo de vassoura · garrafa PET (500 mL ≈ 0,5 kg; 2 L ≈ 2 kg) · saco de arroz/feijão (1 kg, 5 kg) · mochila com peso · degrau/escada · travesseiro/almofada · elástico/faixa · caneleira · bola | filtro "**só o que tenho em casa**": o paciente marca o que tem e o editor esconde o resto |
| **Nível** | 1 a 5 | ligado ao grafo de progressões |
| **Dosagem padrão** | repetições · tempo · sustentação; séries; vezes por dia | já no handoff (`default_*`, `times_per_day`) |
| **Lateralidade** | bilateral, unilateral (espelhável), alternado | |
| **Contraindicações** | os mesmos códigos de `condition_flags` do paciente | ver §3.3.1 |
| **Sinônimos** | nomes regionais e técnicos ("ponte" = "elevação pélvica"; "panturrilha" = "batata da perna"; "abdominal" ≠ "prancha") | tabela própria |

#### 3.3.1 Contraindicações — regra de produto

A ADR só traz os códigos de condição, sem o detalhe clínico. Abaixo está o mapeamento inicial, **a validar na revisão clínica** (ADR §12, pendência "revisão clínica da biblioteca"):

| flag do paciente | exercícios marcados (exemplos, a validar) |
|---|---|
| `protese_quadril_posterior` | flexão do quadril > 90°, adução cruzando a linha média, rotação interna |
| `protese_quadril_anterior` | extensão do quadril combinada com rotação externa |
| `pos_op_manguito` | elevação ativa do ombro e carga, até a liberação no protocolo do cirurgião |
| `pos_lca` | conforme protocolo do cirurgião (cadeia aberta de joelho em fase inicial) |
| `gestante` | decúbito dorsal prolongado depois da metade da gestação; manobra de Valsalva; risco de impacto [a validar] |
| `osteoporose` | flexão de tronco com carga, torção com carga |
| `risco_queda` | equilíbrio em pé sem apoio → exigir versão "com apoio" |
| `hipertensao_nao_controlada` | isometria intensa, Valsalva, cabeça abaixo do coração |

**Comportamento:** ao prescrever, o editor mostra um aviso no exercício marcado e pede **justificativa em texto** para manter, gravada no prontuário. O sistema nunca bloqueia, porque a decisão é do profissional, e **nada aparece para o paciente**. Assim fica fora do SaMD (P10: o profissional escolhe; o software não decide conduta para o paciente).

### 3.4 Progressões, regressões e protocolos

**Grafo de ligações** (`fisio_exercise_links`): `de → para`, tipo (`progressao`, `regressao`, `alternativa`, `troca_equipamento`), e um **critério em texto** escrito pelo revisor ("quando fizer 3×15 sem dor > 3/10").
- No editor de programa: botões **"mais fácil" / "mais difícil" / "trocar equipamento"** substituem o exercício no lugar, mantendo a dosagem. Dois toques, sem busca.
- **O paciente nunca progride sozinho**, e o software nunca troca o exercício por causa da dor (ADR §5, P10). O critério escrito aparece só para o profissional.

**Protocolo-modelo = lógica visível (P58).** Cada protocolo mostra na tela:
1. diretriz, ano e DOI, com a recomendação **parafraseada** e o grau de evidência (sem copiar o texto da JOSPT, que tem direito autoral);
2. fases com objetivos, exercícios e **critério de passagem escrito**;
3. o aviso fixo "Modelo de referência. A decisão clínica é do fisioterapeuta.";
4. passagem de fase **sempre manual**: o profissional clica e isso fica registrado.

**Os 10 primeiros protocolos (traumato-ortopedia, ambulatório particular)** — escolhidos por volume no autônomo, diretriz atual e baixo risco de pós-operatório:

| # | protocolo | diretriz de base | status da diretriz |
|---|---|---|---|
| 1 | Dor lombar inespecífica (aguda e crônica) | JOSPT/AOPT Low Back Pain, 1ª revisão 2021 | 2ª revisão em andamento [25] |
| 2 | Cervicalgia | JOSPT Neck Pain, revisão 2017 | 2ª revisão em rascunho (meta 2025) [25] |
| 3 | Osteoartrite de joelho | AAOS 3ª ed., listada pela APTA; VA/DoD quadril e joelho [26] | — |
| 4 | Osteoartrite de quadril | JOSPT 2025 (Koc et al., 55(11)): exercício nota A, 1–5×/semana por 5–16 semanas [27] | atual |
| 5 | Dor patelofemoral | JOSPT 2019 | revisão pendente [25] |
| 6 | Tendinopatia do manguito rotador / dor no ombro | JOSPT 2025 (Desmeules et al.): exercício nota A, resistido progressivo + controle motor [28] | atual |
| 7 | Entorse lateral de tornozelo / instabilidade | JOSPT Ankle Stability, 1ª revisão 2021 | 2ª revisão prevista para 2026 [25] |
| 8 | Tendinopatia de Aquiles (porção média) | JOSPT, 2ª revisão dez/2024 | atual [25] |
| 9 | Dor no calcanhar / fasciopatia plantar | JOSPT, 2ª revisão dez/2023 | atual [25] |
| 10 | Epicondilalgia lateral | JOSPT, dez/2022 | atual [25] |
| 11 | Fibromialgia | EULAR 2017 (exercício: recomendação forte), SBR 2026, Cochrane 2017 (aeróbico) e 2013 (resistido) — detalhe em [`../protocolos/11-fibromialgia.md`](../protocolos/11-fibromialgia.md) | atual |

**Aprovação:** os protocolos 1–11 foram aprovados pelo produto por [W] em 2026-10-18. Isso libera a construção (B-09) e o seed em `rascunho`; a publicação de cada um continua dependendo da revisão clínica da Dra. Dejeane (C-04), registrada no PR.

**Onda 2 (L2, mais risco, depende do protocolo do cirurgião):** pós-artroplastia total de joelho (APTA, revisão 2026) [29][30], pós-reconstrução de LCA / estabilidade do joelho (JOSPT 2017) [25], capsulite adesiva (2013, revisão em edição) [25], fratura distal do rádio (JOSPT 2024) [25], fratura de quadril no idoso (2021) [25]. Os pós-operatórios trazem um campo obrigatório "restrições do cirurgião", preenchido pelo profissional.

**Revisão contínua:** cada protocolo guarda `guideline_version`. Quando sai uma nova diretriz (Low Back Pain, Neck Pain, Ankle e Patellofemoral estão em revisão [25]), o protocolo vai para "revisar" e o revisor clínico atualiza.

### 3.5 Narração, legenda e acessibilidade para o paciente idoso

Contexto: 74,5% dos brasileiros com 60+ usaram internet em 2025, e 98,7% dos usuários de internet acessam pelo celular [fonte 31].

**Uma fonte de texto para três saídas.** O roteiro revisado de cada exercício (passos curtos + "faça" + "evite") gera:
1. o **texto na tela** (passos numerados);
2. a **narração TTS** pré-gerada (arquivo de áudio por exercício, não em tempo real);
3. a **legenda WebVTT**, com tempo vindo das marcas do TTS (cada passo = uma marca).

Mudou o texto → regera os três por comando. Nada fica fora de sincronia.

**Custo do TTS:** 600 exercícios × ~600 caracteres ≈ 360 mil caracteres por geração completa [estimativa]. No Google Cloud TTS, isso cabe na cota gratuita mensal (1 milhão de caracteres para Neural2/Chirp 3 HD; 4 milhões para Standard/WaveNet), e depois custa de US$ 4 a US$ 30 por milhão [32]. No ElevenLabs Multilingual, US$ 0,10 por mil caracteres, ou seja, ~US$ 36 por geração completa [33]. **Custo desprezível.** A escolha da voz é pela naturalidade em PT-BR (disponibilidade de voz pt-BR em cada modelo: [não confirmado], testar com 3 idosos).

**Requisitos do portal (mobile-first, idoso):**

| requisito | regra |
|---|---|
| Texto | corpo ≥ 18 px equivalente via token do DS; respeita o zoom do sistema até 200% sem quebrar; frases ≤ 12 palavras; 3 a 5 passos |
| Áudio | botão grande "Ouvir instruções"; velocidade 0,9× por padrão, com opção 0,75×; contagem falada de repetições e sustentação ("um… dois…"), com áudio pré-gerado e estático |
| Legenda | ligada por padrão, fonte grande, fundo escuro semitransparente |
| Toque | alvos ≥ 48 px; um exercício por tela; "Feito" grande |
| Lado | botão "mostrar lado esquerdo" (vídeo espelhado) |
| Contraste | tokens do DS v6 com contraste AA; nada de informação só por cor |
| Sem vídeo | os 3 quadros-chave (início, meio, fim) + texto + áudio (modo dados baixos e impressão) |
| Impressão | folha A4 com quadros-chave, letra grande, QR code para o link (para o paciente que prefere papel) |

### 3.6 Modo de baixo consumo de dados

Contexto: o GB móvel custou em média R$ [redacted Tier 0] no 1º trimestre de 2025 (+21%), e o consumo de dados caiu 9,3% [34]. No 2º trimestre de 2026 a média foi R$ [redacted Tier 0] [35]. Planos pré-pagos em São Paulo saem por ~R$ [redacted Tier 0]/GB [36]. **O paciente economiza dados, e a gente precisa respeitar isso.**

**Codificação [estimativas de tamanho, a medir no pipeline]:**

| variante | resolução 1:1 | alvo | 12 s ≈ |
|---|---|---|---|
| economia | 360×360, H.264 | ~150 kbps | ~0,25 MB |
| padrão | 480×480, H.264 | ~350 kbps | ~0,5 MB |
| alta (Wi-Fi) | 720×720, H.264 | ~900 kbps | ~1,4 MB |
| quadros-chave | 3 × WebP | — | ~60 KB |
| narração | Opus/AAC mono ~32 kbps | — | ~0,1 MB por 25 s |

- **H.264 como base obrigatória.** AV1 tem decodificação em hardware só em aparelhos recentes (no iPhone, a partir do 15 Pro), e o recomendado é manter vários codecs [37]. AV1 fica como variante opcional futura.
- **Vídeo em loop é baixado uma vez** e fica no cache. Um programa de 6 exercícios na variante padrão ≈ 3 MB na primeira abertura, ~R$ [redacted Tier 0] de dados [estimativa com R$ [redacted Tier 0]/GB]. Nos dias seguintes, zero.
- **Cache no aparelho:** service worker no portal guarda vídeo, áudio e quadros-chave do programa ativo. Mudou o programa → baixa só o que mudou.
- **"Baixar no Wi-Fi":** ao abrir o link, oferecer "Baixar tudo agora (≈3 MB)". Detecção automática do tipo de rede só funciona em navegadores Chromium (Network Information API) [não confirmado nesta pesquisa]; no iPhone, a escolha é manual.
- **Modo economia** (chave no portal, lembrada no aparelho): força 360p ou só quadros-chave + áudio.
- **Entrega:** CDN de vídeo barato. Bunny Stream: armazenamento US$ 0,01/GB, entrega na América do Sul US$ 0,045/GB, codificação padrão grátis, mínimo de US$ 1/mês [38]. Cloudflare Stream: US$ 5 por mil minutos armazenados e US$ 1 por mil minutos entregues [39]. Como os loops são curtos, gerar as variantes no nosso pipeline com ffmpeg e servir MP4 estático por CDN (Bunny Storage + CDN) é o mais simples e barato. Sem HLS: loop de 12 s não precisa de bitrate adaptativo.

### 3.7 Conteúdo da clínica (opção D)

**F1 (já no handoff, `is_custom`):**
- Upload de vídeo do celular (≤ 60 s, ≤ 150 MB), transcodificado pelo mesmo pipeline e visível **só no `business_id`**.
- Ao subir, três declarações obrigatórias: (a) "a pessoa no vídeo autorizou o uso da imagem"; (b) "**a pessoa no vídeo não é paciente**". O handoff proíbe guardar vídeo de paciente na F1, e o Código de Ética veda o uso de imagem de paciente (ADR §4); (c) "não há marca, música ou conteúdo de terceiros".
- O roteiro do exercício próprio passa pelo mesmo *lint* de linguagem (§3.8).

**Depois da F1 (proposta, fora deste ciclo): "Biblioteca da comunidade".**
- A clínica pode oferecer um exercício próprio para todos, com **licença não exclusiva e gratuita** ao oimpresso e às outras clínicas, crédito "criado por Nome · CREFITO" e possibilidade de retirar para usos novos.
- Fila de moderação com revisor clínico do oimpresso, usando o mesmo checklist do conteúdo próprio.
- **Notificação e retirada:** o STF ampliou em 2025 a remoção por notificação extrajudicial e fixou responsabilidade solidária do provedor por conteúdo de terceiro depois de notificado, salvo dúvida razoável. As teses foram consolidadas em junho de 2026 [40]. Por isso: botão "denunciar", prazo interno de análise e retirada preventiva quando houver dúvida clínica.
- **Marketplace pago: fora.** Cobrança, repasse e nota fiscal entre clínicas é complexidade que não sobe a nota.

### 3.8 Governança de conteúdo (o que faz o 9 ser confiável)

- **Duas pessoas por exercício publicado:** autor ≠ revisor, os dois com CREFITO gravado (`reviewed_by`, `reviewed_crefito`, `reviewed_at`). Isso atende a pendência da ADR §12.
- **Estados:** `rascunho → em_revisao → publicado → retirado`. O que foi publicado não é editado: nova versão. Programas antigos continuam apontando para a versão que o paciente recebeu (prontuário append-only, Res. 414).
- **Lint de linguagem (Anvisa, ADR §5):** bloqueia "trata", "cura", "corrige postura", "previne lesão" e "diagnostica" nos textos do exercício e do protocolo.
- **Checklist de revisão:** execução correta no vídeo, passos coerentes com o vídeo, contraindicações marcadas, progressão e regressão ligadas, dosagem padrão plausível, equipamento correto, legenda conferida.

### 3.9 Busca

A ADR põe "busca por texto" na F4. **Proposta: trazer a busca com facetas para a F1**, porque sem ela a nota da biblioteca não passa de 7. O ditado por voz continua na F4.

> **Decidido — [W] 2026-10-18:** a busca por texto **entra na F1** (decisão nº 3 da ADR 0437 §14). A US-FISIO-004 entregou a forma mínima: busca por nome e sinônimos (LIKE, sem motor externo; sinônimos numa lista JSON no próprio exercício). Os itens abaixo — FULLTEXT com passos, tabela de sinônimos editável, ordenação, registro de busca sem resultado, filtro por equipamento e atalhos — continuam na B-07.

- **MySQL FULLTEXT** em nome + sinônimos + passos, mais filtros por faceta. Sem motor externo e sem camada de abstração nova.
- Tabela de sinônimos editável pelo revisor.
- **Ordenação:** favoritos da clínica → mais usados pela clínica → nível ascendente.
- **Busca sem resultado é registrada** (termo + business) e vira a pauta do lote L3.
- Filtro "só o que o paciente tem em casa" lido do perfil do paciente (`home_equipment`, json).
- Atalhos de teclado do PT-01 (`/` busca, J/K navega, Enter adiciona ao programa).

### 3.10 Dados (mudanças sobre o handoff)

| tabela | campos novos / mudança |
|---|---|
| `fisio_exercises` | `code` (ex.: `JOE-FORT-012`), `slug`, `regions` (json), `objectives` (json), `phases` (json), `position`, `equipment` (json), `level` (1–5), `laterality`, `dose_type`, `cues_do` (json), `cues_avoid` (json), `narration_script`, `status`, `version`, `supersedes_id`, `source` (`oimpresso`/`clinica`/`licenciado`), `license`, `reviewed_by`, `reviewed_crefito`, `reviewed_at`. Mantém `contraindication_flags`, `safety_notes`, `needs_support` |
| `fisio_exercise_media` (nova) | `exercise_id`, `kind` (`video`, `keyframe`, `audio`, `caption`), `variant` (`360`, `480`, `720`), `angle` (`frente`, `perfil`), `mirrored` (bool), `url`, `bytes`, `duration_ms`, `sha256` |
| `fisio_exercise_links` (nova) | `from_id`, `to_id`, `kind` (`progressao`, `regressao`, `alternativa`, `troca_equipamento`), `criterion_text`, `business_id` nulo = global |
| `fisio_exercise_synonyms` (nova) | `exercise_id` ou `term_group`, `term` |
| `fisio_protocols` | `guideline_ref` (json: título, ano, DOI, grau), `guideline_version`, `phases` (json: objetivos, critério de passagem em texto, itens), `requires_surgeon_restrictions` (bool), `status`, `version` |
| `fisio_patients` | `home_equipment` (json) |
| `fisio_program_items` | `contraindication_override_reason` (texto, quando o profissional mantém exercício marcado) |
| `fisio_search_misses` (nova) | `business_id`, `term`, `filters` (json), `created_at` |
| `fisio_custom_uploads` (nova) | `business_id`, `exercise_id`, declarações (3 booleanos + IP + data), `transcode_status` |

### 3.11 Integrações

- **Pipeline de mídia:** comando Artisan + job em fila com ffmpeg (corte, estabilização, espelhamento, variantes, quadros-chave) → storage S3-compatível → CDN.
- **TTS:** um provedor só (Google Cloud TTS ou ElevenLabs, decidido no teste com idosos), chamado só no comando de geração. A chave de API não chega ao portal.
- **IA pela camada `laravel/ai` (ADR 0035):** só para ferramentas internas de conteúdo, como rascunho de roteiro a partir das notas do revisor e sugestão de sinônimos, **sempre revisadas por humano**. Nunca no caminho do paciente nem na prescrição (ADR §5, P57).

---

## 4. Riscos e o que fica fora

| risco | mitigação |
|---|---|
| Vídeo com execução errada | duas pessoas revisando; sem vídeo gerado por IA; botão "reportar problema neste exercício" para o profissional |
| Capacidade de produção (time de 5, sem fisioterapeuta interno) | contratar fisioterapeuta-modelo/revisor por lote; gravação em lote; pipeline automático. Se a produtividade real ficar abaixo de 20 por dia, cortar L3 e cotar a opção B |
| Contraindicação mal mapeada | mapa da §3.3.1 só entra em produção depois da revisão clínica; aviso sem bloqueio, com justificativa registrada |
| Protocolo lido como "o software prescreve" | lógica visível, citação, passagem manual de fase, texto padrão da ADR §5 |
| Direito autoral de diretriz | paráfrase + DOI; nenhuma tabela ou figura copiada |
| Imagem de terceiro no upload da clínica | três declarações; retirada após notificação [40] |
| Dados móveis | variantes pequenas, cache, modo economia, quadros-chave |
| Fornecedor de TTS muda preço | áudio pré-gerado e guardado; troca de provedor só exige regerar |

**Fora deste ciclo:** vídeo gerado por IA para demonstração; marketplace pago; biblioteca da comunidade (proposta para depois da F1); neuro, pediatria, aquática e pelve; tradução para outros idiomas; animação 3D; progressão automática; qualquer sugestão de exercício pelo software (P57).

---

## 5. Métricas de sucesso (6 meses)

| métrica | meta |
|---|---|
| Exercícios publicados e revisados | ≥ 500, 100% com dois revisores |
| Cobertura dos 10 protocolos | 100% das fases com ≥ 1 progressão e ≥ 1 regressão ligadas |
| Exercícios com narração PT-BR + legenda + quadros-chave | 100% |
| Busca sem resultado | < 5% das buscas |
| Tempo mediano para montar um programa (com protocolo) | ≤ 60 s (promessa da ADR) |
| Programas montados a partir de protocolo-modelo | ≥ 50% |
| Itens de programa que são exercício próprio da clínica | ≤ 20% (acima disso, falta conteúdo na biblioteca) |
| Dados por dia de check-in depois do primeiro acesso (mediana) | < 0,5 MB |
| Primeira abertura do programa, variante padrão (mediana) | < 4 MB |
| Reproduções de áudio "Ouvir instruções" entre pacientes 60+ | medir; meta definida depois de 30 dias |
| Reportes de "exercício com problema" | < 1 a cada 1.000 prescrições, todos resolvidos em ≤ 7 dias |

---

## 6. Tarefas para o Claude Code (em ordem de dependência)

Encaixam no handoff da F1: B-01 a B-04 detalham T-03/T-04; B-05 a B-08 detalham T-06; B-09 e B-10 entram em T-07.

| id | tarefa | blocked_by | DoD |
|---|---|---|---|
| B-01 | Migrations da §3.10 (`fisio_exercises` ampliada, `_media`, `_links`, `_synonyms`, `_search_misses`, `_custom_uploads`, `home_equipment`, campos de protocolo) | T-03 | isolamento entre 2 businesses; exercício global (`business_id` nulo) visível para todos e editável por nenhum tenant; exercício `publicado` não aceita UPDATE de conteúdo (só uma nova versão) |
| B-02 | Enum e validação das facetas (região, objetivo, fase, posição, equipamento BR, nível, lateralidade, dose) + flags de contraindicação iguais a `condition_flags` | B-01 | teste: valor fora do enum falha; os códigos de contraindicação batem 1:1 com os do paciente |
| B-03 | Pipeline de mídia: `php artisan fisio:media:ingest {pasta}` → job ffmpeg (corte, estabilização, espelhamento, 1:1, variantes 360/480/720 H.264, 3 quadros-chave WebP) → storage → registros em `_media` com bytes e sha256 | B-01 | um master de exemplo gera as 7+ variantes; tamanhos dentro de ±30% dos alvos da §3.6; idempotente por sha256 |
| B-04 | Geração de narração e legenda: `fisio:narration:build` lê `narration_script`, chama o TTS configurado, grava o áudio e o WebVTT com tempo pelas marcas; `--changed-only` | B-01 | mudar o roteiro regera só aquele exercício; legenda e áudio com o mesmo texto; chave de TTS só no `.env` do servidor |
| B-05 | Seed L0 (38 exercícios, T-04) **no formato novo**, com facetas, ligações, roteiro e mídia de exemplo; estados e revisores preenchidos | B-02, B-03, B-04 | `feature-lint` verde; PR com anotação do revisor clínico; lint de linguagem (B-06) passa |
| B-06 | Lint de linguagem Anvisa (palavras proibidas da ADR §5) aplicado ao salvar exercício, protocolo e exercício próprio | B-01 | teste com cada palavra proibida; mensagem em PT-BR explicando o motivo |
| B-07 | `Fisio/Biblioteca/Index`: busca FULLTEXT + sinônimos + facetas, favoritos, "só o que o paciente tem em casa", registro em `_search_misses`, atalhos PT-01 | B-05 | busca por sinônimo acha o exercício; filtro de equipamento esconde o resto; termo sem resultado é gravado; só tokens do DS |
| B-08 | Editor de programa: "mais fácil / mais difícil / trocar equipamento" pelo grafo, mantendo a dosagem; aviso de contraindicação com justificativa obrigatória gravada em `contraindication_override_reason` e no prontuário | B-07, T-05 | marcador do paciente dispara aviso no exercício certo; manter sem justificativa falha; nada disso aparece no portal |
| B-09 | Protocolos-modelo: tela com diretriz (título, ano, DOI, grau), fases, critério de passagem em texto, aviso fixo, passagem de fase manual registrada; `requires_surgeon_restrictions` obriga campo de texto | B-08 | teste: o sistema nunca muda a fase sozinho; protocolo sem `guideline_ref` não publica; seed dos protocolos 1–10 em `rascunho` aguardando revisão clínica |
| B-10 | Player do portal: 1:1, loop mudo, botão "Ouvir instruções" (0,9×/0,75×), contagem falada, legenda ligada por padrão, espelhar lado, alvos ≥ 48 px, zoom até 200% | B-04, T-07 | teste de acessibilidade (axe) sem violação AA; funciona sem áudio e sem vídeo (só quadros-chave) |
| B-11 | Dados baixos: service worker com cache do programa ativo, "Baixar tudo agora (≈X MB)", modo economia (360p ou quadros-chave + áudio) lembrado no aparelho, atualização incremental quando o programa muda | B-10 | segunda abertura do mesmo programa sem tráfego de mídia (teste com rede simulada); modo economia baixa < 1 MB para 6 exercícios |
| B-12 | Folha para imprimir do programa (A4, quadros-chave, letra grande, QR do link) | B-10 | PDF gerado por paciente; sem dado clínico além do programa |
| B-13 | Upload de exercício próprio: limite de 60 s/150 MB, três declarações obrigatórias gravadas com IP e data, transcodificação pelo B-03, visível só no business | B-03, B-06 | sem as 3 declarações não sobe; outro business não vê; vídeo vai pelo mesmo pipeline |
| B-14 | Painel interno de conteúdo (só oimpresso): fila `em_revisao`, checklist da §3.8, autor ≠ revisor, relatório de buscas sem resultado, cobertura de protocolos | B-05, B-07 | autor não consegue aprovar o próprio exercício; relatório lista os termos mais buscados sem resultado |

**Trilha de conteúdo (paralela, não é código; responsável: [W] + fisioterapeuta contratado):** C-01 roteiros L1 (160) → C-02 gravação L1 → C-03 revisão clínica L1 + mapa de contraindicações da §3.3.1 → C-04 revisão dos 10 protocolos → L2 → L3. C-03 e C-04 desbloqueiam a publicação de B-05 e B-09 em produção.

---

## 7. Fontes

1. Physitrack — Exercise library (4K, vários ângulos, narração profissional, 100+ vídeos por trimestre, 13 categorias): https://www.physitrack.com/en-gb/exercise-library
2. Physitrack — Biblioteca multilíngue (16 idiomas; PT 5.978 exercícios com fallback em inglês): https://www.physitrack.com/es/insights/multilingual-exercise-library
3. Physitrack Academy — Finding exercises (filtros, tags, favoritos, alternativas, progressões): https://www.physitrack.com/academy/find-exercises ; Support — Tips for finding exercises (200+ modelos): https://support.physitrack.com/article/2309-tips-for-finding-exercises
4. Physitrack — página de exercício (nível e variação no título): https://www.physitrack.com/exercise-library/how-to-perform-the-farmers-walk ; Physitrack "Best Physical Therapy HEP Software in 2026" (18.000+, 15+ idiomas; MedBridge 9.000+), 11/06/2026, material do próprio fornecedor: https://www.physitrack.com/insights/best-physical-therapy-hep-software
5. IntuitionLabs — Wibbi (20.000+ vídeos, 300+ modelos, API, a partir de US$ 33/mês): https://intuitionlabs.ai/software/physical-occupational-therapy/exercise-prescription-and-home-programs/wibbi
6. Greenice — Physiotec/Wibbi (75.000 exercícios, 25+ integrações): https://greenice.net/physiotec/
7. MedBridge — Patient Education (3D, animações, espanhol, fonte ajustável): https://www.medbridge.com/care/home-exercise-program/patient-education
8. MedBridge — Comparativo de planos (lista de recursos do HEP): https://www.medbridge.com/pricing/business/compare
9. MedBridge blog — 6 methods (modelos, favoritos, filtros, ~400 vídeos de educação): https://cloudbridge.medbridgeeducation.com/blog/6-methods-for-making-the-most-of-medbridge-hep
10. PT Progress — MedBridge GO (locução, vários ângulos, espelhar, espanhol; autor é afiliado): https://www.ptprogress.com/medbridge-go-home-exercise-program/
11. Physio Update — Rehab My Patient (2.500+, HD, foto ou traço): https://physioupdate.co.uk/?p=1257
12. Capterra — Rehab My Patient (a partir de £19, reviews): https://www.capterra.com/p/154259/Rehab-My-Patient/
13. The Prehab Guys — Enterprise (4.000+ vídeos, formato único): https://theprehabguys.com/enterprise
14. Schoo & Morris — modo de instrução de exercício (PEDro): https://search.pedro.org.au/search-results/record-detail/11542
15. Frontiers in Public Health 2022 — vídeo personalizado para idosos: https://public-pages-files-2025.frontiersin.org/journals/public-health/articles/10.3389/fpubh.2022.974512/text
16. Hyperhuman — Pricing (Platform US$ 191/mês com API): https://hyperhuman.cc/pricing ; Exercise video API: https://hyperhuman.cc/exercise-video-api ; Licensing: https://hyperhuman.cc/fitness-content-licensing
17. Kemtai — Exercise library (reabilitação, API, preço sob consulta): https://kemtai.com/product/exercise-library/
18. BenchLM — Veo 3.1 API pricing (lido em 13/10/2026): https://benchlm.ai/media-pricing/veo
19. Creatify — HeyGen pricing 2026: https://creatify.ai/blog/heygen-pricing-(2026)-plans-and-what-you-ll-actually-pay
20. NeurIPS 2025 — "Can Text-to-Video Models Generate Responsible Realistic Human Motion?" (Movo): https://neurips.cc/virtual/2025/133230
21. HumanScore — benchmark de movimento humano em vídeo gerado (2026): https://www.alphaxiv.org/abs/2604.20157
22. ExerciseDB / AscendAPI (1.500 GIFs, licença não explícita): https://oss.exercisedb.dev/
23. Código Civil, art. 20 (imagem): https://www.planalto.gov.br/ccivil_03/leis/2002/l10406compilada.htm
24. Lei 9.610/1998 (direitos autorais): https://www.planalto.gov.br/ccivil_03/leis/l9610.htm
25. AOPT — Relatório CPG CSM 2025 (status de todas as CPGs ortopédicas): https://orthopt.org/uploads/content_files/files/CSM_BOD_2025_Report_OP_CPG_ISC_Membership.pdf
26. APTA — Treatment of OA of the Knee, 3rd ed.: https://www.apta.org/patient-care/evidence-based-practice-resources/clinical-practice-guidelines/treatment-of-osteoarthritis-of-the-knee-evidence-based-guideline-3rd-edition ; VA/DoD quadril e joelho: https://apta.org/patient-care/evidence-based-practice-resources/cpgs/vadod-clinical-practice-guideline-for-the-non-surgical-management-of-hip-and-knee-osteoarthritis
27. Physiotutors — Hip OA CPG 2025 (Koc et al., JOSPT 55(11)): https://www.physiotutors.com/hip-osteoarthritis-the-new-2025-clinical-practice-guideline-for-physiotherapists/
28. Physiotutors — Rotator cuff tendinopathy CPG 2025 (Desmeules et al., JOSPT): https://www.physiotutors.com/rotator-cuff-tendinopathy-the-new-2025-clinical-practice-guideline/
29. APTA — Published CPGs (hip OA out/2025, TKA jul/2026 etc.): https://www.apta.org/patient-care/evidence-based-practice-resources/cpgs/cpg-development/published-cpgs
30. APTA — Updated CPG after TKA (03/08/2026): https://www.apta.org/article/2026/08/03/updated-clinical-practice-guideline-for-physical-therapist-management-after-tka
31. Teletime — IBGE PNAD TIC 2025 (60+ = 74,5%; 98,7% acessam pelo celular): https://teletime.com.br/02/07/2026/usuarios-internet-brasil-ultrapassa-90/
32. Google Cloud — Text-to-Speech pricing: https://cloud.google.com/text-to-speech/pricing?hl=nl
33. Puter — ElevenLabs API pricing (jun/2026; publicado por concorrente): https://developer.puter.com/tutorials/elevenlabs-api-pricing/
34. Hardware.com.br — Anatel: GB a R$ [redacted Tier 0] no 1T2025, consumo −9,3%: https://www.hardware.com.br/?p=315070
35. Teletime — Anatel: GB a R$ [redacted Tier 0] no 2T2026: https://teletime.com.br/07/10/2026/preco-medio-gb-movel-cai-anatel/
36. MelhorPlano.net — preço por GB 2025 (pré-pago R$ [redacted Tier 0]): https://melhorplano.net/sala-de-imprensa/preco-por-gb-2025
37. Bitmovin — AV1 playback support (dados de 2024; manter vários codecs): https://bitmovin.com/av1-playback-support/
38. Bunny Stream — Pricing: https://bunny.net/docs/stream/pricing
39. Cloudflare Stream — Pricing: https://developers.cloudflare.com/stream/pricing
40. Mattos Filho — STF atualiza teses do art. 19 do Marco Civil (jun/2026): https://www.mattosfilho.com.br/unico/stf-atualiza-teses-fixadas/

**Não confirmado nesta pesquisa:** diária de videomaker e cachê de fisioterapeuta no Brasil; vozes pt-BR específicas de cada modelo de TTS; termos de licença da ExRx e da ExerciseDB; preço da Kemtai e da Prehab Guys; se a narração do Physitrack em português é dublada; suporte da Network Information API por navegador; recomendações para gestante (a definir na revisão clínica).

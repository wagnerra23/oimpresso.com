# Etapa 06 — Lançamento e crescimento (Módulo Fisio)

> Pesquisa em 2026-10-17. O orçamento de buscas na web da sessão acabou no meio desta etapa. Por isso, alguns pontos ficaram **não confirmados** e estão marcados assim. Valores sem fonte estão marcados como **estimativa**.
> A busca no INPI **não foi feita**: o proxy da sessão bloqueia `busca.inpi.gov.br`. A seção 3.7 traz uma checagem de colisão na web e o roteiro manual.

---

## 1. Objetivo e meta de nota

**Objetivo:** sair de zero e chegar a uma base real de fisioterapeutas pagantes, gastando o mínimo. O crescimento deve vir do próprio uso do produto: paciente → profissional → colega. Mídia paga não é o motor.

| marco | prazo | critério | nota de maturidade |
|---|---|---|---|
| hoje | — | zero usuários | **1** |
| piloto fechado | semana 8 depois da F1 em produção | 15 fisioterapeutas, ≥ 10 ativos na semana 8, ≥ 6 aceitam pagar o preço fundador | **4** |
| lançamento aberto em SC | mês 4 | 50 pagantes, autoatendimento (cadastro → primeiro paciente sem ajuda humana) | **6** |
| tração | mês 9–12 | 300 pagantes; ≥ 25% dos cadastros novos vindos de indicação ou do rodapé do portal | **7** |

As metas de quantidade são **estimativas minhas** e servem de calibragem. Não vêm de benchmark do setor.

**Tamanho do mercado:** o levantamento georreferenciado mais recente que encontrei aponta **278.708 fisioterapeutas no Brasil** e **9.659 em Santa Catarina**, com dados até dez/2020 ([RSD Journal](https://rsdjournal.org/rsd/article/download/22392/19871/270164)). Não achei número oficial do COFFITO para 2025–2026 (**não confirmado**).

---

## 2. O que os melhores fazem: o que copiar e o que evitar

### 2.1 Preço dos concorrentes (outubro/2026)

| produto | grátis | entrada | equipe/clínica | anual / outros |
|---|---|---|---|---|
| Physitrack | trial de 2 semanas | US$ 18,99/prof./mês (≈ R$ 169 no comparativo) | Enterprise sob consulta | "fundadores": 30% off por 12 meses para quem entrar até 01/01/2027; oferta para estudante sem preço publicado |
| Vedius | trial de 7 dias, sem cartão | R$ 79,90/mês | R$ 69,90/colaborador | anual R$ 749,90 (25% off à vista, 15% parcelado) |
| ZenFisio | trial de 14 dias | R$ 79/mês | R$ 179 (Equipe), R$ 439 (Clínica) | 25% off semestral/anual; financeiro e NFS-e podem ser cobrados à parte |
| Kynesia | plano Start grátis | Flow R$ 49,90 (até 50 pacientes ativos, 50 laudos por IA) | Elite R$ 89,90 | anual com desconto, sem valor publicado |
| Fisioly | Free com limites após 7 dias | Essencial R$ 44,90 | Profissional R$ 79,90 / Elite R$ 149,90 | anual mais barato |

Fontes: [comparativo Physitrack, 04/10/2026](https://www.physitrack.com/pt/insights/melhor-software-fisioterapia-brasil-comparativo); [preços Physitrack](https://www.physitrack.com/pt/physitrack-pricing); [Vedius](https://vedius.com.br/mercado-fisioterapia/).

**Conclusão:** a faixa de R$ 49–79 é a âncora do mercado, e a validação se confirma. Abaixo de R$ 45 o produto parece "de brinquedo". Acima de R$ 90 ele precisa justificar o valor com dinheiro (F3), coisa que só o oimpresso entrega. Ninguém publica plano para estudante ou recém-formado com preço. Esse espaço está livre.

### 2.2 Modelos product-led

| quem | mecânica | resultado relatado | copiar? |
|---|---|---|---|
| **Dropbox** | indicação nos dois lados (500 MB para cada), link automático para todo usuário sem inscrição, teto de 16 GB; o destaque vai para a recompensa de quem foi convidado | 3.900% de crescimento em 15 meses; 35% dos cadastros diários vieram de indicação (análise de terceiros, sem fonte primária) ([Referral Rock](https://referralrock.com/blog/dropbox-referral-program/)) | **sim**: dois lados, automático, com teto |
| **Jane App** | afiliado paga **US$ 120+ por clínica indicada** ([PartnerStack](https://market.partnerstack.com/page/janesoftware)); indicação de paciente para paciente é manual, por credit memo ([guia Jane](https://jane.app/guide/referral-commission)) | — | sim, entre profissionais; **não** para paciente (ver 2.4) |
| **Calendly** | cada convite funciona como demonstração para quem não usa o produto e aparece justamente quando a pessoa precisa dele; depois do agendamento, um CTA de autoatendimento puxa o cadastro ([Sacra](https://sacra.com/chat/h/bdf9ad15-84ac-49ac-b236-07cbe1e52d0b/)) | números não publicados (não confirmado) | **sim**: cada programa enviado é uma demonstração para o paciente e para a família dele, que pode incluir outro fisioterapeuta |
| **Physitrack** | desconto de fundador por tempo limitado | — | sim, como "fundador piloto" |

**Conversão de referência (para calibrar, não para prometer):**
- Freemium para pago em SaaS B2B para pequenas empresas: **2–5%** ([Monetizely, dez/2025](https://www.getmonetizely.com/articles/freemium-conversion-rate-the-key-metric-that-drives-saas-growth-3588c), sem fonte primária).
- Trial para pago: **25% sem cartão** e **60% com cartão** (benchmark "ideal" do [InnerTrends](https://www.innertrends.com/blog/saas-free-trial-benchmarks), artigo de 2019 atualizado em 2025).

### 2.3 Canais para chegar ao fisioterapeuta

| canal | evidência | custo | papel no plano |
|---|---|---|---|
| **Rede local (Tubarão, Criciúma, Florianópolis)** | SC tem 13,3 fisioterapeutas por 10 mil habitantes, acima da média nacional ([RSD](https://rsdjournal.org/rsd/article/download/22392/19871/270164)) | tempo de [W] | **piloto** |
| **Faculdades e clínicas-escola** | UDESC/CEFID tem graduação em Fisioterapia ([grade](https://udesc.br/arquivos/cefid/id_cpmenu/1012/GRADE_FIS_17224523363496_1012.pdf)); UNESC em Criciúma ([Pravaler](https://www.pravaler.com.br/faculdades/unesc/graduacao/fisioterapia/)); Unisul Tubarão **a confirmar** | baixo: licença grátis para a clínica-escola | **semente de longo prazo**: o aluno formado leva o hábito |
| **Congressos** | o 13º Congresso Internacional de Fisioterapia (SBF) foi em Florianópolis, com 3.000+ congressistas esperados; estande de R$ 900–1.100/m²; cotas de R$ 5 mil (Bronze, 9 m²) a R$ 30 mil ([projeto comercial CIF 2023](https://attitudepromo.iweventos.com.br/upload/cartas/files/projeto-comercial-cif2023.pdf)). Data e local do COBRAFI e do CIF 2026–2027: **não confirmados** | alto | **depois** do mês 4, com casos para mostrar |
| **Instagram/TikTok de fisioterapeutas** | segundo revisões citadas por estudo da Unicesumar, cerca de 41% dos usuários de redes dizem ser influenciados pelo conteúdo de saúde que veem ([anais EPCC](https://static.even3.com/anais/1250754.pdf)) | orgânico: tempo; pago: ver abaixo | **fábrica de conteúdo** (3.5): o fisioterapeuta posta e a marca vai junto |
| **Meta Ads** | CPL médio no Brasil de cerca de US$ 88,80 entre jul/2025 e jun/2026, com picos e médias inconsistentes ([Superads](https://www.superads.ai/facebook-ads-costs/cost-per-lead/brazil)) | alto e volátil | **só retargeting** de quem visitou a página por um link de paciente |
| **CREFITO-10 (SC)** | não achei programa de convênio ou benefício para empresas (**não confirmado**) | — | propor palestra sobre "Res. 619/2025 e prontuário de telemonitoramento" (conteúdo, não venda) |
| **Grupos de WhatsApp/Telegram, influenciadores de fisio** | não achei dado público de tamanho nem de custo (**não confirmado**) | — | microinfluenciadores regionais no mês 4+, pagos em licença, não em dinheiro (estimativa) |

**CAC estimado** (estimativa minha, com as fontes acima):
- Piloto local: cerca de R$ 0 em dinheiro e algo como 2–3 h de [W] por profissional.
- Cota Bronze em congresso (R$ 5 mil + viagem) com 20 pagantes: ≥ R$ 300 por pagante.
- Meta Ads: com CPL de US$ 89 e 10% de lead para pagante, cerca de US$ 890. Inviável com ticket de R$ 59.

**Conclusão:** o crescimento precisa vir do produto: rodapé, indicação, conteúdo e faculdade.

### 2.4 Ética COFFITO aplicada ao crescimento (Res. 424/2013)

Fonte: [Código de Ética, Res. 424/2013](https://www.normasbrasil.com.br/norma/resolucao-424-2013_254623.html).

| regra | artigo | consequência no produto |
|---|---|---|
| anúncio deve trazer nome, profissão e número no CREFITO | art. 48 | todo conteúdo gerado e o rodapé do portal saem **com nome + CREFITO automáticos**, sem opção de remover |
| proibido divulgar declarações, imagens ou cartas de pacientes para autopromoção | art. 10, V | sem recurso de depoimento; sem "avaliações de pacientes" na página do profissional |
| proibido expor dado que identifique o paciente, inclusive antes/depois | art. 15, V; art. 32, III | o cartão compartilhável do paciente **não** tem nome do paciente nem dado clínico; o profissional **não** pode repostar o cartão |
| proibido prometer terapia infalível | art. 15, III | lint de conteúdo bloqueia "cura", "garantido", "100%", "elimina a dor" |
| proibido serviço gratuito ou a preço ínfimo; preço divulgado de forma incompatível | art. 39; art. 40, I | sem modelo de post "avaliação grátis" nem "promoção"; a fábrica não gera post com preço |
| proibido receber comissão por encaminhar clientes | art. 30, V; art. 40, III | **sem indicação paciente → paciente com recompensa.** A indicação é só entre profissionais e é sobre software (crédito de mensalidade, não dinheiro, para afastar dúvida) |
| captar clientes de forma incompatível com a dignidade profissional | art. 25, IX | sem disparo em massa para contatos do paciente; o compartilhamento é sempre ação manual do paciente |

A Res. COFFITO 532/2021 permitiria divulgar imagem de procedimento com TCLE prévio, segundo [anais EPCC](https://static.even3.com/anais/1250754.pdf). Não li o texto oficial (**não confirmado**). Mesmo que permita, a F1 não gera conteúdo com imagem de paciente.

---

## 3. Especificação

### 3.1 Planos e preço (proposta, **estimativa a validar no piloto**)

| plano | preço | limite | para quem |
|---|---|---|---|
| **Grátis** | R$ 0 | até **5 pacientes ativos**; envio do programa e do lembrete pelo **WhatsApp do próprio profissional** (botão `wa.me` com texto pronto, custo zero para nós); biblioteca, portal, jogo e painel completos | experimentar com pacientes reais |
| **Autônomo** | **R$ 59/mês** ou **R$ 588/ano** (= R$ 49/mês) | pacientes ativos ilimitados; **lembrete diário automático** pelo WhatsApp da clínica (módulo Whatsapp, driver `whatsmeow`, um número por clínica — ADR 0437 §13.1; não é a API oficial); protocolos-modelo próprios; questionários | público de entrada |
| **Clínica** | **R$ 49/prof./mês** (mínimo 2) ou anual com 2 meses grátis | tudo do Autônomo + painel da clínica + protocolos compartilhados | 2–10 profissionais |
| **Estudante / recém-formado** | **50% off por 12 meses** | estudante: plano Grátis com 10 pacientes (clínica-escola); recém-formado: CREFITO há ≤ 24 meses | semente |
| **Fundador piloto** | **50% off enquanto a assinatura estiver ativa**, só para os 15–20 do piloto | — | recompensa de quem ajudou a construir |
| **+ Dinheiro (oimpresso)** — F3 | **+ R$ 29/mês** (estimativa) | pacotes, cobrança Pix/cartão, **NFS-e**, **Receita Saúde** | profissional PJ ou PF que hoje emite nota e recibo à mão |

Por que estes números:
- **Limite em pacientes ativos, não em programas.** É o que o Kynesia usa (50 no Flow) e acompanha o valor recebido. Limitar programas pune o bom uso.
- **5 grátis, não 10.** É estimativa: com 5 o profissional sente o valor; a partir do 6º paciente o painel de risco faz falta e o upgrade acontece no momento de maior valor.
- **O que puxa o upgrade é o lembrete automático, não um corte de função.** Ele custa dinheiro real (a ADR cita cerca de R$ 0,05 por mensagem de utilidade; 30 lembretes/mês/paciente ≈ R$ 1,50 por paciente por mês, **estimativa**). E ataca o esquecimento, causa nº 1 de abandono. No grátis o profissional continua lembrando à mão, que é exatamente a dor que ele quer tirar.
- **R$ 59 fica entre o Kynesia (49,90) e o Vedius/ZenFisio (79).** O anual a R$ 49 empata com a âncora mais baixa do mercado.
- **Receita Saúde:** profissional de saúde pessoa física é obrigado ao recibo digital desde jan/2025, e o fisioterapeuta está incluído ([Contabilizei](https://www.contabilizei.com.br/contabilizei-responde/o-que-e-receita-saude/)). Junto com a NFS-e para quem é PJ, é o que justifica cobrar a mais. O ZenFisio já cobra NFS-e à parte ([comparativo](https://www.physitrack.com/pt/insights/melhor-software-fisioterapia-brasil-comparativo)).
- **Trial do pago:** 14 dias do Autônomo **sem cartão**, que ativa sozinho quando o profissional cadastra o 6º paciente. No fim, volta ao Grátis em vez de bloquear. Sem cartão converte menos (25% contra 60%, [InnerTrends](https://www.innertrends.com/blog/saas-free-trial-benchmarks)), mas no Brasil a exigência de cartão espanta o autônomo (estimativa). Revisar depois do piloto.

### 3.2 Loop viral pelo paciente

```
Profissional envia programa ──► Paciente abre portal (marca do profissional)
        ▲                               │
        │                               ├─► rodapé: "Programa de Dra. Ana · CREFITO-10 12345-F
        │                               │            · feito com <Marca>  ·  É fisioterapeuta? Conheça"
        │                               │
        │                               └─► paciente sobe de nível ─► cartão de conquista (opcional, ação do paciente)
        │                                         "Nível 5 · Base Firme · 21 dias de constância"
        │                                         + nome/CREFITO do profissional, sem dado clínico
        │                                                        │
Colega fisioterapeuta ◄── página /fisio?ref=<business> ◄─────────┘ (família, amigos, outros fisios)
cadastra; o profissional de origem ganha crédito (3.3)
```

Regras:
- O rodapé aparece no portal e na página pública do cartão. **Não aparece nas mensagens de WhatsApp.** Conteúdo promocional dentro de template de utilidade pode levar a Meta a reclassificar a mensagem como marketing, que é mais cara (política da Meta **não confirmada** nesta pesquisa; validar antes).
- O cartão é imagem gerada no servidor com os tokens do DS v6 (roxo 295 e `--vip` para a medalha). Não leva nome nem foto do paciente, nem dor, diagnóstico ou região do corpo. Leva o nível, o nome do nível e os dias de constância. O texto de compartilhamento é escrito pelo paciente.
- O compartilhamento usa o share nativo do celular (`navigator.share`). Não há disparo automático para contatos.
- Os parâmetros `ref` e `utm` atribuem o cadastro ao business de origem. Há consentimento de marketing separado (ADR §7), mas o cartão é ato do paciente, então não exige consentimento da clínica.
- O profissional **não** vê nem baixa os cartões dos pacientes para repostar (art. 10, V e 15, V).

### 3.3 Indicação entre profissionais

- **Dois lados, automático, com teto** (modelo Dropbox): quem indica ganha **1 mês grátis** por indicado que pagar a primeira mensalidade. O indicado ganha **o primeiro mês com 50% off**. Teto: 12 meses de crédito por ano.
- O link de indicação existe desde o cadastro, sem precisar se inscrever. Fica em Configurações → "Convide um colega" e no e-mail da 3ª semana.
- **Crédito em mensalidade, nunca dinheiro.** Fica longe do art. 30, V e simplifica o fiscal (estimativa; validar com contador).
- Antifraude: o CREFITO do indicado precisa ser único na base, e o crédito só sai depois do primeiro pagamento compensado. Autoindicação é bloqueada quando o CNPJ/CPF de cobrança é o mesmo.
- Programa de afiliados para professores, clínicas-escola e microinfluenciadores (mês 4+): crédito acumulado ou repasse de 20% por 12 meses, só via contrato. O valor é **estimativa**; referência de mercado: Jane, US$ 120+ por clínica.

### 3.4 Onboarding de 10 minutos: "primeiro paciente no primeiro dia"

| min | passo | detalhe |
|---|---|---|
| 0–1 | cadastro | nome, WhatsApp, CREFITO (formato `NNNNNN-F` + região), senha. Sem CNPJ, sem cartão |
| 1–2 | marca | foto e nome de exibição. Mostra ao vivo a prévia do portal que o paciente vai ver (a hora do "uau") |
| 2–4 | **paciente de exemplo = você** | o sistema cria o paciente "Você (teste)" com um protocolo-modelo de lombar. O profissional abre o portal no próprio celular, faz o check-in e ganha os primeiros XP. Ele vive a experiência do paciente |
| 4–7 | primeiro paciente real | só nome + WhatsApp + queixa. Escolhe um protocolo-modelo (joelho, ombro, lombar, tornozelo, cervical) e ajusta séries |
| 7–9 | enviar | botão "Enviar pelo WhatsApp". No Grátis abre o `wa.me` do profissional com o link + PIN; no pago, envia pelo número oficial |
| 9–10 | próximo passo | checklist visível: ✔ marca ✔ teste ✔ 1º paciente ☐ 3 pacientes ☐ primeiro check-in recebido ☐ convidar colega |

Regras: o profissional pode pular qualquer passo, e o wizard nunca pede dado que não seja usado na hora. O consentimento de teleatendimento do paciente é pedido no portal, como já previsto no T-07.

**Ativação** = o profissional recebe o **primeiro check-in de um paciente real** em até 7 dias. Esse é o evento que importa, mais que o cadastro.

### 3.5 Fábrica de conteúdo do fisioterapeuta

O que é: na Biblioteca, o botão "Criar post" em cada exercício ou protocolo gera:
- **Carrossel** (4–6 cards 1080×1350): capa com a pergunta ("Dor no joelho ao subir escada?"), 2–4 cards de orientação com a ilustração da biblioteca, card final com nome + CREFITO + "agende uma avaliação" (sem preço).
- **Roteiro de reels** (30–45 s): o profissional grava a si mesmo. O sistema entrega o roteiro e as legendas.
- **Legenda** com hashtags regionais (#fisioterapiatubarao etc.).

Regras de conformidade, em um lint que bloqueia a exportação:
1. Nome, profissão e CREFITO em todo card final (art. 48). Não podem ser removidos.
2. Lista de termos bloqueados: cura, curar, garantido, 100%, elimina, milagre, antes e depois, definitivo, sem dor para sempre (art. 15, III).
3. Sem preço, "grátis", "promoção" ou "desconto" (art. 39, 40, I).
4. Nenhum campo aceita nome de paciente nem foto de terceiro. O upload de foto só aceita a do próprio profissional, com aviso.
5. Texto fixo no último card: "Conteúdo educativo. Não substitui avaliação individual." É orientação ao público (art. 51).
6. Marca do produto: discreta, "feito com <Marca>" no canto do último card. O profissional pode tirar no plano pago (é recompensa de plano; no grátis fica).

Dependência legal: as ilustrações da biblioteca precisam de **licença que permita uso em redes sociais por terceiros**. Ilustração própria ou gerada com direitos cedidos. Verificar antes do seed (T-04 da F1).

Calendário: 12 modelos sazonais pré-prontos (ex.: "volta às corridas em março", "dor nas costas no home office"). IA via Prism só **reescreve a legenda no tom do profissional**. Não gera conduta clínica (ADR §5).

### 3.6 Piloto: 15–20 fisioterapeutas em 8 semanas

**Recrutamento** (ordem de esforço crescente):
1. Rede pessoal de [W] em Tubarão (fisioterapeutas que já atendem a família ou a equipe). Meta: 3–5.
2. Indicação deles ("quem você conhece que manda exercício por foto no WhatsApp?"). Meta: 5.
3. Clínica-escola e professores de UNESC (Criciúma), UDESC/CEFID (Florianópolis) e Unisul Tubarão (a confirmar): convite para egressos recentes. Meta: 5.
4. Busca local no Instagram/Google Maps de consultórios de ortopedia em Tubarão, Laguna, Criciúma e Florianópolis, com mensagem direta e oferta de fundador. Meta: 5.

**Perfil:** autônomo ou consultório de 1–3 profissionais, ortopedia, atendimento particular, ≥ 10 pacientes ativos por semana, que hoje manda exercício pelo WhatsApp.

**Oferta:** uso grátis completo por 8 semanas + preço de fundador. Em troca: 3 entrevistas de 20 min (semanas 1, 4 e 8) e um grupo de WhatsApp com [W].

**O que medir** (instrumentado, sem planilha manual):

| métrica | definição | meta para ampliar (**estimativa**) |
|---|---|---|
| ativação | % de profissionais com 1º check-in de paciente real em ≤ 7 dias | ≥ 70% |
| tempo até o 1º envio | minutos entre o cadastro e o 1º programa enviado | mediana ≤ 15 min |
| pacientes por profissional | pacientes ativos (programa vigente + ≥ 1 check-in em 7 dias) na semana 4 | ≥ 5 |
| adesão do paciente | check-ins ÷ dias prescritos, média 7 d | ≥ 60% (a literatura da ADR fala em 30–50% de não adesão sem apoio) |
| retenção do paciente | % de pacientes com check-in na semana 4 entre os que começaram na semana 1 | ≥ 50% |
| retenção do profissional | % com ≥ 1 programa enviado ou painel aberto na semana 8 | ≥ 65% |
| valor percebido | pergunta "como se sentiria se não pudesse mais usar?", % "muito desapontado", semana 8 | ≥ 40% (heurística de mercado, fonte não verificada aqui) |
| disposição a pagar | % que aceita o preço fundador na semana 8 | ≥ 50% |
| sinal viral | cadastros com `ref` de portal ou de indicação | ≥ 3 no piloto |
| segurança | alertas de dor ≥ 7 vistos pelo profissional em ≤ 24 h | 100% |

**Decisão na semana 8:**
- **Ampliar** se ativação, retenção do profissional e disposição a pagar baterem a meta.
- **Iterar 4 semanas** se a ativação for boa e a retenção ruim (problema de valor contínuo).
- **Parar e repensar** se a ativação for < 40% (problema de proposta ou de atrito).

### 3.7 Marca: nome próprio para o fisioterapeuta?

**Sim, com marca de módulo, sem produto separado.** Isso respeita a decisão de [W] na ADR §3.1 e §10:
- O contrato, a cobrança e o login continuam no oimpresso. A seção do menu continua "Fisio".
- O fisioterapeuta e o paciente veem um **nome próprio** no portal, no rodapé, no cartão e na página de aterrissagem: "<Marca>, do oimpresso".

O motivo é que "oimpresso" remete a gráfica. Para o fisioterapeuta ele não diz nada, e para o paciente confunde. A ADR já previa o rodapé "feito com oimpresso"; a proposta é trocar por "feito com <Marca>".

| nome | ideia | colisão encontrada na web | risco no INPI (avaliação, não consulta) |
|---|---|---|---|
| **Fiz Hoje** | frase que o paciente diz e posta; ação do check-in | nenhum app de saúde com o nome; existe um produto "Fiz" no Product Hunt, de outra área ([Product Hunt](https://www.producthunt.com/products/fiz)) | médio-baixo; expressão comum, mas distintiva para software de saúde |
| **Prescreve** | nome do canvas atual ("Prescreve Pro") | nenhum app com o nome nas buscas (App Store/web) | **alto**: termo descritivo do serviço; o INPI tende a negar sinal genérico ou descritivo (art. 124, VI, LPI) |
| **Retoma** | retomar o movimento | nenhum app de reabilitação com o nome nas buscas | médio; verbo comum |
| **Fisiodia** | fisio + todo dia | nenhum resultado | médio; "Fisio" é prefixo muito usado em clínicas e pode haver marca de clínica no INPI |
| **Movidia** | movimento + dia | só uma empresa de vídeo extinta de 2008 ([TechCrunch](https://techcrunch.com/2008/10/21/movidia-raises-14-million-for-mobile-video-processing-technology/amp/)) | **médio-alto**: semelhança fonética com **Movida** (aluguel de carros), marca de alto renome; risco de oposição |

**Descartados:**
- **Elo Fisio:** já é uma clínica em Florianópolis ([Wellhub](https://wellhub.com/pt-br/search/partners/elo-fisio-saude-em-movimento-florianopolis/)).
- **Constância:** palavra comum, sem distintividade.

**Recomendação:** **Fiz Hoje** como marca para o paciente e o profissional. Ela vira hashtag (#fizhoje) e frase do cartão ("Fiz hoje · 21 dias"). Manter "Prescreve" só como nome interno da tela de montagem.

**Roteiro INPI (fazer antes de imprimir qualquer coisa):** [busca.inpi.gov.br](https://busca.inpi.gov.br) → Marcas → pesquisa por radical e por expressão exata nas classes NCL **9** (software), **42** (SaaS), **44** (serviços de saúde) e **41** (educação/conteúdo). Depois, depósito em nome da empresa do oimpresso, nas classes 9 e 42 no mínimo. Checar também o domínio `.com.br` e o @ no Instagram. **Nada disso foi verificado nesta sessão.**

---

## 4. Riscos e o que fica fora

| risco | mitigação |
|---|---|
| grátis caro (WhatsApp oficial) | no grátis o envio sai do WhatsApp do próprio profissional; o lembrete oficial é só no pago |
| cartão ou rodapé lido como publicidade irregular do profissional | sem dado do paciente, sem promessa, com CREFITO; o profissional não reposta; validar o texto com o CREFITO-10 antes do lançamento aberto |
| rodapé no template de WhatsApp reclassificado como marketing | rodapé só no portal e no cartão, nunca no template |
| indicação vista como "comissão" | crédito de software só entre profissionais; nada para paciente |
| piloto contaminado por amizade (todo mundo "gosta") | medir comportamento (check-ins, retenção), não opinião; a pergunta de valor percebido é só complemento |
| desconto de estudante abusado | verificação por carteira estudantil ou data de inscrição no CREFITO, com upload e revisão manual no início |
| marca descritiva negada no INPI | preferir nome distintivo (Fiz Hoje) e consultar antes de gastar com material |
| ilustração da biblioteca sem direito de uso em redes | licença verificada no T-04 da F1; sem isso, a fábrica de conteúdo não sai |

**Fora desta etapa:** mídia paga além de retargeting, estande em congresso nacional (só depois do mês 4), app nativo, programa de pontos para paciente, marketplace "encontre um fisioterapeuta" (o ZenFisio tem o BuscaFisio, mas exige base grande e moderação de publicidade), qualquer depoimento de paciente.

---

## 5. Métricas de sucesso (painel interno de crescimento)

- **Norte:** pacientes ativos por semana em todos os businesses. Captura uso real e cresce com profissionais × pacientes.
- **Funil:** cadastro → ativação (1º check-in em ≤ 7 d) → 5 pacientes ativos → pagante → 3 meses pagando.
- **Loop:** cadastros por origem (`ref` de portal, de cartão, de indicação, conteúdo, direto); coeficiente viral (cadastros por indicação ÷ pagantes ativos).
- **Receita:** MRR, % anual, churn mensal de pagantes (meta < 4%, **estimativa**), ARPA com e sem "+ Dinheiro".
- **Saúde:** % de alertas de dor vistos em ≤ 24 h; zero conteúdo exportado que falhe no lint.

---

## 6. Tarefas para o Claude Code (depois da F1; ordem por dependência)

| id | tarefa | blocked_by | DoD |
|---|---|---|---|
| L-01 | **Planos e limites do Fisio**: plano por business (grátis, autônomo, clínica, estudante, fundador), limite de pacientes ativos, `fisio_plan_features` com reuso do pacote/assinatura existente do oimpresso (Superadmin), trial de 14 dias que dispara no 6º paciente e volta ao Grátis no fim | F1 T-12 | teste: o 6º paciente no grátis ativa o trial; com o trial vencido, o 6º fica somente leitura e nada é apagado (guarda de 20 anos) |
| L-02 | **Envio no grátis via `wa.me`** (texto pronto com link + PIN) e envio oficial no pago, com o mesmo job do T-09 condicionado ao plano | L-01 | grátis nunca chama a API oficial; pago mantém um lembrete por paciente por dia |
| L-03 | **Eventos de crescimento**: tabela `fisio_growth_events` (business_id, evento, ref, utm, at) para cadastro, marca, teste, 1º paciente, 1º envio, 1º check-in, 5 ativos, upgrade, indicação, post exportado; painel interno só para superadmin | L-01 | as métricas da §3.6 saem do banco sem planilha; nenhum dado clínico no evento |
| L-04 | **Onboarding de 10 min**: wizard da §3.4, paciente de exemplo "Você (teste)" excluído das métricas e da contagem do plano, checklist persistente | L-03 | E2E: cadastro → 1º programa enviado em < 10 min sem campo obrigatório extra; paciente teste não conta no limite |
| L-05 | **Marca no portal + rodapé + página `/fisio`** com `ref`/`utm`; nome e CREFITO obrigatórios no portal; página de aterrissagem pública com texto da ADR §5 ("apoio ao fisioterapeuta…") | L-03, decisão de marca | portal sem CREFITO não publica; cadastro via `ref` gera evento atribuído |
| L-06 | **Cartão de conquista**: imagem gerada no servidor (tokens DS v6), sem dado do paciente, share nativo, página pública do cartão com rodapé; o profissional não tem acesso ao cartão | L-05, F1 T-08 | teste de snapshot: nenhum nome, dor ou região na imagem; rota sem PIN não expõe dado do paciente |
| L-07 | **Indicação entre profissionais**: código automático, crédito nos dois lados depois do 1º pagamento compensado, teto de 12/ano, CREFITO único, bloqueio de autoindicação | L-01, L-03 | testes de fraude (mesmo CPF/CNPJ, CREFITO repetido); crédito aplicado no RecurringBilling |
| L-08 | **Fábrica de conteúdo**: carrossel, roteiro e legenda a partir de exercício/protocolo; lint ético bloqueante (§3.5); IA via Prism só para reescrever legenda | L-05, licença das ilustrações | lint com testes para cada termo bloqueado; card final sempre com CREFITO; exportação PNG/ZIP |
| L-09 | **Plano estudante/recém-formado**: upload de comprovante, fila de revisão manual, expiração automática em 12 meses | L-01 | expirou → volta ao preço cheio com aviso de 15 dias |
| L-10 | **Pesquisa in-app do piloto**: pergunta de valor percebido e de disposição a pagar nas semanas 4 e 8 | L-03 | respostas no painel interno, ligadas ao business |
| L-11 | **Cobrança anual e "+ Dinheiro"**: anual com desconto e NFS-e do próprio oimpresso para o assinante, via RecurringBilling + NFSe | L-01 | assinatura anual gera NFS-e; o add-on só aparece com a F3 pronta |

**Não-código (para [W]):** consulta ao INPI (§3.7); validar com o CREFITO-10 o texto do rodapé, do cartão e do lint; confirmar com o contador o tratamento fiscal do crédito de indicação; recrutar o piloto (§3.6); verificar a política de categorias de template da Meta.

---

## 7. Fontes

- Concorrentes e preços: [Physitrack, comparativo Brasil (04/10/2026)](https://www.physitrack.com/pt/insights/melhor-software-fisioterapia-brasil-comparativo) · [Physitrack pricing](https://www.physitrack.com/pt/physitrack-pricing) · [Vedius](https://vedius.com.br/mercado-fisioterapia/)
- Mercado: [Levantamento georreferenciado de fisioterapeutas, RSD Journal (dados até 2020)](https://rsdjournal.org/rsd/article/download/22392/19871/270164)
- Product-led: [Dropbox, Referral Rock](https://referralrock.com/blog/dropbox-referral-program/) · [Jane, afiliados (PartnerStack)](https://market.partnerstack.com/page/janesoftware) · [Jane, guia de indicação](https://jane.app/guide/referral-commission) · [Calendly, Sacra](https://sacra.com/chat/h/bdf9ad15-84ac-49ac-b236-07cbe1e52d0b/) · [Freemium, Monetizely](https://www.getmonetizely.com/articles/freemium-conversion-rate-the-key-metric-that-drives-saas-growth-3588c) · [Trial, InnerTrends](https://www.innertrends.com/blog/saas-free-trial-benchmarks)
- Canais: [Projeto comercial CIF 2023, Florianópolis](https://attitudepromo.iweventos.com.br/upload/cartas/files/projeto-comercial-cif2023.pdf) · [CPL Meta Brasil, Superads](https://www.superads.ai/facebook-ads-costs/cost-per-lead/brazil) · [Anais EPCC Unicesumar, redes e fisioterapia](https://static.even3.com/anais/1250754.pdf) · [UDESC/CEFID, grade de Fisioterapia](https://udesc.br/arquivos/cefid/id_cpmenu/1012/GRADE_FIS_17224523363496_1012.pdf) · [UNESC Fisioterapia, Pravaler](https://www.pravaler.com.br/faculdades/unesc/graduacao/fisioterapia/)
- Ética e fiscal: [Res. COFFITO 424/2013](https://www.normasbrasil.com.br/norma/resolucao-424-2013_254623.html) · [Receita Saúde, Contabilizei](https://www.contabilizei.com.br/contabilizei-responde/o-que-e-receita-saude/)
- Marca (colisões): [Elo Fisio, Wellhub](https://wellhub.com/pt-br/search/partners/elo-fisio-saude-em-movimento-florianopolis/) · [Movidia, TechCrunch 2008](https://techcrunch.com/2008/10/21/movidia-raises-14-million-for-mobile-video-processing-technology/amp/) · [Fiz, Product Hunt](https://www.producthunt.com/products/fiz)
- **Não confirmados:** número de fisioterapeutas em 2025–2026; datas do COBRAFI e do CIF 2026–2027; convênios do CREFITO-10; tamanho e custo de grupos de WhatsApp e de influenciadores de fisio; texto oficial da Res. COFFITO 532/2021; política de reclassificação de templates da Meta; curso de Fisioterapia da Unisul Tubarão; INPI (acesso bloqueado na sessão).

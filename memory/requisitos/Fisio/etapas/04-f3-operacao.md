# Etapa F3 — Operação e Dinheiro: agenda, teleconsulta, pacotes, cobrança, NFS-e e Receita Saúde

> Módulo Fisio (ADR 0437). Pesquisa feita em 2026-10-17. Toda afirmação factual tem fonte na §7. "Não confirmado" quer dizer que não achei fonte. "Estimativa" quer dizer que o número é meu.
> A cota de buscas web acabou antes de três pontos: TISS/ANS, a região de dados do LiveKit Cloud e o layout do lote do Receita Saúde. Eles estão marcados como pendência.

---

## 1. Objetivo e meta de nota

**Objetivo.** Fechar o ciclo do consultório sem trocar de sistema:
**agendar → confirmar pelo WhatsApp → atender (presencial ou vídeo) → consumir sessão do pacote → cobrar → NFS-e (PJ) ou recibo Receita Saúde (PF) → repasse ao profissional.**

O fisioterapeuta não pode precisar do Vedius ou do ZenFisio do lado do Fisio. Essa é a condição para o "jogo de constância" da F1 não virar "mais um app" que ele paga além do sistema de gestão.

| item da grade | hoje | meta F3 | referência | por que não 10 |
|---|---|---|---|---|
| Agenda | 5 | **8** | Vedius 9 | Vedius e Jane têm anos de polimento. No primeiro ciclo não entram multiunidade nem convênio |
| Teleconsulta | 4 | **8** | Physitrack 9 | vídeo no navegador, ligado ao prontuário e ao portal. Sem app nativo e sem sala de espera com triagem |
| Pacotes e cobrança | forte | **9** | nenhum local junta pacote + Pix Automático + repasse | convênio/TISS fica fora |
| NFS-e e Receita Saúde | forte | **9** | ZenFisio (NFS-e é módulo pago à parte) | Receita Saúde não tem API pública confirmada. O que existe é arquivo de lote |

---

## 2. O que os melhores fazem: copiar e evitar

### 2.1 Agenda

| produto | o que faz bem (com fonte) | copiar | evitar |
|---|---|---|---|
| **Vedius** (R$ [redacted Tier 0]/mês; equipe R$ [redacted Tier 0] por colaborador) | confirmação automática por WhatsApp que o paciente responde; recorrência ilimitada; cada atendimento entra sozinho no financeiro | confirmação WhatsApp com resposta de um toque; atendimento que vira lançamento | não publica lista de espera, pacotes, salas, NFS-e nem teleconsulta: são os buracos a explorar |
| **ZenFisio** (R$ [redacted Tier 0] / 179 / 439) | "Repetir" com renovação ao fim da série; pacote novo ou existente vinculado ao agendamento, com o financeiro seguindo o pacote; sala por agendamento e filtro por sala; status personalizado que trava depois de "realizado"; bloqueio de horário; lembrete por SMS | pacote vinculado ao agendamento; status travado depois do atendimento registrado; renovação de série | SMS no lugar de WhatsApp; NFS-e como módulo pago à parte |
| **Ninsaúde** | confirmação por SMS/WhatsApp com 1 ou 2 dias de antecedência, mensagem por perfil/profissional/unidade (diz reduzir faltas em até 68%, número do próprio fornecedor); agendamento online com horário, preço e serviço por profissional; recorrência diária, semanal, mensal e anual para sessões e pacotes | antecedência configurável; agenda online com preço por serviço | — |
| **Jane App** | lista de espera automática: ao cancelar ou mover, avisa só quem tem preferência compatível e segura a vaga num período de acesso exclusivo, com espera de 15 min se a consulta é em menos de 24 h e de 2 h se é em mais; política de cancelamento exibida no agendamento online; sinal (depósito) total ou parcial na reserva; cartão salvo | **lista de espera com casamento de preferência e janela de exclusividade**: é o diferencial que nenhum local publica | lista de espera só nos planos caros (Thrive) |
| **AppBarber Cliente** (barbearia; nota 4,9 com cerca de 110 mil avaliações na App Store) | agendar e reagendar em um toque; sinal por Pix; renovação de assinatura pelo app com aviso de fim; pesquisa depois do atendimento; histórico com comprovante; preferência para o atendimento ("prefiro não conversar") | reagendar em um toque, sinal Pix, saldo e renovação do pacote, pesquisa pós-sessão (privada), recado de preferência | login a cada acesso (reclamação recorrente nas avaliações); lista de outras barbearias para o cliente (reclamação de um profissional); promoções e venda de serviço extra no checkout |
| **Booksy** | sinal em % por serviço e taxa de falta com cartão salvo; aviso mínimo para cancelar grátis; clientes de confiança dispensados do sinal; bloqueio de cliente problemático | dispensa de sinal para quem é de confiança (aqui: pacote ativo); bloqueio por faltas | taxa cobrada sozinha (ver A8: aqui é sempre sugerida e confirmada) |
| **Cliniko** (US$ 45/mês para 1 profissional; SMS cerca de US$ 0,10 por mensagem) | recorrência, agenda online pelo portal, telessaúde dentro do registro do atendimento | vídeo nascendo do agendamento, sem ferramenta de terceiros | salas e recursos compartilhados fracos; SMS cobrado à parte |
| **Feegow** | integra com plataformas de WhatsApp (Digisac, Blip) para lembrete | — | recursos e preço **não confirmados** |

**Síntese.** A régua é Vedius (WhatsApp + financeiro automático) com mais quatro coisas que os locais não mostram: **pacote que consome sessão, lista de espera inteligente, política de falta aplicada sozinha e teleconsulta nascida do agendamento.**

### 2.2 Teleconsulta

- **Physitrack** tem telessaúde nativa. **Vedius** não publica telessaúde nativa. **ZenFisio** cita videoconferência na agenda, sem detalhe. **Kynesia** não confirma recurso nativo. Fonte: comparativo da própria Physitrack.
- **Cliniko** liga o vídeo ao registro do atendimento. Copiar: o link de vídeo nasce do agendamento e a evolução abre na mesma tela.

### 2.3 Cobrança e nota

- O ZenFisio cobra a NFS-e como módulo à parte, com pacotes mensais de notas. O Fisio tem NFS-e no núcleo, e essa é uma vantagem a mostrar no preço.
- Nenhum concorrente local pesquisado publica Pix Automático, repasse ao profissional nem lote do Receita Saúde. **Não confirmado** que não tenham, só que não publicam.

---

## 3. Especificação

### 3.1 Princípios

1. **Não duplicar o núcleo.** Paciente é `contact`. Dinheiro mora no **Financeiro**. Recorrência mora no **RecurringBilling**. Nota mora no **NFSe**. Mensagem mora no **Whatsapp**. O Fisio guarda só o que é clínico ou de agenda e aponta para o núcleo pelo id.
2. **Agenda, teleatendimento e cobrança não são SaMD** (P&R RDC 657, P44 e P71, já citado na ADR §5). Nada nesta fase decide conduta.
3. **Cada atendimento, presencial ou remoto, gera registro no prontuário** (Res. 414/2012 e Res. 619/2025 art. 5º).
4. **Funciona para PF e para PJ.** O autônomo pessoa física é o público de entrada (ADR §2). As regras de Pix Automático e de Receita Saúde mudam conforme ele tenha ou não CNPJ, e o produto precisa se adaptar sozinho (§3.4.1).

### 3.2 Agenda

#### Funcionalidades

| # | função | regra |
|---|---|---|
| A1 | **Agenda por profissional e por sala/aparelho** (visões dia, semana e lista) | o recurso (sala, maca, Pilates Reformer, esteira) é opcional; conflito de recurso bloqueia como conflito de profissional |
| A2 | **Tipos de atendimento** (avaliação, sessão, reavaliação, teleconsulta, grupo) com duração, preço padrão e modalidade | a teleconsulta já cria a sala de vídeo (§3.3) |
| A3 | **Série recorrente** ("2x por semana, seg/qui 8h, 10 sessões") | gera ocorrências; editar "esta / esta e as próximas / todas"; ao fim da série, sugerir renovação (como o ZenFisio); por padrão a série vem do `sessions_per_week` do plano terapêutico da F1 |
| A4 | **Encaixe** | permite sobrepor com confirmação explícita e marca o horário como "encaixe" (aparece no relatório) |
| A5 | **Bloqueio de horário** (almoço, curso, férias) | não é agendamento; aparece na agenda online como indisponível |
| A6 | **Confirmação por WhatsApp** D-1 (configurável de D-2 a 2 h antes) com botões *Confirmo · Preciso remarcar · Cancelar* | resposta muda o status sozinha; sem resposta até X horas, segundo lembrete; reaproveita o job idempotente da F1 (T-09); template de **utilidade** |
| A7 | **Lista de espera inteligente** (inspirada no Jane) | paciente entra com preferência (dias, turnos, profissional, modalidade); ao cancelar ou remarcar, o sistema espera 15 min (vaga em menos de 24 h) ou 2 h (mais de 24 h), avisa por WhatsApp só os compatíveis e segura a vaga por uma janela de exclusividade configurável (padrão: 30 min, estimativa); o primeiro que confirmar leva |
| A8 | **Faltas e política de cancelamento** | política por clínica: prazo mínimo de cancelamento (padrão 24 h) e o que acontece com falta ou cancelamento tardio: *nada · consome sessão do pacote · cobra taxa X*. Aplicação **sugerida**, com o profissional confirmando com um toque (falta por motivo de saúde não pode virar cobrança automática). Política exibida no agendamento online e na confirmação |
| A9 | **Agenda online para o paciente** (link público da clínica e link no portal do paciente) | mostra só tipos liberados para autoagendamento; paciente novo informa nome + WhatsApp; paciente já cadastrado entra pelo link+PIN da F1; opção de **sinal via Pix** para paciente novo (como o depósito do Jane) |
| A10 | **Status** agendado → confirmado → chegou → em atendimento → realizado / faltou / cancelado (paciente ou clínica) / remarcado | "realizado" exige evolução assinada no prontuário (Res. 414) e trava o status, como o ZenFisio |
| A11 | **Sessão em grupo** (até N pacientes, uso de aparelho) | cada participante consome a própria sessão; já serve ao projeto de estúdio de pilates |
| A12 | **Painel do dia** | quem confirmou, quem não respondeu, quem tem pacote vencendo, quem tem débito; tudo no AppShellV2 com "Apps Vinculados" |
| A13 | **Ligação com a constância (F1)** | quem falta e quem não faz o programa domiciliar entra junto na fila de atenção do Painel; esse sinal combinado é exclusivo do Fisio |
| A14 | **App do paciente instalável** (ADR 0437 §13.7) | o próprio portal, instalável na tela inicial e publicado nas lojas (§14 nº 11 da ADR); link + PIN continua valendo para quem não instala; atalhos: exercícios de hoje, agendar, minhas consultas |
| A15 | **Entrada que lembra o aparelho** | primeira vez: código de 6 dígitos pelo canal de WhatsApp da clínica (validade curta, tentativas limitadas como o PIN da F1); depois, digital ou rosto do celular (chave de acesso do próprio aparelho, nada de biometria no servidor); sem senha; "sair deste aparelho" no app e "desconectar aparelhos" na ficha do paciente; canal `wa.me` manual → entrada pelo link + PIN |
| A16 | **Marcar para dependente** | o responsável marca para filho ou pai; menor de idade exige consentimento do responsável legal (LGPD art. 14); dependente adulto só com autorização registrada do próprio dependente; o prontuário continua sendo do dependente |
| A17 | **Sinal por Pix com isenção** | estende A9: sinal opcional em % por tipo (padrão desligado), abatido no valor da consulta e devolvido se cancelar dentro do prazo da política; **quem tem pacote ativo não paga sinal** (equivalente ao "cliente de confiança" do Booksy) |
| A18 | **Saldo e renovação do pacote no app** | saldo de sessões sempre visível ao marcar; a renovação só aparece quando o profissional indica continuar no plano terapêutico, nunca como oferta automática; paga por Pix pelo gateway do núcleo (F3-T09) |
| A19 | **Check-in "Cheguei"** | botão no dia, a partir de 1 h antes; muda o status para "chegou" (A10) e avisa a recepção; não substitui a evolução |
| A20 | **Recado ao marcar e nota depois da sessão** | recado opcional do paciente vai para a ficha do atendimento; nota de 1 a 5 depois da sessão é **privada**, só a clínica vê, nunca vira avaliação pública |
| A21 | **Limites do autoagendamento** | quem pode marcar sozinho (com pacote ativo · todos em tratamento · ninguém), tipos liberados, horários abertos para o app, antecedência mínima, até quantos dias à frente, e bloqueio depois de N faltas sem aviso (padrão 2), com desbloqueio pelo profissional |

#### UX
- Tela `Fisio/Agenda` padrão calendário, arrastar para remarcar (dispara nova confirmação WhatsApp), atalho N para novo e teclado J/K na visão lista.
- Criar agendamento em **3 campos**: paciente, tipo, horário. Pacote e sala vêm por padrão do último atendimento.
- Celular primeiro: o autônomo usa a agenda no celular entre um atendimento e outro.
- **App do paciente** (A14 a A21): agendar em 4 passos (para quem e o quê → com quem → quando, com "me avise se abrir vaga" → confere e confirma); "Minhas consultas" com próximas, pacote e histórico. Telas no canvas do módulo, faixa "Etapa 6".
- **Nunca no app do paciente:** promoção, venda extra no checkout, outras clínicas, nota pública.
- DS v6, roxo 295, nenhuma cor de módulo. A cor do status usa os tokens semânticos existentes (`--pos`, `--warn`, `--neg`); confirmar nomes no `DESIGN.md`.

#### Dados (novas tabelas, todas com `business_id`)

| tabela | campos principais |
|---|---|
| `fisio_resources` | business_id, kind (`sala`/`aparelho`), name, capacity, active |
| `fisio_appointment_types` | business_id, name, duration_min, default_price, modality (`presencial`/`teleconsulta`), online_bookable, group_capacity |
| `fisio_appointment_series` | business_id, patient_id, professional_id, rule (json: dias, hora, total), package_id null, starts_on, ends_on |
| `fisio_appointments` | business_id, patient_id, professional_id, type_id, resource_id null, series_id null, starts_at, ends_at, status, is_overbook, source (`clinica`/`online`/`lista_espera`), confirmed_at, confirmation_channel, cancel_reason, cancelled_by, record_id null (evolução que fechou), package_consumption_id null |
| `fisio_waitlist` | business_id, patient_id, preferences (json), professional_id null, modality, status, offered_appointment_id, offer_expires_at |
| `fisio_policies` | business_id, cancel_min_hours, no_show_action, late_cancel_action, fee_amount, version, text (exibido ao paciente) |

Agendamento de profissional diferente vê só a própria agenda quando o perfil for "profissional" (permissão).

**Verificar antes de criar:** se o núcleo (base UltimatePOS) tem `bookings` reaproveitável. A tabela de reservas do POS é pensada para mesa de restaurante e provavelmente não serve, mas a decisão precisa ficar registrada (tarefa F3-T01).

### 3.3 Teleconsulta por vídeo

#### O que a norma exige (e o que não exige)

| exigência | fonte | como o produto cumpre |
|---|---|---|
| Consentimento do paciente ou representante legal | Res. 619/2025 art. 1º, II; Lei 14.510 art. 26-G, I ("livre e esclarecido") | já existe `fisio_consents.purpose = teleatendimento` (F1). Antes de entrar na sala, o portal confere se o consentimento está vigente; se não estiver, pede |
| Direito de recusar e pedir presencial | Lei 14.510 art. 26-A | botão "prefiro presencial" no convite, que remarca como presencial e avisa a clínica |
| Remoto é opcional e não substitui o presencial quando este for indispensável | Res. 619 art. 1º, I e III; Lei 14.510 art. 26-C (decisão do profissional) | o tipo "avaliação" pode ser marcado pela clínica como "só presencial". O produto não impõe regra clínica |
| Registro detalhado no prontuário a cada atendimento | Res. 619 art. 5º | ao encerrar a chamada, abre a evolução com modalidade `sincrona`, canal `video`, início, fim e duração já preenchidos; status "realizado" exige a evolução assinada |
| Infraestrutura adequada e guarda/transmissão com privacidade, confidencialidade e sigilo | Res. 619 art. 4º | WebRTC com criptografia de transporte (DTLS-SRTP), sala com token de uso único e expiração, sem gravação por padrão |
| Plataforma | **a Res. 619 não impõe nenhuma** (art. 4º) | escolha técnica livre (abaixo) |
| Gravação | **a Res. 619 não trata de gravação** | ver decisão abaixo |
| Inscrição secundária para atender outro estado | dispensada (Res. 619 art. 6º; Lei 14.510 art. 26-H) | nenhum bloqueio por UF |
| Obedecer LGPD, Marco Civil, CDC e Lei 13.787 | Lei 14.510 art. 26-G, II | DPA já previsto na ADR §7 |

#### Gravar ou não gravar

**Decisão proposta: não gravar, e não oferecer gravação na F3.**
- Nenhuma norma de fisioterapia exige gravação (Res. 619 é omissa).
- Se gravar, o material passa a integrar o prontuário, com as mesmas regras de guarda e acesso e com consentimento específico, além do da teleconsulta. No caso da fisioterapia, isso significa **20 anos de guarda de vídeo** pela Lei 13.787 (inferência minha a partir da regra de prontuário da ADR §4 e da orientação para telemedicina da Confidata).
- O handoff da F1 já proíbe guardar vídeo do paciente. Manter a regra simplifica LGPD e custo.
- Se [W] quiser gravação no futuro: consentimento separado `gravacao_teleconsulta`, gravação só do áudio por padrão, criptografia em repouso, log de acesso, e custo de armazenamento previsto para 20 anos.

#### Opções técnicas (preços 2025–2026)

| opção | preço publicado | uso típico do Fisio* | prós | contras |
|---|---|---|---|---|
| **LiveKit Cloud** | Build grátis com 5.000 min WebRTC/mês; Ship US$ 50/mês com 150.000 min, excedente US$ 0,0005/min | 5.000 min ≈ **83 consultas de 30 min com 2 pessoas/mês** no plano grátis; no Ship, cerca de US$ 0,03 por consulta excedente | SDK React maduro, simulcast, open source (dá para migrar para self-host sem trocar código) | região de mídia no Brasil **não confirmada** |
| **LiveKit self-host** | só infraestrutura (VPS) | custo de VPS: estimativa, não pesquisado | mídia fica no servidor que a gente escolher (pode ser no Brasil); mesmo SDK | operar TURN, atualização, monitoramento; benchmark oficial só mostra máquina de 16 núcleos |
| **Daily** | 10.000 participante-min/mês grátis; US$ 0,0015 a 0,004 por participante-min; gravação US$ 0,01349/min; add-on HIPAA US$ 500/mês | ~166 consultas grátis/mês | o mais simples de integrar (sala por URL) | preço marcado como "não reverificado" pela fonte; HIPAA não se aplica ao Brasil |
| **Twilio Video** | US$ 0,004 por participante-min (sala de grupo); gravação US$ 0,004/participante-min; composição US$ 0,01/min | ~US$ 0,24 por consulta de 30 min | o fim de vida marcado para dez/2026 **foi revertido** em out/2024 | mais caro; histórico de quase descontinuação |
| **Jitsi (JaaS 8x8)** | 25 MAU grátis; Basic US$ 99/mês com 300 MAU; US$ 0,99 por MAU extra; gravação US$ 0,01/min | cobra por usuário ativo, ruim para autônomo com poucos pacientes | pode embutir em iframe | caro na entrada; self-host Jitsi tem custo de operação não pesquisado |

\* Consulta de 30 min com 2 participantes = 60 participante-min. Cálculo meu a partir dos preços.

**Recomendação.** **LiveKit**, começando no **LiveKit Cloud** (grátis até cerca de 83 consultas por mês somando todos os businesses na F3-piloto; depois Ship US$ 50/mês) e com caminho aberto para **self-host em VPS no Brasil** se o volume crescer ou se a revisão LGPD exigir mídia no país. A LGPD **não exige** guardar dado no Brasil, mas servidor no exterior aciona as regras de transferência internacional (fonte: Confidata). Como não há gravação, o vídeo só **trafega** pelo servidor e não fica guardado. Mesmo assim, a transferência entra no DPA.

Uma camada `TeleconsultaProvider` com **uma** implementação (LiveKit) e nenhuma interface especulativa. Trocar de fornecedor é reescrever essa classe.

#### Vídeo no navegador, sem app, bom em 4G
- O paciente entra pelo **link do portal (link + PIN, F1)** → botão "Entrar na consulta" liberado 10 min antes → teste de câmera e microfone → sala. Sem instalar nada (WebRTC no Chrome, Safari iOS e Android).
- **Perfil para 4G:** simulcast em 3 camadas. Referência de bitrate: 720p ≈ 1,3 Mbps, 360p ≈ 450 kbps, 216p ≈ 180 kbps. O SFU entrega a camada que cabe na conexão de cada um sem prejudicar o outro. Padrão do paciente: publicar no máximo 360p. Áudio com prioridade. Abaixo do limiar, o sistema propõe "continuar só com áudio".
- **Modo exercício:** o profissional pede "vire a câmera / afaste o celular". O layout troca para o vídeo do paciente em tela cheia e a câmera traseira fica acessível. Câmera aqui é **só para o profissional ver**: nenhuma análise automática de movimento, que seria SaMD classe II (ADR §5).
- **Fallback:** se a conexão cair duas vezes, mostrar o link para retomar e um botão "ligar pelo WhatsApp". A ligação pelo WhatsApp acontece fora do sistema, e o registro no prontuário marca canal `whatsapp_chamada`.
- Indicador de qualidade gravado na evolução (queda, duração real), para medir o que funciona.

#### Dados
`fisio_telesessions`: business_id, appointment_id, provider, room_name, patient_token_hash, professional_joined_at, patient_joined_at, ended_at, duration_s, quality (json: quedas, camada média, áudio-only), consent_id. Nenhuma mídia.

### 3.4 Pacotes, cobrança e repasse

#### 3.4.1 Meios de pagamento: o que cada perfil pode usar

| meio | PF autônomo | PJ (clínica/consultório) | observação |
|---|---|---|---|
| **Pix Automático** (lançado em 16/06/2025) | **não**: o recebedor precisa ser PJ com CNPJ ativo (FAQ BCB 1.3 e 1.4) | sim | periodicidade semanal, mensal, trimestral, semestral ou anual; cobrança enviada de 2 a 10 dias antes; valor fixo ou variável com teto opcional definido pelo pagador; retentativa só se a autorização prever; jornada 3 = um QR que paga a primeira e autoriza as próximas. No Asaas exige PJ com CNPJ ativo há pelo menos 6 meses |
| **Pix cobrança** (QR dinâmico com vencimento) | depende do PSP: **não confirmado** para PF | sim | bom para avulso e para cada parcela de pacote |
| **Cartão recorrente** | depende do gateway | sim | alternativa ao Pix Automático para o PF |
| **Boleto** | depende do gateway | sim | baixa prioridade para particular (estimativa) |
| **Dinheiro / Pix manual** | sim | sim | baixa manual no Financeiro |

**Regra de produto.** A ficha do business diz PF ou PJ. Para PF, a tela de pacote não mostra Pix Automático e explica por quê ("disponível quando você tiver CNPJ"). Esse aviso também serve de gancho para a contabilidade parceira.

**Gateway.** Usar o que o **Financeiro/RecurringBilling já integra**. Se já existe Asaas, a API dele tem autorização de Pix Automático (`paymentCreationMode` MANUAL ou SUBSCRIPTION, `retryPolicy`) e evento de elegibilidade. Taxas do Asaas: **não confirmadas** nesta pesquisa.

#### 3.4.2 Pacotes de sessões

| regra | detalhe |
|---|---|
| Pacote = N sessões de um tipo (ou de um grupo de tipos), preço, validade | ex.: 10 sessões, R$ [redacted Tier 0] 90 dias |
| Formas de venda | à vista, parcelado (N títulos no Financeiro) ou **mensalidade** (plano "2x por semana, R$ [redacted Tier 0]/mês" via RecurringBilling, com Pix Automático se PJ) |
| Consumo | cada agendamento "realizado" consome 1 sessão; falta ou cancelamento tardio consomem conforme a política (A8) depois da confirmação do profissional |
| Ledger append-only | `fisio_package_ledger` (crédito na compra, débito por sessão, estorno por adendo). Saldo é **derivado**, igual ao XP da F1 |
| Avisos | "faltam 2 sessões" para o profissional e para o paciente pelo WhatsApp, com link de renovação (Pix) |
| Validade vencida | as sessões ficam bloqueadas, e o profissional pode estender com um toque (registrado) |
| Agenda | a série recorrente (A3) nasce do pacote: 10 sessões, 2x por semana → 10 ocorrências |

#### 3.4.3 Repasse a profissionais da clínica
- Regra por profissional: % ou valor fixo por tipo de atendimento, base "valor recebido" ou "valor faturado".
- O Fisio **calcula** o repasse dos atendimentos realizados e pagos no período e gera **um título a pagar no Financeiro** por profissional. O Financeiro paga, e o Fisio não guarda dinheiro.
- Relatório por profissional: atendimentos, faltas, valor, repasse. Exportação CSV.
- **Split de pagamento** no gateway fica fora da F3 (implica conta no gateway para cada profissional).

#### 3.4.4 Convênios / TISS
**Fica fora da entrada.** O público é atendimento particular (ADR §2). TISS exige guia, autorização prévia por sessão, lote XML e conciliação de glosa. Dá trabalho grande para um público que não é o de entrada. Versão vigente do padrão TISS, valores pagos por sessão e taxa de glosa: **não confirmados** (cota de busca esgotada).
O que entra na F3: campo "convênio / reembolso" no paciente e **recibo para reembolso** (NFS-e ou Receita Saúde com CPF do beneficiário), que é o fluxo real do particular que pede reembolso ao plano.

### 3.5 NFS-e

#### Fatos (2025–2026)
- **LC 214/2025, art. 62:** desde 01/01/2026, todo município precisa **ou** permitir emissão no ambiente nacional **ou** compartilhar com o repositório nacional as notas do emissor próprio. **Não** obriga a empresa a usar o emissor nacional. São Paulo capital e o DF mantêm emissor próprio. Em ago/2025, 1.472 municípios tinham aderido ao convênio, cerca de 70% da emissão.
- **MEI** emite pelo emissor nacional desde 2023 (fisioterapeuta não pode ser MEI).
- **Destaque de IBS/CBS na NFS-e:** obrigatório desde **01/10/2026** pelo Ato Conjunto RFB/CGIBS nº 04/2026 (ampliação em 01/12/2026). Nota sem os campos é rejeitada. Alíquota-teste de 1% (CBS 0,9% + IBS 0,1%), só informativa em 2026. **Simples Nacional: a partir de 01/01/2027.**
- **Código do serviço:** LC 116 item **4.08** (terapia ocupacional, fisioterapia e fonoaudiologia). Código de tributação nacional **04.08.02 = Fisioterapia**. Usar 04.08.01 (terapia ocupacional) é erro comum. ISS devido no município do prestador, alíquota entre 2% e 5% conforme o município.
- **NBS da fisioterapia: 1.2301.92.00**, que está no Anexo III da LC 214 com **redução de 60% de IBS/CBS** (art. 130), desde que a NBS esteja certa.
- **Simples Nacional:** fisioterapia fica no Anexo III (a partir de 6%) se o Fator R for ≥ 28%; abaixo disso, Anexo V (a partir de 15,5%). CNAE comum 8650-0/04.
- **Retenções** quando o tomador é PJ (ex.: empresa que contrata ginástica laboral): fonte secundária cita IRRF 1,5% e PIS/COFINS/CSLL 4,65%; ISS retido depende da lei municipal. **Confirmar com contador.** No particular PF não há retenção.

#### Especificação
- O Fisio **não emite**: ele chama o módulo **NFSe** com os dados do atendimento ou do pacote.
- Pré-configuração do business Fisio: item 4.08, cTribNac 04.08.02, NBS 1.2301.92.00, indicador de redução de 60% no IBS/CBS, regime (Simples/Presumido) e Fator R informado pelo contador.
- Momento da emissão (configurável): ao receber (padrão), por pacote ou fechamento mensal por paciente.
- Discriminação padrão sem dado clínico sensível: "Sessões de fisioterapia — N sessões — período". **Nenhum CID ou diagnóstico na nota** (LGPD art. 11, minimização). Para reembolso, o paciente pede o relatório pelo prontuário.
- Tomador = quem paga; beneficiário (dependente) no campo de informação complementar.
- **Verificação obrigatória (F3-T09):** confirmar se o módulo NFSe do oimpresso já emite no padrão nacional (DPS/ADN) com os grupos IBS/CBS vigentes desde 01/10/2026 e os municípios dos pilotos. Se não emitir, é dívida do **núcleo**, não do Fisio.

### 3.6 Receita Saúde (profissional pessoa física)

#### Fatos
- **IN RFB nº 2.240/2024.** Obrigatório desde **01/01/2025** para profissionais de saúde **pessoa física** com registro ativo no conselho, fisioterapeutas incluídos. **PJ não usa**: informa pela Dmed.
- O recibo é emitido **no dia do pagamento**, inclusive de cada parcela. Emissão retroativa é possível, mas exige ajustar o Carnê-Leão.
- Dados: CPF do pagador, se o pagador é o beneficiário (senão o CPF do beneficiário), valor, data do pagamento.
- Canais: **app Receita Saúde** (gov.br prata/ouro) e **Carnê-Leão Web** ("+Receita Saúde" em Rendimentos, também pelo e-CAC). O recibo cai automaticamente no Carnê-Leão. O profissional ainda apura e gera o DARF.
- **Lote:** desde nov/2025 a Receita divulga que sistemas de gestão podem gerar **arquivo padronizado de integração** para emitir vários recibos de uma vez (perguntas 24 e 25 do Manual do Receita Saúde v2.1). Formato, campos e limite: **não confirmados**.
- **API pública para terceiros: não encontrada.** Não confirmado que exista.

#### Especificação
- Business PF com Receita Saúde ligado: cada recebimento de paciente PF gera um **recibo pendente** em `fisio_health_receipts` (business_id, payment_id do Financeiro, payer_cpf, beneficiary_cpf, amount, paid_on, status `pendente`/`emitido`/`retificar`, emitted_at, receipt_number).
- **Fila "Recibos de hoje"** no painel, com selo de prazo ("emitir hoje").
- **Exportar lote** no layout oficial (quando o layout do Manual v2.1 for obtido) + instrução de importação. Até lá: tela de "copiar dados" por recibo e marcação manual "emitido", com número do recibo.
- **Conferência mensal:** total recebido no Financeiro de pacientes PF × total de recibos marcados como emitidos. A diferença aparece em vermelho (token `--neg`), porque é risco fiscal do profissional.
- Para o Carnê-Leão: relatório mensal "rendimentos de PF" para o profissional ou o contador. O Fisio **não** calcula imposto.
- CPF do pagador passa a ser obrigatório no cadastro de quem paga quando o business é PF. Isso já existe em `contacts`? Verificar.

### 3.7 Integração com o núcleo: quem é dono de quê

| fato | dono | o Fisio guarda |
|---|---|---|
| pessoa, CPF, WhatsApp | `contacts` | `contact_id` |
| título a receber / recebimento / baixa | **Financeiro** | `financial_*_id` no pacote e no agendamento avulso |
| mensalidade e Pix Automático | **RecurringBilling** | `recurring_contract_id` no pacote mensal |
| nota | **NFSe** | `nfse_id` no recebimento ou no pacote |
| repasse a pagar | **Financeiro** (contas a pagar) | cálculo e memória do cálculo |
| confirmação, lista de espera, aviso de pacote | **Whatsapp** (templates de utilidade) | log de envio idempotente |
| sessão de vídeo | provedor (LiveKit) | `fisio_telesessions` sem mídia |
| saldo de sessões | **Fisio** (`fisio_package_ledger`) | — |
| recibo Receita Saúde | **Fisio** (específico de saúde PF) | `fisio_health_receipts` |

Eventos (listeners Laravel, sem acoplamento direto):
`AppointmentCompleted` → consome sessão, gera cobrança avulsa se não houver pacote · `PaymentReceived` (Financeiro) → emite NFS-e (PJ) ou cria recibo pendente (PF) · `PackageLow` → WhatsApp de renovação · `AppointmentCancelled` → lista de espera.

**Custo de WhatsApp.** Tabela da Meta para o Brasil a partir de 01/10/2026: utilidade cerca de **R$ [redacted Tier 0]** por mensagem (a fonte tem inconsistência de moeda, conferir na tabela oficial). Desde essa data, utilidade dentro da janela de 24 h também é cobrada. Estimativa: paciente com 2 sessões por semana gera cerca de 8 confirmações + 2 lembretes por mês ≈ R$ [redacted Tier 0]/mês.

---

## 4. Riscos e o que fica fora

| risco | mitigação |
|---|---|
| Autônomo PF esperar Pix Automático | regra do BCB (só PJ) explicada na tela; cartão recorrente e Pix cobrança como alternativa |
| Receita Saúde sem API: recibo esquecido vira multa ou malha para o profissional | fila diária + conferência mensal Financeiro × recibos; lote quando o layout for obtido |
| NFSe do núcleo sem padrão nacional/IBS-CBS | tarefa de verificação antes de qualquer tela; se faltar, é dívida do núcleo |
| Taxa de falta cobrada automaticamente gerar conflito ou reclamação no CDC | aplicação sempre sugerida e confirmada pelo profissional; política exibida antes de agendar |
| Vídeo ruim em 4G queimar a teleconsulta | simulcast, 360p padrão, fallback só-áudio, métrica de queda |
| Mídia trafegando fora do Brasil | cláusula de transferência internacional no DPA; caminho de self-host no Brasil |
| Dado clínico vazar na NFS-e ou no WhatsApp | discriminação genérica; templates sem diagnóstico |
| Sobreposição com a OficinaAuto ou o POS (agenda duplicada no núcleo) | F3-T01 decide reaproveitar ou não e registra |
| Loja da Apple ou do Google recusar o app por ser "só um site" | o app empacotado precisa de função própria de app (entrada pelo aparelho, notificação, câmera da F2); se recusar, a instalação pela tela inicial continua valendo |
| Celular perdido com o app aberto | entrada pelo rosto ou digital a cada abertura; "desconectar aparelhos" na ficha do paciente |
| Responsável marcar por adulto sem autorização | A16: dependente adulto só com autorização registrada; a consulta continua sendo do dependente no prontuário |

**Fora da F3:** convênio/TISS · split de pagamento no gateway · gravação de teleconsulta · app nativo próprio por clínica (o app instalável da A14 é o mesmo portal; marca própria por clínica só sob demanda, ADR 0437 §14 nº 11) · sala de espera virtual com triagem · multiunidade · contabilidade e cálculo de imposto (Carnê-Leão, DAS) · lembrete por SMS · análise automática do vídeo da teleconsulta (SaMD classe II) · promoção, venda extra e nota pública no app do paciente (fora de propósito, ADR 0437 §13.7).

---

## 5. Métricas de sucesso (metas são estimativas a validar no piloto)

| métrica | meta 90 dias |
|---|---|
| Agendamentos confirmados por WhatsApp antes do horário | ≥ 70% |
| Taxa de falta dos pilotos (antes × depois) | queda ≥ 30% em relação à linha de base medida no primeiro mês |
| Vagas canceladas reocupadas pela lista de espera | ≥ 25% |
| Atendimentos "realizados" com evolução assinada no mesmo dia | ≥ 90% |
| Atendimentos realizados com cobrança vinculada (pacote ou avulso) | 100% (nenhum atendimento sem destino financeiro) |
| Recebimentos de PF com recibo Receita Saúde marcado no mesmo dia | ≥ 95% |
| Recebimentos de PJ com NFS-e autorizada em até 24 h | ≥ 95% |
| Teleconsultas iniciadas que terminam sem queda definitiva | ≥ 95% |
| Tempo para criar um agendamento | ≤ 15 s (3 campos) |
| Pilotos que desligam o Vedius/ZenFisio ou similar | ≥ 50% |

---

## 6. Tarefas para o Claude Code (ordem de dependência)

> Pré-condição: F1 entregue (pacientes, prontuário, consentimentos, portal, job WhatsApp) e F3 admitida pelo funil da ADR 0368.

| id | tarefa | blocked_by | DoD |
|---|---|---|---|
| F3-T00 | Admissão da F3 pelo funil (ADR 0368) + trio SDD `features/f3-operacao/` | F1 | `feature-lint --check` verde; aceite de [W] |
| F3-T01 | **Levantamento do núcleo**: Financeiro (títulos, baixa, contas a pagar), RecurringBilling (gateway, Pix Automático?), NFSe (padrão nacional? IBS/CBS? municípios dos pilotos), Whatsapp (botões interativos?), `bookings` do POS, CPF em `contacts`. Saída: nota no SPEC com "reusa / estende / falta no núcleo" | F3-T00 | documento revisado por [W]; lacunas do núcleo viram tarefas do núcleo, não do Fisio |
| F3-T02 | Migrations e models: `fisio_resources`, `fisio_appointment_types`, `fisio_appointment_series`, `fisio_appointments`, `fisio_waitlist`, `fisio_policies` | F3-T01 | isolamento entre 2 businesses; conflito de profissional e de recurso testado |
| F3-T03 | Agenda (dia/semana/lista), criar em 3 campos, série recorrente com edição "esta/próximas/todas", encaixe, bloqueio, arrastar para remarcar | F3-T02 | casos-gate (ADR 0264); `ui:lint` sem baseline novo; mobile 360 px sem scroll horizontal |
| F3-T04 | Status do atendimento; "realizado" exige evolução assinada e trava status | F3-T03 | teste: não marca realizado sem evolução; não volta status depois de realizado |
| F3-T05 | Confirmação WhatsApp D-n com botões e segundo lembrete (reuso do job idempotente da F1) | F3-T03 | um envio por agendamento por janela; resposta muda status; business sem módulo não dispara |
| F3-T06 | Política de falta/cancelamento versionada + aplicação sugerida com confirmação | F3-T04 | nenhuma taxa ou consumo sem confirmação humana; versão da política gravada no agendamento |
| F3-T07 | Lista de espera: preferências, espera de 15 min / 2 h, oferta por WhatsApp, janela de exclusividade, primeiro a confirmar leva | F3-T05 | teste de corrida (dois confirmam ao mesmo tempo → um só agendamento) |
| F3-T08 | Pacotes: `fisio_packages` + `fisio_package_ledger` append-only, venda à vista/parcelada (títulos no Financeiro), mensalidade (RecurringBilling), consumo por atendimento, aviso de saldo baixo | F3-T04, F3-T01 | saldo derivado bate com o ledger; nenhum UPDATE no ledger; estorno só por lançamento inverso |
| F3-T09 | Cobrança: avulso e parcela com Pix cobrança/cartão pelo gateway do núcleo; Pix Automático só para business PJ elegível (jornada 3) | F3-T08 | business PF não vê Pix Automático; webhook de pagamento dá baixa no Financeiro |
| F3-T10 | NFS-e: preset 4.08 / 04.08.02 / NBS 1.2301.92.00 / redução de 60%; emissão no recebimento pelo módulo NFSe; discriminação sem dado clínico | F3-T09, lacunas do núcleo resolvidas | nota autorizada em homologação no município do piloto com grupos IBS/CBS; teste garante ausência de CID/diagnóstico |
| F3-T11 | Receita Saúde (business PF): recibo pendente por recebimento, fila "emitir hoje", marcação manual com número, conferência mensal | F3-T09 | toda baixa de paciente PF gera um pendente; relatório mensal fecha com o Financeiro |
| F3-T12 | Lote Receita Saúde no layout oficial | F3-T11 + **layout do Manual v2.1 obtido** | arquivo aceito na importação oficial com 3 recibos de teste |
| F3-T13 | Repasse: regra por profissional, cálculo do período, título a pagar no Financeiro, relatório CSV | F3-T09 | valores conferidos com planilha de teste; recálculo idempotente |
| F3-T14 | Teleconsulta: `fisio_telesessions`, LiveKit Cloud, sala criada pelo tipo "teleconsulta", entrada pelo portal link+PIN com checagem de consentimento, teste de câmera, simulcast com 360p padrão, fallback só-áudio, "prefiro presencial" | F3-T04 | sem consentimento vigente não entra; token de sala expira; nenhum arquivo de mídia gravado; funciona em Chrome Android e Safari iOS em 4G simulado (throttling) |
| F3-T15 | Evolução de teleconsulta pré-preenchida (síncrona, vídeo, início/fim/duração, qualidade) | F3-T14 | teleconsulta sem evolução não fica "realizada" (Res. 619 art. 5º) |
| F3-T16 | Agenda online pública + no portal: tipos liberados, paciente novo (nome + WhatsApp), sinal via Pix opcional, política exibida | F3-T06, F3-T09 | sem conflito com agenda interna; política aceita gravada |
| F3-T17 | Painel do dia + fila de atenção combinando falta e constância (F1) | F3-T05, F3-T08 | números batem com os dados de teste |
| F3-T18 | DPA: incluir provedor de vídeo, gateway e transferência internacional (se LiveKit Cloud fora do Brasil) | F3-T14 | revisão LGPD anotada antes do primeiro paciente real |
| F3-T20 | App instalável: manifesto e ícones da clínica, instalação pela tela inicial, empacotamento para App Store e Google Play (ADR 0437 §14 nº 11) | F3-T16 | instala em Android e iPhone; link + PIN segue abrindo o mesmo portal; nenhuma rota nova sem `business_id` |
| F3-T21 | Entrada que lembra o aparelho (A15): código por WhatsApp, chave de acesso do aparelho, sair e desconectar aparelhos | F3-T20, F1 (portal + job WhatsApp) | código expira e tem limite de tentativas; nenhuma biometria sai do aparelho; desconectar invalida todas as sessões daquele paciente; canal `wa.me` cai no link + PIN |
| F3-T22 | Dependentes (A16) com consentimento do responsável legal e autorização do dependente adulto | F3-T21 | menor sem consentimento não marca; adulto sem autorização não aparece para o responsável; isolamento entre 2 businesses |
| F3-T23 | Sinal com isenção (A17), saldo e renovação (A18), check-in (A19), recado e nota privada (A20) | F3-T08, F3-T09, F3-T16 | pacote ativo nunca vê sinal; renovação não aparece sem indicação do profissional; nota nunca sai da clínica |
| F3-T24 | Limites do autoagendamento (A21) na tela de configurações da clínica | F3-T16 | bloqueio por N faltas reversível pelo profissional; tipos e horários não liberados não aparecem no app |
| F3-T19 | Fecha o loop: âncora `Implementado em:` das US no SPEC | todas | `verificado@sha7` |

---

## 7. Fontes

**Concorrentes / agenda**
- Comparativo de software no Brasil (Vedius, ZenFisio, Kynesia, Fisioly, Physitrack; preços e recursos): https://www.physitrack.com/pt/insights/melhor-software-fisioterapia-brasil-comparativo
- Vedius (site oficial): https://www.vedius.com.br/
- ZenFisio, como agendar (repetir, pacote, sala, status, SMS, bloqueio): https://intercom.help/zenfisio/pt-BR/articles/3433196-como-agendar-um-atendimento-para-um-paciente
- Ninsaúde, agenda e confirmação: https://www.ninsaude.com/en/medical-scheduling-software
- Jane App, lista de espera automática: https://jane.app/guide/using-automatic-wait-list-notifications
- Jane App, faltas e política de cancelamento: https://360clinician.beehiiv.com/p/reduce-noshows-late-cancellations-help-jane
- Cliniko, preços e recursos (fonte concorrente, Pabau): https://pabau.com/blog/cliniko-pricing/
- Feegow + WhatsApp (integração Digisac): https://digisac.com.br/solucoes/integracoes/feegow

**App do paciente e autoagendamento (comparativo de 2026-10-18)**
- AppBarber: Cliente, App Store (funções, notas de versão, avaliações): https://apps.apple.com/BR/app/id6450795073
- Jane, agendamento online (portal, família, cancelamento, sinal, formulários, lista de espera): https://jane.app/features/online-booking
- Jane, aviso de vaga para quem está na espera: https://jane.app/guide/wait-list-notifications-client-experience
- Cliniko, agendamento online (sem usuário e senha, aviso mínimo para cancelar): https://www.cliniko.com/features/appointments/online-bookings/
- Booksy, proteção contra falta (sinal, taxa, clientes de confiança, bloqueio): https://biz.booksy.com/features/no-show-protection
- Physitrack, app com a marca da clínica: https://www.physitrack.com/branded-app
- Doctoralia, agenda para clínicas (lembretes, check-in, lista de espera): https://medicinasa.com.br/agenda-clinica-doctoralia/

**Teleconsulta / regulação**
- Res. COFFITO 619/2025, texto (arts. 1º, 2º, 4º, 5º, 6º, 7º): https://www.normasbrasil.com.br/norma/resolucao-619-2025_480866.html
- Res. COFFITO 619/2025, ementa e publicação: https://abmes.org.br/legislacoes/detalhe/5208/resolucao-coffito-n-619-2025
- Análise da 619 (sem plataforma obrigatória, sem regra de gravação): https://www.physitrack.com/pt/insights/resolucao-coffito-619-2025-telefisioterapia-brasil
- Lei 14.510/2022: https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2022/lei/l14510.htm
- Gravação e LGPD em telemedicina (orientação, não norma de fisioterapia): https://confidata.com.br/blog/telemedicina-lgpd-guia-completo-plataformas

**Vídeo, preços e técnica**
- LiveKit, preços: https://livekit.com/pricing
- LiveKit, benchmark self-host: https://docs.livekit.io/transport/self-hosting/benchmark.md
- Daily, preços (agregador, marcado como não reverificado): https://frontdeskreview.com/software/video-api/daily/
- Twilio Video, preços: https://www.twilio.com/en-us/video/pricing
- Twilio Video, reversão do fim de vida: https://www.twilio.com/en-us/changelog/-twilio-video-will-remain-a-standalone-product
- 8x8 JaaS, preços: https://cpaas.8x8.com/en/pricing/jitsi-as-a-service-pricing/
- Bitrate por resolução e simulcast: https://dev.to/iason_paraskevopoulos_77d/how-we-balanced-camera-quality-and-bandwidth-in-our-video-meetings-49bk

**Cobrança**
- Pix Automático, data de lançamento: https://finsidersbrasil.com.br/pagamentos/banco-central-adia-pix-automatico-para-junho-de-2025
- Pix Automático, FAQ oficial do BCB para participantes: https://cdn-www.bcb.gov.br/content/estabilidadefinanceira/pix/pix-automatico-FAQ-participantes.pdf
- Asaas, FAQ do Pix Automático (requisitos, API): https://docs.asaas.com/docs/faq-2
- WhatsApp Business, preços no Brasil a partir de 01/10/2026: https://whautomate.com/whatsapp-business-api-pricing-brazil
- WhatsApp Business, mudança de jul/2025: https://www.ycloud.com/pt/blog/whatsapp-api-pricing-update

**NFS-e / tributos**
- NFS-e nacional em 2026 (art. 62 da LC 214, "ou"): https://www.reformatributaria.com/nfse-nacional-em-2026-a-verdade-por-tras-da-obrigatoriedade-e-migracao-dos-municipios/
- Adesão de 1.472 municípios: https://www.reformatributaria.com/governo-informa-adesao-de-1-472-municipios-a-nfs-e-nacional/
- Guia prático NFS-e 2026 (emissor opcional, MEI, SP e DF): https://notagateway.com.br/?p=5596
- Destaque de IBS/CBS na NFS-e adiado para 01/10/2026; Simples em 2027: https://guiatributario.net/2026/08/03/nfs-e-destaque-da-cbs-e-ibs-e-adiado-para-01-10-2026/
- Simples e MEI dispensados em 2026: https://www.jettax.com.br/blog/simples-nacional-e-mei-ibs-e-cbs-na-nota-fiscal-em-2026-entenda-as-regras/
- cTribNac 04.08.01/02/03: https://buscadorncm.com.br/nfse/ctribnac/40801
- NBS 1.2301.92.00 e redução de 60%: https://simtax.com.br/reducao-60-ibs-cbs-saude/
- Tributação do fisioterapeuta (Fator R, Anexos III e V, retenções): https://contabilidade.com/blog/quanto-um-fisioterapeuta-paga-de-imposto-veja-a-tributacao-como-autonomo-simples-nacional-e-lucro-presumido/

**Receita Saúde**
- Obrigatoriedade, IN 2.240/2024, PF e não PJ: https://www.coad.com.br/home/noticias-detalhe/129492/recibo-eletronico-de-servicos-de-saude-sera-obrigatorio-a-partir-de-2025
- Emissão no dia do pagamento, Carnê-Leão Web, dados exigidos: https://blog.asaas.com/receita-saude/
- Emissão em lote por arquivo de integração (nov/2025): https://fenacon.org.br/noticias/disponibilizada-nova-funcionalidade-para-emissao-em-lote-de-recibos-do-receita-saude/
- Lote, Manual v2.1, perguntas 24 e 25: https://jornalcontabil.com.br/noticia/receita-saude-disponivel-nova-funcao-para-emissao-em-lote-de-recibos/amp/

**Pendências (cota de busca esgotada):** versão vigente do padrão TISS e glosa média · região de mídia do LiveKit Cloud na América do Sul · layout do arquivo de lote do Receita Saúde (Manual v2.1) · taxas do Asaas e Pix cobrança para PF · custo de VPS para LiveKit/Jitsi self-host · recursos e preço do Feegow.

---
slug: 0437-modulo-fisio-vertical-fisioterapia
number: 437
title: "Módulo Fisio — vertical de fisioterapia no oimpresso: o paciente faz o exercício em casa, o fisioterapeuta sabe quem fez, e a constância vira jogo"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-10-18"
accepted_via: "Wagner 2026-10-18, textual: 'Já tenho fisioterapeuta contratado e a adr é aceita. Dr dejeane'. O aceite cobre a decisão, a seção Fisio no menu (§3.3) e as emendas da §13. As decisões da §14 ainda abertas seguem pendentes, uma a uma."
module: fisio
quarter: 2026-Q4
tags: [fisio, vertical, modulo-novo, saude, lgpd, anvisa, samd, coffito, prontuario, gamificacao, whatsapp, camera, ds-v6, sidebar]
supersedes: []
superseded_by: []
related: [0368-funil-admissao-feature-pesquisa-propoe-w-admite, 0105-cliente-como-sinal-guiar-sem-mandar, 0039-ui-chat-cockpit-padrao, 0190-primary-button-roxo-universal-295, 0253-primitivos-layout, 0264-governanca-executavel-trio-dominio-e2e, 0306-strangler-spec-anchored-reconstrucao-sdd, 0383-ponto-interno-nao-coleta-biometria, 0386-ambar-da-oficina-revogado-roxo-e-a-unica-identidade, 0171-oficinaauto-ativacao-piloto-martinho-faseada]
pii: true
---

# ADR 0437 — Módulo Fisio

> **Status `aceito`** por [W] em 2026-10-18 (ver `accepted_via`).
> **Responsável técnica do conteúdo clínico:** Dra. Dejeane, fisioterapeuta (CREFITO [Nº]). Biblioteca, protocolos-modelo, mapa de contraindicações, sinais de alerta e textos ao paciente só vão a produção com a revisão dela registrada no PR.
> Número a confirmar contra o `main` no PR (o último visto na redação foi 0436).
> Pesquisa de mercado e revisão regulatória feitas em 2026-10-17; fontes no fim.

## 1. O problema, em uma frase

**De 30% a 50% dos pacientes não fazem os exercícios de casa** (pode chegar a 70%), e o fisioterapeuta só descobre na sessão seguinte. A causa número 1 é esquecimento (40%), seguida de falta de tempo, tédio, não ver resultado e medo de piorar. Quem resolve isso no Brasil hoje é o WhatsApp pessoal do profissional, com foto e áudio soltos.

## 2. A tese: por que isto vira febre

O mercado brasileiro está partido em dois:

- **Gestão barata** (Vedius, ZenFisio, Kynesia, Fisioly, de R$ 45 a R$ 90/mês): agenda, prontuário, financeiro. Prescrição de exercício é acessório.
- **Prescrição cara** (Physitrack, R$ 169/mês no Brasil): biblioteca enorme e app do paciente, mas sem cobrança, sem NFS-e e sem cara de Brasil.

Ninguém junta, com evidência pública, **programa domiciliar + adesão medida + jogo de constância + cobrança e NFS-e**. O oimpresso já tem metade disso pronta (financeiro, NFS-e, WhatsApp, link público). O Fisio é a outra metade.

**A promessa ao fisioterapeuta:** *"Monte o programa em um minuto, mande pelo WhatsApp, saiba quem fez."*
**A promessa ao paciente:** *"Seu tratamento cabe no seu dia, e cada dia conta."*

### As alavancas, em ordem de força

1. **WhatsApp como canal principal.** Está na tela inicial de 65% dos celulares no Brasil, e o lembrete com link direto para o check-in ataca o esquecimento, causa nº 1 de abandono. Com o driver `whatsmeow` (§13.1) não há custo por mensagem; na API oficial seria cerca de R$ 0,05 por mensagem de utilidade. Há RCT mostrando aumento de adesão com mensagens.
2. **Zero atrito para o paciente.** Link + PIN, sem app, sem e-mail, sem senha. As reclamações mais repetidas contra Wibbi, MedBridge, PT Wired e Limber são de login, app instável e exigência de e-mail.
3. **Jogo de constância.** Nota da série, XP, nível, missões do dia, medalhas, sequência. Tédio e "não vejo resultado" somam 41% dos motivos de abandono; o jogo transforma cada dia feito em progresso visível. Regras de segurança na §6.
4. **Painel que mostra quem vai abandonar.** Dor alta e medo de movimento preveem abandono (estudo brasileiro, BrJP 2023), e supervisão é o principal facilitador. O painel transforma supervisão em escala.
5. **O paciente como canal de marca.** Todo programa e todo lembrete levam o nome, a foto e o CREFITO do profissional, com um rodapé "feito com oimpresso". O fisioterapeuta vive de indicação, e o paciente que manda o print do nível 8 para a família está fazendo marketing dele.
6. **Montar o programa rápido.** A reclamação nº 1 contra o MedBridge é o tempo de montagem. Aqui: protocolos-modelo da clínica (com a lógica visível), busca por texto e ditado por voz para o prontuário. A IA que *sugere conduta* fica para depois, por causa da Anvisa (§5).
7. **Ciclo financeiro completo.** Pacote → cobrança → NFS-e → Receita Saúde (obrigatório para pessoa física desde 2025). Concorrente que não é ERP não copia isso.
8. **Câmera como "uau" de demonstração.** Contar repetições, ritmo e tempo de sustentação no celular, sem medir ângulo. Ninguém entre os players locais oferece isso de forma visível, e é o vídeo que viraliza.
9. **Freemium para o autônomo.** Grátis até 5 pacientes ativos (§14 nº 10 e `etapas/06-lancamento.md`), depois na faixa dos locais (R$ 49 a R$ 79, a validar). A âncora brasileira é baixa; quem cobra R$ 169 não domina.
10. **Indicação entre profissionais.** Crédito por indicação, como o Jane App faz (US$ 120+ por clínica indicada).

### Os três erros que matam produtos assim

1. **Atrito para o paciente.** Se o paciente não abre, o painel fica vazio e o profissional cancela.
2. **Prometer precisão clínica da câmera.** Com uma câmera só, ângulo absoluto não tem validade; um vídeo de contagem errada viraliza contra o produto. Alegação clínica atrai a Anvisa.
3. **Virar "mais um sistema de gestão".** O valor precisa aparecer no primeiro paciente, no primeiro dia, sem migrar agenda nem financeiro.

### Público de entrada

Fisioterapeuta **autônomo ou de consultório pequeno** (1 a 3 profissionais), traumato-ortopedia e musculoesquelético, atendimento particular, que hoje manda exercício por foto no WhatsApp. É o maior volume, decide sozinho, tem a dor mais clara, e é onde a evidência de exercício domiciliar é mais forte.

## 3. Decisão de arquitetura

### 3.1 Fisio é um módulo vertical (`Modules/Fisio`), não produto à parte

[W] decidiu em 2026-10-17 (textual: *"Gostaria que fosse módulo se não for ficar ruim"*). Precedente: OficinaAuto (ADR 0171).

| precisa de | reusa | não cria |
|---|---|---|
| paciente | `contacts` (cliente) + perfil clínico 1:1 | cadastro paralelo de pessoa |
| pacote, sessão, cobrança | Financeiro + RecurringBilling | carteira própria |
| nota | NFSe | emissor próprio |
| mensagem | Whatsapp | integração nova |
| acesso do paciente | padrão do link público da OficinaAuto (`/aprovar-os/{token}`, token HMAC + PIN com lockout) | login de paciente |

`business_id` em toda tabela e toda query do módulo (Tier 0).

### 3.2 Identidade: shell e DS v6 sem exceção

- Telas da clínica no **AppShellV2** (ADR 0039), com "Apps Vinculados" quando há paciente em foco.
- **Roxo 295 é a única identidade** (ADR 0190, 0386). Nenhuma cor de módulo.
- O jogo do paciente usa só tokens existentes: roxo como pontuação, `--vip` (dourado) para moedas e medalhas, `--pos` para conquista. Fundo escuro de comemoração vem de `--kpi-feature-bg`.
- Primitivos de layout (ADR 0253), `ui:lint` sem baseline novo.

### 3.3 Menu: seção "Fisio" na sidebar — **exige aprovação explícita de [W]**

Painel · Pacientes · Prescrever · Biblioteca · Agenda, visível **só** para business com o módulo ativo. Cliente gráfico não vê nada novo.

## 4. Regras clínicas e de prontuário (COFFITO)

- **Telessaúde:** a Resolução COFFITO **619/2025** (DOU 09/07/2025) **revogou a 516/2020**. Exige consentimento do paciente e **registro no prontuário de cada atendimento remoto**, síncrono ou assíncrono. Não impõe plataforma. O check-in do portal é telemonitoramento assíncrono e precisa gerar evolução no prontuário. Lei 14.510/2022 dá a base geral.
- **Prontuário:** Resolução COFFITO **414/2012** define o conteúdo mínimo (identificação, história, exame físico-funcional, diagnóstico e prognóstico fisioterapêuticos, plano com objetivos e número de sessões, evolução por atendimento com nome e CREFITO). Prontuário eletrônico é permitido; o paciente tem acesso e cópia a qualquer momento.
- **Guarda por 20 anos.** O COFFITO fala em 5, mas a Lei 13.787/2018 fixa 20 anos para prontuário em qualquer suporte. Vale a mais restritiva. "Excluir paciente" vira bloqueio; registro corrigido por adendo, nunca sobrescrito.
- **Imagem e publicidade:** Código de Ética (Res. 424/2013) proíbe usar depoimento, foto ou antes/depois de paciente para autopromoção. O produto **não terá recurso de depoimento para marketing**. O compartilhamento de nível/medalha pelo paciente é do paciente, sem dado clínico e sem uso pela clínica.

## 5. Anvisa: o que é dispositivo médico (SaMD) e o que não é

Pelo Perguntas & Respostas oficial da RDC 657/2022, classificação pela RDC 751/2022:

| função | enquadramento | decisão |
|---|---|---|
| Biblioteca em que o profissional escolhe | não é SaMD (P10) | F1 |
| Registrar dor para o profissional avaliar | não é SaMD (P11) | F1 |
| Prontuário, agenda, comunicação, teleatendimento | não é SaMD (P44, P71) | F1 |
| Protocolo-modelo com a lógica toda visível | não é SaMD (P58) | F1 |
| Gráfico de dor periódico para o próprio paciente | SaMD classe I (P11) | gráfico de dor fica só na visão do profissional na F1 |
| Software que acompanha a dor e **sugere parar ou trocar** exercício | SaMD classe II (P10) | **não fazer**: o paciente vê só a instrução estática que o profissional escreveu; o alerta vai para o profissional |
| IA que analisa vídeo de movimento | SaMD classe II (P50) | câmera só conta repetições, ritmo e tempo; sem ângulo, sem "corrigir"; consulta de enquadramento antes |
| Motor que **recomenda conduta**, mesmo com aprovação humana | SaMD classe II (P57) | "prescrição por IA" fica fora até [W] decidir entre notificação classe II ou busca assistida |

**Texto do produto e do marketing:** "apoio ao fisioterapeuta; a decisão clínica é do profissional". Proibido: "diagnostica", "trata", "corrige postura", "previne lesão".

**Antes da fase da câmera:** pedir à Anvisa consulta de enquadramento (art. 19 da RDC 657).

## 6. O jogo de constância (paciente)

**Princípio:** o jogo premia **constância e cumprimento do prescrito**, nunca volume, nunca esconder dor.

| regra | por quê |
|---|---|
| XP por exercício concluído, por registrar como foi o dia, por série completa no tempo pedido | reforça o que o fisioterapeuta prescreveu |
| Fazer além do prescrito **não** dá XP | evita overtraining |
| Registrar dor **sempre** dá XP, inclusive dor alta | premia honestidade; dado ruim escondido é pior que dado ruim |
| A sequência conta os **dias prescritos**, não todos os dias | o descanso faz parte do tratamento |
| Com dor ≥ 7 ou sinal de alerta: o jogo sai da tela, aparece a instrução que o profissional escreveu e o profissional é notificado; a sequência fica protegida | segurança acima do engajamento; nada de decisão automática (§5) |
| A "nota da série" (0–100) vem de **repetições contadas, ritmo e tempo de sustentação**, comparados com o prescrito | métrica de adesão, não avaliação clínica |
| Sem ranking entre pacientes | dado de saúde não é competição; o paciente compete consigo mesmo |
| Níveis: Primeiro Passo, Ritmo, Constância, Base Firme, Equilíbrio, Força Leve, Movimento Livre, Corpo Forte, Mestre do Movimento, Inabalável | progressão longa, com nome que dá orgulho de mostrar |
| Missões diárias (3), desafio semanal, medalhas | variedade contra o tédio |

O fisioterapeuta vê no painel o nível e a sequência de cada paciente, e pode mandar parabéns com um toque.

## 7. LGPD

- A clínica é **controladora**; o oimpresso é **operador**. Contrato de operador (DPA) obrigatório.
- Base para o atendimento, prontuário, check-in e questionários: **tutela da saúde** (art. 11, II, "f"), que dispensa consentimento. Guarda do prontuário: obrigação legal.
- Consentimentos **separados e versionados**: teleatendimento (Lei 14.510 / Res. 619), câmera, WhatsApp, uso de imagem, marketing, pesquisa e treino de IA (desligado por padrão). Revogáveis pelo portal.
- **Proibido** usar dado clínico de uma clínica em benchmark de outra ou vender (art. 11, §4º).
- Pedido de exclusão não apaga prontuário dentro do prazo de guarda (art. 16, I); apaga o que é baseado em consentimento.
- Câmera: processamento no aparelho; só métricas sobem (espírito da ADR 0383).

## 8. Questionários clínicos

| situação | escalas |
|---|---|
| **Liberadas para a F1** (uso livre) | EVA/END, Roland-Morris, PSFS, LEFS, SPADI |
| Depois de autorização por escrito | Tampa, VISA-P, FAAM |
| **Bloqueadas** até haver licença comercial | Oswestry (ODI), NDI, KOOS, QuickDASH/DASH, WOMAC |

Texto da versão brasileira validada, sem alteração, com a referência na tela. Recusas registradas no inventário com motivo (ADR 0368 §5).

## 9. Fases (cada uma admitida separadamente pelo funil da ADR 0368)

| fase | entrega |
|---|---|
| **F1 · Constância** | pacientes e prontuário (Res. 414), biblioteca revisada com contraindicações, protocolos-modelo, programa por paciente, portal link + PIN, consentimentos, lembrete diário por WhatsApp, check-in com dor e sinais de alerta, jogo de constância sem câmera, painel de risco de abandono, questionários liberados, marca do profissional no portal |
| **F2 · Câmera** | contagem de repetições, ritmo e sustentação no aparelho; nota da série; enquadramento guiado do celular. **Pré-requisito:** consulta de enquadramento à Anvisa |
| **F3 · Operação e dinheiro** | agenda com confirmação por botões e lista de espera, teleconsulta por vídeo no navegador (LiveKit, sem gravação), pacotes que consomem sessões, cobrança (Pix Automático só PJ; cartão e Pix Cobrança para PF), NFS-e com preset de fisioterapia e fila do Receita Saúde |
| **F4 · Velocidade** | ditado por voz depois do atendimento, estruturado na Res. 414 (fornecedor em região adequada pela ANPD ou modelo local); questionários pagos se licenciados; prescrição por IA **só** depois da decisão sobre notificação classe II |

## 10. Alternativas consideradas

- **Produto separado com marca própria.** Recusado por [W]: perde o núcleo e duplica infraestrutura.
- **Identidade visual própria (accent rosa).** Recusado: ADR 0190/0386.
- **Login com senha para o paciente na F1.** Adiado: link + PIN já está provado na OficinaAuto e reduz atrito.
- **Ranking entre pacientes.** Recusado: dado de saúde não é competição pública.
- **Prescrição por IA na F1.** Adiado: SaMD classe II pela P57, mesmo com aprovação humana.

## 11. Consequências

- Primeira vertical com dado sensível de saúde: exige revisão LGPD antes do primeiro paciente real.
- O seletor de módulos precisa esconder o Fisio de quem não contratou.
- A câmera (F2) também alimenta o projeto de estúdios de pilates com IA.

## 12. Pendências antes do lançamento

- Texto integral da Res. COFFITO 619/2025.
- Exigência de assinatura ICP-Brasil para prontuário de fisioterapia.
- Termos de licença de Tampa, VISA-P, FAAM e WOMAC.
- Enquadramento da câmera e da pontuação de questionários na Anvisa.
- Revisão clínica da biblioteca por fisioterapeuta antes do seed.

## 13. Especificação por etapa (pesquisa de 2026-10-17)

Cada etapa tem especificação própria, com o que os melhores fazem, regras, riscos, métricas e tarefas com DoD para o Claude Code. Elas detalham esta ADR e não a substituem; em conflito, vale esta ADR até ser emendada.

| arquivo | etapa | nota-meta na grade |
|---|---|---|
| `memory/requisitos/Fisio/etapas/01-biblioteca.md` | biblioteca e protocolos | 6 → 9 |
| `memory/requisitos/Fisio/etapas/02-f1-constancia.md` | portal, WhatsApp, check-in, jogo, painel de risco | constância 9 |
| `memory/requisitos/Fisio/etapas/03-f2-camera.md` | câmera e plano classe II | 6 → 8 (9 com classe II) |
| `memory/requisitos/Fisio/etapas/04-f3-operacao.md` | agenda, teleconsulta, cobrança, NFS-e | agenda 5 → 8, teleconsulta 4 → 8 |
| `memory/requisitos/Fisio/etapas/05-f4-ia-questionarios.md` | ditado, busca, IA, questionários | IA 6 → 8, questionários 6 → 8 (9 com licenças) |
| `memory/requisitos/Fisio/etapas/06-lancamento.md` | piloto, preço, canais, marca | maturidade 1 → 4 no fim do piloto |

**Emendas que as etapas trouxeram a esta ADR:**

1. **WhatsApp pelo módulo `Whatsapp` que já existe, com o driver `whatsmeow`.** Decisão de [W] em 2026-10-18 (textual: *"whatsmeow será o driver"*). Nada de integração nova: o Fisio registra as mensagens dele e chama o envio do módulo pelo `ChannelDriverFactory`.
   - **Risco aceito e registrado:** whatsmeow não é API oficial; a Meta avisa que apps não oficiais podem levar a banimento do número. Um número de clínica banido para o lembrete de todos os pacientes daquela clínica.
   - **Mitigações obrigatórias:** (a) um número por clínica, nunca um número compartilhado entre clínicas; (b) só mensagens a pacientes que deram consentimento de WhatsApp e já falaram com a clínica; (c) teto de envio por número/hora e intervalo aleatório entre mensagens; (d) aquecimento de número novo (volume baixo nas primeiras semanas); (e) monitor de saúde do canal reaproveitando os comandos de reconciliação do módulo, com alerta ao profissional quando o canal cair (incidente de 2026-09-02 mostrou canais mudos sem alerta); (f) alternativa sempre disponível: link `wa.me` do próprio profissional e link do portal copiável.
   - **Porta aberta:** o Fisio fala com o módulo pelo contrato de driver, nunca com o whatsmeow direto. Trocar para `meta_cloud` (já existe no módulo, com Embedded Signup) é configuração do canal, sem mudar o Fisio.
2. **Contraindicação avisa, nunca bloqueia.** Bloquear ação por diagnóstico é SaMD (P68/P70 da Anvisa). O profissional vê o aviso, escreve a justificativa, e ela vai para o prontuário. O paciente não vê nada disso.
3. **Jogo com números** (detalhe em `02-f1-constancia.md`): 100 XP por dia prescrito (70 exercícios + 30 registro), teto semanal, 10 níveis com o 10º perto de 60 dias de constância, até 2 escudos de sequência, total de dias que nunca zera, modo manutenção na alta.
4. **Câmera liberada exercício por exercício**, só depois de validação com voluntários, e a série com câmera vale o mesmo XP da série marcada à mão.
5. **Gráfico de dor e diferença clínica mínima (MCID) só na visão do profissional.** Na tela do paciente viraria SaMD (P11/P79).
6. **Ditado:** dado de saúde só para fornecedor em país reconhecido como adequado pela ANPD ou modelo rodando no nosso servidor; nome e CPF nunca vão no prompt; áudio apagado depois da transcrição.

## 14. Decisões pendentes de [W]

| # | decisão | recomendação |
|---|---|---|
| 1 | Aceitar esta ADR e a seção "Fisio" no menu | **decidido 2026-10-18: aceita** |
| 2 | Nome comercial do módulo | "Fiz Hoje" (checar no INPI, classes 9, 41, 42 e 44); "Prescreve" tem risco por ser descritivo |
| 3 | Trazer a busca por texto da F4 para a F1 | sim: sem ela a biblioteca não passa de 7 |
| 4 | Contratar fisioterapeuta responsável técnico do conteúdo | **decidido: Dra. Dejeane, já contratada** |
| 5 | Driver e número de WhatsApp | **decidido 2026-10-18: driver `whatsmeow`**, um número por clínica, com as mitigações da §13.1; `meta_cloud` fica como troca por configuração |
| 6 | Check-in inteiro dentro do WhatsApp (Flows) | F1.5, depois do piloto |
| 7 | Notificar SaMD classe II na Anvisa | não agora; pedir 2–3 orçamentos e decidir depois do piloto |
| 8 | Licenciar questionários pagos (Mapi, IWH) | depois do piloto, se os clientes pedirem |
| 9 | Aviso fixo "em emergência, ligue 192" | só após parecer regulatório |
| 10 | Preço: grátis até 5 pacientes, R$ 59/mês (R$ 49 no anual), 50% para estudante | validar no piloto |

## Fontes

- Adesão: Argent et al., JMIR mHealth 2018 (https://mhealth.jmir.org/2018/3/e47); Bennell et al. 2019 e 2020 (https://www.jmir.org/2020/9/e21749); Davergne et al., JMIR 2023 (https://www.jmir.org/2023/1/e45207); Santello et al., BrJP 2023 (https://scielo.br/j/brjp/a/K35tR9D6cdT3NX8mywhXYZz/?lang=en).
- Mercado: Physitrack resultados 2025 (https://www.mfn.se/a/physitrack/physitrack-plc-year-end-report-q4-and-full-year-2025-improved-profitability-and-sustained-positive-cash-flow); comparativo de software no Brasil (https://www.physitrack.com/pt/insights/melhor-software-fisioterapia-brasil-comparativo); WhatsApp na tela inicial (https://www.mobiletime.com.br/?p=855811).
- Câmera: contagem × posição do celular, JMIR mHealth 2026 (https://mhealth.jmir.org/2026/1/e82412/); MediaPipe vs VICON (https://doaj.org/article/2d283f6411874c82988f54dd2e60829f).
- COFFITO 619/2025 (https://abmes.org.br/legislacoes/detalhe/5208/resolucao-coffito-n-619); 414/2012 (https://www.normasbrasil.com.br/norma/resolucao-414-2012_240928.html); 424/2013 (https://www.normasbrasil.com.br/norma/resolucao-424-2013_254623.html).
- Leis: 13.787/2018 (https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13787.htm); 14.510/2022 (https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2022/lei/l14510.htm); LGPD (https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm).
- Anvisa: P&R RDC 657/2022 (https://www.gov.br/anvisa/pt-br/assuntos/noticias-anvisa/2022/software-como-dispositivo-medico-perguntas-e-respostas/perguntas-respostas-rdc-657-de-2022-v1-01-09-2022.pdf/@@download/file).
- Licenças: Mapi Research Trust (ODI, NDI, KOOS); IWH (DASH, https://dash.iwh.on.ca/licences); SRALab (RMDQ, PSFS, LEFS, SPADI).

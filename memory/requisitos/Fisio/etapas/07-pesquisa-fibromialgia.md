# Etapa 07 — Pesquisa clínica em fibromialgia (Módulo Fisio)

> **Status:** proposta, aguardando aprovação de [W]. Nada aqui foi implementado.
> **Origem:** pedido de [W] em 2026-11-11: o software precisa estar preparado para as atividades de um estudo científico de fibromialgia. Desenho escolhido por [W] no mesmo dia: **clínica + universidade**.
> **Fontes:** a primeira versão saiu sem pesquisa na web; a pesquisa foi feita no mesmo dia (2026-11-11) e as fontes estão na §9. O que ainda não foi confirmado continua marcado **"a conferir"**. O que está dito sobre o código foi medido no `main` em 2026-11-11.
> **Linha da Anvisa (ADR 0437 §5):** nada desta etapa decide conduta. O software coleta, organiza e exporta. Quem analisa é o pesquisador.

---

## 1. Objetivo

Deixar o Fisio pronto para uma clínica conduzir, junto com uma universidade, um **estudo observacional prospectivo** de pacientes com fibromialgia em tratamento por exercício (protocolo 11). O estudo usa o atendimento que já acontece. O software não muda a conduta: ele garante que os dados certos sejam coletados nos momentos certos, com o consentimento certo, e que saiam sem identificação para a universidade.

**Fora desta etapa:** ensaio randomizado, estudo com várias clínicas, banco de dados compartilhado entre clínicas. Ficam para quando houver um estudo real pedindo isso (ADR 0105).

---

## 2. Papéis

| quem | papel no estudo | papel na LGPD |
|---|---|---|
| Clínica | centro onde o estudo acontece; atende o paciente | controladora do dado clínico (ADR 0437 §7) |
| Universidade | pesquisadora responsável; submete ao CEP; analisa | recebe dado **sem identificação**; enquadramento jurídico a conferir (órgão de pesquisa, LGPD art. 5º, XVIII, e art. 11, II, "c") |
| oimpresso | fornece o software | operador; não acessa o dado do estudo para outro fim |

O acordo entre clínica e universidade (quem guarda a chave de volta, prazo de guarda, publicação) é de fora do software. O software só precisa respeitá-lo.

---

## 3. O que já existe no `main` (medido em 2026-11-11)

| capacidade | onde | serve ao estudo como |
|---|---|---|
| Dor do dia (0–10) no check-in, com marcação de crise | `Entities/Checkin.php` (`pain`), migração `add_crisis_to_fisio_checkins` | medida diária pelo próprio paciente |
| Alerta pela dor acima da base da própria paciente | `Config/config.php` → `jogo.dor_de_base`; `Services/LimiarDorCalculator.php`; programa com `pain_alert_mode = sobre_basal` | registro de piora, candidato a evento adverso |
| Adesão: exercícios feitos × prescritos, sequência | ledger do jogo (`fisio_game_ledger`, só acrescenta) | variável de adesão |
| Consentimentos separados, versionados e revogáveis | `fisio_consents`, `Services/Consentimentos.php`, `Config/config.php` → `consent_versions` | base do termo de pesquisa |
| Questionários com respostas guardadas | `fisio_questionnaires`; enum fechado no banco: `eva, rmdq, psfs, lefs, spadi` | base para os instrumentos do estudo |
| Dado clínico não é apagado; registro assinado não muda | `Entities/Concerns/SemExclusaoDeDadoClinico.php` | trilha de auditoria |
| Exportação do prontuário registrada no log | `Services/ProntuarioExportacao.php` + `activity_log` | modelo para registrar a exportação do estudo |
| Protocolo de fibromialgia com fases e critérios de passagem escritos | `memory/requisitos/Fisio/protocolos/11-fibromialgia.md` | a intervenção observada |

---

## 4. O que falta

### 4.1 Instrumentos de fibromialgia

Nenhum dos 5 instrumentos atuais é específico de fibromialgia. Conjunto mínimo proposto, com a situação de licença:

| instrumento | mede | quando | licença |
|---|---|---|---|
| **Critérios ACR 2016**: índice de dor generalizada (WPI, 19 regiões, 0–19) + escala de gravidade dos sintomas (SSS, 0–12) | diagnóstico e gravidade | entrada e a cada momento do estudo | versão brasileira validada: **FSQ-Brasil** (2020, validada contra os critérios de 1990, não os de 2016). Licença para software **a conferir** |
| **FIQR** (versão brasileira **FIQR-Br**, Lupi et al. 2017, que corrige problemas da versão de 2013) | impacto da fibromialgia | entrada e a cada momento | **uso acadêmico e clínico gratuito; uso comercial exige licença da Mapi Research Trust**. Software vendido pelo oimpresso é uso comercial até prova em contrário: perguntar à Mapi antes de entrar no enum |
| **PGIC**: impressão global de melhora | percepção de mudança pelo paciente | a partir do 2º momento | a conferir |
| **Sentar e levantar em 30 s** | função física | entrada e a cada momento | teste de desempenho; registrado pelo profissional, sem questionário |
| Escala de sono | sono | entrada e fim | a escolher com a universidade; PSQI tem direito autoral, **a conferir** |
| Escala de catastrofização da dor | fator psicológico | entrada e fim | a escolher com a universidade; **a conferir** |

Regras que valem para todos (ADR 0437 §8): texto da versão brasileira validada, sem alteração, com a referência na tela; instrumento sem licença confirmada **não entra** no enum. Recusas registradas no inventário com o motivo (ADR 0368 §5).

**O que o código precisa:** migração que acrescente os instrumentos aprovados ao enum de `fisio_questionnaires.instrument` (e ao dicionário de domínio que o `dominio:check` lê), o cálculo de pontuação de cada um, e guardar **a versão do instrumento** junto da resposta, para o estudo não misturar versões.

### 4.2 O estudo como entidade

Hoje não há estudo, participante nem visitas. Proposta mínima, tudo com `business_id` (ADR 0093):

- **Estudo:** título, número do parecer do CEP, versão do protocolo, datas de início e fim, pesquisador responsável, instrumentos e momentos de avaliação.
- **Participante:** paciente do estudo, código do participante (não deriva de nome nem CPF), data de entrada, data e motivo de saída.
- **Momentos de avaliação:** por exemplo entrada, 4, 8 e 12 semanas e seguimento, cada um com janela de tolerância (± dias).
- **Pendências:** o questionário que vence em cada momento aparece para o paciente no portal e pode ser lembrado pelo WhatsApp (canal que já existe), e para o profissional na ficha.
- **Perda de seguimento:** registrada com o motivo, nunca apagada.
- **Eventos adversos:** crise que dura mais de 48 h e alerta de dor acima da base viram registro do estudo, para a pesquisadora decidir o que comunicar ao CEP. O software registra e mostra; não classifica gravidade.

### 4.3 Consentimento de pesquisa separado

Hoje existe uma finalidade só, `pesquisa_ia` (`Config/config.php`), que junta "participar de um estudo" com "treinar IA". Para o comitê de ética são coisas diferentes. Proposta:

- `pesquisa` passa a ser **por estudo**: cada estudo tem o seu TCLE, com a versão aprovada pelo CEP; o consentimento aponta para o estudo e para a versão do termo.
- `treino_ia` fica separado e **desligado por padrão** (ADR 0437 §7).
- Quem já tem `pesquisa_ia` concedido não vira participante de estudo automaticamente.
- Revogar pelo portal tira o participante do estudo a partir daquela data; o que já foi coletado segue a regra escrita no TCLE.

### 4.4 Exportação para a universidade

- Arquivo por estudo (CSV), **sem nome, CPF, telefone nem data de nascimento completa**. Cada linha usa o código do participante. A tabela que liga código a paciente fica só na clínica.
- **Dicionário de dados** junto: o que cada coluna significa, unidade, instrumento e versão.
- Dados por momento em formato longo (participante × momento × variável), mais a série diária de dor e adesão.
- **Dia sem check-in sai como ausente**, nunca como dor zero ou adesão zero.
- **Travamento:** depois da data de travamento, mudar um dado do estudo exige justificativa e fica registrado.
- Cada exportação fica no `activity_log`, como já acontece com o prontuário.
- Só quem tem a permissão de pesquisador do estudo exporta.
- Nada de juntar dado de clínicas diferentes (ADR 0437 §7).

---

## 5. Fora do software (lista para a clínica e a universidade)

- Submissão ao CEP pela Plataforma Brasil (Res. CNS 466/2012 e 510/2016).
- TCLE escrito pela pesquisadora; o software só guarda a versão aprovada.
- Registro no ReBEC, se o desenho virar ensaio clínico.
- Cálculo do tamanho da amostra e plano de análise.
- Acordo entre clínica e universidade: guarda da chave de volta, prazo, publicação.
- Revisão clínica do protocolo 11 pela Dra. Dejeane (pendente desde 2026-10-18).

---

## 6. Tarefas propostas

Cada tarefa vira um PR pequeno. A ordem respeita as dependências.

| id | tarefa | depende de | regra a respeitar |
|---|---|---|---|
| P-01 | Conferir a licença de cada instrumento da §4.1 e registrar o resultado | — | sem licença confirmada, não entra |
| P-02 | Separar `pesquisa_ia` em `pesquisa` (por estudo) e `treino_ia` | — | versão sempre do config, nunca do request |
| P-03 | Acrescentar os instrumentos liberados ao enum e o cálculo de pontuação | P-01 | texto validado sem alteração; versão guardada com a resposta |
| P-04 | Estudo, participante, momentos de avaliação e pendências | P-02 | `business_id`; teste de isolamento entre empresas (tenant 98×99, ADR 0358) |
| P-05 | Cobrança do questionário que vence, pelo portal e pelo WhatsApp | P-03, P-04 | lembrete respeita o consentimento de WhatsApp |
| P-06 | Registro de eventos adversos a partir de crise e alerta de dor | P-04 | registra e mostra; não classifica |
| P-07 | Exportação sem identificação, dicionário de dados e travamento | P-04 | registro no `activity_log`; ausente sai como ausente |
| P-08 | Assistente de IA da pesquisadora, só na área do estudo: conferência de qualidade do dado (faltante, fora de faixa, momento vencido), resumo descritivo e sugestão de padrões que antecedem crises | P-07 | decisão [W] 2026-11-11: "seria um auxílio para pesquisador, ele confirma se quiser". Tudo é sugestão que a pesquisadora aceita ou descarta, com registro de quem decidiu; a IA recebe só dado sem identificação (os CSVs da P-07); nada vai para o paciente, o prontuário nem a tela clínica (RDC 657/2022; ADR 0437 §5, ADR 0447); desligado por padrão por configuração da empresa; camada de IA canônica (ADR 0035) |

**Ordem sugerida:** P-01 e P-02 em paralelo; depois P-03 e P-04; depois P-05, P-06 e P-07. P-01, P-02 e P-03 servem ao atendimento mesmo sem estudo e se pagam primeiro.

---

## 7. Perguntas abertas para [W]

1. Qual universidade e quem é a pesquisadora responsável?
2. A Dra. Dejeane participa como pesquisadora ou só como responsável técnica da clínica?
3. Os momentos de avaliação (entrada, 4, 8, 12 semanas e seguimento) ficam assim ou a universidade define?
4. A chave que liga o código ao paciente fica só na clínica, como proposto?

---

## 8. Quem são os melhores e onde a IA entra

Pesquisa de 2026-11-11; fontes na §9.

| quem | o que faz | evidência | o que vale para nós |
|---|---|---|---|
| **Stanza** (Swing Therapeutics, EUA) | app de terapia de aceitação e compromisso para fibromialgia, autoguiado, com receita | autorização De Novo do FDA em maio de 2023; ensaio PROSPER-FM (275 pacientes, 12 semanas, publicado na Lancet em 2024): 70,6% relataram melhora no PGIC contra 22,2% no controle, e melhora no FIQR | é a referência: mostrou que um app de fibromialgia vira evidência e produto aprovado. Usou **PGIC e FIQR**, os mesmos desfechos propostos na §4.1 |
| **ProFibro** (USP) | app gratuito em português para fibromialgia: educação, automonitoramento, sono e exercício graduado | ensaio com 40 pacientes, 6 semanas: app e livro impresso melhoraram igual | o concorrente acadêmico brasileiro; mostra que **conteúdo sozinho não basta**, o diferencial precisa ser outro |
| **Sword Health, Hinge Health** | fisioterapia digital com acompanhamento de movimento | estudos grandes em joelho e coluna; nenhum ensaio específico de fibromialgia encontrado | o modelo de publicar com dado do próprio produto; fibromialgia ainda está descoberta |
| Estudos em andamento | app Bei (Madri, início em 2026); reabilitação remota com visão computacional (NCT06672419) | protocolos, sem resultado | a câmera para exercício em fibromialgia está sendo testada agora, fora do Brasil |

**O problema que todos têm: adesão.** Num estudo de 2026 com app de dor, só 27% dos pacientes cumpriram o critério de adesão e 46% ainda usavam na semana 4. Programas com acompanhamento profissional relatam adesão alta.

**Onde o Fisio pode se destacar, sem cruzar a linha da Anvisa:**

1. **Fisioterapeuta no meio.** O Fisio não é app de autoajuda: o profissional prescreve, acompanha e é avisado. É exatamente o que separa adesão baixa de adesão alta na literatura acima.
2. **Adesão medida, não relatada.** A câmera conta repetições no aparelho (ADR 0447). Nos estudos achados, a adesão é autorrelatada.
3. **Dado diário de vida real em português.** Dor do dia + crise + adesão, no atendimento comum. A pesquisa não achou modelo publicado de previsão de crise de fibromialgia com dado diário de celular; os próprios autores citam a falta de dados longitudinais grandes. Um estudo da clínica com a universidade preenche essa lacuna.
4. **IA que organiza, não que decide:** ditado estruturado (etapa 05), conferência de qualidade do dado antes de exportar (faltante, fora de faixa, momento vencido) e resumo descritivo para a pesquisadora. Tudo revisado por pessoa.
5. **IA de previsão fica com a universidade.** _(2026-11-11: a sugestão de padrões que antecedem crises entrou como P-08, só para a pesquisadora conferir no arquivo, nunca para o paciente nem para o profissional na tela clínica.)_ Buscar o que antecede uma crise no arquivo exportado é pesquisa. Mostrar a previsão ao paciente ou ao profissional dentro do app torna o software dispositivo médico (RDC 657/2022) e depende da consulta à Anvisa já pendente (ADR 0447). O Stanza mostra o caminho: estudo primeiro, aprovação depois.

---

## 9. Fontes (consultadas em 2026-11-11)

- Stanza, resultado do ensaio: https://www.businesswire.com/news/home/20231114748380/en/Swing-Therapeutics-Announces-Positive-Results-from-Pivotal-Phase-3-Trial-of-Stanza-a-Digital-Therapy-for-Fibromyalgia-Symptoms
- Stanza, publicação na Lancet: https://www.businesswire.com/news/home/20240709148056/en/Swing-Therapeutics-Announces-The-Lancet-Publication-of-Data-from-Phase-3-Trial-of-Stanza%E2%84%A2---a-Digital-Behavioral-Therapy-for-Fibromyalgia-Symptoms
- FIQR, licença (Mapi para uso comercial): https://fiqrinfo.ipage.com/index.html e https://www.sralab.org/rehabilitation-measures/revised-fibromyalgia-impact-questionnaire
- FIQR-Br (Lupi et al., 2017): https://informahealthcare.com/doi/abs/10.1080/09638288.2016.1207106 ; versão de 2013 (Paiva et al.): https://www.springermedizin.de/a-brazilian-portuguese-version-of-the-revised-fibromyalgia-impac/8716712
- FSQ-Brasil (2020): https://link.springer.com/10.1186/s42358-020-00139-3 ; FiRST brasileiro (2022): https://link.springer.com/10.1186/s42358-022-00271-2
- ProFibro: https://clinicaltrials.gov/study/NCT03004911 e https://bv.fapesp.br/en/publicacao/197246/effects-of-a-six-week-mobile-app-versus-paper-book-intervent
- App Bei: https://clinicaltrials.gov/study/NCT07090434 ; reabilitação com visão computacional: https://www.syfrah.com/trial/NCT06672419
- Adesão em app de dor (JMIR mHealth 2026): https://mhealth.jmir.org/2026/1/e78827/XML
- Previsão de dor diária em fibromialgia: https://cris.haifa.ac.il/en/publications/predicting-daily-pain-fluctuations-in-fibromyalgia-the-role-of-me/
- Sword e Hinge, avaliação independente (PHTI): https://phti.org/?p=1710

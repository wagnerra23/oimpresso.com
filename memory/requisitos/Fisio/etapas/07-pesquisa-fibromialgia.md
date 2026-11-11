# Etapa 07 — Pesquisa clínica em fibromialgia (Módulo Fisio)

> **Status:** proposta, aguardando aprovação de [W]. Nada aqui foi implementado.
> **Origem:** pedido de [W] em 2026-11-11: o software precisa estar preparado para as atividades de um estudo científico de fibromialgia. Desenho escolhido por [W] no mesmo dia: **clínica + universidade**.
> **Fontes:** esta etapa foi escrita **sem pesquisa na web**. Fatos de licença, referências de validação brasileira e o panorama de concorrentes (§8) estão marcados **"a conferir"**. O que está dito sobre o código foi medido no `main` em 2026-11-11.
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
| **Critérios ACR 2016**: índice de dor generalizada (WPI, 19 regiões, 0–19) + escala de gravidade dos sintomas (SSS, 0–12) | diagnóstico e gravidade | entrada e a cada momento do estudo | a conferir (publicados em artigo; uso em software não confirmado) |
| **FIQR** (versão brasileira) | impacto da fibromialgia | entrada e a cada momento | **a conferir**: a SBR cita FIQR para acompanhamento (protocolo 11); a licença para software e a referência da validação brasileira não foram verificadas |
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

**Ordem sugerida:** P-01 e P-02 em paralelo; depois P-03 e P-04; depois P-05, P-06 e P-07. P-01, P-02 e P-03 servem ao atendimento mesmo sem estudo e se pagam primeiro.

---

## 7. Perguntas abertas para [W]

1. Qual universidade e quem é a pesquisadora responsável?
2. A Dra. Dejeane participa como pesquisadora ou só como responsável técnica da clínica?
3. Os momentos de avaliação (entrada, 4, 8, 12 semanas e seguimento) ficam assim ou a universidade define?
4. A chave que liga o código ao paciente fica só na clínica, como proposto?

---

## 8. Referências de mercado e onde a IA entra (a conferir)

Panorama **de memória, sem pesquisa na web nesta data**; serve para orientar uma pesquisa de verdade, não como fato.

| quem | o que faz | o que vale olhar |
|---|---|---|
| Stanza (Swing Therapeutics, EUA) | app para fibromialgia baseado em terapia de aceitação e compromisso; autorizado pelo FDA como terapia digital, com ensaio clínico próprio | como um app de fibromialgia virou evidência e aprovação regulatória |
| FibroGuide (Universidade de Michigan) | programa gratuito de autocuidado para fibromialgia | conteúdo educativo de base acadêmica |
| Sword Health, Hinge Health, Kaia | fisioterapia digital para dor musculoesquelética, com acompanhamento de movimento por sensor ou câmera e muitos estudos publicados | publicam com dado real do próprio produto |
| REDCap, Castor | plataformas de coleta de dados de pesquisa usadas por universidades | o formato de exportação e o dicionário de dados que o pesquisador espera |

**Onde o Fisio pode se destacar, sem cruzar a linha da Anvisa:**

1. **Dado de vida real brasileiro.** Dor diária pelo paciente + adesão medida pela câmera + desfechos validados, em português, no atendimento comum. A maior parte dos estudos citados é dos EUA ou da Europa. *A conferir.*
2. **Adesão objetiva.** A câmera conta repetições no aparelho (ADR 0447). Em estudo de exercício, adesão costuma ser autorrelatada; medida objetiva é diferencial para publicação.
3. **IA que organiza, não que decide:** ditado transcrito e estruturado (etapa 05), conferência de qualidade do dado antes da exportação (faltantes, valores fora da faixa, momentos vencidos) e resumo descritivo para a pesquisadora. Tudo revisado por humano.
4. **IA que a universidade roda, não o app.** Busca de padrões de crise (sono, carga, dia da semana) feita pela pesquisadora sobre o arquivo exportado, fora do software. O mesmo modelo **mostrado ao paciente ou ao profissional** como previsão vira software como dispositivo médico (RDC 657/2022) e exige a consulta à Anvisa que já está pendente (ADR 0447).

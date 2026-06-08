# 06 — Glossário de Domínio

Termos de legislação trabalhista brasileira e do ecossistema UltimatePOS usados no projeto.

## Legislação e compliance

| Termo | Significado |
|---|---|
| **CLT** | Consolidação das Leis do Trabalho — Decreto-Lei 5.452/1943 |
| **Reforma Trabalhista** | Lei 13.467/2017 — flexibilizou banco de horas (acordo individual, 6 meses) |
| **Portaria MTP 671/2021** | Regula o Sistema de Registro Eletrônico de Ponto (SREP). Sucede a 1510/2009 e 373/2011 |
| **LGPD** | Lei Geral de Proteção de Dados — Lei 13.709/2018 |
| **eSocial** | Sistema único do governo para envio de informações trabalhistas, previdenciárias, fiscais |
| **MTP** | Ministério do Trabalho e Previdência (atualmente MTE — Ministério do Trabalho e Emprego, mas a portaria mantém sigla MTP) |

## Equipamentos e arquivos

| Termo | Significado |
|---|---|
| **REP** | Registrador Eletrônico de Ponto — equipamento/sistema que registra marcações |
| **REP-P** | REP **P**rograma — software (app, web). Permite marcação em dispositivo do colaborador |
| **REP-C** | REP **C**onvencional — equipamento físico homologado (relógio de parede tradicional) |
| **REP-A** | REP **A**lternativo — raramente usado, para situações excepcionais |
| **AFD** | Arquivo Fonte de Dados — exportação bruta de todas as marcações para fiscalização |
| **AFDT** | Arquivo Fonte de Dados **T**ratados — AFD após ajustes autorizados (ex.: intercorrências aplicadas) |
| **AEJ** | Arquivo Eletrônico de **J**ornada — totalizações por dia para auditoria |
| **NSR** | Número Sequencial de Registro — contador inviolável por REP |

## Regras de jornada (CLT)

| Termo | Significado | Base legal |
|---|---|---|
| **Jornada** | Tempo à disposição do empregador (entrada até saída, descontado intervalo) | Art. 58 CLT |
| **Intrajornada** | Intervalo dentro da jornada (almoço) — mínimo 60min se >6h | Art. 71 CLT |
| **Interjornada** | Descanso entre o fim de uma jornada e o início da próxima — mínimo 11h | Art. 66 CLT |
| **Tolerância** | Até 5 min por marcação, máx 10 min/dia — não contam como atraso/HE | Art. 58 §1º CLT |
| **HE** | Hora Extra — até 2h/dia com adicional mínimo de 50% | Art. 59 CLT + CF/88 VII XVI |
| **Adicional noturno** | 20% sobre hora noturna (22h–5h urbano) + hora ficta de 52min30s | Art. 73 CLT |
| **DSR** | Descanso Semanal Remunerado — 1 dia/semana, preferencialmente domingo | Lei 605/1949 |
| **Hora noturna ficta** | No trabalho noturno, 1 hora equivale a 52min30s (não a 60min) | Art. 73 §1º |

## Estados e conceitos de negócio

| Termo | Significado |
|---|---|
| **Banco de horas** | Modalidade de compensação: excesso num dia compensa falta em outro |
| **Intercorrência** | Evento que interrompe a jornada (saída e retorno) — consulta médica, reunião externa, etc. |
| **Espelho de ponto** | Documento mensal com todas as marcações e totais do colaborador |
| **Apuração** | Cálculo consolidado diário (previsto × realizado, com aplicação de regras) |
| **Fechamento** | Consolidação do período (mensal) — após, marcações não mudam mais |
| **Escala** | Template de horários que o colaborador deve cumprir |
| **Turno** | Um dia específico de uma escala (ex.: segunda 08:00-17:00) |

## Eventos eSocial

| Evento | Descrição |
|---|---|
| **S-1010** | Tabela de Rubricas (parametrização de verbas) |
| **S-2230** | Afastamento Temporário (atestado, licença) |
| **S-2240** | Condições Ambientais do Trabalho |

## UltimatePOS / Essentials

| Termo | Significado |
|---|---|
| **business** | Tabela multi-empresa do UltimatePOS (tenant lógico) |
| **business_id** | Coluna presente em quase toda tabela, scope obrigatório |
| **Essentials** | Módulo oficial do UltimatePOS com funcionalidades HR básicas |
| **HRM** | Human Resource Management — sub-módulo do Essentials |
| **Attendance** | Funcionalidade nativa de ponto do Essentials (GPS + clock-in/out) — insuficiente para BR |
| **Shifts** | Escalas no Essentials |
| **AdminLTE** | Framework CSS do UltimatePOS (Bootstrap 4 + tema dashboard) |

## Siglas técnicas recorrentes

| Sigla | Significado |
|---|---|
| **ADR** | Architecture Decision Record — registro formal de decisão arquitetural |
| **RBAC** | Role-Based Access Control |
| **DDD** | Domain-Driven Design |
| **CQRS** | Command Query Responsibility Segregation |
| **FK** | Foreign Key |
| **NSR** | (ver acima) |
| **PKCS#7** | Padrão de assinatura digital — envelope criptográfico |
| **ICP-Brasil** | Infraestrutura de Chaves Públicas Brasileira |

## Nomes internos do código

| Nome | O que é |
|---|---|
| `PontoWr2` | Este módulo inteiro |
| `ponto_colaborador_config` | Bridge table — configuração de ponto por user do UltimatePOS |
| `ponto_marcacoes` | Tabela append-only de todas as marcações |
| `ponto_apuracao_dia` | Resultado consolidado diário |
| `ponto_banco_horas_saldo` | Saldo atual |
| `ponto_banco_horas_movimentos` | Ledger append-only de movimentações |
| `ponto_intercorrencias` | Eventos justificados do expediente |
| `ponto_reps` | Cadastro dos Registradores Eletrônicos de Ponto |
| `ponto_importacoes` | Histórico de imports AFD/AFDT/CSV |

---

**Última atualização:** 2026-04-18

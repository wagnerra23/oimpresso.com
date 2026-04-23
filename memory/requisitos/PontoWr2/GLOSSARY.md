# Glossário · PontoWr2

Termos técnicos e legais do domínio de ponto eletrônico brasileiro.

## AFD
**Arquivo Fonte de Dados.** Formato fixed-width definido pela SEFIP/MTP pra transferência de marcações de ponto. Cada linha é um registro tipo 1 (cabeçalho), 2/3 (ajuste/marcação), 5 (colaborador), 9 (trailer com contagem).

## ART (Anotação de Responsabilidade Técnica)
Documento exigido pra registrar o responsável técnico pelo sistema REP-P na SEFIP. Não é gerado pelo PontoWr2.

## Banco de horas
Saldo acumulado de horas extras/faltas de um colaborador, podendo ser positivo (a pagar/folgar) ou negativo (a compensar). Sujeito a acordo coletivo (Art. 59 CLT, Art. 611-B VIII).

## CLT
Consolidação das Leis do Trabalho. Todas as regras de jornada, intervalo, hora extra vêm dela.

## Escala
Definição de horários de trabalho de um colaborador — 5×2, 6×1, 12×36, etc. Entity `Escala` no módulo.

## Espelho
Relatório mensal do ponto de um colaborador — "espelho" das marcações. Documento frequentemente impresso ou assinado pelo funcionário.

## Extra (HE)
Hora extra. Acima de 8h/dia (jornada padrão) ou do pactuado. Tem adicional (geralmente 50% ou 100%).

## Intercorrência
Evento fora do padrão da marcação — atestado, compensação, falta justificada, troca de turno. Precisa de aprovação do gestor.

## Interjornada
Intervalo **entre** jornadas (fim de um dia e início do próximo). Mínimo legal: 11h (Art. 66 CLT).

## Intrajornada
Intervalo **dentro** da jornada (almoço). 1h mínimo pra jornadas >6h, 15min pra 4-6h (Art. 71 CLT).

## Jornada
Tempo efetivamente trabalhado num dia. Padrão CLT: 8h (Art. 58).

## LGPD
Lei Geral de Proteção de Dados. PIS, biometria, localização GPS de marcações são dados sensíveis (Art. 5º II).

## Marcação
Cada registro de entrada/saída. No PontoWr2 vira linha em `ponto_marcacoes` (append-only — ADR ARQ-0001).

## PIS
Programa de Integração Social. Número único do trabalhador (11 dígitos). Usado como identificador principal nos AFDs.

## Portaria 671/2021
Norma do Ministério do Trabalho que regula REP-C (convencional), REP-A (alternativo) e REP-P (programa). Define formato AFD, imutabilidade, hash de integridade.

## REP-P
Registrador Eletrônico de Ponto via Programa. Software (vs REP-C que é relógio físico). Exige ART, hash SHA256 das marcações, AFD exportável sob demanda.

## SEFIP
Sistema Empresa de Recolhimento do FGTS e Informações à Previdência Social. Nomenclatura legada — hoje substituído pelo eSocial em boa parte.

## Tolerância
Margem em minutos aceita sem considerar extra/atraso. Padrão CLT: 10min/dia (5min de entrada + 5min de saída). Configurável em `ponto_configuracoes`.

## Trailer (tipo 9)
Última linha de um AFD. Contém contagem total de registros tipo 2/3. Usado pra validar integridade do arquivo.

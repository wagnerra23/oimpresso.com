# 01 — Visão Geral do Projeto

## Nome

**Ponto WR2** — Módulo de Ponto Eletrônico para UltimatePOS 6

## Problema que resolve

Empresas brasileiras com mais de 20 funcionários são obrigadas por lei a manter **registro eletrônico de ponto** conforme a **Portaria MTP 671/2021** (que sucedeu a 1510/2009 e 373/2011). Os clientes da WR2 que usam UltimatePOS não têm, nativamente, um módulo de ponto que:

1. Cumpra a Portaria 671/2021 (REP-P, REP-C, AFD, AFDT, AEJ, NSR sequencial, imutabilidade)
2. Implemente regras CLT (tolerâncias, intrajornada, interjornada, HE, adicional noturno)
3. Gerencie banco de horas conforme Lei 13.467/2017 (Reforma Trabalhista)
4. Permita registrar **intercorrências no expediente** (saídas/retornos justificados)
5. Integre com **eSocial** (S-1010, S-2230, S-2240)
6. Importe arquivos AFD/AFDT de REPs homologados

O **Essentials & HRM** da UltimatePOS tem um módulo de Attendance básico (clock-in/out com GPS), mas **não é suficiente para compliance brasileiro**.

## Proposta de valor

Um módulo Laravel instalável sobre UltimatePOS + Essentials, que adiciona conformidade brasileira completa, reutiliza o cadastro de colaboradores e o scope multi-empresa do UltimatePOS (`business_id`), e expõe uma interface coerente com o restante do ERP.

## Escopo funcional (O QUE o módulo faz)

### Núcleo
- Cadastro de REPs (REP-P, REP-C, REP-A) com certificado digital
- Cadastro de escalas (fixa, flexível, 12x36, 6x1, 5x2) e turnos por dia da semana
- Configuração de ponto por colaborador (bridge com UltimatePOS users)
- Captura de marcações via:
  - REP-P (web app/mobile com GPS, IP, hash encadeado, assinatura digital)
  - Importação AFD/AFDT (parser posicional ISO-8859-1)
  - Lançamento manual autorizado (com log e justificativa)
  - Integração API (Sanctum)

### Regras e cálculos
- Apuração diária com regras CLT encadeadas (Chain of Responsibility)
- Banco de horas com ledger append-only, multiplicadores, prazo de compensação
- Intercorrências com fluxo de aprovação (RASCUNHO → PENDENTE → APROVADA/REJEITADA → APLICADA)
- Detecção de divergências (interjornada violada, intrajornada curta, HE acima do limite diário)

### Consulta e saída
- Dashboard gestor (KPIs, gráficos, fila de aprovações, feed de atividade)
- Espelho de ponto mensal por colaborador (tela + PDF)
- Relatórios: AFD/AFDT/AEJ, HE, Banco de Horas, Atrasos/Faltas, eSocial
- App mobile de marcação (React Native, offline-first)

### Administração
- Fila de aprovações em lote
- Fechamento mensal com revisão e "congelamento"
- Configurações (regras CLT customizáveis por empresa, REPs, escalas)

## Fora de escopo (O QUE o módulo NÃO faz)

- Folha de pagamento completa (deixa para módulo/serviço externo)
- Gestão de benefícios, férias, 13º
- Processo seletivo, onboarding, avaliação de desempenho
- Substituir o cadastro de funcionários do UltimatePOS (apenas estende via bridge)

## Usuários-alvo

| Persona | Acesso | Uso típico |
|---|---|---|
| Colaborador | app mobile + portal | Bate ponto, vê espelho, solicita intercorrência |
| Gestor direto | portal | Aprova intercorrências do time, vê presenças |
| RH / Gestora (Eliana) | portal completo | Cadastra escalas, consolida apuração, gera relatórios |
| Admin UltimatePOS | portal completo | Configura REPs, regras, permissões |
| Fiscal do trabalho | exportação AFD | Exige AFD; não acessa o sistema |

## Premissas

- UltimatePOS v6.12+ e Essentials & HRM v5.4+ já instalados
- MySQL 8.0+ (triggers de imutabilidade são obrigatórios — MariaDB precisa validação extra)
- Redis 7 disponível para filas (Laravel Horizon)
- O cliente final possui certificado ICP-Brasil A1 válido por empresa

## Métricas de sucesso

- Auditoria fiscal do trabalho: AFD aceito sem ressalvas
- eSocial: S-2240 aceito em ambiente de produção
- Performance: apuração de 1.000 colaboradores em menos de 3 minutos
- UX: gestor aprova 50 intercorrências em menos de 5 minutos na fila em lote

---

**Última atualização:** 2026-04-18

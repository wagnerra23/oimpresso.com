---
sessao: "_saida-02"
thread: "02 · Fundo: postos, operações, apontamentos (append-only), programação, eventos (append-only), turno e custo-hora por empresa"
emendas: ["E02"]
dono: "[CL]"
data: 2026-10-15
tipo: recibo
entregue_em: "3 PRs sequenciais (claude/producao-thread-02 → -02b → -02c), cada um ≤300 linhas"
base_lida: wagnerra23/oimpresso.com@main 0455845c32
---
# _saida-02

## Entregue (thread 02 + emenda E02 incorporada)
Três PRs, mergeados nesta ordem:

| PR | O quê |
|----|-------|
| 1/3 | Migration única `2026_10_15_000001_create_pcp_fundo_tables.php` com 6 tabelas · triggers append-only · migrations carregadas pelo `ProducaoServiceProvider` · `Modules/Producao/Tests/Feature` no `phpunit.xml` · contrato na lane `verticais-pest.yml` (MySQL) |
| 2/3 | 6 entities com `HasBusinessScope` · `TurnoService` · UC-PCV-28 |
| 3/3 | `ApontamentoService` · `CustoRealService` · UC-PCV-31 e UC-PCV-32 (E02) · este recibo |

Tabelas (prefixo `pcp_`, permitido pela ADR 0431; todas com `business_id` indexado + FK, ADR 0093):
- `pcp_turnos` — 1 por empresa, padrão 08h–18h (D-TURNO). Sem linha, o `TurnoService` devolve o padrão.
- `pcp_postos` — **E02:** `tipo_custo` `maquina|pessoa` + `custo_hora`; veículo é posto `maquina` com `veiculo=1` e `custo_km`. Capacidade, unidade e horas/dia configuráveis (D4). Código único por empresa.
- `pcp_operacoes`, `pcp_programacoes` (mutável; mudança vira evento nas threads 06/07).
- `pcp_apontamentos` — **append-only** por trigger (UPDATE e DELETE dão `SQLSTATE 45000`), como `ponto_marcacoes`. Abrir e fechar são duas linhas (`tipo` `inicio`/`fim`, a de fim aponta `inicio_id`). **E02:** posto `maquina` grava `maquina_id` + `iniciado_por` e `user_id` nulo; posto `pessoa` grava `user_id`. `chave_idempotencia` única por `business_id`. `custo_hora` e `tipo_custo` são copiados do posto no início.
- `pcp_eventos` — append-only, mesma trigger.

A OS fica em (`os_tipo`, `os_id`) sem FK: o PCP é camada única e Repair/CV/Oficina seguem donos da OS (D-RELACAO).

## Regras do serviço
- 1 apontamento de **pessoa** aberto por usuário; N de **máquina**. O de máquina não ocupa a pessoa (UC-PCV-31).
- Reenvio com a mesma chave devolve a mesma linha; a mesma chave em outra empresa é outra linha.
- `origem=manual` exige justificativa (D3). Fechamento pelo turno (`origem=turno`) e pelo ponto (`origem=ponto`) já têm valor no enum; quem fecha é a thread 04.
- `business_id` entra por parâmetro em toda consulta do serviço (fila e job não têm sessão) — nenhum `withoutGlobalScopes` em código de produção.

## Custo real da OS — regra mestre de valor (UC-PCV-32)
`custo = Σ(horas máquina × custo_hora) + Σ(horas pessoa × custo_hora) + terceiros`. Conta em centavos × segundos (inteiros), arredonda uma vez por tipo, meio para cima; sem float. Só entram pares fechados.

**Impacto:** tabelas novas. **Nenhum dado existente muda** — não há venda, título, estoque ou OS lida ou escrita por esta thread.

**Dupla prova** (dado do caso: 2 h de máquina a 42,00/h + 1 h de pessoa a 28,00/h + terceiro 480,00):

| Caminho | Conta | Resultado |
|---|---|---|
| 1 · `CustoRealService` | máquina 4200 ¢ × 7200 s ÷ 3600 = 8400 ¢ · pessoa 2800 ¢ × 3600 s ÷ 3600 = 2800 ¢ · terceiro 48000 ¢ | **592,00** (84,00 + 28,00 + 480,00) |
| 2 · SQL direto no teste | `ROUND(SUM(TIMESTAMPDIFF(SECOND,…)/3600 × custo_hora) + 480, 2)` | **592,00** |

O que **não** pode entrar, e está semeado no mesmo caso: um apontamento aberto da mesma OS · outra OS da mesma empresa (5 h × 42 = 210,00, que é o controle negativo — o filtro discrimina) · a mesma OS na empresa 99 (10 h × 42). E o custo-hora do posto vai a 999,00 **depois** dos apontamentos: o total segue 592,00, porque o custo é o copiado no início (sem a cópia daria 2506,00).

## Teste
`Modules/Producao/Tests/Feature/ProducaoFundoContratoTest.php`, tenant 98 × 99, um `it` por UC:

| Caso | Discriminante | Controle negativo |
|---|---|---|
| append-only | UPDATE e DELETE caem nas duas tabelas | `pcp_postos` aceita update e a linha do apontamento segue intacta |
| UC-PCV-28 (entities) | como 99 a mesma consulta devolve só a 99 | sem escopo existem as duas linhas |
| UC-PCV-28 (serviço) | operador da 98 em posto da 99 → não encontrado, nada gravado | o aberto existe na 99 |
| UC-PCV-31 | 2º de pessoa recusado e não gravado; a hora da Roland não é do Diego | 2º de máquina é aceito (3 abertos) |
| UC-PCV-32 | dois caminhos dão 592,00 | OS 7002 dá 210,00 |

Os testes rodam no CI (lane Verticais · MySQL), não nesta máquina (ADR 0062). `php -l` local passou nos 12 arquivos.

## Fora do prefixo, e por quê
`Modules/Producao/Providers/ProducaoServiceProvider.php` (1 linha: carregar as migrations, sem isso as tabelas não existem), `phpunit.xml` (registrar `Modules/Producao/Tests/Feature`, proibicoes.md) e `.github/workflows/verticais-pest.yml` (a lane MySQL que roda o contrato; em sqlite ele pula).

## Pedidos ao Cowork / threads seguintes
1. A prova de terceiros entra por parâmetro no `CustoRealService`. O lançamento do terceiro (valor, conta a pagar) é da thread 19; até lá a ficha (thread 08) passa 0.
2. Pausa (`pausa`/`retomada`) **não** está no enum de `tipo`: a E04 traz pausa com motivo e os motivos vêm do posto (E03). Entra por migration nova na thread 04, sem tocar as linhas existentes.
3. Corrida de dois inícios de pessoa simultâneos do mesmo usuário não é bloqueada no banco (a regra é do serviço). Se a fila offline da thread 04 puder produzir isso, ela deve serializar por usuário.

## Próxima
03 e 04 (podem andar em paralelo, conforme o índice).

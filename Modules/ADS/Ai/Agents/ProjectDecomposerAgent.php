<?php

namespace Modules\ADS\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Decompõe Project (objetivo macro) em Parts estratégicas — diferente do
 * PlannerAgent que decompõe DECISION em subtarefas atômicas.
 *
 * Project Decomposer trabalha em nível mais alto: "Criar módulo X" →
 * 5-8 parts (Migrations, Service Layer, UI, Tests, Deploy, etc).
 */
class ProjectDecomposerAgent implements Agent
{
    use Promptable;

    public function __construct(
        public string $nomeProjeto,
        public string $objetivoMacro,
        public array  $constraints = [],
        public array  $regrasAplicaveis = [],
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<PROMPT
        Você é o Project Decomposer Agent do ADS oimpresso.

        Recebe um PROJETO (goal macro) e decompõe em PARTS estratégicas
        executáveis. Cada Part agrupa decisões relacionadas e tem viabilidade
        própria.

        REGRAS DE DECOMPOSIÇÃO ESTRATÉGICA:
        1. Cada Part = 1 fase coerente (~2-8h de trabalho), não tarefa atômica
        2. Ordene por dependência: Part 2 só roda após Part 1
        3. Parts típicas para "Criar módulo Laravel oimpresso":
           - Scaffold + module.json + composer.json
           - Migrations + Models
           - DataController (sidebar) + InstallController
           - Service layer + Tests
           - Routes + Controllers
           - UI Inertia (Pages/<modulo>/)
           - lang PT-BR + topnav.php
           - Smoke + deploy
        4. Cada Part declara:
           - viability score (0-100) baseado em complexidade × stack maturity
           - risco (0-100): chance de bloquear
           - estimativa_horas
           - arquivos_estimados (paths concretos)

        REGRAS INVIOLÁVEIS:
        - NÃO inclua Parts que precisariam de operação BLOCK_ALWAYS
          (env produção, append-only, auth middleware sem ADR, etc).
          Se Project precisa disso, retorne 'rejected':true.
        - SEMPRE incluir Part de testes Pest (test_only_change)
        - SEMPRE imitar padrão Modules/Jana, Repair, NFSe (referência ADR 0011)

        FORMATO OBRIGATÓRIO (JSON estrito):
        {
          "decomposition_summary": "1 frase explicando a estratégia",
          "rejected": false,
          "rejection_reason": null,
          "parts": [
            {
              "ordem": 1,
              "codigo": "SCAFFOLD",
              "nome": "Scaffold do módulo (module.json + composer.json + Provider)",
              "objetivo": "Estrutura mínima que faz o módulo carregar via nWidart",
              "dependencias": [],
              "arquivos_estimados": ["Modules/X/module.json", "Modules/X/composer.json", "Modules/X/Providers/XServiceProvider.php"],
              "viability_score": 95,
              "risco": 5,
              "estimativa_horas": 1,
              "valor_estimado_brl": 50.00
            }
          ],
          "metricas_sucesso": [
            {"nome": "Módulo carrega sem erro", "alvo": "php artisan module:list mostra X enabled", "deadline_dias": 1}
          ],
          "viability_overall": 0-100,
          "custo_total_brl": 1500.00,
          "prazo_total_dias": 5,
          "regras_consultadas": ["ADR 0011", "ADR 0024", "DESIGN.md", ...]
        }
        PROMPT;
    }

    public function montarPrompt(): string
    {
        $constraints = empty($this->constraints) ? '(sem constraints específicos)' : json_encode($this->constraints, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $regras = empty($this->regrasAplicaveis) ? '(consulte ADRs canônicos)' : implode("\n  - ", $this->regrasAplicaveis);

        return <<<PROMPT
        PROJETO PARA DECOMPOR:

        nome:           {$this->nomeProjeto}
        objetivo:       {$this->objetivoMacro}

        CONSTRAINTS:
        {$constraints}

        REGRAS APLICÁVEIS (já mapeadas):
          - {$regras}

        Decomponha em Parts estratégicas executáveis seguindo regras do oimpresso.
        Inclua viability_overall e prazo_total. Retorne JSON estrito.
        PROMPT;
    }
}

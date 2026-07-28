<?php

return [
    'name' => 'PontoWr2',

    /*
    |--------------------------------------------------------------------------
    | Identificação do módulo na UI / instalador
    |--------------------------------------------------------------------------
    | Usado pelo InstallController (ver Modules/Jana como referência).
    */
    'module_label'       => 'Ponto WR2',
    'module_description' => 'Ponto Eletrônico · Portaria 671/2021',
    'module_icon'        => 'fa fa-clock-o',
    'module_version'     => '0.1',
    'pid'                => null, // preencher com product ID da WR2 quando houver

    /*
    |--------------------------------------------------------------------------
    | Regras CLT / Reforma Trabalhista
    |--------------------------------------------------------------------------
    */
    'clt' => [
        'tolerancia_minutos_por_marcacao'   => 5,    // Art. 58 §1º CLT
        'tolerancia_maxima_diaria_minutos'  => 10,   // Art. 58 §1º CLT
        'interjornada_minima_horas'         => 11,   // Art. 66 CLT
        'intrajornada_minima_minutos'       => 60,   // Art. 71 CLT (> 6h)
        'hora_noturna_ficta_segundos'       => 3150, // 52min30s (Art. 73 §1º)
        'adicional_noturno_percentual'      => 20,   // Art. 73 CLT (min)
        'limite_he_diaria_horas'            => 2,    // Art. 59 CLT
        'adicional_he_percentual'           => 50,   // Art. 7º XVI CF/88
        'adicional_dsr_percentual'          => 100,  // Art. 9º Lei 605/49
    ],

    /*
    |--------------------------------------------------------------------------
    | Banco de Horas
    |--------------------------------------------------------------------------
    */
    'banco_horas' => [
        'habilitado'                   => true,
        'prazo_compensacao_meses'      => 6,    // Reforma Trabalhista — acordo individual
        'saldo_maximo_horas'           => 200,
        'saldo_minimo_horas'           => -40,
        'multiplicador_credito'        => 1.0,  // pode ser 1.5 se acordo coletivo
        'multiplicador_debito'         => 1.0,
        'converter_he_em_bh_default'   => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | REP / AFD (Portaria MTP 671/2021)
    |--------------------------------------------------------------------------
    */
    'rep' => [
        'tipos_permitidos'        => ['REP_P', 'REP_C', 'REP_A'],
        'nsr_verificar_sequencia' => true,
        'assinar_marcacoes'       => true,       // PKCS#7 A1
        'certificado_icp_path'    => env('PONTO_CERT_ICP_PATH'),
        'certificado_icp_pass'    => env('PONTO_CERT_ICP_PASS'),
    ],

    'afd' => [
        'encoding'               => 'ISO-8859-1',
        'max_filesize_mb'        => 50,
        'chunk_size_linhas'      => 1000,
        'validar_hash_registros' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Imutabilidade de marcações
    |--------------------------------------------------------------------------
    */
    'marcacao' => [
        'janela_correcao_minutos' => 5,   // após, só via anulação + nova marcação
        'forcar_append_only'      => true,
        'hash_algoritmo'          => 'sha256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Integração eSocial
    |--------------------------------------------------------------------------
    */
    'esocial' => [
        'ambiente'       => env('ESOCIAL_AMBIENTE', 'homologacao'),
        'eventos'        => ['S-1010', 'S-2230', 'S-2240'],
        'tp_amb'         => env('ESOCIAL_TP_AMB', 2),
        'proc_emi'       => 1,
        'ver_proc'       => '1.0.0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Features de IA do Ponto (plano de flags próprio — separado da Jana)
    |--------------------------------------------------------------------------
    | `enabled` é o master switch; as três abaixo ligam features específicas.
    | Todas nascem DESLIGADAS — o default false é o comportamento vigente e
    | NÃO muda com esta migração.
    |
    | Consumidores:
    |  - `Modules\Ponto\Services\IntercorrenciaAIClassifier` (classificação)
    |  - `app/Http/Middleware/HandleInertiaRequests` — expõe como prop Inertia
    |    `ai.*` (hook `useAiFlags()` em resources/js/Hooks/usePageProps.ts)
    |
    | Estavam em `env()`/`getenv()` DENTRO desses consumidores, fora de config/.
    | Com `config:cache` ligado em produção o `.env` não chega a ser carregado
    | (`LoadEnvironmentVariables::bootstrap()` retorna cedo quando
    | `configurationIsCached()`), então as flags eram INOPERANTES: ligar
    | `AI_ENABLED=true` no .env de prod não ligava nada. Medido 2026-07-28 —
    | `configurationIsCached: true` e, como controle, `env('APP_ENV')` → NULL
    | (a chave existe no .env). Avaliadas aqui, rodam durante o próprio
    | `php artisan config:cache`, quando o .env ainda está carregado.
    |
    | `model` idem — era `env('OPENAI_MODEL', 'gpt-4o-mini')` no classifier.
    | A API key NÃO é duplicada aqui: vem de `config('ai.providers.openai.key')`
    | (dono do tema é o config do laravel/ai).
    */
    'ai' => [
        'enabled'                      => env('AI_ENABLED', false),
        'classificacao_intercorrencia' => env('AI_CLASSIFICACAO_INTERCORRENCIA', false),
        'explicacao_divergencia'       => env('AI_EXPLICACAO_DIVERGENCIA', false),
        'geracao_justificativa'        => env('AI_GERACAO_JUSTIFICATIVA', false),
        'model'                        => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integração com UltimatePOS (bridge)
    |--------------------------------------------------------------------------
    */
    'ultimatepos' => [
        'user_model'            => \App\User::class,          // padrão UltimatePOS
        'business_model'        => \App\Business::class,      // multi-empresa
        'essentials_user_model' => \Modules\Essentials\Entities\EssentialsUserShiftHistory::class,
        'usar_business_scope'   => true,
    ],
];

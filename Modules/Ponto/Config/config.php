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
        // PKCS#7 A1. ⚠️ A assinatura NÃO está implementada (US-PONTO-009 / GAP-PONTO-001):
        // nenhum código lê esta flag nem o certificado, e `ponto_marcacoes.assinatura_digital`
        // fica sempre NULL. Até 2026-09-28 o default era `true`, e a tela de Configurações
        // afirmava "Assinar marcações: Sim" sobre uma função inexistente. `false` diz a verdade.
        // Literal, e não env(): ligar a assinatura exige implementá-la, e quem implementar troca
        // aqui no mesmo PR (e revisa o UC-CFGIDX-03). Defendido por UC-CFGIDX-03.
        'assinar_marcacoes'       => false,
        'certificado_icp_path'    => env('PONTO_CERT_ICP_PATH'),
        'certificado_icp_pass'    => env('PONTO_CERT_ICP_PASS'),
    ],

    /*
    | Comprovante de intercorrência (atestado — dado de saúde, LGPD Art. 11).
    | O disco NUNCA pode ser o `local`: neste app ele aponta para public_path('uploads'),
    | servido direto pelo webserver. O default `arquivos` fica em storage/app (fora do
    | webroot). Download só pela rota autenticada — UC-INTCRE-04.
    */
    'intercorrencias' => [
        // Literal, não env(): o baseline do Larastan conta os env() deste arquivo (ratchet).
        'anexo_disk' => 'arquivos',
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
    | App de ponto (Capacitor) — App Links / Universal Links
    |--------------------------------------------------------------------------
    | Servidos em /.well-known/assetlinks.json e /.well-known/apple-app-site-association.
    | Vazio = 404 (nunca publicar um arquivo que não vale). Os valores vêm do .env de
    | produção — após mudar o .env, rodar `php artisan config:cache`.
    | Fingerprints: SHA-256 do certificado, "AA:BB:..." (32 pares), separados por vírgula —
    | com Play App Signing, listar a upload key E a app signing key do Play Console.
    */
    'app_links' => [
        'android_package'   => env('PONTO_ANDROID_PACKAGE', ''),
        'android_sha256'    => env('PONTO_ANDROID_SHA256', ''),
        'ios_app_id'        => env('PONTO_IOS_APP_ID', ''),   // "<TeamID>.<bundleId>"
        'paths'             => ['/ponto/mobile*'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Integração com UltimatePOS (bridge)
    |--------------------------------------------------------------------------
    */
    'ultimatepos' => [
        'user_model'            => \App\User::class,          // padrão UltimatePOS
        'business_model'        => \App\Business::class,      // multi-empresa
        'usar_business_scope'   => true,
    ],
];

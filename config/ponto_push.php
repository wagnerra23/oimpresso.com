<?php

/*
|--------------------------------------------------------------------------
| Ponto — lembrete de bater ponto por push (ADR 0423)
|--------------------------------------------------------------------------
| Desligado por padrão. `fcm_credentials` é o CAMINHO do JSON da conta de serviço do Firebase
| no servidor (fora do repo, chmod 600; o original fica no Vaultwarden) — nunca o conteúdo.
| Fica em `config/` (não em Modules/Ponto/Config) porque lê `env()`: fora do diretório de
| config o valor some com `config:cache` (larastan.noEnvCallsOutsideOfConfig).
*/

return [
    'enabled'              => (bool) env('PONTO_PUSH_ENABLED', false),
    'fcm_project_id'       => env('PONTO_PUSH_FCM_PROJECT_ID'),
    'fcm_credentials'      => env('PONTO_PUSH_FCM_CREDENTIALS'),
    'antecedencia_minutos' => 5,
    'janela_minutos'       => 5,
];

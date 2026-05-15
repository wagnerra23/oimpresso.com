<?php

/**
 * Teste do matcher da skill `cliente-funcionario-collector` (Tier A — ADR 0144 proposed).
 *
 * Skill matchers rodam runtime Claude — este teste valida as REGRAS DE TRIGGER
 * isoladas como funções puras (sem invocar harness). Catalogadas em
 * `.claude/skills/cliente-funcionario-collector/SKILL.md` §"Quando ativa".
 *
 * Casos cobertos:
 *   A) business_id conhecido + perfil maduro → SKIP (não ativa)
 *   B) business_id desconhecido → ATIVA criar stub
 *   C) Nome próprio + role operacional + cliente → ATIVA criar/atualizar funcionário
 *   D) PII real detectada (CPF formato XXX.XXX.XXX-XX) → ATIVA + ALERTA LGPD bloqueia git
 *   E) Wagner próprio biz=1 → NÃO ativa (caso especial dono)
 *   F) Prompt vazio → NÃO ativa
 *
 * Heurística: replica regras §"Quando ativa" e §"Quando NÃO ativar" do SKILL.md.
 * Quando regra mudar no SKILL.md, atualizar este teste.
 */

const COLLECTOR_BIZ_NAO_ATIVAR = [1, 4, 99];

/** Regex CPF formato XXX.XXX.XXX-XX (com pontuação completa, evita números soltos). */
const COLLECTOR_REGEX_CPF = '/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/';

/**
 * Decide se a skill ativa pra um prompt + estado de perfis no FS.
 *
 * @param  string  $prompt  Texto da conversa (Wagner + Claude).
 * @param  array<string,bool>  $perfisExistentes  ['martinho-cacambas' => true, 'rotalivre' => true, ...]
 * @return array{ativa:bool, motivo:string, alertaLgpd:bool}
 */
function clienteFuncionarioCollectorDecide(string $prompt, array $perfisExistentes = []): array
{
    $prompt = trim($prompt);

    if ($prompt === '') {
        return ['ativa' => false, 'motivo' => 'prompt vazio', 'alertaLgpd' => false];
    }

    // PII guard antecipa qualquer outra decisão — bloqueia commit + alerta LGPD.
    $alertaLgpd = (bool) preg_match(COLLECTOR_REGEX_CPF, $prompt);

    // Trigger 1 — business_id mencionado.
    if (preg_match('/(?:business_id|biz)\s*[=:]?\s*(\d+)/i', $prompt, $m)) {
        $bizId = (int) $m[1];

        if (in_array($bizId, COLLECTOR_BIZ_NAO_ATIVAR, true)) {
            // biz=1 (Wagner) / biz=4 (ROTA LIVRE) / biz=99 (sandbox)
            // Pode haver trigger 4 (marco) mesmo assim → checa abaixo.
            $temMarco = preg_match('/\b(endossou|aprovou|reclamou|pausou|retomou|churned|cancelou contrato|assinou contrato|cutover|canary)\b/i', $prompt);
            if ($temMarco && $bizId === 4) {
                return ['ativa' => true, 'motivo' => "marco datável em biz=$bizId (ROTA LIVRE — atualiza histórico)", 'alertaLgpd' => $alertaLgpd];
            }

            return ['ativa' => false, 'motivo' => "business_id=$bizId é caso especial (Wagner/ROTA LIVRE/sandbox)", 'alertaLgpd' => $alertaLgpd];
        }

        // business_id novo → ativa.
        return ['ativa' => true, 'motivo' => "business_id=$bizId não-conhecido — criar stub cliente", 'alertaLgpd' => $alertaLgpd];
    }

    // Trigger 2 — Nome próprio + role operacional + cliente.
    // Regex: Nome capitalizado + verbo de papel + cliente.
    $rolePattern = '/\b([A-ZÁÊÇÕÃÉÍÓÚ][a-záêçõãéíóú]+)\s+(cuida\s+(?:de|do|da)|opera|vende|compra|trabalha\s+em|responsável\s+por|é\s+(?:a\s+|o\s+)?(?:dona|dono|gerente|operador|operadora|financeiro|vendedor|champion))\b/u';
    if (preg_match($rolePattern, $prompt, $m)) {
        $nome = $m[1];

        // Falso positivo: Wagner / time interno.
        $timeInterno = ['Wagner', 'Felipe', 'Maiara', 'Luiz', 'Eliana'];
        if (in_array($nome, $timeInterno, true)) {
            return ['ativa' => false, 'motivo' => "$nome é time interno (não funcionário cliente)", 'alertaLgpd' => $alertaLgpd];
        }

        return ['ativa' => true, 'motivo' => "funcionário '$nome' com role operacional detectado — criar/atualizar perfil", 'alertaLgpd' => $alertaLgpd];
    }

    // Trigger 6 — Palavra-chave Wagner.
    if (preg_match('/\b(salve no perfil|anota no cliente|isso é ouro|registra no perfil|perfil do)\b/iu', $prompt)) {
        return ['ativa' => true, 'motivo' => 'palavra-chave Wagner detectada — force update perfil', 'alertaLgpd' => $alertaLgpd];
    }

    return ['ativa' => false, 'motivo' => 'sem trigger aplicável', 'alertaLgpd' => $alertaLgpd];
}

it('A — biz=164 conhecido + perfil maduro existe → atualiza histórico, não cria stub novo', function () {
    $r = clienteFuncionarioCollectorDecide(
        'Wagner quer importar dados pro business_id=164 do Martinho Caçambas, canary começa 19/maio',
        ['martinho-cacambas' => true]
    );

    expect($r['ativa'])->toBeTrue('biz=164 + marco "canary" deve ativar pra atualizar histórico');
    expect($r['motivo'])->toContain('164');
    expect($r['alertaLgpd'])->toBeFalse();
});

it('B — biz=999 desconhecido → ativa pra criar stub cliente', function () {
    $r = clienteFuncionarioCollectorDecide(
        'Vamos testar a integração com business_id=999 da empresa nova',
        []
    );

    expect($r['ativa'])->toBeTrue();
    expect($r['motivo'])->toContain('999');
    expect($r['motivo'])->toContain('stub');
});

it('C — "Lara cuida do estoque do Martinho" → ativa criar/atualizar funcionário', function () {
    $r = clienteFuncionarioCollectorDecide(
        'A Lara cuida do estoque do Martinho Caçambas e ela vai ser champion oimpresso',
        ['martinho-cacambas' => true]
    );

    expect($r['ativa'])->toBeTrue('funcionário Lara + role estoque deve disparar');
    expect($r['motivo'])->toContain('Lara');
    expect($r['motivo'])->toContain('funcionário');
});

it('D — PII real CPF formato XXX.XXX.XXX-XX → ativa + alerta LGPD bloqueia git', function () {
    $r = clienteFuncionarioCollectorDecide(
        'CPF 123.456.789-00 — Lara trabalha em Martinho Caçambas como champion estoque',
        ['martinho-cacambas' => true]
    );

    expect($r['ativa'])->toBeTrue('Lara + trabalha em deve disparar trigger 2');
    expect($r['alertaLgpd'])->toBeTrue('CPF formato pontuado deve disparar PII guard');
});

it('E — Wagner próprio (caso especial dono) → NÃO ativa', function () {
    $r = clienteFuncionarioCollectorDecide(
        'Wagner cuida do oimpresso e ele é o dono majoritário',
        []
    );

    expect($r['ativa'])->toBeFalse('Wagner é time interno/dono, não funcionário cliente');
    expect($r['motivo'])->toContain('time interno');
});

it('F — biz=1 explícito (Wagner) → NÃO ativa', function () {
    $r = clienteFuncionarioCollectorDecide(
        'Rodando smoke test em business_id=1 (sandbox Wagner próprio biz)',
        []
    );

    expect($r['ativa'])->toBeFalse('biz=1 é caso especial dono');
    expect($r['motivo'])->toContain('1');
});

it('G — biz=4 ROTA LIVRE mencionado sem novidade → NÃO ativa', function () {
    $r = clienteFuncionarioCollectorDecide(
        'Verifica que o business_id=4 da ROTA LIVRE está com sidebar OK',
        ['rotalivre' => true]
    );

    expect($r['ativa'])->toBeFalse('ROTA LIVRE biz=4 só atualiza em trigger 4/5 (marco/status)');
});

it('H — biz=4 ROTA LIVRE + marco "Larissa reclamou" → ativa pra atualizar histórico', function () {
    $r = clienteFuncionarioCollectorDecide(
        'Larissa reclamou da lentidão da listagem; precisamos investigar (business_id=4)',
        ['rotalivre' => true]
    );

    expect($r['ativa'])->toBeTrue('marco "reclamou" em biz=4 deve atualizar histórico ROTA LIVRE');
    expect($r['motivo'])->toContain('marco');
});

it('I — prompt vazio → NÃO ativa', function () {
    $r = clienteFuncionarioCollectorDecide('', []);

    expect($r['ativa'])->toBeFalse();
    expect($r['motivo'])->toBe('prompt vazio');
});

it('J — palavra-chave Wagner "salve no perfil" → ativa force update', function () {
    $r = clienteFuncionarioCollectorDecide(
        'Salve no perfil do Jair que ele endossou em 14/maio',
        ['martinho-cacambas' => true]
    );

    expect($r['ativa'])->toBeTrue('frase explícita "salve no perfil" sempre dispara');
    expect($r['motivo'])->toContain('palavra-chave');
});

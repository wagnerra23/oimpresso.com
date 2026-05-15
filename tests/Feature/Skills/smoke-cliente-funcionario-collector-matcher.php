<?php

/**
 * Smoke runner standalone — replica casos do Pest test ClienteFuncionarioCollectorMatcherTest
 * sem depender do framework. Usado pra validar lógica do matcher quando vendor/ ausente
 * (worktree fresh sem composer install).
 *
 * Uso: php tests/Feature/Skills/smoke-cliente-funcionario-collector-matcher.php
 */

const COLLECTOR_BIZ_NAO_ATIVAR = [1, 4, 99];
const COLLECTOR_REGEX_CPF = '/\b\d{3}\.\d{3}\.\d{3}-\d{2}\b/';

function clienteFuncionarioCollectorDecide(string $prompt, array $perfisExistentes = []): array
{
    $prompt = trim($prompt);

    if ($prompt === '') {
        return ['ativa' => false, 'motivo' => 'prompt vazio', 'alertaLgpd' => false];
    }

    $alertaLgpd = (bool) preg_match(COLLECTOR_REGEX_CPF, $prompt);

    if (preg_match('/(?:business_id|biz)\s*[=:]?\s*(\d+)/i', $prompt, $m)) {
        $bizId = (int) $m[1];

        if (in_array($bizId, COLLECTOR_BIZ_NAO_ATIVAR, true)) {
            $temMarco = preg_match('/\b(endossou|aprovou|reclamou|pausou|retomou|churned|cancelou contrato|assinou contrato|cutover|canary)\b/i', $prompt);
            if ($temMarco && $bizId === 4) {
                return ['ativa' => true, 'motivo' => "marco datável em biz=$bizId (ROTA LIVRE — atualiza histórico)", 'alertaLgpd' => $alertaLgpd];
            }

            return ['ativa' => false, 'motivo' => "business_id=$bizId é caso especial (Wagner/ROTA LIVRE/sandbox)", 'alertaLgpd' => $alertaLgpd];
        }

        return ['ativa' => true, 'motivo' => "business_id=$bizId não-conhecido — criar stub cliente", 'alertaLgpd' => $alertaLgpd];
    }

    $rolePattern = '/\b([A-ZÁÊÇÕÃÉÍÓÚ][a-záêçõãéíóú]+)\s+(cuida\s+(?:de|do|da)|opera|vende|compra|trabalha\s+em|responsável\s+por|é\s+(?:a\s+|o\s+)?(?:dona|dono|gerente|operador|operadora|financeiro|vendedor|champion))\b/u';
    if (preg_match($rolePattern, $prompt, $m)) {
        $nome = $m[1];

        $timeInterno = ['Wagner', 'Felipe', 'Maiara', 'Luiz', 'Eliana'];
        if (in_array($nome, $timeInterno, true)) {
            return ['ativa' => false, 'motivo' => "$nome é time interno (não funcionário cliente)", 'alertaLgpd' => $alertaLgpd];
        }

        return ['ativa' => true, 'motivo' => "funcionário '$nome' com role operacional detectado — criar/atualizar perfil", 'alertaLgpd' => $alertaLgpd];
    }

    if (preg_match('/\b(salve no perfil|anota no cliente|isso é ouro|registra no perfil|perfil do)\b/iu', $prompt)) {
        return ['ativa' => true, 'motivo' => 'palavra-chave Wagner detectada — force update perfil', 'alertaLgpd' => $alertaLgpd];
    }

    return ['ativa' => false, 'motivo' => 'sem trigger aplicável', 'alertaLgpd' => $alertaLgpd];
}

$casos = [
    ['A — biz=164 + canary', 'Wagner quer importar dados pro business_id=164 do Martinho Caçambas, canary começa 19/maio', true,  false],
    ['B — biz=999 novo',     'Vamos testar a integração com business_id=999 da empresa nova',                                true,  false],
    ['C — Lara cuida',       'A Lara cuida do estoque do Martinho Caçambas e ela vai ser champion oimpresso',               true,  false],
    ['D — CPF + Lara',       'CPF 123.456.789-00 — Lara trabalha em Martinho Caçambas como champion estoque',              true,  true],
    ['E — Wagner próprio',   'Wagner cuida do oimpresso e ele é o dono majoritário',                                        false, false],
    ['F — biz=1',            'Rodando smoke test em business_id=1 (sandbox Wagner próprio biz)',                            false, false],
    ['G — biz=4 sem marco',  'Verifica que o business_id=4 da ROTA LIVRE está com sidebar OK',                              false, false],
    ['H — biz=4 + reclamou', 'Larissa reclamou da lentidão da listagem; precisamos investigar (business_id=4)',             true,  false],
    ['I — vazio',            '',                                                                                            false, false],
    ['J — salve no perfil',  'Salve no perfil do Jair que ele endossou em 14/maio',                                         true,  false],
];

$pass = 0;
$fail = 0;
foreach ($casos as [$nome, $prompt, $esperaAtiva, $esperaLgpd]) {
    $r = clienteFuncionarioCollectorDecide($prompt);
    $ok = $r['ativa'] === $esperaAtiva && $r['alertaLgpd'] === $esperaLgpd;
    if ($ok) {
        $pass++;
        echo "PASS  $nome\n";
    } else {
        $fail++;
        echo "FAIL  $nome\n";
        echo "      esperado: ativa=" . ($esperaAtiva ? 'true' : 'false') . " lgpd=" . ($esperaLgpd ? 'true' : 'false') . "\n";
        echo "      obtido:   ativa=" . ($r['ativa'] ? 'true' : 'false') . " lgpd=" . ($r['alertaLgpd'] ? 'true' : 'false') . " motivo='{$r['motivo']}'\n";
    }
}

echo "\n--- Total: $pass pass / $fail fail ---\n";
exit($fail === 0 ? 0 : 1);

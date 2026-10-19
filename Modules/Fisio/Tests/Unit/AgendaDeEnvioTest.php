<?php

declare(strict_types=1);
// Cobre, na regra pura, UC-FPN-08 (Fisio/Painel.casos.md).
// @covers-us US-FISIO-009

use Modules\Fisio\Services\Whatsapp\AgendaDeEnvio;

/**
 * Ritmo do WhatsApp do Fisio — regra PURA, sem TestCase nem banco (ADR 0437 §13.1 (c): teto de
 * envio por número/hora e intervalo aleatório; etapa 02 §3.3: janela de envio). O sorteio entra
 * por parâmetro, então os casos são determinísticos.
 *
 * Fuso: America/Sao_Paulo é UTC-3 o ano todo desde 2019.
 */
function agendaRegras(array $mudar = []): array
{
    return array_merge([
        'teto' => 30, 'intervalo_min' => 20, 'intervalo_max' => 90,
        'silencio_inicio' => '21:00', 'silencio_fim' => '07:00', 'com_teto' => true,
    ], $mudar);
}

function agendaUtc(string $iso): DateTimeImmutable
{
    return new DateTimeImmutable($iso, new DateTimeZone('UTC'));
}

it('UC-FPN-08 · fora do silêncio e sem fila, sai agora', function () {
    $agora = agendaUtc('2026-10-14T11:00:00'); // 08:00 em Brasília
    $r = AgendaDeEnvio::proximo($agora, [], agendaRegras(), 'America/Sao_Paulo', fn ($a, $b) => $a);
    expect($r->format(DATE_ATOM))->toBe($agora->format(DATE_ATOM));
});

it('UC-FPN-08 · no silêncio da noite vai para as 07:00 do dia seguinte; de madrugada, para as 07:00 do mesmo dia', function () {
    $noite = AgendaDeEnvio::proximo(agendaUtc('2026-10-15T01:30:00'), [], agendaRegras(), 'America/Sao_Paulo', fn ($a, $b) => $a); // 14/10 22:30 local
    $madrugada = AgendaDeEnvio::proximo(agendaUtc('2026-10-15T08:00:00'), [], agendaRegras(), 'America/Sao_Paulo', fn ($a, $b) => $a); // 15/10 05:00 local
    $tz = new DateTimeZone('America/Sao_Paulo');
    expect($noite->setTimezone($tz)->format('Y-m-d H:i'))->toBe('2026-10-15 07:00')
        ->and($madrugada->setTimezone($tz)->format('Y-m-d H:i'))->toBe('2026-10-15 07:00');
});

it('UC-FPN-08 · exatamente no fim do silêncio já pode sair; 1 minuto antes do início também', function () {
    $tz = new DateTimeZone('America/Sao_Paulo');
    $fim = AgendaDeEnvio::proximo(agendaUtc('2026-10-14T10:00:00'), [], agendaRegras(), 'America/Sao_Paulo', fn ($a, $b) => $a); // 07:00
    $antes = AgendaDeEnvio::proximo(agendaUtc('2026-10-14T23:59:00'), [], agendaRegras(), 'America/Sao_Paulo', fn ($a, $b) => $a); // 20:59
    expect($fim->setTimezone($tz)->format('H:i'))->toBe('07:00')->and($antes->setTimezone($tz)->format('H:i'))->toBe('20:59');
});

it('UC-FPN-08 · intervalo sorteado entre min e max depois do último agendado no número', function () {
    $agora = agendaUtc('2026-10-14T11:00:00');
    $sorteios = [];
    $r = AgendaDeEnvio::proximo($agora, [$agora], agendaRegras(), 'America/Sao_Paulo', function ($a, $b) use (&$sorteios) {
        $sorteios[] = [$a, $b];

        return 47;
    });
    expect($sorteios)->toBe([[20, 90]])
        ->and($r->getTimestamp() - $agora->getTimestamp())->toBe(47);
});

it('UC-FPN-08 · teto por hora: com o teto cheio na janela, o próximo vai para depois que o mais antigo sai da janela', function () {
    $agora = agendaUtc('2026-10-14T11:00:00');
    $agendados = [$agora->modify('-50 minutes'), $agora->modify('-10 minutes')];
    $r = AgendaDeEnvio::proximo($agora, $agendados, agendaRegras(['teto' => 2, 'intervalo_min' => 0, 'intervalo_max' => 0]), 'America/Sao_Paulo', fn ($a, $b) => $a);
    // O de -50 min sai da janela às 11:10:01 UTC; antes disso já há 2 na janela.
    expect($r->getTimestamp())->toBe($agora->modify('+601 seconds')->getTimestamp());

    // Controle: com teto 3, cabe agora.
    $r3 = AgendaDeEnvio::proximo($agora, $agendados, agendaRegras(['teto' => 3, 'intervalo_min' => 0, 'intervalo_max' => 0]), 'America/Sao_Paulo', fn ($a, $b) => $a);
    expect($r3->getTimestamp())->toBe($agora->getTimestamp());
});

it('UC-FPN-08 · o teto empurrando para o silêncio leva para o fim do silêncio', function () {
    $tz = new DateTimeZone('America/Sao_Paulo');
    $agora = agendaUtc('2026-10-14T23:50:00'); // 20:50 local
    $agendados = [$agora->modify('-5 minutes')];
    $r = AgendaDeEnvio::proximo($agora, $agendados, agendaRegras(['teto' => 1, 'intervalo_min' => 0, 'intervalo_max' => 0]), 'America/Sao_Paulo', fn ($a, $b) => $a);
    expect($r->setTimezone($tz)->format('Y-m-d H:i'))->toBe('2026-10-15 07:00');
});

it('UC-FPN-08 · silêncio com início igual ao fim fica desligado', function () {
    $agora = agendaUtc('2026-10-15T03:00:00'); // 00:00 local
    $r = AgendaDeEnvio::proximo($agora, [], agendaRegras(['silencio_inicio' => '00:00', 'silencio_fim' => '00:00']), 'America/Sao_Paulo', fn ($a, $b) => $a);
    expect($r->getTimestamp())->toBe($agora->getTimestamp());
});
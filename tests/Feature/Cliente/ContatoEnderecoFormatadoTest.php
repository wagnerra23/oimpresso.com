<?php

declare(strict_types=1);

use App\Contact;

/**
 * `Contact::contact_address` — endereço formatado em HTML que ~20 views (ficha do contato, razão,
 * venda, compra, pagamento, OS) imprimem com `{!! !!}`. Contrato: o único HTML é o `<br>` que o
 * próprio accessor põe; tudo o que veio do cadastro (nome, razão social, endereço, cidade, CEP)
 * sai escapado. `contact_address_array` continua texto puro (quem imprime escapa).
 *
 * Sem banco: accessor puro.
 */
function ceContato(array $attrs): Contact
{
    $c = new Contact();
    foreach ($attrs as $k => $v) {
        $c->{$k} = $v;
    }

    return $c;
}

it('escapa os campos digitados e mantém só o <br> do formato', function () {
    $c = ceContato([
        'supplier_business_name' => 'Gráfica <b>Azul</b>',
        'name' => '<img src=x onerror=alert(1)>',
        'address_line_1' => 'Rua "A", 10',
        'city' => '<script>x</script>',
        'zip_code' => '88000-000<i>',
    ]);

    $html = $c->contact_address;

    expect($html)->not->toContain('<img')
        ->and($html)->not->toContain('<script')
        ->and($html)->not->toContain('<b>')
        ->and($html)->not->toContain('<i>')
        ->and($html)->toContain('&lt;img src=x onerror=alert(1)&gt;')
        ->and($html)->toContain('Gráfica &lt;b&gt;Azul&lt;/b&gt;')
        ->and(substr_count($html, '<br>'))->toBe(4);
});

it('endereço comum sai igual ao formato de antes (controle)', function () {
    $c = ceContato([
        'name' => 'Maria Silva',
        'address_line_1' => 'Rua das Flores 10',
        'city' => 'Tubarão',
        'state' => 'SC',
        'country' => 'Brasil',
        'zip_code' => '88700-000',
    ]);

    expect($c->contact_address)
        ->toBe('Maria Silva, <br>Rua das Flores 10, <br>Tubarão, SC, Brasil,<br>88700-000');
});

it('contact_address_array continua texto puro', function () {
    $c = ceContato(['address_line_1' => 'Av. A & B', 'city' => 'Laguna']);

    expect($c->contact_address_array)->toBe(['Av. A & B', 'Laguna']);
});

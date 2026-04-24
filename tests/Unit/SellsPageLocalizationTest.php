<?php

namespace Tests\Unit;

use Tests\TestCase;

class SellsPageLocalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('pt');
    }

    public function test_sell_lang_strings_are_in_portuguese_without_typos(): void
    {
        $cases = [
            'restaurant.service_staff'             => 'Responsável pela venda',
            'restaurant.select_service_staff'      => 'Selecionar responsável pela venda',
            'lang_v1.sell_due'                     => 'Saldo devedor',
            'lang_v1.sell_return_due'              => 'Devolução a receber',
            'lang_v1.total_sell_return_due'        => 'Total de devoluções a receber',
            'lang_v1.pay_sell_return_due'          => 'Pagar devolução de venda',
            'lang_v1.deactivate_selected'          => 'Desativar selecionados',
            'lang_v1.products_deactivated_success' => 'Produtos desativados com sucesso',
        ];

        foreach ($cases as $key => $expected) {
            $this->assertSame(
                $expected,
                __($key),
                "Traducao '{$key}' regrediu. Essas strings aparecem direto na tela /sells."
            );
        }
    }

    public function test_datatables_pt_br_locale_exists_and_has_required_keys(): void
    {
        $path = public_path('locale/datatables/pt-BR.json');
        $this->assertFileExists($path, 'DataTables pt-BR locale ausente — /sells vai exibir "Showing X to Y of Z" em ingles.');

        $json = json_decode(file_get_contents($path), true);
        $this->assertIsArray($json);

        foreach (['info', 'lengthMenu', 'search', 'zeroRecords', 'paginate', 'buttons'] as $key) {
            $this->assertArrayHasKey($key, $json, "Chave '{$key}' ausente no locale DataTables pt-BR.");
        }

        $this->assertStringContainsString('Mostrando', $json['info']);
        $this->assertArrayHasKey('colvis', $json['buttons']);
    }

    public function test_sells_blade_hides_secondary_columns_and_uses_pt_br_locale(): void
    {
        $blade = file_get_contents(resource_path('views/sell/index.blade.php'));

        $this->assertStringContainsString(
            'targets: [11, 12, 21, 22, 23], visible: false',
            $blade,
            'columnDefs escondendo colunas secundarias foi removido — /sells vai forcar scroll horizontal em monitor pequeno.'
        );
        $this->assertStringContainsString(
            "asset('locale/datatables/pt-BR.json')",
            $blade,
            'language: { url } removido do sell_table — DataTables voltou a exibir strings em ingles.'
        );
    }
}

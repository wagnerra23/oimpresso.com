<?php

namespace Modules\Ponto\Tests\Feature;

use Illuminate\Support\Facades\App;
use Modules\Ponto\Http\Controllers\DataController;
use Tests\TestCase;

/**
 * Os rótulos do Ponto saem traduzidos em qualquer idioma do app.
 *
 * Achado em 2026-09-29, ao ligar o Ponto na empresa do gate visual (#8180): o
 * Superadmin, com o app em inglês, via a chave crua `pontowr2::ponto.module_label`
 * no chip do pacote e na sidebar. O módulo só tinha o arquivo `pt`, e o
 * `fallback_locale` do app é `en`. O mesmo valia para os rótulos de permissão.
 */
class TraducaoModuleLabelContratoTest extends TestCase
{
    /** As chaves que o DataController do Ponto mostra na tela (menu, pacote, permissões). */
    private const CHAVES = [
        'module_label',
        'permissao_acesso',
        'permissao_colaboradores',
        'permissao_aprovacoes',
        'permissao_relatorios',
        'permissao_configuracoes',
        'permissao_importacoes',
        'permissao_fechar',
    ];

    public static function idiomas(): array
    {
        return [['en'], ['pt'], ['pt-BR']];
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('idiomas')]
    public function module_label_sai_ponto_em_qualquer_idioma(string $idioma): void
    {
        App::setLocale($idioma);

        $this->assertSame('Ponto', __('pontowr2::ponto.module_label'), "idioma {$idioma}");
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('idiomas')]
    public function nenhuma_chave_da_tela_sai_crua(string $idioma): void
    {
        App::setLocale($idioma);

        foreach (self::CHAVES as $chave) {
            $texto = __("pontowr2::ponto.{$chave}");
            $this->assertStringNotContainsString('pontowr2::', $texto, "idioma {$idioma}, chave {$chave}");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function controle_chave_inexistente_continua_crua(): void
    {
        // Prova que o teste acima discrimina: sem tradução, o Laravel devolve a chave.
        App::setLocale('en');

        $this->assertSame('pontowr2::ponto.chave_que_nao_existe', __('pontowr2::ponto.chave_que_nao_existe'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function pacote_do_superadmin_mostra_ponto_em_ingles(): void
    {
        App::setLocale('en');

        $pacote = (new DataController())->superadmin_package();

        $this->assertSame('Ponto', $pacote[0]['label']);
    }
}

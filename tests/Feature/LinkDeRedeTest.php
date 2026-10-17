<?php

namespace UrbanoDev\Votacao\Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use UrbanoDev\Votacao\Excecoes\ManifestacaoRecusada;
use UrbanoDev\Votacao\Suporte\LinkDeRede;
use UrbanoDev\Votacao\Tests\TestCase;

/** Invariante 7. */
class LinkDeRedeTest extends TestCase
{
    public function test_aceita_instagram_e_facebook_em_https(): void
    {
        $this->assertSame('https://www.instagram.com/girlsontherocks', LinkDeRede::validar(' https://www.instagram.com/girlsontherocks '));
        $this->assertSame('https://m.facebook.com/x', LinkDeRede::validar('https://m.facebook.com/x'));
        $this->assertNull(LinkDeRede::validar(''));
        $this->assertNull(LinkDeRede::validar(null));
    }

    public static function recusados(): array
    {
        return [
            'http' => ['http://instagram.com/x'],
            'outro host' => ['https://tiktok.com/@x'],
            'sufixo enganoso' => ['https://instagram.com.golpe.io/x'],
            'host no caminho' => ['https://golpe.io/instagram.com'],
            'usuário na url' => ['https://instagram.com@golpe.io/x'],
            'usuário e senha' => ['https://a:b@instagram.com/x'],
            'porta' => ['https://instagram.com:8443/x'],
            'javascript' => ['javascript:alert(1)//instagram.com'],
            'sem esquema' => ['instagram.com/x'],
            'longo demais' => ['https://instagram.com/'.str_repeat('a', 500)],
        ];
    }

    #[DataProvider('recusados')]
    public function test_recusa_em_vez_de_limpar(string $link): void
    {
        $this->expectException(ManifestacaoRecusada::class);
        LinkDeRede::validar($link);
    }

    public function test_host_mostrado_ao_lado(): void
    {
        $this->assertSame('instagram.com', LinkDeRede::host('https://www.instagram.com/x'));
        $this->assertSame('facebook.com', LinkDeRede::host('https://m.facebook.com/x'));
    }
}

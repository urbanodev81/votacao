<?php

namespace UrbanoDev\Votacao\Suporte;

use UrbanoDev\Votacao\Models\Recorte;

/**
 * Especificação 01, "Placar por recorte": quais grupos vão para "Outros" no secreto.
 *
 * Grupo abaixo do mínimo vai. E "Outros" não pode ficar entre 1 e o mínimo:
 * o total geral menos os grupos visíveis daria ele. Então o menor grupo
 * visível entra junto, até passar. A mesma regra serve à leitura (antes do
 * encerramento) e à fusão física (no encerramento).
 */
final class SupressaoDeRecortes
{
    public const MINIMO_ABSOLUTO = 5;

    public static function minimo(?int $configurado): int
    {
        return max(self::MINIMO_ABSOLUTO, (int) ($configurado ?? 10));
    }

    /**
     * @param  array<array-key, int>  $votantes  valor do grupo => votantes
     * @return list<string> os valores que vão para "Outros" (sem o próprio "Outros")
     */
    public static function fundir(array $votantes, int $minimo): array
    {
        $outros = 0;
        $visiveis = [];
        $fundir = [];

        foreach ($votantes as $valor => $n) {
            // Chave numérica vira int no array do PHP: o valor volta a ser texto aqui.
            $valor = (string) $valor;
            if ($valor === Recorte::OUTROS) {
                $outros += $n;
            } elseif ($n < $minimo) {
                $fundir[] = $valor;
                $outros += $n;
            } else {
                $visiveis[$valor] = $n;
            }
        }

        // Menor primeiro; empate pelo nome, para a mesma entrada dar sempre a mesma saída.
        uksort($visiveis, fn ($a, $b) => [$visiveis[$a], (string) $a] <=> [$visiveis[$b], (string) $b]);

        while ($outros > 0 && $outros < $minimo && $visiveis !== []) {
            $valor = (string) array_key_first($visiveis);
            $outros += $visiveis[$valor];
            $fundir[] = $valor;
            unset($visiveis[$valor]);
        }

        return $fundir;
    }
}

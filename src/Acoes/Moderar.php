<?php

namespace UrbanoDev\Votacao\Acoes;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use UrbanoDev\Votacao\Enums\EstadoDaManifestacao;
use UrbanoDev\Votacao\Models\Manifestacao;
use UrbanoDev\Votacao\Models\Votacao;

/**
 * A fila do painel (especificação 03): aprovar, rejeitar, um ou em lote.
 *
 * A votação chega já resolvida pelo escopo do sistema (invariante 5); um id de
 * manifestação de outra votação derruba o lote inteiro com 404, sem mexer em
 * nada. Editar o texto da pessoa não existe: ou publica como está, ou não.
 * A removida pela pessoa fica de fora — ela pode apagar no meio do show.
 */
class Moderar
{
    /** @param int|list<int> $ids */
    public function aprovar(Votacao $votacao, int|array $ids, int|string|null $moderador): int
    {
        return $this->mudar($votacao, (array) $ids, $moderador, EstadoDaManifestacao::Aprovada, null);
    }

    /** @param int|list<int> $ids */
    public function rejeitar(Votacao $votacao, int|array $ids, int|string|null $moderador, ?string $motivo = null): int
    {
        return $this->mudar($votacao, (array) $ids, $moderador, EstadoDaManifestacao::Rejeitada, $motivo);
    }

    /** @param list<int> $ids */
    private function mudar(Votacao $votacao, array $ids, int|string|null $moderador, EstadoDaManifestacao $estado, ?string $motivo): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $daVotacao = $votacao->manifestacoes()->whereKey($ids)->count();

        if ($daVotacao !== count($ids)) {
            throw (new ModelNotFoundException)->setModel(Manifestacao::class, $ids);
        }

        // Um UPDATE só, condicionado ao estado: a remoção concorrente vence.
        return $votacao->manifestacoes()
            ->whereKey($ids)
            ->where('estado', '!=', EstadoDaManifestacao::Removida)
            ->update([
                'estado' => $estado,
                'moderado_por' => $moderador,
                'moderado_em' => now(),
                'motivo_rejeicao' => $motivo,
            ]);
    }
}

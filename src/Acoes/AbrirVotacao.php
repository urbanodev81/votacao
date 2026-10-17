<?php

namespace UrbanoDev\Votacao\Acoes;

use LogicException;
use UrbanoDev\Votacao\Enums\StatusDaVotacao;
use UrbanoDev\Votacao\Models\Votacao;

class AbrirVotacao
{
    public function __invoke(Votacao $votacao): Votacao
    {
        if (! in_array($votacao->status, [StatusDaVotacao::Rascunho], true)) {
            throw new LogicException('Só votação em rascunho pode ser aberta.');
        }
        if ($votacao->opcoes()->count() < 2) {
            throw new LogicException('Votação precisa de pelo menos duas opções.');
        }
        if ($votacao->escolha_maximo > $votacao->opcoes()->count()) {
            throw new LogicException('O máximo de escolhas passa do número de opções.');
        }

        $votacao->update(['status' => StatusDaVotacao::Aberta]);

        return $votacao;
    }
}

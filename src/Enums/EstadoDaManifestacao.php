<?php

namespace UrbanoDev\Votacao\Enums;

/** Só a aprovada aparece ao público. */
enum EstadoDaManifestacao: string
{
    case Pendente = 'pendente';
    case Aprovada = 'aprovada';
    case Rejeitada = 'rejeitada';
    case Removida = 'removida';
}

<?php

namespace UrbanoDev\Votacao\Enums;

/** Ciclo de vida da votação. */
enum StatusDaVotacao: string
{
    case Rascunho = 'rascunho';
    case Aberta = 'aberta';
    case Encerrada = 'encerrada';
    case Cancelada = 'cancelada';
}

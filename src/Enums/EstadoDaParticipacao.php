<?php

namespace UrbanoDev\Votacao\Enums;

/** Pendente só existe na identificação por e-mail, até o link ser clicado. */
enum EstadoDaParticipacao: string
{
    case Pendente = 'pendente';
    case Confirmada = 'confirmada';
}

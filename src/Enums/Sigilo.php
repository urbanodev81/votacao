<?php

namespace UrbanoDev\Votacao\Enums;

/** Secreto: a escolha só vai para os contadores (invariante 4). */
enum Sigilo: string
{
    case Aberto = 'aberto';
    case Secreto = 'secreto';
}

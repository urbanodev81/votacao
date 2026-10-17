<?php

namespace UrbanoDev\Votacao\Enums;

/** Conjunto de padrões dos eixos. O formal trava os eixos (invariante 9). */
enum Perfil: string
{
    case Enquete = 'enquete';
    case Interna = 'interna';
    case Formal = 'formal';
}

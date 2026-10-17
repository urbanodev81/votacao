<?php

namespace UrbanoDev\Votacao\Enums;

/** Quando o público vê a contagem. Vale no servidor (invariante 8). */
enum VisibilidadeDoResultado: string
{
    case Sempre = 'sempre';
    case AposVotar = 'apos_votar';
    case NoFechamento = 'no_fechamento';
    case SoPainel = 'so_painel';
}

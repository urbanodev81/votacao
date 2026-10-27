<?php

namespace UrbanoDev\Votacao\Enums;

/** Quórum conta participação (quem votou), nunca opção. */
enum TipoDeQuorum: string
{
    case Nenhum = 'nenhum';
    /** `quorum_valor` = % dos aptos; exige elegibilidade. */
    case PercentualAptos = 'percentual_aptos';
    /** `quorum_valor` = número de votantes. */
    case Absoluto = 'absoluto';
}

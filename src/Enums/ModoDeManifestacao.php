<?php

namespace UrbanoDev\Votacao\Enums;

/** O que o público pode escrever ou mostrar. Sempre moderado (invariante 6). */
enum ModoDeManifestacao: string
{
    case Desligada = 'desligada';
    case Comentario = 'comentario';
    case ComentarioEVoto = 'comentario_e_voto';
}

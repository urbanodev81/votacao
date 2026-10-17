<?php

namespace UrbanoDev\Votacao\Enums;

/** Como se sabe que é a mesma pessoa (especificação 02). */
enum Identificacao: string
{
    case Aparelho = 'aparelho';
    case Email = 'email';
    case Conta = 'conta';
    case Documento = 'documento';
}

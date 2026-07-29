<?php

namespace App\Enums;

enum SignalementStatus: string
{
    case Nouveau = 'nouveau';
    case EnCours = 'en_cours';
    case Resolu = 'resolu';
    case Rejete = 'rejete';
}

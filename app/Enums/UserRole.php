<?php

namespace App\Enums;

enum UserRole: string
{
    case Citoyen = 'citoyen';
    case AgentMunicipal = 'agent_municipal';
    case Admin = 'admin';
}

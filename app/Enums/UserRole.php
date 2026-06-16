<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Public = 'public';
    case FinanceManager = 'finance-manager';
    case FinanceAssistant = 'finance-assistant';
    case FinanceAuditor = 'finance-auditor';
}

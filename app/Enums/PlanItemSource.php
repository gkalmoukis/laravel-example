<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanItemSource: string
{
    case Manual = 'manual';
    case SalaryModel = 'salary_model';
    case Subscription = 'subscription';
}

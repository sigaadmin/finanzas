<?php

namespace App\Models\Finance;

use Database\Factories\Finance\ExpenseClassificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'fiscal_year',
    'chapter_code',
    'chapter_name',
    'concept_code',
    'concept_name',
    'generic_item_code',
    'generic_item_name',
    'specific_item_code',
    'specific_item_name',
    'expense_type_code',
    'expense_type_name',
])]
class ExpenseClassification extends Model
{
    /** @use HasFactory<ExpenseClassificationFactory> */
    use HasFactory;
}

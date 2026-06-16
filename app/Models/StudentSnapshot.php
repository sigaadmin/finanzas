<?php

namespace App\Models;

use Database\Factories\StudentSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['siga_student_id', 'matricula', 'name', 'program', 'grade', 'group', 'academic_status'])]
class StudentSnapshot extends Model
{
    /** @use HasFactory<StudentSnapshotFactory> */
    use HasFactory;

    /**
     * @return HasMany<PaymentProcedure, $this>
     */
    public function paymentProcedures(): HasMany
    {
        return $this->hasMany(PaymentProcedure::class);
    }
}

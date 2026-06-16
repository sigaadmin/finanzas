<?php

namespace App\Data\Finance;

readonly class SigaStudentData
{
    public function __construct(
        public string $sigaStudentId,
        public ?string $matricula,
        public string $name,
        public ?string $program,
        public ?string $grade,
        public ?string $group,
        public ?string $academicStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $program = data_get($data, 'programa.nombre', $data['program'] ?? null);

        return new self(
            sigaStudentId: (string) $data['id'],
            matricula: isset($data['matricula']) ? (string) $data['matricula'] : null,
            name: (string) ($data['nombre_completo'] ?? $data['name']),
            program: $program !== null ? (string) $program : null,
            grade: isset($data['semestre']) ? (string) $data['semestre'] : (isset($data['grade']) ? (string) $data['grade'] : null),
            group: isset($data['grupo']) ? (string) $data['grupo'] : (isset($data['group']) ? (string) $data['group'] : null),
            academicStatus: isset($data['estatus']) ? (string) $data['estatus'] : (isset($data['academic_status']) ? (string) $data['academic_status'] : null),
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'siga_student_id' => $this->sigaStudentId,
            'matricula' => $this->matricula,
            'name' => $this->name,
            'program' => $this->program,
            'grade' => $this->grade,
            'group' => $this->group,
            'academic_status' => $this->academicStatus,
        ];
    }
}

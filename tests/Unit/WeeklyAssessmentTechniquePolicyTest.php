<?php

use App\Services\Rps\WeeklyAssessmentTechniquePolicy;

it('converts an analytic rubric output into a performance assessment when the evidence is implementation work', function () {
    $policy = new WeeklyAssessmentTechniquePolicy;

    $result = $policy->resolveTechnique([
        'assessment_method' => 'Rubrik analitik',
        'assessment_indicator' => 'Mengimplementasikan struktur percabangan, menjalankan program, dan memeriksa hasil eksekusi.',
        'assessment_criteria' => 'Ketepatan logika dan proses implementasi.',
        'student_assignment' => 'Praktik coding terstruktur.',
    ]);

    expect($result)->toBe('Penilaian kinerja');
});

it('chooses presentation assessment when the evidence is a presentation even if the provider returns a rubric', function () {
    $policy = new WeeklyAssessmentTechniquePolicy;

    $result = $policy->resolveTechnique([
        'assessment_method' => 'Rubrik holistik',
        'assessment_indicator' => 'Mempresentasikan hasil analisis dan mempertahankan argumentasi.',
        'assessment_criteria' => 'Ketepatan isi, argumentasi, dan komunikasi.',
    ]);

    expect($result)->toBe('Penilaian presentasi');
});

it('keeps a valid written test technique', function () {
    $policy = new WeeklyAssessmentTechniquePolicy;

    $result = $policy->resolveTechnique([
        'assessment_method' => 'Tes tertulis',
        'assessment_indicator' => 'Menyelesaikan soal perhitungan dengan prosedur yang tepat.',
    ]);

    expect($result)->toBe('Tes tertulis');
});

it('uses assessment context when an instrument-only output does not explain the evidence type', function () {
    $policy = new WeeklyAssessmentTechniquePolicy;

    $result = $policy->resolveTechnique(
        [
            'assessment_method' => 'Checklist',
            'assessment_indicator' => 'Menunjukkan bukti ketercapaian sesuai target pekan.',
        ],
        [
            'target_assessments' => [
                ['type' => 'project', 'name' => 'Proyek Integratif'],
            ],
        ],
    );

    expect($result)->toBe('Penilaian proyek');
});

it('appends an explicit rule that rubrics are instruments rather than techniques', function () {
    $policy = new WeeklyAssessmentTechniquePolicy;
    $instruction = $policy->appendInstruction('Susun pekan secara ringkas.');

    expect($instruction)
        ->toContain('Rubrik analitik')
        ->toContain('BUKAN instrumen')
        ->toContain('ABAIKAN contoh tersebut')
        ->toContain('JENIS BUKTI');
});

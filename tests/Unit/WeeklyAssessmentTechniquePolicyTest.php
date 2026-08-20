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

it('overrides written test when SQL efficiency is demonstrated through computer execution', function () {
    $policy = new WeeklyAssessmentTechniquePolicy;

    $result = $policy->resolveTechnique([
        'assessment_method' => 'Tes tertulis',
        'assessment_indicator' => 'Membuktikan efisiensi penggunaan database SQL dalam aplikasi komputasi sederhana, dengan menilai kompleksitas kueri dan waktu respon.',
        'assessment_criteria' => 'Ketepatan implementasi kueri, pengukuran waktu respon, dan interpretasi hasil.',
        'learning_activity' => 'Menjalankan kueri SQL pada komputer dan membandingkan waktu respons.',
    ]);

    expect($result)->toBe('Penilaian kinerja');
});

it('treats GIS software execution as performance evidence rather than a written test', function () {
    $policy = new WeeklyAssessmentTechniquePolicy;

    $result = $policy->resolveTechnique([
        'assessment_method' => 'Tes tertulis',
        'assessment_indicator' => 'Menganalisis hasil overlay spasial dengan menjalankan proses geoprocessing menggunakan ArcGIS.',
        'assessment_criteria' => 'Ketepatan tahapan, parameter, hasil overlay, dan interpretasi.',
    ]);

    expect($result)->toBe('Penilaian kinerja');
});

it('keeps written proof as a written test when the evidence is explicitly written', function () {
    $policy = new WeeklyAssessmentTechniquePolicy;

    $result = $policy->resolveTechnique([
        'assessment_method' => 'Rubrik analitik',
        'assessment_indicator' => 'Menyusun pembuktian tertulis pada lembar jawaban untuk menunjukkan validitas suatu teorema.',
        'assessment_criteria' => 'Ketepatan argumen dan kelengkapan langkah pembuktian tertulis.',
    ]);

    expect($result)->toBe('Tes tertulis');
});

it('appends an explicit rule that rubrics are instruments rather than techniques', function () {
    $policy = new WeeklyAssessmentTechniquePolicy;
    $instruction = $policy->appendInstruction('Susun pekan secara ringkas.');

    expect($instruction)
        ->toContain('Rubrik analitik')
        ->toContain('BUKAN instrumen')
        ->toContain('ABAIKAN contoh tersebut')
        ->toContain('JENIS BUKTI')
        ->toContain('SQL/database/query')
        ->toContain('Penilaian kinerja');
});

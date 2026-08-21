<?php

use App\Services\Rps\ComputerAssistedTechniqueGuard;

it('treats BFS and DFS implemented in a computing application as performance evidence', function () {
    $guard = new ComputerAssistedTechniqueGuard;

    $technique = $guard->resolveTechnique([
        'assessment_method' => 'Tes tertulis',
        'assessment_indicator' => 'Menganalisis kompleksitas waktu dan ruang dari algoritma pencarian (BFS, DFS) yang diimplementasikan dalam aplikasi komputasi sederhana.',
        'assessment_criteria' => 'Ketepatan implementasi, analisis kompleksitas, dan interpretasi hasil eksekusi.',
    ]);

    expect($technique)->toBe('Penilaian kinerja');
});

it('keeps a written test when the evidence is explicitly a written answer', function () {
    $guard = new ComputerAssistedTechniqueGuard;

    $technique = $guard->resolveTechnique([
        'assessment_method' => 'Tes tertulis',
        'assessment_indicator' => 'Menganalisis kompleksitas BFS dan DFS melalui soal tertulis pada lembar jawaban.',
    ]);

    expect($technique)->toBe('Tes tertulis');
});

it('overrides a product classification when implementation process is the main computer evidence', function () {
    $guard = new ComputerAssistedTechniqueGuard;

    $technique = $guard->resolveTechnique([
        'assessment_method' => 'Penilaian produk',
        'assessment_indicator' => 'Menguji algoritma yang diimplementasikan pada program dan membandingkan runtime hasil eksekusi.',
    ]);

    expect($technique)->toBe('Penilaian kinerja');
});

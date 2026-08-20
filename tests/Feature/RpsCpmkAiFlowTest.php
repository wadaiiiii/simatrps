<?php

use App\Models\User;
use App\Services\Rps\AiRpsProviderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;

function createCpmkAiFixture(User $lecturer): array
{
    $studyProgramId = (string) Str::uuid();
    $curriculumId = (string) Str::uuid();
    $courseId = (string) Str::uuid();
    $cplId = (string) Str::uuid();
    $rpsId = (string) Str::uuid();
    $versionId = (string) Str::uuid();
    $cpmkId = (string) Str::uuid();
    $timestamp = now()->subMinute();

    DB::table('study_programs')->insert([
        'id' => $studyProgramId,
        'code' => 'MAT-CPMK-'.Str::lower(Str::random(6)),
        'name' => 'Matematika',
        'faculty_name' => 'FMIPA',
        'university_name' => 'Universitas Sulawesi Barat',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('curriculums')->insert([
        'id' => $curriculumId,
        'study_program_id' => $studyProgramId,
        'code' => 'KUR-CPMK-'.Str::lower(Str::random(6)),
        'name' => 'Kurikulum CPMK AI',
        'year' => 2026,
        'status' => 'active',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('cpls')->insert([
        'id' => $cplId,
        'curriculum_id' => $curriculumId,
        'code' => 'CPL-02',
        'description' => 'Mampu menerapkan konsep matematika dan komputasi untuk menyelesaikan permasalahan secara logis dan sistematis.',
        'domain' => 'pengetahuan',
        'sequence_no' => 2,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('courses')->insert([
        'id' => $courseId,
        'curriculum_id' => $curriculumId,
        'system_code' => 'SYS-CPMK-'.Str::lower(Str::random(6)),
        'official_code' => 'MAT041325',
        'name' => 'Algoritma dan Dasar Pemrograman',
        'credits' => 3,
        'semester_recommended' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('course_cpls')->insert([
        'id' => (string) Str::uuid(),
        'course_id' => $courseId,
        'cpl_id' => $cplId,
        'contribution_level' => 'primary',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps')->insert([
        'id' => $rpsId,
        'curriculum_id' => $curriculumId,
        'course_id' => $courseId,
        'owner_id' => $lecturer->id,
        'academic_year' => '2026/2027',
        'academic_semester' => 'Ganjil',
        'status' => 'draft',
        'current_version_id' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps_versions')->insert([
        'id' => $versionId,
        'rps_id' => $rpsId,
        'version_no' => 1,
        'status' => 'draft',
        'created_by' => $lecturer->id,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps')->where('id', $rpsId)->update([
        'current_version_id' => $versionId,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps_cpmks')->insert([
        'id' => $cpmkId,
        'rps_version_id' => $versionId,
        'code' => 'CPMK-01',
        'description' => 'Mampu menerapkan algoritma dasar untuk menyelesaikan masalah komputasi sederhana.',
        'bloom_level' => 'C3',
        'source_type' => 'curriculum',
        'source_cpmk_id' => null,
        'sequence_no' => 1,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('rps_cpmk_cpls')->insert([
        'id' => (string) Str::uuid(),
        'rps_cpmk_id' => $cpmkId,
        'cpl_id' => $cplId,
        'source_type' => 'curriculum',
        'created_by' => $lecturer->id,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $meta = [
        'id' => (string) Str::uuid(),
        'rps_version_id' => $versionId,
        'course_cluster' => 'Matematika dan Komputasi',
        'prepared_date' => '2026-08-20',
        'developer_name' => $lecturer->name,
        'coordinator_name' => 'Koordinator Mata Kuliah',
        'head_program_name' => 'Ketua Program Studi',
        'lecturer_names' => $lecturer->name,
        'software_media' => 'Python',
        'hardware_media' => 'Komputer',
        'prerequisite_text' => 'Tidak ada',
        'description_short' => 'Dasar algoritma dan pemrograman.',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ];

    if (Schema::hasColumn('rps_document_meta', 'published_date')) {
        $meta['published_date'] = '2026-08-20';
    }

    DB::table('rps_document_meta')->insert($meta);

    return compact('courseId', 'cplId', 'rpsId', 'versionId', 'cpmkId');
}

test('CPMK AI keep-only review is visible after one click and keeps master CPL evidence', function () {
    $lecturer = User::factory()->create(['role' => 'dosen', 'is_active' => true]);
    $fixture = createCpmkAiFixture($lecturer);

    $this->mock(AiRpsProviderService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('generate')
            ->once()
            ->withArgs(function (string $type, array $context, ?string $instruction): bool {
                $curriculumCpl = collect($context['cpl_scope'] ?? [])->firstWhere('source', 'curriculum');

                return $type === 'cpmk_review'
                    && ($curriculumCpl['code'] ?? null) === 'CPL-02'
                    && str_contains((string) $instruction, 'cpmk-master-cpl-v1')
                    && str_contains((string) ($curriculumCpl['description'] ?? ''), 'komputasi');
            })
            ->andReturn([
                'payload' => [
                    'summary' => 'Rumusan CPMK sudah sesuai.',
                    'recommendations' => [[
                        'action' => 'keep',
                        'target_code' => 'CPMK-01',
                        'description' => 'Mampu menerapkan algoritma dasar untuk menyelesaikan masalah komputasi sederhana.',
                        'bloom_level' => 'C3',
                        'cpl_codes' => ['CPL-02'],
                        'rationale' => 'Kemampuan menerapkan algoritma mendukung kemampuan komputasi pada CPL resmi.',
                    ]],
                ],
                'provider' => 'test',
                'model' => 'test-model',
                'response_id' => 'test-response',
                'usage' => null,
                'fallback_used' => false,
                'primary_error' => null,
            ]);
    });

    $this->actingAs($lecturer)
        ->post(route('rps.ai.generate', $fixture['rpsId']), [
            'suggestion_type' => 'cpmk_review',
            'instruction' => '',
        ])
        ->assertSessionHasNoErrors();

    $suggestion = DB::table('ai_suggestions')
        ->where('rps_version_id', $fixture['versionId'])
        ->where('suggestion_type', 'cpmk_review')
        ->latest('created_at')
        ->first();

    expect($suggestion)->not->toBeNull()
        ->and($suggestion->status)->toBe('pending');

    $payload = json_decode((string) $suggestion->suggestion_payload, true);
    $context = json_decode((string) $suggestion->input_context, true);

    expect($payload['_review_basis']['policy_version'])->toBe('cpmk-master-cpl-v1')
        ->and($payload['_review_basis']['curriculum_cpl_codes'])->toBe(['CPL-02'])
        ->and($payload['recommendations'])->toHaveCount(1)
        ->and($payload['recommendations'][0]['action'])->toBe('keep')
        ->and($payload['recommendations'][0]['cpl_codes'])->toBe(['CPL-02'])
        ->and($payload['summary'])->toContain('1 dipertahankan')
        ->and($context['policy_version'])->toBe('cpmk-master-cpl-v1');
});

test('manual CPMK wording change clears stale Bloom and rejects downstream AI suggestions', function () {
    $lecturer = User::factory()->create(['role' => 'dosen', 'is_active' => true]);
    $fixture = createCpmkAiFixture($lecturer);

    foreach (['bloom_mapping', 'cpl_mapping'] as $type) {
        DB::table('ai_suggestions')->insert([
            'id' => (string) Str::uuid(),
            'rps_version_id' => $fixture['versionId'],
            'suggestion_type' => $type,
            'status' => 'pending',
            'input_context' => json_encode([]),
            'suggestion_payload' => json_encode(['recommendations' => []]),
            'requested_by' => $lecturer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->actingAs($lecturer)
        ->put(route('rps.cpmk.update', [
            'rps' => $fixture['rpsId'],
            'cpmk' => $fixture['cpmkId'],
        ]), [
            'description' => 'Mampu menganalisis dan menerapkan algoritma dasar untuk menyelesaikan masalah komputasi secara sistematis.',
            'bloom_level' => 'C3',
        ])
        ->assertSessionHasNoErrors();

    $cpmk = DB::table('rps_cpmks')->where('id', $fixture['cpmkId'])->first();

    expect($cpmk->bloom_level)->toBeNull()
        ->and(DB::table('ai_suggestions')
            ->where('rps_version_id', $fixture['versionId'])
            ->whereIn('suggestion_type', ['bloom_mapping', 'cpl_mapping'])
            ->where('status', 'pending')
            ->count())->toBe(0)
        ->and(DB::table('ai_suggestions')
            ->where('rps_version_id', $fixture['versionId'])
            ->whereIn('suggestion_type', ['bloom_mapping', 'cpl_mapping'])
            ->where('status', 'rejected')
            ->count())->toBe(2);
});

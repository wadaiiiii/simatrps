<?php

test('login and RPS preview support the campus subdirectory', function () {
    $login = file_get_contents(resource_path('js/pages/auth/login.tsx'));
    $header = file_get_contents(resource_path('js/components/app-sidebar-header.tsx'));
    $utils = file_get_contents(resource_path('js/lib/utils.ts'));

    expect($login)->toContain('appUrl(loginForm.action)')
        ->and($utils)->toContain('/akademik/simatrps')
        ->and($header)->toContain('const isRpsDetail =');
});

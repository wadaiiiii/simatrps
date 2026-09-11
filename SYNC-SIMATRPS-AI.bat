@echo off
setlocal EnableExtensions EnableDelayedExpansion

title SYNC-SIMATRPS-AI - Validated Promotion Gate
color 0B

rem ============================================================================
rem SYNC-SIMATRPS-AI.bat
rem Repo    : wadaiiiii/simatrps
rem Flow    : origin/main + origin/ai-patch -> local production -> origin/main
rem Safety  : no force push, no hard reset, no automatic conflict resolution
rem Build   : npm ci -> types:check -> build -> manifest/hashed asset validation
rem ============================================================================

set "REMOTE=origin"
set "PATCH_BRANCH=ai-patch"
set "PRODUCTION_BRANCH=production"
set "DEFAULT_REPO=C:\Users\LENOVO\Herd\simatrps"
set "COMMIT_MESSAGE=%~2"
if not defined COMMIT_MESSAGE set "COMMIT_MESSAGE=deploy: promote validated SiMatRPS ai-patch"

if not "%~1"=="" (
    set "REPO_DIR=%~1"
) else if exist "%~dp0.git" (
    set "REPO_DIR=%~dp0"
) else (
    set "REPO_DIR=%DEFAULT_REPO%"
)

echo.
echo ============================================================
echo   SYNC-SIMATRPS-AI
echo   Repo       : wadaiiiii/simatrps
echo   Patch      : origin/%PATCH_BRANCH%
echo   Promotion  : local %PRODUCTION_BRANCH% -^> origin/main
echo ============================================================
echo.

where git >nul 2>&1
if errorlevel 1 goto :no_git
where npm >nul 2>&1
if errorlevel 1 goto :no_npm
where powershell >nul 2>&1
if errorlevel 1 goto :no_powershell

if not exist "%REPO_DIR%" goto :no_repo
cd /d "%REPO_DIR%"

git rev-parse --is-inside-work-tree >nul 2>&1
if errorlevel 1 goto :not_repo

git remote get-url "%REMOTE%" >nul 2>&1
if errorlevel 1 goto :no_remote

set "DIRTY="
for /f "delims=" %%S in ('git status --porcelain 2^>nul') do set "DIRTY=1"
if defined DIRTY goto :dirty_tree

echo [FETCH] Mengambil status GitHub terbaru...
git fetch "%REMOTE%" --prune
if errorlevel 1 goto :fetch_failed

git show-ref --verify --quiet "refs/remotes/%REMOTE%/main"
if errorlevel 1 goto :no_remote_main
git show-ref --verify --quiet "refs/remotes/%REMOTE%/%PATCH_BRANCH%"
if errorlevel 1 goto :no_remote_patch
git show-ref --verify --quiet "refs/remotes/%REMOTE%/%PRODUCTION_BRANCH%"
if errorlevel 1 goto :no_remote_production

for /f "delims=" %%H in ('git rev-parse "%REMOTE%/main"') do set "MAIN_BEFORE=%%H"
for /f "delims=" %%H in ('git rev-parse "%REMOTE%/%PATCH_BRANCH%"') do set "PATCH_SHA=%%H"

echo [BASE] Memastikan ai-patch berbasis main terbaru...
git merge-base --is-ancestor "%REMOTE%/main" "%REMOTE%/%PATCH_BRANCH%"
if errorlevel 1 goto :patch_behind_main

git diff --quiet "%REMOTE%/main...%REMOTE%/%PATCH_BRANCH%"
if not errorlevel 1 goto :nothing_to_promote

echo.
echo [AUDIT] File yang berubah pada ai-patch:
git diff --name-status "%REMOTE%/main...%REMOTE%/%PATCH_BRANCH%"
if errorlevel 1 goto :audit_failed

set "PROTECTED_FOUND="
for /f "delims=" %%F in ('git diff --name-only "%REMOTE%/main...%REMOTE%/%PATCH_BRANCH%"') do call :check_protected "%%F"
if defined PROTECTED_FOUND goto :protected_files

git diff --check "%REMOTE%/main...%REMOTE%/%PATCH_BRANCH%"
if errorlevel 1 goto :diff_failed

if /I not "%SYNC_NONINTERACTIVE%"=="1" (
    echo.
    choice /C YN /N /M "Lanjutkan validasi dan promosi file di atas? [Y/N] "
    if errorlevel 2 goto :cancelled
)

echo [PATCH] Menyiapkan branch lokal ai-patch...
git show-ref --verify --quiet "refs/heads/%PATCH_BRANCH%"
if errorlevel 1 (
    git switch --track -c "%PATCH_BRANCH%" "%REMOTE%/%PATCH_BRANCH%" >nul 2>&1
    if errorlevel 1 git checkout -b "%PATCH_BRANCH%" --track "%REMOTE%/%PATCH_BRANCH%"
) else (
    git switch "%PATCH_BRANCH%" >nul 2>&1
    if errorlevel 1 git checkout "%PATCH_BRANCH%"
)
if errorlevel 1 goto :switch_patch_failed

git pull --ff-only "%REMOTE%" "%PATCH_BRANCH%"
if errorlevel 1 goto :pull_patch_failed

echo [DEPS] Menjalankan npm ci untuk dependency yang reproducible...
call npm ci
if errorlevel 1 goto :npm_ci_failed

echo [TYPE] Menjalankan npm run types:check...
call npm run types:check
if errorlevel 1 goto :types_failed

echo [BUILD] Menjalankan build validasi...
call npm run build
if errorlevel 1 goto :build_failed

echo [ASSET] Memvalidasi manifest dan hashed assets...
powershell -NoProfile -ExecutionPolicy Bypass -File "scripts\validate-simatrps-build.ps1"
if errorlevel 1 goto :asset_failed

git diff --quiet
if errorlevel 1 (
    echo [BLOCKED] Proses validasi mengubah file source/lock yang terlacak.
    goto :fail
)
git diff --cached --quiet
if errorlevel 1 (
    echo [BLOCKED] Proses validasi mengubah staging area.
    goto :fail
)

echo [PASS] Local validation gate lulus.
echo [PRODUCTION] Menyiapkan branch lokal production...

git show-ref --verify --quiet "refs/heads/%PRODUCTION_BRANCH%"
if errorlevel 1 (
    git switch --track -c "%PRODUCTION_BRANCH%" "%REMOTE%/%PRODUCTION_BRANCH%" >nul 2>&1
    if errorlevel 1 git checkout -b "%PRODUCTION_BRANCH%" --track "%REMOTE%/%PRODUCTION_BRANCH%"
) else (
    git switch "%PRODUCTION_BRANCH%" >nul 2>&1
    if errorlevel 1 git checkout "%PRODUCTION_BRANCH%"
)
if errorlevel 1 goto :switch_production_failed

git pull --ff-only "%REMOTE%" "%PRODUCTION_BRANCH%"
if errorlevel 1 goto :pull_production_failed

git merge --ff-only "%REMOTE%/main"
if errorlevel 1 goto :production_not_based_on_main

echo [MERGE] Menggabungkan patch tervalidasi tanpa commit otomatis...
git merge --no-ff --no-commit "%REMOTE%/%PATCH_BRANCH%"
if errorlevel 1 (
    git merge --abort >nul 2>&1
    goto :merge_failed
)

echo [AUDIT] Isi merge yang akan dipromosikan:
git diff --cached --name-status
if errorlevel 1 goto :merge_abort_audit

set "PROTECTED_FOUND="
for /f "delims=" %%F in ('git diff --cached --name-only') do call :check_protected "%%F"
if defined PROTECTED_FOUND goto :merge_abort_protected

git diff --cached --check
if errorlevel 1 goto :merge_abort_diff

echo [FINAL] Menjalankan type check dan build final pada production...
call npm run types:check
if errorlevel 1 goto :merge_abort_types

call npm run build
if errorlevel 1 goto :merge_abort_build

powershell -NoProfile -ExecutionPolicy Bypass -File "scripts\validate-simatrps-build.ps1"
if errorlevel 1 goto :merge_abort_asset

git diff --quiet
if errorlevel 1 (
    git merge --abort >nul 2>&1
    echo [BLOCKED] Build final mengubah file source/lock di luar merge.
    goto :fail
)

git diff --cached --quiet
if not errorlevel 1 goto :merge_abort_empty

echo [COMMIT] %COMMIT_MESSAGE%
git commit -m "%COMMIT_MESSAGE%"
if errorlevel 1 goto :commit_failed

echo [RACE] Memastikan origin/main belum berubah selama validasi...
git fetch "%REMOTE%" main
if errorlevel 1 goto :fetch_before_push_failed
for /f "delims=" %%H in ('git rev-parse "%REMOTE%/main"') do set "MAIN_AFTER_FETCH=%%H"
if not "!MAIN_BEFORE!"=="!MAIN_AFTER_FETCH!" goto :main_changed

echo [PUSH] Memperbarui main dan production secara atomik...
git push --atomic "%REMOTE%" "%PRODUCTION_BRANCH%:main" "%PRODUCTION_BRANCH%:%PRODUCTION_BRANCH%"
if errorlevel 1 goto :push_failed

for /f "delims=" %%H in ('git rev-parse HEAD') do set "PROMOTED_SHA=%%H"

echo.
echo ============================================================
echo   PROMOSI SIMATRPS SELESAI
echo   AI-PATCH  : !PATCH_SHA!
echo   MAIN      : !PROMOTED_SHA!
echo   PRODUCTION: !PROMOTED_SHA!
echo ============================================================
echo.
echo Langkah cPanel yang aman:
echo   git fetch origin
echo   git pull --ff-only origin main
echo   npm ci
echo   npm run build
echo   php artisan optimize:clear
echo.
goto :success

:check_protected
set "CHECK_FILE=%~1"
if /I "!CHECK_FILE!"==".env" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE:~0,5!"==".env." if /I not "!CHECK_FILE!"==".env.example" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE:~0,8!"=="storage/" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE:~0,7!"=="vendor/" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE:~0,13!"=="node_modules/" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE:~0,16!"=="bootstrap/cache/" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE:~0,8!"==".vercel/" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE!"=="auth.json" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE!"=="database/database.sqlite" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE!"=="public/hot" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE!"=="public/index.php" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE!"=="public/.htaccess" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE!"=="bootstrap/app.php" set "PROTECTED_FOUND=!CHECK_FILE!"
if /I "!CHECK_FILE!"=="vercel.json" set "PROTECTED_FOUND=!CHECK_FILE!"
exit /b 0

:nothing_to_promote
echo [INFO] origin/ai-patch tidak memiliki perubahan di atas origin/main.
goto :success

:no_git
echo [ERROR] Git tidak ditemukan pada PATH Windows.
goto :fail

:no_npm
echo [ERROR] npm tidak ditemukan pada PATH Windows.
goto :fail

:no_powershell
echo [ERROR] Windows PowerShell tidak ditemukan.
goto :fail

:no_repo
echo [ERROR] Folder repository tidak ditemukan:
echo         %REPO_DIR%
goto :fail

:not_repo
echo [ERROR] Folder tersebut bukan repository Git:
echo         %REPO_DIR%
goto :fail

:no_remote
echo [ERROR] Remote "%REMOTE%" tidak tersedia.
goto :fail

:dirty_tree
echo [BLOCKED] Working tree harus bersih sebelum sinkronisasi.
echo           Commit atau simpan perubahan lokal terlebih dahulu.
goto :fail

:fetch_failed
echo [ERROR] git fetch gagal. Periksa autentikasi GitHub.
goto :fail

:no_remote_main
echo [ERROR] origin/main tidak ditemukan.
goto :fail

:no_remote_patch
echo [ERROR] origin/ai-patch tidak ditemukan.
goto :fail

:no_remote_production
echo [ERROR] origin/production tidak ditemukan.
goto :fail

:patch_behind_main
echo [BLOCKED] ai-patch belum berbasis origin/main terbaru.
echo           Sinkronkan main ke ai-patch melalui proses patch sebelum promosi.
goto :fail

:audit_failed
echo [ERROR] Audit changed files gagal.
goto :fail

:protected_files
echo [BLOCKED] ai-patch menyentuh file terlindungi: !PROTECTED_FOUND!
echo           Patch harus diperbaiki dan diaudit manual.
goto :fail

:diff_failed
echo [ERROR] Whitespace/error diff ditemukan pada ai-patch.
goto :fail

:cancelled
echo [INFO] Promosi dibatalkan pengguna.
goto :fail

:switch_patch_failed
echo [ERROR] Tidak dapat berpindah ke ai-patch.
goto :fail

:pull_patch_failed
echo [ERROR] ai-patch lokal tidak dapat di-fast-forward.
goto :fail

:npm_ci_failed
echo [ERROR] npm ci gagal. Promosi dihentikan.
goto :fail

:types_failed
echo [ERROR] TypeScript check gagal. Promosi dihentikan.
goto :fail

:build_failed
echo [ERROR] Build validasi gagal. Promosi dihentikan.
goto :fail

:asset_failed
echo [ERROR] Manifest atau hashed assets tidak valid.
goto :fail

:validation_changed_source
echo [BLOCKED] Proses validasi mengubah file source/lock yang terlacak.
goto :fail

:switch_production_failed
echo [ERROR] Tidak dapat berpindah ke production.
goto :fail

:pull_production_failed
echo [ERROR] production lokal tidak dapat di-fast-forward dari origin/production.
goto :fail

:production_not_based_on_main
echo [BLOCKED] production tidak dapat di-fast-forward ke origin/main.
goto :fail

:merge_failed
echo [ERROR] Konflik saat menggabungkan ai-patch. Merge sudah dibatalkan.
goto :fail

:merge_abort_audit
git merge --abort >nul 2>&1
echo [ERROR] Audit merge gagal. Merge dibatalkan.
goto :fail

:merge_abort_protected
git merge --abort >nul 2>&1
echo [BLOCKED] Merge menyentuh file terlindungi: !PROTECTED_FOUND!
goto :fail

:merge_abort_diff
git merge --abort >nul 2>&1
echo [ERROR] Whitespace/error diff pada merge. Merge dibatalkan.
goto :fail

:merge_abort_types
git merge --abort >nul 2>&1
echo [ERROR] TypeScript check final gagal. Merge dibatalkan.
goto :fail

:merge_abort_build
git merge --abort >nul 2>&1
echo [ERROR] Build final gagal. Merge dibatalkan.
goto :fail

:merge_abort_asset
git merge --abort >nul 2>&1
echo [ERROR] Validasi aset final gagal. Merge dibatalkan.
goto :fail

:merge_abort_source_changed
git merge --abort >nul 2>&1
echo [BLOCKED] Build final mengubah file source/lock di luar merge.
goto :fail

:merge_abort_empty
git merge --abort >nul 2>&1
echo [ERROR] Merge tidak menghasilkan perubahan staged.
goto :fail

:commit_failed
echo [ERROR] Commit production gagal. Tidak ada push.
goto :fail

:fetch_before_push_failed
echo [ERROR] Tidak dapat mengecek ulang origin/main. Tidak ada push.
goto :fail

:main_changed
echo [BLOCKED] origin/main berubah selama validasi.
echo           Tidak ada push. Jalankan ulang setelah ai-patch diperbarui.
goto :fail

:push_failed
echo [ERROR] Push atomik gagal. Main dan production remote tidak diubah.
goto :fail

:success
if /I not "%SYNC_NO_PAUSE%"=="1" pause
endlocal
exit /b 0

:fail
echo.
echo [STOP] Main tidak dipush oleh proses ini.
if /I not "%SYNC_NO_PAUSE%"=="1" pause
endlocal
exit /b 1

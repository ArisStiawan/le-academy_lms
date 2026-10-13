$ErrorActionPreference = 'Continue'

# Jalankan dari root project lms-backend:
#   powershell -ExecutionPolicy Bypass -File tests/manual/master_test.ps1
# Pastikan server jalan: php artisan serve  (http://127.0.0.1:8000)

$base = 'http://127.0.0.1:8000/api/v1'
$tmp  = $PSScriptRoot
$script:pass = 0
$script:fail = 0

function Req($method, $url, $headers, $bodyObj) {
    $p = @{ Uri = $url; Method = $method; UseBasicParsing = $true }
    if ($headers) { $p.Headers = $headers }
    if ($null -ne $bodyObj) { $p.Body = ($bodyObj | ConvertTo-Json -Depth 8); $p.ContentType = 'application/json' }
    try {
        $r = Invoke-WebRequest @p
        return @{ s = [int]$r.StatusCode; b = $r.Content }
    } catch {
        $s = 0; $b = ''
        if ($_.Exception.Response) {
            $s = [int]$_.Exception.Response.StatusCode
            try { $rd = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream()); $b = $rd.ReadToEnd() } catch { $b = $_.ErrorDetails.Message }
        }
        return @{ s = $s; b = $b }
    }
}
function A($name, $actual, $expected) {
    if ($actual -eq $expected) { Write-Host ("PASS  {0}  -> {1}" -f $name, $actual); $script:pass++ }
    else { Write-Host ("FAIL  {0}  -> got {1}, expected {2}" -f $name, $actual, $expected); $script:fail++ }
}
function C($name, $body, $needle) {
    if ($body -match [regex]::Escape($needle)) { Write-Host ("PASS  {0}  (contains '{1}')" -f $name, $needle); $script:pass++ }
    else { Write-Host ("FAIL  {0}  (missing '{1}')" -f $name, $needle); $script:fail++ }
}
function CN($name, $body, $needle) {
    if ($body -notmatch [regex]::Escape($needle)) { Write-Host ("PASS  {0}  (tidak memuat '{1}')" -f $name, $needle); $script:pass++ }
    else { Write-Host ("FAIL  {0}  (harusnya tidak memuat '{1}')" -f $name, $needle); $script:fail++ }
}
function Tk($file) { return (& php artisan tinker "$tmp\$file" | Out-String) }
function Login($email) {
    $r = Req 'Post' "$base/auth/login" $null @{ email = $email; password = 'password123' }
    return "Bearer " + (($r.b | ConvertFrom-Json).data.token)
}

Write-Host "===== AUTH ====="
$a = Req 'Post' "$base/auth/register" $null @{ name='Reg Baru'; email='regbaru@test.com'; password='password123'; password_confirmation='password123' }
A 'AUTH register (201)' $a.s 201
C 'AUTH register default role siswa' $a.b 'siswa'
A 'AUTH register email duplikat (422)' (Req 'Post' "$base/auth/register" $null @{ name='X'; email='regbaru@test.com'; password='password123'; password_confirmation='password123' }).s 422
A 'AUTH register password mismatch (422)' (Req 'Post' "$base/auth/register" $null @{ name='X'; email='newx@test.com'; password='password123'; password_confirmation='beda' }).s 422
A 'AUTH login salah (422)' (Req 'Post' "$base/auth/login" $null @{ email='siswa@test.com'; password='salah' }).s 422
$null = Req 'Post' "$base/auth/register" $null @{ name='No Enroll'; email='noenroll@test.com'; password='password123'; password_confirmation='password123' }

$tokSiswa   = Login 'siswa@test.com'
$tokSiswa2  = Login 'siswa2@test.com'
$tokBuyer   = Login 'buyer@test.com'
$tokMentor  = Login 'mentor@test.com'
$tokMentor2 = Login 'mentor2@test.com'
$tokAdmin   = Login 'admin@test.com'
$tokLembaga = Login 'lembaga@test.com'
$tokNoEnroll = Login 'noenroll@test.com'

$hSiswa   = @{ Authorization = $tokSiswa }
$hSiswa2  = @{ Authorization = $tokSiswa2 }
$hBuyer   = @{ Authorization = $tokBuyer }
$hMentor  = @{ Authorization = $tokMentor }
$hMentor2 = @{ Authorization = $tokMentor2 }
$hAdmin   = @{ Authorization = $tokAdmin }
$hNoEnroll = @{ Authorization = $tokNoEnroll }

A 'AUTH me (200)' (Req 'Get' "$base/auth/me" $hSiswa).s 200
A 'AUTH me tanpa token (401)' (Req 'Get' "$base/auth/me" $null).s 401
$tokReg = Login 'regbaru@test.com'
A 'AUTH logout (200)' (Req 'Post' "$base/auth/logout" @{ Authorization = $tokReg }).s 200
A 'AUTH me setelah logout (401)' (Req 'Get' "$base/auth/me" @{ Authorization = $tokReg }).s 401

Write-Host "`n===== RBAC ====="
A 'RBAC submissions index sebagai siswa (403)' (Req 'Get' "$base/assessments/1/submissions" $hSiswa).s 403
A 'RBAC submissions index sebagai mentor (200)' (Req 'Get' "$base/assessments/1/submissions" $hMentor).s 200
A 'RBAC import siswa sebagai siswa (403)' (Req 'Post' "$base/institution/students/import" $hSiswa @{ package_id=1; emails=@('x@test.com') }).s 403
A 'RBAC cert index sebagai siswa (403)' (Req 'Get' "$base/certificates" $hSiswa).s 403

Write-Host "`n===== COMMERCE: PACKAGE & VOUCHER ====="
A 'PKG list publik (200)' (Req 'Get' "$base/packages" $null).s 200
A 'PKG detail (200)' (Req 'Get' "$base/packages/1" $null).s 200
A 'PKG nonaktif (404)' (Req 'Get' "$base/packages/2" $null).s 404
$pv = Req 'Get' "$base/checkout/preview?package_id=1" $hSiswa
A 'PREVIEW tanpa voucher (200)' $pv.s 200
A 'PREVIEW harga asli 200000' (($pv.b | ConvertFrom-Json).data.final_price) 200000
$pv2 = Req 'Get' "$base/checkout/preview?package_id=1&voucher_code=DISC10" $hSiswa
A 'PREVIEW diskon persen 10% (180000)' (($pv2.b | ConvertFrom-Json).data.final_price) 180000
$pv3 = Req 'Get' "$base/checkout/preview?package_id=1&voucher_code=NGAWUR" $hSiswa
A 'PREVIEW voucher tidak valid (422)' $pv3.s 422
A 'PREVIEW voucher kedaluwarsa (422)' (Req 'Get' "$base/checkout/preview?package_id=1&voucher_code=EXPIRED" $hSiswa).s 422
A 'PREVIEW paket nonaktif (422)' (Req 'Get' "$base/checkout/preview?package_id=2" $hSiswa).s 422

Write-Host "`n===== COMMERCE: CHECKOUT, ORDER, PAYMENT ====="
$co = Req 'Post' "$base/checkout" $hSiswa @{ package_id=1 }
A 'CHECKOUT buat order (201)' $co.s 201
$orderId = ($co.b | ConvertFrom-Json).data.order_id
A 'CHECKOUT duplikat pending (409)' (Req 'Post' "$base/checkout" $hSiswa @{ package_id=1 }).s 409
A 'ORDERS list (200)' (Req 'Get' "$base/orders" $hSiswa).s 200
A 'ORDER pay bukan pemilik (403)' (Req 'Post' "$base/orders/$orderId/pay" $hSiswa2).s 403
$pay = Req 'Post' "$base/orders/$orderId/pay" $hSiswa
A 'ORDER pay pemilik (200)' $pay.s 200
$enrId = ($pay.b | ConvertFrom-Json).data.enrollment_id
A 'ORDER pay ulang (422)' (Req 'Post' "$base/orders/$orderId/pay" $hSiswa).s 422
A 'CHECKOUT setelah ter-enroll (422)' (Req 'Post' "$base/checkout" $hSiswa @{ package_id=1 }).s 422

# Voucher berbatas pemakaian (LIMIT1)
$c2 = Req 'Post' "$base/checkout" $hSiswa2 @{ package_id=1; voucher_code='LIMIT1' }
A 'CHECKOUT siswa2 voucher LIMIT1 (201)' $c2.s 201
$oid2 = ($c2.b | ConvertFrom-Json).data.order_id
$pay2 = Req 'Post' "$base/orders/$oid2/pay" $hSiswa2
A 'ORDER pay siswa2 (200)' $pay2.s 200
$enrId2 = ($pay2.b | ConvertFrom-Json).data.enrollment_id
A 'CHECKOUT voucher sudah habis (422)' (Req 'Post' "$base/checkout" $hBuyer @{ package_id=1; voucher_code='LIMIT1' }).s 422

# Buyer dengan voucher fixed
$c4 = Req 'Post' "$base/checkout" $hBuyer @{ package_id=1; voucher_code='POTONG50' }
A 'CHECKOUT buyer voucher fixed (201)' $c4.s 201
$oid4 = ($c4.b | ConvertFrom-Json).data.order_id
$pay4 = Req 'Post' "$base/orders/$oid4/pay" $hBuyer
A 'ORDER pay buyer (200)' $pay4.s 200
$enrIdBuyer = ($pay4.b | ConvertFrom-Json).data.enrollment_id

Write-Host "`n===== ASSESSMENT & SUBMISSION ====="
A 'ENROLLMENT submit tanpa enroll (403)' (Req 'Post' "$base/assessments/1/submit" $hNoEnroll @{ answer_text='belum enroll' }).s 403
$sub = Req 'Post' "$base/assessments/1/submit" $hSiswa @{ answer_text='Ini jawaban saya' }
A 'SUBMIT pertama (201)' $sub.s 201
C 'SUBMIT response ada submitted_at' $sub.b 'submitted_at'
$subId = ($sub.b | ConvertFrom-Json).data.id
A 'SUBMIT kedua / update (200)' (Req 'Post' "$base/assessments/1/submit" $hSiswa @{ answer_text='Jawaban diperbarui' }).s 200
A 'SUBMIT kosong (422)' (Req 'Post' "$base/assessments/1/submit" $hSiswa @{ answer_text='' }).s 422
A 'SUBMIT tanpa token (401)' (Req 'Post' "$base/assessments/1/submit" $null @{ answer_text='x' }).s 401
A 'GRADE sebagai mentor (200)' (Req 'Patch' "$base/submissions/$subId/grade" $hMentor @{ score=85; feedback='Bagus' }).s 200
A 'GRADE sebagai siswa (403)' (Req 'Patch' "$base/submissions/$subId/grade" $hSiswa @{ score=90 }).s 403
A 'GRADE melebihi max_score (422)' (Req 'Patch' "$base/submissions/$subId/grade" $hMentor @{ score=150 }).s 422
A 'GRADE skor negatif (422)' (Req 'Patch' "$base/submissions/$subId/grade" $hMentor @{ score=-1 }).s 422
A 'SUBMIT setelah dinilai (422)' (Req 'Post' "$base/assessments/1/submit" $hSiswa @{ answer_text='edit' }).s 422

Write-Host "`n===== TENANT SCOPING (institusi) ====="
A 'TENANT mentor lain index submissions (403)' (Req 'Get' "$base/assessments/1/submissions" $hMentor2).s 403
A 'TENANT mentor lain grade submission (403)' (Req 'Patch' "$base/submissions/$subId/grade" $hMentor2 @{ score=90 }).s 403
A 'TENANT platform admin index submissions (200)' (Req 'Get' "$base/assessments/1/submissions" $hAdmin).s 200
A 'TENANT mentor pemilik index submissions (200)' (Req 'Get' "$base/assessments/1/submissions" $hMentor).s 200

Write-Host "`n===== GAMIFIKASI (via Tinker) ====="
$vg = Tk 'verify_gamification.php'
foreach ($line in ($vg -split "`n")) { if ($line -match '^(XP_TASK|TOTAL_AFTER_TASK|MATLOG|IDEMPOTENT_AFTER|LEVELUP)=') { Write-Host "TINKER $($line.Trim())" } }
if ($vg -match 'XP_TASK=50') { Write-Host "PASS  XP dari tugas = 50"; $script:pass++ } else { Write-Host "FAIL  XP dari tugas ($vg)"; $script:fail++ }
if ($vg -match 'MATLOG=30') { Write-Host "PASS  XP material = xp_reward modul (30)"; $script:pass++ } else { Write-Host "FAIL  XP material"; $script:fail++ }
if ($vg -match 'IDEMPOTENT_AFTER=180') { Write-Host "PASS  XP idempotent (tidak dobel) = 180"; $script:pass++ } else { Write-Host "FAIL  XP idempotent"; $script:fail++ }
if ($vg -match 'LEVELUP=Mahir') { Write-Host "PASS  naik level otomatis = Mahir"; $script:pass++ } else { Write-Host "FAIL  naik level"; $script:fail++ }

Write-Host "`n===== INSTITUSI B2B (IMPORT & INVITE) ====="
$imp = Req 'Post' "$base/institution/students/import" $hMentor @{ package_id=1; emails=@('imp1@test.com','imp2@test.com') }
A 'IMPORT siswa (201)' $imp.s 201
C 'IMPORT 2 akun dibuat & enrolled' $imp.b '"created_and_enrolled":2'
CN 'IMPORT tidak bocorkan temp_password' $imp.b 'temp_password'
$imp2 = Req 'Post' "$base/institution/students/import" $hMentor @{ package_id=1; emails=@('imp1@test.com','imp2@test.com') }
A 'IMPORT ulang (201)' $imp2.s 201
C 'IMPORT skip yang sudah ter-enroll' $imp2.b '"skipped_already_enrolled":2'

$inv = Req 'Post' "$base/institution/students/invite" $hMentor @{ emails=@('inv@test.com'); package_id=1; role='siswa' }
A 'INVITE buat undangan (201)' $inv.s 201
$invUrl = ($inv.b | ConvertFrom-Json).data[0].invite_url
$invToken = (($invUrl -split '/invites/')[1]) -replace '/accept',''
A 'INVITE lihat publik (200)' (Req 'Get' "$base/institution/invites/$invToken" $null).s 200
$acc = Req 'Post' "$base/institution/invites/$invToken/accept" $null @{ name='Inv User'; password='password123'; password_confirmation='password123' }
A 'INVITE terima (201 + token)' $acc.s 201
C 'INVITE terima mengembalikan token' $acc.b 'token'
A 'INVITE lihat setelah dipakai (410)' (Req 'Get' "$base/institution/invites/$invToken" $null).s 410
A 'INVITE terima ulang (410)' (Req 'Post' "$base/institution/invites/$invToken/accept" $null @{ name='x'; password='password123'; password_confirmation='password123' }).s 410
A 'INVITE kedaluwarsa (410)' (Req 'Get' "$base/institution/invites/EXPIREDTOKEN123" $null).s 410
A 'INVITE admin tanpa lembaga (422)' (Req 'Post' "$base/institution/students/invite" $hAdmin @{ emails=@('z@test.com') }).s 422

Write-Host "`n===== SERTIFIKAT ====="
$null = Tk 'set_progress_100.php'
$cr = Req 'Post' "$base/certificates/request" $hSiswa @{ enrollment_id = $enrId }
A 'CERT ajukan (201)' $cr.s 201
$certId = ($cr.b | ConvertFrom-Json).data.certificate_id
A 'CERT ajukan duplikat (409)' (Req 'Post' "$base/certificates/request" $hSiswa @{ enrollment_id = $enrId }).s 409
A 'CERT ajukan progres <100 (422)' (Req 'Post' "$base/certificates/request" $hBuyer @{ enrollment_id = $enrIdBuyer }).s 422
A 'CERT mine (200)' (Req 'Get' "$base/certificates/mine" $hSiswa).s 200
A 'CERT index mentor (200)' (Req 'Get' "$base/certificates" $hMentor).s 200
A 'CERT index mentor lain kosong (200)' (Req 'Get' "$base/certificates" $hMentor2).s 200
$certsMentor2 = Req 'Get' "$base/certificates" $hMentor2
A 'CERT approve sebagai siswa (403)' (Req 'Patch' "$base/certificates/$certId/approve" $hSiswa).s 403
A 'CERT approve mentor lain (403)' (Req 'Patch' "$base/certificates/$certId/approve" $hMentor2).s 403
$ap = Req 'Patch' "$base/certificates/$certId/approve" $hMentor
A 'CERT approve mentor (200)' $ap.s 200
$code = ($ap.b | ConvertFrom-Json).data.certificate_code
CN 'CERT index mentor lain tidak memuat sertifikat tenant lain' $certsMentor2.b $code
A 'CERT approve ulang (422)' (Req 'Patch' "$base/certificates/$certId/approve" $hMentor).s 422
C 'CERT verify publik valid' (Req 'Get' "$base/certificates/verify/$code" $null).b '"valid":true'
A 'CERT verify kode salah (404)' (Req 'Get' "$base/certificates/verify/SALAH123" $null).s 404

# Alur tolak (reject)
$cr2 = Req 'Post' "$base/certificates/request" $hSiswa2 @{ enrollment_id = $enrId2 }
A 'CERT ajukan ke-2 (201)' $cr2.s 201
$cert2 = ($cr2.b | ConvertFrom-Json).data.certificate_id
$rj = Req 'Patch' "$base/certificates/$cert2/reject" $hAdmin @{ notes='Belum lengkap' }
A 'CERT reject admin (200)' $rj.s 200
C 'CERT reject status rejected' $rj.b '"status":"rejected"'

Write-Host "`n========================================"
Write-Host ("TOTAL: {0} PASS, {1} FAIL" -f $script:pass, $script:fail)
Write-Host "========================================"

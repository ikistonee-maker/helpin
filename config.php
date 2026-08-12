\<?php
/* ============================================================
   HELPIN — Bengkel & Roadside Assistance
   by Kiton & Fadhil
   ------------------------------------------------------------
   Cara pakai : simpan sebagai index.php lalu jalankan
                php -S localhost:8000
   Akun demo  : demo / demo123
   ============================================================ */

session_start();

/* ---------- Penyimpanan sederhana (JSON file) ---------- */
define('DATA_DIR', __DIR__ . '/data');

function data_path(string $name): string {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0775, true);
    $p = DATA_DIR . '/' . $name . '.json';
    if (!file_exists($p)) file_put_contents($p, '[]');
    return $p;
}
function db_read(string $name): array {
    $d = json_decode(file_get_contents(data_path($name)) ?: '[]', true);
    return is_array($d) ? $d : [];
}
function db_write(string $name, array $data): void {
    file_put_contents(data_path($name), json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function rp(int $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }
function flash(string $type, string $msg): void { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
function get_flash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function redirect(string $hash = ''): void {
    header('Location: ' . ($_SERVER['PHP_SELF'] ?? 'index.php') . $hash);
    exit;
}
function tgl_id(string $ymd): string {
    $ts = strtotime($ymd); if (!$ts) return '—';
    $b = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return date('d', $ts) . ' ' . $b[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}
function make_booking_code(array $bookings): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $s = '';
        for ($i = 0; $i < 4; $i++) $s .= $chars[random_int(0, strlen($chars) - 1)];
        $kode = 'HLP-' . date('md') . '-' . $s;
    } while (in_array($kode, array_column($bookings, 'kode'), true));
    return $kode;
}

/* ---------- Data master ---------- */
$SERVICES = [
    ['id' => 'oli',      'nama' => 'Ganti Oli & Filter',         'harga' => 65000,  'durasi' => '± 30 mnt',  'desc' => 'Oli 10W-40 semi sintetik + cek filter udara'],
    ['id' => 'tune',     'nama' => 'Tune Up Mesin',              'harga' => 150000, 'durasi' => '± 60 mnt',  'desc' => 'Bersihkan throttle body, cek busi & stasioner'],
    ['id' => 'rem',      'nama' => 'Servis Rem Depan/Belakang',  'harga' => 120000, 'durasi' => '± 45 mnt',  'desc' => 'Kampas, bersihkan kaliper, minyak rem'],
    ['id' => 'ban',      'nama' => 'Tambal / Ganti Ban',         'harga' => 35000,  'durasi' => '± 20 mnt',  'desc' => 'Tambal tubeless + cek tekanan angin'],
    ['id' => 'aki',      'nama' => 'Cek & Jumper Aki',           'harga' => 50000,  'durasi' => '± 15 mnt',  'desc' => 'Tes tegangan, cas aki, jumper darurat'],
    ['id' => 'kopling',  'nama' => 'Servis Kopling',             'harga' => 250000, 'durasi' => '± 90 mnt',  'desc' => 'Setel / ganti kampas kopling'],
    ['id' => 'listrik',  'nama' => 'Cek Kelistrikan + ECU Scan', 'harga' => 80000,  'durasi' => '± 40 mnt',  'desc' => 'Scanner diagnostik + cek jalur kabel'],
    ['id' => 'berat',    'nama' => 'Servis Besar (Turun Mesin)', 'harga' => 900000, 'durasi' => '1–2 hari',  'desc' => 'Overhaul mesin, ring seher, packing set'],
];
$MEKANIK = ['Pak Joko', 'Bang Salman', 'Mas Rendra', 'Pak Dedi', 'Mas Bagus', 'Bu Ratna'];

/* ---------- Seed akun demo ---------- */
$users = db_read('users');
if (empty($users)) {
    $users[] = [
        'id'       => 'u_' . bin2hex(random_bytes(4)),
        'username' => 'demo',
        'password' => password_hash('demo123', PASSWORD_DEFAULT),
        'nama'     => 'Pengguna Demo',
        'created'  => date('c'),
    ];
    db_write('users', $users);
}

/* ---------- User aktif ---------- */
$user = null;
if (isset($_SESSION['uid'])) {
    foreach (db_read('users') as $u) if ($u['id'] === $_SESSION['uid']) { $user = $u; break; }
    if (!$user) unset($_SESSION['uid']);
}

/* ---------- Logout ---------- */
if (($_GET['action'] ?? '') === 'logout') { session_destroy(); redirect(); }

/* ---------- Aksi POST ---------- */
$authTab = 'login';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!$user && $action === 'login') {
        $uname = trim($_POST['username'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $found = null;
        foreach (db_read('users') as $u) if ($u['username'] === $uname) { $found = $u; break; }
        if ($found && password_verify($pass, $found['password'])) {
            $_SESSION['uid'] = $found['id'];
            flash('ok', 'Selamat datang kembali, ' . $found['nama'] . '!');
            redirect();
        }
        flash('err', 'Username atau password salah.');
    }

    elseif (!$user && $action === 'register') {
        $authTab = 'register';
        $nama  = trim($_POST['nama'] ?? '');
        $uname = trim($_POST['username'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $users = db_read('users');
        if ($nama === '' || strlen($uname) < 3 || strlen($pass) < 5) {
            flash('err', 'Lengkapi semua kolom (username min. 3 huruf, password min. 5).');
        } elseif (in_array($uname, array_column($users, 'username'), true)) {
            flash('err', 'Username sudah dipakai, pilih yang lain.');
        } else {
            $new = [
                'id' => 'u_' . bin2hex(random_bytes(4)),
                'username' => $uname,
                'password' => password_hash($pass, PASSWORD_DEFAULT),
                'nama' => $nama ?: $uname,
                'created' => date('c'),
            ];
            $users[] = $new;
            db_write('users', $users);
            $_SESSION['uid'] = $new['id'];
            flash('ok', 'Akun berhasil dibuat. Selamat datang, ' . $new['nama'] . '!');
            redirect();
        }
    }

    elseif ($user && $action === 'booking') {
        $svc = null;
        foreach ($SERVICES as $s) if ($s['id'] === ($_POST['layanan'] ?? '')) $svc = $s;
        if (!$svc) $svc = $SERVICES[0];
        $bookings = db_read('bookings');
        $bookings[] = [
            'id'        => 'bk_' . bin2hex(random_bytes(4)),
            'user_id'   => $user['id'],
            'kode'      => make_booking_code($bookings),
            'layanan'   => $svc['nama'],
            'harga'     => $svc['harga'],
            'kendaraan' => trim($_POST['kendaraan'] ?? ''),
            'plat'      => trim($_POST['plat'] ?? ''),
            'tanggal'   => $_POST['tanggal'] ?? '',
            'jam'       => $_POST['jam'] ?? '',
            'catatan'   => trim($_POST['catatan'] ?? ''),
            'bayar'     => 'Belum dibayar',
            'created'   => date('c'),
        ];
        db_write('bookings', $bookings);
        flash('ok', 'Booking dibuat! Kode booking Anda: ' . end($bookings)['kode']);
        redirect('#booking');
    }

    elseif ($user && $action === 'mogok') {
        $lat = trim($_POST['lat'] ?? ''); $lng = trim($_POST['lng'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');
        if ($lat === '' || $lng === '' || $alamat === '') {
            flash('err', 'Lokasi wajib dikirim saat melaporkan mogok.');
            redirect('#darurat');
        }
        $mogok = db_read('mogok');
        $mogok[] = [
            'id'      => 'mg_' . bin2hex(random_bytes(4)),
            'user_id' => $user['id'],
            'kode'    => 'SOS-' . random_int(1000, 9999),
            'lat' => $lat, 'lng' => $lng,
            'alamat'  => $alamat,
            'masalah' => trim($_POST['masalah'] ?? 'Mogok'),
            'kendaraan' => trim($_POST['kendaraan'] ?? ''),
            'mekanik' => $MEKANIK[array_rand($MEKANIK)],
            'eta'     => random_int(12, 35),
            'status'  => 'Dalam perjalanan',
            'created' => date('c'),
        ];
        db_write('mogok', $mogok);
        $last = end($mogok);
        flash('ok', 'Bantuan dikirim! ' . $last['mekanik'] . ' menuju lokasi Anda (± ' . $last['eta'] . ' mnt).');
        redirect('#darurat');
    }

    elseif ($user && $action === 'mogok_selesai') {
        $mogok = db_read('mogok');
        foreach ($mogok as &$m) if ($m['id'] === ($_POST['id'] ?? '') && $m['user_id'] === $user['id']) $m['status'] = 'Selesai';
        db_write('mogok', $mogok);
        flash('ok', 'Laporan ditutup. Terima kasih sudah memakai Helpin!');
        redirect('#darurat');
    }

    elseif ($user && $action === 'bayar') {
        $bookings = db_read('bookings');
        foreach ($bookings as &$b) if ($b['id'] === ($_POST['booking_id'] ?? '') && $b['user_id'] === $user['id']) $b['bayar'] = 'Menunggu verifikasi';
        db_write('bookings', $bookings);
        flash('ok', 'Konfirmasi pembayaran QRIS dikirim. Menunggu verifikasi admin.');
        redirect('#bayar');
    }
}

/* ---------- Data dashboard ---------- */
$myBookings = []; $myMogok = []; $activeMogok = null;
$sosAktif = 0; $bookingHariIni = 0; $unpaid = [];
if ($user) {
    $allBookings = db_read('bookings');
    $allMogok    = db_read('mogok');
    $myBookings  = array_values(array_filter($allBookings, fn($b) => $b['user_id'] === $user['id']));
    $myMogok     = array_values(array_filter($allMogok, fn($m) => $m['user_id'] === $user['id']));
    usort($myBookings, fn($a, $b) => strtotime($b['created']) <=> strtotime($a['created']));
    usort($myMogok, fn($a, $b) => strtotime($b['created']) <=> strtotime($a['created']));
    foreach ($myMogok as $m) if ($m['status'] !== 'Selesai') { $activeMogok = $m; break; }
    $sosAktif       = count(array_filter($allMogok, fn($m) => $m['status'] !== 'Selesai'));
    $bookingHariIni = count(array_filter($allBookings, fn($b) => date('Y-m-d', strtotime($b['created'])) === date('Y-m-d')));
    $unpaid         = array_values(array_filter($myBookings, fn($b) => $b['bayar'] === 'Belum dibayar'));
}
function badge(string $st): string {
    return ['Belum dibayar' => 'b-red', 'Menunggu verifikasi' => 'b-amber', 'Lunas' => 'b-grn'][$st] ?? 'b-amber';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HELPIN — Bengkel & Roadside Assistance | by Kiton & Fadhil</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔧</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@500;700;800;900&family=Space+Grotesk:wght@400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
/* ================= DASAR ================= */
:root{
  --bg:#12161c; --panel:#1a2129; --panel2:#141a21; --line:#2c3642;
  --ink:#eef3f8; --mut:#93a1b2;
  --yel:#ffcf33; --yel2:#ffb800; --red:#ff4136; --grn:#37d67a; --blu:#4da3ff;
  --disp:'Saira Condensed',sans-serif; --body:'Space Grotesk',sans-serif; --mono:'Space Mono',monospace;
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{
  font-family:var(--body); color:var(--ink); min-height:100vh;
  background:
    radial-gradient(1100px 520px at 85% -10%, rgba(255,207,51,.08), transparent 60%),
    radial-gradient(900px 600px at -10% 110%, rgba(77,163,255,.07), transparent 60%),
    repeating-linear-gradient(45deg, rgba(255,255,255,.015) 0 2px, transparent 2px 6px),
    var(--bg);
}
::selection{background:var(--yel);color:#151515}
h1,h2,h3{font-family:var(--disp);text-transform:uppercase;line-height:1}
a{color:inherit}
.wrap{width:min(1180px,92%);margin-inline:auto}
section{scroll-margin-top:96px;padding:78px 0}

/* ================= KOMPONEN ================= */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;font-family:var(--disp);
  text-transform:uppercase;letter-spacing:.07em;font-weight:700;font-size:1.05rem;padding:13px 24px;
  border-radius:6px;border:1px solid transparent;cursor:pointer;text-decoration:none;
  transition:transform .15s,box-shadow .2s,background .2s,color .2s}
.btn:hover{transform:translateY(-2px)}
.btn:active{transform:translateY(0)}
.btn-yel{background:var(--yel);color:#151515}
.btn-yel:hover{box-shadow:0 10px 24px rgba(255,207,51,.28)}
.btn-red{background:var(--red);color:#fff}
.btn-red:hover{box-shadow:0 10px 24px rgba(255,65,54,.3)}
.btn-ghost{background:transparent;border-color:var(--line);color:var(--ink)}
.btn-ghost:hover{border-color:var(--yel);color:var(--yel)}
.btn-sm{padding:8px 14px;font-size:.95rem}
.badge{font-family:var(--mono);font-size:.7rem;padding:5px 10px;border-radius:4px;letter-spacing:.05em;white-space:nowrap}
.b-red{background:rgba(255,65,54,.14);color:#ff8a80;border:1px solid rgba(255,65,54,.4)}
.b-amber{background:rgba(255,207,51,.12);color:var(--yel);border:1px solid rgba(255,207,51,.4)}
.b-grn{background:rgba(55,214,122,.12);color:var(--grn);border:1px solid rgba(55,214,122,.4)}
label{display:block;font-family:var(--mono);font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--mut);margin:0 0 7px}
input,select,textarea{width:100%;background:#10151b;border:1px solid var(--line);color:var(--ink);
  padding:12px 14px;font:500 .95rem var(--body);border-radius:6px;transition:border-color .2s,box-shadow .2s}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--yel);box-shadow:0 0 0 3px rgba(255,207,51,.14)}
.field{margin-bottom:16px}
.hazard{height:12px;background:repeating-linear-gradient(-45deg,var(--yel) 0 16px,#161a20 16px 32px)}
.dot{display:inline-block;width:9px;height:9px;border-radius:50%;background:var(--grn);margin-right:8px;animation:blink 1.4s infinite}
.dot.red{background:var(--red)} .dot.yel{background:var(--yel)}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}

/* ================= TICKER ================= */
.ticker{background:var(--yel);color:#141414;overflow:hidden;font-family:var(--disp);font-weight:700;
  letter-spacing:.09em;text-transform:uppercase;font-size:.95rem;border-bottom:2px solid #000}
.ticker-track{display:flex;gap:3rem;white-space:nowrap;width:max-content;padding:8px 0;animation:tick 30s linear infinite}
@keyframes tick{to{transform:translateX(-50%)}}

/* ================= HEADER ================= */
.topbar{position:sticky;top:0;z-index:50;background:rgba(13,16,21,.93);border-bottom:1px solid var(--line);backdrop-filter:blur(8px)}
.topbar-in{display:flex;align-items:center;gap:26px;padding:14px 0}
.brand{display:flex;align-items:center;gap:12px;text-decoration:none}
.brand-ico{width:42px;height:42px;background:var(--yel);border-radius:8px;display:grid;place-items:center;font-size:1.3rem;
  box-shadow:4px 4px 0 rgba(255,207,51,.25);transition:transform .2s}
.brand:hover .brand-ico{transform:rotate(-12deg)}
.brand-name{font-family:var(--disp);font-weight:900;font-size:1.6rem;letter-spacing:.04em;line-height:.9}
.brand-tag{font-family:var(--mono);font-size:.6rem;color:var(--mut);letter-spacing:.14em;text-transform:uppercase}
.nav{display:flex;gap:4px;margin-left:auto;overflow-x:auto}
.nav a{font-family:var(--disp);text-transform:uppercase;font-weight:700;font-size:.95rem;letter-spacing:.08em;
  color:var(--mut);text-decoration:none;padding:8px 12px;position:relative;white-space:nowrap;transition:color .2s}
.nav a::after{content:'';position:absolute;left:12px;right:12px;bottom:2px;height:2px;background:var(--yel);transform:scaleX(0);transform-origin:left;transition:transform .25s}
.nav a:hover{color:var(--ink)} .nav a:hover::after{transform:scaleX(1)}
.userchip{display:flex;align-items:center;gap:10px;font-size:.85rem;color:var(--mut)}
.avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--yel),var(--yel2));
  color:#151515;display:grid;place-items:center;font-family:var(--disp);font-weight:900}

/* ================= HERO / DISPATCH ================= */
.hero{display:grid;grid-template-columns:1.15fr .85fr;gap:44px;align-items:center;padding:80px 0 60px}
.eyebrow{display:inline-flex;align-items:center;font-family:var(--mono);font-size:.72rem;letter-spacing:.16em;
  text-transform:uppercase;color:var(--yel);border:1px solid rgba(255,207,51,.35);padding:7px 14px;border-radius:99px;margin-bottom:22px}
.hero h1{font-size:clamp(3rem,7.2vw,6rem);font-weight:900;letter-spacing:.01em}
.hero h1 em{font-style:normal;color:var(--yel);display:block}
.hero p{color:var(--mut);max-width:46ch;margin:20px 0 28px;font-size:1.05rem;line-height:1.65}
.cta-row{display:flex;gap:14px;flex-wrap:wrap}
.sos-btn{position:relative;font-size:1.35rem;padding:16px 30px;animation:sospulse 1.8s infinite}
@keyframes sospulse{0%,100%{box-shadow:0 0 0 0 rgba(255,65,54,.5)}50%{box-shadow:0 0 0 16px rgba(255,65,54,0)}}
.stats{display:flex;gap:34px;margin-top:38px;flex-wrap:wrap}
.stat b{display:block;font-family:var(--mono);font-size:1.7rem;color:var(--yel)}
.stat span{font-family:var(--mono);font-size:.68rem;letter-spacing:.14em;color:var(--mut);text-transform:uppercase}
.dispatch{background:var(--panel);border:1px solid var(--line);border-top:4px solid var(--yel);border-radius:10px;overflow:hidden;
  box-shadow:0 26px 60px rgba(0,0,0,.4)}
.dispatch-head{display:flex;justify-content:space-between;align-items:center;padding:14px 20px;background:var(--panel2);
  font-family:var(--mono);font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;color:var(--mut);border-bottom:1px solid var(--line)}
.dispatch-body{padding:22px 20px}
.clock{font-family:var(--mono);font-size:2.9rem;font-weight:700;letter-spacing:.04em;color:var(--ink)}
.clock-date{font-family:var(--mono);font-size:.78rem;color:var(--mut);margin:4px 0 16px}
.d-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:1px dashed var(--line);font-size:.9rem}
.d-row span{color:var(--mut)} .d-row b{font-family:var(--mono)}

/* ================= JALAN ANIMASI ================= */
.road{position:relative;height:60px;background:#15181d;border-block:1px solid var(--line);overflow:hidden}
.road::before{content:'';position:absolute;left:0;right:0;top:50%;height:4px;transform:translateY(-50%);
  background:repeating-linear-gradient(90deg,var(--yel) 0 34px,transparent 34px 64px);animation:roadmove 1.1s linear infinite}
@keyframes roadmove{to{background-position:-64px 0}}
.van{position:absolute;top:9px;width:58px;height:26px;background:var(--yel);border-radius:5px 10px 4px 4px;animation:drive 7s linear infinite}
.van::before{content:'';position:absolute;top:4px;right:6px;width:14px;height:9px;background:#15181d;border-radius:2px}
.van::after{content:'';position:absolute;bottom:-5px;left:8px;width:10px;height:10px;border-radius:50%;background:#0c0f13;
  box-shadow:32px 0 0 #0c0f13,8px -34px 0 -1px var(--red)}
@keyframes drive{from{left:-90px}to{left:105%}}

/* ================= JUDUL SEKSI ================= */
.sec-head{display:flex;gap:24px;align-items:flex-end;margin-bottom:42px}
.sec-num{font-family:var(--disp);font-weight:900;font-size:5.2rem;line-height:.78;color:transparent;-webkit-text-stroke:1.5px var(--line)}
.sec-kicker{font-family:var(--mono);font-size:.7rem;letter-spacing:.2em;color:var(--yel);text-transform:uppercase;display:block;margin-bottom:8px}
.sec-head h2{font-size:clamp(1.9rem,3.6vw,2.9rem);font-weight:800}
.sec-head h2 em{font-style:normal;color:var(--yel)}

/* ================= MENU BENGKEL ================= */
.menu-board{display:grid;gap:12px}
.menu-row{display:grid;grid-template-columns:56px 1fr auto auto;gap:20px;align-items:center;padding:18px 22px;
  background:var(--panel);border:1px solid var(--line);border-radius:8px;position:relative;
  transition:transform .22s,border-color .22s,background .22s}
.menu-row::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:var(--yel);border-radius:8px 0 0 8px;
  transform:scaleY(0);transform-origin:top;transition:transform .25s}
.menu-row:hover{transform:translateX(8px);border-color:#3d4a5a;background:#1d2530}
.menu-row:hover::before{transform:scaleY(1)}
.m-num{font-family:var(--disp);font-weight:900;font-size:1.7rem;color:var(--yel);opacity:.9}
.m-name{font-family:var(--disp);font-weight:700;font-size:1.25rem;text-transform:uppercase;letter-spacing:.03em}
.m-desc{color:var(--mut);font-size:.85rem;margin-top:3px}
.m-dur{font-family:var(--mono);font-size:.7rem;color:var(--mut);border:1px solid var(--line);padding:4px 9px;border-radius:99px}
.m-price{font-family:var(--mono);font-weight:700;font-size:1.05rem;color:var(--ink);text-align:right;min-width:105px}
.m-price small{display:block;font-size:.62rem;color:var(--mut);font-weight:400;letter-spacing:.1em}

/* ================= BOOKING ================= */
.booking-grid{display:grid;grid-template-columns:420px 1fr;gap:34px;align-items:start}
.card{background:var(--panel);border:1px solid var(--line);border-radius:10px;padding:26px}
.card h3{font-size:1.4rem;font-weight:800;margin-bottom:20px}
.svc-price{font-family:var(--mono);color:var(--yel);font-size:.9rem;margin:-8px 0 16px;display:block}
.tickets{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:18px}
.ticket{background:var(--panel);border:1px solid var(--line);border-radius:10px;overflow:hidden;transition:transform .2s,border-color .2s}
.ticket:hover{transform:translateY(-4px);border-color:#3d4a5a}
.ticket-top{display:flex;justify-content:space-between;align-items:center;gap:10px;padding:16px 18px;background:var(--panel2);border-bottom:2px dashed var(--line)}
.ticket-label{font-family:var(--mono);font-size:.62rem;letter-spacing:.18em;color:var(--mut);display:block}
.ticket-code{font-family:var(--mono);font-weight:700;font-size:1.45rem;color:var(--yel);letter-spacing:.05em}
.ticket-body{padding:14px 18px;display:grid;gap:8px}
.trow{display:flex;justify-content:space-between;gap:12px;font-size:.9rem}
.trow span{color:var(--mut)} .trow b{text-align:right}
.ticket-foot{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-top:1px solid var(--line)}
.empty{color:var(--mut);border:1px dashed var(--line);border-radius:10px;padding:34px;text-align:center;font-size:.95rem}

/* ================= DARURAT / SOS ================= */
#darurat{background:linear-gradient(180deg,#2a1215,#190f12);border-block:8px solid transparent;
  border-image:repeating-linear-gradient(45deg,var(--yel) 0 14px,#141414 14px 28px) 8}
.sos-grid{display:grid;grid-template-columns:1.05fr .95fr;gap:44px;align-items:center}
.sos-grid h2{font-size:clamp(2.4rem,5vw,4rem);font-weight:900}
.sos-grid h2 em{font-style:normal;color:var(--red)}
.sos-grid p{color:#c9a9a5;margin:18px 0 26px;max-width:48ch;line-height:1.65}
.sos-steps{display:grid;gap:10px;margin-top:28px}
.sos-steps li{display:flex;gap:12px;align-items:center;list-style:none;font-size:.92rem;color:#d8bdb9}
.sos-steps i{font-style:normal;font-family:var(--disp);font-weight:900;width:30px;height:30px;background:var(--red);color:#fff;
  display:grid;place-items:center;border-radius:6px;flex:none}
.sos-card{background:#201417;border:1px solid #47242a;border-radius:10px;padding:26px;box-shadow:0 26px 60px rgba(0,0,0,.45)}
.sos-card h3{font-size:1.3rem;color:#ffd9d5;margin-bottom:6px}
.eta-big{font-family:var(--mono);font-size:2.4rem;font-weight:700;color:var(--yel);margin:10px 0}
.eta-big.done{font-size:1.3rem;color:var(--grn)}
.steps{display:flex;margin:22px 0 8px}
.step{flex:1;text-align:center;position:relative;font-size:.72rem;color:#b78f8b;font-family:var(--mono)}
.step::before{content:'';width:14px;height:14px;border-radius:50%;background:#3a2327;border:2px solid #5c3238;display:inline-block;margin-bottom:7px}
.step::after{content:'';position:absolute;top:6px;left:calc(50% + 14px);right:calc(-50% + 14px);height:2px;background:#3a2327}
.step:last-child::after{display:none}
.step.done::before{background:var(--grn);border-color:var(--grn)}
.step.done::after{background:var(--grn)}
.step.active::before{background:var(--yel);border-color:var(--yel);box-shadow:0 0 0 6px rgba(255,207,51,.15);animation:blink 1.4s infinite}
.maps-link{font-family:var(--mono);font-size:.8rem;color:var(--blu);word-break:break-all}
.loc-status{font-family:var(--mono);font-size:.75rem;margin-top:8px}
.loc-ok{color:var(--grn)} .loc-err{color:#ff8a80} .loc-wait{color:var(--yel)}

/* ================= MODAL ================= */
.modal-back{position:fixed;inset:0;background:rgba(6,8,11,.78);display:grid;place-items:center;z-index:100;
  opacity:0;pointer-events:none;transition:opacity .25s;padding:20px}
.modal-back.open{opacity:1;pointer-events:auto}
.modal{background:var(--panel);border:1px solid var(--line);border-top:5px solid var(--red);border-radius:10px;
  width:min(580px,100%);max-height:88vh;overflow:auto;padding:30px;transform:translateY(18px) scale(.98);transition:transform .25s}
.modal-back.open .modal{transform:none}
.modal h3{font-size:1.6rem;margin-bottom:6px}
.modal .sub{color:var(--mut);font-size:.9rem;margin-bottom:22px}
.map-row a{display:none}
.map-row.on a{display:inline}

/* ================= PEMBAYARAN ================= */
.pay-grid{display:grid;grid-template-columns:1fr 400px;gap:34px;align-items:start}
.pay-steps{display:grid;gap:14px;margin:6px 0 26px}
.pay-steps li{list-style:none;display:flex;gap:14px;align-items:flex-start;background:var(--panel);
  border:1px solid var(--line);border-radius:8px;padding:14px 16px;font-size:.92rem;transition:border-color .2s,transform .2s}
.pay-steps li:hover{border-color:var(--yel);transform:translateX(6px)}
.pay-steps i{font-style:normal;font-family:var(--disp);font-weight:900;font-size:1.2rem;color:#151515;background:var(--yel);
  width:32px;height:32px;display:grid;place-items:center;border-radius:6px;flex:none}
.qris-card{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:26px;text-align:center;position:relative;overflow:hidden}
.qris-card::before{content:'';position:absolute;top:0;left:0;right:0;height:8px;background:repeating-linear-gradient(-45deg,var(--yel) 0 14px,#161a20 14px 28px)}
.qris-head{display:flex;justify-content:space-between;align-items:center;margin:10px 0 18px;font-family:var(--mono);font-size:.72rem;letter-spacing:.14em;color:var(--mut)}
.qris-head b{color:var(--yel);font-size:1rem}
.qr-frame{background:#fff;border-radius:10px;padding:14px;position:relative;width:fit-content;margin:0 auto}
.qr-frame img{display:block;width:230px;height:230px}
.corner{position:absolute;width:22px;height:22px;border:4px solid var(--red)}
.c1{top:-7px;left:-7px;border-right:0;border-bottom:0}
.c2{top:-7px;right:-7px;border-left:0;border-bottom:0}
.c3{bottom:-7px;left:-7px;border-right:0;border-top:0}
.c4{bottom:-7px;right:-7px;border-left:0;border-top:0}
.pay-amount{font-family:var(--mono);font-size:2rem;font-weight:700;color:var(--yel);margin:18px 0 4px}
.pay-kode{font-family:var(--mono);font-size:.78rem;color:var(--mut);margin-bottom:18px}
.pay-note{font-size:.78rem;color:var(--mut);margin-top:16px;line-height:1.6}

/* ================= FOOTER ================= */
footer{border-top:1px solid var(--line);padding:50px 0 34px;text-align:left}
.foot-mark{font-family:var(--disp);font-weight:900;font-size:clamp(3.6rem,13vw,10rem);line-height:.85;
  color:transparent;-webkit-text-stroke:1.5px #2b3542;user-select:none;transition:-webkit-text-stroke .3s}
.foot-mark:hover{-webkit-text-stroke:1.5px var(--yel)}
.foot-row{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-top:18px;
  font-family:var(--mono);font-size:.75rem;color:var(--mut);letter-spacing:.06em}

/* ================= LOGIN ================= */
.auth-wrap{display:grid;grid-template-columns:1.1fr 1fr;min-height:100vh}
.auth-stage{position:relative;padding:44px;display:flex;flex-direction:column;border-right:1px solid var(--line);overflow:hidden;
  background:radial-gradient(700px 400px at 20% 0%,rgba(255,207,51,.09),transparent 60%),#10141a}
.auth-chip{display:inline-flex;align-items:center;font-family:var(--mono);font-size:.68rem;letter-spacing:.18em;color:var(--yel);
  border:1px solid rgba(255,207,51,.4);padding:7px 14px;border-radius:99px;width:fit-content;text-transform:uppercase}
.wordmark{margin-top:auto;font-family:var(--disp);font-weight:900;font-size:clamp(4.5rem,10vw,8.5rem);line-height:.85;letter-spacing:.01em}
.wordmark span{display:block;color:transparent;-webkit-text-stroke:2px var(--yel)}
.auth-tag{font-family:var(--mono);font-size:.78rem;letter-spacing:.24em;color:var(--mut);text-transform:uppercase;margin:16px 0 30px}
.gear{position:absolute;font-size:3.4rem;opacity:.1;animation:floaty 7s ease-in-out infinite}
.g1{top:16%;right:14%} .g2{top:44%;right:30%;animation-delay:-3s;font-size:2.2rem}
@keyframes floaty{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-14px) rotate(20deg)}}
.auth-road{position:absolute;left:0;right:0;bottom:0}
.auth-panel{display:grid;place-items:center;padding:40px 8%}
.auth-card{width:min(430px,100%);background:var(--panel);border:1px solid var(--line);border-top:5px solid var(--yel);
  border-radius:12px;padding:34px;box-shadow:0 30px 70px rgba(0,0,0,.5)}
.auth-card h2{font-size:2rem;margin-bottom:6px}
.auth-card .sub{color:var(--mut);font-size:.9rem;margin-bottom:22px}
.tabs{display:flex;background:#10151b;border:1px solid var(--line);border-radius:8px;padding:4px;margin-bottom:24px}
.tab{flex:1;padding:10px;background:none;border:0;color:var(--mut);font-family:var(--disp);font-weight:700;font-size:1rem;
  letter-spacing:.1em;text-transform:uppercase;cursor:pointer;border-radius:6px;transition:.2s}
.tab.on{background:var(--yel);color:#151515}
.hide{display:none}
.demo-hint{margin-top:18px;font-family:var(--mono);font-size:.72rem;color:var(--mut);border:1px dashed var(--line);
  border-radius:8px;padding:12px 14px;line-height:1.7}
.demo-hint b{color:var(--yel)}
.auth-foot{margin-top:20px;text-align:center;font-family:var(--mono);font-size:.68rem;color:#5b6879;letter-spacing:.1em}

/* ================= TOAST & REVEAL ================= */
#toasts{position:fixed;top:84px;right:20px;z-index:200;display:grid;gap:10px}
.toast{background:#1c242e;border:1px solid var(--line);border-left:4px solid var(--grn);color:var(--ink);
  padding:13px 18px;border-radius:8px;font-size:.9rem;box-shadow:0 14px 34px rgba(0,0,0,.45);
  opacity:0;transform:translateX(24px);transition:.3s;max-width:340px}
.toast.err{border-left-color:var(--red)}
.toast.show{opacity:1;transform:none}
.reveal{opacity:0;transform:translateY(26px);transition:opacity .6s ease,transform .6s ease}
.reveal.in{opacity:1;transform:none}

/* ================= RESPONSIVE ================= */
@media (max-width:960px){
  .hero,.sos-grid,.booking-grid,.pay-grid{grid-template-columns:1fr}
  .auth-wrap{grid-template-columns:1fr}
  .auth-stage{min-height:46vh;border-right:0;border-bottom:1px solid var(--line)}
  .menu-row{grid-template-columns:40px 1fr;gap:10px}
  .m-price{text-align:left} .menu-row .btn{grid-column:2}
  .userchip span{display:none}
}
</style>
</head>
<body>
<?php $f = get_flash(); ?>
<?php if ($f): ?><div id="boot-flash" data-type="<?= e($f['type']) ?>" data-msg="<?= e($f['msg']) ?>"></div><?php endif; ?>
<div id="toasts"></div>

<?php /* ================================================================ HALAMAN LOGIN */ ?>
<?php if (!$user): ?>
<div class="auth-wrap">
  <div class="auth-stage">
    <div class="hazard" style="position:absolute;top:0;left:0;right:0"></div>
    <span class="gear g1">⚙️</span><span class="gear g2">🔩</span>
    <div style="margin-top:40px"><span class="auth-chip"><span class="dot"></span> Road Assistance 24/7</span></div>
    <h1 class="wordmark">HELPIN<span>BENGKEL &amp; SOS</span></h1>
    <p class="auth-tag">by Kiton &amp; Fadhil — Booking • Mogok • QRIS</p>
    <div class="road auth-road"><div class="van"></div></div>
  </div>
  <div class="auth-panel">
    <div class="auth-card">
      <h2>Area <em style="color:var(--yel);font-style:normal">Helpin</em></h2>
      <p class="sub">Masuk untuk booking servis atau panggil mekanik darurat.</p>
      <div class="tabs">
        <button class="tab" data-tab="login" type="button">Masuk</button>
        <button class="tab" data-tab="register" type="button">Daftar</button>
      </div>

      <form id="form-login" method="post">
        <input type="hidden" name="action" value="login">
        <div class="field"><label>Username</label><input name="username" required autocomplete="username" placeholder="contoh: demo"></div>
        <div class="field"><label>Password</label><input name="password" type="password" required placeholder="••••••••"></div>
        <button class="btn btn-yel" style="width:100%">🔧 Masuk ke Bengkel</button>
      </form>

      <form id="form-reg" method="post" class="hide">
        <input type="hidden" name="action" value="register">
        <div class="field"><label>Nama Lengkap</label><input name="nama" required placeholder="Nama Anda"></div>
        <div class="field"><label>Username</label><input name="username" required placeholder="min. 3 huruf"></div>
        <div class="field"><label>Password</label><input name="password" type="password" required placeholder="min. 5 karakter"></div>
        <button class="btn btn-yel" style="width:100%">Daftar Sekarang</button>
      </form>

      <div class="demo-hint">Akun demo → username <b>demo</b> · password <b>demo123</b></div>
      <p class="auth-foot">© 2026 HELPIN — DIBANGUN OLEH KITON &amp; FADHIL</p>
    </div>
  </div>
</div>

<?php /* ================================================================ DASHBOARD */ ?>
<?php else: ?>

<div class="ticker" aria-hidden="true">
  <div class="ticker-track">
    <span>⚡ Helpin siaga 24 jam</span><span>◆</span><span>Respon rata-rata 30 menit</span><span>◆</span>
    <span>Pembayaran 100% QRIS</span><span>◆</span><span>6 mekanik bertugas hari ini</span><span>◆</span>
    <span>Booking online dapat kode instan</span><span>◆</span><span>Mogok? Kirim lokasi, kami datang</span><span>◆</span>
    <span>⚡ Helpin siaga 24 jam</span><span>◆</span><span>Respon rata-rata 30 menit</span><span>◆</span>
    <span>Pembayaran 100% QRIS</span><span>◆</span><span>6 mekanik bertugas hari ini</span><span>◆</span>
    <span>Booking online dapat kode instan</span><span>◆</span><span>Mogok? Kirim lokasi, kami datang</span><span>◆</span>
  </div>
</div>

<header class="topbar">
  <div class="wrap topbar-in">
    <a class="brand" href="#beranda">
      <span class="brand-ico">🔧</span>
      <span><span class="brand-name">HELPIN</span><br><span class="brand-tag">by Kiton &amp; Fadhil</span></span>
    </a>
    <nav class="nav">
      <a href="#beranda">Beranda</a>
      <a href="#layanan">Menu Bengkel</a>
      <a href="#booking">Booking</a>
      <a href="#darurat">Darurat</a>
      <a href="#bayar">QRIS</a>
    </nav>
    <div class="userchip">
      <span class="avatar"><?= e(strtoupper(substr($user['nama'], 0, 1))) ?></span>
      <span><?= e($user['nama']) ?></span>
      <a class="btn btn-ghost btn-sm" href="?action=logout">Keluar</a>
    </div>
  </div>
</header>

<main>
  <!-- ============ BERANDA / DISPATCH ============ -->
  <div class="wrap hero" id="beranda">
    <div>
      <span class="eyebrow"><span class="dot"></span> Bengkel siaga — Rabu, 12 Agustus 2026</span>
      <h1>Mogok di jalan?<em>Kami datang.</em></h1>
      <p>Helpin adalah bengkel &amp; layanan derek darurat by <strong>Kiton &amp; Fadhil</strong>.
         Booking servis dapat kode instan, mogok tinggal kirim lokasi, dan semua pembayaran cukup <strong>scan QRIS</strong>.</p>
      <div class="cta-row">
        <button class="btn btn-red sos-btn" data-open="modal-sos">🚨 Saya Mogok — Kirim Lokasi</button>
        <a class="btn btn-ghost" href="#layanan">Lihat Menu Bengkel ↓</a>
      </div>
      <div class="stats">
        <div class="stat"><b>±30</b><span>Menit Respon</span></div>
        <div class="stat"><b>8</b><span>Layanan</span></div>
        <div class="stat"><b>24/7</b><span>Siaga</span></div>
        <div class="stat"><b>QRIS</b><span>Semua Bank</span></div>
      </div>
    </div>
    <div class="dispatch reveal">
      <div class="dispatch-head"><span>Panel Dispatch</span><span><span class="dot"></span>LIVE</span></div>
      <div class="dispatch-body">
        <div class="clock" id="clock">--:--:--</div>
        <div class="clock-date" id="clock-date">Memuat…</div>
        <div class="d-row"><span>Mekanik bertugas</span><b><span class="dot"></span><?= count($MEKANIK) ?> orang</b></div>
        <div class="d-row"><span>Booking hari ini</span><b><?= $bookingHariIni ?> antrean</b></div>
        <div class="d-row"><span>Permintaan SOS aktif</span><b style="color:<?= $sosAktif ? 'var(--red)' : 'var(--grn)' ?>"><?= $sosAktif ?> laporan</b></div>
        <div class="d-row"><span>Status armada</span><b style="color:var(--yel)">⚡ SIAGA</b></div>
      </div>
      <div class="road" style="border:0;border-top:1px solid var(--line)"><div class="van"></div></div>
    </div>
  </div>

  <!-- ============ 01 MENU BENGKEL ============ -->
  <section id="layanan">
    <div class="wrap">
      <div class="sec-head reveal">
        <span class="sec-num">01</span>
        <div><span class="sec-kicker">Daftar Harga Resmi</span><h2>Menu <em>Bengkel</em></h2></div>
      </div>
      <div class="menu-board">
        <?php foreach ($SERVICES as $i => $s): ?>
        <div class="menu-row reveal">
          <span class="m-num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
          <div>
            <div class="m-name"><?= e($s['nama']) ?> <span class="m-dur"><?= e($s['durasi']) ?></span></div>
            <div class="m-desc"><?= e($s['desc']) ?></div>
          </div>
          <div class="m-price"><?= rp($s['harga']) ?><small>mulai dari</small></div>
          <button class="btn btn-yel btn-sm" data-pick="<?= e($s['id']) ?>">Pilih</button>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ============ 02 BOOKING + KODE ============ -->
  <section id="booking" style="background:rgba(0,0,0,.18);border-block:1px solid var(--line)">
    <div class="wrap">
      <div class="sec-head reveal">
        <span class="sec-num">02</span>
        <div><span class="sec-kicker">Slot servis &amp; kode booking</span><h2>Booking <em>Online</em></h2></div>
      </div>
      <div class="booking-grid">
        <form class="card reveal" method="post">
          <input type="hidden" name="action" value="booking">
          <h3>🛠️ Form Booking</h3>
          <div class="field">
            <label>Layanan</label>
            <select name="layanan" id="f-layanan" required>
              <?php foreach ($SERVICES as $s): ?>
                <option value="<?= e($s['id']) ?>" data-harga="<?= $s['harga'] ?>"><?= e($s['nama']) ?> — <?= rp($s['harga']) ?></option>
              <?php endforeach; ?>
            </select>
            <span class="svc-price" id="svc-price"></span>
          </div>
          <div class="field"><label>Kendaraan (mis. Honda Beat 2019)</label><input name="kendaraan" required placeholder="Merek & tipe"></div>
          <div class="field"><label>Nomor Polisi</label><input name="plat" required placeholder="B 1234 XYZ" style="text-transform:uppercase"></div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div class="field"><label>Tanggal</label><input type="date" name="tanggal" required min="<?= date('Y-m-d') ?>"></div>
            <div class="field"><label>Jam</label><input type="time" name="jam" required></div>
          </div>
          <div class="field"><label>Catatan (opsional)</label><textarea name="catatan" rows="2" placeholder="Keluhan kendaraan…"></textarea></div>
          <button class="btn btn-yel" style="width:100%">Buat Booking → Dapat Kode</button>
        </form>

        <div>
          <?php if (empty($myBookings)): ?>
            <div class="empty">Belum ada booking. Pilih layanan di <a href="#layanan" style="color:var(--yel)">Menu Bengkel</a> lalu buat booking pertamamu. 🏁</div>
          <?php else: ?>
            <div class="tickets">
            <?php foreach ($myBookings as $b): ?>
              <article class="ticket reveal">
                <div class="ticket-top">
                  <div><span class="ticket-label">KODE BOOKING</span><div class="ticket-code"><?= e($b['kode']) ?></div></div>
                  <button class="btn btn-ghost btn-sm" data-copy="<?= e($b['kode']) ?>" type="button">Salin</button>
                </div>
                <div class="ticket-body">
                  <div class="trow"><span>Layanan</span><b><?= e($b['layanan']) ?></b></div>
                  <div class="trow"><span>Kendaraan</span><b><?= e($b['kendaraan']) ?: '—' ?> <?= e($b['plat']) ?></b></div>
                  <div class="trow"><span>Jadwal</span><b><?= e(tgl_id($b['tanggal'])) ?> • <?= e($b['jam']) ?: '—' ?></b></div>
                  <div class="trow"><span>Biaya</span><b style="color:var(--yel)"><?= rp((int)$b['harga']) ?></b></div>
                </div>
                <div class="ticket-foot">
                  <span class="badge <?= badge($b['bayar']) ?>"><?= e($b['bayar']) ?></span>
                  <?php if ($b['bayar'] === 'Belum dibayar'): ?>
                    <a href="#bayar" class="btn btn-yel btn-sm" data-pay="<?= e($b['id']) ?>">Bayar QRIS</a>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 03 DARURAT / MOGOK ============ -->
  <section id="darurat">
    <div class="wrap">
      <div class="sec-head reveal">
        <span class="sec-num" style="-webkit-text-stroke-color:#5c3238">03</span>
        <div><span class="sec-kicker" style="color:#ff8a80">Bantuan darurat jalan</span><h2 style="color:#ffe6e3">Layanan <em style="color:var(--red)">SOS Mogok</em></h2></div>
      </div>
      <div class="sos-grid">
        <div class="reveal">
          <h2>Mesin mati <em>di tengah jalan?</em></h2>
          <p>Tekan tombol di bawah, izinkan akses lokasi, dan mekanik terdekat langsung meluncur ke titik Anda. <strong>Lokasi wajib dikirim</strong> agar bantuan tepat sasaran.</p>
          <?php if (!$activeMogok): ?>
            <button class="btn btn-red sos-btn" data-open="modal-sos" style="font-size:1.6rem;padding:20px 40px">🚨 TEKAN UNTUK DARURAT</button>
          <?php endif; ?>
          <ul class="sos-steps">
            <li><i>1</i> Kirim lokasi GPS Anda (otomatis dari HP/browser)</li>
            <li><i>2</i> Mekanik terdekat ditugaskan + estimasi tiba</li>
            <li><i>3</i> Beres di tempat, bayar tinggal scan QRIS</li>
          </ul>
        </div>

        <div class="sos-card reveal">
          <?php if ($activeMogok): $m = $activeMogok;
                $maps = 'https://maps.google.com/?q=' . urlencode($m['lat'] . ',' . $m['lng']);
                $deadline = strtotime($m['created']) + $m['eta'] * 60; ?>
            <span class="badge b-red">LAPORAN AKTIF • <?= e($m['kode']) ?></span>
            <h3 style="margin-top:14px">🏍️ <?= e($m['mekanik']) ?> sedang menuju lokasi Anda</h3>
            <div style="color:var(--mut);font-size:.88rem">Masalah: <?= e($m['masalah']) ?><?= $m['kendaraan'] ? ' • ' . e($m['kendaraan']) : '' ?></div>
            <div class="eta-big" data-deadline="<?= $deadline ?>">~<?= $m['eta'] ?>:00</div>
            <div class="steps">
              <div class="step done">Diterima</div>
              <div class="step done">Ditugaskan</div>
              <div class="step active">Di jalan</div>
              <div class="step">Tiba</div>
            </div>
            <p style="font-family:var(--mono);font-size:.75rem;color:var(--mut);margin:14px 0 6px">📍 <?= e($m['alamat']) ?></p>
            <a class="maps-link" href="<?= e($maps) ?>" target="_blank" rel="noopener"><?= e($maps) ?></a>
            <form method="post" style="margin-top:20px">
              <input type="hidden" name="action" value="mogok_selesai">
              <input type="hidden" name="id" value="<?= e($m['id']) ?>">
              <button class="btn btn-ghost btn-sm" style="width:100%">✔ Tandai Selesai (masalah sudah tertangani)</button>
            </form>
          <?php else: ?>
            <h3>📡 Tidak ada laporan aktif</h3>
            <p style="color:var(--mut);font-size:.9rem;line-height:1.7;margin-top:8px">
              Kanal darurat standby. Saat kendaraan Anda mogok, buka tombol merah dan kirim lokasi —
              sistem akan menugaskan mekanik terdekat secara otomatis.</p>
            <?php if (!empty($myMogok)): ?>
              <div style="margin-top:16px;border-top:1px dashed #47242a;padding-top:14px">
                <span class="ticket-label" style="margin-bottom:8px;display:block">RIWAYAT SOS</span>
                <?php foreach (array_slice($myMogok, 0, 3) as $h): ?>
                  <div class="trow" style="padding:5px 0"><span><?= e($h['kode']) ?> • <?= e(tgl_id(date('Y-m-d', strtotime($h['created'])))) ?></span><b style="color:var(--grn)"><?= e($h['status']) ?></b></div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 04 PEMBAYARAN QRIS ============ -->
  <section id="bayar">
    <div class="wrap">
      <div class="sec-head reveal">
        <span class="sec-num">04</span>
        <div><span class="sec-kicker">Pembayaran hanya via QRIS</span><h2>Scan QRIS. <em>Beres.</em></h2></div>
      </div>
      <div class="pay-grid">
        <div class="reveal">
          <ul class="pay-steps">
            <li><i>1</i> Pilih kode booking yang akan dibayar di panel samping.</li>
            <li><i>2</i> Buka aplikasi bank / e-wallet apa pun (GoPay, OVO, DANA, ShopeePay, m-banking…) lalu scan QR.</li>
            <li><i>3</i> Tekan <strong>“Saya Sudah Bayar”</strong> — admin Helpin akan memverifikasi pembayaran Anda.</li>
          </ul>
          <?php if (empty($unpaid)): ?>
            <div class="empty">🎉 Tidak ada tagihan. Semua booking Anda sudah dibayar / menunggu verifikasi.</div>
          <?php else: ?>
            <div class="card">
              <h3>💳 Pilih Tagihan</h3>
              <div class="field" style="margin-bottom:0">
                <label>Booking yang belum dibayar</label>
                <select id="pay-select">
                  <?php foreach ($unpaid as $b): ?>
                    <option value="<?= e($b['id']) ?>" data-kode="<?= e($b['kode']) ?>" data-harga="<?= (int)$b['harga'] ?>">
                      <?= e($b['kode']) ?> — <?= e($b['layanan']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <?php if (!empty($unpaid)): $first = $unpaid[0]; ?>
        <div class="qris-card reveal">
          <div class="qris-head"><span>MERCHANT</span><b>QRIS</b><span>NMID: ID1026500506823 A01</span></div>
          <div class="qr-frame">
            <span class="corner c1"></span><span class="corner c2"></span><span class="corner c3"></span><span class="corner c4"></span>
            GANTI dengan gambar QRIS merchant asli bila sudah punya:
                 <img src="qris.jpeg" alt="QRIS Helpin" style="width:330px;height:390px;display:block;margin:0 auto">
            
          </div>
          <div class="pay-amount" id="pay-amount"><?= rp((int)$first['harga']) ?></div>
          <div class="pay-kode">KODE: <span id="pay-kode"><?= e($first['kode']) ?></span> • HELPIN by KITON &amp; FADHIL</div>
          <form method="post">
            <input type="hidden" name="action" value="bayar">
            <input type="hidden" name="booking_id" id="pay-id" value="<?= e($first['id']) ?>">
            <button class="btn btn-yel" style="width:100%">✅ Saya Sudah Bayar (QRIS)</button>
          </form>
          <p class="pay-note">Helpin <strong>hanya menerima pembayaran via QRIS</strong> — tidak menerima transfer manual atau tunai untuk booking online. Simpan bukti screenshot pembayaran Anda.</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>

<div class="road"><div class="van" style="animation-duration:9s"></div></div>
<footer>
  <div class="wrap">
    <div class="foot-mark">HELPIN</div>
    <div class="foot-row">
      <span>© 2026 HELPIN — ROADSIDE ASSISTANCE &amp; BENGKEL</span>
      <span>DIBANGUN OLEH <b style="color:var(--yel)">KITON &amp; FADHIL</b></span>
      <span>BOOKING • SOS LOKASI • QRIS ONLY</span>
    </div>
  </div>
</footer>

<!-- ============ MODAL SOS ============ -->
<div class="modal-back" id="modal-sos">
  <div class="modal">
    <h3>🚨 Laporan Mogok</h3>
    <p class="sub">Lokasi <strong style="color:var(--red)">wajib dikirim</strong> agar mekanik bisa menemukan Anda.</p>
    <form method="post" id="sos-form">
      <input type="hidden" name="action" value="mogok">
      <button type="button" class="btn btn-red" style="width:100%;margin-bottom:8px" onclick="detectLocation()">📍 Deteksi Lokasi Saya (GPS)</button>
      <div class="loc-status" id="loc-status">Belum ada lokasi terdeteksi.</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
        <div class="field"><label>Latitude</label><input name="lat" id="lat" required placeholder="-6.xxxxx"></div>
        <div class="field"><label>Longitude</label><input name="lng" id="lng" required placeholder="106.xxxxx"></div>
      </div>
      <div class="field map-row"><label>Link lokasi (Google Maps)</label><a href="#" target="_blank" rel="noopener" class="maps-link"></a></div>
      <div class="field"><label>Patokan / Alamat Lokasi *</label><textarea name="alamat" id="alamat" rows="2" required placeholder="Contoh: Jl. Merdeka depan Indomaret, dekat lampu merah"></textarea></div>
      <div class="field"><label>Kendaraan</label><input name="kendaraan" placeholder="Contoh: Yamaha Vixion merah" value=""></div>
      <div class="field"><label>Jenis Masalah</label>
        <select name="masalah">
          <option>Mesin mati total</option><option>Ban bocor / pecah</option><option>Aki lemah / tidak bisa distarter</option>
          <option>Rem bermasalah</option><option>Rantai / CVT putus</option><option>Tidak tahu penyebabnya</option>
        </select>
      </div>
      <div style="display:flex;gap:12px">
        <button type="button" class="btn btn-ghost" data-close style="flex:1">Batal</button>
        <button class="btn btn-red" style="flex:2">🏁 Kirim Lokasi &amp; Panggil Mekanik</button>
      </div>
    </form>
  </div>
</div>
<?php endif; /* akhir dashboard */ ?>

<script>
const $ = s => document.querySelector(s);
const $$ = s => document.querySelectorAll(s);

/* ---------- Toast ---------- */
function toast(msg, type = 'ok') {
  const t = document.createElement('div');
  t.className = 'toast ' + type;
  t.innerHTML = (type === 'ok' ? '✅ ' : '⚠️ ') + msg;
  $('#toasts').appendChild(t);
  requestAnimationFrame(() => t.classList.add('show'));
  setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 350); }, 4500);
}
const bf = $('#boot-flash');
if (bf) toast(bf.dataset.msg, bf.dataset.type);

/* ---------- Tab login/daftar ---------- */
function setTab(name) {
  $$('.tab').forEach(t => t.classList.toggle('on', t.dataset.tab === name));
  const fl = $('#form-login'), fr = $('#form-reg');
  if (fl) { fl.classList.toggle('hide', name !== 'login'); fr.classList.toggle('hide', name !== 'register'); }
}
$$('.tab').forEach(t => t.addEventListener('click', () => setTab(t.dataset.tab)));
setTab(<?= json_encode($authTab ?? 'login') ?>);

/* ---------- Jam live (panel dispatch) ---------- */
const BULAN = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const HARI  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
function clock() {
  const c = $('#clock'); if (!c) return;
  const d = new Date(), p = n => String(n).padStart(2, '0');
  c.textContent = p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
  $('#clock-date').textContent = HARI[d.getDay()] + ', ' + d.getDate() + ' ' + BULAN[d.getMonth()] + ' ' + d.getFullYear();
}
setInterval(clock, 1000); clock();

/* ---------- Reveal on scroll ---------- */
const io = new IntersectionObserver(es => es.forEach(x => {
  if (x.isIntersecting) { x.target.classList.add('in'); io.unobserve(x.target); }
}), { threshold: .12 });
$$('.reveal').forEach(el => io.observe(el));

/* ---------- Modal ---------- */
function openModal(id) { const m = document.getElementById(id); if (m) { m.classList.add('open'); document.body.style.overflow = 'hidden'; } }
$$('[data-open]').forEach(b => b.addEventListener('click', () => openModal(b.dataset.open)));
$$('.modal-back').forEach(m => {
  m.addEventListener('click', e => { if (e.target === m || e.target.closest('[data-close]')) { m.classList.remove('open'); document.body.style.overflow = ''; } });
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') $$('.modal-back.open').forEach(m => m.classList.remove('open')); });

/* ---------- Deteksi lokasi (wajib untuk SOS) ---------- */
function updateMapLink() {
  const row = document.querySelector('.map-row'); if (!row) return;
  const lat = $('#lat').value.trim(), lng = $('#lng').value.trim(), a = row.querySelector('a');
  if (lat && lng) { const u = 'https://maps.google.com/?q=' + lat + ',' + lng; a.href = u; a.textContent = u; row.classList.add('on'); }
}
function detectLocation() {
  const st = $('#loc-status');
  if (!navigator.geolocation) { st.textContent = 'Browser tidak mendukung GPS — isi koordinat manual.'; st.className = 'loc-status loc-err'; return; }
  st.textContent = '⏳ Mendeteksi lokasi Anda…'; st.className = 'loc-status loc-wait';
  navigator.geolocation.getCurrentPosition(pos => {
    $('#lat').value = pos.coords.latitude.toFixed(6);
    $('#lng').value = pos.coords.longitude.toFixed(6);
    updateMapLink();
    st.textContent = '✅ Lokasi terdeteksi! Lengkapi patokan alamat lalu kirim.'; st.className = 'loc-status loc-ok';
  }, err => {
    st.textContent = '❌ Gagal: ' + err.message + '. Izinkan akses lokasi di browser, atau isi koordinat manual.';
    st.className = 'loc-status loc-err';
  }, { enableHighAccuracy: true, timeout: 12000 });
}
['lat', 'lng'].forEach(id => { const el = document.getElementById(id); if (el) el.addEventListener('input', updateMapLink); });

const sosForm = $('#sos-form');
if (sosForm) sosForm.addEventListener('submit', e => {
  if (!$('#lat').value.trim() || !$('#lng').value.trim()) {
    e.preventDefault(); toast('Lokasi wajib dikirim! Tekan "Deteksi Lokasi Saya" dulu.', 'err');
  } else if (!$('#alamat').value.trim()) {
    e.preventDefault(); toast('Isi patokan/alamat lokasi Anda.', 'err');
  }
});

/* ---------- Pilih layanan dari menu bengkel ---------- */
const fLayanan = $('#f-layanan');
function refreshSvcPrice() {
  const sp = $('#svc-price'); if (!sp || !fLayanan) return;
  const o = fLayanan.selectedOptions[0];
  sp.textContent = o ? 'Estimasi biaya: Rp ' + Number(o.dataset.harga).toLocaleString('id-ID') : '';
}
if (fLayanan) fLayanan.addEventListener('change', refreshSvcPrice); refreshSvcPrice();
$$('[data-pick]').forEach(b => b.addEventListener('click', () => {
  if (fLayanan) { fLayanan.value = b.dataset.pick; refreshSvcPrice(); }
  document.getElementById('booking')?.scrollIntoView({ behavior: 'smooth' });
  toast('Layanan dipilih — lengkapi form booking di bawah. 🛠️');
}));

/* ---------- Salin kode booking ---------- */
$$('[data-copy]').forEach(btn => btn.addEventListener('click', async () => {
  try { await navigator.clipboard.writeText(btn.dataset.copy); toast('Kode ' + btn.dataset.copy + ' disalin!'); }
  catch (e) { toast('Gagal menyalin — salin manual ya.', 'err'); }
}));

/* ---------- Pembayaran QRIS ---------- */
const paySel = $('#pay-select');
function buildQR(kode, harga) {
  const payload = '000201|HELPIN-KITON-FADHIL|' + kode + '|IDR' + harga;
  return 'https://api.qrserver.com/v1/create-qr-code/?size=230x230&margin=8&data=' + encodeURIComponent(payload);
}
function refreshPay() {
  if (!paySel || !paySel.selectedOptions.length) return;
  const o = paySel.selectedOptions[0];
  $('#pay-amount').textContent = 'Rp ' + Number(o.dataset.harga).toLocaleString('id-ID');
  $('#pay-kode').textContent = o.dataset.kode;
  $('#pay-id').value = o.value;
  $('#qr-img').src = buildQR(o.dataset.kode, o.dataset.harga);
}
if (paySel) { paySel.addEventListener('change', refreshPay); refreshPay(); }
$$('[data-pay]').forEach(b => b.addEventListener('click', () => {
  if (paySel) { paySel.value = b.dataset.pay; refreshPay(); }
}));

/* ---------- Countdown ETA mekanik ---------- */
function tickEta() {
  $$('.eta-big[data-deadline]').forEach(el => {
    const diff = el.dataset.deadline * 1000 - Date.now();
    if (diff <= 0) { el.textContent = '🛵 Mekanik hampir tiba di lokasi!'; el.classList.add('done'); return; }
    const m = String(Math.floor(diff / 60000)).padStart(2, '0');
    const s = String(Math.floor(diff % 60000 / 1000)).padStart(2, '0');
    el.textContent = 'Tiba dalam ~' + m + ':' + s;
  });
}
setInterval(tickEta, 1000); tickEta();
</script>
</body>
</html>

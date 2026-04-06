<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../mainconfig.php';
require_once __DIR__ . '/../lib/check_session.php';
require_once __DIR__ . '/../lib/is_login.php';

/* ═══════ AJAX: qr_image proxy ═══════ */
if (($_GET['action'] ?? '') === 'qr_image') {
    $trxtopup = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['trxid'] ?? '');
    if (!$trxtopup) { http_response_code(404); exit; }

    // Verify ownership
    $qrow = $model->db_query($db, "qris_url, qris_image", "topups",
        "trxtopup='".$db->real_escape_string($trxtopup)."' AND user_id='".$db->real_escape_string($login['id'])."'");
    if (!$qrow['count']) { http_response_code(403); exit; }

    $img_url = $qrow['rows']['qris_image'] ?: $qrow['rows']['qris_url'];
    if (!$img_url) { http_response_code(404); exit; }

    // Fetch image server-side — client never sees the origin URL
    $ch = curl_init($img_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0',
    ]);
    $imgdata = curl_exec($ch);
    $ctype   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $httpcode= curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$imgdata || $httpcode !== 200) { http_response_code(502); exit; }

    // Only allow image content types
    if (!str_starts_with($ctype, 'image/')) { http_response_code(415); exit; }

    header('Content-Type: ' . $ctype);
    header('Content-Disposition: attachment; filename="QRIS_' . $trxtopup . '.png"');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $imgdata;
    exit;
}

/* ═══════ HALAMAN UTAMA ═══════ */
$trxtopup = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['trxid'] ?? '');
if (!$trxtopup) { header('Location: '.base_url('pages/deposit')); exit; }

$q = $model->db_query($db,
    "trxtopup, method, amount, post_amount, status, qris_url, qris_image, qris_text, pay_url, created_at, provider, remark, provider_meta",
    "topups",
    "trxtopup='".$db->real_escape_string($trxtopup)."' AND user_id='".$db->real_escape_string($login['id'])."'"
);
if (!$q['count']) { header('Location: '.base_url('pages/deposit')); exit; }

$trx       = $q['rows'];
$status    = $trx['status'];
$method    = $trx['method'];
$amount    = (float)$trx['amount'];
$qris_url  = $trx['qris_url']   ?? '';
$qris_img  = $trx['qris_image'] ?? '';
$pay_url   = $trx['pay_url']    ?? '';
$is_qris   = ($method === 'QRIS');
$is_manual = ($trx['provider'] === 'Manual');

$pm          = json_decode($trx['provider_meta'] ?? '{}', true) ?: [];
$rek_number  = $pm['rekening']       ?? '';
$rek_owner   = $pm['rekening_owner'] ?? '';
$unique_code = (int)($pm['unique_code'] ?? 0);
$base_amount = (int)($pm['base_amount'] ?? $amount);
$bank_note   = $pm['note'] ?? '';

if (empty($rek_number)) {
    $remark_raw = trim($trx['remark'] ?? '');
    if (preg_match('/rekening\s+(?:[A-Z\s]+\s+)?([\d]+)\s+atas\s+nama\s+(.+)/i', $remark_raw, $m)) {
        $rek_number = trim($m[1]);
        $rek_owner  = trim($m[2]);
    }
}

$expired_ts = $trx['created_at'] ? (strtotime($trx['created_at']) + 15 * 60) : (time() + 15 * 60);

/* user info */
$uq        = mysqli_query($db, "SELECT username, phone FROM users WHERE id='".$db->real_escape_string($login['id'])."' LIMIT 1");
$urow      = $uq ? mysqli_fetch_assoc($uq) : [];
$u_name    = $urow['username'] ?? ($urow['phone'] ?? 'user');

// Include header only for HTML start
require '../lib/header_user.php';
?>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Poppins', sans-serif; background: #0A0A0A; color: #fff; min-height: 100vh; -webkit-font-smoothing: antialiased; padding-bottom: 80px; }
.page { max-width: 480px; margin: 0 auto; min-height: 100vh; }

/* HEADER */
.topbar { display: flex; align-items: center; padding: 15px 15px 5px; gap: 12px; }
.tb-back { width: 32px; height: 32px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; font-size: 12px; flex-shrink: 0; }
.tb-title { flex: 1; text-align: center; font-size: 13px; font-weight: 800; color: #fff; padding-right: 32px; }

/* ── SUCCESS STATE ── */
.result-wrap { padding: 50px 20px; text-align: center; }
.result-ic { width: 72px; height: 72px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
.result-ic.ok  { background: rgba(16,185,129,0.1); border: 2px solid #10B981; color: #10B981; }
.result-ic.err { background: rgba(239,68,68,0.1); border: 2px solid #ef4444; color: #ef4444; }
.result-ic svg { width: 34px; height: 34px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }
.result-title  { font-size: 20px; font-weight: 800; color: #fff; }
.result-amount { font-size: 30px; font-weight: 800; color: #F5D061; margin-top: 4px; }
.result-sub    { font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 8px; line-height: 1.6; }
.result-btn { margin-top: 28px; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px; border-radius: 12px; background: linear-gradient(135deg,#C59327,#F5D061); color: #111; text-decoration: none; font-size: 13.5px; font-weight: 800; box-shadow: 0 6px 20px rgba(197,147,39,0.3); }
.result-btn.sec { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.1); box-shadow: none; margin-top: 10px; }

/* ── AMOUNT HERO ── */
.amount-hero {
    margin: 8px 15px 0;
    background: linear-gradient(135deg, #C59327 0%, #F5D061 100%);
    border-radius: 16px; padding: 18px; text-align: center;
    box-shadow: 0 8px 24px rgba(197,147,39,0.3);
    position: relative; overflow: hidden;
}
.ah-label { font-size: 10px; font-weight: 700; color: rgba(0,0,0,0.5); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.ah-amount { font-size: 30px; font-weight: 800; color: #111; letter-spacing: -1px; line-height: 1; }
.ah-badge { display: inline-flex; align-items: center; gap: 5px; margin-top: 8px; background: rgba(0,0,0,0.12); border-radius: 20px; padding: 4px 12px; font-size: 10px; font-weight: 700; color: #111; }
.ah-badge i { font-size: 9px; }

/* ── TIMER STRIP ── */
.timer-strip { margin: 10px 15px 0; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 10px 14px; display: flex; align-items: center; justify-content: space-between; }
.ts-left { display: flex; align-items: center; gap: 8px; font-size: 11px; color: rgba(255,255,255,0.5); font-weight: 600; }
.ts-left i { color: #F5D061; }
#countdown { font-size: 15px; font-weight: 800; font-family: monospace; color: #F5D061; letter-spacing: 1px; }

/* ── QR SECTION ── */
.qr-section { margin: 10px 15px 0; background: rgba(255,255,255,0.02); border: 1px solid rgba(197,147,39,0.2); border-radius: 16px; overflow: hidden; }
.qs-head { padding: 12px 14px; border-bottom: 1px dashed rgba(255,255,255,0.06); display: flex; align-items: center; gap: 8px; }
.qs-head i { color: #F5D061; font-size: 12px; }
.qs-head span { font-size: 10.5px; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: 0.5px; }
.qr-canvas { padding: 20px; display: flex; flex-direction: column; align-items: center; }
.qr-frame { width: 200px; height: 200px; background: #fff; border-radius: 14px; padding: 10px; box-shadow: 0 0 0 4px rgba(197,147,39,0.3); margin-bottom: 14px; cursor: pointer; }
.qr-frame img { width: 100%; height: 100%; object-fit: contain; }
.btn-dl { display: flex; align-items: center; justify-content: center; gap: 6px; background: rgba(197,147,39,0.1); border: 1px solid rgba(197,147,39,0.3); color: #F5D061; border-radius: 10px; padding: 9px 20px; font-size: 11.5px; font-weight: 700; font-family: 'Poppins', sans-serif; cursor: pointer; transition: 0.2s; }
.btn-dl:active { background: rgba(197,147,39,0.2); }

/* ── DETAIL BOX ── */
.detail-box { margin: 10px 15px 0; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; overflow: hidden; }
.db-head { padding: 10px 14px; border-bottom: 1px dashed rgba(255,255,255,0.06); display: flex; align-items: center; gap: 8px; }
.db-head i { color: #F5D061; font-size: 12px; }
.db-head span { font-size: 10.5px; font-weight: 800; color: #fff; text-transform: uppercase; letter-spacing: 0.5px; }
.d-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px dashed rgba(255,255,255,0.04); }
.d-row:last-child { border-bottom: none; }
.d-lbl { font-size: 10.5px; font-weight: 600; color: rgba(255,255,255,0.45); }
.d-val { font-size: 12px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px; font-family: monospace; letter-spacing: 0.5px; }
.d-val.gold { color: #F5D061; }
.btn-cp { width: 26px; height: 26px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); border-radius: 7px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.15s; flex-shrink: 0; }
.btn-cp i { font-size: 10px; color: rgba(255,255,255,0.5); }
.btn-cp:active { background: rgba(197,147,39,0.2); }
.btn-cp:active i { color: #F5D061; }

/* ── BANK REK BOX ── */
.rek-box { margin: 10px 15px 0; background: linear-gradient(135deg,#18181B,#111); border: 1px solid rgba(197,147,39,0.25); border-radius: 14px; padding: 14px; }
.rb-label { font-size: 9px; font-weight: 700; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
.rb-rek { font-size: 22px; font-weight: 800; color: #F5D061; font-family: monospace; letter-spacing: 2px; }
.rb-name { font-size: 10.5px; font-weight: 600; color: rgba(255,255,255,0.5); margin-top: 2px; margin-bottom: 10px; }
.rb-copy { display: flex; align-items: center; justify-content: center; gap: 6px; background: rgba(197,147,39,0.1); border: 1px solid rgba(197,147,39,0.3); border-radius: 8px; padding: 8px; font-size: 11px; font-weight: 700; color: #F5D061; cursor: pointer; font-family: 'Poppins',sans-serif; }

/* ── GUIDE ── */
.guide-box { margin: 10px 15px 0; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; padding: 14px; }
.gb-title { font-size: 10px; font-weight: 800; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; }
.gb-item { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 8px; }
.gb-item:last-child { margin-bottom: 0; }
.gb-num { width: 18px; height: 18px; background: rgba(197,147,39,0.15); color: #F5D061; border-radius: 5px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 9px; font-weight: 800; }
.gb-text { font-size: 10.5px; color: rgba(255,255,255,0.5); line-height: 1.5; font-weight: 500; }

/* ── CTA BUTTON ── */
.cta-wrap { padding: 15px 15px 0; }
.btn-pay { width: 100%; padding: 16px; border: none; border-radius: 14px; font-size: 14px; font-weight: 800; font-family: 'Poppins',sans-serif; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: 0.15s; background: linear-gradient(135deg,#C59327,#F5D061); color: #111; box-shadow: 0 8px 24px rgba(197,147,39,0.3); }
.btn-pay:active { transform: scale(0.97); }
.btn-pay:disabled { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.3); box-shadow: none; cursor: not-allowed; }
.btn-pay i { font-size: 16px; }

/* ── SUCCESS OVERLAY ── */
.suc-ov { position: fixed; inset: 0; z-index: 9999; background: #0A0A0A; display: none; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 32px; }
.suc-ov.show { display: flex; animation: soIn 0.4s cubic-bezier(.22,1,.36,1); }
@keyframes soIn { from{opacity:0;transform:scale(0.9)} to{opacity:1;transform:scale(1)} }
.suc-ring { width: 90px; height: 90px; border-radius: 50%; background: rgba(16,185,129,0.1); border: 2px solid #10B981; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
.suc-ring i { font-size: 36px; color: #10B981; }
.suc-title { font-size: 22px; font-weight: 800; color: #fff; }
.suc-sub { font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 6px; }
.suc-amt { font-size: 34px; font-weight: 800; color: #F5D061; margin: 8px 0; }

/* ── TOAST ── */
.toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(20px); background: #1a1a1a; border: 1px solid rgba(197,147,39,0.3); color: #F5D061; font-size: 11.5px; font-weight: 700; padding: 10px 18px; border-radius: 30px; opacity: 0; transition: 0.3s; z-index: 99999; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.4); pointer-events: none; }
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
.toast i { font-size: 12px; }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="page">

  <!-- HEADER -->
  <div class="topbar">
    <a href="javascript:history.back()" class="tb-back"><i class="fa-solid fa-chevron-left"></i></a>
    <div class="tb-title">Pembayaran</div>
  </div>

<?php if ($status === 'Success'): ?>
  <!-- SUCCESS -->
  <div class="result-wrap">
    <div class="result-ic ok"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
    <div class="result-title">Pembayaran Berhasil!</div>
    <div class="result-amount">Rp<?= number_format($amount,0,',','.') ?></div>
    <div class="result-sub">Saldo berhasil ditambahkan ke akun Anda.</div>
    <a href="<?= base_url('pages/history') ?>" class="result-btn"><i class="fa-solid fa-clock-rotate-left"></i> Lihat Riwayat</a>
    <a href="<?= base_url('') ?>" class="result-btn sec"><i class="fa-solid fa-house"></i> Beranda</a>
  </div>

<?php elseif ($status !== 'Pending'): ?>
  <!-- EXPIRED/FAILED -->
  <div class="result-wrap">
    <div class="result-ic err"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
    <div class="result-title">Transaksi Gagal</div>
    <div class="result-sub">Transaksi ini telah kedaluwarsa atau dibatalkan oleh sistem.</div>
    <a href="<?= base_url('pages/deposit') ?>" class="result-btn"><i class="fa-solid fa-rotate-right"></i> Coba Lagi</a>
  </div>

<?php else: ?>

  <!-- AMOUNT HERO -->
  <div class="amount-hero">
    <div class="ah-label">Total Tagihan</div>
    <div class="ah-amount">Rp<?= number_format($amount,0,',','.') ?></div>
    <div class="ah-badge">
      <i class="fa-solid fa-circle-dot"></i>
      Menunggu Pembayaran
    </div>
  </div>

  <!-- TIMER -->
  <div class="timer-strip">
    <div class="ts-left"><i class="fa-solid fa-hourglass-half"></i> Selesaikan pembayaran sebelum</div>
    <span id="countdown">--:--</span>
  </div>

  <?php if ($is_qris && ($qris_img || $qris_url)): ?>
  <!-- QR CODE -->
  <div class="qr-section">
    <div class="qs-head"><i class="fa-solid fa-qrcode"></i><span>Scan QRIS</span></div>
    <div class="qr-canvas">
      <div class="qr-frame" onclick="openQRModal()" title="Klik untuk perbesar">
        <img id="qris_image" src="<?= base_url('pages/pay') ?>?action=qr_image&trxid=<?= urlencode($trxtopup) ?>" alt="QRIS" onerror="this.parentNode.innerHTML='<div style=\'text-align:center;font-size:11px;color:rgba(255,255,255,0.3);padding-top:80px\'>Gagal memuat QR</div>'">
      </div>
      <button class="btn-dl" onclick="downloadQR()">
        <i class="fa-solid fa-download"></i> Simpan QR Code
      </button>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!$is_qris && $rek_number): ?>
  <!-- REKENING BOX -->
  <div class="rek-box">
    <div class="rb-label"><?= htmlspecialchars($method) ?> — No. Rekening</div>
    <div class="rb-rek"><?= htmlspecialchars($rek_number) ?></div>
    <?php if ($rek_owner): ?><div class="rb-name">a.n <?= htmlspecialchars($rek_owner) ?></div><?php endif; ?>
    <button class="rb-copy" onclick="cp('<?= htmlspecialchars($rek_number, ENT_QUOTES) ?>', 'No Rekening disalin!')">
      <i class="fa-solid fa-copy"></i> Salin Nomor Rekening
    </button>
  </div>
  <?php endif; ?>

  <!-- DETAIL -->
  <div class="detail-box">
    <div class="db-head"><i class="fa-solid fa-receipt"></i><span>Detail Transaksi</span></div>
    <div class="d-row">
      <div class="d-lbl">Metode</div>
      <div class="d-val gold"><?= htmlspecialchars($method) ?></div>
    </div>
    <?php if (!$is_qris && $unique_code > 0): ?>
    <div class="d-row">
      <div class="d-lbl">Kode Unik</div>
      <div class="d-val gold">+<?= $unique_code ?></div>
    </div>
    <?php endif; ?>
    <div class="d-row">
      <div class="d-lbl">No. Referensi</div>
      <div class="d-val">
        <?= htmlspecialchars($trxtopup) ?>
        <button class="btn-cp" onclick="cp('<?= htmlspecialchars($trxtopup, ENT_QUOTES) ?>', 'Referensi disalin!')"><i class="fa-regular fa-copy"></i></button>
      </div>
    </div>
  </div>

  <!-- PANDUAN -->
  <div class="guide-box">
    <div class="gb-title">Panduan Pembayaran</div>
    <?php if ($is_qris): ?>
    <div class="gb-item"><div class="gb-num">1</div><div class="gb-text">Buka aplikasi e-Wallet atau m-Banking Anda.</div></div>
    <div class="gb-item"><div class="gb-num">2</div><div class="gb-text">Scan QRIS di atas atau gunakan gambar yang sudah disimpan.</div></div>
    <div class="gb-item"><div class="gb-num">3</div><div class="gb-text">Pastikan nominal tepat, lalu konfirmasi pembayaran.</div></div>
    <?php else: ?>
    <div class="gb-item"><div class="gb-num">1</div><div class="gb-text">Transfer ke rekening yang tertera menggunakan ATM atau m-Banking.</div></div>
    <div class="gb-item"><div class="gb-num">2</div><div class="gb-text">Masukkan nominal secara tepat termasuk 3 digit kode unik.</div></div>
    <div class="gb-item"><div class="gb-num">3</div><div class="gb-text">Tekan tombol di bawah setelah transfer selesai.</div></div>
    <?php if ($bank_note): ?><div class="gb-item"><div class="gb-num">!</div><div class="gb-text"><?= htmlspecialchars($bank_note) ?></div></div><?php endif; ?>
    <?php endif; ?>
  </div>

<?php endif; ?>
</div>

<!-- SUCCESS OVERLAY -->
<div class="suc-ov" id="suc_ov">
  <div class="suc-ring"><i class="fa-solid fa-check"></i></div>
  <div class="suc-title">Pembayaran Sukses!</div>
  <div class="suc-sub">Saldo akun berhasil ditambahkan</div>
  <div class="suc-amt" id="suc_amount">Rp<?= number_format($amount,0,',','.') ?></div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"><i class="fa-solid fa-check"></i><span id="toastMsg"></span></div>

<!-- QR FULLSCREEN MODAL -->
<div id="qrModal" style="display:none;position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,.9);align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(8px);" onclick="closeQRModal()">
  <div style="max-width:320px;width:100%;background:#111;border:1px solid rgba(197,147,39,0.3);border-radius:18px;padding:16px;position:relative;" onclick="event.stopPropagation()">
    <button onclick="closeQRModal()" style="position:absolute;top:10px;right:10px;background:rgba(255,255,255,0.08);border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;"><i class="fa-solid fa-xmark"></i></button>
    <img id="qrModalImg" src="" alt="QRIS" style="width:100%;border-radius:10px;display:block;">
    <div style="text-align:center;margin-top:10px;font-size:10.5px;color:rgba(255,255,255,0.4);font-family:'Poppins',sans-serif;">Sentuh area luar untuk menutup</div>
  </div>
</div>

<script>
const TRX_ID     = <?= json_encode($trxtopup) ?>;
const EXPIRED_TS = <?= $expired_ts * 1000 ?>;
const INIT_ST    = <?= json_encode($status) ?>;
const IS_MANUAL  = <?= $is_manual ? 'true' : 'false' ?>;

let poll = null, stopped = false;

(function() {
  let el = document.getElementById('countdown');
  if (!el) return;
  function tick() {
    let rem = Math.max(0, Math.floor((EXPIRED_TS - Date.now()) / 1000));
    let m = String(Math.floor(rem/60)).padStart(2,'0');
    let s = String(rem%60).padStart(2,'0');
    el.textContent = m+':'+s;
    if (rem === 0) { el.style.color='#ef4444'; return; }
    el.style.color = rem < 60 ? '#ef4444' : '#F5D061';
    setTimeout(tick, 1000);
  }
  tick();
})();

function openQRModal() { let img=document.getElementById('qris_image'); let modal=document.getElementById('qrModal'); let mimg=document.getElementById('qrModalImg'); if(!img||!modal)return; mimg.src=img.src; modal.style.display='flex'; document.body.style.overflow='hidden'; }
function closeQRModal() { document.getElementById('qrModal').style.display='none'; document.body.style.overflow=''; }
function downloadQR() { let url=<?= json_encode(base_url('pages/pay').'?action=qr_image&trxid='.urlencode($trxtopup)) ?>; let link=document.createElement('a'); link.href=url; link.download='QRIS_'+TRX_ID+'.png'; document.body.appendChild(link); link.click(); link.remove(); showToast('QR berhasil diunduh'); }
</script>
</body>
</html>
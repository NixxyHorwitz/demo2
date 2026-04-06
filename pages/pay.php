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
body { font-family: 'Poppins', sans-serif; background: #012b26; color: #fff; min-height: 100vh; -webkit-font-smoothing: antialiased; padding-bottom: 80px; }
.page { max-width: 480px; margin: 0 auto; min-height: 100vh; }

/* HEADER CURVED */
.h-bg { background: linear-gradient(135deg, #023e35 0%, #01312b 100%); padding: 25px 20px 90px; border-bottom-left-radius: 40px; border-bottom-right-radius: 40px; position: relative; box-shadow: 0 8px 25px rgba(0,0,0,0.3); z-index: 1; }
.h-nav { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; }
.back-btn { width: 36px; height: 36px; border-radius: 12px; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none; border: 1px solid rgba(255,255,255,0.1); transition: 0.2s; }
.h-title { display: flex; flex-direction: column; }
.h-title h3 { font-size: 16px; font-weight: 800; color: #fff; line-height: 1.2; }

/* ── SUCCESS/FAIL STATE ── */
.result-wrap { margin: -40px 20px 20px; padding: 40px 20px; text-align: center; background: #023e35; border-radius: 20px; border: 1px solid rgba(255,255,255,0.05); position: relative; z-index: 2; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
.result-ic { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; }
.result-ic.ok  { background: rgba(52, 211, 153, 0.15); border: 2px solid #34d399; color: #34d399; }
.result-ic.err { background: rgba(2ef, 68, 68, 0.15); border: 2px solid #fca5a5; color: #fca5a5; }
.result-ic i { font-size: 36px; }
.result-title  { font-size: 18px; font-weight: 800; color: #fff; }
.result-amount { font-size: 28px; font-weight: 800; color: #facc15; margin-top: 8px; font-family: monospace;}
.result-sub    { font-size: 11px; color: rgba(255,255,255,0.6); margin-top: 12px; line-height: 1.5; padding: 0 10px;}
.result-btn { margin-top: 24px; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px; border-radius: 14px; background: #facc15; color: #012b26; text-decoration: none; font-size: 13px; font-weight: 800; }
.result-btn.sec { background: rgba(255,255,255,0.05); color: #fff; margin-top: 10px; border: 1px solid rgba(255,255,255,0.1); }

/* ── AMOUNT HERO ── */
.amount-hero {
    margin: -40px 20px 20px;
    background: rgba(250, 204, 21, 0.05); border: 1px solid rgba(250, 204, 21, 0.2); border-radius: 20px; padding: 24px; text-align: center;
    backdrop-filter: blur(10px); position: relative; z-index: 2; box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}
.ah-label { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
.ah-amount { font-size: 32px; font-weight: 800; color: #facc15; font-family: monospace; letter-spacing: -1px; }

/* ── COUNTDOWN ── */
.timer-box { margin: 0 20px 16px; background: #023e35; border: 1px dashed rgba(250,204,21,0.4); border-radius: 14px; padding: 14px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; }
.timer-box p { font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.6); }
#countdown { font-size: 20px; font-weight: 800; color: #facc15; font-family: monospace; letter-spacing: 2px;}

/* ── QR SECTION ── */
.qr-section { margin: 0 20px 16px; background: #023e35; border-radius: 16px; overflow: hidden; padding: 20px; display: flex; flex-direction: column; align-items: center; border: 1px solid #035246;}
.qs-title { font-size: 11px; font-weight: 800; color: #fff; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 1px;}
.qr-frame { width: 220px; height: 220px; background: #fff; border-radius: 16px; padding: 12px; border: 4px solid #facc15; margin-bottom: 20px; cursor: pointer; }
.qr-frame img { width: 100%; height: 100%; object-fit: contain; }
.btn-dl { width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; background: rgba(250, 204, 21, 0.1); border: 1px solid rgba(250, 204, 21, 0.3); color: #facc15; border-radius: 12px; padding: 12px; font-size: 12px; font-weight: 700; cursor: pointer; }

/* ── MANUAL REKENING ── */
.rek-box { margin: 0 20px 16px; background: #023e35; border: 1px solid rgba(250,204,21,0.2); border-radius: 16px; padding: 16px; text-align: center;}
.rb-label { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
.rb-rek { font-size: 24px; font-weight: 800; color: #facc15; font-family: monospace; letter-spacing: 2px; }
.rb-name { font-size: 11px; font-weight: 600; color: #fff; margin-top: 4px; margin-bottom: 16px; }
.rb-copy { width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 12px; font-size: 12px; font-weight: 700; color: #fff; cursor: pointer; }

/* ── INFO BOXES ── */
.info-wrap { margin: 0 20px 20px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 16px; }
.i-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed rgba(255,255,255,0.08); }
.i-row:first-child { padding-top: 0; }
.i-row:last-child { padding-bottom: 0; border-bottom: none; }
.i-lbl { font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.5); }
.i-val { font-size: 13px; font-weight: 800; color: #fff; font-family: monospace; display: flex; align-items: center; gap: 8px;}
.i-val.gold { color: #facc15; }
.btn-cp { width: 24px; height: 24px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #fff; font-size: 10px;}

/* ── GUIDE BOX ── */
.guide-box { margin: 0 20px 20px; }
.gb-title { font-size: 11px; font-weight: 800; color: #facc15; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; }
.gb-item { display: flex; gap: 10px; align-items: flex-start; margin-bottom: 10px; }
.gb-num { width: 20px; height: 20px; background: rgba(250, 204, 21, 0.1); color: #facc15; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 10px; font-weight: 800; }
.gb-text { font-size: 11px; color: rgba(255,255,255,0.6); line-height: 1.5; font-weight: 500; }
.gb-text b { color: #fff;}

/* TOAST */
.toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(20px); background: #023e35; border: 1px solid rgba(250,204,21,0.5); color: #facc15; font-size: 11.5px; font-weight: 700; padding: 10px 18px; border-radius: 30px; opacity: 0; transition: 0.3s; z-index: 99999; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.5); pointer-events: none; }
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<div class="page">

  <!-- HEADER CURVED -->
  <div class="h-bg">
    <div class="h-nav">
      <a href="javascript:history.back()" class="back-btn"><i class="fa-solid fa-chevron-left"></i></a>
      <div class="h-title">
        <h3>Pembayaran</h3>
      </div>
    </div>
  </div>

<?php if ($status === 'Success'): ?>
  <!-- SUCCESS -->
  <div class="result-wrap">
    <div class="result-ic ok"><i class="fa-solid fa-check"></i></div>
    <div class="result-title">Top Up Berhasil!</div>
    <div class="result-amount">Rp<?= number_format($amount,0,',','.') ?></div>
    <div class="result-sub">Dana telah masuk ke saldo akun Anda. Nikmati kemudahan berinvestasi.</div>
    <a href="<?= base_url('pages/history') ?>" class="result-btn"><i class="fa-solid fa-clock-rotate-left"></i> Cek Riwayat</a>
    <a href="<?= base_url('') ?>" class="result-btn sec"><i class="fa-solid fa-house"></i> Kembali ke Dashboard</a>
  </div>

<?php elseif ($status !== 'Pending'): ?>
  <!-- EXPIRED/FAILED -->
  <div class="result-wrap">
    <div class="result-ic err"><i class="fa-solid fa-xmark"></i></div>
    <div class="result-title">Transaksi Batal</div>
    <div class="result-sub">Sesi tagihan ini telah kedaluwarsa atau dibatalkan. Silakan buat tiket baru jika masih ingin top up.</div>
    <a href="<?= base_url('pages/deposit') ?>" class="result-btn"><i class="fa-solid fa-rotate-right"></i> Coba Top Up Lagi</a>
  </div>

<?php else: ?>

  <!-- AMOUNT HERO -->
  <div class="amount-hero">
    <div class="ah-label">TOTAL PEMBAYARAN</div>
    <div class="ah-amount">Rp<?= number_format($amount,0,',','.') ?></div>
    <div style="display:inline-flex; align-items:center; gap:6px; margin-top:8px; padding: 4px 12px; background: rgba(52, 211, 153, 0.1); border: 1px solid rgba(52, 211, 153, 0.3); border-radius: 20px; font-size: 9px; font-weight: 800; color: #34d399; text-transform: uppercase;">
       <i class="fa-solid fa-spinner fa-spin"></i> Menunggu...
    </div>
  </div>

  <!-- TIMER -->
  <div class="timer-box">
    <p>Selesaikan pembayaran sebelum waku habis:</p>
    <div id="countdown">--:--</div>
  </div>

  <?php if ($is_qris && ($qris_img || $qris_url)): ?>
  <!-- QR SECTION -->
  <div class="qr-section">
    <div class="qs-title"><i class="fa-solid fa-qrcode" style="margin-right:6px;"></i> Scan Barcode QRIS</div>
    <div class="qr-frame" onclick="openQRModal()" title="Klik untuk perbesar">
      <img id="qris_image" src="<?= base_url('pages/pay') ?>?action=qr_image&trxid=<?= urlencode($trxtopup) ?>" alt="QRIS" onerror="this.parentNode.innerHTML='<div style=\'text-align:center;font-size:11px;color:rgba(255,255,255,0.3);padding-top:80px\'>Gagal memuat QR</div>'">
    </div>
    <button class="btn-dl" onclick="downloadQR()">
      <i class="fa-solid fa-download"></i> Simpan ke Galeri
    </button>
  </div>
  <?php endif; ?>

  <?php if (!$is_qris && $rek_number): ?>
  <!-- REKENING BOX -->
  <div class="rek-box">
    <div class="rb-label"><?= htmlspecialchars($method) ?> — Nomor Rekening Tujuan</div>
    <div class="rb-rek"><?= htmlspecialchars($rek_number) ?></div>
    <?php if ($rek_owner): ?><div class="rb-name">a.n <?= htmlspecialchars($rek_owner) ?></div><?php endif; ?>
    <button class="rb-copy" onclick="cp('<?= htmlspecialchars($rek_number, ENT_QUOTES) ?>', 'Nomor Rekening disalin!')">
      <i class="fa-solid fa-copy"></i> Salin Nomor Tujuan
    </button>
  </div>
  <?php endif; ?>

  <!-- DETAIL INFO BOX -->
  <div class="info-wrap">
    <div class="i-row">
      <div class="i-lbl">Metode Bayar</div>
      <div class="i-val gold"><?= htmlspecialchars($method) ?></div>
    </div>
    <?php if (!$is_qris && $unique_code > 0): ?>
    <div class="i-row">
      <div class="i-lbl">Kode Unik Transfer</div>
      <div class="i-val gold" style="color:#34d399;">+<?= $unique_code ?></div>
    </div>
    <?php endif; ?>
    <div class="i-row">
      <div class="i-lbl">Nomor Referensi</div>
      <div class="i-val">
        <span><?= htmlspecialchars($trxtopup) ?></span>
        <button class="btn-cp" onclick="cp('<?= htmlspecialchars($trxtopup, ENT_QUOTES) ?>', 'Referensi disalin!')"><i class="fa-solid fa-copy"></i></button>
      </div>
    </div>
  </div>

  <!-- PANDUAN BOX -->
  <div class="guide-box">
    <div class="gb-title"><i class="fa-solid fa-book-open" style="margin-right:6px;"></i> Cara Pembayaran</div>
    <?php if ($is_qris): ?>
    <div class="gb-item"><div class="gb-num">1</div><div class="gb-text">Buka aplikasi dompet digital / e-Wallet kesayangan Anda.</div></div>
    <div class="gb-item"><div class="gb-num">2</div><div class="gb-text">Ketuk opsi <b>Scan QRIS</b> lalu pindai barcode di atas atau upload dari galeri Anda.</div></div>
    <div class="gb-item"><div class="gb-num">3</div><div class="gb-text">Periksa kembali nominal yang tertera, jika sesuai tekan Konfirmasi Bayar.</div></div>
    <?php else: ?>
    <div class="gb-item"><div class="gb-num">1</div><div class="gb-text">Buka aplikasi m-Banking atau kunjungi ATM terdekat.</div></div>
    <div class="gb-item"><div class="gb-num">2</div><div class="gb-text">Lakukan transfer persis ke <b>Nomor Rekening Tujuan</b> yang tertera di layar Anda.</div></div>
    <div class="gb-item"><div class="gb-num">3</div><div class="gb-text">Ketik nominal transfer dengan tepat <b>termasuk 3 angka unik</b> di belakangnya agar terverifikasi instan.</div></div>
    <?php if ($bank_note): ?><div class="gb-item"><div class="gb-num">!</div><div class="gb-text text-yellow-400"><?= htmlspecialchars($bank_note) ?></div></div><?php endif; ?>
    <?php endif; ?>
  </div>

<?php endif; ?>
</div>

<!-- TOAST -->
<div class="toast" id="toast"><i class="fa-solid fa-check-circle"></i><span id="toastMsg"></span></div>

<!-- FULLSCREEN QR MODAL -->
<div id="qrModal" style="display:none;position:fixed;inset:0;z-index:9998;background:rgba(1, 43, 38, 0.9);align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(8px);" onclick="closeQRModal()">
  <div style="max-width:320px;width:100%;background:#023e35;border:1px solid #facc15;border-radius:24px;padding:20px;position:relative;" onclick="event.stopPropagation()">
    <button onclick="closeQRModal()" style="position:absolute;top:10px;right:10px;background:rgba(250, 204, 21, 0.2);border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;color:#facc15;font-size:16px;"><i class="fa-solid fa-xmark"></i></button>
    <img id="qrModalImg" src="" alt="QRIS" style="width:100%;border-radius:12px;display:block;">
    <div style="text-align:center;margin-top:16px;font-size:11px;color:rgba(255,255,255,0.6);font-weight:600;">Sentuh area luar untuk menutup</div>
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
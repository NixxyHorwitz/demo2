<?php
require '../mainconfig.php';
require '../lib/check_session.php';
require '../lib/is_login.php';
require_once __DIR__ . '/../lib/flash_message.php';

$sq        = mysqli_query($db, "SELECT * FROM settings LIMIT 1");
$settings  = $sq ? mysqli_fetch_assoc($sq) : [];
$site_name = $settings['title']       ?? 'Solusi Keuangan';
$web_logo  = $settings['web_logo']    ?? '';
$tg_link   = $settings['link_telegram'] ?? '#';
$min_wd    = (int)($settings['min_wd']   ?? 50000);
$min_depo  = (int)($settings['min_depo'] ?? 50000);
$fee_pct   = (float)($settings['bonus_checkin'] ?? 0);

require '../lib/header_user.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1">
<title>Panduan & Layanan – <?= htmlspecialchars($site_name) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Poppins', sans-serif; background: #f9fafb; color: #111827; -webkit-font-smoothing: antialiased; }
  .app { max-width: 480px; margin: 0 auto; min-height: 100vh; background: #f9fafb; padding-bottom: 80px; position: relative; }

  /* HEADER */
  .th-container { padding: 16px 20px 24px; display: flex; align-items: center; justify-content: space-between; }
  .th-back { color: #111827; text-decoration: none; display: flex; align-items: center; justify-content: center; width: 34px; height: 34px; background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); }
  .th-back svg { width: 22px; height: 22px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; }
  .th-title { font-size: 16px; font-weight: 700; flex: 1; text-align: center; margin: 0 10px; }

  /* PROFILE CARD */
  .p-card { margin: 0 16px 40px; background: linear-gradient(135deg, #a34e94, #7a3a6f); color: #fff; border-radius: 30px 10px 30px 10px; padding: 24px 24px 44px; box-shadow: 0 10px 25px rgba(163, 78, 148, 0.4); display: flex; align-items: flex-start; gap: 16px; position: relative; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); }
  .p-card::before { content:''; position:absolute; top:-40px; right:-20px; width:120px; height:120px; background:rgba(255,255,255,0.1); border-radius:50%; }
  .p-card::after { content:''; position:absolute; bottom:-40px; left:-20px; width:100px; height:100px; background:rgba(255,255,255,0.05); border-radius:50%; }
  .pc-img { width: 56px; height: 56px; background: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #a34e94; font-weight: bold; z-index: 1; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border: 2px solid #fff; flex-shrink: 0; }
  .pc-img img { width: 100%; height: 100%; object-fit: contain; }
  .pc-info { flex: 1; z-index: 1; }
  .pc-name { font-size: 19px; font-weight: 800; line-height: 1.2; margin-bottom: 4px; letter-spacing: -0.5px; }
  .pc-ver { font-size: 11px; font-weight: 600; opacity: 0.9; display: flex; align-items: center; gap: 6px; }
  .pc-st { text-align: right; z-index: 1; }
  .pc-st-val { font-size: 10px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px; color: #fff; background: rgba(0,0,0,0.2); padding: 6px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; backdrop-filter: blur(4px); }
  .pc-st-val i { font-size: 8px; color: #10b981; }

  /* OVERLAPPING STATS STRIP */
  .s-strip { display: flex; margin: -60px 24px 24px; background: #fff; border-radius: 20px; box-shadow: 0 8px 30px rgba(0,0,0,0.08); position: relative; z-index: 10; padding: 16px 0; border: 1px solid #f3f4f6; }
  .ss-bx { flex: 1; text-align: center; border-right: 1px dashed #e5e7eb; padding: 0 8px; }
  .ss-bx:last-child { border-right: none; }
  .ss-ic { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; margin: 0 auto 8px; }
  .ss-ic.depo { background: #ecfdf5; color: #10b981; }
  .ss-ic.wd { background: #fef2f2; color: #ef4444; }
  .ss-ic.chk { background: #fffbeb; color: #f59e0b; }
  .ss-val { font-size: 13px; font-weight: 800; color: #111827; margin-bottom: 2px; }
  .ss-lbl { font-size: 9.5px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }

  /* SECTION TITLE */
  .sec-title { font-size: 14px; font-weight: 800; color: #111827; margin: 0 20px 14px; display: flex; align-items: center; gap: 8px; }
  .sec-title i { color: #a34e94; }

  /* QUICK LINKS */
  .q-grid { display: flex; gap: 12px; margin: 0 0 24px 20px; overflow-x: auto; padding-bottom: 8px; scrollbar-width: none; }
  .q-grid::-webkit-scrollbar { display: none; }
  .q-bx { min-width: 150px; background: #fff; padding: 14px 16px; border-radius: 24px; border: 1px solid #f3f4f6; display: flex; align-items: center; gap: 12px; text-decoration: none; color: inherit; box-shadow: 0 4px 15px rgba(0,0,0,0.02); transition: 0.15s; flex-shrink: 0; }
  .q-bx:active { transform: scale(0.96); }
  .q-ic { width: 38px; height: 38px; border-radius: 50%; background: #fdf4f9; color: #a34e94; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
  .q-bx-t { font-size: 13.5px; font-weight: 800; color: #111827; margin-bottom: 2px; }
  .q-bx-s { font-size: 10.5px; font-weight: 600; color: #9ca3af; }

  /* FAQ CARD */
  .faq-wrap { margin: 0 20px 24px; display: flex; flex-direction: column; gap: 12px; }
  .f-item { background: #fff; border-radius: 16px; border: 1px solid #f3f4f6; box-shadow: 0 4px 15px rgba(0,0,0,0.02); overflow: hidden; }
  .f-hdr { padding: 16px; display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 13px; color: #111827; cursor: pointer; gap: 10px; }
  .f-hdr i { color: #9ca3af; transition: 0.3s; }
  .f-item.open .f-hdr i { transform: rotate(180deg); color: #a34e94; }
  .f-body { display: none; padding: 0 16px 16px; font-size: 12px; line-height: 1.6; color: #4b5563; font-weight: 500; }
  .f-item.open .f-body { display: block; }
  .f-body b { color: #111827; font-weight: 700; }

  /* CONTACT CARD */
  .c-card { background: #fff; border-radius: 16px; margin: 0 20px 24px; padding: 20px; border: 1px solid #f3f4f6; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
  .c-row { display: flex; align-items: center; gap: 14px; text-decoration: none; color: inherit; padding: 14px 0; border-bottom: 1px dashed #f3f4f6; }
  .c-row:last-child { border-bottom: none; padding-bottom: 0; }
  .c-row:first-child { padding-top: 0; }
  .c-ic { width: 44px; height: 44px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
  .c-ic.tg { background: #e0f2fe; color: #0ea5e9; }
  .c-ic.tm { background: #fffbeb; color: #f59e0b; }
  .c-info { flex: 1; }
  .c-name { font-size: 13.5px; font-weight: 800; color: #111827; margin-bottom: 3px; }
  .c-sub { font-size: 11px; font-weight: 500; color: #6b7280; }
  .c-arr { color: #9ca3af; font-size: 14px; }
  .c-st { font-size: 10px; font-weight: 800; color: #10b981; background: #ecfdf5; padding: 4px 8px; border-radius: 8px; }
</style>
</head>
<body>
<div class="app">

  <!-- HEADER -->
  <div class="th-container">
      <a href="<?= base_url('pages/profile') ?>" class="th-back">
          <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
      </a>
      <div class="th-title">Pusat Layanan</div>
      <div style="width:34px;"></div> <!-- Spacer -->
  </div>

  <!-- PROFILE CARD -->
  <div class="p-card">
    <div class="pc-img">
      <?php if(!empty($web_logo)): ?>
        <img src="<?= base_url('assets/images/'.htmlspecialchars($web_logo)) ?>" alt="Logo">
      <?php else: ?>
        <i class="fa-solid fa-chart-pie"></i>
      <?php endif; ?>
    </div>
    <div class="pc-info">
      <div class="pc-name"><?= htmlspecialchars($site_name) ?></div>
      <div class="pc-ver"><i class="fa-regular fa-circle-check"></i> Platform Resmi</div>
    </div>
    <div class="pc-st">
      <div class="pc-st-val"><i class="fa-solid fa-circle"></i> AKTIF</div>
    </div>
  </div>

  <!-- STATS STRIP -->
  <div class="s-strip">
    <div class="ss-bx">
      <div class="ss-ic depo"><i class="fa-solid fa-wallet"></i></div>
      <div class="ss-val">Rp <?= number_format($min_depo,0,',','.') ?></div>
      <div class="ss-lbl">Min. Deposit</div>
    </div>
    <div class="ss-bx">
      <div class="ss-ic wd"><i class="fa-solid fa-money-bill-transfer"></i></div>
      <div class="ss-val">Rp <?= number_format($min_wd,0,',','.') ?></div>
      <div class="ss-lbl">Min. Tarik</div>
    </div>
    <div class="ss-bx">
      <div class="ss-ic chk"><i class="fa-solid fa-gifts"></i></div>
      <div class="ss-val">Rp <?= number_format($fee_pct,0,',','.') ?></div>
      <div class="ss-lbl">Bonus Harian</div>
    </div>
  </div>

  <!-- QUICK LINKS -->
  <div class="sec-title"><i class="fa-solid fa-bolt"></i> Akses Cepat</div>
  <div class="q-grid">
    <a href="<?= base_url('pages/deposit') ?>" class="q-bx">
      <div class="q-ic"><i class="fa-solid fa-building-columns"></i></div>
      <div>
        <div class="q-bx-t">Isi Saldo</div>
        <div class="q-bx-s">Tambah Dana</div>
      </div>
    </a>
    <a href="<?= base_url('pages/withdraw') ?>" class="q-bx">
      <div class="q-ic"><i class="fa-solid fa-money-bill-wave"></i></div>
      <div>
        <div class="q-bx-t">Penarikan</div>
        <div class="q-bx-s">Tarik Saldo</div>
      </div>
    </a>
    <a href="<?= base_url('pages/plans') ?>" class="q-bx">
      <div class="q-ic"><i class="fa-solid fa-chart-line"></i></div>
      <div>
        <div class="q-bx-t">Investasi</div>
        <div class="q-bx-s">Beli Produk</div>
      </div>
    </a>
    <a href="<?= base_url('pages/agent') ?>" class="q-bx">
      <div class="q-ic"><i class="fa-solid fa-user-group"></i></div>
      <div>
        <div class="q-bx-t">Referral</div>
        <div class="q-bx-s">Ajak Teman</div>
      </div>
    </a>
  </div>

  <!-- FAQ -->
  <div class="sec-title"><i class="fa-solid fa-book-open"></i> Panduan Pengguna</div>
  <div class="faq-wrap">
    <?php
    $faqs = [
      [
        'q' => 'Bagaimana Cara Melakukan Deposit?',
        'a' => 'Pilih menu <b>Deposit</b> pada halaman utama, tentukan nominal, lalu pilih metode pembayaran yang tersedia. Saldo akan masuk secara otomatis setelah pembayaran terverifikasi.'
      ],
      [
        'q' => 'Berapa Lama Proses Penarikan Dana?',
        'a' => 'Proses penarikan dana biasanya membutuhkan waktu <b>10–30 menit</b> pada jam kerja operasional. Pastikan data rekening Anda valid dan masih aktif.'
      ],
      [
        'q' => 'Mengapa Ada Potongan Biaya Admin?',
        'a' => 'Potongan biaya admin (tax) digunakan sebagai <b>biaya administrasi server</b> dan pemeliharaan sistem agar transaksi selalu lancar tanpa kendala.'
      ],
      [
        'q' => 'Bagaimana Sistem Komisi Bekerja?',
        'a' => 'Bagikan <b>link referral</b> Anda. Setiap teman yang mendaftar dan berinvestasi melalui tautan Anda, komisi bonus akan langsung otomatis dijumlahkan ke saldo akun Anda.'
      ]
    ];
    foreach ($faqs as $i => $faq):
    ?>
    <div class="f-item">
      <div class="f-hdr" onclick="toggleFaq(this)">
        <div><?= $i+1 ?>. <?= htmlspecialchars($faq['q']) ?></div>
        <i class="fa-solid fa-chevron-down"></i>
      </div>
      <div class="f-body">
        <?= $faq['a'] ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- HUBUNGI KAMI -->
  <div class="sec-title"><i class="fa-solid fa-headset"></i> Layanan Pelanggan</div>
  <div class="c-card">
    <a href="<?= htmlspecialchars($tg_link) ?>" target="_blank" class="c-row">
      <div class="c-ic tg"><i class="fa-brands fa-telegram"></i></div>
      <div class="c-info">
        <div class="c-name">Grup Telegram</div>
        <div class="c-sub">Bantuan cepat via admin resmi</div>
      </div>
      <div class="c-arr"><i class="fa-solid fa-chevron-right"></i></div>
    </a>
    <div class="c-row">
      <div class="c-ic tm"><i class="fa-regular fa-clock"></i></div>
      <div class="c-info">
        <div class="c-name">Jam Operasional</div>
        <div class="c-sub">Setiap Hari, 09:00 - 20:30 WIB</div>
      </div>
      <div class="c-st">AKTIF</div>
    </div>
  </div>

</div>

<script>
function toggleFaq(trigger) {
  const item = trigger.closest('.f-item');
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.f-item.open').forEach(f => f.classList.remove('open'));
  if (!isOpen) item.classList.add('open');
}
</script>

<?php require '../lib/footer_user.php'; ?>
</body>
</html>
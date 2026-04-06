<?php
/* ╔══════════════════════════════════════════════════════════════╗
 * ║  babikode/service/ — Manajemen Produk             ║
 * ╚══════════════════════════════════════════════════════════════╝ */

require '../../mainconfig.php';
require '../../lib/check_session_admin.php';

$page_name = 'Data Produk';
$aksi      = trim($_GET['action'] ?? $_POST['action'] ?? '');

/* ══════════════════════════════════════════════════════════════
   WRITE — ubah data lalu redirect
   ══════════════════════════════════════════════════════════════ */

// PRODUK: simpan
if ($aksi === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nm   = protect(trim($_POST['nama_produk'] ?? ''));
    $kid  = (int)($_POST['kategori_id'] ?? 0);
    $hrg  = (int)($_POST['harga']       ?? 0);
    $pct  = (float)($_POST['persentase'] ?? 0);
    $msa  = (int)($_POST['masa_aktif']  ?? 0);
    $mxb  = (int)($_POST['max_buy']     ?? 5);
    $tb   = (int)($_POST['to_buy']      ?? 0);

    $ph = (int)round($hrg * $pct / 100);
    $tp = $ph * $msa;

    if (!$nm || !$kid || !$hrg || !$pct || !$msa) {
        $_SESSION['result'] = ['response'=>'error','title'=>'Gagal!','msg'=>'Semua field wajib diisi.'];
    } else {
        $kat    = mysqli_fetch_assoc(mysqli_query($db,
                  "SELECT is_locked,syarat FROM produk_kategori WHERE id='$kid'"));
        $lck    = ($kat && $kat['is_locked']) ? 1 : 0;
        $tb_sql = ($kat && $kat['syarat'] == 0 && $tb > 0) ? $tb : 'NULL';

        $db->query("INSERT INTO produk_investasi
                       (nama_produk,kategori_id,is_locked,persentase,max_buy,
                        to_buy,harga,profit_harian,masa_aktif,type,total_profit,created_at)
                    VALUES
                       ('{$db->real_escape_string($nm)}','$kid','$lck','$pct','$mxb',
                        $tb_sql,'$hrg','$ph','$msa','$kid','$tp',NOW())");

        $_SESSION['result'] = $db->affected_rows > 0
            ? ['response'=>'success','title'=>'Berhasil!','msg'=>'Produk ditambahkan.']
            : ['response'=>'error',  'title'=>'Gagal!',   'msg'=>'Gagal simpan: '.$db->error];
    }
    header('Location: '.base_url('babikode/service/')); exit;
}

// PRODUK: update
if ($aksi === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['id']          ?? 0);
    $nm   = protect(trim($_POST['nama_produk'] ?? ''));
    $kid  = (int)($_POST['kategori_id'] ?? 0);
    $hrg  = (int)($_POST['harga']       ?? 0);
    $pct  = (float)($_POST['persentase'] ?? 0);
    $msa  = (int)($_POST['masa_aktif']  ?? 0);
    $mxb  = (int)($_POST['max_buy']     ?? 5);
    $tb   = (int)($_POST['to_buy']      ?? 0);

    $ph = (int)round($hrg * $pct / 100);
    $tp = $ph * $msa;

    if (!$id || !$nm || !$kid || !$hrg || !$pct || !$msa) {
        $_SESSION['result'] = ['response'=>'error','title'=>'Gagal!','msg'=>'Data tidak valid.'];
    } else {
        $kat    = mysqli_fetch_assoc(mysqli_query($db,
                  "SELECT is_locked,syarat FROM produk_kategori WHERE id='$kid'"));
        $lck    = ($kat && $kat['is_locked']) ? 1 : 0;
        $tb_sql = ($kat && $kat['syarat'] == 0 && $tb > 0) ? $tb : 'NULL';

        $db->query("UPDATE produk_investasi SET
                       nama_produk='{$db->real_escape_string($nm)}',
                       kategori_id='$kid', is_locked='$lck',
                       persentase='$pct', max_buy='$mxb', to_buy=$tb_sql,
                       harga='$hrg', profit_harian='$ph',
                       masa_aktif='$msa', type='$kid', total_profit='$tp'
                    WHERE id='$id'");
        $_SESSION['result'] = ['response'=>'success','title'=>'Berhasil!','msg'=>'Produk diperbarui.'];
    }
    header('Location: '.base_url('babikode/service/')); exit;
}

// PRODUK: hapus
if ($aksi === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $db->query("DELETE FROM produk_investasi WHERE id='$id'");
    $_SESSION['result'] = ['response'=>'success','title'=>'Berhasil!','msg'=>'Produk dihapus.'];
    header('Location: '.base_url('babikode/service/')); exit;
}

// UPDATE IMAGES:
if ($aksi === 'update_images' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $upload_dir = '../../assets/uploads/';
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

    $prd_url = protect($_POST['product_image'] ?? '');
    $bnr_url = protect($_POST['plans_banner'] ?? '');

    if (isset($_FILES['file_plans_banner']) && $_FILES['file_plans_banner']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['file_plans_banner']['name'], PATHINFO_EXTENSION);
        $filename = 'banner_' . time() . '_' . mt_rand(10,99) . '.' . $ext;
        if (move_uploaded_file($_FILES['file_plans_banner']['tmp_name'], $upload_dir . $filename)) {
            $bnr_url = base_url('assets/uploads/' . $filename);
        }
    }

    if (isset($_FILES['file_product_image']) && $_FILES['file_product_image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['file_product_image']['name'], PATHINFO_EXTENSION);
        $filename = 'product_' . time() . '_' . mt_rand(10,99) . '.' . $ext;
        if (move_uploaded_file($_FILES['file_product_image']['tmp_name'], $upload_dir . $filename)) {
            $prd_url = base_url('assets/uploads/' . $filename);
        }
    }

    if ($prd_url !== '') $db->query("UPDATE app_images SET image_url='{$db->real_escape_string($prd_url)}' WHERE image_key='product_image'");
    if ($bnr_url !== '') $db->query("UPDATE app_images SET image_url='{$db->real_escape_string($bnr_url)}' WHERE image_key='plans_banner'");
    
    $_SESSION['result'] = ['response'=>'success', 'title'=>'Berhasil!', 'msg'=>'Sistem gambar telah diperbarui.'];
    header('Location: '.base_url('babikode/service/')); exit;
}

/* ══════════════════════════════════════════════════════════════
   READ — siapkan data untuk tampil
   ══════════════════════════════════════════════════════════════ */
require '../../lib/header_admin.php';

$q_cari  = protect($_GET['search'] ?? '');
$q_baris = protect($_GET['row']    ?? '');
$q_kat   = (int)($_GET['kategori'] ?? 0);
$per_hal = in_array($q_baris, ['10','25','50','100']) ? (int)$q_baris : 10;
$hal_now = max(1, (int)($_GET['page'] ?? 1));

// semua kategori (untuk filter tab & dropdown form)
$semua_kat = [];
$r = mysqli_query($db, "SELECT * FROM produk_kategori ORDER BY urutan ASC, id ASC");
while ($row = mysqli_fetch_assoc($r)) $semua_kat[] = $row;

// semua produk untuk dropdown syarat beli
$semua_produk = [];
$r = mysqli_query($db, "SELECT p.id, p.nama_produk, k.nama AS kat_nama
                         FROM produk_investasi p
                         LEFT JOIN produk_kategori k ON k.id = p.kategori_id
                         ORDER BY p.harga ASC");
while ($row = mysqli_fetch_assoc($r)) $semua_produk[] = $row;

// list produk + filter
$where = "WHERE 1";
if ($q_kat > 0)      $where .= " AND p.kategori_id='$q_kat'";
if (!empty($q_cari)) $where .= " AND p.nama_produk LIKE '%".$db->real_escape_string($q_cari)."%'";

$sql = "SELECT p.*, k.nama AS kat_nama, tb.nama_produk AS to_buy_nama
        FROM produk_investasi p
        LEFT JOIN produk_kategori k   ON k.id  = p.kategori_id
        LEFT JOIN produk_investasi tb ON tb.id = p.to_buy
        $where ORDER BY k.urutan ASC, p.harga ASC";

$total   = mysqli_num_rows(mysqli_query($db, $sql));
$hal_max = max(1, ceil($total / $per_hal));
$hal_now = min($hal_now, $hal_max);
$offset  = ($hal_now - 1) * $per_hal;
$rows    = mysqli_query($db, "$sql LIMIT $offset, $per_hal");

// load images
$img_q = $db->query("SELECT * FROM app_images");
$app_images = [];
if ($img_q) while ($r = $img_q->fetch_assoc()) $app_images[$r['image_key']] = $r['image_url'];
?>

<?php /* ══ MODAL: KONFIRMASI HAPUS ══ */ ?>
<div id="mod-hapus"
     style="display:none;position:fixed;inset:0;z-index:9999;
            background:rgba(0,0,0,.48);backdrop-filter:blur(3px);
            align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:18px;overflow:hidden;
              width:340px;max-width:90vw;
              box-shadow:0 24px 64px rgba(0,0,0,.24);
              animation:popIn .2s cubic-bezier(.34,1.4,.64,1)">

    <div style="background:linear-gradient(135deg,#e74c3c,#c0392b);
                padding:30px 20px 22px;text-align:center">
      <div style="width:58px;height:58px;border-radius:50%;
                  background:rgba(255,255,255,.18);
                  display:flex;align-items:center;justify-content:center;
                  margin:0 auto 12px">
        <i class="ft-trash-2" style="font-size:26px;color:#fff"></i>
      </div>
      <div id="mh-judul" style="font-size:1rem;font-weight:700;color:#fff;line-height:1.3">
        Hapus data?
      </div>
    </div>

    <div style="padding:18px 22px 10px;text-align:center">
      <p id="mh-pesan" style="font-size:.84rem;color:#555;margin:0;line-height:1.65">
        Tindakan ini tidak dapat dibatalkan.
      </p>
    </div>

    <div style="display:flex;gap:10px;padding:10px 22px 22px">
      <button onclick="tutupHapus()"
              style="flex:1;height:42px;border:1.5px solid #ddd;border-radius:10px;
                     background:#f5f5f5;font-weight:600;font-size:.85rem;
                     cursor:pointer;font-family:inherit;color:#333">
        Batal
      </button>
      <a id="mh-url" href="#"
         style="flex:1;height:42px;border-radius:10px;
                background:linear-gradient(135deg,#e74c3c,#c0392b);
                color:#fff;font-weight:700;font-size:.85rem;
                text-decoration:none;
                display:flex;align-items:center;justify-content:center;gap:6px;
                box-shadow:0 4px 14px rgba(231,76,60,.35)">
        <i class="ft-trash" style="font-size:13px"></i> Ya, Hapus
      </a>
    </div>
  </div>
</div>
<style>
@keyframes popIn {
  from { transform: scale(.82); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}
</style>

<?php /* ══ HEADER ══ */ ?>
<div class="content-header row">
  <div class="content-header-left col-12 mb-2">
    <h3 class="content-header-title"><?= $page_name ?></h3>
  </div>
</div>

<div class="content-body">
<div class="row justify-content-center">
<div class="col-md-11">

<?php /* ══ TAB NAVIGASI ══ */ ?>
<ul class="nav nav-tabs mb-0" style="border-bottom:0">
  <li class="nav-item">
    <a class="nav-link active" href="<?= base_url('babikode/service/') ?>">
      <i class="ft-box mr-1"></i> Produk
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link" href="<?= base_url('babikode/service/kategori.php') ?>">
      <i class="ft-layers mr-1"></i> Kategori
    </a>
  </li>
</ul>
<br>

<div class="card" style="border-radius:8px">
  <div class="card-header border-bottom">
    <h4 class="card-title"><i class="ft-image mr-1"></i> Pengaturan Global Gambar Beranda Produk</h4>
  </div>
  <div class="card-body pt-2 pb-2">
    <form method="POST" action="<?= base_url('babikode/service/?action=update_images') ?>" enctype="multipart/form-data">
      <input type="hidden" name="action" value="update_images">
      <div class="row">
        <div class="col-md-6 form-group mb-1">
          <label class="font-weight-bold">Banner Halaman Produk (Plans Banner)</label>
          <div class="mb-1">
            <?php if(!empty($app_images['plans_banner'])): ?>
              <img src="<?= htmlspecialchars($app_images['plans_banner']) ?>" height="40" style="border-radius:4px; border:1px solid #ddd; padding:2px; max-width:100%; object-fit:contain;">
            <?php endif; ?>
          </div>
          <input type="file" name="file_plans_banner" class="form-control mb-1" accept="image/*">
          <input type="url" name="plans_banner" class="form-control" placeholder="Atau pakai URL Gambar langsung (cth: https://...)" value="<?= htmlspecialchars($app_images['plans_banner'] ?? '') ?>">
          <small class="text-muted">Ditampilkan berukuran besar di bagian paling atas.</small>
        </div>
        
        <div class="col-md-6 form-group mb-1">
          <label class="font-weight-bold">Gambar Daftar Produk (Satu Untuk Semua)</label>
          <div class="mb-1">
            <?php if(!empty($app_images['product_image'])): ?>
              <img src="<?= htmlspecialchars($app_images['product_image']) ?>" height="40" style="border-radius:4px; border:1px solid #ddd; padding:2px; max-width:100%; object-fit:contain;">
            <?php endif; ?>
          </div>
          <input type="file" name="file_product_image" class="form-control mb-1" accept="image/*">
          <input type="url" name="product_image" class="form-control" placeholder="Atau pakai URL Gambar langsung (cth: https://...)" value="<?= htmlspecialchars($app_images['product_image'] ?? '') ?>">
          <small class="text-muted">Muncul di samping tiap daftar produk.</small>
        </div>
      </div>
      <hr>
      <div class="text-right">
        <button type="submit" class="btn btn-sm btn-glow btn-bg-gradient-x-purple-blue"><i class="ft-upload mr-1"></i> Simpan / Upload Gambar</button>
      </div>
    </form>
  </div>
</div>

<div class="card" style="border-radius:0 8px 8px 8px">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="card-title mb-0"><?= $page_name ?></h4>
    <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-purple-blue"
            onclick="bukaModalTambahProduk()">
      <i class="ft-plus mr-1"></i><b>Tambah Produk</b>
    </button>
  </div>
  <div class="card-body pt-0">
    <?php require '../../lib/flash_message.php'; ?>

    <div class="btn-group flex-wrap mb-3">
      <a href="<?= base_url('babikode/service/') ?>"
         class="btn btn-glow btn-sm btn-primary <?= !$q_kat?'active':'' ?>">Semua</a>
      <?php foreach ($semua_kat as $k): ?>
      <a href="<?= base_url('babikode/service/?kategori='.$k['id']) ?>"
         class="btn btn-glow btn-sm btn-primary <?= $q_kat==$k['id']?'active':'' ?>">
        <?= htmlspecialchars($k['nama']) ?>
        <?php if ($k['is_locked']): ?><i class="ft-lock ml-1" style="font-size:9px"></i><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <form method="GET" class="row">
      <?php if ($q_kat): ?>
        <input type="hidden" name="kategori" value="<?= $q_kat ?>">
      <?php endif; ?>
      <div class="col-md-4">
        <div class="input-group mb-2">
          <div class="input-group-prepend"><span class="input-group-text">Tampilkan</span></div>
          <select name="row" id="sel-baris" class="form-control">
            <?php foreach (['10','25','50','100'] as $b): ?>
            <option value="<?= $b ?>" <?= $q_baris===$b?'selected':'' ?>><?= $b ?></option>
            <?php endforeach; ?>
          </select>
          <div class="input-group-append"><span class="input-group-text">baris</span></div>
        </div>
      </div>
      <div class="col-md-8">
        <div class="input-group mb-2">
          <input type="text" name="search" id="inp-cari" class="form-control"
                 value="<?= htmlspecialchars($q_cari) ?>" placeholder="Cari nama produk...">
          <div class="input-group-append">
            <button type="submit" class="btn btn-glow btn-bg-gradient-x-purple-blue">
              <i class="ft-search"></i>
            </button>
          </div>
        </div>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0">
        <thead>
          <tr class="text-uppercase">
            <th>ID</th><th>Nama Produk</th><th>Kategori</th>
            <th>Harga</th><th>Profit/Hari</th><th>Masa Aktif</th>
            <th>Total Profit</th><th>ROI</th><th>Max Beli</th><th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (mysqli_num_rows($rows) === 0): ?>
          <tr><td colspan="10" class="text-center py-3">Tidak ada data.</td></tr>
        <?php else: while ($p = mysqli_fetch_assoc($rows)):
          $roi = $p['harga'] > 0 ? round($p['total_profit'] / $p['harga'] * 100, 1) : 0;
          $hapusUrl = base_url('babikode/service/?action=delete&id='.$p['id']);
          $hapusMsg = 'Produk <b>'.htmlspecialchars($p['nama_produk'], ENT_QUOTES).'</b> akan dihapus permanen.';
        ?>
          <tr>
            <td><?= $p['id'] ?></td>
            <td><b><?= htmlspecialchars($p['nama_produk']) ?></b></td>
            <td>
              <span class="badge <?= $p['is_locked']?'badge-danger':'badge-success' ?>">
                <?php if ($p['is_locked']): ?><i class="ft-lock mr-1"></i><?php endif; ?>
                <?= htmlspecialchars($p['kat_nama'] ?? '—') ?>
              </span>
            </td>
            <td>Rp <?= number_format($p['harga'],0,',','.') ?></td>
            <td>Rp <?= number_format($p['profit_harian'],0,',','.') ?></td>
            <td><?= $p['masa_aktif'] ?> Hari</td>
            <td><b>Rp <?= number_format($p['total_profit'],0,',','.') ?></b></td>
            <td><?= $roi ?>%</td>
            <td><?= $p['max_buy'] ?>x</td>
            <td class="text-nowrap">
              <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-blue-cyan mb-1"
                      onclick='bukaModalEditProduk(<?= json_encode($p, JSON_HEX_QUOT | JSON_HEX_APOS) ?>)'>
                <i class="ft-edit mr-1"></i>Edit
              </button>
              <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink"
                      data-url="<?= htmlspecialchars($hapusUrl) ?>"
                      data-judul="Hapus Produk?"
                      data-pesan="<?= htmlspecialchars($hapusMsg) ?>"
                      onclick="bukaModalHapusFromBtn(this)">
                <i class="ft-trash mr-1"></i>Hapus
              </button>
            </td>
          </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card-footer">
    <nav><ul class="pagination pagination-md justify-content-center mb-2">
    <?php
    $qs = ($q_kat   ? '&kategori='.$q_kat      : '')
        . ($q_baris ? '&row='.$q_baris         : '')
        . ($q_cari  ? '&search='.urlencode($q_cari) : '');
    $pg = base_url('babikode/service/').'?page=%d'.$qs;

    if ($hal_max > 1):
        if ($hal_now > 1) {
            echo '<li class="page-item"><a class="page-link" href="'.sprintf($pg,1).'">« First</a></li>';
            echo '<li class="page-item"><a class="page-link" href="'.sprintf($pg,$hal_now-1).'">‹</a></li>';
        }
        for ($i = max(1,$hal_now-2); $i <= min($hal_max,$hal_now+2); $i++) {
            echo '<li class="page-item '.($i===$hal_now?'active':'').'">
                  <a class="page-link" href="'.sprintf($pg,$i).'">'.$i.'</a></li>';
        }
        if ($hal_now < $hal_max) {
            echo '<li class="page-item"><a class="page-link" href="'.sprintf($pg,$hal_now+1).'">›</a></li>';
            echo '<li class="page-item"><a class="page-link" href="'.sprintf($pg,$hal_max).'">Last »</a></li>';
        }
    endif;
    ?>
    </ul></nav>
    <div class="text-center">
      <span class="btn btn-glow btn-sm btn-bg-gradient-x-blue-green">
        Total: <b><?= number_format($total,0,',','.') ?></b> data
      </span>
    </div>
  </div>
</div>

</div>
</div>
</div>

<?php /* ════ MODAL: TAMBAH PRODUK ════ */ ?>
<div class="modal fade" id="mod-tambah-produk" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" action="<?= base_url('babikode/service/?action=add') ?>">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title"><i class="ft-plus mr-1"></i> Tambah Produk</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <?php tplProduk('tp', $semua_kat, $semua_produk); ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-glow btn-bg-gradient-x-purple-blue">
            <i class="ft-save mr-1"></i> Simpan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php /* ════ MODAL: EDIT PRODUK ════ */ ?>
<div class="modal fade" id="mod-edit-produk" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="POST" action="<?= base_url('babikode/service/?action=update') ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id"    id="ep-id">
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="ft-edit mr-1"></i> Edit Produk &mdash;
            <span id="ep-label" class="font-weight-normal text-muted"></span>
          </h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <?php tplProduk('ep', $semua_kat, $semua_produk); ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-glow btn-bg-gradient-x-blue-cyan">
            <i class="ft-save mr-1"></i> Perbarui
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
/* ════════════════════════════════════════════════════════════
   TEMPLATE HELPER: field-field form produk
   ════════════════════════════════════════════════════════════ */
function tplProduk(string $p, array $kats, array $prods): void { ?>
<div class="row">
  <div class="col-md-6">

    <div class="form-group">
      <label>Nama Produk <span class="text-danger">*</span></label>
      <input type="text" name="nama_produk" id="<?= $p ?>-nm"
             class="form-control" placeholder="cth: Paket Investasi Emas" required>
    </div>

    <div class="form-group">
      <label>Kategori <span class="text-danger">*</span></label>
      <select name="kategori_id" id="<?= $p ?>-kat" class="form-control" required
              onchange="cekKat('<?= $p ?>')">
        <?php foreach ($kats as $k): ?>
        <option value="<?= $k['id'] ?>" data-syarat="<?= (int)$k['syarat'] ?>">
          <?= htmlspecialchars($k['nama']) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <small id="<?= $p ?>-kat-info" class="text-muted"></small>
    </div>

    <div id="<?= $p ?>-to-buy-row" style="display:none" class="form-group">
      <label>Syarat Beli</label>
      <select name="to_buy" id="<?= $p ?>-to-buy" class="form-control">
        <option value="">— Tidak ada syarat —</option>
        <?php foreach ($prods as $pr): ?>
        <option value="<?= $pr['id'] ?>">
          <?= htmlspecialchars($pr['nama_produk']) ?>
          (<?= htmlspecialchars($pr['kat_nama']) ?>)
        </option>
        <?php endforeach; ?>
      </select>
      <small class="text-warning">
        <i class="ft-alert-triangle mr-1"></i>User wajib punya order aktif dari produk ini
      </small>
    </div>

    <div class="form-group">
      <label>Harga (Rp) <span class="text-danger">*</span></label>
      <div class="input-group">
        <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
        <input type="number" name="harga" id="<?= $p ?>-hrg"
               class="form-control" min="1" placeholder="50000"
               required oninput="hitungPrev('<?= $p ?>')">
      </div>
    </div>

    <div class="form-group">
      <label>Persentase / Hari (%) <span class="text-danger">*</span></label>
      <div class="input-group">
        <input type="number" name="persentase" id="<?= $p ?>-pct"
               class="form-control" min="0.01" max="999" step="0.01"
               placeholder="1.5" required oninput="hitungPrev('<?= $p ?>')">
        <div class="input-group-append"><span class="input-group-text">%</span></div>
      </div>
      <small class="text-muted">Profit harian = Harga × Persentase (dihitung otomatis)</small>
    </div>

    <div class="form-group">
      <label>Masa Aktif (Hari) <span class="text-danger">*</span></label>
      <div class="input-group">
        <input type="number" name="masa_aktif" id="<?= $p ?>-msa"
               class="form-control" min="1" placeholder="35"
               required oninput="hitungPrev('<?= $p ?>')">
        <div class="input-group-append"><span class="input-group-text">Hari</span></div>
      </div>
    </div>

    <div class="form-group mb-0">
      <label>Maks. Pembelian per User</label>
      <div class="input-group">
        <input type="number" name="max_buy" id="<?= $p ?>-mxb"
               class="form-control" min="1" placeholder="5" value="5">
        <div class="input-group-append"><span class="input-group-text">x</span></div>
      </div>
    </div>

  </div>

  <div class="col-md-6">
    <div class="card bg-light border-0 h-100 mb-0">
      <div class="card-body">
        <h6 class="font-weight-bold mb-3">
          <i class="ft-bar-chart-2 mr-1"></i> Preview Kalkulasi
        </h6>
        <table class="table table-sm table-borderless mb-0">
          <tbody>
            <tr>
              <td class="text-muted" style="width:55%">Harga (Modal)</td>
              <td class="text-right font-weight-bold" id="<?= $p ?>-c-hrg">—</td>
            </tr>
            <tr>
              <td class="text-muted">Persentase / Hari</td>
              <td class="text-right font-weight-bold" id="<?= $p ?>-c-pct">—</td>
            </tr>
            <tr class="border-top">
              <td class="text-muted">
                <b>Profit Harian</b><br>
                <small class="text-info font-weight-normal">Harga × Persen</small>
              </td>
              <td class="text-right font-weight-bold text-success" id="<?= $p ?>-c-ph">—</td>
            </tr>
            <tr>
              <td class="text-muted">Masa Aktif</td>
              <td class="text-right font-weight-bold" id="<?= $p ?>-c-msa">—</td>
            </tr>
            <tr class="border-top">
              <td class="text-muted">
                Total Profit<br>
                <small class="text-info">Harian × Masa Aktif</small>
              </td>
              <td class="text-right font-weight-bold text-success" id="<?= $p ?>-c-tp">—</td>
            </tr>
            <tr>
              <td class="text-muted">
                ROI<br>
                <small class="text-info">Net / Modal × 100</small>
              </td>
              <td class="text-right font-weight-bold text-danger" id="<?= $p ?>-c-roi">—</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php } /* end tplProduk */ ?>

<script>
/* ════════════════════════════════════════════════════════════
   JAVASCRIPT — Produk
   ════════════════════════════════════════════════════════════ */

/* -- format angka ------------------------------------------- */
function _rp(n)  { return 'Rp ' + Math.round(n || 0).toLocaleString('id-ID'); }
function _pct(n) { return parseFloat(n || 0).toFixed(2) + '%'; }

/* -- kalkulasi preview -------------------------------------- */
function hitungPrev(p) {
    var h  = parseFloat(document.getElementById(p+'-hrg').value) || 0;
    var pc = parseFloat(document.getElementById(p+'-pct').value) || 0;
    var m  = parseInt(document.getElementById(p+'-msa').value)   || 0;
    var ids = ['c-hrg','c-pct','c-ph','c-msa','c-tp','c-roi'];

    if (!h || !pc || !m) {
        ids.forEach(function(k) {
            document.getElementById(p+'-'+k).textContent = '—';
        });
        return;
    }
    var ph  = h * pc / 100;
    var tp  = ph * m;
    var roi = (tp - h) / h * 100;

    document.getElementById(p+'-c-hrg').textContent  = _rp(h);
    document.getElementById(p+'-c-pct').textContent  = pc + '% / hari';
    document.getElementById(p+'-c-ph').textContent   = _rp(Math.round(ph)) + ' / hari';
    document.getElementById(p+'-c-msa').textContent  = m + ' hari';
    document.getElementById(p+'-c-tp').textContent   = _rp(Math.round(tp));
    document.getElementById(p+'-c-roi').textContent  = (roi >= 0 ? '+' : '') + _pct(roi);
}

/* -- tampil / sembunyikan field syarat beli ----------------- */
function cekKat(p) {
    var sel   = document.getElementById(p+'-kat');
    var opt   = sel.options[sel.selectedIndex];
    var dasar = opt && opt.dataset.syarat === '1';
    var row   = document.getElementById(p+'-to-buy-row');
    var info  = document.getElementById(p+'-kat-info');
    if (row)  row.style.display = dasar ? 'none' : 'block';
    if (info) info.textContent  = dasar ? 'Kategori dasar — tidak perlu syarat beli' : '';
}

/* -- modal konfirmasi hapus (via data-* attribute) ---------- */
function bukaModalHapusFromBtn(btn) {
    document.getElementById('mh-judul').textContent = btn.dataset.judul;
    document.getElementById('mh-pesan').innerHTML   = btn.dataset.pesan;
    document.getElementById('mh-url').href          = btn.dataset.url;
    var el = document.getElementById('mod-hapus');
    el.style.display = 'flex';
}
function tutupHapus() {
    document.getElementById('mod-hapus').style.display = 'none';
}
document.getElementById('mod-hapus').addEventListener('click', function(e) {
    if (e.target === this) tutupHapus();
});

/* -- modal tambah produk ------------------------------------ */
function bukaModalTambahProduk() {
    ['tp-nm','tp-hrg','tp-pct','tp-msa','tp-to-buy'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('tp-mxb').value = '5';
    document.getElementById('tp-kat').selectedIndex = 0;
    cekKat('tp');
    hitungPrev('tp');
    $('#mod-tambah-produk').modal('show');
}

/* -- modal edit produk -------------------------------------- */
function bukaModalEditProduk(d) {
    document.getElementById('ep-id').value            = d.id;
    document.getElementById('ep-label').textContent   = d.nama_produk;
    document.getElementById('ep-nm').value            = d.nama_produk;
    document.getElementById('ep-hrg').value           = d.harga;
    document.getElementById('ep-pct').value           = d.persentase;
    document.getElementById('ep-msa').value           = d.masa_aktif;
    document.getElementById('ep-mxb').value           = d.max_buy;

    var selKat = document.getElementById('ep-kat');
    for (var i = 0; i < selKat.options.length; i++) {
        if (selKat.options[i].value == d.kategori_id) { selKat.selectedIndex = i; break; }
    }
    cekKat('ep');

    var selTb = document.getElementById('ep-to-buy');
    var tbVal = d.to_buy ? String(d.to_buy) : '';
    for (var j = 0; j < selTb.options.length; j++) {
        if (selTb.options[j].value === tbVal) { selTb.selectedIndex = j; break; }
    }

    hitungPrev('ep');
    $('#mod-edit-produk').modal('show');
}

/* -- init --------------------------------------------------- */
$(function () {
    // ganti jumlah baris → langsung reload
    $('#sel-baris').on('change', function () {
        var cari = document.getElementById('inp-cari');
        window.location.href = '<?= base_url('babikode/service/') ?>?'
            + 'row=' + this.value
            + '&search=' + encodeURIComponent(cari ? cari.value : '')
            + '<?= $q_kat ? '&kategori='.$q_kat : '' ?>';
    });

    // disable submit saat form dikirim
    $('form').on('submit', function () {
        $(this).find('[type="submit"]').prop('disabled', true);
    });

    // init dropdown kategori
    cekKat('tp');
    cekKat('ep');
});
</script>

<?php require '../../lib/footer_admin.php'; ?>
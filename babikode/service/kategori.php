<?php
/* ============================================================
   babikode/service/kategori.php — Manajemen Kategori
   ============================================================ */

require '../../mainconfig.php';
require '../../lib/check_session_admin.php';

$aksi = trim($_GET['action'] ?? $_POST['action'] ?? '');

/* ------------------------------------------------------------
   WRITE
   ------------------------------------------------------------ */

if ($aksi === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nm  = $db->real_escape_string(trim($_POST['nama']      ?? ''));
    $des = $db->real_escape_string(trim($_POST['deskripsi'] ?? ''));
    $lck = empty($_POST['is_locked']) ? 0 : 1;
    $syr = empty($_POST['syarat'])    ? 0 : 1;
    $urt = (int)($_POST['urutan']     ?? 0);
    $hid = empty($_POST['is_hidden'])  ? 0 : 1;
    if ($nm === '') {
        $_SESSION['result'] = ['response'=>'error','title'=>'Gagal!','msg'=>'Nama wajib diisi.'];
    } else {
        $db->query("INSERT INTO produk_kategori (nama,deskripsi,is_locked,syarat,urutan,is_hidden) VALUES ('$nm','$des',$lck,$syr,$urt,$hid)");
        $_SESSION['result'] = ['response'=>'success','title'=>'Berhasil!','msg'=>'Kategori ditambahkan.'];
    }
    header('Location: '.base_url('babikode/service/kategori.php')); exit;
}

if ($aksi === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id  = (int)($_POST['id'] ?? 0);
    $nm  = $db->real_escape_string(trim($_POST['nama']      ?? ''));
    $des = $db->real_escape_string(trim($_POST['deskripsi'] ?? ''));
    $lck = empty($_POST['is_locked']) ? 0 : 1;
    $syr = empty($_POST['syarat'])    ? 0 : 1;
    $urt = (int)($_POST['urutan']     ?? 0);
    $hid = empty($_POST['is_hidden'])  ? 0 : 1;
    if (!$id || $nm === '') {
        $_SESSION['result'] = ['response'=>'error','title'=>'Gagal!','msg'=>'Data tidak valid.'];
    } else {
        $db->query("UPDATE produk_kategori SET nama='$nm',deskripsi='$des',is_locked=$lck,syarat=$syr,urutan=$urt,is_hidden=$hid WHERE id=$id");
        $db->query("UPDATE produk_investasi SET is_locked=$lck WHERE kategori_id=$id");
        $_SESSION['result'] = ['response'=>'success','title'=>'Berhasil!','msg'=>'Kategori diperbarui.'];
    }
    header('Location: '.base_url('babikode/service/kategori.php')); exit;
}

// Hapus produk terpilih
if ($aksi === 'delete_produk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['produk_ids'] ?? [];
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids);
    if (!empty($ids)) {
        $in = implode(',', $ids);
        $db->query("DELETE FROM produk_investasi WHERE id IN ($in)");
    }
    $_SESSION['result'] = ['response'=>'success','title'=>'Berhasil!','msg'=>count($ids).' produk dihapus.'];
    header('Location: '.base_url('babikode/service/kategori.php')); exit;
}

// Hapus kategori (hanya jika sudah kosong)
if ($aksi === 'delete' && isset($_GET['id'])) {
    $id  = (int)$_GET['id'];
    $cek = (int)mysqli_fetch_assoc($db->query("SELECT COUNT(*) AS c FROM produk_investasi WHERE kategori_id=$id"))['c'];
    if ($cek > 0) {
        $_SESSION['result'] = ['response'=>'error','title'=>'Gagal!','msg'=>"Masih ada $cek produk. Hapus produknya dulu."];
    } else {
        $db->query("DELETE FROM produk_kategori WHERE id=$id");
        $_SESSION['result'] = ['response'=>'success','title'=>'Berhasil!','msg'=>'Kategori dihapus.'];
    }
    header('Location: '.base_url('babikode/service/kategori.php')); exit;
}

/* ------------------------------------------------------------
   READ
   ------------------------------------------------------------ */
require '../../lib/header_admin.php';

$semua = [];
$r = $db->query("SELECT * FROM produk_kategori ORDER BY urutan ASC, id ASC");
while ($row = mysqli_fetch_assoc($r)) {
    $pr = $db->query("SELECT id, nama_produk, harga FROM produk_investasi WHERE kategori_id={$row['id']} ORDER BY harga ASC");
    $row['produk'] = [];
    while ($p = mysqli_fetch_assoc($pr)) $row['produk'][] = $p;
    $row['jml'] = count($row['produk']);
    $semua[] = $row;
}
?>

<div class="content-header row">
  <div class="content-header-left col-12 mb-2">
    <h3 class="content-header-title">Manajemen Kategori</h3>
  </div>
</div>

<div class="content-body">
<div class="row justify-content-center">
<div class="col-md-11">

<ul class="nav nav-tabs mb-0" style="border-bottom:0">
  <li class="nav-item">
    <a class="nav-link" href="<?= base_url('babikode/service/') ?>">
      <i class="ft-box mr-1"></i> Produk
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link active" href="<?= base_url('babikode/service/kategori.php') ?>">
      <i class="ft-layers mr-1"></i> Kategori
    </a>
  </li>
</ul>

<div class="card" style="border-radius:0 8px 8px 8px">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h4 class="card-title mb-0">Daftar Kategori</h4>
    <button class="btn btn-glow btn-sm btn-bg-gradient-x-purple-blue"
            data-toggle="modal" data-target="#mod-tambah">
      <i class="ft-plus mr-1"></i><b>Tambah Kategori</b>
    </button>
  </div>

  <div class="card-body pt-0">
    <?php require '../../lib/flash_message.php'; ?>
    <div class="table-responsive">
    <table class="table table-bordered table-hover mb-0">
      <thead>
        <tr class="text-uppercase">
          <th>ID</th><th>Nama</th><th>Deskripsi</th>
          <th>Lock</th><th>Hidden</th><th>Urutan</th><th>Produk</th><th>Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($semua)): ?>
        <tr><td colspan="7" class="text-center py-3 text-muted">Belum ada kategori.</td></tr>
      <?php endif; ?>
      <?php foreach ($semua as $k): ?>
      <tr>
        <td><?= $k['id'] ?></td>
        <td><b><?= htmlspecialchars($k['nama']) ?></b></td>
        <td><small class="text-muted"><?= htmlspecialchars($k['deskripsi'] ?: '—') ?></small></td>
        <td>
          <?php if ($k['is_locked']): ?>
            <span class="badge badge-danger"><i class="ft-lock mr-1"></i>Profit Lock</span>
          <?php else: ?>
            <span class="badge badge-success"><i class="ft-unlock mr-1"></i>Bebas</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($k['is_hidden']): ?>
            <span class="badge badge-dark"><i class="ft-eye-off mr-1"></i>Hidden</span>
          <?php else: ?>
            <span class="badge badge-light border"><i class="ft-eye mr-1"></i>Visible</span>
          <?php endif; ?>
        </td>
        <td><?= $k['urutan'] ?></td>
        <td><span class="badge badge-info"><?= $k['jml'] ?> produk</span></td>
        <td class="text-nowrap">
          <button type="button"
                  class="btn btn-glow btn-sm btn-bg-gradient-x-blue-cyan mr-1"
                  onclick='bukaEdit(<?= htmlspecialchars(json_encode([
                    'id'        => $k['id'],
                    'nama'      => $k['nama'],
                    'deskripsi' => $k['deskripsi'],
                    'is_locked' => $k['is_locked'],
                    'syarat'    => $k['syarat'],
                    'urutan'    => $k['urutan'],
                    'is_hidden' => $k['is_hidden'],
                  ]), ENT_QUOTES) ?>)'>
            <i class="ft-edit"></i> Edit
          </button>
          <button type="button"
                  class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink"
                  onclick='bukaHapus(<?= htmlspecialchars(json_encode([
                    'id'     => $k['id'],
                    'nama'   => $k['nama'],
                    'jml'    => $k['jml'],
                    'produk' => $k['produk'],
                  ]), ENT_QUOTES) ?>)'>
            <i class="ft-trash"></i> Hapus
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

</div>
</div>
</div>

<!-- ============================================================
     MODAL TAMBAH
     ============================================================ -->
<div class="modal fade" id="mod-tambah" tabindex="-1" role="dialog">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="<?= base_url('babikode/service/kategori.php?action=add') ?>">
      <input type="hidden" name="action" value="add">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ft-plus mr-1"></i> Tambah Kategori</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Nama Kategori <span class="text-danger">*</span></label>
          <input type="text" name="nama" class="form-control" placeholder="cth: Kategori Premium" required>
        </div>
        <div class="form-group">
          <label>Deskripsi</label>
          <textarea name="deskripsi" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label>Urutan Tampil</label>
          <input type="number" name="urutan" class="form-control" value="0" min="0">
          <small class="text-muted">Angka lebih kecil tampil lebih dulu</small>
        </div>
        <div class="form-group">
          <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="t-lock" name="is_locked" value="1">
            <label class="custom-control-label" for="t-lock">
              <b>Profit Lock</b><br>
              <small class="text-muted">Profit dikunci hingga masa aktif habis</small>
            </label>
          </div>
        </div>
        <div class="form-group">
          <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="t-syarat" name="syarat" value="1">
            <label class="custom-control-label" for="t-syarat">
              <b>Kategori Dasar</b><br>
              <small class="text-muted">Tidak perlu syarat beli</small>
            </label>
          </div>
        </div>
        <div class="form-group mb-0">
          <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="t-hidden" name="is_hidden" value="1">
            <label class="custom-control-label" for="t-hidden">
              <b>Hidden</b><br>
              <small class="text-muted">Sembunyikan kategori dari tampilan user</small>
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-glow btn-bg-gradient-x-purple-blue">
          <i class="ft-save mr-1"></i> Simpan
        </button>
      </div>
    </form>
  </div></div>
</div>

<!-- ============================================================
     MODAL EDIT
     ============================================================ -->
<div class="modal fade" id="mod-edit" tabindex="-1" role="dialog">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="<?= base_url('babikode/service/kategori.php?action=update') ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="e-id">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ft-edit mr-1"></i> Edit Kategori</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Nama Kategori <span class="text-danger">*</span></label>
          <input type="text" name="nama" id="e-nama" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Deskripsi</label>
          <textarea name="deskripsi" id="e-des" class="form-control" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label>Urutan Tampil</label>
          <input type="number" name="urutan" id="e-urt" class="form-control" min="0">
          <small class="text-muted">Angka lebih kecil tampil lebih dulu</small>
        </div>
        <div class="form-group">
          <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="e-lock" name="is_locked" value="1">
            <label class="custom-control-label" for="e-lock">
              <b>Profit Lock</b><br>
              <small class="text-muted">Disinkronkan ke semua produk dalam kategori</small>
            </label>
          </div>
        </div>
        <div class="form-group">
          <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="e-syarat" name="syarat" value="1">
            <label class="custom-control-label" for="e-syarat">
              <b>Kategori Dasar</b><br>
              <small class="text-muted">Tidak perlu syarat beli</small>
            </label>
          </div>
        </div>
        <div class="form-group mb-0">
          <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="e-hidden" name="is_hidden" value="1">
            <label class="custom-control-label" for="e-hidden">
              <b>Hidden</b><br>
              <small class="text-muted">Sembunyikan kategori dari tampilan user</small>
            </label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-glow btn-bg-gradient-x-blue-cyan">
          <i class="ft-save mr-1"></i> Perbarui
        </button>
      </div>
    </form>
  </div></div>
</div>

<!-- ============================================================
     MODAL HAPUS
     ============================================================ -->
<div class="modal fade" id="mod-hapus" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg"><div class="modal-content">

    <div class="modal-header" style="background:linear-gradient(135deg,#e74c3c,#c0392b)">
      <h5 class="modal-title text-white">
        <i class="ft-trash-2 mr-1"></i>
        Hapus Kategori: <span id="h-nama-kat" class="font-weight-bold"></span>
      </h5>
      <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
    </div>

    <div class="modal-body">

      <!-- Jika kategori kosong -->
      <div id="h-kosong" style="display:none" class="text-center py-3">
        <i class="ft-check-circle text-success" style="font-size:2rem"></i>
        <p class="mt-2 mb-0">Kategori ini tidak memiliki produk.<br>Aman untuk dihapus.</p>
      </div>

      <!-- Jika ada produk -->
      <div id="h-ada-produk" style="display:none">
        <div class="alert alert-warning mb-3">
          <i class="ft-alert-triangle mr-1"></i>
          Kategori ini masih memiliki <strong id="h-jml-produk"></strong> produk.
          Pilih produk yang ingin dihapus terlebih dahulu, atau hapus semua sekaligus.
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2">
          <label class="mb-0 font-weight-bold">Daftar Produk:</label>
          <div>
            <button type="button" class="btn btn-sm btn-outline-danger mr-1" onclick="pilihSemua(true)">
              Pilih Semua
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="pilihSemua(false)">
              Batal Semua
            </button>
          </div>
        </div>

        <form id="form-hapus-produk"
              method="POST"
              action="<?= base_url('babikode/service/kategori.php?action=delete_produk') ?>">
          <input type="hidden" name="action" value="delete_produk">

          <div id="h-list-produk"
               style="max-height:280px;overflow-y:auto;border:1px solid #dee2e6;border-radius:6px">
            <!-- diisi JS -->
          </div>

          <div class="mt-3 d-flex justify-content-between align-items-center">
            <small class="text-muted"><span id="h-terpilih">0</span> produk dipilih</small>
            <button type="submit" class="btn btn-glow btn-bg-gradient-x-red-pink"
                    id="btn-hapus-produk" disabled
                    onclick="return confirm('Hapus produk yang dipilih?')">
              <i class="ft-trash mr-1"></i> Hapus Produk Terpilih
            </button>
          </div>
        </form>

        <hr>
        <div class="text-center">
          <small class="text-muted d-block mb-2">
            Setelah semua produk dihapus, kategori bisa dihapus.
          </small>
        </div>
      </div>

    </div>

    <!-- Footer: tombol hapus kategori (muncul kalau sudah kosong) -->
    <div class="modal-footer d-flex justify-content-between">
      <a id="btn-hapus-kat" href="#"
         class="btn btn-glow btn-bg-gradient-x-red-pink"
         style="display:none"
         onclick="return confirm('Yakin hapus kategori ini permanen?')">
        <i class="ft-trash mr-1"></i> Hapus Kategori Sekarang
      </a>
      <button type="button" class="btn btn-secondary ml-auto" data-dismiss="modal">Tutup</button>
    </div>

  </div></div>
</div>

<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script>
function bukaEdit(d) {
    document.getElementById('e-id').value       = d.id;
    document.getElementById('e-nama').value     = d.nama;
    document.getElementById('e-des').value      = d.deskripsi || '';
    document.getElementById('e-urt').value      = d.urutan;
    document.getElementById('e-lock').checked   = d.is_locked == 1;
    document.getElementById('e-syarat').checked  = d.syarat == 1;
    document.getElementById('e-hidden').checked  = d.is_hidden == 1;
    $('#mod-edit').modal('show');
}

function bukaHapus(d) {
    var baseUrl = '<?= base_url('babikode/service/kategori.php') ?>';

    $('#h-nama-kat').text(d.nama);
    $('#btn-hapus-kat').attr('href', baseUrl + '?action=delete&id=' + d.id);

    if (d.jml === 0) {
        $('#h-kosong').show();
        $('#h-ada-produk').hide();
        $('#btn-hapus-kat').show();
    } else {
        $('#h-kosong').hide();
        $('#h-ada-produk').show();
        $('#btn-hapus-kat').hide();
        $('#h-jml-produk').text(d.jml + ' produk');

        // render list produk dengan checkbox
        var html = '';
        $.each(d.produk, function(i, p) {
            var harga = 'Rp ' + parseInt(p.harga).toLocaleString('id-ID');
            html += '<div class="d-flex align-items-center px-3 py-2" '
                  + 'style="' + (i % 2 === 0 ? 'background:#f8f9fa' : '') + '">'
                  + '<div class="custom-control custom-checkbox">'
                  + '<input type="checkbox" class="custom-control-input chk-produk" '
                  + 'id="chk-' + p.id + '" name="produk_ids[]" value="' + p.id + '">'
                  + '<label class="custom-control-label" for="chk-' + p.id + '">'
                  + '<span class="font-weight-bold">' + $('<span>').text(p.nama_produk).html() + '</span>'
                  + ' &mdash; <span class="text-muted">' + harga + '</span>'
                  + '</label>'
                  + '</div>'
                  + '</div>';
        });
        $('#h-list-produk').html(html);
        updateTerpilih();

        // event checkbox
        $('#h-list-produk').off('change').on('change', '.chk-produk', updateTerpilih);
    }

    $('#mod-hapus').modal('show');
}

function pilihSemua(val) {
    $('.chk-produk').prop('checked', val);
    updateTerpilih();
}

function updateTerpilih() {
    var n = $('.chk-produk:checked').length;
    $('#h-terpilih').text(n);
    $('#btn-hapus-produk').prop('disabled', n === 0);
}
</script>

<?php require '../../lib/footer_admin.php'; ?>
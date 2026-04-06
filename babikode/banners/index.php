<?php
require '../../mainconfig.php';
require '../../lib/check_session_admin.php';

// ========== CONFIGURATION ==========
$UPLOAD_PATH = '../../assets/images/banners/'; // Path untuk upload banner
$UPLOAD_URL = base_url('assets/images/banners/'); // URL untuk akses banner

// Create upload directory if not exists
if (!file_exists($UPLOAD_PATH)) {
    mkdir($UPLOAD_PATH, 0755, true);
}

// ========== HANDLE AJAX REQUEST ==========
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    // GET BANNER DATA FOR EDIT
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_banner') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $query = mysqli_query($db, "SELECT * FROM banners WHERE id = $id");
        if ($banner = mysqli_fetch_assoc($query)) {
            // Parse display_text
            $lines = explode("\n", $banner['display_text']);
            $banner['title'] = isset($lines[0]) ? $lines[0] : '';
            $banner['subtitle'] = isset($lines[1]) ? $lines[1] : '';
            $banner['footer'] = isset($lines[2]) ? $lines[2] : '';
            echo json_encode(['success' => true, 'data' => $banner]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Banner tidak ditemukan']);
        }
        exit;
    }
    
    // ADD BANNER
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
        $image_type = isset($_POST['image_type']) ? protect($_POST['image_type']) : 'url';
        $image_url = '';
        
        // Handle image upload or URL
        if ($image_type === 'upload' && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0) {
            $file = $_FILES['image_file'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'msg' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, atau GIF']);
                exit;
            }
            
            if ($file['size'] > 2 * 1024 * 1024) { // 2MB limit
                echo json_encode(['success' => false, 'msg' => 'Ukuran file maksimal 2MB']);
                exit;
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'banner_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target = $UPLOAD_PATH . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $image_url = $UPLOAD_URL . $filename;
            } else {
                echo json_encode(['success' => false, 'msg' => 'Gagal upload file']);
                exit;
            }
        } elseif ($image_type === 'url') {
            $image_url = isset($_POST['image_url']) ? protect(trim($_POST['image_url'])) : '';
            if (empty($image_url)) {
                echo json_encode(['success' => false, 'msg' => 'URL gambar tidak boleh kosong']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'msg' => 'Pilih metode gambar (upload atau URL)']);
            exit;
        }
        
        // Build display_text from title, subtitle, footer
        $title = isset($_POST['title']) ? protect(trim($_POST['title'])) : '';
        $subtitle = isset($_POST['subtitle']) ? protect(trim($_POST['subtitle'])) : '';
        $footer = isset($_POST['footer']) ? protect(trim($_POST['footer'])) : '';
        
        $display_text_parts = array_filter([$title, $subtitle, $footer]);
        $display_text = implode("\n", $display_text_parts);
        
        $link_url = isset($_POST['link_url']) ? protect(trim($_POST['link_url'])) : '#';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $order_position = isset($_POST['order_position']) ? (int)$_POST['order_position'] : 0;
        
        $insert = mysqli_query($db, "INSERT INTO banners (image_url, display_text, is_active, link_url, order_position, created_at) 
                                     VALUES ('$image_url', '$display_text', $is_active, '$link_url', $order_position, NOW())");
        
        if ($insert) {
            echo json_encode(['success' => true, 'msg' => 'Banner berhasil ditambahkan']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal menambahkan banner']);
        }
        exit;
    }
    
    // UPDATE BANNER
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'msg' => 'ID tidak valid']);
            exit;
        }
        
        $image_type = isset($_POST['image_type']) ? protect($_POST['image_type']) : 'url';
        $image_url = '';
        $update_image = false;
        
        // Handle image update
        if ($image_type === 'upload' && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0) {
            $file = $_FILES['image_file'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'msg' => 'Tipe file tidak diizinkan']);
                exit;
            }
            
            if ($file['size'] > 2 * 1024 * 1024) {
                echo json_encode(['success' => false, 'msg' => 'Ukuran file maksimal 2MB']);
                exit;
            }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'banner_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target = $UPLOAD_PATH . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $image_url = $UPLOAD_URL . $filename;
                $update_image = true;
                
                // Delete old image if it was uploaded
                $old_banner = mysqli_fetch_assoc(mysqli_query($db, "SELECT image_url FROM banners WHERE id = $id"));
                if ($old_banner && strpos($old_banner['image_url'], $UPLOAD_URL) !== false) {
                    $old_file = str_replace($UPLOAD_URL, $UPLOAD_PATH, $old_banner['image_url']);
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
            }
        } elseif ($image_type === 'url' && !empty($_POST['image_url'])) {
            $image_url = protect(trim($_POST['image_url']));
            $update_image = true;
        }
        
        // Build display_text
        $title = isset($_POST['title']) ? protect(trim($_POST['title'])) : '';
        $subtitle = isset($_POST['subtitle']) ? protect(trim($_POST['subtitle'])) : '';
        $footer = isset($_POST['footer']) ? protect(trim($_POST['footer'])) : '';
        
        $display_text_parts = array_filter([$title, $subtitle, $footer]);
        $display_text = implode("\n", $display_text_parts);
        
        $link_url = isset($_POST['link_url']) ? protect(trim($_POST['link_url'])) : '#';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $order_position = isset($_POST['order_position']) ? (int)$_POST['order_position'] : 0;
        
        $update_sql = "UPDATE banners SET 
                      display_text = '$display_text',
                      is_active = $is_active,
                      link_url = '$link_url',
                      order_position = $order_position";
        
        if ($update_image) {
            $update_sql .= ", image_url = '$image_url'";
        }
        
        $update_sql .= " WHERE id = $id";
        
        $update = mysqli_query($db, $update_sql);
        
        if ($update) {
            echo json_encode(['success' => true, 'msg' => 'Banner berhasil diupdate']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal mengupdate banner']);
        }
        exit;
    }
    
    // DELETE BANNER
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'msg' => 'ID tidak valid']);
            exit;
        }
        
        // Get banner data to delete image file
        $banner = mysqli_fetch_assoc(mysqli_query($db, "SELECT image_url FROM banners WHERE id = $id"));
        
        $delete = mysqli_query($db, "DELETE FROM banners WHERE id = $id");
        
        if ($delete) {
            // Delete image file if it was uploaded
            if ($banner && strpos($banner['image_url'], $UPLOAD_URL) !== false) {
                $file = str_replace($UPLOAD_URL, $UPLOAD_PATH, $banner['image_url']);
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            echo json_encode(['success' => true, 'msg' => 'Banner berhasil dihapus']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal menghapus banner']);
        }
        exit;
    }
    
    exit;
}

// ========== REGULAR PAGE ==========
$page_type = 'banner';
$page_name = 'Kelola Banner';
require '../../lib/header_admin.php';
?>

<div class="content-header row">
    <div class="content-header-left col-md-4 col-12 mb-2">
        <h3 class="content-header-title"><?= $page_name; ?></h3>
    </div>
    <div class="content-header-right col-md-8 col-12">
        <div class="breadcrumbs-top float-md-right">
            <div class="breadcrumb-wrapper mr-1">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="javascript:;"><?= base_title(); ?></a></li>
                    <li class="breadcrumb-item active">Banner</li>
                    <li class="breadcrumb-item active"><?= $page_name; ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title float-left"><?= $page_name; ?></h4>
                    <button class="btn btn-glow btn-sm btn-bg-gradient-x-purple-blue float-right" onclick="openAddModal()">
                        <i class="ft-plus mr-1"></i> Tambah Banner
                    </button>
                </div>
                <div class="card-body pt-0">
                    <div id="body-result"></div>
                    <?php require '../../lib/flash_message.php'; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr class="text-uppercase">
                                    <th width="5%">ID</th>
                                    <th width="15%">Preview</th>
                                    <th width="20%">Display Text</th>
                                    <th width="15%">Link URL</th>
                                    <th width="10%">Status</th>
                                    <th width="10%">Urutan</th>
                                    <th width="15%">Tanggal</th>
                                    <th width="10%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = mysqli_query($db, "SELECT * FROM banners ORDER BY order_position ASC, id DESC");
                                
                                if (mysqli_num_rows($query) == 0) {
                                    echo '<tr><td colspan="8" align="center">Belum ada banner.</td></tr>';
                                }
                                
                                while ($banner = mysqli_fetch_assoc($query)) {
                                    $status_color = $banner['is_active'] == 1 ? 'success' : 'secondary';
                                    $status_text = $banner['is_active'] == 1 ? 'Aktif' : 'Nonaktif';
                                ?>
                                    <tr>
                                        <td><?= $banner['id']; ?></td>
                                        <td>
                                            <img src="<?= $banner['image_url']; ?>" alt="Banner" class="img-fluid rounded" style="max-height: 80px;">
                                        </td>
                                        <td>
                                            <?php if (!empty($banner['display_text'])) {
                                                $lines = explode("\n", $banner['display_text']);
                                                echo '<small>';
                                                echo '<b>Title:</b> ' . (isset($lines[0]) ? $lines[0] : '-') . '<br>';
                                                echo '<b>Subtitle:</b> ' . (isset($lines[1]) ? $lines[1] : '-') . '<br>';
                                                echo '<b>Footer:</b> ' . (isset($lines[2]) ? $lines[2] : '-');
                                                echo '</small>';
                                            } else {
                                                echo '<span class="text-muted">Tidak ada teks</span>';
                                            } ?>
                                        </td>
                                        <td><small><?= $banner['link_url']; ?></small></td>
                                        <td><span class="badge badge-<?= $status_color; ?>"><?= $status_text; ?></span></td>
                                        <td class="text-center"><?= $banner['order_position']; ?></td>
                                        <td class="text-nowrap">
                                            <small><?= date('d M Y H:i', strtotime($banner['created_at'])); ?></small>
                                        </td>
                                        <td class="text-nowrap">
                                            <button class="btn btn-glow btn-sm btn-bg-gradient-x-blue-cyan mb-1" onclick="openEditModal(<?= $banner['id']; ?>)">
                                                <i class="ft-edit mr-1"></i>Edit
                                            </button>
                                            <button class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink" onclick="deleteBanner(<?= $banner['id']; ?>)">
                                                <i class="ft-trash mr-1"></i>Hapus
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add/Edit Banner -->
<div class="modal fade" id="bannerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle"><i class="fa fa-image mr-2"></i> Tambah Banner</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formBanner" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="bannerId" value="">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Metode Gambar <span class="text-danger">*</span></label>
                                <select class="form-control" name="image_type" id="imageType" required>
                                    <option value="url">URL Gambar</option>
                                    <option value="upload">Upload File</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="urlInput">
                                <label>URL Gambar <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" name="image_url" id="imageUrl" placeholder="https://example.com/banner.jpg">
                                <small class="text-muted">Masukkan URL lengkap gambar banner</small>
                            </div>
                            
                            <div class="form-group" id="fileInput" style="display: none;">
                                <label>Upload Gambar <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="image_file" id="imageFile" accept="image/*">
                                <small class="text-muted">Format: JPG, PNG, GIF. Maks 2MB</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Link URL</label>
                                <input type="text" class="form-control" name="link_url" id="linkUrl" value="#" placeholder="#">
                                <small class="text-muted">URL tujuan saat banner diklik</small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Display Text - Title</label>
                                <input type="text" class="form-control" name="title" id="displayTitle" placeholder="Contoh: PROMO SPESIAL">
                                <small class="text-muted">Baris pertama (opsional)</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Display Text - Subtitle</label>
                                <input type="text" class="form-control" name="subtitle" id="displaySubtitle" placeholder="Contoh: Diskon 50% Semua Produk">
                                <small class="text-muted">Baris kedua (opsional)</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Display Text - Footer</label>
                                <input type="text" class="form-control" name="footer" id="displayFooter" placeholder="Contoh: Berlaku hingga 31 Desember">
                                <small class="text-muted">Baris ketiga (opsional)</small>
                            </div>
                            
                            <small class="text-info d-block mb-2"><i class="ft-info mr-1"></i> Kosongkan semua field Display Text jika tidak ingin menampilkan teks</small>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Urutan Tampil</label>
                                <input type="number" class="form-control" name="order_position" id="orderPosition" value="0" min="0">
                                <small class="text-muted">Angka lebih kecil tampil lebih dulu</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status</label>
                                <div class="custom-control custom-checkbox mt-2">
                                    <input type="checkbox" class="custom-control-input" name="is_active" id="isActive" checked>
                                    <label class="custom-control-label" for="isActive">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="modal-result"></div>
                    
                    <div class="form-group text-right mb-0">
                        <button type="button" class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink" data-dismiss="modal">
                            <i class="ft-x mr-1"></i>Batal
                        </button>
                        <button type="submit" id="btnSubmit" class="btn btn-glow btn-sm btn-bg-gradient-x-blue-green">
                            <i class="ft-save mr-1"></i>Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
// Helper function untuk notifikasi (fallback jika toastr tidak ada)
function showNotification(type, message) {
    if (typeof toastr !== 'undefined') {
        toastr[type](message);
    } else {
        alert(message);
    }
}

// Toggle between URL and File input
$('#imageType').on('change', function() {
    if ($(this).val() === 'url') {
        $('#urlInput').show();
        $('#fileInput').hide();
        $('#imageUrl').prop('required', true);
        $('#imageFile').prop('required', false).val('');
    } else {
        $('#urlInput').hide();
        $('#fileInput').show();
        $('#imageUrl').prop('required', false);
        $('#imageFile').prop('required', true);
    }
});

// Prevent modal from closing when clicking backdrop during upload
$('#bannerModal').on('hide.bs.modal', function (e) {
    if ($('#btnSubmit').prop('disabled')) {
        e.preventDefault();
        showNotification('warning', 'Mohon tunggu hingga proses upload selesai');
        return false;
    }
});

// Open Add Modal
function openAddModal() {
    $('#modalTitle').html('<i class="fa fa-plus mr-2"></i> Tambah Banner');
    $('#formAction').val('add');
    $('#bannerId').val('');
    $('#formBanner')[0].reset();
    $('#imageType').val('url').trigger('change');
    $('#isActive').prop('checked', true);
    $('#modal-result').html('');
    $('#bannerModal').modal('show');
}

// Open Edit Modal
function openEditModal(id) {
    $('#modalTitle').html('<i class="fa fa-edit mr-2"></i> Edit Banner');
    $('#formAction').val('update');
    $('#bannerId').val(id);
    $('#modal-result').html('');
    
    $.ajax({
        url: window.location.href,
        method: 'GET',
        data: { action: 'get_banner', id: id },
        dataType: 'json',
        beforeSend: function() {
            $('#formBanner').find('input, select, button').prop('disabled', true);
        },
        success: function(response) {
            if (response.success) {
                const banner = response.data;
                
                // Check if URL or uploaded file
                if (banner.image_url.indexOf('<?= $UPLOAD_URL; ?>') !== -1) {
                    $('#imageType').val('url');
                } else {
                    $('#imageType').val('url');
                }
                $('#imageType').trigger('change');
                
                $('#imageUrl').val(banner.image_url);
                $('#linkUrl').val(banner.link_url);
                $('#displayTitle').val(banner.title || '');
                $('#displaySubtitle').val(banner.subtitle || '');
                $('#displayFooter').val(banner.footer || '');
                $('#orderPosition').val(banner.order_position);
                $('#isActive').prop('checked', banner.is_active == 1);
                
                $('#bannerModal').modal('show');
            } else {
                showNotification('error', response.msg);
            }
        },
        error: function() {
            showNotification('error', 'Gagal memuat data banner');
        },
        complete: function() {
            $('#formBanner').find('input, select, button').prop('disabled', false);
        }
    });
}

// Submit Form
$('#formBanner').on('submit', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    // Validasi form
    const imageType = $('#imageType').val();
    const imageUrl = $('#imageUrl').val().trim();
    const imageFile = $('#imageFile')[0].files[0];
    
    if (imageType === 'url' && !imageUrl) {
        showNotification('error', 'URL gambar tidak boleh kosong');
        return false;
    }
    
    if (imageType === 'upload' && !imageFile && $('#formAction').val() === 'add') {
        showNotification('error', 'Silakan pilih file gambar');
        return false;
    }
    
    const formData = new FormData(this);
    const $submitBtn = $('#formBanner').find('button[type="submit"]');
    const originalBtnText = $submitBtn.html();
    
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        cache: false,
        beforeSend: function() {
            $submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i>Menyimpan...');
            $('#formBanner').find('input, select, textarea').prop('disabled', true);
        },
        success: function(response) {
            if (response.success) {
                showNotification('success', response.msg);
                $('#bannerModal').modal('hide');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotification('error', response.msg);
                $submitBtn.prop('disabled', false).html(originalBtnText);
                $('#formBanner').find('input, select, textarea').prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('Upload error:', error);
            showNotification('error', 'Terjadi kesalahan saat menyimpan banner. Silakan coba lagi.');
            $submitBtn.prop('disabled', false).html(originalBtnText);
            $('#formBanner').find('input, select, textarea').prop('disabled', false);
        }
    });
    
    return false;
});

// Delete Banner
function deleteBanner(id) {
    if (!confirm('Yakin ingin menghapus banner ini?')) {
        return;
    }
    
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: { action: 'delete', id: id },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('success', response.msg);
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                showNotification('error', response.msg);
            }
        },
        error: function() {
            showNotification('error', 'Terjadi kesalahan saat menghapus banner');
        }
    });
}
</script>

<?php
require '../../lib/footer_admin.php';
?>
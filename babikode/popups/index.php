<?php
require '../../mainconfig.php';
require '../../lib/check_session_admin.php';

// ========== CONFIGURATION ==========
$UPLOAD_PATH = '../../assets/images/popups/'; // Path untuk upload popup image
$UPLOAD_URL = base_url('assets/images/popups/'); // URL untuk akses popup image

// Create upload directory if not exists
if (!file_exists($UPLOAD_PATH)) {
    mkdir($UPLOAD_PATH, 0755, true);
}

// ========== HANDLE AJAX REQUEST ==========
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    
    // GET POPUP DATA FOR EDIT
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_popup') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $query = mysqli_query($db, "SELECT * FROM news_popup WHERE id = $id");
        if ($popup = mysqli_fetch_assoc($query)) {
            echo json_encode(['success' => true, 'data' => $popup]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Popup tidak ditemukan']);
        }
        exit;
    }
    
    // ADD POPUP
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
        $title = isset($_POST['title']) ? protect(trim($_POST['title'])) : '';
        $description = isset($_POST['description']) ? protect(trim($_POST['description'])) : '';
        
        if (empty($title) || empty($description)) {
            echo json_encode(['success' => false, 'msg' => 'Title dan Description wajib diisi']);
            exit;
        }
        
        $image_url = '';
        $has_image = isset($_POST['has_image']) ? (int)$_POST['has_image'] : 0;
        
        // Handle image upload if exists
        if ($has_image && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0) {
            $file = $_FILES['image_file'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'msg' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, atau GIF']);
                exit;
            }
            
           // Maksimal 10MB
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode([
                'success' => false,
                'msg' => 'Ukuran file maksimal 10MB'
            ]);
            exit;
        }
                    
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'popup_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target = $UPLOAD_PATH . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $image_url = $UPLOAD_URL . $filename;
            } else {
                echo json_encode(['success' => false, 'msg' => 'Gagal upload file']);
                exit;
            }
        }
        
        $button_text = isset($_POST['button_text']) ? protect(trim($_POST['button_text'])) : NULL;
        $button_url = isset($_POST['button_url']) ? protect(trim($_POST['button_url'])) : NULL;
        $is_active = 0; // Default non-aktif saat ditambah
        
        // Escape NULL values properly
        $image_sql = empty($image_url) ? 'NULL' : "'$image_url'";
        $button_text_sql = empty($button_text) ? 'NULL' : "'$button_text'";
        $button_url_sql = empty($button_url) ? 'NULL' : "'$button_url'";
        
        $insert = mysqli_query($db, "INSERT INTO news_popup (title, description, image, button_text, button_url, is_active, created_at) 
                                     VALUES ('$title', '$description', $image_sql, $button_text_sql, $button_url_sql, $is_active, NOW())");
        
        if ($insert) {
            echo json_encode(['success' => true, 'msg' => 'Popup berhasil ditambahkan']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal menambahkan popup: ' . mysqli_error($db)]);
        }
        exit;
    }
    
    // UPDATE POPUP
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'msg' => 'ID tidak valid']);
            exit;
        }
        
        $title = isset($_POST['title']) ? protect(trim($_POST['title'])) : '';
        $description = isset($_POST['description']) ? protect(trim($_POST['description'])) : '';
        
        if (empty($title) || empty($description)) {
            echo json_encode(['success' => false, 'msg' => 'Title dan Description wajib diisi']);
            exit;
        }
        
        $image_url = '';
        $update_image = false;
        $has_image = isset($_POST['has_image']) ? (int)$_POST['has_image'] : 0;
        
        // Handle image update
        if ($has_image && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === 0) {
            $file = $_FILES['image_file'];
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            
            if (!in_array($file['type'], $allowed_types)) {
                echo json_encode(['success' => false, 'msg' => 'Tipe file tidak diizinkan']);
                exit;
            }
            
            // Maksimal 10MB
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode([
                'success' => false,
                'msg' => 'Ukuran file maksimal 10MB'
            ]);
            exit;
        }
            
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'popup_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $target = $UPLOAD_PATH . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target)) {
                $image_url = $UPLOAD_URL . $filename;
                $update_image = true;
                
                // Delete old image if it was uploaded
                $old_popup = mysqli_fetch_assoc(mysqli_query($db, "SELECT image FROM news_popup WHERE id = $id"));
                if ($old_popup && !empty($old_popup['image']) && strpos($old_popup['image'], $UPLOAD_URL) !== false) {
                    $old_file = str_replace($UPLOAD_URL, $UPLOAD_PATH, $old_popup['image']);
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
            }
        } elseif (!$has_image) {
            // User wants to remove image
            $update_image = true;
            $image_url = NULL;
            
            // Delete old image
            $old_popup = mysqli_fetch_assoc(mysqli_query($db, "SELECT image FROM news_popup WHERE id = $id"));
            if ($old_popup && !empty($old_popup['image']) && strpos($old_popup['image'], $UPLOAD_URL) !== false) {
                $old_file = str_replace($UPLOAD_URL, $UPLOAD_PATH, $old_popup['image']);
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
        }
        
        $button_text = isset($_POST['button_text']) ? protect(trim($_POST['button_text'])) : NULL;
        $button_url = isset($_POST['button_url']) ? protect(trim($_POST['button_url'])) : NULL;
        
        $button_text_sql = empty($button_text) ? 'NULL' : "'$button_text'";
        $button_url_sql = empty($button_url) ? 'NULL' : "'$button_url'";
        
        $update_sql = "UPDATE news_popup SET 
                      title = '$title',
                      description = '$description',
                      button_text = $button_text_sql,
                      button_url = $button_url_sql,
                      updated_at = NOW()";
        
        if ($update_image) {
            $image_sql = is_null($image_url) ? 'NULL' : "'$image_url'";
            $update_sql .= ", image = $image_sql";
        }
        
        $update_sql .= " WHERE id = $id";
        
        $update = mysqli_query($db, $update_sql);
        
        if ($update) {
            echo json_encode(['success' => true, 'msg' => 'Popup berhasil diupdate. Perubahan akan tampil ke semua user.']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal mengupdate popup: ' . mysqli_error($db)]);
        }
        exit;
    }
    
    // ACTIVATE POPUP (hanya 1 yang bisa aktif)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'msg' => 'ID tidak valid']);
            exit;
        }
        
        // Nonaktifkan semua popup
        mysqli_query($db, "UPDATE news_popup SET is_active = 0");
        
        // Aktifkan popup yang dipilih dan update timestamp agar muncul ke semua user
        $activate = mysqli_query($db, "UPDATE news_popup SET is_active = 1, updated_at = NOW() WHERE id = $id");
        
        if ($activate) {
            echo json_encode(['success' => true, 'msg' => 'Popup berhasil diaktifkan. Popup lain otomatis dinonaktifkan.']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal mengaktifkan popup']);
        }
        exit;
    }
    
    // DEACTIVATE POPUP
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deactivate') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'msg' => 'ID tidak valid']);
            exit;
        }
        
        $deactivate = mysqli_query($db, "UPDATE news_popup SET is_active = 0 WHERE id = $id");
        
        if ($deactivate) {
            echo json_encode(['success' => true, 'msg' => 'Popup berhasil dinonaktifkan']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal menonaktifkan popup']);
        }
        exit;
    }
    
    // DELETE POPUP
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'msg' => 'ID tidak valid']);
            exit;
        }
        
        // Get popup data to delete image file
        $popup = mysqli_fetch_assoc(mysqli_query($db, "SELECT image FROM news_popup WHERE id = $id"));
        
        $delete = mysqli_query($db, "DELETE FROM news_popup WHERE id = $id");
        
        if ($delete) {
            // Delete image file if it was uploaded
            if ($popup && !empty($popup['image']) && strpos($popup['image'], $UPLOAD_URL) !== false) {
                $file = str_replace($UPLOAD_URL, $UPLOAD_PATH, $popup['image']);
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            
            // Delete related viewed records
            mysqli_query($db, "DELETE FROM news_popup_viewed WHERE popup_id = $id");
            
            echo json_encode(['success' => true, 'msg' => 'Popup berhasil dihapus']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Gagal menghapus popup']);
        }
        exit;
    }
    
    exit;
}

// ========== REGULAR PAGE ==========
$page_type = 'popup';
$page_name = 'Kelola Popup News';
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
                    <li class="breadcrumb-item active">Popup</li>
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
                        <i class="ft-plus mr-1"></i> Tambah Popup
                    </button>
                </div>
                <div class="card-body pt-0">
                    <div id="body-result"></div>
                    <?php require '../../lib/flash_message.php'; ?>
                    
                    <div class="alert alert-info">
                        <i class="ft-info mr-1"></i> <strong>Info:</strong> Hanya 1 popup yang bisa aktif. Saat mengaktifkan popup, popup lain akan otomatis dinonaktifkan. Perubahan pada popup akan memunculkan kembali popup ke semua user.
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr class="text-uppercase">
                                    <th width="5%">ID</th>
                                    <th width="15%">Title</th>
                                    <th width="20%">Description</th>
                                    <th width="10%">Image</th>
                                    <th width="10%">Button</th>
                                    <th width="8%">Status</th>
                                    <th width="12%">Tanggal</th>
                                    <th width="20%">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $query = mysqli_query($db, "SELECT * FROM news_popup ORDER BY created_at DESC");
                                
                                if (mysqli_num_rows($query) == 0) {
                                    echo '<tr><td colspan="8" align="center">Belum ada popup.</td></tr>';
                                }
                                
                                while ($popup = mysqli_fetch_assoc($query)) {
                                    $status_color = $popup['is_active'] == 1 ? 'success' : 'secondary';
                                    $status_text = $popup['is_active'] == 1 ? 'Aktif' : 'Nonaktif';
                                ?>
                                    <tr>
                                        <td><?= $popup['id']; ?></td>
                                        <td><strong><?= htmlspecialchars($popup['title']); ?></strong></td>
                                        <td>
                                            <small><?= nl2br(htmlspecialchars(substr($popup['description'], 0, 100))); ?><?= strlen($popup['description']) > 100 ? '...' : ''; ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($popup['image'])): ?>
                                                <img src="<?= $popup['image']; ?>" alt="Popup" class="img-fluid rounded" style="max-height: 60px;">
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($popup['button_text'])): ?>
                                                <small><strong><?= htmlspecialchars($popup['button_text']); ?></strong><br>
                                                <span class="text-muted"><?= htmlspecialchars($popup['button_url']); ?></span></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge badge-<?= $status_color; ?>"><?= $status_text; ?></span></td>
                                        <td class="text-nowrap">
                                            <small>
                                                <strong>Dibuat:</strong> <?= date('d/m/Y H:i', strtotime($popup['created_at'])); ?><br>
                                                <strong>Update:</strong> <?= date('d/m/Y H:i', strtotime($popup['updated_at'])); ?>
                                            </small>
                                        </td>
                                        <td class="text-nowrap">
                                            <?php if ($popup['is_active'] == 0): ?>
                                                <button class="btn btn-glow btn-sm btn-bg-gradient-x-blue-green mb-1" onclick="activatePopup(<?= $popup['id']; ?>)">
                                                    <i class="ft-check mr-1"></i>Aktifkan
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-glow btn-sm btn-bg-gradient-x-orange-yellow mb-1" onclick="deactivatePopup(<?= $popup['id']; ?>)">
                                                    <i class="ft-x mr-1"></i>Nonaktifkan
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-glow btn-sm btn-bg-gradient-x-blue-cyan mb-1" onclick="openEditModal(<?= $popup['id']; ?>)">
                                                <i class="ft-edit mr-1"></i>Edit
                                            </button>
                                            <button class="btn btn-glow btn-sm btn-bg-gradient-x-red-pink" onclick="deletePopup(<?= $popup['id']; ?>)">
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

<!-- Modal Add/Edit Popup -->
<div class="modal fade" id="popupModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitle"><i class="fa fa-bullhorn mr-2"></i> Tambah Popup</h5>
                <button type="button" class="close text-white" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="formPopup" enctype="multipart/form-data">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="popupId" value="">
                    
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" id="popupTitle" placeholder="Contoh: 🎉 Promo Spesial Akhir Tahun!" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Description <span class="text-danger">*</span></label>
                                <textarea class="form-control" name="description" id="popupDescription" rows="4" placeholder="Masukkan deskripsi popup. Bisa multi-line." required></textarea>
                                <small class="text-muted">Support multi-line. Tekan Enter untuk baris baru.</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Gambar (Opsional)</label>
                                <div class="custom-control custom-checkbox mb-2">
                                    <input type="checkbox" class="custom-control-input" name="has_image" id="hasImage" value="1">
                                    <label class="custom-control-label" for="hasImage">Tampilkan Gambar</label>
                                </div>
                                <input type="file" class="form-control" name="image_file" id="imageFile" accept="image/*" disabled>
                                <small class="text-muted">Format: JPG, PNG, GIF. Maks 2MB</small>
                            </div>
                            
                            <div id="currentImagePreview" style="display: none;">
                                <label>Gambar Saat Ini:</label><br>
                                <img id="currentImage" src="" alt="Current" class="img-fluid rounded mb-2" style="max-height: 120px;">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Button Text (Opsional)</label>
                                <input type="text" class="form-control" name="button_text" id="buttonText" placeholder="Contoh: Top Up Sekarang">
                                <small class="text-muted">Kosongkan jika tidak perlu tombol</small>
                            </div>
                            
                            <div class="form-group">
                                <label>Button URL (Opsional)</label>
                                <input type="text" class="form-control" name="button_url" id="buttonUrl" placeholder="Contoh: /topup">
                                <small class="text-muted">URL tujuan tombol (relatif atau absolut)</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="ft-alert-triangle mr-1"></i> <strong>Catatan:</strong> Popup baru tidak langsung aktif. Anda perlu mengaktifkannya manual setelah disimpan.
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
// Helper function untuk notifikasi
function showNotification(type, message) {
    if (typeof toastr !== 'undefined') {
        toastr[type](message);
    } else {
        alert(message);
    }
}

// Toggle image input
$('#hasImage').on('change', function() {
    if ($(this).is(':checked')) {
        $('#imageFile').prop('disabled', false);
    } else {
        $('#imageFile').prop('disabled', true).val('');
    }
});

// Open Add Modal
function openAddModal() {
    $('#modalTitle').html('<i class="fa fa-plus mr-2"></i> Tambah Popup');
    $('#formAction').val('add');
    $('#popupId').val('');
    $('#formPopup')[0].reset();
    $('#hasImage').prop('checked', false).trigger('change');
    $('#currentImagePreview').hide();
    $('#modal-result').html('');
    $('#popupModal').modal('show');
}

// Open Edit Modal
function openEditModal(id) {
    $('#modalTitle').html('<i class="fa fa-edit mr-2"></i> Edit Popup');
    $('#formAction').val('update');
    $('#popupId').val(id);
    $('#modal-result').html('');
    $('#currentImagePreview').hide();
    
    $.ajax({
        url: window.location.href,
        method: 'GET',
        data: { action: 'get_popup', id: id },
        dataType: 'json',
        beforeSend: function() {
            $('#formPopup').find('input, select, button, textarea').prop('disabled', true);
        },
        success: function(response) {
            if (response.success) {
                const popup = response.data;
                
                $('#popupTitle').val(popup.title);
                $('#popupDescription').val(popup.description);
                $('#buttonText').val(popup.button_text || '');
                $('#buttonUrl').val(popup.button_url || '');
                
                // Handle image
                if (popup.image) {
                    $('#hasImage').prop('checked', true).trigger('change');
                    $('#currentImage').attr('src', popup.image);
                    $('#currentImagePreview').show();
                } else {
                    $('#hasImage').prop('checked', false).trigger('change');
                }
                
                $('#popupModal').modal('show');
            } else {
                showNotification('error', response.msg);
            }
        },
        error: function() {
            showNotification('error', 'Gagal memuat data popup');
        },
        complete: function() {
            $('#formPopup').find('input, select, button, textarea').prop('disabled', false);
        }
    });
}

// Submit Form
$('#formPopup').on('submit', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const formData = new FormData(this);
    const $submitBtn = $('#formPopup').find('button[type="submit"]');
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
            $('#formPopup').find('input, select, textarea').prop('disabled', true);
        },
        success: function(response) {
            if (response.success) {
                showNotification('success', response.msg);
                $('#popupModal').modal('hide');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                showNotification('error', response.msg);
                $submitBtn.prop('disabled', false).html(originalBtnText);
                $('#formPopup').find('input, select, textarea').prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error:', error);
            showNotification('error', 'Terjadi kesalahan saat menyimpan popup.');
            $submitBtn.prop('disabled', false).html(originalBtnText);
            $('#formPopup').find('input, select, textarea').prop('disabled', false);
        }
    });
    
    return false;
});

// Activate Popup
function activatePopup(id) {
    if (!confirm('Yakin ingin mengaktifkan popup ini? Popup lain akan otomatis dinonaktifkan dan popup ini akan muncul ke semua user.')) {
        return;
    }
    
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: { action: 'activate', id: id },
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
            showNotification('error', 'Terjadi kesalahan saat mengaktifkan popup');
        }
    });
}

// Deactivate Popup
function deactivatePopup(id) {
    if (!confirm('Yakin ingin menonaktifkan popup ini?')) {
        return;
    }
    
    $.ajax({
        url: window.location.href,
        method: 'POST',
        data: { action: 'deactivate', id: id },
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
showNotification('error', 'Terjadi kesalahan saat menonaktifkan popup');
}
});
}
// Delete Popup
function deletePopup(id) {
if (!confirm('Yakin ingin menghapus popup ini? Data yang sudah dihapus tidak dapat dikembalikan.')) {
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
        showNotification('error', 'Terjadi kesalahan saat menghapus popup');
    }
});
}
</script>
<?php
require '../../lib/footer_admin.php';
?>
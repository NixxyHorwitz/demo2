<?php
require '../../mainconfig.php';
require '../../lib/check_session_admin.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$page_type = 'tele_ai'; 
$page_name = 'Konfigurasi Bot Telegram';

// --- PROSES AJAX REQUEST ---
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) and strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    if (!$csrf_token) {
        exit(header("HTTP/1.1 403 No direct script access allowed!"));
    }

    if (isset($_POST['action']) && $_POST['action'] == 'sync_webhook') {
        $settings = $model->db_query($db, "tele_token", "settings")['rows'];
        $token = $settings['tele_token'];
        if (empty($token)) {
            $result_msg = ['response' => 'error', 'msg' => 'Telegram Token belum diisi.'];
            goto end_process;
        }

        $webhook_url = base_url('api/telehook.php');
        $api_url = "https://api.telegram.org/bot$token/setWebhook?url=" . urlencode($webhook_url);
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $res = json_decode($response, true);
        if ($res && $res['ok']) {
            $result_msg = ['response' => 'success', 'msg' => 'Webhook berhasil disinkronkan!<br>Endpoint: '.$webhook_url];
        } else {
            $err = isset($res['description']) ? $res['description'] : 'Gagal terhubung ke Telegram API';
            $result_msg = ['response' => 'error', 'msg' => 'Gagal sinkron webhook: ' . $err];
        }
        goto end_process;
    }

    if (isset($_POST['tele_token'])) {
        $input_post = array(
            'tele_admin_id' => protect($_POST['tele_admin_id'] ?? ''),
            'tele_token' => protect($_POST['tele_token'] ?? ''),
            'tele_openai_key' => protect($_POST['tele_openai_key'] ?? ''),
            'tele_system_prompt' => mysqli_real_escape_string($db, trim($_POST['tele_system_prompt'] ?? ''))
        );

        $update = $model->db_update($db, "settings", $input_post, "id = '1'");
        
        if ($update) {
            $result_msg = ['response' => 'success', 'msg' => 'Konfigurasi Bot Telegram berhasil disimpan.'];
        } else {
            $result_msg = ['response' => 'error', 'msg' => 'Gagal menyimpan: ' . mysqli_error($db)];
        }
    }

end_process:
    if (isset($result_msg)) { ?>
        <script type="text/javascript">
            Swal.fire({
                icon: "<?= $result_msg['response']; ?>",
                title: "<?= ($result_msg['response'] == 'success') ? 'Yeay!' : 'Ups!'; ?>",
                html: "<?= $result_msg['msg']; ?>",
                customClass: { confirmButton: 'btn btn-glow btn-bg-gradient-x-blue-green' },
                buttonsStyling: false,
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed && "<?= $result_msg['response']; ?>" == "success") {
                    window.location.reload(); 
                }
            });
        </script>
<?php
    }
    exit();
}

require '../../lib/header_admin.php';
$web_ai = $model->db_query($db, "tele_admin_id, tele_token, tele_openai_key, tele_system_prompt", "settings", "id = '1'")['rows'];

$defaultPrompt = <<<EOT
KAMU ADALAH ADMIN CUSTOMER SERVICE WANITA dari \$namaWeb.

================ IDENTITAS =================
- Perempuan
- Ramah, santai, natural
- Gaya chat seperti admin asli (bukan robot)
- Sedikit manja & friendly
- DILARANG mengatakan atau menyarankan:
  "mulai dari nominal kecil", "coba kecil dulu", atau kalimat serupa

================ CARA MENJAWAB (WAJIB) =================
1. Baca pertanyaan user dengan TELITI
2. Jawab SESUAI konteks
3. Jika belum jelas → WAJIB tanya balik dengan halus
4. Jangan langsung asumsi user sudah investasi
5. Jawaban singkat, jelas, natural

================ TAMBAHAN PENTING =================

JIKA user bilang:
- scam
- hoax
- penipu
- penipuan
- tidak percaya
- takut ditipu

MAKA:
- Jawab dengan santai & meyakinkan (jangan kaku)
- Bilang sudah banyak member WD
- Arahkan lihat bukti di channel Telegram

WAJIB kirim link channel ini:
\$linkch

Contoh gaya:
"Tenang kak 😊 bukan scam kok
Sudah banyak member yang WD juga 💸
Bukti penarikan bisa lihat di sini ya 👉 \$linkch"

Lalu tetap arahkan ke website di akhir.

================ STRATEGI PENTING (WAJIB IKUTI) =================
SETIAP USER DIBAGI 2 KONDISI:

================ [1] JIKA USER BELUM INVESTASI =================
(TIDAK ADA bukti sudah depo / join)

MAKA:
- JANGAN bahas kontrak
- JANGAN bahas saldo tertahan
- JANGAN bahas profit belum bisa ditarik

FOKUS:
- Profit masuk setiap hari 😊
- Bisa WD setiap hari 💸
- Arahkan user untuk mulai dari nominal kecil
- Bangun kepercayaan

Jika ragu → tanya:
"Udah join/invest belum kak? 😊"

================ [2] JIKA USER SUDAH INVESTASI =================
(Ciri-ciri:)
- Komplain WD
- Bilang saldo tidak bisa ditarik
- Bilang profit tidak masuk
- Bilang sudah depo / sudah jalan

MAKA:
BARU BOLEH jelaskan:

- Ada 2 saldo:
  1. Saldo profit (dari investasi)
  2. Saldo utama (yang bisa ditarik)

- Saldo profit:
  ➤ Akan masuk ke saldo utama setelah kontrak selesai

- Saldo utama:
  ➤ Bisa ditarik setiap hari
  ➤ Proses maksimal 1x24 jam

Jelaskan dengan HALUS, jangan bikin user panik

================ JAWABAN BERDASARKAN PERTANYAAN =================

[REAL / AMAN]
- Yakinkan santai
- Suruh coba nominal kecil

[DEPOSIT]
- Pakai QRIS
- Semua bank & e-wallet bisa

[WD / TARIK]
- Kalau BELUM jelas status user → tanya dulu
- Kalau BELUM investasi → bilang bisa WD harian
- Kalau SUDAH investasi & gagal WD → jelaskan sistem saldo

[PROFIT]
- Masuk setiap hari
- Jangan bahas kontrak kalau belum investasi

[CARA]
- Jawab singkat step by step

================ GAYA BICARA =================
- Panggil: Kak
- Gunakan emoji: 😊✨💸😘
- Santai, natural
- 1–3 kalimat (kecuali perlu)

================ PENUTUP WAJIB =================
Selalu arahkan ke website:
\$linkWeb

Contoh:
- "Yuk kak langsung coba di website ya 😊"
- "Aku tunggu kak di website ya 😘"

================ LARANGAN =================
- Jangan langsung bahas kontrak ke user baru
- Jangan bikin user takut / ragu
- Jangan jawab kaku seperti robot
- Jangan bertentangan dengan aturan ini
EOT;

$current_prompt = $web_ai['tele_system_prompt'];
if (empty(trim($current_prompt))) {
    $current_prompt = $defaultPrompt;
}
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
                    <li class="breadcrumb-item active">Pengaturan</li>
                    <li class="breadcrumb-item active">Bot Telegram</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content-body">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title float-left">
                        <i class="ft-message-square"></i> <?= $page_name; ?>
                    </h4>
                    <div class="float-right">
                        <a href="javascript:;" onclick="syncWebhook()" class="btn btn-glow btn-bg-gradient-x-blue-green" id="btn-sync">
                            <i class="ft-refresh-cw"></i> Sync Webhook
                        </a>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div id="body-result"></div>
                    
                    <form method="POST" class="row" id="form-tele">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <strong><i class="ft-info"></i> Informasi:</strong> Pastikan Anda telah mensetting Telegram Token. Setelah data disimpan, klik 'Sync Webhook' untuk menghubungkan bot ke website Anda.<br>
                                Endpoint Webhook: <code><?= base_url('api/telehook.php') ?></code>
                            </div>
                        </div>

                        <div class="col-md-12"><hr><h5><i class="ft-settings"></i> Konfigurasi Bot</h5></div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Telegram Bot Token <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="tele_token" value="<?= $web_ai['tele_token']; ?>" placeholder="123456789:AAH..." required>
                                <small class="text-muted">Dapatkan dari @BotFather</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Telegram Admin ID (Opsional)</label>
                                <input type="text" class="form-control" name="tele_admin_id" value="<?= $web_ai['tele_admin_id']; ?>" placeholder="123456789">
                                <small class="text-muted">Tujuan log bot dikirim ke Telegram ID admin.</small>
                            </div>
                        </div>

                        <div class="col-md-12"><hr><h5><i class="ft-cpu"></i> Konfigurasi OpenAI</h5></div>
                        
                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="form-label">OpenAI API Key <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="tele_openai_key" value="<?= $web_ai['tele_openai_key']; ?>" placeholder="sk-proj-..." required>
                                <small class="text-muted">Dapatkan API Key di <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></small>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="form-group">
                                <label class="form-label">System Prompt (Konsep Otak/Bot CS)</label>
                                <textarea class="form-control" name="tele_system_prompt" rows="15" placeholder="Masukkan instruksi otak bot..."><?= htmlspecialchars($current_prompt); ?></textarea>
                                <small class="text-muted">Instruksi bagaimana bot harus bertindak. Kosongkan ini untuk tidak menggunakan prompt (disarankan diisi detail).</small>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="col-md-12 mt-2">
                            <div class="form-group">
                                <a href="javascript:;" onclick="btn_post_tele();" id="btn_post" class="btn btn-glow btn-bg-gradient-x-purple-blue float-right">
                                    <i class="fa fa-save"></i> Simpan Konfigurasi
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script type="text/javascript">
    function btn_post_tele() {
        var form = $('#form-tele')[0]; 
        var formData = new FormData(form); 
        
        formData.append('csrf_token', '<?= csrf_token(); ?>');

        $.ajax({
            type: 'POST',
            url: '<?= base_url('babikode/tele_ai/'); ?>',
            data: formData,
            contentType: false, 
            processData: false, 
            success: function(data) {
                $('#btn_post').removeClass('disabled');
                $('#body-result').html(data);
            },
            error: function() {
                $('#btn_post').removeClass('disabled');
                $('#body-result').html('<div class="alert alert-danger">Terjadi kesalahan sistem!</div>');
            },
            beforeSend: function() {
                $('#btn_post').addClass('disabled');
                $('#body-result').html('<div class="progress mb-4"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%">Menyimpan Data...</div></div>');
            }
        });
    }

    function syncWebhook() {
        Swal.fire({
            title: 'Sync Webhook?',
            text: "Ini akan menghubungkan bot Telegram ke web Anda.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#00cef9',
            cancelButtonColor: '#ff5858',
            confirmButtonText: 'Ya, Sync!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    type: 'POST',
                    url: '<?= base_url('babikode/tele_ai/'); ?>',
                    data: {
                        action: 'sync_webhook',
                        csrf_token: '<?= csrf_token(); ?>'
                    },
                    success: function(data) {
                        $('#btn-sync').removeClass('disabled');
                        $('#body-result').html(data);
                    },
                    error: function() {
                        $('#btn-sync').removeClass('disabled');
                        $('#body-result').html('<div class="alert alert-danger">Terjadi kesalahan sistem!</div>');
                    },
                    beforeSend: function() {
                        $('#btn-sync').addClass('disabled');
                        $('#body-result').html('<div class="progress mb-4"><div class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width: 100%">Menyinkronkan...</div></div>');
                    }
                });
            }
        });
    }
</script>

<?php require '../../lib/footer_admin.php'; ?>

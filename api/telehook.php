<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Jakarta');

require '../mainconfig.php';

// ==========================================
// 1. KONFIGURASI DARI DATABASE
// ==========================================
$settingsQuery = $model->db_query($db, "tele_admin_id, tele_token, tele_openai_key, tele_system_prompt, title, link_telegram, min_depo, min_wd", "settings", "id = '1'");
$settings = $settingsQuery['rows'];

$adminId       = $settings['tele_admin_id'];
$telegramToken = $settings['tele_token'];
$openaiApiKey  = $settings['tele_openai_key'];

$namaWeb = $settings['title'];
$linkWeb = base_url(); 
$linkch  = $settings['link_telegram'];
$minDepo = "Rp " . number_format($settings['min_depo'], 0, ',', '.');
$minWD   = "Rp " . number_format($settings['min_wd'], 0, ',', '.');

// Pastikan token terisi
if(empty($telegramToken)) exit("Bot token not configured");

// ==========================================
// 2. SYSTEM PROMPT (OTAK BOT)
// ==========================================
$systemPrompt = $settings['tele_system_prompt'];

// Jika system prompt di database kosong, gunakan default
if(empty(trim($systemPrompt))) {
$systemPrompt = <<<EOT
KAMU ADALAH ADMIN CUSTOMER SERVICE WANITA dari $namaWeb.

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
$linkch

Contoh gaya:
"Tenang kak 😊 bukan scam kok
Sudah banyak member yang WD juga 💸
Bukti penarikan bisa lihat di sini ya 👉 $linkch"

Lalu tetap arahkan ke website di akhir.

================ PENANGANAN PERTANYAAN KHUSUS =================

JIKA user bertanya tentang:

--- PROFIT BELUM MASUK / SALDO ---
(kata kunci: saldo tidak masuk, profit belum masuk, keuntungan belum cair, pencairan, saldo utama)

MAKA:
- Jawab dengan tenang & meyakinkan
- Jelaskan bahwa profit akan otomatis masuk ke saldo utama setelah kontrak selesai

Contoh gaya:
"Sabar ya kak 😊
Saldo profit atau keuntungan Kaka nanti akan otomatis masuk ke saldo utama setelah masa kontrak selesai ya ✨"

---

--- WD HARIAN / BISA TARIK ---
(kata kunci: wd harian, bisa wd tiap hari, withdraw setiap hari)

MAKA:
- Tegaskan bisa WD setiap hari
- Sebutkan minimal WD

Contoh gaya:
"Bisa ditarik setiap hari kok kak 😊
Minimal withdraw $minWD ya 💸"

---

--- PROFIT DIKUNCI / LOCK ---
(kata kunci: profit di lock, profit dikunci)

MAKA:
- Yakinkan bahwa tidak ada sistem lock
- Profit langsung masuk saldo

Contoh gaya:
"Tenang kak 😊
Profit tidak di lock ya 💸
Langsung masuk ke saldo dan bisa digunakan atau ditarik kapan saja"

---

--- CARA WD ---
(kata kunci: cara wd, cara withdraw, gimana tarik)

MAKA:
- Jawab simple step by step

Contoh gaya:
"Gampang banget kak 😊
Tinggal masuk menu withdraw, isi data lalu submit ya 👍"

---

--- PERTANYAAN KONTRAK / KAPAN BISA TARIK ---
(kata kunci: kapan profit bisa ditarik, kapan cair, kontrak)

MAKA:
- Jelaskan sistem:
  ➤ Profit masuk setelah kontrak selesai
  ➤ Setelah itu baru bisa ditarik

Contoh gaya:
"Jadi gini kak 😊
Profit bisa ditarik setelah masa kontrak selesai ya ✨
Nanti otomatis masuk ke saldo utama, baru bisa di WD 💸"

================ INFORMASI PRODUK =================
- Semua produk bisa dibeli kapan saja
- Tidak harus berurutan
- Tidak ada syarat khusus
- User bebas pilih produk mana saja sesuai keinginan

Jika user bertanya:
- harus urut atau tidak
- bisa pilih produk bebas atau tidak
- ada syarat atau tidak

MAKA:
- Jawab dengan santai & meyakinkan
- Tegaskan bahwa semua produk bebas dipilih tanpa urutan dan tanpa syarat

Contoh gaya:
"Bebas kok kak 😊
Tidak harus urut ya, Kaka bisa pilih produk mana aja sesuai yang diinginkan ✨
Tanpa syarat juga kok 💸"

================ INFO MINIMAL TRANSAKSI =================
- Minimal depo / isi ulang / deposit: $minDepo
- Minimal WD / tarik / menarik / cair: $minWD

Jika user bertanya soal:
- minimal deposit
- minimal isi ulang
- minimal depo

Jawab:
"Minimal depositnya $minDepo ya kak 😊"

Jika user bertanya soal:
- minimal WD
- minimal tarik
- minimal penarikan
- minimal cair

Jawab:
"Minimal withdraw / penarikan $minWD ya kak 💸"

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
$linkWeb

Contoh:
- "Yuk kak langsung coba di website ya 😊"
- "Aku tunggu kak di website ya 😘"

================ LARANGAN =================
- Jangan langsung bahas kontrak ke user baru
- Jangan bikin user takut / ragu
- Jangan jawab kaku seperti robot
- Jangan bertentangan dengan aturan ini

EOT;
} else {
    // Replace placeholder string with actual variables
    $systemPrompt = str_replace(
    ['$namaWeb', '$linkWeb', '$linkch', '$minDepo', '$minWD'],
    [$namaWeb, $linkWeb, $linkch, $minDepo, $minWD],
    $systemPrompt
);
}

function getWaktuLengkap(){
    date_default_timezone_set('Asia/Jakarta');

    $hariList = [
        'Sunday'=>'Minggu',
        'Monday'=>'Senin',
        'Tuesday'=>'Selasa',
        'Wednesday'=>'Rabu',
        'Thursday'=>'Kamis',
        'Friday'=>'Jumat',
        'Saturday'=>'Sabtu'
    ];

    $hari = $hariList[date('l')];
    $jam  = date('H:i');
    $jamInt = date('H');

    if ($jamInt < 11) {
        $waktu = "Pagi";
    } elseif ($jamInt < 15) {
        $waktu = "Siang";
    } elseif ($jamInt < 19) {
        $waktu = "Sore";
    } else {
        $waktu = "Malam";
    }

    return "Hari ini $hari, jam $jam WIB, waktu $waktu";
}

function forwardBotReply($userId, $userText, $botReply, $username, $firstName, $adminId, $token){
    if(empty($adminId)) return;

    $usernameText = ($username && $username != 'no_username') 
        ? "@".$username 
        : "(tidak ada username)";

    $msg = "🤖 BOT LOG\n\n";
    $msg .= "👤 Nama: $firstName\n";
    $msg .= "🔗 Username: $usernameText\n";
    $msg .= "🆔 ID: $userId\n\n";
    $msg .= "💬 Pertanyaan:\n$userText\n\n";
    $msg .= "🤖 Jawaban:\n$botReply";

    sendMessage($adminId, $msg, $token);
}

// ==========================================
// 3. FUNCTION
// ==========================================
function sendMessage($chatId, $text, $token, $replyMsgId = null){
    $url = "https://api.telegram.org/bot$token/sendMessage";

    $data = [
        'chat_id' => $chatId,
        'text' => $text
    ];

    if($replyMsgId){
        $data['reply_to_message_id'] = $replyMsgId;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function kirimTyping($chatId, $token){
    $url = "https://api.telegram.org/bot$token/sendChatAction";

    $data = [
        'chat_id' => $chatId,
        'action' => 'typing'
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function delayNatural(){
    usleep(rand(700000,1500000));
}

function salam(){
    $jam = date("H");
    if ($jam < 11) return "Pagi kak ☀️";
    if ($jam < 15) return "Siang kak 🌤";
    if ($jam < 19) return "Sore kak 🌇";
    return "Malam kak 🌙";
}

function rapikanText($text){
    $text = trim($text);

    // Kalau sudah rapi, skip
    if (substr_count($text, "\n") > 2) {
        return $text;
    }

    // Enter setelah emoji
    $text = preg_replace('/(😊|😘|💸|👍)/u', "$1\n", $text);

    // Enter sebelum kata penting
    $text = preg_replace('/\b(Dan|Jadi|Tapi|Kalau|Untuk)\b/u', "\n$1", $text);

    // Rapikan
    $text = preg_replace("/\n{3,}/", "\n", $text);

    return trim($text);
}

// ==========================================
// 4. TERIMA DATA TELEGRAM
// ==========================================
$update = json_decode(file_get_contents("php://input"), true);
if (!isset($update['message']['text'])) exit;

$chatId = $update['message']['chat']['id'];
$messageId = $update['message']['message_id'];
$text   = trim($update['message']['text']);
$username = $update['message']['from']['username'] ?? 'Tidak ada';
$firstName = $update['message']['from']['first_name'] ?? 'User';

$lower  = strtolower($text);

// ==========================================
// 5. COMMAND START
// ==========================================
if ($text == '/start') {
    sendMessage($chatId, salam()." 👋\nAdmin $namaWeb siap bantu ya kak 😊", $telegramToken);
    exit;
}

// ==========================================
// 6. RESPON CEPAT (FAST RESPONSE)
// ==========================================
if (strpos($lower,'link') !== false) {
    $msg = "Silakan akses websitenya ya kak 👉 $linkWeb";
    sendMessage($chatId, rapikanText($msg), $telegramToken, $messageId);
    exit;
}

// ==========================================
// 7. OPENAI (STATELESS TANPA HISTORY)
// ==========================================
function askAI($text,$apiKey,$systemPrompt){
    if(empty($apiKey)) return "Maaf kak, layanan AI sedang tidak aktif. Ada yang bisa dibantu? 😊";

    $waktuSekarang = getWaktuLengkap();
    $url = "https://api.openai.com/v1/chat/completions";

    $data = [
        "model" => "gpt-4o-mini",
        "messages" => [
            [
                "role"=>"system",
                "content"=>$systemPrompt . "\n\nINFO WAKTU REALTIME:\n" . $waktuSekarang . "\nGunakan informasi waktu ini jika relevan dalam jawaban."
            ],
            ["role"=>"user","content"=>"INFO WAKTU SEKARANG: $waktuSekarang\n\nUser: ".$text]
        ],
        "max_tokens" => 150,
        "temperature" => 0.9
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($data),
        CURLOPT_HTTPHEADER=>[
            "Content-Type: application/json",
            "Authorization: Bearer $apiKey"
        ],
        CURLOPT_RETURNTRANSFER=>true
    ]);

    $result = curl_exec($ch);

    if(curl_errno($ch)){
        return "Maaf kak, maksud nya gimana ya 🙏";
    }
    curl_close($ch);

    $res = json_decode($result,true);
    return $res['choices'][0]['message']['content'] ?? "Maaf kak, maksud nya gimana ya 🙏";
}

// ==========================================
// 8. KIRIM KE AI
// ==========================================
kirimTyping($chatId,$telegramToken);
delayNatural();

$reply = askAI($text,$openaiApiKey,$systemPrompt);
$replyp = rapikanText($reply);

sendMessage($chatId,$replyp,$telegramToken,$messageId);

// 🔥 KIRIM LOG KE ADMIN
forwardBotReply($chatId, $text, $replyp, $username, $firstName, $adminId, $telegramToken);
?>

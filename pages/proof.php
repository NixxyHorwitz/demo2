<?php
declare(strict_types=1);
require_once __DIR__ . '/../mainconfig.php';
require_once __DIR__ . '/../lib/check_session.php';
require_once __DIR__ . '/../lib/is_login.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Proof Generator</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Internal:wght@400;500;600;700&family=Inter:wght@400;500;600;700;800;900&family=Roboto+Slab:wght@600;700;800&display=swap" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        :root { --borcor: #e5e7eb; }
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; margin: 0; }
        .main-wrapper { display: flex; min-height: 100vh; gap: 2rem; padding: 2rem; }
        
        .editor-panel {
            flex: 1; max-width: 400px; background: white; padding: 2rem;
            border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            height: fit-content; position: sticky; top: 2rem; z-index: 100;
        }
        .preview-panel { flex: 1; display: flex; flex-direction: column; align-items: center; }
        .form-label { display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem; }
        .form-input {
            width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 0.5rem;
            font-size: 1rem; font-family: 'Inter', sans-serif; transition: border-color 0.2s;
        }
        .form-input:focus { outline: none; border-color: #1A8FE8; }
        .btn-save {
            width: 100%; background-color: #1A8FE8; color: white; border: none; padding: 1rem;
            border-radius: 0.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; margin-top: 1rem;
        }
        .btn-save:hover { background-color: #1570c0; }
        
        /* DANA Styles */
        .dana-container { max-width: 500px; background-color: white; min-height: 80vh; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-radius: 0.5rem; }
        .dana-header { background-color: #1A8FE8; border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; padding: 1rem 1.25rem; padding-top: 1.7rem; color: white; display: flex; align-items: center; }
        .qris-card { background-color: white; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); padding: 1.5rem; border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; position: relative; margin: 1rem 1rem 0; background-image: repeating-linear-gradient(45deg, transparent, transparent 100px, rgba(0, 168, 255, 0.03) 100px, rgba(0, 168, 255, 0.03) 200px); }
        .watermark-bg { position: absolute; inset: 0; opacity: 0.05; pointer-events: none; overflow:hidden; }
        .watermark-text { color: #1A8FE8; font-size: 3.75rem; font-weight: 700; transform: rotate(-40deg); position: absolute; }
        .watermark-text img { height: 3rem; }
        .qris-logo { height: 12rem; position: relative; z-index: 10; margin: 0 auto; display: block; }
        .transaction-info { display: flex; justify-content: space-between; font-size: 0.75rem; color: #9ca3af; position: relative; z-index: 10; }
        .dana-main { margin: 1rem; padding: 1.5rem; border: 1px solid var(--borcor); border-top: none; margin-top: 0; border-bottom-left-radius: 0.3rem; border-bottom-right-radius: 0.3rem; }
        .total-payment-box { background-color: #E3F2FD; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; }
        
        /* GOPAY Common & Overrides */
        .dotted-divider { border-top: 1px dashed #E0E0E0; margin: 16px 0; }
        .dotted-divider-dark { border-top: 1px dashed #2A2C31; margin: 16px 0; }
        .font-main-amount { font-family: 'Rupa Serif', 'LFT Etica Sheriff', 'Roboto Slab', Georgia, serif; font-weight: 700; }
        .war-trakjil-text { color: #00B8D4; font-weight: 900; font-size: 11px; font-style: italic; text-transform: uppercase; line-height: 0.9; text-align: center; transform: skewX(-10deg); text-shadow: 1px 1px 0px #fff, -1px -1px 0px #fff, 1px -1px 0px #fff, -1px 1px 0px #fff; }

        @media (max-width: 1024px) { .main-wrapper { flex-direction: column; padding: 0.5rem; } .editor-panel { max-width: 100%; position: static; } }
    </style>
</head>
<body>
    <div class="absolute top-4 left-4 z-50">
        <a href="javascript:history.back()" class="text-gray-600 font-medium hover:text-blue-500 bg-white px-4 py-2 rounded-lg shadow"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <div class="main-wrapper mt-10">
        <!-- Editor Panel -->
        <div class="editor-panel">
            <h2 class="text-2xl font-bold mb-6 text-gray-800">Bukti Transfer</h2>

            <div class="mb-4">
                <label class="form-label">Tipe Template</label>
                <select id="templateType" class="form-input" onchange="updateFormDisplay(); updatePreview();">
                    <option value="dana_payment">DANA - Pembayaran</option>
                    <option value="dana_receive">DANA - Terima Saldo</option>
                    <option value="gopay_light_pay">GOPAY (Light) - Transfer/Bayar</option>
                    <option value="gopay_light_receive" selected>GOPAY (Light) - Terima Saldo</option>
                    <option value="gopay_dark_topup">GOPAY (Dark) - Top Up</option>
                </select>
            </div>

            <!-- DANA FORM -->
            <div id="form_dana" class="form-group-wrapper hidden">
                <div class="mb-4">
                    <label class="form-label" id="lblDanaMerchant">Nama Penerima/Pengirim</label>
                    <input type="text" id="danaMerchant" class="form-input" value="DEFT BARBER" oninput="updatePreview()">
                </div>
                <div class="mb-4">
                    <label class="form-label">Nomor DANA (cth: 0857•••5875)</label>
                    <input type="text" id="danaReceiver" class="form-input" value="0857•••5875" oninput="updatePreview()">
                </div>
                <div class="mb-4">
                    <label class="form-label">Nominal (Rp)</label>
                    <input type="number" id="danaAmount" class="form-input" value="40000" oninput="updatePreview()">
                </div>
                <div class="flex gap-4 mb-4">
                    <div class="w-1/2">
                        <label class="form-label">Tanggal</label>
                        <input type="text" id="danaDate" class="form-input" value="01 Jan 2026" oninput="updatePreview()">
                    </div>
                    <div class="w-1/2">
                        <label class="form-label">Waktu</label>
                        <input type="text" id="danaTime" class="form-input" value="15:55" oninput="updatePreview()">
                    </div>
                </div>
            </div>

            <!-- GOPAY LIGHT FORM -->
            <div id="form_gopay_light" class="form-group-wrapper hidden">
                <div class="mb-4">
                    <label class="form-label" id="lblGopayLightName">Diterima dari / Pembayaran ke</label>
                    <input type="text" id="glName" class="form-input" value="ladang" oninput="updatePreview()">
                </div>
                <div class="mb-4">
                    <label class="form-label">GoPay Masking (cth: ****2078)</label>
                    <input type="text" id="glNumber" class="form-input" value="****2078" oninput="updatePreview()">
                </div>
                <div class="mb-4">
                    <label class="form-label">Jumlah Saldo (Rp)</label>
                    <input type="number" id="glAmount" class="form-input" value="100000" oninput="updatePreview()">
                </div>
                <div class="flex gap-4 mb-4">
                    <div class="w-1/2">
                        <label  class="form-label">Tanggal</label>
                        <input type="text" id="glDate" class="form-input" value="13 Mar 2026" oninput="updatePreview()">
                    </div>
                    <div class="w-1/2">
                        <label class="form-label">Waktu</label>
                        <input type="text" id="glTime" class="form-input" value="4:20" oninput="updatePreview()">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">ID Transaksi</label>
                    <input type="text" id="glTrx" class="form-input" value="0520260312212006c..." oninput="updatePreview()">
                </div>
            </div>

            <!-- GOPAY DARK FORM -->
            <div id="form_gopay_dark" class="form-group-wrapper hidden">
                <div class="mb-4">
                    <label class="form-label">Tipe Top Up (cth: GoPay Top Up)</label>
                    <input type="text" id="gdType" class="form-input" value="GoPay Top Up" oninput="updatePreview()">
                </div>
                <div class="mb-4">
                    <label class="form-label">Metode/Via (cth: Via Okeconnect)</label>
                    <input type="text" id="gdVia" class="form-input" value="Via Okeconnect" oninput="updatePreview()">
                </div>
                <div class="mb-4">
                    <label class="form-label">Total Top Up (Rp)</label>
                    <input type="number" id="gdAmount" class="form-input" value="42500" oninput="updatePreview()">
                </div>
                <div class="flex gap-4 mb-4">
                    <div class="w-1/2">
                        <label class="form-label">Tanggal</label>
                        <input type="text" id="gdDate" class="form-input" value="27 Mar 2026" oninput="updatePreview()">
                    </div>
                    <div class="w-1/2">
                        <label class="form-label">Waktu</label>
                        <input type="text" id="gdTime" class="form-input" value="13:16" oninput="updatePreview()">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label">ID Transaksi</label>
                    <input type="text" id="gdTrx" class="form-input" value="A120260327061606t..." oninput="updatePreview()">
                </div>
            </div>

            <button class="btn-save" onclick="enterFullscreen()">
                <i class="fas fa-expand"></i> Layar Penuh (Screenshot Manual)
            </button>
        </div>

        <!-- Preview Panel -->
        <div class="preview-panel">
            <div class="text-xl font-bold text-gray-800 mb-4">Live Preview</div>

            <!-- TPL 1 & 2: DANA -->
            <div class="receipt-wrapper w-full flex justify-center hidden" id="tpl_dana">
                <style>
                    .dana-qris-bg {
                        background-image: repeating-linear-gradient(45deg, transparent, transparent 100px, rgba(0, 168, 255, 0.03) 100px, rgba(0, 168, 255, 0.03) 200px);
                    }
                </style>
                <div id="wrapper_dana" class="w-full max-w-[400px] bg-[#f3f4f6] mx-auto pb-0" style="min-height: 100vh;">
                    <div class="w-full bg-white mx-auto shadow-sm" style="min-height: 100vh;">
                        <!-- Header with extended blue background -->
                        <div class="bg-[#1A8FE8]">
                            <div class="text-white px-3 pt-3 pb-2 flex items-center justify-center relative">
                                <i class="fas fa-chevron-left text-[16px] absolute left-4 cursor-pointer hover:opacity-80" onclick="exitFs()"></i>
                                <h1 class="text-[14px] font-semibold tracking-wide">Detail Transaksi</h1>
                            </div>

                            <!-- QRIS Card -->
                            <div class="mx-3 mt-2 relative">
                                <div class="bg-white dana-qris-bg relative shadow-[0_1px_2px_rgba(0,0,0,0.05)] px-5 pt-12 pb-6 rounded-t-[8px] overflow-hidden border-b border-gray-100">
                                    <!-- Watermark pattern -->
                                    <div class="absolute inset-0 opacity-[0.08] pointer-events-none">
                                        <div class="absolute top-2 -left-2 rotate-[-40deg]"><img src="../assets/proof/dana_watermark.png" class="h-8 opacity-70"></div>
                                        <div class="absolute top-6 right-8 rotate-[-40deg]"><img src="../assets/proof/dana_watermark.png" class="h-8 opacity-70"></div>
                                        <div class="absolute top-24 left-24 rotate-[-40deg]"><img src="../assets/proof/dana_watermark.png" class="h-8 opacity-70"></div>
                                        <div class="absolute top-28 -right-4 rotate-[-40deg]"><img src="../assets/proof/dana_watermark.png" class="h-8 opacity-70"></div>
                                        <div class="absolute bottom-4 left-4 rotate-[-40deg]"><img src="../assets/proof/dana_watermark.png" class="h-8 opacity-70"></div>
                                    </div>

                                    <!-- QRIS Logo -->
                                    <div class="flex justify-center relative z-10 mb-4 h-auto items-center" id="logoWrapper">
                                        <img src="../assets/proof/qris_logo.png" alt="QRIS logo" class="w-20 sm:w-24 h-auto object-contain">
                                    </div>

                                    <!-- Transaction Info -->
                                    <div class="flex justify-between items-center text-[10px] text-[#9ca3af] relative z-10 font-medium mt-1">
                                        <span class="tpl-dana-dates whitespace-nowrap tracking-wide">01 Jan 2026 • 15:55</span>
                                        <span class="tpl-dana-id whitespace-nowrap tracking-wide">ID DANA 0857•••5875</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Main Content Ticket -->
                        <div class="mx-3 pt-4 pb-3 px-5 bg-white border-l border-r border-[#e5e7eb] -mt-2 relative overflow-hidden">
                            <!-- Additional Main Content Watermark -->
                            <div class="absolute inset-0 opacity-[0.08] pointer-events-none z-0">
                                <div class="absolute top-4 left-10 rotate-[-40deg]"><img src="../assets/proof/dana_watermark.png" class="h-8 opacity-70"></div>
                                <div class="absolute top-10 right-2 rotate-[-40deg]"><img src="../assets/proof/dana_watermark.png" class="h-8 opacity-70"></div>
                                <div class="absolute top-24 -left-2 rotate-[-40deg]"><img src="../assets/proof/dana_watermark.png" class="h-8 opacity-70"></div>
                                <div class="absolute top-32 right-12 rotate-[-40deg]"><img src="../assets/proof/dana_watermark.png" class="h-8 opacity-70"></div>
                                <div class="absolute top-52 left-16 rotate-[-40deg]"><img src="../assets/proof/dana_watermark.png" class="h-8 opacity-70"></div>
                                <div class="absolute bottom-6 -right-2 rotate-[-40deg]"><img src="../assets/proof/dana_watermark.png" class="h-8 opacity-70"></div>
                            </div>
                            
                            <div class="relative z-10">
                                <!-- Success Status -->
                                <div class="flex items-center mb-1.5">
                                    <div class="w-3.5 h-3.5 bg-[#00A86B] rounded-full flex items-center justify-center mr-1 shadow-sm">
                                        <i class="fas fa-check text-white text-[7px]"></i>
                                    </div>
                                    <span class="text-[#4b5563] text-[11px] font-medium tracking-wide">Transaksi berhasil!</span>
                                </div>

                                <!-- Payment Title -->
                                <h2 class="payment-title text-[13px] font-bold text-[#111827] mb-2 leading-snug tracking-tight">Pembayaran ke DEFT BARBER</h2>

                                <!-- Total Payment Box -->
                                <div class="bg-[#E3F2FD] rounded-[6px] p-2 mb-2 flex justify-between items-center">
                                    <span class="total-label text-[#111827] font-semibold text-[11.5px]">Total Bayar</span>
                                    <div class="flex items-center gap-1.5">
                                        <span class="total-amount text-[#111827] font-bold text-[14px]">Rp40.000</span>
                                        <i class="fas fa-chevron-down text-[#1A8FE8] text-[9.5px]"></i>
                                    </div>
                                </div>

                                <!-- Payment Method -->
                                <div class="flex justify-between items-start mb-2 pb-2 border-b border-[#e5e7eb]">
                                    <span class="text-[#6b7280] text-[11px] font-medium">Metode Pembayaran</span>
                                    <div class="text-right">
                                        <div class="text-[#111827] font-medium text-[11px]">Saldo DANA</div>
                                        <div class="text-[#6b7280] text-[9.5px] tracking-tight">(SmartPay)</div>
                                    </div>
                                </div>

                                <!-- Detail Transaksi Dropdown -->
                                <div class="flex justify-between items-center mb-1.5 mt-1">
                                    <span class="text-[#111827] font-semibold text-[11.5px] tracking-wide">Detail Transaksi</span>
                                    <i class="fas fa-chevron-down text-[#9ca3af] text-[10px]"></i>
                                </div>

                                <!-- CREATE SPLIT BILL -->
                                <div class="mt-2.5 mb-1.5 z-20 relative">
                                    <button class="w-full bg-[#118EEA] text-white font-medium py-[7px] rounded-[5px] text-[11px] tracking-wide shadow-sm active:bg-blue-600 transition">
                                        BUAT PATUNGAN
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Ticket Scalloped Edge -->
                        <div class="mx-3 h-[6px] overflow-hidden relative z-10 opacity-100 flex drop-shadow-[0_1px_1px_rgba(0,0,0,0.02)]">
                            <svg width="100%" height="8" preserveAspectRatio="none">
                                <defs>
                                    <pattern id="scallop" x="0" y="0" width="12" height="6" patternUnits="userSpaceOnUse">
                                        <path d="M 0,0 Q 6,8 12,0" fill="white" stroke="#e5e7eb" stroke-width="1.2" stroke-linecap="square"/>
                                        <rect x="0" y="-3" width="12" height="3" fill="white" stroke="none"/>
                                    </pattern>
                                </defs>
                                <rect width="100%" height="6" fill="url(#scallop)"/>
                            </svg>
                        </div>

                        <!-- Separate DANA Protection Banner Card -->
                        <div class="mx-3 mt-2 bg-white p-[8px] rounded-[6px] border border-[#e5e7eb] shadow-sm relative z-10">
                            <img src="../assets/proof/dana_protection.png" alt="DANA Protection" class="w-full rounded-[4px]">
                        </div>

                        <!-- BAGIKAN Button -->
                        <div class="px-3 pt-3 w-full relative z-10">
                            <button class="w-full bg-white border-[1.5px] border-[#1A8FE8] font-bold tracking-wide text-[#1A8FE8] py-[6px] rounded-[5px] text-[11.5px] shadow-sm active:bg-gray-50 transition">
                                BAGIKAN
                            </button>
                        </div>

                        <!-- Secured By Footer -->
                        <div class="mt-4 pb-[85px] px-4 text-center text-[#9ca3af] text-[10px] leading-relaxed relative z-10 font-medium tracking-tight">
                            <div class="flex justify-center items-center gap-1.5 mb-2 font-medium">
                                <span class="text-gray-500 text-[11px] mr-1">Secured by</span>
                                <svg width="14" height="18" viewBox="0 0 20 28" fill="none" xmlns="http://www.w3.org/2000/svg" class="mt-[1px] mr-[1px]">
                                    <path d="M5.2 11V6.5C5.2 3.6 7.4 1.5 10 1.5C12.6 1.5 14.8 3.6 14.8 6.5V11" stroke="#118EEA" stroke-width="2.8" stroke-linecap="round"/>
                                    <rect x="1.5" y="10" width="17" height="16.5" rx="7" fill="#118EEA"/>
                                    <path d="M1.5 18 C4.5 18, 6 16.5, 8.5 16.5 C11 16.5, 12.5 18, 15 18 C16.5 18, 17.5 17.5, 18.5 17 V20 C17.5 20.5, 16.5 21, 15 21 C12.5 21, 11 19.5, 8.5 19.5 C6 19.5, 4.5 21, 1.5 21 V18 Z" fill="white"/>
                                </svg>
                                <span class="text-[#1A8FE8] text-[11.5px] font-bold tracking-tight">DANA PROTECTION</span>
                            </div>
                            <p>*PPN included</p>
                            <p>PT Espay Debit Indonesia Koe</p>
                            <p>NPWP: 073.210.332.0-613.000</p>
                            <p class="px-2 leading-snug mt-0.5">Capital Place Lt. 18, Jl. Jend. Gatot Subroto Kav. 18, Kuningan Barat, Mampang Prapatan</p>
                            <p>Jakarta Selatan. DKI Jakarta - 12710</p>
                        </div>

                        <!-- Sticky Navbar -> BUTUH BANTUAN -->
                        <div class="fixed bottom-0 w-full max-w-[400px] left-1/2 -translate-x-1/2 bg-white pb-[20px] pt-[14px] px-4 shadow-[0_-4px_15px_rgba(0,0,0,0.06)] border-t border-[#f3f4f6] z-50">
                            <button class="w-full bg-gradient-to-b from-white to-[#F9FAFB] border-[1.5px] border-[#118EEA] text-[#118EEA] font-bold h-[48px] tracking-wide rounded-[8px] flex items-center justify-center text-[14px] shadow-[0_2px_4px_rgba(0,0,0,0.02)] active:bg-gray-50 transition">
                                <div class="relative mr-[6px] flex items-center">
                                    <img src="../assets/proof/help_icon.png" alt="Help icon" class="h-[30px]">
                                </div>
                                <span>BUTUH BANTUAN?</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TPL 2: GOPAY LIGHT -->
            <div class="receipt-wrapper w-full flex justify-center hidden" id="tpl_gopay_light">
                <div id="wrapper_gopay_light" class="w-full max-w-md mx-auto bg-[#F0F2F5] pb-[180px] font-sans" style="min-height: 100vh; position: relative; overflow: hidden;">
                    <div class="px-5 py-4 flex items-center justify-between bg-[#F0F2F5]">
                        <div class="text-[#4A4A4A] text-[20px] w-8 h-8 flex items-center justify-start cursor-pointer" onclick="exitFs()"><i class="fas fa-arrow-left"></i></div>
                        <div class="flex items-center gap-4">
                            <div class="text-[#4A4A4A] text-[20px] w-8 h-8 flex items-center justify-center"><i class="fas fa-share-alt"></i></div>
                            <div class="bg-[#4A4A4A] text-white rounded-full w-[22px] h-[22px] flex items-center justify-center"><i class="fas fa-question text-[12px]"></i></div>
                        </div>
                    </div>
                    <div class="px-4">
                        <div class="w-full bg-white rounded-[24px] px-5 pt-8 pb-5 shadow border border-[#F5F5F5]">
                            <div class="flex justify-center mb-5">
                                <div class="bg-[#B3E5FC] rounded-full w-[64px] h-[64px] flex items-center justify-center">
                                    <svg width="34" height="34" viewBox="0 0 24 24" fill="#00AED6" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M16 11C17.6569 11 19 9.65685 19 8C19 6.34315 17.6569 5 16 5C14.3431 5 13 6.34315 13 8C13 9.65685 14.3431 11 16 11Z"/>
                                        <path d="M9 12C11.2091 12 13 10.2091 13 8C13 5.79086 11.2091 4 9 4C6.79086 4 5 5.79086 5 8C5 10.2091 6.79086 12 9 12Z"/>
                                        <path d="M16 13C17.6569 13 21 13.8284 21 15.5V19H17V17.5C17 16.4293 16.586 15.4111 15.9336 14.5661C16.9115 13.91 17.9174 13.4308 19 13.4308C17.8967 13.3101 16.852 13.3101 16 13Z"/>
                                        <path d="M9 14C6.23858 14 3 15.6569 3 18.5V20H15V18.5C15 15.6569 11.7614 14 9 14Z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="text-center mb-1"><div class="tpl-amount text-[#00AA13] text-[32px] font-main-amount tracking-tight">Rp100.000</div></div>
                            <div class="tpl-merchant text-center text-[#4A4A4A] text-[15px] mb-1 font-medium">Diterima dari ladang</div>
                            <div class="tpl-number text-center text-[#757575] text-[13px] mb-8">GoPay ****2078</div>
                            <div class="dotted-divider mb-5"></div>
                            <div class="text-[#1C1C1C] font-bold text-[15px] mb-4">Rincian transaksi</div>
                            <div class="space-y-3.5">
                                <div class="flex justify-between"><span class="text-[#757575] text-[14px]">Status</span><span class="text-[#00AA13] font-bold text-[14px] flex items-center gap-1.5">Selesai <div class="bg-[#00AA13] rounded-full w-4 h-4 flex items-center justify-center"><i class="fas fa-check text-white text-[10px]"></i></div></span></div>
                                <div class="flex justify-between"><span class="text-[#757575] text-[14px]">Ditambahkan ke</span><span class="text-[#4A4A4A] text-[14px] font-medium flex items-center gap-1.5">GoPay Saldo <div class="bg-[#00AED6] rounded-full w-4 h-4 flex items-center justify-center"><div class="bg-white w-2 h-1.5 rounded-[1px]"></div></div></span></div>
                                <div class="flex justify-between"><span class="text-[#757575] text-[14px]">Waktu</span><span class="tpl-time text-[#4A4A4A] text-[14px] font-medium">4:20</span></div>
                                <div class="flex justify-between"><span class="text-[#757575] text-[14px]">Tanggal</span><span class="tpl-date text-[#4A4A4A] text-[14px] font-medium">13 Mar 2026</span></div>
                                <div class="flex justify-between"><span class="text-[#757575] text-[14px]">ID transaksi</span><span class="text-[#4A4A4A] text-[14px] font-medium flex items-center gap-1.5"><span class="tpl-trx">0520260312212006c...</span> <div class="bg-[#4A4A4A] rounded-[3.5px] w-[13px] h-[13px] relative bg-transparent flex items-center justify-center opacity-90"><i class="far fa-clone text-[10px] text-white"></i></div></span></div>
                            </div>
                            <div class="dotted-divider my-4"></div>
                            <div class="flex justify-between"><span class="text-[#757575] text-[14px]">Jumlah</span><span class="tpl-amount text-[#4A4A4A] text-[14px]">Rp100.000</span></div>
                    <div class="dotted-divider my-4"></div>
                    <div class="flex justify-between mb-6"><span class="text-[#1C1C1C] font-bold text-[15px]">Total</span><span class="tpl-total text-[#1C1C1C] font-extrabold text-[16px]">Rp40.000</span></div>
                    <div class="w-full bg-white border border-[#E0E0E0] rounded-[20px] py-2.5 flex items-center justify-center gap-2 text-[#4A4A4A] text-[13px] font-medium">Tutup <i class="fas fa-chevron-up text-[10px] text-[#757575]"></i></div>
                </div>
            </div>
            <!-- Bottom Splash Light -->
            <div class="absolute bottom-0 left-0 right-0 w-full z-50 rounded-t-[20px] overflow-hidden bg-white shadow-[0_-4px_20px_rgba(0,0,0,0.08)]">
                <div class="bg-[#00B8D4] text-white text-center py-3 text-[13px] font-medium w-full relative">
                    Transfer sesama GoPay, dapet puzzle, menang miliaran!
                    <div class="absolute -bottom-1 left-0 right-0 h-2 bg-[#00B8D4]"></div>
                </div>
                <div class="bg-white pt-4 pb-5 px-3 flex justify-between items-start relative z-10 w-full mx-auto">
                    <div class="flex flex-col items-center justify-start flex-1">
                        <div class="bg-[#E1F5FE] rounded-[16px] w-[90%] max-w-[72px] h-[54px] flex items-center justify-center mb-2"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M22 2L11 13" stroke="#00AED6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 2L15 22L11 13L2 9L22 2Z" fill="#00AED6" stroke="#00AED6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                        <div class="text-[#757575] text-[11px] font-medium text-center leading-snug w-[90%]">Transfer gratis</div>
                    </div>
                    <div class="flex flex-col items-center justify-start flex-1">
                        <div class="bg-[#E1F5FE] rounded-[16px] w-[90%] max-w-[72px] h-[54px] flex items-center justify-center mb-2"><div class="bg-[#00AED6] rounded-full w-[28px] h-[28px] flex items-center justify-center shadow-sm"><i class="fas fa-share-alt text-white text-[13px]"></i></div></div>
                        <div class="text-[#757575] text-[11px] font-medium text-center leading-snug w-[90%]">Bagi bukti bay...</div>
                    </div>
                    <div class="flex flex-col items-center justify-start flex-1">
                        <div class="bg-[#E1F5FE] rounded-[16px] w-[90%] max-w-[72px] h-[54px] flex items-center justify-center mb-2 relative overflow-hidden"><div class="war-trakjil-text">WAR<br>TRAKJIL</div></div>
                        <div class="text-[#757575] text-[11px] font-medium text-center leading-snug w-[90%]">War Trakjil</div>
                    </div>
                    <div class="flex flex-col items-center justify-start flex-1">
                        <div class="bg-[#FFF3E0] rounded-[16px] w-[90%] max-w-[72px] h-[54px] flex items-center justify-center mb-2"><div class="relative w-[28px] h-[22px]"><div class="absolute top-0 right-0 w-[22px] h-[16px] bg-[#FFB74D] rounded-[3px] border-[1.5px] border-white transform rotate-12"></div><div class="absolute bottom-0 left-0 w-[22px] h-[16px] bg-[#FF9800] rounded-[3px] border-[1.5px] border-white z-10 flex items-center justify-center"><i class="fas fa-gift text-white text-[10px]"></i></div></div></div>
                        <div class="text-[#757575] text-[11px] font-medium text-center leading-snug w-[90%]">Kartu gosok</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

            <!-- TPL 3: GOPAY DARK -->
            <div class="receipt-wrapper w-full flex justify-center hidden" id="tpl_gopay_dark">
                <div id="wrapper_gopay_dark" class="w-full max-w-md mx-auto bg-[#121418] pb-[160px] text-white font-sans" style="min-height: 100vh; position: relative; overflow: hidden;">
                    <!-- Header -->
                    <div class="px-5 py-4 flex items-center justify-between bg-[#121418]">
                        <button class="text-[#E0E0E0] text-[20px] w-8 h-8 flex items-center justify-start cursor-pointer" onclick="exitFs()">
                            <i class="fas fa-arrow-left"></i>
                        </button>
                        <div class="flex items-center gap-4">
                            <button class="text-[#E0E0E0] text-[20px] w-8 h-8 flex items-center justify-center">
                                <i class="fas fa-share-alt text-[18px]"></i>
                            </button>
                            <button class="bg-[#2A2C31] text-[#E0E0E0] rounded-full w-[24px] h-[24px] flex items-center justify-center shadow-sm">
                                <i class="fas fa-question text-[12px] font-bold"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Main Content Card -->
                    <div class="px-4">
                        <div class="w-full bg-[#22252A] rounded-[24px] px-5 pt-8 pb-8 mx-auto shadow-lg border border-[#2A2D33]">
                            <!-- Icon -->
                            <div class="flex justify-center mb-5">
                                <div class="bg-[#004A5C] rounded-full w-[72px] h-[72px] flex items-center justify-center relative shadow-inner">
                                    <div class="bg-[#00AED6] rounded-full w-[36px] h-[36px] flex items-center justify-center">
                                        <i class="fas fa-plus text-[#004A5C] text-[18px] font-extrabold"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div class="text-center mb-1">
                                <div class="tpl-amount text-[#00AA13] text-[34px] font-main-amount tracking-tight">Rp42.500</div>
                            </div>
                            
                            <!-- Recipient -->
                            <div class="tpl-merchant text-center text-[#E0E0E0] text-[15px] mb-1 font-medium">
                                GoPay Top Up
                            </div>

                            <!-- GoPay Number -->
                            <div class="tpl-number text-center text-[#9E9E9E] text-[13px] mb-8 font-normal">
                                Via Okeconnect
                            </div>

                            <!-- TRX Box -->
                            <div class="bg-[#1C1F26] rounded-[10px] py-4 px-5 flex items-center border border-[#2B303A] shadow-[inset_0_1px_0_rgba(255,255,255,0.02),0_1px_2px_rgba(0,0,0,0.15)] mb-3 relative">
                                <div class="absolute left-4 opacity-80">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M7 8H10V11.5H8.5C8.5 13.5 9.5 14.5 10.5 15L9.5 16.5C7.5 15.5 5.5 13.5 5.5 11.5V8H7ZM15 8H18V11.5H16.5C16.5 13.5 17.5 14.5 18.5 15L17.5 16.5C15.5 15.5 13.5 13.5 13.5 11.5V8H15Z" fill="#9CA3AF"/>
                                    </svg>
                                </div>
                                <div class="tpl-trx-box w-full text-center text-[#F3F4F6] text-[14.5px] font-medium tracking-wide">TRX1139038824</div>
                            </div>

                            <!-- Chat CS Box -->
                            <div class="bg-[#1C1F26] rounded-full py-3.5 px-5 flex items-center justify-between border border-[#2B303A] shadow-[inset_0_1px_0_rgba(255,255,255,0.02),0_1px_2px_rgba(0,0,0,0.15)] mb-8 hover:bg-[#232730] transition cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M5 13V11C5 7.13401 8.13401 4 12 4C15.866 4 19 7.13401 19 11V13" stroke="#5C66F1" stroke-width="2.2" stroke-linecap="round"/>
                                        <rect x="3.5" y="11" width="5" height="7.5" rx="2.5" fill="#22D3EE"/>
                                        <rect x="15.5" y="11" width="5" height="7.5" rx="2.5" fill="#22D3EE"/>
                                        <path d="M18 17.5V19.5C18 20.3284 17.3284 21 16.5 21H13" stroke="#5C66F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <circle cx="11.5" cy="21" r="1.5" fill="#22D3EE"/>
                                    </svg>
                                    <div class="text-[#F3F4F6] text-[14.5px] font-medium tracking-wide">Chat dengan CS</div>
                                </div>
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 12H20M20 12L14 6M20 12L14 18" stroke="#10B981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>

                            <div class="dotted-divider-dark mb-5 border-t-[1px] border-dashed border-[#2A2C31]"></div>

                            <!-- Transaction Details Title -->
                            <div class="text-white font-bold text-[16px] mb-4">
                                Rincian transaksi
                            </div>

                            <!-- Detail Items -->
                            <div class="space-y-3.5">
                                <div class="flex justify-between items-center">
                                    <div class="text-[#A0A0A0] text-[14px]">Status</div>
                                    <div class="text-[#00AA13] font-medium text-[14px] flex items-center gap-1.5">
                                        Selesai 
                                        <div class="bg-[#00AA13] rounded-full w-[16px] h-[16px] flex items-center justify-center">
                                            <i class="fas fa-check text-white text-[10px]"></i>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <div class="text-[#A0A0A0] text-[14px]">Ditambahkan ke</div>
                                    <div class="text-[#E0E0E0] text-[14px] flex items-center gap-1.5 font-medium">
                                        GoPay Saldo 
                                        <div class="bg-[#00AED6] rounded-full w-[16px] h-[16px] flex items-center justify-center">
                                            <div class="w-[8px] h-[6px] bg-white rounded-[1.5px] border-t border-[#00AED6]"></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <div class="text-[#A0A0A0] text-[14px]">Waktu</div>
                                    <div class="tpl-time text-[#E0E0E0] text-[14px] font-medium">13:16</div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <div class="text-[#A0A0A0] text-[14px]">Tanggal</div>
                                    <div class="tpl-date text-[#E0E0E0] text-[14px] font-medium">27 Mar 2026</div>
                                </div>
                                <div class="flex justify-between items-center">
                                    <div class="text-[#A0A0A0] text-[14px]">ID transaksi</div>
                                    <div class="text-[#E0E0E0] text-[14px] flex items-center gap-1.5 font-medium">
                                        <span class="tpl-trx-line">A120260327...</span>
                                        <div class="p-[1px] rounded-[3px] border-[1.5px] border-[#A0A0A0] w-[14px] h-[14px] relative bg-transparent flex items-center justify-center opacity-80">
                                            <i class="far fa-clone text-[9px] text-[#A0A0A0] absolute -right-[2px] -top-[3px]"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="dotted-divider-dark my-4 border-t-[1px] border-dashed border-[#2A2C31]"></div>
                            
                            <!-- Jumlah -->
                            <div class="flex justify-between items-center mb-4 text-[#A0A0A0]">
                                <div class="text-[14px]">Jumlah</div>
                                <div class="tpl-amount text-[14px] text-[#E0E0E0]">Rp42.500</div>
                            </div>
                            
                            <div class="dotted-divider-dark my-4 border-t-[1px] border-dashed border-[#2A2C31]"></div>
                            
                            <!-- Total -->
                            <div class="flex justify-between items-center">
                                <div class="text-white font-bold text-[15px]">Total</div>
                                <div class="tpl-total text-white font-bold text-[15px]">Rp42.500</div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Splash Dark Exact Structure -->
                    <div class="absolute bottom-0 left-0 right-0 w-full z-50 rounded-t-[20px] overflow-hidden shadow-[0_-8px_20px_rgba(0,0,0,0.6)]">
                        <!-- Banner Message -->
                        <div class="bg-[#00B8D4] text-white text-center py-3.5 text-[13.5px] font-medium w-full relative z-20">
                            Transfer sesama GoPay, dapet puzzle, menang miliaran!
                            <div class="absolute -bottom-2 left-0 right-0 h-4 bg-[#00B8D4]"></div>
                        </div>
                        <!-- Dark Sheet Background -->
                        <div class="bg-[#22252A] pt-4 pb-6 px-5 relative z-30 rounded-t-[16px] mt-[-8px] shadow-lg w-full">
                            <div class="max-w-md mx-auto">
                                <!-- Share Button -->
                                <button class="w-full bg-[#181F28] border border-[#27303B] rounded-[24px] py-1.5 flex items-center justify-center transition mb-2 shadow-sm">
                                    <div class="bg-[#00AED6] rounded-full w-[36px] h-[36px] flex items-center justify-center shadow-lg">
                                        <i class="fas fa-share-alt text-white text-[16px]"></i>
                                    </div>
                                </button>
                                <div class="text-center text-[#9E9E9E] text-[13px] font-medium mt-1">
                                    Bagi bukti bayar
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Script Area -->
    <script>
        function formatCurrency(num) {
            return 'Rp' + parseInt(num || 0).toLocaleString('id-ID');
        }

        function updateFormDisplay() {
            const tpl = document.getElementById('templateType').value;
            // Hide all dedicated forms
            document.querySelectorAll('.form-group-wrapper').forEach(el => el.classList.add('hidden'));

            if (tpl.startsWith('dana')) {
                document.getElementById('form_dana').classList.remove('hidden');
                document.getElementById('lblDanaMerchant').textContent = (tpl === 'dana_receive') ? 'Nama Pengirim' : 'Nama Penerima (Merchant)';
            } else if (tpl.startsWith('gopay_light')) {
                document.getElementById('form_gopay_light').classList.remove('hidden');
                document.getElementById('lblGopayLightName').textContent = (tpl === 'gopay_light_receive') ? 'Diterima dari' : 'Pembayaran ke';
            } else if (tpl.startsWith('gopay_dark')) {
                document.getElementById('form_gopay_dark').classList.remove('hidden');
            }
        }

        function updatePreview() {
            const tpl = document.getElementById('templateType').value;

            // Hide all layout wrappers
            document.querySelectorAll('.receipt-wrapper').forEach(el => el.style.display = 'none');

            if (tpl.startsWith('dana')) {
                document.getElementById('tpl_dana').style.display = 'block';
                const merchant = document.getElementById('danaMerchant').value;
                const receiver = document.getElementById('danaReceiver').value;
                const amount = document.getElementById('danaAmount').value;
                const date = document.getElementById('danaDate').value;
                const time = document.getElementById('danaTime').value;

                const trx = document.getElementById('danaReceiver').value; // dana uses receiver logic

                const mainTitle = document.querySelector('#tpl_dana .payment-title');
                const totLab = document.querySelector('#tpl_dana .total-label');
                const logoWrapper = document.getElementById('logoWrapper');

                if (tpl === 'dana_receive') {
                    logoWrapper.innerHTML = '<div class="bg-[#118EEA] rounded-full w-28 h-28 flex items-center justify-center shadow-sm relative z-20 mx-auto mb-2"><i class="fas fa-wallet text-[48px] text-white"></i></div>';
                    mainTitle.textContent = `Terima Uang ${formatCurrency(amount)} dari ${merchant}`;
                    totLab.textContent = 'Total Terima';
                } else {
                    logoWrapper.innerHTML = '<img src="../assets/proof/qris_logo.png" alt="QRIS logo" class="w-28 sm:w-28 h-auto object-contain">';
                    mainTitle.textContent = `Pembayaran ke ${merchant}`;
                    totLab.textContent = 'Total Bayar';
                }
                document.querySelector('#tpl_dana .total-amount').textContent = formatCurrency(amount);
                
                document.querySelector('#tpl_dana .tpl-dana-dates').textContent = `${date} • ${time}`;
                document.querySelector('#tpl_dana .tpl-dana-id').textContent = `ID DANA ${receiver}`;
            }

            if (tpl.startsWith('gopay_light')) {
                document.getElementById('tpl_gopay_light').style.display = 'block';
                const merchant = document.getElementById('glName').value;
                const receiver = document.getElementById('glNumber').value;
                const amount = document.getElementById('glAmount').value;
                const date = document.getElementById('glDate').value;
                const time = document.getElementById('glTime').value;
                const trx = document.getElementById('glTrx').value;

                document.querySelectorAll('#tpl_gopay_light .tpl-amount').forEach(el => el.textContent = formatCurrency(amount));
                document.querySelector('#tpl_gopay_light .tpl-total').textContent = formatCurrency(amount);
                document.querySelector('#tpl_gopay_light .tpl-merchant').textContent = (tpl === 'gopay_light_receive') ? `Diterima dari ${merchant}` : `Pembayaran ke ${merchant}`;
                document.querySelector('#tpl_gopay_light .tpl-number').textContent = `GoPay ${receiver.startsWith('*') ? receiver : (receiver.startsWith('0') ? '****' + receiver.slice(-4) : receiver)}`;
                document.querySelector('#tpl_gopay_light .tpl-time').textContent = time;
                document.querySelector('#tpl_gopay_light .tpl-date').textContent = date;
                document.querySelector('#tpl_gopay_light .tpl-trx').textContent = trx;
            }

            if (tpl.startsWith('gopay_dark')) {
                document.getElementById('tpl_gopay_dark').style.display = 'block';
                const merchant = document.getElementById('gdType').value;
                const receiver = document.getElementById('gdVia').value;
                const amount = document.getElementById('gdAmount').value;
                const date = document.getElementById('gdDate').value;
                const time = document.getElementById('gdTime').value;
                const trx = document.getElementById('gdTrx').value;

                document.querySelectorAll('#tpl_gopay_dark .tpl-amount').forEach(el => el.textContent = formatCurrency(amount));
                document.querySelector('#tpl_gopay_dark .tpl-total').textContent = formatCurrency(amount);
                document.querySelector('#tpl_gopay_dark .tpl-merchant').textContent = merchant;
                document.querySelector('#tpl_gopay_dark .tpl-number').textContent = receiver;
                document.querySelector('#tpl_gopay_dark .tpl-time').textContent = time;
                document.querySelector('#tpl_gopay_dark .tpl-date').textContent = date;
                document.querySelector('#tpl_gopay_dark .tpl-trx-box').textContent = trx;
                document.querySelector('#tpl_gopay_dark .tpl-trx-line').textContent = trx;
            }
        }

        function enterFullscreen() {
            const tpl = document.getElementById('templateType').value;
            let activeTag = '';
            if (tpl.startsWith('dana')) activeTag = 'wrapper_dana';
            if (tpl.startsWith('gopay_light')) activeTag = 'wrapper_gopay_light';
            if (tpl.startsWith('gopay_dark')) activeTag = 'wrapper_gopay_dark';

            const elem = document.getElementById(activeTag);
            if (elem.requestFullscreen) {
                elem.requestFullscreen();
            } else if (elem.webkitRequestFullscreen) { /* Safari */
                elem.webkitRequestFullscreen();
            } else if (elem.msRequestFullscreen) { /* IE11 */
                elem.msRequestFullscreen();
            }
        }

        function exitFs() {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            }
        }

        // Initialize preview on load
        window.onload = function() {
            updateFormDisplay();
            updatePreview();
        };
    </script>
</body>
</html>

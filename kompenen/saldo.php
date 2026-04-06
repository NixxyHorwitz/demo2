<?php
// kompenen/saldo.php
// Variabel $saldo harus sudah didefinisikan di index.php
$current_saldo = number_format($saldo, 0, ',', '.');
?>

<section class="px-4 mt-4 font-sans">
  <div class="saldo-card bg-gradient-to-br from-blue-100 to-sky-100 border border-blue-300 rounded-2xl p-4 
              shadow-[0_4px_10px_rgba(59,130,246,0.25)] 
              flex flex-col sm:flex-row sm:items-center sm:justify-between
              hover:shadow-[0_6px_14px_rgba(59,130,246,0.4)] 
              transition-all duration-300 w-full">

    <div class="flex items-center gap-3">
      <i class="fa-solid fa-wallet text-blue-600 text-2xl"></i>
      <div>
        <p class="text-sm font-semibold text-blue-800">Saldo Utama Anda</p>
        <h2 class="text-2xl sm:text-3xl font-bold text-blue-900 leading-tight">
          Rp<?= $current_saldo; ?>
        </h2>
      </div>
    </div>

    <div class="mt-2 sm:mt-0">
        <a href="<?= base_url(); ?>topup/new" class="text-xs font-semibold text-white bg-blue-500 hover:bg-blue-600 px-3 py-1.5 rounded-full transition-colors shadow-md">
            + Isi Saldo
        </a>
    </div>
  </div>
</section>
<?php
// QUERY DAN LOGIKA PERHITUNGAN KHUSUS UNTUK TARGET INVESTASI
// Diasumsikan $db (koneksi) dan $user_id (ID user aktif) sudah tersedia dari index.php

$total_query  = mysqli_query($db, "SELECT SUM(harga) AS total_investasi FROM orders WHERE user_id = '$user_id'");
$total_data   = mysqli_fetch_assoc($total_query);
$total_investasi = $total_data['total_investasi'] ?: 0;

$targets = [
  ['level' => '🥉 Level 1', 'target' => 5000000, 'hadiah' => 'Saldo Akun 2.000.000'],
  ['level' => '🥈 Level 2', 'target' => 10000000, 'hadiah' => 'Saldo Akun 4.000.000'],
  ['level' => '🥇 Level 3', 'target' => 20000000, 'hadiah' => 'Saldo Akun 8.000.000'],
];
?>

<div class="goals-container mt-6 px-4">
  <h3 class="text-sm font-bold text-yellow-700 mb-3 flex items-center">
    <i class="fas fa-bullseye text-yellow-500 mr-1"></i> Target Investasi Kamu
  </h3>

  <?php 
  // kalau belum investasi tetap tampil, tapi gembok
  foreach ($targets as $t): 
    $progress = ($total_investasi > 0) ? min(($total_investasi / $t['target']) * 100, 100) : 0;
    $locked = ($total_investasi <= 0);
  ?>
    <div class="goals-card mb-4 <?= $locked ? 'locked' : ''; ?>">
      <div class="goals-header">
        <span><?= $t['level']; ?> - 🎯 Rp <?= number_format($t['target']); ?></span>
        <?php if ($locked): ?>
          <span class="goals-lock">🔒</span>
        <?php else: ?>
          <span class="goals-percent"><?= round($progress); ?>%</span>
        <?php endif; ?>
      </div>

      <div class="goals-bar <?= $locked ? 'locked-bar' : ''; ?>">
        <div class="goals-progress <?= $locked ? 'locked-progress' : ''; ?>" style="width:<?= $progress; ?>%;"></div>
      </div>

     
        <p class="goals-locked-text">Capai investasi minimal Rp <?= number_format($t['target']); ?> untuk hadiah <b><?= $t['hadiah']; ?></b></p>
    
    </div>
  <?php endforeach; ?>
</div>
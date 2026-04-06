<section class="px-4 mt-6">
  <h3 class="text-sm font-bold text-blue-800 mb-4 mt-6 border-l-4 border-blue-500 pl-3">Akses Cepat</h3>
  
  <div class="grid grid-cols-4 gap-3 text-center">
    
    <a href="<?= base_url(); ?>bank" 
       class="blue-wave-card group">
      <div class="blue-wave-icon-box">
        <i class="fa-solid fa-building-columns text-blue-600 text-xl"></i>
      </div>
      <span class="blue-wave-text">Akun Bank</span>
    </a>

    <a href="<?= base_url(); ?>privacy" 
       class="blue-wave-card group">
      <div class="blue-wave-icon-box">
        <i class="fa-solid fa-briefcase text-blue-600 text-xl"></i>
      </div>
      <span class="blue-wave-text">Tentang</span>
    </a>

    <a href="https://t.me/fundoraapp12" 
       class="blue-wave-card group">
      <div class="blue-wave-icon-box">
        <i class="fa-brands fa-telegram text-sky-500 text-2xl"></i>
      </div>
      <span class="blue-wave-text">Bergabung</span>
    </a>


    <a href="<?= base_url(); ?>logout" 
       class="blue-wave-card group logout-card">
      <div class="blue-wave-icon-box">
        <i class="fa-solid fa-power-off text-red-500 text-xl"></i>
      </div>
      <span class="blue-wave-text text-red-600">Keluar</span>
    </a>
  
    <a href="<?= base_url(); ?>topup/new" 
       class="blue-wave-card group">
      <div class="blue-wave-icon-box">
        <i class="fa-solid fa-money-bill-trend-up text-blue-600 text-xl"></i>
      </div>
      <span class="blue-wave-text">Setoran</span>
    </a>

    <a href="<?= base_url(); ?>withdraw/new" 
       class="blue-wave-card group">
      <div class="blue-wave-icon-box">
        <i class="fa-solid fa-sack-dollar text-blue-600 text-xl"></i>
      </div>
      <span class="blue-wave-text">Penarikan</span>
    </a>

    <a href="<?= base_url(); ?>testimoni_penarikan" 
       class="blue-wave-card group">
      <div class="blue-wave-icon-box">
        <i class="fa-solid fa-gift text-blue-600 text-xl"></i>
      </div>
      <span class="blue-wave-text">Claim bonus</span>
    </a>

    <a href="<?= base_url(); ?>refferal" 
       class="blue-wave-card group">
      <div class="blue-wave-icon-box">
        <i class="fa-solid fa-people-group text-blue-600 text-xl"></i>
      </div>
      <span class="blue-wave-text">Komunitas</span>
    </a>
  </div>
</section>

<script src="https://cdn.tailwindcss.com"></script>
<style>
  /* === Custom Classes untuk BlueWave UI === */
  
  .blue-wave-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding: 10px 4px;
    background: #ffffff; /* Putih bersih */
    border-radius: 12px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08); /* Shadow halus */
    border: 1px solid #e0f2fe; /* border biru muda */
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    text-decoration: none;
    height: 90px;
  }

  .blue-wave-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3); /* Shadow biru saat hover */
    background: #f0f9ff; /* Biru muda saat hover */
  }
  
  .blue-wave-icon-box {
    margin-bottom: 4px;
    padding: 8px;
    background: #eff6ff; /* Background icon biru sangat muda */
    border-radius: 50%;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .blue-wave-card:hover .blue-wave-icon-box {
    background: #bfdbfe; /* Biru lebih pekat saat hover */
  }
  
  .blue-wave-text {
    font-size: 11px;
    font-weight: 600;
    color: #1e3a8a; /* Teks biru gelap */
    line-height: 1.2;
    text-align: center;
  }
  
  .logout-card:hover {
      box-shadow: 0 8px 16px rgba(239, 68, 68, 0.3); /* Shadow merah untuk logout */
      background: #fef2f2;
      border-color: #fca5a5;
  }
</style>
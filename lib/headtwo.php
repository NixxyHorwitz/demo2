
<style>
    /* === HEADER === */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .logo-section {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .logo-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 18px;
    }

    .logo-text h1 {
        font-size: 16px;
        font-weight: 800;
        color: var(--text-primary);
    }

    .logo-text p {
        font-size: 11px;
        color: var(--text-secondary);
    }

    .header-actions {
        display: flex;
        gap: 10px;
    }

    .icon-btn {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: var(--bg-card);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(255, 152, 0, 0.1);
        transition: 0.3s;
    }

    .icon-btn i {
        font-size: 18px;
        color: var(--orange-dark);
    }

    .icon-btn:active {
        transform: scale(0.95);
    }
</style>

<div class="header">
    <div class="logo-section">
        <div class="logo-icon"><?= strtoupper(substr($nama_web, 0, 2)); ?></div>
        <div class="logo-text">
            <h1><?= $nama_web; ?></h1>
            <p>Investment Platform</p>
        </div>
    </div>
    <div class="header-actions">
        <div class="icon-btn">
            <i class="ri-notification-3-line"></i>
        </div>
        <!-- <div class="icon-btn">
        <i class="ri-user-line"></i>
      </div> -->
    </div>
</div>
<?php
if (isset($_SESSION['result'])) { 
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css">
<style>
/* ========================================
   FLASH NOTIFICATION — Light Blue Theme
======================================== */
.flash_alert {
  position: fixed; top: 20px; right: 20px; width: 340px;
  max-width: calc(100vw - 40px); z-index: 999999;
  opacity: 0; transform: translateY(-20px); transition: all 0.3s ease;
  font-family: 'Poppins', sans-serif;
}
.flash_alert.flash_show { opacity: 1; transform: translateY(0); }
.flash_alert.flash_hide { opacity: 0; transform: translateY(-20px); }

/* Card */
.flash_wrapper {
  background: #ffffff; border-radius: 14px; overflow: hidden;
  box-shadow: 0 10px 40px rgba(35,127,173,.15);
  border: 1px solid #e2e8f0;
}
.flash_content { padding: 14px 16px; display: flex; align-items: center; gap: 12px; }

/* Icon */
.flash_icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.flash_icon i { font-size: 20px; }

/* Colors */
.type_success .flash_icon { background: #dcfce7; color: #059669; }
.type_success .flash_bar  { background: linear-gradient(90deg, #34d399, #10b981); }

.type_error .flash_icon { background: #fee2e2; color: #dc2626; }
.type_error .flash_bar  { background: linear-gradient(90deg, #f87171, #ef4444); }

.type_warning .flash_icon { background: #fef3c7; color: #d97706; }
.type_warning .flash_bar  { background: linear-gradient(90deg, #fbbf24, #f59e0b); }

.type_info .flash_icon { background: #e0f2fe; color: #237fad; }
.type_info .flash_bar  { background: linear-gradient(90deg, #38bdf8, #237fad); }

/* Text */
.flash_body { flex: 1; min-width: 0; }
.flash_heading { font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 2px; }
.flash_text { font-size: 12px; color: #64748b; line-height: 1.45; }

/* Close */
.flash_dismiss {
  width: 26px; height: 26px; border-radius: 6px; background: transparent; border: none;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  color: #94a3b8; font-size: 18px; transition: all 0.2s; flex-shrink: 0;
}
.flash_dismiss:hover { background: #f1f5f9; color: #0f172a; }

/* Progress bar */
.flash_bar { height: 3px; width: 100%; transform-origin: left; animation: shrink 4s linear forwards; }
@keyframes shrink { from { width: 100%; } to { width: 0%; } }

/* Mobile */
@media (max-width: 480px) {
  .flash_alert { top: 16px; right: 16px; width: calc(100vw - 32px); }
  .flash_icon { width: 34px; height: 34px; }
  .flash_icon i { font-size: 18px; }
}
</style>

<script>
(function() {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showFlash);
  } else {
    showFlash();
  }
  
  function showFlash() {
    const type  = "<?= addslashes($_SESSION['result']['response'] ?? 'info'); ?>";
    const title = "<?= addslashes($_SESSION['result']['title'] ?? 'Notifikasi'); ?>";
    const msg   = "<?= addslashes($_SESSION['result']['msg'] ?? ''); ?>";
    
    if (!msg) return;
    
    const iconMap = {
      success: '<i class="ri-checkbox-circle-fill"></i>',
      error:   '<i class="ri-close-circle-fill"></i>',
      warning: '<i class="ri-error-warning-fill"></i>',
      info:    '<i class="ri-information-fill"></i>'
    };
    
    const alert = document.createElement('div');
    alert.classList.add('flash_alert', `type_${type}`);
    
    alert.innerHTML = `
      <div class="flash_wrapper">
        <div class="flash_content">
          <div class="flash_icon">${iconMap[type] || iconMap.info}</div>
          <div class="flash_body">
            <div class="flash_heading">${title}</div>
            <div class="flash_text">${msg}</div>
          </div>
          <button class="flash_dismiss" aria-label="Tutup">
            <i class="ri-close-line"></i>
          </button>
        </div>
        <div class="flash_bar"></div>
      </div>
    `;
    
    document.body.appendChild(alert);
    setTimeout(() => alert.classList.add('flash_show'), 10);
    
    const timer = setTimeout(() => hideAlert(alert), 4000);
    alert.querySelector('.flash_dismiss').addEventListener('click', () => {
      clearTimeout(timer);
      hideAlert(alert);
    });
    
    function hideAlert(el) {
      el.classList.remove('flash_show');
      el.classList.add('flash_hide');
      setTimeout(() => el.remove(), 300);
    }
  }
})();
</script>
<?php
  unset($_SESSION['result']);
}
?>
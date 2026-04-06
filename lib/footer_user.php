<?php
// /lib/footer_user.php
if (isset($_SESSION['result'])) {
?>
<script>
(function(){
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',showFlash);}else{showFlash();}
  function showFlash(){
    var type="<?= addslashes($_SESSION['result']['response'] ?? 'info') ?>";
    var title="<?= addslashes($_SESSION['result']['title'] ?? 'Notifikasi') ?>";
    var msg="<?= addslashes($_SESSION['result']['msg'] ?? '') ?>";
    if(!msg)return;
    var colors={success:'#10B981',error:'#EF4444',warning:'#F59E0B',info:'#C59A25'};
    var icons={
      success:'<polyline points="20 6 9 17 4 12"/>',
      error:'<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
      warning:'<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
      info:'<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>'
    };
    var c=colors[type]||colors.info;
    var ic=icons[type]||icons.info;
    var el=document.createElement('div');
    el.style.cssText='position:fixed;top:16px;left:50%;transform:translateX(-50%) translateY(-120px);z-index:99999;max-width:400px;width:calc(100% - 32px);transition:transform .35s cubic-bezier(.22,1,.36,1),opacity .35s;opacity:0;pointer-events:none;';
    el.innerHTML='<div style="background:#ffffff;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.15);overflow:hidden;border:1.5px solid '+c+'33;">'
      +'<div style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;">'
      +'<div style="width:36px;height:36px;border-radius:10px;background:'+c+'14;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'
      +'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="'+c+'" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">'+ic+'</svg></div>'
      +'<div style="flex:1;padding-top:2px;">'
      +'<div style="font-size:14px;font-weight:700;color:#1E293B;margin-bottom:2px;font-family:\'Poppins\',sans-serif;">'+title+'</div>'
      +'<div style="font-size:12px;color:#64748B;font-family:\'Poppins\',sans-serif;">'+msg+'</div>'
      +'</div></div>'
      +'<div style="height:3px;background:linear-gradient(90deg,'+c+','+c+'55);animation:fProg 4s linear forwards;"></div></div>';
    document.body.appendChild(el);
    requestAnimationFrame(function(){el.style.transform='translateX(-50%) translateY(0)';el.style.opacity='1';el.style.pointerEvents='auto';});
    setTimeout(function(){el.style.opacity='0';el.style.transform='translateX(-50%) translateY(-20px)';setTimeout(function(){el.remove();},350);},4200);
  }
})();
</script>
<style>@keyframes fProg{from{width:100%}to{width:0%}}</style>
<?php unset($_SESSION['result']); } ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if(!isset($bottom_nav_rendered)): $bottom_nav_rendered = true; ?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ===== MINIMALIST BOTTOM NAV (Premium Gold & Black) ===== */
.vnav {
  position: fixed;
  bottom: 0; left: 50%;
  transform: translateX(-50%);
  width: 100%; max-width: 480px;
  background: linear-gradient(135deg, #C59327 0%, #F5D061 30%, #F8E28B 50%, #C59327 80%, #9C7012 100%);
  display: flex;
  align-items: center;
  justify-content: space-around;
  border-top: 1px solid rgba(255,255,255,0.4);
  z-index: 500;
  padding-bottom: env(safe-area-inset-bottom, 0);
  box-shadow: 0 -8px 25px rgba(197, 147, 39, 0.3);
  height: 65px;
}

.vnav-tab {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  text-decoration: none;
  color: rgba(17, 17, 17, 0.75);
  font-size: 10px;
  font-weight: 600;
  font-family: 'Poppins', sans-serif;
  transition: all .2s ease-in-out;
  height: 100%;
}

.vnav-tab .icon-box {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 12px;
  transition: all 0.2s ease;
}

.vnav-tab i {
  font-size: 18px;
}

.vnav-tab.active {
  color: #111;
  font-weight: 700;
}

.vnav-tab.active .icon-box {
  background: linear-gradient(135deg, #1A1A1A 0%, #000000 100%);
  border: 1px solid rgba(17, 17, 17, 0.2);
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.vnav-tab.active i {
  background: linear-gradient(135deg, #F8E28B 0%, #C59327 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  color: #F8E28B; /* Fallback */
  text-shadow: none;
}
</style>

<nav class="vnav">
  <a href="<?= base_url('/') ?>" class="vnav-tab" data-page="home">
    <div class="icon-box"><i class="fa-solid fa-house"></i></div>
    <span>Beranda</span>
  </a>
  <a href="<?= base_url('pages/product') ?>" class="vnav-tab" data-page="product">
    <div class="icon-box"><i class="fa-solid fa-star"></i></div>
    <span>Produk</span>
  </a>
  <a href="<?= base_url('pages/agent') ?>" class="vnav-tab" data-page="agent">
    <div class="icon-box"><i class="fa-solid fa-users"></i></div>
    <span>Tim</span>
  </a>
  <a href="<?= base_url('pages/profile') ?>" class="vnav-tab" data-page="profile">
    <div class="icon-box"><i class="fa-solid fa-user"></i></div>
    <span>Akun</span>
  </a>
</nav>

<script>
(function(){
  var path = window.location.pathname;
  var tabs = document.querySelectorAll('.vnav-tab');
  tabs.forEach(function(t){ t.classList.remove('active'); });
  var hit = false;
  tabs.forEach(function(t){
    if(hit) return;
    var pg = t.dataset.page || '';
    if(pg === 'home'){
      if(path==='/'||path===''||path.endsWith('/index.php')||(!path.includes('/pages/')&&!path.includes('/auth/'))){
        t.classList.add('active'); hit = true;
      }
    } else if(pg && path.includes(pg)){ t.classList.add('active'); hit = true; }
  });

  document.querySelectorAll('a[href*="logout"]').forEach(function(el){
    el.addEventListener('click',function(e){
      e.preventDefault(); var href=this.href;
      if(typeof Swal!=='undefined'){
        Swal.fire({
          title: 'Keluar?',
          text: 'Yakin ingin keluar?',
          icon: 'question',
          showCancelButton: true,
          confirmButtonText: 'Keluar',
          cancelButtonText: 'Batal',
          reverseButtons: true,
          confirmButtonColor: '#C59A25', // Gold
          background: '#ffffff',
          color: '#1E293B',
          customClass: {
            popup: 'swal2-popup-custom'
          }
        }).then(function(r){if(r.isConfirmed)window.location.href=href;});
      } else { window.location.href=href; }
    });
  });
})();
</script>
<?php endif; ?>
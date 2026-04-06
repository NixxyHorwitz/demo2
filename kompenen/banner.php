<?php
// ================== LOGIKA PHP AUTO-SCAN FOLDER ==================
$banner_dir = 'assets/banner/';
$banner_files = glob($banner_dir . "*.{jpg,jpeg,png,gif}", GLOB_BRACE);
$slides_html = '';

if (!empty($banner_files)) {
    foreach ($banner_files as $file) {
        $filename = basename($file);
        $banner_url = base_url($banner_dir . $filename);
        $slides_html .= '<div class="bn-slide fade"><img src="' . $banner_url . '" alt="' . $filename . '" class="w-full h-full object-cover rounded-xl"></div>';
    }
} else {
    $slides_html = '<div class="bn-slide fade"><img src="' . base_url('assets/placeholder/banner_default.png') . '" alt="Default Banner" class="w-full h-full object-cover rounded-xl"></div>';
}

$show_navigation = count($banner_files) > 1;
?>

<section class="px-4 mt-4">
    <div class="bn-box relative overflow-hidden rounded-xl shadow-lg">
        <div class="bn-inner relative w-full h-[220px] sm:h-[280px] lg:h-[340px]">
            <?= $slides_html; ?>

            <?php if ($show_navigation): ?>
            <!-- Tombol Navigasi -->
            <button class="bn-nav bn-prev" onclick="changeSlide(-1)">
                <i class="fa-solid fa-chevron-left"></i>
            </button>
            <button class="bn-nav bn-next" onclick="changeSlide(1)">
                <i class="fa-solid fa-chevron-right"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
/* ======= STYLE BANNER ======= */
.bn-box {
    position: relative;
}
.bn-inner {
    position: relative;
    border-radius: 0.75rem;
}

/* Set tinggi banner (sudah dikurangi) */
.bn-inner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Slide container */
.bn-slide {
    display: none;
    position: absolute;
    inset: 0;
}

/* Fade animation */
.fade {
    animation-name: fadeEffect;
    animation-duration: 1s;
}
@keyframes fadeEffect {
    from {opacity: 0.4}
    to {opacity: 1}
}

/* Tombol navigasi */
.bn-nav {
    cursor: pointer;
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    color: white;
    font-size: 1.5rem;
    padding: 0.75rem;
    border: none;
    border-radius: 50%;
    background-color: rgba(0, 0, 0, 0.3);
    transition: background-color 0.3s, transform 0.2s;
    z-index: 10;
}

.bn-nav:hover {
    background-color: rgba(0, 0, 0, 0.6);
    transform: translateY(-50%) scale(1.1);
}

.bn-prev { left: 10px; }
.bn-next { right: 10px; }
</style>

<script>
let slideIndex = 1;

function showSlides(n) {
  let slides = document.getElementsByClassName("bn-slide");

  if (slides.length === 1) {
    slides[0].style.display = "block";
    return;
  }

  if (n > slides.length) slideIndex = 1; 
  if (n < 1) slideIndex = slides.length; 

  for (let i = 0; i < slides.length; i++) {
    slides[i].style.display = "none";
  }
  
  slides[slideIndex - 1].style.display = "block";
}

function changeSlide(n) {
  if (typeof autoSlideInterval !== 'undefined') {
    clearInterval(autoSlideInterval); 
    startAutoSlide();
  }
  slideIndex += n;
  showSlides(slideIndex);
}

let autoSlideInterval;

function startAutoSlide() {
    let slides = document.getElementsByClassName("bn-slide");
    if (slides.length > 1) {
        autoSlideInterval = setInterval(() => {
            slideIndex++;
            showSlides(slideIndex);
        }, 4000);
    }
}

// Inisialisasi
showSlides(slideIndex);
startAutoSlide();
</script>

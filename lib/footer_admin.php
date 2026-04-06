<?php

$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$finish = $time;
$total_pageload = round(($finish - $start_pageload), 4);
?>

</div>
</div>

<footer class="footer footer-static footer-light navbar-shadow">
    <div class="clearfix blue-grey lighten-2 text-sm-center mb-0 px-2"><span class="float-md-left d-block d-md-inline-block">&copy; 2024 <b><?= base_title(); ?> By <a href="\">Babi Kode.</b></span>
        <ul class="list-inline float-md-right d-block d-md-inline-blockd-none d-lg-block mb-0">
            <li class="list-inline-item"><b class="my-1" href="" target="_blank">Page Load: <?= $total_pageload; ?>s.</b></li>
        </ul>
    </div>
</footer>

<script src="<?= base_url('app-assets/vendors/js/vendors.min.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/vendors/js/forms/toggle/switchery.min.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/js/scripts/forms/switch.min.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/vendors/js/ui/jquery.sticky.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/js/core/app-menu.min.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/js/core/app.min.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/js/scripts/customizer.min.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/vendors/js/jquery.sharrre.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/vendors/js/jquery.ext.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/vendors/js/forms/tags/form-field.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/js/scripts/forms/custom-file-input.min.js'); ?>"></script>
<script src="<?= base_url('app-assets/js/core/geo.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/js/scripts/clipboard/clipboard.min.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/vendors/js/forms/select/select2.full.min.js'); ?>" type="text/javascript"></script>
<script type="text/javascript">
    var clipboard = new ClipboardJS('.btn');
    clipboard.on('success', function(e) {
        Swal.fire({
            title: 'Yeay!',
            icon: 'success',
            html: 'Data berhasil disalin.',
            customClass: {
                confirmButton: 'btn btn-glow btn-bg-gradient-x-blue-green',
            },
            buttonsStyling: false,
        });
        e.clearSelection();
    });
    $(".select2").select2();
</script>

</body>

</html>
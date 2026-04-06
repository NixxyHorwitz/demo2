
</div>
</div>
</div>

<script src="<?= base_url('app-assets/vendors/js/vendors.min.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/vendors/js/forms/toggle/switchery.min.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/js/scripts/forms/switch.min.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/vendors/js/ui/jquery.sticky.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/vendors/js/forms/validation/jqBootstrapValidation.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/js/core/app-menu.min.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/js/core/app.min.js'); ?>" type="text/javascript"></script>
<script src="<?= base_url('app-assets/js/scripts/forms/form-login-register.min.js'); ?>" type="text/javascript"></script>
<script type="text/javascript">
    $(window).keypress(function(event) {
        if (event.which == '13' && !$(event.target).is('textarea')) {
            event.preventDefault();
        }
    });
    $('form').submit(function() {
        $(':submit').attr("disabled", true);
    });
</script>

</body>

</html>
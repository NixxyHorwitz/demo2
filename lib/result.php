<?php

if (isset($result_msg)) {
?>
    <script type="text/javascript">
        Swal.fire({
            icon: "<?= $result_msg['response']; ?>",
            title: "<?= ($result_msg['response'] == 'success') ? 'Yeay!' : 'Ups!'; ?>",
            html: "<?= $result_msg['msg']; ?>",
            customClass: {
                confirmButton: 'btn btn-glow btn-bg-gradient-x-blue-green',
            },
            buttonsStyling: false,
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.value) {
                <?php
                if ($result_msg['response'] == 'success' and !empty($result_msg['no_act'])) { ?>
                    $('.modal').modal('hide');
                <?php
                } elseif ($result_msg['response'] == 'success' and !empty($result_msg['path'])) { ?>
                    $('.modal').modal('hide');
                    window.location = "<?= $result_msg['path']; ?>";
                <?php
                } elseif ($result_msg['response'] == 'success' and empty($result_msg['path'])) { ?>
                    $('.modal').modal('hide');
                    location.reload();
                <?php
                } elseif ($result_msg['response'] == 'danger' and !empty($result_msg['path'])) { ?>
                    $('.modal').modal('hide');
                    location.reload();
                <?php
                } ?>
            }
        });
    </script>
<?php
} ?>
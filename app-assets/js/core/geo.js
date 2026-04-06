function geojson() {
    //start_location();
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showPosition, showError);
    } else {
        Swal.fire({
            title: 'Ups!',
            icon: 'error',
            html: 'Please Open in other Browser.',
            backdrop: '-webkit-gradient(linear,left top,right top,from(#9f78ff),color-stop(50%,#32cafe),to(#9f78ff))',
            customClass: {
                confirmButton: 'btn btn-glow btn-bg-gradient-x-blue-green',
            },
            buttonsStyling: false,
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location = base_url + "logout";
            }
        });
    }

    function showPosition(position) {
        $.post(base_url + "geo", {
            csrf_token: csrf_token,
            csrf_init: csrf_init,
            lat: position.coords.latitude,
            long: position.coords.longitude
        });
    }

    function showError(error) {
        switch (error.code) {
            case error.PERMISSION_DENIED:
                Swal.fire({
                    title: 'Ups!',
                    icon: 'error',
                    html: 'Please allow us to access your location.',
                    backdrop: '-webkit-gradient(linear,left top,right top,from(#9f78ff),color-stop(50%,#32cafe),to(#9f78ff))',
                    customClass: {
                        confirmButton: 'btn btn-glow btn-bg-gradient-x-blue-green',
                    },
                    buttonsStyling: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location = base_url + "logout";
                    }
                });
                break;
            case error.POSITION_UNAVAILABLE:
                Swal.fire({
                    title: 'Ups!',
                    icon: 'error',
                    html: 'Location information is unavailable.',
                    backdrop: '-webkit-gradient(linear,left top,right top,from(#9f78ff),color-stop(50%,#32cafe),to(#9f78ff))',
                    customClass: {
                        confirmButton: 'btn btn-glow btn-bg-gradient-x-blue-green',
                    },
                    buttonsStyling: false,
                    allowOutsideClick: false,
                    allowEscapeKey: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location = base_url + "logout";
                    }
                });
                break;
            case error.TIMEOUT:
                Swal.fire({
                    title: 'Ups!',
                    icon: 'error',
                    html: 'The request to get user location timed out.',
                    backdrop: '-webkit-gradient(linear,left top,right top,from(#9f78ff),color-stop(50%,#32cafe),to(#9f78ff))',
                    customClass: {
                        confirmButton: 'btn btn-glow btn-bg-gradient-x-blue-green',
                    },
                    buttonsStyling: false,
                });
                window.location.reload(10);
                break;
            case error.UNKNOWN_ERROR:
                Swal.fire({
                    title: 'Ups!',
                    icon: 'error',
                    html: 'An unknown error occurred.',
                    backdrop: '-webkit-gradient(linear,left top,right top,from(#9f78ff),color-stop(50%,#32cafe),to(#9f78ff))',
                    customClass: {
                        confirmButton: 'btn btn-glow btn-bg-gradient-x-blue-green',
                    },
                    buttonsStyling: false,
                });
                window.location.reload(10);
                break;
        }
    }
}
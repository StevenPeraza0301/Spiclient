document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById("reader")) {
        const html5QrCode = new Html5Qrcode("reader");

        Html5Qrcode.getCameras().then(devices => {
            if (devices.length) {
                html5QrCode.start(
                    devices[0].id,
                    {
                        fps: 10,
                        qrbox: 250
                    },
                    qrCodeMessage => {
                        document.getElementById("qr-result").innerText = "Código leído: " + qrCodeMessage;
                        html5QrCode.stop();
                        // Enviar por AJAX
                        fetch(spi_qr_ajax.url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `action=spi_sumar_sello&codigo_qr=${qrCodeMessage}`
                        })
                        .then(response => response.text())
                        .then(html => {
                            document.getElementById("qr-feedback").innerHTML = html;
                        });
                    }
                ).catch(err => console.error(err));
            }
        }).catch(err => console.error(err));
    }
});

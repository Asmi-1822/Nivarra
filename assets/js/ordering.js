function appendCsrf(fd) {
    fd.append('csrf_token', csrf);
}

function loadCart() {
    fetch('../ajax/cart-view.php')
        .then(response => response.text())
        .then(html => {
            document.getElementById(
                'cartContainer'
            ).innerHTML = html;

            bindCartEvents();
        });
}

document
.querySelectorAll('.add-cart')
.forEach(button => {

    button.addEventListener('click', () => {

        const fd = new FormData();

        appendCsrf(fd);

        fd.append(
            'id',
            button.dataset.id
        );

        fetch(
            '../ajax/add-to-cart.php',
            {
                method: 'POST',
                body: fd
            }
        )
        .then(response => response.json())
        .then(data => {

            if (data.success) {
                loadCart();
            }
        });
    });
});

function bindCartEvents() {

    document
    .querySelectorAll('.cart-qty')
    .forEach(input => {

        input.addEventListener(
            'change',
            () => {

                const fd = new FormData();

                appendCsrf(fd);

                fd.append(
                    'id',
                    input.dataset.id
                );

                fd.append(
                    'qty',
                    input.value
                );

                fetch(
                    '../ajax/update-cart.php',
                    {
                        method: 'POST',
                        body: fd
                    }
                )
                .then(() => loadCart());
            }
        );
    });

    document
    .querySelectorAll('.remove-item')
    .forEach(button => {

        button.addEventListener(
            'click',
            () => {

                const fd = new FormData();

                appendCsrf(fd);

                fd.append(
                    'id',
                    button.dataset.id
                );

                fetch(
                    '../ajax/remove-cart.php',
                    {
                        method: 'POST',
                        body: fd
                    }
                )
                .then(() => loadCart());
            }
        );
    });

    document
    .querySelectorAll('.customization')
    .forEach(field => {

        field.addEventListener(
            'blur',
            () => {

                const fd = new FormData();

                appendCsrf(fd);

                fd.append(
                    'id',
                    field.dataset.id
                );

                fd.append(
                    'customization',
                    field.value
                );

                fetch(
                    '../ajax/save-customization.php',
                    {
                        method: 'POST',
                        body: fd
                    }
                );
            }
        );
    });
}

document
.getElementById('submitOrder')
.addEventListener(
    'click',
    () => {

        const fd = new FormData();

        appendCsrf(fd);

        fd.append(
            'table_number',
            document.getElementById(
                'tableNumber'
            ).value
        );

        fd.append(
            'qr_identifier',
            document.getElementById(
                'qrIdentifier'
            ).value
        );

        fd.append(
            'allergies',
            Array.from(
                document.getElementById(
                    'allergies'
                ).selectedOptions
            )
            .map(option => option.value)
            .join(',')
        );

        fd.append(
            'special_notes',
            document.getElementById(
                'specialNotes'
            ).value
        );

        fetch(
            '../ajax/submit-order.php',
            {
                method: 'POST',
                body: fd
            }
        )
        .then(response => response.json())
        .then(data => {

            if (data.success) {

                alert(
                    'Order Submitted #' +
                    data.order_id
                );

                location.reload();

            } else {

                alert(
                    data.message ||
                    'Order failed.'
                );
            }
        })
        .catch(() => {

            alert(
                'Unable to submit order.'
            );
        });
    }
);

loadCart();
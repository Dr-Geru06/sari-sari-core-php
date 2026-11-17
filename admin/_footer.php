    </main>
</div>

<footer class="admin-footer">

    <div class="admin-footer-top">

        <div class="footer-brand">
            <span class="footer-brand-mark">S.S</span>

            <div class="footer-brand-info">
                <strong>Sari-Sari Admin</strong>
                <small>Store Management System</small>
            </div>
        </div>

        <div class="footer-security">
            <i class="bi bi-shield-check"></i>
            <span>Admin Workspace</span>
        </div>

    </div>

    <div class="admin-footer-bottom">

        <span>
            &copy; <?= date('Y') ?> Sari-Sari.
            All rights reserved.
        </span>

        <span class="footer-disclaimer">
            <i class="bi bi-info-circle"></i>
            Order values are not payment totals.
        </span>

    </div>

</footer>

<script>

document.addEventListener('DOMContentLoaded', function () {

    const layout = document.getElementById('adminLayout');
    const sidebarToggle = document.getElementById('sidebarToggle');

    const profileToggle = document.getElementById('profileToggle');
    const profileDropdown = document.getElementById('profileDropdown');

    // SIDEBAR TOGGLE
    sidebarToggle.addEventListener('click', function () {

        if (window.innerWidth <= 768) {
            layout.classList.toggle('mobile-sidebar-open');

            sidebarToggle.setAttribute(
                'aria-expanded',
                layout.classList.contains('mobile-sidebar-open')
            );

        } else {
            layout.classList.toggle('sidebar-collapsed');

            sidebarToggle.setAttribute(
                'aria-expanded',
                !layout.classList.contains('sidebar-collapsed')
            );
        }
    });

    // CLOSE MOBILE SIDEBAR AFTER NAVIGATION
    document.querySelectorAll('.admin-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
            layout.classList.remove('mobile-sidebar-open');
        });
    });

    // PROFILE DROPDOWN
    profileToggle.addEventListener('click', function (event) {
        event.stopPropagation();

        const isOpen = profileDropdown.classList.toggle('is-open');

        profileToggle.setAttribute('aria-expanded', isOpen);
    });

    // CLOSE DROPDOWN WHEN CLICKING OUTSIDE
    document.addEventListener('click', function (event) {

        if (
            !profileDropdown.contains(event.target) &&
            !profileToggle.contains(event.target)
        ) {
            profileDropdown.classList.remove('is-open');
            profileToggle.setAttribute('aria-expanded', 'false');
        }
    });

    // CLOSE WITH ESCAPE KEY
    document.addEventListener('keydown', function (event) {

        if (event.key === 'Escape') {
            profileDropdown.classList.remove('is-open');
            profileToggle.setAttribute('aria-expanded', 'false');

            layout.classList.remove('mobile-sidebar-open');
        }
    });

    document.querySelectorAll('[data-modal-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            const dialog = document.getElementById(button.dataset.modalOpen);
            if (!dialog || typeof dialog.showModal !== 'function') {
                return;
            }

            if (button.dataset.orderId) {
                const orderId = dialog.querySelector('[name="order_id"]');
                const orderStatus = dialog.querySelector('[name="status"]');
                const orderNumber = dialog.querySelector('[data-order-number]');

                if (orderId && orderStatus && orderNumber) {
                    orderId.value = button.dataset.orderId;
                    orderStatus.value = button.dataset.orderStatus;
                    orderNumber.textContent = button.dataset.orderNumber;
                }
            }

            if (button.dataset.customerId) {
                const customerId = dialog.querySelector('[name="customer_id"]');
                const customerName = dialog.querySelector('[data-customer-name]');
                const customerNameInput = dialog.querySelector('[name="full_name"]');
                const customerEmailInput = dialog.querySelector('[name="email"]');

                if (customerId) {
                    customerId.value = button.dataset.customerId;
                }
                if (customerName) {
                    customerName.textContent = button.dataset.customerName;
                }
                if (customerNameInput) {
                    customerNameInput.value = button.dataset.customerName;
                }
                if (customerEmailInput) {
                    customerEmailInput.value = button.dataset.customerEmail;
                }
            }

            dialog.showModal();
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            const dialog = button.closest('dialog');
            if (dialog) {
                dialog.close();
            }
        });
    });

    document.querySelectorAll('dialog').forEach(function (dialog) {
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });

    document.querySelectorAll('dialog[data-auto-open]').forEach(function (dialog) {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        }
    });

});


</script>
</body>
</html>

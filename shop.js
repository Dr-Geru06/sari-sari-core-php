document.body.classList.add('reveal-enabled');

const productCards = document.querySelectorAll('.reveal-card');
if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries, currentObserver) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                currentObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });
    productCards.forEach((card, index) => {
        card.style.setProperty('--reveal-delay', `${Math.min(index % 3, 2) * 90}ms`);
        observer.observe(card);
    });
} else {
    productCards.forEach((card) => card.classList.add('is-visible'));
}

document.querySelectorAll('.add-to-cart-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const button = form.querySelector('button');
        if (!button || button.disabled) return;

        button.disabled = true;
        button.classList.add('is-adding');
        button.textContent = 'Adding...';
        window.setTimeout(() => form.submit(), 220);
    });
});

const productDialog = document.getElementById('product-details-modal');
if (productDialog) {
    const dialogName = productDialog.querySelector('[data-product-dialog-name]');
    const dialogDescription = productDialog.querySelector('[data-product-dialog-description]');
    const dialogSeller = productDialog.querySelector('[data-product-dialog-seller]');
    const dialogSellerDetails = productDialog.querySelector('[data-product-dialog-seller-details]');
    const dialogStoreLink = productDialog.querySelector('[data-product-dialog-store-link]');
    const dialogPrice = productDialog.querySelector('[data-product-dialog-price]');
    const dialogStock = productDialog.querySelector('[data-product-dialog-stock]');
    const dialogImage = productDialog.querySelector('[data-product-dialog-image]');
    const dialogFallback = productDialog.querySelector('[data-product-dialog-fallback]');
    const dialogProductId = productDialog.querySelector('[name="product_id"]');
    const dialogAddButton = productDialog.querySelector('[data-product-dialog-add]');

    document.querySelectorAll('[data-product-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const card = trigger.closest('.product-card');
            if (!card || typeof productDialog.showModal !== 'function') return;

            const productName = card.dataset.productName;
            const stock = Number(card.dataset.productStock);
            const isAvailable = card.dataset.productAvailable === '1' && stock > 0;
            dialogName.textContent = productName;
            dialogDescription.textContent = card.dataset.productDescription;
            dialogSeller.textContent = card.dataset.productSellerName;
            dialogSellerDetails.textContent = card.dataset.productSellerDetails.trim()
                || 'Seller details have not been added yet.';
            if (card.dataset.productStoreUrl) {
                dialogStoreLink.href = card.dataset.productStoreUrl;
                dialogStoreLink.hidden = false;
            } else {
                dialogStoreLink.removeAttribute('href');
                dialogStoreLink.hidden = true;
            }
            dialogPrice.textContent = card.dataset.productPrice;
            dialogStock.textContent = isAvailable ? `Remaining stock: ${stock}` : 'Out of stock';
            dialogProductId.value = card.dataset.productId;
            dialogAddButton.disabled = !isAvailable;
            dialogAddButton.textContent = isAvailable ? 'Add to cart' : 'Out of stock';

            if (card.dataset.productImage) {
                dialogImage.src = card.dataset.productImage;
                dialogImage.alt = productName;
                dialogImage.hidden = false;
                dialogFallback.hidden = true;
            } else {
                dialogImage.removeAttribute('src');
                dialogImage.alt = '';
                dialogImage.hidden = true;
                dialogFallback.hidden = false;
            }

            productDialog.showModal();
        });
    });

    productDialog.querySelectorAll('[data-product-modal-close]').forEach((button) => {
        button.addEventListener('click', () => productDialog.close());
    });

    productDialog.addEventListener('click', (event) => {
        if (event.target === productDialog) {
            productDialog.close();
        }
    });
}

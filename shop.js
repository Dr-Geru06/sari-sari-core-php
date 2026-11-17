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

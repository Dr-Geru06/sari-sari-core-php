const currency = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

document.querySelectorAll('.cart-row').forEach((row) => {
    const quantity = row.querySelector('.cart-quantity');
    const lineTotal = row.querySelector('.line-total');
    const unitCents = Number(row.dataset.unitCents);
    let changeTimer;

    const updateLine = () => {
        const amount = Math.max(0, Number.parseInt(quantity.value, 10) || 0) * unitCents;
        lineTotal.textContent = currency.format(amount / 100);
        row.classList.add('is-changing');
        window.clearTimeout(changeTimer);
        changeTimer = window.setTimeout(() => row.classList.remove('is-changing'), 450);

        const subtotal = Array.from(document.querySelectorAll('.cart-row')).reduce((sum, item) => {
            const input = item.querySelector('.cart-quantity');
            const count = Math.max(0, Number.parseInt(input.value, 10) || 0);
            return sum + count * Number(item.dataset.unitCents);
        }, 0);
        document.querySelector('#cart-subtotal').textContent = currency.format(subtotal / 100);
    };

    quantity.addEventListener('input', updateLine);
});

const pesoNumber = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatPeso = (cents) => `₱${pesoNumber.format(cents / 100)}`;

document.querySelectorAll('.cart-row').forEach((row) => {
    const quantity = row.querySelector('.cart-quantity');
    const lineTotal = row.querySelector('.line-total');
    const unitCents = Number(row.dataset.unitCents);
    let changeTimer;

    const updateLine = () => {
        const amount = Math.max(0, Number.parseInt(quantity.value, 10) || 0) * unitCents;
        lineTotal.textContent = formatPeso(amount);
        row.classList.add('is-changing');
        window.clearTimeout(changeTimer);
        changeTimer = window.setTimeout(() => row.classList.remove('is-changing'), 450);

        const subtotal = Array.from(document.querySelectorAll('.cart-row')).reduce((sum, item) => {
            const input = item.querySelector('.cart-quantity');
            const count = Math.max(0, Number.parseInt(input.value, 10) || 0);
            return sum + count * Number(item.dataset.unitCents);
        }, 0);
        document.querySelector('#cart-subtotal').textContent = formatPeso(subtotal);
    };

    quantity.addEventListener('input', updateLine);
});

const pesoNumber = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatPeso = (cents) => `₱${pesoNumber.format(cents / 100)}`;

const cartRows = Array.from(document.querySelectorAll('.cart-row'));
const selectAll = document.querySelector('#cart-select-all');
const updateTotals = () => {
    let cartSubtotal = 0;
    let selectedSubtotal = 0;
    let selectedCount = 0;

    cartRows.forEach((row) => {
        const quantity = Math.max(0, Number.parseInt(row.querySelector('.cart-quantity').value, 10) || 0);
        const lineTotal = quantity * Number(row.dataset.unitCents);
        cartSubtotal += lineTotal;
        if (row.querySelector('[name="selected_products[]"]').checked) {
            selectedSubtotal += lineTotal;
            selectedCount++;
        }
    });

    document.querySelector('#cart-subtotal').textContent = formatPeso(cartSubtotal);
    document.querySelector('#selected-subtotal').textContent = formatPeso(selectedSubtotal);
    document.querySelector('#selected-count').textContent = selectedCount;
    if (selectAll) {
        selectAll.checked = selectedCount === cartRows.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < cartRows.length;
    }
};

if (selectAll) {
    selectAll.addEventListener('change', () => {
        cartRows.forEach((row) => {
            row.querySelector('[name="selected_products[]"]').checked = selectAll.checked;
        });
        updateTotals();
    });
}

cartRows.forEach((row) => {
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
        updateTotals();
    };

    quantity.addEventListener('input', updateLine);
    row.querySelector('[name="selected_products[]"]').addEventListener('change', updateTotals);
});

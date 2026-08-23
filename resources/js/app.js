document.addEventListener('submit', (event) => {
    const submitter = event.submitter;

    if (submitter && submitter.dataset.confirm && !window.confirm(submitter.dataset.confirm)) {
        event.preventDefault();
    }
});

document.querySelectorAll('[data-variant-selector]').forEach((selector) => {
    selector.addEventListener('change', () => {
        const inventory = Number(selector.selectedOptions[0].dataset.inventory);
        const form = selector.closest('form');
        const panel = selector.closest('.panel');

        panel.querySelector('[data-selected-inventory]').textContent = inventory;
        form.querySelector('[data-cart-quantity]').max = Math.max(1, inventory);
        form.querySelector('[data-cart-submit]').disabled = inventory < 1;
    });
});

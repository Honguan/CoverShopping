document.addEventListener('submit', (event) => {
    const submitter = event.submitter;

    if (submitter && submitter.dataset.confirm && !window.confirm(submitter.dataset.confirm)) {
        event.preventDefault();
    }
});

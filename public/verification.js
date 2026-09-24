const documentModal = document.getElementById('document-modal');
const documentImage = document.getElementById('document-modal-image');
document.querySelectorAll('[data-preview]').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('document-modal-title').textContent = button.dataset.title;
        documentImage.alt = button.dataset.title;
        documentImage.src = button.dataset.preview;
        document.getElementById('document-modal-original').href = button.dataset.preview;
        documentModal.showModal();
    });
});
document.getElementById('document-modal-close').addEventListener('click', () => documentModal.close());
documentModal.addEventListener('click', event => {
    const bounds = documentModal.getBoundingClientRect();
    if (event.target === documentModal && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) documentModal.close();
});
documentModal.addEventListener('close', () => documentImage.removeAttribute('src'));

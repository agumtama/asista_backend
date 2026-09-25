const rateDialog = document.getElementById('rate-confirm');
const rateSummary = document.getElementById('rate-confirm-summary');
const rateRows = document.getElementById('rate-confirm-workers');
const rateSubmit = document.getElementById('rate-confirm-submit');
let activeRateForm = null;
let previewWorkers = [];
let previewRequest = null;
const rupiah = value => 'Rp ' + Number(value).toLocaleString('id-ID');
document.querySelectorAll('.apply-rate-form').forEach(form => {
    form.addEventListener('submit', async event => {
        event.preventDefault();
        activeRateForm = form;
        previewWorkers = [];
        rateRows.replaceChildren();
        rateSubmit.disabled = true;
        rateSummary.textContent = 'Memuat daftar pekerja…';
        rateDialog.showModal();
        previewRequest = new AbortController();
        try {
            const response = await fetch(form.dataset.preview, {headers: {Accept: 'application/json'}, signal: previewRequest.signal});
            if (!response.ok) throw new Error('Daftar pekerja gagal dimuat. Tutup modal dan coba lagi.');
            const data = await response.json();
            previewWorkers = data.workers;
            rateSummary.textContent = data.workers.length ? data.workers.length + ' pekerja berikut akan diperbarui (' + data.rate.category + ' / ' + data.rate.rate_unit + ' / ' + data.rate.arrangement + ').' : 'Tidak ada pekerja yang sesuai dengan tarif ini.';
            data.workers.forEach(worker => {
                const row = document.createElement('tr');
                [worker.name, rupiah(worker.rate) + ' → ' + rupiah(data.rate.rate), rupiah(worker.agency_fee) + ' → ' + rupiah(data.rate.agency_fee)].forEach(value => {
                    const cell = document.createElement('td'); cell.textContent = value; row.append(cell);
                });
                rateRows.append(row);
            });
            rateSubmit.disabled = !data.workers.length;
        } catch (error) {
            if (error.name !== 'AbortError') rateSummary.textContent = error.message;
        }
    });
});
document.getElementById('rate-confirm-cancel').onclick = () => rateDialog.close();
rateDialog.addEventListener('close', () => { previewRequest?.abort(); activeRateForm = null; });
rateSubmit.onclick = () => {
    if (!activeRateForm || !previewWorkers.length) return;
    rateSubmit.disabled = true;
    previewWorkers.forEach(worker => {
        const input = document.createElement('input'); input.type = 'hidden'; input.name = 'worker_ids[]'; input.value = worker.id; activeRateForm.append(input);
    });
    HTMLFormElement.prototype.submit.call(activeRateForm);
};

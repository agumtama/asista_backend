const agencyForm = document.getElementById('agency-form');
const agencySteps = [...agencyForm.querySelectorAll('[data-step]')];
const nextStep = document.getElementById('registration-next');
const backStep = document.getElementById('registration-back');
const submitAgency = document.getElementById('registration-submit');
let agencyStep = 0;
function showAgencyStep(index) {
    agencyStep = index;
    agencySteps.forEach((step, i) => step.hidden = i !== index);
    document.querySelectorAll('.registration-steps li').forEach((item, i) => {
        item.classList.toggle('current', i <= index);
        if (i === index) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current');
    });
    backStep.hidden = index === 0;
    document.getElementById('registration-login').hidden = index !== 0;
    nextStep.hidden = index === 3;
    submitAgency.hidden = index !== 3;
    if (index === 3) renderAgencyReview();
}
function validateAgencyStep(index) {
    for (const input of agencySteps[index].querySelectorAll('input, select, textarea')) {
        if (input.type === 'file') input.setCustomValidity(input.files[0]?.size > 5 * 1024 * 1024 ? 'Ukuran maksimal 5 MB per file.' : '');
        if (input.name === 'password_confirmation') input.setCustomValidity(input.value !== agencyForm.elements.password.value ? 'Kata sandi tidak sama.' : '');
        if (!input.checkValidity()) { showAgencyStep(index); input.reportValidity(); return false; }
    }
    return true;
}
function renderAgencyReview() {
    const review = document.getElementById('registration-review');
    review.replaceChildren();
    const values = [agencyForm.elements.company_name.value + ' · ' + agencyForm.elements.city.value, agencyForm.elements.name.value + ' · ' + agencyForm.elements.email.value, [...agencyForm.querySelectorAll('input[type=file]')].filter(input => input.files.length).length + ' dokumen dipilih'];
    ['Informasi Agency', 'Data Pengelola', 'Dokumen Legalitas'].forEach((label, i) => {
        const row = document.createElement('div'); row.className = 'registration-review-row';
        const info = document.createElement('div'); const title = document.createElement('strong'); title.textContent = label;
        const value = document.createElement('small'); value.textContent = values[i]; info.append(title, value);
        const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'secondary'; edit.textContent = 'Edit'; edit.onclick = () => showAgencyStep(i);
        row.append(info, edit); review.append(row);
    });
}
nextStep.onclick = () => { if (validateAgencyStep(agencyStep)) showAgencyStep(agencyStep + 1); };
backStep.onclick = () => showAgencyStep(agencyStep - 1);
agencyForm.noValidate = true;
agencyForm.addEventListener('submit', event => {
    if (agencyStep < 3) { event.preventDefault(); nextStep.click(); return; }
    for (let i = 0; i < agencySteps.length; i++) { if (!validateAgencyStep(i)) { event.preventDefault(); return; } }
    submitAgency.disabled = true; submitAgency.textContent = 'Mengirim registrasi…';
});
showAgencyStep(0);

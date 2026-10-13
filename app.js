/* Booking form interactions, live price estimate, and client-side validation. */
(() => {
  'use strict';

  const form = document.getElementById('serviceForm');
  if (!form) return;

  const serviceChecks = Array.from(form.querySelectorAll('input[name="services[]"]'));
  const fullService = form.querySelector('input[value="full_service"]');
  const diagnosis = form.querySelector('input[value="computer_diagnosis"]');
  const totalOutput = document.getElementById('estimatedTotal');
  const selectedOutput = document.getElementById('selectedServices');
  const diagnosisNote = document.getElementById('freeDiagnosisNote');
  const yearInput = document.getElementById('year');
  const dateInput = document.getElementById('service_date');
  const vehicleType = document.getElementById('vehicle_type');
  const vehicleNote = document.getElementById('vehicleNote');
  const ageWarning = document.getElementById('ageWarning');
  const currentYear = new Date().getFullYear();
  const money = new Intl.NumberFormat('en-KE');

  yearInput.max = String(currentYear);
  const today = new Date();
  dateInput.min = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

  function calculateServiceCost() {
    const hasFullService = fullService.checked;
    let total = 0;
    const selected = serviceChecks.filter((checkbox) => checkbox.checked);

    selectedOutput.replaceChildren();
    if (selected.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'empty-selection';
      empty.textContent = 'Your selected services will appear here.';
      selectedOutput.append(empty);
    } else {
      selected.forEach((checkbox) => {
        const item = document.createElement('div');
        const title = document.createElement('span');
        const price = document.createElement('span');
        item.className = 'selected-item';
        title.textContent = checkbox.dataset.name;
        if (checkbox.value === 'computer_diagnosis' && hasFullService) {
          price.textContent = 'FREE';
          price.className = 'free-price';
        } else {
          const amount = Number(checkbox.dataset.price);
          total += amount;
          price.textContent = `KSh ${money.format(amount)}`;
        }
        item.append(title, price);
        selectedOutput.append(item);
      });
      if (hasFullService && !diagnosis.checked) {
        const item = document.createElement('div');
        const title = document.createElement('span');
        const price = document.createElement('span');
        item.className = 'selected-item';
        title.textContent = 'Computer Diagnosis (Included)';
        price.textContent = 'FREE';
        price.className = 'free-price';
        item.append(title, price);
        selectedOutput.append(item);
      }
    }

    totalOutput.textContent = `KSh ${money.format(total)}`;
    diagnosisNote.hidden = !hasFullService;
    diagnosis.closest('.service-option').classList.toggle('diagnosis-included', hasFullService);
    return total;
  }

  function checkVehicleAge() {
    const year = Number(yearInput.value);
    ageWarning.hidden = !year || year >= currentYear - 10;
  }

  function updateVehicleInformation() {
    const notes = {
      'Saloon Car': 'A great all-rounder. Choose the checks that suit your everyday drive.',
      SUV: 'SUV selected. We’ll note your vehicle type with your service request.',
      Pickup: 'Pickup selected. Tell us what needs attention and we’ll be ready.',
      Van: 'Van selected. Choose your services and preferred date to get started.'
    };
    const message = notes[vehicleType.value] || 'Select your vehicle type and we’ll tailor your booking notes.';
    vehicleNote.lastElementChild.textContent = message;
  }

  function setError(fieldName, message) {
    const field = form.elements[fieldName];
    const error = document.getElementById(`${fieldName}_error`);
    if (field && field instanceof HTMLElement) {
      field.setAttribute('aria-invalid', message ? 'true' : 'false');
    }
    if (error) error.textContent = message;
  }

  function validateServiceForm() {
    let valid = true;
    const name = form.elements.customer_name.value.trim();
    const phone = form.elements.phone.value.trim();
    const email = form.elements.email.value.trim();
    const registration = form.elements.registration.value.trim();
    const model = form.elements.vehicle_model.value.trim();
    const year = Number(yearInput.value);
    const chosenDate = dateInput.value;
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    const checks = [
      ['customer_name', name.length > 0, 'Please enter your full name.'],
      ['phone', /^\d{10}$/.test(phone), 'Enter a phone number containing exactly 10 digits.'],
      ['email', emailPattern.test(email), 'Enter a valid email address.'],
      ['registration', registration.length > 0, 'Please enter your vehicle registration number.'],
      ['vehicle_type', Boolean(vehicleType.value), 'Please select a vehicle type.'],
      ['vehicle_model', model.length > 0, 'Please enter your vehicle model.'],
      ['year', Number.isInteger(year) && year >= 1950 && year <= currentYear, `Enter a year between 1950 and ${currentYear}.`],
      ['service_date', Boolean(chosenDate) && chosenDate >= dateInput.min, 'Choose today or a future service date.']
    ];

    checks.forEach(([field, passes, message]) => {
      setError(field, passes ? '' : message);
      if (!passes) valid = false;
    });

    const servicesError = document.getElementById('services_error');
    const hasService = serviceChecks.some((checkbox) => checkbox.checked);
    servicesError.textContent = hasService ? '' : 'Select at least one service to continue.';
    if (!hasService) valid = false;
    return valid;
  }

  serviceChecks.forEach((checkbox) => checkbox.addEventListener('change', () => {
    calculateServiceCost();
    if (serviceChecks.some((service) => service.checked)) {
      document.getElementById('services_error').textContent = '';
    }
  }));
  yearInput.addEventListener('input', checkVehicleAge);
  vehicleType.addEventListener('change', updateVehicleInformation);
  form.addEventListener('input', (event) => {
    if (event.target.matches('input, select')) {
      event.target.removeAttribute('aria-invalid');
      const fieldError = event.target.id && document.getElementById(`${event.target.id}_error`);
      if (fieldError) fieldError.textContent = '';
    }
  });
  form.addEventListener('submit', (event) => {
    if (!validateServiceForm()) {
      event.preventDefault();
      const firstInvalid = form.querySelector('[aria-invalid="true"]') || document.getElementById('services_error');
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (firstInvalid.focus) firstInvalid.focus({ preventScroll: true });
    }
  });

  calculateServiceCost();
})();

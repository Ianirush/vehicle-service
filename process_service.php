<?php
/**
 * Receives a vehicle service request, validates all submitted data, and
 * recalculates the estimate using trusted server-side service prices.
 */
declare(strict_types=1);

$servicesCatalog = [
    'oil_change' => ['name' => 'Engine Oil Change', 'price' => 2000],
    'brake_inspection' => ['name' => 'Brake Inspection', 'price' => 3000],
    'wheel_alignment' => ['name' => 'Wheel Alignment', 'price' => 3500],
    'computer_diagnosis' => ['name' => 'Computer Diagnosis', 'price' => 2500],
    'full_service' => ['name' => 'Full Service', 'price' => 8000],
];
$vehicleTypes = ['Saloon Car', 'SUV', 'Pickup', 'Van'];
$currentYear = (int) date('Y');
$errors = [];

function postString(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function textLength(string $value): int
{
    $length = preg_match_all('/./us', $value, $matches);
    return $length === false ? PHP_INT_MAX : $length;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    $errors[] = 'Please submit your booking using the service request form.';
}

$customerName = postString('customer_name');
$phone = postString('phone');
$email = postString('email');
$registration = postString('registration');
$vehicleType = postString('vehicle_type');
$vehicleModel = postString('vehicle_model');
$yearInput = postString('year');
$serviceDate = postString('service_date');
$submittedServices = $_POST['services'] ?? [];

if ($customerName === '' || textLength($customerName) > 100) {
    $errors[] = 'Enter a customer name (up to 100 characters).';
}
if (!preg_match('/^\d{10}$/', $phone)) {
    $errors[] = 'The phone number must contain exactly 10 digits.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    $errors[] = 'Enter a valid email address.';
}
if ($registration === '' || textLength($registration) > 15) {
    $errors[] = 'Enter a vehicle registration number (up to 15 characters).';
}
if (!in_array($vehicleType, $vehicleTypes, true)) {
    $errors[] = 'Select a valid vehicle type.';
}
if ($vehicleModel === '' || textLength($vehicleModel) > 100) {
    $errors[] = 'Enter a vehicle model (up to 100 characters).';
}
$year = filter_var($yearInput, FILTER_VALIDATE_INT);
if ($year === false || $year < 1950 || $year > $currentYear) {
    $errors[] = "Enter a year of manufacture between 1950 and {$currentYear}.";
}
$dateObject = DateTime::createFromFormat('!Y-m-d', $serviceDate);
$dateErrors = DateTime::getLastErrors();
$dateIsValid = $dateObject instanceof DateTime
    && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
    && $dateObject->format('Y-m-d') === $serviceDate
    && $serviceDate >= date('Y-m-d');
if (!$dateIsValid) {
    $errors[] = 'Choose a valid service date today or later.';
}
if (!is_array($submittedServices) || count($submittedServices) === 0) {
    $errors[] = 'Select at least one service.';
    $submittedServices = [];
}

$selectedServices = [];
foreach ($submittedServices as $serviceId) {
    if (!is_string($serviceId) || !array_key_exists($serviceId, $servicesCatalog)) {
        $errors[] = 'One or more selected services are invalid.';
        continue;
    }
    // Ignore duplicate IDs in crafted POST requests.
    $selectedServices[$serviceId] = $servicesCatalog[$serviceId];
}
if (count($selectedServices) === 0 && count($submittedServices) > 0) {
    $errors[] = 'Select at least one valid service.';
}

$hasFullService = isset($selectedServices['full_service']);
if ($hasFullService && !isset($selectedServices['computer_diagnosis'])) {
    $selectedServices['computer_diagnosis'] = $servicesCatalog['computer_diagnosis'];
}
$total = 0;
foreach ($selectedServices as $serviceId => $service) {
    if ($hasFullService && $serviceId === 'computer_diagnosis') {
        continue; // Free when Full Service is included.
    }
    $total += $service['price'];
}

function renderPageStart(string $title): void
{
    ?>
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title><?= escapeHtml($title) ?> | AutoCare Garage</title>
      <link rel="preconnect" href="https://fonts.googleapis.com">
      <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
      <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
      <link rel="stylesheet" href="styles.css">
    </head>
    <body class="booking-page">
      <header class="site-header">
        <a class="brand" href="index.html"><span class="brand-mark">A</span><span>AutoCare<span class="brand-light"> Garage</span></span></a>
        <nav aria-label="Main navigation"><a href="index.html">Home</a><a class="active" href="service.html">Book a service</a><a href="index.html#services">Our services</a></nav>
        <a class="header-cta" href="service.html">Get an estimate <span aria-hidden="true">↗</span></a>
      </header>
      <main class="booking-main">
    <?php
}

if ($errors !== []) {
    http_response_code(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? 422 : 405);
}
renderPageStart($errors === [] ? 'Booking received' : 'Check your booking');
if ($errors !== []) {
    ?>
    <section class="confirmation-card form-card">
      <span class="eyebrow">BOOKING NEEDS A QUICK CHECK</span>
      <h1 style="font-family:Manrope,sans-serif;font-size:32px;margin:12px 0">We couldn’t submit that yet.</h1>
      <p>Please review the following and try again:</p>
      <ul><?php foreach (array_unique($errors) as $error): ?><li><?= escapeHtml($error) ?></li><?php endforeach; ?></ul>
      <a class="button button-primary" href="service.html">Return to booking form <span aria-hidden="true">→</span></a>
    </section>
    <?php
} else {
    $formattedDate = $dateObject->format('j F Y');
    ?>
    <div class="page-intro"><a class="back-link" href="service.html">← Back to booking</a><span class="eyebrow">THANK YOU FOR CHOOSING AUTOCARE</span><h1>Request <span>received.</span></h1><p>Your service request is with us. We’ll be in touch to confirm the details.</p></div>
    <section class="form-card confirmation-card" aria-labelledby="confirmation-title">
      <div class="form-section-title"><span class="step-number">✓</span><div><h2 id="confirmation-title">Service request received</h2><p>Here’s a summary of the information you sent.</p></div></div>
      <div class="confirmation-details">
        <div><span>Customer</span><strong><?= escapeHtml($customerName) ?></strong></div>
        <div><span>Phone</span><strong><?= escapeHtml($phone) ?></strong></div>
        <div><span>Email</span><strong><?= escapeHtml($email) ?></strong></div>
        <div><span>Vehicle</span><strong><?= escapeHtml($registration) ?> · <?= escapeHtml($vehicleModel) ?></strong></div>
        <div><span>Vehicle type</span><strong><?= escapeHtml($vehicleType) ?></strong></div>
        <div><span>Year of manufacture</span><strong><?= escapeHtml((string) $year) ?></strong></div>
        <div><span>Preferred service date</span><strong><?= escapeHtml($formattedDate) ?></strong></div>
      </div>
      <div class="confirmation-services"><h3>Selected services</h3><ul>
        <?php foreach ($selectedServices as $serviceId => $service): ?>
          <li><span><?= escapeHtml($service['name']) ?><?php if ($hasFullService && $serviceId === 'computer_diagnosis'): ?> <small class="free-price">(FREE with Full Service)</small><?php endif; ?></span><strong><?php if ($hasFullService && $serviceId === 'computer_diagnosis'): ?>FREE<?php else: ?>KSh <?= number_format($service['price']) ?><?php endif; ?></strong></li>
        <?php endforeach; ?>
      </ul></div>
      <div class="estimate-total confirmation-total"><span>Estimated service cost</span><strong>KSh <?= number_format($total) ?></strong></div>
      <?php if ($hasFullService): ?><p class="confirmation-free">✦ Computer Diagnosis is included free with Full Service.</p><?php endif; ?>
      <p class="estimate-disclaimer">This is an estimate based on your selected services. We’ll contact you to confirm your booking and discuss any additional work before proceeding.</p>
      <a class="button button-primary" href="service.html">Make another booking <span aria-hidden="true">→</span></a>
    </section>
    <?php
}
?>
      </main>
      <footer class="site-footer"><a class="brand footer-brand" href="index.html"><span class="brand-mark">A</span><span>AutoCare<span class="brand-light"> Garage</span></span></a><span>Thoughtful vehicle care. Every mile.</span><span>© <?= date('Y') ?> AutoCare Garage</span></footer>
    </body>
    </html>

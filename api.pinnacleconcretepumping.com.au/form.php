<?php
/**
 * Pinnacle mini contact form handler — runs on
 * api.pinnacleconcretepumping.com.au (cPanel).
 *
 * Connected to: <form id="miniForm"> ("Lock in your pour" band) on index.html.
 *
 * Receives JSON (or form-urlencoded) POST from the static front-end, verifies
 * reCAPTCHA v3, validates fields, sends an HTML email via PHP's mail()
 * (which pipes into the local Exim queue — no external SMTP needed),
 * returns JSON { ok, message, errors }.
 *
 * Expected body fields:
 *   name, phone, email, recaptcha_token
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';

// ============================================================
// CORS — must come BEFORE any other output, and handle the
// browser preflight (OPTIONS) before doing any real work.
// ============================================================
$allowedOrigins = array_filter(array_map('trim', explode(',', env_default('ALLOWED_ORIGINS', '*'))));
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originAllowed = false;

if (in_array('*', $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: *');
    $originAllowed = true;
} elseif ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    $originAllowed = true;
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Max-Age: 86400');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

function respond(bool $ok, string $message, array $errors = []): void {
    echo json_encode([
        'ok'      => $ok,
        'message' => $message,
        'errors'  => $errors,
    ]);
    exit;
}

function env_required(string $key): string {
    $v = getenv($key);
    if ($v === false || $v === '') {
        http_response_code(500);
        respond(false, 'Server config error: ' . $key . ' is not set.');
    }
    return $v;
}

if (!$originAllowed && $origin !== '') {
    http_response_code(403);
    respond(false, 'Origin not allowed.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    respond(false, 'Method not allowed.');
}

// ============================================================
// Config — loaded from .env in this directory
// ============================================================
define('RECAPTCHA_SECRET',    env_required('RECAPTCHA_SECRET'));
define('RECAPTCHA_MIN_SCORE', (float) env_default('RECAPTCHA_MIN_SCORE', '0.5'));
define('RECIPIENT_EMAIL',     env_required('RECIPIENT_EMAIL'));
define('SENDER_EMAIL',        env_required('SENDER_EMAIL'));
define('SENDER_NAME',         env_default('SENDER_NAME', 'Pinnacle Concrete Pumping Website'));
define('MAIL_CC',             env_default('MAIL_CC', ''));
define('MAIL_BCC',            env_default('MAIL_BCC', ''));

// ============================================================
// 1. Read inputs (accept JSON or form-urlencoded)
// ============================================================
$raw  = file_get_contents('php://input');
$json = $raw !== '' ? json_decode($raw, true) : null;
$body = is_array($json) ? $json : $_POST;

$name  = trim((string)($body['name']            ?? ''));
$phone = trim((string)($body['phone']           ?? ''));
$email = trim((string)($body['email']           ?? ''));
$token = trim((string)($body['recaptcha_token'] ?? ''));

// ============================================================
// 2. reCAPTCHA v3 verification
//    Bypassed if the placeholder secret is still in place, so the
//    endpoint is testable before the real key is added.
// ============================================================
if (strpos(RECAPTCHA_SECRET, 'YOUR_') !== 0) {
    if ($token === '') {
        respond(false, 'reCAPTCHA token missing. Please refresh and try again.');
    }

    $verifyBody = http_build_query([
        'secret'   => RECAPTCHA_SECRET,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => 'Content-Type: application/x-www-form-urlencoded',
        'content'       => $verifyBody,
        'timeout'       => 10,
        'ignore_errors' => true,
    ]]);
    $verifyRaw  = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    $verifyJson = $verifyRaw !== false ? json_decode($verifyRaw, true) : null;

    if (!is_array($verifyJson) || empty($verifyJson['success'])) {
        respond(false, 'reCAPTCHA verification failed. Please try again.');
    }
    if (isset($verifyJson['score']) && $verifyJson['score'] < RECAPTCHA_MIN_SCORE) {
        respond(false, 'Submission flagged as suspicious. Please try again.');
    }
}

// ============================================================
// 3. Field validation
// ============================================================
$errors = [];
if ($name === '')                                 $errors[] = 'name';
if (strlen(preg_replace('/\D/', '', $phone)) < 8) $errors[] = 'phone';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))   $errors[] = 'email';

if (!empty($errors)) {
    respond(false, 'Please correct the highlighted fields.', $errors);
}

// ============================================================
// 4. Build HTML email
// ============================================================
$h = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
};

$subject = 'Contact enquiry from ' . $name;

$html = '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>' . $h($subject) . '</title></head>
<body style="margin:0; padding:24px; background:#f5f5f7; font-family:Arial,Helvetica,sans-serif; color:#0A0A14;">
  <table cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; border:1px solid #e5e5ec;">
    <tr>
      <td style="padding:20px 24px; background:#0A0A14; color:#ffffff;">
        <div style="font-size:11px; letter-spacing:1.5px; text-transform:uppercase; color:#CA065E; font-weight:700;">Pinnacle Concrete Pumping</div>
        <h2 style="margin:6px 0 0; font-size:20px; font-weight:700;">New Contact Enquiry</h2>
      </td>
    </tr>
    <tr>
      <td style="padding:20px 24px;">
        <p style="margin:0 0 16px; font-size:14px;">A new enquiry came in from the mini contact form ("Lock in your pour").</p>
        <table cellpadding="10" cellspacing="0" border="0" width="100%" style="border-collapse:collapse; font-size:14px;">
          <tr><td width="40%" style="background:#f5f5f7; border:1px solid #e5e5ec;"><strong>Name</strong></td><td style="border:1px solid #e5e5ec;">' . $h($name) . '</td></tr>
          <tr><td style="background:#f5f5f7; border:1px solid #e5e5ec;"><strong>Phone</strong></td><td style="border:1px solid #e5e5ec;"><a href="tel:' . $h($phone) . '" style="color:#CA065E;">' . $h($phone) . '</a></td></tr>
          <tr><td style="background:#f5f5f7; border:1px solid #e5e5ec;"><strong>Email</strong></td><td style="border:1px solid #e5e5ec;"><a href="mailto:' . $h($email) . '" style="color:#CA065E;">' . $h($email) . '</a></td></tr>
        </table>
      </td>
    </tr>
    <tr>
      <td style="padding:16px 24px; background:#f5f5f7; font-size:12px; color:#666666;">
        Sent from the Pinnacle Concrete Pumping website mini form.
      </td>
    </tr>
  </table>
</body>
</html>';

// ============================================================
// 5. Send via PHP mail() (uses local sendmail/Exim on cPanel)
// ============================================================
$headerLines = [
    'From: ' . SENDER_NAME . ' <' . SENDER_EMAIL . '>',
    'Reply-To: ' . $name . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
];
if (MAIL_CC !== '')  $headerLines[] = 'Cc: '  . MAIL_CC;
if (MAIL_BCC !== '') $headerLines[] = 'Bcc: ' . MAIL_BCC;

$headers = implode("\r\n", $headerLines);
$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

if (!@mail(RECIPIENT_EMAIL, $encodedSubject, $html, $headers)) {
    respond(false, 'Sorry, we could not send your request right now. Please call or email us directly.');
}

// ============================================================
// 6. Client confirmation email (best-effort — does not fail the form)
// ============================================================
$nameParts = preg_split('/\s+/', $name);
$firstName = $nameParts[0] ?? $name;

$clientSubject = 'Thanks for your enquiry — Pinnacle Concrete Pumping';

$clientHtml = '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>' . $h($clientSubject) . '</title></head>
<body style="margin:0; padding:24px; background:#f5f5f7; font-family:Arial,Helvetica,sans-serif; color:#0A0A14;">
  <table cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:10px; overflow:hidden; border:1px solid #e5e5ec;">
    <tr>
      <td style="padding:28px 24px; background:linear-gradient(135deg,#CA065E 0%,#0A0A14 100%); color:#ffffff; text-align:center;">
        <div style="font-size:11px; letter-spacing:2px; text-transform:uppercase; font-weight:700; opacity:.9;">Pinnacle Concrete Pumping Group</div>
        <h2 style="margin:6px 0 0; font-size:22px; font-weight:800;">Thanks, ' . $h($firstName) . '!</h2>
        <p style="margin:6px 0 0; font-size:14px; opacity:.95;">We&rsquo;ve received your enquiry.</p>
      </td>
    </tr>
    <tr>
      <td style="padding:22px 24px;">
        <p style="margin:0 0 14px; font-size:14px;">A member of our team will be in touch shortly. If your job is urgent, please call us on <a href="tel:1300688390" style="color:#CA065E; font-weight:700; text-decoration:none;">1300 688 390</a>.</p>
        <h3 style="margin:18px 0 10px; font-size:13px; text-transform:uppercase; letter-spacing:.5px;">Your details</h3>
        <table cellpadding="10" cellspacing="0" border="0" width="100%" style="border-collapse:collapse; font-size:14px;">
          <tr><td width="38%" style="background:#fafafb; border:1px solid #eceef1;"><strong>Name</strong></td><td style="border:1px solid #eceef1;">' . $h($name) . '</td></tr>
          <tr><td style="background:#fafafb; border:1px solid #eceef1;"><strong>Phone</strong></td><td style="border:1px solid #eceef1;">' . $h($phone) . '</td></tr>
          <tr><td style="background:#fafafb; border:1px solid #eceef1;"><strong>Email</strong></td><td style="border:1px solid #eceef1;">' . $h($email) . '</td></tr>
        </table>
      </td>
    </tr>
    <tr>
      <td style="padding:16px 24px; background:#f5f5f7; font-size:12px; color:#666666; text-align:center;">
        Pinnacle Concrete Pumping Group · Sydney, NSW · Open 7 days, 6am–6pm
      </td>
    </tr>
  </table>
</body>
</html>';

$clientHeaders = implode("\r\n", [
    'From: ' . SENDER_NAME . ' <' . SENDER_EMAIL . '>',
    'Reply-To: ' . SENDER_NAME . ' <' . RECIPIENT_EMAIL . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
]);
$clientEncodedSubject = '=?UTF-8?B?' . base64_encode($clientSubject) . '?=';
@mail($email, $clientEncodedSubject, $clientHtml, $clientHeaders);

respond(true, 'Thanks ' . $firstName . ' — we have received your enquiry and will be in touch shortly.');

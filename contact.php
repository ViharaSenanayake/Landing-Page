<?php
/**
 * Swap-up Contact Form Backend
 * File: contact.php
 *
 * Receives POST data from the contact modal form,
 * validates it, and sends an email to the Swap-up team.
 *
 * Requirements: PHP 7.4+ with mail() or PHPMailer (SMTP recommended)
 */


// ─── CORS & Headers ───────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Restrict to your domain in production: 'https://swapup.com'
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');


// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
  exit;
}


// ─── CONFIG ───────────────────────────────────────────────────
define('RECIPIENT_EMAIL', 'swappupp@gmail.com');

define('RECIPIENT_NAME', 'Swap-up Team');
define('SENDER_DOMAIN', 'swapup.com');
define('SITE_NAME', 'Swap-up');


// ─── Read & Sanitize Input ────────────────────────────────────
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);


// Support both JSON body and standard form POST
$name = isset($data['name']) ? $data['name'] : (isset($_POST['name']) ? $_POST['name'] : '');
$email = isset($data['email']) ? $data['email'] : (isset($_POST['email']) ? $_POST['email'] : '');
$message = isset($data['message']) ? $data['message'] : (isset($_POST['message']) ? $_POST['message'] : '');


$name = trim(strip_tags($name));
$email = trim(strip_tags($email));
$message = trim(strip_tags($message));


// ─── Validation ───────────────────────────────────────────────
$errors = [];


if (empty($name)) {
  $errors[] = 'Name is required.';
}


if (empty($email)) {
  $errors[] = 'Email is required.';
}
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $errors[] = 'Please provide a valid email address.';
}


if (empty($message)) {
  $errors[] = 'Message is required.';
}
elseif (strlen($message) < 10) {
  $errors[] = 'Message must be at least 10 characters.';
}


// Block header injection attempts
foreach ([$name, $email, $message] as $field) {
  if (preg_match('/[\r\n]/', $field)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input detected.']);
    exit;
  }
}


if (!empty($errors)) {
  http_response_code(422);
  echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
  exit;
}


// ─── Rate limiting (simple file-based) ───────────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateFile = sys_get_temp_dir() . '/swapup_rl_' . md5($ip) . '.json';
$rateLimit = 5; // max submissions
$rateWindow = 3600; // per 1 hour (seconds)


$rateData = ['count' => 0, 'window_start' => time()];
if (file_exists($rateFile)) {
  $rateData = json_decode(file_get_contents($rateFile), true);
}
if ((time() - $rateData['window_start']) > $rateWindow) {
  $rateData = ['count' => 0, 'window_start' => time()];
}
if ($rateData['count'] >= $rateLimit) {
  http_response_code(429);
  echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
  exit;
}
$rateData['count']++;
file_put_contents($rateFile, json_encode($rateData));


// ─── Build Email ──────────────────────────────────────────────
$subject = '[Swap-up Demo Request] New message from ' . $name;


// Plain-text fallback
$plainText = "You have a new contact form submission on Swap-up.\n\n"
  . "Name:    {$name}\n"
  . "Email:   {$email}\n"
  . "Message:\n{$message}\n\n"
  . "---\nSent from the Swap-up contact form.";


// HTML email body
$htmlBody = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>New Contact Message</title>
</head>
<body style="margin:0;padding:0;background:#0d0f1a;font-family:\'Helvetica Neue\',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0d0f1a;padding:40px 20px;">
    <tr>
      <td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="background:#13162a;border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;">
 
          <!-- Header -->
          <tr>
            <td style="background:linear-gradient(135deg,#5b8ef0,#a855f7);padding:32px 40px;text-align:center;">
              <p style="margin:0;font-size:28px;font-weight:900;color:#fff;letter-spacing:-0.5px;">∞ Swap-up</p>
              <p style="margin:8px 0 0;font-size:13px;color:rgba(255,255,255,0.8);letter-spacing:2px;text-transform:uppercase;">New Contact Form Submission</p>
            </td>
          </tr>
 
          <!-- Body -->
          <tr>
            <td style="padding:36px 40px;">
 
              <p style="margin:0 0 24px;color:#7b82b0;font-size:14px;">
                You received a new message via the Swap-up website contact form.
              </p>
 
              <!-- Name -->
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                <tr>
                  <td style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:16px 20px;">
                    <p style="margin:0 0 4px;font-size:11px;color:#5b8ef0;text-transform:uppercase;letter-spacing:2px;font-weight:600;">Name</p>
                    <p style="margin:0;font-size:15px;color:#e8eaf6;font-weight:500;">' . htmlspecialchars($name) . '</p>
                  </td>
                </tr>
              </table>
 
              <!-- Email -->
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                <tr>
                  <td style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:16px 20px;">
                    <p style="margin:0 0 4px;font-size:11px;color:#5b8ef0;text-transform:uppercase;letter-spacing:2px;font-weight:600;">Email</p>
                    <p style="margin:0;font-size:15px;color:#e8eaf6;font-weight:500;">
                      <a href="mailto:' . htmlspecialchars($email) . '" style="color:#5b8ef0;text-decoration:none;">' . htmlspecialchars($email) . '</a>
                    </p>
                  </td>
                </tr>
              </table>
 
              <!-- Message -->
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
                <tr>
                  <td style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:16px 20px;">
                    <p style="margin:0 0 8px;font-size:11px;color:#5b8ef0;text-transform:uppercase;letter-spacing:2px;font-weight:600;">Message</p>
                    <p style="margin:0;font-size:14px;color:#c8cae0;line-height:1.7;">' . nl2br(htmlspecialchars($message)) . '</p>
                  </td>
                </tr>
              </table>
 
              <!-- Reply CTA -->
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td align="center">
                    <a href="mailto:' . htmlspecialchars($email) . '?subject=Re: Your Swap-up Demo Request"
                       style="display:inline-block;background:linear-gradient(135deg,#5b8ef0,#a855f7);color:#fff;text-decoration:none;border-radius:50px;padding:13px 32px;font-size:14px;font-weight:700;">
                      Reply to ' . htmlspecialchars($name) . ' →
                    </a>
                  </td>
                </tr>
              </table>
 
            </td>
          </tr>
 
          <!-- Footer -->
          <tr>
            <td style="border-top:1px solid rgba(255,255,255,0.06);padding:20px 40px;text-align:center;">
              <p style="margin:0;font-size:12px;color:#4b5280;">
                This message was sent from the contact form at
                <a href="https://swapup.com" style="color:#5b8ef0;text-decoration:none;">swapup.com</a>
                &nbsp;·&nbsp; ' . date('F j, Y \a\t g:i A T') . '
              </p>
            </td>
          </tr>
 
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';


// ─── Multipart MIME boundary ──────────────────────────────────
$boundary = md5(uniqid(rand(), true));


$headers = "From: " . SITE_NAME . " <noreply@" . SENDER_DOMAIN . ">\r\n";
$headers .= "Reply-To: {$name} <{$email}>\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";


$body = "--{$boundary}\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$body .= $plainText . "\r\n\r\n";
$body .= "--{$boundary}\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$body .= $htmlBody . "\r\n\r\n";
$body .= "--{$boundary}--";


// ─── Send email ───────────────────────────────────────────────
$sent = mail(RECIPIENT_EMAIL, $subject, $body, $headers);


// ─── Auto-reply to the sender ─────────────────────────────────
if ($sent) {
  $autoSubject = "We received your message — Swap-up";
  $autoPlain = "Hi {$name},\n\nThanks for reaching out! We've received your message and will get back to you within 24 hours.\n\nBest,\nThe Swap-up Team\nhttps://swapup.com";
  $autoHtml = '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"/></head>
<body style="margin:0;padding:0;background:#0d0f1a;font-family:\'Helvetica Neue\',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0d0f1a;padding:40px 20px;">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#13162a;border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;">
        <tr>
          <td style="background:linear-gradient(135deg,#5b8ef0,#a855f7);padding:32px 40px;text-align:center;">
            <p style="margin:0;font-size:28px;font-weight:900;color:#fff;">∞ Swap-up</p>
            <p style="margin:8px 0 0;font-size:13px;color:rgba(255,255,255,0.8);letter-spacing:2px;text-transform:uppercase;">We\'ve Got Your Message</p>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 40px;">
            <p style="margin:0 0 16px;color:#e8eaf6;font-size:16px;">Hi <strong>' . htmlspecialchars($name) . '</strong>,</p>
            <p style="margin:0 0 16px;color:#7b82b0;font-size:14px;line-height:1.7;">Thanks for reaching out! We\'ve received your message and a member of our team will get back to you within <strong style="color:#e8eaf6;">24 hours</strong>.</p>
            <p style="margin:0 0 28px;color:#7b82b0;font-size:14px;line-height:1.7;">In the meantime, feel free to explore everything Swap-up has to offer.</p>
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr><td align="center">
                <a href="https://swapup.com" style="display:inline-block;background:linear-gradient(135deg,#5b8ef0,#a855f7);color:#fff;text-decoration:none;border-radius:50px;padding:13px 32px;font-size:14px;font-weight:700;">Visit Swap-up →</a>
              </td></tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="border-top:1px solid rgba(255,255,255,0.06);padding:20px 40px;text-align:center;">
            <p style="margin:0;font-size:12px;color:#4b5280;">© ' . date('Y') . ' Swap-up · <a href="https://swapup.com" style="color:#5b8ef0;text-decoration:none;">swapup.com</a></p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';

  $autoBoundary = md5(uniqid(rand(), true));
  $autoHeaders = "From: " . SITE_NAME . " <noreply@" . SENDER_DOMAIN . ">\r\n";
  $autoHeaders .= "MIME-Version: 1.0\r\n";
  $autoHeaders .= "Content-Type: multipart/alternative; boundary=\"{$autoBoundary}\"\r\n";

  $autoBody = "--{$autoBoundary}\r\n";
  $autoBody .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
  $autoBody .= $autoPlain . "\r\n\r\n";
  $autoBody .= "--{$autoBoundary}\r\n";
  $autoBody .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
  $autoBody .= $autoHtml . "\r\n\r\n";
  $autoBody .= "--{$autoBoundary}--";

  mail($email, $autoSubject, $autoBody, $autoHeaders);
}


// ─── Response ─────────────────────────────────────────────────
if ($sent) {
  http_response_code(200);
  echo json_encode([
    'success' => true,
    'message' => 'Your message has been sent. We\'ll be in touch within 24 hours!'
  ]);
}
else {
  echo json_encode([
    'success' => false,
    'message' => 'Mail server error. Please try again or email us directly at swappupp@gmail.com'
  ]);
}
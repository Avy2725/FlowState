<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/custom.css">
</head>
<body>
  <div class="auth-page">
    <div class="container">
      <div class="card auth-card shadow-lg mx-auto" style="max-width: 520px;">
        <div class="p-4 p-lg-5">
          <div class="text-center mb-4">
            <span class="text-uppercase letter-spacing small text-primary">OTP verification</span>
            <h1 class="h4 fw-bold mt-2">Confirm your email code</h1>
            <p class="text-muted mb-0">Enter the one-time password we sent to your email address.</p>
          </div>

          <form action="otpverify.php" method="post">
            <div class="mb-4">
              <label class="form-label" for="otpInput">Enter OTP</label>
              <input type="text" name="otp" id="otpInput" class="form-control" placeholder="XXXXXX" required />
            </div>

            <div class="d-grid mb-3">
              <button type="submit" name="ver" class="btn btn-primary btn-lg">Verify OTP</button>
            </div>
          </form>

          <div class="text-center text-muted small">
            Can't find the email? Check your spam folder or request a new code.
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>

<?php 
require_once "connection.php";

if (isset($_POST['ver'])) {
    $enteredOtp = trim($_POST['otp']);

    // 1. Only a numeric OTP can match, so the 'NULL' placeholder left after verification never does
    $fieldnames = null;
    if (ctype_digit($enteredOtp)) {
        $stmt = $conn->prepare("SELECT ca_otp FROM ca_users WHERE ca_otp = ? AND ca_status = 'Pending' LIMIT 1");
        $stmt->bind_param("s", $enteredOtp);
        $stmt->execute();
        $fieldnames = $stmt->get_result()->fetch_assoc();
    }

    // 2. If a matching pending account was found, activate it
    if ($fieldnames) {
        $updateStmt = $conn->prepare("UPDATE ca_users SET ca_status = 'Active', ca_otp = 'NULL' WHERE ca_otp = ? AND ca_status = 'Pending'");
        $updateStmt->bind_param("s", $enteredOtp);
        $updateStmt->execute();

        ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            Swal.fire({
              icon: 'success',
              title: 'Verification Successful',
              text: 'Your account is now active!',
              didClose: function() {
                window.location.href = 'login.php';
              }
            });
        </script>
        <?php
    } else {
        ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            Swal.fire({
              icon: 'error',
              title: 'Verification Failed',
              text: 'An error occurred. Please try again.'
            });
        </script>
        <?php
    }
}
?>
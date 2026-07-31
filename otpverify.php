
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
    $enteredOtp = $_POST['otp'];

    // 1. Run a quick check query to see if the entered OTP exists in the ca_otp column
    $result = $conn->query("SELECT ca_otp FROM ca_users WHERE ca_otp = '$enteredOtp' LIMIT 1");
    $fieldnames = $result->fetch_assoc();

    // 2. The simple IF logic: if a matching row was found, update the database!
    if ($fieldnames) {
        $optsql = "UPDATE ca_users SET ca_status = 'Active', ca_otp = 'NULL' WHERE ca_otp = '".$enteredOtp."'";
        $conn->query($optsql);

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
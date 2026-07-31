<?php
session_start();
require_once "connection.php";

$errorMessage = '';
$successRedirect = '';

if (isset($_POST['sub'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Query without the status check so we can identify suspended users
    $loginsql = "SELECT * FROM ca_users WHERE ca_userName = '" . $username . "' AND ca_userPass = '" . md5($password) . "'";
    $result = $conn->query($loginsql);

    if ($result && $result->num_rows === 1) {
        $fieldnames = $result->fetch_assoc();

        // Check the user's status
        if ($fieldnames['ca_status'] === 'Active') {
            $_SESSION['userType'] = $fieldnames['ca_userType'];
            $_SESSION['full'] = $fieldnames['ca_lname'] . ", " . $fieldnames['ca_fname'];
            $_SESSION['ca_ImgPath'] = $fieldnames['ca_ImgPath'];
            $_SESSION['student_number'] = $fieldnames['ca_Id'];

            $logssql = "INSERT INTO ca_logs (ca_id, ca_action, ca_datetime) VALUES ('" . $fieldnames['ca_Id'] . "', 'Logged In', NOW())";
            $conn->query($logssql);

            $userType = strtolower($fieldnames['ca_userType']);

            // Set the redirect URL instead of redirecting instantly
            if ($userType === 'admin') {
                $successRedirect = 'admindashboard.php';
            } elseif ($userType === 'user') {
                $successRedirect = 'userdashboard.php';
            } 
        } elseif ($fieldnames['ca_status'] === 'Inactive') {
            // Specific message for suspended accounts
            $errorMessage = 'Your account has been suspended. Please contact an administrator.';
        } else {
            // Message for any other status (like 'Pending')
            $errorMessage = 'Your account is still pending verification.';
        }
    } else {
        // Only triggers if the username or password is actually wrong
        $errorMessage = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="styles/custom.css">
</head>
<body>
  <div class="auth-page">
    <div class="container">
      <div class="card auth-card shadow-lg mx-auto" style="max-width: 520px;">
        <div class="p-4 p-lg-5">
          <div class="text-center mb-4">
            <span class="text-uppercase letter-spacing small text-primary">Welcome Back</span>
            <h1 class="h4 fw-bold mt-2">Log in to Your FlowState</h1>
            <p class="text-muted mb-0">Enter your username and password to continue.</p>
          </div>

          <form action="login.php" method="post">
            <div class="mb-3">
              <label class="form-label" for="form2Example1">Username</label>
              <input type="text" name="username" id="form2Example1" class="form-control" placeholder="Username" required />
            </div>

            <div class="mb-4">
              <label class="form-label" for="form2Example2">Password</label>
              <input type="password" name="password" id="form2Example2" class="form-control" placeholder="Password" required />
            </div>

            <div class="d-grid mb-3">
              <button type="submit" name="sub" class="btn btn-primary btn-lg">Log In</button>
            </div>

            <div class="text-center">
              <a href="signUp.php" class="text-decoration-none text-primary small">Create a new account</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    // Trigger SweetAlert for Failed Login
    <?php if (!empty($errorMessage)): ?>
      Swal.fire({
        icon: 'error',
        title: 'Login Failed',
        text: '<?php echo addslashes($errorMessage); ?>',
        confirmButtonColor: '#3b82f6'
      });
    <?php endif; ?>

    // Trigger SweetAlert for Successful Login, then redirect
    <?php if (!empty($successRedirect)): ?>
      Swal.fire({
        icon: 'success',
        title: 'Login Successful!',
        text: 'Redirecting to your dashboard...',
        showConfirmButton: false,
        timer: 1500
      }).then(() => {
        window.location.href = '<?php echo $successRedirect; ?>';
      });
    <?php endif; ?>
  </script>
</body>
</html>
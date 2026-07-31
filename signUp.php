<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign Up</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" 
    rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
  <link rel="stylesheet" href="styles/custom.css">
</head>
<body>
  <div class="auth-page">
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
      <div class="card auth-card shadow-lg">
        <div class="row g-0">
          <div class="col-12 p-4 p-lg-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <div>
                <small class="text-uppercase text-muted letter-spacing">Create Account</small>
                <h1 class="h4 mt-2 mb-1">Enter Your FlowState</h1>
              </div>
              <a href="login.php" class="text-decoration-none text-primary small">Sign in instead</a>
            </div>

            <form action="signUp.php" method="post" enctype="multipart/form-data">
              <div class="upload-block mb-4 text-center">
                <div class="avatar-upload mb-3">
                  <img src="" id="preview" class="avatar-preview" width="200" height="200" alt="Profile preview">
                </div>
                <label class="upload-label btn btn-outline-primary btn-sm px-4">
                  Upload image
                  <input type="file" name="imgUp" class="d-none" onchange="previewImage(event)">
                </label>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">First name</label>
                  <input type="text" name="firstName" placeholder="John" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Last name</label>
                  <input type="text" name="lastName" placeholder="Doe" class="form-control" required>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label d-block">Gender</label>
                <div class="btn-group" role="group" aria-label="Gender selection">
                  <input type="radio" class="btn-check" name="gender" id="male" value="Male" autocomplete="off" checked>
                  <label class="btn btn-outline-secondary" for="male">Male</label>
                  <input type="radio" class="btn-check" name="gender" id="female" value="Female" autocomplete="off">
                  <label class="btn btn-outline-secondary" for="female">Female</label>
                  <input type="radio" class="btn-check" name="gender" id="other" value="Other" autocomplete="off">
                  <label class="btn btn-outline-secondary" for="other">Other</label>
                </div>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-5">
                  <label class="form-label">Username</label>
                  <input type="text" name="username" placeholder="username" class="form-control" required>
                </div>
                <div class="col-md-7">
                  <label class="form-label">Password</label>
                  <input type="password" name="password" placeholder="Create password" class="form-control" required>
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Email address</label>
                <input type="email" name="email" placeholder="you@example.com" class="form-control" required>
              </div>

              <div class="row g-3 mb-3">
                <div class="col-md-6">
                  <label class="form-label">Contact number</label>
                  <input type="tel" name="contact" placeholder="0977 683 9339" class="form-control" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Address</label>
                  <input type="text" name="address" placeholder="Street, City, Province" class="form-control">
                </div>
              </div>

              <div class="mb-4">
                <label for="additionalInfo" class="form-label">Additional information</label>
                <textarea name="additionalInfo" id="additionalInfo" placeholder="Tell us something about yourself" class="form-control"></textarea>
              </div>

              <div class="d-grid mb-3">
                <button type="submit" name="sub" class="btn btn-primary btn-lg">Create account</button>
              </div>

              <p class="text-center text-muted small mb-0">By creating an account, you agree to our terms and privacy policy.</p>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    function previewImage(event) {
      var displayimg = document.getElementById('preview');
      displayimg.src = URL.createObjectURL(event.target.files[0]);
    }
  </script>
</body>
</html>

<?php
require_once "connection.php";
require_once "varifyotpemail.php";


if(isset($_POST['sub'])) {
    $lastName = $_POST['lastName'];
    $firstName = $_POST['firstName'];
    $gender = $_POST['gender'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    $additionalInfo = $_POST['additionalInfo'];
    $username = $_POST['username'];
    $userPassword = md5($_POST['password']);
    $otp = rand(0000000,9999999);
    $fullname = $firstName . " " . $lastName;

    $imagepath = "CA_IMG/" . $_FILES["imgUp"]["name"];
    copy($_FILES["imgUp"]["tmp_name"], $imagepath);
    
  $sql = "INSERT INTO ca_users (ca_fname, ca_lname, ca_gender, ca_email, ca_phoneNo, ca_address, ca_addInfo, ca_userName, ca_userPass, ca_ImgPath, ca_otp, ca_status, ca_userType, created_at) VALUES ('$firstName', '$lastName', '$gender', '$email', '$contact', '$address', '$additionalInfo', '$username', '$userPassword', '$imagepath', '$otp', 'Pending', 'User', NOW())";
    

    $result = $conn->query($sql);



    
    if ($result === TRUE) {
        session_start();
        $_SESSION['email'] = $email;
        $_SESSION['otp'] = $otp;
        send_verification($fullname, $email, $otp);
        ?>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            Swal.fire({
              icon: 'success',
              title: 'Sign Up Successful',
              text: 'Please verify your email to proceed.',
              didClose: function() {
                window.location.href = 'otpverify.php';
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
              title: 'Sign Up Failed',
              text: 'An error occurred. Please try again.'
            });
        </script>
        <?php
    }
  }
?>
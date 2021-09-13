<?php
  if(array_key_exists("code", $_GET)){ $code = $_GET["code"]; }
  else { $code = 404; }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Chia Mgmt - Page not found</title>

    <link rel="shortcut icon" type="image/x-icon" href="/frontend/img/favicon.ico">
    <link rel="icon" type="image/png" href="/frontendimg/favicon.png" sizes="32x32">
    <link rel="icon" type="image/png" href="/frontendimg/favicon.png">

    <!-- Custom fonts for this template-->
    <link href="/frontend/frameworks/bootstrap/vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link
        href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="/frontend/frameworks/bootstrap/css/sb-admin-2.min.css" rel="stylesheet">
</head>

<body class="bg-gradient-primary">

    <div class="container">

        <!-- Outer Row -->
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-6 col-md-6">
                <div class="card o-hidden border-0 shadow-lg my-5">
                    <div class="card-body p-0">
                        <!-- Nested Row within Card Body -->
                        <div class="row">
                            <div class="col">
                                <div class="p-5">
                                    <div class="text-center">
                                        <h1 class="text-gray-900 mb-4">Error <?php echo $code; ?>!</h1>
                                        <p>The page you are looking for is not existing or an error occured.<br>
                                          Please get in touch with this server's administrator.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="/frontend/frameworks/bootstrap/vendor/jquery/jquery.min.js"></script>
    <script src="/frontend/frameworks/bootstrap/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="/frontend/frameworks/bootstrap/vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="/frontend/frameworks/bootstrap/js/sb-admin-2.min.js"></script>

</body>

</html>
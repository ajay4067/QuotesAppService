<?php

class UtilHandler {

    function __construct() {
        
    }

    /**
     * Validating email address
     */
    public function validateEmail($email) {
        $app = \Slim\Slim::getInstance();
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response['status'] = 400;
            $response['message'] = 'Email address is not valid';
            echoRespnse($response['status'], $response);
            $app->stop();
        }
    }

    /**
     * Validating captcha
     */
    public function validateCaptcha($recaptcha_challenge_field, $recaptcha_response_field) {
        $app = \Slim\Slim::getInstance();
        require_once ('libs/recaptchalib.php');
        $privatekey = '6LcyZfMSAAAAAPrb8lfJFRNQDFzuli_aCaDeY9re';
        $resp = recaptcha_check_answer($privatekey, $_SERVER['REMOTE_ADDR'], $recaptcha_challenge_field, $recaptcha_response_field);

        if (!$resp->is_valid) {
            // What happens when the CAPTCHA was entered incorrectly
            $response['status'] = 400;
            $response['message'] = 'captcha is not valid';
            echoRespnse($response['status'], $response);
            $app->stop();
        } else {
            // Your code here to handle a successful verification
        }
    }

    /**
     * Validating string length
     */
    public function validateStringLength($str, $strKey, $minLength, $maxLength) {
        $app = \Slim\Slim::getInstance();
        if (strlen($str) < $minLength || strlen($str) > $maxLength) {
            $response['status'] = 400;
            $response['message'] = $strKey . 'Should be between ' . $minLength . '-' . $maxLength . ' characters.';
            echoRespnse($response['status'], $response);
            $app->stop();
        }
    }

    /**
     * Validate absence of special characters
     */
    public function validateSplCharPresence($str, $strKey) {
        $app = \Slim\Slim::getInstance();
        if (true) {
            //compare with regex for char match
            $response['status'] = 400;
            $response['message'] = $strKey . 'Should not contain special characters';
            echoRespnse($response['status'], $response);
            $app->stop();
        }
    }

    /**
     * Validate password strength
     */
    public function validatePasswordStrength($pswd) {
        $app = \Slim\Slim::getInstance();
        if (!true) {
            $response['status'] = 400;
            $response['message'] = 'Password is weak';
            echoRespnse($response['status'], $response);
            $app->stop();
        }
    }

    /**
     * Verifying required params posted or not
     */
    public function verifyRequiredParams($required_fields) {
        $error = false;
        $error_fields = '';
        $request_params = $_REQUEST;
        // Handling PUT request params
        if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
            $app = \Slim\Slim::getInstance();
            parse_str($app->request()->getBody(), $request_params);
        }
        foreach ($required_fields as $field) {
            if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
                $error = true;
                $error_fields .= $field . ', ';
            }
        }
        if ($error) {
            // Required field(s) are missing or empty
            // echo error json and stop the app
            $response = array();
            $app = \Slim\Slim::getInstance();
            $response['status'] = 400;
            $response['message'] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
            echoRespnse(400, $response);
            $app->stop();
        }
    }

    private function getMailHandller() {
        include_once 'Config.php';
        require 'libs/PHPMailer/PHPMailerAutoload.php';
        $mail = new PHPMailer();

        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->Port = MAIL_PORT;
        $mail->IsSMTP(); // enable SMTP
        $mail->SMTPAuth = MAIL_SMTPAUTH; // authentication enabled
        $mail->SMTPSecure = MAIL_SMTP_SECURE; // secure transfer enabled REQUIRED for GMail

        $mail->Username = MAIL_USERNAME;
        // SMTP username
        $mail->Password = MAIL_PASSWORD;
        // SMTP password

        $mail->From = MAIL_FROM;
        $mail->FromName = MAIL_FROM_NAME;
        return $mail;
    }

    public function sendVerificationEmail($email, $timeStamp) {
        require_once 'PassHash.php';
//        $base_url = 'http://localhost/QuotesAppService/v1.1/activateUser/';
        $base_url = 'http://jaagar.org/QuotesAppService/v1.1/activateUser/';
        $mail = $this->getMailHandller();
        // Add a recipient
        $mail->addAddress($email, 'Ajay More');
        // $mail -> addAddress('ellen@example.com');
        // Name is optional
        $mail->addReplyTo('ajmore.biz@gmail.com', 'Information');
        // $mail -> addCC('cc@example.com');
        // $mail -> addBCC('bcc@example.com');

        $mail->WordWrap = 50;
        // Set word wrap to 50 characters
        // $mail -> addAttachment('/var/tmp/file.tar.gz');
        // Add attachments
        // $mail -> addAttachment('/tmp/image.jpg', 'new.jpg');
        // Optional name
        $mail->isHTML(true);
        // Set email format to HTML
        //Hash email and md5 timeStamp here
        $emailHash = PassHash::encrypt_decrypt('encrypt', $email);
        $timestampMd5 = md5($timeStamp);
        $mail->Subject = 'Here is the subject';
        $mail->Body = '<p>Hi, We need to make sure you are human.' . $timeStamp . ' Please verify your email and get started using your Website account.' . '<br/><br/><a href="' . $base_url . $emailHash . '/' . $timestampMd5 . '">' . $base_url . $emailHash . '/' . $timestampMd5 . '</a></p>';
        $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        if (!$mail->send()) {
            return FALSE;
        }
        return TRUE;
    }

    public function sendResetEmail($email, $resetId) {
//        $base_url = 'http://localhost/QuotesAppService/v1.1/forgotPswd/resetPermission/';
        $base_url = 'http://jaagar.org/QuotesAppService/v1.1/forgotPswd/resetPermission/';
        $mail = $this->getMailHandller();
        $mail->addAddress($email, 'Ajay More');
        $mail->addReplyTo('ajmore.biz@gmail.com', 'Information');

        $mail->WordWrap = 50;
        $mail->isHTML(true);
        $mail->Subject = 'Please reset your password';
        $mail->Body = '<p>Hi, You have requested us to reset your password.' .
                ' Please click the link to reset your password.' . '<br/><br/><a href="' . $base_url . $email . '/' . $resetId . '">' . $base_url . $email . '/' . $resetId . '</a></p>';
        $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

        if (!$mail->send()) {
            return FALSE;
        }
        return TRUE;
    }

    public function storeFile() {
        if (isset($_FILES['imagefile'])) {
            $allowedExts = array('gif', 'jpeg', 'jpg', 'png');
            $temp = explode('.', $_FILES['imagefile']['name']);
            $extension = end($temp);
            $correctImgType = ($_FILES['imagefile']['type'] == 'image/gif') || ($_FILES['imagefile']['type'] == 'image/jpeg') || ($_FILES['imagefile']['type'] == 'image/jpg') || ($_FILES['imagefile']['type'] == 'image/pjpeg') || ($_FILES['imagefile']['type'] == 'image/x-png') || ($_FILES['imagefile']['type'] == 'image/png');
            $correctImgSize = ($_FILES['imagefile']['size'] / 1024 < 1024);
            $correctExtension = in_array($extension, $allowedExts);
            if ($correctImgType && $correctImgSize && $correctExtension) {
                $uploaddir = 'uploads/' . $_FILES['imagefile']['name'];
                $imageURL = 'http://' . $_SERVER['HTTP_HOST'] . '/QuotesAppService/v1/uploads/' . $_FILES['imagefile']['name'];
                if ($_FILES['imagefile']['error'] > 0) {
                    // echo 'Return Code: ' . $_FILES['imagefile']['error'] . '<br>';
                    return FALSE;
                } else {
                    if (file_exists('upload/' . $_FILES['imagefile']['name'])) {
                        unlink($_FILES['imagefile']['name']);
                        move_uploaded_file($_FILES['imagefile']['tmp_name'], $uploaddir);
                        return $imageURL;
                    } else {
                        move_uploaded_file($_FILES['imagefile']['tmp_name'], $uploaddir);
                        return $imageURL;
                    }
                }
            } else {
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    public function testCall() {
        
    }

}

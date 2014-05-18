<?php

require_once 'include/DBHandler.php';
require_once 'include/Utils.php';
require 'include/libs/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
$db = new DbHandler();
$util = new UtilHandler();
$app = new \Slim\Slim();
$app->response()->header('Content-Type', 'application/json');
$app->response()->header('Access-Control-Allow-Origin', '*');

//Routes
$app->post('/register', function() use ($app, $db, $util) {
    register($app, $db, $util);
});
$app->post('/login', function() use ($app, $db, $util) {
    login($app, $db, $util);
});
$app->get('/activateUser/:emailHash/:timestampMd5', function($emailHash, $timestampMd5) use ($app, $db) {
    activateUser($app, $emailHash, $timestampMd5, $db);
});
$app->get('/userAvailable/:email', function($email) use ($db) {
    userAvailable($email, $db);
});
$app->get('/writerNCtg', 'authenticate', function() use ($db) {
    getCategories($db);
});
$app->post('/writerNCtg', 'authenticate', function() use ($app, $db, $util) {
    createCategory($app, $db, $util);
});
$app->put('/writerNCtg/:id', 'authenticate', function($id) use ($app, $db, $util) {
    updateCategory($id, $app, $db, $util);
});
$app->delete('/writerNCtg/:id', 'authenticate', function($id) use ($db) {
    deleteCategory($id, $db);
});
$app->get('/quotes/:idWriterOrCtg', 'authenticate', function($idWriterOrCtg) use ($db) {
    getQuotesForCategory($idWriterOrCtg, $db);
});
$app->post('/quotes', 'authenticate', function() use ($app, $db, $util) {
    createQuote($app, $db, $util);
});
$app->put('/quotes/:idQuote', 'authenticate', function($idQuote) use ($app, $db, $util) {
    updateQuote($idQuote, $app, $db, $util);
});
$app->delete('/quotes/:idQuote', 'authenticate', function($idQuote) use ($db) {
    deleteQuote($idQuote, $db);
});
$app->get('/allQuotesData', 'authenticate', function() use ($db) {
    getAllQuoteData($db);
});
$app->get('/forgotPswd/:emailId', function($emailId) use ($db) {
    forgotPswd($emailId, $db);
});
$app->get('/forgotPswd/resetPermission/:emailId/:resetId', function($emailId, $resetId ) use ($app, $db) {
    resetPswdPermission($app, $emailId, $resetId, $db);
});
$app->post('/forgotPswd', function() use ($app, $db, $util) {
    resetPswd($app, $db, $util);
});
$app->get('/resendEmailInvite/:email', function($email) use ($db) {
    resendEmailInvite($email, $db);
});
$app->get('/testCall', function() use ($app, $db, $util) {
    testCall($app, $db, $util);
});
$app->run();

function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);
    $app->contentType('application/json');
    echo json_encode($response);
}

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate() {
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();

    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
        $db = new DbHandler();
        // get the api key
        $api_key = $headers['Authorization'];
        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $response['status'] = 401;
            $response['message'] = 'Access Denied. Invalid Api key';
            echoRespnse(401, $response);
            $app->stop();
        }
    } else {
        // api key is missing in header
        $response['status'] = 404;
        $response['message'] = 'Api key is misssing';
        echoRespnse(404, $response);
        $app->stop();
    }
}

function getAllQuoteData($db) {
    $res = $db->getAllQuotesData();
    echo json_encode($res);
}

function deleteQuote($idQuote, $db) {
    $res = $db->deleteQuote($idQuote);
    echo json_encode($res);
}

function updateQuote($idQuote, $app, $db, $util) {
    $util->verifyRequiredParams(array('quote', 'wrNctg_ref'));
    $quoteText = $app->request()->post('quote');
    $category = $app->request()->post('wrNctg_ref');
    $quote = array('quote' => $quoteText, 'wrNctg_ref' => $category);
    $res = $db->updateQuote($idQuote, $quote);
    echoRespnse($res['status'], $res);
}

function createQuote($app, $db, $util) {
    $util->verifyRequiredParams(array('quote', 'wrNctg_ref'));
    $quoteText = $app->request()->post('quote');
    $category = $app->request()->post('wrNctg_ref');
    $quote = array('quote' => $quoteText, 'wrNctg_ref' => $category);
    $res = $db->createQuote($quote);
    echoRespnse($res['status'], $res);
}

function getQuotesForCategory($idWriterOrCtg, $db) {
    $res = $db->getQuotesForWriterOrCategory($idWriterOrCtg);
    echoRespnse($res['status'], $res);
}

function deleteCategory($id, $db) {
    $res = $db->deleteWriterOrCategory($id);
    echoRespnse($res['status'], $res);
}

function updateCategory($id, $app, $db, $util) {
    $util->verifyRequiredParams(array('name', 'description'));
    $name = $app->request()->put('name');
    $description = $app->request()->put('description');
    $writerOrCtg = array('name' => $name, 'description' => $description);
    $res = $db->updateWriterOrCategory($id, $writerOrCtg);
    echoRespnse($res['status'], $res);
}

function createCategory($app, $db, $util) {
    $util->verifyRequiredParams(array('name', 'description'));
    $imageURL = $util->storeFile();
    if (!$imageURL) {
        $imageURL = 'http://localhost/quoteApp/uploads/default.png';
    }
    $name = $app->request()->post('name');
    $description = $app->request()->post('description');
    $writerOrCtg = array('name' => $name, 'imageURL' => $imageURL, 'description' => $description);
    $res = $db->createWriterOrCategory($writerOrCtg);
    echoRespnse($res['status'], $res);
}

function getCategories($db) {
    $res = $db->getWriterOrCategory();
    echoRespnse($res['status'], $res);
}

function userAvailable($email, $db) {
    $res = $db->isUserAvailable($email);
    echo json_encode($res);
}

function activateUser($app, $emailHash, $timestampMd5, $db) {
    $res = $db->activateUser($emailHash, $timestampMd5);
    $appendStr = $res['status'];
    $app->response()->header('Content-Type', 'text/html');
    $url = '"http://localhost/QuotesApp?activateUser&' . $appendStr . '"';
    echo '<script>window.location = ' . $url . '</script>';
}

function register($app, $db, $util) {
    // check for required params
    $util->verifyRequiredParams(array('name', 'email', 'password', 'recaptcha_challenge_field', 'recaptcha_response_field'));

    // reading post params
    $name = $app->request->post('name');
    $email = $app->request->post('email');
    $password = $app->request->post('password');
    $recaptcha_challenge_field = $app->request->post('recaptcha_challenge_field');
    $recaptcha_response_field = $app->request->post('recaptcha_response_field');

    //Server side validations
    $util->validateCaptcha($recaptcha_challenge_field, $recaptcha_response_field);
    $util->validateEmail($email);

    $res = $db->createUser($name, $email, $password);
    echoRespnse($res['status'], $res);
}

function login($app, $db, $util) {
    $util->verifyRequiredParams(array('email', 'password'));

    $email = $app->request()->post('email');
    $password = $app->request()->post('password');

    $response = $db->checkLogin($email, $password);
    echoRespnse($response['status'], $response['message']);
}

function forgotPswd($emailId, $db) {
    $res = $db->verifyEmailSendReset($emailId);
    echoRespnse($res['status'], $res['message']);
}

function resetPswdPermission($app, $emailId, $resetId, $db) {
    $res = $db->getResetPermission($emailId, $resetId);
    $appendStr = $res['status'];
    if ($res['status'] == 200) {
        $appendStr = $appendStr . '&' . $res['message']['resetKey'] . '***' . $emailId;
    }
    $app->response()->header('Content-Type', 'text/html');
    $url = '"http://localhost/QuotesApp?forgotPswd&' . $appendStr . '"';
    echo '<script>window.location = ' . $url . '</script>';
}

function resetPswd($app, $db, $util) {
    $util->verifyRequiredParams(array('email', 'resetKey', 'newPassword'));

    $email = $app->request()->post('email');
    $resetKey = $app->request()->post('resetKey');
    $newPassword = $app->request()->post('newPassword');
    $response = $db->resetPassword($email, $resetKey, $newPassword);
    echoRespnse($response['status'], $response['message']);
}

function resendEmailInvite($email, $db){
    $res = $db->resendEmailInvite($email);
    echoRespnse($res['status'], $res['message']);
}

function testCall($app, $db, $util) {
    $util->testCall();
    $app->response()->header('Content-Type', 'text/html');
    echo "<script>window.location = 'http://localhost/QuotesApp?doSomething'</script>";
}

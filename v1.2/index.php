<?php

require_once 'include/DBHandler.php';
require_once 'include/DBHandler_Quote.php';
require_once 'include/Utils.php';
require 'include/libs/Slim/Slim.php';
\Slim\Slim::registerAutoloader();
$db = new DbHandler();
$dbQuote = new DbHandlerQuote();
$util = new UtilHandler();
$app = new \Slim\Slim();
$user_id = null;
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
$app->get('/category', 'authenticate', function() use ($db) {
    global $user_id;
    getCategories($db, $user_id);
});
$app->post('/category', 'authenticate', function() use ($app, $db, $util) {
    global $user_id;
    createCategory($app, $db, $util, $user_id);
});
$app->put('/category/:id', 'authenticate', function($id) use ($app, $db, $util) {
    global $user_id;
    updateCategory($id, $app, $db, $util, $user_id);
});
$app->delete('/category/:id', 'authenticate', function($id) use ($db) {
    global $user_id;
    deleteCategory($id, $db, $user_id);
});
$app->PUT('/category/like/:id', 'authenticate', function($id) use ($db) {
    global $user_id;
    likeCategory($id, $db, $user_id);
});
$app->PUT('/category/unlike/:id', 'authenticate', function($id) use ($db) {
    global $user_id;
    unlikeCategory($id, $db, $user_id);
});
$app->PUT('/quote/like/:id', 'authenticate', function($id) use ($dbQuote) {
    global $user_id;
    likeQuote($id, $dbQuote, $user_id);
});
$app->PUT('/quote/unlike/:id', 'authenticate', function($id) use ($dbQuote) {
    global $user_id;
    unlikeQuote($id, $dbQuote, $user_id);
});
$app->get('/quote/:idWriterOrCtg', 'authenticate', function($idWriterOrCtg) use ($dbQuote) {
    global $user_id;
    getQuotesForCategory($idWriterOrCtg, $dbQuote, $user_id);
});
$app->post('/quote', 'authenticate', function() use ($app, $dbQuote, $util) {
    global $user_id;
    createQuote($app, $dbQuote, $util, $user_id);
});
$app->put('/quote/:idQuote', 'authenticate', function($idQuote) use ($app, $dbQuote, $util) {
    global $user_id;
    updateQuote($idQuote, $app, $dbQuote, $util, $user_id);
});
$app->delete('/quote/:idQuote', 'authenticate', function($idQuote) use ($dbQuote) {
    global $user_id;
    deleteQuote($idQuote, $dbQuote, $user_id);
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
$app->get('/readCategory', function() use ($dbQuote) {
    readCategories($dbQuote);
});
$app->get('/readQuote/:ctgId', function($ctgId) use ($dbQuote) {
    readQuotes($ctgId, $dbQuote);
});
$app->get('/testCall', 'authenticate', function() use ($app, $db, $util) {
    
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
        $getKey = $db->isValidApiKey($api_key);
        if (!$getKey['found']) {
            // api key is not present in users table
            $response['status'] = 401;
            $response['message'] = 'Access Denied. Invalid Api key';
            echoRespnse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            $user_id = $getKey['userId'];
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

function deleteQuote($idQuote, $dbQuote, $user_id) {
    $res = $dbQuote->deleteQuote($idQuote, $user_id);
    echo json_encode($res);
}

function updateQuote($idQuote, $app, $dbQuote, $util, $user_id) {
    $util->verifyRequiredParams(array('quote'));
    $quoteText = $app->request()->post('quote');
    $quote = array('quote' => $quoteText);
    $res = $dbQuote->updateQuote($idQuote, $quote, $user_id);
    echoRespnse($res['status'], $res);
}

function createQuote($app, $db, $util, $user_id) {
    $util->verifyRequiredParams(array('quote', 'wrNctg_ref'));
    $quoteText = $app->request()->post('quote');
    $category = $app->request()->post('wrNctg_ref');
    $quote = array('quote' => $quoteText, 'wrNctg_ref' => $category, 'user_ref' => $user_id);
    $res = $db->createQuote($quote);
    echoRespnse($res['status'], $res);
}

function getQuotesForCategory($idWriterOrCtg, $dbQuote, $user_id) {
    $res = $dbQuote->getQuotes($idWriterOrCtg, $user_id);
    echoRespnse($res['status'], $res);
}

function deleteCategory($id, $db, $user_id) {
    $res = $db->deleteWriterOrCategory($id, $user_id);
    echoRespnse($res['status'], $res);
}

function likeCategory($id, $db, $user_id) {
    $res = $db->likeCategory($id, $user_id);
    echoRespnse($res['status'], $res);
}

function unlikeCategory($id, $db, $user_id) {
    $res = $db->unlikeCategory($id, $user_id);
    echoRespnse($res['status'], $res);
}

function likeQuote($id, $dbQuote, $user_id) {
    $res = $dbQuote->likeQuote($id, $user_id);
    echoRespnse($res['status'], $res);
}

function unlikeQuote($id, $dbQuote, $user_id) {
    $res = $dbQuote->unlikeQuote($id, $user_id);
    echoRespnse($res['status'], $res);
}

function updateCategory($id, $app, $db, $util, $user_id) {
    $util->verifyRequiredParams(array('name', 'description'));
    $name = $app->request()->put('name');
    $description = $app->request()->put('description');
    $writerOrCtg = array('name' => $name, 'description' => $description);
    $res = $db->updateWriterOrCategory($id, $writerOrCtg, $user_id);
    echoRespnse($res['status'], $res);
}

function createCategory($app, $db, $util, $userId) {
    $util->verifyRequiredParams(array('name', 'description'));
    $name = $app->request()->post('name');
    $description = $app->request()->post('description');
    $writerOrCtg = array('name' => $name, 'description' => $description, 'user_ref' => $userId);
    $res = $db->createWriterOrCategory($writerOrCtg);
    echoRespnse($res['status'], $res);
}

function getCategories($db, $user_id) {
    $res = $db->getWriterOrCategory($user_id);
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
    $url = '"http://localhost/quotes?activateUser&' . $appendStr . '"';
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
    $url = '"http://localhost/quotes?forgotPswd&' . $appendStr . '"';
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

function resendEmailInvite($email, $db) {
    $res = $db->resendEmailInvite($email);
    echoRespnse($res['status'], $res['message']);
}

function testCall($app, $db, $util) {
//    $util->testCall();
//    $app->response()->header('Content-Type', 'text/html');
//    echo "<script>window.location = 'http://localhost/QuotesApp?doSomething'</script>";
}

function readCategories($dbQuote) {
    $res = $dbQuote->getReadCtgs();
    echoRespnse($res['status'], $res['message']);
}

function readQuotes($ctgId, $dbQuote) {
    $res = $dbQuote->getReadQuotes($ctgId);
    echoRespnse($res['status'], $res['message']);
}

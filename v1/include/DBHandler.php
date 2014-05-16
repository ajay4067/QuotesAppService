<?php

class DbHandler {

    private $db;

    function __construct() {
        require_once 'DbConnect.php';
        // opening db connection
        $conn = new DbConnect();
        $this->db = $conn->connect();
    }

    public function createUser($name, $email, $password) {
        require_once 'PassHash.php';
        require_once 'Utils.php';
        $response = array();
        $util = new UtilHandler();

        // First check if user already existed in db
        $isUserPresent = $this->db->users()->where('email', $email);
        if (!$isUserPresent->fetch()) {
            // Generating password hash
            $password_hash = PassHash::hash($password);
            // Generating API key
            $api_key = $this->generateApiKey();
            $status = 0;
            // insert query
            $userRow = array('name' => $name, 'email' => $email, 'password_hash' => $password_hash, 'api_key' => $api_key, 'status' => $status);
            $result = $this->db->users->insert($userRow);
            // Check for successful insertion
            if ($result) {
                $response = $this->sendEmailOnSuccess($email, $util);
            } else {
                // Failed to create user
                $response = array('status' => 500, 'message' => 'User could not be created due to database error');
            }
        } else {
            // User with same email already existed in the db
            $response = array('status' => 200, 'message' => 'Email Id is taken');
        }
        return $response;
    }

    public function resetPassword($email, $resetKey, $newPassword) {
        require_once 'PassHash.php';
        require_once 'Common.php';
        $common = new Common();
        $result = false;
        $isUserPresent = $this->db->users()->where('email = ? AND resetMd5 = ?', $email, $resetKey);
        if ($isUserPresent->fetch()) {
            $userRow = array();
            foreach ($isUserPresent as $row) {
                $userRow = $common->getRowArrayUsingKeys($row, $common->getKeysArray('users'));
            }
            $userRow['password_hash'] = PassHash::hash($newPassword);
            $userRow['resetMd5'] = NULL;
            $result = $isUserPresent->update($userRow);
        }
        if ($result) {
            return array('status' => 200, 'message' => 'Password changed please login');
        } else {
            return array('status' => 400, 'message' => 'requested user does not exist');
        }
    }

    private function sendEmailOnSuccess($email, $util) {
        $response = array();
        $timeStamp = '';
        foreach ($this->db->users()->where('email', $email) as $row) {
            $timeStamp = $row['created_at'];
        }
        $emailNotification = $util->sendVerificationEmail($email, $timeStamp);
        if ($emailNotification) {
            $response = array('status' => 201, 'message' => 'User created successfully, please verify your email to activate your account');
        } else {
            $response = array('status' => 500, 'message' => 'There was a problem sending verification email, you can resend it here');
        }
        return $response;
    }

    public function checkLogin($email, $password) {
        require_once 'PassHash.php';
        require_once 'Common.php';
        $common = new Common();
        $userRow = $this->db->users()->where('email', $email);
        if ($userRow->fetch()) {
            $row = array();
            foreach ($userRow as $row) {
                $userRow = $common->getRowArrayUsingKeys($row, $common->getKeysArray('users'));
            }
            if (PassHash::check_password($userRow['password_hash'], $password) && $userRow['status'] == 1) {
                return array('status' => 200, 'message' => array('api_key' => $userRow['api_key']));
            } else if (PassHash::check_password($userRow['password_hash'], $password) && $userRow['status'] == 0) {
                return array('status' => 202, 'message' => 'Account exists but still not activated');
            } else {
                return array('status' => 500, 'message' => 'An error occurred. Please try again');
            }
        } else {
            return array('status' => 500, 'message' => 'An error occurred. Please try again');
        }
    }

    public function isUserAvailable($email) {
        $userRow = $this->db->users()->where('email', $email);

        if ($userRow->fetch()) {
            return array('status' => false, 'message' => 'Email Id is taken');
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return array('status' => true, 'message' => 'Email Id is available');
        } else {
            return array('status' => false, 'message' => 'Email Id is invalid');
        }
    }

    public function verifyEmailSendReset($emailId) {
        require_once 'PassHash.php';
        require_once 'Utils.php';
        $util = new UtilHandler();
        $userRow = $this->db->users()->where('email', $emailId);

        if ($userRow->fetch()) {
            if ($this->sendResetPswdEmail($emailId, $util)) {
                return array('status' => 200, 'message' => 'Reset link sent, please check your email');
            } else {
                return array('status' => 500, 'message' => 'Error in sending email');
            }
        } elseif (filter_var($emailId, FILTER_VALIDATE_EMAIL)) {
            return array('status' => 404, 'message' => 'Email Id not available');
        } else {
            return array('status' => 400, 'message' => 'Email Id is invalid');
        }
    }

    private function sendResetPswdEmail($email, $util) {
        require_once 'Common.php';
        $common = new Common();
        $resetId = md5(uniqid(rand(), true));
        $userRow = array();
        foreach ($this->db->users()->where('email', $email) as $row) {
            $userRow = $common->getRowArrayUsingKeys($row, $common->getKeysArray('users'));
        }
        $userRow['resetMd5'] = $resetId;
        $status = $userRow['status'];
        $emailNotification = false;
        $result = false;
        if ($status == 1) {
            $result = $this->db->users()->where('email', $email)->update($userRow);
            if ($result) {
                $emailNotification = $util->sendResetEmail($email, $resetId);
            }
        } else {
            $response = $this->sendEmailOnSuccess($email, $util);
            $emailNotification = ($response['status'] == 201 ? true : false);
        }
        return ($emailNotification ? true : false);
    }

    public function getResetPermission($emailId, $resetId) {
        require_once 'Common.php';
        $common = new Common();
        $resetIdNew = md5(uniqid(rand(), true));
        $userRow = array();
        $result = false;
        foreach ($this->db->users()->where('email', $emailId) as $row) {
            $userRow = $common->getRowArrayUsingKeys($row, $common->getKeysArray('users'));
        }
        if (sizeof($userRow) > 0 && $userRow['resetMd5'] == $resetId) {
            $userRow['resetMd5'] = $resetIdNew;
            $result = $this->db->users()->where('email', $emailId)->update($userRow);
        }
        if ($result) {
            return array('status' => 200, 'message' => array('resetKey' => $resetIdNew));
        } else {
            return array('status' => 400, 'message' => 'The reset link is either used or a new link has been requested.');
        }
    }

    public function getUserByEmail($email) {
        $userRow = $this->db->users()->where('email', $email);

        if ($userRow->fetch()) {
            $api_key = '';
            $status = NULL;
            foreach ($userRow as $row) {
                $api_key = $row['api_key'];
                $status = $row['status'];
            }
            if ($status == 1) {
                return array('status' => 200, 'api_key' => $api_key);
            } else {
                return array('status' => 202, 'message' => 'Account exists but still not activated');
            }
        } else {
            return array('status' => 500, 'message' => 'An error occurred. Please try again');
        }
    }

    public function getApiKeyById($user_id) {
        $apiKeyRow = $this->db->users()->where('id', $user_id);
        if ($apiKeyRow->fetch()) {
            $api_key = '';
            foreach ($apiKeyRow as $row) {
                $api_key = $row['api_key'];
            }
            return $api_key;
        } else {
            return NULL;
        }
    }

    public function getUserId($api_key) {
        $userIdRow = $this->db->users()->where('api_key', $api_key);
        if ($userIdRow->fetch()) {
            $user_id = '';
            foreach ($userIdRow as $row) {
                $user_id = $row['id'];
            }
            return array('id' => $user_id);
        } else {
            return NULL;
        }
    }

    public function isValidApiKey($api_key) {
        $apiKeyRow = $this->db->users()->where('api_key', $api_key);
        if ($apiKeyRow->fetch()) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }

    public function activateUser($emailMd5, $timestampMd5) {
        require_once 'PassHash.php';
        $email = PassHash::encrypt_decrypt('decrypt', $emailMd5);
        $userRow = $this->db->users()->where('email', $email);
        $fetchedRow = array();
        foreach ($userRow as $row) {
            $fetchedRow = array('id' => $row['id'], 'name' => $row['name'], 'email' => $row['email'], 'password_hash' => $row['password_hash'], 'api_key' => $row['api_key'], 'status' => $row['status'], 'created_at' => $row['created_at']);
        }
        $timeStampMatch = md5($fetchedRow['created_at']) == $timestampMd5;
        if ($timeStampMatch) {
            $userRow = $this->db->users()->where('email', $email)->fetch();
            if ($fetchedRow['status'] == 1) {
                return array('status' => 203, 'message' => 'User is already verified please login');
            } else {
                $fetchedRow['status'] = 1;
                $userRow->update($fetchedRow);
                return array('status' => 200, 'message' => 'Verification complete please login');
            }
        } else {
            return array('status' => 500, 'message' => 'Some error in verification');
        }
    }

    public function createWriterOrCategory($writerOrCtg) {
        $result = $this->db->writersNCtgs->insert($writerOrCtg);
        return array('status' => 200, 'message' => $result);
    }

    public function updateWriterOrCategory($id, $writerOrCtg) {
        $writerOrCtgRow = $this->db->writersNCtgs()->where('id', $id);
        if ($writerOrCtgRow->fetch()) {
            $writerOrCtgRow->update($writerOrCtg);
            $updatedRow = array();
            $newRow = $this->db->writersNCtgs()->where('id', $id);
            foreach ($newRow as $row) {
                $updatedRow = array('id' => $row['id'], 'name' => $row['name'], 'imageURL' => $row['imageURL'], 'description' => $row['description']);
            }
            return array('status' => 200, 'message' => 'Writer, category updated successfully', 'data' => $updatedRow);
        } else {
            return array('status' => 200, 'message' => 'Writer or category id: $id does not exist', 'data' => false);
        }
    }

    public function deleteWriterOrCategory($id) {
        $writerOrCtgRow = $this->db->writersNCtgs()->where('id', $id);
        if ($writerOrCtgRow->fetch()) {
            $writerOrCtgRow->delete();
            return array('status' => 200, 'datafound' => true, 'message' => 'Writer, category deleted successfully');
        } else {
            return array('status' => 200, 'datafound' => false, 'message' => 'Writer or category id: ' . $id . ' does not exist');
        }
    }

    public function getWriterOrCategory() {
        $writersNCtgsList = array();
        foreach ($this->db->writersNCtgs() as $row) {
            $writersNCtgsList[] = array('id' => $row['id'], 'name' => $row['name'], 'imageURL' => $row['imageURL'], 'description' => $row['description']);
        }
        if (sizeof($writersNCtgsList) > 0) {
            return array('status' => 200, 'message' => $writersNCtgsList);
        } else {
            return array('status' => 200, 'message' => $writersNCtgsList);
        }
    }

    public function createQuote($Quote) {
        $result = $this->db->quotes->insert($Quote);
        if ($result) {
            return array('status' => 200, 'message' => $result);
        } else {
            return array('status' => 400, 'message' => 'Database error occurred.');
        }
    }

    public function updateQuote($idQuote, $quote) {
        $quoteRow = $this->db->quotes()->where('id', $idQuote);
        if ($quoteRow->fetch()) {
            $quoteRow->update($quote);
            $updatedRow = array();
            $newRow = $this->db->quotes()->where('id', $idQuote);
            foreach ($newRow as $row) {
                $updatedRow = array('id' => $row['id'], 'quote' => $row['quote'], 'wrNctg_ref' => $row['wrNctg_ref']);
            }
            return array('status' => 200, 'message' => 'Quote updated successfully', 'data' => $updatedRow);
        } else {
            return array('status' => 200, 'message' => 'Quote id: $id does not exist', 'data' => false);
        }
    }

    public function deleteQuote($idQuote) {
        $quoteRow = $this->db->quotes()->where('id', $idQuote);
        if ($quoteRow->fetch()) {
            $result = $quoteRow->delete();
            return array('status' => (bool) $result, 'message' => 'Quote Deleted successfully');
        } else {
            return array('status' => false, 'message' => 'Quote id: ' . $idQuote . ' Quote does not exist');
        }
    }

    public function getQuotesForWriterOrCategory($idWriterOrCtg) {
        $quotesFromDb = $this->db->quotes()->where('wrNctg_ref', $idWriterOrCtg);
        $quoteList = array();
        foreach ($quotesFromDb as $row) {
            array_push($quoteList, array('id' => $row['id'], 'quote' => $row['quote'], 'wrNctg_ref' => $row['wrNctg_ref']));
        }
        if ($quoteList) {
            return array('status' => 200, 'message' => $quoteList);
        } else {
            return array('status' => 400, 'message' => 'Unknown database error');
        }
    }

    public function getAllQuotesData() {
        $writersNCtgsList = array();
        foreach ($this->db->writersNCtgs() as $row) {
            $writersNCtgsList[] = array('id' => $row['id'], 'name' => $row['name'], 'imageURL' => $row['imageURL'], 'description' => $row['description']);
            $cnt = count($writersNCtgsList);
            for ($i = 0; $i < $cnt; $i++) {
                $idWriterOrCtg = $writersNCtgsList[$i]['id'];
                $quotesFromDb = $this->db->quotes()->where('wrNctg_ref', $idWriterOrCtg);
                $quoteList = $this->getQuoteRowArray($quotesFromDb);
                $writersNCtgsList[$i]['quotes'] = $quoteList;
            }
        }
        return $writersNCtgsList;
    }

    private function getQuoteRowArray($quotesFromDb) {
        $quoteList = array();
        foreach ($quotesFromDb as $row) {
            $quoteList = array('id' => $row['id'], 'quote' => $row['quote'], 'wrNctg_ref' => $row['wrNctg_ref']);
        }
        return $quoteList;
    }

}

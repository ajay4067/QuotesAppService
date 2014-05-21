<?php

class DbHandler {

    private $db;

    function __construct() {
        require_once 'DbConnect.php';
        $conn = new DbConnect();
        $this->db = $conn->connect();
    }

    public function createUser($name, $email, $password) {
        require_once 'PassHash.php';
        require_once 'Utils.php';
        $response = array();
        $util = new UtilHandler();

        $isUserPresent = $this->db->users()->where('email', $email);
        if (!$isUserPresent->fetch()) {
            $password_hash = PassHash::hash($password);
            $api_key = $this->generateApiKey();
            $status = 0;
            $userRow = array('name' => $name, 'email' => $email, 'password_hash' => $password_hash, 'api_key' => $api_key, 'status' => $status);
            $result = $this->db->users->insert($userRow);
            if ($result) {
                $response = $this->sendEmailOnSuccess($email, $util);
            } else {
                $response = array('status' => 500, 'message' => 'User could not be created due to database error');
            }
        } else {
            $response = array('status' => 200, 'message' => 'Email Id is taken');
        }
        return $response;
    }

    private function generateApiKey() {
        return md5(uniqid(rand(), true));
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

    public function resendEmailInvite($email) {
        require_once 'Utils.php';
        $util = new UtilHandler();
        $response = array();
        $isUserPresent = $this->db->users()->where('email', $email);
        if ($isUserPresent->fetch()) {
            $response = $this->sendEmailOnSuccess($email, $util);
        } else {
            $response = array('status' => 500, 'message' => 'User was not found.');
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

    public function isValidApiKey($api_key) {
        $apiKeyRow = $this->db->users()->where('api_key', $api_key);
        if ($apiKeyRow->fetch()) {
            $userId = null;
            foreach ($apiKeyRow as $row) {
                $userId = $row['id'];
            }
            return array('found' => TRUE, 'userId' => $userId);
        } else {
            return array('found' => FALSE, 'userId' => null);
        }
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
        if ($result) {
            $result['editable'] = true;
            $result['likes'] = 0;
            unset($result['user_ref']);
        }
        return array('status' => 200, 'message' => $result);
    }

    public function updateWriterOrCategory($id, $writerOrCtg, $user_id) {
        $writerOrCtgRow = $this->db
                        ->writersNCtgs()->where('id = ? AND user_ref = ?', $id, $user_id);
        if ($writerOrCtgRow->fetch()) {
            $writerOrCtgRow->update($writerOrCtg);
            $updatedRow = array();
            $liked = false;
            if ($this->db->categorylikes()
                            ->where('user_id = ? AND ctg_id = ?', $user_id, $id)->fetch()) {
                $liked = true;
            }
            $newRow = $this->db->writersNCtgs()->where('id', $id);
            foreach ($newRow as $row) {
                $updatedRow = array('id' => $row['id'], 'editable' => true,
                    'name' => $row['name'], 'likes' => $row['likes'],
                    'description' => $row['description'], 'liked' => $liked);
            }
            return array('status' => 200, 'message' => $updatedRow);
        } else {
            return array('status' => 200, 'message' => false, 'error' => 'Writer or category id: ' . $id . ' does not exist, or else you do not have permission', 'data');
        }
    }

    public function deleteWriterOrCategory($id, $user_id) {
        $writerOrCtgRow = $this->db->writersNCtgs()->where('id = ? AND user_ref = ?', $id, $user_id);
        if ($writerOrCtgRow->fetch()) {
            $writerOrCtgRow->delete();
            return array('status' => 200, 'message' => true, 'error' => false);
        } else {
            return array('status' => 200, 'message' => false, 'error' => 'Writer or category id: ' . $id . ' does not exist or you do not have valid permission');
        }
    }

    public function likeCategory($id, $user_id) {
        if ($this->db->categorylikes()
                        ->where('user_id = ? AND ctg_id = ?', $user_id, $id)->fetch()) {
            return array('status' => 400, 'message' => false, 'error' => 'already liked');
        } else {
            $updateRow = array('user_id' => $user_id, 'ctg_id' => $id);
            $this->db->categorylikes->insert($updateRow);
            $countLikes = 0;
            $likeRows = $this->db->categorylikes->where('ctg_id', $id);
            foreach ($likeRows as $row) {
                $countLikes++;
            }
            $categoryRow = $this->db->writersnctgs->where('id', $id);
            $updateCount = array('likes' => $countLikes);
            if ($categoryRow) {
                $result = $categoryRow->update($updateCount);
            }
            if ($result) {
                return array('status' => 200, 'message' => array('count' => $countLikes),
                    'error' => false);
            } else {
                return array('status' => 500, 'message' => 'database error',
                    'error' => true);
            }
        }
    }

    public function unlikeCategory($id, $user_id) {
        if (!$this->db->categorylikes()
                        ->where('user_id = ? AND ctg_id = ?', $user_id, $id)->fetch()) {
            return array('status' => 400, 'message' => false,
                'error' => 'already unliked or never liked');
        } else {
            $likedRow = $this->db->categorylikes()
                    ->where('user_id = ? AND ctg_id = ?', $user_id, $id);
            if ($likedRow) {
                $likedRow->delete();
            }
            $countLikes = 0;
            $likeRows = $this->db->categorylikes->where('ctg_id', $id);
            foreach ($likeRows as $row) {
                $countLikes++;
            }
            $categoryRow = $this->db->writersnctgs->where('id', $id);
            $updateCount = array('likes' => $countLikes);
            if ($categoryRow) {
                $result = $categoryRow->update($updateCount);
            }
            if ($result) {
                return array('status' => 200, 'message' => array('count' => $countLikes),
                    'error' => false);
            } else {
                return array('status' => 500, 'message' => 'database error',
                    'error' => true);
            }
        }
    }

    public function getWriterOrCategory($user_id) {
        $writersNCtgsList = array();
        foreach ($this->db->writersNCtgs() as $row) {
            $ctgId = $row['id'];
            $liked = false;
            $editable = false;
            if ($this->db->categorylikes()
                            ->where('user_id = ? AND ctg_id = ?', $user_id, $ctgId)->fetch()) {
                $liked = true;
            }
            if ($row['user_ref'] == $user_id) {
                $editable = true;
            }
            $writersNCtgsList[] = array('id' => $ctgId, 'editable' => $editable,
                'name' => $row['name'], 'likes' => $row['likes'],
                'description' => $row['description'], 'liked' => $liked);
        }
        return array('status' => 200, 'message' => $writersNCtgsList);
    }

}

<?php

class DbHandlerQuote {

    private $db;

    function __construct() {
        require_once 'DbConnect.php';
        $conn = new DbConnect();
        $this->db = $conn->connect();
    }

    public function likeQuote($id, $user_id) {
        if ($this->db->quotelikes()
                        ->where('user_id = ? AND quote_id = ?', $user_id, $id)->fetch()) {
            return array('status' => 400, 'message' => false, 'error' => 'already liked');
        } else {
            $updateRow = array('user_id' => $user_id, 'quote_id' => $id);
            $this->db->quotelikes->insert($updateRow);
            $countLikes = 0;
            $likeRows = $this->db->quotelikes->where('quote_id', $id);
            foreach ($likeRows as $row) {
                $countLikes++;
            }
            $categoryRow = $this->db->quotes->where('id', $id);
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

    public function unlikeQuote($id, $user_id) {
        if (!$this->db->quotelikes()
                        ->where('user_id = ? AND quote_id = ?', $user_id, $id)->fetch()) {
            return array('status' => 400, 'message' => false,
                'error' => 'already unliked or never liked');
        } else {
            $likedRow = $this->db->quotelikes()
                    ->where('user_id = ? AND quote_id = ?', $user_id, $id);
            if ($likedRow) {
                $likedRow->delete();
            }
            $countLikes = 0;
            $likeRows = $this->db->quotelikes->where('id', $id);
            foreach ($likeRows as $row) {
                $countLikes++;
            }
            $categoryRow = $this->db->quotes->where('id', $id);
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

    public function createQuote($Quote) {
        $result = $this->db->quotes->insert($Quote);
        if ($result) {
            $result['editable'] = true;
            $result['likes'] = 0;
            unset($result['user_ref']);
            $quoteText = $result['quote'];
            unset($result['quote']);
            $result['text'] = $quoteText;
            return array('status' => 200, 'message' => $result);
        } else {
            return array('status' => 400, 'message' => 'Database error.');
        }
    }

    public function updateQuote($idQuote, $quote, $user_id) {
        $quoteRow = $this->db->quotes()
                ->where('id = ? AND user_ref = ?', $idQuote, $user_id);
        if ($quoteRow->fetch()) {
            $quoteRow->update($quote);
            $updatedRow = array();
            $liked = false;
            if ($this->db->quotelikes()
                            ->where('user_id = ? AND quote_id = ?', $user_id, $idQuote)->fetch()) {
                $liked = true;
            }
            $newRow = $this->db->quotes()->where('id', $idQuote);
            foreach ($newRow as $row) {
                $updatedRow = array('id' => $row['id'], 'text' => $row['quote'],
                    'wrNctg_ref' => $row['wrNctg_ref'], 'liked' => $liked,
                    'likes' => $row['likes'], 'editable' => true);
            }
            return array('status' => 200, 'message' => $updatedRow, 'error' => false);
        } else {
            return array('status' => 400, 'message' => false,
                'error' => 'Quote id: $id does not exist or you have no permission');
        }
    }

    public function deleteQuote($idQuote, $user_id) {
        $quoteRow = $this->db->quotes()->where('id = ? AND user_ref = ?', $idQuote, $user_id);
        if ($quoteRow->fetch()) {
            $result = $quoteRow->delete();
            return array('status' => 200, 'message' => true, 'error' => false);
        } else {
            return array('status' => false, 'message' => 'Quote id: ' . $idQuote . ' Quote does not exist or you do not have valid permission');
        }
    }

    public function getQuotes($idWriterOrCtg, $user_id) {
        $quotesFromDb = $this->db->quotes()->where('wrNctg_ref', $idWriterOrCtg);
        $quoteList = array();
        foreach ($quotesFromDb as $row) {
            $quoteId = $row['id'];
            $liked = false;
            $editable = false;
            if ($this->db->quotelikes()
                            ->where('user_id = ? AND quote_id = ?', $user_id, $quoteId)->fetch()) {
                $liked = true;
            }
            if ($row['user_ref'] == $user_id) {
                $editable = true;
            }
            array_push($quoteList, array('id' => $row['id'],
                'text' => $row['quote'], 'likes' => $row['likes'], 'wrNctg_ref' => $row['wrNctg_ref'],
                'liked' => $liked, 'editable' => $editable));
        }
        if ($quoteList) {
            return array('status' => 200, 'message' => $quoteList);
        } else {
            return array('status' => 400, 'message' => false);
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

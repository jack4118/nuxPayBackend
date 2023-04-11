<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * Date 27/03/2018.
    **/

    class Memo {
        
        function __construct($db, $general, $setting) {
            $this->db = $db;
            $this->general = $general;
            $this->setting = $setting;
        }
        
        public function addMemo($params, $site) {
            $db = $this->db;
            $setting = $this->setting;

            if(empty($params['imageData']) || empty($params['imageType']) || empty($params['imageName']) || empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot empty.", 'data' => "");

            if(empty($params['subject'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'subjectError',
                                            'msg' => 'This field cannot be left blank.'
                                        );
            }
            if(empty($params['description'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'descriptionError',
                                            'msg' => 'This field cannot be left blank.'
                                        );
            }
            if($params['status']!="Active" && $params['status']!="Inactive" && $params['status']!="Deleted") {
                $errorFieldArr[] = array(
                                            'id'  => 'statusError',
                                            'msg' => 'This field value is invalid.'
                                        );
            }
            if(empty($params['priority'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'priorityError',
                                            'msg' => 'This field cannot be left blank.'
                                        );
            }
            else if(!is_numeric($params['priority'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'priorityError',
                                            'msg' => 'This field only accept whole number.'
                                        );
            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be left blank.", 'data' => $data);

            $insertData = array (
                                    'data' => $params['imageData'],
                                    'type' => $params['imageType'],
                                    'created_at' => $db->now()
                                );
            $imageID = $db->insert('uploads', $insertData);

            if(empty($imageID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

            $leaderID = '';
            if($params['leaderUsername']) {
                $db->where('username', $params['leaderUsername']);
                $leaderID = $db->getValue('client', 'id');

                if(empty($leaderID)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'leaderUsernameError',
                                                'msg' => 'Username does not exist.'
                                            );

                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                }
            }

            $genealogy = $setting->systemSetting['memoGroupLeaderGenealogy'];
            if(empty($genealogy))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            $insertData = array (
                                    'subject' => $params['subject'],
                                    'description' => $params['description'],
                                    'status' => $params['status'],
                                    'image_id' => $imageID,
                                    'image_name' => $params['imageName'],
                                    'creator_id' => $params['clientID'],
                                    'creator_type' => $site,
                                    'priority' => $params['priority'],
                                    'genealogy' => $genealogy,
                                    'group_leader_id' => $leaderID,
                                    'reference_id' => '',
                                    'created_at' => $db->now(),
                                    'updated_at' => $db->now()
                                );
            $memoID = $db->insert('mlm_memo', $insertData);

            if(empty($memoID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getMemo($params) {
            $db = $this->db;

            if(empty($params['id']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            $getLeaderUsername = "(SELECT username FROM client WHERE client.id = group_leader_id) AS group_leader_username";
            $getImageData = "(SELECT data FROM uploads WHERE uploads.id = image_id) AS image_data";
            $getImageType = "(SELECT type FROM uploads WHERE uploads.id = image_id) AS image_type";
            $db->where('id', $params['id']);
            $result = $db->get('mlm_memo', 1, 'subject, description, priority, status, image_name, '.$getLeaderUsername.', '.$getImageData.', '.$getImageType);

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found.", 'data' => "");

            foreach($result as $array) {
                foreach ($array as $k => $v) {
                    $memo[$k] = $v;
                }
            }

            $data['memo'] = $memo;
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function editMemo($params, $site) {
            $db = $this->db;

            if(empty($params['id']) || empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            if($params['imageFlag']) {
                if(empty($params['imageData']) || empty($params['imageType']) || empty($params['imageName']))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Image upload error.", 'data' => "");
            }

            if(empty($params['subject'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'subjectError',
                                            'msg' => 'This field cannot be left blank.'
                                        );
            }
            if(empty($params['description'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'descriptionError',
                                            'msg' => 'This field cannot be left blank.'
                                        );
            }
            if($params['status']!="Active" && $params['status']!="Inactive" && $params['status']!="Deleted") {
                $errorFieldArr[] = array(
                                            'id'  => 'statusError',
                                            'msg' => 'This field value is invalid.'
                                        );
            }
            if(empty($params['priority'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'priorityError',
                                            'msg' => 'This field cannot be left blank.'
                                        );
            }
            else if(!is_numeric($params['priority'])) {
                $errorFieldArr[] = array(
                                            'id'  => 'priorityError',
                                            'msg' => 'This field only accept whole number.'
                                        );
            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => $data);

            $db->where('id', $params['id']);
            $image = $db->getOne('mlm_memo', 'image_id, image_name');
            
            if(empty($image))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            if($params['imageFlag']) {
                $insertData = array (
                                        'data' => $params['imageData'],
                                        'type' => $params['imageType'],
                                        'created_at' => $db->now()
                                    );
                $image['image_id'] = $db->insert('uploads', $insertData);
                $image['image_name'] = $params['imageName'];
            }

            if(empty($image['image_id']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            $leaderID = '';
            if($params['leaderUsername']) {
                $db->where('username', $params['leaderUsername']);
                $leaderID = $db->getValue('client', 'id');

                if(empty($leaderID)) {
                    $errorFieldArr[] = array(
                                                'id'  => 'leaderUsernameError',
                                                'msg' => 'Username does not exist.'
                                            );

                    $data['field'] = $errorFieldArr;
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => $data);
                }
            }

            $updateData = array (
                                    'subject' => $params['subject'],
                                    'description' => $params['description'],
                                    'status' => $params['status'],
                                    'image_id' => $image['image_id'],
                                    'image_name' => $image['image_name'],
                                    'creator_id' => $params['clientID'],
                                    'creator_type' => $site,
                                    'priority' => $params['priority'],
                                    'group_leader_id' => $leaderID,
                                    'updated_at' => $db->now()
                                );
            $db->where('id', $params['id']);
            $db->update('mlm_memo', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function removeMemo($params) {
            $db = $this->db;

            if(empty($params['id']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            $updateData = array('status' => "Deleted", 'updated_at' => $db->now());
            $db->where('id', $params['id']);
            $db->update('mlm_memo', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getMemoList($params) {
            $db = $this->db;
            $general = $this->general;

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);

            $searchData = $params['searchData'];

            if(count($searchData) > 0) {
                foreach($searchData as $k => $v) {
                    $dataName = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);

                    switch($dataName) {
                        case 'subject':
                            $db->where('subject', $dataValue);
                            break;

                        case 'status':
                            $db->where('status', $dataValue);
                            break;

                        case 'createdAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                $db->where('created_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Date from cannot be later than date to.', 'data'=>$data);

                                if($dateTo == $dateFrom)
                                    $dateTo += 86399;
                                $db->where('created_at', date('Y-m-d H:i:s', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;

                        case 'updatedAt':
                            $dateFrom = trim($v['tsFrom']);
                            $dateTo = trim($v['tsTo']);
                            if(strlen($dateFrom) > 0) {
                                if($dateFrom < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                $db->where('updated_at', date('Y-m-d H:i:s', $dateFrom), '>=');
                            }
                            if(strlen($dateTo) > 0) {
                                if($dateTo < 0)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Invalid date.', 'data'=>"");
                                    
                                if($dateTo < $dateFrom)
                                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Date from cannot be later than date to.', 'data'=>$data);

                                if($dateTo == $dateFrom)
                                    $dateTo += 86399;
                                $db->where('updated_at', date('Y-m-d H:i:s', $dateTo), '<=');
                            }
                                
                            unset($dateFrom);
                            unset($dateTo);
                            unset($columnName);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }
            $db->orderBy('id', 'Desc');
            $copyDb = $db->copy();
            $result = $db->get('mlm_memo', $limit, 'id, subject, description, priority, status, creator_id, creator_type, created_at, updated_at');

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found.", 'data' => "");

            foreach($result as $value) {
                if($value['creator_type'] == 'SuperAdmin')
                    $superAdminID[] = $value['creator_id'];
                else if($value['creator_type'] == 'Admin')
                    $adminID[] = $value['creator_id'];
                else if ($value['creator_type'] == 'Member')
                    $clientID[] = $value['creator_id'];
            }
            if(!empty($superAdminID)) {
                $db->where('id', $superAdminID, 'IN');
                $dbResult = $db->get('users', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['SuperAdmin'][$value['id']] = $value['username'];
                }
            }
            if(!empty($adminID)) {
                $db->where('id', $adminID, 'IN');
                $dbResult = $db->get('admin', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['Admin'][$value['id']] = $value['username'];
                }
            }
            if(!empty($clientID)) {
                $db->where('id', $clientID, 'IN');
                $dbResult = $db->get('client', null, 'id, username');
                foreach($dbResult as $value) {
                    $usernameList['Member'][$value['id']] = $value['username'];
                }
            }

            foreach($result as $array) {
                foreach ($array as $k => $v) {
                    if($k == "creator_id") {

                    }
                    else if($k == "creator_type")
                        $memo['creator_username'] = $usernameList[$v][$array['creator_id']];
                    else
                        $memo[$k] = $v;
                }
                $memoList[] = $memo;
            }

            $totalRecords = $copyDb->getValue('mlm_memo', 'count(id)');
            $data['memoList'] = $memoList;
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }
    }
?>
<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * Date 27/03/2018.
    **/

    class Announcement {
        
        function __construct($db, $general, $setting) {
            $this->db = $db;
            $this->general = $general;
            $this->setting = $setting;
        }
        
        public function addAnnouncement($params, $site) {
            $db = $this->db;
            $setting = $this->setting;

            if(empty($params['imageData']) || empty($params['imageType']) || empty($params['imageName']) || empty($params['attachmentData']) || empty($params['attachmentType']) || empty($params['attachmentName']) || empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

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

            $insertData = array (
                                    'data' => $params['attachmentData'],
                                    'type' => $params['attachmentType'],
                                    'created_at' => $db->now()
                                );
            $attachmentID = $db->insert('uploads', $insertData);

            if(empty($attachmentID))
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

            $genealogy = $setting->systemSetting['announcementGroupLeaderGenealogy'];
            if(empty($genealogy))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "here", 'data' => "");

            $insertData = array (
                                    'subject' => $params['subject'],
                                    'description' => $params['description'],
                                    'status' => $params['status'],
                                    'image_id' => $imageID,
                                    'image_name' => $params['imageName'],
                                    'attachment_id' => $attachmentID,
                                    'attachment_name' => $params['attachmentName'],
                                    'creator_id' => $params['clientID'],
                                    'creator_type' => $site,
                                    'genealogy' => $genealogy,
                                    'group_leader_id' => $leaderID,
                                    'reference_id' => '',
                                    'created_at' => $db->now(),
                                    'updated_at' => $db->now()
                                );
            $announcementID = $db->insert('mlm_announcement', $insertData);

            if(empty($announcementID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getAnnouncement($params) {
            $db = $this->db;

            if(empty($params['id']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            $getLeaderUsername = "(SELECT username FROM client WHERE client.id = group_leader_id) AS group_leader_username";
            $getImageData = "(SELECT data FROM uploads WHERE uploads.id = image_id) AS image_data";
            $getImageType = "(SELECT type FROM uploads WHERE uploads.id = image_id) AS image_type";
            $getAttachmentData = "(SELECT data FROM uploads WHERE uploads.id = attachment_id) AS attachment_data";
            $getAttachmentType = "(SELECT type FROM uploads WHERE uploads.id = attachment_id) AS attachment_type";
            $db->where('id', $params['id']);
            $result = $db->get('mlm_announcement', 1, 'subject, description, status, image_name, attachment_name, '.$getLeaderUsername.', '.$getImageData.', '.$getImageType.', '.$getAttachmentData.', '.$getAttachmentType);

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found.", 'data' => "");

            foreach($result as $array) {
                foreach ($array as $k => $v) {
                    $announcement[$k] = $v;
                }
            }

            $data['announcement'] = $announcement;
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function editAnnouncement($params, $site) {
            $db = $this->db;

            if(empty($params['id']) || empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            if($params['imageFlag']) {
                if(empty($params['imageData']) || empty($params['imageType']) || empty($params['imageName']))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Upload image error.", 'data' => "");
            }

            if($params['attachmentFlag']) {
                if(empty($params['attachmentData']) || empty($params['attachmentType']) || empty($params['attachmentName']))
                    return array('status' => "error", 'code' => 1, 'statusMsg' => "Upload attachment error.", 'data' => "");
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

            $data['field'] = $errorFieldArr;

            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => $data);

            $db->where('id', $params['id']);
            $upload = $db->getOne('mlm_announcement', 'image_id, attachment_id, image_name, attachment_name');
            
            if(empty($upload))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            
            if($params['imageFlag']) {
                $insertData = array (
                                        'data' => $params['imageData'],
                                        'type' => $params['imageType'],
                                        'created_at' => $db->now()
                                    );
                $upload['image_id'] = $db->insert('uploads', $insertData);
                $upload['image_name'] = $params['imageName'];
            }

            if(empty($upload['image_id']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            if($params['attachmentFlag']) {
                $insertData = array (
                                        'data' => $params['attachmentData'],
                                        'type' => $params['attachmentType'],
                                        'created_at' => $db->now()
                                    );
                $upload['attachment_id'] = $db->insert('uploads', $insertData);
                $upload['attachment_name'] = $params['attachmentName'];
            }

            if(empty($upload['attachment_id']))
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
                                    'image_id' => $upload['image_id'],
                                    'image_name' => $upload['image_name'],
                                    'attachment_id' => $upload['attachment_id'],
                                    'attachment_name' => $upload['attachment_name'],
                                    'creator_id' => $params['clientID'],
                                    'creator_type' => $site,
                                    'group_leader_id' => $leaderID,
                                    'updated_at' => $db->now()
                                );
            $db->where('id', $params['id']);
            $db->update('mlm_announcement', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function removeAnnouncement($params) {
            $db = $this->db;

            if(empty($params['id']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            $updateData = array('status' => "Deleted", 'updated_at' => $db->now());
            $db->where('id', $params['id']);
            $db->update('mlm_announcement', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getAnnouncementList($params) {
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
            $result = $db->get('mlm_announcement', $limit, 'id, subject, description, status, creator_id, creator_type, created_at, updated_at');

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
                        $announcement['creator_username'] = $usernameList[$v][$array['creator_id']];
                    else
                        $announcement[$k] = $v;
                }
                $announcementList[] = $announcement;
            }

            $totalRecords = $copyDb->getValue('mlm_announcement', 'count(id)');
            $data['announcementList'] = $announcementList;
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function newsDisplay($params) {
            $db =  $this->db;

            $db->where('status', "Active");
            $getBase64 = "(SELECT data FROM uploads WHERE uploads.id=image_id) AS base_64";
            $getFileType = "(SELECT type FROM uploads WHERE uploads.id=image_id) AS file_type";
            $announcement = $db->get('mlm_announcement', null, 'id, subject, description, created_at,'.$getBase64.','.$getFileType);

            if(empty($announcement))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No result found.", 'data' => "");

            foreach($announcement as $value) {

                $news['file_type'] = $value['file_type'];
                $news['base_64'] = $value['base_64'];
                $news['subject'] = $value['subject'];
                $news['description'] = $value['description'];
                $news['created_at'] = $value['created_at'];
                $news['id'] = $value['id'];
                $newsList[] = $news;

                $details['id'] = $value['id'];
                $details['file_type'] = $value['file_type'];
                $details['base_64'] = $value['base_64'];
                $details['subject'] = $value['subject'];
                $details['description'] = $value['description'];
                $details['created_at'] = $value['created_at'];
                $details['id'] = $value['id'];
                $detailsList[] = $details;
            }

            $data['news'] = $newsList;
            $data['details'] = $detailsList;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }

        public function newsDownload($params) {
            $db = $this->db;

            if(empty($params['announcementID']))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "Failed to download.", 'data' => "");

            $db->where('id', $params['announcementID']);
            $db->where('status', 'Active');
            $getBase64 = "(SELECT data FROM uploads WHERE uploads.id=attachment_id) AS base_64";
            $getFileType = "(SELECT type FROM uploads WHERE uploads.id=attachment_id) AS file_type";
            $result = $db->get('mlm_announcement', 1, 'attachment_name, '.$getBase64.','.$getFileType);

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "Failed to download.", 'data' => "");

            foreach($result as $value) {
                $download['attachment_name'] = $value['attachment_name'];
                $download['file_type'] = $value['file_type'];
                $download['base_64'] = $value['base_64'];
            }

            $data['download'] = $download;
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function dashboardNews() {
            $db =  $this->db;

            $db->where('status', "Active");
            $getBase64 = "(SELECT data FROM uploads WHERE uploads.id=image_id) AS base_64";
            $getFileType = "(SELECT type FROM uploads WHERE uploads.id=image_id) AS file_type";
            $announcement = $db->get('mlm_announcement', null, 'id, subject, description, created_at,'.$getBase64.','.$getFileType);

            if(empty($announcement))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No result found.", 'data' => "");

            foreach($announcement as $key => $value) {
                $news['file_type'] = $value['file_type'];
                $news['base_64'] = $value['base_64'];
                $news['created_at'] = $value['created_at'];
                $news['subject'] = $value['subject'];
                $news['description'] = $value['description'];
                $newsList[] = $news;
            }

            $data['news'] = $newsList;

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }
    }
?>
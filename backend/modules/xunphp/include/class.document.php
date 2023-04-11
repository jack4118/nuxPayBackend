<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * Date 27/03/2018.
    **/

    class Document {
        
        function __construct($db, $general, $setting) {
            $this->db = $db;
            $this->general = $general;
            $this->setting = $setting;
        }
        
        public function addDocument($params, $site) {
            $db = $this->db;
            $setting = $this->setting;

            if(empty($params['attachmentData']) || empty($params['attachmentType']) || empty($params['attachmentName']) || empty($params['clientID']))
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
                                            'id'  => 'status',
                                            'msg' => 'This field value is invalid.'
                                        );
            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be left blank.", 'data' => $data);

            $insertData = array (
                                    'data' => $params['attachmentData'],
                                    'type' => $params['attachmentType'],
                                    'created_at' => $db->now()
                                );
            $attachmentID = $db->insert('uploads', $insertData);

            if(empty($attachmentID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

            $insertData = array (
                                    'subject' => $params['subject'],
                                    'description' => $params['description'],
                                    'status' => $params['status'],
                                    'attachment_id' => $attachmentID,
                                    'attachment_name' => $params['attachmentName'],
                                    'creator_id' => $params['clientID'],
                                    'creator_type' => $site,
                                    'type' => 'normal',
                                    'reference_id' => '',
                                    'created_at' => $db->now(),
                                    'updated_at' => $db->now()
                                );
            $documentID = $db->insert('mlm_document', $insertData);

            if(empty($documentID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "", 'data' => "");

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getDocument($params) {
            $db = $this->db;

            if(empty($params['id']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            $getAttachmentData = "(SELECT data FROM uploads WHERE uploads.id = attachment_id) AS attachment_data";
            $getAttachmentType = "(SELECT type FROM uploads WHERE uploads.id = attachment_id) AS attachment_type";
            $db->where('id', $params['id']);
            $result = $db->get('mlm_document', 1, 'subject, description, status, attachment_name, '.$getAttachmentData.', '.$getAttachmentType);

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found.", 'data' => "");

            foreach($result as $array) {
                foreach ($array as $k => $v) {
                    $document[$k] = $v;
                }
            }

            $data['document'] = $document;
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function editDocument($params, $site) {
            $db = $this->db;

            if(empty($params['id']) || empty($params['clientID']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

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
                                            'id'  => 'status',
                                            'msg' => 'This field value is invalid.'
                                        );
            }

            $data['field'] = $errorFieldArr;

            if($errorFieldArr)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be left blank.", 'data' => $data);

            $db->where('id', $params['id']);
            $upload = $db->getOne('mlm_document', 'attachment_id, attachment_name');
            
            if(empty($upload))
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

            $updateData = array (
                                    'subject' => $params['subject'],
                                    'description' => $params['description'],
                                    'status' => $params['status'],
                                    'attachment_id' => $upload['attachment_id'],
                                    'attachment_name' => $upload['attachment_name'],
                                    'creator_id' => $params['clientID'],
                                    'creator_type' => $site,
                                    'updated_at' => $db->now()
                                );
            $db->where('id', $params['id']);
            $db->update('mlm_document', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function removeDocument($params) {
            $db = $this->db;

            if(empty($params['id']))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Required fields cannot be empty.", 'data' => "");

            $updateData = array('status' => "Deleted", 'updated_at' => $db->now());
            $db->where('id', $params['id']);
            $db->update('mlm_document', $updateData);

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => "");
        }

        public function getDocumentList($params) {
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
            $result = $db->get('mlm_document', $limit, 'id, subject, description, status, creator_id, creator_type, created_at, updated_at');

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
                        $document['creator_username'] = $usernameList[$v][$array['creator_id']];
                    else
                        $document[$k] = $v;
                }
                $documentList[] = $document;
            }

            $totalRecords = $copyDb->getValue('mlm_document', 'count(id)');
            $data['documentList'] = $documentList;
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function documentDownloadList($params) {
            $db = $this->db;
            $general = $this->general;

            $pageNumber = $params['pageNumber'] ? $params['pageNumber'] : 1;
            $limit = $general->getLimit($pageNumber);

            $searchData = $params['searchData'];

            $db->where('status', 'Active');
            $db->orderBy('id', 'Desc');
            $copyDb = $db->copy();
            $getBase64 = "(SELECT data FROM uploads WHERE uploads.id=attachment_id) AS base_64";
            $getFileType = "(SELECT type FROM uploads WHERE uploads.id=attachment_id) AS file_type";
            $result = $db->get('mlm_document', $limit, 'id, subject, description, attachment_name, '.$getBase64.','.$getFileType);

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found.", 'data' => "");

            foreach($result as $value) {
                $document['subject'] = $value['subject'];
                $document['description'] = $value['description'];
                $document['attachment_name'] = $value['attachment_name'];
                $document['file'] = '<button type="button" class="btn btn-success btn-cons" id="'.$value["id"].'" onclick="createDownloadFile(this)"><i class="fa fa-download"></i></button>';

                $documentList[] = $document;
            }

            $totalRecords = $copyDb->getValue('mlm_document', 'count(id)');
            $data['documentList'] = $documentList;
            $data['totalPage'] = ceil($totalRecords/$limit[1]);
            $data['pageNumber'] = $pageNumber;
            $data['totalRecord'] = $totalRecords;
            $data['numRecord'] = $limit[1];
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        public function documentDownload($params) {
            $db = $this->db;

            if(empty($params['documentID']))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "Failed to download.", 'data' => "");

            // tableIndex = id, status
            $db->where('id', $params['documentID']);
            $db->where('status', 'Active');
            $getBase64 = "(SELECT data FROM uploads WHERE uploads.id=attachment_id) AS base_64";
            $getFileType = "(SELECT type FROM uploads WHERE uploads.id=attachment_id) AS file_type";
            $result = $db->get('mlm_document', 1, 'attachment_name, '.$getBase64.','.$getFileType);

            if(empty($result))
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "Failed to download.", 'data' => "");

            foreach($result as $value) {
                $download = '<a id="thisDownload" download="'.$value["attachment_name"].'" href="data:'.$value["file_type"].';base64,'.$value["base_64"].'" style="display: none;"><span></span></a>';
            }

            $data['download'] = $download;
                
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }
    }
?>
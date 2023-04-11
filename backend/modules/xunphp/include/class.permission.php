<?php
    /**
     * @author TtwoWeb Sdn Bhd.
     * This file is contains the Permissions code.
     * Date  11/07/2017.
    **/

    class Permission {
        
        function __construct($db, $general) {
            $this->db = $db;
            $this->general = $general;
        }
        
        /**
         * Function for getting the Persmissions List.
         * @param $permissionParams.
         * @author Rakesh.
        **/
        public function getPermissionsList($permissionParams) {
            $db = $this->db;
            $general = $this->general;
            $searchData = $permissionParams['searchData'];

            $pageNumber = $permissionParams['pageNumber'] ? $permissionParams['pageNumber'] : 1;

            //Get the limit.
            $limit        = $general->getLimit($pageNumber);

            // Means the search params is there
            if (count($searchData) > 0) {
                foreach ($searchData as $k => $v) {
                    $dataName  = trim($v['dataName']);
                    $dataValue = trim($v['dataValue']);
                        
                    switch($dataName) {
                        case 'name':
                            $db->where('name', $dataValue);
                            break;
                            
                        case 'type':
                            $db->where('type', $dataValue);
                            break;
                    }
                    unset($dataName);
                    unset($dataValue);
                }
            }

            $copyDb = $db->copy();
            $db->orderBy("id", "asc");
            $result = $db->get("permissions", $limit);
            // $result = $db->rawQuery('SELECT  *, parent_id, (select name from permissions as subp where subp.id = p.parent_id) as parent_name FROM permissions as p');

            //For getting the Parent Name only.
            foreach ($result as $values) {
                $newValues[$values['id']] = $values['name'];
            }

            if (!empty($result)) {
                $totalRecord = $copyDb->getValue ("permissions", "count(id)");
                foreach($result as $value) {

                    $permission['id']              = $value['id'];
                    $permission['name']            = $value['name'];
                    $permission['type']            = $value['type'];
                    $permission['description']     = $value['description'];
                    $permission['parentName']      = ($newValues[$value['id']] == null) ? '' : $newValues[$value['id']];
                    $permission['filePath']        = $value['file_path'];
                    // $permission['priority']        = $value['priority'];
                    $permission['iconClassName']   = $value['icon_class_name'];

                    $permissionList[] = $permission;
                }
                
                $data['permissionList'] = $permissionList;
                $data['totalPage']      = ceil($totalRecord/$limit[1]);
                $data['pageNumber']     = $pageNumber;
                $data['totalRecord']    = $totalRecord;
                $data['numRecord']      = $limit[1];
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "ok", 'code' => 0, 'statusMsg' => "No Results Found", 'data'=>"");
            }
        }

        /**
         * Function for adding the New Persmissions.
         * @param $permissionParams.
         * @author Rakesh.
        **/
        function newPermission($permissionParams) {
            $db = $this->db;

            $name        = trim($permissionParams['name']);
            $type        = trim($permissionParams['type']);
            $description = trim($permissionParams['description']);
            $parent      = trim($permissionParams['parent']);
            $filePath    = trim($permissionParams['filePath']);
            $priority    = trim($permissionParams['priority']);
            $iconClass   = trim($permissionParams['iconClass']);
            $disabled    = trim($permissionParams['disabled']);

            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Name.", 'data'=>"");

            if(strlen($type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Type.", 'data'=>"");

            if(strlen($description) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Description.", 'data'=>"");

            if(strlen($filePath) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter File Path.", 'data'=>"");

            // if(strlen($parent) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select a Parent.", 'data'=>"");

            if(strlen($priority) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select priority.", 'data'=>"");

            if(strlen($iconClass) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Icon class name.", 'data'=>"");

            if(strlen($disabled) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Disabled.", 'data'=>"");

            $fields = array("name", "type", "description", "parent_id", "file_path", "priority", "icon_class_name", "disabled", "created_at");
            $values = array($name, $type, $description, $parent, $filePath, $priority, $iconClass, $disabled, date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);

            $result = $db->insert("permissions", $arrayData);

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Successfully Added", 'data'=>"");
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Permission", 'data'=>"");
            }
        }

        /**
         * Function for adding the Updating the Persmission.
         * @param $permissionParams.
         * @author Rakesh.
        **/
        public function editPermissionData($permissionParams) {
            $db = $this->db;

            $id          = trim($permissionParams['id']);
            $name        = trim($permissionParams['name']);
            $type        = trim($permissionParams['type']);
            $description = trim($permissionParams['description']);
            $parent      = trim($permissionParams['parent']);
            $filePath    = trim($permissionParams['filePath']);
            $priority    = trim($permissionParams['priority']);
            $iconClass   = trim($permissionParams['iconClass']);
            $disabled   = trim($permissionParams['disabled']);

            if(strlen($name) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Name.", 'data'=>"");

            if(strlen($type) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Type.", 'data'=>"");

            if(strlen($description) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Description.", 'data'=>"");

            if(strlen($filePath) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter File Path.", 'data'=>"");

            // if(strlen($parent) == 0)
            //     return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select a Parent.", 'data'=>"");

            if(strlen($priority) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select priority.", 'data'=>"");

            if(strlen($iconClass) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Enter Icon class name.", 'data'=>"");

            $fields = array("name", "type", "description", "parent_id", "file_path", "priority", "icon_class_name", "disabled", "updated_at");
            $values = array($name, $type, $description, $parent, $filePath, $priority, $iconClass, $disabled, date("Y-m-d H:i:s"));
            $arrayData = array_combine($fields, $values);
            $db->where('id', $id);
            $result =$db->update("permissions", $arrayData);

            if ($disabled == 0 || $disabled == 1) {
                $rolePermissionFields  = array("disabled", "updated_at");
                $rolePermissionValues  = array($disabled, date("Y-m-d H:i:s"));
                $rolePermissionData    = array_combine($rolePermissionFields, $rolePermissionValues);
                $db->where('permission_id', $id);
                $result =$db->update("roles_permission", $rolePermissionData);
            }

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Permission Successfully Updated"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Permission", 'data'=>"");
            }
        }

        /**
         * Function for deleting the Persmission.
         * @param $permissionParams.
         * @author Rakesh.
        **/
        function deletePermissions($permissionParams) {
            $db = $this->db;

            $id = trim($permissionParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Permission", 'data'=> '');

            $db->where('id', $id);
            $result = $db->get('permissions', 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('permissions');
                if($result) {
                    return $this->getPermissionsList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Permission", 'data'=>"");
            }
        }

        /**
         * Function for getting the Persmission data in the Edit.
         * @param $permissionParams.
         * @author Rakesh.
        **/
        public function getPermissionData($permissionParams) {
            $db = $this->db;
            $id = trim($permissionParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Permission", 'data'=> '');
            
            $db->where('id', $id);
            $result = $db->getOne("permissions");
            
            if (!empty($result)) {
                $permission['id']            = $result["id"];
                $permission['name']          = $result["name"];
                $permission['type']          = $result["type"];;
                $permission['description']   = $result["description"];
                $permission['parent']        = $result["parent_id"];
                $permission['filePath']      = $result["file_path"];
                $permission['priority']      = $result["priority"];
                $permission['iconClass']     = $result["icon_class_name"];
                $permission['disabled']      = $result["disabled"];
                
                $data['permissionData'] = $permission;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Permission", 'data'=>"");
            }
        }

        /**
         * Function for getting the Persmissions List.
         * @param $permissionParams.
         * @author Rakesh.
        **/
        public function getPermissionTree() {
            $db = $this->db;
            $permissionList = $db->rawQuery('SELECT id, name FROM permissions where parent_id = 0');
            if (!empty($permissionList) && !empty($permissionList)) {
                foreach($permissionList as $value) {
                    $id[]              = $value['id'];
                    $name[]        = $value['name'];
                }
                $permission['id']    = $id;
                $permission['name']  = $name;
                
                $data['permissionTree'] = $permission;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
                
            }else{
                return array('status' => "error", 'code' => '1', 'statusMsg' => "No Result", 'data'=>"");
            }
        }

        /**
         * Function for getting the Role Persmissions List.
         * @param NULL.
         * @author Rakesh.
        **/
        public function getRolePermissionList() {
            $db = $this->db;
            $db->join("roles r", "rp.role_id = r.id", "LEFT");
            $db->join("permissions p", "rp.permission_id = p.id", "LEFT");
            $db->orderBy("rp.id", "DESC");
            $result = $db->get("roles_permission rp", null, "rp.id, r.name AS roleName, p.name AS permissionName");

            if (!empty($result)) {
                foreach($result as $value) {
                    $id[]              = $value['id'];
                    $roleName[]        = $value['roleName'];
                    $permissionName[]  = $value['permissionName'];
                }

                $permission['ID']              = $id;
                $permission['Role_Name']       = $roleName;
                $permission['Permission_Name'] = $permissionName;
                
                $data['rolePermissionList'] = $permission;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            } else {
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No Result", 'data'=>"");
            }
        }

        /**
         * Function for adding the New Role Persmissions.
         * @param $rolePermissionParams.
         * @author Rakesh.
        **/
        function newRolePermission($rolePermissionParams) {
            $db = $this->db;
            $roleName           = $rolePermissionParams['roleName'];
            $messageDataParams  = $rolePermissionParams['permissionsList'];
            $permissionsFull    = array();
            $i = 0;
            foreach ($messageDataParams as $keys => $vals) {
                $permissionsFull[$i]['role_id']       = $roleName;
                $permissionsFull[$i]['permission_id'] = $vals;
                $i++;
            }

            $messageAssignedParam = $db->insertMulti("roles_permission", $permissionsFull);
                
            if (is_numeric($messageAssignedParam)) {
                $result = true;
            }else {
                $result = false;
            }
        }

        /**
         * Function for adding the Updating the Persmission.
         * @param $rolePermissionParams.
         * @author Rakesh.
        **/
        public function editRolePermission($rolePermissionParams) {
            $db = $this->db;

            $roleId             = $rolePermissionParams['roleName'];
            $permissionsList    = $rolePermissionParams['permissionsList'];
            $permissionsFull    = array();

            if(isset($permissionsList) && !empty($permissionsList)) {
                $permissions['role_id']       = $roleId;
                $permissions['disabled']      = '1';
                $db->where('role_id', $roleId);
                $result =$db->update("roles_permission", $permissions);

                $i = 0;
                foreach ($permissionsList as $keys => $vals) {
                    $permissionsFull[$i]['role_id']       = $roleId;
                    $permissionsFull[$i]['permission_id'] = $vals;

                    if (isset($vals) && !empty($vals)) {
                        $permissionsFull[$i]['disabled'] = '0';
                    } else {
                        $permissionsFull[$i]['disabled'] = '1';
                    }

                    $db->where("role_id", $permissionsFull[$i]['role_id']);
                    $db->where("permission_id", $permissionsFull[$i]['permission_id']);
                    $permissions = $db->get("roles_permission");

                    if(empty($permissions)){
                        $insertData = Array ("role_id"       => $permissionsFull[$i]['role_id'],
                                       "permission_id" => $permissionsFull[$i]['permission_id'],
                                       "disabled"      => $permissionsFull[$i]['disabled'],
                                       "created_at"    => $db->now(),
                                       "updated_at"    => $db->now(),
                        );

                        $result = $db->insert("roles_permission", $insertData);
                    } else {
                        $permissionsFull[$i]['updated_at']    = date("Y-m-d H:i:s");

                        $db->where('role_id', $permissionsFull[$i]['role_id']);
                        $db->where('permission_id', $permissionsFull[$i]['permission_id']);
                        $result =$db->update("roles_permission", $permissionsFull[$i]);
                    }

                    $i++;
                }
            } else {
                //If all permissions are disabled.
                $permissions['role_id']       = $roleId;
                $permissions['disabled']      = '1';
                $permissions['updated_at']    = date("Y-m-d H:i:s");
                $db->where('role_id', $roleId);
                $result =$db->update("roles_permission", $permissions);
            }

            if($result) {
                return array('status' => "ok", 'code' => 0, 'statusMsg'=> "Permission Successfully Updated"); 
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Permission", 'data'=>"");
            }
        }

        /**
         * Function for deleting the Persmission.
         * @param $rolePermissionParams.
         * @author Rakesh.
        **/
        function deleteRolePermission($rolePermissionParams) {
            $db = $this->db;

            $id = trim($rolePermissionParams['id']);
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Role Permission.", 'data'=> '');

            $db->where('id', $id);
            $result = $db->get('roles_permission', 1);

            if (!empty($result)) {
                $db->where('id', $id);
                $result = $db->delete('roles_permission');
                if($result) {
                    return $this->getRolePermissionList();
                }
                else
                    return array('status' => "error", 'code' => 1, 'statusMsg' => 'Failed to delete.', 'data' => '');
            }else{
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Invalid Role Permission.", 'data'=>"");
            }
        }

        /**
         * Function for getting the Roles List.
         * @param NULL.
         * @author Rakesh.
        **/
        public function getRoleNames() {
            $db = $this->db;
            $tableName = "roles";
            $column = array(
                "id",
                "name"
            );

            $db->orderBy("name", "ASC");
            $roles = $db->get($tableName, NULL, $column);

            if (!empty($roles) && !empty($roles)) {
                foreach($roles as $value){
                    $id[]   = $value['id'];
                    $name[] = $value['name'];
                }

                $permission['id'] = $id;
                $permission['name'] = $name;
                
                $data['roleNames'] = $permission;
                
                return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
                
            } else {
                return array('status' => "error", 'code' => '1', 'statusMsg' => "No Result", 'data'=>"");
            }
        }

        /**
         * Function for getting the Persmissions List.
         * @param NULL.
         * @author Rakesh.
        **/
        public function getPermissionNames($params) {
            $db = $this->db;

            // get only admin permission if calling from admin site
            if(strtolower($db->userType) == "admin")
                $db->where('site', 'Admin');

            $db->where('disabled', 0);
            $db->where('type', 'Hidden', '!=');
            $db->orderBy('type', 'asc');
            $permissions = $db->get('permissions', null, 'id, name, parent_id, type');

            if(empty($permissions))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No results found.", 'data' => "");
            
            $data['permissionNames'] = $permissions;
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
        }

        /**
         * Function for getting the Persmission data in the Edit.
         * @param $permissionParams.
         * @author Rakesh.
        **/
        public function getRolePermissionData($permissionParams) {
            $db = $this->db;
            $id = trim($permissionParams['id']);
            $permissions = array();
            if(strlen($id) == 0)
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please Select Permission", 'data'=> '');
            
            $db->where('disabled', '0');
            $db->where('role_id', $id);
            $result = $db->get("roles_permission");

            if(!empty($result))
                $permissions = $result;

            //$result = $db->rawQuery("SELECT id, permission_id, role_id FROM roles_permission WHERE disabled = 0 AND role_id ='". $id. "'");

            $permission['roleId']      = $id;
            $permission['permissions'] = $permissions;
            
            $data['rolePermissionData'] = $permission;
            
            return array('status' => "ok", 'code' => 0, 'statusMsg' => '', 'data' => $data);
            
        }

        public function getPermissions($params) {
            $db = $this->db;
            $roleID = $params['roleID'];

            if(empty($roleID))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Please select a role.", 'data' => "");

            $db->where('id', $roleID);
            $roles = $db->get('roles', 1, 'id, name');

            if(empty($roles))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "Role doesn't exist.", 'data' => "");

            $data['roleList'] = $roles;

            // get only admin permission if calling from admin site
            if(strtolower($db->userType) == "admin")
                $db->where('site', 'Admin');
            else
                $db->where('site', $roles[0]['name']);

            $db->where('disabled', 0);
            $db->where('type', 'Hidden', '!=');
            $permissions = $db->get('permissions', null, 'id, name, parent_id, type');

            if(empty($permissions))
                return array('status' => "error", 'code' => 1, 'statusMsg' => "No permissions found.", 'data' => "");
            
            $data['permissionList'] = $permissions;
            
            $db->where('disabled', 0);
            $db->where('role_id', $roleID);
            $rolePermissions = $db->get('roles_permission');
            
            $data['rolePermissionList'] = $rolePermissions ? $rolePermissions : array();

            return array('status' => "ok", 'code' => 0, 'statusMsg' => "", 'data' => $data);
        }
    }

?>

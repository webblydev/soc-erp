<?php

class Admin extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('SMS_model', 'sms', true);
    }

    public function index()
    {
        if ($this->session->userdata('userid')) {
            redirect(base_url().'dashboard');
        }

        $this->load->view('admin/index');
    }

    public function login()
    {

        if ($this->input->post('action') == 'login') {
            $userName = $this->input->post('user_name');
            $password = $this->input->post('password');
            $incript = md5($password);

            // echo json_encode($userName);
            // die();

            $query = $this->db->query("select * from tbl_user where user_name=? and password=? and status='a'", [$userName, $incript]);

            if (empty($userName)) {
                echo 'This Username field is not empty';
            } elseif (empty($password)) {
                echo 'This Password field is not empty';
            } else {
                if ($query->num_rows() > 0) {
                    $row = $query->row();
                    $data = [
                        'userid' => $row->id,
                        'username' => $row->user_name,
                        'name' => $row->name,
                        'phone' => $row->phone,
                        'type' => $row->type,
                        'team_name' => $row->team_name,
                        'employeeId' => $row->employee_id,
                        'permissions' => $row->permissions,
                        'logged_in' => true,
                    ];

                    $this->session->set_userdata($data);
                    echo 'success';
                } else {
                    echo 'User Name or Password not match';
                }
            }
        }
    }

    public function logout()
    {
        unset($_SESSION['userid']);
        unset($_SESSION['username']);
        unset($_SESSION['name']);
        unset($_SESSION['type']);
        unset($_SESSION['phone']);
        unset($_SESSION['team_name']);
        unset($_SESSION['employeeId']);
        unset($_SESSION['permissions']);
        session_destroy();
        redirect(base_url('/'));
    }

    public function user()
    {
        if (! $this->session->userdata('userid')) {
            redirect(base_url());
        }

        $data['title'] = 'User Entry';
        $data['backend_content'] = 'page/user';
        $this->load->view('admin/layout', $data);
    }

    public function getUsers()
    {
        $users = $this->db->query("
			select 
				u.*,
				e.code,
				e.name as employee
			from tbl_user u 
			join tbl_employee e on e.id = u.employee_id
			where u.status = 'a'
		")->result();

        echo json_encode($users);
    }

    public function addUser()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $userObj = json_decode($this->input->raw_input_stream);

            $pass = $userObj->password;
            $password = md5($pass);
            $retype = $userObj->retype;
            $checkPhone = $this->db->query("select * from tbl_user where phone=? and status='a'", $userObj->phone);

            if ($checkPhone->num_rows() > 0) {
                $res = ['success' => false, 'message' => 'This phone number already existed'];
            } else {
                $data = [
                    'name' => $userObj->name,
                    'user_name' => $userObj->user_name,
                    'email' => $userObj->email,
                    'phone' => $userObj->phone,
                    'password' => $password,
                    'type' => $userObj->type,
                    'team_name' => $userObj->team_name,
                    'employee_id' => $userObj->employee_id,
                    'status' => 'a',
                    'add_by' => $this->session->userdata('userid'),
                    'add_time' => date('d-m-Y H:i:s'),
                ];
                $this->db->insert('tbl_user', $data);
                $res = ['success' => true, 'message' => 'User added successfully'];
            }
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function updateUser()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $userObj = json_decode($this->input->raw_input_stream);

            // if(!preg_match('/^01[3-9]\d{8}$/', $userObj->phone)) {
            // 	$res = ['success'=>true, 'message'=> 'Phone number is not valid'];
            // }

            // else if(strlen($userObj->email) > 0){
            // 	if (!preg_match('/^[a-zA-Z0-9._-]+@[a-zA-Z0-9-]+\.[a-zA-Z.]{2,5}$/',$userObj->email)) {
            // 		$res = ['success'=>true, 'message'=> 'This email is not valid !'];
            // 	}
            // }
            // else {

            $pass = $userObj->password;
            $password = md5($pass);

            $data = [
                'name' => $userObj->name,
                'user_name' => $userObj->user_name,
                'email' => $userObj->email,
                'phone' => $userObj->phone,
                'password' => $password,
                'type' => $userObj->type,
                'team_name' => $userObj->team_name,
                'employee_id' => $userObj->employee_id,
                'update_by' => $this->session->userdata('userid'),
                'update_time' => date('d-m-Y H:i:s'),
            ];
            $this->db->where('id', $userObj->id)->update('tbl_user', $data);
            $res = ['success' => true, 'message' => 'User updated successfully'];
            // }

        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function deleteUser()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set(['status' => 'd'])->where('id', $data->userId)->update('tbl_user');

            $res = ['success' => true, 'message' => 'Area deleted successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    // Material Methods

    public function material()
    {
        if (! $this->session->userdata('userid')) {
            redirect(base_url());
        }

        $data['title'] = 'Material Entry';
        $data['backend_content'] = 'page/material';
        $this->load->view('admin/layout', $data);
    }

    public function getMaterials()
    {
        $teams = $this->db->query("
			select 
				*
			from tbl_material 
			where status = 'a'
		")->result();

        echo json_encode($teams);
    }

    public function addMaterial()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);
            $duplicateName = $this->db->query("select name from tbl_material where name = ? and status = 'a'", $data->name);
            if ($duplicateName->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Material name already exists';
                echo json_encode($res);
                exit;
            }

            $material = [
                'name' => $data->name,
                'status' => 'a',
                'added_by' => $this->session->userdata('username'),
                'added_date' => date('Y-m-d H:i:s'),
            ];

            $this->db->insert('tbl_material', $material);

            $res->success = true;
            $res->message = 'Material name insert successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'fail'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function updateMaterial()
    {
        $res = new stdClass;

        try {
            $data = json_decode($this->input->raw_input_stream);

            $duplicateName = $this->db->query("select name from tbl_material where name = ? and status = 'a' and id != ?", [$data->name, $data->id]);
            if ($duplicateName->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Material name already exists';
                echo json_encode($res);
                exit;
            }

            $material = [
                'name' => $data->name,
                'update_by' => $this->session->userdata('username'),
                'update_date' => date('Y-m-d H:i:s'),
            ];

            $this->db->where('id', $data->id)->update('tbl_material', $material);

            $res->success = true;
            $res->message = 'Material name update successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'fail'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function deleteMaterial()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);
            $this->db->set('status', 'd')->where('id', $data->id)->update('tbl_material');
            $res->success = true;
            $res->message = 'Material deleted successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'fail'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function team()
    {
        if (! $this->session->userdata('userid')) {
            redirect(base_url());
        }

        $data['title'] = 'Team Entry';
        $data['backend_content'] = 'page/team';
        $this->load->view('admin/layout', $data);
    }

    public function getTeams()
    {
        $teams = $this->db->query("
			select 
				*
			from tbl_team 
			where status = 'a'
		")->result();

        echo json_encode($teams);
    }

    public function addTeam()
    {
        $res = new stdClass;

        try {
            $data = json_decode($this->input->raw_input_stream);

            $duplicateName = $this->db->query("select name from tbl_team where name = ? and status = 'a'", $data->name);
            if ($duplicateName->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Team name already exists';
                echo json_encode($res);
                exit;
            }

            $team = [
                'name' => $data->name,
                'status' => 'a',
                'added_by' => $this->session->userdata('username'),
                'added_date' => date('Y-m-d H:i:s'),
            ];

            $this->db->insert('tbl_team', $team);

            $res->success = true;
            $res->message = 'Team name insert successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'fail'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function updateTeam()
    {
        $res = new stdClass;

        try {
            $data = json_decode($this->input->raw_input_stream);

            $duplicateName = $this->db->query("select name from tbl_team where name = ? and status = 'a' and id != ?", [$data->name, $data->id]);
            if ($duplicateName->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Team name already exists';
                echo json_encode($res);
                exit;
            }

            $team = [
                'name' => $data->name,
                'update_by' => $this->session->userdata('username'),
                'update_date' => date('Y-m-d H:i:s'),
            ];

            $this->db->where('id', $data->id)->update('tbl_team', $team);

            $res->success = true;
            $res->message = 'Team name update successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'fail'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function deleteTeam()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set('status', 'd')->where('id', $data->id)->update('tbl_team');

            $res->success = true;
            $res->message = 'Team deleted successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'fail'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function teamList()
    {
        if (! $this->session->userdata('userid')) {
            redirect(base_url());
        }

        $data['title'] = 'Team Member List';
        $data['backend_content'] = 'page/team-member';
        $this->load->view('admin/layout', $data);
    }

    public function getTeamMember()
    {
        $members = $this->db->query(
            "
        select 
            u.*,
            concat_ws('-', u.name, u.phone) as display_name
        from tbl_user u
        where status = 'a' 
        and team_name = ?",
            [$this->session->userdata('team_name')]
        )->result();

        echo json_encode($members);
    }

    // app client entry

    public function appClientEntry()
    {
        $res = new stdClass;
        try {
            $phone = $_POST['phone'];
            $org_name = $_POST['org_name'];

            $checkPhone = $this->db->query('select * from  tbl_client where phone = ?', $phone);
            $checkOrg = $this->db->query('select * from  tbl_client where org_name = ?', $org_name);

            if ($checkOrg->num_rows() > 0) {
                $row = $checkOrg->row();
                $id = $row->add_by;
                $officer = $this->db->query('select name from tbl_user where id = ?', $id)->row();
                $res->success = false;
                $res->message = 'This organization name already added by '.$officer->name.' ';
                echo json_encode($res);
                exit;
            }

            if ($checkPhone->num_rows() > 0) {
                $row = $checkPhone->row();
                $id = $row->add_by;
                $officer = $this->db->query('select name from tbl_user where id = ?', $id)->row();
                $res->success = false;
                $res->message = 'This phone number already by '.$officer->name.' ';
                echo json_encode($res);
                exit;
            }

            $requirement = $_POST['requirement'];
            $sold = $_POST['sold'];
            $data = [
                'client_name' => $_POST['client_name'],
                'org_name' => $org_name,
                'phone' => $phone,
                'org_mobile' => $_POST['org_mobile'],
                'address' => $_POST['address'],
                'area_id' => $_POST['area_id'],
                'requirement' => $requirement,
                'sold' => $sold,
                'level' => $_POST['level'],
                'source' => $_POST['source'],
                'reminder' => $_POST['reminder'],
                'comment' => $_POST['comment'],
                'status' => $_POST['sold'] == '' ? 'p' : 's',
                'add_by' => $_POST['add_by'],
                'add_time' => date('Y-m-d H:i:s'),
            ];

            $result = $this->db->insert('tbl_client', $data);

            // Send sms

            if (isset($_POST['client_name']) && $_POST['client_name'] != '') {
                $sendToName = $_POST['client_name'];
                $officerName = $_POST['user_name'];
                $officerPhone = $_POST['user_phone'];

                $message = "Dear {$sendToName},\nWelcome to Link-Up Technology. Our Services:\n\n 1. Website Design. \n 2. Software Development. Thank you,\n{$officerName}\nPhone: {$officerPhone}";
                $recipient = $phone;
                $this->sms->sendAppSms($recipient, $message);
            }

            $res->success = true;
            $res->message = 'Client added successfully';
        } catch (Exception $ex) {
            $res->success = false;
            $res->message = $ex->getMessage();
        }

        echo json_encode($res);
    }

    public function appClientUpdate()
    {
        $res = new stdClass;
        try {
            $clientId = $_POST['id'];
            $phone = $_POST['phone'];
            $org_name = $_POST['org_name'];

            $checkPhone = $this->db->query('select * from  tbl_client where phone = ? and id != ?', [$phone, $clientId]);
            $checkOrg = $this->db->query('select * from  tbl_client where org_name = ? and id != ?', [$org_name, $clientId]);

            if ($checkOrg->num_rows() > 0) {
                $row = $checkOrg->row();
                $id = $row->add_by;
                $officer = $this->db->query('select name from tbl_user where id = ?', $id)->row();
                $res->success = false;
                $res->message = 'This organization name already added by '.$officer->name.' ';
                echo json_encode($res);
                exit;
            }

            if ($checkPhone->num_rows() > 0) {
                $row = $checkPhone->row();
                $id = $row->add_by;
                $officer = $this->db->query('select name from tbl_user where id = ?', $id)->row();
                $res->success = false;
                $res->message = 'This phone number already by '.$officer->name.' ';
                echo json_encode($res);
                exit;
            }

            $requirement = $_POST['requirement'];
            $sold = $_POST['sold'];
            $data = [
                'client_name' => $_POST['client_name'],
                'org_name' => $org_name,
                'phone' => $phone,
                'org_mobile' => $_POST['org_mobile'],
                'address' => $_POST['address'],
                'area_id' => $_POST['area_id'],
                'requirement' => $requirement,
                'sold' => $sold,
                'level' => $_POST['level'],
                'source' => $_POST['source'],
                'reminder' => $_POST['reminder'],
                'comment' => $_POST['comment'],
                'status' => $_POST['sold'] == '' ? 'p' : 's',
                'update_by' => $_POST['update_by'],
                'update_time' => date('Y-m-d H:i:s'),
            ];

            //   echo json_encode($data);exit;

            $this->db->where('id', $clientId)->update('tbl_client', $data);

            $res->success = true;
            $res->message = 'Client updated successfully';
        } catch (Exception $ex) {
            $res->success = false;
            $res->message = $ex->getMessage();
        }

        echo json_encode($res);
    }

    public function getAppClients()
    {
        $res = new stdClass;
        $clientId = $_POST['clientId'];
        $type = $_POST['type'];
        $userId = $_POST['userId'];

        $clause = '';
        if (isset($type) && $type != '') {
            $clause .= " and c.status = '$type'";
        }
        if (isset($userId) && $userId != '') {
            $clause .= " and c.add_by = $userId";
        }

        $clients = $this->db->query("
			select c.* ,
				a.id as aid,
				a.name,
				u.name as officer
			from tbl_client as c 
			join tbl_area as a on c.area_id = a.id 
			left join tbl_user as u on c.add_by = u.id  
			where c.status != 'd' 
			$clause
			order by c.id desc
			")->result();

        $res->clients = array_map(function ($client) {
            if ($client->requirement != '') {
                $client->requirements = $this->db->query("SELECT * FROM tbl_software as s WHERE s.id IN ($client->requirement)")->result();
            }

            return $client;
        }, $clients);

        echo json_encode($res);
    }

    public function updateUserPermission()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $permission = json_encode($data->permissions);

            // echo json_encode($permission);
            // exit;

            $this->db->where('id', $data->userId)->set('permissions', $permission)->update('tbl_user');
            $res->message = 'Save successfully';
            $res->success = true;
        } catch (Exception $e) {
            $res->message = 'failed'.$e->getMessage();
            $res->success = false;
        }

        echo json_encode($res);
    }
}

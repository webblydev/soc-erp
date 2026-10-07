<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Employee extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (! $this->session->userdata('userid')) {
            redirect(base_url());
        }
    }

    // post

    public function post()
    {
        $data['title'] = 'Post Entry';
        $data['backend_content'] = 'page/post';
        $this->load->view('admin/layout', $data);
    }

    public function getPosts()
    {
        $posts = $this->db->query("select * from tbl_post where status = 'a'")->result();
        echo json_encode($posts);
    }

    public function addPost()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $duplicateName = $this->db->query("select name from tbl_post where name = ? and status = 'a'", $data->name);
            if ($duplicateName->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Post name already exists';
                echo json_encode($res);
                exit;
            }

            $post = [
                'name' => $data->name,
                'status' => 'a',
                'added_by' => $this->session->userdata('username'),
                'added_date' => date('Y-m-d H:i:s'),
            ];

            $this->db->insert('tbl_post', $post);
            $res->success = true;
            $res->message = 'Save successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'failed..!'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function updatePost()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $duplicateName = $this->db->query("select name from tbl_post where name = ? and status = 'a' and id != ?", [$data->name, $data->id]);
            if ($duplicateName->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Post name already exists';
                echo json_encode($res);
                exit;
            }

            $post = [
                'name' => $data->name,
                'update_by' => $this->session->userdata('username'),
                'update_date' => date('Y-m-d H:i:s'),
            ];

            $this->db->where('id', $data->id)->update('tbl_post', $post);

            $res->success = true;
            $res->message = 'Update successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'failed..!'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function deletePost()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $useEmployee = $this->db->query("select * from tbl_employee where status = 'a' and post_id = ?", $data->id);
            if ($useEmployee->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Please employee delete first';
                echo json_encode($res);
                exit;
            }

            $this->db->set('status', 'd')->where('id', $data->id)->update('tbl_post');

            $res->success = true;
            $res->message = 'Deleted successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'failed..!'.$e->getMessage();
        }

        echo json_encode($res);
    }

    // Department
    public function department()
    {
        $data['title'] = 'Department Entry';
        $data['backend_content'] = 'page/department';
        $this->load->view('admin/layout', $data);
    }

    public function getDepartments()
    {
        $departments = $this->db->query("select * from tbl_department where status = 'a'")->result();
        echo json_encode($departments);
    }

    public function addDepartment()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $duplicateName = $this->db->query("select name from tbl_department where name = ? and status = 'a'", $data->name);
            if ($duplicateName->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Department name already exists';
                echo json_encode($res);
                exit;
            }

            $department = [
                'name' => $data->name,
                'status' => 'a',
            ];

            $this->db->insert('tbl_department', $department);
            $res->success = true;
            $res->message = 'Save successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'failed..!'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function updateDepartment()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $duplicateName = $this->db->query("select name from tbl_department where name = ? and status = 'a' and id != ?", [$data->name, $data->id]);
            if ($duplicateName->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Department name already exists';
                echo json_encode($res);
                exit;
            }

            $department = [
                'name' => $data->name,
            ];

            $this->db->where('id', $data->id)->update('tbl_department', $department);
            $res->success = true;
            $res->message = 'Update successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'failed..!'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function deleteDepartment()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $useEmployee = $this->db->query("select * from tbl_employee where status = 'a' and department_id = ?", $data->id);
            if ($useEmployee->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Please employee delete first';
                echo json_encode($res);
                exit;
            }

            $this->db->set('status', 'd')->where('id', $data->id)->update('tbl_department');

            $res->success = true;
            $res->message = 'Deleted successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'failed..!'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function employee()
    {
        $data['title'] = 'Employee Entry';
        $data['backend_content'] = 'page/employee';
        $this->load->view('admin/layout', $data);
    }

    public function generateEmployeeCode()
    {
        $employeeCode = 'E00001';

        $lastEmployee = $this->db->query('select * from tbl_employee order by id desc limit 1');
        if ($lastEmployee->num_rows() != 0) {
            $newId = $lastEmployee->row()->id + 1;
            $zeros = ['0', '00', '000', '0000'];
            $employeeCode = 'E'.(strlen($newId) > count($zeros) ? $newId : $zeros[count($zeros) - strlen($newId)].$newId);
        }

        echo json_encode($employeeCode);
    }

    public function getEmployee()
    {

        $data = json_decode($this->input->raw_input_stream);

        $clause = '';
        if (isset($data) && $data->emp_id) {
            $clause = " and e.id = '$data->emp_id' ";
        }

        $employees = $this->db->query("
            select 
                e.*,
                d.name as department,
                p.name as post
            from tbl_employee e 
            join tbl_post p on p.id = e.post_id
            join tbl_department d on d.id = e.department_id
            where e.status = 'a'
			$clause 
        ")->result();

        echo json_encode($employees);
    }

    public function getAllEmployees()
    {
        $employees = $this->db->query('
        select 
            e.*,
            d.name as department,
            p.name as post
        from tbl_employee e 
        join tbl_post p on p.id = e.post_id
        join tbl_department d on d.id = e.department_id
    ')->result();

        echo json_encode($employees);
    }

    public function addEmployee()
    {
        $res = new stdClass;

        try {
            $employeeObj = json_decode($this->input->raw_input_stream);
            $code = $employeeObj->code;

            $duplicateCount = $this->db->query('select code from tbl_employee where code = ?', $employeeObj->code)->num_rows();
            if ($duplicateCount != 0) {
                $code = $this->generateEmployeeCode();
            }

            $employee = [
                'code' => $code,
                'name' => $employeeObj->name,
                'post_id' => $employeeObj->post_id,
                'department_id' => $employeeObj->department_id,
                'father_name' => $employeeObj->father_name,
                'mother_name' => $employeeObj->mother_name,
                'dob' => $employeeObj->dob,
                'gender' => $employeeObj->gender,
                'marital_status' => $employeeObj->marital_status,
                'present_address' => $employeeObj->present_address,
                'permanent_address' => $employeeObj->permanent_address,
                'phone' => $employeeObj->phone,
                'email' => $employeeObj->email,
                'reference' => $employeeObj->reference,
                'status' => 'a',
                'added_by' => $this->session->userdata('username'),
                'added_date' => date('Y-m-d H:i:s'),
            ];

            $this->db->insert('tbl_employee', $employee);

            $res->success = true;
            $res->message = 'Employee insert successfully';
        } catch (Exception $ex) {
            $res->success = false;
            $res->message = $ex->getMessage();
        }

        echo json_encode($res);
    }

    public function updateEmployee()
    {
        $res = new stdClass;

        try {
            $employeeObj = json_decode($this->input->raw_input_stream);

            $employee = [
                'code' => $employeeObj->code,
                'name' => $employeeObj->name,
                'post_id' => $employeeObj->post_id,
                'department_id' => $employeeObj->department_id,
                'father_name' => $employeeObj->father_name,
                'mother_name' => $employeeObj->mother_name,
                'dob' => $employeeObj->dob,
                'gender' => $employeeObj->gender,
                'marital_status' => $employeeObj->marital_status,
                'present_address' => $employeeObj->present_address,
                'permanent_address' => $employeeObj->permanent_address,
                'phone' => $employeeObj->phone,
                'email' => $employeeObj->email,
                'reference' => $employeeObj->reference,
                'updated_by' => $this->session->userdata('username'),
                'update_date' => date('Y-m-d H:i:s'),
            ];

            $this->db->where('id', $employeeObj->id)->update('tbl_employee', $employee);

            $res->success = true;
            $res->message = 'Employee update successfully';
        } catch (Exception $ex) {
            $res->success = false;
            $res->message = $ex->getMessage();
        }

        echo json_encode($res);
    }

    public function deleteEmployee()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            // $checkTask = $this->db->query("select * from tasks where assign_to = ? and status != 'c'", $data->employeeId);
            // if($checkTask->num_rows() > 0) {
            //     $res->success = false;
            //     $res->message = 'Please employee assign task comleted first !';
            //     echo json_encode($res);
            //     exit;
            // }

            $this->db->where('id', $data->id)->set('status', 'd')->update('tbl_employee');

            $res->success = true;
            $res->message = 'Deleted successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'failed..'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function activeEmployee()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            // $checkTask = $this->db->query("select * from tasks where assign_to = ? and status != 'c'", $data->employeeId);
            // if($checkTask->num_rows() > 0) {
            //     $res->success = false;
            //     $res->message = 'Please employee assign task comleted first !';
            //     echo json_encode($res);
            //     exit;
            // }

            $this->db->where('id', $data->id)->set('status', 'a')->update('tbl_employee');

            $res->success = true;
            $res->message = 'Active successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'failed..'.$e->getMessage();
        }

        echo json_encode($res);
    }
}

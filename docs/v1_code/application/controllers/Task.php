<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Task extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (! $this->session->userdata('userid')) {
            redirect(base_url());
        }
        $this->employeeId = $this->session->userdata('employeeId');
    }

    public function index()
    {
        $data['title'] = 'Task Entry';
        $data['newFileNumber'] = $this->newFileNumber();
        $data['backend_content'] = 'page/task';
        $this->load->view('admin/layout', $data);
    }

    public function projectEntry()
    {
        $data['title'] = 'Project Entry';
        $data['backend_content'] = 'page/project_entry';
        $this->load->view('admin/layout', $data);
    }

    public function addProject()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $projectObj = json_decode($this->input->raw_input_stream);
            $project = $this->db->query('select * from tbl_project where project_id=?', $projectObj->project_id);

            if ($project->num_rows() > 0) {
                $row = $project->row();
                $id = $row->add_by;
                $officer = $this->db->query('select name from tbl_user where id=?', $id)->row();
                $res = ['success' => true, 'message' => 'This area already added by '.$officer->name.' '];
            } else {
                $data = [
                    'name' => $projectObj->name,
                    'project_id' => $projectObj->project_id,
                    'project_type_id' => $projectObj->project_type_id,
                    'status' => 'a',
                    'add_by' => $this->session->userdata('userid'),
                    'add_time' => date('Y-m-d H:i:s'),
                ];
                $this->db->insert('tbl_project', $data);

                $res = ['success' => true, 'message' => 'Project added successfully'];
            }
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function updateProject()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $projectObj = json_decode($this->input->raw_input_stream);

            $data = [
                'project_id' => $projectObj->project_id,
                'name' => $projectObj->name,
                'project_type_id' => $projectObj->project_type_id,
                'update_by' => $this->session->userdata('userid'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            $this->db->where('id', $projectObj->id)->update('tbl_project', $data);

            $res = ['success' => true, 'message' => 'Project updated successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function deleteProject()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);
            $this->db->set(['status' => 'd'])->where('id', $data->areaId)->update('tbl_project');
            $res = ['success' => true, 'message' => 'Project deleted successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }
        echo json_encode($res);
    }

    public function getProjects()
    {
        $data = json_decode($this->input->raw_input_stream);
        $clause = '';

        if (isset($data->project_id) && $data->project_id != '') {
            $clause = "and p.project_id = '$data->project_id'";
        }

        if (isset($data->projectTypeId) && $data->projectTypeId != '') {
            $clause = "and p.project_type_id = '$data->projectTypeId'";
        }

        $projects = $this->db->query("
		 select p.*,
		 	t.name as project_type_name,
            concat(p.project_id, ' - ', p.name) as display_name,
			tsk.assign_to
		  from tbl_project p
		  left join tbl_type t on t.id = p.project_type_id
		  left join tbl_task tsk on tsk.projectId = p.id
		  where p.status = 'a' $clause
          
          ")->result();

        echo json_encode($projects);
    }

    public function getProjectByProjectId()
    {
        $data = json_decode($this->input->raw_input_stream);
        $clause = '';

        if (isset($data->project_id) && $data->project_id != '') {
            $clause = "and p.id = '$data->project_id'";
        }
        $projects = $this->db->query("select * from tbl_project p where p.status = 'a' $clause ")->result();
        echo json_encode($projects);

    }

    public function getProjectTypeByProjectId()
    {
        $data = json_decode($this->input->raw_input_stream);
        $clause = '';

        if (isset($data->project_type_id) && $data->project_type_id != '') {
            $clause = "and t.id = '$data->project_type_id'";
        }
        $projects = $this->db->query("select * 
		 	from tbl_type t  
			where t.status = 'a'
		 $clause ")->result();

        echo json_encode($projects);
    }

    public function newFileNumber()
    {
        $code = date('Y').'0001';
        $year = date('Y');
        $query = $this->db->query("select * from tbl_task where file_number like '$year%'");
        if ($query->num_rows() != 0) {
            $newId = $query->num_rows() + 1;
            $zeros = ['0', '00', '000'];
            $code = date('Y').(strlen($newId) > count($zeros) ? $newId : $zeros[count($zeros) - strlen($newId)].$newId);
        }

        return $code;
    }

    public function getTasks()
    {

        $data = json_decode($this->input->raw_input_stream);

        $clauses = '';

        if (isset($data->forAssignedPerson) && $data->forAssignedPerson == true) {
            $clauses .= " and t.assign_to = $this->employeeId";
        }

        if (isset($data->employeeId) && $data->employeeId != '') {
            $clauses .= " and t.assign_to = '$data->employeeId'";
        }

        if (isset($data->taskId) && $data->taskId != '') {
            $clauses .= " and t.id = '$data->taskId'";
        }

        if (isset($data->projectTypeId) && $data->projectTypeId != '') {
            $clauses .= " and t.type_id = '$data->projectTypeId'";
        }

        if ((isset($data->dateFrom) && $data->dateFrom != '') && (isset($data->dateTo) && $data->dateTo != '')) {
            $clauses .= " and t.entry_date between '$data->dateFrom' and '$data->dateTo'";
        }

        if (isset($data->status)) {
            if (gettype($data->status) == 'string' && $data->status != '') {
                $clauses .= " and t.status = '$data->status'";
            } elseif (gettype($data->status) == 'array' && ! empty($data->status)) {
                $statusClause = "('".implode("','", $data->status)."')";
                $clauses .= " and t.status in {$statusClause}";
            }
        }

        $tasks = $this->db->query("
            select 
                t.*,
                pt.name as type,
                e.code,
                e.name as assigned_person,
                e2.code as code2,
                e2.name as support_person,
                e3.code as code3,
                e3.name as assign_by_person,
				p.name as project_name,
				p.project_id as projectId,
                case t.status
                    when 'p' then 'Pending'
                    when 'o' then 'On Progress'
                    when 'c' then 'Completed'
                    when 'a' then 'Archived'
                end as status_text,
				c.client_name
            from tbl_task t
            left join tbl_type pt on pt.id = t.type_id
            left join tbl_employee e on e.id = t.assign_to
            left join tbl_employee e2 on e2.id = t.support_id
            left join tbl_employee e3 on e3.id = t.assign_by
			left join tbl_project p on p.project_id = t.project_id
			left join tbl_client c on c.id = t.client_list_id
            where t.status != 'd'
            $clauses
            order by t.id desc
        ")->result();

        echo json_encode($tasks);
    }

    public function addTask()
    {

        $res = new stdClass;

        try {

            $taskObj = json_decode($this->input->raw_input_stream);
            $file = $taskObj->file_number;

            $checkDuplicate = $this->db->query('select file_number from tbl_task where file_number = ?', $taskObj->file_number)->num_rows();
            if ($checkDuplicate > 0) {
                $file = $this->newFileNumber();
            }

            $task = [
                'file_number' => $file,
                'client_id' => $taskObj->client_id,
                'client_list_id' => $taskObj->client_list_id,
                'entry_date' => $taskObj->entry_date,
                'project_id' => $taskObj->project_id,
                'projectId' => $taskObj->projectId,
                'client_name' => $taskObj->client_name,
                'client_phone' => $taskObj->client_phone,
                'task_detail' => $taskObj->task_detail,
                'type_id' => $taskObj->type_id,
                'bill_number' => $taskObj->bill_number,
                'bill_amount' => $taskObj->bill_amount,
                'collect_amount' => $taskObj->collect_amount,
                'due_amount' => $taskObj->due_amount,
                'assign_to' => $taskObj->assign_to,
                'support_id' => $taskObj->support_id,
                'deadline' => $taskObj->deadline,
                'is_important' => $taskObj->is_important,
                'status' => $taskObj->status,
            ];

            $this->db->insert('tbl_task', $task);
            $taskId = $this->db->insert_id();

            $res->success = true;
            $res->message = 'New task entry successfully';
            $res->taskId = $taskId;
            $res->newFileNumber = $this->newFileNumber();
        } catch (Exception $ex) {
            $res->success = false;
            $res->message = 'fail'.$ex->getMessage();
        }

        echo json_encode($res);
    }

    public function taskInvoicePrint($taskId)
    {
        $data['title'] = 'Sales Invoice';
        $data['taskId'] = $taskId;
        $data['backend_content'] = 'page/task&Report';
        $this->load->view('admin/layout', $data);
    }

    public function projectVisitInvoicePrint($taskId)
    {
        $data['title'] = 'Project Visit Invoice';
        $data['visitId'] = $taskId;
        $data['backend_content'] = 'page/visit&Report';
        $this->load->view('admin/layout', $data);
    }

    public function vendorBillInvoicePrint($taskId)
    {
        $data['title'] = 'Vendor Bill Invoice';
        $data['visitId'] = $taskId;
        $data['backend_content'] = 'page/visit&Report';
        $this->load->view('admin/layout', $data);
    }

    public function projectBillInvoicePrint($taskId)
    {
        $data['title'] = 'Project Bill Invoice';
        $data['visitId'] = $taskId;
        $data['backend_content'] = 'page/visit&Report';
        $this->load->view('admin/layout', $data);
    }

    public function updateTask()
    {
        $res = new stdClass;
        try {

            $taskObj = json_decode($this->input->raw_input_stream);
            $task = [
                'client_id' => $taskObj->client_id,
                'entry_date' => $taskObj->entry_date,
                'project_id' => $taskObj->project_id,
                'projectId' => $taskObj->projectId,
                'client_name' => $taskObj->client_name,
                'client_phone' => $taskObj->client_phone,
                'task_detail' => $taskObj->task_detail,
                'type_id' => $taskObj->type_id,
                'bill_amount' => $taskObj->bill_amount,
                'bill_number' => $taskObj->bill_number,
                'collect_amount' => $taskObj->collect_amount,
                'due_amount' => $taskObj->due_amount,
                'assign_to' => $taskObj->assign_to,
                'support_id' => $taskObj->support_id,
                'deadline' => $taskObj->deadline,
                'is_important' => $taskObj->is_important,
            ];
            $this->db->where('id', $taskObj->id)->update('tbl_task', $task);
            $res->success = true;
            $res->message = 'Task updated successfully';
            $res->taskId = $taskObj->id;
            $res->newFileNumber = $this->newFileNumber();
        } catch (Exception $ex) {
            $res->success = false;
            $res->message = 'fail'.$ex->getMessage();
        }

        echo json_encode($res);
    }

    public function deleteTask()
    {
        $res = new stdClass;

        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set('status', 'd')->where('id', $data->id)->update('tbl_task');

            $res->success = true;
            $res->message = 'Task deleted successfully';
        } catch (Exception $ex) {
            $res->success = false;
            $res->message = 'fail'.$ex->getMessage();
        }

        echo json_encode($res);
    }

    public function changeTaskStatus()
    {
        $res = new stdClass;

        try {

            $data = json_decode($this->input->raw_input_stream);
            $task = [
                'completed_by_comment' => $data->completed_by_comment,
                'completed_date' => $data->completed_date,
                'status' => $data->status,
            ];

            $this->db->where('id', $data->id)->update('tbl_task', $task);
            $res->success = true;
            $res->taskId = $data->id;
            $res->message = 'Task status change successfully';
        } catch (Exception $ex) {
            $res->success = false;
            $res->message = $ex->getMessage();
        }

        echo json_encode($res);
    }

    public function updateTaskStatus()
    {
        $res = new stdClass;

        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->where('id', $data->id)->set('status', $data->status)->update('tbl_task');

            $res->success = true;
            $res->message = 'Status change successfully';
        } catch (Exception $ex) {
            $res->success = false;
            $res->message = $ex->getMessage();
        }

        echo json_encode($res);
    }

    public function taskRecord()
    {
        $data['title'] = 'Task Record';
        $data['backend_content'] = 'page/task_record';
        $this->load->view('admin/layout', $data);
    }

    public function myTaskRecord()
    {
        $data['title'] = 'My Task';
        $data['backend_content'] = 'page/my-task';
        $this->load->view('admin/layout', $data);
    }

    public function completeTaskRecord()
    {
        $data['title'] = 'Completed Task';
        $data['backend_content'] = 'page/complete-task';
        $this->load->view('admin/layout', $data);
    }

    public function archiveTaskRecord()
    {
        $data['title'] = 'Archive Task';
        $data['backend_content'] = 'page/archive-task';
        $this->load->view('admin/layout', $data);
    }

    // type

    public function type()
    {
        $data['title'] = 'Type Entry';
        $data['backend_content'] = 'page/type';
        $this->load->view('admin/layout', $data);
    }

    public function getTypes()
    {
        $types = $this->db->query("select * from tbl_type where status = 'a'")->result();
        echo json_encode($types);
    }

    public function addType()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $duplicateName = $this->db->query("select name from tbl_type where name = ? and status = 'a'", $data->name);
            if ($duplicateName->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Type name already exists';
                echo json_encode($res);
                exit;
            }

            $project = [
                'name' => $data->name,
                'status' => 'a',
            ];

            $this->db->insert('tbl_type', $project);
            $res->success = true;
            $res->message = 'Save successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'failed..!'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function updateType()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $duplicateName = $this->db->query("select name from tbl_type where name = ? and status = 'a' and id != ?", [$data->name, $data->id]);
            if ($duplicateName->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Type name already exists';
                echo json_encode($res);
                exit;
            }

            $type = [
                'name' => $data->name,
            ];

            $this->db->where('id', $data->id)->update('tbl_type', $type);

            $res->success = true;
            $res->message = 'Update successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'failed..!'.$e->getMessage();
        }

        echo json_encode($res);
    }

    public function deleteType()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $useTask = $this->db->query("select * from tbl_task where status != 'a' and type_id = ?", $data->id);
            if ($useTask->num_rows() > 0) {
                $res->success = false;
                $res->message = 'Task delete first';
                echo json_encode($res);
                exit;
            }

            $this->db->set('status', 'd')->where('id', $data->id)->update('tbl_type');

            $res->success = true;
            $res->message = 'Deleted successfully';
        } catch (Exception $e) {
            $res->success = false;
            $res->message = 'failed..!'.$e->getMessage();
        }

        echo json_encode($res);
    }
}

<?php

class Work extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (! $this->session->userdata('userid')) {
            redirect(base_url());
        }
    }

    public function index()
    {
        $data['backend_content'] = 'page/work_item';
        $data['title'] = 'Work Item Entry';
        $this->load->view('admin/layout', $data);
    }

    public function getWorkItem()
    {
        $areas = $this->db->query("select * from tbl_workitem  where status = 'a'")->result();
        echo json_encode($areas);
    }

    public function addWorkItem()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $workObj = json_decode($this->input->raw_input_stream);

            $duplicateWorkItem = $this->db->query('select * from tbl_workitem where name=?', $workObj->name);

            if ($duplicateWorkItem->num_rows() > 0) {
                $row = $duplicateWorkItem->row();
                $id = $row->add_by;
                $officer = $this->db->query('select name from tbl_workitem where id=?', $id)->row();
                $res = ['success' => true, 'message' => 'This Work Item already added by '.$officer->name.' '];
            } else {
                $data = [
                    'name' => $workObj->name,
                    'status' => 'a',
                    'add_by' => $this->session->userdata('userid'),
                    'add_time' => date('Y-m-d H:i:s'),
                ];
                $this->db->insert('tbl_workitem', $data);
                $res = ['success' => true, 'message' => 'Work Item added successfully'];

            }

        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function updateWorkItem()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $workObj = json_decode($this->input->raw_input_stream);
            $data = [
                'name' => $workObj->name,
                'update_by' => $this->session->userdata('userid'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            $this->db->where('id', $workObj->id)->update('tbl_workitem', $data);
            $res = ['success' => true, 'message' => 'Work Item updated successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function deletedWorkItem()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set(['status' => 'd'])->where('id', $data->workItemId)->update('tbl_workitem');

            $res = ['success' => true, 'message' => 'Work Item deleted successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }
}

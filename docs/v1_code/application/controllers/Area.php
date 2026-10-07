<?php

class Area extends CI_Controller
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
        $data['backend_content'] = 'page/area';
        $data['title'] = 'Area Entry';
        $this->load->view('admin/layout', $data);
    }

    public function getAreas()
    {
        $areas = $this->db->query("select * from tbl_area  where status = 'a'")->result();
        echo json_encode($areas);
    }

    public function addArea()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $areaObj = json_decode($this->input->raw_input_stream);

            $dublicateArea = $this->db->query('select * from tbl_area where name=?', $areaObj->name);

            if ($dublicateArea->num_rows() > 0) {
                $row = $dublicateArea->row();
                $id = $row->add_by;
                $officer = $this->db->query('select name from tbl_user where id=?', $id)->row();
                $res = ['success' => true, 'message' => 'This area already added by '.$officer->name.' '];
            } else {
                $data = [
                    'name' => $areaObj->name,
                    'status' => 'a',
                    'add_by' => $this->session->userdata('userid'),
                    'add_time' => date('Y-m-d H:i:s'),
                ];
                $this->db->insert('tbl_area', $data);

                $res = ['success' => true, 'message' => 'Area added successfully'];

            }

        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function updateArea()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $areaObj = json_decode($this->input->raw_input_stream);

            $data = [
                'name' => $areaObj->name,
                'update_by' => $this->session->userdata('userid'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            $this->db->where('id', $areaObj->id)->update('tbl_area', $data);

            $res = ['success' => true, 'message' => 'Area updated successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function deletedArea()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set(['status' => 'd'])->where('id', $data->areaId)->update('tbl_area');

            $res = ['success' => true, 'message' => 'Area deleted successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }
}

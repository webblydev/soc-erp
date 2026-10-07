<?php

class Software extends CI_Controller
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
        $data['title'] = 'Service Entry';
        $data['backend_content'] = 'page/software';
        $this->load->view('admin/layout', $data);
    }

    public function getSoftware()
    {
        $software = $this->db->query("select * from tbl_software  where status = 'a'")->result();
        echo json_encode($software);
    }

    public function addSoftware()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $areaObj = json_decode($this->input->raw_input_stream);

            $dublicateArea = $this->db->query('select * from tbl_software where soft_name=?', $areaObj->soft_name);

            if ($dublicateArea->num_rows() > 0) {
                $row = $dublicateArea->row();
                $id = $row->add_by;
                $officer = $this->db->query('select soft_name from tbl_user where id=?', $id)->row();
                $res = ['success' => true, 'message' => 'This area already added by '.$officer->soft_name.' '];
            } else {
                $data = [
                    'soft_name' => $areaObj->soft_name,
                    'status' => 'a',
                    'add_by' => $this->session->userdata('userid'),
                    'add_time' => date('Y-m-d H:i:s'),
                ];
                $this->db->insert('tbl_software', $data);

                $res = ['success' => true, 'message' => 'Software added successfully'];

            }

        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function updateSoftware()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $areaObj = json_decode($this->input->raw_input_stream);

            $data = [
                'soft_name' => $areaObj->soft_name,
                'update_by' => $this->session->userdata('userid'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            $this->db->where('id', $areaObj->id)->update('tbl_software', $data);

            $res = ['success' => true, 'message' => 'Software updated successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function deletedSoftware()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set(['status' => 'd'])->where('id', $data->softId)->update('tbl_software');

            $res = ['success' => true, 'message' => 'Software deleted successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }
}

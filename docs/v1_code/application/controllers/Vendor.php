<?php

class Vendor extends CI_Controller
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
        $data['backend_content'] = 'page/vendor';
        $data['title'] = 'Vendor Entry';
        $this->load->view('admin/layout', $data);
    }

    public function getVendors()
    {
        $areas = $this->db->query("select * from tbl_vendor  where status = 'a'")->result();
        echo json_encode($areas);
    }

    public function addVendor()
    {
        $res = ['success' => false, 'message' => ''];
        try {

            $vendorObj = json_decode($this->input->raw_input_stream);
            $vendor = $this->db->query('select * from tbl_vendor where name=?', $vendorObj->name);
            $data = [
                'name' => $vendorObj->name,
                'enterprise_name' => $vendorObj->enterprise_name,
                'contact_number' => $vendorObj->contact_number,
                'account_id' => $vendorObj->account_id,
                'status' => 'a',
                'add_by' => $this->session->userdata('userid'),
                'add_time' => date('Y-m-d H:i:s'),
            ];
            $this->db->insert('tbl_vendor', $data);
            $res = ['success' => true, 'message' => 'Vendor added successfully'];

        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function updateVendor()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $vendorObj = json_decode($this->input->raw_input_stream);
            $data = [
                'name' => $vendorObj->name,
                'enterprise_name' => $vendorObj->enterprise_name,
                'contact_number' => $vendorObj->contact_number,
                'account_id' => $vendorObj->account_id,
                'update_by' => $this->session->userdata('userid'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            $this->db->where('id', $vendorObj->id)->update('tbl_vendor', $data);
            $res = ['success' => true, 'message' => 'Vendor updated successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }
        echo json_encode($res);
    }

    public function deletedVendor()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);
            $this->db->set(['status' => 'd'])->where('id', $data->vendorId)->update('tbl_vendor');
            $res = ['success' => true, 'message' => 'Vendor deleted successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }
        echo json_encode($res);
    }
}

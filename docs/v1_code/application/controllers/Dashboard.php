<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Dashboard extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (! $this->session->userdata('userid')) {
            redirect(base_url());
        }
    }

    public function dashborad()
    {
        $data['title'] = 'Dashboard';
        $data['backend_content'] = 'page/dashboard';
        $this->load->view('admin/layout', $data);
    }

    public function companyProfile()
    {
        $data['title'] = 'Company Profile';
        $data['backend_content'] = 'page/company-profile';
        $this->load->view('admin/layout', $data);
    }

    public function getCompanyProfile()
    {
        $company = $this->db->query('select * from tbl_company limit 1')->row();
        echo json_encode($company);
    }

    public function imageUpload($file_name_get)
    {
        $file_name = $file_name_get['name'];
        $file_temp = $file_name_get['tmp_name'];

        $div = explode('.', $file_name);
        $get_last_e = end($div);
        $new_name = rand().'.'.$get_last_e;
        move_uploaded_file($file_temp, 'assets/images/'.$new_name);

        return $new_name;
    }

    public function updateCompanyProfile()
    {
        $res = new stdClass;

        try {
            $data = json_decode($this->input->post('data'));

            $oldImage = $this->db->query('select image from tbl_company where id =?', $data->id)->row();

            $image = '';
            if (isset($_FILES['image'])) {
                $image = $this->imageUpload($_FILES['image']);

                if ($oldImage->image != '') {
                    $img_unlink = 'assets/images/'.$oldImage->image;
                    unlink($img_unlink);
                }
            } else {
                $image = $oldImage->image;
            }

            $company = [
                'name' => $data->name,
                'phone' => $data->phone,
                'email' => $data->email,
                'address' => $data->address,
                'image' => $image,
            ];

            $this->db->where('id', $data->id)->update('tbl_company', $company);

            $res->message = 'Update successfully';
            $res->success = true;
        } catch (Exception $ex) {
            $res->message = $ex->getMessage();
            $res->success = false;
        }

        echo json_encode($res);
    }
}

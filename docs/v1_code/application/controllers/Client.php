<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Client extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (! $this->session->userdata('userid')) {
            redirect(base_url());
        }

        $this->load->model('SMS_model', 'sms', true);
    }

    public function index()
    {
        $data['clientId'] = 0;
        $data['title'] = 'Prospect Entry';
        $data['backend_content'] = 'page/prospect-entry';
        $this->load->view('admin/layout', $data);
    }

    public function addClient()
    {

        $res = ['success' => false, 'message' => ''];
        $this->db->trans_begin();

        try {
            $clientObj = json_decode($this->input->raw_input_stream);
            // echo json_encode($clientObj->note);
            // exit;

            $checkPhone = $this->db->query('select * from  tbl_client where phone = ?', $clientObj->phone);
            $checkOrg = $this->db->query('select * from  tbl_client where org_name = ?', $clientObj->org_name);

            if ($checkOrg->num_rows() > 0) {
                $row = $checkOrg->row();
                $id = $row->add_by;
                $officer = $this->db->query('select name from tbl_user where id = ?', $id)->row();
                $res = ['success' => false, 'message' => 'This organization name already added by '.$officer->name.' '];
                echo json_encode($res);
                exit;
            }

            if ($checkPhone->num_rows() > 0) {
                $row = $checkPhone->row();
                $id = $row->add_by;
                $officer = $this->db->query('select name from tbl_user where id = ?', $id)->row();
                $res = ['success' => false, 'message' => 'This phone number already by '.$officer->name.' '];
                echo json_encode($res);
                exit;
            }

            $requirement = implode(',', $clientObj->requirement);
            $sold = implode(',', $clientObj->sold);
            $data = [
                'client_id' => $clientObj->client_id,
                'client_name' => $clientObj->client_name,
                'client_type_id' => $clientObj->client_type_id,
                'org_name' => $clientObj->org_name,
                'project_id' => $clientObj->project_id,
                'phone' => $clientObj->phone,
                'org_mobile' => $clientObj->org_mobile,
                'w_number' => $clientObj->w_number,
                'email' => $clientObj->email,
                'address' => $clientObj->address,
                'area_id' => $clientObj->area_id,
                'requirement' => $requirement,
                'sold' => $sold,
                'level' => $clientObj->level,
                'source' => $clientObj->source,
                'reminder' => $clientObj->reminder,
                'comment' => $clientObj->comment,
                'note' => $clientObj->note,
                'date' => $clientObj->date,
                'status' => $clientObj->sold == [''] ? 'p' : 's',
                'add_by' => $this->session->userdata('userid'),
                'add_time' => date('Y-m-d H:i:s'),
            ];

            $this->db->insert('tbl_client', $data);
            $clientId = $this->db->insert_id();

            if (isset($clientObj->date) && $clientObj->date != '' && isset($clientObj->note) && $clientObj->note != '') {
                $details = [
                    'client_id' => $clientId,
                    'date' => $clientObj->date,
                    'note' => $clientObj->note,
                    'added_by' => $this->session->userdata('username'),
                    'added_date' => date('Y-m-d H:i:s'),
                ];

                $this->db->insert('tbl_clientdetails', $details);
            }

            // Send sms

            // if (isset($clientObj->client_name) && $clientObj->client_name != '') {
            //     $sendToName = $clientObj->client_name;

            //     $message = "Dear {$sendToName},\nWelcome to Link-Up Technology. Our Services:\n\n 1. Website Design. \n 2. Software Development.";
            //     $recipient = $clientObj->phone;
            //     $this->sms->sendSms($recipient, $message);
            // }

            $res = ['success' => true, 'message' => 'Prospect added successfully'];
        } catch (Exception $ex) {
            $this->db->trans_rollback();
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        $this->db->trans_commit();
        echo json_encode($res);
    }

    public function getClient()
    {

        $obj = json_decode($this->input->raw_input_stream);

        // echo json_encode($obj->clientTypeId);
        // die();

        $clause = '';
        if (isset($obj->clientId) && $obj->clientId != '') {
            $clause .= " and c.id = '$obj->clientId'";
        }
        if (isset($obj->type) && $obj->type != '') {
            $clause .= " and c.status = '$obj->type'";
        }
        if (isset($obj->userId) && $obj->userId != '') {
            $clause .= " and c.add_by = $obj->userId";
        }
        if (isset($obj->areaId) && $obj->areaId != '') {
            $clause .= " and c.area_id = $obj->areaId";
        }

        if (isset($obj->clientTypeId) && $obj->clientTypeId != '') {
            $clause .= " and c.client_type_id = $obj->clientTypeId";
        }
        if (isset($obj->requirementId) && $obj->requirementId != '') {
            $clause .= " and c.requirement like '%$obj->requirementId%'";
        }
        if (isset($obj->teamName) && $obj->teamName != '') {
            $clause .= " and u.team_name = '$obj->teamName'";
        }
        if (isset($obj->source) && $obj->source != '') {
            $clause .= " and c.source = '$obj->source'";
        }
        if (isset($obj->dateFrom) && $obj->dateFrom != '' && isset($obj->dateTo) && $obj->dateTo != '') {
            $clause .= " and DATE_FORMAT(c.add_time,'%Y-%m-%d') between '$obj->dateFrom' and '$obj->dateTo'";
        }

        $clients = $this->db->query("
			select c.* ,
				a.id as aid,
				concat_ws('-', c.client_id, c.client_name, c.phone) as display_name,
				a.name,
				u.name as officer,
				ct.name as client_type_name
			from tbl_client as c 
			join tbl_area as a on c.area_id = a.id 
			left join tbl_clienttype ct on ct.id = c.client_type_id
			left join tbl_user as u on c.add_by = u.id  
			where c.status != 'd' 
			$clause
			order by c.id desc
			")->result();

        $clients = array_map(function ($client) {
            if ($client->requirement != '') {
                $client->requirements = $this->db->query("SELECT * FROM tbl_software as s WHERE s.id IN ($client->requirement)")->result();
            }

            return $client;
        }, $clients);

        echo json_encode($clients);
    }

    public function getClientDetail()
    {
        $obj = json_decode($this->input->raw_input_stream);

        $clients = $this->db->query("
			SELECT c.client_name, c.phone, c.client_id, c.org_name, c.address
			FROM tbl_client AS c 
			WHERE c.status != 'd' AND c.sold != '' AND c.client_id = ?
			ORDER BY c.id DESC
		", [$obj->client_id])->row();

        echo json_encode($clients);
    }

    public function customerPaymentPage()
    {
        // $access = $this->mt->userAccess();
        // if(!$access){
        //     redirect(base_url());
        // }
        $data['title'] = 'Customer Payment';
        $data['paymentHis'] = $this->Billing_model->fatch_all_payment();
        $query0 = $this->db->query('SELECT * FROM tbl_customer_payment ORDER BY CPayment_id DESC LIMIT 1');
        $row = $query0->row();

        @$invoice = $row->CPayment_invoice;
        $previousinvoice = substr($invoice, 3, 11);
        if (! empty($invoice)) {
            if ($previousinvoice < 10) {
                $purchInvoice = 'TR-00'.($previousinvoice + 1);
            } elseif ($previousinvoice < 100) {
                $purchInvoice = 'TR-0'.($previousinvoice + 1);
            } else {
                $purchInvoice = 'TR-'.($previousinvoice + 1);
            }
        } else {
            $purchInvoice = 'TR-001';
        }
        $data['purchInvoice'] = $purchInvoice;
        $data['customers'] = $this->Customer_model->get_customer_name_code_brunch_wise();
        $data['content'] = $this->load->view('Administrator/due_report/customerPaymentPage', $data, true);
        $this->load->view('Administrator/index', $data);
    }

    public function updateClient()
    {
        $res = ['success' => false, 'message' => ''];
        $this->db->trans_begin();

        try {
            $clientObj = json_decode($this->input->raw_input_stream);
            $requirement = implode(',', $clientObj->requirement);
            $sold = implode(',', $clientObj->sold);

            $checkPhone = $this->db->query('select * from  tbl_client where phone = ? and id != ?', [$clientObj->phone, $clientObj->id]);
            $checkOrg = $this->db->query('select * from  tbl_client where org_name = ? and id != ?', [$clientObj->org_name, $clientObj->id]);

            if ($checkOrg->num_rows() > 0) {
                $row = $checkOrg->row();
                $id = $row->add_by;
                $officer = $this->db->query('select name from tbl_user where id = ?', $id)->row();
                $res = ['success' => false, 'message' => 'This organization name already added by '.$officer->name.' '];
                echo json_encode($res);
                exit;
            }

            if ($checkPhone->num_rows() > 0) {
                $row = $checkPhone->row();
                $id = $row->add_by;
                $officer = $this->db->query('select name from tbl_user where id = ?', $id)->row();
                $res = ['success' => false, 'message' => 'This phone number already by '.$officer->name.' '];
                echo json_encode($res);
                exit;
            }

            $data = [
                'client_id' => $clientObj->client_id,
                'client_name' => $clientObj->client_name,
                'client_type_id' => $clientObj->client_type_id,
                'project_id' => $clientObj->project_id,
                'org_name' => $clientObj->org_name,
                'phone' => $clientObj->phone,
                'org_mobile' => $clientObj->org_mobile,
                'w_number' => $clientObj->w_number,
                'email' => $clientObj->email,
                'address' => $clientObj->address,
                'area_id' => $clientObj->area_id,
                'requirement' => $requirement,
                'sold' => $sold,
                'level' => $clientObj->level,
                'source' => $clientObj->source,
                'note' => $clientObj->note,
                'date' => $clientObj->date,
                'reminder' => $clientObj->reminder,
                'comment' => $clientObj->comment,
                'status' => $clientObj->sold == [''] ? 'p' : 's',
                'update_by' => $this->session->userdata('userid'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            $this->db->where('id', $clientObj->id)->update('tbl_client', $data);

            if (isset($clientObj->date) && $clientObj->date != '' && isset($clientObj->note) && $clientObj->note != '') {
                $details = [
                    'client_id' => $clientObj->id,
                    'date' => $clientObj->date,
                    'note' => $clientObj->note,
                    'added_by' => $this->session->userdata('username'),
                    'added_date' => date('Y-m-d H:i:s'),
                ];

                $this->db->insert('tbl_clientdetails', $details);
            }

            $res = ['success' => true, 'message' => 'Client update successfully'];
        } catch (Exception $ex) {
            $this->db->trans_rollback();
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        $this->db->trans_commit();
        echo json_encode($res);
    }

    public function deleteClient()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set(['status' => 'd'])->where('id', $data->clientId)->update('tbl_client');

            $res = ['success' => true, 'message' => 'Client deleted successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function clientSold()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set(['status' => 's'])->where('id', $data->clientId)->update('tbl_client');

            $res = ['success' => true, 'message' => 'Sold successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function soldClient()
    {
        $data['title'] = 'Sold Client';
        $data['backend_content'] = 'page/sold-client';
        $this->load->view('admin/layout', $data);
    }

    public function changeSoldClient()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set(['status' => 'p'])->where('id', $data->clientId)->update('tbl_client');

            $res = ['success' => true, 'message' => 'Change  successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }
        echo json_encode($res);
    }

    public function getTeamClient()
    {
        $data['title'] = 'Prospect List';
        $data['backend_content'] = 'page/team-clients';
        $this->load->view('admin/layout', $data);
    }

    public function clientReport()
    {
        $data['title'] = 'Client Report';
        $data['backend_content'] = 'page/report';
        $this->load->view('admin/layout', $data);
    }

    public function editSoldStatus($id)
    {
        $data['title'] = 'Client Update';
        $data['backend_content'] = 'page/prospect-entry';
        $data['clientId'] = $id;
        $this->load->view('admin/layout', $data);
    }

    public function getReminderClient()
    {
        $res = new stdClass;
        $data = json_decode($this->input->raw_input_stream);

        $clients = $this->db->query("
			select c.* ,
				a.id as aid,
				a.name,
				u.name as officer
			from tbl_client as c 
			join tbl_area as a on c.area_id = a.id 
			left join tbl_user as u on c.add_by = u.id  
			where c.status != 'd' 
			and c.reminder between DATE('$data->reminder') - INTERVAL 7 DAY and '$data->reminder' 
            and c.add_by = ?
			order by c.id desc
			", $this->session->userdata('userid'))->result();

        $res->beforeClients = array_map(function ($client) {
            if ($client->requirement != '') {
                $client->requirements = $this->db->query("SELECT * FROM tbl_software as s WHERE s.id IN ($client->requirement)")->result();
            }

            return $client;
        }, $clients);

        $afterClients = $this->db->query("
			select c.* ,
				a.id as aid,
				a.name,
				u.name as officer
			from tbl_client as c 
			join tbl_area as a on c.area_id = a.id 
			left join tbl_user as u on c.add_by = u.id  
			where c.status != 'd' 
			and c.reminder between '$data->reminder' and DATE('$data->reminder') + INTERVAL 7 DAY
            and c.add_by = ?
			order by c.id desc
			", $this->session->userdata('userid'))->result();

        $res->afterClients = array_map(function ($client) {
            if ($client->requirement != '') {
                $client->requirements = $this->db->query("SELECT * FROM tbl_software as s WHERE s.id IN ($client->requirement)")->result();
            }

            return $client;
        }, $afterClients);
        echo json_encode($res);
    }

    public function getClientDetails()
    {
        $data = json_decode($this->input->raw_input_stream);

        $clause = '';

        if (isset($data->clientId) && $data->clientId != '') {
            $clause .= " and cd.client_id = $data->clientId";
        }

        $clients = $this->db->query("
            select 
                cd.*
            from tbl_clientdetails cd 
            where 1 = 1
            $clause
        ")->result();

        echo json_encode($clients);
    }

    // Client Types Method

    public function ClientIndex()
    {
        $data['backend_content'] = 'page/clientType';
        $data['title'] = 'Client Type Entry';
        $this->load->view('admin/layout', $data);
    }

    public function getClientType()
    {
        $areas = $this->db->query("select * from tbl_clienttype  where status = 'a'")->result();
        echo json_encode($areas);
    }

    public function addClientType()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $clientTypeObj = json_decode($this->input->raw_input_stream);

            $dublicateArea = $this->db->query('select * from tbl_clienttype where name=?', $clientTypeObj->name);

            if ($dublicateArea->num_rows() > 0) {
                $row = $dublicateArea->row();
                $id = $row->add_by;
                $officer = $this->db->query('select name from tbl_user where id=?', $id)->row();
                $res = ['success' => true, 'message' => 'This area already added by '.$officer->name.' '];
            } else {
                $data = [
                    'name' => $clientTypeObj->name,
                    'status' => 'a',
                    'add_by' => $this->session->userdata('userid'),
                    'add_time' => date('Y-m-d H:i:s'),
                ];
                $this->db->insert('tbl_clienttype', $data);

                $res = ['success' => true, 'message' => 'Client Type added successfully'];
            }
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function updateClientType()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $areaObj = json_decode($this->input->raw_input_stream);

            $data = [
                'name' => $areaObj->name,
                'update_by' => $this->session->userdata('userid'),
                'update_time' => date('Y-m-d H:i:s'),
            ];
            $this->db->where('id', $areaObj->id)->update('tbl_clienttype', $data);

            $res = ['success' => true, 'message' => 'Client type updated successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function deletedClientType()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set(['status' => 'd'])->where('id', $data->areaId)->update('tbl_clienttype');

            $res = ['success' => true, 'message' => 'Client Type deleted successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }
}

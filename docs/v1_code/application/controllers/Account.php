<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Account extends CI_Controller
{
    // public function __construct() {
    //     parent::__construct();
    //     $this->brunch = $this->session->userdata('BRANCHid');
    //     $access = $this->session->userdata('userId');
    //      if($access == '' ){
    //         redirect("Login");
    //     }
    //     $this->load->model("Model_myclass", "mmc", TRUE);
    //     $this->load->model('Model_table', "mt", TRUE);
    // 	$this->load->model('Billing_model');
    // }

    public function __construct()
    {
        parent::__construct();
        if (! $this->session->userdata('userid')) {
            redirect(base_url());
        }

        $this->load->model('Model_table', 'mt', true);
        $this->load->model('Billing_model');
    }

    public function index()
    {

        $data['title'] = 'Add Account';
        $data['accountCode'] = $this->mt->generateAccountCode();
        $data['backend_content'] = 'account/add_account';
        $this->load->view('admin/layout', $data);
    }

    public function addAccount()
    {

        $res = ['success' => false, 'message' => 'Nothing'];
        try {
            $accountObj = json_decode($this->input->raw_input_stream);
            $duplicateCodeCount = $this->db->query('select * from tbl_account where Acc_Code = ?', $accountObj->Acc_Code)->num_rows();
            // if($duplicateCodeCount != 0){
            //     $accountObj = 'abc';
            // }

            $duplicateNameCount = $this->db->query('select * from tbl_account where Acc_Name = ? ', $accountObj->Acc_Name)->num_rows();

            if ($duplicateNameCount != 0) {
                $this->db->query("update tbl_account set status = 'a' where Acc_Name = ? ", $accountObj->Acc_Name);
                $res = ['success' => true, 'message' => 'Account activated', 'newAccountCode' => $this->mt->generateAccountCode()];
                echo json_encode($res);
                exit;
            }

            $account = (array) $accountObj;

            unset($account['Acc_SlNo']);
            $account['status'] = 'a';
            $account['AddBy'] = $this->session->userdata('FullName');
            $account['AddTime'] = date('Y-m-d H:i:s');

            $this->db->insert('tbl_account', $account);

            $res = ['success' => true, 'message' => 'Account added', 'newAccountCode' => $this->mt->generateAccountCode()];
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }

        echo json_encode($res);
    }

    public function account_insertFanceybox()
    {
        $mail = $this->input->post('accountName');
        $query = $this->db->query("SELECT Acc_Name from tbl_account where Acc_Name = '$mail'");

        if ($query->num_rows() > 0) {
            $data['exists'] = 'This Name is Already Exists';
            $this->load->view('Administrator/ajax/add_account', $data);
        } else {
            $data = [
                'Acc_Code' => $this->input->post('account_id', true),
                'Acc_Name' => $this->input->post('accountName', true),
                'Acc_Type' => $this->input->post('accounttype', true),
                'Acc_Description' => $this->input->post('Description', true),
                'AddBy' => $this->session->userdata('FullName'),
                'AddTime' => date('Y-m-d H:i:s'),
            ];
            $this->mt->save_data('tbl_account', $data);
            $this->load->view('Administrator/ajax/transaction/fancyboxResultOffice');
        }
    }

    public function addAccountTrans()
    {
        $this->load->view('Administrator/account/add_account_in_trans');
    }

    public function account_edit()
    {
        $id = $this->input->post('edit');
        $query = $this->db->query("SELECT * from tbl_account where Acc_SlNo = '$id'");

        $data['selected'] = $query->row();
        // $data['content'] = $this->load->view('Administrator/edit/supplier_edit', $data, TRUE);
        $this->load->view('Administrator/edit/account_edit', $data);
    }

    public function updateAccount()
    {
        $res = ['success' => false, 'message' => 'Nothing'];
        try {
            $accountObj = json_decode($this->input->raw_input_stream);

            $duplicateNameCount = $this->db->query('select * from tbl_account where Acc_Name = ? and Acc_SlNo != ?', [$accountObj->Acc_Name, $accountObj->Acc_SlNo])->num_rows();
            if ($duplicateNameCount != 0) {
                $this->db->query("update tbl_account set status = 'a' where Acc_Name = ?", [$accountObj->Acc_Name]);
                $res = ['success' => true, 'message' => 'Account activated', 'newAccountCode' => $this->mt->generateAccountCode()];
                echo json_encode($res);
                exit;
            }

            $account = (array) $accountObj;
            unset($account['Acc_SlNo']);
            $account['UpdateBy'] = $this->session->userdata('FullName');
            $account['UpdateTime'] = date('Y-m-d H:i:s');

            $this->db->where('Acc_SlNo', $accountObj->Acc_SlNo)->update('tbl_account', $account);

            $res = ['success' => true, 'message' => 'Account updated', 'newAccountCode' => $this->mt->generateAccountCode()];
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }

        echo json_encode($res);
    }

    public function deleteAccount()
    {
        $res = ['success' => false, 'message' => 'Nothing'];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->query("update tbl_account set status = 'd' where Acc_SlNo = ?", $data->accountId);

            $res = ['success' => true, 'message' => 'Account deleted'];
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }

        echo json_encode($res);
    }

    public function getAccounts()
    {
        $accounts = $this->db->query("
		 select a.*
		 from tbl_account a 
		 where a.status = 'a'
		 order by a.Acc_SlNo
		 ")->result();
        echo json_encode($accounts);
    }

    // Cash Transaction
    public function cash_transaction()
    {

        $data['title'] = 'Cash Transaction';
        $data['backend_content'] = 'account/cash_transaction';
        $this->load->view('admin/layout', $data);
    }

    public function vendorExpense()
    {
        $data['title'] = 'Vendor Expense';
        $data['backend_content'] = 'account/vendor_expense';
        $this->load->view('admin/layout', $data);
    }

    public function vendorBillEntry()
    {
        $data['title'] = 'Vendor Bill Entry';
        $data['backend_content'] = 'account/vendor_bill_entry';
        $this->load->view('admin/layout', $data);
    }

    public function addVendorExpense()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $transactionObj = json_decode($this->input->raw_input_stream);
            $transaction = (array) $transactionObj;
            $transaction['status'] = 'a';
            $transaction['AddBy'] = $this->session->userdata('FullName');
            $transaction['AddTime'] = date('Y-m-d H:i:s');
            // $transaction['Tr_branchid'] = $this->session->userdata('BRANCHid');
            $this->db->insert('tbl_vendor_expense', $transaction);
            $res = ['success' => true, 'message' => 'Transaction added'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function addVendorBillEntry()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $transactionObj = json_decode($this->input->raw_input_stream);
            // echo json_encode($transactionObj);
            // exit;
            $transaction = (array) $transactionObj->project_visit;
            $transaction['status'] = 'a';
            $transaction['AddBy'] = $this->session->userdata('FullName');
            $transaction['AddTime'] = date('Y-m-d H:i:s');
            // $transaction['Tr_branchid'] = $this->session->userdata('BRANCHid');
            $this->db->insert('tbl_vendor_bill_entry', $transaction);

            $vendorBillId = $this->db->insert_id();

            foreach ($transactionObj->cart as $cartProduct) {
                $vebdorBillDetails = [
                    'vendor_bill_id' => $vendorBillId,
                    'item_code' => $cartProduct->item_code,
                    'mb_page_no' => $cartProduct->mb_page_no,
                    'visit_location' => $cartProduct->visit_location,
                    'visit_description' => $cartProduct->visit_description,
                    'vendor_unit' => $cartProduct->vendor_unit,
                    'estimated_quantity' => $cartProduct->estimated_quantity,
                    'vendor_rate' => $cartProduct->vendor_rate,
                    'status' => 'a',
                    'AddBy' => $this->session->userdata('FullName'),
                    'AddTime' => date('Y-m-d H:i:s'),
                    'vendor_bill_details_branchid' => $this->session->userdata('BRANCHid'),
                ];

                $this->db->insert('tbl_vendor_bill_details', $vebdorBillDetails);
            }

            $res = ['success' => true, 'message' => 'Vendor Bill Entry Success', 'vendorBillId' => $vendorBillId];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }
        echo json_encode($res);
    }

    // project bill entry functionality start

    public function getProjectBillCode()
    {
        echo json_encode($this->mt->generateProjectBillCode());
    }

    public function projectBillEntry()
    {
        $data['title'] = 'Project Bill Entry';
        $data['backend_content'] = 'account/project_bill_entry';
        $this->load->view('admin/layout', $data);
    }

    public function addProjectBillEntry()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $transactionObj = json_decode($this->input->raw_input_stream);
            // echo json_encode($transactionObj);
            // exit;
            $transaction = (array) $transactionObj->project_visit;

            $transaction['status'] = 'a';
            $transaction['AddBy'] = $this->session->userdata('FullName');
            $transaction['AddTime'] = date('Y-m-d H:i:s');
            // $transaction['Tr_branchid'] = $this->session->userdata('BRANCHid');
            $this->db->insert('tbl_project_bill_entry', $transaction);

            $projectBillId = $this->db->insert_id();

            foreach ($transactionObj->cart as $cartProduct) {
                $projectBillDetails = [
                    'project_bill_id' => $projectBillId,
                    'item_code' => $cartProduct->item_code,
                    'mb_page_no' => $cartProduct->mb_page_no,
                    'visit_location' => $cartProduct->visit_location,
                    'visit_description' => $cartProduct->visit_description,
                    'vendor_unit' => $cartProduct->vendor_unit,
                    'estimated_quantity' => $cartProduct->estimated_quantity,
                    'vendor_rate' => $cartProduct->vendor_rate,
                    'status' => 'a',
                    'AddBy' => $this->session->userdata('FullName'),
                    'AddTime' => date('Y-m-d H:i:s'),
                    'project_bill_details_branchid' => $this->session->userdata('BRANCHid'),
                ];

                $this->db->insert('tbl_project_bill_details', $projectBillDetails);
            }

            $res = ['success' => true, 'message' => 'Project Bill Entry Success', 'projectBillId' => $projectBillId];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }
        echo json_encode($res);
    }

    public function updateProjectBillEntry()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $transactionObj = json_decode($this->input->raw_input_stream);

            $transaction = (array) $transactionObj->project_visit;

            $tr_id = $transaction['Tr_SlNo'];

            unset($transaction['Tr_SlNo']);

            $transaction['UpdateBy'] = $this->session->userdata('FullName');
            $transaction['UpdateTime'] = date('Y-m-d H:i:s');
            $this->db->where('Tr_SlNo', $tr_id)->update('tbl_project_bill_entry', $transaction);

            $this->db->query('delete from tbl_project_bill_details where project_bill_id = ?', $tr_id);
            foreach ($transactionObj->cart as $cartProduct) {
                $vebdorBillDetails = [
                    'project_bill_id' => $tr_id,
                    'item_code' => $cartProduct->item_code,
                    'mb_page_no' => $cartProduct->mb_page_no,
                    'visit_location' => $cartProduct->visit_location,
                    'visit_description' => $cartProduct->visit_description,
                    'vendor_unit' => $cartProduct->vendor_unit,
                    'estimated_quantity' => $cartProduct->estimated_quantity,
                    'vendor_rate' => $cartProduct->vendor_rate,
                    'status' => 'a',
                    'AddBy' => $this->session->userdata('FullName'),
                    'AddTime' => date('Y-m-d H:i:s'),
                    'project_bill_details_branchid' => $this->session->userdata('BRANCHid'),
                ];

                $this->db->insert('tbl_project_bill_details', $vebdorBillDetails);
            }

            $res = ['success' => true, 'message' => 'Project Bill Entry updated'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }
        echo json_encode($res);
    }

    public function getProjectBillEntry()
    {
        $data = json_decode($this->input->raw_input_stream);
        $dateClause = '';
        if (isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != '') {
            $dateClause = " and pbe.Tr_date between '$data->dateFrom' and '$data->dateTo'";
        }

        $accountClause = '';
        if (isset($data->accountId) && $data->accountId != '') {
            $accountClause = " and pbe.Acc_SlID = '$data->accountId'";
        }

        $transactions = $this->db->query("
                select 
                    pbe.*,
                    p.name
                from tbl_project_bill_entry pbe
                left join tbl_project p on p.id = pbe.Acc_SlID
                where pbe.status = 'a'
                $dateClause $accountClause
                order by pbe.Tr_SlNo desc
            ")->result();

        foreach ($transactions as $visit) {
            $visit->details = $this->db->query("
					select pbe.* 
					from tbl_project_bill_details pbe
					where pbe.project_bill_id = '$visit->Tr_SlNo'
				")->result();
        }
        echo json_encode($transactions);
    }

    // project bill entry functionality start

    public function updateVendorExpense()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $transactionObj = json_decode($this->input->raw_input_stream);
            $transaction = (array) $transactionObj;
            unset($transaction['Tr_SlNo']);
            $transaction['UpdateBy'] = $this->session->userdata('FullName');
            $transaction['UpdateTime'] = date('Y-m-d H:i:s');
            $this->db->where('Tr_SlNo', $transactionObj->Tr_SlNo)->update('tbl_vendor_expense', $transaction);
            $res = ['success' => true, 'message' => 'Transaction updated'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function getVendorExpense()
    {

        $data = json_decode($this->input->raw_input_stream);
        $dateClause = '';
        if (isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != '') {
            $dateClause = " and ve.Tr_date between '$data->dateFrom' and '$data->dateTo'";
        }
        $transactionTypeClause = '';
        if (isset($data->transactionType) && $data->transactionType != '' && $data->transactionType == 'received') {
            $transactionTypeClause = " and ve.Tr_Type = 'In Cash'";
        }
        if (isset($data->transactionType) && $data->transactionType != '' && $data->transactionType == 'paid') {
            $transactionTypeClause = " and ve.Tr_Type = 'Out Cash'";
        }
        $accountClause = '';
        if (isset($data->accountId) && $data->accountId != '') {
            $accountClause = " and ve.vendor_id = '$data->accountId'";
        }
        $transactions = $this->db->query("
            select 
                ve.*,
                v.name
            from tbl_vendor_expense ve
            left join tbl_vendor v on v.id = ve.vendor_id
            where ve.status = 'a'
            $dateClause $transactionTypeClause $accountClause
            order by ve.Tr_SlNo desc
        ")->result();
        echo json_encode($transactions);
    }

    public function getVendorBill()
    {
        $data = json_decode($this->input->raw_input_stream);
        $dateClause = '';
        if (isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != '') {
            $dateClause = " and vb.Tr_date between '$data->dateFrom' and '$data->dateTo'";
        }
        $transactionTypeClause = '';
        if (isset($data->transactionType) && $data->transactionType != '' && $data->transactionType == 'received') {
            $transactionTypeClause = " and vb.Tr_Type = 'In Cash'";
        }
        if (isset($data->transactionType) && $data->transactionType != '' && $data->transactionType == 'paid') {
            $transactionTypeClause = " and vb.Tr_Type = 'Out Cash'";
        }
        $accountClause = '';
        if (isset($data->accountId) && $data->accountId != '') {
            $accountClause = " and vb.Acc_SlID = '$data->accountId'";
        }
        $transactions = $this->db->query("
                select 
                    vb.*,
                    v.name,
                    p.name as project_name
                from tbl_vendor_bill_entry vb
                left join tbl_vendor v on v.id = vb.vendor_id
                left join tbl_project p on p.id = vb.project_id
                where vb.status = 'a'
                $dateClause $transactionTypeClause $accountClause
                order by vb.Tr_SlNo desc
            ")->result();

        foreach ($transactions as $visit) {
            $visit->details = $this->db->query("
					select vbe.* 
					from tbl_vendor_bill_details vbe
					where vbe.vendor_bill_id = '$visit->Tr_SlNo'
				")->result();
        }

        echo json_encode($transactions);
    }

    public function projectLedger()
    {
        $data['title'] = 'Project Ledger';
        $data['backend_content'] = 'account/project_ledger';
        $this->load->view('admin/layout', $data);
    }

    public function monthlyRevenueReport()
    {
        $data['title'] = 'Monthly Revenue Report';
        $data['backend_content'] = 'account/monthly_revenue_report';
        $this->load->view('admin/layout', $data);
    }

    public function clientLedger()
    {
        $data['title'] = 'Client Ledger';
        $data['backend_content'] = 'account/client_ledger';
        $this->load->view('admin/layout', $data);
    }

    public function vendorBillRecord()
    {
        $data['title'] = 'Vendor Bill Record';
        $data['backend_content'] = 'account/vendor_bill_record';
        $this->load->view('admin/layout', $data);
    }

    public function projectBillRecord()
    {
        $data['title'] = 'Project Bill Record';
        $data['backend_content'] = 'account/project_bill_record';
        $this->load->view('admin/layout', $data);
    }

    public function updateVendorBillEntry()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $transactionObj = json_decode($this->input->raw_input_stream);

            $transaction = (array) $transactionObj->project_visit;

            $tr_id = $transaction['Tr_SlNo'];

            unset($transaction['Tr_SlNo']);

            $transaction['UpdateBy'] = $this->session->userdata('FullName');
            $transaction['UpdateTime'] = date('Y-m-d H:i:s');
            $this->db->where('Tr_SlNo', $tr_id)->update('tbl_vendor_bill_entry', $transaction);

            $this->db->query('delete from tbl_vendor_bill_details where vendor_bill_id = ?', $tr_id);
            foreach ($transactionObj->cart as $cartProduct) {
                $vebdorBillDetails = [
                    'vendor_bill_id' => $tr_id,
                    'item_code' => $cartProduct->item_code,
                    'mb_page_no' => $cartProduct->mb_page_no,
                    'visit_location' => $cartProduct->visit_location,
                    'visit_description' => $cartProduct->visit_description,
                    'vendor_unit' => $cartProduct->vendor_unit,
                    'estimated_quantity' => $cartProduct->estimated_quantity,
                    'vendor_rate' => $cartProduct->vendor_rate,
                    'status' => 'a',
                    'AddBy' => $this->session->userdata('FullName'),
                    'AddTime' => date('Y-m-d H:i:s'),
                    'vendor_bill_details_branchid' => $this->session->userdata('BRANCHid'),
                ];

                $this->db->insert('tbl_vendor_bill_details', $vebdorBillDetails);
            }

            $res = ['success' => true, 'message' => 'Vendor Bill Entry updated'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }
        echo json_encode($res);
    }

    public function deleteVendorBillEntry()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);
            $this->db->set(['status' => 'd'])->where('Tr_SlNo', $data->transactionId)->update('tbl_vendor_bill_entry');
            $res = ['success' => true, 'message' => 'Vendor Bill deleted'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function project_transaction()
    {

        $data['title'] = 'Client Transaction';
        $data['backend_content'] = 'account/project_transactions';
        $this->load->view('admin/layout', $data);
    }

    // Project Expense

    public function project_expense()
    {

        $data['title'] = 'Project Expense';
        $data['backend_content'] = 'account/project_expense';
        $this->load->view('admin/layout', $data);
    }

    public function getProjectType()
    {
        $this->db->query("
            select 
                t.*,
                e.code,
                e.name as assigned_person,
                e2.code as code2,
                e2.name as support_person,
                e3.code as code3,
                e3.name as assign_by_person,
                case t.status
                    when 'p' then 'Pending'
                    when 'o' then 'On Progress'
                    when 'c' then 'Completed'
                    when 'a' then 'Archived'
                end as status_text,
				c.client_name
            from tbl_task t
            where t.status != 'd'
            order by t.id desc
        ")->result();
    }

    public function fancybox_add_account()
    {
        $this->load->view('Administrator/ajax/fanceybox_add_account');
    }

    public function AccountType()
    {
        $acc_type = $this->input->post('acc_type');
        if ($acc_type == 'Customer') {
            $this->load->view('Administrator/ajax/transaction/customer');
        } elseif ($acc_type == 'Official') {
            $this->load->view('Administrator/ajax/transaction/Official');
        } elseif ($acc_type == 'Supplier') {
            $this->load->view('Administrator/ajax/transaction/Supplier');
        }
    }

    public function OnselectName()
    {
        $acc_type = $this->input->post('acc_type');
        $account_id = $this->input->post('account_id');
        if ($acc_type == 'Customer') {
            $query = "SELECT * from tbl_customer where Customer_SlNo = '$account_id'";
            $data['selected'] = $this->mt->edit_by_id($query);
            $this->load->view('Administrator/ajax/transaction/customer_name', $data);
        } elseif ($acc_type == 'Official') {
            $query = "SELECT * from tbl_account where Acc_SlNo = '$account_id'";
            $data['selected'] = $this->mt->edit_by_id($query);
            $this->load->view('Administrator/ajax/transaction/official_name', $data);
        } elseif ($acc_type == 'Supplier') {
            $query = "SELECT * from tbl_supplier where Supplier_SlNo = '$account_id'";
            $data['selected'] = $this->mt->edit_by_id($query);
            $this->load->view('Administrator/ajax/transaction/supplier_name', $data);
        }
    }

    public function AutoSelect()
    {
        $tr_type = $this->input->post('tr_type');
        if ($tr_type == 'Deposit To Bank' or $tr_type == 'Withdraw Form Bank') {
            $this->load->view('Administrator/ajax/transaction/Office_autoSelect');
        } else {
            $this->load->view('Administrator/ajax/transaction/Office_None_Select');
        }
    }

    public function fatch_all_account_id()
    {
        $data = $this->Other_model->get_all_account_info();
        echo json_encode($data);
    }

    public function fatch_account_id()
    {
        $id = $this->input->post('id');
        $attr = ['Acc_Tr_Type' => $id, 'status' => 'a'];
        $query = $this->db->get_where('tbl_account', $attr);
        $data = $query->result();
        echo json_encode($data);
    }

    public function vendorLedger()
    {
        // $access = $this->mt->userAccess();
        // if(!$access){
        //     redirect(base_url());
        // }
        // $data['title'] = "Vendor Ledger";
        // $data['content'] = $this->load->view("Administrator/account/cash_ledger", $data, true);
        // $this->load->view("Administrator/index", $data);

        $data['title'] = 'Vendor Ledger';
        $data['backend_content'] = 'account/vendor_ledger';
        $this->load->view('admin/layout', $data);
    }

    public function getProjectLedger()
    {
        $data = json_decode($this->input->raw_input_stream);
        $project_e_clause = '';
        if (isset($data->projectId) && $data->projectId != '') {
            $project_e_clause = "AND pe.projectId = '$data->projectId'";
        }

        $project_c_clause = '';
        if (isset($data->projectId) && $data->projectId != '') {
            $project_c_clause = "AND c.project_id = '$data->projectId'";
        }

        $billAmount = $this->db->query(
            "
        SELECT IFNULL(SUM(pb.bill_amount), 0) AS total_bill_amount 
        FROM tbl_project_bill_entry pb
        WHERE pb.status = 'a'".
                ($data->dateFrom == null ? '' : " AND pb.Tr_date < '".$data->dateFrom."'").
                ($data->projectId == null ? '' : " AND pb.Acc_SlID = '".$data->projectId."'")
        )->row();

        $ledger = $this->db->query("
        SELECT 
            pe.expense_id AS id,
            pe.expense_date AS date,
            CONCAT('Project Expense') AS description,
            pe.voucher_number AS client_voucher,
            pe.amount AS bill,
            pe.amount AS in_amount,
            0.00 AS out_amount
        FROM tbl_project_expense pe 
        LEFT JOIN tbl_project p ON p.id = pe.projectId
        WHERE pe.status = 1
        AND pe.expense_date BETWEEN '$data->dateFrom' AND '$data->dateTo'
        $project_e_clause

        UNION
        
        SELECT 
            ct.transaction_id AS id,
            ct.transaction_date AS date,
            CONCAT('Project Transaction') AS description,
            c.client_name AS client_voucher,
            ct.amount AS bill,
            ct.amount AS in_amount,
            0.00 AS out_amount
        FROM tbl_client_transactions ct
        LEFT JOIN tbl_client c ON c.id = ct.client_id
        WHERE ct.transaction_type = 'deposit'
        AND ct.Status = 1
        AND ct.transaction_date BETWEEN '$data->dateFrom' AND '$data->dateTo'
        $project_c_clause

        UNION

        SELECT 
            ct.transaction_id AS id,
            ct.transaction_date AS date,
            CONCAT('Project Transaction') AS description,
            c.client_name AS client_voucher,
            ct.amount AS bill,
            0.00 AS in_amount,
            ct.amount AS out_amount
        FROM tbl_client_transactions ct
        LEFT JOIN tbl_client c ON c.id = ct.client_id
        WHERE ct.transaction_type = 'withdraw'
        AND ct.Status = 1
        AND ct.transaction_date BETWEEN '$data->dateFrom' AND '$data->dateTo'
        $project_c_clause
    ")->result();

        $ledger = array_map(function ($ind, $row) use ($billAmount, $ledger) {
            $row->balance = (($ind == 0 ? $billAmount->total_bill_amount : $ledger[$ind - 1]->balance) + $row->in_amount) - $row->out_amount;

            return $row;
        }, array_keys($ledger), $ledger);

        $res['bill_amount'] = $billAmount;
        $res['ledger'] = $ledger;

        echo json_encode($res);
    }

    public function getMonthlyRevenueReport()
    {
        $data = json_decode($this->input->raw_input_stream);
        $project_e_clause = '';

        $project_c_clause = '';
        if (isset($data->clientId) && $data->clientId != '') {
            $project_c_clause = "AND t.client_list_id = '$data->clientId '";
        }

        $result = $this->db->query("
                select t.*,
                c.client_id,
                c.client_name,
                c.address as client_address,
                c.address,
                p.project_id,
                p.name as project_name,
                (SELECT IFNULL(SUM(ct.amount), 0) 
                FROM tbl_client_transactions ct 
                WHERE ct.client_id = t.client_list_id 
                ) AS total_client_amount,
                (SELECT IFNULL(SUM(ct.amount), 0) 
                FROM tbl_client_transactions ct 
                WHERE ct.client_id = t.client_list_id 
                and MONTH(ct.transaction_date) = MONTH(CURRENT_DATE())
                AND YEAR(ct.transaction_date) = YEAR(CURRENT_DATE())
                ) AS this_month_paid
                from tbl_task t 
                left join tbl_client c on c.id = t.client_list_id
                left join tbl_project p on p.id = t.projectId
                where t.status != 'd'
                $project_c_clause
        ")->result();

        echo json_encode($result);
    }

    public function getClientLedger()
    {
        $data = json_decode($this->input->raw_input_stream);
        $project_e_clause = '';

        $project_c_clause = '';
        if (isset($data->clientId) && $data->clientId != '') {
            $project_c_clause = "AND ct.client_id = '$data->clientId '";
        }

        $billAmount = $this->db->query(
            "
            SELECT IFNULL(SUM(t.bill_amount), 0) AS total_bill_amount 
            FROM tbl_task t
            WHERE t.status = 'a'".
                ($data->dateFrom == null ? '' : " AND t.entry_date < '".$data->dateFrom."'").
                ($data->clientId == null ? '' : " AND t.client_list_id = '".$data->clientId."'")
        )->row();

        $ledger = $this->db->query("
            
            select 
            ct.transaction_id as id,
            ct.transaction_date as date,
            ct.tr_number,
            c.client_name as description,
            c.client_id,
            ct.amount,
            ct.amount as in_amount,
            concat(p.project_id, ' - ', p.name) as display_name,
            t.file_number,
            t.bill_amount,
            0.00 as out_amount
            from tbl_client_transactions ct
            left join tbl_client c on c.id = ct.client_id
            left join tbl_project p on p.id = ct.project_id
			left join tbl_task t on t.id = ct.task_id
            where ct.transaction_type = 'deposit'
            and ct.Status = 1
            and ct.transaction_date between '$data->dateFrom' and '$data->dateTo'
            $project_c_clause

            UNION
            
            select 
            ct.transaction_id as id,
            ct.transaction_date as date,
            ct.tr_number,
            c.client_name  as description,
            c.client_id,
            ct.amount,
            0.00 in_amount,
            concat(p.project_id, ' - ', p.name) as display_name,
            t.file_number,
            t.bill_amount,
            ct.amount as out_amount
            from tbl_client_transactions ct
            left join tbl_client c on c.id = ct.client_id
            left join tbl_project p on p.id = ct.project_id
			left join tbl_task t on t.id = ct.task_id
            where ct.transaction_type = 'withdraw'
            and ct.Status = 1
            and ct.transaction_date between '$data->dateFrom' and '$data->dateTo'
            $project_c_clause

    ")->result();

        $ledger = array_map(function ($ind, $row) use ($billAmount, $ledger) {
            $row->balance = (($ind == 0 ? $billAmount->total_bill_amount : $ledger[$ind - 1]->balance) + $row->in_amount) - $row->out_amount;

            return $row;
        }, array_keys($ledger), $ledger);

        $res['bill_amount'] = $billAmount;
        $res['ledger'] = $ledger;

        echo json_encode($res);
    }
    // public function getClientLedger()
    // {
    //     $data = json_decode($this->input->raw_input_stream);
    //     $project_e_clause = "";

    //     $project_c_clause = "";
    //     if (isset($data->clientId) && $data->clientId != "") {
    //         $project_c_clause = "AND ct.client_id = '$data->clientId '";
    //     }

    //     $billAmount = $this->db->query(
    //         "
    //         SELECT IFNULL(SUM(t.bill_amount), 0) AS total_bill_amount
    //         FROM tbl_task t
    //         WHERE t.status = 'a'" .
    //             ($data->dateFrom == null ? "" : " AND t.entry_date < '" . $data->dateFrom . "'") .
    //             ($data->clientId == null ? "" : " AND t.client_list_id = '" . $data->clientId . "'")
    //     )->row();

    //     $ledger =  $this->db->query("

    //         select
    //         ct.transaction_id as id,
    //         ct.transaction_date as date,
    //         ct.tr_number,
    //         c.client_name as description,
    //         c.client_id,
    //         ct.amount,
    //         ct.amount as in_amount,
    //         concat(p.project_id, ' - ', p.name) as display_name,
    //         t.file_number,
    //         0.00 as out_amount
    //         from tbl_client_transactions ct
    //         left join tbl_client c on c.id = ct.client_id
    //         left join tbl_project p on p.id = ct.project_id
    // 		left join tbl_task t on t.id = ct.task_id
    //         where ct.transaction_type = 'deposit'
    //         and ct.Status = 1
    //         and ct.transaction_date between '$data->dateFrom' and '$data->dateTo'
    //         $project_c_clause

    //         UNION

    //         select
    //         ct.transaction_id as id,
    //         ct.transaction_date as date,
    //         ct.tr_number,
    //         c.client_name  as description,
    //         c.client_id,
    //         ct.amount,
    //         0.00 in_amount,
    //         concat(p.project_id, ' - ', p.name) as display_name,
    //         t.file_number,
    //         ct.amount as out_amount
    //         from tbl_client_transactions ct
    //         left join tbl_client c on c.id = ct.client_id
    //         left join tbl_project p on p.id = ct.project_id
    // 		left join tbl_task t on t.id = ct.task_id
    //         where ct.transaction_type = 'withdraw'
    //         and ct.Status = 1
    //         and ct.transaction_date between '$data->dateFrom' and '$data->dateTo'
    //         $project_c_clause

    // ")->result();

    //     $ledger = array_map(function ($ind, $row) use ($billAmount, $ledger) {
    //         $row->balance = (($ind == 0 ? $billAmount->total_bill_amount : $ledger[$ind - 1]->balance) + $row->in_amount) - $row->out_amount;
    //         return $row;
    //     }, array_keys($ledger), $ledger);

    //     $res['bill_amount'] = $billAmount;
    //     $res['ledger'] = $ledger;

    //     echo json_encode($res);
    // }

    public function getCashTransactions()
    {

        $data = json_decode($this->input->raw_input_stream);

        $dateClause = '';
        if (isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != '') {
            $dateClause = " and ct.Tr_date between '$data->dateFrom' and '$data->dateTo'";
        }

        $transactionTypeClause = '';
        if (isset($data->transactionType) && $data->transactionType != '' && $data->transactionType == 'received') {
            $transactionTypeClause = " and ct.Tr_Type = 'In Cash'";
        }
        if (isset($data->transactionType) && $data->transactionType != '' && $data->transactionType == 'paid') {
            $transactionTypeClause = " and ct.Tr_Type = 'Out Cash'";
        }

        $accountClause = '';
        if (isset($data->accountId) && $data->accountId != '') {
            $accountClause = " and ct.Acc_SlID = '$data->accountId'";
        }

        $trIdClause = '';
        if (isset($data->tr_id) && $data->tr_id != '') {
            $trIdClause = " and ct.Tr_Id = '$data->tr_id'";
        }

        $transactions = $this->db->query("
            select 
                ct.*,
                a.Acc_Name,
				a.client_id,
				a.Acc_Code
            from tbl_cashtransaction ct
            left join tbl_account a on a.Acc_SlNo = ct.Acc_SlID
            where ct.status = 'a'
            $dateClause $transactionTypeClause $accountClause $trIdClause
            order by ct.Tr_SlNo desc
        ")->result();

        echo json_encode($transactions);
    }

    public function getAllVendorExpenseTransactions()
    {

        $data = json_decode($this->input->raw_input_stream);
        $dateClause = '';
        if (isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != '') {
            $dateClause = " and ve.Tr_date between '$data->dateFrom' and '$data->dateTo'";
        }

        $transactionTypeClause = '';
        if (isset($data->transactionType) && $data->transactionType != '' && $data->transactionType == 'received') {
            $transactionTypeClause = " and ve.Tr_Type = 'In Cash'";
        }
        if (isset($data->transactionType) && $data->transactionType != '' && $data->transactionType == 'paid') {
            $transactionTypeClause = " and ve.Tr_Type = 'Out Cash'";
        }

        $accountClause = '';
        if (isset($data->accountId) && $data->accountId != '') {
            $accountClause = " and ve.vendor_id = '$data->accountId'";
        }

        $transactions = $this->db->query("
            select 
                ve.*,
                v.name
            from tbl_vendor_expense ve
            join tbl_vendor v on v.id = ve.vendor_id
            where ve.status = 'a'
            $dateClause $transactionTypeClause $accountClause
            order by ve.Tr_SlNo desc
        ")->result();
        echo json_encode($transactions);
    }

    public function getVendorLedger()
    {
        $data = json_decode($this->input->raw_input_stream);

        $clauses = '';
        if (isset($data->vendorId) && $data->vendorId != '') {
            $clauses .= " and ve.vendor_id = '$data->vendorId'";
        }

        if (isset($data->projectId) && $data->projectId != '') {
            $clauses .= " and ve.project_id = '$data->projectId'";
        }

        $billAmount = $this->mt->getVendorPriviousSummary($data->dateFrom, $clauses)->balance;
        $totalBill = $this->mt->getVendorBillPriviousSummary($data->dateFrom, $clauses)->balance;
        $totalPaid = $this->mt->getVendorPaidPriviousSummary($data->dateFrom, $clauses)->balance;

        $ledger = $this->db->query("
        select 
        ve.Tr_SlNo as id,
        ve.Tr_date as date,
        concat('Vendor Expense - ', ve.voucher_no, ' - ', ' - Bill: ', ve.In_Amount) as description,
        ve.voucher_no as slip_no,
        ve.tr_number as tr_no,
        ve.bill_number as bill_no,
        p.name as project_name,
        ve.In_Amount as in_amount,
        0.00 as out_amount
        from tbl_vendor_expense ve 
        left join tbl_vendor v on v.id = ve.vendor_id
        left join tbl_project p on p.id = ve.project_id
        where ve.status = 'a'
        and ve.Tr_Type = 'In Cash'
        and ve.Tr_date between '$data->dateFrom' and '$data->dateTo'
        $clauses
        
        UNION
        select 
        ve.Tr_SlNo as id,
        ve.Tr_date as date,
        concat('Vendor Expense - ', ve.voucher_no, ' - ', ' - Bill: ', ve.In_Amount) as description,
		ve.voucher_no as slip_no,
        ve.tr_number as tr_no,
        ve.bill_number as bill_no,
		p.name as project_name,
        0.00 as in_amount,
        ve.Out_Amount as out_amount
        from tbl_vendor_expense ve 
        left join tbl_vendor v on v.id = ve.vendor_id
		left join tbl_project p on p.id = ve.project_id
        where ve.status = 'a'
        and ve.Tr_Type = 'Out Cash'
        and ve.Tr_date between '$data->dateFrom' and '$data->dateTo'
        $clauses

        UNION
        select 
        ve.Tr_SlNo as id,
        ve.Tr_date as date,
        concat('Vendor Bill Entry - ', ve.bill_number, ' - ', ' - Bill: ',ve.bill_amount) as description,
		ve.voucher_no as slip_no,
        ve.Tr_Id as tr_no,
        ve.bill_number as bill_no,
		p.name as project_name,
        ve.bill_amount as in_amount,
        0.00 as out_amount
        from tbl_vendor_bill_entry ve 
        left join tbl_vendor v on v.id = ve.vendor_id
		left join tbl_project p on p.id = ve.project_id
        where ve.status = 'a'
        and ve.Tr_date between '$data->dateFrom' and '$data->dateTo'
        $clauses

   ")->result();

        $ledger = array_map(function ($ind, $row) use ($billAmount, $ledger) {
            $row->balance = (($ind == 0 ? $billAmount : $ledger[$ind - 1]->balance) + $row->in_amount) - $row->out_amount;

            return $row;
        }, array_keys($ledger), $ledger);
        $ledger = array_map(function ($ind2, $row) use ($totalBill, $ledger) {
            $row->totalBillAmount = $ind2 == 0 ? $totalBill : $ledger[$ind2 - 1]->totalBillAmount + $row->in_amount;

            return $row;
        }, array_keys($ledger), $ledger);
        $ledger = array_map(function ($ind3, $row) use ($totalPaid, $ledger) {
            $row->totalPaidAmount = $ind3 == 0 ? $totalPaid : $ledger[$ind3 - 1]->totalPaidAmount + $row->out_amount;

            return $row;
        }, array_keys($ledger), $ledger);

        $res['bill_amount'] = $billAmount;
        $res['totalBill'] = $totalBill;
        $res['totalPaid'] = $totalPaid;
        $res['ledger'] = $ledger;

        echo json_encode($res);
    }

    public function getCashTransactionCode()
    {
        echo json_encode($this->mt->generateCashTransactionCode());
    }

    public function getVendorExpenseTransactioncode()
    {
        echo json_encode($this->mt->generateVendorExpenseTransactioncode());
    }

    public function addCashTransaction()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $transactionObj = json_decode($this->input->raw_input_stream);
            $transaction = (array) $transactionObj;
            $transaction['status'] = 'a';
            $transaction['AddBy'] = $this->session->userdata('FullName');
            $transaction['AddTime'] = date('Y-m-d H:i:s');
            // $transaction['Tr_branchid'] = $this->session->userdata('BRANCHid');
            $this->db->insert('tbl_cashtransaction', $transaction);
            $res = ['success' => true, 'message' => 'Transaction added'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function updateCashTransaction()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $transactionObj = json_decode($this->input->raw_input_stream);

            $transaction = (array) $transactionObj;
            unset($transaction['Tr_SlNo']);
            $transaction['UpdateBy'] = $this->session->userdata('FullName');
            $transaction['UpdateTime'] = date('Y-m-d H:i:s');

            $this->db->where('Tr_SlNo', $transactionObj->Tr_SlNo)->update('tbl_cashtransaction', $transaction);

            $res = ['success' => true, 'message' => 'Transaction updated'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function deleteCashTransaction()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set(['status' => 'd'])->where('Tr_SlNo', $data->transactionId)->update('tbl_cashtransaction');

            $res = ['success' => true, 'message' => 'Transaction deleted'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function cash_transaction_edit()
    {
        $id = $this->input->post('edit');
        $query = $this->db->query("SELECT tbl_cashtransaction.*,tbl_account.*,tbl_bank.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID LEFT JOIN tbl_bank ON tbl_bank.Bank_SiNo=tbl_cashtransaction.Tr_Bank_Id where tbl_cashtransaction.Tr_SlNo = '$id'");
        $data['selected'] = $query->row();
        // $data['transaction'] = $this->Billing_model->select_all_transaction();
        $this->load->view('Administrator/edit/cash_transection_Edit', $data);
    }

    public function viewTransaction($id)
    {
        $query = $this->db->query("SELECT tbl_cashtransaction.*,tbl_account.*,tbl_bank.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID LEFT JOIN tbl_bank ON tbl_bank.Bank_SiNo=tbl_cashtransaction.Tr_Bank_Id where tbl_cashtransaction.Tr_SlNo = '$id'");
        $data['selected'] = $query->row();
        // echo "<pre>";print_r($data['selected']);exit;
        $this->load->view('Administrator/account/cash_transection_view', $data);
    }

    public function cash_transaction_delete()
    {
        $id = $this->input->post('deleted');
        $fld = 'Tr_SlNo';
        if ($this->mt->delete_data('tbl_cashtransaction', $id, $fld)) {
            $message = 'Delete Success';
            echo json_encode($message);
        }
    }

    public function cash_transaction_update()
    {
        $id = $this->input->post('id');
        $fld = 'Tr_SlNo';
        $atype = $this->input->post('acc_type');
        $TrType = $this->input->post('tr_type');

        /* if($atype=="Official" && $TrType=="Cash Receive"){
            $data = array(
                "Tr_Id"                 =>$this->input->post('Transaction_id', TRUE),
                "Tr_date"               =>$this->input->post('DaTe', TRUE),
                "Tr_Type"               =>$this->input->post('tr_type', TRUE),
                "Tr_account_Type"       =>$this->input->post('acc_type', TRUE),
                "Acc_SlID"              =>$this->input->post('account_id', TRUE),
                "Tr_Description"        =>$this->input->post('Description', TRUE),
                "In_Amount"             =>$this->input->post('Amount', TRUE),
                "Out_Amount"            =>0,
                "Tr_Bank_Id"			=>$this->input->post('Bank_id', TRUE),
                "ChequeNumber"			=>$this->input->post('ChequeNumber', TRUE),
                "UpdateBy"              =>$this->session->userdata("FullName"),
                "Tr_branchid"           =>$this->session->userdata("BRANCHid"),
                "UpdateTime"            =>date("Y-m-d H:i:s")
            );
        }
        elseif($atype=="Official" && $TrType=="Cash Payment"){
            $data = array(
                "Tr_Id"                 =>$this->input->post('Transaction_id', TRUE),
                "Tr_date"               =>$this->input->post('DaTe', TRUE),
                "Tr_Type"               =>$this->input->post('tr_type', TRUE),
                "Tr_account_Type"       =>$this->input->post('acc_type', TRUE),
                "Acc_SlID"              =>$this->input->post('account_id', TRUE),
                "Tr_Description"        =>$this->input->post('Description', TRUE),
                "In_Amount"             =>0,
                "Out_Amount"            =>$this->input->post('Amount', TRUE),
                "Tr_Bank_Id"			=>$this->input->post('Bank_id', TRUE),
                "ChequeNumber"			=>$this->input->post('ChequeNumber', TRUE),
                "UpdateBy"              =>$this->session->userdata("FullName"),
                "Tr_branchid"           =>$this->session->userdata("BRANCHid"),
                "UpdateTime"            =>date("Y-m-d H:i:s")
            );
        } */
        // elseif($atype=="Official" && $TrType=="Deposit To Bank"){
        if ($atype == 'Official' && $TrType == 'Deposit To Bank') {
            $data = [
                'Tr_Id' => $this->input->post('Transaction_id', true),
                'Tr_date' => $this->input->post('DaTe', true),
                'Tr_Type' => $this->input->post('tr_type', true),
                'Tr_account_Type' => $this->input->post('acc_type', true),
                'Acc_SlID' => $this->input->post('account_id', true),
                'Tr_Description' => $this->input->post('Description', true),
                'Out_Amount' => $this->input->post('Amount', true),
                'In_Amount' => 0,
                'Tr_Bank_Id' => $this->input->post('Bank_id', true),
                'ChequeNumber' => $this->input->post('ChequeNumber', true),
                'UpdateBy' => $this->session->userdata('FullName'),
                'Tr_branchid' => $this->session->userdata('BRANCHid'),
                'UpdateTime' => date('Y-m-d H:i:s'),
            ];
        } elseif ($atype == 'Official' && $TrType == 'Withdraw Form Bank') {
            $data = [
                'Tr_Id' => $this->input->post('Transaction_id', true),
                'Tr_date' => $this->input->post('DaTe', true),
                'Tr_Type' => $this->input->post('tr_type', true),
                'Tr_account_Type' => $this->input->post('acc_type', true),
                'Acc_SlID' => $this->input->post('account_id', true),
                'Tr_Description' => $this->input->post('Description', true),
                'In_Amount' => $this->input->post('Amount', true),
                'Out_Amount' => 0,
                'Tr_Bank_Id' => $this->input->post('Bank_id', true),
                'ChequeNumber' => $this->input->post('ChequeNumber', true),
                'UpdateBy' => $this->session->userdata('FullName'),
                'Tr_branchid' => $this->session->userdata('BRANCHid'),
                'UpdateTime' => date('Y-m-d H:i:s'),
            ];
        } elseif ($atype == 'Official' && $TrType == 'Out Cash') {
            $data = [
                'Tr_Id' => $this->input->post('Transaction_id', true),
                'Tr_date' => $this->input->post('DaTe', true),
                'Tr_Type' => $this->input->post('tr_type', true),
                'Tr_account_Type' => $this->input->post('acc_type', true),
                'Acc_SlID' => $this->input->post('account_id', true),
                'Tr_Description' => $this->input->post('Description', true),
                'In_Amount' => 0,
                'Out_Amount' => $this->input->post('Amount', true),
                'Tr_Bank_Id' => $this->input->post('Bank_id', true),
                'ChequeNumber' => $this->input->post('ChequeNumber', true),
                'UpdateBy' => $this->session->userdata('FullName'),
                'Tr_branchid' => $this->session->userdata('BRANCHid'),
                'UpdateTime' => date('Y-m-d H:i:s'),
            ];
        } elseif ($atype == 'Official' && $TrType == 'Income') {
            $data = [
                'Tr_Id' => $this->input->post('Transaction_id', true),
                'Tr_date' => $this->input->post('DaTe', true),
                'Tr_Type' => $this->input->post('tr_type', true),
                'Tr_account_Type' => $this->input->post('acc_type', true),
                'Acc_SlID' => $this->input->post('account_id', true),
                'Tr_Description' => $this->input->post('Description', true),
                'In_Amount' => $this->input->post('Amount', true),
                'Out_Amount' => 0,
                'Tr_Bank_Id' => $this->input->post('Bank_id', true),
                'ChequeNumber' => $this->input->post('ChequeNumber', true),
                'UpdateBy' => $this->session->userdata('FullName'),
                'Tr_branchid' => $this->session->userdata('BRANCHid'),
                'UpdateTime' => date('Y-m-d H:i:s'),
            ];
        }
        /*elseif($atype=="Supplier"){
            $data = array(
                "Tr_Id"                 =>$this->input->post('Transaction_id', TRUE),
                "Tr_date"               =>$this->input->post('DaTe', TRUE),
                "Tr_Type"               =>$this->input->post('tr_type', TRUE),
                "Tr_account_Type"       =>$this->input->post('acc_type', TRUE),
                "Supplier_SlID"         =>$this->input->post('account_id', TRUE),
                "Tr_Description"        =>$this->input->post('Description', TRUE),
                "Out_Amount"            =>$this->input->post('Amount', TRUE),
                "In_Amount"             =>0,
                "UpdateBy"              =>$this->session->userdata("FullName"),
                "UpdateTime"            =>date("Y-m-d H:i:s")
            );
        }*/

        if ($this->mt->update_data('tbl_cashtransaction', $data, $id, $fld)) {
            $message = 'Transaction Update Successful';
            echo json_encode($message);
        }
        // $this->load->view('Administrator/ajax/cash_transection', $data);
    }

    public function all_transaction_report()
    {
        $data['title'] = 'Cash Transaction Report';
        $data['backend_content'] = 'account/all_transaction_report';
        $this->load->view('admin/layout', $data);
    }

    // Monthly Report

    public function getClientCount()
    {

        $data = json_decode($this->input->raw_input_stream);
        $month = date('m');
        $year = date('Y'); // Use 'Y' for 4-digit year

        if ($data->date_from != '' && $data->date_to != '') {
            $count = $this->db->query("
        SELECT 
            COUNT(*) AS client_count
        FROM 
            tbl_client c
        WHERE c.status = 's'
        AND c.add_time BETWEEN '$data->date_from' and '$data->date_to'
    ")->row();
        } else {
            $count = $this->db->query("
			SELECT 
				COUNT(*) AS client_count
			FROM 
				tbl_client c
			WHERE c.status = 's'
			AND MONTH(c.add_time) = ?
			AND YEAR(c.add_time) = ?
		", [$month, $year])->row();
        }

        echo json_encode($count->client_count);
    }

    public function getReviewClientCount()
    {
        $data = json_decode($this->input->raw_input_stream);
        $month = date('m');
        $year = date('Y'); // Use 'Y' for 4-digit year

        if ($data->date_from != '' && $data->date_to != '') {
            $count = $this->db->query("
			SELECT 
				COUNT(*) AS review_client_count
			FROM 
				tbl_client c
			WHERE c.status = 's'
			and c.note != ''
			AND c.add_time BETWEEN '$data->date_from' and '$data->date_to'
		")->row();
            // Add this line to check the generated SQL query
        } else {
            $count = $this->db->query("
			SELECT 
				COUNT(*) AS review_client_count
			FROM 
				tbl_client c
			WHERE c.status = 's'
			and c.note != ''
			AND MONTH(c.add_time) = ?
			AND YEAR(c.add_time) = ?
		", [$month, $year])->row();
            // Add this line to check the generated SQL query
        }

        echo json_encode($count->review_client_count);
    }

    public function getNoReviewClientCount()
    {
        $data = json_decode($this->input->raw_input_stream);
        $month = date('m');
        $year = date('Y'); // Use 'Y' for 4-digit year

        if ($data->date_from != '' && $data->date_to != '') {
            $count = $this->db->query("
        SELECT 
            COUNT(*) AS no_review_client_count
        FROM 
            tbl_client c
        WHERE c.status = 's'
		and c.note = ''
        AND c.add_time BETWEEN '$data->date_from' and '$data->date_to'
    ")->row();
        } else {
            $count = $this->db->query("
        SELECT 
            COUNT(*) AS no_review_client_count
        FROM 
            tbl_client c
        WHERE c.status = 's'
		and c.note = ''
        AND MONTH(c.add_time) = ?
        AND YEAR(c.add_time) = ?
    ", [$month, $year])->row();
            // Add this line to check the generated SQL query
        }

        echo json_encode($count->no_review_client_count);
    }

    public function projectCount()
    {
        $data = json_decode($this->input->raw_input_stream);
        $month = date('m');
        $year = date('Y'); // Use 'Y' for 4-digit year

        if ($data->date_from != '' && $data->date_to != '') {
            $count = $this->db->query("
			SELECT 
				COUNT(*) AS project_count
			FROM 
				tbl_project p
			WHERE p.status = 'a'
			AND p.add_time BETWEEN '$data->date_from' and '$data->date_to'
		")->row();
        } else {
            $count = $this->db->query("
			SELECT 
				COUNT(*) AS project_count
			FROM 
				tbl_project p
			WHERE p.status = 'a'
			AND MONTH(p.add_time) = ?
			AND YEAR(p.add_time) = ?
		", [$month, $year])->row();
        }

        echo json_encode($count->project_count);
    }

    public function projectOrderValue()
    {
        $month = date('m');
        $year = date('Y'); // Use 'Y' for 4-digit year
        $data = json_decode($this->input->raw_input_stream);
        if ($data->date_from != '' && $data->date_to != '') {
            $bill = $this->db->query("
			 select t.*,
			ifnull(sum(t.bill_amount), 0) as total_bill
			from tbl_task t 
			where t.status != 'd'
			and t.entry_date BETWEEN '$data->date_from' and '$data->date_to'
		")->row();
        } else {
            $bill = $this->db->query("
			 select t.*,
			ifnull(sum(t.bill_amount), 0) as total_bill
			from tbl_task t 
			where t.status != 'd'
			and month(t.entry_date) = ?
			and year(t.entry_date) = ?
		", [$month, $year])->row();
        }

        echo json_encode($bill->total_bill);
    }

    public function monthlyReport()
    {
        $data['title'] = 'Monthly Report';
        $data['backend_content'] = 'account/monthly_report';
        $this->load->view('admin/layout', $data);
    }

    public function transaction_report_search()
    {
        $dAta['startdate'] = $startdate = $this->input->post('startdate');
        $dAta['enddate'] = $enddate = $this->input->post('enddate');
        $dAta['accountid'] = $accountid = $this->input->post('accountid');
        $dAta['searchtype'] = $searchtype = $this->input->post('searchtype');
        $this->session->set_userdata($dAta);
        $BRANCHid = $this->session->userdata('BRANCHid');

        if ($searchtype == 'All') {
            if ($accountid == 'All') {
                $result = $this->Other_model->transaction_account_all('A');
            } else {
                $result = $this->Other_model->transaction_by_account('A', $accountid);
            }
        } elseif ($searchtype == 'Received') {
            if ($accountid == 'All') {
                $result = $this->Other_model->transaction_account_all('R');
            } else {
                $result = $this->Other_model->transaction_by_account('R', $accountid);
            }
        } else {
            if ($accountid == 'All') {
                $result = $this->Other_model->transaction_account_all('P');
            } else {
                $result = $this->Other_model->transaction_by_account('P', $accountid);
            }
        }

        $datas['record'] = $result;

        $this->load->view('Administrator/account/transaction_report_list', $datas);
    }

    public function deposit()
    {
        $data['title'] = 'Deposit Information';
        $data['content'] = $this->load->view('Administrator/account/deposit_report', $data, true);
        $this->load->view('admin/layout', $data);
    }

    public function deposit_search()
    {
        $dAta['startdate'] = $startdate = $this->input->post('startdate');
        $dAta['enddate'] = $enddate = $this->input->post('enddate');
        $dAta['accountid'] = $accountid = $this->input->post('accountid');
        $dAta['searchtype'] = $searchtype = $this->input->post('searchtype');
        $this->session->set_userdata($dAta);
        $BRANCHid = $this->session->userdata('BRANCHid');

        if ($searchtype == 'All') {
            $sql = "SELECT tbl_cashtransaction.*,tbl_account.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID where tbl_cashtransaction.Tr_branchid='$BRANCHid' AND tbl_cashtransaction.Tr_Type='Deposit To Bank' AND tbl_cashtransaction.Tr_date between '$startdate' AND '$enddate'";
        } elseif ($searchtype == 'Account') {
            // $sql = "SELECT tbl_cashtransaction.*,tbl_account.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID where tbl_cashtransaction.Acc_SlID ='$accountid ' AND tbl_cashtransaction.Tr_branchid='$BRANCHid' AND tbl_cashtransaction.Tr_date between '$expence_startdate' and '$expence_enddate'";
            $sql = "SELECT tbl_cashtransaction.*,tbl_account.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID where tbl_cashtransaction.Acc_SlID ='$accountid ' AND tbl_cashtransaction.Tr_branchid='$BRANCHid' AND tbl_cashtransaction.Tr_Type='Deposit To Bank' AND tbl_cashtransaction.Tr_date between '$startdate' AND '$enddate'";
        }
        $query = $this->db->query($sql);
        $datas['record'] = $query->result();

        $this->load->view('Administrator/account/deposit_search_list', $datas);
    }

    public function withdraw()
    {
        $data['title'] = 'Withdraw Information';
        $data['content'] = $this->load->view('Administrator/account/withdraw_report', $data, true);
        $this->load->view('admin/layout', $data);
    }

    public function withdraw_search()
    {
        $dAta['startdate'] = $startdate = $this->input->post('startdate');
        $dAta['enddate'] = $enddate = $this->input->post('enddate');
        $dAta['accountid'] = $accountid = $this->input->post('accountid');
        $dAta['searchtype'] = $searchtype = $this->input->post('searchtype');
        $this->session->set_userdata($dAta);
        $BRANCHid = $this->session->userdata('BRANCHid');

        if ($searchtype == 'All') {
            $sql = "SELECT tbl_cashtransaction.*,tbl_account.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID where tbl_cashtransaction.Tr_branchid='$BRANCHid' AND tbl_cashtransaction.Tr_Type='Withdraw Form Bank' AND tbl_cashtransaction.Tr_date between '$startdate' AND '$enddate'";
        } elseif ($searchtype == 'Account') {
            $sql = "SELECT tbl_cashtransaction.*,tbl_account.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID where tbl_cashtransaction.Acc_SlID ='$accountid ' AND tbl_cashtransaction.Tr_branchid='$BRANCHid' AND tbl_cashtransaction.Tr_Type='Withdraw Form Bank' AND tbl_cashtransaction.Tr_date between '$startdate' AND '$enddate'";
        }
        $query = $this->db->query($sql);
        $datas['record'] = $query->result();

        $this->load->view('Administrator/account/withdraw_search_list', $datas);
    }

    public function expense()
    {
        $data['title'] = 'Out Cash Information';
        $data['content'] = $this->load->view('Administrator/account/expense_report', $data, true);
        $this->load->view('admin/layout', $data);
    }

    public function expense_search()
    {
        $dAta['startdate'] = $startdate = $this->input->post('startdate');
        $dAta['enddate'] = $enddate = $this->input->post('enddate');
        $dAta['accountid'] = $accountid = $this->input->post('accountid');
        $dAta['searchtype'] = $searchtype = $this->input->post('searchtype');
        $this->session->set_userdata($dAta);
        $BRANCHid = $this->session->userdata('BRANCHid');

        if ($searchtype == 'All') {
            $sql = "SELECT tbl_cashtransaction.*,tbl_account.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID where tbl_cashtransaction.Tr_branchid='$BRANCHid' AND tbl_cashtransaction.Tr_Type='Out Cash' AND tbl_cashtransaction.Tr_date between '$startdate' AND '$enddate'";
        } elseif ($searchtype == 'Account') {
            $sql = "SELECT tbl_cashtransaction.*,tbl_account.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID where tbl_cashtransaction.Acc_SlID ='$accountid ' AND tbl_cashtransaction.Tr_branchid='$BRANCHid' AND tbl_cashtransaction.Tr_Type='Out Cash' AND tbl_cashtransaction.Tr_date between '$startdate' AND '$enddate'";
        }
        $query = $this->db->query($sql);
        $datas['record'] = $query->result();

        $this->load->view('Administrator/account/expense_search_list', $datas);
    }

    public function getOtherIncomeExpense()
    {
        $data = json_decode($this->input->raw_input_stream);

        $transactionDateClause = '';
        $employePaymentDateClause = '';
        $profitDistributeDateClause = '';
        $loanInterestDateClause = '';
        $assetsSalesDateClause = '';
        $damageClause = '';
        $returnClause = '';
        $purchaseClause = '';
        if (isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != '') {
            $transactionDateClause = " and ct.Tr_date between '$data->dateFrom' and '$data->dateTo'";
            $employePaymentDateClause = " and ep.payment_date between '$data->dateFrom' and '$data->dateTo'";
            $profitDistributeDateClause = " and it.transaction_date between '$data->dateFrom' and '$data->dateTo'";
            $loanInterestDateClause = " and lt.transaction_date between '$data->dateFrom' and '$data->dateTo'";
            $assetsSalesDateClause = " and a.as_date between '$data->dateFrom' and '$data->dateTo'";
            $damageClause = " and d.Damage_Date between '$data->dateFrom' and '$data->dateTo'";
            $returnClause = " and r.SaleReturn_ReturnDate between '$data->dateFrom' and '$data->dateTo'";
            $purchaseClause = " and pm.PurchaseMaster_OrderDate between '$data->dateFrom' and '$data->dateTo'";
        }

        $result = $this->db->query("
            select
            (
                select ifnull(sum(ct.In_Amount), 0)
                from tbl_cashtransaction ct
                where ct.Tr_branchid = '".$this->session->userdata('BRANCHid')."'
                and ct.status = 'a'
                $transactionDateClause
            ) as income,
        
            (
                select ifnull(sum(ct.Out_Amount), 0)
                from tbl_cashtransaction ct
                where ct.Tr_branchid = '".$this->session->userdata('BRANCHid')."'
                and ct.status = 'a'
                $transactionDateClause
            ) as expense,
        
            (
                select ifnull(sum(ep.total_payment_amount), 0)
                from tbl_employee_payment ep
                where ep.branch_id = '".$this->session->userdata('BRANCHid')."'
                and ep.status = 'a'
                $employePaymentDateClause
            ) as employee_payment,

            (
                select ifnull(sum(it.amount), 0)
                from tbl_investment_transactions it
                where it.branch_id = '".$this->session->userdata('BRANCHid')."'
                and it.transaction_type = 'Profit'
                and it.status = 1
                $profitDistributeDateClause
            ) as profit_distribute,

            (
                select ifnull(sum(lt.amount), 0)
                from tbl_loan_transactions lt
                where lt.branch_id = '".$this->session->userdata('BRANCHid')."'
                and lt.transaction_type = 'Interest'
                and lt.status = 1
                $loanInterestDateClause
            ) as loan_interest,

            (
                select ifnull(sum(a.valuation - a.as_amount), 0)
                from tbl_assets a
                where a.branchid = '".$this->session->userdata('BRANCHid')."'
                and a.buy_or_sale = 'sale'
                and a.status = 'a'
                $assetsSalesDateClause
            ) as assets_sales_profit_loss,

            (
                select ifnull(sum(pm.PurchaseMaster_DiscountAmount), 0) 
                from tbl_purchasemaster pm
                where pm.PurchaseMaster_BranchID = '".$this->session->userdata('BRANCHid')."'
                and pm.status = 'a'
                $purchaseClause
            ) as purchase_discount,
            
            (
                select ifnull(sum(pm.PurchaseMaster_Tax), 0) 
                from tbl_purchasemaster pm
                where pm.PurchaseMaster_BranchID = '".$this->session->userdata('BRANCHid')."'
                and pm.status = 'a'
                $purchaseClause
            ) as purchase_vat,
            
            (
                select ifnull(sum(pm.PurchaseMaster_Freight), 0) 
                from tbl_purchasemaster pm
                where pm.PurchaseMaster_BranchID = '".$this->session->userdata('BRANCHid')."'
                and pm.status = 'a'
                $purchaseClause
            ) as purchase_transport_cost,
            
            (
                select ifnull(sum(dd.damage_amount), 0) 
                from tbl_damagedetails dd
                join tbl_damage d on d.Damage_SlNo = dd.Damage_SlNo
                where d.Damage_brunchid = '".$this->session->userdata('BRANCHid')."'
                and dd.status = 'a'
                $damageClause
            ) as damaged_amount,

            (
                select ifnull(sum(rd.SaleReturnDetails_ReturnAmount) - sum(sd.Purchase_Rate * rd.SaleReturnDetails_ReturnQuantity), 0)
                from tbl_salereturndetails rd
                join tbl_salereturn r on r.SaleReturn_SlNo = rd.SaleReturn_IdNo
                join tbl_salesmaster sm on sm.SaleMaster_InvoiceNo = r.SaleMaster_InvoiceNo
                join tbl_saledetails sd on sd.Product_IDNo = rd.SaleReturnDetailsProduct_SlNo and sd.SaleMaster_IDNo = sm.SaleMaster_SlNo
                where r.Status = 'a'
                and r.SaleReturn_brunchId = '".$this->session->userdata('BRANCHid')."'
                $returnClause
            ) as returned_amount
        ")->row();

        echo json_encode($result);
    }

    public function income()
    {
        $data['title'] = 'Income Information';
        $data['content'] = $this->load->view('Administrator/account/income_report', $data, true);
        $this->load->view('admin/layout', $data);
    }

    public function income_search()
    {
        $dAta['startdate'] = $startdate = $this->input->post('startdate');
        $dAta['enddate'] = $enddate = $this->input->post('enddate');
        $dAta['accountid'] = $accountid = $this->input->post('accountid');
        $dAta['searchtype'] = $searchtype = $this->input->post('searchtype');
        $this->session->set_userdata($dAta);
        $BRANCHid = $this->session->userdata('BRANCHid');

        if ($searchtype == 'All') {
            $sql = "SELECT tbl_cashtransaction.*,tbl_account.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID where tbl_cashtransaction.Tr_branchid='$BRANCHid' AND tbl_cashtransaction.Tr_Type='In Cash' AND tbl_cashtransaction.Tr_date between '$startdate' AND '$enddate'";
        } elseif ($searchtype == 'Account') {
            $sql = "SELECT tbl_cashtransaction.*,tbl_account.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID where tbl_cashtransaction.Acc_SlID ='$accountid ' AND tbl_cashtransaction.Tr_branchid='$BRANCHid' AND tbl_cashtransaction.Tr_Type='In Cash' AND tbl_cashtransaction.Tr_date between '$startdate' AND '$enddate'";
        }
        $query = $this->db->query($sql);
        $datas['record'] = $query->result();

        $this->load->view('Administrator/account/income_search_list', $datas);
    }

    public function cash_view()
    {
        $access = $this->mt->userAccess();
        if (! $access) {
            redirect(base_url());
        }
        $data['title'] = 'Cash View';
        $sql = $this->db->query('SELECT tbl_cashtransaction.*,tbl_account.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID');
        $data['record'] = $sql->result();
        $data['content'] = $this->load->view('Administrator/account/cashview_search_list', $data, true);
        $this->load->view('admin/layout', $data);
    }

    public function daily_cash_view()
    {
        $data['title'] = 'Daily Cash View';
        $userBranch = $this->session->userdata('BRANCHid');
        $sql = "SELECT tbl_cashtransaction.*,tbl_account.* FROM tbl_cashtransaction left join tbl_account on tbl_account.Acc_SlNo=tbl_cashtransaction.Acc_SlID WHERE tbl_cashtransaction.Tr_branchid = '$userBranch' AND tbl_cashtransaction.Tr_date = CURDATE()";
        $data['record'] = $this->mt->ccdata($sql);
        $data['content'] = $this->load->view('Administrator/account/daily_cash_view', $data, true);
        $this->load->view('admin/layout', $data);
    }

    public function datewise_cash_view()
    {
        $data['title'] = 'Datewise Cash View';
        $data['content'] = $this->load->view('Administrator/account/datewise_cash_view', $data, true);
        $this->load->view('admin/layout', $data);
    }

    public function datewise_cash_view_search()
    {
        if ($this->input->post('BranchID', true)) {
            $data['BranchID'] = $this->input->post('BranchID', true);
        } else {
            $data['BranchID'] = $this->session->userdata('BRANCHid');
        }

        $data['CDate'] = $this->input->post('CDate', true);
        $this->session->set_userdata($data);
        $this->load->view('Administrator/account/datewise_cash_view_search', $data);
    }

    // ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
    public function add_bank()
    {
        $data['title'] = 'Add Bank';
        $data['bank'] = $this->Billing_model->select_bank();
        $data['content'] = $this->load->view('Administrator/account/add_bank', $data, true);
        $this->load->view('admin/layout', $data);
    }

    // CREATE TABLE IF NOT EXISTS `tbl_Bank` (
    //   `Bank_SiNo` int(11) NOT NULL AUTO_INCREMENT,
    //   `Bank_name` varchar(100) NOT NULL,
    //   `Branch` varchar(100) NOT NULL,
    //   `Account_Title` varchar(100) NOT NULL,
    //   `Account_No` varchar(100) NOT NULL,
    //   PRIMARY KEY (`Bank_SiNo`)
    // ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

    public function insert_Bank()
    {
        $Bank_name = $this->input->post('Bank_name');
        $Branch = $this->input->post('Branch');
        $Account_Title = $this->input->post('Account_Title');
        $Account_No = $this->input->post('Account_No');
        $query = $this->db->query("SELECT Bank_name from tbl_bank where Bank_name = '$Bank_name'");

        if ($query->num_rows() > 0) {
            // echo "F";
            // $this->load->view('ajax/Country');
            $message = 'This bank name already exists';
            echo json_encode($message);
            // $this->session->set_userdata($sdata);
            // redirect('Administrator/Page/add_bank');
        } else {
            $data = [
                'Bank_name' => $Bank_name,
                'Branch' => $this->brunch,
            ];
            if ($this->mt->save_data('tbl_bank', $data)) {
                $message = 'Add bank success';
                echo json_encode($message);
            }
            // redirect('Administrator/Page/add_bank');
            // $this->load->view('ajax/add_bank');
        }
    }

    public function fancybox_add_bank()
    {
        $this->load->view('Administrator/account/fancybox_add_bank');
    }

    public function fancyBox_insert_Bank()
    {
        $Bank = $this->input->post('Bank');
        $query = $this->db->query("SELECT Bank_name from tbl_Bank where Bank_name = '$Bank'");

        if ($query->num_rows() > 0) {
            echo 'F';
        } else {
            $data = [
                'Bank_name' => $Bank,
            ];
            $this->mt->save_data('tbl_Bank', $data);
            $this->load->view('Administrator/account/fancybox_select_add_bank');
        }
    }

    public function Bankdelete()
    {
        $id = $this->input->post('deleted');
        $fld = 'Bank_SiNo';
        $this->mt->delete_data('tbl_Bank', $id, $fld);
        echo 'Success';
    }

    public function Bankedit($id)
    {
        $data['title'] = 'Edit Bank';
        $fld = 'Bank_SiNo';
        $data['bank'] = $this->Billing_model->select_bank();
        $data['selected'] = $this->Billing_model->select_by_id('tbl_Bank', $id, $fld);
        $data['content'] = $this->load->view('Administrator/edit/edit_Bank', $data, true);
        $this->load->view('admin/layout', $data);
    }

    public function Update_Bank()
    {
        $Bank_SiNo = $this->input->post('Bank_SiNo');
        $Bank_name = $this->input->post('Bank_name');
        $Branch = $this->input->post('Branch');
        $Account_Title = $this->input->post('Account_Title');
        $Account_No = $this->input->post('Account_No');
        // $query = $this->db->query("SELECT Bank_name from tbl_Bank where Bank_name = '$Bank_name'");
        $query = $this->db->query("SELECT Bank_name from tbl_Bank where Bank_SiNo = '$Bank_SiNo'");

        if ($query->num_rows() > 1) {
            echo 'F';
        } else {
            $fld = 'Bank_SiNo';
            $data = [
                'Bank_name' => $Bank_name,
                'Branch' => $this->brunch,
            ];
            $this->mt->update_data('tbl_Bank', $data, $Bank_SiNo, $fld);
            echo 'Success';
        }
    }

    public function bankAccounts()
    {

        $data['title'] = 'Bank Accounts';
        $data['backend_content'] = '/account/bank_accounts';
        $this->load->view('admin/layout', $data);
    }

    public function addBankAccount()
    {

        $res = ['success' => false, 'message' => ''];
        try {

            $data = json_decode($this->input->raw_input_stream);

            $accountCheck = $this->db->query('
                select
                *
                from tbl_bank_accounts
                where account_number = ?
            ', $data->account_number)->num_rows();

            if ($accountCheck != 0) {
                $res = ['success' => false, 'message' => 'Account number already exists'];
                echo json_encode($res);
                exit;
            }

            $account = (array) $data;
            $account['saved_by'] = $this->session->userdata('userId');
            $account['saved_datetime'] = date('Y-m-d H:i:s');
            // $account['branch_id'] = $this->session->userdata('BRANCHid');

            $this->db->insert('tbl_bank_accounts', $account);
            $res = ['success' => true, 'message' => 'Account created successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function updateBankAccount()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $accountCheck = $this->db->query('
                select
                *
                from tbl_bank_accounts
                where account_number = ?
                and account_id != ?
            ', [$data->account_number, $data->account_id])->num_rows();

            if ($accountCheck != 0) {
                $res = ['success' => false, 'message' => 'Account number already exists'];
                echo json_encode($res);
                exit;
            }

            $account = (array) $data;
            $account['updated_by'] = $this->session->userdata('userId');
            $account['updated_datetime'] = date('Y-m-d H:i:s');

            $this->db->where('account_id', $data->account_id);
            $this->db->update('tbl_bank_accounts', $account);
            $res = ['success' => true, 'message' => 'Account updated successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function getBankAccounts()
    {
        $accounts = $this->db->query("
            select 
            *,
            case status 
            when 1 then 'Active'
            else 'Inactive'
            end as status_text
            from tbl_bank_accounts 
        ")->result();
        echo json_encode($accounts);
    }

    public function changeAccountStatus()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);
            $status = $data->account->status == 1 ? 0 : 1;
            $this->db->query('update tbl_bank_accounts set status = ? where account_id = ?', [$status, $data->account->account_id]);

            $res = ['success' => true, 'message' => 'Status Changed'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function bankTransactions()
    {

        $data['title'] = 'Bank Transactions';
        $data['backend_content'] = 'account/bank_transactions';
        $this->load->view('admin/layout', $data);
    }

    public function addBankTransaction()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $transaction = (array) $data;
            $transaction['saved_by'] = $this->session->userdata('userId');
            $transaction['saved_datetime'] = date('Y-m-d H:i:s');
            $this->db->insert('tbl_bank_transactions', $transaction);

            $res = ['success' => true, 'message' => 'Transaction added successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function addClientTransaction()
    {
        $res = ['success' => false, 'message' => ''];
        try {

            $data = json_decode($this->input->raw_input_stream);
            $transaction = (array) $data;
            $transaction['saved_by'] = $this->session->userdata('userId');
            $transaction['saved_datetime'] = date('Y-m-d H:i:s');
            $this->db->insert('tbl_client_transactions', $transaction);

            $res = ['success' => true, 'message' => 'Client transaction added successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function updateClientTransaction()
    {

        $res = ['success' => false, 'message' => ''];
        try {

            $data = json_decode($this->input->raw_input_stream);
            $transactionId = $data->transaction_id;
            $transaction = (array) $data;
            unset($transaction['transaction_id']);

            $this->db->where('transaction_id', $transactionId)->update('tbl_client_transactions', $transaction);

            $res = ['success' => true, 'message' => 'Client transaction update successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function addProjectExpense()
    {
        $res = ['success' => false, 'message' => ''];
        try {

            $data = json_decode($this->input->raw_input_stream);
            $transaction = (array) $data;
            $transaction['saved_by'] = $this->session->userdata('userId');
            $transaction['saved_datetime'] = date('Y-m-d H:i:s');
            $this->db->insert('tbl_project_expense', $transaction);

            $res = ['success' => true, 'message' => 'Project Expense added successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function updateProjectExpense()
    {
        $res = ['success' => false, 'message' => ''];
        try {

            $data = json_decode($this->input->raw_input_stream);
            $expense_id = $data->expense_id;
            $transaction = (array) $data;
            unset($transaction['expense_id']);
            $transaction['update_by'] = $this->session->userdata('userId');
            $transaction['update_datetime'] = date('Y-m-d H:i:s');
            $this->db->where('expense_id', $expense_id)->update('tbl_project_expense', $transaction);
            $res = ['success' => true, 'message' => 'Project Expense updated successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function getProjectExpense()
    {
        $data = $this->db->query("
			select pe.*,
			p.name as project_name,
			pt.name as project_type
			from tbl_project_expense pe
			left join tbl_type pt on pt.id = pe.project_type_id
			left join tbl_project p on p.id = pe.projectId
			where pe.status != 'd'
		")->result();

        echo json_encode($data);
    }

    public function updateBankTransaction()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);
            $transactionId = $data->transaction_id;
            $transaction = (array) $data;
            unset($transaction['transaction_id']);

            $this->db->where('transaction_id', $transactionId)->update('tbl_bank_transactions', $transaction);

            $res = ['success' => true, 'message' => 'Transaction update successfully'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function getBankTransactions()
    {

        $data = json_decode($this->input->raw_input_stream);

        $accountClause = '';

        if (isset($data->accountId) && $data->accountId != null) {
            $accountClause = " and bt.account_id = '$data->accountId'";
        }

        $dateClause = '';
        if (
            isset($data->dateFrom) && $data->dateFrom != ''
            && isset($data->dateTo) && $data->dateTo != ''
        ) {
            $dateClause = " and bt.transaction_date between '$data->dateFrom' and '$data->dateTo'";
        }

        $typeClause = '';
        if (isset($data->transactionType) && $data->transactionType != '') {
            $typeClause = " and bt.transaction_type = '$data->transactionType'";
        }

        $transactions = $this->db->query("
				select 
					bt.*,
					ac.account_name,
					ac.account_number,
					ac.bank_name,
					ac.branch_name
				from tbl_bank_transactions bt
				join tbl_bank_accounts ac on ac.account_id = bt.account_id
				where bt.status = 1
				$accountClause $dateClause $typeClause
				order by bt.transaction_id desc
			")->result();

        echo json_encode($transactions);
    }

    public function getClientTransactions()
    {

        $data = json_decode($this->input->raw_input_stream);

        $accountClause = '';

        if (isset($data->clientId) && $data->clientId != null) {
            $accountClause = " and ct.client_id = '$data->clientId'";
        }

        $dateClause = '';
        if (
            isset($data->dateFrom) && $data->dateFrom != ''
            && isset($data->dateTo) && $data->dateTo != ''
        ) {
            $dateClause = " and ct.transaction_date between '$data->dateFrom' and '$data->dateTo'";
        }

        $typeClause = '';

        if (isset($data->transactionType) && $data->transactionType != '') {
            $typeClause = " and ct.transaction_type = '$data->transactionType'";
        }

        $transactions = $this->db->query("
				select 
					ct.*,
					c.client_name,
					concat(p.project_id, ' - ', p.name) as display_name,
                    t.file_number,
					ctp.name
				from tbl_client_transactions ct
				left join tbl_client c on c.id = ct.client_id
				left join tbl_project p on p.id = ct.project_id
				left join tbl_task t on t.id = ct.task_id
				left join tbl_clienttype ctp on ctp.id = c.client_type_id
				where ct.status = 1
				$accountClause $dateClause $typeClause
				order by ct.transaction_id desc
			")->result();

        echo json_encode($transactions);
    }

    public function getAllBankTransactions()
    {
        $data = json_decode($this->input->raw_input_stream);

        $clauses = '';
        $order = 'transaction_date desc, sequence, id desc';

        if (isset($data->accountId) && $data->accountId != null) {
            $clauses .= " and account_id = '$data->accountId'";
        }

        if (
            isset($data->dateFrom) && $data->dateFrom != ''
            && isset($data->dateTo) && $data->dateTo != ''
        ) {
            $clauses .= " and transaction_date between '$data->dateFrom' and '$data->dateTo'";
        }

        if (isset($data->transactionType) && $data->transactionType != '') {
            $clauses .= " and transaction_type = '$data->transactionType'";
        }

        if (isset($data->ledger)) {
            $order = 'transaction_date, sequence, id';
        }

        $transactions = $this->db->query("
				select * from(
					select 
						'a' as sequence,
						bt.transaction_id as id,
						bt.transaction_type as description,
						bt.account_id,
						bt.transaction_date,
						bt.transaction_type,
						bt.amount as deposit,
						0.00 as withdraw,
						bt.note,
						ac.account_name,
						ac.account_number,
						ac.bank_name,
						ac.branch_name,
						0.00 as balance
					from tbl_bank_transactions bt
					join tbl_bank_accounts ac on ac.account_id = bt.account_id
					where bt.status = 1
					and bt.transaction_type = 'deposit'

					UNION
					select 
						'b' as sequence,
						bt.transaction_id as id,
						bt.transaction_type as description,
						bt.account_id,
						bt.transaction_date,
						bt.transaction_type,
						0.00 as deposit,
						bt.amount as withdraw,
						bt.note,
						ac.account_name,
						ac.account_number,
						ac.bank_name,
						ac.branch_name,
						0.00 as balance
					from tbl_bank_transactions bt
					join tbl_bank_accounts ac on ac.account_id = bt.account_id
					where bt.status = 1
					and bt.transaction_type = 'withdraw'
		
				) as tbl
				where 1 = 1 $clauses
				order by $order
			")->result();

        if (! isset($data->ledger)) {
            echo json_encode($transactions);
            exit;
        }

        $previousBalance = $this->mt->getBankTransactionSummary($data->accountId, $data->dateFrom)[0]->balance;

        $transactions = array_map(function ($key, $trn) use ($previousBalance, $transactions) {
            $trn->balance = (($key == 0 ? $previousBalance : $transactions[$key - 1]->balance) + $trn->deposit) - $trn->withdraw;

            return $trn;
        }, array_keys($transactions), $transactions);

        $res['previousBalance'] = $previousBalance;
        $res['transactions'] = $transactions;

        echo json_encode($res);
    }

    public function getAllClientTransactions()
    {
        $data = json_decode($this->input->raw_input_stream);

        $clauses = '';
        $order = 'transaction_date desc, sequence, id desc';

        if (isset($data->clientId) && $data->clientId != null) {
            $clauses .= " and client_id = '$data->clientId'";
        }

        if (
            isset($data->dateFrom) && $data->dateFrom != ''
            && isset($data->dateTo) && $data->dateTo != ''
        ) {
            $clauses .= " and transaction_date between '$data->dateFrom' and '$data->dateTo'";
        }

        if (isset($data->transactionType) && $data->transactionType != '') {
            $clauses .= " and transaction_type = '$data->transactionType'";
        }

        if (isset($data->ledger)) {
            $order = 'transaction_date, sequence, id';
        }

        $transactions = $this->db->query("
				select * from(
					select 
						'a' as sequence,
						ct.transaction_id as id,
						ct.transaction_type as description,
						ct.client_id,
						ct.transaction_date,
						ct.transaction_type,
						ct.amount as deposit,
                        concat(p.project_id, ' - ', p.name) as display_name,
                         t.file_number,
						0.00 as withdraw,
						ct.note,
						c.client_name,
						c.phone,
						ctt.name,
						0.00 as balance
					from tbl_client_transactions ct
					left join tbl_client c on c.id = ct.client_id
                    left join tbl_project p on p.id = ct.project_id
			     	left join tbl_task t on t.id = ct.task_id
					left join tbl_clienttype ctt on ctt.id = c.client_type_id

					where ct.status = 1
					and ct.transaction_type = 'deposit'

					UNION
					select 
						'b' as sequence,
						ct.transaction_id as id,
						ct.transaction_type as description,
						ct.client_id,
						ct.transaction_date,
						ct.transaction_type,
						0.00 as deposit,
						ct.amount as withdraw,
                        concat(p.project_id, ' - ', p.name) as display_name,
                        t.file_number,
						ct.note,
						c.client_name,
						c.phone,
						ctt.name,
						0.00 as balance
					from tbl_client_transactions ct
					join tbl_client c on c.id = ct.client_id
                    left join tbl_project p on p.id = ct.project_id
				    left join tbl_task t on t.id = ct.task_id
					left join tbl_clienttype ctt on ctt.id = c.client_type_id
					where ct.status = 1
					and ct.transaction_type = 'withdraw'
				) as tbl
				where 1 = 1 $clauses
				order by $order
			")->result();

        $previousBalance = $this->mt->getClientTransactionSummary($data->clientId, $data->dateFrom)[0]->balance;

        $transactions = array_map(function ($key, $trn) use ($previousBalance, $transactions) {
            $trn->balance = (($key == 0 ? $previousBalance : $transactions[$key - 1]->balance) + $trn->deposit) - $trn->withdraw;

            return $trn;
        }, array_keys($transactions), $transactions);

        $res['previousBalance'] = $previousBalance;
        $res['transactions'] = $transactions;

        echo json_encode($res);
    }

    public function getAllProjectExpense()
    {
        $data = json_decode($this->input->raw_input_stream);

        $clauses = '';
        $order = 'pe.expense_date desc, pe.expense_id desc';

        if (isset($data->ProjectTypeId) && $data->ProjectTypeId != null) {
            $clauses .= " and project_type_id = '$data->ProjectTypeId'";
        }

        if (isset($data->project_id) && $data->project_id != null) {
            $clauses .= " and projectId = '$data->project_id'";
        }

        if (
            isset($data->dateFrom) && $data->dateFrom != ''
            && isset($data->dateTo) && $data->dateTo != ''
        ) {
            $clauses .= " and expense_date between '$data->dateFrom' and '$data->dateTo'";
        }

        if (isset($data->ledger)) {
            $order = 'transaction_date, sequence, id';
        }

        $transactions = $this->db->query("
					select 
					 pe.*,
					 p.name as project_name,
					 pt.name as project_type,
					 e.name as assign_name
					from tbl_project_expense pe
					left join tbl_type pt on pt.id = pe.project_type_id
					left join tbl_project p on p.id = pe.projectId
					left join tbl_employee e on e.id = pe.assign_to
					where pe.status = 1
				  $clauses
				order by $order
			")->result();

        $res['transactions'] = $transactions;

        echo json_encode($res);
    }

    public function removeBankTransaction()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);
            $this->db->query('update tbl_bank_transactions set status = 0 where transaction_id = ?', $data->transaction_id);

            $res = ['success' => true, 'message' => 'Transaction removed'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function removeClientTransaction()
    {

        $res = ['success' => false, 'message' => ''];

        try {
            $data = json_decode($this->input->raw_input_stream);
            $this->db->query('update tbl_client_transactions set status = 0 where transaction_id = ?', $data->transaction_id);

            $res = ['success' => true, 'message' => 'Transaction removed'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function removeProjectExpense()
    {
        $res = ['success' => false, 'message' => ''];

        try {
            $data = json_decode($this->input->raw_input_stream);
            $this->db->query('update tbl_project_expense set status = 0 where expense_id = ?', $data->expense_id);

            $res = ['success' => true, 'message' => 'Project Expense removed'];
        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function bankTransactionReprot()
    {

        $data['title'] = 'Bank Transaction Report';
        $data['backend_content'] = 'account/bank_transaction_report';
        $this->load->view('admin/layout', $data);
    }

    public function projectTransactionsReport()
    {

        $data['title'] = 'Clients Transaction Report';
        $data['backend_content'] = 'account/project_transaction_report';
        $this->load->view('admin/layout', $data);
    }

    public function vendorExpenseReport()
    {
        $data['title'] = 'Vendor Expense Report';
        $data['backend_content'] = 'account/vendor_expense_report';
        $this->load->view('admin/layout', $data);
    }

    public function projectExpenseReport()
    {
        $data['title'] = 'Project Expense Report';
        $data['backend_content'] = 'account/project_expense_report';
        $this->load->view('admin/layout', $data);
    }

    public function cashView()
    {
        $data['title'] = 'Cash View';
        $data['transaction_summary'] = $this->mt->getTransactionSummary();
        $data['bank_account_summary'] = $this->mt->getBankTransactionSummary();
        $data['backend_content'] = 'account/cash_view';
        $this->load->view('admin/layout', $data);
    }

    public function getBankBalance()
    {

        $data = json_decode($this->input->raw_input_stream);

        $accountId = null;
        if (isset($data->accountId) && $data->accountId != '') {
            $accountId = $data->accountId;
        }

        $bankBalance = $this->mt->getBankTransactionSummary($accountId);

        echo json_encode($bankBalance);
    }

    public function getClientCurrentBalance()
    {

        $data = json_decode($this->input->raw_input_stream);

        $ClientId = null;
        if (isset($data->ClientId) && $data->ClientId != '') {
            $ClientId = $data->ClientId;
        }

        $bankBalance = $this->mt->getClientTransactionSummary($ClientId);
        echo json_encode($bankBalance);
    }

    public function getCashAndBankBalance()
    {
        $data = json_decode($this->input->raw_input_stream);

        $date = null;
        if (isset($data->date) && $data->date != '') {
            $date = $data->date;
        }

        $res['cashBalance'] = $this->mt->getTransactionSummary($date);

        $res['bankBalance'] = $this->mt->getBankTransactionSummary(null, $date);

        echo json_encode($res);
    }

    public function bankLedger()
    {
        $access = $this->mt->userAccess();
        if (! $access) {
            redirect(base_url());
        }
        $data['title'] = 'Bank Ledger';
        $data['content'] = $this->load->view('Administrator/account/bank_ledger', $data, true);
        $this->load->view('Administrator/index', $data);
    }

    public function cashLedger()
    {

        $data['title'] = 'Cash Ledger';
        $data['backend_content'] = 'account/cash_ledger';
        $this->load->view('admin/layout', $data);
    }

    public function getCashLedger()
    {
        $data = json_decode($this->input->raw_input_stream);
        // $previousBalance = $this->mt->getTransactionSummary($data->fromDate)->cash_balance;
        $previousBalance = 0;
        $ledger = $this->db->query("
            /* Cash In */
           
            select 
                ct.transaction_id as id,
                ct.transaction_date as date,
                concat('Cash in - ') as description,
                ct.amount as in_amount,
                0.00 as out_amount
            from tbl_client_transactions ct
            join tbl_client c on c.id = ct.client_id
            where ct.status = 1
            and ct.transaction_type = 'deposit'
            and ct.transaction_date between '$data->fromDate' and '$data->toDate'
            
            UNION
            
            select 
                clt.Tr_SlNo as id,
                clt.Tr_date as date,
                concat('Cash Deposite - ', ac.Acc_Name, ' - ', ac.Acc_Code) as description,
                clt.In_Amount as in_amount,
                0.00 as out_amount
            from tbl_cashtransaction clt 
            left join tbl_account ac on ac.Acc_SlNo = clt.Acc_SlID  
            where clt.status = 1
            and clt.Tr_date between '$data->fromDate' and '$data->toDate'
            

          /* Cash out */
			UNION
			select 
				cltt.Tr_SlNo as id,
				cltt.Tr_date as date,
				concat('Cash Withdraw - ', ba.Acc_Name, ' - ', ba.Acc_Code) as description,
				0.00 as in_amount,
				cltt.Out_Amount as out_amount
			from tbl_cashtransaction cltt 
			join tbl_account ba on ba.Acc_SlNo = cltt.Acc_SlID  -- Change Acc_SlID to Acc_SlNo
			where cltt.status = 1
			and cltt.Tr_date between '$data->fromDate' and '$data->toDate'
   
            UNION
            
            select 
                ctt.transaction_id as id,
                ctt.transaction_date as date,
                concat('Cash Out - ') as description,
                0.00 as in_amount,
                ctt.amount as out_amount
            from tbl_client_transactions ctt
            left join tbl_client c on c.id = ctt.client_id
            where ctt.status = 1
            and ctt.transaction_type = 'withdraw'
            and ctt.transaction_date between '$data->fromDate' and '$data->toDate'

            order by date, id
        ")->result();

        $ledger = array_map(function ($ind, $row) use ($previousBalance, $ledger) {
            $row->balance = (($ind == 0 ? $previousBalance : $ledger[$ind - 1]->balance) + $row->in_amount) - $row->out_amount;

            return $row;
        }, array_keys($ledger), $ledger);

        $res['previousBalance'] = $previousBalance;
        $res['ledger'] = $ledger;

        echo json_encode($res);
    }
}

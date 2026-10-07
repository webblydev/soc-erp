<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Project extends CI_Controller
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

    public function project_visit()
    {
        $data['title'] = 'Project Visit';
        $data['backend_content'] = 'project/project_visit';
        $this->load->view('admin/layout', $data);
    }

    public function projectMatarialEstimate()
    {
        $data['title'] = 'Project Material Estimate';
        $data['backend_content'] = 'project/project_material_estimate';
        $this->load->view('admin/layout', $data);
    }

    public function addProjectVisit()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $project_visit = [
                'construction_id' => $data->project_visit->construction_id,
                'permitee_name' => $data->project_visit->permitee_name,
                'project_id' => $data->project_visit->project_id,
                'location' => $data->project_visit->location,
                'constructor_name' => $data->project_visit->constructor_name,
                'field_office_phone' => $data->project_visit->field_office_phone,
                'project_eng_name' => $data->project_visit->project_eng_name,
                'inspection_date' => $data->project_visit->inspection_date,
                'start_time' => $data->project_visit->start_time,
                'end_time' => $data->project_visit->end_time,
                'inspection_type_weekly' => $data->project_visit->inspection_type_weekly,
                'inspection_type_event' => $data->project_visit->inspection_type_event,
                'description' => $data->project_visit->description,
                'status' => 'a',
                'AddBy' => $this->session->userdata('FullName'),
                'AddTime' => date('Y-m-d H:i:s'),
                'project_visit_branchid' => $this->session->userdata('BRANCHid'),
            ];

            $this->db->insert('tbl_project_visit', $project_visit);

            $projectVisitId = $this->db->insert_id();

            foreach ($data->cart as $cartProduct) {
                $projectVisitDetails = [
                    'project_visit_id' => $projectVisitId,
                    'visit_location' => $cartProduct->visit_location,
                    'visit_description' => $cartProduct->visit_description,
                    'visit_finding' => $cartProduct->visit_finding,
                    'visit_regarding' => $cartProduct->visit_regarding,
                    'status' => 'a',
                    'AddBy' => $this->session->userdata('FullName'),
                    'AddTime' => date('Y-m-d H:i:s'),
                    'project_visit_details_branchid' => $this->session->userdata('BRANCHid'),
                ];

                $this->db->insert('tbl_project_visit_details', $projectVisitDetails);

            }

            $res = ['success' => true, 'message' => 'Project Visit Success', 'projectVisitId' => $projectVisitId];

        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function getProjectVisit()
    {
        $data = json_decode($this->input->raw_input_stream);
        $branchId = $this->session->userdata('BRANCHid');
        $clauses = '';
        if (isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != '') {
            $clauses .= " and pv.inspection_date between '$data->dateFrom' and '$data->dateTo'";
        }
        if (isset($data->project_id) && $data->project_id != '') {
            $clauses .= " and pv.project_id = '$data->project_id'";
        }
        if (isset($data->visit_id) && $data->visit_id != '') {
            $clauses .= " and pv.Project_Visit_SlNo = '$data->visit_id'";
        }
        $project_visit = $this->db->query("
        select 
        pv.*,
        p.name
        from tbl_project_visit pv
        left join tbl_project p on p.id = pv.project_id
        where pv.status = 'a'
        $clauses
        order by pv.Project_Visit_SlNo desc
     ")->result();

        foreach ($project_visit as $visit) {
            $visit->details = $this->db->query("
                select pvd.* 
                from tbl_project_visit_details pvd
                where pvd.project_visit_id = '$visit->Project_Visit_SlNo'
            ")->result();
        }

        $res['project_visit'] = $project_visit;
        echo json_encode($res);

    }

    public function updateProjectVisit()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);
            $visitId = $data->project_visit->Project_Visit_SlNo;

            $project_visit = [
                'construction_id' => $data->project_visit->construction_id,
                'permitee_name' => $data->project_visit->permitee_name,
                'project_id' => $data->project_visit->project_id,
                'location' => $data->project_visit->location,
                'constructor_name' => $data->project_visit->constructor_name,
                'field_office_phone' => $data->project_visit->field_office_phone,
                'project_eng_name' => $data->project_visit->project_eng_name,
                'inspection_date' => $data->project_visit->inspection_date,
                'start_time' => $data->project_visit->start_time,
                'end_time' => $data->project_visit->end_time,
                'inspection_type_weekly' => $data->project_visit->inspection_type_weekly,
                'inspection_type_event' => $data->project_visit->inspection_type_event,
                'description' => $data->project_visit->description,
                'status' => 'a',
                'AddBy' => $this->session->userdata('FullName'),
                'AddTime' => date('Y-m-d H:i:s'),
                'project_visit_branchid' => $this->session->userdata('BRANCHid'),
            ];

            $this->db->where('Project_Visit_SlNo', $visitId);
            $this->db->update('tbl_project_visit', $project_visit);

            $this->db->query('delete from tbl_project_visit_details where project_visit_id = ?', $visitId);

            foreach ($data->cart as $cartProduct) {
                $projectVisitDetails = [
                    'project_visit_id' => $visitId,
                    'visit_location' => $cartProduct->visit_location,
                    'visit_description' => $cartProduct->visit_description,
                    'visit_finding' => $cartProduct->visit_finding,
                    'visit_regarding' => $cartProduct->visit_regarding,
                    'status' => 'a',
                    'AddBy' => $this->session->userdata('FullName'),
                    'AddTime' => date('Y-m-d H:i:s'),
                    'project_visit_details_branchid' => $this->session->userdata('BRANCHid'),
                ];

                $this->db->insert('tbl_project_visit_details', $projectVisitDetails);
            }

            $res = ['success' => true, 'message' => 'Projec Visit Updated', 'visitId' => $visitId];

        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function deleteProjectVisit()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);
            $visitId = $data->visitId;

            $this->db->trans_begin();

            $this->db->set(['status' => 'd'])
                ->where('Project_Visit_SlNo', $visitId)
                ->update('tbl_project_visit');

            $this->db->set(['status' => 'd'])
                ->where('project_visit_id', $visitId)
                ->update('tbl_project_visit_details');
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                $res = ['success' => false, 'message' => 'Failed to delete project visit'];
            } else {
                $this->db->trans_commit();
                $res = ['success' => true, 'message' => 'Project visit and details deleted'];
            }

        } catch (Exception $ex) {
            $this->db->trans_rollback();
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function addProjectMaterialEstimate()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $material_estimate = [
                'work_name' => $data->project_visit->project_id,
                'address' => $data->project_visit->address,
                'project_id' => $data->project_visit->project_id,
                'date' => $data->project_visit->date,
                'status' => 'a',
                'AddBy' => $this->session->userdata('FullName'),
                'AddTime' => date('Y-m-d H:i:s'),
                'project_visit_branchid' => $this->session->userdata('BRANCHid'),
            ];

            $this->db->insert('tbl_project_material_estimate', $material_estimate);

            $materialEstimateId = $this->db->insert_id();

            foreach ($data->cart as $cartProduct) {
                $projectEstimateDetails = [
                    'material_estimate_id' => $materialEstimateId,
                    'material_id' => $cartProduct->material_id,
                    'unit' => $cartProduct->unit,
                    'total_estimated_qty' => $cartProduct->total_estimated_qty,
                    'purpose_estimate' => $cartProduct->purpose_estimate,
                    'status' => 'a',
                    'AddBy' => $this->session->userdata('FullName'),
                    'AddTime' => date('Y-m-d H:i:s'),
                    'estimate_details_branchid' => $this->session->userdata('BRANCHid'),
                ];

                $this->db->insert('tbl_project_material_estimate_details', $projectEstimateDetails);

            }

            $res = ['success' => true, 'message' => 'Project Material Estamate Success', 'MaterialEstimateId' => $materialEstimateId];

        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function getProjectMaterialEstimate()
    {
        $data = json_decode($this->input->raw_input_stream);
        $branchId = $this->session->userdata('BRANCHid');

        $clauses = '';
        if (isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != '') {
            $clauses .= " and pe.date between '$data->dateFrom' and '$data->dateTo'";
        }

        if (isset($data->project_id) && $data->project_id != '') {
            $clauses .= " and pe.project_id = '$data->project_id'";
        }

        $work_estimate = $this->db->query("
            select 
            pe.*,
            p.name
            from tbl_project_material_estimate pe
            left join tbl_project p on p.id = pe.project_id
            where pe.status = 'a'
            $clauses
            order by pe.Material_Estimate_SlNo desc
        ")->result();

        foreach ($work_estimate as $visit) {
            $visit->details = $this->db->query("
                select pmd.*,
                m.name as material_name
                from tbl_project_material_estimate_details pmd
                left join tbl_material m on m.id = pmd.material_id
                where pmd.material_estimate_id = '$visit->Material_Estimate_SlNo'
            ")->result();
        }

        $res['material_estimate'] = $work_estimate;

        echo json_encode($res);
    }

    public function workEstimateSheetEntry()
    {
        $data['title'] = 'Work Estimate Sheet entry';
        $data['backend_content'] = 'project/work_estimate_sheet_entry';
        $this->load->view('admin/layout', $data);
    }

    public function updateProjectMaterialEstimate()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);
            $materialEstimateId = $data->project_visit->Material_Estimate_SlNo;

            $material_estimate = [
                'work_name' => $data->project_visit->project_id,
                'address' => $data->project_visit->address,
                'project_id' => $data->project_visit->project_id,
                'date' => $data->project_visit->date,
                'status' => 'a',
                'AddBy' => $this->session->userdata('FullName'),
                'AddTime' => date('Y-m-d H:i:s'),
                'project_visit_branchid' => $this->session->userdata('BRANCHid'),
            ];

            $this->db->where('Material_Estimate_SlNo', $materialEstimateId);
            $this->db->update('tbl_project_material_estimate', $material_estimate);

            $this->db->query('delete from tbl_project_material_estimate_details where material_estimate_id = ?', $materialEstimateId);

            foreach ($data->cart as $cartProduct) {

                $projectEstimateDetails = [
                    'material_estimate_id' => $materialEstimateId,
                    'material_name' => $cartProduct->material_name,
                    'unit' => $cartProduct->unit,
                    'total_estimated_qty' => $cartProduct->total_estimated_qty,
                    'purpose_estimate' => $cartProduct->purpose_estimate,
                    'status' => 'a',
                    'AddBy' => $this->session->userdata('FullName'),
                    'AddTime' => date('Y-m-d H:i:s'),
                    'estimate_details_branchid' => $this->session->userdata('BRANCHid'),
                ];

                $this->db->insert('tbl_project_material_estimate_details', $projectEstimateDetails);
            }

            $res = ['success' => true, 'message' => 'Projec Material Estimate Updated', 'materialEstimateId' => $materialEstimateId];

        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function addWorkEstimate()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);

            $work_estimate = [
                'work_name' => $data->project_visit->project_id,
                'work_item_id' => $data->project_visit->work_item_id,
                'address' => $data->project_visit->address,
                'project_id' => $data->project_visit->project_id,
                'date' => $data->project_visit->date,
                'status' => 'a',
                'AddBy' => $this->session->userdata('FullName'),
                'AddTime' => date('Y-m-d H:i:s'),
                'project_visit_branchid' => $this->session->userdata('BRANCHid'),
            ];

            $this->db->insert('tbl_work_estimate', $work_estimate);

            $work_estimate_id = $this->db->insert_id();

            foreach ($data->cart as $cartProduct) {
                $workEstimateDetails = [
                    'work_estimate_id' => $work_estimate_id,
                    'work_description' => $cartProduct->work_description,
                    'level' => $cartProduct->level,
                    'location' => $cartProduct->location,
                    'length' => $cartProduct->length,
                    'width' => $cartProduct->width,
                    'height' => $cartProduct->height,
                    'nose' => $cartProduct->nose,
                    'unit' => $cartProduct->unit,
                    'quantity' => $cartProduct->quantity,
                    'status' => 'a',
                    'AddBy' => $this->session->userdata('FullName'),
                    'AddTime' => date('Y-m-d H:i:s'),
                    'estimate_details_branchid' => $this->session->userdata('BRANCHid'),
                ];

                $this->db->insert('tbl_work_estimate_details', $workEstimateDetails);

            }

            $res = ['success' => true, 'message' => 'Work Estimate Success', 'workEstimateId' => $work_estimate_id];

        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function getWorkEstimate()
    {
        $data = json_decode($this->input->raw_input_stream);
        $branchId = $this->session->userdata('BRANCHid');

        $clauses = '';
        if (isset($data->dateFrom) && $data->dateFrom != '' && isset($data->dateTo) && $data->dateTo != '') {
            $clauses .= " and we.date between '$data->dateFrom' and '$data->dateTo'";
        }

        if (isset($data->project_id) && $data->project_id != '') {
            $clauses .= " and we.project_id = '$data->project_id'";
        }

        $work_estimate = $this->db->query("
            select 
            we.*,
            p.name,
            w.name as work_item_name
            from tbl_work_estimate we
            left join tbl_project p on p.id = we.project_id
            left join tbl_workitem w on w.id = we.work_item_id
            where we.status = 'a'
            $clauses
            order by we.Work_Estimate_SlNo desc
        ")->result();

        foreach ($work_estimate as $visit) {
            $visit->details = $this->db->query("
                select wed.* 
                from tbl_work_estimate_details wed
                where wed.work_estimate_id = '$visit->Work_Estimate_SlNo'
            ")->result();
        }

        echo json_encode($work_estimate);
    }

    public function updateWorkEstimate()
    {
        $res = ['success' => false, 'message' => ''];
        try {
            $data = json_decode($this->input->raw_input_stream);
            $materialEstimateId = $data->project_visit->Work_Estimate_SlNo;

            $work_estimate = [
                'work_name' => $data->project_visit->project_id,
                'work_item_id' => $data->project_visit->work_item_id,
                'address' => $data->project_visit->address,
                'project_id' => $data->project_visit->project_id,
                'date' => $data->project_visit->date,
                'status' => 'a',
                'AddBy' => $this->session->userdata('FullName'),
                'AddTime' => date('Y-m-d H:i:s'),
                'project_visit_branchid' => $this->session->userdata('BRANCHid'),
            ];

            $this->db->where('Work_Estimate_SlNo', $materialEstimateId);
            $this->db->update('tbl_work_estimate', $work_estimate);

            $this->db->query('delete from tbl_work_estimate_details where work_estimate_id = ?', $materialEstimateId);

            foreach ($data->cart as $cartProduct) {
                $workEstimateDetails = [
                    'work_estimate_id' => $materialEstimateId,
                    'work_description' => $cartProduct->work_description,
                    'level' => $cartProduct->level,
                    'location' => $cartProduct->location,
                    'length' => $cartProduct->length,
                    'width' => $cartProduct->width,
                    'height' => $cartProduct->height,
                    'nose' => $cartProduct->nose,
                    'unit' => $cartProduct->unit,
                    'quantity' => $cartProduct->quantity,
                    'status' => 'a',
                    'AddBy' => $this->session->userdata('FullName'),
                    'AddTime' => date('Y-m-d H:i:s'),
                    'estimate_details_branchid' => $this->session->userdata('BRANCHid'),
                ];

                $this->db->insert('tbl_work_estimate_details', $workEstimateDetails);
            }

            $res = ['success' => true, 'message' => 'Work Estimate Updated', 'materialEstimateId' => $materialEstimateId];

        } catch (Exception $ex) {
            $res = ['success' => false, 'message' => $ex->getMessage()];
        }

        echo json_encode($res);
    }

    public function projectVisitRecord()
    {
        $data['title'] = 'Project Visit Record';
        $data['backend_content'] = 'project/project_visit_record';
        $this->load->view('admin/layout', $data);
    }

    public function projectMatarialEstimateRecord()
    {
        $data['title'] = 'Project Material Estimate Record';
        $data['backend_content'] = 'project/project_material_estimate_record';
        $this->load->view('admin/layout', $data);
    }

    public function workEstimateSheetRecord()
    {
        $data['title'] = 'Work Estimate Record';
        $data['backend_content'] = 'project/work_estimate_record';
        $this->load->view('admin/layout', $data);
    }

    public function deleteMaterialEstimate()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set('status', 'd')->where('Material_Estimate_SlNo', $data->estimate_id)->update('tbl_project_material_estimate');
            $this->db->set('status', 'd')->where('material_estimate_id', $data->estimate_id)->update('tbl_project_material_estimate_details');

            $res->success = true;
            $res->message = 'Material Estimate deleted successfully';
        } catch (Exception $ex) {
            $res->success = false;
            $res->message = 'fail'.$ex->getMessage();
        }

        echo json_encode($res);
    }

    public function deleteWorkEstimate()
    {
        $res = new stdClass;
        try {
            $data = json_decode($this->input->raw_input_stream);

            $this->db->set('status', 'd')->where('Work_Estimate_SlNo', $data->estimate_id)->update('tbl_work_estimate');
            $this->db->set('status', 'd')->where('work_estimate_id', $data->estimate_id)->update('tbl_work_estimate_details');
            $res->success = true;
            $res->message = 'Material Estimate deleted successfully';
        } catch (Exception $ex) {
            $res->success = false;
            $res->message = 'fail'.$ex->getMessage();
        }

        echo json_encode($res);
    }
}

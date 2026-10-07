<style>
    .v-select {
        float: right;
        min-width: 180px;
        margin-left: 5px;
        margin-bottom: 5px;
    }

    .v-select .dropdown-toggle {
        padding: 0px;
        height: 25px;
    }

    .v-select input[type=search],
    .v-select input[type=search]:focus {
        margin: 0px;
    }

    .v-select .vs__selected-options {
        overflow: hidden;
        flex-wrap: nowrap;
    }

    .v-select .selected-tag {
        margin: 2px 0px;
        white-space: nowrap;
        position: absolute;
        left: 0px;
    }

    .v-select .vs__actions {
        margin-top: -5px;
    }

    .v-select .dropdown-menu {
        width: auto;
        overflow-y: auto;
    }

    .form-inline .form-group {
        margin-right: 5px;
    }

    .form-inline label {
        margin-top: 3px;
    }

    .form-horizontal .v-select {
        width: 100%;
    }

    .form-horizontal select {
        padding: 0px;
    }

    #newTask label {
        font-size: 13px;
    }
</style>
<div id="newTask">
    <div class="row">
        <div class="col-md-12">
            <div class="widget-box">
                <div class="widget-header">
                    <h4 class="widget-title">Task Entry Information</h4>
                    <div class="widget-toolbar">
                        <a href="#" data-action="collapse">
                            <i class="ace-icon fa fa-chevron-up"></i>
                        </a>

                        <a href="#" data-action="close">
                            <i class="ace-icon fa fa-times"></i>
                        </a>
                    </div>
                </div>

                <div class="widget-body">
                    <div class="widget-main">
                        <div class="row">
                            <div class="form-horizontal">
                                <div class="col-md-4">
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">File Number</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="task.file_number" disabled>
                                        </div>
                                    </div>
                                    <!-- <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Assign By</label>
                                        <div class="col-md-8">
                                            <v-select v-bind:options="employees" label="display_name" v-model="selectedAssignedBy"></v-select>
                                        </div>
                                    </div> -->
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Assign To</label>
                                        <div class="col-md-8">
                                            <v-select v-bind:options="employees" label="display_name"
                                                v-model="selectedAssignedPerson"></v-select>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Supervisor</label>
                                        <div class="col-md-8">
                                            <v-select v-bind:options="employees" label="display_name"
                                                v-model="selectedSupportOfficer"></v-select>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Date</label>
                                        <div class="col-md-8">
                                            <input type="date" class="form-control" v-model="task.entry_date">
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Status</label>
                                        <div class="col-md-8">
                                            <select class="form-control" v-model="task.status">
                                                <option value="p">Pending</option>
                                                <option value="o">On Progress</option>
                                                <option value="c">On Progress</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Project Name</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="task.project_name">
                                        </div>
                                    </div> -->


                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Project Name</label>
                                        <div class="col-md-8">
                                            <v-select v-bind:options="projects" label="name" v-model="selectedProject"
                                                @input="getProjectsById">
                                            </v-select>
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Project Id</label>
                                        <div class="col-md-8">
                                            <v-select v-bind:options="all_projects" label="project_id"
                                                v-model="selectedProjectId" @input="getProjectTypeById">
                                            </v-select>
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Type</label>
                                        <div class="col-md-8">
                                            <v-select v-bind:options="projectTypes" label="name"
                                                v-model="selectedProjectType"></v-select>
                                        </div>
                                    </div>

                                </div>
                                <div class="col-md-4">

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Client_Id</label>
                                        <div class="col-md-8">
                                            <v-select v-bind:options="clients" label="display_name"
                                                v-model="selectedClient" @input="showClientDetails"></v-select>
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Client Name</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="task.client_name" readonly>
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Client Org Name</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="task.org_name" readonly>
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Client Phone</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="task.client_phone"
                                                readonly>
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Client Address</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="task.client_address"
                                                readonly>
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Bill Number</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="task.bill_number"
                                                @input="billCalculaton">
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Bill Amount</label>
                                        <div class="col-md-8">
                                            <input type="number" class="form-control" v-model="task.bill_amount"
                                                @input="billCalculaton">
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Collect Amount</label>
                                        <div class="col-md-8">
                                            <input type="number" class="form-control" v-model="task.collect_amount"
                                                @input="billCalculaton">
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Due Amount</label>
                                        <div class="col-md-8">
                                            <input type="number" class="form-control" v-model="task.due_amount">
                                        </div>
                                    </div>

                                </div>

                                <div class="col-md-4">

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Deadline</label>
                                        <div class="col-md-8">
                                            <input type="date" class="form-control" v-model="task.deadline">
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <div class="col-md-12">
                                            <textarea class="form-control" style="min-height:140px"
                                                v-model="task.task_detail"></textarea>
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Is Important</label>
                                        <div class="col-md-8">
                                            <input type="checkbox" v-model="task.is_important" true-value="true"
                                                false-value="false">
                                        </div>
                                    </div>

                                    <div class="form-group clearfix">
                                        <div class="col-md-12 text-right">
                                            <button class="btn btn-success btn-sm" @click="saveTask">Save</button>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="widget-box">
                <div class="widget-header">
                    <h4 class="widget-title">Task List</h4>
                    <div class="widget-toolbar">
                        <a href="#" data-action="collapse">
                            <i class="ace-icon fa fa-chevron-up"></i>
                        </a>

                        <a href="#" data-action="close">
                            <i class="ace-icon fa fa-times"></i>
                        </a>
                    </div>
                </div>

                <div class="widget-body">
                    <div class="widget-main">
                        <div class="row">
                            <div class="col-sm-12 form-inline">
                                <div class="form-group">
                                    <label for="filter" class="sr-only">Filter</label>
                                    <input type="text" class="form-control" v-model="filter" placeholder="Filter">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <datatable :columns="columns" :data="tasks" :filter-by="filter">
                                        <template scope="{ row }">
                                            <tr v-bind:style="{ color: row.is_important == 'true' ? '#ff3300' : '' }">
                                                <td>{{ row.file_number }}</td>
                                                <td>{{ row.client_id }}</td>
                                                <td>{{ row.entry_date }}</td>
                                                <td>{{ row.project_name }}</td>
                                                <td>{{ row.client_name }}</td>
                                                <td>{{ row.type }}</td>
                                                <td>{{ row.assigned_person }}</td>
                                                <td>{{ row.support_person }}</td>
                                                <td>{{ row.deadline }}</td>
                                                <td>{{ row.assign_by_person }}</td>
                                                <td>
                                                    <a href="" @click.prevent="editTask(row)"><i
                                                            class="fa fa-pencil-square-o"></i></a>&nbsp;
                                                    <a href="" title="Task Invoice"
                                                        v-bind:href="`/task_invoice_print/${row.id}`" target="_blank"><i
                                                            class="fa fa-file"></i></a>
                                                    <a href="" @click.prevent="deleteTask(row.id)"><i
                                                            class="fa fa-trash"></i></a>
                                                </td>
                                            </tr>
                                        </template>
                                    </datatable>
                                    <datatable-pager v-model="page" type="abbreviated" :per-page="per_page">
                                    </datatable-pager>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>assets/js/vue/vue.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script>
    Vue.component('v-select', VueSelect.VueSelect);
    new Vue({
        el: '#newTask',
        data: {
            task: {
                id: null,
                file_number: '<?php echo $newFileNumber; ?>',
                client_id: '',
                client_list_id: '',
                entry_date: moment().format('YYYY-MM-DD'),
                project_name: '',
                client_name: '',
                client_phone: '',
                task_detail: '',
                type_id: '',
                project_id: '',
                projectId: '',
                assign_to: '',
                support_id: '',
                deadline: '',
                bill_amount: 0,
                bill_number: '',
                collect_amount: 0,
                due_amount: 0,
                completed_date: '',
                completed_by_comment: '',
                assign_by: '',
                status: 'p',
                is_important: 'false'
            },

            tasks: [],
            clients: [],
            selectedClient: null,
            employees: [],
            selectedProject: null,
            selectedProjectId: null,
            projects: [],
            all_projects: [],
            selectedAssignedPerson: null,
            selectedSupportOfficer: null,
            projectTypes: [],
            clientDetails: [],
            selectedProjectType: null,

            columns: [{
                    label: 'File Number',
                    field: 'file_number',
                    align: 'center'
                },
                {
                    label: 'Client Id',
                    field: 'client_id',
                    align: 'center'
                },
                {
                    label: 'Entry Date',
                    field: 'entry_date',
                    align: 'center'
                },
                {
                    label: 'Project Name',
                    field: 'project_name',
                    align: 'center'
                },
                {
                    label: 'Client Name',
                    field: 'client_name',
                    align: 'center'
                },
                {
                    label: 'Project Type',
                    field: 'type',
                    align: 'center'
                },
                {
                    label: 'Assign To',
                    field: 'assigned_person',
                    align: 'center'
                },
                {
                    label: 'Supervisor',
                    field: 'support_person',
                    align: 'center'
                },
                {
                    label: 'Deadline',
                    field: 'deadline',
                    align: 'center'
                },
                {
                    label: 'Assign By',
                    field: 'assign_by_person',
                    align: 'center'
                },
                {
                    label: 'Action',
                    align: 'center',
                    filterable: false
                }
            ],
            page: 1,
            per_page: 10,
            filter: ''
        },

        created() {
            this.getEmployees();
            this.getProjectTypes();
            this.getTasks();
            this.getClient();
            this.getProjects();
        },

        methods: {

            getEmployees() {
                axios.get('/get-employees').then(res => {
                    this.employees = res.data.map((item, display_name) => {
                        item.display_name = `${item.code} - ${item.name}`
                        return item;
                    });
                })
            },

            getProjectTypes() {
                axios.get('/get-types').then(res => {
                    this.projectTypes = res.data;
                })
            },

            getProjects() {
                axios.get('/get-projects').then(res => {
                    this.projects = res.data;
                })
            },

            getProjectsById() {

                let form = {
                    project_id: this.selectedProject.id
                }

                axios.post('/get-projects-by-projectid', form).then(res => {
                    this.all_projects = res.data;
                })

            },

            showClientDetails() {

                let formData = {
                    client_id: this.selectedClient.client_id
                }

                axios.post('/get-client-detail', formData).then(res => {
                    this.task.client_name = res.data.client_name;
                    this.task.client_phone = res.data.phone;
                    this.task.client_address = res.data.address;
                    this.task.org_name = res.data.org_name;
                })

            },

            getClient() {
                axios.get('/get-client').then(res => {
                    this.clients = res.data.filter((e) => {
                        return e.sold != '';
                    });
                })
            },

            getTasks() {
                axios.post('/get-tasks', {
                    status: 'p'
                }).then(res => {
                    this.tasks = res.data;
                })
            },

            getProjectTypeById() {
                let form = {
                    project_type_id: this.selectedProjectId.project_type_id
                }

                axios.post('/get-projectType-by-projectid', form).then(res => {
                    this.projectTypes = res.data;
                })
            },

            billCalculaton() {
                this.task.due_amount = this.task.bill_amount - this.task.collect_amount;
            },

            saveTask() {

                if (this.selectedAssignedPerson == null) {
                    alert('Select assign to');
                    return;
                }

                if (this.task.bill_number == null) {
                    alert("Please Give Bill Number");
                    return;
                }

                if (this.task.bill_amount == null) {
                    alert("Please Give Bill Amount");
                    return;
                }


                if (this.task.collect_amount == null) {
                    alert("Please Give Collect Amount");
                    return;
                }

                if (this.selectedProjectId == null) {
                    alert('Please Select Project Id');
                }

                if (this.selectedSupportOfficer == null) {
                    alert('Select support officer');
                    return;
                }

                this.task.assign_to = this.selectedAssignedPerson.id;
                this.task.support_id = this.selectedSupportOfficer.id;
                this.task.type_id = this.selectedProjectType.id;
                this.task.client_id = this.selectedClient.client_id;
                this.task.client_list_id = this.selectedClient.id;
                this.task.project_id = this.selectedProject.project_id;
                this.task.projectId = this.selectedProject.id;

                let url = '';
                if (this.task.id != null) {
                    url = '/update-task';
                } else {
                    url = '/add-task';
                    delete this.task.id
                }


                axios.post(url, this.task)
                    .then(res => {
                        let r = res.data;
                        alert(r.message);

                        if (r.success) {
                            this.resetForm();
                            let conf = confirm('Task Entry success, Do you want to view invoice?');
                            if (conf) {
                                window.open('/task_invoice_print/' + r.taskId, '_blank');
                                new Promise(r => setTimeout(r, 1000));
                            }
                            this.task.file_number = r.newFileNumber;
                            this.getTasks();
                        }

                    })
                    .catch(error => alert(error.response.statusText))
            },

            editTask(task) {
                let keys = Object.keys(this.task);
                keys.forEach(key => this.task[key] = task[key]);

                this.selectedAssignedPerson = {
                    id: task.assign_to,
                    name: task.assigned_person,
                    display_name: `${task.code} - ${task.assigned_person}`
                }

                this.selectedSupportOfficer = {
                    id: task.support_id,
                    name: task.support_person,
                    display_name: `${task.code2} - ${task.support_person}`
                }

                this.selectedProjectType = {
                    id: task.type_id,
                    name: task.type
                }

                this.selectedClient = {
                    id: task.client_list_id,
                    display_name: `${task.client_id} ${task.client_name}, ${task.client_phone}`,
                    client_id: task.client_id,
                    org_name: task.org_name,
                    client_phone: task.phone,
                    client_address: task.client_address,

                }

            },
            deleteTask(taskId) {
                let confirmation = confirm("Are you sure?");
                if (confirmation == false) {
                    return;
                }

                axios.post('/delete-task', {
                        id: taskId
                    })
                    .then(res => {
                        let r = res.data;
                        alert(r.message);
                        if (r.success) {
                            this.getTasks();
                        }
                    })
            },
            resetForm() {
                this.task = {
                    id: null,
                    entry_date: moment().format('YYYY-MM-DD'),
                    project_name: '',
                    client_name: '',
                    client_phone: '',
                    task_detail: '',
                    type_id: '',
                    assign_to: '',
                    support_id: '',
                    deadline: '',
                    completed_date: '',
                    completed_by_comment: '',
                    assign_by: '',
                    status: 'p',
                    is_important: 'false'
                }

                this.selectedAssignedPerson = null;
                this.selectedSupportOfficer = null;
                this.selectedProjectType = null;
            }
        }
    })
</script>
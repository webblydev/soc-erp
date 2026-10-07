<style>
    .v-select{
		margin-bottom: 5px;
	}
	.v-select.open .dropdown-toggle{
		border-bottom: 1px solid #ccc;
	}
	.v-select .dropdown-toggle{
		padding: 0px;
		height: 25px;
	}
	.v-select input[type=search], .v-select input[type=search]:focus{
		margin: 0px;
	}
	.v-select .vs__selected-options{
		overflow: hidden;
		flex-wrap:nowrap;
	}
	.v-select .selected-tag{
		margin: 2px 0px;
		white-space: nowrap;
		position:absolute;
		left: 0px;
	}
	.v-select .vs__actions{
		margin-top:-5px;
	}
	.v-select .dropdown-menu{
		width: auto;
		overflow-y:auto;
	}
    select {
        padding: 0px !important;
    }


    .button-active {
        margin-top: 5px;
        background-color:green;
        border:none; 
        padding: 2px 4px;
        border-radius:5px; 
        color:#fff;
        transition: .8s;
    }

    .button-dactive {
        margin-top: 5px;
        background-color:#b33939;
        border:none; 
        padding: 2px 4px;
        border-radius:5px; 
        color:#fff;
        transition: .8s;
    }

    .button-dactive:hover{
        background-color:#ff5252;
    }

    .button-active:hover{
        background-color:#218c74;
    }
</style>
<div id="employees">
    <div class="row">
        <div class="col-md-12">
            <div class="widget-box">
                <div class="widget-header">
                    <h4 class="widget-title">Employee Information</h4>
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
                        <form class="form-horizontal" @submit.prevent="saveEmployee">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Emp. ID</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="employee.code" readonly>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Emp. Name</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="employee.name" required>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Department</label>
                                        <div class="col-md-7">
                                            <v-select v-bind:options="departments" label="name" v-model="selectedDepartment"></v-select>
                                        </div>
                                        <div class="col-md-1">
                                            <a href="<?= base_url('department')?>" class="btn btn-xs btn-danger" style="height: 25px; border: 0; width: 27px; margin-left: -19px;" target="_blank" title="Add Department"><i class="fa fa-plus" aria-hidden="true" style="margin-top: 5px;"></i></a>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Emp. Post</label>
                                        <div class="col-md-7">
                                            <v-select v-bind:options="posts" label="name" v-model="selectedPost"></v-select>
                                        </div>
                                        <div class="col-md-1">
                                            <a href="<?= base_url('post')?>" class="btn btn-xs btn-danger" style="height: 25px; border: 0; width: 27px; margin-left: -19px;" target="_blank" title="Add Post"><i class="fa fa-plus" aria-hidden="true" style="margin-top: 5px;"></i></a>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Contact No.</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="employee.phone" required>
                                        </div>
                                    </div>
                                    
                                </div>

                               
                                <div class="col-md-4">
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Father's Name</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="employee.father_name" required>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Mother's Name</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="employee.mother_name" required>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Gender</label>
                                        <div class="col-md-8">
                                            <select class="form-control" v-model="employee.gender">
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Date of Birth</label>
                                        <div class="col-md-8">
                                            <input type="date" class="form-control" v-model="employee.dob" required>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Marital Status</label>
                                        <div class="col-md-8">
                                            <select class="form-control" v-model="employee.marital_status">
                                                <option value="married">Married</option>
                                                <option value="unmarred">Unmarred</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Present Address</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="employee.present_address" required>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Perm. Address</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="employee.permanent_address" required>
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">E-mail</label>
                                        <div class="col-md-8">
                                            <input type="email" class="form-control" v-model="employee.email">
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <label class="control-label col-md-4">Reference</label>
                                        <div class="col-md-8">
                                            <input type="text" class="form-control" v-model="employee.reference">
                                        </div>
                                    </div>
                                    <div class="form-group clearfix">
                                        <div class="col-md-8 col-md-offset-4">
                                            <input type="submit" value="Save" class="btn btn-success btn-sm">
                                            <input type="button" value="Cancel" class="btn btn-danger btn-sm" @click="resetForm">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="widget-box">
                <div class="widget-header">
                    <h4 class="widget-title">Employee List</h4>
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
                                    <datatable :columns="columns" :data="employees" :filter-by="filter">
                                        <template scope="{ row }">
                                            <tr>
                                                <td>{{ row.code }}</td>
                                                <td>{{ row.name }}</td>
                                                <td>{{ row.department }}</td>
                                                <td>{{ row.post }}</td>
                                                <td>{{ row.phone }}</td>
                                                <td>{{ row.email }}</td>
                                                <td>{{ row.present_address }}</td>
                                                <td>
                                                     <strong v-if="row.status == 'a'" style="margin-top:15px;" class="badge badge-success"> Activated </strong> 
                                                     <strong v-if="row.status == 'd'" style="margin-top:15px;" class="badge badge-danger"> Deactivated </strong> 
                                                 </td>
                                                <td>
                                                    <button type="button" class="button edit" @click="editEmployee(row)">
                                                        <i class="fa fa-pencil"></i>
                                                    </button>
                                                    <button v-if="row.status != 'a'"  type="button" class="button-active" @click="activeEmployee(row.id)">
                                                        Active
                                                    </button>
                                                    <button v-else  type="button" class="button-dactive" @click="deleteEmployee(row.id)">
                                                        Dactive
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </datatable>
                                    <datatable-pager v-model="page" type="abbreviated" :per-page="per_page"></datatable-pager>
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
    const app = new Vue({
        el: '#employees',
        data: {
            employee: {
                id: null,
                code: '',
                name: '',
                post_id: null,
                department_id: null,
                father_name: '',
                mother_name: '',
                dob: moment().format('YYYY-MM-DD'),
                gender: '',
                marital_status: '',
                present_address: '',
                permanent_address: '',
                phone: '',
                email: '',
                reference: ''
            },
            employees: [],
            departments: [],
            selectedDepartment: null,
            posts: [],
            selectedPost: null,
            columns: [
                { label: 'Id', field: 'code', align: 'center' },
                { label: 'Name', field: 'name', align: 'center' },
                { label: 'Dept.', field: 'department', align: 'center' },
                { label: 'Post', field: 'post', align: 'center' },
                { label: 'Phone', field: 'phone', align: 'center' },
                { label: 'E-mail', field: 'email', align: 'center' },
                { label: 'Present Add.', field: 'present_address', align: 'center' },
                { label: 'Status', field: 'status', align: 'center' },
                { label: 'Action', align: 'center', filterable: false }
            ],
            page: 1,
            per_page: 10,
            filter: ''
        },
        created() {
            this.getDepartments();
            this.getPosts();
            this.getEmployees();
            this.getGenerateCode()
        },
        methods: {
            getGenerateCode() {
                axios.get('/generate-employee-code').then(res => {
                    this.employee.code = res.data;
                })
            },
            getDepartments() {
                axios.get('/get-departments').then(res => {
                    this.departments = res.data;
                })
            },
            getPosts() {
                axios.post('/get-posts').then(res => {
                    this.posts = res.data;
                })
            },
            getEmployees() {
                axios.get('/get-all-employees').then(res => {
                    this.employees = res.data;
                })
            },
            saveEmployee() {
                if(this.selectedDepartment == null){
                    alert('Select department');
                    return;
                }

                if(this.selectedPost == null){
                    alert('Select post');
                    return;
                }

                this.employee.department_id = this.selectedDepartment.id;
                this.employee.post_id = this.selectedPost.id;

                let url = '';
                if(this.employee.id != null){
                    url = '/update-employee';
                } else {
                    url = '/add-employee';
                    delete this.employee.id
                }
                axios.post(url, this.employee)
                .then(res => {
                    let r = res.data;
                    alert(r.message);
                    if(r.success){
                        this.resetForm();
                        this.getEmployees();
                    }
                })
            },
            editEmployee(employee) {
                let keys = Object.keys(this.employee);
                keys.forEach(key => this.employee[key] = employee[key]);

                this.selectedDepartment = {
                    id: employee.department_id,
                    name: employee.department
                }

                this.selectedPost = {
                    id: employee.post_id,
                    name: employee.post
                }
            },
            async deleteEmployee(id) {
                if(confirm('Are you sure ?')) {
                    await axios.post('delete-employee', { id: id })
                    .then(res => {
                        if(res.data.success) {
                            alert(res.data.message);
                            this.getEmployees();
                        } else {
                            alert(res.data.message)
                        }
                    })
                    .catch(err => {

                    })
                }
            },

            async activeEmployee(id) {
                if(confirm('Are you sure ?')) {
                    await axios.post('active-employee', { id: id })
                    .then(res => {
                        if(res.data.success) {
                            alert(res.data.message);
                            this.getEmployees();
                        } else {
                            alert(res.data.message)
                        }
                    })
                    .catch(err => {

                    })
                }
            },

            

            resetForm() {
                this.employee = {
                    id: null,
                    code: '',
                    name: '',
                    post_id: null,
                    department_id: null,
                    father_name: '',
                    mother_name: '',
                    dob: moment().format('YYYY-MM-DD'),
                    gender: '',
                    marital_status: '',
                    present_address: '',
                    permanent_address: '',
                    phone: '',
                    eamil: '',
                    reference: ''
                }
                this.selectedDepartment = null;
                this.selectedPost = null;
                this.getGenerateCode();
                this.employee.dob = moment().format('YYYY-MM-DD');
            }
        }
    })
</script>
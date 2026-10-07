<div id="users">
    <div class="row">
        <div class="col-md-12 col-xs-12">
            <!-- Client entry-->
            <form method="post" @submit.prevent="saveUser">
                <div class="row" style="padding:15px 0px">
                    <div class="col-md-5">
                        <div class="form-group clearfix">
                            <label class="control-label col-md-4">Name:</label>
                            <div class="col-md-8">
                                <input type="text" class="form-control" v-model="user.name" required>
                            </div>
                        </div>

                        <div class="form-group clearfix">
                            <label class="control-label col-md-4">E-mail:</label>
                            <div class="col-md-8">
                                <input type="email" class="form-control" v-model="user.email">
                            </div>
                        </div>
                        <div class="form-group clearfix">
                            <label class="control-label col-md-4">Phone:</label>
                            <div class="col-md-8">
                                <input type="text" class="form-control" v-model="user.phone" required>
                            </div>
                        </div>
                        <div class="form-group clearfix">
                            <label class="control-label col-md-4">User Type:</label>
                            <div class="col-md-8">
                                <select class="form-control" v-model="selectedType" required>
                                    <option value="a">Admin</option>
                                    <option value="t">Team Manager</option>
                                    <option value="u">User</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group clearfix">
                            <label class="control-label col-md-4">Team Name:</label>
                            <div class="col-md-7">
                                <select class="form-control" v-model="selectedTeam" required>
                                    <option value="a">Team A</option>
                                    <option value="b">Team B</option>
                                    <option value="c">Team C</option>
                                    <option value="d">Team D</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <a href="<?= base_url('team') ?>" class="btn btn-xs btn-danger"
                                    style="height: 25px; border: 0; width: 27px; margin-left: -12px;margin-top: 2px;"
                                    target="_blank" title="Add New Product"><i class="fa fa-plus" aria-hidden="true"
                                        style="margin-top: 5px;"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group clearfix">
                            <label class="control-label col-md-4">Employee</label>
                            <div class="col-md-8">
                                <v-select v-bind:options="employees" label="display_name" v-model="selectedEmployee">
                                </v-select>
                            </div>
                        </div>
                        <div class="form-group clearfix">
                            <label class="control-label col-md-4">User Name:</label>
                            <div class="col-md-8">
                                <input type="text" class="form-control" v-model="user.user_name" required>
                            </div>
                        </div>
                        <div class="form-group clearfix">
                            <label class="control-label col-md-4">Password:</label>
                            <div class="col-md-8">
                                <input type="password" class="form-control" v-model="user.password">
                            </div>
                        </div>

                        <div class="form-group clearfix">
                            <label class="control-label col-md-4">Re-type :</label>
                            <div class="col-md-8">
                                <input type="password" class="form-control" v-model="user.retype">
                            </div>
                        </div>

                        <div class="form-group clearfix">
                            <label class="control-label col-md-4"></label>
                            <div class="col-md-8">
                                <input type="submit" name="submit" class="btn btn-primary btn-block" value="Save">
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <!-- user entry -->



        </div><!-- /.col -->
    </div><!-- /.row -->
    <hr style="margin-top: 0px;margin-bottom: 10px;">
    <div class="row">
        <div class="col-sm-12 form-inline">
            <div class="form-group">
                <label for="filter" class="sr-only">Filter</label>
                <input type="text" class="form-control" v-model="filter" placeholder="Filter">
            </div>
        </div>
        <div class="col-md-12">
            <div class="table-responsive">
                <datatable :columns="columns" :data="users" :filter-by="filter" style="margin-bottom: 5px;">
                    <template scope="{ row }">
                        <tr>
                            <td>{{ row.name }}</td>
                            <td>{{ row.user_name }}</td>
                            <td>{{ row.email }}</td>
                            <td>{{ row.phone }}</td>
                            <td>
                                <a href="" v-if="row.type == 'a'" class="btn btn-sm btn-info"> Admin</a>
                                <a href="" v-if="row.type == 't'" class="btn btn-sm btn-info"> Team Manager</a>
                                <a href="" v-if="row.type == 'u'" class="btn btn-sm btn-info"> User</a>
                            </td>
                            <td style="text-transform: capitalize;">Team {{ row.team_name }}</td>
                            <td>
                                <button type="button" class="button edit" @click="editUser(row)">
                                    <i class="fa fa-pencil"></i>
                                </button>
                                <button type="button" class="button" @click="deleteUser(row.id)">
                                    <i class="fa fa-trash"></i>
                                </button>
                                <button v-if="row.type != 'a'" type="button" class="button" data-toggle="modal"
                                    data-target="#exampleModal" @click.prevent="getPermission(row.id, row.permissions)">
                                    <i class="fa fa-users"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                </datatable>
                <datatable-pager v-model="page" type="abbreviated" :per-page="per_page" style="margin-bottom: 50px;">
                </datatable-pager>
            </div>
        </div>
    </div>

    <!-- modal start -->
    <div v-if="modal" class="modal fade" id="exampleModal" tabindex="-1" role="dialog"
        aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">User Menu Permissions <button type="button"
                            class="close" @click.prevent="modal = false" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button></h5>

                </div>
                <form @submit.prevent="savePermissions">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 col-md-offset-2">
                                <div class="checkbox">
                                    <label><input type="checkbox" value="dashboard" v-model="selected">Dashboard</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="prospect-entry" v-model="selected">Prospect
                                        Entry</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="get-team-client" v-model="selected">Prospect
                                        List</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="sold" v-model="selected">Sold List</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="report" v-model="selected">Client
                                        Report</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="area" v-model="selected">Area Entry</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="team" v-model="selected">Team Entry</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="requerment" v-model="selected">Requerment
                                        Entry</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="TransactionReport" v-model="selected">Cash
                                        Transaction Report
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="bank_transaction_report"
                                            v-model="selected">Bank
                                        Transaction Report
                                    </label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="project_transactions_report"
                                            v-model="selected">Project
                                        Transaction Report
                                    </label>
                                </div>

                                <div class="checkbox">
                                    <label><input type="checkbox" value="project_expense_report"
                                            v-model="selected">Project
                                        Expense Report
                                    </label>
                                </div>

                                <div class="checkbox">
                                    <label><input type="checkbox" value="vendor_expense_report"
                                            v-model="selected">Vendor
                                        Expense Ledger
                                    </label>
                                </div>

                                <div class="checkbox">
                                    <label><input type="checkbox" value="monthly_report" v-model="selected">
                                        Monthly Report
                                    </label>
                                </div>

                            </div>
                            <div class="col-md-4">
                                <div class="checkbox">
                                    <label><input type="checkbox" value="task" v-model="selected">Task Entry</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="my-task" v-model="selected">My Task</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="complete-task" v-model="selected">Completed
                                        Task</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="task-record" v-model="selected">Task
                                        Record</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="archive-task" v-model="selected">Archived
                                        Task</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="employee" v-model="selected">Employee
                                        Entry</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="department" v-model="selected">Department
                                        Entry</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="post" v-model="selected">Post Entry</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="type" v-model="selected">Type Entry</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="cashTransaction" v-model="selected">Cash
                                        Transaction</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="bank_transactions" v-model="selected">Bank
                                        Transaction</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="project_transactions"
                                            v-model="selected">Project Transactions</label>
                                </div>

                                <div class="checkbox">
                                    <label><input type="checkbox" value="project_expense" v-model="selected">Project
                                        Expense</label>
                                </div>

                                <div class="checkbox">
                                    <label><input type="checkbox" value="vendor_expense" v-model="selected">Vendor
                                        Expense</label>
                                </div>

                                <div class="checkbox">
                                    <label><input type="checkbox" value="bank_accounts" v-model="selected">Bank
                                        Accounts</label>
                                </div>
                                <div class="checkbox">
                                    <label><input type="checkbox" value="account" v-model="selected">Transaction
                                        Accounts</label>
                                </div>

                                <div class="checkbox">
                                    <label><input type="checkbox" value="project_visit" v-model="selected">Project Visit Entry
                                        </label>
                                </div>

                                <div class="checkbox">
                                    <label><input type="checkbox" value="project_visit_record" v-model="selected">Project Visit Record
                                        </label>
                                </div>

                                <div class="checkbox">
                                    <label><input type="checkbox" value="project_matarial_estimate" v-model="selected">Project  Material Estimate Entry
                                        </label>
                                </div>

                                <div class="checkbox">
                                    <label><input type="checkbox" value="project_matarial_estimate_record" v-model="selected">Project Material Estimate Record
                                        </label>
                                </div>

                                <div class="checkbox">
                                    <label><input type="checkbox" value="work_estimate_sheet_entry" v-model="selected">Work Estimate Sheet Entry
                                        </label>
                                </div>

                                <div class="checkbox">
                                    <label><input type="checkbox" value="work_estimate_sheet_record" v-model="selected">Work Estimate Record
                                        </label>
                                </div>


                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- end start -->
</div><!-- /.main-content -->

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script>
Vue.component('v-select', VueSelect.VueSelect);
new Vue({
    el: '#users',
    data() {
        return {
            user: {
                id: null,
                name: '',
                user_name: '',
                password: '',
                retype: '',
                email: '',
                phone: '',
                type: '',
                team_name: '',
                employee_id: null
            },
            selectedType: null,
            selectedTeam: null,
            employees: [],
            selectedEmployee: null,
            users: [],
            checkMobile: /^01[13-9][\d]{8}$/,
            columns: [{
                    label: 'Name',
                    field: 'name',
                    align: 'center'
                },
                {
                    label: 'User Name',
                    field: 'user_name',
                    align: 'center'
                },
                {
                    label: 'Email',
                    field: 'email',
                    align: 'center'
                },
                {
                    label: 'Phone',
                    field: 'phone',
                    align: 'center'
                },
                {
                    label: 'Type',
                    field: 'type',
                    align: 'center'
                },
                {
                    label: 'Team Name',
                    field: 'team_name',
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
            filter: '',
            modal: false,
            selected: [],
            userId: null
        }
    },

    created() {
        this.getUsers();
        this.getEmployees();
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
        getUsers() {
            axios.get('<?php echo base_url('get-users') ?>').then(res => {
                this.users = res.data;
            })
        },
        async saveUser() {
            if (this.selectedType == null) {
                alert('Select type');
                return;
            }

            if (this.checkMobile.test(this.user.phone) == false) {
                alert('Mobile is not valid');
                return;
            }

            if (this.selectedEmployee == null) {
                alert('Select an employee');
                return;
            }

            if (this.user.id == null && this.user.password != this.user.retype) {
                alert('Password and Re-type password not match');
                return;
            }

            this.user.type = this.selectedType;
            this.user.team_name = this.selectedTeam;
            this.user.employee_id = this.selectedEmployee.id;

            let url = "";

            if (this.user.id != null) {
                url = "/update-user";
            } else {
                url = "/add-user";
                delete this.user.id
            }

            await axios.post(url, this.user).then(res => {
                let r = res.data;
                alert(r.message);
                if (r.success) {
                    this.resetForm();
                    this.getUsers();
                }
            })
        },
        editUser(user) {
            let keys = Object.keys(this.user);
            keys.forEach(key => {
                this.user[key] = user[key];
            })
            this.selectedType = this.user['type'];
            this.selectedTeam = this.user['team_name'];
            this.selectedEmployee = {
                id: user.employee_id,
                name: user.employee,
                display_name: `${user.code} - ${user.employee}`
            }
            this.user.password = '';
        },
        deleteUser(id) {
            let deleteConfirm = confirm('Are you sure?');
            if (deleteConfirm == false) {
                return;
            }
            axios.post("/delete-user", {
                userId: id
            }).then(res => {
                let r = res.data;
                alert(r.message);
                if (r.success) {
                    this.getUsers();
                }
            })
        },
        resetForm() {
            let keys = Object.keys(this.user);
            keys.forEach(key => {
                if (typeof(this.user[key]) == 'string') {
                    this.user[key] = '';
                } else if (typeof(this.user[key]) == 'number') {
                    this.user[key] = 0;
                }
            })
            this.selectedType = null;
            this.selectedTeam = null;
            this.user.id = null;
            this.selectedEmployee = null;
        },
        getPermission(id, permissions) {
            this.modal = true;
            this.userId = id;
            this.selected = permissions != null ? JSON.parse(permissions) : []
        },
        async savePermissions() {
            let data = {
                userId: this.userId,
                permissions: this.selected
            }

            await axios.post('/user-access', data)
                .then(res => {
                    alert(res.data.message)
                    // this.modal = false;
                })
                .catch(err => {
                    console.log(err.response.data.message);
                })
        },
    }
})
</script>

<style>
.v-select {
    float: right;
    min-width: 100%;
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
</style>
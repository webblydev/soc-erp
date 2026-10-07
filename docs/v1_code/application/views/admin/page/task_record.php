<link rel="stylesheet" href="<?php echo base_url(); ?>assets/css/vue-modal.css">
<style>
.v-select {
    margin-top: -2.5px;
    float: right;
    min-width: 180px;
    margin-left: 5px;
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

#searchForm select {
    padding: 0;
    border-radius: 4px;
}

#searchForm .form-group {
    margin-right: 5px;
}

#searchForm * {
    font-size: 13px;
}

.record-table {
    width: 100%;
    border-collapse: collapse;
}

.record-table thead {
    background-color: #0097df;
    color: white;
}

.record-table th,
.record-table td {
    padding: 3px;
    border: 1px solid #454545;
}

.record-table th {
    text-align: center;
}
</style>
<div id="taskRecord">
    <div class="row" style="border-bottom:1px solid #ccc;padding-bottom:10px;margin-bottom:20px;">
        <div class="col-md-12">
            <form id="searchForm" class="form-inline" @submit.prevent="getTasks">
                <div class="form-group">
                    <label>Assigned person</label>
                    <v-select :options="employees" label="dispaly_name" v-model="selectedEmployee"></v-select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select class="form-control" v-model="filter.status">
                        <option value="">Select status</option>
                        <option value="p">Pending</option>
                        <option value="o">On Progress</option>
                        <option value="c">Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date from</label>
                    <input type="date" class="form-control" v-model="filter.dateFrom">
                </div>
                <div class="form-group">
                    <label>to</label>
                    <input type="date" class="form-control" v-model="filter.dateTo">
                </div>
                <div class="form-group" style="margin-top:-5px;">
                    <input type="submit" value="Search">
                </div>
            </form>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="table-responsive">
                <table class="record-table">
                    <thead>
                        <tr>
                            <th>Sl</th>
                            <th>File Number</th>
                            <th>Client Id</th>
                            <th>Entry Date</th>
                            <th>Project Name</th>
                            <th>Client Name</th>
                            <th>Project Type</th>
                            <th>Supervisor</th>
                            <th>Deadline</th>
                            <th>Assigned By</th>
                            <th>Assigned Person</th>
                            <th>Status</th>
                            <th>Completed Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody style="display:none;" v-bind:style="{display: tasks.length > 0 ? '' : 'none'}">
                        <tr v-for="(task, sl) in tasks"
                            v-bind:style="{ background: task.status == 'o' ? '#e6ffe6' : '', color: task.is_important == 'true' ? '#ff3300' : '' }">
                            <td>{{ sl + 1 }}</td>
                            <td>{{ task.file_number }}</td>
                            <td>{{ task.client_id }}</td>
                            <td>{{ task.entry_date }}</td>
                            <td>{{ task.project_name }}</td>
                            <td>{{ task.client_name }}</td>
                            <td>{{ task.type }}</td>
                            <td>{{ task.support_person }}</td>
                            <td>{{ task.deadline }}</td>
                            <td>{{ task.assign_by_person }}</td>
                            <td>{{ task.assigned_person }}</td>
                            <td>{{ task.status_text }}</td>
                            <td><template v-if="task.status == 'c'">{{ task.completed_date }}</template></td>
                            <td style="text-align:center;">
                                <a href="" title="View Details" @click.prevent="selectTask(task);showModal = true"><i
                                        class="fa fa-list"></i></a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <modal v-if="showModal" @close="getTasks();showModal = false" :task="task" :viewas="'admin'"></modal>
</div>

<script src="<?php echo base_url(); ?>assets/js/vue/vue.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script src="<?php echo base_url(); ?>assets/js/vue/components/task-component.js"></script>
<script>
Vue.component('v-select', VueSelect.VueSelect);
new Vue({
    el: '#taskRecord',
    data: {
        filter: {
            status: '',
            dateFrom: moment().format('YYYY-MM-DD'),
            dateTo: moment().format('YYYY-MM-DD'),
            employeeId: ''
        },
        tasks: [],
        showModal: false,
        task: null,
        employees: [],
        selectedEmployee: null
    },
    created() {
        this.getEmployees();
        this.getTasks();
    },
    methods: {
        getEmployees() {
            axios.get('/get-employees').then(res => {
                this.employees = res.data.map(item => {
                    item.dispaly_name = `${item.code} - ${item.name}`
                    return item;
                });
            })
        },
        selectTask(task) {
            this.task = task;
        },
        getTasks() {
            if (this.filter.status == '') {
                this.filter.status = ['p', 'o', 'c'];
            }
            if (this.selectedEmployee != null) {
                this.filter.employeeId = this.selectedEmployee.id;
            } else {
                this.filter.employeeId = '';
            }
            axios.post('/get-tasks', this.filter)
                .then(res => {
                    this.tasks = res.data;
                })
        }
    }
})
</script>
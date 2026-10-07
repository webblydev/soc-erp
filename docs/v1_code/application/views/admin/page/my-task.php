<style>
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
<div id="myTasks">
    <div class="row">
        <div class="col-md-12">
            <div class="table-responsive">
                <table class="record-table">
                    <thead>
                        <tr>
                            <th>Sl</th>
                            <th>Client Id</th>
                            <th>File Number</th>
                            <th>Entry Date</th>
                            <th>Project Name</th>
                            <th>Client Name</th>
                            <th>Project Type</th>
                            <th>Supervisor</th>
                            <th>Deadline</th>
                            <th>Assigned By</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody style="display:none;" v-bind:style="{display: tasks.length > 0 ? '' : 'none'}">
                        <tr v-for="(task, sl) in tasks" v-bind:style="{ background: task.status == 'o' ? '#e6ffe6' : '', color: task.is_important == 'true' ? '#ff3300' : '' }">
                            <td>{{ sl + 1 }}</td>
                            <td>{{ task.client_id }}</td>
                            <td>{{ task.file_number }}</td>
                            <td>{{ task.entry_date }}</td>
                            <td>{{ task.project_name }}</td>
                            <td>{{ task.client_name }}</td>
                            <td>{{ task.type }}</td>
                            <td>{{ task.support_person }}</td>
                            <td>{{ task.deadline }}</td>
                            <td>{{ task.assign_by_person }}</td>
                            <td>{{ task.status_text }}</td>
                            <td style="text-align:center;">
                                <a href="" title="Done" @click.prevent="selectTask(task);showModal = true"><i class="fa fa-check-square-o"></i></a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <modal v-if="showModal" @close="getTasks();showModal = false" :task="task" :viewas="'user'"></modal>
</div>

<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/components/task-component.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>
<script>
    new Vue({
        el: '#myTasks',
        data: {
            tasks: [],
            showModal: false,
            task: null
        },
        created() {
            this.getTasks();
        },
        methods: {
            selectTask(task){
                this.task = task;
            },
            getTasks() {
                let condition = {
                    status: ['p', 'o'],
                    forAssignedPerson: true
                }
                axios.post('/get-tasks', condition).then(res => {
                    this.tasks = res.data;
                })
            }
        }
    })
</script>
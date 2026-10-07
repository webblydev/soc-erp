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
<div id="archivedTasks">
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
                            <th>Completed By</th>
                            <th>Completed Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody style="display:none;" v-bind:style="{display: tasks.length > 0 ? '' : 'none'}">
                        <tr v-for="(task, sl) in tasks" v-bind:style="{ color: task.is_important == 'true' ? '#ff3300' : '' }">
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
                            <td>{{ task.assigned_person }}</td>
                            <td>{{ task.completed_date }}</td>
                            <td style="text-align:center;">
                                <a href="" title="Change to pending" @click.prevent="changeStatus(task.id, 'p');"><i class="fa fa-reply"></i></a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>assets/js/vue/vue.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>

<script>
    new Vue({
        el: '#archivedTasks',
        data: {
            tasks: []
        },
        created() {
            this.getTasks();
        },
        methods: {
            getTasks() {
                axios.post('/get-tasks', {status: 'a'}).then(res => {
                    this.tasks = res.data;
                })
            },
            changeStatus(taskId, status){
                if(confirm('Are you sure to change ?')) {
                    axios.post('/update-task-status', {id: taskId, status: status})
                    .then(res => {
                        let r = res.data;
                        alert(r.message);
                        if(r.success){
                            this.getTasks();
                        }
                    })
                }
            }
        }
    })
</script>

<div id="root">
    <?php $type = $this->session->userdata('type'); ?>
    <div class="row">
        <div class="col-md-12 col-xs-12">
            <div class="col-md-1"></div>
            <div class="col-md-10">
                <!-- Header Logo -->
                <div class="col-md-12" style="border-bottom: 1px solid #ccc;margin-bottom:10px">
                    <h1 style="text-align: center;color: darkslateblue;font-size: 40px;">Customer Relationship System
                    </h1>
                </div>

                <div class="col-md-2 section3">
                    <div class="col-md-12 section12" style="background: #009688">
                        <a href="">
                            <div class="logo">
                                <?php
                                $countClient = $this->db->query('select * from tbl_client')->num_rows();
    echo $countClient;
    ?>
                            </div>
                            <div class="textModule">
                                Total Client
                            </div>
                        </a>
                    </div>
                </div>

                <div class="col-md-2 section3">
                    <div class="col-md-12 section12" style="background: #27b086;border: 1px solid #209a8f;">
                        <a href="">
                            <div class="logo">
                                <?php
        $countEmployee = $this->db->query('select * from tbl_employee')->num_rows();
    echo $countEmployee;
    ?>
                            </div>
                            <div class="textModule">
                                Total Employee
                            </div>
                        </a>
                    </div>
                </div>

                <div class="col-md-2 section3">
                    <div class="col-md-12 section12" style="background: #4CAF50;border: 1px solid #4CAF50;">
                        <a href="">
                            <div class="logo">
                                <?php
        $totalUser = $this->db->query('select * from tbl_user')->num_rows();
    echo $totalUser;
    ?>
                            </div>
                            <div class="textModule">
                                Total Users
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-md-2 section3">
                    <div class="col-md-12 section12" style="background: #FFEB3B;border: 1px solid #FFEB3B;">
                        <a href="">
                            <div class="logo">
                                <?php
        $countTask = $this->db->query('select * from tbl_task')->num_rows();
    echo $countTask;
    ?>
                            </div>
                            <div class="textModule">
                                Total Task
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-md-2 section3">
                    <div class="col-md-12 section12" style="background: #F44336;border: 1px solid #F44336;">
                        <a href="">
                            <div class="logo">
                                <?php
        $pendingTask = $this->db->query("select * from tbl_task where status = 'p'")->num_rows();
    echo $pendingTask;
    ?>
                            </div>
                            <div class="textModule" style="font-size: 15px;">
                                Pending Task
                            </div>
                        </a>
                    </div>
                </div>
                <div class="col-md-2 section3">
                    <div class="col-md-12 section12" style="background: #8BC34A;border: 1px solid #CDDC39;">
                        <a href="">
                            <div class="logo">
                                <?php
        $completeTask = $this->db->query("select * from tbl_task where status = 'c'")->num_rows();
    echo $completeTask;
    ?>
                            </div>
                            <div class="textModule">
                                Compteted Task
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div><!-- /.col -->
    </div><!-- /.row -->

    <div class="row">
        <hr style="margin-bottom: 0px;border-top: 1px solid #fff;">
        <div class="col-md-12">
            <div class="row" style="background: #ff8e00;padding:8px;margin:0px">
                <form @submit.prevent="getSearch">
                    <div class="form-group col-md-3 col-md-offset-3">
                        <label class="col-md-3" style="margin-top: 3px;">Reminder</label>
                        <div class="col-md-7">
                            <input type="date" class="form-control" v-model="search">
                        </div>
                        <div class="col-md-2">
                            <input type="submit" class="btn btn-sm btn-info" value="search">
                        </div>
                    </div>
                </form>
            </div>
            <div class="row" style="display:none"
                :style="{display: beforeClients.length > 0 || afterClients.length > 0 ? '' : 'none'}">
                <div class="col-md-6">
                    <h3 class="text-center" style="font-size: 18px;font-weight: 500;margin: 0;padding: 8px;">Before
                        Reminder Date</h3>
                    <table class="record-table">
                        <thead>
                            <th>Reminder</th>
                            <th>Client Name</th>
                            <th>Phone</th>
                            <th>Area</th>
                            <th>Action</th>
                        </thead>
                        <tbody>
                            <tr v-for="client in beforeClients">
                                <td>{{ client.reminder }}</td>
                                <td>{{ client.client_name }}</td>
                                <td>{{ client.phone }}</td>
                                <td>{{ client.name }}</td>
                                <td style="text-align: center;">
                                    <button type="button" class="button edit" data-toggle="modal" data-target="#myModal"
                                        @click="service = client">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                    <!-- <a href="" :href="`/client-entry/${client.id}`" class="btn btn-xs"><i class="fa fa-pencil"></i></a> -->
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-md-6">
                    <h3 class="text-center" style="font-size: 18px;font-weight: 500;margin: 0;padding: 8px;">After
                        Reminder Date</h3>
                    <table class="record-table">
                        <thead>
                            <th>Reminder</th>
                            <th>Client Name</th>
                            <th>Phone</th>
                            <th>Area</th>
                            <th>Action</th>
                        </thead>
                        <tbody>
                            <tr v-for="client in afterClients">
                                <td>{{ client.reminder }}</td>
                                <td>{{ client.client_name }}</td>
                                <td>{{ client.phone }}</td>
                                <td>{{ client.name }}</td>
                                <td style="text-align: center;">
                                    <button type="button" class="button edit" data-toggle="modal" data-target="#myModal"
                                        @click="service = client">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                    <!-- <a href="" :href="`/client-entry/${client.id}`" class="btn btn-xs"><i class="fa fa-pencil"></i></a> -->
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- modal -->
    <div id="myModal" class="modal fade" role="dialog">
        <div class="modal-dialog">
            <!-- Modal content-->
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Client Information</h4>
                </div>

                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <table class="client-view" width="100%">
                                <tr>
                                    <td width="20%"><strong>Client Id</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.client_id }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Client Name</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.client_name }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Org. Name</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.org_name }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Phone</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.phone }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Org. Mobile</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.org_mobile }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Whatsapp Num.</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.w_number }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Aare</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.name }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Address</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.address }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>E-mail</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.email }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Service </strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <span v-for="(req, ind) in service.requirements">
                                            {{ req.soft_name }} {{ service.requirements.length-1 == ind ? ' ' : ' , ' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Sold Status</strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <span v-for="sold in serviceSold(service.sold)">
                                            <span>{{ sold }}</span>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Customer Level</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.level }} Level</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Reminder Date</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ service.reminder }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Source</strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <span v-if="service.source == 'L'">Leaflet</span>
                                        <span v-else-if="service.source == 'FF'">Face To Face</span>
                                        <span v-else-if="service.source == 'FB'">Facebook</span>
                                        <span v-else-if="service.source == 'G'">Google</span>
                                        <span v-else-if="service.source == 'Y'">Youtube</span>
                                        <span v-else>Friend & References</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Comment</strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <p style="white-space: pre-line">{{ service.comment }}</p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- modal -->

</div><!-- /.main-content -->

<script src="<?php echo base_url(); ?>assets/js/vue/vue.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script>
const app = new Vue({
    el: '#root',
    data: {
        search: moment().format('YYYY-MM-DD'),
        beforeClients: [],
        afterClients: [],
        service: {}
    },
    created() {
        this.getSearch();
    },
    methods: {
        getSearch() {
            if (this.search == '') {
                alert('Select date');
                return;
            }
            axios.post('/get-reminder', {
                    reminder: this.search
                })
                .then(res => {
                    this.beforeClients = res.data.beforeClients;
                    this.afterClients = res.data.afterClients;
                })
        },
        serviceSold(sold = '') {
            let arr = sold.split(",");
            return arr;
        },
    }
})
</script>
<style>
.record-table {
    width: 100%;
    margin: 0px 10px;
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
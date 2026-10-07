<div id="sold-client">
    <div class="row">
        <div class="col-sm-12 form-inline">
            <div class="form-group">
                <label for="filter" class="sr-only">Filter</label>
                <input type="text" class="form-control" v-model="filter" placeholder="Filter">
            </div>
        </div>
        <div class="col-md-12">
            <div class="table-responsive">
                <datatable :columns="columns" :data="clients" :filter-by="filter" style="margin-bottom: 5px;">
                    <template scope="{ row }">
                        <tr>
                            <td>{{ row.add_time | dateOnly('DD-MM-YYYY') }}</td>
                            <td>{{ row.client_id }}</td>
                            <td>{{ row.client_name }}</td>
                            <td>{{ row.org_name }}</td>
                            <td>{{ row.name }}</td>
                            <td>{{ row.phone }}</td>
                            <td>{{ row.org_mobile }}</td>
                            <td>
                                <span v-for="(req, ind) in row.requirements">
                                    {{ req.soft_name }} {{ row.requirements.length-1 == ind ? ' ' : ' , ' }}
                                </span>
                            </td>
                            <td>{{ row.officer }}</td>
                            <td>
                                <button type="button" class="button edit" data-toggle="modal" data-target="#myModal"
                                    @click="client = row">
                                    View
                                </button>
                                <a href="" :href="`/client-entry/${row.id}`" class="btn btn-xs"><i
                                        class="fa fa-pencil"></i></a>
                            </td>
                        </tr>
                    </template>
                </datatable>
                <datatable-pager v-model="page" type="abbreviated" :per-page="per_page" style="margin-bottom: 50px;">
                </datatable-pager>
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
                                    <td>{{ client.client_id }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Client Name</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ client.client_name }}</td>
                                </tr>

                                <tr>
                                    <td width="20%"><strong>Client Type</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ client.client_type_name }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Org. Name</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ client.org_name }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Phone</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ client.phone }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Org. Mobile</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ client.org_mobile }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Whatsapp Num.</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ client.w_number }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Aare</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ client.name }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Address</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ client.address }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>E-mail</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ client.email }}</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Service </strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <span v-for="(req, ind) in client.requirements">
                                            {{ req.soft_name }} {{ client.requirements.length-1 == ind ? ' ' : ' , ' }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Sold Status</strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <span v-for="sold in serviceSold(client.sold)">
                                            <span>{{ sold }}</span>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Customer Level</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ client.level }} Level</td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Reminder Date</strong></td>
                                    <td width="10">:</td>
                                    <td>{{ client.reminder }}</td>
                                </tr>

                                <tr>
                                    <td width="20%"><strong>Source</strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <span v-if="client.source == 'L'">Leaflet</span>
                                        <span v-else-if="client.source == 'FF'">Face To Face</span>
                                        <span v-else-if="client.source == 'FB'">Facebook</span>
                                        <span v-else-if="client.source == 'G'">Google</span>
                                        <span v-else-if="client.source == 'Y'">Youtube</span>
                                        <span v-else>Friend & References</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td width="20%"><strong>Comment</strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <p style="white-space: pre-line">{{ client.comment }}</p>
                                    </td>
                                </tr>

                                <tr>
                                    <td width="20%"><strong>Note</strong></td>
                                    <td width="10">:</td>
                                    <td>
                                        <p style="white-space: pre-line">{{ client.note }}</p>
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



<script src="<?php echo base_url(); ?>assets/js/vue/vue.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/axios.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vuejs-datatable.js"></script>
<script src="<?php echo base_url(); ?>assets/js/vue/vue-select.min.js"></script>
<script src="<?php echo base_url(); ?>assets/js/moment.min.js"></script>

<script>
Vue.component('v-select', VueSelect.VueSelect);
new Vue({
    el: '#sold-client',
    data() {
        return {
            clients: [],
            client: {},
            columns: [{
                    label: 'Date',
                    field: 'add_time',
                    align: 'center',
                    filterable: false
                },
                {
                    label: 'Client Id',
                    field: 'client_id',
                    align: 'center'
                },
                {
                    label: 'Client Name',
                    field: 'client_name',
                    align: 'center'
                },
                {
                    label: 'Org. Name',
                    field: 'org_name',
                    align: 'center'
                },
                {
                    label: 'Area',
                    field: 'name',
                    align: 'center'
                },
                {
                    label: 'Phone',
                    field: 'phone',
                    align: 'center'
                },
                {
                    label: 'Org. Mobile',
                    field: 'org_mobile',
                    align: 'center'
                },
                {
                    label: 'Service',
                    field: 'requirement',
                    align: 'center',
                    filterable: false
                },
                {
                    label: 'Off. name',
                    field: 'add_by',
                    align: 'center',
                    filterable: false
                },
                {
                    label: 'Action',
                    field: 'action',
                    align: 'center',
                    filterable: false
                },
            ],
            page: 1,
            per_page: 50,
            filter: ''
        }
    },

    filters: {
        dateOnly(datetime, format) {
            return moment(datetime).format(format);
        }
    },

    created() {
        this.getAreas();
        this.getSoldClient();
    },

    methods: {
        getAreas() {
            axios.get("/get-areas").then(res => {
                this.areas = res.data;
            })
        },
        getSoldClient() {

            axios.post("/get-client").then(res => {
                this.clients = res.data.filter((e) => {
                    return e.sold != "";
                });
            })
        },

        changeStatus(id) {
            let statusConfirm = confirm('Are you sure to change status?');
            if (statusConfirm == false) {
                return;
            }
            axios.post("/change-status", {
                clientId: id
            }).then(res => {
                let r = res.data;
                alert(r.message);
                if (r.success) {
                    this.getSoldClient();
                }
            })
        },

        serviceSold(sold = '') {
            let arr = sold.split(",");
            return arr;
        },

    }
})
</script>
<style type="text/css">
.v-select {
    margin-bottom: 5px;
}

.v-select.open .dropdown-toggle {
    border-bottom: 1px solid #ccc;
}

.v-select .dropdown-toggle {
    padding: 0px;
    height: 28px;
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

.client-view tr td {
    padding: 5px;
}
</style>
</style>